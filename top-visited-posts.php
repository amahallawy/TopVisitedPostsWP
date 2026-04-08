<?php
/**
 * Plugin Name:       Top Visited Posts
 * Plugin URI:        https://example.com/top-visited-posts
 * Description:       Display a configurable section of top visited posts by category with smooth scroll-to-post navigation.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Mahallawy
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       top-visited-posts
 * Domain Path:       /languages
 */

// Abort if called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'TVP_VERSION', '1.0.0' );
define( 'TVP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TVP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TVP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load plugin files.
 */
require_once TVP_PLUGIN_DIR . 'includes/class-tvp-tracker.php';
require_once TVP_PLUGIN_DIR . 'admin/class-tvp-admin.php';
require_once TVP_PLUGIN_DIR . 'admin/class-tvp-docs.php';
require_once TVP_PLUGIN_DIR . 'public/class-tvp-public.php';

/**
 * Initialize the plugin on plugins_loaded.
 */
function tvp_init() {
	// Admin settings and menu.
	if ( is_admin() ) {
		$admin = new TVP_Admin();
		$admin->init();

		$docs = new TVP_Docs();
		$docs->init();
	}

	// Frontend display & tracking.
	$public = new TVP_Public();
	$public->init();

	// Post view tracker (runs on both admin-ajax and frontend).
	$tracker = new TVP_Tracker();
	$tracker->init();
}
add_action( 'plugins_loaded', 'tvp_init' );

/**
 * Activation hook — set default options.
 */
function tvp_activate() {
	$defaults = array(
		'category'      => 0,
		'page_id'       => 0,
		'num_posts'     => 5,
		'section_title' => __( 'Top Visited Posts', 'top-visited-posts' ),
		'layout'        => 'list',
		'columns'       => 3,
		'show_rank'     => 1,
		'order_by'      => array( 'most_views' ),
		'elements'      => array( 'thumbnail', 'title', 'excerpt', 'date', 'views' ),
	);
	if ( false === get_option( 'tvp_settings' ) ) {
		add_option( 'tvp_settings', $defaults );
	}
}
register_activation_hook( __FILE__, 'tvp_activate' );

/**
 * Add Settings link on the Plugins page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function tvp_plugin_action_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=top-visited-posts' ) ),
		esc_html__( 'Settings', 'top-visited-posts' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . TVP_PLUGIN_BASENAME, 'tvp_plugin_action_links' );
