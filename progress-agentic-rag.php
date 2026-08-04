<?php
/**
 * Progress Agentic RAG bootstrap.
 *
 * @package ProgressAgenticRag
 *
 * @wordpress-plugin
 * Plugin Name:       Progress Agentic RAG connector
 * Plugin URI:        https://github.com/nuclia/wordpress-plugin
 * Description:       Index WordPress content into Progress Agentic RAG and power knowledge-base search.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            Progress Software
 * Author URI:        https://www.progress.com/
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       progress-agentic-rag-connector
 * Domain Path:       /languages
 */

use ProgressAgenticRag\Autoloader;
use ProgressAgenticRag\Infrastructure\Requirements;
use ProgressAgenticRag\Lifecycle\Activator;
use ProgressAgenticRag\Lifecycle\Deactivator;
use ProgressAgenticRag\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Public plugin constants already use the Progress Agentic RAG prefix.
define( 'PROGRESS_AGENTIC_RAG_VERSION', '1.0.0' );
define( 'PROGRESS_AGENTIC_RAG_MIN_PHP_VERSION', '8.1' );
define( 'PROGRESS_AGENTIC_RAG_MIN_WP_VERSION', '6.8' );
define( 'PROGRESS_AGENTIC_RAG_FILE', __FILE__ );
define( 'PROGRESS_AGENTIC_RAG_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROGRESS_AGENTIC_RAG_URL', plugin_dir_url( __FILE__ ) );
define( 'PROGRESS_AGENTIC_RAG_BASENAME', plugin_basename( __FILE__ ) );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

require_once PROGRESS_AGENTIC_RAG_PATH . 'src/Autoloader.php';

Autoloader::register();

register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Deactivator::class, 'deactivate' ] );

if ( ! Requirements::is_supported() ) {
	add_action( 'admin_notices', [ Requirements::class, 'render_admin_notice' ] );
	return;
}

require_once PROGRESS_AGENTIC_RAG_PATH . 'vendor/action-scheduler/action-scheduler.php';

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->register();
	}
);
