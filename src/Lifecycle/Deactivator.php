<?php
/**
 * Plugin deactivation tasks.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Lifecycle;

defined( 'ABSPATH' ) || exit;

final class Deactivator {
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
