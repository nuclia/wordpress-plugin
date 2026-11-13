<?php
/**
 * Checkpointed manual sync preparation and recovery.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Indexing;

use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Settings\SettingsRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class ManualPreparation {
	public const HOOK = 'progress_agentic_rag_prepare_manual_sync';
	public const RECOVERY_HOOK = 'progress_agentic_rag_recover_manual_sync';
	public const POST_HOOK = 'progress_agentic_rag_manual_sync_post';
	private const GROUP = 'progress-agentic-rag-indexing';
	private const VERIFY_BATCH = 25;
	private const RELOCK_WAIT = 10;

	private bool $yield_runner = false;

	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly ApiClient $api,
		private readonly Scheduler $scheduler,
		private readonly PreparationStore $store
	) {
	}

	public function register(): void {
		add_action( self::HOOK, [ $this, 'process' ], 10, 2 );
		add_action( self::RECOVERY_HOOK, [ $this, 'recover' ] );
		add_filter( 'action_scheduler_maximum_execution_time_likely_to_be_exceeded', [ $this, 'should_yield' ] );
	}

	public function should_yield( bool $exceeded ): bool {
		return $exceeded || $this->yield_runner;
	}

	public function begin( array $types, string $trigger = 'manual' ): array|WP_Error {
		if ( ! $this->store->lock() ) {
			return new WP_Error( 'progress_agentic_rag_sync_busy', __( 'Sync is busy. Please retry shortly.', 'progress-agentic-rag-connector' ) );
		}
		try {
			$current = $this->state();
			if ( 'running' === ( $current['status'] ?? '' ) ) {
				return $current;
			}
			// A persisted recovery event closes the crash window before the first action is queued.
			if ( ! $this->arm_recovery() ) {
				return new WP_Error( 'progress_agentic_rag_recovery_unavailable', __( 'Could not schedule sync recovery. Check WordPress cron.', 'progress-agentic-rag-connector' ) );
			}
			$state = [
				'id' => wp_generate_uuid4(), 'status' => 'running', 'orchestrated' => true,
				'phase' => 'mappings', 'step' => 0, 'cursor' => 0, 'page' => 0, 'retry_count' => 0,
				'post_types' => $types, 'source' => $this->source(), 'trigger' => $trigger,
				'total' => 0, 'completed' => 0, 'failed' => 0, 'recovered' => 0,
				'current' => __( 'Waiting for background preparation.', 'progress-agentic-rag-connector' ),
				'message' => __( 'Preparing sync…', 'progress-agentic-rag-connector' ),
				'started_at' => time(), 'updated_at' => time(),
			];
			$this->save( $state );
			if ( ! $this->schedule( $state, time() ) ) {
				$this->fail( $state, __( 'Could not queue sync preparation. Please retry.', 'progress-agentic-rag-connector' ) );
				return new WP_Error( 'progress_agentic_rag_schedule_failed', $state['message'] );
			}
			return $state;
		} finally {
			$this->store->unlock();
		}
	}

	public function process( string $run, int $step ): void {
		if ( ! $this->store->lock() ) {
			$this->scheduler->schedule_single_action( time() + 30, self::HOOK, [ 'sync_id' => $run, 'step' => $step ], self::GROUP );
			return;
		}
		try {
			$state = $this->state();
			if ( ! $this->active( $state, $run ) || empty( $state['phase'] ) || $step !== (int) $state['step'] ) {
				return;
			}
			$this->yield_runner = true;
			if ( (int) ( $state['next_attempt'] ?? 0 ) > time() ) {
				$this->schedule( $state, $state['next_attempt'] );
				return;
			}
			if ( $state['source'] !== $this->source() || ! $this->settings->get_api_is_reachable() ) {
				$this->fail( $state, __( 'Connection settings changed during preparation. Start a new sync.', 'progress-agentic-rag-connector' ) );
				return;
			}
			$this->arm_recovery();
			$types = array_values( array_intersect( $state['post_types'], array_keys( array_filter( $this->settings->get_indexable_post_types() ) ) ) );
			if ( empty( $types ) ) {
				$this->fail( $state, __( 'Selected content types are no longer enabled. Start a new sync.', 'progress-agentic-rag-connector' ) );
				return;
			}
			switch ( $state['phase'] ) {
				case 'mappings':
					if ( empty( $state['table_ready'] ) ) {
						$this->store->initialize();
						$state += $this->store->bounds();
						$state['table_ready'] = true;
						$this->save( $state );
					}
					$chunk = $this->store->snapshot_mappings( $run, $state['cursor'], $state['mapping_limit'] );
					$state['cursor'] = $chunk['cursor'];
					if ( $chunk['done'] ) {
						$state['phase'] = 'candidates';
						$state['cursor'] = 0;
					}
					break;
				case 'candidates':
					$chunk = $this->store->snapshot_candidates( $run, $state['cursor'], $state['post_limit'], $types );
					$state['cursor'] = $chunk['cursor'];
					if ( $chunk['done'] ) {
						$state['phase'] = 'scan';
						$state['cursor'] = 0;
					}
					break;
				case 'scan':
					$remote = $this->remote( $run, $step, fn () => $this->api->resource_page( $state['page'] ) );
					if ( null === $remote ) {
						return;
					}
					[ $state, $page ] = $remote;
					if ( is_wp_error( $page ) ) {
						$this->retry( $state, $page );
						return;
					}
					if ( ! empty( $page['resources'] ) && ( $state['fingerprint'] ?? null ) === $page['fingerprint'] ) {
						$this->fail( $state, __( 'Resource lookup stopped because the API repeated a page. Existing mappings were preserved.', 'progress-agentic-rag-connector' ) );
						return;
					}
					$this->store->record_page( $run, $page['resources'] );
					$state['fingerprint'] = $page['fingerprint'];
					$state['page']++;
					if ( $page['last'] ) {
						$state['phase'] = 'verify';
						$state['cursor'] = 0;
					}
					break;
				case 'verify':
					// The listing is paginated by offset without a snapshot, so a resource can be skipped while the KB changes.
					// Confirm each mapping the scan did not see before apply may delete it.
					$rows = $this->store->unseen( $run, $state['cursor'], self::VERIFY_BATCH );
					$remote = empty( $rows ) ? [ $state, [ 'found' => [], 'error' => null ] ] : $this->remote( $run, $step, function () use ( $rows ) {
						$found = [];
						foreach ( $rows as $row ) {
							$resource = $this->api->find_resource_by_slug( (string) $row->post_id );
							if ( is_wp_error( $resource ) ) {
								return [ 'found' => $found, 'error' => $resource ];
							}
							$found[ (int) $row->post_id ] = $resource;
						}
						return [ 'found' => $found, 'error' => null ];
					} );
					if ( null === $remote ) {
						return;
					}
					[ $state, $verified ] = $remote;
					foreach ( $verified['found'] as $post_id => $resource ) {
						if ( '' !== $resource['rid'] ) {
							$this->store->record_page( $run, [ [ 'slug' => (string) $post_id, 'rid' => $resource['rid'], 'seqid' => $resource['seqid'] ] ] );
						}
						$state['cursor'] = $post_id;
					}
					if ( null !== $verified['error'] ) {
						$this->retry( $state, $verified['error'] );
						return;
					}
					if ( count( $rows ) < self::VERIFY_BATCH ) {
						$state['phase'] = 'apply';
						$state['cursor'] = 0;
					}
					break;
				case 'apply':
					$chunk = $this->store->apply_page( $run, $state['cursor'], $types );
					$state['cursor'] = $chunk['cursor'];
					if ( $chunk['done'] ) {
						$state['phase'] = 'queue';
						$state['cursor'] = 0;
					}
					break;
				case 'queue':
					$rows = $this->store->candidates( $run, $state['cursor'], $types );
					foreach ( $rows as $offset => $row ) {
						if ( ! $this->schedule_item( $run, (int) $row->post_id, $row->post_type, time() + ( ( $state['total'] + $offset ) * 2 ) ) ) {
							throw new \RuntimeException( __( 'Could not queue a resource sync.', 'progress-agentic-rag-connector' ) );
						}
						$this->store->mark_queued( $run, (int) $row->post_id );
						$state['cursor'] = (int) $row->post_id;
					}
					$state = array_replace( $state, $this->store->stats( $run ) );
					if ( count( $rows ) < 100 ) {
						$state['phase'] = '';
						$state['current'] = __( 'Waiting for the first entity.', 'progress-agentic-rag-connector' );
						$state['message'] = __( 'Preparation complete. Resources are queued for sync.', 'progress-agentic-rag-connector' );
						if ( $state['completed'] + $state['failed'] >= $state['total'] ) {
							$state['status'] = 'complete';
							$state['message'] = 0 === $state['total']
								? __( 'Everything selected is already synced.', 'progress-agentic-rag-connector' )
								: __( 'Sync complete.', 'progress-agentic-rag-connector' );
							if ( 0 === $state['total'] ) {
								$state['current'] = __( 'No eligible entities to sync.', 'progress-agentic-rag-connector' );
								if ( $state['recovered'] > 0 ) {
									$state['message'] = sprintf(
										/* translators: %d is the number of recovered upstream resources. */
										_n( 'Recovered %d existing upstream resource. Everything selected is already synced.', 'Recovered %d existing upstream resources. Everything selected is already synced.', $state['recovered'], 'progress-agentic-rag-connector' ),
										$state['recovered']
									);
								}
							}
							$this->record_terminal( $state );
						}
					}
					break;
			}
			$state['retry_count'] = 0;
			$state['next_attempt'] = 0;
			$state['step']++;
			$state['updated_at'] = time();
			if ( ! empty( $state['phase'] ) ) {
				$state['current'] = sprintf(
					/* translators: %d is the number of KB pages scanned. */
					__( 'Preparing sync: %d KB pages checked.', 'progress-agentic-rag-connector' ), $state['page']
				);
				$state['message'] = __( 'Preparing sync…', 'progress-agentic-rag-connector' );
			}
			$this->save( $state );
			if ( ! empty( $state['phase'] ) && ! $this->schedule( $state, time() + 2 ) ) {
				throw new \RuntimeException( __( 'Could not queue the next preparation step.', 'progress-agentic-rag-connector' ) );
			}
		} catch ( \Throwable $error ) {
			$state = $this->state();
			if ( $this->active( $state, $run ) ) {
				$this->retry( $state, new WP_Error( 'progress_agentic_rag_preparation_failed', sanitize_text_field( $error->getMessage() ) ) );
			}
		} finally {
			$this->store->unlock();
		}
	}

	public function process_item( int $id, string $type, string $run, callable $sync, string $label = '' ): void {
		if ( ! $this->store->lock() ) {
			$this->scheduler->schedule_single_action( time() + 30, self::POST_HOOK, [ 'post_id' => $id, 'post_type' => $type, 'sync_id' => $run ], self::GROUP );
			return;
		}
		try {
			$state = $this->state();
			if ( ! $this->active( $state, $run ) || ( ! empty( $state['phase'] ) && 'queue' !== $state['phase'] ) ) {
				return;
			}
			$outcome = $this->store->outcome( $run, $id );
			if ( null === $outcome ) {
				return;
			}
			$result = null;
			// An item that already finished only refreshes the counters, which repairs a lost final update.
			if ( '' === $outcome ) {
				if ( $state['source'] !== $this->source() ) {
					$this->fail( $state, __( 'Connection settings changed. Start a new sync.', 'progress-agentic-rag-connector' ) );
					return;
				}
				if ( $this->store->begin_item( $run, $id ) ) {
					if ( '' !== $label ) {
						// Written while locked: saving the state after unlocking could undo a concurrent cancel.
						$state['current']    = $label;
						$state['updated_at'] = time();
						$this->save( $state );
					}
					// The upload is slow, so do not hold the lock (and block cancelling) while it runs.
					$this->store->unlock();
					try {
						$result = $sync();
						$this->store->finish_item( $run, $id, ! is_wp_error( $result ) );
					} finally {
						$relocked = $this->store->lock( self::RELOCK_WAIT );
					}
					if ( ! $relocked ) {
						$this->scheduler->schedule_single_action( time() + 30, self::POST_HOOK, [ 'post_id' => $id, 'post_type' => $type, 'sync_id' => $run ], self::GROUP );
						return;
					}
					$state = $this->state();
					if ( ! $this->active( $state, $run ) ) {
						return;
					}
				} else {
					$result = new WP_Error( 'progress_agentic_rag_item_interrupted', __( 'Resource sync was repeatedly interrupted. Retry this item after checking background scheduling.', 'progress-agentic-rag-connector' ) );
					$this->settings->add_failed_sync_item( $id, $type, get_the_title( $id ), $result->get_error_message(), 'manual' );
					$this->store->finish_item( $run, $id, false );
				}
			}
			$state = array_replace( $state, $this->store->stats( $run ) );
			if ( is_wp_error( $result ) ) {
				$state['current'] = sanitize_text_field( $result->get_error_message() );
			}
			if ( empty( $state['phase'] ) && $state['completed'] + $state['failed'] >= $state['total'] ) {
				$state['status'] = 'complete';
				$state['message'] = __( 'Sync complete.', 'progress-agentic-rag-connector' );
				$this->record_terminal( $state );
			}
			$state['updated_at'] = time();
			$this->save( $state );
		} finally {
			$this->store->unlock();
		}
	}

	public function recover(): void {
		$state = $this->state();
		if ( empty( $state['orchestrated'] ) || 'running' !== ( $state['status'] ?? '' ) ) {
			return;
		}
		$this->arm_recovery();
		if ( ! $this->store->lock() ) {
			return;
		}
		try {
			$state = $this->state();
			if ( ! $this->active( $state, (string) ( $state['id'] ?? '' ) ) ) {
				return;
			}
			if ( ! empty( $state['phase'] ) ) {
				$args = [ 'sync_id' => $state['id'], 'step' => $state['step'] ];
				if ( ! $this->scheduler->has_action( self::HOOK, $args, self::GROUP ) ) {
					$this->retry( $state, new WP_Error( 'progress_agentic_rag_preparation_interrupted', __( 'Preparation was interrupted. Resuming the saved checkpoint.', 'progress-agentic-rag-connector' ) ) );
				}
			} elseif ( ! empty( $state['table_ready'] ) ) {
				foreach ( $this->store->unfinished( $state['id'] ) as $row ) {
					$this->schedule_item( $state['id'], (int) $row->post_id, $row->post_type, time() );
				}
			}
		} finally {
			$this->store->unlock();
		}
	}

	public function cancel(): array|WP_Error {
		if ( ! $this->store->lock( self::RELOCK_WAIT ) ) {
			return new WP_Error( 'progress_agentic_rag_sync_busy', __( 'A sync step is finishing. Please retry cancellation shortly.', 'progress-agentic-rag-connector' ) );
		}
		try {
			$state = $this->state();
			if ( empty( $state['orchestrated'] ) || 'running' !== ( $state['status'] ?? '' ) ) {
				return $state;
			}
			$state['status'] = 'cancelled';
			$state['phase'] = '';
			$state['message'] = __( 'Sync cancelled. Completed work was retained.', 'progress-agentic-rag-connector' );
			$this->record_terminal( $state );
			$this->save( $state );
			$this->scheduler->unschedule_all_actions( self::HOOK, null, '' );
			$this->scheduler->unschedule_all_actions( self::POST_HOOK, null, '' );
			return $state;
		} finally {
			$this->store->unlock();
		}
	}

	// Runs slow remote work without the lock so a cancel is never blocked behind it. Returns the fresh state and the
	// result, or null when the step is no longer current.
	private function remote( string $run, int $step, callable $work ): ?array {
		$this->store->unlock();
		try {
			$result = $work();
		} finally {
			$relocked = $this->store->lock( self::RELOCK_WAIT );
		}
		if ( ! $relocked ) {
			$this->scheduler->schedule_single_action( time() + 30, self::HOOK, [ 'sync_id' => $run, 'step' => $step ], self::GROUP );
			return null;
		}
		$state = $this->state();
		if ( ! $this->active( $state, $run ) || $step !== (int) $state['step'] ) {
			return null;
		}
		return [ $state, $result ];
	}

	private function retry( array $state, WP_Error $error ): void {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];
		$state['retry_count'] = (int) ( $state['retry_count'] ?? 0 ) + 1;
		if ( false === ( $data['retryable'] ?? true ) || $state['retry_count'] > 3 ) {
			$this->fail( $state, sanitize_text_field( $error->get_error_message() ) );
			return;
		}
		$delay = max( 30 * ( 2 ** ( $state['retry_count'] - 1 ) ), (int) ( $data['retry_after'] ?? 0 ) );
		$state['next_attempt'] = time() + min( 3600, $delay );
		$state['updated_at'] = time();
		$state['message'] = sanitize_text_field( $error->get_error_message() );
		$state['current'] = __( 'Waiting to retry the saved preparation step.', 'progress-agentic-rag-connector' );
		$this->save( $state );
		$this->schedule( $state, $state['next_attempt'] );
	}

	private function fail( array &$state, string $message ): void {
		$state['status'] = 'failed';
		$state['message'] = $message;
		$state['updated_at'] = time();
		$this->record_terminal( $state );
		$this->save( $state );
	}

	private function schedule( array $state, int $at ): bool {
		return $this->scheduler->schedule_single_action( $at, self::HOOK, [ 'sync_id' => $state['id'], 'step' => $state['step'] ], self::GROUP );
	}

	private function schedule_item( string $run, int $id, string $type, int $at ): bool {
		$args = [ 'post_id' => $id, 'post_type' => $type, 'sync_id' => $run ];
		return $this->scheduler->has_action( self::POST_HOOK, $args, self::GROUP )
			|| $this->scheduler->schedule_single_action( $at, self::POST_HOOK, $args, self::GROUP );
	}

	private function arm_recovery(): bool {
		return (bool) wp_next_scheduled( self::RECOVERY_HOOK )
			|| (bool) wp_schedule_single_event( time() + 120, self::RECOVERY_HOOK );
	}

	private function active( array $state, string $run ): bool {
		return ! empty( $state['orchestrated'] ) && $run === ( $state['id'] ?? '' ) && 'running' === ( $state['status'] ?? '' );
	}

	private function state(): array {
		$state = get_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [] );
		return is_array( $state ) ? $state : [];
	}

	private function save( array $state ): void {
		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state, false );
	}

	private function source(): string {
		return hash( 'sha256', $this->settings->get_string( SettingsRepository::OPTION_ZONE ) . "\0"
			. $this->settings->get_string( SettingsRepository::OPTION_KBID ) . "\0" . $this->settings->get_string( SettingsRepository::OPTION_TOKEN ) );
	}

	private function record_terminal( array &$state ): void {
		if ( ! empty( $state['history_recorded'] ) ) {
			return;
		}
		$this->settings->add_sync_history_entry( [
			'id' => $state['id'], 'type' => 'automatic' === ( $state['trigger'] ?? '' ) ? 'automatic_sync' : 'manual_sync',
			'status' => 'complete' === $state['status'] && $state['failed'] > 0 ? 'failed' : $state['status'],
			'total' => $state['total'], 'completed' => $state['completed'], 'failed' => $state['failed'],
			'message' => $state['message'], 'current' => $state['current'],
			'started_at' => $state['started_at'], 'finished_at' => time(),
		] );
		$state['history_recorded'] = true;
		if ( ! empty( $state['table_ready'] ) ) {
			$this->store->purge( $state['id'] );
		}
	}
}
