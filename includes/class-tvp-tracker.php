<?php
/**
 * Tracks post views via AJAX.
 *
 * @package TopVisitedPosts
 */

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks and persists post view counts via AJAX.
 */
class TVP_Tracker {

	/**
	 * Meta key for storing view count.
	 *
	 * @var string
	 */
	const META_KEY = 'tvp_view_count';

	/**
	 * How long one visitor's view of a post blocks another count, in seconds.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 1800;

	/**
	 * Most visitors remembered per post when no persistent object cache exists.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX_VISITORS = 1000;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_ajax_tvp_track_view', array( $this, 'track_view' ) );
		add_action( 'wp_ajax_nopriv_tvp_track_view', array( $this, 'track_view' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker_script' ) );
	}

	/**
	 * Enqueue the tracking script on single post pages.
	 *
	 * @return void
	 */
	public function enqueue_tracker_script() {
		if ( ! is_single() ) {
			return;
		}

		wp_enqueue_script(
			'tvp-tracker',
			TVP_PLUGIN_URL . 'public/js/tracker.js',
			array(),
			TVP_VERSION,
			true
		);

		wp_localize_script(
			'tvp-tracker',
			'tvpTracker',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'postId'  => get_the_ID(),
			)
		);
	}

	/**
	 * AJAX handler — increment the view count for a post.
	 *
	 * Deliberately has no nonce check: the request is anonymous, changes
	 * nothing but a public counter, and pages served from a page cache
	 * outlive any nonce. See docs/agdr/AgDR-0005-tracking-without-nonce.md.
	 *
	 * @return void
	 */
	public function track_view() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Anonymous view counter; see docblock.

		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( 'Invalid post.' );
		}

		// Rate limit: one count per visitor + post per window.
		if ( ! self::claim_view( $post_id, self::visitor_key() ) ) {
			wp_send_json_success(
				array(
					'views'   => (int) get_post_meta( $post_id, self::META_KEY, true ),
					'counted' => false,
				)
			);
		}

		// Ensure meta row exists before atomic increment.
		if ( '' === get_post_meta( $post_id, self::META_KEY, true ) ) {
			add_post_meta( $post_id, self::META_KEY, 0, true );
		}

		// Atomic increment — avoids race conditions under concurrent requests.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic increment requires a direct query; there is no WP-Meta API equivalent.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
				$post_id,
				self::META_KEY
			)
		);

		// Clean the meta cache so subsequent reads reflect the new value.
		wp_cache_delete( $post_id, 'post_meta' );
		$new_count = (int) get_post_meta( $post_id, self::META_KEY, true );

		wp_send_json_success(
			array(
				'views'   => $new_count,
				'counted' => true,
			)
		);
	}

	/**
	 * A short, salted, non-reversible key for the current visitor.
	 *
	 * Uses wp_hash() so the stored value cannot be matched back to an IP by
	 * hashing candidate addresses, as a plain md5() could.
	 *
	 * @return string
	 */
	private static function visitor_key() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'v' . substr( wp_hash( 'tvp_view|' . $remote_addr ), 0, 16 );
	}

	/**
	 * Record that a visitor viewed a post, unless they already did within the window.
	 *
	 * With a persistent object cache this is one atomic cache entry and never
	 * touches the database. Without one, a single transient per post holds the
	 * recent visitors, instead of one transient per visitor and post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $visitor Visitor key from visitor_key().
	 * @return bool True if this view should be counted.
	 */
	private static function claim_view( $post_id, $visitor ) {
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $visitor . '_' . $post_id, 1, 'tvp_views', self::RATE_LIMIT_WINDOW );
		}

		$transient_key = 'tvp_view_' . $post_id;
		$cutoff        = time() - self::RATE_LIMIT_WINDOW;
		$seen          = get_transient( $transient_key );
		$seen          = is_array( $seen ) ? $seen : array();

		// Forget visitors whose window has passed.
		$seen = array_filter(
			$seen,
			function ( $viewed_at ) use ( $cutoff ) {
				return (int) $viewed_at > $cutoff;
			}
		);

		if ( isset( $seen[ $visitor ] ) ) {
			return false;
		}

		$seen[ $visitor ] = time();

		// Keep the record bounded on very busy posts by dropping the oldest visitors.
		if ( count( $seen ) > self::RATE_LIMIT_MAX_VISITORS ) {
			asort( $seen );
			$seen = array_slice( $seen, -self::RATE_LIMIT_MAX_VISITORS, null, true );
		}

		set_transient( $transient_key, $seen, self::RATE_LIMIT_WINDOW );
		return true;
	}

	/**
	 * Get the view count for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int View count.
	 */
	public static function get_views( $post_id ) {
		return (int) get_post_meta( $post_id, self::META_KEY, true );
	}
}
