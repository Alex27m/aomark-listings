<?php
/**
 * Deterministic, disposable WordPress Playground fixture for Listings QA.
 *
 * This harness is test-only and must not be included in the release ZIP.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$aomark_qa_option             = 'aomark_listings_qa_result';
$aomark_qa_expect_elementor   = defined( 'AOMARK_LISTINGS_QA_EXPECT_ELEMENTOR' ) && AOMARK_LISTINGS_QA_EXPECT_ELEMENTOR;
$aomark_qa_expect_woocommerce = defined( 'AOMARK_LISTINGS_QA_EXPECT_WOOCOMMERCE' ) && AOMARK_LISTINGS_QA_EXPECT_WOOCOMMERCE;

$aomark_qa_fail = static function ( $message, $details = array() ) use ( $aomark_qa_option ) {
	$result = array(
		'schema'  => 1,
		'status'  => 'fail',
		'message' => (string) $message,
		'details' => is_array( $details ) ? $details : array(),
	);

	update_option( $aomark_qa_option, $result, false );
	throw new RuntimeException( (string) $message . ' ' . wp_json_encode( $result['details'] ) );
};

$aomark_qa_required_functions = array(
	'aomark_listings_get_models',
	'aomark_listings_get_model',
	'aomark_listings_get_field',
	'aomark_listings_register_content_types',
	'aomark_listings_render_filter',
	'aomark_listings_render_results',
	'aomark_listings_render_results_inner',
	'aomark_listings_render_map',
	'aomark_listings_render_meta',
	'aomark_listings_get_map_items',
);

foreach ( $aomark_qa_required_functions as $aomark_qa_required_function ) {
	if ( ! function_exists( $aomark_qa_required_function ) ) {
		$aomark_qa_fail(
			'Aomark Listings did not load every required QA contract.',
			array( 'missing_function' => $aomark_qa_required_function )
		);
	}
}

if ( ! defined( 'AOMARK_LISTINGS_PLUGIN_FILE' ) || ! defined( 'AOMARK_LISTINGS_VERSION' ) ) {
	$aomark_qa_fail( 'Aomark Listings activation constants are unavailable.' );
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$aomark_qa_plugin_basename = plugin_basename( AOMARK_LISTINGS_PLUGIN_FILE );
if ( ! is_plugin_active( $aomark_qa_plugin_basename ) ) {
	$aomark_qa_fail(
		'Aomark Listings is not active.',
		array( 'plugin' => $aomark_qa_plugin_basename )
	);
}

$aomark_qa_elementor_loaded = did_action( 'elementor/loaded' ) > 0;
if ( $aomark_qa_expect_elementor !== $aomark_qa_elementor_loaded ) {
	$aomark_qa_fail(
		'Elementor dependency state does not match the Blueprint contract.',
		array(
			'expected' => $aomark_qa_expect_elementor,
			'loaded'   => $aomark_qa_elementor_loaded,
		)
	);
}

$aomark_qa_woocommerce_loaded = class_exists( 'WooCommerce' );
if ( $aomark_qa_expect_woocommerce !== $aomark_qa_woocommerce_loaded ) {
	$aomark_qa_fail(
		'WooCommerce coexistence state does not match the Blueprint contract.',
		array(
			'expected' => $aomark_qa_expect_woocommerce,
			'loaded'   => $aomark_qa_woocommerce_loaded,
		)
	);
}

$aomark_qa_models = aomark_listings_get_models();
if ( ! isset( $aomark_qa_models['real_estate'], $aomark_qa_models['directory'] ) ) {
	$aomark_qa_fail(
		'The clean-install model registry is incomplete.',
		array( 'model_ids' => array_keys( $aomark_qa_models ) )
	);
}

$aomark_qa_model = $aomark_qa_models['real_estate'];
if ( ! post_type_exists( $aomark_qa_model['post_type'] ) ) {
	$aomark_qa_fail(
		'The Real Estate post type is not registered.',
		array( 'post_type' => $aomark_qa_model['post_type'] )
	);
}

$aomark_qa_taxonomies = array();
foreach ( (array) $aomark_qa_model['taxonomies'] as $aomark_qa_taxonomy ) {
	$aomark_qa_taxonomies[ $aomark_qa_taxonomy['id'] ] = $aomark_qa_taxonomy;

	if ( ! taxonomy_exists( $aomark_qa_taxonomy['slug'] ) ) {
		$aomark_qa_fail(
			'A configured Real Estate taxonomy is not registered.',
			array( 'taxonomy' => $aomark_qa_taxonomy['slug'] )
		);
	}

	$aomark_qa_taxonomy_object = get_taxonomy( $aomark_qa_taxonomy['slug'] );
	if (
		! $aomark_qa_taxonomy_object
		|| ! in_array( $aomark_qa_model['post_type'], (array) $aomark_qa_taxonomy_object->object_type, true )
	) {
		$aomark_qa_fail(
			'A configured taxonomy is not attached to its listing post type.',
			array(
				'post_type' => $aomark_qa_model['post_type'],
				'taxonomy'  => $aomark_qa_taxonomy['slug'],
			)
		);
	}
}

foreach ( array( 'location', 'type', 'status', 'features' ) as $aomark_qa_taxonomy_id ) {
	if ( ! isset( $aomark_qa_taxonomies[ $aomark_qa_taxonomy_id ] ) ) {
		$aomark_qa_fail(
			'The Real Estate taxonomy contract is incomplete.',
			array( 'missing_taxonomy_id' => $aomark_qa_taxonomy_id )
		);
	}
}

global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules( false );

if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
	$aomark_qa_fail(
		'Pretty permalinks were not enabled.',
		array( 'permalink_structure' => get_option( 'permalink_structure' ) )
	);
}

$aomark_qa_upsert_term = static function ( $taxonomy, $name, $slug, $description, $parent = 0 ) use ( $aomark_qa_fail ) {
	$existing = get_term_by( 'slug', $slug, $taxonomy );

	if ( $existing instanceof WP_Term ) {
		$updated = wp_update_term(
			(int) $existing->term_id,
			$taxonomy,
			array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $description,
				'parent'      => absint( $parent ),
			)
		);
		if ( is_wp_error( $updated ) ) {
			$aomark_qa_fail(
				'An existing QA term could not be updated.',
				array(
					'taxonomy' => $taxonomy,
					'error'    => $updated->get_error_message(),
				)
			);
		}

		return (int) $existing->term_id;
	}

	$created = wp_insert_term(
		$name,
		$taxonomy,
		array(
			'slug'        => $slug,
			'description' => $description,
			'parent'      => absint( $parent ),
		)
	);
	if ( is_wp_error( $created ) ) {
		$aomark_qa_fail(
			'A QA term could not be created.',
			array(
				'taxonomy' => $taxonomy,
				'error'    => $created->get_error_message(),
			)
		);
	}

	return (int) $created['term_id'];
};

$aomark_qa_location_taxonomy = $aomark_qa_taxonomies['location']['slug'];
$aomark_qa_type_taxonomy     = $aomark_qa_taxonomies['type']['slug'];
$aomark_qa_status_taxonomy   = $aomark_qa_taxonomies['status']['slug'];
$aomark_qa_feature_taxonomy  = $aomark_qa_taxonomies['features']['slug'];

$aomark_qa_term_ids = array();
$aomark_qa_term_ids['belgrade'] = $aomark_qa_upsert_term(
	$aomark_qa_location_taxonomy,
	'Aomark QA Belgrade',
	'aomark-qa-belgrade',
	'<p>Parent location used by the disposable Aomark Listings QA fixture.</p>'
);
$aomark_qa_term_ids['vracar'] = $aomark_qa_upsert_term(
	$aomark_qa_location_taxonomy,
	'Aomark QA Vracar',
	'aomark-qa-vracar',
	'<p>Child location used for archive, filter, and URL checks.</p>',
	$aomark_qa_term_ids['belgrade']
);
$aomark_qa_term_ids['apartment'] = $aomark_qa_upsert_term(
	$aomark_qa_type_taxonomy,
	'Aomark QA Apartment',
	'aomark-qa-apartment',
	'Deterministic apartment fixture.'
);
$aomark_qa_term_ids['house'] = $aomark_qa_upsert_term(
	$aomark_qa_type_taxonomy,
	'Aomark QA House',
	'aomark-qa-house',
	'Deterministic house fixture.'
);
$aomark_qa_term_ids['sale'] = $aomark_qa_upsert_term(
	$aomark_qa_status_taxonomy,
	'Aomark QA For Sale',
	'aomark-qa-for-sale',
	'Deterministic sale status.'
);
$aomark_qa_term_ids['rent'] = $aomark_qa_upsert_term(
	$aomark_qa_status_taxonomy,
	'Aomark QA For Rent',
	'aomark-qa-for-rent',
	'Deterministic rental status.'
);
$aomark_qa_term_ids['fixture'] = $aomark_qa_upsert_term(
	$aomark_qa_feature_taxonomy,
	'Aomark QA Fixture',
	'aomark-qa-fixture',
	'Isolates the disposable QA dataset from other listings.'
);
$aomark_qa_term_ids['terrace'] = $aomark_qa_upsert_term(
	$aomark_qa_feature_taxonomy,
	'Aomark QA Terrace',
	'aomark-qa-terrace',
	'Deterministic feature fixture.'
);

$aomark_qa_fields = array();
foreach ( (array) $aomark_qa_model['fields'] as $aomark_qa_field ) {
	$aomark_qa_fields[ $aomark_qa_field['id'] ] = $aomark_qa_field;
}

foreach ( array( 'price', 'area', 'bedrooms', 'bathrooms', 'property_id', 'location' ) as $aomark_qa_field_id ) {
	if ( ! isset( $aomark_qa_fields[ $aomark_qa_field_id ] ) ) {
		$aomark_qa_fail(
			'The Real Estate field contract is incomplete.',
			array( 'missing_field_id' => $aomark_qa_field_id )
		);
	}
}

$aomark_qa_upsert_listing = static function ( $index, $status, $mapped ) use (
	$aomark_qa_fail,
	$aomark_qa_fields,
	$aomark_qa_model,
	$aomark_qa_taxonomies,
	$aomark_qa_term_ids
) {
	$slug       = sprintf( 'aomark-qa-property-%02d', $index );
	$title      = sprintf( 'Aomark QA Property %02d', $index );
	$post_date  = sprintf( '2025-01-%02d 10:00:00', min( 28, $index ) );
	$properties = array(
		'post_title'    => $title,
		'post_name'     => $slug,
		'post_type'     => $aomark_qa_model['post_type'],
		'post_status'   => $status,
		'post_content'  => sprintf( '<p>Deterministic content for QA property %02d.</p>', $index ),
		'post_excerpt'  => sprintf( 'Theme-neutral excerpt for QA property %02d.', $index ),
		'post_date'     => $post_date,
		'post_date_gmt' => get_gmt_from_date( $post_date ),
	);
	$existing   = get_page_by_path( $slug, OBJECT, $aomark_qa_model['post_type'] );

	if ( $existing instanceof WP_Post ) {
		$properties['ID'] = (int) $existing->ID;
		$post_id          = wp_update_post( $properties, true );
	} else {
		$post_id = wp_insert_post( $properties, true );
	}

	if ( is_wp_error( $post_id ) ) {
		$aomark_qa_fail(
			'A QA listing could not be created.',
			array(
				'slug'  => $slug,
				'error' => $post_id->get_error_message(),
			)
		);
	}

	$post_id = (int) $post_id;
	$terms   = array(
		$aomark_qa_taxonomies['location']['slug'] => array( $aomark_qa_term_ids['vracar'] ),
		$aomark_qa_taxonomies['type']['slug']     => array( 0 === $index % 2 ? $aomark_qa_term_ids['apartment'] : $aomark_qa_term_ids['house'] ),
		$aomark_qa_taxonomies['status']['slug']   => array( 0 === $index % 3 ? $aomark_qa_term_ids['rent'] : $aomark_qa_term_ids['sale'] ),
		$aomark_qa_taxonomies['features']['slug'] => array( $aomark_qa_term_ids['fixture'], $aomark_qa_term_ids['terrace'] ),
	);

	foreach ( $terms as $taxonomy => $assigned_term_ids ) {
		$assigned = wp_set_object_terms( $post_id, $assigned_term_ids, $taxonomy, false );
		if ( is_wp_error( $assigned ) ) {
			$aomark_qa_fail(
				'QA listing terms could not be assigned.',
				array(
					'post_id'  => $post_id,
					'taxonomy' => $taxonomy,
					'error'    => $assigned->get_error_message(),
				)
			);
		}
	}

	$meta_values = array(
		'price'       => (string) ( 95000 + ( $index * 12500 ) ),
		'area'        => (string) ( 55 + ( $index * 4 ) ),
		'bedrooms'    => (string) ( 1 + ( $index % 5 ) ),
		'bathrooms'   => (string) ( 1 + ( $index % 3 ) ),
		'property_id' => sprintf( 'AOM-QA-%03d', $index ),
	);

	foreach ( $meta_values as $field_id => $value ) {
		update_post_meta(
			$post_id,
			aomark_listings_meta_key( $aomark_qa_fields[ $field_id ] ),
			$value
		);
	}

	$location_key = aomark_listings_meta_key( $aomark_qa_fields['location'] );
	if ( $mapped ) {
		update_post_meta( $post_id, $location_key . '_address', sprintf( 'QA Street %d, Belgrade, Serbia', $index ) );
		update_post_meta( $post_id, $location_key . '_lat', number_format( 44.790000 + ( $index * 0.001100 ), 6, '.', '' ) );
		update_post_meta( $post_id, $location_key . '_lng', number_format( 20.450000 + ( $index * 0.001300 ), 6, '.', '' ) );
	} else {
		delete_post_meta( $post_id, $location_key . '_address' );
		delete_post_meta( $post_id, $location_key . '_lat' );
		delete_post_meta( $post_id, $location_key . '_lng' );
	}

	return $post_id;
};

$aomark_qa_published_ids = array();
for ( $aomark_qa_index = 1; $aomark_qa_index <= 13; $aomark_qa_index++ ) {
	$aomark_qa_published_ids[] = $aomark_qa_upsert_listing( $aomark_qa_index, 'publish', true );
}
$aomark_qa_unmapped_id    = $aomark_qa_upsert_listing( 14, 'publish', false );
$aomark_qa_published_ids[] = $aomark_qa_unmapped_id;
$aomark_qa_draft_id       = $aomark_qa_upsert_listing( 15, 'draft', true );

$aomark_qa_page = get_page_by_path( 'aomark-listings-qa' );
if ( $aomark_qa_page instanceof WP_Post ) {
	$aomark_qa_page_id = (int) $aomark_qa_page->ID;
} else {
	$aomark_qa_page_id = wp_insert_post(
		array(
			'post_title'   => 'Aomark Listings QA',
			'post_name'    => 'aomark-listings-qa',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => '<p>Preparing the disposable QA fixture.</p>',
		),
		true
	);
	if ( is_wp_error( $aomark_qa_page_id ) ) {
		$aomark_qa_fail(
			'The public QA page could not be created.',
			array( 'error' => $aomark_qa_page_id->get_error_message() )
		);
	}
}

$aomark_qa_page_url = get_permalink( $aomark_qa_page_id );
$aomark_qa_results_settings = array(
	'model_id'         => $aomark_qa_model['id'],
	'per_page'         => 4,
	'columns'          => 3,
	'pagination'       => 'numbered',
	'ajax'             => 'yes',
	'read_url_filters' => 'yes',
	'show_filter'      => 'yes',
	'show_count'       => 'yes',
	'show_sort'        => 'yes',
	'sort'             => 'date_desc',
	'results_url'      => $aomark_qa_page_url,
	'taxonomy_filters' => array(
		array(
			'taxonomy_id' => $aomark_qa_model['id'] . ':features',
			'terms'       => 'aomark-qa-fixture',
		),
	),
);
$aomark_qa_map_settings = array(
	'model_id'         => $aomark_qa_model['id'],
	'source'           => 'query',
	'map_limit'        => 50,
	'read_url_filters' => 'yes',
	'taxonomy_filters' => $aomark_qa_results_settings['taxonomy_filters'],
	'height'           => 420,
);

$aomark_qa_rendered_inner = aomark_listings_render_results_inner( $aomark_qa_results_settings, 1 );
$aomark_qa_map_items      = aomark_listings_get_map_items( $aomark_qa_map_settings );
$aomark_qa_filter_html    = aomark_listings_render_filter(
	array(
		'model_id'     => $aomark_qa_model['id'],
		'results_url'  => $aomark_qa_page_url,
		'show_reset'   => 'yes',
		'button_text'  => 'Search QA properties',
		'reset_text'   => 'Reset QA filters',
	)
);
$aomark_qa_results_html = aomark_listings_render_results( $aomark_qa_results_settings );
$aomark_qa_map_html     = aomark_listings_render_map( $aomark_qa_map_settings );
$aomark_qa_meta_html    = aomark_listings_render_meta(
	array(
		'model_id'   => $aomark_qa_model['id'],
		'post_id'    => $aomark_qa_published_ids[0],
		'show_labels' => 'yes',
	)
);

$aomark_qa_page_content  = '<div class="aomark-listings-qa">';
$aomark_qa_page_content .= '<h1>Aomark Listings disposable QA</h1>';
$aomark_qa_page_content .= '<p>This page is generated only inside the local WordPress Playground harness.</p>';
$aomark_qa_page_content .= '<section aria-labelledby="aomark-qa-filter-heading"><h2 id="aomark-qa-filter-heading">Filter</h2>';
$aomark_qa_page_content .= '<div class="aomark-listings-component aomark-listings-component--filter" data-aomark-component="filter" data-aomark-connection="qa-primary" data-model-id="' . esc_attr( $aomark_qa_model['id'] ) . '">' . $aomark_qa_filter_html . '</div></section>';
$aomark_qa_page_content .= '<section aria-labelledby="aomark-qa-results-heading"><h2 id="aomark-qa-results-heading">Results</h2>';
$aomark_qa_page_content .= '<div class="aomark-listings-component aomark-listings-component--results" data-aomark-component="results" data-aomark-connection="qa-primary" data-model-id="' . esc_attr( $aomark_qa_model['id'] ) . '">' . $aomark_qa_results_html . '</div></section>';
$aomark_qa_page_content .= '<section aria-labelledby="aomark-qa-map-heading"><h2 id="aomark-qa-map-heading">Map</h2>';
$aomark_qa_page_content .= '<div class="aomark-listings-component aomark-listings-component--map" data-aomark-component="map" data-aomark-connection="qa-primary" data-model-id="' . esc_attr( $aomark_qa_model['id'] ) . '">' . $aomark_qa_map_html . '</div></section>';
$aomark_qa_page_content .= '<section aria-labelledby="aomark-qa-meta-heading"><h2 id="aomark-qa-meta-heading">First listing meta</h2>' . $aomark_qa_meta_html . '</section>';
$aomark_qa_page_content .= '</div>';

$aomark_qa_page_update = wp_update_post(
	array(
		'ID'           => $aomark_qa_page_id,
		'post_title'   => 'Aomark Listings QA',
		'post_status'  => 'publish',
		'post_content' => $aomark_qa_page_content,
	),
	true
);
if ( is_wp_error( $aomark_qa_page_update ) ) {
	$aomark_qa_fail(
		'The public QA page could not be populated.',
		array( 'error' => $aomark_qa_page_update->get_error_message() )
	);
}

flush_rewrite_rules( false );

$aomark_qa_archive_url = get_post_type_archive_link( $aomark_qa_model['post_type'] );
$aomark_qa_term_url    = get_term_link( $aomark_qa_term_ids['vracar'], $aomark_qa_location_taxonomy );
$aomark_qa_first_url   = get_permalink( $aomark_qa_published_ids[0] );

if ( is_wp_error( $aomark_qa_term_url ) ) {
	$aomark_qa_fail(
		'The QA taxonomy archive URL could not be generated.',
		array( 'error' => $aomark_qa_term_url->get_error_message() )
	);
}

$aomark_qa_pretty_permalink_structure = get_option( 'permalink_structure' );
$wp_rewrite->set_permalink_structure( '' );
flush_rewrite_rules( false );

$aomark_qa_plain_archive_url = get_post_type_archive_link( $aomark_qa_model['post_type'] );
$aomark_qa_plain_page_url    = get_permalink( $aomark_qa_page_id );
$aomark_qa_plain_term_url    = get_term_link( $aomark_qa_term_ids['vracar'], $aomark_qa_location_taxonomy );
if ( is_wp_error( $aomark_qa_plain_term_url ) ) {
	$aomark_qa_fail(
		'The plain-permalink QA taxonomy URL could not be generated.',
		array( 'error' => $aomark_qa_plain_term_url->get_error_message() )
	);
}

$aomark_qa_plain_archive_filter = aomark_listings_render_filter(
	array(
		'model_id'    => $aomark_qa_model['id'],
		'results_url' => $aomark_qa_plain_archive_url,
	)
);
$aomark_qa_plain_page_filter = aomark_listings_render_filter(
	array(
		'model_id'    => $aomark_qa_model['id'],
		'results_url' => $aomark_qa_plain_page_url,
	)
);
$aomark_qa_plain_term_filter = aomark_listings_render_filter(
	array(
		'model_id'    => $aomark_qa_model['id'],
		'results_url' => $aomark_qa_plain_term_url,
	)
);

$aomark_qa_plain_archive_args = array();
wp_parse_str( (string) wp_parse_url( $aomark_qa_plain_archive_url, PHP_URL_QUERY ), $aomark_qa_plain_archive_args );
$aomark_qa_plain_term_args = array();
wp_parse_str( (string) wp_parse_url( $aomark_qa_plain_term_url, PHP_URL_QUERY ), $aomark_qa_plain_term_args );
$aomark_qa_plain_term_route_args = aomark_listings_route_query_args( $aomark_qa_plain_term_url, $aomark_qa_model );
$aomark_qa_plain_term_controls_preserved = ! empty( $aomark_qa_plain_term_route_args );
foreach ( $aomark_qa_plain_term_route_args as $aomark_qa_route_key => $aomark_qa_route_value ) {
	if (
		false === strpos( $aomark_qa_plain_term_filter, 'name="' . esc_attr( $aomark_qa_route_key ) . '"' )
		|| false === strpos( $aomark_qa_plain_term_filter, 'value="' . esc_attr( $aomark_qa_route_value ) . '"' )
	) {
		$aomark_qa_plain_term_controls_preserved = false;
		break;
	}
}
$aomark_qa_original_get = $_GET;
$_GET = array(
	'alm_model'   => $aomark_qa_model['id'],
	'alm_keyword' => 'Aomark QA',
);
$aomark_qa_plain_page_two_url = aomark_listings_results_page_url( 2, $aomark_qa_model, $aomark_qa_plain_archive_url );
$_GET = $aomark_qa_original_get;
$aomark_qa_plain_page_two_args = array();
wp_parse_str( (string) wp_parse_url( $aomark_qa_plain_page_two_url, PHP_URL_QUERY ), $aomark_qa_plain_page_two_args );

$wp_rewrite->set_permalink_structure( $aomark_qa_pretty_permalink_structure );
flush_rewrite_rules( false );

$aomark_qa_checks = array(
	'archive_url_is_pretty'      => is_string( $aomark_qa_archive_url ) && false === strpos( $aomark_qa_archive_url, '?post_type=' ),
	'listing_url_is_pretty'      => is_string( $aomark_qa_first_url ) && false === strpos( $aomark_qa_first_url, '?p=' ),
	'page_url_is_pretty'         => is_string( $aomark_qa_page_url ) && false === strpos( $aomark_qa_page_url, '?page_id=' ),
	'term_url_is_pretty'         => is_string( $aomark_qa_term_url ) && false === strpos( $aomark_qa_term_url, '?taxonomy=' ),
	'published_query_count'      => 14 === (int) $aomark_qa_rendered_inner['total'],
	'numbered_pagination_exists' => 4 === (int) $aomark_qa_rendered_inner['max_pages'],
	'map_query_excludes_unmapped' => 13 === count( $aomark_qa_map_items ),
	'draft_is_not_public'        => 'draft' === get_post_status( $aomark_qa_draft_id ),
	'filter_rendered'            => false !== strpos( $aomark_qa_filter_html, 'aomark-listings-filter' ),
	'results_rendered'           => false !== strpos( $aomark_qa_results_html, 'aomark-listings-results' ),
	'integrated_filter_action_preserved' => false !== strpos( $aomark_qa_results_html, 'action="' . esc_attr( $aomark_qa_page_url ) . '"' ),
	'plain_archive_route_exists' => isset( $aomark_qa_plain_archive_args['post_type'] ) && $aomark_qa_model['post_type'] === $aomark_qa_plain_archive_args['post_type'],
	'plain_archive_filter_route_preserved' => false !== strpos( $aomark_qa_plain_archive_filter, 'name="post_type"' ),
	'plain_page_filter_route_preserved' => false !== strpos( $aomark_qa_plain_page_filter, 'name="page_id"' ),
	'plain_term_filter_route_preserved' => $aomark_qa_plain_term_controls_preserved && $aomark_qa_plain_term_route_args === $aomark_qa_plain_term_args,
	'plain_pagination_route_preserved' => isset( $aomark_qa_plain_page_two_args['post_type'], $aomark_qa_plain_page_two_args['alm_model'], $aomark_qa_plain_page_two_args['alm_page'] )
		&& $aomark_qa_model['post_type'] === $aomark_qa_plain_page_two_args['post_type']
		&& $aomark_qa_model['id'] === $aomark_qa_plain_page_two_args['alm_model']
		&& 2 === (int) $aomark_qa_plain_page_two_args['alm_page'],
	'map_rendered'               => false !== strpos( $aomark_qa_map_html, 'data-map-config=' ),
	'meta_rendered'              => false !== strpos( $aomark_qa_meta_html, 'aomark-listings-meta' ),
);
$aomark_qa_failed_checks = array();

foreach ( $aomark_qa_checks as $aomark_qa_check_name => $aomark_qa_check_passed ) {
	if ( ! $aomark_qa_check_passed ) {
		$aomark_qa_failed_checks[] = $aomark_qa_check_name;
	}
}

if ( ! empty( $aomark_qa_failed_checks ) ) {
	$aomark_qa_fail(
		'One or more deterministic Listings QA checks failed.',
		array(
			'failed_checks' => $aomark_qa_failed_checks,
			'checks'        => $aomark_qa_checks,
		)
	);
}

$aomark_qa_result = array(
	'schema'      => 1,
	'status'      => 'pass',
	'plugin'      => array(
		'basename' => $aomark_qa_plugin_basename,
		'version'  => AOMARK_LISTINGS_VERSION,
		'active'   => true,
	),
	'environment' => array(
		'wordpress'  => get_bloginfo( 'version' ),
		'php'        => PHP_VERSION,
		'theme'      => get_stylesheet(),
		'elementor'  => $aomark_qa_elementor_loaded ? ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'loaded' ) : false,
		'woocommerce' => $aomark_qa_woocommerce_loaded ? ( defined( 'WC_VERSION' ) ? WC_VERSION : 'loaded' ) : false,
	),
	'models'      => array(
		'ids'       => array_keys( $aomark_qa_models ),
		'post_type' => $aomark_qa_model['post_type'],
		'taxonomies' => wp_list_pluck( $aomark_qa_model['taxonomies'], 'slug', 'id' ),
	),
	'fixture'     => array(
		'page_id'       => (int) $aomark_qa_page_id,
		'page_url'      => $aomark_qa_page_url,
		'listing_ids'   => array_map( 'absint', $aomark_qa_published_ids ),
		'unmapped_id'   => (int) $aomark_qa_unmapped_id,
		'draft_id'      => (int) $aomark_qa_draft_id,
		'term_ids'      => array_map( 'absint', $aomark_qa_term_ids ),
		'published'     => (int) $aomark_qa_rendered_inner['total'],
		'mapped'        => count( $aomark_qa_map_items ),
		'result_pages'  => (int) $aomark_qa_rendered_inner['max_pages'],
	),
	'routes'      => array(
		'archive' => $aomark_qa_archive_url,
		'listing' => $aomark_qa_first_url,
		'term'    => $aomark_qa_term_url,
	),
	'checks'      => $aomark_qa_checks,
);

update_option( $aomark_qa_option, $aomark_qa_result, false );
echo wp_json_encode( $aomark_qa_result );
