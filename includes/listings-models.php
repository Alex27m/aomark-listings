<?php
/**
 * Listing model registry, presets, CPT/taxonomy registration and admin metaboxes.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AOMARK_LISTINGS_OPTION = 'aomark_listings_models';

function aomark_listings_bool( $value, $default = false ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	if ( null === $value || '' === $value ) {
		return (bool) $default;
	}

	return in_array( (string) $value, [ '1', 'yes', 'true', 'on' ], true );
}

function aomark_listings_supported_field_types() {
	return [
		'text'     => __( 'Text', 'aomark-listings' ),
		'textarea' => __( 'Textarea', 'aomark-listings' ),
		'number'   => __( 'Number', 'aomark-listings' ),
		'price'    => __( 'Price', 'aomark-listings' ),
		'select'   => __( 'Select', 'aomark-listings' ),
		'checkbox' => __( 'Checkbox', 'aomark-listings' ),
		'image'    => __( 'Image', 'aomark-listings' ),
		'gallery'  => __( 'Gallery', 'aomark-listings' ),
		'location' => __( 'Location', 'aomark-listings' ),
		'url'      => __( 'URL', 'aomark-listings' ),
		'email'    => __( 'Email', 'aomark-listings' ),
		'date'     => __( 'Date', 'aomark-listings' ),
	];
}

function aomark_listings_sanitize_id( $value, $fallback = 'listing' ) {
	$value = str_replace( [ 'Đ', 'đ', '&' ], [ 'Dj', 'dj', ' and ' ], (string) $value );
	$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;
	$value = strtolower( $value );
	$value = preg_replace( '/[^a-z0-9]+/', '_', $value );
	$value = trim( $value, '_' );

	return '' !== $value ? $value : $fallback;
}

function aomark_listings_sanitize_post_type( $value, $fallback = 'aomark_listing' ) {
	$value = aomark_listings_sanitize_id( $value, $fallback );
	$value = substr( $value, 0, 20 );

	return '' !== $value ? $value : $fallback;
}

function aomark_listings_sanitize_taxonomy_slug( $value, $fallback = 'aomark_listing_category' ) {
	$value = aomark_listings_sanitize_id( $value, $fallback );
	$value = substr( $value, 0, 32 );

	return '' !== $value ? $value : $fallback;
}

function aomark_listings_meta_key( $field ) {
	$key = is_array( $field ) && isset( $field['key'] ) ? (string) $field['key'] : (string) $field;
	$key = sanitize_key( $key );

	if ( '' === $key ) {
		$key = 'aomark_field';
	}

	if ( 0 !== strpos( $key, 'aomark_' ) ) {
		$key = 'aomark_' . $key;
	}

	return $key;
}

function aomark_listings_parse_select_options( $raw ) {
	$options = [];
	$lines   = is_array( $raw ) ? $raw : preg_split( '/\r\n|\r|\n/', (string) $raw );

	foreach ( $lines as $line ) {
		if ( is_array( $line ) ) {
			$value = isset( $line['value'] ) ? sanitize_key( $line['value'] ) : '';
			$label = isset( $line['label'] ) ? sanitize_text_field( $line['label'] ) : $value;
		} else {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$value = sanitize_key( $parts[0] );
			$label = isset( $parts[1] ) && '' !== $parts[1] ? sanitize_text_field( $parts[1] ) : sanitize_text_field( $parts[0] );
		}

		if ( '' !== $value ) {
			$options[] = [
				'value' => $value,
				'label' => $label,
			];
		}
	}

	return $options;
}

function aomark_listings_select_options_to_text( $options ) {
	$lines = [];

	foreach ( (array) $options as $option ) {
		if ( ! is_array( $option ) || empty( $option['value'] ) ) {
			continue;
		}

		$lines[] = $option['value'] . '|' . ( $option['label'] ?? $option['value'] );
	}

	return implode( "\n", $lines );
}

function aomark_listings_preset_model( $preset, $id = '' ) {
	$preset = sanitize_key( (string) $preset );

	if ( 'directory' === $preset ) {
		$model_id = $id ? aomark_listings_sanitize_id( $id ) : 'directory';

		return [
			'id'           => $model_id,
			'preset'       => 'directory',
			'post_type'    => aomark_listings_sanitize_post_type( 'aomark_directory' ),
			'singular'     => __( 'Directory Item', 'aomark-listings' ),
			'plural'       => __( 'Directory Items', 'aomark-listings' ),
			'menu_icon'    => 'dashicons-location-alt',
			'has_archive'  => true,
			'show_in_rest' => true,
			'taxonomies'   => [
				[
					'id'           => 'category',
					'slug'         => 'aomark_directory_category',
					'singular'     => __( 'Category', 'aomark-listings' ),
					'plural'       => __( 'Categories', 'aomark-listings' ),
					'hierarchical' => true,
					'filterable'   => true,
				],
				[
					'id'           => 'location',
					'slug'         => 'aomark_directory_location',
					'singular'     => __( 'Location', 'aomark-listings' ),
					'plural'       => __( 'Locations', 'aomark-listings' ),
					'hierarchical' => true,
					'filterable'   => true,
				],
			],
			'fields'       => [
				[ 'id' => 'summary', 'key' => 'aomark_summary', 'label' => __( 'Summary', 'aomark-listings' ), 'type' => 'textarea', 'filterable' => false, 'card' => true ],
				[ 'id' => 'phone', 'key' => 'aomark_phone', 'label' => __( 'Phone', 'aomark-listings' ), 'type' => 'text', 'filterable' => false, 'card' => false ],
				[ 'id' => 'email', 'key' => 'aomark_email', 'label' => __( 'Email', 'aomark-listings' ), 'type' => 'email', 'filterable' => false, 'card' => false ],
				[ 'id' => 'website', 'key' => 'aomark_website', 'label' => __( 'Website', 'aomark-listings' ), 'type' => 'url', 'filterable' => false, 'card' => false ],
				[ 'id' => 'location', 'key' => 'aomark_location', 'label' => __( 'Map Location', 'aomark-listings' ), 'type' => 'location', 'filterable' => false, 'card' => false ],
				[ 'id' => 'gallery', 'key' => 'aomark_gallery', 'label' => __( 'Gallery', 'aomark-listings' ), 'type' => 'gallery', 'filterable' => false, 'card' => false ],
			],
		];
	}

	if ( 'custom' === $preset ) {
		$model_id = $id ? aomark_listings_sanitize_id( $id ) : 'custom_listing';

		return [
			'id'           => $model_id,
			'preset'       => 'custom',
			'post_type'    => aomark_listings_sanitize_post_type( 'aomark_listing' ),
			'singular'     => __( 'Listing', 'aomark-listings' ),
			'plural'       => __( 'Listings', 'aomark-listings' ),
			'menu_icon'    => 'dashicons-list-view',
			'has_archive'  => true,
			'show_in_rest' => true,
			'taxonomies'   => [
				[
					'id'           => 'category',
					'slug'         => 'aomark_listing_category',
					'singular'     => __( 'Category', 'aomark-listings' ),
					'plural'       => __( 'Categories', 'aomark-listings' ),
					'hierarchical' => true,
					'filterable'   => true,
				],
			],
			'fields'       => [
				[ 'id' => 'subtitle', 'key' => 'aomark_subtitle', 'label' => __( 'Subtitle', 'aomark-listings' ), 'type' => 'text', 'filterable' => false, 'card' => true ],
				[ 'id' => 'gallery', 'key' => 'aomark_gallery', 'label' => __( 'Gallery', 'aomark-listings' ), 'type' => 'gallery', 'filterable' => false, 'card' => false ],
			],
		];
	}

	$model_id = $id ? aomark_listings_sanitize_id( $id ) : 'real_estate';

	return [
		'id'           => $model_id,
		'preset'       => 'real_estate',
		'post_type'    => aomark_listings_sanitize_post_type( 'aomark_property' ),
		'singular'     => __( 'Property', 'aomark-listings' ),
		'plural'       => __( 'Properties', 'aomark-listings' ),
		'menu_icon'    => 'dashicons-building',
		'has_archive'  => true,
		'show_in_rest' => true,
		'taxonomies'   => [
			[
				'id'           => 'location',
				'slug'         => 'aomark_property_location',
				'singular'     => __( 'Location', 'aomark-listings' ),
				'plural'       => __( 'Locations', 'aomark-listings' ),
				'hierarchical' => true,
				'filterable'   => true,
			],
			[
				'id'           => 'type',
				'slug'         => 'aomark_property_type',
				'singular'     => __( 'Type', 'aomark-listings' ),
				'plural'       => __( 'Types', 'aomark-listings' ),
				'hierarchical' => true,
				'filterable'   => true,
			],
			[
				'id'           => 'status',
				'slug'         => 'aomark_property_status',
				'singular'     => __( 'Status', 'aomark-listings' ),
				'plural'       => __( 'Statuses', 'aomark-listings' ),
				'hierarchical' => false,
				'filterable'   => true,
			],
			[
				'id'           => 'features',
				'slug'         => 'aomark_property_feature',
				'singular'     => __( 'Feature', 'aomark-listings' ),
				'plural'       => __( 'Features', 'aomark-listings' ),
				'hierarchical' => false,
				'filterable'   => true,
			],
		],
		'fields'       => [
			[ 'id' => 'price', 'key' => 'aomark_price', 'label' => __( 'Price', 'aomark-listings' ), 'type' => 'price', 'filterable' => true, 'card' => true ],
			[ 'id' => 'area', 'key' => 'aomark_area', 'label' => __( 'Area', 'aomark-listings' ), 'type' => 'number', 'filterable' => true, 'card' => true, 'suffix' => 'm2' ],
			[ 'id' => 'bedrooms', 'key' => 'aomark_bedrooms', 'label' => __( 'Bedrooms', 'aomark-listings' ), 'type' => 'number', 'filterable' => true, 'card' => true ],
			[ 'id' => 'bathrooms', 'key' => 'aomark_bathrooms', 'label' => __( 'Bathrooms', 'aomark-listings' ), 'type' => 'number', 'filterable' => true, 'card' => true ],
			[ 'id' => 'property_id', 'key' => 'aomark_property_id', 'label' => __( 'Property ID', 'aomark-listings' ), 'type' => 'text', 'filterable' => true, 'card' => false ],
			[ 'id' => 'location', 'key' => 'aomark_location', 'label' => __( 'Map Location', 'aomark-listings' ), 'type' => 'location', 'filterable' => false, 'card' => false ],
			[ 'id' => 'gallery', 'key' => 'aomark_gallery', 'label' => __( 'Gallery', 'aomark-listings' ), 'type' => 'gallery', 'filterable' => false, 'card' => false ],
		],
	];
}

function aomark_listings_default_models() {
	return [
		'real_estate' => aomark_listings_preset_model( 'real_estate', 'real_estate' ),
		'directory'   => aomark_listings_preset_model( 'directory', 'directory' ),
	];
}

function aomark_listings_sanitize_taxonomy( $taxonomy, $index = 0 ) {
	$taxonomy = is_array( $taxonomy ) ? $taxonomy : [];
	$id       = aomark_listings_sanitize_id( $taxonomy['id'] ?? 'taxonomy_' . $index, 'taxonomy_' . $index );
	$slug     = aomark_listings_sanitize_taxonomy_slug( $taxonomy['slug'] ?? 'aomark_' . $id, 'aomark_' . $id );

	return [
		'id'           => $id,
		'slug'         => $slug,
		'singular'     => sanitize_text_field( $taxonomy['singular'] ?? ucfirst( str_replace( '_', ' ', $id ) ) ),
		'plural'       => sanitize_text_field( $taxonomy['plural'] ?? ucfirst( str_replace( '_', ' ', $id ) ) ),
		'hierarchical' => aomark_listings_bool( $taxonomy['hierarchical'] ?? true, true ),
		'filterable'   => aomark_listings_bool( $taxonomy['filterable'] ?? true, true ),
	];
}

function aomark_listings_sanitize_field( $field, $index = 0 ) {
	$field      = is_array( $field ) ? $field : [];
	$label      = sanitize_text_field( $field['label'] ?? '' );
	$id_source  = isset( $field['id'] ) && '' !== trim( (string) $field['id'] ) ? $field['id'] : $label;
	$id         = aomark_listings_sanitize_id( $id_source, 'field_' . $index );
	$type       = sanitize_key( $field['type'] ?? 'text' );
	$group      = sanitize_key( $field['group'] ?? '' );
	$key_source = isset( $field['key'] ) && '' !== trim( (string) $field['key'] ) ? $field['key'] : $id;

	if ( ! isset( aomark_listings_supported_field_types()[ $type ] ) ) {
		$type = 'text';
	}

	if ( ! in_array( $group, [ '', 'details', 'features', 'location', 'media' ], true ) ) {
		$group = '';
	}

	return [
		'id'         => $id,
		'key'        => aomark_listings_meta_key( $key_source ),
		'label'      => '' !== $label ? $label : ucfirst( str_replace( '_', ' ', $id ) ),
		'type'       => $type,
		'group'      => $group,
		'filterable' => aomark_listings_bool( $field['filterable'] ?? false, false ),
		'card'       => aomark_listings_bool( $field['card'] ?? false, false ),
		'suffix'     => sanitize_text_field( $field['suffix'] ?? '' ),
		'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
		'options'    => 'select' === $type ? aomark_listings_parse_select_options( $field['options'] ?? [] ) : [],
	];
}

function aomark_listings_sanitize_model( $model ) {
	$model = is_array( $model ) ? $model : [];
	$id    = aomark_listings_sanitize_id( $model['id'] ?? 'listing' );

	$sanitized = [
		'id'           => $id,
		'preset'       => sanitize_key( $model['preset'] ?? 'custom' ),
		'post_type'    => aomark_listings_sanitize_post_type( $model['post_type'] ?? 'aomark_' . $id ),
		'singular'     => sanitize_text_field( $model['singular'] ?? __( 'Listing', 'aomark-listings' ) ),
		'plural'       => sanitize_text_field( $model['plural'] ?? __( 'Listings', 'aomark-listings' ) ),
		'menu_icon'    => sanitize_text_field( $model['menu_icon'] ?? 'dashicons-list-view' ),
		'has_archive'  => aomark_listings_bool( $model['has_archive'] ?? true, true ),
		'show_in_rest' => aomark_listings_bool( $model['show_in_rest'] ?? true, true ),
		'taxonomies'   => [],
		'fields'       => [],
	];

	foreach ( (array) ( $model['taxonomies'] ?? [] ) as $index => $taxonomy ) {
		$sanitized['taxonomies'][] = aomark_listings_sanitize_taxonomy( $taxonomy, $index );
	}

	foreach ( (array) ( $model['fields'] ?? [] ) as $index => $field ) {
		$sanitized['fields'][] = aomark_listings_sanitize_field( $field, $index );
	}

	return $sanitized;
}

function aomark_listings_get_models() {
	$models = get_option( AOMARK_LISTINGS_OPTION, '__aomark_listings_missing__' );

	if ( '__aomark_listings_missing__' === $models ) {
		$models = aomark_listings_default_models();
	}

	if ( ! is_array( $models ) ) {
		return [];
	}

	$sanitized = [];

	foreach ( $models as $model ) {
		$model = aomark_listings_sanitize_model( $model );
		$sanitized[ $model['id'] ] = $model;
	}

	return $sanitized;
}

function aomark_listings_get_model( $model_id = '' ) {
	$models = aomark_listings_get_models();

	if ( $model_id && isset( $models[ $model_id ] ) ) {
		return $models[ $model_id ];
	}

	return ! empty( $models ) ? reset( $models ) : null;
}

function aomark_listings_get_model_by_post_type( $post_type ) {
	foreach ( aomark_listings_get_models() as $model ) {
		if ( $post_type === $model['post_type'] ) {
			return $model;
		}
	}

	return null;
}

function aomark_listings_get_model_options() {
	$options = [];

	foreach ( aomark_listings_get_models() as $model ) {
		$options[ $model['id'] ] = $model['plural'] . ' (' . $model['post_type'] . ')';
	}

	return $options;
}

function aomark_listings_get_field( $model, $field_id ) {
	foreach ( (array) ( $model['fields'] ?? [] ) as $field ) {
		if ( $field_id === $field['id'] || $field_id === $field['key'] ) {
			return $field;
		}
	}

	return null;
}

function aomark_listings_get_first_field_by_type( $model, $types ) {
	$types = (array) $types;

	foreach ( (array) ( $model['fields'] ?? [] ) as $field ) {
		if ( in_array( $field['type'], $types, true ) ) {
			return $field;
		}
	}

	return null;
}

function aomark_listings_field_help_text( $field ) {
	$type = is_array( $field ) ? ( $field['type'] ?? 'text' ) : (string) $field;

	$help = [
		'text'     => __( 'Short single-line text saved as post meta.', 'aomark-listings' ),
		'textarea' => __( 'Longer text for summaries, descriptions or notes.', 'aomark-listings' ),
		'number'   => __( 'Numeric value that can be sorted and filtered.', 'aomark-listings' ),
		'price'    => __( 'Numeric price value. Formatting is handled by listing widgets.', 'aomark-listings' ),
		'select'   => __( 'Dropdown value. Options come from this field definition.', 'aomark-listings' ),
		'checkbox' => __( 'Boolean on/off value saved as 1 or 0.', 'aomark-listings' ),
		'image'    => __( 'Single media library image attachment.', 'aomark-listings' ),
		'gallery'  => __( 'Multiple media library image attachments.', 'aomark-listings' ),
		'location' => __( 'Interactive address search and map. Coordinates are managed automatically for map widgets.', 'aomark-listings' ),
		'url'      => __( 'Website URL with URL sanitization.', 'aomark-listings' ),
		'email'    => __( 'Email address with email sanitization.', 'aomark-listings' ),
		'date'     => __( 'Date value saved in YYYY-MM-DD format.', 'aomark-listings' ),
	];

	return $help[ $type ] ?? __( 'Custom listing field saved as post meta.', 'aomark-listings' );
}

function aomark_listings_metabox_group_for_field( $field ) {
	$group = sanitize_key( $field['group'] ?? '' );
	if ( in_array( $group, [ 'details', 'features', 'location', 'media' ], true ) ) {
		return $group;
	}

	if ( in_array( $field['id'] ?? '', [ 'area', 'bedrooms', 'bathrooms', 'rooms', 'parking', 'garages' ], true ) ) {
		return 'features';
	}

	if ( 'location' === ( $field['type'] ?? '' ) ) {
		return 'location';
	}

	if ( in_array( $field['type'] ?? '', [ 'image', 'gallery' ], true ) ) {
		return 'media';
	}

	return 'details';
}

function aomark_listings_metabox_group_label( $group ) {
	$labels = [
		'details'  => __( 'Details', 'aomark-listings' ),
		'features' => __( 'Features', 'aomark-listings' ),
		'location' => __( 'Location', 'aomark-listings' ),
		'media'    => __( 'Media', 'aomark-listings' ),
	];

	return $labels[ $group ] ?? ucfirst( $group );
}

function aomark_listings_register_content_types() {
	foreach ( aomark_listings_get_models() as $model ) {
		register_post_type(
			$model['post_type'],
			[
				'labels'       => [
					'name'               => $model['plural'],
					'singular_name'      => $model['singular'],
					'add_new_item'       => sprintf( __( 'Add New %s', 'aomark-listings' ), $model['singular'] ),
					'edit_item'          => sprintf( __( 'Edit %s', 'aomark-listings' ), $model['singular'] ),
					'new_item'           => sprintf( __( 'New %s', 'aomark-listings' ), $model['singular'] ),
					'view_item'          => sprintf( __( 'View %s', 'aomark-listings' ), $model['singular'] ),
					'search_items'       => sprintf( __( 'Search %s', 'aomark-listings' ), $model['plural'] ),
					'not_found'          => sprintf( __( 'No %s found', 'aomark-listings' ), strtolower( $model['plural'] ) ),
					'not_found_in_trash' => sprintf( __( 'No %s found in Trash', 'aomark-listings' ), strtolower( $model['plural'] ) ),
				],
				'public'       => true,
				'has_archive'  => (bool) $model['has_archive'],
				'show_in_rest' => (bool) $model['show_in_rest'],
				'menu_icon'    => $model['menu_icon'],
				'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				'rewrite'      => [ 'slug' => sanitize_title( $model['post_type'] ) ],
			]
		);

		foreach ( (array) $model['taxonomies'] as $taxonomy ) {
			register_taxonomy(
				$taxonomy['slug'],
				[ $model['post_type'] ],
				[
					'labels'            => [
						'name'          => $taxonomy['plural'],
						'singular_name' => $taxonomy['singular'],
						'search_items'  => sprintf( __( 'Search %s', 'aomark-listings' ), $taxonomy['plural'] ),
						'all_items'     => sprintf( __( 'All %s', 'aomark-listings' ), $taxonomy['plural'] ),
						'edit_item'     => sprintf( __( 'Edit %s', 'aomark-listings' ), $taxonomy['singular'] ),
						'add_new_item'  => sprintf( __( 'Add New %s', 'aomark-listings' ), $taxonomy['singular'] ),
					],
					'public'            => true,
					'hierarchical'      => (bool) $taxonomy['hierarchical'],
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => [ 'slug' => sanitize_title( $taxonomy['slug'] ) ],
				]
			);
		}
	}
}
add_action( 'init', 'aomark_listings_register_content_types', 5 );

function aomark_listings_activate() {
	if ( '__aomark_listings_missing__' === get_option( AOMARK_LISTINGS_OPTION, '__aomark_listings_missing__' ) ) {
		update_option( AOMARK_LISTINGS_OPTION, aomark_listings_default_models(), false );
	}

	aomark_listings_register_content_types();
	flush_rewrite_rules();
}

function aomark_listings_deactivate() {
	flush_rewrite_rules();
}

function aomark_listings_render_admin_field_input( $post_id, $field ) {
	$key         = aomark_listings_meta_key( $field );
	$type        = $field['type'];
	$placeholder = (string) ( $field['placeholder'] ?? '' );
	$value       = get_post_meta( $post_id, $key, true );

	if ( 'textarea' === $type ) {
		printf(
			'<textarea class="widefat" rows="4" name="aomark_listing_meta[%1$s]" placeholder="%2$s">%3$s</textarea>',
			esc_attr( $key ),
			esc_attr( $placeholder ),
			esc_textarea( (string) $value )
		);
		return;
	}

	if ( 'select' === $type ) {
		printf( '<select class="widefat" name="aomark_listing_meta[%s]">', esc_attr( $key ) );
		printf( '<option value="">%s</option>', esc_html( $placeholder ) );
		foreach ( (array) $field['options'] as $option ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $option['value'] ),
				selected( (string) $value, (string) $option['value'], false ),
				esc_html( $option['label'] )
			);
		}
		echo '</select>';
		return;
	}

	if ( 'checkbox' === $type ) {
		printf(
			'<label><input type="checkbox" name="aomark_listing_meta[%1$s]" value="1"%2$s> %3$s</label>',
			esc_attr( $key ),
			checked( aomark_listings_bool( $value ), true, false ),
			esc_html__( 'Enabled', 'aomark-listings' )
		);
		return;
	}

	if ( 'image' === $type ) {
		$image_id = absint( $value );
		$preview  = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail' ) : '';
		printf(
			'<div class="aomark-listings-media-field"><div class="aomark-listings-media-preview">%1$s</div><input type="hidden" name="aomark_listing_meta[%2$s]" value="%3$s"><button type="button" class="button aomark-listings-select-image">%4$s</button> <button type="button" class="button-link aomark-listings-clear-media">%5$s</button></div>',
			$preview, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_attr( $key ),
			esc_attr( $image_id ),
			esc_html__( 'Choose Image', 'aomark-listings' ),
			esc_html__( 'Clear', 'aomark-listings' )
		);
		return;
	}

	if ( 'gallery' === $type ) {
		$ids = is_array( $value ) ? array_map( 'absint', $value ) : array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
		echo '<div class="aomark-listings-gallery-field">';
		printf( '<input type="hidden" name="aomark_listing_meta[%s]" value="%s">', esc_attr( $key ), esc_attr( implode( ',', $ids ) ) );
		echo '<div class="aomark-listings-gallery-preview">';
		foreach ( $ids as $image_id ) {
			$image = wp_get_attachment_image( $image_id, 'thumbnail' );
			if ( $image ) {
				echo '<span data-id="' . esc_attr( $image_id ) . '">' . $image . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		echo '</div>';
		printf( '<button type="button" class="button aomark-listings-select-gallery">%s</button> ', esc_html__( 'Choose Gallery', 'aomark-listings' ) );
		printf( '<button type="button" class="button-link aomark-listings-clear-media">%s</button>', esc_html__( 'Clear', 'aomark-listings' ) );
		echo '</div>';
		return;
	}

	if ( 'location' === $type ) {
		$address    = get_post_meta( $post_id, $key . '_address', true );
		$lat        = get_post_meta( $post_id, $key . '_lat', true );
		$lng        = get_post_meta( $post_id, $key . '_lng', true );
		$results_id = 'aomark-location-results-' . $post_id . '-' . sanitize_html_class( $key );
		echo '<div class="aomark-listings-location-editor" data-aomark-location-editor>';
		echo '<div class="aomark-listings-location-search">';
		printf(
			'<input class="widefat" type="search" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="%1$s" data-aomark-location-address name="aomark_listing_meta[%2$s_address]" value="%3$s" placeholder="%4$s">',
			esc_attr( $results_id ),
			esc_attr( $key ),
			esc_attr( $address ),
			esc_attr( $placeholder ?: __( 'Start typing an address…', 'aomark-listings' ) )
		);
		printf( '<div class="aomark-listings-location-results" id="%s" data-aomark-location-results role="listbox" hidden></div>', esc_attr( $results_id ) );
		echo '</div>';
		printf( '<input type="hidden" data-aomark-location-lat name="aomark_listing_meta[%1$s_lat]" value="%2$s">', esc_attr( $key ), esc_attr( $lat ) );
		printf( '<input type="hidden" data-aomark-location-lng name="aomark_listing_meta[%1$s_lng]" value="%2$s">', esc_attr( $key ), esc_attr( $lng ) );
		echo '<div class="aomark-listings-location-map" data-aomark-location-map></div>';
		echo '<div class="aomark-listings-location-meta">';
		echo '<p class="aomark-listings-location-status" data-aomark-location-status aria-live="polite">' . esc_html__( 'Search for an address, then fine-tune the pin by dragging it or clicking the map.', 'aomark-listings' ) . '</p>';
		echo '<p class="aomark-listings-location-attribution">' . wp_kses_post( __( 'Search by <a href="https://photon.komoot.io/" target="_blank" rel="noopener noreferrer">Photon</a> · Data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a>.', 'aomark-listings' ) ) . '</p>';
		echo '</div>';
		echo '</div>';
		return;
	}

	$input_type = 'text';
	if ( in_array( $type, [ 'number', 'price' ], true ) ) {
		$input_type = 'number';
	} elseif ( in_array( $type, [ 'url', 'email', 'date' ], true ) ) {
		$input_type = $type;
	}

	printf(
		'<input class="widefat" type="%1$s" step="%2$s" name="aomark_listing_meta[%3$s]" value="%4$s" placeholder="%5$s">',
		esc_attr( $input_type ),
		'number' === $input_type ? 'any' : '',
		esc_attr( $key ),
		esc_attr( (string) $value ),
		esc_attr( $placeholder )
	);
}

function aomark_listings_add_meta_boxes() {
	foreach ( aomark_listings_get_models() as $model ) {
		add_meta_box(
			'aomark_listings_fields',
			sprintf( __( '%s Fields', 'aomark-listings' ), $model['singular'] ),
			'aomark_listings_render_meta_box',
			$model['post_type'],
			'normal',
			'high',
			[ 'model' => $model ]
		);
	}
}
add_action( 'add_meta_boxes', 'aomark_listings_add_meta_boxes' );

function aomark_listings_render_meta_box( $post, $box ) {
	$model = $box['args']['model'] ?? aomark_listings_get_model_by_post_type( $post->post_type );

	if ( ! $model ) {
		return;
	}

	wp_nonce_field( 'aomark_listings_save_meta', 'aomark_listings_meta_nonce' );
	$groups = [];

	foreach ( (array) $model['fields'] as $field ) {
		$group            = aomark_listings_metabox_group_for_field( $field );
		$groups[ $group ] = $groups[ $group ] ?? [];
		$groups[ $group ][] = $field;
	}

	$groups = array_filter( $groups );

	if ( empty( $groups ) ) {
		echo '<p>' . esc_html__( 'No fields configured for this listing model yet.', 'aomark-listings' ) . '</p>';
		return;
	}

	$group_keys   = array_keys( $groups );
	$active_group = reset( $group_keys );
	echo '<div class="aomark-listings-metabox-shell aomark-admin">';

	if ( count( $groups ) > 1 ) {
		echo '<nav class="aomark-listings-metabox-tabs aomark-tabs" aria-label="' . esc_attr__( 'Listing field groups', 'aomark-listings' ) . '">';
		foreach ( $groups as $group => $fields ) {
			printf(
				'<button type="button" class="aomark-tab %1$s" data-aomark-metabox-tab="%2$s">%3$s <span>%4$d</span></button>',
				$active_group === $group ? 'is-active' : '',
				esc_attr( $group ),
				esc_html( aomark_listings_metabox_group_label( $group ) ),
				count( $fields )
			);
		}
		echo '</nav>';
	}

	echo '<div class="aomark-listings-metabox-panels aomark-tabs-content">';
	foreach ( $groups as $group => $fields ) {
		printf(
			'<section class="aomark-listings-metabox-panel aomark-tab-panel %1$s" data-aomark-metabox-panel="%2$s">',
			$active_group === $group ? 'is-active' : '',
			esc_attr( $group )
		);
		echo '<div class="aomark-listings-metabox">';

		foreach ( $fields as $field ) {
			$help = function_exists( 'aomark_listings_help_tip' ) ? aomark_listings_help_tip( aomark_listings_field_help_text( $field ) ) : '';
			echo '<div class="aomark-listings-metabox-field aomark-listings-metabox-field--' . esc_attr( $field['type'] ) . '">';
			echo '<label><strong>' . esc_html( $field['label'] ) . '</strong> ' . $help . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			aomark_listings_render_admin_field_input( $post->ID, $field );
			echo '</div>';
		}

		echo '</div>';
		echo '</section>';
	}

	echo '</div>';
	echo '</div>';
}

function aomark_listings_sanitize_meta_value( $value, $type ) {
	if ( 'textarea' === $type ) {
		return sanitize_textarea_field( $value );
	}

	if ( 'url' === $type ) {
		return esc_url_raw( $value );
	}

	if ( 'email' === $type ) {
		return sanitize_email( $value );
	}

	if ( in_array( $type, [ 'number', 'price' ], true ) ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return preg_replace( '/[^0-9.,-]/', '', $value );
	}

	if ( 'date' === $type ) {
		return preg_replace( '/[^0-9-]/', '', (string) $value );
	}

	return sanitize_text_field( $value );
}

function aomark_listings_save_meta_box( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['aomark_listings_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aomark_listings_meta_nonce'] ) ), 'aomark_listings_save_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$model = aomark_listings_get_model_by_post_type( get_post_type( $post_id ) );

	if ( ! $model ) {
		return;
	}

	$data = isset( $_POST['aomark_listing_meta'] ) && is_array( $_POST['aomark_listing_meta'] ) ? wp_unslash( $_POST['aomark_listing_meta'] ) : [];

	foreach ( (array) $model['fields'] as $field ) {
		$key  = aomark_listings_meta_key( $field );
		$type = $field['type'];

		if ( 'location' === $type ) {
			foreach ( [ '_address' => 'text', '_lat' => 'number', '_lng' => 'number' ] as $suffix => $value_type ) {
				$meta_key = $key . $suffix;
				$value    = isset( $data[ $meta_key ] ) ? aomark_listings_sanitize_meta_value( $data[ $meta_key ], $value_type ) : '';
				'' === $value ? delete_post_meta( $post_id, $meta_key ) : update_post_meta( $post_id, $meta_key, $value );
			}
			continue;
		}

		if ( 'checkbox' === $type ) {
			update_post_meta( $post_id, $key, isset( $data[ $key ] ) ? '1' : '0' );
			continue;
		}

		if ( 'gallery' === $type ) {
			$ids = isset( $data[ $key ] ) ? array_filter( array_map( 'absint', explode( ',', (string) $data[ $key ] ) ) ) : [];
			empty( $ids ) ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, array_values( $ids ) );
			continue;
		}

		if ( 'image' === $type ) {
			$value = isset( $data[ $key ] ) ? absint( $data[ $key ] ) : 0;
			$value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
			continue;
		}

		$value = isset( $data[ $key ] ) ? aomark_listings_sanitize_meta_value( $data[ $key ], $type ) : '';
		'' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
	}
}
add_action( 'save_post', 'aomark_listings_save_meta_box' );
