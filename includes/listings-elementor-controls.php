<?php
/**
 * Shared Elementor controls for Aomark Listings widgets.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_elementor_category( $elements_manager ) {
	$elements_manager->add_category(
		'aomark-listings',
		[
			'title' => __( 'Aomark Listings', 'aomark-listings' ),
			'icon'  => 'fa fa-plug',
		]
	);
}
add_action( 'elementor/elements/categories_registered', 'aomark_listings_elementor_category' );

function aomark_listings_all_field_options( $types = [] ) {
	$types = array_filter( (array) $types );
	$options = [];

	foreach ( aomark_listings_get_models() as $model ) {
		foreach ( (array) $model['fields'] as $field ) {
			if ( ! empty( $types ) && ! in_array( $field['type'], $types, true ) ) {
				continue;
			}
			$options[ $model['id'] . ':' . $field['id'] ] = $model['plural'] . ' / ' . $field['label'];
		}
	}

	return $options;
}

function aomark_listings_all_taxonomy_options() {
	$options = [];

	foreach ( aomark_listings_get_models() as $model ) {
		foreach ( (array) $model['taxonomies'] as $taxonomy ) {
			$options[ $model['id'] . ':' . $taxonomy['id'] ] = $model['plural'] . ' / ' . $taxonomy['plural'];
		}
	}

	return $options;
}

/**
 * Return common WordPress image sizes for widget controls.
 *
 * @return array<string,string>
 */
function aomark_listings_image_size_options() {
	$options = [
		'thumbnail'    => esc_html__( 'Thumbnail', 'aomark-listings' ),
		'medium'       => esc_html__( 'Medium', 'aomark-listings' ),
		'medium_large' => esc_html__( 'Medium Large', 'aomark-listings' ),
		'large'        => esc_html__( 'Large', 'aomark-listings' ),
		'full'         => esc_html__( 'Full', 'aomark-listings' ),
	];

	foreach ( get_intermediate_image_sizes() as $size ) {
		if ( ! isset( $options[ $size ] ) ) {
			$options[ $size ] = ucwords( str_replace( [ '-', '_' ], ' ', $size ) );
		}
	}

	return $options;
}

/**
 * Normalize legacy comma-separated and modern Select2 field values.
 *
 * Composite values use the "model:field" format so fields from models with
 * identical IDs stay unambiguous in Elementor controls.
 *
 * @param mixed  $raw      Saved control value.
 * @param string $model_id Optional model constraint.
 * @return string[]
 */
function aomark_listings_normalize_selected_ids( $raw, $model_id = '' ) {
	$values = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
	$ids    = [];

	foreach ( $values as $value ) {
		$value = sanitize_text_field( trim( (string) $value ) );
		if ( '' === $value ) {
			continue;
		}

		if ( false !== strpos( $value, ':' ) ) {
			$parts = explode( ':', $value, 2 );
			if ( $model_id && sanitize_key( $parts[0] ) !== sanitize_key( $model_id ) ) {
				continue;
			}
			$value = $parts[1];
		}

		$value = sanitize_key( $value );
		if ( '' !== $value ) {
			$ids[] = $value;
		}
	}

	return array_values( array_unique( $ids ) );
}

/**
 * Add a reusable surface control set (background, border, radius and shadow).
 *
 * @param \Elementor\Widget_Base $widget   Elementor widget.
 * @param string                 $prefix   Unique control prefix.
 * @param string                 $selector Scoped CSS selector.
 * @param bool                   $padding  Whether to include responsive padding.
 */
function aomark_listings_add_surface_controls( $widget, $prefix, $selector, $padding = true ) {
	$widget->add_group_control(
		\Elementor\Group_Control_Background::get_type(),
		[
			'name'     => $prefix . '_background',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => $selector,
		]
	);

	$widget->add_group_control(
		\Elementor\Group_Control_Border::get_type(),
		[
			'name'     => $prefix . '_border',
			'selector' => $selector,
		]
	);

	$widget->add_responsive_control(
		$prefix . '_radius',
		[
			'label'      => esc_html__( 'Border Radius', 'aomark-listings' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%', 'em' ],
			'selectors'  => [
				$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$widget->add_group_control(
		\Elementor\Group_Control_Box_Shadow::get_type(),
		[
			'name'     => $prefix . '_shadow',
			'selector' => $selector,
		]
	);

	if ( $padding ) {
		$widget->add_responsive_control(
			$prefix . '_padding',
			[
				'label'      => esc_html__( 'Padding', 'aomark-listings' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);
	}
}

/**
 * Add a full button style set with normal and hover states.
 *
 * @param \Elementor\Widget_Base $widget   Elementor widget.
 * @param string                 $prefix   Unique control prefix.
 * @param string                 $selector Scoped CSS selector.
 */
function aomark_listings_add_button_style_controls( $widget, $prefix, $selector ) {
	$widget->add_group_control(
		\Elementor\Group_Control_Typography::get_type(),
		[
			'name'     => $prefix . '_typography',
			'selector' => $selector,
		]
	);

	$widget->add_responsive_control(
		$prefix . '_padding',
		[
			'label'      => esc_html__( 'Padding', 'aomark-listings' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em' ],
			'selectors'  => [
				$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$widget->add_responsive_control(
		$prefix . '_min_height',
		[
			'label'      => esc_html__( 'Minimum Height', 'aomark-listings' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 24, 'max' => 100 ] ],
			'selectors'  => [ $selector => 'min-height: {{SIZE}}{{UNIT}};' ],
		]
	);

	$widget->add_responsive_control(
		$prefix . '_radius',
		[
			'label'      => esc_html__( 'Border Radius', 'aomark-listings' ),
			'type'       => \Elementor\Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%', 'em' ],
			'selectors'  => [
				$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		]
	);

	$widget->start_controls_tabs( $prefix . '_style_tabs' );
	$widget->start_controls_tab(
		$prefix . '_normal_tab',
		[ 'label' => esc_html__( 'Normal', 'aomark-listings' ) ]
	);
	$widget->add_control(
		$prefix . '_text_color',
		[
			'label'     => esc_html__( 'Text Color', 'aomark-listings' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ $selector => 'color: {{VALUE}} !important;' ],
		]
	);
	$widget->add_group_control(
		\Elementor\Group_Control_Background::get_type(),
		[
			'name'     => $prefix . '_background',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => $selector,
		]
	);
	$widget->add_group_control(
		\Elementor\Group_Control_Border::get_type(),
		[
			'name'     => $prefix . '_border',
			'selector' => $selector,
		]
	);
	$widget->add_group_control(
		\Elementor\Group_Control_Box_Shadow::get_type(),
		[
			'name'     => $prefix . '_shadow',
			'selector' => $selector,
		]
	);
	$widget->end_controls_tab();

	$hover_selector = $selector . ':hover, ' . $selector . ':focus';
	$widget->start_controls_tab(
		$prefix . '_hover_tab',
		[ 'label' => esc_html__( 'Hover', 'aomark-listings' ) ]
	);
	$widget->add_control(
		$prefix . '_hover_text_color',
		[
			'label'     => esc_html__( 'Text Color', 'aomark-listings' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ $hover_selector => 'color: {{VALUE}} !important;' ],
		]
	);
	$widget->add_group_control(
		\Elementor\Group_Control_Background::get_type(),
		[
			'name'     => $prefix . '_hover_background',
			'types'    => [ 'classic', 'gradient' ],
			'selector' => $hover_selector,
		]
	);
	$widget->add_control(
		$prefix . '_hover_border_color',
		[
			'label'     => esc_html__( 'Border Color', 'aomark-listings' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [ $hover_selector => 'border-color: {{VALUE}};' ],
		]
	);
	$widget->add_group_control(
		\Elementor\Group_Control_Box_Shadow::get_type(),
		[
			'name'     => $prefix . '_hover_shadow',
			'selector' => $hover_selector,
		]
	);
	$widget->end_controls_tab();
	$widget->end_controls_tabs();

	$widget->add_control(
		$prefix . '_transition',
		[
			'label'      => esc_html__( 'Transition Duration', 'aomark-listings' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 's' ],
			'range'      => [ 's' => [ 'min' => 0, 'max' => 2, 'step' => 0.05 ] ],
			'selectors'  => [ $selector => 'transition-duration: {{SIZE}}{{UNIT}};' ],
		]
	);
}

function aomark_listings_resolve_field_setting( $settings, $allowed_types = [] ) {
	$model_id = sanitize_key( $settings['model_id'] ?? '' );
	$field_id = sanitize_text_field( $settings['field_id'] ?? '' );

	if ( false !== strpos( $field_id, ':' ) ) {
		$parts = explode( ':', $field_id, 2 );
		$model_id = sanitize_key( $parts[0] );
		$field_id = sanitize_key( $parts[1] );
	}

	$model = aomark_listings_get_model( $model_id );
	$field = aomark_listings_get_field( $model, $field_id );

	if ( $field && ! empty( $allowed_types ) && ! in_array( $field['type'], (array) $allowed_types, true ) ) {
		$field = null;
	}

	return [ $model, $field ];
}

function aomark_listings_add_model_control( $widget, $default = '' ) {
	$options = aomark_listings_get_model_options();
	$keys    = array_keys( $options );
	$default = $default ?: ( $keys[0] ?? '' );

	$widget->add_control(
		'model_id',
		[
			'label'       => esc_html__( 'Listing Model', 'aomark-listings' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => $options,
			'default'     => $default,
			'label_block' => true,
		]
	);
}

function aomark_listings_add_query_controls( $widget ) {
	$widget->start_controls_section(
		'section_query',
		[
			'label' => esc_html__( 'Query', 'aomark-listings' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		]
	);

	aomark_listings_add_model_control( $widget );

	$widget->add_control(
		'per_page',
		[
			'label'   => esc_html__( 'Posts Per Page', 'aomark-listings' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 9,
			'min'     => 1,
			'max'     => 60,
		]
	);

	$widget->add_control(
		'read_url_filters',
		[
			'label'        => esc_html__( 'Read URL Filters', 'aomark-listings' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		]
	);

	$widget->add_control(
		'sort',
		[
			'label'   => esc_html__( 'Default Sort', 'aomark-listings' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'date_desc',
			'options' => [
				'date_desc'  => esc_html__( 'Newest', 'aomark-listings' ),
				'date_asc'   => esc_html__( 'Oldest', 'aomark-listings' ),
				'title_asc'  => esc_html__( 'Title A-Z', 'aomark-listings' ),
				'title_desc' => esc_html__( 'Title Z-A', 'aomark-listings' ),
				'price_asc'  => esc_html__( 'Price Low to High', 'aomark-listings' ),
				'price_desc' => esc_html__( 'Price High to Low', 'aomark-listings' ),
			],
		]
	);

	$taxonomy_repeater = new \Elementor\Repeater();
	$taxonomy_repeater->add_control(
		'taxonomy_id',
		[
			'label'       => esc_html__( 'Taxonomy', 'aomark-listings' ),
			'type'        => \Elementor\Controls_Manager::SELECT2,
			'options'     => aomark_listings_all_taxonomy_options(),
			'label_block' => true,
		]
	);
	$taxonomy_repeater->add_control(
		'terms',
		[
			'label'       => esc_html__( 'Term Slugs', 'aomark-listings' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => esc_html__( 'Comma-separated slugs.', 'aomark-listings' ),
		]
	);
	$widget->add_control(
		'taxonomy_filters',
		[
			'label'       => esc_html__( 'Taxonomy Filters', 'aomark-listings' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $taxonomy_repeater->get_controls(),
			'title_field' => '{{{ taxonomy_id }}}',
		]
	);

	$meta_repeater = new \Elementor\Repeater();
	$meta_repeater->add_control(
		'field_id',
		[
			'label'       => esc_html__( 'Field', 'aomark-listings' ),
			'type'        => \Elementor\Controls_Manager::SELECT2,
			'options'     => aomark_listings_all_field_options(),
			'label_block' => true,
		]
	);
	$meta_repeater->add_control(
		'compare',
		[
			'label'   => esc_html__( 'Compare', 'aomark-listings' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => '=',
			'options' => [
				'='    => '=',
				'LIKE' => 'Contains',
				'>='   => '>=',
				'<='   => '<=',
				'>'    => '>',
				'<'    => '<',
			],
		]
	);
	$meta_repeater->add_control(
		'value',
		[
			'label' => esc_html__( 'Value', 'aomark-listings' ),
			'type'  => \Elementor\Controls_Manager::TEXT,
		]
	);
	$widget->add_control(
		'meta_filters',
		[
			'label'       => esc_html__( 'Meta Filters', 'aomark-listings' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $meta_repeater->get_controls(),
			'title_field' => '{{{ field_id }}} {{{ compare }}} {{{ value }}}',
		]
	);

	$widget->end_controls_section();
}
