<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aomark_Listings_Meta_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'aomark_listing_meta'; }
	public function get_title(): string { return esc_html__( 'Listing Meta', 'aomark-listings' ); }
	public function get_icon(): string { return 'eicon-info-circle-o'; }
	public function get_categories(): array { return [ 'aomark-listings' ]; }
	public function get_keywords(): array { return [ 'aomark', 'listings', 'meta', 'fields', 'details' ]; }
	public function get_style_depends(): array { aomark_listings_register_assets(); return [ 'aomark-listings' ]; }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_meta', [ 'label' => esc_html__( 'Meta', 'aomark-listings' ) ] );
		aomark_listings_add_model_control( $this );
		$this->add_control( 'post_id', [ 'label' => esc_html__( 'Listing ID', 'aomark-listings' ), 'description' => esc_html__( 'Optional. Leave empty to use the current listing.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1 ] );
		$this->add_control(
			'field_ids',
			[
				'label'       => esc_html__( 'Fields', 'aomark-listings' ),
				'description' => esc_html__( 'Leave empty to use fields marked “Show on cards” in the model.', 'aomark-listings' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => aomark_listings_all_field_options(),
				'multiple'    => true,
				'label_block' => true,
			]
		);
		$this->add_control( 'show_labels', [ 'label' => esc_html__( 'Show Labels', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'label_suffix', [ 'label' => esc_html__( 'Label Suffix', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '', 'condition' => [ 'show_labels' => 'yes' ] ] );
		$this->add_control( 'currency', [ 'label' => esc_html__( 'Currency', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '$' ] );
		$this->add_control( 'decimals', [ 'label' => esc_html__( 'Decimals', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 4 ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_layout', [ 'label' => esc_html__( 'Layout', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'columns', [ 'label' => esc_html__( 'Columns', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '1', 'options' => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ] ] );
		$this->add_responsive_control( 'row_gap', [ 'label' => esc_html__( 'Row Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta' => 'row-gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'column_gap', [ 'label' => esc_html__( 'Column Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 80 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta' => 'column-gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_control( 'row_alignment', [ 'label' => esc_html__( 'Value Alignment', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'space-between', 'options' => [ 'space-between' => esc_html__( 'Opposite Sides', 'aomark-listings' ), 'flex-start' => esc_html__( 'Start', 'aomark-listings' ), 'center' => esc_html__( 'Center', 'aomark-listings' ), 'flex-end' => esc_html__( 'End', 'aomark-listings' ) ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta__row' => 'justify-content: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_rows', [ 'label' => esc_html__( 'Rows', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'row_inner_gap', [ 'label' => esc_html__( 'Label / Value Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta__row' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		aomark_listings_add_surface_controls( $this, 'meta_row', '{{WRAPPER}} .aomark-listings-meta__row' );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_label', [ 'label' => esc_html__( 'Labels', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => [ 'show_labels' => 'yes' ] ] );
		$this->add_control( 'label_color', [ 'label' => esc_html__( 'Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta__label' => 'color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'label_typography', 'selector' => '{{WRAPPER}} .aomark-listings-meta__label' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_value', [ 'label' => esc_html__( 'Values', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_control( 'value_color', [ 'label' => esc_html__( 'Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-meta__value' => 'color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'value_typography', 'selector' => '{{WRAPPER}} .aomark-listings-meta__value' ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		echo aomark_listings_render_meta( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
