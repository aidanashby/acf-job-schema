<?php
/**
 * Clears the cached page when a job's closing date passes, so the removed markup reaches visitors and Google.
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

const CLOSE_HOOK = 'acf_job_schema_closed';

// ACF saves field values on save_post at priority 10, so read them afterwards.
add_action( 'acf/save_post', __NAMESPACE__ . '\schedule_closing', 20 );
add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status ) {
			schedule_closing( $post->ID );
		}
	},
	10,
	3
);
add_action( 'before_delete_post', __NAMESPACE__ . '\unschedule_closing' );
add_action( CLOSE_HOOK, __NAMESPACE__ . '\job_closed' );

/**
 * Replace any scheduled closing event for this post with one at its current closing date.
 *
 * @param int|string $post_id ACF passes strings such as "options" for non-post screens.
 */
function schedule_closing( $post_id ): void {
	if ( ! is_numeric( $post_id ) ) {
		return;
	}
	$post_id = (int) $post_id;
	unschedule_closing( $post_id );
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return;
	}

	$state = job_for_post( $post_id );
	$close = $state['job']['valid_through'] ?? null;
	if ( $close && $close > time() ) {
		wp_schedule_single_event( $close + 1, CLOSE_HOOK, array( $post_id ) );
	}
}

function unschedule_closing( int $post_id ): void {
	wp_clear_scheduled_hook( CLOSE_HOOK, array( $post_id ) );
}

/**
 * Purge the post from LiteSpeed Cache. Does nothing when LiteSpeed Cache isn't active.
 */
function job_closed( int $post_id ): void {
	do_action( 'litespeed_purge_post', $post_id );
}
