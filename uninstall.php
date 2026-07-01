<?php
/**
 * Remove plugin-owned data on uninstall.
 *
 * @package ProgressAgenticRag
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$progress_agentic_rag_options = [
	'nuclia_zone',
	'nuclia_token',
	'nuclia_kbid',
	'nuclia_account_id',
	'nuclia_api_is_reachable',
	'nuclia_taxonomy_label_map',
	'nuclia_labelsets_cache',
	'nuclia_indexable_post_types',
	'progress_agentic_rag_manual_sync_state',
	'progress_agentic_rag_delete_sync_state',
];

foreach ( $progress_agentic_rag_options as $progress_agentic_rag_option ) {
	delete_option( $progress_agentic_rag_option );
}

foreach ( get_post_types( [], 'names' ) as $progress_agentic_rag_post_type ) {
	delete_option( 'nuclia_indexable_' . $progress_agentic_rag_post_type );
}

global $wpdb;

$progress_agentic_rag_table_name = $wpdb->prefix . 'agentic_rag_for_wp';
$wpdb->query( "DROP TABLE IF EXISTS $progress_agentic_rag_table_name" );
