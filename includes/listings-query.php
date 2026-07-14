<?php
/**
 * Generic listing query and data helpers.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_request_value( $key ) {
	if ( ! isset( $_GET[ $key ] ) ) {
		return '';
	}

	$value = wp_unslash( $_GET[ $key ] );
	$value = is_array( $value ) ? reset( $value ) : $value;

	return sanitize_text_field( $value );
}

function aomark_listings_request_array( $key ) {
	if ( ! isset( $_GET[ $key ] ) ) {
		return [];
	}

	$value = wp_unslash( $_GET[ $key ] );
	$value = is_array( $value ) ? $value : [ $value ];

	return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
}

function aomark_listings_filter_param( $id ) {
	return 'alm_' . aomark_listings_sanitize_id( $id );
}

function aomark_listings_numeric_value( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value || ! preg_match( '/\d/', $value ) ) {
		return null;
	}

	$value = preg_replace( '/[^\d,.\-]/', '', $value );
	if ( false !== strpos( $value, ',' ) && false !== strpos( $value, '.' ) ) {
		$value = str_replace( ',', '', $value );
	} elseif ( false !== strpos( $value, ',' ) ) {
		$value = str_replace( ',', '.', $value );
	}

	return is_numeric( $value ) ? (float) $value : null;
}

function aomark_listings_format_price( $value, $currency = '$', $decimals = 0 ) {
	$number = aomark_listings_numeric_value( $value );

	if ( null === $number ) {
		return trim( (string) $value );
	}

	return trim( $currency ) . number_format_i18n( $number, absint( $decimals ) );
}

function aomark_listings_field_raw_value( $post_id, $field ) {
	$key = aomark_listings_meta_key( $field );

	if ( 'location' === $field['type'] ) {
		return [
			'address' => get_post_meta( $post_id, $key . '_address', true ),
			'lat'     => get_post_meta( $post_id, $key . '_lat', true ),
			'lng'     => get_post_meta( $post_id, $key . '_lng', true ),
		];
	}

	return get_post_meta( $post_id, $key, true );
}

function aomark_listings_field_display_value( $post_id, $field, $settings = [] ) {
	$value = aomark_listings_field_raw_value( $post_id, $field );

	if ( 'location' === $field['type'] ) {
		return is_array( $value ) ? trim( (string) ( $value['address'] ?? '' ) ) : '';
	}

	if ( 'checkbox' === $field['type'] ) {
		return aomark_listings_bool( $value ) ? __( 'Yes', 'aomark-listings' ) : '';
	}

	if ( 'image' === $field['type'] || 'gallery' === $field['type'] ) {
		return '';
	}

	if ( 'select' === $field['type'] ) {
		foreach ( (array) $field['options'] as $option ) {
			if ( (string) $value === (string) $option['value'] ) {
				return $option['label'];
			}
		}
	}

	if ( 'price' === $field['type'] ) {
		return aomark_listings_format_price( $value, $settings['currency'] ?? '$', $settings['decimals'] ?? 0 );
	}

	$value = is_scalar( $value ) ? trim( (string) $value ) : '';
	$suffix = trim( (string) ( $field['suffix'] ?? '' ) );

	return '' !== $value && '' !== $suffix ? $value . ' ' . $suffix : $value;
}

function aomark_listings_gallery_ids( $post_id, $field ) {
	$value = get_post_meta( $post_id, aomark_listings_meta_key( $field ), true );

	if ( is_array( $value ) ) {
		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	return array_values( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) );
}

function aomark_listings_location_value( $post_id, $model ) {
	$field = aomark_listings_get_first_field_by_type( $model, 'location' );

	if ( ! $field ) {
		return false;
	}

	$value = aomark_listings_field_raw_value( $post_id, $field );
	$lat   = isset( $value['lat'] ) ? aomark_listings_numeric_value( $value['lat'] ) : null;
	$lng   = isset( $value['lng'] ) ? aomark_listings_numeric_value( $value['lng'] ) : null;

	if ( null === $lat || null === $lng || 0.0 === $lat && 0.0 === $lng || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
		return false;
	}

	return [
		'lat'     => $lat,
		'lng'     => $lng,
		'address' => trim( (string) ( $value['address'] ?? '' ) ),
	];
}

function aomark_listings_get_filterable_taxonomies( $model ) {
	return array_values(
		array_filter(
			(array) ( $model['taxonomies'] ?? [] ),
			function ( $taxonomy ) {
				return ! empty( $taxonomy['filterable'] );
			}
		)
	);
}

function aomark_listings_get_filterable_fields( $model ) {
	return array_values(
		array_filter(
			(array) ( $model['fields'] ?? [] ),
			function ( $field ) {
				return ! empty( $field['filterable'] ) && ! in_array( $field['type'], [ 'image', 'gallery', 'location', 'textarea' ], true );
			}
		)
	);
}

function aomark_listings_build_query_args( $settings = [], $page = 1 ) {
	$model = aomark_listings_get_model( $settings['model_id'] ?? '' );
	$page  = max( 1, absint( $page ) );
	$per_page = max( 1, min( 60, absint( $settings['per_page'] ?? 9 ) ) );
	$read_url = 'yes' === ( $settings['read_url_filters'] ?? 'yes' );
	$request_sort = $read_url ? aomark_listings_request_value( 'alm_sort' ) : '';
	$sort     = sanitize_key( '' !== $request_sort ? $request_sort : ( $settings['sort'] ?? 'date_desc' ) );
	$sort     = $sort ?: 'date_desc';

	$args = [
		'post_type'      => $model['post_type'],
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $page,
	];

	if ( $read_url ) {
		$keyword = aomark_listings_request_value( 'alm_keyword' );
		if ( '' !== $keyword ) {
			$args['s'] = $keyword;
		}
	}

	$tax_query = [];
	foreach ( (array) ( $settings['taxonomy_filters'] ?? [] ) as $filter ) {
		$ref = sanitize_text_field( $filter['taxonomy_id'] ?? '' );
		$terms_raw = sanitize_text_field( $filter['terms'] ?? '' );
		if ( '' === $ref || '' === $terms_raw ) {
			continue;
		}

		$parts = explode( ':', $ref, 2 );
		$filter_model_id = isset( $parts[1] ) ? sanitize_key( $parts[0] ) : $model['id'];
		$taxonomy_id = isset( $parts[1] ) ? sanitize_key( $parts[1] ) : sanitize_key( $parts[0] );

		if ( $filter_model_id !== $model['id'] ) {
			continue;
		}

		foreach ( (array) $model['taxonomies'] as $taxonomy ) {
			if ( $taxonomy_id !== $taxonomy['id'] || ! taxonomy_exists( $taxonomy['slug'] ) ) {
				continue;
			}

			$terms = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $terms_raw ) ) ) );
			if ( ! empty( $terms ) ) {
				$tax_query[] = [
					'taxonomy'         => $taxonomy['slug'],
					'field'            => 'slug',
					'terms'            => $terms,
					'include_children' => true,
				];
			}
		}
	}

	if ( $read_url ) {
		foreach ( aomark_listings_get_filterable_taxonomies( $model ) as $taxonomy ) {
			$terms = aomark_listings_request_array( aomark_listings_filter_param( $taxonomy['id'] ) );
			if ( ! empty( $terms ) && taxonomy_exists( $taxonomy['slug'] ) ) {
				$tax_query[] = [
					'taxonomy'         => $taxonomy['slug'],
					'field'            => 'slug',
					'terms'            => array_map( 'sanitize_title', $terms ),
					'include_children' => true,
				];
			}
		}
	}

	if ( ! empty( $tax_query ) ) {
		$args['tax_query'] = count( $tax_query ) > 1 ? array_merge( [ 'relation' => 'AND' ], $tax_query ) : $tax_query;
	}

	$meta_query = [];
	foreach ( (array) ( $settings['meta_filters'] ?? [] ) as $filter ) {
		$ref = sanitize_text_field( $filter['field_id'] ?? '' );
		$value = isset( $filter['value'] ) ? trim( sanitize_text_field( $filter['value'] ) ) : '';
		if ( '' === $ref || '' === $value ) {
			continue;
		}

		$parts = explode( ':', $ref, 2 );
		$filter_model_id = isset( $parts[1] ) ? sanitize_key( $parts[0] ) : $model['id'];
		$field_id = isset( $parts[1] ) ? sanitize_key( $parts[1] ) : sanitize_key( $parts[0] );

		if ( $filter_model_id !== $model['id'] ) {
			continue;
		}

		$field = aomark_listings_get_field( $model, $field_id );
		if ( ! $field ) {
			continue;
		}

		$compare = strtoupper( sanitize_text_field( $filter['compare'] ?? '=' ) );
		$compare = in_array( $compare, [ '=', 'LIKE', '>=', '<=', '>', '<' ], true ) ? $compare : '=';
		$query = [
			'key'     => aomark_listings_meta_key( $field ),
			'value'   => $value,
			'compare' => $compare,
		];

		if ( in_array( $field['type'], [ 'number', 'price' ], true ) ) {
			$number = aomark_listings_numeric_value( $value );
			if ( null === $number ) {
				continue;
			}
			$query['value'] = $number;
			$query['type']  = 'NUMERIC';
		}

		$meta_query[] = $query;
	}

	if ( $read_url ) {
		foreach ( aomark_listings_get_filterable_fields( $model ) as $field ) {
			$key = aomark_listings_meta_key( $field );
			if ( in_array( $field['type'], [ 'number', 'price' ], true ) ) {
				$min = aomark_listings_numeric_value( aomark_listings_request_value( 'alm_min_' . $field['id'] ) );
				$max = aomark_listings_numeric_value( aomark_listings_request_value( 'alm_max_' . $field['id'] ) );
				if ( null !== $min ) {
					$meta_query[] = [ 'key' => $key, 'value' => $min, 'type' => 'NUMERIC', 'compare' => '>=' ];
				}
				if ( null !== $max ) {
					$meta_query[] = [ 'key' => $key, 'value' => $max, 'type' => 'NUMERIC', 'compare' => '<=' ];
				}
			} else {
				$value = aomark_listings_request_value( aomark_listings_filter_param( 'field_' . $field['id'] ) );
				if ( '' !== $value ) {
					$meta_query[] = [
						'key'     => $key,
						'value'   => sanitize_text_field( $value ),
						'compare' => 'select' === $field['type'] || 'checkbox' === $field['type'] ? '=' : 'LIKE',
					];
				}
			}
		}
	}

	if ( ! empty( $meta_query ) ) {
		$args['meta_query'] = count( $meta_query ) > 1 ? array_merge( [ 'relation' => 'AND' ], $meta_query ) : $meta_query;
	}

	if ( 'title_asc' === $sort || 'title_desc' === $sort ) {
		$args['orderby'] = 'title';
		$args['order']   = 'title_asc' === $sort ? 'ASC' : 'DESC';
	} elseif ( 'price_asc' === $sort || 'price_desc' === $sort ) {
		$price_field = aomark_listings_get_first_field_by_type( $model, 'price' );
		if ( $price_field ) {
			$args['meta_key'] = aomark_listings_meta_key( $price_field );
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'price_asc' === $sort ? 'ASC' : 'DESC';
		}
	} else {
		$args['orderby'] = 'date';
		$args['order']   = 'date_asc' === $sort ? 'ASC' : 'DESC';
	}

	return [
		'model' => $model,
		'args'  => $args,
		'sort'  => $sort,
	];
}

function aomark_listings_get_query( $settings = [], $page = 1 ) {
	$built = aomark_listings_build_query_args( $settings, $page );
	$query = new WP_Query( $built['args'] );

	return [
		'model' => $built['model'],
		'query' => $query,
		'sort'  => $built['sort'],
	];
}

function aomark_listings_get_map_items( $settings = [] ) {
	$settings['per_page'] = $settings['map_limit'] ?? $settings['limit'] ?? 200;
	$data  = aomark_listings_get_query( $settings, 1 );
	$model = $data['model'];
	$items = [];

	foreach ( $data['query']->posts as $post ) {
		$location = aomark_listings_location_value( $post->ID, $model );
		if ( ! $location ) {
			continue;
		}

		$price_field = aomark_listings_get_first_field_by_type( $model, 'price' );
		$items[] = [
			'id'      => $post->ID,
			'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'url'     => get_permalink( $post ),
			'image'   => get_the_post_thumbnail_url( $post, 'medium' ) ?: '',
			'lat'     => $location['lat'],
			'lng'     => $location['lng'],
			'address' => $location['address'],
			'price'   => $price_field ? aomark_listings_field_display_value( $post->ID, $price_field, $settings ) : '',
		];
	}

	wp_reset_postdata();

	return $items;
}
