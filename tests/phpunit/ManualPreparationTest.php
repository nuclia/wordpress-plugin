<?php
/**
 * Resumable manual preparation regressions.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Indexing\ManualPreparation;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Indexing\PreparationStore;
use ProgressAgenticRag\Indexing\Scheduler;
use ProgressAgenticRag\Settings\SettingsRepository;

final class ManualPreparationTest extends TestCase {
	private PreparationStore $store;
	private ManualPreparation $preparation;
	private SettingsRepository $settings;

	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_options'] = [];
		$GLOBALS['progress_agentic_rag_test_http_requests'] = [];
		$GLOBALS['progress_agentic_rag_test_http_responses'] = [];
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
		$GLOBALS['progress_agentic_rag_test_cron'] = [];
		$GLOBALS['wpdb'] = new ProgressAgenticRagTestWpdb();
		update_option( SettingsRepository::OPTION_ZONE, 'europe-1' );
		update_option( SettingsRepository::OPTION_KBID, 'kb-123' );
		update_option( SettingsRepository::OPTION_TOKEN, 'test-token' );
		update_option( SettingsRepository::OPTION_API_IS_REACHABLE, 'yes' );
		update_option( SettingsRepository::OPTION_INDEXABLE_POST_TYPES, [ 'post' => 1 ] );
		$this->settings = new SettingsRepository();
		$this->store = $this->createMock( PreparationStore::class );
		$this->store->method( 'lock' )->willReturn( true );
		$this->store->method( 'bounds' )->willReturn( [ 'mapping_limit' => 10000, 'post_limit' => 10000 ] );
		$this->store->method( 'snapshot_mappings' )->willReturn( [ 'cursor' => 10000, 'done' => true ] );
		$this->store->method( 'snapshot_candidates' )->willReturn( [ 'cursor' => 10000, 'done' => true ] );
		$this->preparation = new ManualPreparation( $this->settings, new ApiClient( $this->settings ), new Scheduler(), $this->store );
	}

	public function test_scan_advances_one_page_per_job_and_reconciliation_waits_for_last_page(): void {
		$this->store->expects( self::once() )->method( 'apply_page' )->willReturn( [ 'cursor' => 10000, 'done' => true ] );
		$this->store->expects( self::exactly( 2 ) )->method( 'record_page' );
		$this->prepare_scan();
		$this->response( 200, [ 'resources' => [ [ 'id' => 'rid-first', 'slug' => '1' ] ], 'pagination' => [ 'page' => 0, 'last' => false ] ] );
		$step = $this->state()['step'];
		$this->next();
		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( 'scan', $this->state()['phase'] );
		self::assertSame( 1, $this->state()['page'] );
		$this->preparation->process( $this->state()['id'], $step );
		self::assertCount( 1, $GLOBALS['progress_agentic_rag_test_http_requests'], 'Duplicate checkpoints must do nothing.' );
		$this->response( 200, [ 'resources' => [ [ 'id' => 'rid-last', 'slug' => '10000' ] ], 'pagination' => [ 'page' => 1, 'last' => true ] ] );
		$this->next();
		self::assertSame( 'verify', $this->state()['phase'] );
		self::assertStringContainsString( 'page=1&size=250', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );
		$this->next();
		self::assertSame( 'apply', $this->state()['phase'] );
		$this->next();
		self::assertSame( 'queue', $this->state()['phase'] );
		self::assertTrue( $this->preparation->should_yield( false ), 'The runner must stop batching after preparation.' );
	}

	public function test_malformed_and_repeated_pages_fail_without_reconciling_mappings(): void {
		$this->store->expects( self::never() )->method( 'apply_page' );
		$this->prepare_scan();
		$page = [ 'resources' => [ [ 'id' => 'same-rid', 'slug' => '1' ] ], 'pagination' => [ 'last' => false ] ];
		$this->response( 200, $page );
		$this->next();
		$this->response( 200, $page );
		$this->next();
		self::assertSame( 'failed', $this->state()['status'] );
		self::assertStringContainsString( 'repeated a page', $this->state()['message'] );

		foreach ( [
			[ 'unexpected' => [] ],
			[ 'resources' => [], 'pagination' => [ 'last' => 'false' ] ],
			[ 'resources' => [], 'pagination' => [ 'page' => 99, 'last' => true ] ],
			[ 'resources' => [], 'pagination' => [ 'page' => 'invalid', 'last' => true ] ],
		] as $body ) {
			$this->prepare_scan();
			$this->response( 200, $body );
			$this->next();
			self::assertSame( 'failed', $this->state()['status'] );
			self::assertStringContainsString( 'invalid resource page', $this->state()['message'] );
		}
	}

	public function test_rate_limits_back_off_and_retry_only_the_saved_page_with_a_limit(): void {
		$this->store->expects( self::never() )->method( 'apply_page' );
		$this->prepare_scan();
		$step = $this->state()['step'];
		for ( $attempt = 1; $attempt <= 4; $attempt++ ) {
			$this->response( 429, [ 'detail' => 'Too many requests' ], [ 'Retry-After' => '90' ] );
			$this->next();
			self::assertSame( $step, $this->state()['step'] );
			self::assertSame( 0, $this->state()['page'] );
			if ( $attempt < 4 ) {
				self::assertSame( 'running', $this->state()['status'] );
				self::assertGreaterThanOrEqual( time() + 89, $this->state()['next_attempt'] );
				$this->next();
				self::assertCount( $attempt, $GLOBALS['progress_agentic_rag_test_http_requests'], 'An early retry must not call the API.' );
				$state = $this->state();
				$state['next_attempt'] = 0;
				update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
			}
		}
		self::assertSame( 'failed', $this->state()['status'] );
		self::assertStringContainsString( 'rate limit', $this->state()['message'] );
		self::assertCount( 4, $GLOBALS['progress_agentic_rag_test_http_requests'] );
	}

	public function test_watchdog_recovers_missing_continuation_without_scanning_and_rejects_old_runs(): void {
		$this->prepare_scan();
		$before = $this->state();
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
		$this->preparation->recover();
		$job = end( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( [ 'sync_id' => $before['id'], 'step' => $before['step'] ], $job['args'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertNotFalse( wp_next_scheduled( ManualPreparation::RECOVERY_HOOK ) );
		$this->preparation->process( 'obsolete-run', $before['step'] );
		self::assertSame( $before['page'], $this->state()['page'] );
		$state = $this->state();
		$state['next_attempt'] = 0;
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->response( 200, [ 'resources' => [], 'pagination' => [ 'page' => 0, 'last' => true ] ] );
		$this->next();
		self::assertSame( 'verify', $this->state()['phase'] );
		self::assertSame( 1, $this->state()['page'] );
	}

	public function test_repeatedly_interrupted_uploads_become_retryable_failed_items(): void {
		$this->prepare_scan();
		$state = $this->state();
		$state['phase'] = '';
		$state['total'] = 1;
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->store->method( 'outcome' )->willReturn( '' );
		$this->store->method( 'begin_item' )->willReturn( false );
		$this->store->expects( self::once() )->method( 'finish_item' )->with( $state['id'], 31, false );
		$this->store->expects( self::once() )->method( 'purge' )->with( $state['id'] );
		$this->store->method( 'stats' )->willReturn( [ 'total' => 1, 'completed' => 0, 'failed' => 1, 'recovered' => 0 ] );
		$this->preparation->process_item( 31, 'post', $state['id'], static function (): never { self::fail( 'Exhausted jobs must not upload again.' ); } );
		self::assertSame( 'complete', $this->state()['status'] );
		self::assertSame( 1, $this->state()['failed'] );
		self::assertSame( 31, $this->settings->get_failed_sync_items()['31|post']['post_id'] );
	}

	public function test_cancellation_and_changed_connection_prevent_further_scan_or_application(): void {
		$this->store->expects( self::never() )->method( 'apply_page' );
		$this->store->expects( self::exactly( 2 ) )->method( 'purge' );
		$this->prepare_scan();
		$before = $this->state();
		$this->preparation->cancel();
		$this->preparation->process( $before['id'], $before['step'] );
		self::assertSame( 'cancelled', $this->state()['status'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );

		$this->prepare_scan();
		update_option( SettingsRepository::OPTION_KBID, 'different-kb' );
		$this->next();
		self::assertSame( 'failed', $this->state()['status'] );
		self::assertStringContainsString( 'settings changed', $this->state()['message'] );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );
	}

	public function test_upload_outcomes_are_idempotent_and_missing_actions_are_requeued(): void {
		$this->prepare_scan();
		$state = $this->state();
		$state['phase'] = '';
		$state['total'] = 2;
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->store->method( 'outcome' )->willReturn( '', 'complete' );
		$this->store->method( 'begin_item' )->willReturn( true );
		$this->store->expects( self::once() )->method( 'finish_item' )->with( $state['id'], 31, true );
		$this->store->method( 'stats' )->willReturn( [ 'total' => 2, 'completed' => 1, 'failed' => 0, 'recovered' => 0 ] );
		$this->store->method( 'unfinished' )->willReturn( [ (object) [ 'post_id' => 32, 'post_type' => 'post' ] ] );
		$calls = 0;
		$sync = static function () use ( &$calls ): bool { $calls++; return true; };
		$this->preparation->process_item( 31, 'post', $state['id'], $sync );
		$this->preparation->process_item( 31, 'post', $state['id'], $sync );
		self::assertSame( 1, $calls );
		self::assertSame( 1, $this->state()['completed'] );
		$GLOBALS['progress_agentic_rag_test_scheduled_actions'] = [];
		$this->preparation->recover();
		$job = end( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( ManualPreparation::POST_HOOK, $job['hook'] );
		self::assertSame( 32, $job['args']['post_id'] );
	}

	public function test_preparation_status_never_reports_completion_or_mutates_the_checkpoint(): void {
		$this->prepare_scan();
		$state = $this->state();
		$state['total'] = 1;
		$state['completed'] = 1;
		$state['updated_at'] = time() - 600;
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$sync = new ManualSync( $this->settings, new ApiClient( $this->settings ), null, $this->store );
		for ( $poll = 0; $poll < 3; $poll++ ) {
			$status = $sync->status();
			self::assertSame( 'running', $status['status'] );
			self::assertSame( 0, $status['percent'] );
			self::assertTrue( $status['preparing'] );
			self::assertStringContainsString( 'WordPress cron', $status['message'] );
		}
		self::assertSame( $state, $this->state() );
		self::assertSame( [], $GLOBALS['progress_agentic_rag_test_http_requests'] );
	}

	public function test_verify_confirms_unseen_mappings_by_slug_before_apply_can_delete_them(): void {
		$this->prepare_scan();
		$state = $this->state();
		$state['phase'] = 'verify';
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->store->method( 'unseen' )->willReturn( [ (object) [ 'post_id' => 7 ], (object) [ 'post_id' => 8 ] ] );
		$this->store->expects( self::once() )->method( 'record_page' )->with( $state['id'], [ [ 'slug' => '7', 'rid' => 'rid-7', 'seqid' => '3' ] ] );
		$this->response( 200, [ 'id' => 'rid-7', 'slug' => '7', 'last_seqid' => 3 ] );
		$this->response( 404, [ 'detail' => 'Resource does not exist' ] );
		$this->next();
		self::assertSame( 'apply', $this->state()['phase'] );
		self::assertSame( 0, $this->state()['cursor'] );
		self::assertCount( 2, $GLOBALS['progress_agentic_rag_test_http_requests'] );
		self::assertStringContainsString( '/slug/7', $GLOBALS['progress_agentic_rag_test_http_requests'][0]['url'] );
		self::assertStringContainsString( '/slug/8', $GLOBALS['progress_agentic_rag_test_http_requests'][1]['url'] );
	}

	public function test_verify_keeps_progress_and_retries_when_the_slug_lookup_is_rate_limited(): void {
		$this->prepare_scan();
		$state = $this->state();
		$state['phase'] = 'verify';
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->store->method( 'unseen' )->willReturn( [ (object) [ 'post_id' => 7 ], (object) [ 'post_id' => 8 ] ] );
		$this->store->expects( self::once() )->method( 'record_page' );
		$this->response( 200, [ 'id' => 'rid-7', 'slug' => '7' ] );
		$this->response( 429, [], [ 'retry-after' => '60' ] );
		$this->next();
		self::assertSame( 'running', $this->state()['status'] );
		self::assertSame( 'verify', $this->state()['phase'] );
		self::assertSame( 7, $this->state()['cursor'], 'Confirmed mappings must not be looked up again.' );
		self::assertGreaterThan( time(), $this->state()['next_attempt'] );
	}

	public function test_cancel_reports_busy_without_touching_the_run_when_the_lock_is_held(): void {
		$this->prepare_scan();
		$before = $this->state();
		$busy = $this->createMock( PreparationStore::class );
		$busy->method( 'lock' )->willReturn( false );
		$busy->expects( self::never() )->method( 'purge' );
		$result = ( new ManualPreparation( $this->settings, new ApiClient( $this->settings ), new Scheduler(), $busy ) )->cancel();
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( $before, $this->state() );
	}

	public function test_cancel_removes_every_queued_step_and_item_regardless_of_group(): void {
		$this->prepare_scan();
		$run = $this->state()['id'];
		foreach ( [ ManualPreparation::HOOK, ManualPreparation::POST_HOOK ] as $hook ) {
			as_schedule_single_action( time(), $hook, [ 'sync_id' => $run ], 'progress-agentic-rag-indexing' );
		}
		as_schedule_single_action( time(), 'unrelated_hook', [], 'progress-agentic-rag-indexing' );
		$this->preparation->cancel();
		self::assertSame( [ 'unrelated_hook' ], array_column( $GLOBALS['progress_agentic_rag_test_scheduled_actions'], 'hook' ) );
	}

	public function test_scan_is_retried_later_when_the_lock_cannot_be_retaken_after_the_request(): void {
		$this->prepare_scan();
		$before = $this->state();
		$store = $this->createMock( PreparationStore::class );
		$store->method( 'lock' )->willReturnOnConsecutiveCalls( true, false );
		$store->expects( self::never() )->method( 'record_page' );
		$this->response( 200, [ 'resources' => [ [ 'id' => 'rid-first', 'slug' => '1' ] ], 'pagination' => [ 'page' => 0, 'last' => false ] ] );
		( new ManualPreparation( $this->settings, new ApiClient( $this->settings ), new Scheduler(), $store ) )->process( $before['id'], $before['step'] );
		self::assertSame( $before, $this->state() );
		$job = end( $GLOBALS['progress_agentic_rag_test_scheduled_actions'] );
		self::assertSame( [ 'sync_id' => $before['id'], 'step' => $before['step'] ], $job['args'] );
		self::assertGreaterThan( time() + 20, $job['timestamp'] );
	}

	public function test_a_cancel_during_slow_requests_is_not_overwritten_by_the_running_step_or_item(): void {
		$this->prepare_scan();
		$run = $this->state()['id'];
		$cancel = function () use ( $run ): void {
			$state = $this->state();
			$state['status'] = 'cancelled';
			update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		};
		$this->store->expects( self::never() )->method( 'record_page' );
		$this->store->method( 'unlock' )->willReturnCallback( $cancel );
		$this->response( 200, [ 'resources' => [ [ 'id' => 'rid-first', 'slug' => '1' ] ], 'pagination' => [ 'page' => 0, 'last' => false ] ] );
		$this->next();
		self::assertSame( 'cancelled', $this->state()['status'] );
		self::assertSame( 0, $this->state()['page'] );

		$state = $this->state();
		$state['status'] = 'running';
		$state['phase'] = '';
		$state['total'] = 1;
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->store->method( 'outcome' )->willReturn( '' );
		$this->store->method( 'begin_item' )->willReturn( true );
		$this->store->expects( self::once() )->method( 'finish_item' )->with( $run, 31, true );
		$this->store->expects( self::never() )->method( 'stats' );
		$this->preparation->process_item( 31, 'post', $run, static fn (): bool => true );
		self::assertSame( 'cancelled', $this->state()['status'] );
	}

	public function test_current_label_is_saved_before_unlocking_so_a_cancel_cannot_be_undone(): void {
		$this->prepare_scan();
		$state = $this->state();
		$state['phase'] = '';
		$state['total'] = 1;
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		$this->store->method( 'outcome' )->willReturn( '' );
		$this->store->method( 'begin_item' )->willReturn( true );
		$this->store->method( 'unlock' )->willReturnCallback( function (): void {
			self::assertSame( 'Post: Hello', $this->state()['current'], 'The label must be saved while the lock is still held.' );
			$state = $this->state();
			$state['status'] = 'cancelled';
			update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		} );
		$this->preparation->process_item( 31, 'post', $state['id'], static fn (): bool => true, 'Post: Hello' );
		self::assertSame( 'cancelled', $this->state()['status'] );
	}

	private function prepare_scan(): void {
		$this->preparation->begin( [ 'post' ] );
		$this->next();
		$this->next();
		self::assertSame( 'scan', $this->state()['phase'] );
	}

	private function next(): void {
		$state = $this->state();
		$this->preparation->process( $state['id'], $state['step'] );
	}

	private function state(): array {
		return get_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [] );
	}

	private function response( int $code, array $body, array $headers = [] ): void {
		$GLOBALS['progress_agentic_rag_test_http_responses'][] = [
			'response' => [ 'code' => $code ], 'body' => wp_json_encode( $body ), 'headers' => $headers,
		];
	}
}
