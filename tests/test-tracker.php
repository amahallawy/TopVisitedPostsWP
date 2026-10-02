<?php
/**
 * Tests for the AJAX view tracker.
 *
 * @package TopVisitedPosts
 */

/**
 * The AJAX tracker counts, rate-limits and rejects view requests.
 *
 * @covers TVP_Tracker
 */
class Test_TVP_Tracker extends WP_Ajax_UnitTestCase {

	/**
	 * A published post to track.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Track as a logged-out visitor from a fixed IP.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
		$this->post_id          = self::factory()->post->create();
	}

	/**
	 * Clean up request globals.
	 */
	public function tear_down() {
		unset( $_SERVER['REMOTE_ADDR'], $_POST['post_id'], $_POST['nonce'] );
		parent::tear_down();
	}

	/**
	 * Send a tracking request and return the decoded JSON response.
	 *
	 * @param int         $post_id Post ID to track.
	 * @param string|null $nonce   Nonce to send; a valid one when null.
	 * @return array Decoded response.
	 */
	private function track( $post_id, $nonce = null ) {
		$this->_last_response = '';
		$_POST['post_id']     = (string) $post_id;
		$_POST['nonce']       = null === $nonce ? wp_create_nonce( 'tvp_track_view' ) : $nonce;

		try {
			$this->_handleAjax( 'tvp_track_view' );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_*() ends the request by throwing this; the JSON is in _last_response.
			$this->assertNotSame( '', $this->_last_response );
		}

		return json_decode( $this->_last_response, true );
	}

	public function test_first_view_is_counted() {
		$response = $this->track( $this->post_id );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['counted'] );
		$this->assertSame( 1, $response['data']['views'] );
		$this->assertSame( 1, TVP_Tracker::get_views( $this->post_id ) );
	}

	public function test_repeat_view_from_same_ip_is_rate_limited() {
		$this->track( $this->post_id );
		$response = $this->track( $this->post_id );

		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['counted'] );
		$this->assertSame( 1, TVP_Tracker::get_views( $this->post_id ) );
	}

	public function test_view_from_another_ip_is_counted() {
		$this->track( $this->post_id );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.99';
		$response               = $this->track( $this->post_id );

		$this->assertTrue( $response['data']['counted'] );
		$this->assertSame( 2, TVP_Tracker::get_views( $this->post_id ) );
	}

	public function test_unknown_post_is_rejected() {
		$response = $this->track( $this->post_id + 1000 );

		$this->assertFalse( $response['success'] );
	}

	public function test_page_is_rejected() {
		$page_id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$response = $this->track( $page_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, TVP_Tracker::get_views( $page_id ) );
	}

	public function test_draft_is_rejected() {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$response = $this->track( $draft_id );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, TVP_Tracker::get_views( $draft_id ) );
	}

	public function test_view_from_a_cached_page_with_a_stale_nonce_is_counted() {
		$response = $this->track( $this->post_id, 'expired-nonce-from-cached-html' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, TVP_Tracker::get_views( $this->post_id ) );
	}

	public function test_view_without_any_nonce_is_counted() {
		$this->_last_response = '';
		$_POST['post_id']     = (string) $this->post_id;
		unset( $_POST['nonce'] );

		try {
			$this->_handleAjax( 'tvp_track_view' );
		} catch ( WPAjaxDieContinueException $e ) {
			// wp_send_json_*() ends the request by throwing this.
			$this->assertNotSame( '', $this->_last_response );
		}

		$this->assertSame( 1, TVP_Tracker::get_views( $this->post_id ) );
	}

	public function test_many_visitors_add_at_most_one_rate_limit_record_per_post() {
		foreach ( range( 1, 5 ) as $i ) {
			$_SERVER['REMOTE_ADDR'] = '198.51.100.' . $i;
			$this->track( $this->post_id );
		}

		$this->assertSame( 5, TVP_Tracker::get_views( $this->post_id ) );
		$this->assertLessThanOrEqual( 2, $this->count_rate_limit_option_rows(), 'Expected one transient (value + timeout) for the post, not one per visitor.' );
	}

	public function test_rate_limit_still_applies_per_visitor_with_shared_record() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.1';
		$this->track( $this->post_id );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.2';
		$this->track( $this->post_id );
		$_SERVER['REMOTE_ADDR'] = '198.51.100.1';
		$response               = $this->track( $this->post_id );

		$this->assertFalse( $response['data']['counted'] );
		$this->assertSame( 2, TVP_Tracker::get_views( $this->post_id ) );
	}

	public function test_persistent_object_cache_rate_limits_without_option_rows() {
		$was_using = wp_using_ext_object_cache( true );

		try {
			$this->track( $this->post_id );
			$response = $this->track( $this->post_id );
		} finally {
			// The previous value can be null, which would leave the flag on; cast it.
			wp_using_ext_object_cache( (bool) $was_using );
		}

		$this->assertFalse( $response['data']['counted'] );
		$this->assertSame( 1, TVP_Tracker::get_views( $this->post_id ) );
		$this->assertSame( 0, $this->count_rate_limit_option_rows() );
	}

	public function test_stored_rate_limit_record_does_not_contain_raw_ip() {
		$this->track( $this->post_id );

		global $wpdb;
		$values = $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '%tvp\\_view%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->assertNotEmpty( $values );
		foreach ( $values as $value ) {
			$this->assertStringNotContainsString( '203.0.113.10', $value );
			$this->assertStringNotContainsString( md5( '203.0.113.10' ), $value );
		}
	}

	/**
	 * Count option rows written by the tracker's rate limiter.
	 *
	 * @return int
	 */
	private function count_rate_limit_option_rows() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%tvp\\_view%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_untracked_post_has_zero_views() {
		$this->assertSame( 0, TVP_Tracker::get_views( $this->post_id ) );
	}
}
