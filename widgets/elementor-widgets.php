<?php
/**
 * Elementor widget registrar for Aomark Listings.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_register_elementor_widgets( $widgets_manager ) {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	$widget_files = [
		'widgets/listing-results.php',
		'widgets/listing-filter.php',
		'widgets/listing-map.php',
		'widgets/listing-field.php',
		'widgets/listing-gallery.php',
		'widgets/listing-meta.php',
	];

	foreach ( $widget_files as $file ) {
		require_once AOMARK_LISTINGS_PLUGIN_DIR . $file;
	}

	$widgets_manager->register( new Aomark_Listings_Results_Widget() );
	$widgets_manager->register( new Aomark_Listings_Filter_Widget() );
	$widgets_manager->register( new Aomark_Listings_Map_Widget() );
	$widgets_manager->register( new Aomark_Listings_Field_Widget() );
	$widgets_manager->register( new Aomark_Listings_Gallery_Widget() );
	$widgets_manager->register( new Aomark_Listings_Meta_Widget() );
}
add_action( 'elementor/widgets/register', 'aomark_listings_register_elementor_widgets' );

function aomark_listings_register_elementor_widgets_legacy_hook() {
	if ( ! did_action( 'elementor/widgets/register' ) && class_exists( '\Elementor\Plugin' ) ) {
		aomark_listings_register_elementor_widgets( \Elementor\Plugin::instance()->widgets_manager );
	}
}
add_action( 'elementor/widgets/widgets_registered', 'aomark_listings_register_elementor_widgets_legacy_hook' );
