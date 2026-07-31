<?php
/**
 * Manual sync service tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Settings\SettingsRepository;

final class ManualSyncTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_options']        = [];
		$GLOBALS['progress_agentic_rag_test_http_requests']  = [];
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
		$GLOBALS['progress_agentic_rag_test_posts']          = [];
		$GLOBALS['progress_agentic_rag_test_post_type_objects'] = [
			'post' => (object) [
				'labels' => (object) [
					'name'          => 'Posts',
					'singular_name' => 'Post',
				],
			],
		];
		$GLOBALS['progress_agentic_rag_test_post_terms']     = [];
		$GLOBALS['progress_agentic_rag_test_taxonomies']     = [];
		$GLOBALS['progress_agentic_rag_test_terms']          = [];
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_actions']        = [];
		$GLOBALS['wpdb']                                     = new ProgressAgenticRagTestWpdb();

		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		update_option( SettingsRepository::OPTION_KBID, 'kb-123' );
		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
	}

	public function test_register_accepts_background_progress_id_arguments(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$sync->register();

		$accepted_args = [];
		foreach ( $GLOBALS['progress_agentic_rag_test_actions'] as $action ) {
			$accepted_args[ $action['hook_name'] ] = $action['accepted_args'];
		}

		self::assertSame( 3, $accepted_args['progress_agentic_rag_background_sync_post'] );
		self::assertSame( 3, $accepted_args['progress_agentic_rag_background_delete_resource'] );
	}

	public function test_start_schedules_manual_sync_jobs_and_worker_indexes_post(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'ID' => '31',
			],
			(object) [
				'ID' => '32',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][31] = new WP_Post(
			[
				'ID'           => 31,
				'post_title'   => 'Manual Sync Article',
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => '<p>Body</p>',
			]
		);

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$result   = $sync->start( [ 'post' ] );

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 2, $result['total'] );
		self::assertSame( 0, $result['processed'] );
		self::assertSame( 'Waiting for the first entity.', $result['current'] );
		self::assertSame( 'Sync in progress: 0 of 2 processed.', $result['message'] );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_manual_sync_post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );
		self::assertSame( 'progress-agentic-rag-indexing', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['group'] );
		self::assertSame( 31, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
		self::assertSame( 'post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_type'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"uuid":"rid-31","seqid":"seq-31"}',
		];

		$sync->process_single_post(
			31,
			'post',
			$GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['sync_id']
		);

		$status  = $sync->status();
		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][1];

		self::assertSame( 1, $status['completed'] );
		self::assertSame( 'Post: Manual Sync Article', $status['current'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources?from=0&size=250', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( 'POST', $request['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources', $request['url'] );
		self::assertSame( 'rid-31', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
	}

	public function test_start_returns_existing_running_status_without_scheduling_duplicate_jobs(): void {
		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'id'        => 'sync-running',
				'status'    => 'running',
				'total'     => 3,
				'completed' => 1,
				'failed'    => 0,
				'current'   => 'Queued manual sync.',
				'message'   => 'Manual sync queued.',
			]
		);
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		$settings = new SettingsRepository();
		$result   = ( new ManualSync( $settings, new ApiClient( $settings ) ) )->start( [ 'post' ] );

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 'sync-running', $result['id'] );
		self::assertSame( 3, $result['total'] );
		self::assertSame( 1, $result['completed'] );
		self::assertSame( 1, $result['processed'] );
		self::assertSame( 'Waiting for the next entity.', $result['current'] );
		self::assertSame( 'Sync in progress: 1 of 3 processed.', $result['message'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
	}

	public function test_start_clears_stale_mapping_before_scheduling_manual_sync(): void {
		$GLOBALS['wpdb'] = new class() extends ProgressAgenticRagTestWpdb {
			private int $unindexed_calls = 0;

			public function get_results( string $query ): array {
				if ( str_contains( $query, 'post_id, nuclia_rid' ) ) {
					return [
						(object) [
							'post_id'    => '33',
							'nuclia_rid' => 'rid-stale',
						],
					];
				}

				$this->unindexed_calls++;
				return 1 === $this->unindexed_calls ? [ (object) [ 'ID' => '33' ] ] : [];
			}
		};
		$GLOBALS['progress_agentic_rag_test_posts'][33] = new WP_Post(
			[
				'ID'          => 33,
				'post_title'  => 'Stale Manual Article',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[]}',
			],
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[]}',
			],
		];

		$result = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->start( [ 'post' ] );

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 1, $result['total'] );
		self::assertSame( [ 'post_id' => 33 ], $GLOBALS['wpdb']->deleted[0]['where'] );
		self::assertSame( 33, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
	}

	public function test_background_post_sync_schedules_and_indexes_publishable_post(): void {
		$post = new WP_Post(
			[
				'ID'           => 41,
				'post_title'   => 'Background Sync Article',
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => '<p>Body</p>',
			]
		);
		$GLOBALS['progress_agentic_rag_test_posts'][41] = $post;

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$sync->schedule_background_post_sync( 41, $post, true );

		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_background_sync_post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );
		self::assertSame( 'progress-agentic-rag-background', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['group'] );
		self::assertSame( 41, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
		self::assertSame( 'post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_type'] );
		self::assertNotEmpty( $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['background_id'] );

		$status = $sync->background_sync_status();
		self::assertSame( 'running', $status['status'] );
		self::assertSame( 1, $status['total'] );
		self::assertSame( 0, $status['processed'] );
		self::assertSame( 'Post: Background Sync Article', $status['current'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"uuid":"rid-41","seqid":"seq-41"}',
		];

		$sync->process_background_post( 41, 'post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['background_id'] );
		$status = $sync->background_sync_status();

		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( 'POST', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( 'rid-41', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 1, $status['completed'] );
		self::assertSame( 100, $status['percent'] );
	}

	public function test_ensure_automatic_sync_schedules_selected_unindexed_content(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'ID' => '51',
			],
			(object) [
				'ID' => '52',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][51] = new WP_Post(
			[
				'ID'          => 51,
				'post_title'  => 'Automatic One',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_posts'][52] = new WP_Post(
			[
				'ID'          => 52,
				'post_title'  => 'Automatic Two',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$result   = $sync->ensure_automatic_sync();

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 2, $result['total'] );
		self::assertSame( 2, $result['pending'] );
		self::assertSame( 2, $result['scheduled'] );
		self::assertTrue( $result['is_active'] );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_background_sync_post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );
		self::assertSame( 'progress-agentic-rag-background', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['group'] );
		self::assertSame( 51, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
		self::assertSame( 52, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][1]['args']['post_id'] );
		self::assertSame( $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['background_id'], $GLOBALS['progress_agentic_rag_test_scheduled_actions'][1]['args']['background_id'] );
	}

	public function test_ensure_automatic_sync_recovers_existing_resources_before_scheduling(): void {
		$GLOBALS['wpdb'] = new class() extends ProgressAgenticRagTestWpdb {
			private int $get_results_calls = 0;

			public function get_results( string $query ): array {
				if ( str_contains( $query, 'post_id, nuclia_rid' ) ) {
					return [];
				}

				$this->get_results_calls++;

				return 1 === $this->get_results_calls ? [ (object) [ 'ID' => '53' ] ] : [];
			}
		};
		$GLOBALS['progress_agentic_rag_test_posts'][53] = new WP_Post(
			[
				'ID'          => 53,
				'post_title'  => 'Recovered Automatic',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"resources":[{"id":"rid-53","slug":"53","seqid":"seq-53"}]}',
		];

		$result = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->ensure_automatic_sync();

		self::assertSame( 0, $result['scheduled'] );
		self::assertSame( 'rid-53', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
	}

	public function test_ensure_automatic_sync_clears_stale_mapping_before_scheduling(): void {
		$GLOBALS['wpdb'] = new class() extends ProgressAgenticRagTestWpdb {
			private int $unindexed_calls = 0;

			public function get_results( string $query ): array {
				if ( str_contains( $query, 'post_id, nuclia_rid' ) ) {
					return [
						(object) [
							'post_id'    => '54',
							'nuclia_rid' => 'rid-stale',
						],
					];
				}

				$this->unindexed_calls++;
				return 1 === $this->unindexed_calls ? [ (object) [ 'ID' => '54' ] ] : [];
			}
		};
		$GLOBALS['progress_agentic_rag_test_posts'][54] = new WP_Post(
			[
				'ID'          => 54,
				'post_title'  => 'Stale Automatic Article',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[]}',
			],
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[]}',
			],
		];

		$result = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->ensure_automatic_sync();

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 1, $result['scheduled'] );
		self::assertSame( [ 'post_id' => 54 ], $GLOBALS['wpdb']->deleted[0]['where'] );
		self::assertSame( 54, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
	}

	public function test_ensure_automatic_sync_retries_stale_failed_state_without_failed_items(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'ID' => '54',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][54] = new WP_Post(
			[
				'ID'          => 54,
				'post_title'  => 'Retry Stale Automatic',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"resources":[]}',
		];
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'status' => 'complete',
				'total'  => 1,
				'failed' => 1,
			]
		);

		$result = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->ensure_automatic_sync();

		self::assertSame( 1, $result['scheduled'] );
		self::assertSame( 'running', $result['status'] );
		self::assertSame( 54, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
	}

	public function test_ensure_automatic_sync_does_not_duplicate_active_background_jobs(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'ID' => '51',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][51] = new WP_Post(
			[
				'ID'          => 51,
				'post_title'  => 'Automatic One',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][] = [
			'timestamp' => time(),
			'hook'      => 'progress_agentic_rag_background_sync_post',
			'args'      => [
				'post_id'       => 51,
				'post_type'     => 'post',
				'background_id' => 'existing-background',
			],
			'group'     => 'progress-agentic-rag-background',
			'status'    => 'pending',
		];
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'existing-background',
				'status'    => 'running',
				'total'     => 1,
				'completed' => 0,
				'failed'    => 0,
				'current'   => 'Post: Automatic One',
				'message'   => 'Automatic background sync queued.',
			]
		);

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$result   = $sync->ensure_automatic_sync();

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 1, $result['pending'] );
		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
	}

	public function test_background_failures_are_recorded_and_retryable(): void {
		$post = new WP_Post(
			[
				'ID'           => 61,
				'post_title'   => 'Retry Me',
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => '<p>Body</p>',
			]
		);
		$GLOBALS['progress_agentic_rag_test_posts'][61] = $post;
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Upstream rejected the resource."}',
		];

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$sync->schedule_background_post_sync( 61, $post, true );

		try {
			$sync->process_background_post( 61, 'post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['background_id'] );
			self::fail( 'Expected background failure.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'Upstream rejected', $exception->getMessage() );
		}

		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['status'] = 'failed';
		$status  = $sync->background_sync_status();
		$failed  = array_values( $settings->get_failed_sync_items() );
		$history = $settings->get_sync_history();

		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 1, $status['failed'] );
		self::assertFalse( $status['is_active'] );
		self::assertSame( 61, $failed[0]['post_id'] );
		self::assertSame( 'Post: Retry Me', $failed[0]['label'] );
		self::assertSame( 'automatic_sync', $history[0]['type'] );
		self::assertSame( 'failed', $history[0]['status'] );

		$retry = $sync->retry_failed_sync_items();

		self::assertSame( 1, $retry['scheduled'] );
		self::assertSame( [], $settings->get_failed_sync_items() );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_background_sync_post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][1]['hook'] );
		self::assertSame( 61, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][1]['args']['post_id'] );
	}

	public function test_background_sync_status_reconciles_completed_actions_for_current_progress_id(): void {
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][] = [
			'timestamp' => time(),
			'hook'      => 'progress_agentic_rag_background_sync_post',
			'args'      => [
				'post_id'       => 51,
				'post_type'     => 'post',
				'background_id' => 'background-current',
			],
			'group'     => 'progress-agentic-rag-background',
			'status'    => 'complete',
		];
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][] = [
			'timestamp' => time(),
			'hook'      => 'progress_agentic_rag_background_sync_post',
			'args'      => [
				'post_id'       => 52,
				'post_type'     => 'post',
				'background_id' => 'background-old',
			],
			'group'     => 'progress-agentic-rag-background',
			'status'    => 'complete',
		];
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-current',
				'status'    => 'running',
				'total'     => 2,
				'completed' => 0,
				'failed'    => 0,
				'current'   => 'Post: Automatic One',
				'message'   => 'Automatic background sync queued.',
			]
		);

		$settings = new SettingsRepository();
		$status   = ( new ManualSync( $settings, new ApiClient( $settings ) ) )->background_sync_status();
		$state    = get_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE );

		self::assertSame( 1, $status['completed'] );
		self::assertSame( 1, $status['processed'] );
		self::assertSame( 50, $status['percent'] );
		self::assertSame( 1, $state['completed'] );
	}

	public function test_background_post_sync_schedules_delete_for_existing_non_indexable_post(): void {
		$GLOBALS['wpdb']->var = 'rid-42';
		$post                 = new WP_Post(
			[
				'ID'          => 42,
				'post_title'  => 'Draft Article',
				'post_type'   => 'post',
				'post_status' => 'draft',
			]
		);

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$sync->schedule_background_post_sync( 42, $post, true );

		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_background_delete_resource', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );
		self::assertSame( 'progress-agentic-rag-background', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['group'] );
		self::assertSame( 42, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
		self::assertSame( 'rid-42', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['rid'] );
		self::assertNotEmpty( $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['background_id'] );
		self::assertSame( 1, $sync->background_sync_status()['total'] );
	}

	public function test_delete_synced_resources_deletes_each_upstream_resource(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'    => '11',
				'nuclia_rid' => 'rid-11',
			],
			(object) [
				'post_id'    => '12',
				'nuclia_rid' => 'rid-12',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][11] = new WP_Post(
			[
				'ID'         => 11,
				'post_title' => 'Delete One',
				'post_type'  => 'post',
			]
		);
		$GLOBALS['progress_agentic_rag_test_posts'][12] = new WP_Post(
			[
				'ID'         => 12,
				'post_title' => 'Delete Two',
				'post_type'  => 'post',
			]
		);
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][] = [
			'timestamp' => time(),
			'hook'      => 'progress_agentic_rag_reprocess_resource_labels',
			'args'      => [],
			'group'     => 'progress-agentic-rag-labels',
			'status'    => 'pending',
		];
		update_option(
			SettingsRepository::OPTION_TAXONOMY_LABEL_MAP,
			[
				'category' => [
					'labelset' => 'Topic',
				],
			]
		);
		update_option(
			SettingsRepository::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => 123,
				'labelsets'  => [ 'Topic' ],
				'labels'     => [
					'Topic' => [ 'Support' ],
				],
			]
		);

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$result   = $sync->delete_synced_resources();

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 2, $result['total'] );
		self::assertSame( 0, $result['deleted'] );
		self::assertSame( 0, $result['failed'] );
		self::assertSame( 'Delete in progress: 0 of 2 processed.', $result['message'] );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_delete_synced_resource', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );
		self::assertSame( 'progress-agentic-rag-delete', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['group'] );
		self::assertSame( 11, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
		self::assertSame( 'rid-11', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['rid'] );
		self::assertSame( 'progress_agentic_rag_delete_synced_resource', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][1]['hook'] );
		self::assertSame( [], get_option( SettingsRepository::OPTION_TAXONOMY_LABEL_MAP ) );
		self::assertSame( $settings->defaults()[ SettingsRepository::OPTION_LABELSETS_CACHE ], get_option( SettingsRepository::OPTION_LABELSETS_CACHE ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 204,
				],
				'body'     => '',
			],
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '',
			],
		];

		$delete_id = $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['delete_id'];
		$sync->process_single_delete( 11, 'rid-11', $delete_id );
		$sync->process_single_delete( 12, 'rid-12', $delete_id );
		$status = $sync->delete_status();

		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 2, $status['total'] );
		self::assertSame( 2, $status['deleted'] );
		self::assertSame( 0, $status['failed'] );
		self::assertSame( 'Post: Delete Two', $status['current'] );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( 'DELETE', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-11', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( [ 'post_id' => 11 ], $GLOBALS['wpdb']->deleted[0]['where'] );
		self::assertSame( [ 'post_id' => 12 ], $GLOBALS['wpdb']->deleted[1]['where'] );
	}

	public function test_delete_synced_resources_returns_existing_running_status_without_duplicates(): void {
		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-running',
				'status'  => 'running',
				'total'   => 4,
				'deleted' => 1,
				'failed'  => 0,
				'current' => 'Resource for entity #11',
				'message' => 'Delete in progress: 1 of 4 processed.',
			]
		);
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		$settings = new SettingsRepository();
		$result   = ( new ManualSync( $settings, new ApiClient( $settings ) ) )->delete_synced_resources();

		self::assertSame( 'running', $result['status'] );
		self::assertSame( 'delete-running', $result['id'] );
		self::assertSame( 4, $result['total'] );
		self::assertSame( 1, $result['deleted'] );
		self::assertSame( 1, $result['processed'] );
		self::assertSame( 'Resource for entity #11', $result['current'] );
		self::assertSame( 'Delete in progress: 1 of 4 processed.', $result['message'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
	}

	public function test_start_label_reprocess_schedules_jobs_and_worker_patches_labels(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'    => '21',
				'nuclia_rid' => 'rid-21',
			],
			(object) [
				'post_id'    => '22',
				'nuclia_rid' => 'rid-22',
			],
		];
		$GLOBALS['wpdb']->var                                      = 2;
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = true;
		$GLOBALS['progress_agentic_rag_test_post_terms'][21]         = [
			'category' => [ 7 ],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][21]              = new WP_Post(
			[
				'ID'           => 21,
				'post_title'   => 'Updated Labels',
				'post_content' => '<p>Body</p>',
			]
		);
		update_option(
			SettingsRepository::OPTION_TAXONOMY_LABEL_MAP,
			[
				'category' => [
					'labelset' => 'Topic',
					'terms'    => [
						7 => [ 'Support' ],
					],
				],
			]
		);

		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$result   = $sync->start_label_reprocess();

		self::assertSame( 2, $result['scheduled'] );
		self::assertSame( 2, $result['pending'] );
		self::assertSame( 2, $result['synced'] );
		self::assertTrue( $result['is_active'] );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'progress_agentic_rag_reprocess_resource_labels', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );
		self::assertSame( 'progress-agentic-rag-labels', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['group'] );
		self::assertSame( 21, $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['post_id'] );
		self::assertSame( 'rid-21', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['rid'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{}',
		];

		$sync->process_single_label_reprocess(
			21,
			'rid-21',
			$GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['reprocess_id']
		);

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];
		$body    = json_decode( $request['args']['body'], true );

		self::assertSame( 'PATCH', $request['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-21', $request['url'] );
		self::assertSame(
			[
				[
					'labelset' => 'Topic',
					'label'    => 'Support',
				],
			],
			$body['usermetadata']['classifications']
		);
	}

	public function test_start_returns_guard_errors_for_delete_connection_and_empty_selection(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );

		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'status' => 'running',
			]
		);

		self::assertInstanceOf( WP_Error::class, $sync->start( [ 'post' ] ) );

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [] );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		self::assertInstanceOf( WP_Error::class, $sync->start( [ 'post' ] ) );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );

		self::assertInstanceOf( WP_Error::class, $sync->start( [] ) );
	}

	public function test_status_completes_running_state_and_preserves_recovered_message(): void {
		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'id'        => 'sync-complete',
				'status'    => 'running',
				'total'     => 2,
				'completed' => 2,
				'failed'    => 0,
				'recovered' => 1,
				'current'   => '',
			]
		);

		$status = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->status();

		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 'Manual sync complete.', $status['message'] );
		self::assertSame( 100, $status['percent'] );
	}

	public function test_start_reports_recovered_existing_resources(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'ID' => '61',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][61] = new WP_Post(
			[
				'ID'          => 61,
				'post_title'  => 'Recovered Article',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"resources":[{"id":"rid-61","slug":"61","seqid":"seq-61"}]}',
		];

		$result = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->start( [ 'post' ] );

		self::assertSame( 1, $result['recovered'] );
		self::assertStringContainsString( 'Recovered 1 existing upstream resource.', $result['message'] );
		self::assertSame( 'rid-61', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
	}

	public function test_delete_synced_resources_guard_errors_and_empty_completion(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );

		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'status' => 'running',
			]
		);

		self::assertInstanceOf( WP_Error::class, $sync->delete_synced_resources() );

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [] );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		self::assertInstanceOf( WP_Error::class, $sync->delete_synced_resources() );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		$GLOBALS['wpdb']->results = [];

		$status = $sync->delete_synced_resources();

		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 'No synced resources to delete.', $status['current'] );
	}

	public function test_delete_status_completes_with_failure_message_and_default_current(): void {
		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-complete',
				'status'  => 'running',
				'total'   => 2,
				'deleted' => 1,
				'failed'  => 1,
				'current' => '',
			]
		);

		$status = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->delete_status();

		self::assertSame( 'complete', $status['status'] );
		self::assertStringContainsString( 'Some synced resources could not be deleted.', $status['message'] );
		self::assertSame( '', $status['current'] );
	}

	public function test_delete_status_reconciles_completed_actions_for_current_delete_id(): void {
		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-action-complete',
				'status'  => 'running',
				'total'   => 2,
				'deleted' => 0,
				'failed'  => 0,
				'current' => 'Waiting for delete.',
			]
		);
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [
			[
				'hook'   => 'progress_agentic_rag_delete_synced_resource',
				'args'   => [
					'post_id'   => 11,
					'rid'       => 'rid-11',
					'delete_id' => 'delete-action-complete',
				],
				'group'  => 'progress-agentic-rag-delete',
				'status' => 'complete',
			],
			[
				'hook'   => 'progress_agentic_rag_delete_synced_resource',
				'args'   => [
					'post_id'   => 12,
					'rid'       => 'rid-12',
					'delete_id' => 'delete-action-complete',
				],
				'group'  => 'progress-agentic-rag-delete',
				'status' => 'complete',
			],
		];

		$status = ( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->delete_status();

		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 2, $status['deleted'] );
		self::assertSame( 2, $status['processed'] );
		self::assertSame( 100, $status['percent'] );
		self::assertSame( 2, get_option( SettingsRepository::OPTION_DELETE_SYNC_STATE )['deleted'] );
	}

	public function test_label_reprocess_guard_errors_and_cancel(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		self::assertInstanceOf( WP_Error::class, $sync->start_label_reprocess() );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'status' => 'running',
			]
		);

		self::assertInstanceOf( WP_Error::class, $sync->start_label_reprocess() );

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [] );
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'][] = [
			'timestamp' => time(),
			'hook'      => 'progress_agentic_rag_reprocess_resource_labels',
			'args'      => [],
			'group'     => 'progress-agentic-rag-labels',
			'status'    => 'pending',
		];

		self::assertInstanceOf( WP_Error::class, $sync->start_label_reprocess() );
		self::assertSame( 'Label reprocessing cancelled.', $sync->cancel_label_reprocess()['message'] );
	}

	public function test_process_single_post_handles_mismatch_missing_and_already_indexed(): void {
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
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );

		$sync->process_single_post( 71, 'post', 'old-sync' );
		self::assertSame( 0, $sync->status()['processed'] );

		$sync->process_single_post( 71, 'post', 'sync-current' );
		self::assertSame( 1, $sync->status()['failed'] );

		$GLOBALS['progress_agentic_rag_test_posts'][72] = new WP_Post(
			[
				'ID'          => 72,
				'post_title'  => 'Already Indexed',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['wpdb']->var = 'rid-72';

		$sync->process_single_post( 72, 'post', 'sync-current' );

		self::assertSame( 1, $sync->status()['completed'] );
	}

	public function test_process_single_post_skips_disabled_post_type(): void {
		update_option(
			SettingsRepository::OPTION_MANUAL_SYNC_STATE,
			[
				'id'        => 'sync-current',
				'status'    => 'running',
				'total'     => 1,
				'completed' => 0,
				'failed'    => 0,
			]
		);
		update_option( SettingsRepository::OPTION_INDEXABLE_POST_TYPES, [ 'post' => 0 ] );
		$GLOBALS['progress_agentic_rag_test_posts'][73] = new WP_Post(
			[
				'ID'          => 73,
				'post_title'  => 'Disabled Type',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"uuid":"rid-73","seqid":"seq-73"}',
		];

		$sync = new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) );
		$sync->process_single_post( 73, 'post', 'sync-current' );
		$status = $sync->status();

		self::assertSame( 'complete', $status['status'] );
		self::assertSame( 1, $status['completed'] );
		self::assertSame( 0, $status['failed'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( [], $GLOBALS['wpdb']->inserted );
	}

	public function test_process_label_reprocess_and_delete_validate_inputs_and_failures(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );

		$sync->process_single_label_reprocess( 0, '', '' );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );

		$this->expectException( RuntimeException::class );
		$sync->process_single_label_reprocess( 81, 'rid-81', 'reprocess-1' );
	}

	public function test_process_label_reprocess_throws_sanitized_api_error(): void {
		$GLOBALS['progress_agentic_rag_test_posts'][82] = new WP_Post(
			[
				'ID'         => 82,
				'post_title' => 'Label Error',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"<b>Bad labels</b>"}',
		];

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Bad labels' );

		( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->process_single_label_reprocess( 82, 'rid-82', 'reprocess-1' );
	}

	public function test_process_single_delete_marks_invalid_and_api_failures(): void {
		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-current',
				'status'  => 'running',
				'total'   => 2,
				'deleted' => 0,
				'failed'  => 0,
			]
		);

		$sync = new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) );

		$sync->process_single_delete( 91, 'rid-91', 'old-delete' );
		self::assertSame( 0, $sync->delete_status()['processed'] );

		$sync->process_single_delete( 0, '', 'delete-current' );
		self::assertSame( 1, $sync->delete_status()['failed'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Delete failed"}',
		];
		$sync->process_single_delete( 91, 'rid-91', 'delete-current' );

		self::assertSame( 2, $sync->delete_status()['failed'] );
	}

	public function test_background_attachment_delete_and_workers_cover_success_and_error_paths(): void {
		$GLOBALS['progress_agentic_rag_test_posts'][101] = new WP_Post(
			[
				'ID'          => 101,
				'post_title'  => 'Attachment',
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
			]
		);
		$settings = new SettingsRepository();
		$settings->update_indexable_post_types( [ 'post', 'page', 'attachment' ] );
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );

		$sync->schedule_background_attachment_sync( 101 );
		self::assertSame( 'progress_agentic_rag_background_sync_post', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['hook'] );

		$GLOBALS['wpdb']->var = 'rid-101';
		$sync->schedule_background_delete( 101, $GLOBALS['progress_agentic_rag_test_posts'][101] );
		self::assertSame( 'progress_agentic_rag_background_delete_resource', $GLOBALS['progress_agentic_rag_test_scheduled_actions'][1]['hook'] );

		$background_id = $GLOBALS['progress_agentic_rag_test_scheduled_actions'][0]['args']['background_id'];
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"uuid":"rid-101","seqid":"seq-101"}',
		];
		$sync->process_background_post( 101, 'attachment', $background_id );
		self::assertSame( 1, $sync->background_sync_status()['completed'] );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );
		$this->expectException( RuntimeException::class );
		$sync->process_background_delete( 101, 'rid-101', $background_id );
	}

	public function test_ensure_automatic_sync_skips_for_invalid_states_and_failed_complete_without_retry(): void {
		$sync = new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );
		self::assertSame( 'idle', $sync->ensure_automatic_sync()['status'] );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [ 'status' => 'running' ] );
		self::assertSame( 'idle', $sync->ensure_automatic_sync()['status'] );

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [] );
		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [ 'status' => 'running' ] );
		self::assertSame( 'idle', $sync->ensure_automatic_sync()['status'] );

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [] );
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'status' => 'complete',
				'total'  => 1,
				'failed' => 1,
			]
		);
		self::assertSame( 1, $sync->ensure_automatic_sync()['failed'] );
	}

	public function test_schedule_background_post_sync_skips_unselected_and_duplicate_posts(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		$post     = new WP_Post(
			[
				'ID'          => 111,
				'post_title'  => '',
				'post_type'   => 'book',
				'post_status' => 'publish',
			]
		);

		$sync->schedule_background_post_sync( 111, $post, true );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );

		$settings->update_indexable_post_types( [ 'post', 'page', 'book' ] );
		$GLOBALS['progress_agentic_rag_test_post_type_objects']['book'] = (object) [
			'labels' => (object) [
				'name'          => 'Books',
				'singular_name' => '',
			],
		];
		$GLOBALS['progress_agentic_rag_test_posts'][111] = $post;

		$sync->schedule_background_post_sync( 111, $post, true );
		$sync->schedule_background_post_sync( 111, $post, true );

		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( 'Books: Entity #111', $sync->background_sync_status()['current'] );
	}

	public function test_background_post_worker_handles_connection_missing_post_delete_and_sync_errors(): void {
		$settings = new SettingsRepository();
		$sync     = new ManualSync( $settings, new ApiClient( $settings ) );
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-1',
				'status'    => 'running',
				'total'     => 4,
				'completed' => 0,
				'failed'    => 0,
			]
		);

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );
		try {
			$sync->process_background_post( 121, 'post', 'background-1' );
			self::fail( 'Expected connection exception.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'connection is not validated', $exception->getMessage() );
		}

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		$sync->process_background_post( 121, 'post', 'background-1' );
		self::assertSame( 1, $sync->background_sync_status()['completed'] );

		$GLOBALS['wpdb']->var = 'rid-122';
		$GLOBALS['progress_agentic_rag_test_posts'][122] = new WP_Post(
			[
				'ID'          => 122,
				'post_title'  => 'Draft',
				'post_type'   => 'post',
				'post_status' => 'draft',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 204,
			],
			'body'     => '',
		];
		$sync->process_background_post( 122, 'post', 'background-1' );
		self::assertSame( 'DELETE', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );

		$GLOBALS['wpdb']->var = '';
		$GLOBALS['progress_agentic_rag_test_posts'][123] = new WP_Post(
			[
				'ID'          => 123,
				'post_title'  => 'Sync Error',
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Sync failed"}',
		];

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Sync failed' );
		$sync->process_background_post( 123, 'post', 'background-1' );
	}

	public function test_background_delete_worker_completes_invalid_and_successful_deletes(): void {
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-delete',
				'status'    => 'running',
				'total'     => 2,
				'completed' => 0,
				'failed'    => 0,
			]
		);
		$sync = new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) );

		$sync->process_background_delete( 0, '', 'background-delete' );
		self::assertSame( 1, $sync->background_sync_status()['completed'] );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 204,
			],
			'body'     => '',
		];
		$sync->process_background_delete( 131, 'rid-131', 'background-delete' );

		self::assertSame( 2, $sync->background_sync_status()['completed'] );
	}

	public function test_background_delete_worker_throws_on_api_error(): void {
		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-delete-error',
				'status'    => 'running',
				'total'     => 1,
				'completed' => 0,
				'failed'    => 0,
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Delete failed"}',
		];

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Delete failed' );

		( new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) ) )->process_background_delete( 132, 'rid-132', 'background-delete-error' );
	}

	public function test_manual_sync_edge_states_cover_recovery_delete_and_label_branches(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'ID' => '141',
			],
		];
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Lookup failed"}',
		];

		$sync = new ManualSync( new SettingsRepository(), new ApiClient( new SettingsRepository() ) );

		self::assertInstanceOf( WP_Error::class, $sync->start( [ 'post' ] ) );

		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-failed',
				'status'    => 'running',
				'total'     => 1,
				'completed' => 0,
				'failed'    => 1,
			]
		);

		$background_status = $sync->background_sync_status();

		self::assertSame( 'complete', $background_status['status'] );
		self::assertSame( 'Automatic background sync finished with failures.', $background_status['message'] );
		self::assertSame( 0, $background_status['percent'] );

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [] );
		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'    => '0',
				'nuclia_rid' => '',
			],
			(object) [
				'post_id'    => '142',
				'nuclia_rid' => 'rid-142',
			],
		];

		$delete_status = $sync->delete_synced_resources();

		self::assertSame( 1, $delete_status['failed'] );
		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );

		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-running-first',
				'status'  => 'running',
				'total'   => 2,
				'deleted' => 0,
				'failed'  => 0,
				'current' => '',
			]
		);

		self::assertSame( 'Waiting for the first resource.', $sync->delete_status()['current'] );

		update_option(
			SettingsRepository::OPTION_DELETE_SYNC_STATE,
			[
				'id'      => 'delete-running-next',
				'status'  => 'running',
				'total'   => 2,
				'deleted' => 1,
				'failed'  => 0,
				'current' => '',
			]
		);

		self::assertSame( 'Waiting for the next resource.', $sync->delete_status()['current'] );

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [] );
		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'    => '0',
				'nuclia_rid' => '',
			],
			(object) [
				'post_id'    => '143',
				'nuclia_rid' => 'rid-143',
			],
		];

		$label_status = $sync->start_label_reprocess();

		self::assertSame( 1, $label_status['scheduled'] );
	}

	public function test_background_and_private_helpers_cover_edge_branches(): void {
		$settings = new SettingsRepository();
		$settings->update_indexable_post_types( [ 'post' ] );
		$sync = new ManualSync( $settings, new ApiClient( $settings ) );

		$sync->process_single_label_reprocess( 151, 'rid-151', 'reprocess-1' );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'no' );
		$sync->schedule_background_delete( 151, new WP_Post( [ 'ID' => 151 ] ) );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );

		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		$GLOBALS['wpdb']->var = '';
		$sync->schedule_background_delete( 151, new WP_Post( [ 'ID' => 151 ] ) );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );

		update_option(
			SettingsRepository::OPTION_BACKGROUND_SYNC_STATE,
			[
				'id'        => 'background-delete-error',
				'status'    => 'running',
				'total'     => 1,
				'completed' => 0,
				'failed'    => 0,
			]
		);
		$GLOBALS['wpdb']->var = 'rid-152';
		$GLOBALS['progress_agentic_rag_test_posts'][152] = new WP_Post(
			[
				'ID'          => 152,
				'post_title'  => 'Draft Delete Error',
				'post_type'   => 'post',
				'post_status' => 'draft',
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Delete failed"}',
		];

		try {
			$sync->process_background_post( 152, 'post', 'background-delete-error' );
			self::fail( 'Expected delete exception.' );
		} catch ( RuntimeException $exception ) {
			self::assertStringContainsString( 'Delete failed', $exception->getMessage() );
		}

		$args_ref = new ReflectionMethod( $sync, 'scheduled_action_args' );
		$args_ref->setAccessible( true );
		$action_with_method = new class() {
			public function get_args(): array {
				return [ 'post_id' => 1 ];
			}
		};

		self::assertSame( [ 'post_id' => 1 ], $args_ref->invoke( $sync, $action_with_method ) );
		self::assertSame( [ 'post_id' => 2 ], $args_ref->invoke( $sync, (object) [ 'args' => [ 'post_id' => 2 ] ] ) );
		self::assertSame( [ 'post_id' => 3 ], $args_ref->invoke( $sync, [ 'args' => [ 'post_id' => 3 ] ] ) );
		self::assertSame( [], $args_ref->invoke( $sync, 'bad-action' ) );

		$is_indexable_ref = new ReflectionMethod( $sync, 'is_indexable_post' );
		$is_indexable_ref->setAccessible( true );

		self::assertFalse(
			$is_indexable_ref->invoke(
				$sync,
				new WP_Post(
					[
						'post_type'     => 'post',
						'post_status'   => 'publish',
						'post_password' => 'secret',
					]
				),
				'post'
			)
		);

		$update_background_ref = new ReflectionMethod( $sync, 'update_background_current' );
		$update_background_ref->setAccessible( true );
		$complete_background_ref = new ReflectionMethod( $sync, 'mark_background_completed' );
		$complete_background_ref->setAccessible( true );
		$fail_background_ref = new ReflectionMethod( $sync, 'mark_background_failed' );
		$fail_background_ref->setAccessible( true );

		$update_background_ref->invoke( $sync, 'Ignored', '' );
		$update_background_ref->invoke( $sync, 'Ignored', 'wrong-background' );
		$complete_background_ref->invoke( $sync, '' );
		$complete_background_ref->invoke( $sync, 'wrong-background' );
		$fail_background_ref->invoke( $sync, 'Ignored', '' );
		$fail_background_ref->invoke( $sync, 'Ignored', 'wrong-background' );

		if ( ! class_exists( 'ActionScheduler_Store' ) ) {
			eval( 'class ActionScheduler_Store { public const STATUS_COMPLETE = "complete"; public const STATUS_FAILED = "failed"; public const STATUS_PENDING = "pending"; public const STATUS_RUNNING = "in-progress"; }' );
		}

		$action_status_ref = new ReflectionMethod( $sync, 'action_status' );
		$action_status_ref->setAccessible( true );

		self::assertSame( 'complete', $action_status_ref->invoke( $sync, 'STATUS_COMPLETE', 'fallback' ) );
	}
}
