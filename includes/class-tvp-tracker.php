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

class TVP_Tracker {

	/**
	 * Meta key for storing view count.
	 *
	 * @var string
	 */
	const META_KEY = 'tvp_view_count';

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'wp_ajax_tvp_track_view', array( $this, 'track_view' ) );
		add_action( 'wp_ajax_nopriv_tvp_track_view', array( $this, 'track_view' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker_script' ) );
	}

	/**
	 * Enqueue the tracking script on single post pages.
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

		wp_localize_script( 'tvp-tracker', 'tvpTracker', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'postId'  => get_the_ID(),
			'nonce'   => wp_create_nonce( 'tvp_track_view' ),
		) );
	}

	/**
	 * AJAX handler — increment the view count for a post.
	 */
	public function track_view() {
		check_ajax_referer( 'tvp_track_view', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		$post = get_post( $post_id );
		if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error( 'Invalid post.' );
		}

		// Rate limit: one count per IP + post per 30 minutes.
		$ip_hash       = md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$transient_key = 'tvp_view_' . $ip_hash . '_' . $post_id;
		if ( get_transient( $transient_key ) ) {
			wp_send_json_success( array(
				'views'   => (int) get_post_meta( $post_id, self::META_KEY, true ),
				'counted' => false,
			) );
		}
		set_transient( $transient_key, 1, 30 * MINUTE_IN_SECONDS );

		// Ensure meta row exists before atomic increment.
		if ( '' === get_post_meta( $post_id, self::META_KEY, true ) ) {
			add_post_meta( $post_id, self::META_KEY, 0, true );
		}

		// Atomic increment — avoids race conditions under concurrent requests.
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s",
			$post_id,
			self::META_KEY
		) );

		// Clean the meta cache so subsequent reads reflect the new value.
		wp_cache_delete( $post_id, 'post_meta' );
		$new_count = (int) get_post_meta( $post_id, self::META_KEY, true );

		wp_send_json_success( array( 'views' => $new_count, 'counted' => true ) );
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
