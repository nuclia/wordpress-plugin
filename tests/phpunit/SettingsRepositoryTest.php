<?php
/**
 * Settings repository tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Settings\SettingsRepository;

final class SettingsRepositoryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_options']    = [];
		$GLOBALS['progress_agentic_rag_test_taxonomies'] = [];
		$GLOBALS['progress_agentic_rag_test_terms']      = [];
	}

	public function test_defaults_keep_reference_plugin_option_names(): void {
		$repository = new SettingsRepository();
		$defaults   = $repository->defaults();

		self::assertArrayHasKey( SettingsRepository::OPTION_ZONE, $defaults );
		self::assertArrayHasKey( SettingsRepository::OPTION_TOKEN, $defaults );
		self::assertArrayHasKey( SettingsRepository::OPTION_KBID, $defaults );
		self::assertArrayHasKey( SettingsRepository::OPTION_ACCOUNT_ID, $defaults );
		self::assertArrayHasKey( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $defaults );
		self::assertArrayHasKey( SettingsRepository::OPTION_SYNC_HISTORY, $defaults );
		self::assertArrayHasKey( SettingsRepository::OPTION_FAILED_SYNC_ITEMS, $defaults );
		self::assertSame( 'agentic_rag_for_wp', SettingsRepository::SYNC_TABLE_NAME );
	}

	public function test_connection_settings_normalize_copied_endpoint_and_bearer_token(): void {
		$repository = new SettingsRepository();

		$repository->update_connection_settings(
			'https://aws-us-east-2-1.rag.progress.cloud/api/v1/kb/example-kb',
			'https://aws-us-east-2-1.rag.progress.cloud/api/v1/kb/example-kb',
			'',
			'Bearer example-token'
		);

		self::assertSame( 'aws-us-east-2-1', $repository->get_string( SettingsRepository::OPTION_ZONE ) );
		self::assertSame( 'example-kb', $repository->get_string( SettingsRepository::OPTION_KBID ) );
		self::assertSame( '', $repository->get_string( SettingsRepository::OPTION_ACCOUNT_ID ) );
		self::assertSame( 'example-token', $repository->get_string( SettingsRepository::OPTION_TOKEN ) );
	}

	public function test_connection_settings_normalize_host_without_scheme(): void {
		$repository = new SettingsRepository();

		$repository->update_connection_settings(
			'europe-1.rag.progress.cloud',
			'example-kb',
			'example-account',
			'example-token'
		);

		self::assertSame( 'europe-1', $repository->get_string( SettingsRepository::OPTION_ZONE ) );
	}

	public function test_api_reachable_requires_complete_connection_settings(): void {
		$repository = new SettingsRepository();

		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		update_option( SettingsRepository::OPTION_KBID, 'kb-123' );
		update_option( SettingsRepository::OPTION_TOKEN, 'token' );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );

		self::assertTrue( $repository->get_api_is_reachable() );

		update_option( SettingsRepository::OPTION_TOKEN, '' );

		self::assertFalse( $repository->get_api_is_reachable() );
	}

	public function test_taxonomy_label_map_sanitizes_terms_and_fallback_labels(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['category'] = true;
		$GLOBALS['progress_agentic_rag_test_terms']['category']      = [ 7 => true ];

		$repository = new SettingsRepository();
		$repository->update_taxonomy_label_map(
			[
				'category'    => [
					'labelset' => 'Topic',
					'terms'    => [
						7  => [ 'Support', 'Support', '<b>Docs</b>', '' ],
						99 => [ 'Ignored' ],
					],
					'fallback' => [
						'labelset' => 'Audience',
						'labels'   => [ 'General', 'General', '<i>Visitor</i>', '' ],
					],
				],
				'bad<script>' => [
					'labelset' => 'Ignored',
				],
			]
		);

		self::assertSame(
			[
				'category' => [
					'labelset' => 'Topic',
					'terms'    => [
						7 => [ 'Support', 'Docs' ],
					],
					'fallback' => [
						'labelset' => 'Audience',
						'labels'   => [ 'General', 'Visitor' ],
					],
				],
			],
			get_option( SettingsRepository::OPTION_TAXONOMY_LABEL_MAP )
		);
	}

	public function test_taxonomy_label_map_keeps_valid_fallback_without_term_mapping(): void {
		$GLOBALS['progress_agentic_rag_test_taxonomies']['post_tag'] = true;

		$repository = new SettingsRepository();
		$repository->update_taxonomy_label_map(
			[
				'post_tag' => [
					'labelset' => '',
					'terms'    => [],
					'fallback' => [
						'labelset' => 'Audience',
						'labels'   => 'General',
					],
				],
			]
		);

		self::assertSame(
			[
				'post_tag' => [
					'fallback' => [
						'labelset' => 'Audience',
						'labels'   => [ 'General' ],
					],
				],
			],
			get_option( SettingsRepository::OPTION_TAXONOMY_LABEL_MAP )
		);
	}

	public function test_cache_helpers_clear_labels_and_option_names(): void {
		$repository = new SettingsRepository();

		$repository->set_labelsets_cache( [ 'Topic' ] );
		$repository->set_labelset_labels_cache( 'Topic', [ 'Support', 'Docs' ] );

		self::assertSame( [ 'Support', 'Docs' ], $repository->get_labelset_labels_cache( 'Topic' ) );

		$repository->set_labelsets_cache_with_labels(
			[ 'Audience' ],
			[
				'Audience' => [ 'General' ],
				7          => [ 'Ignored' ],
			]
		);

		self::assertSame( [ 'Audience' ], $repository->get_labelsets_cache()['labelsets'] );
		self::assertSame( [ 'General' ], $repository->get_labelset_labels_cache( 'Audience' ) );

		update_option( SettingsRepository::OPTION_TAXONOMY_LABEL_MAP, [ 'category' => [ 'labelset' => 'Topic' ] ] );
		$repository->clear_labels();

		self::assertSame( [], get_option( SettingsRepository::OPTION_TAXONOMY_LABEL_MAP ) );
		self::assertSame( $repository->defaults()[ SettingsRepository::OPTION_LABELSETS_CACHE ], get_option( SettingsRepository::OPTION_LABELSETS_CACHE ) );
		self::assertSame( array_keys( $repository->defaults() ), $repository->option_names() );
	}

	public function test_update_connection_settings_keeps_existing_token_when_empty_token_posted(): void {
		$repository = new SettingsRepository();
		update_option( SettingsRepository::OPTION_TOKEN, 'existing-token' );

		$repository->update_connection_settings( 'europe-1', 'kb-123', 'account', '' );

		self::assertSame( 'existing-token', get_option( SettingsRepository::OPTION_TOKEN ) );
	}

	public function test_sync_history_and_failed_items_are_sanitized(): void {
		$repository = new SettingsRepository();

		$repository->add_sync_history_entry(
			[
				'type'        => 'manual<script>',
				'status'      => 'failed',
				'total'       => 3,
				'completed'   => 2,
				'failed'      => 1,
				'message'     => '<b>Done</b>',
				'current'     => '<i>Post</i>',
				'started_at'  => 10,
				'finished_at' => 20,
			]
		);
		$repository->add_failed_sync_item( 31, 'post<script>', '<b>Post title</b>', '<i>Rejected</i>', 'manual<script>' );

		$history = $repository->get_sync_history();
		$failed  = array_values( $repository->get_failed_sync_items() );

		self::assertSame( 'manualscript', $history[0]['type'] );
		self::assertSame( 'Done', $history[0]['message'] );
		self::assertSame( 'Post', $history[0]['current'] );
		self::assertSame( 31, $failed[0]['post_id'] );
		self::assertSame( 'postscript', $failed[0]['post_type'] );
		self::assertSame( 'Post title', $failed[0]['label'] );
		self::assertSame( 'Rejected', $failed[0]['message'] );

		$repository->remove_failed_sync_item( 31, 'postscript' );
		self::assertSame( [], $repository->get_failed_sync_items() );
	}
}
