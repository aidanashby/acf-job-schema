<?php
/**
 * In-WordPress checks after setup.php: closing-date scheduling, the purge on close, mapping notices and
 * the Job schema column. Prints FAIL lines and a summary; tests/run.sh fails on any FAIL.
 */

require_once WP_PLUGIN_DIR . '/acf-job-schema/includes/admin.php'; // Loaded only in wp-admin normally.

$ajs_failures = 0;
$ajs_checks   = 0;
$ajs_check    = function ( bool $ok, string $label ) use ( &$ajs_failures, &$ajs_checks ) {
	++$ajs_checks;
	if ( ! $ok ) {
		++$ajs_failures;
		echo "FAIL: $label\n";
	}
};
$ajs_capture = function ( callable $fn, ...$args ): string {
	ob_start();
	$fn( ...$args );
	return (string) ob_get_clean();
};
$ajs_post = function ( string $slug ): int {
	$ids = get_posts( array( 'post_type' => 'any', 'meta_key' => '_ajs_fixture', 'meta_value' => $slug, 'fields' => 'ids', 'post_status' => 'any' ) );
	return (int) ( $ids[0] ?? 0 );
};

// --- Closing-date scheduling ------------------------------------------------------------
$ajs_id    = $ajs_post( 'complete' );
$ajs_close = ( new DateTimeImmutable( wp_date( 'Y-m-d', strtotime( '30 days' ) ) . ' 23:59:59', wp_timezone() ) )->getTimestamp();
$ajs_check( wp_next_scheduled( AJS\CLOSE_HOOK, array( $ajs_id ) ) === $ajs_close + 1, 'complete job: purge scheduled one second after closing' );
$ajs_check( false === wp_next_scheduled( AJS\CLOSE_HOOK, array( $ajs_post( 'closed' ) ) ), 'closed job: nothing scheduled' );

wp_update_post( array( 'ID' => $ajs_id, 'post_status' => 'draft' ) );
$ajs_check( false === wp_next_scheduled( AJS\CLOSE_HOOK, array( $ajs_id ) ), 'unpublished job: schedule cleared' );
wp_update_post( array( 'ID' => $ajs_id, 'post_status' => 'publish' ) );
$ajs_check( wp_next_scheduled( AJS\CLOSE_HOOK, array( $ajs_id ) ) === $ajs_close + 1, 'republished job: schedule restored' );

update_field( 'field_ajs_closing', wp_date( 'Ymd', strtotime( '40 days' ) ), $ajs_id );
do_action( 'acf/save_post', $ajs_id );
$ajs_new = ( new DateTimeImmutable( wp_date( 'Y-m-d', strtotime( '40 days' ) ) . ' 23:59:59', wp_timezone() ) )->getTimestamp();
$ajs_check( wp_next_scheduled( AJS\CLOSE_HOOK, array( $ajs_id ) ) === $ajs_new + 1, 'changed closing date: rescheduled' );
update_field( 'field_ajs_closing', wp_date( 'Ymd', strtotime( '30 days' ) ), $ajs_id );
do_action( 'acf/save_post', $ajs_id );

$ajs_purged = array();
add_action(
	'litespeed_purge_post',
	function ( $id ) use ( &$ajs_purged ) {
		$ajs_purged[] = $id;
	}
);
do_action( AJS\CLOSE_HOOK, $ajs_id );
$ajs_check( array( $ajs_id ) === $ajs_purged, 'closing event asks LiteSpeed to purge that post' );

// --- Mapping notices ----------------------------------------------------------------------
$ajs_out = $ajs_capture( 'AJS\mapping_notice', 'job_broken' );
$ajs_check( false !== strpos( $ajs_out, 'notice-error' ), 'broken mapping: error notice' );
foreach ( array( 'Description', 'location', 'Closes, Closes again', 'Casual', 'Salary unit' ) as $ajs_text ) {
	$ajs_check( false !== strpos( $ajs_out, $ajs_text ), "broken mapping notice mentions \"$ajs_text\"" );
}
$ajs_check( '' === $ajs_capture( 'AJS\mapping_notice', 'job_test' ), 'complete mapping: no notice' );
$ajs_check( '' === $ajs_capture( 'AJS\mapping_notice', 'post' ), 'post type with no mapping: no notice' );
$ajs_check( in_array( 'job_test', AJS\mapped_post_types(), true ) && in_array( 'job_acf', AJS\mapped_post_types(), true ) && ! in_array( 'post', AJS\mapped_post_types(), true ), 'mapped post types found from location rules' );

// --- Column ---------------------------------------------------------------------------------
$ajs_column = array(
	'complete'            => 'Valid',
	'closed'              => 'Closed',
	'missing-description' => 'Missing: Description',
	'no-location'         => 'Missing: Location',
	'broken-mapping'      => 'Setup error',
	'invalid-choice'      => 'Valid <span title=',
);
foreach ( $ajs_column as $ajs_slug => $ajs_start ) {
	$ajs_out = $ajs_capture( 'AJS\render_column', 'job_schema', $ajs_post( $ajs_slug ) );
	$ajs_check( 0 === strpos( $ajs_out, $ajs_start ), "column for $ajs_slug starts \"$ajs_start\" (got \"$ajs_out\")" );
}
$ajs_regular = get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'fields' => 'ids' ) );
if ( $ajs_regular ) {
	$ajs_check( 'Not set up' === $ajs_capture( 'AJS\render_column', 'job_schema', $ajs_regular[0] ), 'post without mapped fields: Not set up' );
}

echo 0 === $ajs_failures ? "All $ajs_checks WordPress checks passed.\n" : "$ajs_failures of $ajs_checks WordPress checks failed.\n";
