<?php
/**
 * Exact-package legacy plugin-basename upgrade assertions.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aomark_listings_legacy_results = [];

/**
 * Record one legacy-upgrade assertion.
 *
 * @param string $name      Assertion name.
 * @param bool   $condition Assertion result.
 */
function aomark_listings_legacy_assert( $name, $condition ) {
	global $aomark_listings_legacy_results;
	$aomark_listings_legacy_results[ $name ] = (bool) $condition;
	if ( ! $condition ) {
		throw new RuntimeException( 'Legacy basename assertion failed: ' . $name );
	}
}

$aomark_listings_network  = is_multisite();
$aomark_listings_old      = 'aomark-listings/aomark-real-estate.php';
$aomark_listings_current  = 'aomark-listings/aomark-listings.php';
$aomark_listings_active   = $aomark_listings_network
	? array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) )
	: (array) get_option( 'active_plugins', [] );

aomark_listings_legacy_assert( 'runtime_version_3_5_3', defined( 'AOMARK_LISTINGS_VERSION' ) && '3.5.3' === AOMARK_LISTINGS_VERSION );
aomark_listings_legacy_assert( 'canonical_runtime_file', defined( 'AOMARK_LISTINGS_PLUGIN_FILE' ) && $aomark_listings_current === plugin_basename( AOMARK_LISTINGS_PLUGIN_FILE ) );
aomark_listings_legacy_assert( 'canonical_active_entry', in_array( $aomark_listings_current, $aomark_listings_active, true ) );
aomark_listings_legacy_assert( 'legacy_active_entry_removed', ! in_array( $aomark_listings_old, $aomark_listings_active, true ) );
aomark_listings_legacy_assert( 'canonical_active_entry_unique', 1 === count( array_keys( $aomark_listings_active, $aomark_listings_current, true ) ) );
aomark_listings_legacy_assert( 'legacy_bootstrap_retained', file_exists( WP_PLUGIN_DIR . '/aomark-listings/aomark-real-estate.php' ) );
$aomark_listings_models      = get_option( 'aomark_listings_models', '__aomark_listings_missing__' );
$aomark_listings_models_hash = get_option( 'aomark_listings_legacy_models_hash', '' );
aomark_listings_legacy_assert( 'listing_models_exist', is_array( $aomark_listings_models ) );
aomark_listings_legacy_assert(
	'listing_models_preserved',
	is_string( $aomark_listings_models_hash )
	&& 64 === strlen( $aomark_listings_models_hash )
	&& hash_equals( $aomark_listings_models_hash, hash( 'sha256', maybe_serialize( $aomark_listings_models ) ) )
);

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$aomark_listings_plugins = get_plugins();
aomark_listings_legacy_assert( 'single_plugin_header', isset( $aomark_listings_plugins[ $aomark_listings_current ] ) && ! isset( $aomark_listings_plugins[ $aomark_listings_old ] ) );

deactivate_plugins( $aomark_listings_current, true, $aomark_listings_network );
$aomark_listings_after_deactivate = $aomark_listings_network
	? array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) )
	: (array) get_option( 'active_plugins', [] );
aomark_listings_legacy_assert( 'canonical_deactivation_path', ! in_array( $aomark_listings_current, $aomark_listings_after_deactivate, true ) );

$aomark_listings_activation = activate_plugin( $aomark_listings_current, '', $aomark_listings_network, true );
aomark_listings_legacy_assert( 'canonical_reactivation_succeeds', ! is_wp_error( $aomark_listings_activation ) );
$aomark_listings_after_activate = $aomark_listings_network
	? array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) )
	: (array) get_option( 'active_plugins', [] );
aomark_listings_legacy_assert( 'canonical_reactivation_path', in_array( $aomark_listings_current, $aomark_listings_after_activate, true ) );

$aomark_listings_legacy_payload = [
	'status'     => 'pass',
	'mode'       => $aomark_listings_network ? 'multisite' : 'single-site',
	'assertions' => $aomark_listings_legacy_results,
];

update_option( 'aomark_listings_legacy_basename_qa_result', $aomark_listings_legacy_payload, false );
echo wp_json_encode( $aomark_listings_legacy_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
