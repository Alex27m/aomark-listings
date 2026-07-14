<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aomark_Listings_Field_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'aomark_listing_field'; }
	public function get_title(): string { return esc_html__( 'Listing Field', 'aomark-listings' ); }
	public function get_icon(): string { return 'eicon-code'; }
	public function get_categories(): array { return [ 'aomark-listings' ]; }
	public function get_keywords(): array { return [ 'aomark', 'listings', 'field', 'dynamic', 'value' ]; }
	public function get_style_depends(): array { aomark_listings_register_assets(); return [ 'aomark-listings' ]; }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_field', [ 'label' => esc_html__( 'Field', 'aomark-listings' ) ] );
		aomark_listings_add_model_control( $this );
		$this->add_control( 'field_id', [ 'label' => esc_html__( 'Field', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT2, 'options' => aomark_listings_all_field_options(), 'label_block' => true ] );
		$this->add_control( 'post_id', [ 'label' => esc_html__( 'Listing ID', 'aomark-listings' ), 'description' => esc_html__( 'Optional. Leave empty to use the current listing.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1 ] );
		$this->add_control( 'html_tag', [ 'label' => esc_html__( 'HTML Tag', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'div', 'options' => [ 'div' => 'div', 'span' => 'span', 'p' => 'p', 'h1' => 'h1', 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h5', 'h6' => 'h6' ] ] );
		$this->add_control( 'fallback', [ 'label' => esc_html__( 'Fallback', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'dynamic' => [ 'active' => true ] ] );
		$this->add_control( 'currency', [ 'label' => esc_html__( 'Currency', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '$' ] );
		$this->add_control( 'decimals', [ 'label' => esc_html__( 'Decimals', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 4 ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_format', [ 'label' => esc_html__( 'Format', 'aomark-listings' ) ] );
		$this->add_control( 'show_label', [ 'label' => esc_html__( 'Show Label', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no' ] );
		$this->add_control( 'custom_label', [ 'label' => esc_html__( 'Custom Label', 'aomark-listings' ), 'description' => esc_html__( 'Leave empty to use the field label.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'condition' => [ 'show_label' => 'yes' ] ] );
		$this->add_control( 'label_position', [ 'label' => esc_html__( 'Label Position', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'inline', 'options' => [ 'inline' => esc_html__( 'Inline', 'aomark-listings' ), 'stacked' => esc_html__( 'Above', 'aomark-listings' ) ], 'condition' => [ 'show_label' => 'yes' ] ] );
		$this->add_control( 'prefix', [ 'label' => esc_html__( 'Prefix', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'dynamic' => [ 'active' => true ] ] );
		$this->add_control( 'suffix', [ 'label' => esc_html__( 'Suffix', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'dynamic' => [ 'active' => true ] ] );
		$this->add_control( 'link_to', [ 'label' => esc_html__( 'Link', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'none', 'options' => [ 'none' => esc_html__( 'None', 'aomark-listings' ), 'listing' => esc_html__( 'Current Listing', 'aomark-listings' ), 'field' => esc_html__( 'Field URL / Email', 'aomark-listings' ), 'custom' => esc_html__( 'Custom URL', 'aomark-listings' ) ] ] );
		$this->add_control( 'custom_url', [ 'label' => esc_html__( 'Custom URL', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::URL, 'dynamic' => [ 'active' => true ], 'condition' => [ 'link_to' => 'custom' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_layout', [ 'label' => esc_html__( 'Layout', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'align', [ 'label' => esc_html__( 'Alignment', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => [ 'flex-start' => [ 'title' => esc_html__( 'Left', 'aomark-listings' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'aomark-listings' ), 'icon' => 'eicon-text-align-center' ], 'flex-end' => [ 'title' => esc_html__( 'Right', 'aomark-listings' ), 'icon' => 'eicon-text-align-right' ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-field' => 'justify-content: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'gap', [ 'label' => esc_html__( 'Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-field' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		aomark_listings_add_surface_controls( $this, 'field_container', '{{WRAPPER}} .aomark-listings-field' );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_label', [ 'label' => esc_html__( 'Label', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => [ 'show_label' => 'yes' ] ] );
		$this->add_control( 'label_color', [ 'label' => esc_html__( 'Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-field__label' => 'color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'label_typography', 'selector' => '{{WRAPPER}} .aomark-listings-field__label' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_value', [ 'label' => esc_html__( 'Value', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_control( 'value_color', [ 'label' => esc_html__( 'Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-field__value, {{WRAPPER}} .aomark-listings-field__value a' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'color', [ 'type' => \Elementor\Controls_Manager::HIDDEN, 'selectors' => [ '{{WRAPPER}} .aomark-listings-field' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'value_hover_color', [ 'label' => esc_html__( 'Link Hover Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-field__value a:hover' => 'color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'value_typography', 'selector' => '{{WRAPPER}} .aomark-listings-field__value' ] );
		$this->add_control( 'affix_color', [ 'label' => esc_html__( 'Prefix / Suffix Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-field__prefix, {{WRAPPER}} .aomark-listings-field__suffix' => 'color: {{VALUE}};' ] ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		[ $model, $field ] = aomark_listings_resolve_field_setting( $settings );
		if ( $model && $field ) {
			$settings['model_id'] = $model['id'];
			$settings['field_id'] = $field['id'];
		}
		if ( isset( $settings['custom_url']['url'] ) ) {
			$settings['custom_url'] = $settings['custom_url']['url'];
		}
		echo aomark_listings_render_field( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
