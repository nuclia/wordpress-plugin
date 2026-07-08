<?php
/**
 * Admin page tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Admin\AdminPage;
use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Settings\SettingsRepository;

final class AdminPageTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_options'] = [];
		$GLOBALS['progress_agentic_rag_test_http_requests'] = [];
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
		$GLOBALS['progress_agentic_rag_test_http_post_responses'] = [];
		$GLOBALS['progress_agentic_rag_test_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_enqueued_styles'] = [];
		$GLOBALS['progress_agentic_rag_test_enqueued_scripts'] = [];
		$GLOBALS['progress_agentic_rag_test_localized_scripts'] = [];
		$GLOBALS['progress_agentic_rag_test_menu_pages'] = [];
		$GLOBALS['progress_agentic_rag_test_safe_redirects'] = [];
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_taxonomies'] = [];
		$GLOBALS['progress_agentic_rag_test_terms'] = [];
		$GLOBALS['progress_agentic_rag_test_post_terms'] = [];
		$GLOBALS['progress_agentic_rag_test_posts'] = [];
		$GLOBALS['progress_agentic_rag_test_post_type_objects'] = [
			'post'       => (object) [
				'labels' => (object) [
					'name'          => 'Posts',
					'singular_name' => 'Post',
				],
			],
			'page'       => (object) [
				'labels' => (object) [
					'name'          => 'Pages',
					'singular_name' => 'Page',
				],
			],
			'attachment' => (object) [
				'labels' => (object) [
					'name'          => 'Media',
					'singular_name' => 'Media',
				],
			],
		];
		$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
		$_GET = [];
		$_POST = [];

		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		update_option( SettingsRepository::OPTION_KBID, 'kb-123' );
		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
	}

	public function test_register_add_menu_and_enqueue_assets_localize_admin_state(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = (object) [
			'labels' => (object) [ 'name' => 'Categories' ],
		];
		$GLOBALS['progress_agentic_rag_test_terms']['category'] = [
			7 => (object) [
				'term_id' => 7,
				'name'    => 'Support',
			],
		];
		$admin = $this->admin_page();
		$admin->register();

		$actions = array_column( $GLOBALS['progress_agentic_rag_test_actions'], 'hook_name' );

		self::assertContains( 'admin_menu', $actions );
		self::assertContains( 'admin_enqueue_scripts', $actions );
		self::assertContains( 'wp_ajax_progress_agentic_rag_manual_sync_start', $actions );
		self::assertContains( 'wp_ajax_progress_agentic_rag_get_labelset_labels', $actions );

		$admin->add_menu();
		$admin->enqueue_assets( 'wrong-hook' );

		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_enqueued_styles'] );

		$admin->enqueue_assets( $GLOBALS['progress_agentic_rag_test_menu_pages'][0]['hook'] );

		self::assertSame( 'progress-agentic-rag-admin', $GLOBALS['progress_agentic_rag_test_enqueued_styles'][0]['handle'] );
		self::assertSame( 'progress-agentic-rag-admin', $GLOBALS['progress_agentic_rag_test_enqueued_scripts'][0]['handle'] );
		self::assertSame( 'progressAgenticRagAdmin', $GLOBALS['progress_agentic_rag_test_localized_scripts'][0]['object_name'] );
		self::assertArrayHasKey( 'initialSyncStatus', $GLOBALS['progress_agentic_rag_test_localized_scripts'][0]['l10n'] );
		self::assertArrayHasKey( 'mapping', $GLOBALS['progress_agentic_rag_test_localized_scripts'][0]['l10n'] );
		self::assertSame( 'Support', $GLOBALS['progress_agentic_rag_test_localized_scripts'][0]['l10n']['mapping']['taxonomies']['category']['terms'][0]['name'] );
		self::assertStringNotContainsString( 'secret-token', wp_json_encode( $GLOBALS['progress_agentic_rag_test_localized_scripts'][0]['l10n'] ) );
	}

	public function test_render_connection_tab_does_not_output_saved_token(): void {
		$_GET['tab'] = 'connection';

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertStringContainsString( 'Connection settings', $output );
		self::assertStringContainsString( 'Token saved', $output );
		self::assertStringContainsString( 'Stored token is never displayed', $output );
		self::assertStringNotContainsString( 'secret-token', $output );
	}

	public function test_render_taxonomy_labeling_tab_and_notices(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = (object) [
			'labels' => (object) [ 'name' => 'Categories' ],
		];
		$GLOBALS['progress_agentic_rag_test_terms']['category'] = [
			7 => (object) [
				'term_id' => 7,
				'name'    => 'Support',
			],
		];
		update_option(
			SettingsRepository::OPTION_TAXONOMY_LABEL_MAP,
			[
				'category' => [
					'labelset' => 'Topic',
					'terms'    => [
						7 => [ 'Docs' ],
					],
					'fallback' => [
						'labelset' => 'Audience',
						'labels'   => [ 'General' ],
					],
				],
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [ 'code' => 200 ],
				'body'     => '{"labelsets":["Topic","Audience"]}',
			],
			[
				'response' => [ 'code' => 200 ],
				'body'     => '{"labels":["Docs"]}',
			],
			[
				'response' => [ 'code' => 200 ],
				'body'     => '{"labels":["General"]}',
			],
		];
		$_GET = [
			'tab'                         => 'taxonomy-labeling',
			'progress-agentic-rag-updated' => 'true',
			'connection-status'           => 'connected',
		];

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertStringContainsString( 'Taxonomy labeling', $output );
		self::assertStringContainsString( 'Categories', $output );
		self::assertStringContainsString( 'Connection settings saved.', $output );
	}

	public function test_render_defaults_to_indexation_for_unknown_tab_and_blocks_unauthorized_user(): void {
		$_GET['tab'] = 'unknown';

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertStringContainsString( 'Indexation', $output );
		self::assertStringContainsString( 'Sync manually', $output );

		$GLOBALS['progress_agentic_rag_test_current_user_can'] = false;
		ob_start();
		$this->admin_page()->render();

		self::assertSame( '', ob_get_clean() );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_render_indexation_running_states_and_notices(): void {
		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'id'        => 'sync-running',
				'status'    => 'running',
				'total'     => 5,
				'completed' => 2,
				'failed'    => 0,
			]
		);
		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-running',
				'status'  => 'running',
				'total'   => 4,
				'deleted' => 1,
				'failed'  => 0,
			]
		);
		$_GET = [
			'progress-agentic-rag-updated' => 'true',
			'connection-status'           => 'failed',
		];
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertStringContainsString( 'Sync in progress (2/5, 40%)', $output );
		self::assertStringContainsString( 'Delete in progress (1/4, 25%)', $output );
		self::assertStringContainsString( 'could not validate the connection', $output );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_render_default_updated_notice(): void {
		$_GET = [
			'progress-agentic-rag-updated' => 'true',
		];
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;

		ob_start();
		$this->admin_page()->render();

		self::assertStringContainsString( 'Settings saved.', ob_get_clean() );
	}

	public function test_admin_helpers_cover_connection_labels_and_mapping_edges(): void {
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );
		update_option( SettingsRepository::OPTION_TOKEN, '' );

		$admin = $this->admin_page();
		$status_ref = new ReflectionMethod( $admin, 'connection_status_label' );
		$status_ref->setAccessible( true );

		self::assertSame( 'Not configured', $status_ref->invoke( $admin ) );

		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );

		self::assertSame( 'Connection failed', $status_ref->invoke( $admin ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"labels":["General"]}',
		];

		$labels_ref = new ReflectionMethod( $admin, 'labelset_labels_for_mapping' );
		$labels_ref->setAccessible( true );

		self::assertSame(
			[
				'Audience' => [ 'General' ],
			],
			$labels_ref->invoke(
				$admin,
				[
					'bad'      => 'skip',
					'category' => [
						'fallback' => [
							'labelset' => 'Audience',
						],
					],
				]
			)
		);
	}

	public function test_save_connection_settings_normalizes_validates_and_redirects(): void {
		$_POST = [
			SettingsRepository::OPTION_ZONE       => 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123',
			SettingsRepository::OPTION_KBID       => 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123',
			SettingsRepository::OPTION_ACCOUNT_ID => 'account-123',
			SettingsRepository::OPTION_TOKEN      => 'Bearer new-token',
		];

		try {
			$this->admin_page()->save_connection_settings();
			self::fail( 'Expected redirect.' );
		} catch ( ProgressAgenticRagTestRedirect $redirect ) {
			self::assertStringContainsString( 'tab=connection', $redirect->location );
			self::assertStringContainsString( 'connection-status=connected', $redirect->location );
		}

		self::assertSame( 'europe-1', get_option( SettingsRepository::OPTION_ZONE ) );
		self::assertSame( 'kb-123', get_option( SettingsRepository::OPTION_KBID ) );
		self::assertSame( 'new-token', get_option( SettingsRepository::OPTION_TOKEN ) );
		self::assertSame( 'yes', get_option( SettingsRepository::OPTION_API_IS_REACHABLE ) );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( 'Bearer new-token', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['headers']['X-NUCLIA-SERVICEACCOUNT'] );
	}

	public function test_save_handlers_die_for_unauthorized_user(): void {
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = false;

		$this->expectException( ProgressAgenticRagTestWpDie::class );

		$this->admin_page()->save_connection_settings();
	}

	public function test_other_save_handlers_die_for_unauthorized_user(): void {
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = false;

		foreach ( [ 'save_indexation_settings', 'save_taxonomy_labeling_settings' ] as $method ) {
			try {
				$this->admin_page()->{$method}();
				self::fail( 'Expected wp_die.' );
			} catch ( ProgressAgenticRagTestWpDie $exception ) {
				self::assertStringContainsString( 'permission', $exception->getMessage() );
			}
		}
	}

	public function test_save_connection_settings_marks_failed_validation(): void {
		$GLOBALS['progress_agentic_rag_test_http_post_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{}',
		];
		$_POST = [
			SettingsRepository::OPTION_ZONE       => 'bad zone',
			SettingsRepository::OPTION_KBID       => 'kb-123',
			SettingsRepository::OPTION_ACCOUNT_ID => '',
			SettingsRepository::OPTION_TOKEN      => 'new-token',
		];

		try {
			$this->admin_page()->save_connection_settings();
			self::fail( 'Expected redirect.' );
		} catch ( ProgressAgenticRagTestRedirect $redirect ) {
			self::assertStringContainsString( 'connection-status=failed', $redirect->location );
		}

			self::assertSame( 'no', get_option( SettingsRepository::OPTION_API_IS_REACHABLE ) );

			$GLOBALS['progress_agentic_rag_test_http_post_responses'][] = new WP_Error( 'http_failed', 'Network failed' );
			$_POST = [
				SettingsRepository::OPTION_ZONE       => 'europe-1',
				SettingsRepository::OPTION_KBID       => 'kb-123',
				SettingsRepository::OPTION_ACCOUNT_ID => '',
				SettingsRepository::OPTION_TOKEN      => 'new-token',
			];

			try {
				$this->admin_page()->save_connection_settings();
				self::fail( 'Expected redirect.' );
			} catch ( ProgressAgenticRagTestRedirect $redirect ) {
				self::assertStringContainsString( 'connection-status=failed', $redirect->location );
			}
		}

		public function test_save_indexation_settings_keeps_allowed_unique_post_types(): void {
		$_POST = [
			'indexable_post_types' => [ 'post', 'page', 'bad<script>', 'post' ],
		];

		try {
			$this->admin_page()->save_indexation_settings();
			self::fail( 'Expected redirect.' );
		} catch ( ProgressAgenticRagTestRedirect $redirect ) {
			self::assertStringContainsString( 'tab=indexation', $redirect->location );
		}

		self::assertSame(
			[
				'post' => 1,
				'page' => 1,
			],
			get_option( SettingsRepository::OPTION_INDEXABLE_POST_TYPES )
		);
	}

	public function test_save_taxonomy_labeling_settings_sanitizes_map(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = true;
		$GLOBALS['progress_agentic_rag_test_terms']['category'] = [ 7 => true ];
		$_POST = [
			SettingsRepository::OPTION_TAXONOMY_LABEL_MAP => [
				'category' => [
					'labelset' => 'Topic',
					'terms'    => [
						7 => [ '<b>Support</b>' ],
					],
				],
			],
		];

		try {
			$this->admin_page()->save_taxonomy_labeling_settings();
			self::fail( 'Expected redirect.' );
		} catch ( ProgressAgenticRagTestRedirect $redirect ) {
			self::assertStringContainsString( 'tab=taxonomy-labeling', $redirect->location );
		}

		self::assertSame(
			[
				'category' => [
					'labelset' => 'Topic',
					'terms'    => [
						7 => [ 'Support' ],
					],
				],
			],
			get_option( SettingsRepository::OPTION_TAXONOMY_LABEL_MAP )
		);
	}

	public function test_ajax_handlers_return_json_success_and_errors(): void {
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = false;

		try {
			$this->admin_page()->manual_sync_status();
			self::fail( 'Expected JSON error.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertFalse( $response->success );
			self::assertSame( 403, $response->status );
		}

		$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;

		try {
			$this->admin_page()->manual_sync_status();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 'idle', $response->data['status'] );
		}

		try {
			$this->admin_page()->background_sync_status();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertArrayHasKey( 'status', $response->data );
		}

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"labels":["One","Two"]}',
		];
		$_POST['labelset'] = 'Topic';

		try {
			$this->admin_page()->get_labelset_labels();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( [ 'One', 'Two' ], $response->data['labels'] );
		}
	}

	public function test_ajax_start_manual_sync_success_and_error_paths(): void {
		$_POST['post_types'] = [ 'post', 'bad<script>' ];

		try {
			$this->admin_page()->start_manual_sync();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 'complete', $response->data['status'] );
		}

		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'status' => 'running',
			]
		);

		try {
			$this->admin_page()->start_manual_sync();
			self::fail( 'Expected JSON error.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertFalse( $response->success );
			self::assertSame( 400, $response->status );
		}
	}

	public function test_ajax_delete_and_label_handlers_return_statuses(): void {
		try {
			$this->admin_page()->delete_synced_resources_status();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 'idle', $response->data['status'] );
		}

		try {
			$this->admin_page()->delete_synced_resources();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 'complete', $response->data['status'] );
		}

		try {
			$this->admin_page()->start_label_reprocess();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 0, $response->data['scheduled'] );
		}

		try {
			$this->admin_page()->cancel_label_reprocess();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 'Label reprocessing cancelled.', $response->data['message'] );
		}

		try {
			$this->admin_page()->label_reprocess_status();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertArrayHasKey( 'synced', $response->data );
		}
	}

	public function test_ajax_delete_and_label_handlers_return_errors(): void {
		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'status' => 'running',
			]
		);

		try {
			$this->admin_page()->delete_synced_resources();
			self::fail( 'Expected JSON error.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertFalse( $response->success );
			self::assertSame( 400, $response->status );
		}

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [] );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		try {
			$this->admin_page()->start_label_reprocess();
			self::fail( 'Expected JSON error.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertFalse( $response->success );
			self::assertSame( 400, $response->status );
		}
	}

	public function test_ajax_handlers_return_unauthorized_errors(): void {
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = false;

		foreach (
			[
				'start_manual_sync',
				'delete_synced_resources_status',
				'background_sync_status',
				'delete_synced_resources',
				'start_label_reprocess',
				'cancel_label_reprocess',
				'label_reprocess_status',
				'get_labelset_labels',
			] as $method
		) {
			try {
				$this->admin_page()->{$method}();
				self::fail( 'Expected JSON error.' );
			} catch ( ProgressAgenticRagTestJsonResponse $response ) {
				self::assertFalse( $response->success );
				self::assertSame( 403, $response->status );
			}
		}
	}

	private function admin_page(): AdminPage {
		$settings = new SettingsRepository();
		$api_client = new ApiClient( $settings );

		return new AdminPage(
			$settings,
			new ManualSync( $settings, $api_client ),
			$api_client
		);
	}
}
