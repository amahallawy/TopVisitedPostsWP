<?php
/**
 * PHPUnit bootstrap for the Top Visited Posts integration tests.
 *
 * Runs inside the wp-env tests container, which provides the WordPress test
 * library at WP_TESTS_DIR. See docs/agdr/AgDR-0002-phpunit-wp-env.md.
 *
 * @package TopVisitedPosts
 */

$tvp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $tvp_tests_dir ) {
	$tvp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $tvp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find {$tvp_tests_dir}/includes/functions.php. Run the suite with: npm run test:php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

require_once $tvp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/top-visited-posts.php';
	}
);

require $tvp_tests_dir . '/includes/bootstrap.php';
