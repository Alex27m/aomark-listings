<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aomark_Listings_Gallery_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'aomark_listing_gallery'; }
	public function get_title(): string { return esc_html__( 'Listing Gallery', 'aomark-listings' ); }
	public function get_icon(): string { return 'eicon-gallery-grid'; }
	public function get_categories(): array { return [ 'aomark-listings' ]; }
	public function get_keywords(): array { return [ 'aomark', 'listings', 'gallery', 'image', 'photos' ]; }
	public function get_style_depends(): array { return [ 'aomark-listings' ]; }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_gallery', [ 'label' => esc_html__( 'Content', 'aomark-listings' ) ] );
		aomark_listings_add_model_control( $this );
		$this->add_control( 'field_id', [ 'label' => esc_html__( 'Image / Gallery Field', 'aomark-listings' ), 'description' => esc_html__( 'Only compatible image and gallery fields are shown.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => aomark_listings_all_field_options( [ 'image', 'gallery' ] ), 'label_block' => true ] );
		$this->add_control( 'image_size', [ 'label' => esc_html__( 'Image Size', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => aomark_listings_image_size_options(), 'default' => 'medium_large' ] );
		$this->add_control( 'max_images', [ 'label' => esc_html__( 'Maximum Images', 'aomark-listings' ), 'description' => esc_html__( 'Existing zero values are safely capped at 100 images.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 24, 'min' => 1, 'max' => 100 ] );
		$this->add_control( 'link_to', [ 'label' => esc_html__( 'Link', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'media', 'options' => [ 'media' => esc_html__( 'Media File', 'aomark-listings' ), 'none' => esc_html__( 'None', 'aomark-listings' ) ] ] );
		$this->add_control( 'lightbox', [ 'label' => esc_html__( 'Lightbox', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => [ 'link_to' => 'media' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_advanced', [ 'label' => esc_html__( 'Advanced', 'aomark-listings' ) ] );
		$this->add_control( 'post_id', [ 'label' => esc_html__( 'Preview a Specific Listing ID', 'aomark-listings' ), 'description' => esc_html__( 'Leave empty to use the current listing automatically. Use this only for an editor preview override.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1 ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_layout', [ 'label' => esc_html__( 'Layout', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'columns', [ 'label' => esc_html__( 'Columns', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '2', 'options' => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ] ] );
		$this->add_responsive_control( 'column_gap', [ 'label' => esc_html__( 'Column Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 80 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery' => 'column-gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'row_gap', [ 'label' => esc_html__( 'Row Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 80 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery' => 'row-gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'gap', [ 'type' => \Elementor\Controls_Manager::HIDDEN, 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_image', [ 'label' => esc_html__( 'Images', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'aspect_ratio', [ 'label' => esc_html__( 'Aspect Ratio', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '1 / 1', 'options' => [ '1 / 1' => '1:1', '4 / 3' => '4:3', '3 / 2' => '3:2', '16 / 9' => '16:9', 'auto' => esc_html__( 'Auto', 'aomark-listings' ) ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery__item' => 'aspect-ratio: {{VALUE}};' ] ] );
		$this->add_control( 'object_fit', [ 'label' => esc_html__( 'Object Fit', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'cover', 'options' => [ 'cover' => esc_html__( 'Cover', 'aomark-listings' ), 'contain' => esc_html__( 'Contain', 'aomark-listings' ) ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery__item img' => 'object-fit: {{VALUE}};' ] ] );
		$this->add_control( 'object_position', [ 'label' => esc_html__( 'Object Position', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'center center', 'options' => [ 'center center' => esc_html__( 'Center', 'aomark-listings' ), 'center top' => esc_html__( 'Top', 'aomark-listings' ), 'center bottom' => esc_html__( 'Bottom', 'aomark-listings' ), 'left center' => esc_html__( 'Left', 'aomark-listings' ), 'right center' => esc_html__( 'Right', 'aomark-listings' ) ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery__item img' => 'object-position: {{VALUE}};' ] ] );
		aomark_listings_add_surface_controls( $this, 'gallery_item', '{{WRAPPER}} .aomark-listings-gallery__item', false );
		$this->start_controls_tabs( 'gallery_image_tabs' );
		$this->start_controls_tab( 'gallery_image_normal', [ 'label' => esc_html__( 'Normal', 'aomark-listings' ) ] );
		$this->add_control( 'image_opacity', [ 'label' => esc_html__( 'Opacity', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0.1, 'max' => 1, 'step' => 0.05 ] ], 'default' => [ 'size' => 1 ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery__item img' => 'opacity: {{SIZE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Css_Filter::get_type(), [ 'name' => 'image_filters', 'selector' => '{{WRAPPER}} .aomark-listings-gallery__item img' ] );
		$this->end_controls_tab();
		$this->start_controls_tab( 'gallery_image_hover', [ 'label' => esc_html__( 'Hover', 'aomark-listings' ) ] );
		$this->add_control( 'image_hover_opacity', [ 'label' => esc_html__( 'Opacity', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0.1, 'max' => 1, 'step' => 0.05 ] ], 'default' => [ 'size' => 0.9 ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery__item:hover img' => 'opacity: {{SIZE}};' ] ] );
		$this->add_control( 'image_hover_scale', [ 'label' => esc_html__( 'Zoom', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 1, 'max' => 1.4, 'step' => 0.01 ] ], 'default' => [ 'size' => 1.04 ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-gallery__item:hover img' => 'transform: scale({{SIZE}});' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Css_Filter::get_type(), [ 'name' => 'image_hover_filters', 'selector' => '{{WRAPPER}} .aomark-listings-gallery__item:hover img' ] );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		[ $model, $field ] = aomark_listings_resolve_field_setting( $settings, [ 'image', 'gallery' ] );
		if ( $model && $field ) {
			$settings['model_id'] = $model['id'];
			$settings['field_id'] = $field['id'];
		} else {
			aomark_listings_elementor_editor_notice( __( 'Choose a listing type and image/gallery field to preview this widget.', 'aomark-listings' ) );
			return;
		}
		echo aomark_listings_render_gallery( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
