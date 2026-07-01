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
}
