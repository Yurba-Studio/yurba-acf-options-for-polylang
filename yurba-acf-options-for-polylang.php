<?php
/**
 * Plugin Name:       Yurba ACF Options for Polylang
 * Plugin URI:        https://github.com/Yurba-Studio/yurba-acf-options-for-polylang
 * Description:       Per-language ACF options pages for Polylang, with a per-field choice of what is translated, what falls back to the default language and what is shared by all languages. A drop-in replacement for "ACF Options for Polylang" by Be API.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yurba Studio
 * Author URI:        https://dev.yurba.one
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       yurba-acf-options-for-polylang
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'YAOFP_VERSION', '1.0.1' );
define( 'YAOFP_FILE', __FILE__ );
define( 'YAOFP_DIR', plugin_dir_path( __FILE__ ) );
define( 'YAOFP_URL', plugin_dir_url( __FILE__ ) );

require_once YAOFP_DIR . 'includes/helpers.php';
require_once YAOFP_DIR . 'includes/values.php';
require_once YAOFP_DIR . 'includes/admin.php';
require_once YAOFP_DIR . 'includes/api.php';

add_action( 'plugins_loaded', 'yaofp_bootstrap', 20 );

/**
 * Starts the plugin once ACF and Polylang are loaded, or explains in the admin why it can't.
 */
function yaofp_bootstrap() {
	load_plugin_textdomain( 'yurba-acf-options-for-polylang', false, dirname( plugin_basename( YAOFP_FILE ) ) . '/languages' );

	$problem = yaofp_requirements_problem();
	if ( $problem ) {
		add_action( 'admin_notices', function () use ( $problem ) {
			printf( '<div class="notice notice-error"><p><strong>Yurba ACF Options for Polylang:</strong> %s</p></div>', esc_html( $problem ) );
		} );
		return;
	}

	yaofp_register_value_hooks();

	if ( is_admin() ) {
		yaofp_register_admin_hooks();
	}
}

/**
 * Returns why the plugin can't run, or an empty string when everything is in place.
 */
function yaofp_requirements_problem() {
	if ( defined( 'BEA_ACF_OPTIONS_FOR_POLYLANG_VERSION' ) ) {
		return __( 'Deactivate "ACF Options for Polylang" by Be API: this plugin replaces it and keeps its data.', 'yurba-acf-options-for-polylang' );
	}

	if ( ! function_exists( 'acf' ) || ! function_exists( 'acf_get_setting' ) ) {
		return __( 'Advanced Custom Fields is required.', 'yurba-acf-options-for-polylang' );
	}

	if ( version_compare( acf_get_setting( 'version' ), '6.0', '<' ) ) {
		return __( 'Advanced Custom Fields 6.0 or newer is required.', 'yurba-acf-options-for-polylang' );
	}

	if ( ! defined( 'POLYLANG_VERSION' ) || ! function_exists( 'pll_languages_list' ) ) {
		return __( 'Polylang is required.', 'yurba-acf-options-for-polylang' );
	}

	return '';
}
