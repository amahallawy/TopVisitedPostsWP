<?php
/**
 * Tests for settings sanitisation.
 *
 * @package TopVisitedPosts
 */

/**
 * Settings sanitisation keeps saved options valid.
 *
 * @covers TVP_Admin::sanitize_settings
 */
class Test_TVP_Admin_Settings extends WP_UnitTestCase {

	/**
	 * Admin instance under test.
	 *
	 * @var TVP_Admin
	 */
	private $admin;

	/**
	 * Start every test with no saved settings.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( 'tvp_settings' );
		$this->admin = new TVP_Admin();
	}

	public function test_empty_input_gets_safe_defaults() {
		$result = $this->admin->sanitize_settings( array() );

		$this->assertSame( 0, $result['category'] );
		$this->assertSame( 0, $result['page_id'] );
		$this->assertSame( 5, $result['num_posts'] );
		$this->assertSame( array( 'most_views' ), $result['order_by'] );
		$this->assertSame( 'list', $result['layout'] );
		$this->assertSame( 3, $result['columns'] );
		$this->assertSame( array( 'title' ), $result['elements'] );
		$this->assertSame( 20, $result['excerpt_words'] );
		$this->assertSame( 0, $result['excerpt_preserve_breaks'] );
	}

	public function test_numbers_are_clamped() {
		$low  = $this->admin->sanitize_settings(
			array(
				'num_posts'     => '0',
				'columns'       => '1',
				'excerpt_words' => '0',
			)
		);
		$high = $this->admin->sanitize_settings(
			array(
				'num_posts'     => '500',
				'columns'       => '9',
				'excerpt_words' => '500',
			)
		);

		$this->assertSame( array( 1, 2, 1 ), array( $low['num_posts'], $low['columns'], $low['excerpt_words'] ) );
		$this->assertSame( array( 50, 4, 100 ), array( $high['num_posts'], $high['columns'], $high['excerpt_words'] ) );
	}

	public function test_unknown_order_criteria_are_dropped_and_order_kept() {
		$result = $this->admin->sanitize_settings( array( 'order_by' => array( 'newest', 'bogus', 'most_views' ) ) );

		$this->assertSame( array( 'newest', 'most_views' ), $result['order_by'] );
	}

	public function test_unknown_elements_are_dropped() {
		$result = $this->admin->sanitize_settings( array( 'elements' => array( 'views', '<script>', 'title' ) ) );

		$this->assertSame( array( 'views', 'title' ), $result['elements'] );
	}

	public function test_layout_accepts_only_list_or_grid() {
		$grid  = $this->admin->sanitize_settings( array( 'layout' => 'grid' ) );
		$bogus = $this->admin->sanitize_settings( array( 'layout' => 'carousel' ) );

		$this->assertSame( 'grid', $grid['layout'] );
		$this->assertSame( 'list', $bogus['layout'] );
	}

	public function test_section_title_is_stripped_of_markup() {
		$result = $this->admin->sanitize_settings( array( 'section_title' => '<b>Popular</b> now' ) );

		$this->assertSame( 'Popular now', $result['section_title'] );
	}

	public function test_absent_excerpt_fields_keep_saved_values() {
		update_option(
			'tvp_settings',
			array(
				'excerpt_words'           => 42,
				'excerpt_preserve_breaks' => 1,
			)
		);

		$result = $this->admin->sanitize_settings( array() );

		$this->assertSame( 42, $result['excerpt_words'] );
		$this->assertSame( 1, $result['excerpt_preserve_breaks'] );
	}

	public function test_explicit_zero_turns_preserve_breaks_off() {
		update_option( 'tvp_settings', array( 'excerpt_preserve_breaks' => 1 ) );

		$result = $this->admin->sanitize_settings( array( 'excerpt_preserve_breaks' => '0' ) );

		$this->assertSame( 0, $result['excerpt_preserve_breaks'] );
	}
}
