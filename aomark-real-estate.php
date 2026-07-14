<?php
/**
 * Plugin Name: Aomark Listings
 * Description: Elementor-first listing models, custom post types, fields, filters, maps and listing widgets by Aomark.io.
 * Version: 3.4.0
 * Author: Aomark.io
 * Author URI: https://aomark.io
 * Text Domain: aomark-listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AOMARK_LISTINGS_VERSION', '3.4.0' );
define( 'AOMARK_LISTINGS_PLUGIN_FILE', __FILE__ );
define( 'AOMARK_LISTINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AOMARK_LISTINGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$files = [
	'includes/listings-models.php',
	'includes/listings-admin.php',
	'includes/listings-assets.php',
	'includes/listings-query.php',
	'includes/listings-render.php',
	'includes/listings-elementor-controls.php',
	'widgets/elementor-widgets.php',
];

foreach ( $files as $file ) {
	require_once AOMARK_LISTINGS_PLUGIN_DIR . $file;
}

register_activation_hook( __FILE__, 'aomark_listings_activate' );
register_deactivation_hook( __FILE__, 'aomark_listings_deactivate' );
