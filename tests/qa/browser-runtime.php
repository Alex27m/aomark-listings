<?php
/**
 * Browser-only disposable QA renderer.
 *
 * It replaces the stored QA page body at request time so WordPress content
 * sanitization and wpautop cannot strip or rewrite the real form controls.
 * This file is copied to mu-plugins only inside WordPress Playground.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identify the disposable browser QA page.
 *
 * @return bool
 */
function aomark_listings_browser_qa_is_page() {
	return is_page( 'aomark-listings-qa' );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( aomark_listings_browser_qa_is_page() && function_exists( 'aomark_listings_enqueue_map_assets' ) ) {
			aomark_listings_enqueue_map_assets();
		}
	},
	20
);

add_filter(
	'the_content',
	function ( $content ) {
		if ( ! aomark_listings_browser_qa_is_page() || ! function_exists( 'aomark_listings_render_results' ) ) {
			return $content;
		}

		$model_id    = 'real_estate';
		$results_url = get_permalink();
		$settings    = [
			'model_id'         => $model_id,
			'per_page'         => 4,
			'columns'          => 3,
			'pagination'       => 'numbered',
			'ajax'             => 'yes',
			'read_url_filters' => 'yes',
			'show_filter'      => 'yes',
			'show_count'       => 'yes',
			'show_sort'        => 'yes',
			'sort'             => 'date_desc',
			'results_url'      => $results_url,
			'taxonomy_filters' => [
				[
					'taxonomy_id' => $model_id . ':features',
					'terms'       => 'aomark-qa-fixture',
				],
			],
		];
		$map_settings = [
			'model_id'         => $model_id,
			'source'           => 'query',
			'map_limit'        => 50,
			'read_url_filters' => 'yes',
			'taxonomy_filters' => $settings['taxonomy_filters'],
			'height'           => 420,
		];
		$first_listing = get_posts(
			[
				'post_type'      => 'aomark_property',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$output  = '<div class="aomark-listings-qa">';
		$output .= '<h1>Aomark Listings disposable browser QA</h1>';
		$output .= '<p>This page renders plugin controls at request time only inside WordPress Playground.</p>';
		$output .= '<section aria-labelledby="aomark-qa-filter-heading"><h2 id="aomark-qa-filter-heading">Filter</h2>';
		$output .= '<div class="aomark-listings-component aomark-listings-component--filter" data-aomark-component="filter" data-aomark-connection="qa-primary" data-model-id="' . esc_attr( $model_id ) . '">';
		$output .= aomark_listings_render_filter(
			[
				'model_id'    => $model_id,
				'results_url' => $results_url,
				'show_reset'  => 'yes',
				'button_text' => 'Search QA properties',
				'reset_text'  => 'Reset QA filters',
			]
		);
		$output .= '</div></section>';
		$output .= '<section aria-labelledby="aomark-qa-results-heading"><h2 id="aomark-qa-results-heading">Results</h2>';
		$output .= '<div class="aomark-listings-component aomark-listings-component--results" data-aomark-component="results" data-aomark-connection="qa-primary" data-model-id="' . esc_attr( $model_id ) . '">';
		$output .= aomark_listings_render_results( $settings );
		$output .= '<div class="aomark-listings-status screen-reader-text" data-aomark-listings-status role="status" aria-live="polite" aria-atomic="true"></div>';
		$output .= '</div></section>';
		$output .= '<section aria-labelledby="aomark-qa-map-heading"><h2 id="aomark-qa-map-heading">Map</h2>';
		$output .= '<div class="aomark-listings-component aomark-listings-component--map" data-aomark-component="map" data-aomark-connection="qa-primary" data-model-id="' . esc_attr( $model_id ) . '">';
		$output .= aomark_listings_render_map( $map_settings );
		$output .= '</div></section>';
		if ( $first_listing ) {
			$output .= '<section aria-labelledby="aomark-qa-meta-heading"><h2 id="aomark-qa-meta-heading">First listing meta</h2>';
			$output .= aomark_listings_render_meta(
				[
					'model_id'    => $model_id,
					'post_id'     => $first_listing[0],
					'show_labels' => 'yes',
				]
			);
			$output .= '</section>';
		}
		$output .= '</div>';

		return $output;
	},
	99
);
