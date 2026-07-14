<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aomark_Listings_Map_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'aomark_listing_map'; }
	public function get_title(): string { return esc_html__( 'Listing Map', 'aomark-listings' ); }
	public function get_icon(): string { return 'eicon-google-maps'; }
	public function get_categories(): array { return [ 'aomark-listings' ]; }
	public function get_keywords(): array { return [ 'aomark', 'listings', 'map', 'location', 'property' ]; }
	public function get_style_depends(): array { return [ 'aomark-listings', 'aomark-listings-leaflet' ]; }
	public function get_script_depends(): array { return [ 'aomark-listings-leaflet', 'aomark-listings' ]; }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_map', [ 'label' => esc_html__( 'Map', 'aomark-listings' ) ] );
		aomark_listings_add_model_control( $this );
		$this->add_control( 'source', [ 'label' => esc_html__( 'Source', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'query', 'options' => [ 'query' => esc_html__( 'Query Results', 'aomark-listings' ), 'current' => esc_html__( 'Current Listing', 'aomark-listings' ) ] ] );
		$this->add_control( 'post_id', [ 'label' => esc_html__( 'Listing ID', 'aomark-listings' ), 'description' => esc_html__( 'Optional. Leave empty to use the current listing.', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'condition' => [ 'source' => 'current' ] ] );
		$this->add_control( 'read_url_filters', [ 'label' => esc_html__( 'Read URL Filters', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => [ 'source' => 'query' ] ] );
		$this->add_control( 'map_limit', [ 'label' => esc_html__( 'Maximum Pins', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 200, 'min' => 1, 'max' => 500, 'condition' => [ 'source' => 'query' ] ] );
		$this->add_control( 'zoom', [ 'label' => esc_html__( 'Single Pin Zoom', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 10, 'min' => 2, 'max' => 18 ] );
		$this->add_control( 'fit_bounds', [ 'label' => esc_html__( 'Fit All Pins', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes', 'condition' => [ 'source' => 'query' ] ] );
		$this->add_control( 'scroll_wheel_zoom', [ 'label' => esc_html__( 'Scroll Wheel Zoom', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no' ] );
		$this->add_control( 'zoom_control', [ 'label' => esc_html__( 'Zoom Controls', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'dragging', [ 'label' => esc_html__( 'Draggable Map', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'empty_message', [ 'label' => esc_html__( 'Empty Message', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => esc_html__( 'No mapped listings found.', 'aomark-listings' ) ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_popup', [ 'label' => esc_html__( 'Popup', 'aomark-listings' ) ] );
		$this->add_control( 'popup_image', [ 'label' => esc_html__( 'Image', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'popup_price', [ 'label' => esc_html__( 'Price', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'currency', [ 'label' => esc_html__( 'Currency', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '$', 'condition' => [ 'popup_price' => 'yes' ] ] );
		$this->add_control( 'decimals', [ 'label' => esc_html__( 'Price Decimals', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 0, 'min' => 0, 'max' => 4, 'condition' => [ 'popup_price' => 'yes' ] ] );
		$this->add_control( 'popup_address', [ 'label' => esc_html__( 'Address', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_advanced_connection', [ 'label' => esc_html__( 'Advanced', 'aomark-listings' ) ] );
		$this->add_control(
			'connection_id',
			[
				'label'       => esc_html__( 'Connection ID', 'aomark-listings' ),
				'description' => esc_html__( 'Optional. Use the same ID on Listing Results when a page contains more than one listings view.', 'aomark-listings' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'condition'   => [ 'source' => 'query' ],
			]
		);
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_map', [ 'label' => esc_html__( 'Map', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'height', [ 'label' => esc_html__( 'Height', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', 'vh' ], 'range' => [ 'px' => [ 'min' => 220, 'max' => 1200 ], 'vh' => [ 'min' => 20, 'max' => 100 ] ], 'default' => [ 'size' => 480, 'unit' => 'px' ], 'tablet_default' => [ 'size' => 420, 'unit' => 'px' ], 'mobile_default' => [ 'size' => 340, 'unit' => 'px' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-map' => 'height: {{SIZE}}{{UNIT}};' ] ] );
		aomark_listings_add_surface_controls( $this, 'map_canvas', '{{WRAPPER}} .aomark-listings-map', false );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_controls', [ 'label' => esc_html__( 'Zoom Controls', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE, 'condition' => [ 'zoom_control' => 'yes' ] ] );
		$this->add_control( 'control_color', [ 'label' => esc_html__( 'Icon Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .leaflet-control-zoom a' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'control_background', [ 'label' => esc_html__( 'Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .leaflet-control-zoom a' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'control_hover_color', [ 'label' => esc_html__( 'Hover Icon', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .leaflet-control-zoom a:hover' => 'color: {{VALUE}};' ] ] );
		$this->add_control( 'control_hover_background', [ 'label' => esc_html__( 'Hover Background', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .leaflet-control-zoom a:hover' => 'background-color: {{VALUE}};' ] ] );
		$this->add_control( 'control_border_color', [ 'label' => esc_html__( 'Border Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .leaflet-bar, {{WRAPPER}} .leaflet-bar a' => 'border-color: {{VALUE}};' ] ] );
		$this->add_responsive_control( 'control_radius', [ 'label' => esc_html__( 'Border Radius', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ], 'selectors' => [ '{{WRAPPER}} .leaflet-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_popup', [ 'label' => esc_html__( 'Popup', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_responsive_control( 'popup_width', [ 'label' => esc_html__( 'Content Width', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 140, 'max' => 420 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-popup' => 'width: {{SIZE}}{{UNIT}};' ] ] );
		$this->add_control( 'popup_title_color', [ 'label' => esc_html__( 'Title Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-popup a' => 'color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'popup_title_typography', 'selector' => '{{WRAPPER}} .aomark-listings-popup strong' ] );
		$this->add_control( 'popup_text_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-popup' => 'color: {{VALUE}};' ] ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'popup_text_typography', 'selector' => '{{WRAPPER}} .aomark-listings-popup' ] );
		$this->add_responsive_control( 'popup_image_height', [ 'label' => esc_html__( 'Image Height', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 60, 'max' => 300 ] ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-popup img' => 'height: {{SIZE}}{{UNIT}};' ], 'condition' => [ 'popup_image' => 'yes' ] ] );
		$this->add_responsive_control( 'popup_image_radius', [ 'label' => esc_html__( 'Image Radius', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', '%' ], 'selectors' => [ '{{WRAPPER}} .aomark-listings-popup img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ], 'condition' => [ 'popup_image' => 'yes' ] ] );
		$this->end_controls_section();

		$this->start_controls_section( 'section_style_empty', [ 'label' => esc_html__( 'Empty State', 'aomark-listings' ), 'tab' => \Elementor\Controls_Manager::TAB_STYLE ] );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), [ 'name' => 'empty_typography', 'selector' => '{{WRAPPER}} .aomark-listings-empty' ] );
		$this->add_control( 'empty_color', [ 'label' => esc_html__( 'Text Color', 'aomark-listings' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => [ '{{WRAPPER}} .aomark-listings-empty' => 'color: {{VALUE}};' ] ] );
		aomark_listings_add_surface_controls( $this, 'map_empty', '{{WRAPPER}} .aomark-listings-empty' );
		$this->end_controls_section();
	}

	protected function render(): void {
		$settings      = $this->get_settings_for_display();
		$connection_id = sanitize_key( $settings['connection_id'] ?? '' );
		$model          = aomark_listings_get_model( $settings['model_id'] ?? '' );
		$model_id       = $model ? $model['id'] : sanitize_key( $settings['model_id'] ?? '' );
		$source        = in_array( (string) ( $settings['source'] ?? 'query' ), [ 'current', 'single' ], true ) ? 'current' : 'query';
		$empty_message = sanitize_text_field( $settings['empty_message'] ?? __( 'No mapped listings found.', 'aomark-listings' ) );
		$map_config    = [
			'queryMap'        => 'query' === $source,
			'zoom'            => max( 2, min( 18, absint( $settings['zoom'] ?? 10 ) ) ),
			'fitBounds'       => 'yes' === ( $settings['fit_bounds'] ?? 'yes' ),
			'scrollWheelZoom' => 'yes' === ( $settings['scroll_wheel_zoom'] ?? 'no' ),
			'zoomControl'     => 'yes' === ( $settings['zoom_control'] ?? 'yes' ),
			'dragging'        => 'yes' === ( $settings['dragging'] ?? 'yes' ),
			'popupImage'      => 'yes' === ( $settings['popup_image'] ?? 'yes' ),
			'popupPrice'      => 'yes' === ( $settings['popup_price'] ?? 'yes' ),
			'popupAddress'    => 'yes' === ( $settings['popup_address'] ?? 'yes' ),
		];
		?>
		<div class="aomark-listings-component aomark-listings-component--map" data-aomark-component="map" data-aomark-connection="<?php echo esc_attr( $connection_id ); ?>" data-model-id="<?php echo esc_attr( $model_id ); ?>" data-map-source="<?php echo esc_attr( $source ); ?>" data-map-config="<?php echo esc_attr( wp_json_encode( $map_config ) ); ?>" data-empty-message="<?php echo esc_attr( $empty_message ); ?>">
			<?php echo aomark_listings_render_map( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	}
}
