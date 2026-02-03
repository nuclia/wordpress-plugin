<?php
/**
 * Nuclia search for WP uninstall file.
 *
 * @since   1.0.0
 *
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// If uninstall not called from WordPress, then exit.
	exit;
}

// Delete the database table
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}agentic_rag_for_wp");

// Delete the options
delete_option('nuclia_zone');
delete_option('nuclia_token');
delete_option('nuclia_kbid');
delete_option('nuclia_api_is_reachable');
delete_option('nuclia_indexable_post_types');

// Also clean up dynamic options
// for each post type, delete the option 'nuclia_indexable_{$post_type}'
$post_types = get_post_types();
foreach ( $post_types as $post_type ) {
	delete_option('nuclia_indexable_' . $post_type);
}