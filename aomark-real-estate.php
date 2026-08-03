<?php
/**
 * Legacy bootstrap for installations activated before the canonical
 * aomark-listings.php entry point was introduced.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/aomark-listings.php';

/**
 * Replace the historical active-plugin basename with the canonical one.
 */
function aomark_listings_migrate_legacy_plugin_basename() {
	$legacy_basename    = plugin_basename( __FILE__ );
	$canonical_basename = plugin_basename( AOMARK_LISTINGS_PLUGIN_FILE );

	if ( $legacy_basename === $canonical_basename ) {
		return;
	}

	$active_plugins = get_option( 'active_plugins', [] );
	if ( is_array( $active_plugins ) && in_array( $legacy_basename, $active_plugins, true ) ) {
		$active_plugins = array_map(
			static function ( $basename ) use ( $legacy_basename, $canonical_basename ) {
				return $legacy_basename === $basename ? $canonical_basename : $basename;
			},
			$active_plugins
		);
		update_option( 'active_plugins', array_values( array_unique( $active_plugins ) ) );
	}

	if ( is_multisite() ) {
		$network_plugins = get_site_option( 'active_sitewide_plugins', [] );
		if ( is_array( $network_plugins ) && isset( $network_plugins[ $legacy_basename ] ) ) {
			$activated_at = $network_plugins[ $legacy_basename ];
			unset( $network_plugins[ $legacy_basename ] );
			$network_plugins[ $canonical_basename ] = $activated_at;
			update_site_option( 'active_sitewide_plugins', $network_plugins );
		}
	}
}

aomark_listings_migrate_legacy_plugin_basename();
