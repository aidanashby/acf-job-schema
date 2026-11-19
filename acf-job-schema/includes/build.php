<?php
/**
 * Builds a JobPosting from plain values, and checks a post type's field mapping.
 *
 * No WordPress calls in this file, so tests/build-test.php can run it with plain PHP.
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

const EMPLOYMENT_TYPES = array( 'FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'PER_DIEM', 'OTHER' );
const SALARY_UNITS     = array( 'HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR' );

/**
 * Every property a field can be mapped to: label and the ACF field types that suit it.
 *
 * @return array<string, array{label: string, types: string[]}>
 */
function properties(): array {
	return array(
		'title'           => array( 'label' => 'Job title', 'types' => array( 'text' ) ),
		'description'     => array( 'label' => 'Description', 'types' => array( 'wysiwyg', 'textarea' ) ),
		'date_posted'     => array( 'label' => 'Date posted', 'types' => array( 'date_picker', 'date_time_picker' ) ),
		'valid_through'   => array( 'label' => 'Closing date', 'types' => array( 'date_picker', 'date_time_picker' ) ),
		'employment_type' => array( 'label' => 'Employment type', 'types' => array( 'select', 'radio', 'checkbox', 'button_group' ) ),
		'salary_min'      => array( 'label' => 'Salary minimum', 'types' => array( 'number' ) ),
		'salary_max'      => array( 'label' => 'Salary maximum', 'types' => array( 'number' ) ),
		'salary_unit'     => array( 'label' => 'Salary unit', 'types' => array( 'select', 'radio', 'button_group' ) ),
		'remote'          => array( 'label' => 'Fully remote', 'types' => array( 'true_false' ) ),
		'street'          => array( 'label' => 'Street address', 'types' => array( 'text' ) ),
		'locality'        => array( 'label' => 'Town or city', 'types' => array( 'text' ) ),
		'region'          => array( 'label' => 'County or region', 'types' => array( 'text' ) ),
		'postcode'        => array( 'label' => 'Postcode', 'types' => array( 'text' ) ),
		'country'         => array( 'label' => 'Country', 'types' => array( 'text', 'select' ) ),
		'map'             => array( 'label' => 'Map location', 'types' => array( 'google_map' ) ),
		'identifier'      => array( 'label' => 'Job reference', 'types' => array( 'text', 'number' ) ),
	);
}

/**
 * Build the JobPosting for one post.
 *
 * @param array $v   Raw values keyed by property (see properties()). Missing keys count as empty.
 * @param array $ctx timezone (DateTimeZone), now (timestamp), organization (name, url, logo),
 *                   currency, default_country, remote_country.
 * @return array{status: string, schema: ?array, missing: string[], warnings: array<string, string>, valid_through: ?int}
 *         status is 'valid', 'invalid' (something required is missing) or 'closed'.
 */
function build_job_posting( array $v, array $ctx ): array {
	$v        = array_merge( array_fill_keys( array_keys( properties() ), '' ), $v );
	$missing  = array();
	$warnings = array();
	$tz       = $ctx['timezone'];

	$title = clean_text( (string) $v['title'] );
	if ( '' === $title ) {
		$missing[] = 'title';
	}

	$description = clean_description( (string) $v['description'] );
	$desc_text   = clean_text( str_replace( '<br>', ' ', $description ) );
	if ( '' === $desc_text || 0 === strcasecmp( $desc_text, $title ) ) {
		$missing[] = 'description';
	}

	$posted = parse_date( (string) $v['date_posted'], $tz );
	if ( ! $posted ) {
		$missing[] = 'date_posted';
	}

	$valid_through = null;
	if ( '' === trim( (string) $v['valid_through'] ) ) {
		$warnings['no_closing_date'] = 'No closing date, so the job will stay listed until the post is unpublished.';
	} else {
		$closing = parse_date( (string) $v['valid_through'], $tz );
		if ( $closing ) {
			// A date-only closing date means the end of that day.
			$valid_through = $closing['date_only'] ? $closing['date']->setTime( 23, 59, 59 ) : $closing['date'];
		} else {
			$warnings['bad_closing_date'] = 'The closing date could not be read, so it was left out.';
		}
	}

	$org_name = trim( (string) ( $ctx['organization']['name'] ?? '' ) );
	if ( '' === $org_name ) {
		$missing[] = 'hiring_organization';
	}

	$remote  = ! empty( $v['remote'] ) && '0' !== $v['remote'];
	$address = $remote ? null : build_address( $v, (string) $ctx['default_country'] );
	if ( ! $remote && ! isset( $address['addressLocality'] ) && ! isset( $address['postalCode'] ) ) {
		$missing[] = 'location';
	}

	$closing_ts = $valid_through ? $valid_through->getTimestamp() : null;
	$status     = $closing_ts && $closing_ts < $ctx['now'] ? 'closed' : ( $missing ? 'invalid' : 'valid' );
	$result     = array(
		'status'        => $status,
		'schema'        => null,
		'missing'       => $missing,
		'warnings'      => $warnings,
		'valid_through' => $closing_ts,
	);
	if ( 'valid' !== $status ) {
		return $result;
	}

	$schema = array(
		'@type'       => 'JobPosting',
		'title'       => $title,
		'description' => $description,
		'datePosted'  => $posted['date_only'] ? $posted['date']->format( 'Y-m-d' ) : $posted['date']->format( 'c' ),
	);
	if ( $valid_through ) {
		$schema['validThrough'] = $valid_through->format( 'c' );
	}

	$schema['hiringOrganization'] = array( '@type' => 'Organization', 'name' => $org_name );
	if ( ! empty( $ctx['organization']['url'] ) ) {
		$schema['hiringOrganization']['sameAs'] = $ctx['organization']['url'];
	}
	if ( ! empty( $ctx['organization']['logo'] ) ) {
		$schema['hiringOrganization']['logo'] = $ctx['organization']['logo'];
	}

	if ( $remote ) {
		// Google: TELECOMMUTE is only for 100% remote jobs, with the countries applicants may live in.
		$schema['jobLocationType']               = 'TELECOMMUTE';
		$schema['applicantLocationRequirements'] = array( '@type' => 'Country', 'name' => $ctx['remote_country'] );
	} else {
		$schema['jobLocation'] = array( '@type' => 'Place', 'address' => $address );
	}

	$types = array_values( array_filter( array_map( 'strval', (array) $v['employment_type'] ), 'strlen' ) );
	$valid = array_values( array_intersect( $types, EMPLOYMENT_TYPES ) );
	if ( count( $valid ) < count( $types ) ) {
		$result['warnings']['bad_employment_type'] = 'Employment type values Google doesn\'t accept were left out: ' . implode( ', ', array_diff( $types, EMPLOYMENT_TYPES ) ) . '.';
	}
	if ( $valid ) {
		$schema['employmentType'] = 1 === count( $valid ) ? $valid[0] : $valid;
	}

	$salary = build_salary( $v, (string) $ctx['currency'] );
	if ( is_array( $salary ) ) {
		$schema['baseSalary'] = $salary;
	} elseif ( is_string( $salary ) ) {
		$result['warnings']['salary_incomplete'] = $salary;
	}

	$reference = clean_text( (string) $v['identifier'] );
	if ( '' !== $reference ) {
		$schema['identifier'] = array( '@type' => 'PropertyValue', 'name' => $org_name, 'value' => $reference );
	}

	$result['schema'] = $schema;
	return $result;
}

/**
 * Plain text: tags removed, entities decoded, whitespace collapsed.
 */
function clean_text( string $text ): string {
	$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Keep only the tags Google reads in a job description (p, ul, li, br), without attributes.
 */
function clean_description( string $html ): string {
	$html = str_replace( array( "\r\n", "\r" ), "\n", $html );
	$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $html );

	if ( strip_tags( $html ) === $html ) {
		// A plain textarea: its line breaks are the only structure.
		return str_replace( "\n", '<br>', trim( $html ) );
	}

	$html = preg_replace( '/\s+/', ' ', $html );
	$html = preg_replace( '#<h[1-6]\b[^>]*>#i', '<p>', $html );
	$html = preg_replace( '#</h[1-6]>#i', '</p>', $html );
	$html = preg_replace( '#<(/?)ol\b[^>]*>#i', '<$1ul>', $html );
	$html = strip_tags( $html, '<p><ul><li><br>' );
	$html = preg_replace( '#<(p|ul|li)\b[^>]*>#i', '<$1>', $html );
	$html = preg_replace( '#<br\b[^>]*>#i', '<br>', $html );
	$html = preg_replace( '#<p>(\s|&nbsp;)*</p>#i', '', $html );
	$html = preg_replace( '/>\s+</', '><', $html );
	return trim( $html );
}

/**
 * Read a stored ACF or WordPress date: Ymd (date picker), Y-m-d, or Y-m-d H:i:s (date-time picker, post date).
 *
 * @return array{date: \DateTimeImmutable, date_only: bool}|null
 */
function parse_date( string $raw, \DateTimeZone $tz ): ?array {
	$raw     = trim( $raw );
	$formats = array(
		'/^\d{8}$/'                               => array( 'Ymd', true ),
		'/^\d{4}-\d{2}-\d{2}$/'                   => array( 'Y-m-d', true ),
		'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/' => array( 'Y-m-d H:i:s', false ),
	);
	foreach ( $formats as $pattern => list( $format, $date_only ) ) {
		if ( ! preg_match( $pattern, $raw ) ) {
			continue;
		}
		$date = \DateTimeImmutable::createFromFormat( '!' . $format, $raw, $tz );
		// Formatting back catches impossible dates such as 20261340, which PHP would roll over.
		if ( $date && $date->format( $format ) === $raw ) {
			return array( 'date' => $date, 'date_only' => $date_only );
		}
		return null;
	}
	return null;
}

/**
 * PostalAddress from the address fields, with a Google Map field filling any part left empty.
 */
function build_address( array $v, string $default_country ): array {
	$map  = is_array( $v['map'] ) ? $v['map'] : array();
	$from = array(
		'streetAddress'   => array( $v['street'], trim( ( $map['street_number'] ?? '' ) . ' ' . ( $map['street_name'] ?? '' ) ) ),
		'addressLocality' => array( $v['locality'], $map['city'] ?? '' ),
		'addressRegion'   => array( $v['region'], $map['state'] ?? '' ),
		'postalCode'      => array( $v['postcode'], $map['post_code'] ?? '' ),
		'addressCountry'  => array( $v['country'], $map['country_short'] ?? '', $default_country ),
	);

	$address = array( '@type' => 'PostalAddress' );
	foreach ( $from as $key => $candidates ) {
		foreach ( $candidates as $candidate ) {
			$candidate = clean_text( (string) $candidate );
			if ( '' !== $candidate ) {
				$address[ $key ] = $candidate;
				break;
			}
		}
	}
	return $address;
}

/**
 * MonetaryAmount, null when no salary was entered, or a warning message when what was entered can't be used.
 *
 * @return array|string|null
 */
function build_salary( array $v, string $currency ) {
	$min_raw = trim( (string) $v['salary_min'] );
	$max_raw = trim( (string) $v['salary_max'] );
	if ( '' === $min_raw && '' === $max_raw ) {
		return null; // A unit on its own (often a select's default) isn't a salary.
	}

	$min  = '' === $min_raw ? null : parse_number( $min_raw );
	$max  = '' === $max_raw ? null : parse_number( $max_raw );
	$unit = trim( (string) $v['salary_unit'] );

	if ( false === $min || false === $max ) {
		return 'The salary isn\'t a number, so it was left out.';
	}
	if ( ! in_array( $unit, SALARY_UNITS, true ) ) {
		return 'The salary has no valid unit (' . implode( ', ', SALARY_UNITS ) . '), so it was left out.';
	}
	if ( null !== $min && null !== $max && $max < $min ) {
		return 'The salary maximum is below the minimum, so the salary was left out.';
	}

	$value = array( '@type' => 'QuantitativeValue' );
	if ( null !== $min && null !== $max && $min !== $max ) {
		$value['minValue'] = $min;
		$value['maxValue'] = $max;
	} else {
		$value['value'] = $min ?? $max;
	}
	$value['unitText'] = $unit;

	return array( '@type' => 'MonetaryAmount', 'currency' => $currency, 'value' => $value );
}

/**
 * A salary figure as int or float, ignoring £, commas and spaces. False when it isn't a number.
 *
 * @return int|float|false
 */
function parse_number( string $raw ) {
	$clean = preg_replace( '/[£,\s\x{00A0}]/u', '', $raw );
	if ( ! is_numeric( $clean ) ) {
		return false;
	}
	$number = (float) $clean;
	return floor( $number ) === $number ? (int) $number : $number;
}

/**
 * Check one post type's mapping.
 *
 * @param array $fields    Mapped fields keyed by property: label, type, choices.
 * @param array $conflicts Properties mapped by more than one field: property => field labels.
 * @return array{errors: array<string, string>, warnings: array<string, string>}
 */
function check_mapping( array $fields, array $conflicts ): array {
	$errors   = array();
	$warnings = array();
	$labels   = properties();

	if ( ! isset( $fields['description'] ) ) {
		$errors['no_description'] = 'No field is mapped to Description. Google requires a job description.';
	}
	if ( ! array_intersect_key( $fields, array_flip( array( 'locality', 'postcode', 'map', 'remote' ) ) ) ) {
		$errors['no_location'] = 'No field gives a location. Map a Town or city, Postcode, Map location or Fully remote field.';
	}
	if ( $conflicts ) {
		$list = array();
		foreach ( $conflicts as $property => $field_labels ) {
			$list[] = $labels[ $property ]['label'] . ' (' . implode( ', ', $field_labels ) . ')';
		}
		$errors['duplicate'] = 'More than one field is mapped to the same property: ' . implode( '; ', $list ) . '.';
	}

	if ( ! isset( $fields['valid_through'] ) ) {
		$warnings['no_closing_date'] = 'No field is mapped to Closing date, so jobs won\'t be removed from Google automatically when they close.';
	}
	foreach ( array( 'employment_type' => EMPLOYMENT_TYPES, 'salary_unit' => SALARY_UNITS ) as $property => $allowed ) {
		if ( ! isset( $fields[ $property ] ) ) {
			continue;
		}
		$bad = array_diff( array_map( 'strval', array_keys( (array) ( $fields[ $property ]['choices'] ?? array() ) ) ), $allowed );
		if ( $bad ) {
			$warnings[ 'bad_choices_' . $property ] = sprintf(
				'The %s field has choice values Google won\'t accept: %s. Use Google\'s codes as the values (%s), for example "%s : Your label".',
				$fields[ $property ]['label'],
				implode( ', ', $bad ),
				implode( ', ', $allowed ),
				$allowed[0]
			);
		}
	}
	$has_figure = isset( $fields['salary_min'] ) || isset( $fields['salary_max'] );
	if ( $has_figure !== isset( $fields['salary_unit'] ) ) {
		$warnings['salary_incomplete'] = 'Salary needs both a figure (Salary minimum or maximum) and a Salary unit field, so it will be left out.';
	}

	return array( 'errors' => $errors, 'warnings' => $warnings );
}
