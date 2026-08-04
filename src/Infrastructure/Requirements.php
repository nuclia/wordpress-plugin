<?php
/**
 * Runtime requirement checks.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Infrastructure;

defined( 'ABSPATH' ) || exit;

final class Requirements {
	public static function is_supported(): bool {
		return self::is_php_supported() && self::is_wp_supported();
	}

	public static function is_php_supported(): bool {
		return version_compare( PHP_VERSION, PROGRESS_AGENTIC_RAG_MIN_PHP_VERSION, '>=' );
	}

	public static function is_wp_supported(): bool {
		$wp_version = (string) ( $GLOBALS['wp_version'] ?? '0' );

		return version_compare( $wp_version, PROGRESS_AGENTIC_RAG_MIN_WP_VERSION, '>=' );
	}

	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$messages = [];

		if ( ! self::is_php_supported() ) {
			$messages[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'Progress Agentic RAG requires PHP %1$s or later. Your site is running PHP %2$s.', 'progress-agentic-rag-connector' ),
				PROGRESS_AGENTIC_RAG_MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		if ( ! self::is_wp_supported() ) {
			$messages[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'Progress Agentic RAG requires WordPress %1$s or later. Your site is running WordPress %2$s.', 'progress-agentic-rag-connector' ),
				PROGRESS_AGENTIC_RAG_MIN_WP_VERSION,
				(string) ( $GLOBALS['wp_version'] ?? 'unknown' )
			);
		}

		if ( [] === $messages ) {
			return;
		}

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/requirements-notice.php';
	}
}
