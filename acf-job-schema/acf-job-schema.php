<?php
/**
 * Plugin Name:       ACF Job Schema
 * Description:       Google JobPosting structured data built from ACF fields. Map fields in each field's ACF settings.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            aidanashby
 * Plugin URI:        https://github.com/aidanashby/acf-job-schema
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Update URI:        https://github.com/aidanashby/acf-job-schema
 *
 * @package AJS
 */

namespace AJS;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';

require_once __DIR__ . '/includes/build.php';

// Updates from GitHub releases. Loaded in every context so WP-Cron auto-updates work.
add_action(
	'plugins_loaded',
	function () {
		require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/aidanashby/acf-job-schema/',
			__FILE__,
			'acf-job-schema'
		);
		$checker->getVcsApi()->enableReleaseAssets();
	}
);

// Everything else calls ACF, so it loads only when ACF (or Secure Custom Fields) does.
add_action(
	'acf/init',
	function () {
		require_once __DIR__ . '/includes/mapping.php';
		require_once __DIR__ . '/includes/field-setting.php';
		require_once __DIR__ . '/includes/output.php';
		require_once __DIR__ . '/includes/expiry.php';
		if ( is_admin() ) {
			require_once __DIR__ . '/includes/admin.php';
		}
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! did_action( 'acf/init' ) && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p>ACF Job Schema needs Advanced Custom Fields or Secure Custom Fields to be active.</p></div>';
		}
	}
);
