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

	return [
		'model_id'         => sanitize_key( $settings['model_id'] ?? '' ),
		'per_page'         => max( 1, min( 60, absint( $settings['per_page'] ?? 9 ) ) ),
		'read_url_filters' => aomark_listings_switch_setting( $settings, 'read_url_filters', 'yes' ),
		'ajax'             => aomark_listings_switch_setting( $settings, 'ajax', 'yes' ),
		'pagination'       => in_array( (string) ( $settings['pagination'] ?? 'numbered' ), [ 'numbered', 'load_more', 'none' ], true ) ? (string) $settings['pagination'] : 'numbered',
		'skin'             => in_array( (string) ( $settings['skin'] ?? 'cards' ), [ 'cards', 'list' ], true ) ? (string) $settings['skin'] : 'cards',
		'columns'          => max( 1, min( 6, absint( $settings['columns'] ?? 3 ) ) ),
		'show_filter'      => aomark_listings_switch_setting( $settings, 'show_filter', 'no' ),
		'show_count'       => aomark_listings_switch_setting( $settings, 'show_count', 'yes' ),
		'show_sort'        => aomark_listings_switch_setting( $settings, 'show_sort', 'yes' ),
		'show_image'       => aomark_listings_switch_setting( $settings, 'show_image', 'yes' ),
		'show_title'       => aomark_listings_switch_setting( $settings, 'show_title', 'yes' ),
		'show_price'       => aomark_listings_switch_setting( $settings, 'show_price', 'yes' ),
		'show_meta'        => aomark_listings_switch_setting( $settings, 'show_meta', 'yes' ),
		'show_button'      => aomark_listings_switch_setting( $settings, 'show_button', 'yes' ),
		'sort'             => sanitize_key( $settings['sort'] ?? 'date_desc' ),
		'image_size'       => sanitize_key( $settings['image_size'] ?? 'medium_large' ),
		'title_tag'        => in_array( (string) ( $settings['title_tag'] ?? 'h3' ), [ 'h2', 'h3', 'h4', 'h5', 'div' ], true ) ? (string) $settings['title_tag'] : 'h3',
		'button_text'      => sanitize_text_field( $settings['button_text'] ?? __( 'View Details', 'aomark-listings' ) ),
		'empty_message'    => sanitize_text_field( $settings['empty_message'] ?? __( 'No listings found.', 'aomark-listings' ) ),
		'load_more_text'   => sanitize_text_field( $settings['load_more_text'] ?? __( 'Load More', 'aomark-listings' ) ),
		'currency'         => sanitize_text_field( $settings['currency'] ?? '$' ),
		'decimals'         => min( 4, absint( $settings['decimals'] ?? 0 ) ),
		'taxonomy_filters' => isset( $settings['taxonomy_filters'] ) && is_array( $settings['taxonomy_filters'] ) ? $settings['taxonomy_filters'] : [],
		'meta_filters'     => isset( $settings['meta_filters'] ) && is_array( $settings['meta_filters'] ) ? $settings['meta_filters'] : [],
		'card_field_ids'   => $settings['card_field_ids'] ?? [],
	];
}

function aomark_listings_switch_setting( $settings, $key, $default = 'yes' ) {
	if ( is_array( $settings ) && array_key_exists( $key, $settings ) ) {
		return 'yes' === $settings[ $key ] ? 'yes' : 'no';
	}

	return 'yes' === $default ? 'yes' : 'no';
}

function aomark_listings_render_filter( $settings = [] ) {
	aomark_listings_enqueue_assets();

	$model = aomark_listings_get_model( $settings['model_id'] ?? '' );
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

	$results_url = $settings['results_url'] ? $settings['results_url'] : get_permalink();
	$results_url = $results_url ? $results_url : home_url( '/' );

	ob_start();
	?>
	<div class="aomark-listings-filter" data-model-id="<?php echo esc_attr( $model['id'] ); ?>">
		<form class="aomark-listings-filter__form" action="<?php echo esc_url( $results_url ); ?>" method="get">
			<input type="hidden" name="alm_model" value="<?php echo esc_attr( $model['id'] ); ?>">

			<?php if ( 'yes' === aomark_listings_switch_setting( $settings, 'show_keyword', 'yes' ) ) : ?>
				<div class="aomark-listings-filter__field aomark-listings-filter__field--keyword">
					<input type="search" name="alm_keyword" value="<?php echo esc_attr( aomark_listings_request_value( 'alm_keyword' ) ); ?>" placeholder="<?php echo esc_attr( $settings['keyword_placeholder'] ); ?>" aria-label="<?php echo esc_attr( $settings['keyword_placeholder'] ); ?>">
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
					<?php $selected = aomark_listings_request_value( aomark_listings_filter_param( $taxonomy['id'] ) ); ?>
					<?php $terms = get_terms( [ 'taxonomy' => $taxonomy['slug'], 'hide_empty' => false ] ); ?>
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
							<input type="number" step="any" name="<?php echo esc_attr( 'alm_min_' . $field['id'] ); ?>" value="<?php echo esc_attr( aomark_listings_request_value( 'alm_min_' . $field['id'] ) ); ?>" placeholder="<?php echo esc_attr( sprintf( __( 'Min %s', 'aomark-listings' ), $field['label'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Minimum %s', 'aomark-listings' ), $field['label'] ) ); ?>">
						</div>
						<div class="aomark-listings-filter__field aomark-listings-filter__field--max">
							<input type="number" step="any" name="<?php echo esc_attr( 'alm_max_' . $field['id'] ); ?>" value="<?php echo esc_attr( aomark_listings_request_value( 'alm_max_' . $field['id'] ) ); ?>" placeholder="<?php echo esc_attr( sprintf( __( 'Max %s', 'aomark-listings' ), $field['label'] ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Maximum %s', 'aomark-listings' ), $field['label'] ) ); ?>">
						</div>
					<?php elseif ( 'select' === $field['type'] ) : ?>
						<?php $selected = aomark_listings_request_value( aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>
						<div class="aomark-listings-filter__field">
							<select name="<?php echo esc_attr( aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
								<option value=""><?php echo esc_html( $field['label'] ); ?></option>
								<?php foreach ( (array) $field['options'] as $option ) : ?>
									<option value="<?php echo esc_attr( $option['value'] ); ?>" <?php selected( $selected, $option['value'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php else : ?>
						<div class="aomark-listings-filter__field">
							<input type="text" name="<?php echo esc_attr( aomark_listings_filter_param( 'field_' . $field['id'] ) ); ?>" value="<?php echo esc_attr( aomark_listings_request_value( aomark_listings_filter_param( 'field_' . $field['id'] ) ) ); ?>" placeholder="<?php echo esc_attr( $field['label'] ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>">
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>

			<div class="aomark-listings-filter__field aomark-listings-filter__field--button">
				<button type="submit" class="aomark-listings-button"><?php echo esc_html( $settings['button_text'] ); ?></button>
			</div>
			<?php if ( 'yes' === aomark_listings_switch_setting( $settings, 'show_reset', 'no' ) ) : ?>
				<div class="aomark-listings-filter__field aomark-listings-filter__field--reset">
					<button type="button" class="aomark-listings-reset"><?php echo esc_html( $settings['reset_text'] ); ?></button>
				</div>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

function aomark_listings_render_card( $post_id, $model, $settings ) {
	$title = get_the_title( $post_id );
	$url   = get_permalink( $post_id );
	$image_id = get_post_thumbnail_id( $post_id );
	$image    = $image_id ? wp_get_attachment_image(
		$image_id,
		$settings['image_size'] ?? 'medium_large',
		false,
		[ 'alt' => $title ]
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
	<article class="aomark-listings-card<?php echo 'yes' === $settings['show_image'] ? '' : ' aomark-listings-card--no-image'; ?>">
		<?php if ( 'yes' === $settings['show_image'] ) : ?>
			<a class="aomark-listings-card__image" href="<?php echo esc_url( $url ); ?>">
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
				<a class="aomark-listings-card__button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $settings['button_text'] ); ?></a>
			<?php endif; ?>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

function aomark_listings_render_pagination( $page, $max_pages ) {
	$page      = max( 1, absint( $page ) );
	$max_pages = max( 1, absint( $max_pages ) );

	if ( $max_pages <= 1 ) {
		return '';
	}

	$pages = array_unique( array_filter( [ 1, $page - 1, $page, $page + 1, $max_pages ] ) );
	sort( $pages );

	ob_start();
	?>
	<nav class="aomark-listings-pagination" aria-label="<?php esc_attr_e( 'Listings pagination', 'aomark-listings' ); ?>">
		<button type="button" data-page="<?php echo esc_attr( max( 1, $page - 1 ) ); ?>" <?php disabled( $page <= 1 ); ?>><?php esc_html_e( 'Previous', 'aomark-listings' ); ?></button>
		<?php foreach ( $pages as $page_number ) : ?>
				<button type="button" class="<?php echo $page_number === $page ? 'is-active' : ''; ?>" data-page="<?php echo esc_attr( $page_number ); ?>" <?php echo $page_number === $page ? 'aria-current="page"' : ''; ?> <?php disabled( $page_number === $page ); ?>><?php echo esc_html( $page_number ); ?></button>
		<?php endforeach; ?>
		<button type="button" data-page="<?php echo esc_attr( min( $max_pages, $page + 1 ) ); ?>" <?php disabled( $page >= $max_pages ); ?>><?php esc_html_e( 'Next', 'aomark-listings' ); ?></button>
	</nav>
	<?php
	return ob_get_clean();
}

function aomark_listings_render_results_inner( $settings = [], $page = 1 ) {
	$settings = aomark_listings_sanitize_results_settings( $settings );
	$data     = aomark_listings_get_query( $settings, $page );
	$model    = $data['model'];
	$query    = $data['query'];
	$page     = max( 1, absint( $page ) );

	ob_start();
	?>
	<?php if ( 'yes' === $settings['show_filter'] ) : ?>
		<div class="aomark-listings-results__filter">
			<?php echo aomark_listings_render_filter( [ 'model_id' => $model['id'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	<?php endif; ?>

		<?php if ( 'yes' === $settings['show_count'] || 'yes' === $settings['show_sort'] ) : ?>
			<div class="aomark-listings-results__bar">
				<?php if ( 'yes' === $settings['show_count'] ) : ?>
					<div class="aomark-listings-results__count"><?php echo esc_html( sprintf( _n( '%s listing', '%s listings', (int) $query->found_posts, 'aomark-listings' ), number_format_i18n( (int) $query->found_posts ) ) ); ?></div>
				<?php endif; ?>
				<?php if ( 'yes' === $settings['show_sort'] ) : ?>
					<label class="aomark-listings-results__sort">
						<span><?php esc_html_e( 'Sort', 'aomark-listings' ); ?></span>
						<select data-aomark-listings-sort>
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
		<?php echo aomark_listings_render_pagination( $page, (int) $query->max_num_pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php elseif ( 'load_more' === $settings['pagination'] && $page < (int) $query->max_num_pages ) : ?>
		<div class="aomark-listings-results__actions">
			<button type="button" class="aomark-listings-load-more" data-page="<?php echo esc_attr( $page + 1 ); ?>"><?php echo esc_html( $settings['load_more_text'] ); ?></button>
		</div>
	<?php endif; ?>
	<?php
	wp_reset_postdata();

	return [
		'html'      => ob_get_clean(),
		'total'     => (int) $query->found_posts,
		'page'      => $page,
		'max_pages' => (int) $query->max_num_pages,
		'model'     => $model,
	];
}

function aomark_listings_render_results( $settings = [] ) {
	aomark_listings_enqueue_assets();

	$settings = aomark_listings_sanitize_results_settings( $settings );
	$model    = aomark_listings_get_model( $settings['model_id'] );
	$settings['model_id'] = $model['id'];
	$rendered = aomark_listings_render_results_inner( $settings, 1 );
	$id = 'aomark-listings-results-' . wp_unique_id();

	ob_start();
	?>
	<div id="<?php echo esc_attr( $id ); ?>" class="aomark-listings-results aomark-listings-results--<?php echo esc_attr( $settings['skin'] ); ?>" data-ajax="<?php echo esc_attr( $settings['ajax'] ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>">
		<div data-aomark-listings-results-inner>
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
 * @param array $settings Widget settings.
 * @return int
 */
function aomark_listings_context_post_id( $settings = [] ) {
	$post_id = absint( $settings['post_id'] ?? 0 );

	return $post_id ?: absint( get_the_ID() );
}

function aomark_listings_render_field( $settings = [] ) {
	$model = aomark_listings_get_model( $settings['model_id'] ?? '' );
	$field = aomark_listings_get_field( $model, $settings['field_id'] ?? '' );
	$post_id = aomark_listings_context_post_id( $settings );

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
	$model = aomark_listings_get_model( $settings['model_id'] ?? '' );
	$post_id = aomark_listings_context_post_id( $settings );

	if ( ! $post_id ) {
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

	$model = aomark_listings_get_model( $settings['model_id'] ?? '' );
	$field = aomark_listings_get_field( $model, $settings['field_id'] ?? '' );
	$post_id = aomark_listings_context_post_id( $settings );

	if ( ! $post_id || ! $field ) {
		return '';
	}

	$ids = 'image' === $field['type'] ? [ absint( get_post_meta( $post_id, aomark_listings_meta_key( $field ), true ) ) ] : aomark_listings_gallery_ids( $post_id, $field );
	$ids = array_filter( $ids );
	$max_images = absint( $settings['max_images'] ?? 0 );
	if ( $max_images > 0 ) {
		$ids = array_slice( $ids, 0, $max_images );
	}
	if ( empty( $ids ) ) {
		return '';
	}

	ob_start();
	echo '<div class="aomark-listings-gallery">';
	foreach ( $ids as $image_id ) {
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
			printf( '<a href="%1$s" class="aomark-listings-gallery__item" data-elementor-open-lightbox="%2$s">%3$s</a>', esc_url( $full ?: $thumb ), esc_attr( $lightbox ), $image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

	$model = aomark_listings_get_model( $settings['model_id'] );
	$items = [];

	if ( in_array( $settings['source'], [ 'current', 'single' ], true ) ) {
		$post_id = aomark_listings_context_post_id( $settings );
		$location = $post_id ? aomark_listings_location_value( $post_id, $model ) : false;
		if ( $location ) {
			$price_field = aomark_listings_get_first_field_by_type( $model, 'price' );
			$items[] = [
				'id'      => $post_id,
				'title'   => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'url'     => get_permalink( $post_id ),
				'image'   => get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '',
				'lat'     => $location['lat'],
				'lng'     => $location['lng'],
				'address' => $location['address'],
				'price'   => $price_field ? aomark_listings_field_display_value( $post_id, $price_field, $settings ) : '',
			];
		}
	} else {
		$settings['model_id'] = $model['id'];
		$items = aomark_listings_get_map_items( $settings );
	}

	if ( empty( $items ) ) {
		return '<div class="aomark-listings-empty">' . esc_html( $settings['empty_message'] ) . '</div>';
	}

	$map_id = 'aomark-listings-map-' . wp_unique_id();
	$height = is_array( $settings['height'] ) && isset( $settings['height']['size'], $settings['height']['unit'] ) ? $settings['height']['size'] . $settings['height']['unit'] : absint( $settings['height'] ) . 'px';
	$config = [
		'id'              => $map_id,
		'items'           => $items,
		'zoom'            => max( 2, min( 18, absint( $settings['zoom'] ) ) ),
		'queryMap'        => ! in_array( $settings['source'], [ 'current', 'single' ], true ),
		'fitBounds'       => 'yes' === aomark_listings_switch_setting( $settings, 'fit_bounds', 'yes' ),
		'scrollWheelZoom' => 'yes' === aomark_listings_switch_setting( $settings, 'scroll_wheel_zoom', 'no' ),
		'zoomControl'     => 'yes' === aomark_listings_switch_setting( $settings, 'zoom_control', 'yes' ),
		'dragging'        => 'yes' === aomark_listings_switch_setting( $settings, 'dragging', 'yes' ),
		'popupImage'      => 'yes' === aomark_listings_switch_setting( $settings, 'popup_image', 'yes' ),
		'popupPrice'      => 'yes' === aomark_listings_switch_setting( $settings, 'popup_price', 'yes' ),
		'popupAddress'    => 'yes' === aomark_listings_switch_setting( $settings, 'popup_address', 'yes' ),
	];

	ob_start();
	?>
	<div class="aomark-listings-map-wrap">
		<div id="<?php echo esc_attr( $map_id ); ?>" class="aomark-listings-map" style="--alm-map-height:<?php echo esc_attr( $height ); ?>" data-map-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>
	</div>
	<?php
	return ob_get_clean();
}

function aomark_listings_ajax_results() {
	check_ajax_referer( 'aomark_listings_results', 'nonce' );

	$page = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
	$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '{}';
	$settings = json_decode( (string) $settings_raw, true );
	$settings = is_array( $settings ) ? $settings : [];

	$filters_raw = isset( $_POST['filters'] ) ? (string) wp_unslash( $_POST['filters'] ) : '';
	parse_str( $filters_raw, $filters );
	if ( isset( $_POST['sort'] ) && '' !== $_POST['sort'] ) {
		$filters['alm_sort'] = sanitize_key( wp_unslash( $_POST['sort'] ) );
	}

	$old_get = $_GET;
	$_GET = $filters;
	$rendered = aomark_listings_render_results_inner( $settings, $page );
	$map_items = aomark_listings_get_map_items( array_merge( $settings, [ 'map_limit' => 300 ] ) );
	$_GET = $old_get;

	wp_send_json_success(
		[
			'html'      => $rendered['html'],
			'total'     => $rendered['total'],
			'page'      => $rendered['page'],
			'max_pages' => $rendered['max_pages'],
			'mapItems'  => $map_items,
		]
	);
}
add_action( 'wp_ajax_aomark_listings_results', 'aomark_listings_ajax_results' );
add_action( 'wp_ajax_nopriv_aomark_listings_results', 'aomark_listings_ajax_results' );
