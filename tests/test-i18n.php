<?php
/**
 * Tests for loading bundled translations.
 *
 * @package TopVisitedPosts
 */

/**
 * Translations shipped in languages/ are loaded for the site locale.
 *
 * @covers ::tvp_load_textdomain
 */
class Test_TVP_I18n extends WP_UnitTestCase {

	/**
	 * Translation file written into languages/ by the test, if any.
	 *
	 * @var string
	 */
	private $mo_file = '';

	/**
	 * The site's translation registry, restored after the test.
	 *
	 * @var WP_Textdomain_Registry|null
	 */
	private $original_registry = null;

	/**
	 * Remove the test translation and undo the locale override.
	 */
	public function tear_down() {
		global $wp_textdomain_registry;

		if ( $this->mo_file && file_exists( $this->mo_file ) ) {
			unlink( $this->mo_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		if ( $this->original_registry ) {
			$wp_textdomain_registry = $this->original_registry; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		unload_textdomain( 'top-visited-posts' );
		remove_all_filters( 'determine_locale' );
		wp_cache_flush();
		parent::tear_down();
	}

	public function test_textdomain_is_loaded_on_init() {
		$this->assertSame( 10, has_action( 'init', 'tvp_load_textdomain' ) );
	}

	public function test_translation_file_in_languages_folder_is_used() {
		global $wp_textdomain_registry;

		$mo = new MO();
		$mo->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'Top Visited Posts',
					'translations' => array( 'Articles populaires' ),
				)
			)
		);
		$this->mo_file = TVP_PLUGIN_DIR . 'languages/top-visited-posts-xx_XX.mo';
		$mo->export_to_file( $this->mo_file );

		// Fresh registry and caches so WordPress re-reads the languages/ folder.
		$this->original_registry = $wp_textdomain_registry;
		$wp_textdomain_registry  = new WP_Textdomain_Registry(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_cache_flush();
		unload_textdomain( 'top-visited-posts' );
		add_filter(
			'determine_locale',
			static function () {
				return 'xx_XX';
			}
		);

		tvp_load_textdomain();

		$this->assertSame( 'Articles populaires', __( 'Top Visited Posts', 'top-visited-posts' ) );
	}
}
