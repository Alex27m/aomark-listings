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

function aomark_listings_admin_url( $args = [] ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=aomark-listings' ) );
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
		'model_id'              => __( 'Internal model identifier. Use lowercase letters, numbers and underscores. Changing it changes how Elementor widgets reference this model.', 'aomark-listings' ),
		'cpt_slug'              => __( 'WordPress post type key. Keep it short and unique; WordPress allows up to 20 characters.', 'aomark-listings' ),
		'singular_label'        => __( 'Label used for one item in the WordPress admin, for example Property or Directory Item.', 'aomark-listings' ),
		'plural_label'          => __( 'Label used for the menu and lists, for example Properties or Directory Items.', 'aomark-listings' ),
		'menu_icon'             => __( 'Dashicon class used in the WordPress admin menu, for example dashicons-building.', 'aomark-listings' ),
		'has_archive'           => __( 'Enable a public archive URL for this model. Flush permalinks after changing archive-related settings.', 'aomark-listings' ),
		'show_in_rest'          => __( 'Expose this model to the block editor and REST API. Keep enabled for modern WordPress workflows.', 'aomark-listings' ),
		'taxonomy_id'           => __( 'Internal taxonomy identifier inside this model. Use lowercase letters, numbers and underscores.', 'aomark-listings' ),
		'taxonomy_slug'         => __( 'Registered WordPress taxonomy slug. It must be unique across the site and is limited to 32 characters.', 'aomark-listings' ),
		'taxonomy_hierarchical' => __( 'Hierarchical taxonomies behave like categories. Disabled taxonomies behave like tags.', 'aomark-listings' ),
		'taxonomy_filterable'   => __( 'Makes this taxonomy available in model-aware Elementor filter controls.', 'aomark-listings' ),
		'field_key'             => __( 'Post meta key used in the database. It is generated from Label for new fields and normalized to start with aomark_.', 'aomark-listings' ),
		'field_label'           => __( 'Human friendly label shown in metaboxes, Elementor controls and frontend metadata. New fields use it to generate ID and Meta Key automatically.', 'aomark-listings' ),
		'field_type'            => __( 'Controls the editor input, saved value format and compatible Elementor widgets.', 'aomark-listings' ),
		'field_group'           => __( 'Choose the tab where this field appears while editing a listing. Automatic groups common real-estate features, location and media fields for you.', 'aomark-listings' ),
		'field_suffix'          => __( 'Optional text displayed after values, for example m2, EUR or km.', 'aomark-listings' ),
		'field_placeholder'     => __( 'Optional faded helper text shown inside an empty editor field, for example Enter price.', 'aomark-listings' ),
		'field_filterable'      => __( 'Makes this field available to Listing Filter and query meta filters.', 'aomark-listings' ),
		'field_card'            => __( 'Marks this field as important card metadata for Listing Results and Listing Meta widgets.', 'aomark-listings' ),
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

function aomark_listings_handle_admin_actions() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( empty( $_POST['aomark_listings_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['aomark_listings_action'] ) );

	if ( ! isset( $_POST['aomark_listings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aomark_listings_nonce'] ) ), 'aomark_listings_admin' ) ) {
		aomark_listings_admin_notice( __( 'Security check failed.', 'aomark-listings' ), 'error' );
		return;
	}

	$models = aomark_listings_get_models();

	if ( 'create_model' === $action ) {
		$preset = sanitize_key( wp_unslash( $_POST['preset'] ?? 'custom' ) );
		$base   = 'real_estate' === $preset ? 'real_estate' : ( 'directory' === $preset ? 'directory' : 'custom_listing' );
		$id     = aomark_listings_unique_model_id( $base, $models );
		$model  = aomark_listings_preset_model( $preset, $id );

		if ( isset( $models[ $id ] ) ) {
			aomark_listings_admin_notice( __( 'Model already exists.', 'aomark-listings' ), 'error' );
			return;
		}

		$models[ $id ] = aomark_listings_sanitize_model( $model );
		update_option( AOMARK_LISTINGS_OPTION, $models, false );
		flush_rewrite_rules();
		wp_safe_redirect( aomark_listings_admin_url( [ 'edit' => $id, 'created' => 1 ] ) );
		exit;
	}

	if ( 'delete_model' === $action ) {
		$id = aomark_listings_sanitize_id( wp_unslash( $_POST['model_id'] ?? '' ) );
		if ( $id && isset( $models[ $id ] ) ) {
			unset( $models[ $id ] );
			update_option( AOMARK_LISTINGS_OPTION, $models, false );
			flush_rewrite_rules();
		}
		wp_safe_redirect( aomark_listings_admin_url( [ 'deleted' => 1 ] ) );
		exit;
	}

	if ( 'save_model' === $action ) {
		$raw = isset( $_POST['model'] ) && is_array( $_POST['model'] ) ? wp_unslash( $_POST['model'] ) : [];
		$old_id = aomark_listings_sanitize_id( wp_unslash( $_POST['old_model_id'] ?? '' ) );
		$model = aomark_listings_admin_normalize_posted_model( $raw );

		if ( $old_id && $old_id !== $model['id'] && isset( $models[ $old_id ] ) ) {
			unset( $models[ $old_id ] );
		}

		$models[ $model['id'] ] = $model;
		update_option( AOMARK_LISTINGS_OPTION, $models, false );
		flush_rewrite_rules();
		wp_safe_redirect( aomark_listings_admin_url( [ 'edit' => $model['id'], 'updated' => 1 ] ) );
		exit;
	}
}
add_action( 'admin_init', 'aomark_listings_handle_admin_actions' );

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

	foreach ( (array) ( $raw['taxonomies'] ?? [] ) as $taxonomy ) {
		if ( empty( $taxonomy['id'] ) && empty( $taxonomy['slug'] ) && empty( $taxonomy['singular'] ) && empty( $taxonomy['plural'] ) ) {
			continue;
		}
		$model['taxonomies'][] = $taxonomy;
	}

	foreach ( (array) ( $raw['fields'] ?? [] ) as $field ) {
		if ( empty( $field['id'] ) && empty( $field['key'] ) && empty( $field['label'] ) ) {
			continue;
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

	wp_enqueue_style( 'aomark-listings-admin-menu', AOMARK_LISTINGS_PLUGIN_URL . 'assets/css/admin-menu.css', [], AOMARK_LISTINGS_VERSION );

	if ( 'toplevel_page_aomark-listings' === $hook ) {
		wp_enqueue_style( 'aomark-listings-admin', AOMARK_LISTINGS_PLUGIN_URL . 'assets/css/admin-listings.css', [], AOMARK_LISTINGS_VERSION );
		wp_enqueue_script( 'aomark-listings-admin', AOMARK_LISTINGS_PLUGIN_URL . 'assets/js/admin-listings.js', [ 'jquery' ], AOMARK_LISTINGS_VERSION, true );
	}

	if ( $is_listing_editor ) {
		wp_enqueue_media();
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4' );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true );
		wp_enqueue_style( 'aomark-listings-admin', AOMARK_LISTINGS_PLUGIN_URL . 'assets/css/admin-listings.css', [], AOMARK_LISTINGS_VERSION );
		wp_enqueue_script( 'aomark-listings-admin', AOMARK_LISTINGS_PLUGIN_URL . 'assets/js/admin-listings.js', [ 'jquery', 'leaflet' ], AOMARK_LISTINGS_VERSION, true );
		wp_localize_script(
			'aomark-listings-admin',
			'AomarkListingsAdmin',
			[
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'geocodeNonce'  => wp_create_nonce( 'aomark_listings_geocode' ),
				'defaultCenter' => apply_filters( 'aomark_listings_editor_map_default_center', [ 20, 0 ] ),
				'defaultZoom'   => (int) apply_filters( 'aomark_listings_editor_map_default_zoom', 2 ),
				'strings'       => [
					'searching'      => __( 'Searching addresses…', 'aomark-listings' ),
					'noResults'      => __( 'No matching addresses found.', 'aomark-listings' ),
					'searchError'    => __( 'Address search is temporarily unavailable.', 'aomark-listings' ),
					'mapUnavailable' => __( 'Map could not be loaded. Check the internet connection and reload the editor.', 'aomark-listings' ),
					'typeMore'       => __( 'Type at least three characters to search.', 'aomark-listings' ),
					'locationSet'    => __( 'Location selected. Drag the pin or click the map to fine-tune it.', 'aomark-listings' ),
					'pinMoved'       => __( 'Pin position updated.', 'aomark-listings' ),
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
	$parts = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $parts ) ) ) );

	return implode( ', ', $parts );
}

function aomark_listings_ajax_geocode() {
	check_ajax_referer( 'aomark_listings_geocode', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( [ 'message' => __( 'You are not allowed to search locations.', 'aomark-listings' ) ], 403 );
	}

	$query = sanitize_text_field( wp_unslash( $_GET['query'] ?? '' ) );
	if ( strlen( $query ) < 3 ) {
		wp_send_json_success( [ 'results' => [] ] );
	}

	$country_code = sanitize_key( apply_filters( 'aomark_listings_geocoder_country_code', '' ) );
	$cache_key    = 'aomark_geo_' . md5( strtolower( $country_code . '|' . $query ) );
	$cached       = get_transient( $cache_key );

	if ( false !== $cached ) {
		wp_send_json_success( [ 'results' => $cached ] );
	}

	$endpoint = apply_filters( 'aomark_listings_geocoder_endpoint', 'https://photon.komoot.io/api/' );
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
			'timeout'    => 8,
			'redirection' => 2,
			'user-agent' => 'Aomark Listings/' . AOMARK_LISTINGS_VERSION . '; ' . home_url( '/' ),
		]
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_send_json_error( [ 'message' => __( 'Address search is temporarily unavailable.', 'aomark-listings' ) ], 502 );
	}

	$payload = json_decode( wp_remote_retrieve_body( $response ), true );
	$results = [];

	foreach ( (array) ( $payload['features'] ?? [] ) as $feature ) {
		$coordinates = $feature['geometry']['coordinates'] ?? [];
		$lng         = isset( $coordinates[0] ) ? (float) $coordinates[0] : null;
		$lat         = isset( $coordinates[1] ) ? (float) $coordinates[1] : null;
		$label       = aomark_listings_geocode_result_label( $feature['properties'] ?? [] );

		if ( null === $lat || null === $lng || '' === $label || abs( $lat ) > 90 || abs( $lng ) > 180 ) {
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

function aomark_listings_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$models  = aomark_listings_get_models();
	$edit_id = isset( $_GET['edit'] ) ? aomark_listings_sanitize_id( wp_unslash( $_GET['edit'] ) ) : '';
	$editing = $edit_id && isset( $models[ $edit_id ] ) ? $models[ $edit_id ] : null;

	?>
	<div class="wrap aomark-listings-admin aomark-admin">
		<div class="aomark-listings-page aomark-page">
		<?php settings_errors( 'aomark_listings_messages' ); ?>
		<header class="aomark-listings-admin-header aomark-settings__header aomark-settings__brandbar">
			<div class="aomark-listings-brand">
				<div>
					<h1><?php esc_html_e( 'Aomark Listings', 'aomark-listings' ); ?></h1>
					<p><?php esc_html_e( 'Model builder for CPTs, fields, filters and Elementor listing widgets.', 'aomark-listings' ); ?></p>
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
						<h2><?php esc_html_e( 'Models', 'aomark-listings' ); ?></h2>
						<?php echo aomark_listings_help_tip( __( 'Each model creates its own CPT, taxonomies, fields and model-aware Elementor options.', 'aomark-listings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<?php if ( empty( $models ) ) : ?>
						<p><?php esc_html_e( 'No listing models yet.', 'aomark-listings' ); ?></p>
					<?php else : ?>
						<ul class="aomark-listings-model-list">
							<?php foreach ( $models as $model ) : ?>
								<li>
									<a class="<?php echo $editing && $editing['id'] === $model['id'] ? 'is-active' : ''; ?>" href="<?php echo esc_url( aomark_listings_admin_url( [ 'edit' => $model['id'] ] ) ); ?>">
										<strong><?php echo esc_html( $model['plural'] ); ?></strong>
										<span><?php echo esc_html( $model['post_type'] ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="aomark-listings-admin-panel aomark-card">
					<div class="aomark-listings-panel-head">
						<h2><?php esc_html_e( 'Create Model', 'aomark-listings' ); ?></h2>
						<?php echo aomark_listings_help_tip( __( 'Presets create a useful starting schema. You can rename, remove and customize everything after creation.', 'aomark-listings' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<form method="post">
						<?php wp_nonce_field( 'aomark_listings_admin', 'aomark_listings_nonce' ); ?>
						<input type="hidden" name="aomark_listings_action" value="create_model">
						<select name="preset" class="widefat">
							<option value="real_estate"><?php esc_html_e( 'Real Estate', 'aomark-listings' ); ?></option>
							<option value="directory"><?php esc_html_e( 'Directory', 'aomark-listings' ); ?></option>
							<option value="custom"><?php esc_html_e( 'Custom Listing', 'aomark-listings' ); ?></option>
						</select>
						<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create From Preset', 'aomark-listings' ); ?></button></p>
					</form>
				</div>
			</aside>

			<main class="aomark-listings-admin-main">
				<?php if ( $editing ) : ?>
					<?php aomark_listings_render_model_form( $editing ); ?>
				<?php else : ?>
					<div class="aomark-listings-admin-panel aomark-card">
						<h2><?php esc_html_e( 'Select a model', 'aomark-listings' ); ?></h2>
						<p><?php esc_html_e( 'Choose a model from the list or create one from a preset.', 'aomark-listings' ); ?></p>
					</div>
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
	?>
	<form id="<?php echo esc_attr( $form_id ); ?>" method="post" class="aomark-listings-model-form">
		<?php wp_nonce_field( 'aomark_listings_admin', 'aomark_listings_nonce' ); ?>
		<input type="hidden" name="aomark_listings_action" value="save_model">
		<input type="hidden" name="old_model_id" value="<?php echo esc_attr( $model['id'] ); ?>">
		<input type="hidden" name="model[preset]" value="<?php echo esc_attr( $model['preset'] ); ?>">

		<div class="aomark-listings-admin-panel aomark-listings-model-editor aomark-card">
			<div class="aomark-listings-model-toolbar">
				<div>
					<h2><?php echo esc_html( $model['plural'] ); ?></h2>
					<p><?php echo esc_html( $model['post_type'] ); ?></p>
				</div>
				<div class="aomark-listings-model-badges">
					<span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $model['preset'] ) ) ); ?></span>
					<span><?php echo esc_html( count( (array) $model['fields'] ) ); ?> <?php esc_html_e( 'fields', 'aomark-listings' ); ?></span>
				</div>
			</div>

			<nav class="aomark-listings-tabs aomark-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Model editor sections', 'aomark-listings' ); ?>">
				<button type="button" class="aomark-tab is-active" data-aomark-admin-tab="overview"><?php esc_html_e( 'Overview', 'aomark-listings' ); ?></button>
				<button type="button" class="aomark-tab" data-aomark-admin-tab="taxonomies"><?php esc_html_e( 'Taxonomies', 'aomark-listings' ); ?></button>
				<button type="button" class="aomark-tab" data-aomark-admin-tab="fields"><?php esc_html_e( 'Fields', 'aomark-listings' ); ?></button>
			</nav>

			<section class="aomark-listings-tab-panel is-active" data-aomark-admin-panel="overview">
				<div class="aomark-listings-grid">
					<label>
						<span><?php esc_html_e( 'Model ID', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'model_id' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[id]" value="<?php echo esc_attr( $model['id'] ); ?>">
					</label>
					<label>
						<span><?php esc_html_e( 'CPT Slug', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'cpt_slug' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[post_type]" value="<?php echo esc_attr( $model['post_type'] ); ?>" maxlength="20">
					</label>
					<label>
						<span><?php esc_html_e( 'Singular Label', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'singular_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[singular]" value="<?php echo esc_attr( $model['singular'] ); ?>">
					</label>
					<label>
						<span><?php esc_html_e( 'Plural Label', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'plural_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[plural]" value="<?php echo esc_attr( $model['plural'] ); ?>">
					</label>
					<label>
						<span><?php esc_html_e( 'Menu Icon', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'menu_icon' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<input type="text" name="model[menu_icon]" value="<?php echo esc_attr( $model['menu_icon'] ); ?>">
					</label>
					<label class="aomark-listings-check">
						<input type="checkbox" name="model[has_archive]" value="1" <?php checked( $model['has_archive'] ); ?>>
						<span><?php esc_html_e( 'Has archive', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'has_archive' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</label>
					<label class="aomark-listings-check">
						<input type="checkbox" name="model[show_in_rest]" value="1" <?php checked( $model['show_in_rest'] ); ?>>
						<span><?php esc_html_e( 'Show in REST/API', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'show_in_rest' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</label>
				</div>
			</section>

			<section class="aomark-listings-tab-panel" data-aomark-admin-panel="taxonomies">
				<div class="aomark-listings-panel-head">
					<div>
						<h3><?php esc_html_e( 'Taxonomies', 'aomark-listings' ); ?></h3>
						<p><?php esc_html_e( 'Create category-like or tag-like groups for filtering and archives.', 'aomark-listings' ); ?></p>
					</div>
					<button type="button" class="button" data-aomark-add-row="taxonomy"><?php esc_html_e( 'Add Taxonomy', 'aomark-listings' ); ?></button>
				</div>
				<div class="aomark-listings-repeaters" data-aomark-rows="taxonomy">
					<?php foreach ( (array) $model['taxonomies'] as $index => $taxonomy ) : ?>
						<?php aomark_listings_render_taxonomy_row( $taxonomy, $index ); ?>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="aomark-listings-tab-panel" data-aomark-admin-panel="fields">
				<div class="aomark-listings-panel-head">
					<div>
						<h3><?php esc_html_e( 'Fields', 'aomark-listings' ); ?></h3>
						<p><?php esc_html_e( 'Fields become native metabox inputs and model-aware Elementor choices.', 'aomark-listings' ); ?></p>
					</div>
					<button type="button" class="button" data-aomark-add-row="field"><?php esc_html_e( 'Add Field', 'aomark-listings' ); ?></button>
				</div>
				<div class="aomark-listings-repeaters" data-aomark-rows="field">
					<?php foreach ( (array) $model['fields'] as $index => $field ) : ?>
						<?php aomark_listings_render_field_row( $field, $index, $field_types ); ?>
					<?php endforeach; ?>
				</div>
				<div class="aomark-listings-repeater-add">
					<button type="button" class="aomark-listings-add-button" data-aomark-add-row="field" aria-label="<?php esc_attr_e( 'Add field', 'aomark-listings' ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Add field', 'aomark-listings' ); ?></span>
					</button>
				</div>
			</section>
		</div>
	</form>

	<div class="aomark-listings-submit-bar">
		<div class="aomark-listings-submit-actions">
			<button form="<?php echo esc_attr( $form_id ); ?>" type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save Model', 'aomark-listings' ); ?></button>
			<a class="button" href="<?php echo esc_url( aomark_listings_admin_url() ); ?>"><?php esc_html_e( 'Close', 'aomark-listings' ); ?></a>
		</div>
		<form method="post" class="aomark-listings-delete-form" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this model? Existing posts will remain in the database, but the CPT will no longer be registered by this plugin.', 'aomark-listings' ) ); ?>');">
			<?php wp_nonce_field( 'aomark_listings_admin', 'aomark_listings_nonce' ); ?>
			<input type="hidden" name="aomark_listings_action" value="delete_model">
			<input type="hidden" name="model_id" value="<?php echo esc_attr( $model['id'] ); ?>">
			<button type="submit" class="button aomark-listings-danger-button"><?php esc_html_e( 'Delete model', 'aomark-listings' ); ?></button>
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
	$title      = ! empty( $taxonomy['singular'] ) ? $taxonomy['singular'] : __( 'New taxonomy', 'aomark-listings' );
	$slug       = ! empty( $taxonomy['slug'] ) ? $taxonomy['slug'] : __( 'not saved yet', 'aomark-listings' );
	$panel_id   = 'aomark-listings-taxonomy-' . sanitize_html_class( (string) $index );
	?>
	<div class="aomark-listings-repeater-row aomark-listings-repeater-row--taxonomy is-collapsed">
		<div class="aomark-listings-row-summary">
			<button type="button" class="aomark-listings-row-toggle" data-aomark-toggle-row aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<span class="aomark-listings-row-index"><?php echo esc_html( $row_number ); ?></span>
				<span class="aomark-listings-row-title">
					<strong><?php echo esc_html( $title ); ?></strong>
					<code><?php echo esc_html( $slug ); ?></code>
				</span>
				<span class="dashicons dashicons-arrow-down-alt2 aomark-listings-row-chevron" aria-hidden="true"></span>
			</button>
			<span class="aomark-listings-type-pill"><?php echo ! empty( $taxonomy['hierarchical'] ) ? esc_html__( 'Category style', 'aomark-listings' ) : esc_html__( 'Tag style', 'aomark-listings' ); ?></span>
			<button type="button" class="aomark-listings-remove-button" data-aomark-remove-row aria-label="<?php esc_attr_e( 'Remove taxonomy', 'aomark-listings' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove taxonomy', 'aomark-listings' ); ?></span></button>
		</div>
		<div class="aomark-listings-row-details" id="<?php echo esc_attr( $panel_id ); ?>">
			<div class="aomark-listings-grid aomark-listings-grid--compact">
				<label><span><?php esc_html_e( 'ID', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_id' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $taxonomy['id'] ?? '' ); ?>"></label>
				<label><span><?php esc_html_e( 'Slug', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_slug' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $taxonomy['slug'] ?? '' ); ?>" maxlength="32"></label>
				<label><span><?php esc_html_e( 'Singular', 'aomark-listings' ); ?></span><input type="text" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][singular]" value="<?php echo esc_attr( $taxonomy['singular'] ?? '' ); ?>"></label>
				<label><span><?php esc_html_e( 'Plural', 'aomark-listings' ); ?></span><input type="text" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][plural]" value="<?php echo esc_attr( $taxonomy['plural'] ?? '' ); ?>"></label>
				<label class="aomark-listings-check"><input type="checkbox" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][hierarchical]" value="1" <?php checked( ! empty( $taxonomy['hierarchical'] ) ); ?>> <span><?php esc_html_e( 'Hierarchical', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_hierarchical' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
				<label class="aomark-listings-check"><input type="checkbox" name="model[taxonomies][<?php echo esc_attr( $index ); ?>][filterable]" value="1" <?php checked( ! empty( $taxonomy['filterable'] ) ); ?>> <span><?php esc_html_e( 'Filterable', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'taxonomy_filterable' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
			</div>
		</div>
	</div>
	<?php
}

function aomark_listings_render_field_row( $field, $index, $field_types ) {
	$row_number = is_numeric( $index ) ? ( (int) $index + 1 ) : '+';
	$type       = $field['type'] ?? 'text';
	$type_label = $field_types[ $type ] ?? ucfirst( $type );
	$title      = ! empty( $field['label'] ) ? $field['label'] : __( 'New field', 'aomark-listings' );
	$name       = ! empty( $field['key'] ) ? $field['key'] : __( 'not saved yet', 'aomark-listings' );
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
	$summary_group   = $group ? ( $group_options[ $group ] ?? $group_options[''] ) : sprintf( __( 'Automatic · %s', 'aomark-listings' ), aomark_listings_metabox_group_label( $effective_group ) );
	?>
	<div class="aomark-listings-repeater-row aomark-listings-repeater-row--field is-collapsed">
		<div class="aomark-listings-row-summary">
			<button type="button" class="aomark-listings-row-toggle" data-aomark-toggle-row aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<span class="aomark-listings-row-index"><?php echo esc_html( $row_number ); ?></span>
				<span class="aomark-listings-row-title">
					<strong><?php echo esc_html( $title ); ?></strong>
					<code><?php echo esc_html( $name ); ?></code>
					<small class="aomark-listings-row-tab"><?php echo esc_html( $summary_group ); ?></small>
				</span>
				<span class="dashicons dashicons-arrow-down-alt2 aomark-listings-row-chevron" aria-hidden="true"></span>
			</button>
			<span class="aomark-listings-type-pill aomark-listings-type-pill--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type_label ); ?></span>
			<button type="button" class="aomark-listings-remove-button" data-aomark-remove-row aria-label="<?php esc_attr_e( 'Remove field', 'aomark-listings' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Remove field', 'aomark-listings' ); ?></span></button>
		</div>
		<div class="aomark-listings-row-details" id="<?php echo esc_attr( $panel_id ); ?>">
			<input type="hidden" data-aomark-field-id name="model[fields][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $field['id'] ?? '' ); ?>">
			<div class="aomark-listings-grid aomark-listings-grid--compact">
				<label><span><?php esc_html_e( 'Label', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" data-aomark-field-label name="model[fields][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $field['label'] ?? '' ); ?>"></label>
				<label><span><?php esc_html_e( 'Meta Key', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_key' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" data-aomark-field-key name="model[fields][<?php echo esc_attr( $index ); ?>][key]" value="<?php echo esc_attr( $field['key'] ?? '' ); ?>"></label>
				<label>
					<span><?php esc_html_e( 'Type', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_type' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<select name="model[fields][<?php echo esc_attr( $index ); ?>][type]">
						<?php foreach ( $field_types as $field_type => $label ) : ?>
							<option value="<?php echo esc_attr( $field_type ); ?>" <?php selected( $field['type'] ?? 'text', $field_type ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Editor Tab', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_group' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<select name="model[fields][<?php echo esc_attr( $index ); ?>][group]">
						<?php foreach ( $group_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $group, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><span><?php esc_html_e( 'Suffix', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_suffix' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" name="model[fields][<?php echo esc_attr( $index ); ?>][suffix]" value="<?php echo esc_attr( $field['suffix'] ?? '' ); ?>"></label>
				<label class="aomark-listings-placeholder<?php echo in_array( $type, [ 'text', 'textarea', 'number', 'price', 'select', 'location', 'url', 'email', 'date' ], true ) ? ' is-visible' : ''; ?>"><span><?php esc_html_e( 'Placeholder', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_placeholder' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><input type="text" name="model[fields][<?php echo esc_attr( $index ); ?>][placeholder]" value="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Enter price', 'aomark-listings' ); ?>"></label>
				<label class="aomark-listings-check"><input type="checkbox" name="model[fields][<?php echo esc_attr( $index ); ?>][filterable]" value="1" <?php checked( ! empty( $field['filterable'] ) ); ?>> <span><?php esc_html_e( 'Filterable', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_filterable' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
				<label class="aomark-listings-check"><input type="checkbox" name="model[fields][<?php echo esc_attr( $index ); ?>][card]" value="1" <?php checked( ! empty( $field['card'] ) ); ?>> <span><?php esc_html_e( 'Show on cards', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_card' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></label>
			</div>
			<label class="aomark-listings-options<?php echo 'select' === $type ? ' is-visible' : ''; ?>">
				<span><?php esc_html_e( 'Select Options', 'aomark-listings' ); ?> <?php echo aomark_listings_help_tip( aomark_listings_admin_help_text( 'field_options' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<textarea name="model[fields][<?php echo esc_attr( $index ); ?>][options]" rows="3" placeholder="value|Label"><?php echo esc_textarea( aomark_listings_select_options_to_text( $field['options'] ?? [] ) ); ?></textarea>
			</label>
		</div>
	</div>
	<?php
}
