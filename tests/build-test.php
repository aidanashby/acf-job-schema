<?php
/**
 * Plain-PHP checks for includes/build.php. No WordPress, no framework.
 *
 * Run: php tests/build-test.php   (exit code 0 = all passed)
 */

define( 'ABSPATH', __DIR__ . '/' ); // build.php exits without it.
require __DIR__ . '/../acf-job-schema/includes/build.php';

use function AJS\build_job_posting;
use function AJS\check_mapping;
use function AJS\clean_description;

$failures = 0;
$checks   = 0;

function check( bool $ok, string $label ): void {
	global $failures, $checks;
	++$checks;
	if ( ! $ok ) {
		++$failures;
		echo "FAIL: $label\n";
	}
}

function same( $actual, $expected, string $label ): void {
	$ok = $actual === $expected;
	check( $ok, $label );
	if ( ! $ok ) {
		echo '  expected: ' . json_encode( $expected, JSON_UNESCAPED_SLASHES ) . "\n";
		echo '  actual:   ' . json_encode( $actual, JSON_UNESCAPED_SLASHES ) . "\n";
	}
}

$tz  = new DateTimeZone( 'Europe/London' );
$now = ( new DateTimeImmutable( '2026-11-19 12:00:00', $tz ) )->getTimestamp();
$ctx = array(
	'timezone'        => $tz,
	'now'             => $now,
	'organization'    => array(
		'name' => 'Example Charity',
		'url'  => 'https://example.org/',
		'logo' => 'https://example.org/logo.png',
	),
	'currency'        => 'GBP',
	'default_country' => 'GB',
	'remote_country'  => 'United Kingdom',
);

/** A complete on-site job. Tests change one thing at a time from this. */
function base(): array {
	return array(
		'title'           => 'Communications Officer',
		'description'     => '<p>Write and plan our communications.</p>',
		'date_posted'     => '20261101',
		'valid_through'   => '20261130',
		'employment_type' => 'FULL_TIME',
		'salary_min'      => '24000',
		'salary_max'      => '26000',
		'salary_unit'     => 'YEAR',
		'remote'          => false,
		'street'          => '1 High Street',
		'locality'        => 'Bristol',
		'region'          => '',
		'postcode'        => 'BS1 1AA',
		'country'         => '',
		'map'             => null,
		'identifier'      => 'REF-12',
	);
}

function with( array $changes ): array {
	return array_merge( base(), $changes );
}

// --- Complete job: exact output ---------------------------------------------------------
$r = build_job_posting( base(), $ctx );
same( $r['status'], 'valid', 'complete job is valid' );
same( $r['missing'], array(), 'complete job has nothing missing' );
same( $r['warnings'], array(), 'complete job has no warnings' );
same(
	$r['schema'],
	array(
		'@type'              => 'JobPosting',
		'title'              => 'Communications Officer',
		'description'        => '<p>Write and plan our communications.</p>',
		'datePosted'         => '2026-11-01',
		'validThrough'       => '2026-11-30T23:59:59+00:00',
		'hiringOrganization' => array(
			'@type'  => 'Organization',
			'name'   => 'Example Charity',
			'sameAs' => 'https://example.org/',
			'logo'   => 'https://example.org/logo.png',
		),
		'jobLocation'        => array(
			'@type'   => 'Place',
			'address' => array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => '1 High Street',
				'addressLocality' => 'Bristol',
				'postalCode'      => 'BS1 1AA',
				'addressCountry'  => 'GB',
			),
		),
		'employmentType'     => 'FULL_TIME',
		'baseSalary'         => array(
			'@type'    => 'MonetaryAmount',
			'currency' => 'GBP',
			'value'    => array(
				'@type'    => 'QuantitativeValue',
				'minValue' => 24000,
				'maxValue' => 26000,
				'unitText' => 'YEAR',
			),
		),
		'identifier'         => array(
			'@type' => 'PropertyValue',
			'name'  => 'Example Charity',
			'value' => 'REF-12',
		),
	),
	'complete job schema'
);
same( $r['valid_through'], ( new DateTimeImmutable( '2026-11-30 23:59:59', $tz ) )->getTimestamp(), 'valid_through timestamp returned' );

// --- Dates -------------------------------------------------------------------------------
$r = build_job_posting( with( array( 'valid_through' => '20270630' ) ), $ctx );
same( $r['schema']['validThrough'], '2027-06-30T23:59:59+01:00', 'date-only closing date in summer time uses +01:00' );

$r = build_job_posting( with( array( 'valid_through' => '2026-12-01 09:30:00' ) ), $ctx );
same( $r['schema']['validThrough'], '2026-12-01T09:30:00+00:00', 'date-time picker closing date keeps its time' );

$r = build_job_posting( with( array( 'date_posted' => '2026-11-01 08:15:00' ) ), $ctx );
same( $r['schema']['datePosted'], '2026-11-01T08:15:00+00:00', 'date-time posted date (core publish date) outputs ISO datetime' );

$r = build_job_posting( with( array( 'valid_through' => '' ) ), $ctx );
same( $r['status'], 'valid', 'no closing date is still valid' );
check( ! isset( $r['schema']['validThrough'] ), 'no closing date: validThrough left out' );
same( $r['valid_through'], null, 'no closing date: no timestamp' );
check( isset( $r['warnings']['no_closing_date'] ), 'no closing date: warning' );

$r = build_job_posting( with( array( 'valid_through' => 'next week' ) ), $ctx );
check( ! isset( $r['schema']['validThrough'] ), 'unreadable closing date left out' );
check( isset( $r['warnings']['bad_closing_date'] ), 'unreadable closing date: warning' );

$r = build_job_posting( with( array( 'date_posted' => '' ) ), $ctx );
same( $r['status'], 'invalid', 'no posted date is invalid' );
same( $r['missing'], array( 'date_posted' ), 'no posted date: listed as missing' );
same( $r['schema'], null, 'invalid job: no schema' );

// --- Closed ------------------------------------------------------------------------------
$r = build_job_posting( with( array( 'valid_through' => '20261118' ) ), $ctx );
same( $r['status'], 'closed', 'closing date passed: closed' );
same( $r['schema'], null, 'closed job: no schema' );

$r = build_job_posting( with( array( 'valid_through' => '20261119' ) ), $ctx );
same( $r['status'], 'valid', 'closes today (date only): still open until 23:59:59' );

// --- Title and description ---------------------------------------------------------------
$r = build_job_posting( with( array( 'title' => 'Finance &amp; Admin <em>Officer</em>' ) ), $ctx );
same( $r['schema']['title'], 'Finance & Admin Officer', 'title: tags stripped, entities decoded' );

$r = build_job_posting( with( array( 'title' => '' ) ), $ctx );
same( $r['missing'], array( 'title' ), 'empty title: missing' );

$r = build_job_posting( with( array( 'description' => '' ) ), $ctx );
same( $r['missing'], array( 'description' ), 'empty description: missing' );

$r = build_job_posting( with( array( 'description' => '<p> </p><img src="x.png">' ) ), $ctx );
same( $r['missing'], array( 'description' ), 'description with no text: missing' );

$r = build_job_posting( with( array( 'description' => '<p>Communications Officer</p>' ) ), $ctx );
same( $r['missing'], array( 'description' ), 'description equal to title: missing' );

same(
	clean_description( '<h2 class="x">About the role</h2><p style="color:red">Plan <strong>our</strong> posts.<br />Daily.</p><ol><li>One</li></ol><script>alert(1)</script><img src="a.png"><p></p>' ),
	'<p>About the role</p><p>Plan our posts.<br>Daily.</p><ul><li>One</li></ul>',
	'description cleaning: headings to p, ol to ul, attributes, unknown tags, script content and empty p removed'
);
same( clean_description( "Line one\n\nLine two" ), 'Line one<br><br>Line two', 'plain textarea newlines become br' );

// --- Organisation ------------------------------------------------------------------------
$no_org                         = $ctx;
$no_org['organization']['name'] = '';
$r                              = build_job_posting( base(), $no_org );
same( $r['missing'], array( 'hiring_organization' ), 'no organisation name: missing' );

$bare_org                 = $ctx;
$bare_org['organization'] = array( 'name' => 'Example Charity', 'url' => '', 'logo' => '' );
$r                        = build_job_posting( base(), $bare_org );
same( $r['schema']['hiringOrganization'], array( '@type' => 'Organization', 'name' => 'Example Charity' ), 'organisation: empty url and logo left out' );

// --- Location ----------------------------------------------------------------------------
$r = build_job_posting( with( array( 'street' => '', 'locality' => '', 'postcode' => '' ) ), $ctx );
same( $r['missing'], array( 'location' ), 'on-site with no town or postcode: location missing' );

$r = build_job_posting( with( array( 'street' => '', 'postcode' => '' ) ), $ctx );
same( $r['schema']['jobLocation']['address'], array( '@type' => 'PostalAddress', 'addressLocality' => 'Bristol', 'addressCountry' => 'GB' ), 'town only is enough' );

$r = build_job_posting( with( array( 'country' => 'IE' ) ), $ctx );
same( $r['schema']['jobLocation']['address']['addressCountry'], 'IE', 'country field overrides default' );

$r = build_job_posting( with( array( 'remote' => true ) ), $ctx );
same( $r['schema']['jobLocationType'], 'TELECOMMUTE', 'remote: TELECOMMUTE' );
same( $r['schema']['applicantLocationRequirements'], array( '@type' => 'Country', 'name' => 'United Kingdom' ), 'remote: applicant country' );
check( ! isset( $r['schema']['jobLocation'] ), 'remote: no jobLocation' );

$r = build_job_posting( with( array( 'remote' => true, 'street' => '', 'locality' => '', 'postcode' => '' ) ), $ctx );
same( $r['status'], 'valid', 'remote with no address is valid' );

$map = array(
	'street_number' => '10',
	'street_name'   => 'Gill Avenue',
	'city'          => 'Bristol',
	'state'         => 'England',
	'post_code'     => 'BS16 2QQ',
	'country_short' => 'GB',
);
$r   = build_job_posting( with( array( 'street' => '', 'locality' => '', 'postcode' => '', 'map' => $map ) ), $ctx );
same(
	$r['schema']['jobLocation']['address'],
	array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => '10 Gill Avenue',
		'addressLocality' => 'Bristol',
		'addressRegion'   => 'England',
		'postalCode'      => 'BS16 2QQ',
		'addressCountry'  => 'GB',
	),
	'map field fills the address'
);
$r = build_job_posting( with( array( 'map' => $map ) ), $ctx );
same( $r['schema']['jobLocation']['address']['streetAddress'], '1 High Street', 'address fields override the map' );

// --- Employment type ---------------------------------------------------------------------
$r = build_job_posting( with( array( 'employment_type' => array( 'PART_TIME', 'TEMPORARY' ) ) ), $ctx );
same( $r['schema']['employmentType'], array( 'PART_TIME', 'TEMPORARY' ), 'several employment types: array' );

$r = build_job_posting( with( array( 'employment_type' => array( 'PART_TIME', 'Casual' ) ) ), $ctx );
same( $r['schema']['employmentType'], 'PART_TIME', 'invalid employment type dropped, single value left as string' );
check( isset( $r['warnings']['bad_employment_type'] ), 'invalid employment type: warning' );

$r = build_job_posting( with( array( 'employment_type' => 'full_time' ) ), $ctx );
check( ! isset( $r['schema']['employmentType'] ), 'employment type is case-sensitive' );

$r = build_job_posting( with( array( 'employment_type' => '' ) ), $ctx );
check( ! isset( $r['schema']['employmentType'] ) && ! isset( $r['warnings']['bad_employment_type'] ), 'empty employment type: left out, no warning' );

// --- Salary ------------------------------------------------------------------------------
$r = build_job_posting( with( array( 'salary_max' => '' ) ), $ctx );
same( $r['schema']['baseSalary']['value'], array( '@type' => 'QuantitativeValue', 'value' => 24000, 'unitText' => 'YEAR' ), 'single salary: value' );

$r = build_job_posting( with( array( 'salary_min' => '£24,000', 'salary_max' => '£26,500.50' ) ), $ctx );
same( array( $r['schema']['baseSalary']['value']['minValue'], $r['schema']['baseSalary']['value']['maxValue'] ), array( 24000, 26500.5 ), 'pound sign and commas stripped' );

$r = build_job_posting( with( array( 'salary_min' => '12.60', 'salary_max' => '', 'salary_unit' => 'HOUR' ) ), $ctx );
same( $r['schema']['baseSalary']['value'], array( '@type' => 'QuantitativeValue', 'value' => 12.6, 'unitText' => 'HOUR' ), 'hourly rate' );

$r = build_job_posting( with( array( 'salary_unit' => '' ) ), $ctx );
check( ! isset( $r['schema']['baseSalary'] ), 'salary without unit: left out' );
check( isset( $r['warnings']['salary_incomplete'] ), 'salary without unit: warning' );

$r = build_job_posting( with( array( 'salary_unit' => 'ANNUM' ) ), $ctx );
check( ! isset( $r['schema']['baseSalary'] ) && isset( $r['warnings']['salary_incomplete'] ), 'invalid salary unit: left out with warning' );

$r = build_job_posting( with( array( 'salary_min' => '24k' ) ), $ctx );
check( ! isset( $r['schema']['baseSalary'] ) && isset( $r['warnings']['salary_incomplete'] ), 'non-numeric salary: left out with warning' );

$r = build_job_posting( with( array( 'salary_min' => '30000', 'salary_max' => '26000' ) ), $ctx );
check( ! isset( $r['schema']['baseSalary'] ) && isset( $r['warnings']['salary_incomplete'] ), 'maximum below minimum: left out with warning' );

$r = build_job_posting( with( array( 'salary_min' => '', 'salary_max' => '', 'salary_unit' => '' ) ), $ctx );
check( ! isset( $r['schema']['baseSalary'] ) && ! isset( $r['warnings']['salary_incomplete'] ), 'no salary at all: left out, no warning' );

// --- Identifier --------------------------------------------------------------------------
$r = build_job_posting( with( array( 'identifier' => '' ) ), $ctx );
check( ! isset( $r['schema']['identifier'] ), 'no reference: identifier left out' );

// --- Several missing at once ---------------------------------------------------------------
$r = build_job_posting( with( array( 'title' => '', 'description' => '', 'locality' => '', 'postcode' => '' ) ), $ctx );
same( $r['missing'], array( 'title', 'description', 'location' ), 'several missing, in property order' );

// --- check_mapping -------------------------------------------------------------------------
function field( string $type, array $choices = array() ): array {
	return array( 'label' => ucfirst( $type ), 'type' => $type, 'choices' => $choices );
}

$full = array(
	'description'     => field( 'wysiwyg' ),
	'valid_through'   => field( 'date_picker' ),
	'locality'        => field( 'text' ),
	'employment_type' => field( 'select', array( 'FULL_TIME' => 'Full time', 'PART_TIME' => 'Part time' ) ),
	'salary_min'      => field( 'number' ),
	'salary_unit'     => field( 'select', array( 'YEAR' => 'Per year' ) ),
);
$m    = check_mapping( $full, array() );
same( $m, array( 'errors' => array(), 'warnings' => array() ), 'complete mapping: no errors or warnings' );

$m = check_mapping( array_diff_key( $full, array( 'description' => 1 ) ), array() );
check( isset( $m['errors']['no_description'] ), 'mapping without description: error' );

$m = check_mapping( array_diff_key( $full, array( 'locality' => 1 ) ), array() );
check( isset( $m['errors']['no_location'] ), 'mapping without town, postcode, map or remote: error' );

foreach ( array( 'postcode' => 'text', 'map' => 'google_map', 'remote' => 'true_false' ) as $prop => $type ) {
	$m = check_mapping( array_merge( array_diff_key( $full, array( 'locality' => 1 ) ), array( $prop => field( $type ) ) ), array() );
	check( ! isset( $m['errors']['no_location'] ), "mapping with $prop instead of town: no location error" );
}

$m = check_mapping( $full, array( 'description' => array( 'Summary', 'Full description' ) ) );
check( isset( $m['errors']['duplicate'] ), 'two fields on one property: error' );
check( false !== strpos( $m['errors']['duplicate'], 'Summary' ), 'duplicate error names the fields' );

$m = check_mapping( array_diff_key( $full, array( 'valid_through' => 1 ) ), array() );
check( isset( $m['warnings']['no_closing_date'] ) && array() === $m['errors'], 'mapping without closing date: warning only' );

$m = check_mapping( array_merge( $full, array( 'employment_type' => field( 'select', array( 'FULL_TIME' => 'Full time', 'Casual' => 'Casual' ) ) ) ), array() );
check( isset( $m['warnings']['bad_choices_employment_type'] ) && false !== strpos( $m['warnings']['bad_choices_employment_type'], 'Casual' ), 'invalid employment type choice named in warning' );

$m = check_mapping( array_merge( $full, array( 'salary_unit' => field( 'select', array( 'annual' => 'Per year' ) ) ) ), array() );
check( isset( $m['warnings']['bad_choices_salary_unit'] ), 'invalid salary unit choice: warning' );

$m = check_mapping( array_diff_key( $full, array( 'salary_unit' => 1 ) ), array() );
check( isset( $m['warnings']['salary_incomplete'] ), 'salary figure without unit mapped: warning' );

$m = check_mapping( array_diff_key( $full, array( 'salary_min' => 1 ) ), array() );
check( isset( $m['warnings']['salary_incomplete'] ), 'salary unit without figure mapped: warning' );

echo 0 === $failures ? "All $checks checks passed.\n" : "$failures of $checks checks failed.\n";
exit( $failures ? 1 : 0 );
