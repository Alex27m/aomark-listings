<?php
/**
 * Lightweight model-contract tests that do not require a WordPress database.
 *
 * The production integration suite should still run against WordPress. This
 * file protects deterministic identifiers and sanitization on every PHP matrix.
 */

define( 'ABSPATH', __DIR__ );

$aomark_test_options = [];

function __( $text ) {
	return $text;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_title( $value ) {
	return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_email( $value ) {
	return filter_var( $value, FILTER_VALIDATE_EMAIL ) ? $value : '';
}

function esc_url_raw( $value ) {
	return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : '';
}

function remove_accents( $value ) {
	return strtr( $value, [ 'Đ' => 'Dj', 'đ' => 'dj' ] );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_json_encode( $value ) {
	return json_encode( $value );
}

function wp_salt() {
	return 'aomark-listings-test-salt';
}

function add_action() {}
function add_filter() {}
function post_type_exists() { return false; }
function taxonomy_exists() { return false; }

function get_option( $name, $default = false ) {
	global $aomark_test_options;
	return array_key_exists( $name, $aomark_test_options ) ? $aomark_test_options[ $name ] : $default;
}

function update_option( $name, $value ) {
	global $aomark_test_options;
	$aomark_test_options[ $name ] = $value;
	return true;
}

class WP_Error {
	private $errors = [];

	public function add( $code, $message ) {
		$this->errors[ $code ][] = $message;
	}

	public function has_errors() {
		return ! empty( $this->errors );
	}

	public function get_error_messages() {
		$messages = [];
		foreach ( $this->errors as $group ) {
			$messages = array_merge( $messages, $group );
		}
		return $messages;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

require dirname( __DIR__ ) . '/includes/listings-models.php';
require dirname( __DIR__ ) . '/includes/listings-admin.php';
require dirname( __DIR__ ) . '/includes/listings-query.php';
require dirname( __DIR__ ) . '/includes/listings-render.php';
require dirname( __DIR__ ) . '/includes/listings-elementor-controls.php';

function aomark_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$legacy_directory = aomark_listings_preset_model( 'directory', 'directory' );
$second_directory = aomark_listings_preset_model( 'directory', 'directory_2' );

aomark_test_assert( 'aomark_directory' === $legacy_directory['post_type'], 'The original Directory post type must remain stable.' );
aomark_test_assert( $legacy_directory['post_type'] !== $second_directory['post_type'], 'A duplicate preset needs a unique post type.' );
aomark_test_assert( $legacy_directory['taxonomies'][0]['slug'] !== $second_directory['taxonomies'][0]['slug'], 'A duplicate preset needs unique taxonomies.' );
aomark_test_assert( $legacy_directory['fields'][1]['key'] !== $second_directory['fields'][1]['key'], 'A duplicate preset needs namespaced field keys.' );
aomark_test_assert( strlen( aomark_listings_sanitize_post_type( 'aomark_a_very_long_listing_type_name' ) ) <= 20, 'Post type keys must stay within WordPress limits.' );
aomark_test_assert( '1234.50' === aomark_listings_sanitize_meta_value( '1,234.50', 'price' ), 'US-formatted prices should normalize.' );
aomark_test_assert( '1234.50' === aomark_listings_sanitize_meta_value( '1.234,50', 'price' ), 'European-formatted prices should normalize.' );
aomark_test_assert( 1234.5 === aomark_listings_numeric_value( '1.234,50' ), 'European-formatted query values should normalize.' );
aomark_test_assert( '1000' === aomark_listings_sanitize_meta_value( '1e3', 'number' ), 'Scientific notation should be stored as a canonical decimal.' );
aomark_test_assert( 1000.0 === aomark_listings_numeric_value( '1e3' ), 'Scientific notation should have one consistent query value.' );
aomark_test_assert( '' === aomark_listings_sanitize_meta_value( '1e999', 'number' ), 'Non-finite numeric input must fail closed.' );
aomark_test_assert( "0|Zero" === aomark_listings_select_options_to_text( [ [ 'value' => '0', 'label' => 'Zero' ] ] ), 'A select option value of zero must survive the admin text round trip.' );
aomark_test_assert( '' === aomark_listings_sanitize_meta_value( [ 'invalid' ], 'url' ), 'Malformed URL metadata must fail closed without a PHP warning.' );
aomark_test_assert( '2026-02-28' === aomark_listings_sanitize_meta_value( '2026-02-28', 'date' ), 'Valid ISO dates should be retained.' );
aomark_test_assert( '' === aomark_listings_sanitize_meta_value( '2026-02-31', 'date' ), 'Invalid dates should be rejected.' );

$duplicate_registry = [
	$legacy_directory,
	$legacy_directory,
];
aomark_test_assert( is_wp_error( aomark_listings_validate_model_registry( $duplicate_registry ) ), 'Duplicate model identifiers must fail validation.' );

$storage_collision = $legacy_directory;
$storage_collision['fields'][] = [
	'id'    => 'location_lat_copy',
	'key'   => 'aomark_location_lat',
	'label' => 'Location latitude copy',
	'type'  => 'number',
];
aomark_test_assert( is_wp_error( aomark_listings_validate_model_registry( [ $storage_collision ] ) ), 'Location-derived metadata keys must not collide with normal fields.' );
aomark_test_assert( is_wp_error( aomark_listings_admin_validate_posted_structure( [ 'taxonomies' => [ 'broken-row' ] ] ) ), 'Malformed admin rows must fail closed instead of deleting configuration.' );

$descriptor = aomark_listings_create_signed_descriptor( 'results', [ 'model_id' => 'directory', 'per_page' => 9 ] );
aomark_test_assert( is_array( aomark_listings_verify_signed_descriptor( $descriptor, 'results' ) ), 'A valid signed descriptor should verify.' );
aomark_test_assert( false === aomark_listings_verify_signed_descriptor( [ 'model_id' => 'directory' ], 'results' ), 'Unsigned settings must be rejected.' );
$tampered_descriptor         = $descriptor;
$tampered_descriptor['p'][0] = 'A' === $tampered_descriptor['p'][0] ? 'B' : 'A';
aomark_test_assert( false === aomark_listings_verify_signed_descriptor( $tampered_descriptor, 'results' ), 'A modified descriptor must be rejected.' );
aomark_test_assert( false === aomark_listings_verify_signed_descriptor( $descriptor, 'map' ), 'Descriptors must be bound to one purpose.' );

$posted_directory = $legacy_directory;
unset( $posted_directory['taxonomies'][0]['hierarchical'], $posted_directory['taxonomies'][0]['filterable'] );
$normalized_directory = aomark_listings_admin_normalize_posted_model( $posted_directory );
aomark_test_assert( false === $normalized_directory['taxonomies'][0]['hierarchical'], 'An unchecked hierarchy setting must remain disabled.' );
aomark_test_assert( false === $normalized_directory['taxonomies'][0]['filterable'], 'An unchecked taxonomy filter setting must remain disabled.' );
aomark_test_assert( true === aomark_listings_admin_validate_technical_values( $legacy_directory, $legacy_directory ), 'Unchanged technical identifiers should save.' );
$changed_directory              = $legacy_directory;
$changed_directory['post_type'] = 'different_type';
aomark_test_assert( is_wp_error( aomark_listings_admin_validate_technical_values( $legacy_directory, $changed_directory ) ), 'Existing post type identifiers must be immutable.' );

$aomark_test_options = [
	AOMARK_LISTINGS_OPTION        => [ 'directory' => $legacy_directory ],
	AOMARK_LISTINGS_SCHEMA_OPTION => 0,
];
aomark_listings_maybe_upgrade_schema();
aomark_test_assert( isset( $aomark_test_options['aomark_listings_models_backup_v1'] ), 'The schema upgrade must preserve a rollback snapshot.' );
$original_backup = $aomark_test_options['aomark_listings_models_backup_v1'];
$aomark_test_options[ AOMARK_LISTINGS_OPTION ] = [ 'directory_2' => $second_directory ];
aomark_listings_maybe_upgrade_schema();
aomark_test_assert( $original_backup === $aomark_test_options['aomark_listings_models_backup_v1'], 'The rollback snapshot must be idempotent.' );

$zero_model     = aomark_listings_preset_model( 'custom', '0' );
$fallback_model = aomark_listings_preset_model( 'custom', 'foo_bar' );
$aomark_test_options[ AOMARK_LISTINGS_OPTION ] = [ '0' => $zero_model, 'foo_bar' => $fallback_model ];
aomark_test_assert( '0' === aomark_listings_get_model( '0' )['id'], 'A model ID of zero must not fall back to the first model.' );
aomark_test_assert( null === aomark_listings_get_model( 'foo-bar' ), 'Model lookup must not rewrite an unknown reference.' );
aomark_test_assert( [ 'shared' ] === aomark_listings_normalize_selected_ids( [ '0:shared', 'foo_bar:foreign' ], '0' ), 'A zero model constraint must reject fields from other models.' );

$_GET = [ 'alm_model' => 'foo_bar', 'alm_keyword' => 'shared term' ];
aomark_test_assert( false === aomark_listings_request_targets_model( $zero_model ), 'URL filters must not leak into another listing type.' );
aomark_test_assert( [] === aomark_listings_sanitize_ajax_filters( 'alm_model=foo_bar&alm_keyword=shared+term', $zero_model ), 'AJAX filters must honor their model scope.' );
$zero_page_url = aomark_listings_results_page_url( 2, $zero_model );
parse_str( ltrim( $zero_page_url, '?' ), $zero_page_params );
aomark_test_assert( '0' === $zero_page_params['alm_model'] && 2 === (int) $zero_page_params['alm_page'], 'Pagination must switch to the model that owns the clicked result view.' );
aomark_test_assert( ! isset( $zero_page_params['alm_keyword'] ), 'Pagination must not retain another model\'s filters.' );

$_GET = [ 'alm_model' => '0', 'alm_keyword' => 'zero term' ];
$zero_page_url = aomark_listings_results_page_url( 3, $zero_model );
parse_str( ltrim( $zero_page_url, '?' ), $zero_page_params );
aomark_test_assert( 'zero term' === $zero_page_params['alm_keyword'], 'Pagination must retain filters for its own model.' );
$built_query = aomark_listings_build_query_args( [ 'model_id' => '0' ], 1 );
aomark_test_assert( false === $built_query['args']['has_password'], 'Public result queries must exclude password-protected posts before pagination.' );

$no_script_model = aomark_listings_admin_normalize_posted_model(
	[
		'id'         => 'no_script',
		'post_type'  => 'aomark_no_script',
		'singular'   => 'Item',
		'plural'     => 'Items',
		'taxonomies' => [ [ 'singular' => 'Service Area', 'plural' => 'Service Areas' ] ],
		'fields'     => [ [ 'label' => 'Contact Name', 'type' => 'text' ] ],
	]
);
aomark_test_assert( 'service_area' === $no_script_model['taxonomies'][0]['id'], 'New taxonomy identifiers need a server-side fallback when admin JavaScript is unavailable.' );
aomark_test_assert( 'contact_name' === $no_script_model['fields'][0]['id'], 'New field identifiers need a server-side fallback when admin JavaScript is unavailable.' );
$_GET = [];

fwrite( STDOUT, "Model contract tests passed.\n" );
