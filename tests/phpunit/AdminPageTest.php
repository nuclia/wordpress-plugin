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
		self::assertContains( 'wp_ajax_progress_agentic_rag_test_connection', $actions );
		self::assertContains( 'wp_ajax_progress_agentic_rag_export_diagnostics', $actions );
		self::assertContains( 'wp_ajax_progress_agentic_rag_delete_synced_resource', $actions );

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

	public function test_render_new_admin_tabs_and_synced_content_without_token(): void {
		$settings = new SettingsRepository();
		$settings->add_sync_history_entry(
			[
				'type'        => 'manual_sync',
				'status'      => 'failed',
				'total'       => 2,
				'completed'   => 1,
				'failed'      => 1,
				'message'     => 'Manual sync complete.',
				'finished_at' => 20,
			]
		);
		$settings->add_failed_sync_item( 31, 'post', 'Post: Failed Article', 'Rejected upstream.', 'manual' );
		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'      => '71',
				'nuclia_rid'   => 'rid-71',
				'nuclia_seqid' => 'seq-71',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][71] = new WP_Post(
			[
				'ID'          => 71,
				'post_title'  => 'Synced Article',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		$_GET['tab'] = 'sync-history';

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertStringContainsString( 'Sync history', $output );
		self::assertStringContainsString( 'Queue health', $output );
		self::assertStringContainsString( 'Post: Failed Article', $output );
		self::assertStringContainsString( 'assets/img/progress-logo.svg', $output );
		self::assertStringContainsString( '<h1>Agentic RAG</h1>', $output );
		self::assertStringNotContainsString( '<h1>Progress Agentic RAG</h1>', $output );
		self::assertLessThan( strpos( $output, 'Taxonomy labeling' ), strpos( $output, 'Indexation' ) );
		self::assertLessThan( strpos( $output, 'Synchronized content' ), strpos( $output, 'Taxonomy labeling' ) );

		$_GET['tab'] = 'synced-content';
		ob_start();
		$this->admin_page()->render();
		$output .= ob_get_clean();

		self::assertStringContainsString( 'Synchronized content', $output );
		self::assertStringContainsString( 'Synced Article', $output );
		self::assertStringContainsString( 'rid-71', $output );
		self::assertStringContainsString( 'Remove from sync', $output );

		foreach ( [ 'search-widget' => 'Search widget', 'diagnostics' => 'Diagnostics' ] as $tab => $heading ) {
			$_GET['tab'] = $tab;
			ob_start();
			$this->admin_page()->render();
			$tab_output = ob_get_clean();
			$output .= $tab_output;
			self::assertStringContainsString( $heading, $output );
			if ( 'search-widget' === $tab ) {
				self::assertStringContainsString( 'Open Progress Agentic RAG dashboard', $tab_output );
				self::assertStringContainsString( 'Gutenberg block', $tab_output );
				self::assertStringContainsString( 'Elementor widget', $tab_output );
				self::assertStringContainsString( 'Widget options', $tab_output );
				self::assertStringContainsString( '[progress_agentic_rag_search features=&quot;answers,rephrase,filter,suggestions&quot;]', $tab_output );
				self::assertStringContainsString( 'answers, rephrase, filter, suggestions', $tab_output );
				self::assertStringNotContainsString( 'Automatic front page', $tab_output );
				self::assertStringNotContainsString( 'Manual embed code', $tab_output );
				self::assertStringNotContainsString( 'apikey', $tab_output );
			} elseif ( 'diagnostics' === $tab ) {
				self::assertStringContainsString( 'Sync failures', $tab_output );
				self::assertStringContainsString( 'Post: Failed Article', $tab_output );
				self::assertStringContainsString( 'Rejected upstream.', $tab_output );
				self::assertStringNotContainsString( 'secret-token', $tab_output );
			}
		}

		self::assertStringNotContainsString( 'Dashboard', $output );
		self::assertStringNotContainsString( 'secret-token', $output );
	}

	public function test_active_tab_defaults_to_connection_when_plugin_is_not_configured(): void {
		update_option( SettingsRepository::OPTION_ZONE, '' );
		update_option( SettingsRepository::OPTION_KBID, '' );
		update_option( SettingsRepository::OPTION_TOKEN, '' );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		$active_tab = new ReflectionMethod( AdminPage::class, 'active_tab' );

		self::assertSame( 'connection', $active_tab->invoke( $this->admin_page() ) );
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

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_render_connection_tab_does_not_overwrite_saved_connection_status(): void {
		$_GET['tab'] = 'connection';
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 401,
			],
			'body'     => '',
		];

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertSame( 'yes', get_option( SettingsRepository::OPTION_API_IS_REACHABLE ) );
		self::assertStringContainsString( 'Connected', $output );
		self::assertStringContainsString( 'Token saved', $output );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );
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
	public function test_render_indexation_hides_completed_failed_automatic_sync_status(): void {
		$GLOBALS['progress_agentic_rag_test_current_user_can'] = true;
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-failed',
				'status'    => 'complete',
				'total'     => 1,
				'completed' => 0,
				'failed'    => 1,
				'current'   => 'Post: Failed Article: Progress Agentic RAG rejected the resource.',
				'message'   => 'Automatic background sync finished with failures.',
			]
		);
		( new SettingsRepository() )->add_failed_sync_item( 31, 'post', 'Post: Failed Article', 'Progress Agentic RAG rejected the resource.', 'automatic' );

		ob_start();
		$this->admin_page()->render();
		$output = ob_get_clean();

		self::assertStringContainsString( 'data-progress-agentic-rag-background-sync hidden', $output );
		self::assertStringNotContainsString( 'progress-agentic-rag__indexation-layout--has-background', $output );
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
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		update_option( SettingsRepository::OPTION_TOKEN, '' );

		$admin = $this->admin_page();
		$status_ref = new ReflectionMethod( $admin, 'connection_status_label' );
		$status_ref->setAccessible( true );

		self::assertSame( 'Not configured', $status_ref->invoke( $admin ) );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );
		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );

		self::assertSame( 'Stored, unverified', $status_ref->invoke( $admin ) );

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
		self::assertSame( 'GET', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
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

			$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Network failed' );
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

	public function test_save_indexation_settings_cancels_removed_post_type_jobs(): void {
		update_option( SettingsRepository::OPTION_INDEXABLE_POST_TYPES, [ 'post' => 1, 'page' => 1 ] );
		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'id'        => 'sync-current',
				'status'    => 'running',
				'total'     => 2,
				'completed' => 0,
				'failed'    => 0,
			]
		);
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-current',
				'status'    => 'running',
				'total'     => 2,
				'completed' => 0,
				'failed'    => 0,
			]
		);
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [
			[
				'hook'   => 'progress_agentic_rag_manual_sync_post',
				'args'   => [ 'post_id' => 31, 'post_type' => 'post', 'sync_id' => 'sync-current' ],
				'group'  => 'progress-agentic-rag-indexing',
				'status' => 'pending',
			],
			[
				'hook'   => 'progress_agentic_rag_manual_sync_post',
				'args'   => [ 'post_id' => 32, 'post_type' => 'page', 'sync_id' => 'sync-current' ],
				'group'  => 'progress-agentic-rag-indexing',
				'status' => 'pending',
			],
			[
				'hook'   => 'progress_agentic_rag_background_sync_post',
				'args'   => [ 'post_id' => 33, 'post_type' => 'post', 'background_id' => 'background-current' ],
				'group'  => 'progress-agentic-rag-background',
				'status' => 'pending',
			],
			[
				'hook'   => 'progress_agentic_rag_background_sync_post',
				'args'   => [ 'post_id' => 34, 'post_type' => 'page', 'background_id' => 'background-current' ],
				'group'  => 'progress-agentic-rag-background',
				'status' => 'pending',
			],
		];
		$_POST = [
			'indexable_post_types' => [ 'page' ],
		];

		try {
			$this->admin_page()->save_indexation_settings();
			self::fail( 'Expected redirect.' );
		} catch ( ProgressAgenticRagTestRedirect $redirect ) {
			self::assertStringContainsString( 'tab=indexation', $redirect->location );
		}

		self::assertSame( [ 'page' => 1 ], get_option( SettingsRepository::OPTION_INDEXABLE_POST_TYPES ) );
		self::assertSame( [ 'page', 'page' ], array_column( array_column( $GLOBALS['progress_agentic_rag_test_scheduled_actions'], 'args' ), 'post_type' ) );
		self::assertSame( 1, get_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE )['total'] );
		self::assertSame( 1, get_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE )['total'] );
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

	public function test_ajax_connection_retry_delete_and_diagnostics(): void {
		$_POST = [
			SettingsRepository::OPTION_ZONE  => 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123',
			SettingsRepository::OPTION_KBID  => 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123',
			SettingsRepository::OPTION_TOKEN => 'Bearer ajax-token',
		];

		try {
			$this->admin_page()->test_connection();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertTrue( $response->data['connected'] );
			self::assertSame( 'GET', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );
			self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
			self::assertSame( 'Bearer ajax-token', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['headers']['X-NUCLIA-SERVICEACCOUNT'] );
		}

		$settings = new SettingsRepository();
		$GLOBALS['progress_agentic_rag_test_posts'][71] = new WP_Post(
			[
				'ID'          => 71,
				'post_title'  => 'Retry Article',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$settings->add_failed_sync_item( 71, 'post', 'Post: Retry Article', 'Rejected upstream.', 'automatic' );
		$_POST = [];

		try {
			$this->admin_page()->retry_failed_sync();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 1, $response->data['scheduled'] );
		}

		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'      => '71',
				'nuclia_rid'   => 'rid-71',
				'nuclia_seqid' => 'seq-71',
			],
		];
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 204,
			],
			'body'     => '',
		];
		$_POST = [ 'post_id' => '71' ];

		try {
			$this->admin_page()->delete_synced_resource();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertSame( 71, $response->data['post_id'] );
			$request = end( $GLOBALS['progress_agentic_rag_test_http_requests'] );
			self::assertSame( 'DELETE', $request['args']['method'] );
			self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-71', $request['url'] );
			self::assertSame( [ 'post_id' => 71 ], $GLOBALS['wpdb']->deleted[0]['where'] );
		}

		$settings->add_failed_sync_item( 72, 'post', 'Post: Failed Diagnostic Article', 'Progress Agentic RAG rejected the resource.', 'automatic' );
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'status'  => 'complete',
				'total'   => 1,
				'failed'  => 1,
				'current' => 'Post: Failed Diagnostic Article: Progress Agentic RAG rejected the resource.',
			]
		);

		try {
			$this->admin_page()->export_diagnostics();
			self::fail( 'Expected JSON success.' );
		} catch ( ProgressAgenticRagTestJsonResponse $response ) {
			self::assertTrue( $response->success );
			self::assertArrayHasKey( 'diagnostics', $response->data );
			self::assertSame( 1, $response->data['diagnostics']['indexation']['background_sync']['failed'] );
			self::assertSame( 'Post: Failed Diagnostic Article', $response->data['diagnostics']['indexation']['failed_items'][0]['label'] );
			self::assertSame( 'Progress Agentic RAG rejected the resource.', $response->data['diagnostics']['indexation']['failed_items'][0]['message'] );
			self::assertStringNotContainsString( 'secret-token', wp_json_encode( $response->data ) );
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
				'test_connection',
				'retry_failed_sync',
				'delete_synced_resource',
				'export_diagnostics',
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
