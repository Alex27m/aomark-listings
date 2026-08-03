<?php
/**
 * Disposable integration checks for higher-risk Aomark Listings release gates.
 *
 * This harness runs only in WordPress Playground and stays outside the package
 * allowlist. It intentionally uses a bounded 300-record dataset; it is not a
 * 5,000-record performance qualification.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$aomark_advanced_option = 'aomark_listings_advanced_qa_result';
$aomark_advanced_checks = array();
$aomark_advanced_timings = array();

$aomark_advanced_fail = static function ( $message, $details = array() ) use ( $aomark_advanced_option ) {
	$result = array(
		'schema'  => 1,
		'status'  => 'fail',
		'message' => (string) $message,
		'details' => is_array( $details ) ? $details : array(),
	);

	update_option( $aomark_advanced_option, $result, false );
	throw new RuntimeException( (string) $message . ' ' . wp_json_encode( $result['details'] ) );
};

$aomark_advanced_assert = static function ( $name, $condition ) use ( &$aomark_advanced_checks ) {
	$aomark_advanced_checks[ (string) $name ] = (bool) $condition;
};

$aomark_advanced_required = array(
	'aomark_listings_get_models',
	'aomark_listings_get_model_exact',
	'aomark_listings_register_content_types',
	'aomark_listings_maybe_upgrade_schema',
	'aomark_listings_build_query_args',
	'aomark_listings_get_query',
	'aomark_listings_get_map_items',
	'aomark_listings_render_results_inner',
	'aomark_listings_context_post_id',
	'aomark_listings_create_signed_descriptor',
	'aomark_listings_verify_signed_descriptor',
	'aomark_listings_sanitize_results_settings',
	'aomark_listings_sanitize_ajax_filters',
	'aomark_listings_save_meta_box',
	'aomark_listings_handle_admin_actions',
);

foreach ( $aomark_advanced_required as $aomark_advanced_function ) {
	if ( ! function_exists( $aomark_advanced_function ) ) {
		$aomark_advanced_fail(
			'A required advanced QA contract is unavailable.',
			array( 'missing_function' => $aomark_advanced_function )
		);
	}
}

if ( ! defined( 'AOMARK_LISTINGS_VERSION' ) || ! defined( 'AOMARK_LISTINGS_SCHEMA_VERSION' ) ) {
	$aomark_advanced_fail( 'Plugin version or schema constants are unavailable.' );
}

// Verify the real activation upgrade preserved the raw pre-v2 registry.
$aomark_advanced_seed_hash = (string) get_option( 'aomark_listings_advanced_qa_seed_hash', '' );
$aomark_advanced_live_registry = get_option( AOMARK_LISTINGS_OPTION, false );
$aomark_advanced_backup = get_option( 'aomark_listings_models_backup_v1', false );
$aomark_advanced_backup_hash = is_array( $aomark_advanced_backup ) ? hash( 'sha256', serialize( $aomark_advanced_backup ) ) : '';
$aomark_advanced_live_hash = is_array( $aomark_advanced_live_registry ) ? hash( 'sha256', serialize( $aomark_advanced_live_registry ) ) : '';

$aomark_advanced_assert( 'legacy_source_is_3_4', '3.4.0' === get_option( 'aomark_listings_advanced_qa_source_version' ) );
$aomark_advanced_assert( 'schema_upgraded_to_current', AOMARK_LISTINGS_SCHEMA_VERSION === absint( get_option( AOMARK_LISTINGS_SCHEMA_OPTION, 0 ) ) );
$aomark_advanced_assert( 'legacy_registry_left_in_place', '' !== $aomark_advanced_seed_hash && hash_equals( $aomark_advanced_seed_hash, $aomark_advanced_live_hash ) );
$aomark_advanced_assert( 'legacy_backup_is_exact', '' !== $aomark_advanced_seed_hash && hash_equals( $aomark_advanced_seed_hash, $aomark_advanced_backup_hash ) );
$aomark_advanced_assert(
	'legacy_unknown_data_preserved',
	isset( $aomark_advanced_backup['legacy_property']['legacy_extension']['retain_exactly'] )
		&& true === $aomark_advanced_backup['legacy_property']['legacy_extension']['retain_exactly']
);

// Add a disposable scale model after the legacy backup is created. Re-running
// the upgrader must never replace the original rollback snapshot.
$aomark_advanced_scale_model = aomark_listings_preset_model( 'real_estate', 'qa_scale' );
for ( $aomark_advanced_field_index = 1; $aomark_advanced_field_index <= 24; $aomark_advanced_field_index++ ) {
	$aomark_advanced_scale_model['fields'][] = array(
		'id'         => sprintf( 'stress_filter_%02d', $aomark_advanced_field_index ),
		'key'        => sprintf( 'aomark_qa_stress_filter_%02d', $aomark_advanced_field_index ),
		'label'      => sprintf( 'Stress filter %02d', $aomark_advanced_field_index ),
		'type'       => 'text',
		'filterable' => true,
		'card'       => false,
	);
}
$aomark_advanced_scale_model = aomark_listings_sanitize_model( $aomark_advanced_scale_model );
$aomark_advanced_registry = $aomark_advanced_live_registry;
$aomark_advanced_registry['qa_scale'] = $aomark_advanced_scale_model;
$aomark_advanced_registry_validation = aomark_listings_validate_model_registry( $aomark_advanced_registry );
if ( is_wp_error( $aomark_advanced_registry_validation ) ) {
	$aomark_advanced_fail(
		'The disposable scale model is invalid.',
		array( 'errors' => $aomark_advanced_registry_validation->get_error_messages() )
	);
}
update_option( AOMARK_LISTINGS_OPTION, $aomark_advanced_registry, false );
aomark_listings_maybe_upgrade_schema();
$aomark_advanced_backup_after = get_option( 'aomark_listings_models_backup_v1', false );
$aomark_advanced_assert( 'legacy_backup_is_idempotent', $aomark_advanced_backup === $aomark_advanced_backup_after );

aomark_listings_register_content_types();
$aomark_advanced_scale_model = aomark_listings_get_model_exact( 'qa_scale', false );
if ( ! $aomark_advanced_scale_model || ! post_type_exists( $aomark_advanced_scale_model['post_type'] ) ) {
	$aomark_advanced_fail( 'The disposable scale listing type was not registered.' );
}

$aomark_advanced_taxonomies = array();
foreach ( $aomark_advanced_scale_model['taxonomies'] as $aomark_advanced_taxonomy ) {
	$aomark_advanced_taxonomies[ $aomark_advanced_taxonomy['id'] ] = $aomark_advanced_taxonomy;
}
if ( empty( $aomark_advanced_taxonomies['features']['slug'] ) || ! taxonomy_exists( $aomark_advanced_taxonomies['features']['slug'] ) ) {
	$aomark_advanced_fail( 'The disposable scale marker taxonomy is unavailable.' );
}
$aomark_advanced_marker_taxonomy = $aomark_advanced_taxonomies['features']['slug'];
$aomark_advanced_marker = get_term_by( 'slug', 'aomark-advanced-qa', $aomark_advanced_marker_taxonomy );
if ( ! $aomark_advanced_marker instanceof WP_Term ) {
	$aomark_advanced_marker_created = wp_insert_term(
		'Aomark advanced QA',
		$aomark_advanced_marker_taxonomy,
		array( 'slug' => 'aomark-advanced-qa' )
	);
	if ( is_wp_error( $aomark_advanced_marker_created ) ) {
		$aomark_advanced_fail(
			'The disposable scale marker could not be created.',
			array( 'error' => $aomark_advanced_marker_created->get_error_message() )
		);
	}
	$aomark_advanced_marker_id = (int) $aomark_advanced_marker_created['term_id'];
} else {
	$aomark_advanced_marker_id = (int) $aomark_advanced_marker->term_id;
}

$aomark_advanced_fields = array();
foreach ( $aomark_advanced_scale_model['fields'] as $aomark_advanced_field ) {
	$aomark_advanced_fields[ $aomark_advanced_field['id'] ] = $aomark_advanced_field;
}
foreach ( array( 'price', 'location', 'stress_filter_01' ) as $aomark_advanced_required_field ) {
	if ( empty( $aomark_advanced_fields[ $aomark_advanced_required_field ] ) ) {
		$aomark_advanced_fail(
			'The disposable scale field contract is incomplete.',
			array( 'missing_field' => $aomark_advanced_required_field )
		);
	}
}

$aomark_advanced_original_user = get_current_user_id();
$aomark_advanced_original_post = $_POST;
$aomark_advanced_original_get = $_GET;

$aomark_advanced_user = static function ( $login, $role ) use ( $aomark_advanced_fail ) {
	$user_id = username_exists( $login );
	if ( ! $user_id ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'user_email' => $login . '@example.test',
				'role'       => $role,
			)
		);
	}
	if ( is_wp_error( $user_id ) ) {
		$aomark_advanced_fail(
			'A disposable QA user could not be created.',
			array(
				'login' => $login,
				'error' => $user_id->get_error_message(),
			)
		);
	}

	$user = new WP_User( (int) $user_id );
	$user->set_role( $role );
	return (int) $user_id;
};

$aomark_advanced_editor_id = $aomark_advanced_user( 'aomark_qa_editor', 'editor' );
$aomark_advanced_subscriber_id = $aomark_advanced_user( 'aomark_qa_subscriber', 'subscriber' );
$aomark_advanced_admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
if ( empty( $aomark_advanced_admin_ids ) ) {
	$aomark_advanced_fail( 'The disposable site has no administrator account.' );
}
$aomark_advanced_admin_id = (int) reset( $aomark_advanced_admin_ids );

$aomark_advanced_upsert_post = static function ( $slug, $title, $status, $password = '' ) use (
	$aomark_advanced_admin_id,
	$aomark_advanced_fail,
	$aomark_advanced_fields,
	$aomark_advanced_marker_id,
	$aomark_advanced_marker_taxonomy,
	$aomark_advanced_scale_model
) {
	$existing = get_page_by_path( $slug, OBJECT, $aomark_advanced_scale_model['post_type'] );
	$properties = array(
		'post_title'    => $title,
		'post_name'     => $slug,
		'post_type'     => $aomark_advanced_scale_model['post_type'],
		'post_status'   => $status,
		'post_password' => $password,
		'post_author'   => $aomark_advanced_admin_id,
		'post_content'  => '<p>Disposable Aomark Listings advanced QA content.</p>',
	);
	if ( $existing instanceof WP_Post ) {
		$properties['ID'] = (int) $existing->ID;
		$post_id = wp_update_post( $properties, true );
	} else {
		$post_id = wp_insert_post( $properties, true );
	}
	if ( is_wp_error( $post_id ) ) {
		$aomark_advanced_fail(
			'A disposable QA listing could not be written.',
			array(
				'slug'  => $slug,
				'error' => $post_id->get_error_message(),
			)
		);
	}

	$post_id = (int) $post_id;
	$assigned = wp_set_object_terms( $post_id, array( $aomark_advanced_marker_id ), $aomark_advanced_marker_taxonomy, false );
	if ( is_wp_error( $assigned ) ) {
		$aomark_advanced_fail(
			'The disposable QA marker could not be assigned.',
			array(
				'post_id' => $post_id,
				'error'   => $assigned->get_error_message(),
			)
		);
	}

	$numeric_index = absint( substr( $slug, -4 ) );
	update_post_meta( $post_id, aomark_listings_meta_key( $aomark_advanced_fields['price'] ), (string) ( 100000 + $numeric_index ) );
	update_post_meta( $post_id, aomark_listings_meta_key( $aomark_advanced_fields['stress_filter_01'] ), 'bounded-fixture' );
	$location_key = aomark_listings_meta_key( $aomark_advanced_fields['location'] );
	update_post_meta( $post_id, $location_key . '_address', sprintf( 'QA Scale Street %d, Belgrade', $numeric_index ) );
	update_post_meta( $post_id, $location_key . '_lat', number_format( 44.700000 + ( ( $numeric_index % 100 ) * 0.0001 ), 6, '.', '' ) );
	update_post_meta( $post_id, $location_key . '_lng', number_format( 20.400000 + ( ( $numeric_index % 100 ) * 0.0001 ), 6, '.', '' ) );

	return $post_id;
};

$aomark_advanced_seed_started = microtime( true );
wp_defer_term_counting( true );
$aomark_advanced_public_ids = array();
for ( $aomark_advanced_index = 1; $aomark_advanced_index <= 300; $aomark_advanced_index++ ) {
	$aomark_advanced_public_ids[] = $aomark_advanced_upsert_post(
		sprintf( 'aomark-advanced-qa-%04d', $aomark_advanced_index ),
		sprintf( 'Aomark advanced public %04d', $aomark_advanced_index ),
		'publish'
	);
}
$aomark_advanced_draft_id = $aomark_advanced_upsert_post( 'aomark-advanced-qa-draft-9001', 'Aomark advanced protected draft', 'draft' );
$aomark_advanced_private_id = $aomark_advanced_upsert_post( 'aomark-advanced-qa-private-9002', 'Aomark advanced protected private', 'private' );
$aomark_advanced_password_id = $aomark_advanced_upsert_post( 'aomark-advanced-qa-password-9003', 'Aomark advanced protected password', 'publish', 'qa-password' );
wp_defer_term_counting( false );
clean_term_cache( $aomark_advanced_marker_id, $aomark_advanced_marker_taxonomy );
$aomark_advanced_timings['seed_seconds'] = round( microtime( true ) - $aomark_advanced_seed_started, 4 );

$aomark_advanced_settings = array(
	'model_id'         => $aomark_advanced_scale_model['id'],
	'per_page'         => 999,
	'pagination'       => 'numbered',
	'read_url_filters' => 'yes',
	'show_filter'      => 'no',
	'show_count'       => 'yes',
	'show_sort'        => 'yes',
	'taxonomy_filters' => array(
		array(
			'taxonomy_id' => $aomark_advanced_scale_model['id'] . ':features',
			'terms'       => 'aomark-advanced-qa',
		),
	),
);

$_GET = array();
wp_set_current_user( 0 );
$aomark_advanced_query_started = microtime( true );
$aomark_advanced_built = aomark_listings_build_query_args( $aomark_advanced_settings, PHP_INT_MAX );
$aomark_advanced_query_data = aomark_listings_get_query( $aomark_advanced_settings, 1 );
$aomark_advanced_query_ids = wp_list_pluck( $aomark_advanced_query_data['query']->posts, 'ID' );
$aomark_advanced_rendered = aomark_listings_render_results_inner( $aomark_advanced_settings, 1 );
$aomark_advanced_timings['query_and_render_seconds'] = round( microtime( true ) - $aomark_advanced_query_started, 4 );

$aomark_advanced_map_started = microtime( true );
$aomark_advanced_map_items = aomark_listings_get_map_items(
	array_merge(
		$aomark_advanced_settings,
		array( 'map_limit' => 999 )
	)
);
$aomark_advanced_timings['map_seconds'] = round( microtime( true ) - $aomark_advanced_map_started, 4 );
$aomark_advanced_map_ids = wp_list_pluck( $aomark_advanced_map_items, 'id' );
$aomark_advanced_protected_ids = array( $aomark_advanced_draft_id, $aomark_advanced_private_id, $aomark_advanced_password_id );

$aomark_advanced_assert( 'query_per_page_clamped_to_60', 60 === (int) $aomark_advanced_built['args']['posts_per_page'] );
$aomark_advanced_assert( 'query_page_clamped_to_200', 200 === (int) $aomark_advanced_built['args']['paged'] );
$aomark_advanced_assert( 'query_status_is_publish_only', 'publish' === $aomark_advanced_built['args']['post_status'] );
$aomark_advanced_assert( 'query_excludes_passwords_at_sql_layer', false === $aomark_advanced_built['args']['has_password'] );
$aomark_advanced_assert( 'anonymous_query_total_is_300', 300 === (int) $aomark_advanced_query_data['query']->found_posts );
$aomark_advanced_assert( 'anonymous_query_page_is_bounded_to_60', 60 === count( $aomark_advanced_query_ids ) );
$aomark_advanced_assert( 'anonymous_query_excludes_all_protected_states', empty( array_intersect( $aomark_advanced_protected_ids, $aomark_advanced_query_ids ) ) );
$aomark_advanced_assert( 'render_total_is_300', 300 === (int) $aomark_advanced_rendered['total'] );
$aomark_advanced_assert( 'render_has_five_bounded_pages', 5 === (int) $aomark_advanced_rendered['max_pages'] );
$aomark_advanced_assert( 'render_does_not_leak_protected_titles', false === strpos( $aomark_advanced_rendered['html'], 'Aomark advanced protected' ) );
$aomark_advanced_assert( 'map_limit_clamped_to_200', 200 === count( $aomark_advanced_map_items ) );
$aomark_advanced_assert( 'map_excludes_all_protected_states', empty( array_intersect( $aomark_advanced_protected_ids, $aomark_advanced_map_ids ) ) );
$aomark_advanced_assert( 'anonymous_can_resolve_public_context', $aomark_advanced_public_ids[0] === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_public_ids[0] ), $aomark_advanced_scale_model ) );
$aomark_advanced_assert( 'anonymous_cannot_resolve_draft_context', 0 === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_draft_id ), $aomark_advanced_scale_model ) );
$aomark_advanced_assert( 'anonymous_cannot_resolve_private_context', 0 === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_private_id ), $aomark_advanced_scale_model ) );
$aomark_advanced_assert( 'anonymous_cannot_resolve_password_context', 0 === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_password_id ), $aomark_advanced_scale_model ) );

wp_set_current_user( $aomark_advanced_admin_id );
$aomark_advanced_admin_query = aomark_listings_get_query( $aomark_advanced_settings, 1 );
$aomark_advanced_assert( 'admin_public_query_still_excludes_protected_states', 300 === (int) $aomark_advanced_admin_query['query']->found_posts );
$aomark_advanced_assert( 'admin_can_resolve_draft_context', $aomark_advanced_draft_id === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_draft_id ), $aomark_advanced_scale_model ) );
$aomark_advanced_assert( 'admin_can_resolve_private_context', $aomark_advanced_private_id === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_private_id ), $aomark_advanced_scale_model ) );
$aomark_advanced_assert( 'admin_can_resolve_password_context', $aomark_advanced_password_id === aomark_listings_context_post_id( array( 'model_id' => 'qa_scale', 'post_id' => $aomark_advanced_password_id ), $aomark_advanced_scale_model ) );

// Verify both the admin configuration boundary and per-post edit boundary.
$aomark_advanced_assert( 'admin_has_manage_options', user_can( $aomark_advanced_admin_id, 'manage_options' ) );
$aomark_advanced_assert( 'editor_lacks_manage_options', ! user_can( $aomark_advanced_editor_id, 'manage_options' ) );
$aomark_advanced_assert( 'subscriber_lacks_manage_options', ! user_can( $aomark_advanced_subscriber_id, 'manage_options' ) );
$aomark_advanced_assert( 'editor_can_edit_listing', user_can( $aomark_advanced_editor_id, 'edit_post', $aomark_advanced_public_ids[0] ) );
$aomark_advanced_assert( 'subscriber_cannot_edit_listing', ! user_can( $aomark_advanced_subscriber_id, 'edit_post', $aomark_advanced_public_ids[0] ) );

global $menu;
$aomark_advanced_menu_before = is_array( $menu ) ? $menu : array();
aomark_listings_admin_menu();
$aomark_advanced_menu_capability = '';
foreach ( (array) $menu as $aomark_advanced_menu_item ) {
	if ( isset( $aomark_advanced_menu_item[2] ) && 'aomark-listings' === $aomark_advanced_menu_item[2] ) {
		$aomark_advanced_menu_capability = isset( $aomark_advanced_menu_item[1] ) ? (string) $aomark_advanced_menu_item[1] : '';
		break;
	}
}
$menu = $aomark_advanced_menu_before;
$aomark_advanced_assert( 'admin_menu_requires_manage_options', 'manage_options' === $aomark_advanced_menu_capability );

$aomark_advanced_registry_before_blocked_action = get_option( AOMARK_LISTINGS_OPTION );
wp_set_current_user( $aomark_advanced_editor_id );
if ( ! function_exists( 'set_current_screen' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
}
if ( ! function_exists( 'add_settings_error' ) ) {
	require_once ABSPATH . 'wp-admin/includes/template.php';
}
if ( function_exists( 'set_current_screen' ) ) {
	set_current_screen( 'dashboard' );
}
$_POST = array(
	'aomark_listings_action' => 'delete_model',
	'aomark_listings_nonce'  => wp_create_nonce( 'aomark_listings_admin' ),
	'model_id'               => 'qa_scale',
);
aomark_listings_handle_admin_actions();
$aomark_advanced_assert( 'editor_cannot_mutate_registry', $aomark_advanced_registry_before_blocked_action === get_option( AOMARK_LISTINGS_OPTION ) );

wp_set_current_user( $aomark_advanced_admin_id );
$_POST = array(
	'aomark_listings_action' => 'delete_model',
	'aomark_listings_nonce'  => 'invalid-nonce',
	'model_id'               => 'qa_scale',
);
aomark_listings_handle_admin_actions();
$aomark_advanced_assert( 'invalid_admin_nonce_cannot_mutate_registry', $aomark_advanced_registry_before_blocked_action === get_option( AOMARK_LISTINGS_OPTION ) );

$aomark_advanced_price_key = aomark_listings_meta_key( $aomark_advanced_fields['price'] );
$aomark_advanced_original_price = get_post_meta( $aomark_advanced_public_ids[0], $aomark_advanced_price_key, true );
wp_set_current_user( $aomark_advanced_subscriber_id );
$_POST = array(
	'aomark_listings_meta_nonce' => wp_create_nonce( 'aomark_listings_save_meta' ),
	'aomark_listing_meta'       => array( $aomark_advanced_price_key => '999999' ),
);
aomark_listings_save_meta_box( $aomark_advanced_public_ids[0] );
$aomark_advanced_assert( 'subscriber_cannot_write_listing_meta', $aomark_advanced_original_price === get_post_meta( $aomark_advanced_public_ids[0], $aomark_advanced_price_key, true ) );

wp_set_current_user( $aomark_advanced_editor_id );
$_POST = array(
	'aomark_listings_meta_nonce' => 'invalid-nonce',
	'aomark_listing_meta'       => array( $aomark_advanced_price_key => '999999' ),
);
aomark_listings_save_meta_box( $aomark_advanced_public_ids[0] );
$aomark_advanced_assert( 'invalid_meta_nonce_cannot_write', $aomark_advanced_original_price === get_post_meta( $aomark_advanced_public_ids[0], $aomark_advanced_price_key, true ) );

$_POST = array(
	'aomark_listings_meta_nonce' => wp_create_nonce( 'aomark_listings_save_meta' ),
	'aomark_listing_meta'       => array( $aomark_advanced_price_key => '1.234,50 EUR' ),
);
aomark_listings_save_meta_box( $aomark_advanced_public_ids[0] );
$aomark_advanced_assert( 'editor_valid_nonce_writes_sanitized_meta', '1234.50' === get_post_meta( $aomark_advanced_public_ids[0], $aomark_advanced_price_key, true ) );
update_post_meta( $aomark_advanced_public_ids[0], $aomark_advanced_price_key, $aomark_advanced_original_price );

// Signed descriptor integrity, purpose binding, depth and size rejection.
$aomark_advanced_payload = array(
	'model_id'         => 'qa_scale',
	'per_page'         => 999,
	'columns'          => 999,
	'decimals'         => 999,
	'button_text'      => str_repeat( 'B', 180 ),
	'empty_message'    => str_repeat( 'E', 480 ),
	'results_url'      => 'https://attacker.example/redirect',
	'taxonomy_filters' => array_fill(
		0,
		12,
		array(
			'taxonomy_id' => 'qa_scale:features',
			'terms'       => 'aomark-advanced-qa',
		)
	),
	'meta_filters'     => array_fill(
		0,
		12,
		array(
			'field_id' => 'qa_scale:stress_filter_01',
			'compare'  => 'REGEXP',
			'value'    => str_repeat( 'V', 250 ),
		)
	),
	'card_field_ids'   => array(),
);
for ( $aomark_advanced_field_index = 1; $aomark_advanced_field_index <= 24; $aomark_advanced_field_index++ ) {
	$aomark_advanced_payload['card_field_ids'][] = sprintf( 'qa_scale:stress_filter_%02d', $aomark_advanced_field_index );
}
$aomark_advanced_descriptor = aomark_listings_create_signed_descriptor( 'results', $aomark_advanced_payload );
$aomark_advanced_verified_payload = aomark_listings_verify_signed_descriptor( $aomark_advanced_descriptor, 'results' );
$aomark_advanced_assert( 'valid_descriptor_round_trips', $aomark_advanced_payload === $aomark_advanced_verified_payload );
$aomark_advanced_assert( 'descriptor_is_purpose_bound', false === aomark_listings_verify_signed_descriptor( $aomark_advanced_descriptor, 'map' ) );

$aomark_advanced_tampered_payload = $aomark_advanced_descriptor;
$aomark_advanced_tampered_payload['p'][0] = 'A' === $aomark_advanced_tampered_payload['p'][0] ? 'B' : 'A';
$aomark_advanced_assert( 'descriptor_payload_tamper_is_rejected', false === aomark_listings_verify_signed_descriptor( $aomark_advanced_tampered_payload, 'results' ) );
$aomark_advanced_tampered_signature = $aomark_advanced_descriptor;
$aomark_advanced_tampered_signature['s'][0] = 'a' === $aomark_advanced_tampered_signature['s'][0] ? 'b' : 'a';
$aomark_advanced_assert( 'descriptor_signature_tamper_is_rejected', false === aomark_listings_verify_signed_descriptor( $aomark_advanced_tampered_signature, 'results' ) );
$aomark_advanced_wrong_version = $aomark_advanced_descriptor;
$aomark_advanced_wrong_version['v'] = 2;
$aomark_advanced_assert( 'descriptor_wrong_version_is_rejected', false === aomark_listings_verify_signed_descriptor( $aomark_advanced_wrong_version, 'results' ) );
$aomark_advanced_assert( 'unsigned_descriptor_is_rejected', false === aomark_listings_verify_signed_descriptor( array( 'p' => $aomark_advanced_descriptor['p'] ), 'results' ) );
$aomark_advanced_oversized = aomark_listings_create_signed_descriptor( 'results', array( 'blob' => str_repeat( 'x', 26000 ) ) );
$aomark_advanced_assert( 'oversized_descriptor_is_rejected', false === aomark_listings_verify_signed_descriptor( $aomark_advanced_oversized, 'results' ) );
$aomark_advanced_deep_payload = array( 'leaf' => true );
for ( $aomark_advanced_depth = 0; $aomark_advanced_depth < 24; $aomark_advanced_depth++ ) {
	$aomark_advanced_deep_payload = array( 'nested' => $aomark_advanced_deep_payload );
}
$aomark_advanced_deep_descriptor = aomark_listings_create_signed_descriptor( 'results', $aomark_advanced_deep_payload );
$aomark_advanced_assert( 'overdeep_descriptor_is_rejected', false === aomark_listings_verify_signed_descriptor( $aomark_advanced_deep_descriptor, 'results' ) );

$aomark_advanced_sanitized_settings = aomark_listings_sanitize_results_settings( $aomark_advanced_verified_payload );
$aomark_advanced_assert( 'descriptor_per_page_is_clamped', 60 === $aomark_advanced_sanitized_settings['per_page'] );
$aomark_advanced_assert( 'descriptor_columns_are_clamped', 6 === $aomark_advanced_sanitized_settings['columns'] );
$aomark_advanced_assert( 'descriptor_decimals_are_clamped', 4 === $aomark_advanced_sanitized_settings['decimals'] );
$aomark_advanced_assert( 'descriptor_button_text_is_bounded', 100 === strlen( $aomark_advanced_sanitized_settings['button_text'] ) );
$aomark_advanced_assert( 'descriptor_empty_message_is_bounded', 300 === strlen( $aomark_advanced_sanitized_settings['empty_message'] ) );
$aomark_advanced_assert( 'descriptor_external_redirect_is_rejected', '' === $aomark_advanced_sanitized_settings['results_url'] );
$aomark_advanced_assert( 'descriptor_taxonomy_filters_are_bounded', 10 === count( $aomark_advanced_sanitized_settings['taxonomy_filters'] ) );
$aomark_advanced_assert( 'descriptor_meta_filters_are_bounded', 10 === count( $aomark_advanced_sanitized_settings['meta_filters'] ) );
$aomark_advanced_assert( 'descriptor_meta_compare_is_allowlisted', '=' === $aomark_advanced_sanitized_settings['meta_filters'][0]['compare'] );
$aomark_advanced_assert( 'descriptor_meta_value_is_bounded', 200 === strlen( $aomark_advanced_sanitized_settings['meta_filters'][0]['value'] ) );
$aomark_advanced_assert( 'descriptor_card_fields_are_bounded', 20 === count( $aomark_advanced_sanitized_settings['card_field_ids'] ) );
$aomark_advanced_unknown_descriptor = aomark_listings_create_signed_descriptor( 'results', array( 'model_id' => 'not_a_model' ) );
$aomark_advanced_unknown_settings = aomark_listings_sanitize_results_settings( aomark_listings_verify_signed_descriptor( $aomark_advanced_unknown_descriptor, 'results' ) );
$aomark_advanced_assert( 'signed_unknown_model_fails_closed', null === aomark_listings_get_model_exact( $aomark_advanced_unknown_settings['model_id'], false ) );

// Exercise the public filter allowlist with more valid controls than permitted.
$aomark_advanced_filter_attack = array(
	'alm_model'   => 'qa_scale',
	'alm_keyword' => str_repeat( 'K', 400 ),
	'alm_sort'    => 'drop_table',
	'post_type'   => 'post',
	'author'      => '1',
);
foreach ( $aomark_advanced_scale_model['taxonomies'] as $aomark_advanced_taxonomy ) {
	$aomark_advanced_filter_attack[ aomark_listings_filter_param( $aomark_advanced_taxonomy['id'] ) ] = array_fill( 0, 30, str_repeat( 'term-', 30 ) );
}
for ( $aomark_advanced_field_index = 1; $aomark_advanced_field_index <= 24; $aomark_advanced_field_index++ ) {
	$aomark_advanced_filter_attack[ aomark_listings_filter_param( sprintf( 'field_stress_filter_%02d', $aomark_advanced_field_index ) ) ] = str_repeat( 'F', 350 );
}
$aomark_advanced_sanitized_filters = aomark_listings_sanitize_ajax_filters( http_build_query( $aomark_advanced_filter_attack ), $aomark_advanced_scale_model );
$aomark_advanced_filter_values_bounded = true;
foreach ( $aomark_advanced_sanitized_filters as $aomark_advanced_filter_value ) {
	if ( is_array( $aomark_advanced_filter_value ) ) {
		if ( count( $aomark_advanced_filter_value ) > 20 ) {
			$aomark_advanced_filter_values_bounded = false;
		}
		foreach ( $aomark_advanced_filter_value as $aomark_advanced_filter_item ) {
			if ( strlen( $aomark_advanced_filter_item ) > 100 ) {
				$aomark_advanced_filter_values_bounded = false;
			}
		}
	} elseif ( strlen( $aomark_advanced_filter_value ) > 200 ) {
		$aomark_advanced_filter_values_bounded = false;
	}
}
$aomark_advanced_assert( 'ajax_filters_are_capped_at_20_active_controls', count( $aomark_advanced_sanitized_filters ) <= 20 );
$aomark_advanced_assert( 'ajax_filter_values_are_bounded', $aomark_advanced_filter_values_bounded );
$aomark_advanced_assert( 'ajax_keyword_is_bounded_to_200', 200 === strlen( $aomark_advanced_sanitized_filters['alm_keyword'] ) );
$aomark_advanced_assert( 'ajax_sort_is_allowlisted', ! isset( $aomark_advanced_sanitized_filters['alm_sort'] ) );
$aomark_advanced_assert( 'ajax_unknown_query_keys_are_dropped', ! isset( $aomark_advanced_sanitized_filters['post_type'] ) && ! isset( $aomark_advanced_sanitized_filters['author'] ) );
$aomark_advanced_assert( 'public_ajax_hook_is_registered', false !== has_action( 'wp_ajax_nopriv_aomark_listings_results', 'aomark_listings_ajax_results' ) );

$_POST = $aomark_advanced_original_post;
$_GET = $aomark_advanced_original_get;
wp_set_current_user( $aomark_advanced_original_user );

$aomark_advanced_failed_checks = array();
foreach ( $aomark_advanced_checks as $aomark_advanced_check_name => $aomark_advanced_check_passed ) {
	if ( ! $aomark_advanced_check_passed ) {
		$aomark_advanced_failed_checks[] = $aomark_advanced_check_name;
	}
}
if ( ! empty( $aomark_advanced_failed_checks ) ) {
	$aomark_advanced_fail(
		'One or more advanced Listings QA checks failed.',
		array(
			'failed_checks' => $aomark_advanced_failed_checks,
			'checks'        => $aomark_advanced_checks,
			'timings'       => $aomark_advanced_timings,
		)
	);
}

$aomark_advanced_result = array(
	'schema'      => 1,
	'status'      => 'pass',
	'plugin'      => array(
		'version'        => AOMARK_LISTINGS_VERSION,
		'schema_version' => AOMARK_LISTINGS_SCHEMA_VERSION,
	),
	'environment' => array(
		'wordpress' => get_bloginfo( 'version' ),
		'php'       => PHP_VERSION,
		'database'  => get_class( $GLOBALS['wpdb'] ),
	),
	'scope'       => array(
		'public_records' => 300,
		'protected_cases' => array( 'draft', 'private', 'password-protected publish' ),
		'note'           => 'Bounded 300-record functional stress smoke; this is not a 5,000-record performance or Core Web Vitals qualification.',
	),
	'legacy'      => array(
		'source_version' => '3.4.0',
		'source_hash'    => $aomark_advanced_seed_hash,
		'backup_hash'    => $aomark_advanced_backup_hash,
	),
	'fixture'     => array(
		'model_id'       => $aomark_advanced_scale_model['id'],
		'post_type'      => $aomark_advanced_scale_model['post_type'],
		'marker_term_id' => $aomark_advanced_marker_id,
		'public_first'   => $aomark_advanced_public_ids[0],
		'public_last'    => end( $aomark_advanced_public_ids ),
		'draft_id'       => $aomark_advanced_draft_id,
		'private_id'     => $aomark_advanced_private_id,
		'password_id'    => $aomark_advanced_password_id,
	),
	'timings'     => $aomark_advanced_timings,
	'checks'      => $aomark_advanced_checks,
);

update_option( $aomark_advanced_option, $aomark_advanced_result, false );
echo wp_json_encode( $aomark_advanced_result );
