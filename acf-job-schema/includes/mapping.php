<?php
/**
 * Finds which ACF fields are mapped to which JobPosting property, and reads their values.
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

/** The ACF field setting that holds a field's property. */
const SETTING = 'job_schema_property';

/**
 * Mapped fields from a set of field groups.
 *
 * @param array $groups ACF field group arrays.
 * @return array{fields: array<string, array>, conflicts: array<string, string[]>}
 *         fields: property => ACF field (with meta_name added). conflicts: property => labels of every field mapped to it.
 */
function mapped_fields( array $groups ): array {
	$fields    = array();
	$conflicts = array();
	$props     = properties();

	foreach ( $groups as $group ) {
		foreach ( flatten_fields( (array) acf_get_fields( $group ) ) as $field ) {
			$property = $field[ SETTING ] ?? '';
			if ( ! isset( $props[ $property ] ) || ! in_array( $field['type'], $props[ $property ]['types'], true ) ) {
				continue;
			}
			if ( isset( $fields[ $property ] ) ) {
				$conflicts[ $property ] = $conflicts[ $property ] ?? array( $fields[ $property ]['label'] );
				$conflicts[ $property ][] = $field['label'];
				continue;
			}
			$fields[ $property ] = $field;
		}
	}
	return array( 'fields' => $fields, 'conflicts' => $conflicts );
}

/**
 * Top-level fields plus the subfields of Group fields, each with the meta name its value is stored under.
 */
function flatten_fields( array $fields, string $prefix = '' ): array {
	$flat = array();
	foreach ( $fields as $field ) {
		$field['meta_name'] = $prefix . $field['name'];
		if ( 'group' === $field['type'] && ! empty( $field['sub_fields'] ) ) {
			// ACF stores a Group's subfields as {group}_{subfield}.
			$flat = array_merge( $flat, flatten_fields( $field['sub_fields'], $field['meta_name'] . '_' ) );
			continue;
		}
		$flat[] = $field;
	}
	return $flat;
}

/**
 * Mapping for one post, from the field groups ACF shows on it (any location rule).
 */
function mapping_for_post( int $post_id ): array {
	static $cache = array();
	if ( ! isset( $cache[ $post_id ] ) ) {
		$cache[ $post_id ] = mapped_fields( acf_get_field_groups( array( 'post_id' => $post_id ) ) );
	}
	return $cache[ $post_id ];
}

/**
 * Post types named in a field group's "Post Type is equal to" location rules.
 *
 * @return string[]
 */
function group_post_types( array $group ): array {
	$types = array();
	foreach ( (array) ( $group['location'] ?? array() ) as $and_rules ) {
		foreach ( (array) $and_rules as $rule ) {
			if ( 'post_type' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '' ) ) {
				$types[] = $rule['value'];
			}
		}
	}
	return array_values( array_unique( $types ) );
}

/**
 * Mapping for a post type, from the field groups whose location rules name it.
 */
function mapping_for_post_type( string $post_type ): array {
	$groups = array_filter(
		acf_get_field_groups(),
		function ( $group ) use ( $post_type ) {
			return in_array( $post_type, group_post_types( $group ), true );
		}
	);
	return mapped_fields( $groups );
}

/**
 * Post types that have at least one mapped field.
 *
 * @return string[]
 */
function mapped_post_types(): array {
	static $types = null;
	if ( null === $types ) {
		$types = array();
		foreach ( acf_get_field_groups() as $group ) {
			if ( mapped_fields( array( $group ) )['fields'] ) {
				$types = array_merge( $types, group_post_types( $group ) );
			}
		}
		$types = array_values( array_unique( $types ) );
	}
	return $types;
}

/**
 * Raw values for build_job_posting(), with the post title and publish date filling an empty title or posted date.
 */
function read_values( int $post_id, array $fields ): array {
	$values = array();
	foreach ( $fields as $property => $field ) {
		// The description needs ACF's formatting (wpautop for WYSIWYG). Everything else is read as stored.
		$values[ $property ] = get_field( $field['meta_name'], $post_id, 'description' === $property );
	}

	$post = get_post( $post_id );
	if ( empty( $values['title'] ) ) {
		$values['title'] = $post->post_title;
	}
	if ( empty( $values['date_posted'] ) ) {
		$values['date_posted'] = $post->post_date;
	}
	return $values;
}

/**
 * Site-wide values: timezone, now, organisation, currency and countries.
 */
function context(): array {
	return apply_filters(
		'acf_job_schema_defaults',
		array(
			'timezone'        => wp_timezone(),
			'now'             => time(),
			'organization'    => organization(),
			'currency'        => 'GBP',
			'default_country' => 'GB',
			'remote_country'  => 'United Kingdom',
		)
	);
}

/**
 * The hiring organisation: Rank Math's Knowledge Graph details if set, otherwise the site's own.
 *
 * @return array{name: string, url: string, logo: string}
 */
function organization(): array {
	$rank_math = (array) get_option( 'rank-math-options-titles', array() );

	$logo = (string) ( $rank_math['knowledgegraph_logo'] ?? '' );
	if ( '' === $logo && get_theme_mod( 'custom_logo' ) ) {
		$logo = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
	}
	if ( '' === $logo && function_exists( 'et_get_option' ) ) {
		$logo = (string) et_get_option( 'divi_logo', '' );
	}
	if ( '' === $logo ) {
		$logo = (string) get_site_icon_url( 512 );
	}

	return apply_filters(
		'acf_job_schema_organization',
		array(
			'name' => (string) ( ! empty( $rank_math['knowledgegraph_name'] ) ? $rank_math['knowledgegraph_name'] : get_bloginfo( 'name' ) ),
			'url'  => home_url( '/' ),
			'logo' => $logo,
		)
	);
}

/**
 * Everything known about one post's job schema.
 *
 * @return array{set_up: bool, mapping: array, job: ?array}
 *         set_up is false when no field is mapped. job is null when the mapping has errors.
 */
function job_for_post( int $post_id ): array {
	$mapped = mapping_for_post( $post_id );
	if ( ! $mapped['fields'] ) {
		return array( 'set_up' => false, 'mapping' => null, 'job' => null );
	}
	$mapping = check_mapping( $mapped['fields'], $mapped['conflicts'] );
	$job     = $mapping['errors'] ? null : build_job_posting( read_values( $post_id, $mapped['fields'] ), context() );
	return array( 'set_up' => true, 'mapping' => $mapping, 'job' => $job );
}
