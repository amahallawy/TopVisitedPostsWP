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

	public function test_invalid_nonce_is_rejected() {
		$this->expectException( WPAjaxDieStopException::class );

		$this->track( $this->post_id, 'not-a-nonce' );
	}

	public function test_untracked_post_has_zero_views() {
		$this->assertSame( 0, TVP_Tracker::get_views( $this->post_id ) );
	}
}
