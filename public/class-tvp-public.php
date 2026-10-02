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

/**
 * Frontend shortcode handler and HTML builder for Top Visited Posts.
 */
class TVP_Public {

	/**
	 * Transient holding the most recent ranking.
	 *
	 * @var string
	 */
	const RANKING_CACHE_KEY = 'tvp_ranked_ids';

	/**
	 * Default ranking cache lifetime in seconds.
	 *
	 * @var int
	 */
	const RANKING_CACHE_TTL = 300;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_shortcode( 'top_visited_posts', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Anything that can change which posts rank clears the cached ranking.
		add_action( 'add_option_tvp_settings', array( __CLASS__, 'flush_ranking_cache' ) );
		add_action( 'update_option_tvp_settings', array( __CLASS__, 'flush_ranking_cache' ) );
		add_action( 'update_option_sticky_posts', array( __CLASS__, 'flush_ranking_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'flush_ranking_cache' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'flush_ranking_cache_on_status_change' ), 10, 2 );
	}

	/**
	 * Delete the cached ranking.
	 *
	 * @return void
	 */
	public static function flush_ranking_cache() {
		delete_transient( self::RANKING_CACHE_KEY );
	}

	/**
	 * Clear the cached ranking when a post enters, leaves or is updated in `publish`.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Previous post status.
	 * @return void
	 */
	public static function flush_ranking_cache_on_status_change( $new_status, $old_status ) {
		if ( 'publish' === $new_status || 'publish' === $old_status ) {
			self::flush_ranking_cache();
		}
	}

	/**
	 * Read the settings that decide which posts rank, validated against allowlists.
	 *
	 * @return array{category: int, order_by: string[], num_posts: int}
	 */
	private function get_ranking_settings() {
		$options   = get_option( 'tvp_settings' );
		$category  = isset( $options['category'] ) ? absint( $options['category'] ) : 0;
		$num_posts = isset( $options['num_posts'] ) ? absint( $options['num_posts'] ) : 5;
		$order_by  = isset( $options['order_by'] ) ? $options['order_by'] : array( 'most_views' );

		// Migrate legacy single-string order_by.
		if ( is_string( $order_by ) ) {
			$order_by = array( $order_by );
		}

		// Re-validate arrays from the database against allowlists.
		$valid_orders = array_keys( TVP_Admin::get_order_criteria() );
		$order_by     = array_values(
			array_filter(
				(array) $order_by,
				function ( $v ) use ( $valid_orders ) {
					return in_array( $v, $valid_orders, true );
				}
			)
		);
		if ( empty( $order_by ) ) {
			$order_by = array( 'most_views' );
		}

		return array(
			'category'  => $category,
			'order_by'  => $order_by,
			'num_posts' => max( 1, $num_posts ),
		);
	}

	/**
	 * IDs of the top posts in display order, from cache when possible.
	 *
	 * @param int      $category  Category ID.
	 * @param string[] $order_by  Ordered sort criteria.
	 * @param int      $num_posts How many posts to return.
	 * @return int[] Post IDs.
	 */
	private function get_ranked_post_ids( $category, $order_by, $num_posts ) {
		$cache_key = md5( (string) wp_json_encode( array( $category, $order_by, $num_posts ) ) );
		$cached    = get_transient( self::RANKING_CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['key'], $cached['ids'] ) && $cached['key'] === $cache_key ) {
			return array_map( 'intval', (array) $cached['ids'] );
		}

		$ids = $this->rank_post_ids( $category, $order_by, $num_posts );

		/**
		 * Filters how long the ranked post IDs are cached, in seconds. 0 disables caching.
		 *
		 * @param int $ttl Cache lifetime in seconds.
		 */
		$ttl = (int) apply_filters( 'tvp_ranking_cache_ttl', self::RANKING_CACHE_TTL );
		if ( $ttl > 0 ) {
			set_transient(
				self::RANKING_CACHE_KEY,
				array(
					'key' => $cache_key,
					'ids' => $ids,
				),
				$ttl
			);
		}

		return $ids;
	}

	/**
	 * Rank posts across the whole category.
	 *
	 * The database returns a small candidate set that is guaranteed to hold the
	 * true top N; the PHP multi-sort then orders it. See
	 * docs/agdr/AgDR-0004-ranking-candidate-queries.md.
	 *
	 * @param int      $category  Category ID.
	 * @param string[] $order_by  Ordered sort criteria.
	 * @param int      $num_posts How many posts to return.
	 * @return int[] Post IDs in display order.
	 */
	private function rank_post_ids( $category, $order_by, $num_posts ) {
		$sticky_ids = array();
		if ( in_array( 'featured', $order_by, true ) ) {
			$sticky_ids = array_values( array_filter( array_map( 'absint', (array) get_option( 'sticky_posts', array() ) ) ) );
		}
		$limit = $num_posts + count( $sticky_ids );

		$base = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'cat'                 => $category,
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		// Posts that have a view count, ordered by it.
		$viewed = get_posts(
			array_merge(
				$base,
				array(
					'meta_key' => TVP_Tracker::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Needed to order by views; result is cached.
					'orderby'  => $this->sql_orderby( $order_by, true ),
				)
			)
		);

		// Posts never tracked, which count as zero views.
		$never_viewed = get_posts(
			array_merge(
				$base,
				array(
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- NOT EXISTS lookup; result is cached.
						array(
							'key'     => TVP_Tracker::META_KEY,
							'compare' => 'NOT EXISTS',
						),
					),
					'orderby'    => $this->sql_orderby( $order_by, false ),
				)
			)
		);

		$sticky = array();
		if ( $sticky_ids ) {
			$sticky = get_posts(
				array_merge(
					$base,
					array(
						'post__in'       => $sticky_ids,
						'posts_per_page' => count( $sticky_ids ),
					)
				)
			);
		}

		$candidates = array();
		foreach ( array_merge( $viewed, $never_viewed, $sticky ) as $candidate ) {
			$candidates[ $candidate->ID ] = $candidate;
		}

		$ranked = array_slice( $this->multi_sort( array_values( $candidates ), $order_by ), 0, $num_posts );

		return array_map(
			function ( $post ) {
				return (int) $post->ID;
			},
			$ranked
		);
	}

	/**
	 * Translate sort criteria into a WP_Query orderby array.
	 *
	 * Mirrors compare_by_criterion() and the final tie-break in multi_sort(), so
	 * the database order matches the PHP order within each candidate set.
	 * "Sticky first" has no SQL form and is handled by the sticky candidate query.
	 *
	 * @param string[] $order_by    Ordered sort criteria.
	 * @param bool     $order_views Whether the query can order by view count.
	 * @return array<string, string> WP_Query orderby.
	 */
	private function sql_orderby( $order_by, $order_views ) {
		$map = array(
			'most_views'  => array( 'meta_value_num', 'DESC' ),
			'least_views' => array( 'meta_value_num', 'ASC' ),
			'newest'      => array( 'date', 'DESC' ),
			'oldest'      => array( 'date', 'ASC' ),
		);

		$orderby = array();
		foreach ( $order_by as $criterion ) {
			if ( ! isset( $map[ $criterion ] ) ) {
				continue;
			}
			list( $field, $direction ) = $map[ $criterion ];
			if ( 'meta_value_num' === $field && ! $order_views ) {
				continue;
			}
			if ( ! isset( $orderby[ $field ] ) ) {
				$orderby[ $field ] = $direction;
			}
		}

		if ( ! isset( $orderby['date'] ) ) {
			$orderby['date'] = 'DESC';
		}
		$orderby['ID'] = 'DESC';

		return $orderby;
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
		// can inject anchor IDs onto Spectra / theme post cards. Only the
		// ranked posts are linked from the section, so only they need anchors.
		$options = get_option( 'tvp_settings' );
		$page_id = isset( $options['page_id'] ) ? absint( $options['page_id'] ) : 0;

		if ( $page_id && is_page( $page_id ) ) {
			$ranking   = $this->get_ranking_settings();
			$post_map  = array();
			$title_map = array();

			if ( $ranking['category'] ) {
				$posts = $this->get_ranked_post_ids( $ranking['category'], $ranking['order_by'], $ranking['num_posts'] );

				foreach ( $posts as $pid ) {
					$post_map[ get_permalink( $pid ) ] = $pid;

					// Fallback key for target pages whose post cards have no
					// permalink link (e.g. some page-builder loop templates):
					// match by normalised title instead. Keyed identically to
					// the JS side (see normaliseTitle() in scroll.js).
					$title_key = self::normalise_title( get_the_title( $pid ) );
					if ( '' !== $title_key ) {
						$title_map[ $title_key ] = $pid;
					}
				}
			}

			wp_localize_script(
				'tvp-scroll',
				'tvpScroll',
				array(
					'postMap'  => $post_map,
					'titleMap' => $title_map,
				)
			);
		}
	}

	/**
	 * Normalise a post title for fallback matching against card headings.
	 *
	 * Must stay byte-for-byte equivalent to normaliseTitle() in scroll.js:
	 * decode entities, NFC-normalise (when intl is available), strip Arabic
	 * diacritics (tashkeel) and tatweel, and collapse whitespace. This makes
	 * matching tolerant of diacritic differences between the stored title and
	 * the rendered card text.
	 *
	 * @param string $title Raw post title.
	 * @return string Normalised title key.
	 */
	public static function normalise_title( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' );

		if ( class_exists( 'Normalizer' ) ) {
			$normalised = Normalizer::normalize( $title, Normalizer::FORM_C );
			if ( false !== $normalised ) {
				$title = $normalised;
			}
		}

		// Strip Arabic diacritics (tashkeel) and tatweel/kashida. The class is
		// concatenated only to keep line length down; the pattern is unchanged.
		$diacritics = '/[\x{0610}-\x{061A}\x{0640}\x{064B}-\x{065F}\x{0670}'
			. '\x{06D6}-\x{06ED}\x{08D3}-\x{08FF}\x{FE70}-\x{FE7F}]/u';
		$title      = preg_replace( $diacritics, '', $title );

		// Collapse all whitespace runs to a single space and trim.
		$title = preg_replace( '/\s+/u', ' ', $title );

		return trim( (string) $title );
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes (unused, settings come from DB).
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Required by add_shortcode signature.
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
		usort(
			$posts,
			function ( $a, $b ) use ( $criteria ) {
				foreach ( $criteria as $criterion ) {
					$result = $this->compare_by_criterion( $a, $b, $criterion );
					if ( 0 !== $result ) {
						return $result;
					}
				}
				// Final tie-break (newest, then highest ID) keeps the order
				// deterministic and matches sql_orderby().
				$result = $this->compare_by_criterion( $a, $b, 'newest' );
				return 0 !== $result ? $result : $b->ID - $a->ID;
			}
		);
		return $posts;
	}

	/**
	 * Build the top visited posts section HTML.
	 *
	 * @return string HTML markup.
	 */
	private function build_section() {
		$options       = get_option( 'tvp_settings' );
		$page_id       = isset( $options['page_id'] ) ? absint( $options['page_id'] ) : 0;
		$section_title = isset( $options['section_title'] ) ? $options['section_title'] : __( 'Top Visited Posts', 'top-visited-posts' );
		$layout        = isset( $options['layout'] ) && in_array( $options['layout'], array( 'list', 'grid' ), true ) ? $options['layout'] : 'list';
		$columns       = isset( $options['columns'] ) ? absint( $options['columns'] ) : 3;
		$show_rank     = isset( $options['show_rank'] ) ? (int) $options['show_rank'] : 1;
		$elements      = isset( $options['elements'] ) ? $options['elements'] : array( 'thumbnail', 'title', 'excerpt', 'date', 'views' );

		$excerpt_words    = isset( $options['excerpt_words'] ) ? absint( $options['excerpt_words'] ) : 20;
		$excerpt_preserve = isset( $options['excerpt_preserve_breaks'] ) ? (int) $options['excerpt_preserve_breaks'] : 0;
		if ( $excerpt_words < 1 ) {
			$excerpt_words = 1;
		}
		if ( $excerpt_words > 100 ) {
			$excerpt_words = 100;
		}

		$ranking   = $this->get_ranking_settings();
		$category  = $ranking['category'];
		$num_posts = $ranking['num_posts'];
		$order_by  = $ranking['order_by'];

		$valid_elements = array_keys( TVP_Admin::get_available_elements() );
		$elements       = array_values(
			array_filter(
				$elements,
				function ( $v ) use ( $valid_elements ) {
					return in_array( $v, $valid_elements, true );
				}
			)
		);
		if ( empty( $elements ) ) {
			$elements = array( 'title' );
		}

		if ( ! $category ) {
			return '<!-- Top Visited Posts: No category selected -->';
		}

		// Rank across the whole category; see get_ranked_post_ids().
		$all_posts = array_filter( array_map( 'get_post', $this->get_ranked_post_ids( $category, $order_by, $num_posts ) ) );
		if ( empty( $all_posts ) ) {
			return '<!-- Top Visited Posts: No posts found -->';
		}

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
					$GLOBALS['post'] = $the_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					setup_postdata( $GLOBALS['post'] );
					++$rank;
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
										if ( $excerpt_preserve ) {
											$excerpt_text = self::trim_words_keep_breaks( get_the_excerpt(), $excerpt_words, '…' );
											?>
											<span class="tvp-post-excerpt tvp-post-excerpt--preserve-breaks"><?php echo nl2br( esc_html( $excerpt_text ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html runs before nl2br, which only injects <br /> tags. ?></span>
											<?php
										} else {
											?>
											<span class="tvp-post-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerpt_words, '…' ) ); ?></span>
											<?php
										}
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

	/**
	 * Trim text to a word count while preserving line breaks.
	 *
	 * Unlike wp_trim_words(), which collapses all whitespace (including
	 * newlines) to single spaces, this keeps `\n` characters so the
	 * excerpt can be rendered with line breaks intact.
	 *
	 * @param string $text      Source text (may contain HTML/newlines).
	 * @param int    $num_words Maximum number of words to keep.
	 * @param string $more      Appended when the text is truncated.
	 * @return string Trimmed plain text with newlines preserved.
	 */
	public static function trim_words_keep_breaks( $text, $num_words, $more = '…' ) {
		$text      = wp_strip_all_tags( $text );
		$num_words = (int) $num_words;

		// Normalise CRLF/CR to LF; collapse spaces/tabs but keep newlines.
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );

		// Split into tokens, treating runs of spaces and newlines as
		// separators but keeping newline tokens so they can be re-emitted.
		$tokens = preg_split( '/( |\n)/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		if ( empty( $tokens ) ) {
			return '';
		}

		$out       = '';
		$word_seen = 0;
		$truncated = false;

		foreach ( $tokens as $token ) {
			if ( "\n" === $token || ' ' === $token ) {
				$out .= $token;
				continue;
			}
			if ( $word_seen >= $num_words ) {
				$truncated = true;
				break;
			}
			$out .= $token;
			++$word_seen;
		}

		$out = rtrim( $out );

		if ( $truncated ) {
			$out .= $more;
		}

		return $out;
	}
}
