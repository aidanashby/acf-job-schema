<?php
/**
 * Test-only post types for acf-job-schema, registered in code. Installed into mu-plugins by tests/run.sh
 * and removed by `tests/run.sh teardown`. The third test type (job_acf) is registered through ACF's own
 * post type screen by tests/fixtures/setup.php, to cover both routes.
 */

add_action(
	'init',
	function () {
		foreach ( array( 'job_test' => 'Test jobs', 'job_broken' => 'Broken test jobs' ) as $type => $label ) {
			register_post_type(
				$type,
				array(
					'label'        => $label,
					'public'       => true,
					'show_in_rest' => true,
					'supports'     => array( 'title', 'editor' ),
				)
			);
		}
	}
);
