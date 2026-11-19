<?php
/**
 * Creates the test field groups, the ACF-registered post type and one post per case, replacing any
 * earlier copies. Prints a manifest line ("MANIFEST:" + JSON) that tests/check-front-end.py reads.
 *
 * Run by tests/run.sh through `studio wp eval-file`. No namespaces or named functions: eval-file
 * runs the file inside a function.
 */

require ABSPATH . 'ajs-teardown.php'; // tests/run.sh copies teardown.php there. Removes earlier fixtures first.

$ajs_field = function ( string $key, string $label, string $name, string $type, string $property = '', array $extra = array() ): array {
	return array_merge(
		array(
			'key'                 => $key,
			'label'               => $label,
			'name'                => $name,
			'type'                => $type,
			'job_schema_property' => $property,
		),
		$extra
	);
};

// ACF-registered post type, the second registration route.
acf_import_internal_post_type(
	array(
		'key'       => 'post_type_ajs_fixture',
		'title'     => 'ACF test jobs',
		'post_type' => 'job_acf',
		'active'    => true,
		'public'    => true,
		'labels'    => array( 'name' => 'ACF test jobs', 'singular_name' => 'ACF test job' ),
		'supports'  => array( 'title', 'editor' ),
	),
	'acf-post-type'
);
// ACF registers its post types on init, which has already run in this request.
register_post_type( 'job_acf', array( 'label' => 'ACF test jobs', 'public' => true ) );

acf_import_field_group(
	array(
		'key'      => 'group_ajs_fixture_jobs',
		'title'    => 'AJS test: job fields',
		'location' => array(
			array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'job_test' ) ),
			array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'job_acf' ) ),
		),
		'fields'   => array(
			$ajs_field( 'field_ajs_desc', 'Description', 'job_description', 'wysiwyg', 'description' ),
			// Display formats differ from storage on purpose: the plugin must read the stored Ymd value.
			$ajs_field( 'field_ajs_posted', 'Date posted', 'job_posted', 'date_picker', 'date_posted', array( 'display_format' => 'd/m/Y', 'return_format' => 'd/m/Y' ) ),
			$ajs_field( 'field_ajs_closing', 'Closing date', 'job_closing', 'date_picker', 'valid_through', array( 'display_format' => 'd/m/Y', 'return_format' => 'd/m/Y' ) ),
			$ajs_field( 'field_ajs_type', 'Employment type', 'job_type', 'checkbox', 'employment_type', array( 'choices' => array( 'FULL_TIME' => 'Full time', 'PART_TIME' => 'Part time', 'TEMPORARY' => 'Temporary' ) ) ),
			$ajs_field( 'field_ajs_salary_text', 'Salary (shown on the page)', 'salary_text', 'text' ),
			$ajs_field( 'field_ajs_smin', 'Salary minimum', 'salary_min', 'number', 'salary_min' ),
			$ajs_field( 'field_ajs_smax', 'Salary maximum', 'salary_max', 'number', 'salary_max' ),
			$ajs_field( 'field_ajs_sunit', 'Salary unit', 'salary_unit', 'select', 'salary_unit', array( 'choices' => array( 'YEAR' => 'Per year', 'HOUR' => 'Per hour' ), 'default_value' => 'YEAR' ) ),
			$ajs_field( 'field_ajs_remote', 'Fully remote', 'remote', 'true_false', 'remote' ),
			$ajs_field(
				'field_ajs_loc',
				'Location',
				'job_location',
				'group',
				'',
				array(
					'sub_fields' => array(
						$ajs_field( 'field_ajs_street', 'Street', 'street', 'text', 'street' ),
						$ajs_field( 'field_ajs_town', 'Town', 'town', 'text', 'locality' ),
						$ajs_field( 'field_ajs_pc', 'Postcode', 'postcode', 'text', 'postcode' ),
					),
				)
			),
			$ajs_field( 'field_ajs_ref', 'Reference', 'job_ref', 'text', 'identifier' ),
		),
	)
);

acf_import_field_group(
	array(
		'key'      => 'group_ajs_fixture_broken',
		'title'    => 'AJS test: broken mapping',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'job_broken' ) ) ),
		'fields'   => array(
			$ajs_field( 'field_ajsb_close1', 'Closes', 'closes', 'date_picker', 'valid_through' ),
			$ajs_field( 'field_ajsb_close2', 'Closes again', 'closes_again', 'date_picker', 'valid_through' ),
			$ajs_field( 'field_ajsb_type', 'Type', 'job_type', 'select', 'employment_type', array( 'choices' => array( 'FULL_TIME' => 'Full time', 'Casual' => 'Casual' ) ) ),
			$ajs_field( 'field_ajsb_smin', 'Salary', 'salary_min', 'number', 'salary_min' ),
		),
	)
);

$ajs_day = function ( int $days ): string {
	return wp_date( 'Ymd', strtotime( "$days days" ) );
};

$ajs_complete = array(
	'field_ajs_desc'        => '<h2>About the role</h2><p>Plan and write our <strong>communications</strong>.</p><ul><li>Social media</li><li>Newsletters</li></ul>',
	'field_ajs_posted'      => $ajs_day( -5 ),
	'field_ajs_closing'     => $ajs_day( 30 ),
	'field_ajs_type'        => array( 'FULL_TIME' ),
	'field_ajs_salary_text' => '£24,000 to £26,000 per year',
	'field_ajs_smin'        => 24000,
	'field_ajs_smax'        => 26000,
	'field_ajs_sunit'       => 'YEAR',
	'field_ajs_remote'      => 0,
	'field_ajs_loc'         => array( 'field_ajs_street' => '1 Test Street', 'field_ajs_town' => 'Bristol', 'field_ajs_pc' => 'BS1 1AA' ),
	'field_ajs_ref'         => 'AJS-001',
);
$ajs_closing_iso = ( new DateTimeImmutable( $ajs_day( 30 ) . ' 23:59:59', wp_timezone() ) )->format( 'c' );

// slug => post type, title, field changes from $ajs_complete, expectations for check-front-end.py.
$ajs_cases = array(
	'complete'                 => array(
		'job_test',
		'Communications Officer',
		array(),
		array(
			'present' => true,
			'equals'  => array(
				'title'                               => 'Communications Officer',
				'description'                         => '<p>About the role</p><p>Plan and write our communications.</p><ul><li>Social media</li><li>Newsletters</li></ul>',
				'datePosted'                          => wp_date( 'Y-m-d', strtotime( '-5 days' ) ),
				'validThrough'                        => $ajs_closing_iso,
				'employmentType'                      => 'FULL_TIME',
				'baseSalary.value.minValue'           => 24000,
				'baseSalary.value.maxValue'           => 26000,
				'baseSalary.value.unitText'           => 'YEAR',
				'jobLocation.address.streetAddress'   => '1 Test Street',
				'jobLocation.address.addressLocality' => 'Bristol',
				'jobLocation.address.postalCode'      => 'BS1 1AA',
				'jobLocation.address.addressCountry'  => 'GB',
				'identifier.value'                    => 'AJS-001',
				'hiringOrganization.name'             => get_bloginfo( 'name' ),
			),
		),
	),
	'remote'                   => array(
		'job_test',
		'Remote Fundraiser',
		array( 'field_ajs_remote' => 1, 'field_ajs_loc' => array( 'field_ajs_street' => '', 'field_ajs_town' => '', 'field_ajs_pc' => '' ) ),
		array( 'present' => true, 'equals' => array( 'jobLocationType' => 'TELECOMMUTE', 'applicantLocationRequirements.name' => 'United Kingdom' ), 'absent_keys' => array( 'jobLocation' ) ),
	),
	'hybrid'                   => array(
		'job_test',
		'Hybrid Officer',
		array(),
		array( 'present' => true, 'equals' => array( 'jobLocation.address.addressLocality' => 'Bristol' ), 'absent_keys' => array( 'jobLocationType' ) ),
	),
	'single-salary'            => array(
		'job_test',
		'Hourly Assistant',
		array( 'field_ajs_smin' => '12.60', 'field_ajs_smax' => '', 'field_ajs_sunit' => 'HOUR' ),
		array( 'present' => true, 'equals' => array( 'baseSalary.value.value' => 12.6, 'baseSalary.value.unitText' => 'HOUR' ) ),
	),
	'no-salary'                => array(
		'job_test',
		'Volunteer Coordinator',
		array( 'field_ajs_smin' => '', 'field_ajs_smax' => '' ),
		array( 'present' => true, 'absent_keys' => array( 'baseSalary' ) ),
	),
	'several-types'            => array(
		'job_test',
		'Temporary Part-time Clerk',
		array( 'field_ajs_type' => array( 'PART_TIME', 'TEMPORARY' ) ),
		array( 'present' => true, 'equals' => array( 'employmentType' => array( 'PART_TIME', 'TEMPORARY' ) ) ),
	),
	'invalid-choice'           => array(
		'job_test',
		'Casual Helper',
		array( 'field_ajs_type' => array( 'PART_TIME', 'Casual' ) ),
		array( 'present' => true, 'equals' => array( 'employmentType' => 'PART_TIME' ) ),
	),
	'posted-fallback'          => array(
		'job_test',
		'Finance Assistant',
		array( 'field_ajs_posted' => '' ),
		array( 'present' => true, 'equals_post_date' => 'datePosted' ),
	),
	'closed'                   => array(
		'job_test',
		'Closed Role',
		array( 'field_ajs_closing' => $ajs_day( -1 ) ),
		array( 'present' => false ),
	),
	'missing-description'      => array(
		'job_test',
		'No Description Role',
		array( 'field_ajs_desc' => '' ),
		array( 'present' => false ),
	),
	'title-equals-description' => array(
		'job_test',
		'Same Title',
		array( 'field_ajs_desc' => '<p>Same Title</p>' ),
		array( 'present' => false ),
	),
	'no-location'              => array(
		'job_test',
		'Nowhere Role',
		array( 'field_ajs_loc' => array( 'field_ajs_street' => '', 'field_ajs_town' => '', 'field_ajs_pc' => '' ) ),
		array( 'present' => false ),
	),
	'acf-post-type'            => array(
		'job_acf',
		'ACF Registered Role',
		array(),
		array( 'present' => true, 'equals' => array( 'title' => 'ACF Registered Role' ) ),
	),
	'broken-mapping'           => array(
		'job_broken',
		'Broken Mapping Role',
		null,
		array( 'present' => false ),
	),
);

$ajs_manifest = array();
foreach ( $ajs_cases as $slug => list( $type, $title, $changes, $expect ) ) {
	$id = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => $title,
			'post_name'   => 'ajs-' . $slug,
			'post_status' => 'publish',
			'meta_input'  => array( '_ajs_fixture' => $slug ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		echo "Could not create $slug: " . $id->get_error_message() . "\n";
		continue;
	}
	if ( null === $changes ) {
		update_field( 'field_ajsb_close1', $ajs_day( 30 ), $id );
		update_field( 'field_ajsb_type', 'Casual', $id );
	} else {
		foreach ( array_merge( $ajs_complete, $changes ) as $key => $value ) {
			update_field( $key, $value, $id );
		}
	}
	// What the edit screen does after saving fields, so the closing date gets scheduled.
	do_action( 'acf/save_post', $id );

	$ajs_manifest[] = array(
		'slug'      => $slug,
		'id'        => $id,
		'url'       => get_permalink( $id ),
		'post_date' => get_post( $id )->post_date,
		'expect'    => $expect,
	);
}

flush_rewrite_rules();
echo 'MANIFEST:' . wp_json_encode( $ajs_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
