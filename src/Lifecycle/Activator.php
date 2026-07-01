<?php
/**
 * Plugin activation tasks.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Lifecycle;

use ProgressAgenticRag\Proxy\ProxyController;
use ProgressAgenticRag\Settings\SettingsRepository;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}

final class Activator {
	public static function activate(): void {
		$settings = new SettingsRepository();
		$settings->add_defaults();

		self::create_sync_table();
		ProxyController::add_rewrite_rules();
		flush_rewrite_rules( false );
	}

	private static function create_sync_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . SettingsRepository::SYNC_TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			post_id mediumint(9) NOT NULL,
			nuclia_rid varchar(255) NOT NULL,
			nuclia_seqid varchar(255),
			PRIMARY KEY  (id),
			INDEX idx_post_id (post_id),
			INDEX idx_nuclia_rid (nuclia_rid)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
