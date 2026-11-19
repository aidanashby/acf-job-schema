<?php
/**
 * Uninstall: removes scheduled closing events and update-checker data.
 *
 * The "Job schema property" setting stays inside ACF field definitions, where it does nothing without
 * this plugin. It isn't removed because that would rewrite field groups, including local JSON files.
 *
 * @package AJS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function ajs_uninstall_site() {
	wp_unschedule_hook( 'acf_job_schema_closed' ); // One event per post, so clear every argument set.
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		ajs_uninstall_site();
		restore_current_blog();
	}
} else {
	ajs_uninstall_site();
}

delete_site_option( 'external_updates-acf-job-schema' );
wp_clear_scheduled_hook( 'puc_cron_check_updates-acf-job-schema' );
