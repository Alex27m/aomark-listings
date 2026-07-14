<?php
/**
 * Frontend asset registration for Aomark Listings.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_register_assets() {
	static $registered = false;

	if ( $registered ) {
		return;
	}

	wp_register_style(
		'aomark-listings-leaflet',
		AOMARK_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/dist/leaflet.css',
		[],
		'1.9.4'
	);

	wp_register_script(
		'aomark-listings-leaflet',
		AOMARK_LISTINGS_PLUGIN_URL . 'assets/vendor/leaflet/dist/leaflet.js',
		[],
		'1.9.4',
		true
	);

	// Keep the historical generic aliases available without depending on them
	// internally, so another plugin cannot replace Aomark's tested Leaflet copy.
	if ( ! wp_style_is( 'leaflet', 'registered' ) ) {
		wp_register_style( 'leaflet', false, [ 'aomark-listings-leaflet' ], '1.9.4' );
	}
	if ( ! wp_script_is( 'leaflet', 'registered' ) ) {
		wp_register_script( 'leaflet', false, [ 'aomark-listings-leaflet' ], '1.9.4', true );
	}

	wp_register_style(
		'aomark-listings',
		AOMARK_LISTINGS_PLUGIN_URL . 'assets/css/listings.css',
		[],
		AOMARK_LISTINGS_VERSION
	);

	wp_register_script(
		'aomark-listings',
		AOMARK_LISTINGS_PLUGIN_URL . 'assets/js/listings.js',
		[],
		AOMARK_LISTINGS_VERSION,
		true
	);

	wp_localize_script(
		'aomark-listings',
		'AomarkListingsSettings',
		[
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'aomark_listings_results' ),
			'i18n'           => [
				'loadError'      => __( 'Listings could not be loaded. Please try again.', 'aomark-listings' ),
				'retry'          => __( 'Retry', 'aomark-listings' ),
				'resultsUpdated' => __( 'Listing results updated.', 'aomark-listings' ),
				'resultsCount'   => __( '%d listings found.', 'aomark-listings' ),
				'mapEmpty'       => __( 'No listing locations match the current filters.', 'aomark-listings' ),
				'mapLabel'       => __( 'Listing locations', 'aomark-listings' ),
			],
		]
	);

	if ( function_exists( 'wp_set_script_translations' ) ) {
		wp_set_script_translations( 'aomark-listings', 'aomark-listings' );
	}

	$registered = true;
}
add_action( 'wp_enqueue_scripts', 'aomark_listings_register_assets' );
add_action( 'elementor/frontend/after_register_styles', 'aomark_listings_register_assets' );
add_action( 'elementor/frontend/after_register_scripts', 'aomark_listings_register_assets' );
add_action( 'elementor/editor/after_enqueue_styles', 'aomark_listings_register_assets' );

function aomark_listings_enqueue_assets() {
	aomark_listings_register_assets();
	wp_enqueue_style( 'aomark-listings' );
	wp_enqueue_script( 'aomark-listings' );
}

function aomark_listings_enqueue_map_assets() {
	aomark_listings_enqueue_assets();

	wp_enqueue_style( 'aomark-listings-leaflet' );
	wp_enqueue_script( 'aomark-listings-leaflet' );
}
