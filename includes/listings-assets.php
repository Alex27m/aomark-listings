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
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aomark_listings_results' ),
		]
	);
}
add_action( 'wp_enqueue_scripts', 'aomark_listings_register_assets' );
add_action( 'elementor/frontend/after_register_styles', 'aomark_listings_register_assets' );
add_action( 'elementor/editor/after_enqueue_styles', 'aomark_listings_register_assets' );

function aomark_listings_enqueue_assets() {
	aomark_listings_register_assets();
	wp_enqueue_style( 'aomark-listings' );
	wp_enqueue_script( 'aomark-listings' );
}

function aomark_listings_enqueue_map_assets() {
	aomark_listings_enqueue_assets();

	wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
	wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );
}
