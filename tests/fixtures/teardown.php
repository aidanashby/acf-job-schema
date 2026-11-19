<?php
/**
 * Removes everything tests/fixtures/setup.php creates: fixture posts, the two field groups and the
 * ACF-registered post type. tests/run.sh also removes the mu-plugin test types.
 */

foreach ( get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_ajs_fixture', 'fields' => 'ids' ) ) as $ajs_id ) {
	wp_delete_post( $ajs_id, true );
}
foreach ( array( 'group_ajs_fixture_jobs', 'group_ajs_fixture_broken' ) as $ajs_key ) {
	$ajs_group = acf_get_field_group( $ajs_key );
	if ( $ajs_group && ! empty( $ajs_group['ID'] ) ) {
		acf_delete_field_group( $ajs_group['ID'] );
	}
}
$ajs_type = acf_get_internal_post_type( 'post_type_ajs_fixture', 'acf-post-type' );
if ( $ajs_type && ! empty( $ajs_type['ID'] ) ) {
	acf_delete_internal_post_type( $ajs_type['ID'], 'acf-post-type' );
}
echo "Fixtures removed.\n";
