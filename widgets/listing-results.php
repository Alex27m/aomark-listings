<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aomark_Listings_Results_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'aomark_listing_results'; }
	public function get_title(): string { return esc_html__( 'Listing Results', 'aomark-listings' ); }
	public function get_icon(): string { return 'eicon-posts-grid'; }
	public function get_categories(): array { return [ 'aomark-listings' ]; }
	public function get_keywords(): array { return [ 'aomark', 'listings', 'results', 'query', 'cards', 'properties' ]; }
	public function get_style_depends(): array { aomark_listings_register_assets(); return [ 'aomark-listings' ]; }
	public function get_script_depends(): array { aomark_listings_register_assets(); return [ 'aomark-listings' ]; }

	protected function register_controls(): void {
		aomark_listings_add_query_controls( $this );

		$this->start_controls_section( 'section_display', [ 'label' => esc_html__( 'Display', 'aomark-listings' ) ] );
		$this->add_control( 'ajax', [ 'label' => esc_html__( 'AJAX Updates', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'show_filter', [ 'label' => esc_html__( 'Integrated Filter', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no' ] );
		$this->add_control( 'show_count', [ 'label' => esc_html__( 'Results Count', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'show_sort', [ 'label' => esc_html__( 'Sort Control', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control(
			'skin',
			[
				'label'   => esc_html__( 'Layout', 'aomark-listings' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'cards',
				'options' => [
					'cards' => esc_html__( 'Grid', 'aomark-listings' ),
					'list'  => esc_html__( 'List', 'aomark-listings' ),
				],
			]
		);
		$this->add_control(
			'pagination',
			[
				'label'   => esc_html__( 'Pagination', 'aomark-listings' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'numbered',
				'options' => [
					'numbered'  => esc_html__( 'Numbered', 'aomark-listings' ),
					'load_more' => esc_html__( 'Load More', 'aomark-listings' ),
					'none'      => esc_html__( 'None', 'aomark-listings' ),
				],
			]
		);
		$this->add_control( 'load_more_text', [ 'label' => esc_html__( 'Load More Text', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => esc_html__( 'Load More', 'aomark-listings' ), 'condition' => [ 'pagination' => 'load_more' ] ] );
		$this->add_control( 'empty_message', [ 'label' => esc_html__( 'Empty Message', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => esc_html__( 'No listings found.', 'aomark-listings' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_card_content', [ 'label' => esc_html__( 'Card Content', 'aomark-listings' ) ] );
		$this->add_control( 'show_image', [ 'label' => esc_html__( 'Image', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'image_size', [ 'label' => esc_html__( 'Image Size', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => aomark_listings_image_size_options(), 'default' => 'medium_large', 'condition' => [ 'show_image' => 'yes' ] ] );
		$this->add_control( 'show_title', [ 'label' => esc_html__( 'Title', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'title_tag', [ 'label' => esc_html__( 'Title HTML Tag', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'h3', 'options' => [ 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div' ], 'condition' => [ 'show_title' => 'yes' ] ] );
		$this->add_control( 'show_price', [ 'label' => esc_html__( 'Price', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'currency', [ 'label' => esc_html__( 'Currency', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '$', 'condition' => [ 'show_price' => 'yes' ] ] );
		$this->add_control( 'decimals', [ 'label' => esc_html__( 'Price Decimals', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 4, 'condition' => [ 'show_price' => 'yes' ] ] );
		$this->add_control( 'show_meta', [ 'label' => esc_html__( 'Meta Fields', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control(
			'card_field_ids',
			[
				'label'       => esc_html__( 'Meta Fields', 'aomark-listings' ),
				'description' => esc_html__( 'Leave empty to use fields marked “Show on cards” in the model.', 'aomark-listings' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => aomark_listings_all_field_options(),
				'multiple'    => true,
				'label_block' => true,
				'condition'   => [ 'show_meta' => 'yes' ],
			]
		);
		$this->add_control( 'show_button', [ 'label' => esc_html__( 'Details Button', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'button_text', [ 'label' => esc_html__( 'Button Text', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => esc_html__( 'View Details', 'aomark-listings' ), 'condition' => [ 'show_button' => 'yes' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_layout', [ 'label' => esc_html__( 'Layout', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'columns', [ 'label' => esc_html__( 'Columns', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '3', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => [ '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-grid' => '--aomark-listings-columns: {{VALUE}};' ], 'condition' => [ 'skin' => 'cards' ] ] );
		$this->add_responsive_control( 'column_gap', [ 'label' => esc_html__( 'Column Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-grid' => 'column-gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'row_gap', [ 'label' => esc_html__( 'Row Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-grid' => 'row-gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'list_image_width', [ 'label' => esc_html__( 'List Image Width', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ '%', 'px' ], 'range' => [ '%' => [ 'min' => 15, 'max' => 60 ], 'px' => [ 'min' => 120, 'max' => 600 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-results--list .aomark-listings-card' => '--alm-list-image-width: {{SIZE}}{{UNIT}};' ], 'condition' => [ 'skin' => 'list', 'show_image' => 'yes' ] ] );
		$this->add_responsive_control( 'gap', [ 'type' => \Elementor\Controls_Manager::HIDDEN, 'selectors' => [ '{{WRAPPER}} .aomark-listings-grid' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_card', [ 'label' => esc_html__( 'Card', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'card_radius', [ 'label' => esc_html__( 'Border Radius', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->add_control( 'card_bg', [ 'type' => \Elementor\Controls_Manager::HIDDEN, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'accent', [ 'type' => \Elementor\Controls_Manager::HIDDEN, 'selectors' => [ '{{WRAPPER}} .aomark-listings-results' => '--alm-accent: {{VALUE}}; --alm-action: {{VALUE}};' ] ] );
		$this->start_controls_tabs( 'card_style_tabs' );
		$this->start_controls_tab( 'card_normal_tab', [ 'label' => esc_html__( 'Normal', 'aomark-listings' ) ] );
		$this->add_group_control( \Elementor\Group_Control_Background::get_type(), [ 'name' => 'card_background', 'types' => [ 'classic', 'gradient' ], 'selector' => '{{WRAPPER}} .aomark-listings-card' ] );
		$this->add_group_control( \Elementor\Group_Control_Border::get_type(), [ 'name' => 'card_border', 'selector' => '{{WRAPPER}} .aomark-listings-card' ] );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [ 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .aomark-listings-card' ] );
		$this->end_controls_tab();
		$this->start_controls_tab( 'card_hover_tab', [ 'label' => esc_html__( 'Hover', 'aomark-listings' ) ] );
		$this->add_control( 'card_hover_background', [ 'label' => esc_html__( 'Background Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card:hover' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'card_hover_border_color', [ 'label' => esc_html__( 'Border Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card:hover' => 'border-color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Box_Shadow::get_type(), [ 'name' => 'card_hover_shadow', 'selector' => '{{WRAPPER}} .aomark-listings-card:hover' ] );
		$this->add_control( 'card_hover_offset', [ 'label' => esc_html__( 'Vertical Offset', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => -20, 'max' => 20 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card:hover' => 'transform: translateY({{SIZE}}{{UNIT}});' ] ] );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_image', [ 'label' => esc_html__( 'Image', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => [ 'show_image' => 'yes' ] ] );
		$this->add_responsive_control( 'image_ratio', [ 'label' => esc_html__( 'Aspect Ratio', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => '4 / 3', 'options' => [ '1 / 1' => '1:1', '4 / 3' => '4:3', '3 / 2' => '3:2', '16 / 9' => '16:9', 'auto' => esc_html__( 'Auto', 'aomark-listings' ) ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__image' => 'aspect-ratio: {{VALUE}};' ], 'condition' => [ 'skin' => 'cards' ] ] );
		$this->add_control( 'image_position', [ 'label' => esc_html__( 'Object Position', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'center center', 'options' => [ 'center center' => esc_html__( 'Center', 'aomark-listings' ), 'center top' => esc_html__( 'Top', 'aomark-listings' ), 'center bottom' => esc_html__( 'Bottom', 'aomark-listings' ), 'left center' => esc_html__( 'Left', 'aomark-listings' ), 'right center' => esc_html__( 'Right', 'aomark-listings' ) ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__image img' => 'object-position: {{VALUE}};' ] ] );
		$this->add_control( 'image_background', [ 'label' => esc_html__( 'Placeholder Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__image, {{WRAPPER}} .aomark-listings-card__placeholder' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'image_hover_scale', [ 'label' => esc_html__( 'Hover Zoom', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 1, 'max' => 1.3, 'step' => 0.01 ] ], 'default' => [ 'size' => 1.035 ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card:hover .aomark-listings-card__image img' => 'transform: scale({{SIZE}});' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_content', [ 'label' => esc_html__( 'Card Content', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'content_padding', [ 'label' => esc_html__( 'Padding', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'content_gap', [ 'label' => esc_html__( 'Elements Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__body' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'content_alignment', [ 'label' => esc_html__( 'Alignment', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => [ 'left' => [ 'title' => esc_html__( 'Left', 'aomark-listings' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'aomark-listings' ), 'icon' => 'eicon-text-align-center' ], 'right' => [ 'title' => esc_html__( 'Right', 'aomark-listings' ), 'icon' => 'eicon-text-align-right' ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__body' => 'text-align: {{VALUE}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_title_price', [ 'label' => esc_html__( 'Title & Price', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_control( 'title_heading', [ 'label' => esc_html__( 'Title', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::HEADING, 'condition' => [ 'show_title' => 'yes' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'title_typography', 'selector' => '{{WRAPPER}} .aomark-listings-card__title', 'condition' => [ 'show_title' => 'yes' ] ] );
		$this->add_control( 'title_color', [ 'label' => esc_html__( 'Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__title a' => 'color: {{VALUE}};' ], 'condition' => [ 'show_title' => 'yes' ] ] );
		$this->add_control( 'title_hover_color', [ 'label' => esc_html__( 'Hover Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__title a:hover' => 'color: {{VALUE}};' ], 'condition' => [ 'show_title' => 'yes' ] ] );
		$this->add_control( 'price_heading', [ 'label' => esc_html__( 'Price', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before', 'condition' => [ 'show_price' => 'yes' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'price_typography', 'selector' => '{{WRAPPER}} .aomark-listings-card__price', 'condition' => [ 'show_price' => 'yes' ] ] );
		$this->add_control( 'price_color', [ 'label' => esc_html__( 'Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__price' => 'color: {{VALUE}};' ], 'condition' => [ 'show_price' => 'yes' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_meta', [ 'label' => esc_html__( 'Card Meta', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => [ 'show_meta' => 'yes' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'meta_typography', 'selector' => '{{WRAPPER}} .aomark-listings-card__meta' ] );
		$this->add_control( 'meta_color', [ 'label' => esc_html__( 'Label Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'meta_value_color', [ 'label' => esc_html__( 'Value Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta strong' => 'color: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'meta_gap', [ 'label' => esc_html__( 'Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_control( 'meta_item_background', [ 'label' => esc_html__( 'Item Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta span' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'meta_item_border_color', [ 'label' => esc_html__( 'Item Border Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta span' => 'border-color: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'meta_item_padding', [ 'label' => esc_html__( 'Item Padding', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'meta_item_radius', [ 'label' => esc_html__( 'Item Radius', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__meta span' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_toolbar', [ 'label' => esc_html__( 'Results Toolbar', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'toolbar_spacing', [ 'label' => esc_html__( 'Bottom Spacing', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-results__bar' => 'margin-bottom: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'toolbar_typography', 'selector' => '{{WRAPPER}} .aomark-listings-results__bar' ] );
		$this->add_control( 'toolbar_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-results__count, {{WRAPPER}} .aomark-listings-results__sort' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'sort_background', [ 'label' => esc_html__( 'Select Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-results__sort select' => 'background-color: {{VALUE}};' ], 'condition' => [ 'show_sort' => 'yes' ] ] );
		$this->add_control( 'sort_border_color', [ 'label' => esc_html__( 'Select Border', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-results__sort select' => 'border-color: {{VALUE}};' ], 'condition' => [ 'show_sort' => 'yes' ] ] );
		$this->add_responsive_control( 'sort_radius', [ 'label' => esc_html__( 'Select Radius', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-results__sort select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ], 'condition' => [ 'show_sort' => 'yes' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_buttons', [ 'label' => esc_html__( 'Buttons', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'button_alignment', [ 'label' => esc_html__( 'Alignment', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::CHOOSE, 'options' => [ 'left' => [ 'title' => esc_html__( 'Left', 'aomark-listings' ), 'icon' => 'eicon-text-align-left' ], 'center' => [ 'title' => esc_html__( 'Center', 'aomark-listings' ), 'icon' => 'eicon-text-align-center' ], 'right' => [ 'title' => esc_html__( 'Right', 'aomark-listings' ), 'icon' => 'eicon-text-align-right' ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-card__button' => '{{VALUE}}' ], 'selectors_dictionary' => [ 'left' => 'margin-left: 0; margin-right: auto;', 'center' => 'margin-left: auto; margin-right: auto;', 'right' => 'margin-left: auto; margin-right: 0;' ] ] );
		aomark_listings_add_button_style_controls( $this, 'results_button', '{{WRAPPER}} :is(.aomark-listings-card__button, .aomark-listings-load-more)' );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_pagination', [ 'label' => esc_html__( 'Pagination', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => [ 'pagination' => 'numbered' ] ] );
		$this->add_responsive_control( 'pagination_gap', [ 'label' => esc_html__( 'Gap', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination' => 'gap: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_responsive_control( 'pagination_spacing', [ 'label' => esc_html__( 'Top Spacing', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'em' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 100 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination' => 'margin-top: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'pagination_typography', 'selector' => '{{WRAPPER}} .aomark-listings-pagination button' ] );
		$this->add_control( 'pagination_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination button' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'pagination_background', [ 'label' => esc_html__( 'Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination button' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'pagination_border_color', [ 'label' => esc_html__( 'Border Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination button' => 'border-color: {{VALUE}};' ] ] );
		$this->add_control( 'pagination_active_color', [ 'label' => esc_html__( 'Active Text', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination button.is-active, {{WRAPPER}} .aomark-listings-pagination button:not(:disabled):hover' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'pagination_active_background', [ 'label' => esc_html__( 'Active Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination button.is-active, {{WRAPPER}} .aomark-listings-pagination button:not(:disabled):hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'pagination_radius', [ 'label' => esc_html__( 'Border Radius', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%', 'em' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-pagination button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_empty', [ 'label' => esc_html__( 'Empty & Loading', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'empty_typography', 'selector' => '{{WRAPPER}} .aomark-listings-empty' ] );
		$this->add_control( 'empty_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-empty' => 'color: {{VALUE}};' ] ] );
		aomark_listings_add_surface_controls( $this, 'empty_box', '{{WRAPPER}} .aomark-listings-empty' );
		$this->add_control( 'loading_opacity', [ 'label' => esc_html__( 'Loading Opacity', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => [ 'px' => [ 'min' => 0.1, 'max' => 1, 'step' => 0.05 ] ], 'default' => [ 'size' => 0.55 ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-results.is-loading [data-aomark-listings-results-inner]' => 'opacity: {{SIZE}};' ] ] );
		$this->end_controls_section();
	}

	protected function render(): void {
		echo aomark_listings_render_results( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
