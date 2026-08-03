<?php
/**
 * Admin settings UI for listing models.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_admin_menu() {
	add_menu_page(
		__( 'Aomark Listings', 'aomark-listings' ),
		__( 'Aomark Listings', 'aomark-listings' ),
		'manage_options',
		'aomark-listings',
		'aomark_listings_render_admin_page',
		AOMARK_LISTINGS_PLUGIN_URL . 'assets/img/aomark-icon-white.png',
		58
	);
}
add_action( 'admin_menu', 'aomark_listings_admin_menu' );

/**
 * Add concise, contextual guidance to the plugin screen.
 */
function aomark_listings_add_admin_help_tabs() {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$screen->add_help_tab(
		[
			'id'      => 'aomark-listings-quick-start',
			'title'   => __( 'Quick start', 'aomark-listings' ),
			'content' => '<p>' . esc_html__( 'Choose a listing type, add your first listing, then create a page in Elementor with the Aomark Listings widgets.', 'aomark-listings' ) . '</p>' .
				'<ol><li>' . esc_html__( 'Create or open a listing type.', 'aomark-listings' ) . '</li><li>' . esc_html__( 'Review its fields and filters.', 'aomark-listings' ) . '</li><li>' . esc_html__( 'Add at least one listing.', 'aomark-listings' ) . '</li><li>' . esc_html__( 'Build the public page in Elementor.', 'aomark-listings' ) . '</li></ol>',
		]
	);

	$screen->add_help_tab(
		[
			'id'      => 'aomark-listings-types-help',
			'title'   => __( 'Listing types', 'aomark-listings' ),
			'content' => '<p>' . esc_html__( 'A listing type defines the fields, categories and filters shared by one kind of content, such as properties, businesses or vehicles.', 'aomark-listings' ) . '</p><p>' . esc_html__( 'Friendly names can be changed at any time. Technical identifiers are kept in Advanced because changing them can disconnect existing content and Elementor widgets.', 'aomark-listings' ) . '</p>',
		]
	);

	$screen->add_help_tab(
		[
			'id'      => 'aomark-listings-elementor-help',
			'title'   => __( 'Elementor', 'aomark-listings' ),
			'content' => '<p>' . esc_html__( 'For a standard listing page, add Listing Filter, Listing Results and optionally Listing Map. Select the same listing type in each widget.', 'aomark-listings' ) . '</p><p>' . esc_html__( 'Use Listing Field, Listing Meta and Listing Gallery when designing a single listing template.', 'aomark-listings' ) . '</p>',
		]
	);

	$screen->set_help_sidebar(
		'<p><strong>' . esc_html__( 'Need a reminder?', 'aomark-listings' ) . '</strong></p><p>' . esc_html__( 'The dashboard always shows the next useful action for each listing type.', 'aomark-listings' ) . '</p>'
	);
}
add_action( 'load-toplevel_page_aomark-listings', 'aomark_listings_add_admin_help_tabs' );

function aomark_listings_admin_url( $args = [] ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=aomark-listings' ) );
}

/**
 * Return the small set of counts needed by the listing type dashboard.
 *
 * @param array $model Listing type configuration.
 * @return array
 */
function aomark_listings_admin_model_counts( $model ) {
	$post_counts = wp_count_posts( $model['post_type'] ?? '' );
	$published   = $post_counts && isset( $post_counts->publish ) ? (int) $post_counts->publish : 0;
	$drafts      = 0;

	foreach ( [ 'draft', 'pending', 'future' ] as $status ) {
		$drafts += $post_counts && isset( $post_counts->{$status} ) ? (int) $post_counts->{$status} : 0;
	}

	$filters = 0;
	foreach ( (array) ( $model['fields'] ?? [] ) as $field ) {
		$filters += ! empty( $field['filterable'] ) ? 1 : 0;
	}
	foreach ( (array) ( $model['taxonomies'] ?? [] ) as $taxonomy ) {
		$filters += ! empty( $taxonomy['filterable'] ) ? 1 : 0;
	}

	return [
		'published' => $published,
		'drafts'    => $drafts,
		'fields'    => count( (array) ( $model['fields'] ?? [] ) ),
		'filters'   => $filters,
	];
}

/**
 * Mark rewrite rules for a safe, deferred refresh.
 */
function aomark_listings_admin_mark_rewrite_dirty() {
	if ( function_exists( 'aomark_listings_mark_rewrite_dirty' ) ) {
		aomark_listings_mark_rewrite_dirty();
		return;
	}

	// Compatibility fallback for older copies of the model registry.
	flush_rewrite_rules();
}

function aomark_listings_help_tip( $text ) {
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return '';
	}

	return sprintf(
		'<span class="aomark-listings-help" tabindex="0" aria-label="%1$s" data-aomark-tooltip="%1$s">?</span>',
		esc_attr( $text )
	);
}

function aomark_listings_admin_help_text( $key ) {
	$help = [
		'model_id'              => __( 'Internal identifier used by saved Elementor widgets. Leave it unchanged unless you are deliberately migrating an existing setup.', 'aomark-listings' ),
		'cpt_slug'              => __( 'Internal WordPress content type key. It must be unique and no longer than 20 characters.', 'aomark-listings' ),
		'singular_label'        => __( 'What you call one item, for example Property, Business or Vehicle.', 'aomark-listings' ),
		'plural_label'          => __( 'What you call a collection of these items, for example Properties, Businesses or Vehicles.', 'aomark-listings' ),
		'menu_icon'             => __( 'Dashicon class used in the WordPress admin menu, for example dashicons-building.', 'aomark-listings' ),
		'has_archive'           => __( 'Creates a standard WordPress archive URL for this listing type.', 'aomark-listings' ),
		'show_in_rest'          => __( 'Required for the block editor and integrations that use the WordPress REST API.', 'aomark-listings' ),
		'taxonomy_id'           => __( 'Internal identifier used in widget settings. It is generated from the category name.', 'aomark-listings' ),
		'taxonomy_slug'         => __( 'Registered WordPress taxonomy slug. It must be unique across the site and is limited to 32 characters.', 'aomark-listings' ),
		'taxonomy_hierarchical' => __( 'Category style allows parent and child terms. Tag style keeps every term on one level.', 'aomark-listings' ),
		'taxonomy_filterable'   => __( 'Allow visitors to filter listing results by this category group.', 'aomark-listings' ),
		'field_key'             => __( 'Database key for this value. New fields generate it automatically from the field name.', 'aomark-listings' ),
		'field_label'           => __( 'The name editors and visitors will see. Technical identifiers are generated automatically.', 'aomark-listings' ),
		'field_type'            => __( 'Choose the editor best suited to the value, such as Text, Price, Image or Location.', 'aomark-listings' ),
		'field_group'           => __( 'Choose where the field appears while editing a listing. Automatic places common field types for you.', 'aomark-listings' ),
		'field_suffix'          => __( 'Optional text displayed after values, for example m2, EUR or km.', 'aomark-listings' ),
		'field_placeholder'     => __( 'Example or hint shown while the editor input is empty.', 'aomark-listings' ),
		'field_filterable'      => __( 'Allow this field to be used in visitor-facing filters.', 'aomark-listings' ),
		'field_card'            => __( 'Include this value in listing result cards by default.', 'aomark-listings' ),
		'field_options'         => __( 'Only used by Select fields. Add one option per line in value|Label format.', 'aomark-listings' ),
	];

	return $help[ $key ] ?? '';
}

function aomark_listings_unique_model_id( $base, $models ) {
	$base = aomark_listings_sanitize_id( $base, 'listing' );
	$id   = $base;
	$i    = 2;

	while ( isset( $models[ $id ] ) ) {
		$id = $base . '_' . $i;
		$i++;
	}

	return $id;
}

function aomark_listings_admin_notice( $message, $type = 'success' ) {
	add_settings_error( 'aomark_listings_messages', 'aomark_listings_message', $message, $type );
}

function aomark_listings_admin_text_length( $value ) {
	$value = (string) $value;
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( $value, 'UTF-8' );
	}

	$count = preg_match_all( '/./us', $value, $characters );

	return false === $count ? strlen( $value ) : $count;
}

function aomark_listings_handle_admin_actions() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( empty( $_POST['aomark_listings_action'] ) ) {
		return;
	}

	$action_raw = wp_unslash( $_POST['aomark_listings_action'] );
	if ( ! is_scalar( $action_raw ) ) {
		return;
	}
	$action = sanitize_key( (string) $action_raw );

	$nonce_raw = isset( $_POST['aomark_listings_nonce'] ) ? wp_unslash( $_POST['aomark_listings_nonce'] ) : '';
	if ( ! is_scalar( $nonce_raw ) || ! wp_verify_nonce( sanitize_text_field( (string) $nonce_raw ), 'aomark_listings_admin' ) ) {
		aomark_listings_admin_notice( __( 'Security check failed.', 'aomark-listings' ), 'error' );
		return;
	}

	$models = aomark_listings_get_models();

	if ( 'create_model' === $action ) {
		$preset_raw = wp_unslash( $_POST['preset'] ?? 'custom' );
		$preset     = is_scalar( $preset_raw ) ? sanitize_key( (string) $preset_raw ) : 'custom';
		if ( ! in_array( $preset, [ 'real_estate', 'directory', 'custom' ], true ) ) {
			$preset = 'custom';
		}
		$base     = 'real_estate' === $preset ? 'real_estate' : ( 'directory' === $preset ? 'directory' : 'custom_listing' );
		$id       = aomark_listings_unique_model_id( $base, $models );
		$model    = aomark_listings_preset_model( $preset, $id );
		$singular_raw = wp_unslash( $_POST['listing_singular'] ?? '' );
		$plural_raw   = wp_unslash( $_POST['listing_plural'] ?? '' );
		$singular = is_scalar( $singular_raw ) ? trim( sanitize_text_field( (string) $singular_raw ) ) : '';
		$plural   = is_scalar( $plural_raw ) ? trim( sanitize_text_field( (string) $plural_raw ) ) : '';

		if ( '' === $singular || '' === $plural || aomark_listings_admin_text_length( $singular ) > 120 || aomark_listings_admin_text_length( $plural ) > 120 ) {
			aomark_listings_admin_notice( __( 'Enter singular and plural names of 120 characters or fewer.', 'aomark-listings' ), 'error' );
			return;
		}

		if ( '' !== $singular ) {
			$model['singular'] = $singular;
		}
		if ( '' !== $plural ) {
			$model['plural'] = $plural;
		}

		if ( isset( $models[ $id ] ) ) {
			aomark_listings_admin_notice( __( 'That listing type already exists.', 'aomark-listings' ), 'error' );
			return;
		}

		$models[ $id ] = aomark_listings_sanitize_model( $model );
		if ( function_exists( 'aomark_listings_validate_model_registry' ) ) {
			$validation = aomark_listings_validate_model_registry( $models );
			if ( is_wp_error( $validation ) ) {
				aomark_listings_admin_notice( implode( ' ', $validation->get_error_messages() ), 'error' );
				return;
			}
		}
		update_option( AOMARK_LISTINGS_OPTION, $models, false );
		aomark_listings_admin_mark_rewrite_dirty();
		wp_safe_redirect( aomark_listings_admin_url( [ 'edit' => $id, 'created' => 1 ] ) );
		exit;
	}

	if ( 'delete_model' === $action ) {
		$id = aomark_listings_sanitize_id( wp_unslash( $_POST['model_id'] ?? '' ), '' );
		if ( '' !== $id && isset( $models[ $id ] ) ) {
			unset( $models[ $id ] );
			update_option( AOMARK_LISTINGS_OPTION, $models, false );
			aomark_listings_admin_mark_rewrite_dirty();
		}
		wp_safe_redirect( aomark_listings_admin_url( [ 'deleted' => 1 ] ) );
		exit;
	}

	if ( 'save_model' === $action ) {
		$raw = isset( $_POST['model'] ) && is_array( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : [];
		$old_id = aomark_listings_sanitize_id( wp_unslash( $_POST['old_model_id'] ?? '' ), '' );
		if ( '' === $old_id || ! isset( $models[ $old_id ] ) ) {
			aomark_listings_admin_notice( __( 'The listing type being edited could not be verified. Nothing was saved.', 'aomark-listings' ), 'error' );
			return;
		}

		$structure_validation = aomark_listings_admin_validate_posted_structure( $raw );
		if ( is_wp_error( $structure_validation ) ) {
			aomark_listings_admin_notice( implode( ' ', $structure_validation->get_error_messages() ), 'error' );
			return;
		}

		$label_validation = aomark_listings_admin_validate_posted_labels( $raw );
		if ( is_wp_error( $label_validation ) ) {
			aomark_listings_admin_notice( implode( ' ', $label_validation->get_error_messages() ), 'error' );
			return;
		}

		$technical_validation = aomark_listings_admin_validate_technical_values( $models[ $old_id ], $raw );
		if ( is_wp_error( $technical_validation ) ) {
			aomark_listings_admin_notice( implode( ' ', $technical_validation->get_error_messages() ), 'error' );
			return;
		}

		$model = aomark_listings_admin_normalize_posted_model( $raw );

		$models[ $model['id'] ] = $model;
		if ( function_exists( 'aomark_listings_validate_model_registry' ) ) {
			$validation = aomark_listings_validate_model_registry( $models );
			if ( is_wp_error( $validation ) ) {
				aomark_listings_admin_notice( implode( ' ', $validation->get_error_messages() ), 'error' );
				return;
			}
		}
		update_option( AOMARK_LISTINGS_OPTION, $models, false );
		aomark_listings_admin_mark_rewrite_dirty();
		wp_safe_redirect( aomark_listings_admin_url( [ 'edit' => $model['id'], 'updated' => 1 ] ) );
		exit;
	}
}
add_action( 'admin_init', 'aomark_listings_handle_admin_actions' );

/**
 * Reject malformed nested rows instead of normalizing them into an accidental
 * deletion of all categories or fields.
 *
 * @param array $raw Posted model data.
 * @return true|WP_Error
 */
function aomark_listings_admin_validate_posted_structure( $raw ) {
	$errors = new WP_Error();
	$raw    = is_array( $raw ) ? $raw : [];

	foreach ( [ 'taxonomies', 'fields' ] as $collection ) {
		if ( isset( $raw[ $collection ] ) && ! is_array( $raw[ $collection ] ) ) {
			$errors->add( 'invalid_model_rows', __( 'The listing type form contained invalid rows. Nothing was saved.', 'aomark-listings' ) );
			continue;
		}

		foreach ( (array) ( $raw[ $collection ] ?? [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				$errors->add( 'invalid_model_row', __( 'The listing type form contained an invalid row. Nothing was saved.', 'aomark-listings' ) );
				continue;
			}

			foreach ( $row as $value ) {
				if ( ! is_scalar( $value ) ) {
					$errors->add( 'invalid_model_value', __( 'The listing type form contained an invalid value. Nothing was saved.', 'aomark-listings' ) );
					break;
				}
			}
		}
	}

	return $errors->has_errors() ? $errors : true;
}

/**
 * Validate names before sanitization can replace an empty field label with an
 * internal identifier.
 *
 * @param array $raw Posted model data.
 * @return true|WP_Error
 */
function aomark_listings_admin_validate_posted_labels( $raw ) {
	$errors = new WP_Error();
	$raw    = is_array( $raw ) ? $raw : [];

	foreach ( [ 'singular', 'plural' ] as $key ) {
		$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( sanitize_text_field( (string) $raw[ $key ] ) ) : '';
		if ( '' === $value ) {
			$errors->add( 'missing_model_label', __( 'Enter both the singular and plural listing type names.', 'aomark-listings' ) );
		} elseif ( aomark_listings_admin_text_length( $value ) > 120 ) {
			$errors->add( 'model_label_too_long', __( 'Listing type names must be 120 characters or fewer.', 'aomark-listings' ) );
		}
	}

	foreach ( (array) ( $raw['taxonomies'] ?? [] ) as $taxonomy ) {
		if ( ! is_array( $taxonomy ) || ( empty( $taxonomy['id'] ) && empty( $taxonomy['slug'] ) && empty( $taxonomy['singular'] ) && empty( $taxonomy['plural'] ) ) ) {
			continue;
		}
		foreach ( [ 'singular', 'plural' ] as $key ) {
			$value = isset( $taxonomy[ $key ] ) && is_scalar( $taxonomy[ $key ] ) ? trim( sanitize_text_field( (string) $taxonomy[ $key ] ) ) : '';
			if ( '' === $value ) {
				$errors->add( 'missing_taxonomy_label', __( 'Every category group needs both a singular and plural name.', 'aomark-listings' ) );
			} elseif ( aomark_listings_admin_text_length( $value ) > 120 ) {
				$errors->add( 'taxonomy_label_too_long', __( 'Category group names must be 120 characters or fewer.', 'aomark-listings' ) );
			}
		}
	}

	foreach ( (array) ( $raw['fields'] ?? [] ) as $field ) {
		if ( ! is_array( $field ) || ( empty( $field['id'] ) && empty( $field['key'] ) && empty( $field['label'] ) ) ) {
			continue;
		}
		$label = isset( $field['label'] ) && is_scalar( $field['label'] ) ? trim( sanitize_text_field( (string) $field['label'] ) ) : '';
		if ( '' === $label ) {
			$errors->add( 'missing_field_label', __( 'Every listing field needs a name.', 'aomark-listings' ) );
		} elseif ( aomark_listings_admin_text_length( $label ) > 120 ) {
			$errors->add( 'field_label_too_long', __( 'Listing field names must be 120 characters or fewer.', 'aomark-listings' ) );
		}
	}

	return $errors->has_errors() ? $errors : true;
}

/**
 * Existing technical identifiers are immutable until a dedicated data and
 * Elementor migration workflow can update every dependent record safely.
 *
 * @param array $existing Existing sanitized model.
 * @param array $raw      Posted model data.
 * @return true|WP_Error
 */
function aomark_listings_admin_validate_technical_values( $existing, $raw ) {
	$errors = new WP_Error();
	$raw    = is_array( $raw ) ? $raw : [];
	$new_id = aomark_listings_sanitize_id( $raw['id'] ?? '', '' );
	$new_post_type = aomark_listings_sanitize_post_type( $raw['post_type'] ?? '', '' );

	if ( $new_id !== $existing['id'] || $new_post_type !== $existing['post_type'] ) {
		$errors->add( 'immutable_model_identifier', __( 'Technical listing type identifiers cannot be changed without a data migration.', 'aomark-listings' ) );
	}

	$existing_taxonomies = [];
	foreach ( (array) ( $existing['taxonomies'] ?? [] ) as $taxonomy ) {
		$existing_taxonomies[ $taxonomy['id'] ] = $taxonomy;
	}

	foreach ( (array) ( $raw['taxonomies'] ?? [] ) as $index => $taxonomy ) {
		if ( ! is_array( $taxonomy ) ) {
			continue;
		}
		$original_id = isset( $taxonomy['original_id'] ) && is_scalar( $taxonomy['original_id'] ) ? aomark_listings_sanitize_id( $taxonomy['original_id'], '' ) : '';
		if ( '' === $original_id && isset( $existing['taxonomies'][ $index ] ) ) {
			$original_id = $existing['taxonomies'][ $index ]['id'];
		}
		if ( '' === $original_id ) {
			continue;
		}
		if ( ! isset( $existing_taxonomies[ $original_id ] ) ) {
			$errors->add( 'unknown_taxonomy_identifier', __( 'An existing category group could not be verified. Nothing was saved.', 'aomark-listings' ) );
			continue;
		}
		$posted = aomark_listings_sanitize_taxonomy( $taxonomy, $index );
		$current = $existing_taxonomies[ $original_id ];
		if ( $posted['id'] !== $current['id'] || $posted['slug'] !== $current['slug'] ) {
			$errors->add( 'immutable_taxonomy_identifier', __( 'Existing category group identifiers cannot be changed without a data migration.', 'aomark-listings' ) );
		}
	}

	$existing_fields = [];
	foreach ( (array) ( $existing['fields'] ?? [] ) as $field ) {
		$existing_fields[ $field['id'] ] = $field;
	}

	foreach ( (array) ( $raw['fields'] ?? [] ) as $index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		$original_id = isset( $field['original_id'] ) && is_scalar( $field['original_id'] ) ? aomark_listings_sanitize_id( $field['original_id'], '' ) : '';
		if ( '' === $original_id && isset( $existing['fields'][ $index ] ) ) {
			$original_id = $existing['fields'][ $index ]['id'];
		}
		if ( '' === $original_id ) {
			continue;
		}
		if ( ! isset( $existing_fields[ $original_id ] ) ) {
			$errors->add( 'unknown_field_identifier', __( 'An existing field could not be verified. Nothing was saved.', 'aomark-listings' ) );
			continue;
		}
		$posted = aomark_listings_sanitize_field( $field, $index );
		$current = $existing_fields[ $original_id ];
		if ( $posted['id'] !== $current['id'] || $posted['key'] !== $current['key'] ) {
			$errors->add( 'immutable_field_identifier', __( 'Existing field identifiers cannot be changed without a data migration.', 'aomark-listings' ) );
		}
	}

	return $errors->has_errors() ? $errors : true;
}

function aomark_listings_admin_normalize_posted_model( $raw ) {
	$raw = is_array( $raw ) ? $raw : [];

	$model = [
		'id'           => $raw['id'] ?? '',
		'preset'       => $raw['preset'] ?? 'custom',
		'post_type'    => $raw['post_type'] ?? '',
		'singular'     => $raw['singular'] ?? '',
		'plural'       => $raw['plural'] ?? '',
		'menu_icon'    => $raw['menu_icon'] ?? '',
		'has_archive'  => isset( $raw['has_archive'] ),
		'show_in_rest' => isset( $raw['show_in_rest'] ),
		'taxonomies'   => [],
		'fields'       => [],
	];

	foreach ( (array) ( $raw['taxonomies'] ?? [] ) as $taxonomy_index => $taxonomy ) {
		if ( ! is_array( $taxonomy ) ) {
			continue;
		}
		if ( empty( $taxonomy['id'] ) && empty( $taxonomy['slug'] ) && empty( $taxonomy['singular'] ) && empty( $taxonomy['plural'] ) ) {
			continue;
		}
		if ( ! isset( $taxonomy['id'] ) || ! is_scalar( $taxonomy['id'] ) || '' === trim( (string) $taxonomy['id'] ) ) {
			$taxonomy['id'] = aomark_listings_bounded_identifier( $taxonomy['singular'] ?? '', 64, 'taxonomy_' . $taxonomy_index );
		}
		if ( ! isset( $taxonomy['slug'] ) || ! is_scalar( $taxonomy['slug'] ) || '' === trim( (string) $taxonomy['slug'] ) ) {
			$post_type       = is_scalar( $model['post_type'] ) ? (string) $model['post_type'] : 'aomark_listing';
			$taxonomy['slug'] = aomark_listings_sanitize_taxonomy_slug( ( $post_type ?: 'aomark_listing' ) . '_' . $taxonomy['id'] );
		}
		$taxonomy['hierarchical'] = isset( $taxonomy['hierarchical'] );
		$taxonomy['filterable']   = isset( $taxonomy['filterable'] );
		$model['taxonomies'][] = $taxonomy;
	}

	foreach ( (array) ( $raw['fields'] ?? [] ) as $field_index => $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		if ( empty( $field['id'] ) && empty( $field['key'] ) && empty( $field['label'] ) ) {
			continue;
		}
		if ( ! isset( $field['id'] ) || ! is_scalar( $field['id'] ) || '' === trim( (string) $field['id'] ) ) {
			$field['id'] = aomark_listings_bounded_identifier( $field['label'] ?? '', 64, 'field_' . $field_index );
		}
		if ( ! isset( $field['key'] ) || ! is_scalar( $field['key'] ) || '' === trim( (string) $field['key'] ) ) {
			$field['key'] = aomark_listings_meta_key( $field['id'] );
		}
		$field['filterable'] = isset( $field['filterable'] );
		$field['card']       = isset( $field['card'] );
		$model['fields'][]   = $field;
	}

	return aomark_listings_sanitize_model( $model );
}

function aomark_listings_admin_enqueue( $hook ) {
	$screen = get_current_screen();
	$is_listing_editor = $screen && aomark_listings_get_model_by_post_type( $screen->post_type ?? '' );
	$is_settings_page  = 'toplevel_page_aomark-listings' === $hook;

	wp_enqueue_style( 'aomark-listings-admin-menu', AOMARK_LISTINGS_PLUGIN_URL . 'assets/css/admin-menu.css', [], AOMARK_LISTINGS_VERSION );

	if ( $is_listing_editor ) {
		wp_enqueue_media();
		aomark_listings_register_assets();
		wp_enqueue_style( 'aomark-listings-leaflet' );
		wp_enqueue_script( 'aomark-listings-leaflet' );
	}

	if ( $is_settings_page || $is_listing_editor ) {
		wp_enqueue_style( 'aomark-listings-admin', AOMARK_LISTINGS_PLUGIN_URL . 'assets/css/admin-listings.css', [], AOMARK_LISTINGS_VERSION );
		wp_enqueue_script( 'aomark-listings-admin', AOMARK_LISTINGS_PLUGIN_URL . 'assets/js/admin-listings.js', $is_listing_editor ? [ 'jquery', 'aomark-listings-leaflet' ] : [ 'jquery' ], AOMARK_LISTINGS_VERSION, true );
		wp_localize_script(
			'aomark-listings-admin',
			'AomarkListingsAdmin',
			[
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'geocodeNonce'    => wp_create_nonce( 'aomark_listings_geocode' ),
				'defaultCenter'   => apply_filters( 'aomark_listings_editor_map_default_center', [ 20, 0 ] ),
				'defaultZoom'     => (int) apply_filters( 'aomark_listings_editor_map_default_zoom', 2 ),
				'tileUrl'         => aomark_listings_tile_url(),
				'tileAttribution' => aomark_listings_tile_attribution(),
				'strings'         => [
					'searching'      => __( 'Searching addresses…', 'aomark-listings' ),
					'noResults'      => __( 'No matching addresses found.', 'aomark-listings' ),
					'searchError'    => __( 'Address search is temporarily unavailable.', 'aomark-listings' ),
					'mapUnavailable' => __( 'Map could not be loaded. Check the internet connection and reload the editor.', 'aomark-listings' ),
					'mapLoaded'      => __( 'Map loaded. Drag the pin or click the map to fine-tune it.', 'aomark-listings' ),
					'mapLoadedButton' => __( 'Map loaded', 'aomark-listings' ),
					'mapTilesUnavailable' => __( 'Map tiles could not be loaded. Coordinates can still be entered directly.', 'aomark-listings' ),
					'typeMore'       => __( 'Type at least three characters to search.', 'aomark-listings' ),
					'readyToSearch'  => __( 'Choose Search address with Photon to request suggestions.', 'aomark-listings' ),
					'locationSet'    => __( 'Location selected. Drag the pin or click the map to fine-tune it.', 'aomark-listings' ),
					'locationSaved'  => __( 'Location selected. Load the map to fine-tune the pin.', 'aomark-listings' ),
					'pinMoved'       => __( 'Pin position updated.', 'aomark-listings' ),
					'coordinatesUpdated' => __( 'Coordinates updated.', 'aomark-listings' ),
					'coordinatesCleared' => __( 'Coordinates cleared.', 'aomark-listings' ),
					'coordinatesInvalid' => __( 'Enter a valid latitude from -90 to 90 and longitude from -180 to 180.', 'aomark-listings' ),
					'newField'          => __( 'New field', 'aomark-listings' ),
					'newCategory'       => __( 'New category group', 'aomark-listings' ),
					'notSaved'          => __( 'not saved yet', 'aomark-listings' ),
					'categoryStyle'     => __( 'Category style', 'aomark-listings' ),
					'tagStyle'          => __( 'Tag style', 'aomark-listings' ),
					'availableFilter'   => __( 'Available as a filter', 'aomark-listings' ),
					'organizationOnly'  => __( 'Organization only', 'aomark-listings' ),
					'automatic'         => __( 'Automatic', 'aomark-listings' ),
					/* translators: %s: automatically selected field group label. */
					'automaticGroup'    => __( 'Automatic · %s', 'aomark-listings' ),
					'features'          => __( 'Features', 'aomark-listings' ),
					'location'          => __( 'Location', 'aomark-listings' ),
					'media'             => __( 'Media', 'aomark-listings' ),
					'details'           => __( 'Details', 'aomark-listings' ),
					'fieldRemoved'      => __( 'Field removed. The change becomes permanent when you save.', 'aomark-listings' ),
					'categoryRemoved'   => __( 'Category group removed. The change becomes permanent when you save.', 'aomark-listings' ),
					'undo'              => __( 'Undo', 'aomark-listings' ),
					'chooseGallery'     => __( 'Choose Gallery', 'aomark-listings' ),
					'chooseImage'       => __( 'Choose Image', 'aomark-listings' ),
					'useGallery'        => __( 'Use Gallery', 'aomark-listings' ),
					'useImage'          => __( 'Use Image', 'aomark-listings' ),
				],
			]
		);
	}
}
add_action( 'admin_enqueue_scripts', 'aomark_listings_admin_enqueue' );

function aomark_listings_geocode_result_label( $properties ) {
	$properties = is_array( $properties ) ? $properties : [];
	$street      = trim( (string) ( $properties['street'] ?? '' ) );
	$house       = trim( (string) ( $properties['housenumber'] ?? '' ) );
	$street_line = trim( $street . ( $house ? ' ' . $house : '' ) );
	$parts       = [
		$properties['name'] ?? '',
		$street_line,
		$properties['district'] ?? '',
		$properties['city'] ?? '',
		$properties['postcode'] ?? '',
		$properties['state'] ?? '',
		$properties['country'] ?? '',
	];
	$parts = array_values(
		array_unique(
			array_filter(
				array_map(
					function ( $part ) {
						return is_scalar( $part ) ? sanitize_text_field( (string) $part ) : '';
					},
					$parts
				)
			)
		)
	);

	return implode( ', ', $parts );
}

function aomark_listings_ajax_geocode() {
	check_ajax_referer( 'aomark_listings_geocode', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( [ 'message' => __( 'You are not allowed to search locations.', 'aomark-listings' ) ], 403 );
	}

	$query_raw = wp_unslash( $_GET['query'] ?? '' );
	$query     = is_scalar( $query_raw ) ? substr( sanitize_text_field( (string) $query_raw ), 0, 200 ) : '';
	if ( strlen( $query ) < 3 ) {
		wp_send_json_success( [ 'results' => [] ] );
	}

	$country_raw  = apply_filters( 'aomark_listings_geocoder_country_code', '' );
	$country_code = is_scalar( $country_raw ) ? sanitize_key( (string) $country_raw ) : '';
	$cache_key    = 'aomark_geo_' . md5( strtolower( $country_code . '|' . $query ) );
	$cached       = get_transient( $cache_key );

	if ( false !== $cached ) {
		wp_send_json_success( [ 'results' => $cached ] );
	}

	$user_id  = get_current_user_id();
	$rate_key = 'aomark_geo_rate_' . $user_id;
	$rate     = get_transient( $rate_key );
	$rate     = is_array( $rate ) ? $rate : [ 'count' => 0, 'started' => time() ];
	if ( time() - absint( $rate['started'] ?? 0 ) >= MINUTE_IN_SECONDS ) {
		$rate = [ 'count' => 0, 'started' => time() ];
	}
	if ( absint( $rate['count'] ?? 0 ) >= 30 ) {
		wp_send_json_error( [ 'message' => __( 'Too many address searches. Please wait a moment and try again.', 'aomark-listings' ) ], 429 );
	}
	$rate['count'] = absint( $rate['count'] ?? 0 ) + 1;
	set_transient( $rate_key, $rate, MINUTE_IN_SECONDS );

	$endpoint = apply_filters( 'aomark_listings_geocoder_endpoint', 'https://photon.komoot.io/api/' );
	$endpoint = is_string( $endpoint ) && '' !== trim( $endpoint ) ? $endpoint : 'https://photon.komoot.io/api/';
	$query_args = [
		'q'     => $query,
		'limit' => 5,
	];
	if ( preg_match( '/^[a-z]{2}$/', $country_code ) ) {
		$query_args['countrycode'] = $country_code;
	}
	$url = add_query_arg( $query_args, $endpoint );
	$response = wp_safe_remote_get(
		$url,
		[
			'timeout'             => 8,
			'redirection'         => 2,
			'limit_response_size' => 1024 * 1024,
			'user-agent'          => 'Aomark Listings/' . AOMARK_LISTINGS_VERSION . ' (+https://github.com/Alex27m/aomark-listings)',
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_send_json_error( [ 'message' => __( 'Address search is temporarily unavailable.', 'aomark-listings' ) ], 502 );
	}

	$payload = json_decode( wp_remote_retrieve_body( $response ), true );
	$results = [];

	foreach ( array_slice( (array) ( $payload['features'] ?? [] ), 0, 5 ) as $feature ) {
		if ( ! is_array( $feature ) ) {
			continue;
		}
		$coordinates = $feature['geometry']['coordinates'] ?? [];
		if ( ! is_array( $coordinates ) || ! isset( $coordinates[0], $coordinates[1] ) || ! is_numeric( $coordinates[0] ) || ! is_numeric( $coordinates[1] ) ) {
			continue;
		}
		$lng         = (float) $coordinates[0];
		$lat         = (float) $coordinates[1];
		$label       = aomark_listings_geocode_result_label( $feature['properties'] ?? [] );

		if ( ! is_finite( $lat ) || ! is_finite( $lng ) || '' === $label || abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			continue;
		}

		$results[] = [
			'label' => $label,
			'lat'   => $lat,
			'lng'   => $lng,
		];
	}

	set_transient( $cache_key, $results, 12 * HOUR_IN_SECONDS );
	wp_send_json_success( [ 'results' => $results ] );
}
add_action( 'wp_ajax_aomark_listings_geocode', 'aomark_listings_ajax_geocode' );

/**
 * Restore useful notices after POST/redirect/GET admin actions.
 *
 * @param array|null $editing Currently selected listing type.
 */
function aomark_listings_admin_add_query_notices( $editing ) {
	if ( isset( $_GET['created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$name = $editing['plural'] ?? __( 'Listing type', 'aomark-listings' );
		aomark_listings_admin_notice(
			sprintf(
				/* translators: %s: listing type plural label. */
				__( '%s created. Review the fields below, save any changes, then add your first listing.', 'aomark-listings' ),
				$name
			)
		);
	}

	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		aomark_listings_admin_notice( __( 'Listing type saved successfully.', 'aomark-listings' ) );
	}

	if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		aomark_listings_admin_notice( __( 'Listing type removed. Existing WordPress content was left in the database.', 'aomark-listings' ) );
	}
}

/**
 * Render the friendly preset chooser.
 */
function aomark_listings_render_preset_creator() {
	$presets = [
		'real_estate' => [
			'title'       => __( 'Real Estate', 'aomark-listings' ),
			'description' => __( 'Properties with price, area, rooms, features, gallery and map location.', 'aomark-listings' ),
			'summary'     => __( '7 fields · 4 category groups', 'aomark-listings' ),
			'singular'    => __( 'Property', 'aomark-listings' ),
			'plural'      => __( 'Properties', 'aomark-listings' ),
			'icon'        => 'dashicons-building',
		],
		'directory'   => [
			'title'       => __( 'Directory', 'aomark-listings' ),
			'description' => __( 'Businesses or places with contact details, categories, gallery and map location.', 'aomark-listings' ),
			'summary'     => __( '6 fields · 2 category groups', 'aomark-listings' ),
			'singular'    => __( 'Directory Item', 'aomark-listings' ),
			'plural'      => __( 'Directory Items', 'aomark-listings' ),
			'icon'        => 'dashicons-location-alt',
		],
		'custom'      => [
			'title'       => __( 'Start Simple', 'aomark-listings' ),
			'description' => __( 'A lightweight starting point you can shape into any kind of listing.', 'aomark-listings' ),
			'summary'     => __( '2 fields · 1 category group', 'aomark-listings' ),
			'singular'    => __( 'Listing', 'aomark-listings' ),
			'plural'      => __( 'Listings', 'aomark-listings' ),
			'icon'        => 'dashicons-list-view',
		],
	];
	?>
	<div id="aomark-create-listing-type" class="aomark-listings-admin-panel aomark-card aomark-listings-create-panel">
		<div class="aomark-listings-panel-head">
			<div>
				<h2><?php esc_html_e( 'Create a listing type', 'aomark-listings' ); ?></h2>
				<p><?php esc_html_e( 'Choose the closest starting point. Every field and category can be adjusted afterwards.', 'aomark-listings' ); ?></p>
			</div>
			<span class="aomark-listings-step-badge"><?php esc_html_e( 'About 1 minute', 'aomark-listings' ); ?></span>
		</div>

		<form method="post" class="aomark-listings-create-form" data-aomark-preset-form>
			<?php wp_nonce_field( 'aomark_listings_admin', 'aomark_listings_nonce' ); ?>
			<input type="hidden" name="aomark_listings_action" value="create_model">
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Choose a starting point', 'aomark-listings' ); ?></legend>
				<div class="aomark-listings-preset-grid">
					<?php foreach ( $presets as $preset_id => $preset ) : ?>
						<label class="aomark-listings-preset-card<?php echo 'real_estate' === $preset_id ? ' is-selected' : ''; ?>">
							<input type="radio" name="preset" value="<?php echo esc_attr( $preset_id ); ?>" data-singular="<?php echo esc_attr( $preset['singular'] ); ?>" data-plural="<?php echo esc_attr( $preset['plural'] ); ?>" <?php checked( 'real_estate', $preset_id ); ?>>
							<span class="dashicons <?php echo esc_attr( $preset['icon'] ); ?>" aria-hidden="true"></span>
							<strong><?php echo esc_html( $preset['title'] ); ?></strong>
							<span><?php echo esc_html( $preset['description'] ); ?></span>
							<small><?php echo esc_html( $preset['summary'] ); ?></small>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

			<div class="aomark-listings-create-names">
				<label>
					<span><?php esc_html_e( 'One item is called', 'aomark-listings' ); ?></span>
					<input type="text" name="listing_singular" value="<?php esc_attr_e( 'Property', 'aomark-listings' ); ?>" maxlength="120" data-aomark-preset-singular required>
					<small><?php esc_html_e( 'Example: Property, Business or Vehicle', 'aomark-listings' ); ?></small>
				</label>
				<label>
					<span><?php esc_html_e( 'A collection is called', 'aomark-listings' ); ?></span>
					<input type="text" name="listing_plural" value="<?php esc_attr_e( 'Properties', 'aomark-listings' ); ?>" maxlength="120" data-aomark-preset-plural required>
					<small><?php esc_html_e( 'Example: Properties, Businesses or Vehicles', 'aomark-listings' ); ?></small>
				</label>
			</div>

			<p class="aomark-listings-create-submit">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Create listing type', 'aomark-listings' ); ?></button>
				<span><?php esc_html_e( 'You can review everything before adding content.', 'aomark-listings' ); ?></span>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Render task-focused listing type cards.
 *
 * @param array $models Registered listing types.
 */
function aomark_listings_render_dashboard( $models ) {
	$total_published = 0;
	foreach ( $models as $model ) {
		$counts          = aomark_listings_admin_model_counts( $model );
		$total_published += $counts['published'];
	}
	?>
	<div class="aomark-listings-dashboard-intro">
		<div>
			<p class="aomark-listings-eyebrow"><?php esc_html_e( 'Your workspace', 'aomark-listings' ); ?></p>
			<h2><?php esc_html_e( 'Listing types', 'aomark-listings' ); ?></h2>
			<p><?php esc_html_e( 'Choose what you want to work on. Each card shows its current status and next useful actions.', 'aomark-listings' ); ?></p>
		</div>
		<div class="aomark-listings-dashboard-total">
			<strong><?php echo esc_html( count( $models ) ); ?></strong>
			<span><?php esc_html_e( 'listing types', 'aomark-listings' ); ?></span>
			<small>
				<?php
				printf(
					/* translators: %s: number of published listings. */
					esc_html__( '%s published listings', 'aomark-listings' ),
					esc_html( $total_published )
				);
				?>
			</small>
		</div>
	</div>

	<?php if ( empty( $models ) ) : ?>
		<div class="aomark-listings-empty-state">
			<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
			<h3><?php esc_html_e( 'Create your first listing type', 'aomark-listings' ); ?></h3>
			<p><?php esc_html_e( 'Start with a preset below. Technical WordPress settings are generated for you.', 'aomark-listings' ); ?></p>
			<a class="button button-primary" href="#aomark-create-listing-type"><?php esc_html_e( 'Choose a starting point', 'aomark-listings' ); ?></a>
		</div>
	<?php else : ?>
		<div class="aomark-listings-dashboard-grid">
			<?php foreach ( $models as $model ) : ?>
				<?php
				$counts       = aomark_listings_admin_model_counts( $model );
				$has_content  = $counts['published'] + $counts['drafts'] > 0;
				$has_fields   = $counts['fields'] > 0;
				$edit_url     = aomark_listings_admin_url( [ 'edit' => $model['id'] ] );
				$add_url      = add_query_arg( 'post_type', $model['post_type'], admin_url( 'post-new.php' ) );
				$content_url  = add_query_arg( 'post_type', $model['post_type'], admin_url( 'edit.php' ) );
				?>
				<article class="aomark-listings-type-card">
					<header>
						<span class="dashicons <?php echo esc_attr( $model['menu_icon'] ?: 'dashicons-list-view' ); ?>" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html( $model['plural'] ); ?></h3>
							<p><?php echo esc_html( $model['singular'] ); ?></p>
						</div>
						<span class="aomark-listings-status-pill <?php echo $has_fields ? 'is-ready' : 'needs-attention'; ?>">
							<?php echo $has_fields ? esc_html__( 'Ready', 'aomark-listings' ) : esc_html__( 'Needs fields', 'aomark-listings' ); ?>
						</span>
					</header>

					<div class="aomark-listings-card-stats">
						<span><strong><?php echo esc_html( $counts['published'] ); ?></strong><?php esc_html_e( 'Published', 'aomark-listings' ); ?></span>
						<span><strong><?php echo esc_html( $counts['drafts'] ); ?></strong><?php esc_html_e( 'Drafts', 'aomark-listings' ); ?></span>
						<span><strong><?php echo esc_html( $counts['fields'] ); ?></strong><?php esc_html_e( 'Fields', 'aomark-listings' ); ?></span>
						<span><strong><?php echo esc_html( $counts['filters'] ); ?></strong><?php esc_html_e( 'Filters', 'aomark-listings' ); ?></span>
					</div>

					<ol class="aomark-listings-task-list">
						<li class="is-complete"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Listing type created', 'aomark-listings' ); ?></li>
						<li class="<?php echo $has_fields ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo $has_fields ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span><?php esc_html_e( 'Fields and filters reviewed', 'aomark-listings' ); ?></li>
						<li class="<?php echo $has_content ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo $has_content ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" aria-hidden="true"></span><?php esc_html_e( 'First listing added', 'aomark-listings' ); ?></li>
					</ol>

					<div class="aomark-listings-card-actions">
						<a class="button button-primary" href="<?php echo esc_url( $has_content ? $content_url : $add_url ); ?>"><?php echo $has_content ? esc_html__( 'Manage listings', 'aomark-listings' ) : esc_html__( 'Add first listing', 'aomark-listings' ); ?></a>
						<a class="button" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit type', 'aomark-listings' ); ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="aomark-listings-next-step">
		<span class="dashicons dashicons-welcome-widgets-menus" aria-hidden="true"></span>
		<div>
			<strong><?php esc_html_e( 'Ready to build the public page?', 'aomark-listings' ); ?></strong>
			<p><?php esc_html_e( 'Create a WordPress page, open it in Elementor and add the Aomark Listing Filter, Results and Map widgets.', 'aomark-listings' ); ?></p>
		</div>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ) ); ?>"><?php esc_html_e( 'Create a page', 'aomark-listings' ); ?></a>
	</div>
	<?php
}

function aomark_listings_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$models  = aomark_listings_get_models();
	$edit_id = isset( $_GET['edit'] ) ? aomark_listings_sanitize_id( wp_unslash( $_GET['edit'] ) ) : '';
	$editing = '' !== $edit_id && isset( $models[ $edit_id ] ) ? $models[ $edit_id ] : null;
	if ( '' !== $edit_id && ! $editing ) {
		aomark_listings_admin_notice( __( 'That listing type could not be found. Choose an available type below.', 'aomark-listings' ), 'error' );
	}
	aomark_listings_admin_add_query_notices( $editing );

	?>
	<div class="wrap aomark-listings-admin aomark-admin">
		<div class="aomark-listings-page aomark-page">
		<?php settings_errors( 'aomark_listings_messages' ); ?>
		<header class="aomark-listings-admin-header aomark-settings__header aomark-settings__brandbar">
			<div class="aomark-listings-brand">
				<div>
					<h1><?php esc_html_e( 'Aomark Listings', 'aomark-listings' ); ?></h1>
					<p><?php esc_html_e( 'Create, organize and display structured listings without managing WordPress internals.', 'aomark-listings' ); ?></p>
				</div>
			</div>
			<div class="aomark-listings-header-actions">
				<a class="aomark-listings-brand-logo-link" href="https://aomark.io" target="_blank" rel="noopener noreferrer">
					<img class="aomark-listings-brand-logo" src="<?php echo esc_url( AOMARK_LISTINGS_PLUGIN_URL . 'assets/img/aomark-logo-white.png' ); ?>" alt="<?php esc_attr_e( 'Aomark', 'aomark-listings' ); ?>">
				</a>
			</div>
		</header>

		<div class="aomark-listings-admin-layout">
			<aside class="aomark-listings-admin-sidebar">
				<div class="aomark-listings-admin-panel aomark-card">
					<div class="aomark-listings-panel-head">
						<h2><?php esc_html_e( 'Listing types', 'aomark-listings' ); ?></h2>
						<?php echo aomark_listings_help_tip( __( 'A listing type keeps the fields, categories and filters for one kind of content together.', 'aomark-listings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<a class="aomark-listings-overview-link<?php echo $editing ? '' : ' is-active'; ?>" href="<?php echo esc_url( aomark_listings_admin_url() ); ?>">
						<span class="dashicons dashicons-grid-view" aria-hidden="true"></span>
						<?php esc_html_e( 'Overview', 'aomark-listings' ); ?>
					</a>
					<?php if ( empty( $models ) ) : ?>
						<p class="aomark-listings-sidebar-empty"><?php esc_html_e( 'No listing types yet. Choose a starting point to create one.', 'aomark-listings' ); ?></p>
					<?php else : ?>
						<ul class="aomark-listings-model-list">
							<?php foreach ( $models as $model ) : ?>
								<?php $counts = aomark_listings_admin_model_counts( $model ); ?>
								<li>
									<a class="<?php echo $editing && $editing['id'] === $model['id'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( aomark_listings_admin_url( [ 'edit' => $model['id'] ] ) ); ?>">
										<strong><?php echo esc_html( $model['plural'] ); ?></strong>
										<span>
											<?php
											printf(
												/* translators: 1: published listing count, 2: field count. */
												esc_html__( '%1$s published · %2$s fields', 'aomark-listings' ),
												esc_html( $counts['published'] ),
												esc_html( $counts['fields'] )
											);
											?>
										</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<a class="button aomark-listings-new-type-button" href="<?php echo esc_url( aomark_listings_admin_url() . '#aomark-create-listing-type' ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'New listing type', 'aomark-listings' ); ?>
					</a>
				</div>
			</aside>

			<main class="aomark-listings-admin-main">
				<?php if ( $editing ) : ?>
					<?php aomark_listings_render_model_form( $editing ); ?>
				<?php else : ?>
					<?php aomark_listings_render_dashboard( $models ); ?>
					<?php aomark_listings_render_preset_creator(); ?>
				<?php endif; ?>
			</main>
			</div>
		</div>
	</div>
	<?php
}

function aomark_listings_render_model_form( $model ) {
	$field_types = aomark_listings_supported_field_types();
	$form_id     = 'aomark-listings-model-form-' . sanitize_html_class( $model['id'] );
	$counts      = aomark_listings_admin_model_counts( $model );
	$add_url     = add_query_arg( 'post_type', $model['post_type'], admin_url( 'post-new.php' ) );
	$preset_names = [
		'real_estate' => __( 'Real Estate', 'aomark-listings' ),
		'directory'   => __( 'Directory', 'aomark-listings' ),
		'custom'      => __( 'Custom', 'aomark-listings' ),
	];
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="aomark-listings-model-form">
		<?php wp_nonce_field( 'aomark_listings_admin', 'aomark_listings_nonce' ); ?>
		<input type="hidden" name="aomark_listings_action" value="save_model">
		<input type="hidden" name="old_model_id" value="<?php echo esc_attr( $model['id'] ); ?>">
		<input type="hidden" name="model[preset]" value="<?php echo esc_attr( $model['preset'] ); ?>">

		<div class="aomark-listings-admin-panel aomark-listings-model-editor aomark-card">
			<div class="aomark-listings-model-toolbar">
				<div>
					<p class="aomark-listings-eyebrow"><?php esc_html_e( 'Listing type', 'aomark-listings' ); ?></p>
					<h2><?php echo esc_html( $model['plural'] ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: 1: published listing count, 2: draft listing count. */
							esc_html__( '%1$s published · %2$s drafts', 'aomark-listings' ),
							esc_html( $counts['published'] ),
							esc_html( $counts['drafts'] )
						);
						?>
					</p>
				</div>
				<div class="aomark-listings-model-badges">
					<span><?php echo esc_html( $preset_names[ $model['preset'] ] ?? __( 'Custom', 'aomark-listings' ) ); ?></span>
					<span><?php echo esc_html( count( (array) $model['fields'] ) ); ?> <?php esc_html_e( 'fields', 'aomark-listings' ); ?></span>
					<a class="button" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add listing', 'aomark-listings' ); ?></a>
				</div>
			</div>

			<nav class="aomark-listings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Listing type editor sections', 'aomark-listings' ); ?>">
				<button id="aomark-tab-basics" type="button" role="tab" aria-selected="true" aria-controls="aomark-panel-basics" class="aomark-listings-tab is-active" data-aomark-admin-tab="basics"><?php esc_html_e( 'Basics', 'aomark-listings' ); ?></button>
				<button id="aomark-tab-taxonomies" type="button" role="tab" aria-selected="false" aria-controls="aomark-panel-taxonomies" tabindex="-1" class="aomark-listings-tab" data-aomark-admin-tab="taxonomies"><?php esc_html_e( 'Categories & Filters', 'aomark-listings' ); ?></button>
				<button id="aomark-tab-fields" type="button" role="tab" aria-selected="false" aria-controls="aomark-panel-fields" tabindex="-1" class="aomark-listings-tab" data-aomark-admin-tab="fields"><?php esc_html_e( 'Listing Fields', 'aomark-listings' ); ?></button>
				<button id="aomark-tab-advanced" type="button" role="tab" aria-selected="false" aria-controls="aomark-panel-advanced" tabindex="-1" class="aomark-listings-tab" data-aomark-admin-tab="advanced"><?php esc_html_e( 'Advanced', 'aomark-listings' ); ?></button>
			</nav>

			<section id="aomark-panel-basics" class="aomark-listings-tab-panel is-active" role="tabpanel" aria-labelledby="aomark-tab-basics" data-aomark-admin-panel="basics">
				<div class="aomark-listings-section-intro">
					<div>
						<h3><?php esc_html_e( 'Names people will see', 'aomark-listings' ); ?></h3>
						<p><?php esc_html_e( 'These names appear in the WordPress menu, content editor and listing widgets. They can be changed safely.', 'aomark-listings' ); ?></p>
					</div>
					<span class="aomark-listings-status-pill is-ready"><?php esc_html_e( 'Safe to edit', 'aomark-listings' ); ?></span>
				</div>
				<div class="aomark-listings-grid">
					<label>
						<span><?php esc_html_e( 'One item', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'singular_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[singular]" value="<?php echo esc_attr( $model['singular'] ); ?>" maxlength="120" required>
						<small><?php esc_html_e( 'For example: Property', 'aomark-listings' ); ?></small>
					</label>
					<label>
						<span><?php esc_html_e( 'Collection', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'plural_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[plural]" value="<?php echo esc_attr( $model['plural'] ); ?>" maxlength="120" required>
						<small><?php esc_html_e( 'For example: Properties', 'aomark-listings' ); ?></small>
					</label>
				</div>
				<div class="aomark-listings-basic-next">
					<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
					<p><strong><?php esc_html_e( 'Next:', 'aomark-listings' ); ?></strong> <?php esc_html_e( 'Review Categories & Filters, then decide which Listing Fields should appear on result cards.', 'aomark-listings' ); ?></p>
				</div>
			</section>

			<section id="aomark-panel-taxonomies" class="aomark-listings-tab-panel" role="tabpanel" aria-labelledby="aomark-tab-taxonomies" data-aomark-admin-panel="taxonomies" hidden>
				<div class="aomark-listings-panel-head">
					<div>
						<h3><?php esc_html_e( 'Categories & Filters', 'aomark-listings' ); ?></h3>
						<p><?php esc_html_e( 'Organize listings into groups visitors can browse or use as filters, such as Location, Type or Status.', 'aomark-listings' ); ?></p>
					</div>
					<button type="button" class="button" data-aomark-add-row="taxonomy"><?php esc_html_e( 'Add category group', 'aomark-listings' ); ?></button>
				</div>
				<div class="aomark-listings-repeaters" data-aomark-rows="taxonomy">
					<?php foreach ( (array) $model['taxonomies'] as $index => $taxonomy ) : ?>
						<?php aomark_listings_render_taxonomy_row( $taxonomy, $index ); ?>
					<?php endforeach; ?>
				</div>
				<div class="aomark-listings-empty-state aomark-listings-empty-state--inline" data-aomark-empty="taxonomy" <?php echo empty( $model['taxonomies'] ) ? '' : 'hidden'; ?>>
					<span class="dashicons dashicons-category" aria-hidden="true"></span>
					<h4><?php esc_html_e( 'No category groups yet', 'aomark-listings' ); ?></h4>
					<p><?php esc_html_e( 'Add one when listings need categories, locations, types or other reusable groups.', 'aomark-listings' ); ?></p>
					<button type="button" class="button" data-aomark-add-row="taxonomy"><?php esc_html_e( 'Add category group', 'aomark-listings' ); ?></button>
				</div>
			</section>

			<section id="aomark-panel-fields" class="aomark-listings-tab-panel" role="tabpanel" aria-labelledby="aomark-tab-fields" data-aomark-admin-panel="fields" hidden>
				<div class="aomark-listings-panel-head">
					<div>
						<h3><?php esc_html_e( 'Listing Fields', 'aomark-listings' ); ?></h3>
						<p><?php esc_html_e( 'Choose what editors enter for each listing and where those values can be used.', 'aomark-listings' ); ?></p>
					</div>
					<button type="button" class="button" data-aomark-add-row="field"><?php esc_html_e( 'Add field', 'aomark-listings' ); ?></button>
				</div>
				<div class="aomark-listings-repeaters" data-aomark-rows="field">
					<?php foreach ( (array) $model['fields'] as $index => $field ) : ?>
						<?php aomark_listings_render_field_row( $field, $index, $field_types ); ?>
					<?php endforeach; ?>
				</div>
				<div class="aomark-listings-empty-state aomark-listings-empty-state--inline" data-aomark-empty="field" <?php echo empty( $model['fields'] ) ? '' : 'hidden'; ?>>
					<span class="dashicons dashicons-editor-table" aria-hidden="true"></span>
					<h4><?php esc_html_e( 'No custom fields yet', 'aomark-listings' ); ?></h4>
					<p><?php esc_html_e( 'Add a field for details such as price, phone, area, gallery or map location.', 'aomark-listings' ); ?></p>
					<button type="button" class="button" data-aomark-add-row="field"><?php esc_html_e( 'Add first field', 'aomark-listings' ); ?></button>
				</div>
				<div class="aomark-listings-repeater-add">
					<button type="button" class="aomark-listings-add-button" data-aomark-add-row="field" aria-label="<?php esc_attr_e( 'Add field', 'aomark-listings' ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Add field', 'aomark-listings' ); ?></span>
					</button>
				</div>
			</section>

			<section id="aomark-panel-advanced" class="aomark-listings-tab-panel" role="tabpanel" aria-labelledby="aomark-tab-advanced" data-aomark-admin-panel="advanced" hidden>
				<div class="aomark-listings-section-intro">
					<div>
						<h3><?php esc_html_e( 'Advanced WordPress settings', 'aomark-listings' ); ?></h3>
						<p><?php esc_html_e( 'Most sites should keep these values unchanged. Friendly names can be edited safely in Basics.', 'aomark-listings' ); ?></p>
					</div>
				</div>

				<div class="aomark-listings-technical-warning">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<div>
						<strong><?php esc_html_e( 'Technical identifiers are read-only', 'aomark-listings' ); ?></strong>
						<p><?php esc_html_e( 'They connect existing content, URLs and Elementor settings. A dedicated migration is required to change them safely, so normal saves always preserve these values.', 'aomark-listings' ); ?></p>
					</div>
				</div>

				<div class="aomark-listings-grid">
					<label>
						<span><?php esc_html_e( 'Listing Type ID', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'model_id' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[id]" value="<?php echo esc_attr( $model['id'] ); ?>" maxlength="64" data-aomark-technical-input readonly>
					</label>
					<label>
						<span><?php esc_html_e( 'WordPress Post Type', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'cpt_slug' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[post_type]" value="<?php echo esc_attr( $model['post_type'] ); ?>" maxlength="20" data-aomark-technical-input readonly>
					</label>
					<label>
						<span><?php esc_html_e( 'Admin Menu Icon', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'menu_icon' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[menu_icon]" value="<?php echo esc_attr( $model['menu_icon'] ); ?>">
					</label>
					<div class="aomark-listings-advanced-switches">
						<label class="aomark-listings-check">
							<input type="checkbox" name="model[has_archive]" value="1" <?php checked( $model['has_archive'] ); ?>>
							<span><?php esc_html_e( 'Enable public archive', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'has_archive' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</label>
						<label class="aomark-listings-check">
							<input type="checkbox" name="model[show_in_rest]" value="1" <?php checked( $model['show_in_rest'] ); ?>>
							<span><?php esc_html_e( 'Enable block editor and REST API', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'show_in_rest' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</label>
					</div>
				</div>
			</section>
		</div>
	</form>

	<div class="aomark-listings-submit-bar">
		<div class="aomark-listings-submit-actions">
			<button form="<?php echo esc_attr( $form_id ); ?>" type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save listing type', 'aomark-listings' ); ?></button>
			<a class="button" href="<?php echo esc_url( aomark_listings_admin_url() ); ?>"><?php esc_html_e( 'Close', 'aomark-listings' ); ?></a>
		</div>
		<form method="post" class="aomark-listings-delete-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this listing type? Existing posts will remain in the database, but WordPress will no longer show them until a compatible listing type is restored.', 'aomark-listings' ) ); ?>');">
			<?php wp_nonce_field( 'aomark_listings_admin', 'aomark_listings_nonce' ); ?>
			<input type="hidden" name="aomark_listings_action" value="delete_model">
			<input type="hidden" name="model_id" value="<?php echo esc_attr( $model['id'] ); ?>">
			<button type="submit" class="button aomark-listings-danger-button"><?php esc_html_e( 'Delete listing type', 'aomark-listings' ); ?></button>
		</form>
	</div>

	<script type="text/template" id="tmpl-aomark-taxonomy-row">
		<?php aomark_listings_render_taxonomy_row( [ 'id' => '', 'slug' => '', 'singular' => '', 'plural' => '', 'hierarchical' => true, 'filterable' => true ], '__i__' ); ?>
	</script>
	<script type="text/template" id="tmpl-aomark-field-row">
		<?php aomark_listings_render_field_row( [ 'id' => '', 'key' => '', 'label' => '', 'type' => 'text', 'group' => '', 'filterable' => false, 'card' => false, 'suffix' => '', 'placeholder' => '', 'options' => [] ], '__i__', $field_types ); ?>
	</script>
	<?php
}

function aomark_listings_render_taxonomy_row( $taxonomy, $index ) {
	$row_number = is_numeric( $index ) ? ( (int) $index + 1 ) : '+';
	$title      = ! empty( $taxonomy['singular'] ) ? $taxonomy['singular'] : __( 'New category group', 'aomark-listings' );
	$panel_id   = 'aomark-listings-taxonomy-' . sanitize_html_class( (string) $index );
	?>
	<div class="aomark-listings-repeater-row aomark-listings-repeater-row--taxonomy is-collapsed">
		<input type="hidden" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][original_id]" value="<?php echo esc_attr( $taxonomy['id'] ?? '' ); ?>">
		<div class="aomark-listings-row-summary">
			<button type="button" class="aomark-listings-row-toggle" data-aomark-toggle-row aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<span class="aomark-listings-row-index"><?php echo esc_html( $row_number ); ?></span>
				<span class="aomark-listings-row-title">
					<strong><?php echo esc_html( $title ); ?></strong>
					<small class="aomark-listings-row-tab"><?php echo ! empty( $taxonomy['filterable'] ) ? esc_html__( 'Available as a filter', 'aomark-listings' ) : esc_html__( 'Organization only', 'aomark-listings' ); ?></small>
				</span>
				<span class="dashicons dashicons-arrow-down-alt2 aomark-listings-row-chevron" aria-hidden="true"></span>
			</button>
			<span class="aomark-listings-type-pill"><?php echo ! empty( $taxonomy['hierarchical'] ) ? esc_html__( 'Category style', 'aomark-listings' ) : esc_html__( 'Tag style', 'aomark-listings' ); ?></span>
			<button type="button" class="aomark-listings-remove-button" data-aomark-remove-row aria-label="<?php esc_attr_e( 'Remove category group', 'aomark-listings' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove category group', 'aomark-listings' ); ?></span></button>
		</div>
		<div class="aomark-listings-row-details" id="<?php echo esc_attr( $panel_id ); ?>">
			<div class="aomark-listings-row-purpose">
				<strong><?php esc_html_e( 'Name and behavior', 'aomark-listings' ); ?></strong>
				<p><?php esc_html_e( 'Use category style for nested choices such as Country → City. Use tag style for a flat list such as Amenities.', 'aomark-listings' ); ?></p>
			</div>
			<div class="aomark-listings-grid">
				<label><span><?php esc_html_e( 'One category', 'aomark-listings' ); ?></span><input type="text" data-aomark-taxonomy-singular name="model[taxonomies][<?php echo esc_attr( $index ); ?>][singular]" value="<?php echo esc_attr( $taxonomy['singular'] ?? '' ); ?>" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Location', 'aomark-listings' ); ?>" required></label>
				<label><span><?php esc_html_e( 'Category collection', 'aomark-listings' ); ?></span><input type="text" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][plural]" value="<?php echo esc_attr( $taxonomy['plural'] ?? '' ); ?>" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Locations', 'aomark-listings' ); ?>" required></label>
				<label class="aomark-listings-check"><input type="checkbox" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][hierarchical]" value="1" <?php checked( ! empty( $taxonomy['hierarchical'] ) ); ?>> <span><?php esc_html_e( 'Allow parent and child categories', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_hierarchical' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
				<label class="aomark-listings-check"><input type="checkbox" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][filterable]" value="1" <?php checked( ! empty( $taxonomy['filterable'] ) ); ?>> <span><?php esc_html_e( 'Use as a visitor filter', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_filterable' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
			</div>
			<details class="aomark-listings-technical-details">
				<summary><?php esc_html_e( 'Technical details', 'aomark-listings' ); ?></summary>
				<div class="aomark-listings-grid">
					<label><span><?php esc_html_e( 'Category Group ID', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_id' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" data-aomark-taxonomy-id data-aomark-technical-input name="model[taxonomies][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $taxonomy['id'] ?? '' ); ?>" maxlength="64" readonly></label>
					<label><span><?php esc_html_e( 'WordPress Taxonomy', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_slug' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" data-aomark-taxonomy-slug data-aomark-technical-input name="model[taxonomies][<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $taxonomy['slug'] ?? '' ); ?>" maxlength="32" readonly></label>
				</div>
			</details>
		</div>
	</div>
	<?php
}

function aomark_listings_render_field_row( $field, $index, $field_types ) {
	$row_number = is_numeric( $index ) ? ( (int) $index + 1 ) : '+';
	$type       = $field['type'] ?? 'text';
	$type_label = $field_types[ $type ] ?? ucfirst( $type );
	$title      = ! empty( $field['label'] ) ? $field['label'] : __( 'New field', 'aomark-listings' );
	$group      = sanitize_key( $field['group'] ?? '' );
	$panel_id   = 'aomark-listings-field-' . sanitize_html_class( (string) $index );
	$group_options = [
		''         => __( 'Automatic', 'aomark-listings' ),
		'details'  => __( 'Details', 'aomark-listings' ),
		'features' => __( 'Features', 'aomark-listings' ),
		'location' => __( 'Location', 'aomark-listings' ),
		'media'    => __( 'Media', 'aomark-listings' ),
	];
	$effective_group = aomark_listings_metabox_group_for_field( $field );
	/* translators: %s: automatically selected field group label. */
	$summary_group   = $group ? ( $group_options[ $group ] ?? $group_options[''] ) : sprintf( __( 'Automatic · %s', 'aomark-listings' ), aomark_listings_metabox_group_label( $effective_group ) );
	$filter_supported = ! in_array( $type, [ 'image', 'gallery', 'location', 'textarea' ], true );
	?>
	<div class="aomark-listings-repeater-row aomark-listings-repeater-row--field is-collapsed">
		<input type="hidden" name="model[fields][<?php echo esc_attr( $index ); ?>][original_id]" value="<?php echo esc_attr( $field['id'] ?? '' ); ?>">
		<div class="aomark-listings-row-summary">
			<button type="button" class="aomark-listings-row-toggle" data-aomark-toggle-row aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<span class="aomark-listings-row-index"><?php echo esc_html( $row_number ); ?></span>
				<span class="aomark-listings-row-title">
					<strong><?php echo esc_html( $title ); ?></strong>
					<small class="aomark-listings-row-tab"><?php echo esc_html( $summary_group ); ?></small>
				</span>
				<span class="dashicons dashicons-arrow-down-alt2 aomark-listings-row-chevron" aria-hidden="true"></span>
			</button>
			<span class="aomark-listings-type-pill aomark-listings-type-pill--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type_label ); ?></span>
			<button type="button" class="aomark-listings-remove-button" data-aomark-remove-row aria-label="<?php esc_attr_e( 'Remove field', 'aomark-listings' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove field', 'aomark-listings' ); ?></span></button>
		</div>
		<div class="aomark-listings-row-details" id="<?php echo esc_attr( $panel_id ); ?>">
			<div class="aomark-listings-field-sections">
				<div class="aomark-listings-field-section">
					<div class="aomark-listings-row-purpose">
						<strong><?php esc_html_e( 'Editor input', 'aomark-listings' ); ?></strong>
						<p><?php esc_html_e( 'Define what editors enter while creating or updating a listing.', 'aomark-listings' ); ?></p>
					</div>
					<div class="aomark-listings-grid">
						<label><span><?php esc_html_e( 'Field name', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" data-aomark-field-label name="model[fields][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>" maxlength="120" placeholder="<?php esc_attr_e( 'e.g. Price', 'aomark-listings' ); ?>" required></label>
						<label>
							<span><?php esc_html_e( 'Input type', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_type' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<select name="model[fields][<?php echo esc_attr( $index ); ?>][type]">
								<?php foreach ( $field_types as $field_type => $label ) : ?>
									<option value="<?php echo esc_attr( $field_type ); ?>" <?php selected( $field['type'] ?? 'text', $field_type ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Editor section', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_group' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<select name="model[fields][<?php echo esc_attr( $index ); ?>][group]">
								<?php foreach ( $group_options as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $group, $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="aomark-listings-placeholder<?php echo in_array( $type, [ 'text', 'textarea', 'number', 'price', 'select', 'location', 'url', 'email', 'date' ], true ) ? ' is-visible' : ''; ?>"><span><?php esc_html_e( 'Input hint', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_placeholder' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" name="model[fields][<?php echo esc_attr( $index ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Enter price', 'aomark-listings' ); ?>"></label>
					</div>
				</div>

				<div class="aomark-listings-field-section">
					<div class="aomark-listings-row-purpose">
						<strong><?php esc_html_e( 'Search & display', 'aomark-listings' ); ?></strong>
						<p><?php esc_html_e( 'Choose how the saved value is used on public listing pages.', 'aomark-listings' ); ?></p>
					</div>
					<div class="aomark-listings-grid">
						<label><span><?php esc_html_e( 'Value suffix', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_suffix' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" name="model[fields][<?php echo esc_attr( $index ); ?>][suffix]" value="<?php echo esc_attr( $field['suffix'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. m² or km', 'aomark-listings' ); ?>"></label>
						<label class="aomark-listings-check<?php echo $filter_supported ? '' : ' is-disabled'; ?>"><input type="checkbox" data-aomark-field-filterable name="model[fields][<?php echo esc_attr( $index ); ?>][filterable]" value="1" <?php checked( ! empty( $field['filterable'] ) ); ?> <?php disabled( ! $filter_supported ); ?>> <span><?php esc_html_e( 'Use as a visitor filter', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_filterable' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
						<label class="aomark-listings-check"><input type="checkbox" name="model[fields][<?php echo esc_attr( $index ); ?>][card]" value="1" <?php checked( ! empty( $field['card'] ) ); ?>> <span><?php esc_html_e( 'Show on result cards', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_card' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
					</div>
				</div>
			</div>
			<label class="aomark-listings-options<?php echo 'select' === $type ? ' is-visible' : ''; ?>">
				<span><?php esc_html_e( 'Choices', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_options' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<textarea name="model[fields][<?php echo esc_attr( $index ); ?>][options]" rows="3" placeholder="value|Label"><?php echo esc_textarea( aomark_listings_select_options_to_text( $field['options'] ?? [] ) ); ?></textarea>
				<small><?php esc_html_e( 'One choice per line. Example: available|Available', 'aomark-listings' ); ?></small>
			</label>
			<details class="aomark-listings-technical-details">
				<summary><?php esc_html_e( 'Technical details', 'aomark-listings' ); ?></summary>
				<div class="aomark-listings-grid">
					<label><span><?php esc_html_e( 'Field ID', 'aomark-listings' ); ?></span><input type="text" data-aomark-field-id data-aomark-technical-input name="model[fields][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $field['id'] ?? '' ); ?>" maxlength="64" readonly></label>
					<label><span><?php esc_html_e( 'Database Meta Key', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_key' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" data-aomark-field-key data-aomark-technical-input name="model[fields][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ?? '' ); ?>" maxlength="191" readonly></label>
				</div>
			</details>
		</div>
	</div>
	<?php
}
