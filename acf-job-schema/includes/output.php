<?php
/**
 * Prints the JobPosting on single posts, or adds it to Rank Math's graph.
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

add_action( 'wp', __NAMESPACE__ . '\prepare_output' );

/**
 * Decide once per request whether this page gets a JobPosting, and how.
 */
function prepare_output(): void {
	if ( is_admin() || ! is_singular() || isset( $_GET['et_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Divi's builder flag, read only.
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || post_password_required( $post ) ) {
		return;
	}

	$schema = page_schema( $post->ID );
	if ( ! $schema ) {
		return;
	}

	if ( defined( 'RANK_MATH_VERSION' ) ) {
		add_filter(
			'rank_math/json_ld',
			function ( $data ) use ( $schema, $post ) {
				return add_to_rank_math( (array) $data, $schema, $post->ID );
			},
			99
		);
		return;
	}

	add_action(
		'wp_head',
		function () use ( $schema ) {
			print_schema( $schema );
		},
		20
	);
}

/**
 * The JobPosting for this post after the acf_job_schema_data filter, or null.
 */
function page_schema( int $post_id ): ?array {
	$state = job_for_post( $post_id );
	if ( ! $state['job'] || 'valid' !== $state['job']['status'] ) {
		return null;
	}
	$schema = apply_filters( 'acf_job_schema_data', $state['job']['schema'], $post_id );
	return is_array( $schema ) && $schema ? $schema : null;
}

/**
 * Add the JobPosting to Rank Math's graph, unless the post's Rank Math schema already has one.
 */
function add_to_rank_math( array $data, array $schema, int $post_id ): array {
	foreach ( $data as $entity ) {
		if ( is_array( $entity ) && in_array( 'JobPosting', (array) ( $entity['@type'] ?? array() ), true ) ) {
			return $data; // The edit screen warns about this. Two JobPostings on one page break Google's rules.
		}
	}
	$data['JobPosting'] = array_merge( array( '@id' => get_permalink( $post_id ) . '#jobposting' ), $schema );
	return $data;
}

function print_schema( array $schema ): void {
	$json = wp_json_encode(
		array_merge( array( '@context' => 'https://schema.org' ), $schema ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG // HEX_TAG stops "</script>" in a field closing the tag.
	);
	if ( $json ) {
		echo '<script type="application/ld+json" class="acf-job-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON encoded above.
	}
}
