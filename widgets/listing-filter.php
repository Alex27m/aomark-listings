<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aomark_Listings_Filter_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'aomark_listing_filter'; }
	public function get_title(): string { return esc_html__( 'Listing Filter', 'aomark-listings' ); }
	public function get_icon(): string { return 'eicon-search'; }
	public function get_categories(): array { return [ 'aomark-listings' ]; }
	public function get_keywords(): array { return [ 'aomark', 'listings', 'filter', 'search', 'property' ]; }
	public function get_style_depends(): array { aomark_listings_register_assets(); return [ 'aomark-listings' ]; }
	public function get_script_depends(): array { aomark_listings_register_assets(); return [ 'aomark-listings' ]; }

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_filter',
			[ 'label' => esc_html__( 'Filter', 'aomark-listings' ) ]
		);
		aomark_listings_add_model_control( $this );
		$this->add_control(
			'results_url',
			[
				'label'       => esc_html__( 'Results URL', 'aomark-listings' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => home_url( '/' ),
				'dynamic'     => [ 'active' => true ],
			]
		);
		$this->add_control(
			'button_text',
			[
				'label'   => esc_html__( 'Button Text', 'aomark-listings' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => esc_html__( 'Search', 'aomark-listings' ),
			]
		);
		$this->add_control(
			'show_reset',
			[
				'label'        => esc_html__( 'Reset Button', 'aomark-listings' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'no',
			]
		);
		$this->add_control(
			'reset_text',
			[
				'label'     => esc_html__( 'Reset Text', 'aomark-listings' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => esc_html__( 'Reset', 'aomark-listings' ),
				'condition' => [ 'show_reset' => 'yes' ],
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_filter_fields',
			[ 'label' => esc_html__( 'Fields', 'aomark-listings' ) ]
		);
		$this->add_control(
			'show_keyword',
			[
				'label'        => esc_html__( 'Keyword Search', 'aomark-listings' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);
		$this->add_control(
			'keyword_placeholder',
			[
				'label'     => esc_html__( 'Keyword Placeholder', 'aomark-listings' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => esc_html__( 'Search', 'aomark-listings' ),
				'condition' => [ 'show_keyword' => 'yes' ],
			]
		);
		$this->add_control(
			'show_taxonomy',
			[
				'label'        => esc_html__( 'Taxonomy Filters', 'aomark-listings' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);
		$this->add_control(
			'taxonomy_ids',
			[
				'label'       => esc_html__( 'Include Taxonomies', 'aomark-listings' ),
				'description' => esc_html__( 'Leave empty to show all filterable taxonomies for the selected model.', 'aomark-listings' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => aomark_listings_all_taxonomy_options(),
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [ 'show_taxonomy' => 'yes' ],
			]
		);
		$this->add_control(
			'show_fields',
			[
				'label'        => esc_html__( 'Custom Field Filters', 'aomark-listings' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);
		$this->add_control(
			'field_ids',
			[
				'label'       => esc_html__( 'Include Fields', 'aomark-listings' ),
				'description' => esc_html__( 'Leave empty to show all filterable fields for the selected model.', 'aomark-listings' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => aomark_listings_all_field_options(),
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [ 'show_fields' => 'yes' ],
			]
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_layout',
			[
				'label' => esc_html__( 'Layout', 'aomark-listings' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$this->add_responsive_control(
			'layout_direction',
			[
				'label'   => esc_html__( 'Direction', 'aomark-listings' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'default' => 'row',
				'options' => [
					'row'    => [ 'title' => esc_html__( 'Row', 'aomark-listings' ), 'icon' => 'eicon-h-align-stretch' ],
					'column' => [ 'title' => esc_html__( 'Column', 'aomark-listings' ), 'icon' => 'eicon-v-align-stretch' ],
				],
				'selectors' => [ '{{WRAPPER}} .aomark-listings-filter__form' => 'flex-direction: {{VALUE}};' ],
			]
		);
		$this->add_responsive_control(
			'gap',
			[
				'label'      => esc_html__( 'Gap', 'aomark-listings' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em' ],
				'range'      => [ 'px' => [ 'min' => 0, 'max' => 80 ] ],
				'selectors'  => [ '{{WRAPPER}} .aomark-listings-filter__form' => 'gap: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'field_min_width',
			[
				'label'      => esc_html__( 'Field Minimum Width', 'aomark-listings' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [
					'px' => [ 'min' => 80, 'max' => 500 ],
					'%'  => [ 'min' => 10, 'max' => 100 ],
				],
				'selectors' => [ '{{WRAPPER}} .aomark-listings-filter__field:not(.aomark-listings-filter__field--button):not(.aomark-listings-filter__field--reset)' => 'min-width: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'form_alignment',
			[
				'label'   => esc_html__( 'Items Alignment', 'aomark-listings' ),
				'type'    => \Elementor\Controls_Manager::CHOOSE,
				'options' => [
					'flex-start' => [ 'title' => esc_html__( 'Start', 'aomark-listings' ), 'icon' => 'eicon-v-align-top' ],
					'center'     => [ 'title' => esc_html__( 'Center', 'aomark-listings' ), 'icon' => 'eicon-v-align-middle' ],
					'stretch'    => [ 'title' => esc_html__( 'Stretch', 'aomark-listings' ), 'icon' => 'eicon-v-align-stretch' ],
					'flex-end'   => [ 'title' => esc_html__( 'End', 'aomark-listings' ), 'icon' => 'eicon-v-align-bottom' ],
				],
				'selectors' => [ '{{WRAPPER}} .aomark-listings-filter__form' => 'align-items: {{VALUE}};' ],
			]
		);
		$this->add_control( 'accent', [ 'type' => \Elementor\Controls_Manager::HIDDEN, 'selectors' => [ '{{WRAPPER}} .aomark-listings-filter' => '--alm-accent: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_form',
			[
				'label' => esc_html__( 'Form', 'aomark-listings' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		aomark_listings_add_surface_controls( $this, 'filter_form', '{{WRAPPER}} .aomark-listings-filter__form' );
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_fields',
			[
				'label' => esc_html__( 'Input & Select', 'aomark-listings' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		$field_selector = '{{WRAPPER}} .aomark-listings-filter__field input, {{WRAPPER}} .aomark-listings-filter__field select';
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[ 'name' => 'field_typography', 'selector' => $field_selector ]
		);
		$this->add_responsive_control(
			'field_height',
			[
				'label'      => esc_html__( 'Height', 'aomark-listings' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [ 'px' => [ 'min' => 30, 'max' => 100 ] ],
				'selectors'  => [ $field_selector => 'min-height: {{SIZE}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'field_padding',
			[
				'label'      => esc_html__( 'Padding', 'aomark-listings' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors'  => [ $field_selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);
		$this->add_responsive_control(
			'field_radius',
			[
				'label'      => esc_html__( 'Border Radius', 'aomark-listings' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [ $field_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);
		$this->start_controls_tabs( 'field_state_tabs' );
		$this->start_controls_tab( 'field_normal_tab', [ 'label' => esc_html__( 'Normal', 'aomark-listings' ) ] );
		$this->add_control( 'field_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ $field_selector => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'field_placeholder_color', [ 'label' => esc_html__( 'Placeholder Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-filter__field input::placeholder' => 'color: {{VALUE}}; opacity: 1;' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), [ 'name' => 'field_background', 'types' => [ 'classic', 'gradient' ], 'selector' => $field_selector ] );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [ 'name' => 'field_border', 'selector' => $field_selector ] );
		$this->end_controls_tab();
		$this->start_controls_tab( 'field_focus_tab', [ 'label' => esc_html__( 'Focus', 'aomark-listings' ) ] );
		$focus_selector = '{{WRAPPER}} .aomark-listings-filter__field input:focus, {{WRAPPER}} .aomark-listings-filter__field select:focus';
		$this->add_control( 'field_focus_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ $focus_selector => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'field_focus_background', [ 'label' => esc_html__( 'Background Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ $focus_selector => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'field_focus_border_color', [ 'label' => esc_html__( 'Border Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ $focus_selector => 'border-color: {{VALUE}};' ] ] );
		$this->add_control( 'field_focus_ring_color', [ 'label' => esc_html__( 'Focus Ring Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ $focus_selector => 'box-shadow: 0 0 0 3px {{VALUE}};' ] ] );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_button',
			[
				'label' => esc_html__( 'Search Button', 'aomark-listings' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);
		aomark_listings_add_button_style_controls( $this, 'search_button', '{{WRAPPER}} .aomark-listings-button' );
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_reset',
			[
				'label'     => esc_html__( 'Reset Button', 'aomark-listings' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [ 'show_reset' => 'yes' ],
			]
		);
		aomark_listings_add_button_style_controls( $this, 'reset_button', '{{WRAPPER}} .aomark-listings-reset' );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		if ( isset( $settings['results_url']['url'] ) ) {
			$settings['results_url'] = $settings['results_url']['url'];
		}
		echo aomark_listings_render_filter( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
