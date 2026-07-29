<?php
/**
 * Frontend asset registration for Aomark Listings.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the HTTPS tile template used by listing maps.
 *
 * The placeholders are temporarily replaced before URL sanitization because
 * WordPress removes curly braces from regular URLs.
 *
 * @return string
 */
function aomark_listings_tile_url() {
	$default = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
	$url     = apply_filters( 'aomark_listings_tile_url', $default );
	$url     = is_string( $url ) ? trim( $url ) : '';

	if ( '' === $url || strlen( $url ) > 500 || 0 !== stripos( $url, 'https://' ) ) {
		return $default;
	}

	foreach ( [ '{z}', '{x}', '{y}' ] as $placeholder ) {
		if ( false === strpos( $url, $placeholder ) ) {
			return $default;
		}
	}

	$tokens = [
		'{s}' => 'AOMARKTILESUBDOMAIN',
		'{z}' => 'AOMARKTILEZOOM',
		'{x}' => 'AOMARKTILEX',
		'{y}' => 'AOMARKTILEY',
		'{r}' => 'AOMARKTILERETINA',
	];
	$safe_url = esc_url_raw( strtr( $url, $tokens ), [ 'https' ] );
	$safe_url = strtr( $safe_url, array_flip( $tokens ) );

	return 0 === stripos( $safe_url, 'https://' ) ? $safe_url : $default;
}

/**
 * Return sanitized map attribution HTML.
 *
 * @return string
 */
function aomark_listings_tile_attribution() {
	$default     = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors';
	$attribution = apply_filters( 'aomark_listings_tile_attribution', $default );

	return wp_kses(
		is_string( $attribution ) && '' !== trim( $attribution ) ? $attribution : $default,
		[
			'a' => [
				'href'   => true,
				'rel'    => true,
				'target' => true,
			],
		]
	);
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
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'aomark_listings_results' ),
			'tileUrl'         => aomark_listings_tile_url(),
			'tileAttribution' => aomark_listings_tile_attribution(),
			'i18n'            => [
				'loadError'      => __( 'Listings could not be loaded. Please try again.', 'aomark-listings' ),
				'retry'          => __( 'Retry', 'aomark-listings' ),
				'resultsUpdated' => __( 'Listing results updated.', 'aomark-listings' ),
				'resultsCount'   => __( '%d listings found.', 'aomark-listings' ),
				'resultsAdded'   => __( 'Listings added: %1$d. Total: %2$d.', 'aomark-listings' ),
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
