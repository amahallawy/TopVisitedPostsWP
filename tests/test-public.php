<?php
/**
 * Tests for the shortcode output and its text helpers.
 *
 * @package TopVisitedPosts
 */

/**
 * The shortcode ranks and renders posts from the configured category.
 *
 * @covers TVP_Public
 */
class Test_TVP_Public extends WP_UnitTestCase {

	/**
	 * Category the section draws from.
	 *
	 * @var int
	 */
	private $category_id;

	/**
	 * Create a category and point the settings at it.
	 */
	public function set_up() {
		parent::set_up();
		$this->category_id = self::factory()->category->create();
		$this->save_settings( array() );
	}

	/**
	 * Save plugin settings on top of a minimal title-only config.
	 *
	 * @param array $overrides Settings to change.
	 */
	private function save_settings( $overrides ) {
		update_option(
			'tvp_settings',
			array_merge(
				array(
					'category'      => $this->category_id,
					'page_id'       => 0,
					'num_posts'     => 5,
					'section_title' => 'Top',
					'order_by'      => array( 'most_views' ),
					'elements'      => array( 'title' ),
				),
				$overrides
			)
		);
	}

	/**
	 * Create a post in the test category with a view count.
	 *
	 * @param string $title Post title.
	 * @param int    $views View count.
	 * @param string $date  Post date (Y-m-d H:i:s).
	 * @return int Post ID.
	 */
	private function make_post( $title, $views, $date = '2026-01-01 10:00:00' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => $title,
				'post_date'     => $date,
				'post_category' => array( $this->category_id ),
			)
		);
		update_post_meta( $post_id, TVP_Tracker::META_KEY, $views );
		return $post_id;
	}

	/**
	 * Return the post titles in the rendered section, in display order.
	 *
	 * @return string[]
	 */
	private function rendered_titles() {
		preg_match_all( '#<span class="tvp-post-title">(.*?)</span>#', do_shortcode( '[top_visited_posts]' ), $matches );
		return $matches[1];
	}

	public function test_no_category_renders_placeholder_comment() {
		$this->save_settings( array( 'category' => 0 ) );

		$this->assertSame( '<!-- Top Visited Posts: No category selected -->', do_shortcode( '[top_visited_posts]' ) );
	}

	public function test_most_viewed_posts_come_first_and_list_is_capped() {
		$this->make_post( 'Low', 5 );
		$this->make_post( 'High', 50 );
		$this->make_post( 'Mid', 10 );
		$this->save_settings( array( 'num_posts' => 2 ) );

		$this->assertSame( array( 'High', 'Mid' ), $this->rendered_titles() );
	}

	public function test_least_views_reverses_the_ranking() {
		$this->make_post( 'Low', 5 );
		$this->make_post( 'High', 50 );
		$this->save_settings( array( 'order_by' => array( 'least_views' ) ) );

		$this->assertSame( array( 'Low', 'High' ), $this->rendered_titles() );
	}

	public function test_second_criterion_breaks_ties() {
		$this->make_post( 'Older', 7, '2026-01-01 10:00:00' );
		$this->make_post( 'Newer', 7, '2026-02-01 10:00:00' );
		$this->save_settings( array( 'order_by' => array( 'most_views', 'newest' ) ) );

		$this->assertSame( array( 'Newer', 'Older' ), $this->rendered_titles() );
	}

	public function test_posts_outside_the_category_are_ignored() {
		$this->make_post( 'Inside', 1 );
		$outside = self::factory()->post->create( array( 'post_title' => 'Outside' ) );
		update_post_meta( $outside, TVP_Tracker::META_KEY, 999 );

		$this->assertSame( array( 'Inside' ), $this->rendered_titles() );
	}

	public function test_normalise_title_strips_arabic_diacritics() {
		$this->assertSame( 'محمد', TVP_Public::normalise_title( 'مُحَمَّد' ) );
	}

	public function test_normalise_title_decodes_entities_and_collapses_whitespace() {
		$this->assertSame( 'Tom & Jerry x', TVP_Public::normalise_title( "  Tom &amp; Jerry \n\t x " ) );
	}

	public function test_trim_words_keep_breaks_keeps_newlines_and_marks_truncation() {
		$this->assertSame( "one two\nthree…", TVP_Public::trim_words_keep_breaks( "one two\nthree four", 3 ) );
	}

	public function test_trim_words_keep_breaks_leaves_short_text_alone() {
		$this->assertSame( 'a b', TVP_Public::trim_words_keep_breaks( '<p>a b</p>', 5 ) );
	}
}
