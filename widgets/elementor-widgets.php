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

	$widgets = [
		new Aomark_Listings_Results_Widget(),
		new Aomark_Listings_Filter_Widget(),
		new Aomark_Listings_Map_Widget(),
		new Aomark_Listings_Field_Widget(),
		new Aomark_Listings_Gallery_Widget(),
		new Aomark_Listings_Meta_Widget(),
	];

	foreach ( $widgets as $widget ) {
		if ( method_exists( $widgets_manager, 'register' ) ) {
			$widgets_manager->register( $widget );
		} elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( $widget );
		}
	}
}
add_action( 'elementor/widgets/register', 'aomark_listings_register_elementor_widgets' );

function aomark_listings_register_elementor_widgets_legacy_hook() {
	if ( ! did_action( 'elementor/widgets/register' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::instance()->widgets_manager ) ) {
		aomark_listings_register_elementor_widgets( \Elementor\Plugin::instance()->widgets_manager );
	}
}

function aomark_listings_maybe_register_elementor_legacy_hook() {
	if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '<' ) ) {
		add_action( 'elementor/widgets/widgets_registered', 'aomark_listings_register_elementor_widgets_legacy_hook' );
	}
}
add_action( 'plugins_loaded', 'aomark_listings_maybe_register_elementor_legacy_hook', 1 );
