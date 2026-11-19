<?php
/**
 * Notices on the field group, post list and post edit screens, and the Job schema column.
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

add_action( 'current_screen', __NAMESPACE__ . '\register_screen' );

/**
 * Hook only what the current screen needs, so other screens pay nothing.
 */
function register_screen( \WP_Screen $screen ): void {
	if ( 'acf-field-group' === $screen->post_type && 'post' === $screen->base ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\field_group_notices' );
		return;
	}
	if ( ! in_array( $screen->post_type, mapped_post_types(), true ) ) {
		return;
	}
	if ( 'edit' === $screen->base ) {
		add_action(
			'admin_notices',
			function () use ( $screen ) {
				mapping_notice( $screen->post_type );
			}
		);
		add_filter( "manage_{$screen->post_type}_posts_columns", __NAMESPACE__ . '\add_column' );
		add_action( "manage_{$screen->post_type}_posts_custom_column", __NAMESPACE__ . '\render_column', 10, 2 );
	} elseif ( 'post' === $screen->base ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\post_notice' );
	}
}

/**
 * On a field group's edit screen: the mapping check for each post type its location rules name.
 */
function field_group_notices(): void {
	$group = acf_get_field_group( get_the_ID() );
	if ( ! $group ) {
		return;
	}
	foreach ( group_post_types( $group ) as $post_type ) {
		mapping_notice( $post_type );
	}
}

/**
 * Errors and warnings for one post type's mapping. Silent when nothing is mapped.
 */
function mapping_notice( string $post_type ): void {
	$mapped = mapping_for_post_type( $post_type );
	if ( ! $mapped['fields'] ) {
		return;
	}
	$check = check_mapping( $mapped['fields'], $mapped['conflicts'] );
	$type  = get_post_type_object( $post_type );
	$name  = $type ? $type->labels->name : $post_type;

	if ( $check['errors'] ) {
		notice( 'error', sprintf( 'Job schema is off for %s until this is fixed in the ACF field settings:', $name ), $check['errors'] );
	}
	if ( $check['warnings'] ) {
		notice( 'warning', sprintf( 'Job schema for %s:', $name ), $check['warnings'] );
	}
}

/**
 * On a post's edit screen: what's missing, and whether the markup is live.
 */
function post_notice(): void {
	$post_id = (int) get_the_ID();
	$state   = job_for_post( $post_id );
	if ( ! $state['set_up'] ) {
		return;
	}
	if ( ! $state['job'] ) {
		notice( 'error', 'Job schema is off for this post type until its ACF field settings are fixed. See the post list for details.' );
		return;
	}

	$job = $state['job'];
	if ( 'closed' === $job['status'] ) {
		notice( 'info', 'Job schema: the closing date has passed, so this job is no longer marked up for Google.' );
	} elseif ( 'invalid' === $job['status'] ) {
		notice( 'error', 'Job schema: this job won\'t be shown to Google until these are filled in:', array_map( __NAMESPACE__ . '\missing_label', $job['missing'] ) );
	}
	if ( $job['warnings'] ) {
		notice( 'warning', 'Job schema:', $job['warnings'] );
	}
	if ( has_rank_math_job_posting( $post_id ) ) {
		notice( 'warning', 'Job schema: this post also has a Job Posting schema added in Rank Math, so this plugin\'s markup is left out to avoid two on one page. Remove the Rank Math one to use the ACF fields.' );
	}
	if ( 'valid' === $job['status'] && 'publish' === get_post_status( $post_id ) ) {
		$test = 'https://search.google.com/test/rich-results?url=' . rawurlencode( get_permalink( $post_id ) );
		notice( 'success', 'Job schema is live on this page. <a href="' . esc_url( $test ) . '" target="_blank" rel="noopener">Check it with Google\'s Rich Results Test</a>.', array(), false );
	}
}

/**
 * Whether Rank Math holds a JobPosting schema for this post (stored as rank_math_schema_* post meta).
 */
function has_rank_math_job_posting( int $post_id ): bool {
	foreach ( get_post_meta( $post_id ) as $key => $values ) {
		if ( 0 !== strpos( $key, 'rank_math_schema_' ) ) {
			continue;
		}
		foreach ( $values as $value ) {
			$value = maybe_unserialize( $value );
			if ( is_array( $value ) && in_array( 'JobPosting', (array) ( $value['@type'] ?? array() ), true ) ) {
				return true;
			}
		}
	}
	return false;
}

function add_column( array $columns ): array {
	$columns['job_schema'] = 'Job schema';
	return $columns;
}

function render_column( string $column, int $post_id ): void {
	if ( 'job_schema' !== $column ) {
		return;
	}
	$state = job_for_post( $post_id );
	if ( ! $state['set_up'] ) {
		echo 'Not set up';
		return;
	}
	if ( ! $state['job'] ) {
		echo 'Setup error';
		return;
	}

	$job = $state['job'];
	if ( 'closed' === $job['status'] ) {
		echo 'Closed';
	} elseif ( 'invalid' === $job['status'] ) {
		echo esc_html( 'Missing: ' . implode( ', ', array_map( __NAMESPACE__ . '\missing_label', $job['missing'] ) ) );
	} else {
		echo 'Valid';
	}
	if ( $job['warnings'] ) {
		printf( ' <span title="%s">(%d %s)</span>', esc_attr( implode( "\n", $job['warnings'] ) ), count( $job['warnings'] ), 1 === count( $job['warnings'] ) ? 'warning' : 'warnings' );
	}
}

function missing_label( string $code ): string {
	$labels = array(
		'title'               => 'Job title',
		'description'         => 'Description (it must also differ from the title)',
		'date_posted'         => 'Date posted',
		'hiring_organization' => 'Organisation name (the site title)',
		'location'            => 'Location (town or postcode, or mark the job as fully remote)',
	);
	return $labels[ $code ] ?? $code;
}

/**
 * Print an admin notice. Items are escaped. The message is escaped unless $escape is false (for trusted markup).
 */
function notice( string $type, string $message, array $items = array(), bool $escape = true ): void {
	echo '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . ( $escape ? esc_html( $message ) : wp_kses_post( $message ) ) . '</p>';
	if ( $items ) {
		echo '<ul style="list-style:disc;padding-left:2em">';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}
