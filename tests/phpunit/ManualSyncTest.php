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
}
