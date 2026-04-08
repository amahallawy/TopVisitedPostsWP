<?php
/**
 * Public-facing functionality for Top Visited Posts.
 *
 * @package TopVisitedPosts
 */

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TVP_Public {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_shortcode( 'top_visited_posts', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend styles and scroll script.
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'tvp-public',
			TVP_PLUGIN_URL . 'public/css/public.css',
			array(),
			TVP_VERSION
		);

		wp_enqueue_script(
			'tvp-scroll',
			TVP_PLUGIN_URL . 'public/js/scroll.js',
			array(),
			TVP_VERSION,
			true
		);

		// On the target page, pass a permalink → post ID map so scroll.js
		// can inject anchor IDs onto Spectra / theme post cards.
		$options = get_option( 'tvp_settings' );
		$page_id = isset( $options['page_id'] ) ? absint( $options['page_id'] ) : 0;

		if ( $page_id && is_page( $page_id ) ) {
			$category = isset( $options['category'] ) ? absint( $options['category'] ) : 0;
			$post_map = array();

			if ( $category ) {
				$posts = get_posts( array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'cat'            => $category,
					'posts_per_page' => 100,
					'fields'         => 'ids',
				) );

				foreach ( $posts as $pid ) {
					$post_map[ get_permalink( $pid ) ] = $pid;
				}
			}

			wp_localize_script( 'tvp-scroll', 'tvpScroll', array(
				'postMap' => $post_map,
			) );
		}
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes (unused, settings come from DB).
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		return $this->build_section();
	}

	/**
	 * Compare two posts by a single sort criterion.
	 *
	 * Returns negative if $a should come first, positive if $b should,
	 * or zero if they are equal on this criterion (so the next layer decides).
	 *
	 * @param WP_Post $a         First post.
	 * @param WP_Post $b         Second post.
	 * @param string  $criterion Sort criterion key.
	 * @return int Comparison result.
	 */
	private function compare_by_criterion( $a, $b, $criterion ) {
		switch ( $criterion ) {
			case 'featured':
				$a_val = is_sticky( $a->ID ) ? 1 : 0;
				$b_val = is_sticky( $b->ID ) ? 1 : 0;
				return $b_val - $a_val; // Sticky first (descending).

			case 'most_views':
				$a_val = TVP_Tracker::get_views( $a->ID );
				$b_val = TVP_Tracker::get_views( $b->ID );
				return $b_val - $a_val; // Most views first (descending).

			case 'least_views':
				$a_val = TVP_Tracker::get_views( $a->ID );
				$b_val = TVP_Tracker::get_views( $b->ID );
				return $a_val - $b_val; // Least views first (ascending).

			case 'newest':
				return strtotime( $b->post_date ) - strtotime( $a->post_date ); // Newest first.

			case 'oldest':
				return strtotime( $a->post_date ) - strtotime( $b->post_date ); // Oldest first.

			default:
				return 0;
		}
	}

	/**
	 * Sort posts by multiple criteria in priority order.
	 *
	 * @param array $posts    Array of WP_Post objects.
	 * @param array $criteria Ordered list of criterion keys.
	 * @return array Sorted posts.
	 */
	private function multi_sort( $posts, $criteria ) {
		usort( $posts, function ( $a, $b ) use ( $criteria ) {
			foreach ( $criteria as $criterion ) {
				$result = $this->compare_by_criterion( $a, $b, $criterion );
				if ( 0 !== $result ) {
					return $result;
				}
			}
			return 0;
		} );
		return $posts;
	}

	/**
	 * Build the top visited posts section HTML.
	 *
	 * @return string HTML markup.
	 */
	private function build_section() {
		$options       = get_option( 'tvp_settings' );
		$category      = isset( $options['category'] ) ? absint( $options['category'] ) : 0;
		$page_id       = isset( $options['page_id'] ) ? absint( $options['page_id'] ) : 0;
		$num_posts     = isset( $options['num_posts'] ) ? absint( $options['num_posts'] ) : 5;
		$section_title = isset( $options['section_title'] ) ? $options['section_title'] : __( 'Top Visited Posts', 'top-visited-posts' );
		$layout        = isset( $options['layout'] ) && in_array( $options['layout'], array( 'list', 'grid' ), true ) ? $options['layout'] : 'list';
		$columns       = isset( $options['columns'] ) ? absint( $options['columns'] ) : 3;
		$show_rank     = isset( $options['show_rank'] ) ? (int) $options['show_rank'] : 1;
		$order_by      = isset( $options['order_by'] ) ? $options['order_by'] : array( 'most_views' );
		$elements      = isset( $options['elements'] ) ? $options['elements'] : array( 'thumbnail', 'title', 'excerpt', 'date', 'views' );

		// Migrate legacy single-string order_by.
		if ( is_string( $order_by ) ) {
			$order_by = array( $order_by );
		}

		// Re-validate arrays from the database against allowlists.
		$valid_orders   = array_keys( TVP_Admin::get_order_criteria() );
		$order_by       = array_values( array_filter( $order_by, function ( $v ) use ( $valid_orders ) {
			return in_array( $v, $valid_orders, true );
		} ) );
		if ( empty( $order_by ) ) {
			$order_by = array( 'most_views' );
		}

		$valid_elements = array_keys( TVP_Admin::get_available_elements() );
		$elements       = array_values( array_filter( $elements, function ( $v ) use ( $valid_elements ) {
			return in_array( $v, $valid_elements, true );
		} ) );
		if ( empty( $elements ) ) {
			$elements = array( 'title' );
		}

		if ( ! $category ) {
			return '<!-- Top Visited Posts: No category selected -->';
		}

		// Fetch all candidates from the category, sort in PHP for multi-layer ordering.
		$query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'cat'            => $category,
		) );

		if ( ! $query->have_posts() ) {
			return '<!-- Top Visited Posts: No posts found -->';
		}

		// Collect all posts.
		$all_posts = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$all_posts[] = get_post();
		}
		wp_reset_postdata();

		// Apply multi-layer sort and slice.
		$all_posts = $this->multi_sort( $all_posts, $order_by );
		$all_posts = array_slice( $all_posts, 0, $num_posts );

		$page_url     = $page_id ? get_permalink( $page_id ) : '';
		$layout_class = 'tvp-layout-' . esc_attr( $layout );
		$rank         = 0;

		// Detect RTL from WordPress locale setting.
		$dir_attr = is_rtl() ? ' dir="rtl"' : '';

		// Inject CSS custom property for grid columns.
		$inline_style = '';
		if ( 'grid' === $layout ) {
			$inline_style = sprintf( ' style="--tvp-columns: %d;"', $columns );
		}

		ob_start();
		?>
		<div class="tvp-section <?php echo esc_attr( $layout_class ); ?>"<?php echo $inline_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?><?php echo $dir_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe static string. ?>>
			<?php if ( $section_title ) : ?>
				<h2 class="tvp-section-title"><?php echo esc_html( $section_title ); ?></h2>
			<?php endif; ?>
			<ul class="tvp-post-list">
				<?php
				foreach ( $all_posts as $the_post ) :
					setup_postdata( $GLOBALS['post'] = $the_post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$rank++;
					$post_id    = $the_post->ID;
					$views      = TVP_Tracker::get_views( $post_id );
					$anchor_id  = 'tvp-post-' . $post_id;
					$thumb_size = ( 'grid' === $layout ) ? 'medium' : 'thumbnail';

					// Build the link: target page URL + hash anchor for scroll.
					if ( $page_url ) {
						$link = esc_url( $page_url . '#' . $anchor_id );
					} else {
						$link = esc_url( get_permalink( $post_id ) );
					}

					$item_classes = 'tvp-post-item';
					if ( is_sticky( $post_id ) ) {
						$item_classes .= ' tvp-post-featured';
					}
				?>
					<li class="<?php echo esc_attr( $item_classes ); ?>">
						<a href="<?php echo esc_url( $link ); ?>" class="tvp-post-link" data-tvp-target="<?php echo esc_attr( $anchor_id ); ?>">
							<?php if ( $show_rank ) : ?>
								<span class="tvp-rank-badge"><?php echo esc_html( $rank ); ?></span>
							<?php endif; ?>
							<?php
							// Render elements in the configured order.
							foreach ( $elements as $element ) :
								switch ( $element ) :
									case 'thumbnail':
										if ( has_post_thumbnail( $post_id ) ) :
											?>
											<span class="tvp-post-thumb">
												<?php echo wp_kses_post( get_the_post_thumbnail( $post_id, $thumb_size ) ); ?>
											</span>
											<?php
										endif;
										break;

									case 'title':
										?>
										<span class="tvp-post-title"><?php echo esc_html( get_the_title() ); ?></span>
										<?php
										break;

									case 'excerpt':
										?>
										<span class="tvp-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 12, '…' ) ); ?></span>
										<?php
										break;

									case 'date':
										?>
										<span class="tvp-post-date">
											<?php
											/* translators: %s: human-readable time difference */
											printf( esc_html__( '%s ago', 'top-visited-posts' ), esc_html( human_time_diff( get_the_time( 'U' ), time() ) ) );
											?>
										</span>
										<?php
										break;

									case 'views':
										?>
										<span class="tvp-post-views">
											<?php
											echo wp_kses(
												sprintf(
													/* translators: %s: view count number (wrapped in <strong>) */
													__( '%s views', 'top-visited-posts' ),
													'<strong>' . esc_html( number_format_i18n( $views ) ) . '</strong>'
												),
												array( 'strong' => array() )
											);
											?>
										</span>
										<?php
										break;
								endswitch;
							endforeach;
							?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}
}
