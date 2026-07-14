<?php
/**
 * Generic listing query and data helpers.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_request_value( $key, $max_length = 200 ) {
	if ( ! isset( $_GET[ $key ] ) ) {
		return '';
	}

	$value = wp_unslash( $_GET[ $key ] );
	$value = is_array( $value ) ? reset( $value ) : $value;
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = sanitize_text_field( (string) $value );

	return substr( $value, 0, max( 1, absint( $max_length ) ) );
}

function aomark_listings_request_array( $key, $max_items = 20, $max_length = 100 ) {
	if ( ! isset( $_GET[ $key ] ) ) {
		return [];
	}

	$value = wp_unslash( $_GET[ $key ] );
	$value = is_array( $value ) ? $value : [ $value ];
	$value = array_slice( $value, 0, max( 1, absint( $max_items ) ) );
	$items = [];

	foreach ( $value as $item ) {
		if ( ! is_scalar( $item ) ) {
			continue;
		}

		$item = substr( sanitize_text_field( (string) $item ), 0, max( 1, absint( $max_length ) ) );
		if ( '' !== $item ) {
			$items[] = $item;
		}
	}

	return array_values( array_unique( $items ) );
}

function aomark_listings_filter_param( $id ) {
	return 'alm_' . aomark_listings_sanitize_id( $id );
}

function aomark_listings_numeric_value( $value ) {
	$value = aomark_listings_normalize_numeric_string( $value );
	if ( '' === $value ) {
		return null;
	}

	$number = (float) $value;

	return is_finite( $number ) ? $number : null;
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

/**
 * Resolve a model without silently substituting a different configured model.
 *
 * An empty ID may still select the first model for backwards-compatible widget
 * defaults. A non-empty unknown ID always fails closed.
 *
 * @param string $model_id      Model ID.
 * @param bool   $allow_default Whether an empty ID may use the first model.
 * @return array|null
 */
function aomark_listings_get_model_exact( $model_id = '', $allow_default = true ) {
	$models   = aomark_listings_get_models();
	$model_id = (string) $model_id;

	if ( '' !== $model_id ) {
		return $models[ $model_id ] ?? null;
	}

	return $allow_default && ! empty( $models ) ? reset( $models ) : null;
}

/**
 * Whether the current public URL belongs to a specific listing type.
 *
 * Empty model parameters retain the historical single-view behavior. Once a
 * model is present, other listing types on the same page ignore its filters.
 *
 * @param array $model Listing model.
 * @return bool
 */
function aomark_listings_request_targets_model( $model ) {
	$requested_model = aomark_listings_request_value( 'alm_model', 100 );

	return '' === $requested_model || ( isset( $model['id'] ) && $model['id'] === $requested_model );
}

/**
 * Read a URL value only when it targets the supplied listing type.
 *
 * @param array  $model      Listing model.
 * @param string $key        Request key.
 * @param int    $max_length Maximum value length.
 * @return string
 */
function aomark_listings_request_value_for_model( $model, $key, $max_length = 200 ) {
	return aomark_listings_request_targets_model( $model ) ? aomark_listings_request_value( $key, $max_length ) : '';
}

/**
 * Restrict a requested sort to the public query contract.
 *
 * @param mixed $sort Sort value.
 * @return string
 */
function aomark_listings_sanitize_sort( $sort ) {
	$sort = sanitize_key( (string) $sort );

	return in_array( $sort, [ 'date_desc', 'date_asc', 'title_asc', 'title_desc', 'price_asc', 'price_desc' ], true ) ? $sort : 'date_desc';
}

function aomark_listings_build_query_args( $settings = [], $page = 1 ) {
	$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
	$model = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	$page  = max( 1, min( 200, absint( $page ) ) );
	$per_page = max( 1, min( 60, absint( $settings['per_page'] ?? 9 ) ) );
	$read_url = $model && aomark_listings_request_targets_model( $model ) && 'yes' === ( $settings['read_url_filters'] ?? 'yes' );
	// Paging and sorting are result controls, not optional content filters.
	$request_sort = $model && aomark_listings_request_targets_model( $model ) ? aomark_listings_request_value( 'alm_sort', 20 ) : '';
	$sort     = aomark_listings_sanitize_sort( '' !== $request_sort ? $request_sort : ( $settings['sort'] ?? 'date_desc' ) );

	if ( ! $model ) {
		return [
			'model' => null,
			'args'  => [
				'post_type'           => 'any',
				'post_status'         => 'publish',
				'has_password'        => false,
				'post__in'            => [ 0 ],
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				'ignore_sticky_posts' => true,
			],
			'sort'  => $sort,
		];
	}

	$args = [
		'post_type'           => $model['post_type'],
		'post_status'         => 'publish',
		'has_password'        => false,
		'posts_per_page'      => $per_page,
		'paged'               => $page,
		'ignore_sticky_posts' => true,
	];

	if ( $read_url ) {
		$keyword = aomark_listings_request_value( 'alm_keyword' );
		if ( '' !== $keyword ) {
			$args['s'] = $keyword;
		}
	}

	$tax_query = [];
	foreach ( array_slice( (array) ( $settings['taxonomy_filters'] ?? [] ), 0, 10 ) as $filter ) {
		if ( ! is_array( $filter ) ) {
			continue;
		}

		$ref = isset( $filter['taxonomy_id'] ) && is_scalar( $filter['taxonomy_id'] ) ? sanitize_text_field( (string) $filter['taxonomy_id'] ) : '';
		$terms_raw = isset( $filter['terms'] ) && is_scalar( $filter['terms'] ) ? substr( sanitize_text_field( (string) $filter['terms'] ), 0, 1000 ) : '';
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

			$terms = array_slice(
				array_filter(
					array_map( 'sanitize_title', array_map( 'trim', explode( ',', $terms_raw ) ) ),
					function ( $term ) {
						return '' !== $term;
					}
				),
				0,
				20
			);
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
			if ( count( $tax_query ) >= 20 ) {
				break;
			}

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
	foreach ( array_slice( (array) ( $settings['meta_filters'] ?? [] ), 0, 10 ) as $filter ) {
		if ( ! is_array( $filter ) ) {
			continue;
		}

		$ref = isset( $filter['field_id'] ) && is_scalar( $filter['field_id'] ) ? sanitize_text_field( (string) $filter['field_id'] ) : '';
		$value = isset( $filter['value'] ) && is_scalar( $filter['value'] ) ? substr( trim( sanitize_text_field( (string) $filter['value'] ) ), 0, 200 ) : '';
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

		$compare = isset( $filter['compare'] ) && is_scalar( $filter['compare'] ) ? strtoupper( sanitize_text_field( (string) $filter['compare'] ) ) : '=';
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
			if ( count( $meta_query ) >= 20 ) {
				break;
			}

			$key = aomark_listings_meta_key( $field );
			if ( in_array( $field['type'], [ 'number', 'price' ], true ) ) {
				$min = aomark_listings_numeric_value( aomark_listings_request_value( 'alm_min_' . $field['id'] ) );
				$max = aomark_listings_numeric_value( aomark_listings_request_value( 'alm_max_' . $field['id'] ) );
				if ( null !== $min && count( $meta_query ) < 20 ) {
					$meta_query[] = [ 'key' => $key, 'value' => $min, 'type' => 'NUMERIC', 'compare' => '>=' ];
				}
				if ( null !== $max && count( $meta_query ) < 20 ) {
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
	$limit = max( 1, min( 500, absint( $settings['map_limit'] ?? $settings['limit'] ?? 200 ) ) );
	$built = aomark_listings_build_query_args( $settings, 1 );
	$model = $built['model'];
	$items = [];

	if ( ! $model ) {
		return $items;
	}

	$location_field = aomark_listings_get_first_field_by_type( $model, 'location' );
	if ( ! $location_field ) {
		return $items;
	}

	$location_key = aomark_listings_meta_key( $location_field );
	if ( empty( $built['args']['meta_query'] ) ) {
		$built['args']['meta_query'] = [];
	}
	// Reject malformed legacy values before DECIMAL casts can turn them into zero
	// and consume one of the bounded map result slots.
	$coordinate_pattern = '^[+-]?([0-9]+([.,][0-9]*)?|[.,][0-9]+)([eE][+-]?[0-9]+)?$';
	$built['args']['meta_query'][] = [
		'key'     => $location_key . '_lat',
		'value'   => $coordinate_pattern,
		'compare' => 'REGEXP',
	];
	$built['args']['meta_query'][] = [
		'key'     => $location_key . '_lng',
		'value'   => $coordinate_pattern,
		'compare' => 'REGEXP',
	];
	$built['args']['meta_query'][] = [
		'key'     => $location_key . '_lat',
		'value'   => [ -90, 90 ],
		'compare' => 'BETWEEN',
		'type'    => 'DECIMAL(10,7)',
	];
	$built['args']['meta_query'][] = [
		'key'     => $location_key . '_lng',
		'value'   => [ -180, 180 ],
		'compare' => 'BETWEEN',
		'type'    => 'DECIMAL(10,7)',
	];
	$built['args']['meta_query'][] = [
		'relation' => 'OR',
		[
			'key'     => $location_key . '_lat',
			'value'   => 0,
			'compare' => '!=',
			'type'    => 'NUMERIC',
		],
		[
			'key'     => $location_key . '_lng',
			'value'   => 0,
			'compare' => '!=',
			'type'    => 'NUMERIC',
		],
	];

	$built['args']['posts_per_page'] = $limit;
	$built['args']['paged']          = 1;
	$built['args']['no_found_rows']  = true;
	$query = new WP_Query( $built['args'] );

	foreach ( $query->posts as $post ) {
		if ( post_password_required( $post ) && ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}

		$location = aomark_listings_location_value( $post->ID, $model );
		if ( ! $location ) {
			continue;
		}

		$price_field = aomark_listings_get_first_field_by_type( $model, 'price' );
		$item = [
			'id'      => $post->ID,
			'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'url'     => get_permalink( $post ),
			'lat'     => $location['lat'],
			'lng'     => $location['lng'],
		];

		if ( 'no' !== ( $settings['popup_image'] ?? 'yes' ) ) {
			$item['image'] = get_the_post_thumbnail_url( $post, 'medium' ) ?: '';
		}
		if ( 'no' !== ( $settings['popup_address'] ?? 'yes' ) ) {
			$item['address'] = $location['address'];
		}
		if ( 'no' !== ( $settings['popup_price'] ?? 'yes' ) ) {
			$item['price'] = $price_field ? aomark_listings_field_display_value( $post->ID, $price_field, $settings ) : '';
		}

		$items[] = $item;
	}

	return $items;
}
