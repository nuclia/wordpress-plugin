<?php
/**
 * API client tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Settings\SettingsRepository;

final class ApiClientTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_options']        = [];
		$GLOBALS['progress_agentic_rag_test_http_requests']  = [];
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
		$GLOBALS['progress_agentic_rag_test_post_terms']     = [];
		$GLOBALS['progress_agentic_rag_test_taxonomies']     = [];
		$GLOBALS['progress_agentic_rag_test_terms']          = [];
		$GLOBALS['progress_agentic_rag_test_attached_files'] = [];
		$GLOBALS['wpdb']                                     = new ProgressAgenticRagTestWpdb();

		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		update_option( SettingsRepository::OPTION_KBID, 'kb-123' );
		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
	}

	public function test_index_post_sends_taxonomy_classifications(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = true;
		$GLOBALS['progress_agentic_rag_test_post_terms'][123]        = [
			'category' => [ 7 ],
		];
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
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"uuid":"rid-123","seqid":"seq-123"}',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'            => 123,
					'post_title'    => '<b>Support Article</b>',
					'post_content'  => '<p>Body</p>',
					'post_date_gmt' => '2026-06-17 08:00:00',
				]
			)
		);

		self::assertTrue( $result );
		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_http_requests'] );

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];
		$body    = json_decode( $request['args']['body'], true );

		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources', $request['url'] );
		self::assertSame( 'POST', $request['args']['method'] );
		self::assertSame( 'Bearer secret-token', $request['args']['headers']['X-NUCLIA-SERVICEACCOUNT'] );
		self::assertSame(
			[
				[
					'labelset' => 'Topic',
					'label'    => 'Support',
				],
			],
			$body['usermetadata']['classifications']
		);
		self::assertSame( 'HTML', $body['texts']['text-1']['format'] );
		self::assertSame( 'rid-123', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
	}

	public function test_update_resource_labels_sends_label_only_patch_body(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = true;
		$GLOBALS['progress_agentic_rag_test_post_terms'][456]        = [
			'category' => [],
		];
		update_option(
			SettingsRepository::OPTION_TAXONOMY_LABEL_MAP,
			[
				'category' => [
					'fallback' => [
						'labelset' => 'Audience',
						'labels'   => [ 'General' ],
					],
				],
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{}',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->update_resource_labels(
			new WP_Post(
				[
					'ID'           => 456,
					'post_title'   => 'Fallback Article',
					'post_content' => '<p>Body</p>',
				]
			),
			'rid-456'
		);

		self::assertTrue( $result );

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];
		$body    = json_decode( $request['args']['body'], true );

		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-456', $request['url'] );
		self::assertSame( 'PATCH', $request['args']['method'] );
		self::assertSame( [ 'usermetadata' ], array_keys( $body ) );
		self::assertSame(
			[
				[
					'labelset' => 'Audience',
					'label'    => 'General',
				],
			],
			$body['usermetadata']['classifications']
		);
	}

	public function test_sync_post_updates_existing_resource_mapping(): void {
		$GLOBALS['wpdb']->var = 'rid-456';
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"seqid":"seq-updated"}',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->sync_post(
			new WP_Post(
				[
					'ID'           => 456,
					'post_title'   => 'Updated Article',
					'post_content' => '<p>Updated body</p>',
				]
			)
		);

		self::assertTrue( $result );

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];
		$body    = json_decode( $request['args']['body'], true );

		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-456', $request['url'] );
		self::assertSame( 'PATCH', $request['args']['method'] );
		self::assertSame( 'Updated Article', $body['title'] );
		self::assertSame( '<p>Updated body</p>', $body['texts']['text-1']['body'] );
		self::assertSame( 'rid-456', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
		self::assertSame( 'seq-updated', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_seqid'] );
	}

	public function test_delete_resource_deletes_upstream_and_local_mapping(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 204,
			],
			'body'     => '',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->delete_resource( 789, 'rid-789' );

		self::assertTrue( $result );

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-789', $request['url'] );
		self::assertSame( 'DELETE', $request['args']['method'] );
		self::assertSame( 'Bearer secret-token', $request['args']['headers']['X-NUCLIA-SERVICEACCOUNT'] );
		self::assertSame( [ 'post_id' => 789 ], $GLOBALS['wpdb']->deleted[0]['where'] );
	}

	public function test_delete_resource_keeps_local_mapping_when_api_rejects_delete(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{}',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->delete_resource( 789, 'rid-789' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( [], $GLOBALS['wpdb']->deleted );
	}

	public function test_attachment_upload_accepts_created_response_and_records_mapping(): void {
		$file = tempnam( sys_get_temp_dir(), 'progress-agentic-rag-' );
		file_put_contents( $file, 'Attachment body' );
		$GLOBALS['progress_agentic_rag_test_attached_files'][100] = $file;
		$GLOBALS['progress_agentic_rag_test_http_responses']      = [
			[
				'response' => [
					'code' => 201,
				],
				'body'     => '{"uuid":"rid-100","seqid":"seq-resource"}',
			],
			[
				'response' => [
					'code' => 201,
				],
				'body'     => '{"uuid":"rid-100","seqid":null,"field_id":"file"}',
			],
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 100,
					'post_title' => 'Knowledge Base Runbook 1',
					'post_type'  => 'attachment',
				]
			)
		);

		unlink( $file );

		self::assertTrue( $result );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( 'POST', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-100/file/file/upload', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );
		self::assertSame( 'rid-100', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
	}

	public function test_attachment_create_conflict_recovers_existing_resource_mapping(): void {
		$file = tempnam( sys_get_temp_dir(), 'progress-agentic-rag-' );
		file_put_contents( $file, 'Attachment body' );
		$GLOBALS['progress_agentic_rag_test_attached_files'][100] = $file;
		$GLOBALS['progress_agentic_rag_test_http_responses']      = [
			[
				'response' => [
					'code' => 409,
				],
				'body'     => '{"detail":"Resource slug 100 already exists"}',
			],
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[{"id":"rid-existing","slug":"100","title":"Knowledge Base Runbook 1","last_seqid":0}]}',
			],
			[
				'response' => [
					'code' => 201,
				],
				'body'     => '{"uuid":"rid-existing","seqid":null,"field_id":"file"}',
			],
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 100,
					'post_title' => 'Knowledge Base Runbook 1',
					'post_type'  => 'attachment',
				]
			)
		);

		unlink( $file );

		self::assertTrue( $result );
		self::assertCount( 3, $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( 'GET', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['args']['method'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources?from=0&size=250', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resource/rid-existing/file/file/upload', $GLOBALS['progress_agentic_rag_test_http_requests'][2]['url'] );
		self::assertSame( 'rid-existing', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
	}

	public function test_recover_existing_resources_repairs_mappings_from_paginated_resource_list(): void {
		$first_page = [];
		for ( $i = 1; $i <= 250; $i++ ) {
			$first_page[] = [
				'id'   => 'rid-other-' . $i,
				'slug' => 'other-' . $i,
			];
		}

		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 200,
				],
				'body'     => json_encode( [ 'resources' => $first_page ] ),
			],
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[{"id":"rid-101","slug":"101","last_seqid":0},{"uuid":"rid-203","slug":"203","seqid":"seq-203"}]}',
			],
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->recover_existing_resources( [ 101, 203 ] );

		self::assertSame( 2, $result );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources?from=0&size=250', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/resources?from=250&size=250', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );
		self::assertSame( 101, $GLOBALS['wpdb']->inserted[0]['data']['post_id'] );
		self::assertSame( 'rid-101', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
		self::assertSame( '0', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_seqid'] );
		self::assertSame( 203, $GLOBALS['wpdb']->inserted[1]['data']['post_id'] );
		self::assertSame( 'rid-203', $GLOBALS['wpdb']->inserted[1]['data']['nuclia_rid'] );
		self::assertSame( 'seq-203', $GLOBALS['wpdb']->inserted[1]['data']['nuclia_seqid'] );
	}

	public function test_reconcile_synced_resources_clears_missing_upstream_mappings(): void {
		$GLOBALS['wpdb']->results = [
			(object) [
				'post_id'      => '101',
				'nuclia_rid'   => 'rid-old',
				'nuclia_seqid' => 'seq-old',
			],
			(object) [
				'post_id'      => '202',
				'nuclia_rid'   => 'rid-missing',
				'nuclia_seqid' => 'seq-missing',
			],
			(object) [
				'post_id'      => '303',
				'nuclia_rid'   => 'rid-303',
				'nuclia_seqid' => 'seq-303',
			],
		];
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"resources":[{"id":"rid-new","slug":"101","seqid":"seq-new"},{"id":"rid-303","slug":"303","seqid":"seq-303"}]}',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->reconcile_synced_resources();

		self::assertSame(
			[
				'checked' => 3,
				'removed' => 1,
				'updated' => 1,
			],
			$result
		);
		self::assertSame( [ 'post_id' => 101 ], $GLOBALS['wpdb']->deleted[0]['where'] );
		self::assertSame( [ 'post_id' => 202 ], $GLOBALS['wpdb']->deleted[1]['where'] );
		self::assertSame( 101, $GLOBALS['wpdb']->inserted[0]['data']['post_id'] );
		self::assertSame( 'rid-new', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
		self::assertSame( 'seq-new', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_seqid'] );
	}

	public function test_get_labelsets_fetches_and_caches_embedded_labels(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"labelsets":[{"labelset":"Topic","labels":["Support","Docs"]},{"name":"Audience"}]}',
		];

		$labelsets = ( new ApiClient( new SettingsRepository() ) )->get_labelsets();

		self::assertSame( [ 'Topic', 'Audience' ], $labelsets );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/labelsets', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( 'GET', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );
		self::assertSame( 'Bearer secret-token', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['headers']['X-NUCLIA-SERVICEACCOUNT'] );

		$cache = get_option( SettingsRepository::OPTION_LABELSETS_CACHE );
		self::assertSame( [ 'Topic', 'Audience' ], $cache['labelsets'] );
		self::assertSame( [ 'Support', 'Docs' ], $cache['labels']['Topic'] );
		self::assertGreaterThan( 0, $cache['fetched_at'] );
	}

	public function test_get_labelset_labels_tries_supported_endpoint_shapes_and_caches_labels(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '{}',
			],
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"labels":[{"label":"General"},{"title":"Advanced"}]}',
			],
		];

		$labels = ( new ApiClient( new SettingsRepository() ) )->get_labelset_labels( 'Audience' );

		self::assertSame( [ 'General', 'Advanced' ], $labels );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/labelsets/Audience', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertSame( 'https://europe-1.rag.progress.cloud/api/v1/kb/kb-123/labelset/Audience', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );

		$cache = get_option( SettingsRepository::OPTION_LABELSETS_CACHE );
		self::assertSame( [ 'General', 'Advanced' ], $cache['labels']['Audience'] );
		self::assertGreaterThan( 0, $cache['fetched_at'] );
	}

	public function test_index_post_returns_errors_for_missing_connection_rejection_and_missing_resource_id(): void {
		update_option( SettingsRepository::OPTION_TOKEN, '' );

		$missing = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 901,
					'post_title' => 'Missing Connection',
				]
			)
		);

		self::assertInstanceOf( WP_Error::class, $missing );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );

		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 400,
			],
			'body'     => '{"detail":"<b>Rejected</b>"}',
		];

		$rejected = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 902,
					'post_title' => 'Rejected',
				]
			)
		);

		self::assertInstanceOf( WP_Error::class, $rejected );
		self::assertStringContainsString( 'Rejected', $rejected->get_error_message() );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"seqid":"seq-no-rid"}',
		];

		$missing_id = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 903,
					'post_title' => 'Missing Resource ID',
				]
			)
		);

		self::assertInstanceOf( WP_Error::class, $missing_id );
	}

	public function test_sync_post_reindexes_when_existing_resource_is_missing_upstream(): void {
		$GLOBALS['wpdb']->var = 'rid-stale';
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '{}',
			],
			[
				'response' => [
					'code' => 201,
				],
				'body'     => '{"uuid":"rid-new","seqid":"seq-new"}',
			],
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->sync_post(
			new WP_Post(
				[
					'ID'         => 904,
					'post_title' => 'Reindexed',
				]
			)
		);

		self::assertTrue( $result );
		self::assertSame( [ 'post_id' => 904 ], $GLOBALS['wpdb']->deleted[0]['where'] );
		self::assertSame( 'PATCH', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['args']['method'] );
		self::assertSame( 'POST', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['args']['method'] );
		self::assertSame( 'rid-new', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_rid'] );
	}

	public function test_label_and_delete_methods_return_wp_errors_for_invalid_or_rejected_requests(): void {
		$client = new ApiClient( new SettingsRepository() );

		self::assertInstanceOf( WP_Error::class, $client->update_resource_labels( new WP_Post( [ 'ID' => 1 ] ), '' ) );
		self::assertInstanceOf( WP_Error::class, $client->delete_resource( 1, '' ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"message":"Label rejected"}',
		];

		self::assertInstanceOf( WP_Error::class, $client->update_resource_labels( new WP_Post( [ 'ID' => 1 ] ), 'rid-1' ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Network failed' );

		self::assertInstanceOf( WP_Error::class, $client->delete_resource( 1, 'rid-1' ) );
	}

	public function test_labelset_methods_use_cache_and_fallbacks(): void {
		update_option(
			SettingsRepository::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => time(),
				'labelsets'  => [ 'Cached' ],
				'labels'     => [
					'Cached' => [ 'One' ],
				],
			]
		);

		$client = new ApiClient( new SettingsRepository() );

		self::assertSame( [ 'Cached' ], $client->get_labelsets() );
		self::assertSame( [ 'One' ], $client->get_labelset_labels( 'Cached' ) );
		self::assertSame( [], $client->get_labelset_labels( '' ) );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );

		update_option(
			SettingsRepository::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => 1,
				'labelsets'  => [ 'Stale' ],
				'labels'     => [],
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{}',
		];

		self::assertSame( [ 'Stale' ], $client->get_labelsets() );
	}

	public function test_recover_existing_resources_ignores_invalid_ids_and_empty_matches(): void {
		$client = new ApiClient( new SettingsRepository() );

		self::assertSame( 0, $client->recover_existing_resources( [ 0, 'bad' ] ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"resources":[{"slug":"905"}]}',
		];

		self::assertSame( 0, $client->recover_existing_resources( [ 905 ] ) );
	}

	public function test_index_conflict_without_existing_resource_and_attachment_upload_failure_return_errors(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 409,
				],
				'body'     => '{"detail":"exists"}',
			],
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"resources":[]}',
			],
		];

		$missing_existing = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 906,
					'post_title' => 'Missing Existing',
				]
			)
		);

		self::assertInstanceOf( WP_Error::class, $missing_existing );

		$file = tempnam( sys_get_temp_dir(), 'progress-agentic-rag-' );
		file_put_contents( $file, 'Attachment body' );
		$GLOBALS['progress_agentic_rag_test_attached_files'][907] = $file;
		$GLOBALS['progress_agentic_rag_test_http_responses']      = [
			[
				'response' => [
					'code' => 201,
				],
				'body'     => '{"uuid":"rid-907","seqid":"seq-resource"}',
			],
			[
				'response' => [
					'code' => 500,
				],
				'body'     => '{"detail":"Upload failed"}',
			],
		];

		$upload_failed = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 907,
					'post_title' => 'Attachment Upload Failure',
					'post_type'  => 'attachment',
				]
			)
		);

		unlink( $file );

		self::assertInstanceOf( WP_Error::class, $upload_failed );
	}

	public function test_sync_post_returns_errors_for_missing_connection_http_failure_and_rejection(): void {
		$GLOBALS['wpdb']->var = 'rid-908';
		update_option( SettingsRepository::OPTION_TOKEN, '' );

		$missing = ( new ApiClient( new SettingsRepository() ) )->sync_post( new WP_Post( [ 'ID' => 908 ] ) );

		self::assertInstanceOf( WP_Error::class, $missing );

		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Network failed' );

		$http_failed = ( new ApiClient( new SettingsRepository() ) )->sync_post( new WP_Post( [ 'ID' => 908 ] ) );

		self::assertInstanceOf( WP_Error::class, $http_failed );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Update rejected"}',
		];

		$rejected = ( new ApiClient( new SettingsRepository() ) )->sync_post( new WP_Post( [ 'ID' => 908 ] ) );

		self::assertInstanceOf( WP_Error::class, $rejected );
		self::assertStringContainsString( 'Update rejected', $rejected->get_error_message() );
	}

	public function test_labelset_normalizers_accept_associative_payloads_and_label_variants(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"labelsets":{"Topic":{"labels":{"Support":true}},"Audience":["General"]}}',
		];

		$client = new ApiClient( new SettingsRepository() );

		self::assertSame( [ 'Topic', 'Audience' ], $client->get_labelsets() );
		self::assertSame( [ 'Support' ], get_option( SettingsRepository::OPTION_LABELSETS_CACHE )['labels']['Topic'] );

		update_option(
			SettingsRepository::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => 1,
				'labelsets'  => [],
				'labels'     => [],
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"labels":[{"text":"Text"},{"uri":"Uri"},{"related":"Related"},{"name":"Name"},{"id":"ID"}]}',
		];

		self::assertSame( [ 'Text', 'Uri', 'Related', 'Name', 'ID' ], $client->get_labelset_labels( 'Variants' ) );
	}

	public function test_recover_existing_resources_returns_lookup_errors(): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => '{"detail":"Lookup failed"}',
		];

		self::assertInstanceOf( WP_Error::class, ( new ApiClient( new SettingsRepository() ) )->recover_existing_resources( [ 909 ] ) );
	}

	public function test_taxonomy_classifications_skip_invalid_maps_and_accept_scalar_labels(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = true;
		$GLOBALS['progress_agentic_rag_test_taxonomies']['post_tag'] = true;
		$GLOBALS['progress_agentic_rag_test_post_terms'][910]        = [
			'category' => [ 7, 8, 9 ],
			'post_tag' => [ 10 ],
		];
		update_option(
			SettingsRepository::OPTION_TAXONOMY_LABEL_MAP,
			[
				'missing'  => [
					'labelset' => 'Ignored',
				],
				'bad'      => 'skip',
				'post_tag' => [
					'terms' => [
						10 => [ 'No labelset' ],
					],
				],
				'category' => [
					'labelset' => 'Topic',
					'terms'    => [
						7 => 'Scalar',
						8 => '',
						9 => [ 'Duplicate', 'Duplicate' ],
					],
				],
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 201,
			],
			'body'     => '{"uuid":"rid-910","seqid":"seq-910"}',
		];

		$result = ( new ApiClient( new SettingsRepository() ) )->index_post(
			new WP_Post(
				[
					'ID'         => 910,
					'post_title' => 'Taxonomy Branches',
				]
			)
		);

		$request = $GLOBALS['progress_agentic_rag_test_http_requests'][0];
		$body    = json_decode( $request['args']['body'], true );

		self::assertTrue( $result );
		self::assertSame(
			[
				[
					'labelset' => 'Topic',
					'label'    => 'Scalar',
				],
				[
					'labelset' => 'Topic',
					'label'    => 'Duplicate',
				],
			],
			$body['usermetadata']['classifications']
		);
	}

	public function test_attachment_upload_branches_update_seqids_and_return_errors(): void {
		$file = tempnam( sys_get_temp_dir(), 'progress-agentic-rag-' );
		file_put_contents( $file, 'Attachment body' );
		$GLOBALS['progress_agentic_rag_test_attached_files'][911] = $file;
		$GLOBALS['progress_agentic_rag_test_attached_files'][912] = $file;
		$GLOBALS['progress_agentic_rag_test_attached_files'][913] = $file;
		$GLOBALS['progress_agentic_rag_test_attached_files'][914] = $file;
		$GLOBALS['progress_agentic_rag_test_attached_files'][915] = $file;

		try {
			$GLOBALS['progress_agentic_rag_test_http_responses'] = [
				[
					'response' => [
						'code' => 201,
					],
					'body'     => '{"uuid":"rid-911","seqid":"seq-resource"}',
				],
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"seqid":"seq-file"}',
				],
			];

			$result = ( new ApiClient( new SettingsRepository() ) )->index_post(
				new WP_Post(
					[
						'ID'         => 911,
						'post_title' => 'Attachment With File Seqid',
						'post_type'  => 'attachment',
					]
				)
			);

			self::assertTrue( $result );
			self::assertSame( 'seq-file', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_seqid'] );

			$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
			$GLOBALS['progress_agentic_rag_test_http_requests']  = [];
			$GLOBALS['progress_agentic_rag_test_http_responses'] = [
				[
					'response' => [
						'code' => 409,
					],
					'body'     => '{"detail":"exists"}',
				],
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"resources":[{"id":"rid-existing","slug":"912","last_seqid":"seq-old"}]}',
				],
				new WP_Error( 'http_failed', 'Upload network failed' ),
			];

			$conflict_upload_failed = ( new ApiClient( new SettingsRepository() ) )->index_post(
				new WP_Post(
					[
						'ID'         => 912,
						'post_title' => 'Attachment Conflict Upload Failure',
						'post_type'  => 'attachment',
					]
				)
			);

			self::assertInstanceOf( WP_Error::class, $conflict_upload_failed );

			$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
			$GLOBALS['progress_agentic_rag_test_http_responses'] = [
				[
					'response' => [
						'code' => 409,
					],
					'body'     => '{"detail":"exists"}',
				],
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"resources":[{"id":"rid-existing","slug":"915","last_seqid":"seq-old"}]}',
				],
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"seqid":"seq-conflict-file"}',
				],
			];

			$conflict_upload_succeeded = ( new ApiClient( new SettingsRepository() ) )->index_post(
				new WP_Post(
					[
						'ID'         => 915,
						'post_title' => 'Attachment Conflict Upload Success',
						'post_type'  => 'attachment',
					]
				)
			);

			self::assertTrue( $conflict_upload_succeeded );
			self::assertSame( 'seq-conflict-file', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_seqid'] );

			$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
			$GLOBALS['wpdb']->var = 'rid-sync';
			$GLOBALS['progress_agentic_rag_test_http_responses'] = [
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"seqid":"seq-patch"}',
				],
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"seqid":"seq-file-sync"}',
				],
			];

			$sync_result = ( new ApiClient( new SettingsRepository() ) )->sync_post(
				new WP_Post(
					[
						'ID'         => 913,
						'post_title' => 'Synced Attachment With File Seqid',
						'post_type'  => 'attachment',
					]
				)
			);

			self::assertTrue( $sync_result );
			self::assertSame( 'seq-file-sync', $GLOBALS['wpdb']->inserted[0]['data']['nuclia_seqid'] );

			$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
			$GLOBALS['wpdb']->var = 'rid-sync';
			$GLOBALS['progress_agentic_rag_test_http_responses'] = [
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"seqid":"seq-patch"}',
				],
				new WP_Error( 'http_failed', 'Upload network failed' ),
			];

			$sync_upload_failed = ( new ApiClient( new SettingsRepository() ) )->sync_post(
				new WP_Post(
					[
						'ID'         => 914,
						'post_title' => 'Synced Attachment Upload Failure',
						'post_type'  => 'attachment',
					]
				)
			);

			self::assertInstanceOf( WP_Error::class, $sync_upload_failed );
		} finally {
			unlink( $file );
		}
	}

	public function test_api_error_lookup_and_labelset_fallback_branches(): void {
		$client = new ApiClient( new SettingsRepository() );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Create failed' );

		self::assertInstanceOf( WP_Error::class, $client->index_post( new WP_Post( [ 'ID' => 915 ] ) ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 409,
				],
				'body'     => '{"detail":"exists"}',
			],
			new WP_Error( 'http_failed', 'Lookup failed' ),
		];

		self::assertInstanceOf( WP_Error::class, $client->index_post( new WP_Post( [ 'ID' => 916 ] ) ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Label failed' );

		self::assertInstanceOf( WP_Error::class, $client->update_resource_labels( new WP_Post( [ 'ID' => 917 ] ), 'rid-917' ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 500,
			],
			'body'     => 'not-json',
		];

		$delete_failed = $client->delete_resource( 918, 'rid-918' );

		self::assertInstanceOf( WP_Error::class, $delete_failed );
		self::assertStringContainsString( 'Progress Agentic RAG rejected the resource deletion.', $delete_failed->get_error_message() );

		update_option(
			SettingsRepository::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => 1,
				'labelsets'  => [ 'Cached' ],
				'labels'     => [],
			]
		);
		update_option( SettingsRepository::OPTION_TOKEN, '' );

		self::assertSame( [ 'Cached' ], $client->get_labelsets() );
		self::assertSame( [], $client->get_labelset_labels( 'Missing Connection Labels' ) );

		update_option( SettingsRepository::OPTION_TOKEN, 'secret-token' );
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [
				'code' => 200,
			],
			'body'     => '[]',
		];

		self::assertSame( [ 'Cached' ], $client->get_labelsets() );

		update_option(
			SettingsRepository::OPTION_LABELSETS_CACHE,
			[
				'fetched_at' => 1,
				'labelsets'  => [],
				'labels'     => [
					'Stale' => [ 'Old' ],
				],
			]
		);
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '{}',
			],
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '{}',
			],
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '{}',
			],
			[
				'response' => [
					'code' => 404,
				],
				'body'     => '{}',
			],
		];

		self::assertSame( [ 'Old' ], $client->get_labelset_labels( 'Stale' ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '"bad"',
			],
		];

		self::assertSame( [], $client->get_labelset_labels( 'No Labels' ) );

		$GLOBALS['progress_agentic_rag_test_http_responses'] = [
			[
				'response' => [
					'code' => 200,
				],
				'body'     => '{"labelsets":[{"id":"Identifier"}]}',
			],
		];

		self::assertSame( [ 'Identifier' ], $client->get_labelsets() );

		update_option( SettingsRepository::OPTION_ZONE, 'bad zone' );

		self::assertInstanceOf( WP_Error::class, $client->recover_existing_resources( [ 919 ] ) );

		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = new WP_Error( 'http_failed', 'Lookup failed' );

		self::assertInstanceOf( WP_Error::class, $client->recover_existing_resources( [ 920 ] ) );
	}

	public function test_private_lookup_helpers_cover_empty_inputs(): void {
		$client = new ApiClient( new SettingsRepository() );

		$single_ref = new ReflectionMethod( $client, 'find_resource_by_slug' );
		$single_ref->setAccessible( true );

		self::assertInstanceOf( WP_Error::class, $single_ref->invoke( $client, '' ) );

		$many_ref = new ReflectionMethod( $client, 'find_resources_by_slugs' );
		$many_ref->setAccessible( true );

		self::assertSame( [], $many_ref->invoke( $client, [ '', '   ' ] ) );
	}
}
