<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package TopVisitedPosts
 */

// Abort if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'tvp_settings' );

// Remove view count meta from all posts.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall cleanup, caching is irrelevant.
$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => 'tvp_view_count' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	array( '%s' )
);
