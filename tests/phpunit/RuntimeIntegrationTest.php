<?php
/**
 * Runtime integration tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Autoloader;
use ProgressAgenticRag\Indexing\Scheduler;
use ProgressAgenticRag\Infrastructure\Requirements;
use ProgressAgenticRag\Lifecycle\Activator;
use ProgressAgenticRag\Lifecycle\Deactivator;
use ProgressAgenticRag\Plugin;
use ProgressAgenticRag\Settings\SettingsRepository;

final class RuntimeIntegrationTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wp_version'] = '6.8';
		$GLOBALS['progress_agentic_rag_test_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_filters'] = [];
		$GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] = [];
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_options'] = [];
		$GLOBALS['progress_agentic_rag_test_rewrite_rules'] = [];
		$GLOBALS['progress_agentic_rag_test_db_delta'] = [];
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;
		$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
	}

	public function test_plugin_register_wires_runtime_hooks(): void {
		Plugin::instance()->register();

		$actions = array_column( $GLOBALS['progress_agentic_rag_test_actions'], 'hook_name' );
		$filters = array_column( $GLOBALS['progress_agentic_rag_test_filters'], 'hook_name' );

		self::assertContains( 'parse_request', $actions );
		self::assertContains( 'wp_enqueue_scripts', $actions );
		self::assertContains( 'progress_agentic_rag_manual_sync_post', $actions );
		self::assertContains( 'admin_menu', $actions );
		self::assertContains( 'query_vars', $filters );
		self::assertContains( 'redirect_canonical', $filters );
	}

	public function test_autoloader_registers_and_ignores_foreign_classes(): void {
		Autoloader::register();

		$load_ref = new ReflectionMethod( Autoloader::class, 'load' );
		$load_ref->setAccessible( true );
		$load_ref->invoke( null, 'Vendor\\ForeignClass' );

		self::assertTrue( true );
	}

	public function test_scheduler_wraps_action_scheduler_functions(): void {
		$scheduler = new Scheduler();

		self::assertSame( 'action_scheduler', $scheduler->backend() );
		self::assertTrue( $scheduler->available() );
		self::assertSame( 'Action Scheduler', $scheduler->backend_label() );
		self::assertSame( 'Background jobs are using Action Scheduler.', $scheduler->backend_message() );

		self::assertTrue( $scheduler->schedule_single_action( 123, 'progress_agentic_rag_test_hook', [ 'id' => 1 ], 'progress-agentic-rag-test' ) );
		self::assertSame( 1, $scheduler->count_actions( 'progress_agentic_rag_test_hook', 'progress-agentic-rag-test', 'pending' ) );
		self::assertCount( 1, $scheduler->scheduled_actions( 'progress_agentic_rag_test_hook', 'progress-agentic-rag-test', 'pending' ) );

		$scheduler->unschedule_all_actions( 'progress_agentic_rag_test_hook', null, 'progress-agentic-rag-test' );

		self::assertSame( 0, $scheduler->count_actions( 'progress_agentic_rag_test_hook', 'progress-agentic-rag-test', 'pending' ) );
	}

	public function test_requirements_report_wordpress_support_and_render_notice(): void {
		$GLOBALS['wp_version'] = '6.7';

		self::assertTrue( Requirements::is_php_supported() );
		self::assertFalse( Requirements::is_wp_supported() );
		self::assertFalse( Requirements::is_supported() );

		ob_start();
		Requirements::render_admin_notice();
		$output = ob_get_clean();

		self::assertStringContainsString( 'requires WordPress 6.8 or later', $output );

		$GLOBALS['progress_agentic_rag_test_current_user_can'] = false;
		ob_start();
			Requirements::render_admin_notice();
			self::assertSame( '', ob_get_clean() );

			$GLOBALS['wp_version'] = '6.8';
			$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;
			ob_start();
			Requirements::render_admin_notice();
			self::assertSame( '', ob_get_clean() );
		}

	public function test_deactivation_flushes_soft_rewrite_rules(): void {
		Deactivator::deactivate();

		self::assertSame( [ false ], $GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] );
	}

	public function test_activation_adds_defaults_table_rewrite_rules_and_flushes(): void {
		$upgrade_dir = ABSPATH . 'wp-admin/includes';
		$upgrade_file = $upgrade_dir . '/upgrade.php';

		if ( ! is_dir( $upgrade_dir ) ) {
			mkdir( $upgrade_dir, 0777, true );
		}
		file_put_contents( $upgrade_file, "<?php\n" );

		try {
			Activator::activate();
		} finally {
			@unlink( $upgrade_file );
			@rmdir( $upgrade_dir );
			@rmdir( dirname( $upgrade_dir ) );
		}

		self::assertArrayHasKey( SettingsRepository::OPTION_ZONE, $GLOBALS['progress_agentic_rag_test_options'] );
		self::assertStringContainsString( 'CREATE TABLE wp_agentic_rag_for_wp', $GLOBALS['progress_agentic_rag_test_db_delta'][0] );
		self::assertSame( '^nuclia-proxy/([a-z0-9-]+)/?(.*)?$', $GLOBALS['progress_agentic_rag_test_rewrite_rules'][0]['regex'] );
		self::assertSame( [ false ], $GLOBALS['progress_agentic_rag_test_flushed_rewrite_rules'] );
	}
}
