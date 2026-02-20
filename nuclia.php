<?php
/**
 * Plugin Name:       Progress Agentic RAG
 * Plugin URI:        https://github.com/nuclia/wordpress-plugin
 * Description:       Integrate the powerful Progress Agentic RAG service with WordPress
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            Progress Software
 * Author URI:        https://www.progress.com/
 * Contributors:      Serge Rauber, Radek Friedl
 * License:           GNU General Public License v2.0 / MIT License
 * Text Domain:       progress-agentic-rag
 * Domain Path:       /languages
 *
 * @since   1.0.0
 * @package nuclia\wordpress-plugin
 */


// Nothing to see here if not loaded in WP context.
if ( ! defined( 'WPINC' ) ) die;


// The Nuclia Search plugin version.
define( 'PROGRESS_NUCLIA_VERSION', '1.0.0' );

// The minimum required PHP version.
define( 'PROGRESS_NUCLIA_MIN_PHP_VERSION', '8.1' );

// The minimum required WordPress version.
define( 'PROGRESS_NUCLIA_MIN_WP_VERSION', '6.8' );


define( 'PROGRESS_NUCLIA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PROGRESS_NUCLIA_PLUGIN_URL', plugins_url( '/', __FILE__ ) );
define( 'PROGRESS_NUCLIA_PATH', __DIR__ . '/' );


/**
 * Check for required PHP version.
 *
 * @since   1.0.0
 *
 * @return bool
 */
function nuclia_php_version_check(): bool {
	if ( version_compare( PHP_VERSION, PROGRESS_NUCLIA_MIN_PHP_VERSION, '<' ) ) {
		return false;
	}
	return true;
}


/**
 * Check for required WordPress version.
 *
 * @since   1.0.0
 *
 * @return bool
 */
function nuclia_wp_version_check(): bool {
	if ( version_compare( $GLOBALS['wp_version'], PROGRESS_NUCLIA_MIN_WP_VERSION, '<' ) ) {
		return false;
	}
	return true;
}


/**
 * Admin notices if requirements aren't met.
 *
 * @since   1.0.0
 */
function nuclia_requirements_error_notice(): void {
	$notices = [];
	if ( ! nuclia_php_version_check() ) {
		$notices[] = sprintf(
			/* translators: placeholder 1 is minimum required PHP version, placeholder 2 is installed PHP version. */
			esc_html__(
                'Nuclia plugin requires PHP %1$s or higher. You’re still on %2$s.',
                'progress-agentic-rag'
            ),
			PROGRESS_NUCLIA_MIN_PHP_VERSION,
			PHP_VERSION
		);

	}

	if ( ! nuclia_wp_version_check() ) {
		$notices[] = sprintf(
			/* translators: placeholder 1 is minimum required WordPress version,
			placeholder 2 is installed WordPress version. */
			esc_html__(
                'Nuclia plugin requires at least WordPress in version %1$s, You are on %2$s.',
                'progress-agentic-rag'
            ),
			PROGRESS_NUCLIA_MIN_WP_VERSION,
			esc_html( $GLOBALS['wp_version'] )
		);
	}

	foreach ( $notices as $notice ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $notice ) . '</p></div>';
	}
}


/**
 * I18n.
 *
 * @since   1.0.0
 */
function nuclia_load_textdomain(): void {
	load_plugin_textdomain(
        'progress-agentic-rag',
        false,
        plugin_basename( dirname( __FILE__ ) ) . '/languages/'
    );
}

add_action( 'init', 'nuclia_load_textdomain' );

/**
 * Debug log function
 *
 * @since   1.0.0
 *
 * @var string	$notice	The notice to log
 */
function nuclia_log( string $notice ): void {
	if ( true === WP_DEBUG ) {
		error_log("[PROGRESS AGENTIC RAG]: $notice\n" );
	};
}

function nuclia_error_log( string $notice ): void {
	error_log("[PROGRESS AGENTIC RAG]: $notice\n" );
}

// load plugin if requirements are met or display admin notice
if ( nuclia_php_version_check() && nuclia_wp_version_check() ) {
	// Load Action Scheduler library for background processing
	require_once PROGRESS_NUCLIA_PATH . 'includes/libraries/action-scheduler/action-scheduler.php';
	
	require_once PROGRESS_NUCLIA_PATH . 'includes/nuclia-proxy-rest.php';
	require_once PROGRESS_NUCLIA_PATH . 'includes/class-nuclia-api.php';
	require_once PROGRESS_NUCLIA_PATH . 'includes/class-nuclia-settings.php';
	require_once PROGRESS_NUCLIA_PATH . 'includes/class-nuclia-background-processor.php';
	require_once PROGRESS_NUCLIA_PATH . 'includes/class-nuclia-label-reprocessor.php';
	require_once PROGRESS_NUCLIA_PATH . 'includes/class-nuclia-plugin.php';
	if ( is_admin() ) {
		require_once PROGRESS_NUCLIA_PATH . 'includes/admin/class-nuclia-admin-page-settings.php';
	}
	$nuclia = Nuclia_Plugin_Factory::create();
} else {
	add_action( 'admin_notices', 'nuclia_requirements_error_notice' );
}

/**
 * Class Nuclia_Plugin_Factory
 *
 * Responsible for creating a shared instance of the main Nuclia_Plugin object.
 *
 * @since 1.0.0
 */
class Nuclia_Plugin_Factory {

	/**
	 * Create and return a shared instance of the Nuclia_Plugin.
	 *
	 * @since  1.0.0
	 *
	 * @return Nuclia_Plugin The shared plugin instance.
	 */
	public static function create(): Nuclia_Plugin {

		/**
		 * The static instance to share, else null.
		 *
		 * @since  1.0.0
		 *
		 * @var null|Nuclia_Plugin $plugin
		 */
		static $plugin = null;

		if ( null !== $plugin ) {
			return $plugin;
		}

		$plugin = new Nuclia_Plugin();

		return $plugin;
	}
}

function agentic_rag_for_wp_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $wpdb->get_charset_collate();
	$table_name = $wpdb->prefix . 'agentic_rag_for_wp';
	$sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id mediumint(9) NOT NULL,
        nuclia_rid varchar(255) NOT NULL,
        nuclia_seqid varchar(255),
        PRIMARY KEY  (id),
        INDEX idx_post_id (post_id),
        INDEX idx_nuclia_rid (nuclia_rid)
    ) $charset_collate;";
	dbDelta( $sql );

	// Register path-only proxy rewrite rule and flush so /nuclia-proxy/{zone} works.
	if ( function_exists( 'nuclia_proxy_add_rewrite_rules' ) ) {
		nuclia_proxy_add_rewrite_rules();
		flush_rewrite_rules();
	}
}

register_activation_hook( __FILE__, 'agentic_rag_for_wp_install' );