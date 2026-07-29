<?php
/**
 * Generic listing renderers.
 *
 * @package Aomark_Listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aomark_listings_sanitize_results_settings( $settings = [] ) {
	$settings = is_array( $settings ) ? $settings : [];
	$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
	$model    = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	$model_id = $model ? $model['id'] : $model_id;
	$sort     = aomark_listings_sanitize_sort( $settings['sort'] ?? 'date_desc' );
	$results_url = '';
	$taxonomy_filters = [];
	$meta_filters     = [];
	$card_field_ids   = [];

	if ( isset( $settings['results_url'] ) && is_scalar( $settings['results_url'] ) ) {
		$candidate_url = esc_url_raw( trim( (string) $settings['results_url'] ), [ 'http', 'https' ] );
		$results_url   = $candidate_url ? wp_validate_redirect( $candidate_url, '' ) : '';
	}

	if ( $model ) {
		foreach ( array_slice( (array) ( $settings['taxonomy_filters'] ?? [] ), 0, 10 ) as $filter ) {
			if ( ! is_array( $filter ) || ! is_scalar( $filter['taxonomy_id'] ?? null ) || ! is_scalar( $filter['terms'] ?? null ) ) {
				continue;
			}

			$reference = sanitize_text_field( (string) $filter['taxonomy_id'] );
			$parts     = explode( ':', $reference, 2 );
			$filter_model_id = isset( $parts[1] ) ? sanitize_key( $parts[0] ) : $model['id'];
			$taxonomy_id     = sanitize_key( isset( $parts[1] ) ? $parts[1] : $parts[0] );
			$known            = false;

			foreach ( (array) $model['taxonomies'] as $taxonomy ) {
				if ( $taxonomy_id === $taxonomy['id'] ) {
					$known = true;
					break;
				}
			}

			$terms = substr( sanitize_text_field( (string) $filter['terms'] ), 0, 1000 );
			if ( $known && $filter_model_id === $model['id'] && '' !== $terms ) {
				$taxonomy_filters[] = [
					'taxonomy_id' => $model['id'] . ':' . $taxonomy_id,
					'terms'       => $terms,
				];
			}
		}

		foreach ( array_slice( (array) ( $settings['meta_filters'] ?? [] ), 0, 10 ) as $filter ) {
			if ( ! is_array( $filter ) || ! is_scalar( $filter['field_id'] ?? null ) || ! is_scalar( $filter['value'] ?? null ) ) {
				continue;
			}

			$reference = sanitize_text_field( (string) $filter['field_id'] );
			$parts     = explode( ':', $reference, 2 );
			$filter_model_id = isset( $parts[1] ) ? sanitize_key( $parts[0] ) : $model['id'];
			$field_id        = sanitize_key( isset( $parts[1] ) ? $parts[1] : $parts[0] );
			$field           = aomark_listings_get_field( $model, $field_id );
			$value           = substr( trim( sanitize_text_field( (string) $filter['value'] ) ), 0, 200 );
			$compare_raw     = isset( $filter['compare'] ) && is_scalar( $filter['compare'] ) ? (string) $filter['compare'] : '=';
			$compare         = strtoupper( sanitize_text_field( $compare_raw ) );
			$compare         = in_array( $compare, [ '=', 'LIKE', '>=', '<=', '>', '<' ], true ) ? $compare : '=';

			if ( $field && $filter_model_id === $model['id'] && '' !== $value ) {
				$meta_filters[] = [
					'field_id' => $model['id'] . ':' . $field['id'],
					'compare'  => $compare,
					'value'    => $value,
				];
			}
		}

		$selected_card_fields = aomark_listings_normalize_selected_ids( $settings['card_field_ids'] ?? [], $model['id'] );
		foreach ( array_slice( $selected_card_fields, 0, 20 ) as $field_id ) {
			$field = aomark_listings_get_field( $model, $field_id );
			if ( $field && ! in_array( $field['type'], [ 'price', 'image', 'gallery', 'location' ], true ) ) {
				$card_field_ids[] = $model['id'] . ':' . $field['id'];
			}
		}
	}

	return [
		'model_id'         => $model_id,
		'per_page'         => max( 1, min( 60, absint( $settings['per_page'] ?? 9 ) ) ),
		'read_url_filters' => aomark_listings_switch_setting( $settings, 'read_url_filters', 'yes' ),
		'ajax'             => aomark_listings_switch_setting( $settings, 'ajax', 'yes' ),
		'pagination'       => in_array( (string) ( $settings['pagination'] ?? 'numbered' ), [ 'numbered', 'load_more', 'none' ], true ) ? (string) $settings['pagination'] : 'numbered',
		'skin'             => in_array( (string) ( $settings['skin'] ?? 'cards' ), [ 'cards', 'list' ], true ) ? (string) ( $settings['skin'] ?? 'cards' ) : 'cards',
		'columns'          => max( 1, min( 6, absint( $settings['columns'] ?? 3 ) ) ),
		'show_filter'      => aomark_listings_switch_setting( $settings, 'show_filter', 'no' ),
		'show_count'       => aomark_listings_switch_setting( $settings, 'show_count', 'yes' ),
		'show_sort'        => aomark_listings_switch_setting( $settings, 'show_sort', 'yes' ),
		'show_image'       => aomark_listings_switch_setting( $settings, 'show_image', 'yes' ),
		'show_title'       => aomark_listings_switch_setting( $settings, 'show_title', 'yes' ),
		'show_price'       => aomark_listings_switch_setting( $settings, 'show_price', 'yes' ),
		'show_meta'        => aomark_listings_switch_setting( $settings, 'show_meta', 'yes' ),
		'show_button'      => aomark_listings_switch_setting( $settings, 'show_button', 'yes' ),
		'sort'             => $sort,
		'image_size'       => sanitize_key( $settings['image_size'] ?? 'medium_large' ),
		'title_tag'        => in_array( (string) ( $settings['title_tag'] ?? 'h3' ), [ 'h2', 'h3', 'h4', 'h5', 'div' ], true ) ? (string) ( $settings['title_tag'] ?? 'h3' ) : 'h3',
		'button_text'      => substr( sanitize_text_field( $settings['button_text'] ?? __( 'View Details', 'aomark-listings' ) ), 0, 100 ),
		'empty_message'    => substr( sanitize_text_field( $settings['empty_message'] ?? __( 'No listings found.', 'aomark-listings' ) ), 0, 300 ),
		'load_more_text'   => substr( sanitize_text_field( $settings['load_more_text'] ?? __( 'Load More', 'aomark-listings' ) ), 0, 100 ),
		'currency'         => substr( sanitize_text_field( $settings['currency'] ?? '$' ), 0, 12 ),
		'decimals'         => min( 4, absint( $settings['decimals'] ?? 0 ) ),
		'results_url'      => $results_url,
		'taxonomy_filters' => $taxonomy_filters,
		'meta_filters'     => $meta_filters,
		'card_field_ids'   => $card_field_ids,
	];
}

function aomark_listings_switch_setting( $settings, $key, $default = 'yes' ) {
	if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
		return 'yes' === $settings[ $key ] ? 'yes' : 'no';
	}

	return 'yes' === $default ? 'yes' : 'no';
}

/**
 * Create a cache-safe, purpose-bound descriptor for public AJAX requests.
 *
 * The payload is visible to the browser but cannot be modified without
 * invalidating its HMAC. This is authorization for configuration integrity;
 * the WordPress nonce remains a separate CSRF/replay-hardening measure.
 *
 * @param string $purpose Descriptor purpose.
 * @param array  $payload Sanitized payload.
 * @return array
 */
function aomark_listings_create_signed_descriptor( $purpose, $payload ) {
	$json    = wp_json_encode( (array) $payload );
	$encoded = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
	$message = 'aomark-listings|' . sanitize_key( $purpose ) . '|1|' . $encoded;

	return [
		'v' => 1,
		'p' => $encoded,
		's' => hash_hmac( 'sha256', $message, wp_salt( 'auth' ) ),
	];
}

/**
 * Verify and decode a signed public descriptor.
 *
 * @param mixed  $descriptor Descriptor array.
 * @param string $purpose    Expected purpose.
 * @return array|false
 */
function aomark_listings_verify_signed_descriptor( $descriptor, $purpose ) {
	if ( ! is_array( $descriptor ) || 1 !== (int) ( $descriptor['v'] ?? 0 ) ) {
		return false;
	}

	$encoded   = isset( $descriptor['p'] ) && is_string( $descriptor['p'] ) ? $descriptor['p'] : '';
	$signature = isset( $descriptor['s'] ) && is_string( $descriptor['s'] ) ? $descriptor['s'] : '';
	if ( '' === $encoded || strlen( $encoded ) > 32768 || ! preg_match( '/^[A-Za-z0-9_-]+$/', $encoded ) || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
		return false;
	}

	$message  = 'aomark-listings|' . sanitize_key( $purpose ) . '|1|' . $encoded;
	$expected = hash_hmac( 'sha256', $message, wp_salt( 'auth' ) );
	if ( ! hash_equals( $expected, $signature ) ) {
		return false;
	}

	$padding = strlen( $encoded ) % 4;
	if ( $padding ) {
		$encoded .= str_repeat( '=', 4 - $padding );
	}

	$json = base64_decode( strtr( $encoded, '-_', '+/' ), true );
	if ( false === $json || strlen( $json ) > 24576 ) {
		return false;
	}

	$payload = json_decode( $json, true, 20 );

	return is_array( $payload ) ? $payload : false;
}

/**
 * Resolve a stable public target for filter and reset actions.
 *
 * Elementor archive templates can change the global post, so archive context
 * must be resolved before falling back to the queried page permalink.
 *
 * @param array $model Listing model.
 * @return string
 */
function aomark_listings_default_results_url( $model ) {
	$post_type = sanitize_key( (string) ( $model['post_type'] ?? '' ) );
	if ( $post_type && is_post_type_archive( $post_type ) ) {
		$archive_url = get_post_type_archive_link( $post_type );
		if ( $archive_url ) {
			return $archive_url;
		}
	}

	$taxonomy_slugs = array_values( array_filter( wp_list_pluck( (array) ( $model['taxonomies'] ?? [] ), 'slug' ) ) );
	if ( ! empty( $taxonomy_slugs ) && is_tax( $taxonomy_slugs ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term && in_array( $term->taxonomy, $taxonomy_slugs, true ) ) {
			$term_url = get_term_link( $term );
			if ( ! is_wp_error( $term_url ) ) {
				return $term_url;
			}
		}
	}

	$queried_object = get_queried_object();
	if ( $queried_object instanceof WP_Post ) {
		$permalink = get_permalink( $queried_object->ID );
		if ( $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/' );
}

/**
 * Return WordPress routing query arguments that a GET form must retain.
 *
 * Browsers replace an action URL's query string when submitting a GET form.
 * Plain-permalink routes therefore need their routing arguments duplicated as
 * hidden controls.
 *
 * @param string     $url   Public results URL.
 * @param array|null $model Listing model whose custom query vars are public routes.
 * @return array
 */
function aomark_listings_route_query_args( $url, $model = null ) {
	$query = wp_parse_url( (string) $url, PHP_URL_QUERY );
	if ( ! is_string( $query ) || '' === $query ) {
		return [];
	}

	$parsed = [];
	wp_parse_str( $query, $parsed );
	$allowed = [ 'p', 'page_id', 'post_type', 'taxonomy', 'term', 'pagename', 'name', 'attachment', 'attachment_id' ];
	if ( is_array( $model ) ) {
		$allowed[] = sanitize_key( (string) ( $model['post_type'] ?? '' ) );
		foreach ( (array) ( $model['taxonomies'] ?? [] ) as $taxonomy ) {
			$allowed[] = sanitize_key( (string) ( $taxonomy['slug'] ?? '' ) );
		}
	}
	$allowed = array_values( array_unique( array_filter( $allowed ) ) );
	$route   = [];

	foreach ( $allowed as $key ) {
		if ( ! isset( $parsed[ $key ] ) || ! is_scalar( $parsed[ $key ] ) ) {
			continue;
		}

		$value = substr( sanitize_text_field( (string) $parsed[ $key ] ), 0, 200 );
		if ( '' !== $value ) {
			$route[ $key ] = $value;
		}
	}

	return $route;
}

/**
 * Render hidden controls that retain a plain-permalink public route.
 *
 * @param string     $url   Public results URL.
 * @param array|null $model Listing model whose custom query vars are public routes.
 * @return string
 */
function aomark_listings_render_route_query_inputs( $url, $model = null ) {
	$html = '';

	foreach ( aomark_listings_route_query_args( $url, $model ) as $key => $value ) {
		$html .= sprintf(
			'<input type="hidden" name="%1$s" value="%2$s" data-aomark-route-param="yes">',
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	return $html;
}

function aomark_listings_render_filter( $settings = [] ) {
	aomark_listings_enqueue_assets();

	$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
	$model = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	if ( ! $model ) {
		return '';
	}

	$settings = wp_parse_args(
		(array) $settings,
		[
			'model_id'      => $model['id'],
			'results_url'   => '',
			'button_text'   => __( 'Search', 'aomark-listings' ),
			'show_keyword'  => 'yes',
			'show_taxonomy' => 'yes',
			'show_fields'   => 'yes',
			'show_reset'    => 'no',
			'reset_text'    => __( 'Reset', 'aomark-listings' ),
			'keyword_placeholder' => __( 'Search', 'aomark-listings' ),
		]
	);

	$explicit_url = is_scalar( $settings['results_url'] ) ? trim( (string) $settings['results_url'] ) : '';
	$results_url  = '' !== $explicit_url ? $explicit_url : aomark_listings_default_results_url( $model );
	$request_targets_model = aomark_listings_request_targets_model( $model );

	ob_start();
	?>
	<div class="aomark-listings-filter" data-model-id="<?php echo esc_attr( $model['id'] ); ?>">
		<form class="aomark-listings-filter__form" action="<?php echo esc_url( $results_url ); ?>" method="get">
			<?php echo aomark_listings_render_route_query_inputs( $results_url, $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="alm_model" value="<?php echo esc_attr( $model['id'] ); ?>">
			<?php $active_sort = $request_targets_model ? aomark_listings_request_value( 'alm_sort', 20 ) : ''; ?>
			<?php if ( '' !== $active_sort ) : ?>
				<input type="hidden" name="alm_sort" value="<?php echo esc_attr( aomark_listings_sanitize_sort( $active_sort ) ); ?>">
			<?php endif; ?>

			<?php if ( 'yes' === aomark_listings_switch_setting( $settings, 'show_keyword', 'yes' ) ) : ?>
				<div class="aomark-listings-filter__field aomark-listings-filter__field--keyword">
					<input type="search" name="alm_keyword" value="<?php echo esc_attr( aomark_listings_request_value_for_model( $model, 'alm_keyword' ) ); ?>" placeholder="<?php echo esc_attr( $settings['keyword_placeholder'] ); ?>" aria-label="<?php echo esc_attr( $settings['keyword_placeholder'] ); ?>">
				</div>
			<?php endif; ?>

			<?php if ( 'yes' === aomark_listings_switch_setting( $settings, 'show_taxonomy', 'yes' ) ) : ?>
				<?php $selected_taxonomies = aomark_listings_normalize_selected_ids( $settings['taxonomy_ids'] ?? [], $model['id'] ); ?>
				<?php foreach ( aomark_listings_get_filterable_taxonomies( $model ) as $taxonomy ) : ?>
					<?php if ( ! empty( $selected_taxonomies ) && ! in_array( $taxonomy['id'], $selected_taxonomies, true ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php if ( ! taxonomy_exists( $taxonomy['slug'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php $selected = aomark_listings_request_value_for_model( $model, aomark_listings_filter_param( $taxonomy['id'] ) ); ?>
					<?php
					$term_limit = (int) apply_filters( 'aomark_listings_filter_term_limit', 200, $taxonomy, $model );
					$term_limit = max( 10, min( 1000, $term_limit ) );
					$terms      = get_terms(
						[
							'taxonomy'   => $taxonomy['slug'],
							'hide_empty' => true,
							'number'     => $term_limit,
							'orderby'    => 'name',
							'order'      => 'ASC',
						]
					);
					if ( '' !== $selected && ! is_wp_error( $terms ) && ! in_array( $selected, wp_list_pluck( $terms, 'slug' ), true ) ) {
						$selected_term = get_term_by( 'slug', $selected, $taxonomy['slug'] );
						if ( $selected_term instanceof WP_Term ) {
							array_unshift( $terms, $selected_term );
						}
					}
					?>
					<div class="aomark-listings-filter__field aomark-listings-filter__field--taxonomy">
						<select name="<?php echo esc_attr( aomark_listings_filter_param( $taxonomy['id'] ) ); ?>" aria-label="<?php echo esc_attr( $taxonomy['plural'] ); ?>">
							<option value=""><?php echo esc_html( $taxonomy['plural'] ); ?></option>
							<?php if ( ! is_wp_error( $terms ) ) : ?>
								<?php foreach ( $terms as $term ) : ?>
									<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( 'yes' === aomark_listings_switch_setting( $settings, 'show_fields', 'yes' ) ) : ?>
				<?php $selected_fields = aomark_listings_normalize_selected_ids( $settings['field_ids'] ?? [], $model['id'] ); ?>
				<?php foreach ( aomark_listings_get_filterable_fields( $model ) as $field ) : ?>
					<?php if ( ! empty( $selected_fields ) && ! in_array( $field['id'], $selected_fields, true ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<?php if ( in_array( $field['type'], [ 'number', 'price' ], true ) ) : ?>
						<div class="aomark-listings-filter__field aomark-listings-filter__field--min">
							<input type="number" step="any" name="<?php echo esc_attr( 'alm_min_' . $field['id'] ); ?>" value="<?php echo esc_attr( aomark_listings_request_value_for_model( $model, 'alm_min_' . $field['id'] ) ); ?>" placeholder="<?php echo esc_attr( sprintf( __( 'Min %s', 'aomark-listings' ), $field['label'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Minimum %s', 'aomark-listings' ), $field['label'] ) ); ?>">
						</div>
						<div class="aomark-listings-filter__field aomark-listings-filter__field--max">
							<input type="number" step="any" name="<?php echo esc_attr( 'alm_max_' . $field['id'] ); ?>" value="<?php echo esc_attr( aomark_listings_request_value_for_model( $model, 'alm_max_' . $field['id'] ) ); ?>" placeholder="<?php echo esc_attr( sprintf( __( 'Max %s', 'aomark-listings' ), $field['label'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Maximum %s', 'aomark-listings' ), $field['label'] ) ); ?>">
						</div>
					<?php elseif ( 'select' === $field['type'] ) : ?>
						<?php $selected = aomark_listings_request_value_for_model( $model, aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>
						<div class="aomark-listings-filter__field">
							<select name="<?php echo esc_attr( aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
								<option value=""><?php echo esc_html( $field['label'] ); ?></option>
								<?php foreach ( (array) $field['options'] as $option ) : ?>
									<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $selected, $option['value'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php elseif ( 'checkbox' === $field['type'] ) : ?>
						<?php $selected = aomark_listings_request_value_for_model( $model, aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>
						<?php $control_id = wp_unique_id( 'aomark-listings-checkbox-filter-' ); ?>
						<div class="aomark-listings-filter__field">
							<label class="aomark-listings-screen-reader-text" for="<?php echo esc_attr( $control_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							<select id="<?php echo esc_attr( $control_id ); ?>" name="<?php echo esc_attr( aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>">
								<option value=""><?php echo esc_html( $field['label'] ); ?></option>
								<option value="1" <?php selected( $selected, '1' ); ?>><?php esc_html_e( 'Yes', 'aomark-listings' ); ?></option>
								<option value="0" <?php selected( $selected, '0' ); ?>><?php esc_html_e( 'No', 'aomark-listings' ); ?></option>
							</select>
						</div>
					<?php else : ?>
						<?php $input_type = in_array( $field['type'], [ 'date', 'email', 'url' ], true ) ? $field['type'] : 'text'; ?>
						<div class="aomark-listings-filter__field">
							<input type="<?php echo esc_attr( $input_type ); ?>" name="<?php echo esc_attr( aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>" value="<?php echo esc_attr( aomark_listings_request_value_for_model( $model, aomark_listings_filter_param( 'field_' . $field['id'] ) ) ); ?>" placeholder="<?php echo esc_attr( $field['label'] ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>

			<div class="aomark-listings-filter__field aomark-listings-filter__field--button">
				<button type="submit" class="aomark-listings-button"><?php echo esc_html( $settings['button_text'] ); ?></button>
			</div>
			<?php if ( 'yes' === aomark_listings_switch_setting( $settings, 'show_reset', 'no' ) ) : ?>
				<div class="aomark-listings-filter__field aomark-listings-filter__field--reset">
					<a href="<?php echo esc_url( $results_url ); ?>" class="aomark-listings-reset"><?php echo esc_html( $settings['reset_text'] ); ?></a>
				</div>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

function aomark_listings_render_card( $post_id, $model, $settings ) {
	$post_id = aomark_listings_context_post_id(
		[
			'post_id'  => $post_id,
			'model_id' => $model['id'] ?? '',
		],
		$model
	);
	if ( ! $post_id ) {
		return '';
	}

	$title            = get_the_title( $post_id );
	$accessible_title = '' !== trim( (string) $title ) ? $title : sprintf( __( 'Listing %d', 'aomark-listings' ), $post_id );
	$url              = get_permalink( $post_id );
	$image_id = get_post_thumbnail_id( $post_id );
	$image    = $image_id ? wp_get_attachment_image(
		$image_id,
		$settings['image_size'] ?? 'medium_large',
		false,
		[ 'alt' => $accessible_title ]
	) : '';
	$price = aomark_listings_get_first_field_by_type( $model, 'price' );
	$selected_card_fields = aomark_listings_normalize_selected_ids( $settings['card_field_ids'] ?? [], $model['id'] );
	$card_fields = array_values(
		array_filter(
			(array) $model['fields'],
			function ( $field ) use ( $selected_card_fields ) {
				$selected = ! empty( $selected_card_fields ) ? in_array( $field['id'], $selected_card_fields, true ) : ! empty( $field['card'] );
				return $selected && 'price' !== $field['type'] && ! in_array( $field['type'], [ 'image', 'gallery', 'location' ], true );
			}
		)
	);
	$title_tag = in_array( (string) ( $settings['title_tag'] ?? 'h3' ), [ 'h2', 'h3', 'h4', 'h5', 'div' ], true ) ? (string) $settings['title_tag'] : 'h3';

	ob_start();
	?>
	<article class="aomark-listings-card<?php echo 'yes' === $settings['show_image'] ? '' : ' aomark-listings-card--no-image'; ?>" tabindex="-1">
		<?php if ( 'yes' === $settings['show_image'] ) : ?>
			<a class="aomark-listings-card__image" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $accessible_title ); ?>">
				<?php if ( $image ) : ?>
					<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="aomark-listings-card__placeholder"></span>
				<?php endif; ?>
			</a>
		<?php endif; ?>
		<div class="aomark-listings-card__body">
			<?php if ( 'yes' === $settings['show_title'] ) : ?>
				<<?php echo tag_escape( $title_tag ); ?> class="aomark-listings-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></<?php echo tag_escape( $title_tag ); ?>>
			<?php endif; ?>
			<?php if ( 'yes' === $settings['show_price'] && $price ) : ?>
				<?php $price_value = aomark_listings_field_display_value( $post_id, $price, $settings ); ?>
				<?php if ( '' !== $price_value ) : ?>
					<div class="aomark-listings-card__price"><?php echo esc_html( $price_value ); ?></div>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( 'yes' === $settings['show_meta'] && ! empty( $card_fields ) ) : ?>
				<div class="aomark-listings-card__meta">
					<?php foreach ( $card_fields as $field ) : ?>
						<?php $value = aomark_listings_field_display_value( $post_id, $field, $settings ); ?>
						<?php if ( '' === $value ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<span><strong><?php echo esc_html( $value ); ?></strong> <?php echo esc_html( $field['label'] ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( 'yes' === $settings['show_button'] ) : ?>
				<a class="aomark-listings-card__button" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $settings['button_text'] . ': ' . $accessible_title ); ?>"><?php echo esc_html( $settings['button_text'] ); ?></a>
			<?php endif; ?>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

/**
 * Build the current results URL for a page while retaining active filters.
 *
 * @param int        $page        Page number.
 * @param array|null $model       Listing model used to scope public URL state.
 * @param string     $results_url Stable public base URL for AJAX responses.
 * @return string
 */
function aomark_listings_results_page_url( $page, $model = null, $results_url = '' ) {
	$page = max( 1, min( 200, absint( $page ) ) );

	if ( ! $model ) {
		$url = remove_query_arg( 'alm_page' );

		return 1 === $page ? $url : add_query_arg( 'alm_page', $page, $url );
	}

	$params = [];
	if ( aomark_listings_request_targets_model( $model ) ) {
		$params = aomark_listings_sanitize_ajax_filters( http_build_query( wp_unslash( (array) $_GET ) ), $model );
	}
	unset( $params['alm_page'] );
	$params['alm_model'] = $model['id'];
	if ( $page > 1 ) {
		$params['alm_page'] = $page;
	}

	$base_url = is_scalar( $results_url ) ? trim( (string) $results_url ) : '';
	$base_url = '' !== $base_url ? $base_url : aomark_listings_default_results_url( $model );
	$base_query = wp_parse_url( $base_url, PHP_URL_QUERY );
	if ( is_string( $base_query ) && '' !== $base_query ) {
		$parsed_base = [];
		wp_parse_str( $base_query, $parsed_base );
		$listing_keys = array_values(
			array_filter(
				array_keys( $parsed_base ),
				function ( $key ) {
					return 0 === strpos( (string) $key, 'alm_' );
				}
			)
		);
		if ( ! empty( $listing_keys ) ) {
			$base_url = remove_query_arg( $listing_keys, $base_url );
		}
	}

	return add_query_arg( $params, $base_url );
}

/**
 * Render sanitized active listing filters as hidden form controls.
 *
 * @param string[]  $excluded Excluded parameter names.
 * @param array|null $model    Listing model used to allowlist filter keys.
 * @return string
 */
function aomark_listings_render_active_filter_inputs( $excluded = [], $model = null ) {
	$excluded = array_map( 'sanitize_key', (array) $excluded );
	$html     = '';
	$source   = (array) $_GET;

	if ( $model ) {
		$source = aomark_listings_request_targets_model( $model ) ? aomark_listings_sanitize_ajax_filters( http_build_query( wp_unslash( $source ) ), $model ) : [];
		$source['alm_model'] = $model['id'];
	} else {
		$source = array_slice( $source, 0, 50, true );
	}

	foreach ( $source as $key => $value ) {
		$key = substr( sanitize_key( (string) $key ), 0, 80 );
		if ( 0 !== strpos( $key, 'alm_' ) || in_array( $key, $excluded, true ) ) {
			continue;
		}

		$values = is_array( $value ) ? array_slice( $value, 0, 20 ) : [ $value ];
		foreach ( $values as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}
			$name  = is_array( $value ) ? $key . '[]' : $key;
			$item  = substr( sanitize_text_field( wp_unslash( (string) $item ) ), 0, 200 );
			$html .= sprintf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( $item ) );
		}
	}

	return $html;
}

function aomark_listings_render_pagination( $page, $max_pages, $model = null, $results_url = '' ) {
	$max_pages = max( 1, min( 200, absint( $max_pages ) ) );
	$page      = max( 1, min( $max_pages, absint( $page ) ) );

	if ( $max_pages <= 1 ) {
		return '';
	}

	$pages = array_values(
		array_unique(
			array_filter(
				[ 1, $page - 1, $page, $page + 1, $max_pages ],
				function ( $candidate ) use ( $max_pages ) {
					return $candidate >= 1 && $candidate <= $max_pages;
				}
			)
		)
	);
	sort( $pages );

	ob_start();
	?>
	<nav class="aomark-listings-pagination" aria-label="<?php esc_attr_e( 'Listings pagination', 'aomark-listings' ); ?>">
		<?php if ( $page > 1 ) : ?>
			<a href="<?php echo esc_url( aomark_listings_results_page_url( $page - 1, $model, $results_url ) ); ?>" data-page="<?php echo esc_attr( $page - 1 ); ?>"><?php esc_html_e( 'Previous', 'aomark-listings' ); ?></a>
		<?php else : ?>
			<span class="is-disabled" aria-disabled="true"><?php esc_html_e( 'Previous', 'aomark-listings' ); ?></span>
		<?php endif; ?>
		<?php foreach ( $pages as $page_number ) : ?>
			<?php if ( $page_number === $page ) : ?>
				<span class="is-active" aria-current="page"><?php echo esc_html( $page_number ); ?></span>
			<?php else : ?>
				<a href="<?php echo esc_url( aomark_listings_results_page_url( $page_number, $model, $results_url ) ); ?>" data-page="<?php echo esc_attr( $page_number ); ?>"><?php echo esc_html( $page_number ); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>
		<?php if ( $page < $max_pages ) : ?>
			<a href="<?php echo esc_url( aomark_listings_results_page_url( $page + 1, $model, $results_url ) ); ?>" data-page="<?php echo esc_attr( $page + 1 ); ?>"><?php esc_html_e( 'Next', 'aomark-listings' ); ?></a>
		<?php else : ?>
			<span class="is-disabled" aria-disabled="true"><?php esc_html_e( 'Next', 'aomark-listings' ); ?></span>
		<?php endif; ?>
	</nav>
	<?php
	return ob_get_clean();
}

function aomark_listings_render_results_inner( $settings = [], $page = 1 ) {
	$settings = aomark_listings_sanitize_results_settings( $settings );
	$model    = aomark_listings_get_model_exact( $settings['model_id'], false );
	$page     = max( 1, min( 200, absint( $page ) ) );

	if ( ! $model ) {
		return [
			'html'      => '<div class="aomark-listings-empty">' . esc_html__( 'The selected listing type is unavailable.', 'aomark-listings' ) . '</div>',
			'total'     => 0,
			'page'      => $page,
			'max_pages' => 0,
			'model'     => null,
		];
	}

	$data  = aomark_listings_get_query( $settings, $page );
	$query = $data['query'];
	$max_pages = min( 200, (int) $query->max_num_pages );
	if ( $max_pages > 0 && $page > $max_pages ) {
		$page  = $max_pages;
		$data  = aomark_listings_get_query( $settings, $page );
		$query = $data['query'];
	}
	$had_global_post      = array_key_exists( 'post', $GLOBALS );
	$original_global_post = $had_global_post ? $GLOBALS['post'] : null;

	ob_start();
	?>
	<?php if ( 'yes' === $settings['show_filter'] ) : ?>
		<div class="aomark-listings-results__filter">
			<?php echo aomark_listings_render_filter( [ 'model_id' => $model['id'], 'results_url' => $settings['results_url'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>

		<?php if ( 'yes' === $settings['show_count'] || 'yes' === $settings['show_sort'] ) : ?>
			<div class="aomark-listings-results__bar">
				<?php if ( 'yes' === $settings['show_count'] ) : ?>
					<div class="aomark-listings-results__count"><?php echo esc_html( sprintf( _n( '%s listing', '%s listings', (int) $query->found_posts, 'aomark-listings' ), number_format_i18n( (int) $query->found_posts ) ) ); ?></div>
				<?php endif; ?>
				<?php if ( 'yes' === $settings['show_sort'] ) : ?>
					<form class="aomark-listings-results__sort" action="<?php echo esc_url( $settings['results_url'] ); ?>" method="get">
						<?php echo aomark_listings_render_route_query_inputs( $settings['results_url'], $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo aomark_listings_render_active_filter_inputs( [ 'alm_sort', 'alm_page' ], $model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<label>
							<span><?php esc_html_e( 'Sort', 'aomark-listings' ); ?></span>
							<select name="alm_sort" data-aomark-listings-sort>
							<option value="date_desc" <?php selected( $data['sort'], 'date_desc' ); ?>><?php esc_html_e( 'Newest', 'aomark-listings' ); ?></option>
							<option value="date_asc" <?php selected( $data['sort'], 'date_asc' ); ?>><?php esc_html_e( 'Oldest', 'aomark-listings' ); ?></option>
							<option value="title_asc" <?php selected( $data['sort'], 'title_asc' ); ?>><?php esc_html_e( 'Title A-Z', 'aomark-listings' ); ?></option>
							<option value="title_desc" <?php selected( $data['sort'], 'title_desc' ); ?>><?php esc_html_e( 'Title Z-A', 'aomark-listings' ); ?></option>
							<?php if ( aomark_listings_get_first_field_by_type( $model, 'price' ) ) : ?>
								<option value="price_asc" <?php selected( $data['sort'], 'price_asc' ); ?>><?php esc_html_e( 'Price Low to High', 'aomark-listings' ); ?></option>
								<option value="price_desc" <?php selected( $data['sort'], 'price_desc' ); ?>><?php esc_html_e( 'Price High to Low', 'aomark-listings' ); ?></option>
							<?php endif; ?>
							</select>
						</label>
						<noscript><button type="submit"><?php esc_html_e( 'Apply sort', 'aomark-listings' ); ?></button></noscript>
					</form>
				<?php endif; ?>
			</div>
	<?php endif; ?>

	<?php if ( ! $query->have_posts() ) : ?>
		<div class="aomark-listings-empty"><?php echo esc_html( $settings['empty_message'] ); ?></div>
	<?php else : ?>
		<div class="aomark-listings-grid">
			<?php while ( $query->have_posts() ) : ?>
				<?php $query->the_post(); ?>
				<?php echo aomark_listings_render_card( get_the_ID(), $model, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endwhile; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'numbered' === $settings['pagination'] ) : ?>
		<?php echo aomark_listings_render_pagination( $page, (int) $query->max_num_pages, $model, $settings['results_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php elseif ( 'load_more' === $settings['pagination'] && $page < min( 200, (int) $query->max_num_pages ) ) : ?>
		<div class="aomark-listings-results__actions">
			<a href="<?php echo esc_url( aomark_listings_results_page_url( $page + 1, $model, $settings['results_url'] ) ); ?>" class="aomark-listings-load-more" data-page="<?php echo esc_attr( $page + 1 ); ?>"><?php echo esc_html( $settings['load_more_text'] ); ?></a>
		</div>
	<?php endif; ?>
	<?php
	if ( $had_global_post ) {
		$GLOBALS['post'] = $original_global_post;
		if ( $original_global_post instanceof WP_Post ) {
			setup_postdata( $original_global_post );
		}
	} else {
		unset( $GLOBALS['post'] );
	}

	return [
		'html'      => ob_get_clean(),
		'total'     => (int) $query->found_posts,
		'page'      => $page,
		'max_pages' => min( 200, (int) $query->max_num_pages ),
		'model'     => $model,
	];
}

function aomark_listings_render_results( $settings = [] ) {
	aomark_listings_enqueue_assets();

	$settings = aomark_listings_sanitize_results_settings( $settings );
	$model    = aomark_listings_get_model_exact( $settings['model_id'], false );
	if ( ! $model ) {
		return '<div class="aomark-listings-empty">' . esc_html__( 'The selected listing type is unavailable.', 'aomark-listings' ) . '</div>';
	}

	$settings['model_id'] = $model['id'];
	if ( '' === $settings['results_url'] ) {
		$settings['results_url'] = aomark_listings_default_results_url( $model );
	}
	$page = aomark_listings_request_targets_model( $model ) ? absint( aomark_listings_request_value( 'alm_page', 4 ) ) : 1;
	$page = max( 1, min( 200, $page ) );
	$rendered   = aomark_listings_render_results_inner( $settings, $page );
	$descriptor = aomark_listings_create_signed_descriptor( 'results', $settings );
	$id         = 'aomark-listings-results-' . wp_unique_id();
	$map_sync   = ! empty( $settings['taxonomy_filters'] ) || ! empty( $settings['meta_filters'] ) || 'date_desc' !== $settings['sort'];

	ob_start();
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="aomark-listings-results aomark-listings-results--<?php echo esc_attr( $settings['skin'] ); ?>" data-model-id="<?php echo esc_attr( $model['id'] ); ?>" data-ajax="<?php echo esc_attr( $settings['ajax'] ); ?>" data-map-sync="<?php echo $map_sync ? 'yes' : 'no'; ?>" data-config="<?php echo esc_attr( wp_json_encode( $descriptor ) ); ?>">
		<div data-aomark-listings-results-inner role="region" aria-label="<?php esc_attr_e( 'Listing results', 'aomark-listings' ); ?>" tabindex="-1">
			<?php echo $rendered['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Resolve the listing used by single-item widgets.
 *
 * An explicit listing ID is useful for standalone pages and editor previews;
 * otherwise widgets keep the normal current-post template behavior.
 *
 * @param array      $settings Widget settings.
 * @param array|null $model    Resolved listing model.
 * @return int
 */
function aomark_listings_context_post_id( $settings = [], $model = null ) {
	if ( ! $model ) {
		$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
		$model    = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	}

	if ( ! $model ) {
		return 0;
	}

	$post_id = absint( $settings['post_id'] ?? 0 );
	$post_id = $post_id ?: absint( get_the_ID() );
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( ! $post || $model['post_type'] !== $post->post_type ) {
		return 0;
	}

	if ( 'publish' === $post->post_status ) {
		if ( post_password_required( $post ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return 0;
		}

		return $post_id;
	}

	if ( current_user_can( 'read_post', $post_id ) || current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	return 0;
}

/**
 * Resolve a possibly composite field reference against exactly one model.
 *
 * @param array  $model     Listing model.
 * @param string $reference Field ID or model:field reference.
 * @return array|null
 */
function aomark_listings_resolve_model_field( $model, $reference ) {
	$reference = sanitize_text_field( (string) $reference );
	if ( false !== strpos( $reference, ':' ) ) {
		$parts = explode( ':', $reference, 2 );
		if ( sanitize_key( $parts[0] ) !== $model['id'] ) {
			return null;
		}
		$reference = $parts[1];
	}

	return aomark_listings_get_field( $model, sanitize_key( $reference ) );
}

function aomark_listings_render_field( $settings = [] ) {
	$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
	$model = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	$field = $model ? aomark_listings_resolve_model_field( $model, $settings['field_id'] ?? '' ) : null;
	$post_id = aomark_listings_context_post_id( $settings, $model );

	if ( ! $post_id || ! $field ) {
		return '';
	}

	$value = aomark_listings_field_display_value( $post_id, $field, $settings );
	if ( '' === $value ) {
		$value = sanitize_text_field( $settings['fallback'] ?? '' );
	}
	if ( '' === $value ) {
		return '';
	}

	$tag            = in_array( (string) ( $settings['html_tag'] ?? 'div' ), [ 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? (string) $settings['html_tag'] : 'div';
	$show_label     = 'yes' === aomark_listings_switch_setting( $settings, 'show_label', 'no' );
	$label_position = in_array( (string) ( $settings['label_position'] ?? 'inline' ), [ 'inline', 'stacked' ], true ) ? (string) $settings['label_position'] : 'inline';
	$label          = sanitize_text_field( $settings['custom_label'] ?? '' );
	$label          = '' !== $label ? $label : $field['label'];
	$prefix         = sanitize_text_field( $settings['prefix'] ?? '' );
	$suffix         = sanitize_text_field( $settings['suffix'] ?? '' );
	$link_to        = (string) ( $settings['link_to'] ?? 'none' );
	$link_url       = '';

	if ( 'listing' === $link_to ) {
		$link_url = get_permalink( $post_id );
	} elseif ( 'field' === $link_to ) {
		$raw_value = (string) get_post_meta( $post_id, aomark_listings_meta_key( $field ), true );
		if ( 'email' === $field['type'] && is_email( $raw_value ) ) {
			$link_url = 'mailto:' . sanitize_email( $raw_value );
		} elseif ( 'url' === $field['type'] ) {
			$link_url = esc_url_raw( $raw_value );
		}
	} elseif ( 'custom' === $link_to ) {
		$link_url = esc_url_raw( $settings['custom_url'] ?? '' );
	}

	ob_start();
	printf(
		'<%1$s class="aomark-listings-field aomark-listings-field--%2$s aomark-listings-field--%3$s">',
		tag_escape( $tag ),
		esc_attr( $field['id'] ),
		esc_attr( $label_position )
	);
	if ( $show_label ) {
		printf( '<span class="aomark-listings-field__label">%s</span>', esc_html( $label ) );
	}
	echo '<span class="aomark-listings-field__value">';
	if ( '' !== $prefix ) {
		printf( '<span class="aomark-listings-field__prefix">%s</span>', esc_html( $prefix ) );
	}
	if ( $link_url ) {
		printf( '<a href="%1$s">%2$s</a>', esc_url( $link_url ), esc_html( $value ) );
	} else {
		echo esc_html( $value );
	}
	if ( '' !== $suffix ) {
		printf( '<span class="aomark-listings-field__suffix">%s</span>', esc_html( $suffix ) );
	}
	echo '</span>';
	printf( '</%s>', tag_escape( $tag ) );

	return ob_get_clean();
}

function aomark_listings_render_meta( $settings = [] ) {
	$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
	$model = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	$post_id = aomark_listings_context_post_id( $settings, $model );

	if ( ! $model || ! $post_id ) {
		return '';
	}

	$field_ids = aomark_listings_normalize_selected_ids( $settings['field_ids'] ?? [], $model['id'] );
	if ( empty( $field_ids ) ) {
		$field_ids = wp_list_pluck(
			array_filter(
				$model['fields'],
				function ( $field ) {
					return ! empty( $field['card'] );
				}
			),
			'id'
		);
	}

	$show_labels  = 'yes' === aomark_listings_switch_setting( $settings, 'show_labels', 'yes' );
	$label_suffix = sanitize_text_field( $settings['label_suffix'] ?? '' );

	ob_start();
	echo '<div class="aomark-listings-meta">';
	foreach ( $field_ids as $field_id ) {
		$field = aomark_listings_get_field( $model, $field_id );
		if ( ! $field ) {
			continue;
		}
		$value = aomark_listings_field_display_value( $post_id, $field, $settings );
		if ( '' === $value ) {
			continue;
		}
		echo '<div class="aomark-listings-meta__row">';
		if ( $show_labels ) {
			printf( '<span class="aomark-listings-meta__label">%s</span>', esc_html( $field['label'] . $label_suffix ) );
		}
		printf( '<strong class="aomark-listings-meta__value">%s</strong>', esc_html( $value ) );
		echo '</div>';
	}
	echo '</div>';
	return ob_get_clean();
}

function aomark_listings_render_gallery( $settings = [] ) {
	aomark_listings_enqueue_assets();

	$model_id = sanitize_key( (string) ( $settings['model_id'] ?? '' ) );
	$model = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	$field = $model ? aomark_listings_resolve_model_field( $model, $settings['field_id'] ?? '' ) : null;
	$post_id = aomark_listings_context_post_id( $settings, $model );

	if ( ! $post_id || ! $field || ! in_array( $field['type'], [ 'image', 'gallery' ], true ) ) {
		return '';
	}

	$ids = 'image' === $field['type'] ? [ absint( get_post_meta( $post_id, aomark_listings_meta_key( $field ), true ) ) ] : aomark_listings_gallery_ids( $post_id, $field );
	$ids = array_values( array_filter( $ids ) );
	$max_images = absint( $settings['max_images'] ?? 0 );
	$max_images = $max_images > 0 ? min( 100, $max_images ) : 100;
	$ids        = array_slice( $ids, 0, $max_images );
	if ( empty( $ids ) ) {
		return '';
	}

	ob_start();
	echo '<div class="aomark-listings-gallery">';
	$image_total = count( $ids );
	foreach ( $ids as $image_index => $image_id ) {
		$image_size = sanitize_key( $settings['image_size'] ?? 'medium_large' );
		$thumb = wp_get_attachment_image_url( $image_id, $image_size );
		$full  = wp_get_attachment_image_url( $image_id, 'full' );
		if ( ! $thumb ) {
			continue;
		}
		$image = wp_get_attachment_image( $image_id, $image_size );
		if ( 'none' === ( $settings['link_to'] ?? 'media' ) ) {
			printf( '<span class="aomark-listings-gallery__item">%s</span>', $image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			$lightbox = 'yes' === aomark_listings_switch_setting( $settings, 'lightbox', 'yes' ) ? 'yes' : 'no';
			$attachment_title = trim( (string) get_the_title( $image_id ) );
			$label_context    = '' !== $attachment_title ? $attachment_title : get_the_title( $post_id );
			/* translators: 1: image number, 2: total images, 3: image or listing title. */
			$link_label = sprintf( __( 'Open image %1$d of %2$d: %3$s', 'aomark-listings' ), $image_index + 1, $image_total, $label_context );
			printf( '<a href="%1$s" class="aomark-listings-gallery__item" data-elementor-open-lightbox="%2$s" aria-label="%3$s">%4$s</a>', esc_url( $full ?: $thumb ), esc_attr( $lightbox ), esc_attr( $link_label ), $image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
	echo '</div>';
	return ob_get_clean();
}

function aomark_listings_render_map( $settings = [] ) {
	aomark_listings_enqueue_map_assets();

	$settings = wp_parse_args(
		(array) $settings,
		[
			'model_id'         => '',
			'source'           => 'query',
			'height'           => 480,
			'zoom'             => 10,
			'read_url_filters' => 'yes',
			'map_limit'        => 200,
			'empty_message'    => __( 'No mapped listings found.', 'aomark-listings' ),
			'fit_bounds'       => 'yes',
			'scroll_wheel_zoom' => 'no',
			'zoom_control'     => 'yes',
			'dragging'         => 'yes',
			'popup_image'      => 'yes',
			'popup_price'      => 'yes',
			'popup_address'    => 'yes',
		]
	);
	$model_id = sanitize_key( (string) $settings['model_id'] );
	$model    = aomark_listings_get_model_exact( $model_id, '' === $model_id );
	$source   = in_array( (string) $settings['source'], [ 'query', 'current', 'single' ], true ) ? (string) $settings['source'] : 'query';
	$settings['model_id']         = $model ? $model['id'] : $model_id;
	$settings['source']           = $source;
	$settings['read_url_filters'] = aomark_listings_switch_setting( $settings, 'read_url_filters', 'yes' );
	$settings['map_limit']        = max( 1, min( 200, absint( $settings['map_limit'] ) ) );
	$settings['currency']         = substr( sanitize_text_field( (string) ( $settings['currency'] ?? '$' ) ), 0, 12 );
	$settings['decimals']         = min( 4, absint( $settings['decimals'] ?? 0 ) );
	$settings['popup_image']      = aomark_listings_switch_setting( $settings, 'popup_image', 'yes' );
	$settings['popup_price']      = aomark_listings_switch_setting( $settings, 'popup_price', 'yes' );
	$settings['popup_address']    = aomark_listings_switch_setting( $settings, 'popup_address', 'yes' );
	$items = [];

	if ( $model && in_array( $source, [ 'current', 'single' ], true ) ) {
		$post_id = aomark_listings_context_post_id( $settings, $model );
		$location = $post_id ? aomark_listings_location_value( $post_id, $model ) : false;
		if ( $location ) {
			$price_field = aomark_listings_get_first_field_by_type( $model, 'price' );
			$item = [
				'id'      => $post_id,
				'title'   => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'url'     => get_permalink( $post_id ),
				'lat'     => $location['lat'],
				'lng'     => $location['lng'],
			];

			if ( 'yes' === $settings['popup_image'] ) {
				$item['image'] = get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '';
			}
			if ( 'yes' === $settings['popup_address'] ) {
				$item['address'] = $location['address'];
			}
			if ( 'yes' === $settings['popup_price'] ) {
				$item['price'] = $price_field ? aomark_listings_field_display_value( $post_id, $price_field, $settings ) : '';
			}

			$items[] = $item;
		}
	} elseif ( $model ) {
		$items = aomark_listings_get_map_items( $settings );
	}

	$map_id = 'aomark-listings-map-' . wp_unique_id();
	if ( is_array( $settings['height'] ) && isset( $settings['height']['size'], $settings['height']['unit'] ) ) {
		$height_size = max( 120, min( 1600, absint( $settings['height']['size'] ) ) );
		$height_unit = in_array( $settings['height']['unit'], [ 'px', 'vh' ], true ) ? $settings['height']['unit'] : 'px';
		$height      = $height_size . $height_unit;
	} else {
		$height = max( 120, min( 1600, absint( $settings['height'] ) ) ) . 'px';
	}

	$query_map = 'query' === $source;
	$ajax_descriptor = null;
	if ( $query_map && $model ) {
		$ajax_descriptor = aomark_listings_create_signed_descriptor(
			'map',
			[
				'model_id'         => $model['id'],
				'read_url_filters' => $settings['read_url_filters'],
				'map_limit'        => $settings['map_limit'],
				'popup_image'      => $settings['popup_image'],
				'popup_price'      => $settings['popup_price'],
				'popup_address'    => $settings['popup_address'],
				'currency'         => $settings['currency'],
				'decimals'         => $settings['decimals'],
			]
		);
	}

	$config = [
		'id'              => $map_id,
		'items'           => $items,
		'zoom'            => max( 2, min( 18, absint( $settings['zoom'] ) ) ),
		'queryMap'        => $query_map,
		'fitBounds'       => 'yes' === aomark_listings_switch_setting( $settings, 'fit_bounds', 'yes' ),
		'scrollWheelZoom' => 'yes' === aomark_listings_switch_setting( $settings, 'scroll_wheel_zoom', 'no' ),
		'zoomControl'     => 'yes' === aomark_listings_switch_setting( $settings, 'zoom_control', 'yes' ),
		'dragging'        => 'yes' === aomark_listings_switch_setting( $settings, 'dragging', 'yes' ),
		'popupImage'      => 'yes' === $settings['popup_image'],
		'popupPrice'      => 'yes' === $settings['popup_price'],
		'popupAddress'    => 'yes' === $settings['popup_address'],
	];
	if ( $ajax_descriptor ) {
		$config['ajaxDescriptor'] = $ajax_descriptor;
	}

	$empty_message = substr( sanitize_text_field( (string) $settings['empty_message'] ), 0, 300 );

	ob_start();
	?>
	<div class="aomark-listings-map-wrap<?php echo empty( $items ) ? ' is-empty' : ''; ?>">
		<div id="<?php echo esc_attr( $map_id ); ?>" class="aomark-listings-map" style="--alm-map-height:<?php echo esc_attr( $height ); ?>" data-model-id="<?php echo esc_attr( $model ? $model['id'] : $model_id ); ?>" data-query-map="<?php echo $query_map ? 'yes' : 'no'; ?>" data-map-query="<?php echo esc_attr( $ajax_descriptor ? wp_json_encode( $ajax_descriptor ) : '' ); ?>" data-map-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" aria-label="<?php esc_attr_e( 'Listings map', 'aomark-listings' ); ?>"<?php echo empty( $items ) ? ' hidden aria-hidden="true"' : ''; ?>></div>
		<div class="aomark-listings-empty" data-aomark-listings-map-empty<?php echo empty( $items ) ? '' : ' hidden'; ?>><?php echo esc_html( $empty_message ); ?></div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Parse only public filter keys declared by the selected model.
 *
 * @param string $raw   URL-encoded filter string.
 * @param array  $model Listing model.
 * @return array
 */
function aomark_listings_sanitize_ajax_filters( $raw, $model ) {
	$parsed = [];
	parse_str( (string) $raw, $parsed );
	$filters = [];
	$active  = 0;
	$scalar  = static function ( $value, $max_length = 200 ) {
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return substr( sanitize_text_field( (string) $value ), 0, $max_length );
	};
	$request_model = $scalar( $parsed['alm_model'] ?? '', 100 );
	if ( '' !== $request_model && $request_model !== $model['id'] ) {
		return [];
	}

	$keyword = $scalar( $parsed['alm_keyword'] ?? '', 200 );
	if ( '' !== $keyword ) {
		$filters['alm_keyword'] = $keyword;
		++$active;
	}

	$sort = sanitize_key( $scalar( $parsed['alm_sort'] ?? '', 20 ) );
	if ( in_array( $sort, [ 'date_desc', 'date_asc', 'title_asc', 'title_desc', 'price_asc', 'price_desc' ], true ) ) {
		$filters['alm_sort'] = $sort;
	}

	foreach ( aomark_listings_get_filterable_taxonomies( $model ) as $taxonomy ) {
		if ( $active >= 20 ) {
			break;
		}

		$key    = aomark_listings_filter_param( $taxonomy['id'] );
		$values = $parsed[ $key ] ?? [];
		$values = is_array( $values ) ? array_slice( $values, 0, 20 ) : [ $values ];
		$terms  = [];
		foreach ( $values as $value ) {
			$value = sanitize_title( $scalar( $value, 100 ) );
			if ( '' !== $value ) {
				$terms[] = $value;
			}
		}
		$terms = array_values( array_unique( $terms ) );
		if ( ! empty( $terms ) ) {
			$filters[ $key ] = $terms;
			++$active;
		}
	}

	foreach ( aomark_listings_get_filterable_fields( $model ) as $field ) {
		if ( $active >= 20 ) {
			break;
		}

		if ( in_array( $field['type'], [ 'number', 'price' ], true ) ) {
			foreach ( [ 'alm_min_' . $field['id'], 'alm_max_' . $field['id'] ] as $key ) {
				$value = $scalar( $parsed[ $key ] ?? '', 50 );
				if ( '' !== $value && null !== aomark_listings_numeric_value( $value ) ) {
					$filters[ $key ] = $value;
					++$active;
				}
				if ( $active >= 20 ) {
					break 2;
				}
			}
		} else {
			$key   = aomark_listings_filter_param( 'field_' . $field['id'] );
			$value = $scalar( $parsed[ $key ] ?? '', 200 );
			if ( '' !== $value ) {
				$filters[ $key ] = $value;
				++$active;
			}
		}
	}

	return $filters;
}

function aomark_listings_ajax_results() {
	$nonce = isset( $_POST['nonce'] ) && is_scalar( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'aomark_listings_results' ) ) {
		wp_send_json_error( [ 'message' => __( 'The listings request could not be verified.', 'aomark-listings' ) ], 403 );
	}

	$page = isset( $_POST['page'] ) && is_scalar( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
	$page = max( 1, min( 200, $page ) );
	$settings_raw = isset( $_POST['settings'] ) && is_scalar( $_POST['settings'] ) ? (string) wp_unslash( $_POST['settings'] ) : '';
	if ( '' === $settings_raw || strlen( $settings_raw ) > 45000 ) {
		wp_send_json_error( [ 'message' => __( 'Invalid listings configuration.', 'aomark-listings' ) ], 400 );
	}

	$descriptor = json_decode( $settings_raw, true, 10 );
	$payload    = aomark_listings_verify_signed_descriptor( $descriptor, 'results' );
	if ( false === $payload ) {
		wp_send_json_error( [ 'message' => __( 'Invalid or expired listings configuration.', 'aomark-listings' ) ], 400 );
	}

	$settings = aomark_listings_sanitize_results_settings( $payload );
	$model    = aomark_listings_get_model_exact( $settings['model_id'], false );
	if ( ! $model ) {
		wp_send_json_error( [ 'message' => __( 'The selected listing type is unavailable.', 'aomark-listings' ) ], 400 );
	}

	$filters_raw = isset( $_POST['filters'] ) && is_scalar( $_POST['filters'] ) ? (string) wp_unslash( $_POST['filters'] ) : '';
	if ( strlen( $filters_raw ) > 8192 ) {
		wp_send_json_error( [ 'message' => __( 'Too many listing filters were supplied.', 'aomark-listings' ) ], 400 );
	}
	$filters = aomark_listings_sanitize_ajax_filters( $filters_raw, $model );
	$map_only = isset( $_POST['map_only'] ) && is_scalar( $_POST['map_only'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['map_only'] ) );

	$sort = isset( $_POST['sort'] ) && is_scalar( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : '';
	if ( in_array( $sort, [ 'date_desc', 'date_asc', 'title_asc', 'title_desc', 'price_asc', 'price_desc' ], true ) ) {
		$settings['sort']    = $sort;
		$filters['alm_sort'] = $sort;
	}

	$old_get = $_GET;
	$_GET    = $filters;
	try {
		$rendered = null;
		if ( ! $map_only ) {
			$rendered = aomark_listings_render_results_inner( $settings, $page );
		}

		$map_items = [];
		$map_valid = false;
		$map_raw   = isset( $_POST['map_descriptor'] ) && is_scalar( $_POST['map_descriptor'] ) ? (string) wp_unslash( $_POST['map_descriptor'] ) : '';
		if ( '' !== $map_raw && strlen( $map_raw ) <= 45000 ) {
			$map_descriptor = json_decode( $map_raw, true, 10 );
			$map_payload    = aomark_listings_verify_signed_descriptor( $map_descriptor, 'map' );
			$map_model_id   = is_array( $map_payload ) ? sanitize_key( (string) ( $map_payload['model_id'] ?? '' ) ) : '';
			if ( $model['id'] === $map_model_id ) {
				$map_valid = true;
				$map_settings = array_merge(
					$settings,
					[
						'read_url_filters' => 'yes' === ( $map_payload['read_url_filters'] ?? 'yes' ) ? 'yes' : 'no',
						'map_limit'        => max( 1, min( 200, absint( $map_payload['map_limit'] ?? 200 ) ) ),
						'popup_image'      => 'yes' === ( $map_payload['popup_image'] ?? 'yes' ) ? 'yes' : 'no',
						'popup_price'      => 'yes' === ( $map_payload['popup_price'] ?? 'yes' ) ? 'yes' : 'no',
						'popup_address'    => 'yes' === ( $map_payload['popup_address'] ?? 'yes' ) ? 'yes' : 'no',
						'currency'         => substr( sanitize_text_field( (string) ( $map_payload['currency'] ?? '$' ) ), 0, 12 ),
						'decimals'         => min( 4, absint( $map_payload['decimals'] ?? 0 ) ),
					]
				);
				$map_items = aomark_listings_get_map_items( $map_settings );
			}
		}

		if ( $map_only && ! $map_valid ) {
			wp_send_json_error( [ 'message' => __( 'Invalid map configuration.', 'aomark-listings' ) ], 400 );
		}
	} finally {
		$_GET = $old_get;
	}

	wp_send_json_success(
		[
			'html'      => $rendered ? $rendered['html'] : '',
			'total'     => $rendered ? $rendered['total'] : 0,
			'page'      => $rendered ? $rendered['page'] : $page,
			'max_pages' => $rendered ? $rendered['max_pages'] : 0,
			'mapItems'  => $map_items,
		]
	);
}
add_action( 'wp_ajax_aomark_listings_results', 'aomark_listings_ajax_results' );
add_action( 'wp_ajax_nopriv_aomark_listings_results', 'aomark_listings_ajax_results' );
