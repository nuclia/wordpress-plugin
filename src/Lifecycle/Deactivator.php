<?php
/**
 * Plugin deactivation tasks.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Lifecycle;

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}

final class Deactivator {
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
