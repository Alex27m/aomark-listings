<?php
/**
 * Seed a pre-schema-v2 (Aomark Listings 3.4-style) registry before activation.
 *
 * This file is test-only and must never be included in the release ZIP.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$aomark_advanced_legacy_registry = array(
	'legacy_property' => array(
		'id'           => 'legacy_property',
		'preset'       => 'real_estate',
		'post_type'    => 'aomark_qa_property',
		'singular'     => 'Legacy Property',
		'plural'       => 'Legacy Properties',
		'menu_icon'    => 'dashicons-building',
		'has_archive'  => true,
		'show_in_rest' => true,
		'taxonomies'   => array(
			array(
				'id'           => 'location',
				'slug'         => 'aomark_qa_location',
				'singular'     => 'Location',
				'plural'       => 'Locations',
				'hierarchical' => true,
				'filterable'   => true,
			),
			array(
				'id'           => 'features',
				'slug'         => 'aomark_qa_feature',
				'singular'     => 'Feature',
				'plural'       => 'Features',
				'hierarchical' => false,
				'filterable'   => true,
			),
		),
		'fields'       => array(
			array(
				'id'         => 'price',
				'key'        => 'aomark_qa_price',
				'label'      => 'Price',
				'type'       => 'price',
				'filterable' => true,
				'card'       => true,
			),
			array(
				'id'         => 'legacy_reference',
				'key'        => 'aomark_qa_legacy_reference',
				'label'      => 'Legacy reference',
				'type'       => 'text',
				'filterable' => true,
				'card'       => true,
			),
			array(
				'id'         => 'location',
				'key'        => 'aomark_qa_map_location',
				'label'      => 'Map location',
				'type'       => 'location',
				'filterable' => false,
				'card'       => false,
			),
		),
		// Unknown legacy data proves the rollback copy is byte-for-byte data
		// preservation, not a sanitized reconstruction of the registry.
		'legacy_extension' => array(
			'source_version' => '3.4.0',
			'retain_exactly' => true,
		),
	),
);

delete_option( 'aomark_listings_models_backup_v1' );
delete_option( 'aomark_listings_advanced_qa_result' );
update_option( 'aomark_listings_models', $aomark_advanced_legacy_registry, false );
update_option( 'aomark_listings_schema_version', 1, false );
update_option( 'aomark_listings_advanced_qa_source_version', '3.4.0', false );
update_option( 'aomark_listings_advanced_qa_seed_hash', hash( 'sha256', serialize( $aomark_advanced_legacy_registry ) ), false );

echo wp_json_encode(
	array(
		'status'         => 'seeded',
		'source_version' => '3.4.0',
		'schema_version' => 1,
		'registry_hash'  => hash( 'sha256', serialize( $aomark_advanced_legacy_registry ) ),
	)
);
