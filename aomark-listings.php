<?php
/**
 * Plugin Name: Aomark Listings
 * Plugin URI: https://github.com/Alex27m/aomark-listings
 * Description: Elementor-first listing models, custom post types, fields, filters, maps and listing widgets by Aomark.io.
 * Version: 3.5.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Aomark.io
 * Author URI: https://aomark.io
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aomark-listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AOMARK_LISTINGS_VERSION', '3.5.3' );
define( 'AOMARK_LISTINGS_PLUGIN_FILE', __FILE__ );
define( 'AOMARK_LISTINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AOMARK_LISTINGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$aomark_listings_files = [
	'includes/listings-models.php',
	'includes/listings-privacy.php',
	'includes/listings-admin.php',
	'includes/listings-assets.php',
	'includes/listings-query.php',
	'includes/listings-render.php',
	'includes/listings-elementor-controls.php',
	'widgets/elementor-widgets.php',
];

foreach ( $aomark_listings_files as $aomark_listings_file ) {
	require_once AOMARK_LISTINGS_PLUGIN_DIR . $aomark_listings_file;
}
unset( $aomark_listings_files, $aomark_listings_file );

register_activation_hook( __FILE__, 'aomark_listings_activate' );
register_deactivation_hook( __FILE__, 'aomark_listings_deactivate' );
