<?php
/**
 * Manual indexing scheduler.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Indexing;

use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Settings\SettingsRepository;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ManualSync {
	private const HOOK_PROCESS_SINGLE     = 'progress_agentic_rag_manual_sync_post';
	private const HOOK_BACKGROUND_SYNC    = 'progress_agentic_rag_background_sync_post';
	private const HOOK_BACKGROUND_DELETE  = 'progress_agentic_rag_background_delete_resource';
	private const HOOK_REPROCESS_LABELS   = 'progress_agentic_rag_reprocess_resource_labels';
	private const HOOK_DELETE_RESOURCE    = 'progress_agentic_rag_delete_synced_resource';
	private const GROUP                   = 'progress-agentic-rag-indexing';
	private const GROUP_BACKGROUND        = 'progress-agentic-rag-background';
	private const GROUP_LABEL_REPROCESSOR = 'progress-agentic-rag-labels';
	private const GROUP_DELETE            = 'progress-agentic-rag-delete';

	private readonly SettingsRepository $settings;
	private readonly ApiClient $api_client;
	private readonly Scheduler $scheduler;

	public function __construct( SettingsRepository $settings, ApiClient $api_client, ?Scheduler $scheduler = null ) {
		$this->settings  = $settings;
		$this->api_client = $api_client;
		$this->scheduler  = $scheduler ?? new Scheduler();
	}

	public function register(): void {
		add_action( self::HOOK_PROCESS_SINGLE, [ $this, 'process_single_post' ], 10, 3 );
		add_action( self::HOOK_BACKGROUND_SYNC, [ $this, 'process_background_post' ], 10, 3 );
		add_action( self::HOOK_BACKGROUND_DELETE, [ $this, 'process_background_delete' ], 10, 3 );
		add_action( self::HOOK_REPROCESS_LABELS, [ $this, 'process_single_label_reprocess' ], 10, 3 );
		add_action( self::HOOK_DELETE_RESOURCE, [ $this, 'process_single_delete' ], 10, 3 );
		add_action( 'save_post', [ $this, 'schedule_background_post_sync' ], 10, 3 );
		add_action( 'add_attachment', [ $this, 'schedule_background_attachment_sync' ], 10, 1 );
		add_action( 'attachment_updated', [ $this, 'schedule_background_attachment_sync' ], 10, 1 );
		add_action( 'delete_post', [ $this, 'schedule_background_delete' ], 10, 2 );
	}

	/**
	 * @param list<string> $post_types
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function start( array $post_types ): array|WP_Error {
		$current = $this->state();
		if ( 'running' === ( $current['status'] ?? '' ) ) {
			return $this->status();
		}

		if ( 'running' === ( $this->delete_state()['status'] ?? '' ) ) {
			return new WP_Error( 'progress_agentic_rag_delete_running', __( 'Wait for synced resource deletion to finish before starting a manual sync.', 'progress-agentic-rag-connector' ) );
		}

		if ( ! $this->scheduler_available() ) {
			return new WP_Error( 'progress_agentic_rag_scheduler_missing', __( 'Background scheduling is not available.', 'progress-agentic-rag-connector' ) );
		}

		if ( ! $this->settings->get_api_is_reachable() ) {
			return new WP_Error( 'progress_agentic_rag_connection_missing', __( 'Validate the Progress Agentic RAG connection before syncing content.', 'progress-agentic-rag-connector' ) );
		}

		$post_types = $this->sanitize_post_types( $post_types );
		if ( empty( $post_types ) ) {
			return new WP_Error( 'progress_agentic_rag_no_post_types', __( 'Select at least one content type to sync.', 'progress-agentic-rag-connector' ) );
		}

		$reconcile = $this->api_client->reconcile_synced_resources();
		if ( is_wp_error( $reconcile ) ) {
			return $reconcile;
		}

		$entities = $this->unindexed_entities( $post_types );
		$recovered = 0;
		if ( ! empty( $entities ) ) {
			$post_ids = [];
			foreach ( $entities as $entity ) {
				$post_ids[] = $entity['post_id'];
			}

			$recovery = $this->api_client->recover_existing_resources( $post_ids );
			if ( is_wp_error( $recovery ) ) {
				return $recovery;
			}

			$recovered = $recovery;
			if ( $recovered > 0 ) {
				$entities = $this->unindexed_entities( $post_types );
			}
		}

		$sync_id  = wp_generate_uuid4();
		$total    = count( $entities );
		$message  = 0 === $total ? __( 'Everything selected is already synced.', 'progress-agentic-rag-connector' ) : sprintf(
			/* translators: %d is the number of entities queued for manual sync. */
			_n( 'Sync in progress: 0 of %d processed.', 'Sync in progress: 0 of %d processed.', $total, 'progress-agentic-rag-connector' ),
			$total
		);

		if ( $recovered > 0 ) {
			$message = 0 === $total ? sprintf(
				/* translators: %d is the number of upstream resources recovered into the local sync table. */
				_n( 'Recovered %d existing upstream resource. Everything selected is already synced.', 'Recovered %d existing upstream resources. Everything selected is already synced.', $recovered, 'progress-agentic-rag-connector' ),
				$recovered
			) : sprintf(
				/* translators: 1: recovered upstream resource count, 2: number of entities queued for manual sync. */
				_n( 'Recovered %1$d existing upstream resource. Sync in progress: 0 of %2$d processed.', 'Recovered %1$d existing upstream resources. Sync in progress: 0 of %2$d processed.', $recovered, 'progress-agentic-rag-connector' ),
				$recovered,
				$total
			);
		}

		$state    = [
			'id'         => $sync_id,
			'status'     => 0 === $total ? 'complete' : 'running',
			'total'      => $total,
			'completed'  => 0,
			'failed'     => 0,
			'recovered'  => $recovered,
			'current'    => 0 === $total ? __( 'No eligible entities to sync.', 'progress-agentic-rag-connector' ) : __( 'Waiting for the first entity.', 'progress-agentic-rag-connector' ),
			'message'    => $message,
			'started_at' => time(),
			'updated_at' => time(),
		];

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );

		foreach ( $entities as $offset => $entity ) {
			$this->schedule_single_action(
				time() + ( $offset * 2 ),
				self::HOOK_PROCESS_SINGLE,
				[
					'post_id'   => $entity['post_id'],
					'post_type' => $entity['post_type'],
					'sync_id'   => $sync_id,
				],
				self::GROUP
			);
		}

		return $this->status();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function status(): array {
		$state = $this->state();
		$total = (int) ( $state['total'] ?? 0 );
		$done  = (int) ( $state['completed'] ?? 0 ) + (int) ( $state['failed'] ?? 0 );
		$done  = min( $total, $done );
		$recovered = (int) ( $state['recovered'] ?? 0 );

		if ( $total > 0 && $done >= $total && 'running' === ( $state['status'] ?? '' ) ) {
			$state['status']     = 'complete';
			$state['message']    = __( 'Manual sync complete.', 'progress-agentic-rag-connector' );
			$state['updated_at'] = time();
			$state               = $this->record_history( $state, 'manual_sync', (int) ( $state['completed'] ?? 0 ) );
			update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
		}

		$current = (string) ( $state['current'] ?? __( 'Waiting to sync.', 'progress-agentic-rag-connector' ) );
		$message = (string) ( $state['message'] ?? '' );

		if ( 'running' === ( $state['status'] ?? '' ) ) {
			$message = sprintf(
				/* translators: 1: processed entity count, 2: total entity count. */
				__( 'Sync in progress: %1$d of %2$d processed.', 'progress-agentic-rag-connector' ),
				$done,
				$total
			);

			if ( $recovered > 0 ) {
				$message = sprintf(
					/* translators: 1: recovered upstream resource count, 2: processed entity count, 3: total entity count. */
					_n( 'Recovered %1$d existing upstream resource. Sync in progress: %2$d of %3$d processed.', 'Recovered %1$d existing upstream resources. Sync in progress: %2$d of %3$d processed.', $recovered, 'progress-agentic-rag-connector' ),
					$recovered,
					$done,
					$total
				);
			}

			if ( '' === $current || __( 'Queued manual sync.', 'progress-agentic-rag-connector' ) === $current ) {
				$current = 0 === $done ? __( 'Waiting for the first entity.', 'progress-agentic-rag-connector' ) : __( 'Waiting for the next entity.', 'progress-agentic-rag-connector' );
			}
		}

		return [
			'id'        => (string) ( $state['id'] ?? '' ),
			'status'    => (string) ( $state['status'] ?? 'idle' ),
			'total'     => $total,
			'completed' => (int) ( $state['completed'] ?? 0 ),
			'failed'    => (int) ( $state['failed'] ?? 0 ),
			'recovered' => $recovered,
			'processed' => $done,
			'current'   => $current,
			'message'   => $message,
			'percent'   => $total > 0 ? min( 100, (int) floor( ( $done / $total ) * 100 ) ) : 100,
		];
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function retry_failed_sync_items(): array|WP_Error {
		if ( ! $this->scheduler_available() ) {
			return new WP_Error( 'progress_agentic_rag_scheduler_missing', __( 'Background scheduling is not available.', 'progress-agentic-rag-connector' ) );
		}

		if ( ! $this->settings->get_api_is_reachable() ) {
			return new WP_Error( 'progress_agentic_rag_connection_missing', __( 'Validate the Progress Agentic RAG connection before retrying failed sync items.', 'progress-agentic-rag-connector' ) );
		}

		if ( 'running' === ( $this->state()['status'] ?? '' ) || 'running' === ( $this->delete_state()['status'] ?? '' ) ) {
			return new WP_Error( 'progress_agentic_rag_sync_running', __( 'Wait for the current sync operation to finish before retrying failed items.', 'progress-agentic-rag-connector' ) );
		}

		$items = $this->settings->get_failed_sync_items();
		if ( empty( $items ) ) {
			$status              = $this->background_sync_status();
			$status['scheduled'] = 0;
			$status['message']   = __( 'No failed sync items are waiting for retry.', 'progress-agentic-rag-connector' );
			return $status;
		}

		$scheduled = 0;
		foreach ( $items as $item ) {
			$post_id   = (int) ( $item['post_id'] ?? 0 );
			$post_type = (string) ( $item['post_type'] ?? '' );
			$post      = get_post( $post_id );

			if ( ! $post instanceof WP_Post || ! $this->is_selected_post_type( $post_type ) || ! $this->is_indexable_post( $post, $post_type ) || $this->is_background_sync_scheduled( $post_id, $post_type ) ) {
				continue;
			}

			$background_id = $this->queue_background_sync( $this->entity_label( $post ) );
			$this->schedule_single_action(
				time() + ( $scheduled * 2 ),
				self::HOOK_BACKGROUND_SYNC,
				[
					'post_id'       => $post_id,
					'post_type'     => $post_type,
					'background_id' => $background_id,
				],
				self::GROUP_BACKGROUND
			);
			$scheduled++;
		}

		$this->settings->clear_failed_sync_items();

		$status              = $this->background_sync_status();
		$status['scheduled'] = $scheduled;
		$status['message']   = sprintf(
			/* translators: %d is the number of failed sync items scheduled for retry. */
			_n( 'Scheduled retry for %d failed sync item.', 'Scheduled retries for %d failed sync items.', $scheduled, 'progress-agentic-rag-connector' ),
			$scheduled
		);

		return $status;
	}

	/**
	 * @param list<string> $post_types
	 *
	 * @return array<string, mixed>
	 */
	public function sync_status( array $post_types ): array {
		$rows            = [];
		$total_indexable = 0;
		$total_synced    = 0;

		foreach ( $this->sanitize_post_types( $post_types ) as $post_type ) {
			$indexable = $this->count_indexable_entities( $post_type );
			$synced    = $this->count_synced_entities( $post_type );

			$rows[] = [
				'post_type' => $post_type,
				'label'     => $this->post_type_label( $post_type ),
				'indexable' => $indexable,
				'synced'    => $synced,
				'remaining' => max( 0, $indexable - $synced ),
			];

			$total_indexable += $indexable;
			$total_synced    += $synced;
		}

		return [
			'rows'            => $rows,
			'total_indexable' => $total_indexable,
			'total_synced'    => $total_synced,
			'total_remaining' => max( 0, $total_indexable - $total_synced ),
			'total_mapped'    => $this->count_synced_resources(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function background_sync_status(): array {
		$state     = $this->background_state();
		$total     = (int) ( $state['total'] ?? 0 );
		$completed = (int) ( $state['completed'] ?? 0 );
		$failed    = (int) ( $state['failed'] ?? 0 );
		$background_id = (string) ( $state['id'] ?? '' );
		if ( '' !== $background_id ) {
			$action_completed = $this->count_background_actions_for_id( $this->action_status( 'STATUS_COMPLETE', 'complete' ), $background_id );
			$action_failed    = $this->count_background_actions_for_id( $this->action_status( 'STATUS_FAILED', 'failed' ), $background_id );
			$completed        = max( $completed, $action_completed );
			$failed           = max( $failed, $action_failed );
			if ( $completed !== (int) ( $state['completed'] ?? 0 ) || $failed !== (int) ( $state['failed'] ?? 0 ) ) {
				$state['completed']  = $completed;
				$state['failed']     = $failed;
				$state['updated_at'] = time();
				update_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $state );
			}
		}
		$processed        = min( $total, $completed + $failed );
		$pending          = $this->count_background_actions( $this->action_status( 'STATUS_PENDING', 'pending' ) );
		$running          = $this->count_background_actions( $this->action_status( 'STATUS_RUNNING', 'in-progress' ) );
		$action_failed    = $this->count_background_actions( $this->action_status( 'STATUS_FAILED', 'failed' ) );

		if ( $total > 0 && $processed >= $total && 'running' === ( $state['status'] ?? '' ) ) {
			$state['status']     = 'complete';
			$state['message']    = $failed > 0
				? __( 'Automatic background sync finished with failures.', 'progress-agentic-rag-connector' )
				: __( 'Automatic background sync complete.', 'progress-agentic-rag-connector' );
			$state['updated_at'] = time();
			$state               = $this->record_history( $state, 'automatic_sync', $completed );
			update_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $state );
		}

		if ( 'running' === ( $state['status'] ?? '' ) ) {
			$state['message'] = sprintf(
				/* translators: 1: processed entity count, 2: total queued entity count. */
				__( 'Automatic sync in progress: %1$d of %2$d processed.', 'progress-agentic-rag-connector' ),
				$processed,
				$total
			);
		}

		$percent = $total > 0 ? min( 100, (int) floor( ( $processed / $total ) * 100 ) ) : 100;
		if ( $total > 0 && 'complete' === ( $state['status'] ?? '' ) && $failed > 0 ) {
			$percent = min( 100, (int) floor( ( $completed / $total ) * 100 ) );
		}

		return [
			'id'            => (string) ( $state['id'] ?? '' ),
			'status'        => (string) ( $state['status'] ?? 'idle' ),
			'total'         => $total,
			'completed'     => $completed,
			'failed'        => $failed,
			'processed'     => $processed,
			'pending'       => $pending,
			'running'       => $running,
			'action_failed' => $action_failed,
			'current'       => (string) ( $state['current'] ?? __( 'No automatic sync running.', 'progress-agentic-rag-connector' ) ),
			'message'       => (string) ( $state['message'] ?? __( 'No automatic sync running.', 'progress-agentic-rag-connector' ) ),
			'percent'       => $percent,
			'is_active'     => 'running' === ( $state['status'] ?? '' ) || $pending > 0 || $running > 0,
		];
	}

	/**
	 * Queue unsynced selected content for automatic background sync.
	 *
	 * @return array<string, mixed>
	 */
	public function ensure_automatic_sync( bool $retry_failed = false ): array {
		if ( ! $this->settings->get_api_is_reachable() || ! $this->scheduler_available() ) {
			return $this->background_sync_status();
		}

		if ( 'running' === ( $this->state()['status'] ?? '' ) || 'running' === ( $this->delete_state()['status'] ?? '' ) ) {
			return $this->background_sync_status();
		}

		$status = $this->background_sync_status();
		if ( ! empty( $status['is_active'] ) ) {
			return $status;
		}

		$post_types = $this->sanitize_post_types( array_keys( array_filter( $this->settings->get_indexable_post_types() ) ) );
		if ( empty( $post_types ) ) {
			return $this->background_sync_status();
		}

		$reconcile = $this->api_client->reconcile_synced_resources();
		if ( is_wp_error( $reconcile ) ) {
			return $this->background_sync_status();
		}

		$entities   = $this->unindexed_entities( $post_types );
		if ( ! empty( $entities ) ) {
			$post_ids = [];
			foreach ( $entities as $entity ) {
				$post_ids[] = $entity['post_id'];
			}

			$recovery = $this->api_client->recover_existing_resources( $post_ids );
			if ( ! is_wp_error( $recovery ) && $recovery > 0 ) {
				$entities = $this->unindexed_entities( $post_types );
			}
		}

		$state = $this->background_state();
		if ( ! $retry_failed && 'complete' === ( $state['status'] ?? '' ) && (int) ( $state['failed'] ?? 0 ) > 0 && ! empty( $this->settings->get_failed_sync_items() ) ) {
			return $status;
		}

		$scheduled  = $this->schedule_background_entities( $entities );
		$status     = $this->background_sync_status();
		$status['scheduled'] = $scheduled;

		return $status;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function delete_synced_resources(): array|WP_Error {
		$current = $this->delete_state();
		if ( 'running' === ( $current['status'] ?? '' ) ) {
			return $this->delete_status();
		}

		if ( 'running' === ( $this->state()['status'] ?? '' ) ) {
			return new WP_Error( 'progress_agentic_rag_manual_sync_running', __( 'Wait for manual sync to finish before deleting synced resources.', 'progress-agentic-rag-connector' ) );
		}

		if ( ! $this->scheduler_available() ) {
			return new WP_Error( 'progress_agentic_rag_scheduler_missing', __( 'Background scheduling is not available.', 'progress-agentic-rag-connector' ) );
		}

		if ( ! $this->settings->get_api_is_reachable() ) {
			return new WP_Error( 'progress_agentic_rag_connection_missing', __( 'Validate the Progress Agentic RAG connection before deleting synced resources.', 'progress-agentic-rag-connector' ) );
		}

		$this->cancel_label_reprocess();
		$this->settings->clear_labels();

		$resources = $this->api_client->get_synced_resources();
		$total     = count( $resources );
		$delete_id = wp_generate_uuid4();
		$state     = [
			'id'         => $delete_id,
			'status'     => 0 === $total ? 'complete' : 'running',
			'total'      => $total,
			'deleted'    => 0,
			'failed'     => 0,
			'current'    => 0 === $total ? __( 'No synced resources to delete.', 'progress-agentic-rag-connector' ) : __( 'Waiting for the first resource.', 'progress-agentic-rag-connector' ),
			'message'    => 0 === $total ? __( 'No synced resources to delete. Label settings cleared.', 'progress-agentic-rag-connector' ) : sprintf(
				/* translators: %d is the number of resources queued for deletion. */
				_n( 'Delete in progress: 0 of %d processed.', 'Delete in progress: 0 of %d processed.', $total, 'progress-agentic-rag-connector' ),
				$total
			),
			'started_at' => time(),
			'updated_at' => time(),
		];

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, $state );

		$scheduled = 0;

		foreach ( $resources as $resource ) {
			$post_id = isset( $resource->post_id ) ? (int) $resource->post_id : 0;
			$rid     = isset( $resource->nuclia_rid ) ? (string) $resource->nuclia_rid : '';

			if ( $post_id <= 0 || '' === $rid ) {
				$this->mark_delete_failed( __( 'Missing synced resource.', 'progress-agentic-rag-connector' ) );
				continue;
			}

			$this->schedule_single_action(
				time() + ( $scheduled * 2 ),
				self::HOOK_DELETE_RESOURCE,
				[
					'post_id'   => $post_id,
					'rid'       => $rid,
					'delete_id' => $delete_id,
				],
				self::GROUP_DELETE
			);

			$scheduled++;
		}

		return $this->delete_status();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function delete_status(): array {
		$state = $this->delete_state();
		$total = (int) ( $state['total'] ?? 0 );
		$deleted = (int) ( $state['deleted'] ?? 0 );
		$failed  = (int) ( $state['failed'] ?? 0 );
		$delete_id = (string) ( $state['id'] ?? '' );
		if ( '' !== $delete_id ) {
			$action_deleted = $this->count_delete_actions_for_id( $this->action_status( 'STATUS_COMPLETE', 'complete' ), $delete_id );
			$action_failed  = $this->count_delete_actions_for_id( $this->action_status( 'STATUS_FAILED', 'failed' ), $delete_id );
			$deleted        = max( $deleted, $action_deleted );
			$failed         = max( $failed, $action_failed );
			if ( $deleted !== (int) ( $state['deleted'] ?? 0 ) || $failed !== (int) ( $state['failed'] ?? 0 ) ) {
				$state['deleted']    = $deleted;
				$state['failed']     = $failed;
				$state['updated_at'] = time();
				update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, $state );
			}
		}
		$done  = $deleted + $failed;
		$done  = min( $total, $done );

		if ( $total > 0 && $done >= $total && 'running' === ( $state['status'] ?? '' ) ) {
			$state['status']     = 'complete';
			$state['message']    = 0 === (int) ( $state['failed'] ?? 0 )
				? __( 'Synced resources deleted from Progress Agentic RAG. Local mappings and label settings cleared.', 'progress-agentic-rag-connector' )
				: __( 'Some synced resources could not be deleted. Label settings were cleared; local mappings were cleared only for successful deletions.', 'progress-agentic-rag-connector' );
			$state['updated_at'] = time();
			$state               = $this->record_history( $state, 'delete_synced', (int) ( $state['deleted'] ?? 0 ) );
			update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, $state );
		}

		$current = (string) ( $state['current'] ?? __( 'Waiting to delete synced resources.', 'progress-agentic-rag-connector' ) );
		$message = (string) ( $state['message'] ?? '' );

		if ( 'running' === ( $state['status'] ?? '' ) ) {
			$message = sprintf(
				/* translators: 1: processed resource count, 2: total resource count. */
				__( 'Delete in progress: %1$d of %2$d processed.', 'progress-agentic-rag-connector' ),
				$done,
				$total
			);

			if ( '' === $current ) {
				$current = 0 === $done ? __( 'Waiting for the first resource.', 'progress-agentic-rag-connector' ) : __( 'Waiting for the next resource.', 'progress-agentic-rag-connector' );
			}
		}

		return [
			'id'        => (string) ( $state['id'] ?? '' ),
			'status'    => (string) ( $state['status'] ?? 'idle' ),
			'total'     => $total,
			'deleted'   => $deleted,
			'completed' => $deleted,
			'failed'    => $failed,
			'processed' => $done,
			'current'   => $current,
			'message'   => $message,
			'percent'   => $total > 0 ? min( 100, (int) floor( ( $done / $total ) * 100 ) ) : 100,
		];
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function start_label_reprocess(): array|WP_Error {
		if ( ! $this->scheduler_available() ) {
			return new WP_Error( 'progress_agentic_rag_scheduler_missing', __( 'Background scheduling is not available.', 'progress-agentic-rag-connector' ) );
		}

		if ( ! $this->settings->get_api_is_reachable() ) {
			return new WP_Error( 'progress_agentic_rag_connection_missing', __( 'Validate the Progress Agentic RAG connection before reprocessing labels.', 'progress-agentic-rag-connector' ) );
		}

		if ( 'running' === ( $this->delete_state()['status'] ?? '' ) ) {
			return new WP_Error( 'progress_agentic_rag_delete_running', __( 'Wait for synced resource deletion to finish before reprocessing labels.', 'progress-agentic-rag-connector' ) );
		}

		$status = $this->label_reprocess_status();
		if ( ! empty( $status['is_active'] ) ) {
			return new WP_Error( 'progress_agentic_rag_label_reprocess_running', __( 'Label reprocessing is already running.', 'progress-agentic-rag-connector' ) );
		}

		$resources    = $this->api_client->get_synced_resources();
		$reprocess_id = wp_generate_uuid4();
		$scheduled    = 0;

		foreach ( $resources as $resource ) {
			$post_id = isset( $resource->post_id ) ? (int) $resource->post_id : 0;
			$rid     = isset( $resource->nuclia_rid ) ? (string) $resource->nuclia_rid : '';

			if ( $post_id <= 0 || '' === $rid ) {
				continue;
			}

			$this->schedule_single_action(
				time() + ( $scheduled * 2 ),
				self::HOOK_REPROCESS_LABELS,
				[
					'post_id'      => $post_id,
					'rid'          => $rid,
					'reprocess_id' => $reprocess_id,
				],
				self::GROUP_LABEL_REPROCESSOR
			);

			$scheduled++;
		}

		$status              = $this->label_reprocess_status();
		$status['scheduled'] = $scheduled;
		$status['message']   = sprintf(
			/* translators: %d is the number of resources scheduled for label reprocessing. */
			_n( 'Scheduled label update for %d synced resource.', 'Scheduled label updates for %d synced resources.', $scheduled, 'progress-agentic-rag-connector' ),
			$scheduled
		);
		$this->settings->add_sync_history_entry(
			[
				'id'          => $reprocess_id,
				'type'        => 'label_reprocess',
				'status'      => 'scheduled',
				'total'       => $scheduled,
				'completed'   => 0,
				'failed'      => 0,
				'message'     => $status['message'],
				'current'     => '',
				'started_at'  => time(),
				'finished_at' => time(),
			]
		);

		return $status;
	}

	public function cancel_label_reprocess(): array {
		$this->scheduler->unschedule_all_actions( self::HOOK_REPROCESS_LABELS, null, self::GROUP_LABEL_REPROCESSOR );

		$status            = $this->label_reprocess_status();
		$status['message'] = __( 'Label reprocessing cancelled.', 'progress-agentic-rag-connector' );
		$this->settings->add_sync_history_entry(
			[
				'type'        => 'label_reprocess',
				'status'      => 'cancelled',
				'total'       => (int) $status['pending'] + (int) $status['running'],
				'completed'   => 0,
				'failed'      => (int) $status['failed'],
				'message'     => $status['message'],
				'current'     => '',
				'started_at'  => time(),
				'finished_at' => time(),
			]
		);

		return $status;
	}

	/**
	 * @param list<string> $post_types Post type names removed from indexing.
	 */
	public function cancel_post_type_sync( array $post_types ): void {
		$post_types = $this->sanitize_post_types( $post_types );
		if ( empty( $post_types ) ) {
			return;
		}

		$manual_cancelled     = 0;
		$background_cancelled = 0;
		$sync_id              = (string) ( $this->state()['id'] ?? '' );
		$background_id        = (string) ( $this->background_state()['id'] ?? '' );

		foreach ( $post_types as $post_type ) {
			$manual_cancelled     += $this->cancel_pending_post_type_actions( self::HOOK_PROCESS_SINGLE, self::GROUP, $post_type, 'sync_id', $sync_id );
			$background_cancelled += $this->cancel_pending_post_type_actions( self::HOOK_BACKGROUND_SYNC, self::GROUP_BACKGROUND, $post_type, 'background_id', $background_id );
		}

		$this->reduce_sync_total( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $manual_cancelled );
		$this->reduce_sync_total( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $background_cancelled );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function label_reprocess_status(): array {
		$pending = $this->count_actions( $this->action_status( 'STATUS_PENDING', 'pending' ) );
		$running = $this->count_actions( $this->action_status( 'STATUS_RUNNING', 'in-progress' ) );
		$failed  = $this->count_actions( $this->action_status( 'STATUS_FAILED', 'failed' ) );

		return [
			'pending'   => $pending,
			'running'   => $running,
			'failed'    => $failed,
			'synced'    => $this->count_synced_resources(),
			'is_active' => $pending > 0 || $running > 0,
		];
	}

	public function process_single_post( int $post_id, string $post_type, string $sync_id ): void {
		$state = $this->state();
		if ( $sync_id !== ( $state['id'] ?? '' ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			$this->mark_failed( __( 'Missing entity.', 'progress-agentic-rag-connector' ) );
			return;
		}

		if ( ! $this->is_selected_post_type( $post_type ) ) {
			$this->settings->remove_failed_sync_item( $post_id, $post_type );
			$this->mark_completed();
			return;
		}

		$this->update_current( $this->entity_label( $post ) );

		if ( ! $this->is_indexable_post( $post, $post_type ) || $this->get_resource_id( $post_id ) ) {
			$this->settings->remove_failed_sync_item( $post_id, $post_type );
			$this->mark_completed();
			return;
		}

		$result = $this->api_client->index_post( $post );
		if ( is_wp_error( $result ) ) {
			$error = $this->error_message( $result );
			$this->settings->add_failed_sync_item( $post_id, $post_type, $this->entity_label( $post ), $error, 'manual' );
			$this->mark_failed( $this->entity_label( $post ) . ': ' . $error );
			return;
		}

		$this->settings->remove_failed_sync_item( $post_id, $post_type );
		$this->mark_completed();
	}

	public function process_single_label_reprocess( int $post_id, string $rid, string $reprocess_id ): void {
		if ( $post_id <= 0 || '' === $rid || '' === $reprocess_id ) {
			return;
		}

		if ( ! $this->settings->get_api_is_reachable() ) {
			throw new \RuntimeException( 'Progress Agentic RAG connection is not validated.' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$result = $this->api_client->update_resource_labels( $post, $rid );
		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is sanitized by error_message().
			throw new \RuntimeException( $this->error_message( $result ) );
		}
	}

	public function process_single_delete( int $post_id, string $rid, string $delete_id ): void {
		$state = $this->delete_state();
		if ( $delete_id !== ( $state['id'] ?? '' ) ) {
			return;
		}

		$post  = get_post( $post_id );
		$label = $post instanceof WP_Post ? $this->entity_label( $post ) : sprintf(
			/* translators: %d is the WordPress post ID for a synced resource being deleted. */
			__( 'Synced resource for entity #%d', 'progress-agentic-rag-connector' ),
			$post_id
		);
		$this->update_delete_current( $label );

		if ( $post_id <= 0 || '' === $rid ) {
			$this->mark_delete_failed( __( 'Missing synced resource.', 'progress-agentic-rag-connector' ) );
			return;
		}

		$result = $this->api_client->delete_resource( $post_id, $rid );
		if ( is_wp_error( $result ) ) {
			$this->mark_delete_failed( $label . ': ' . $this->error_message( $result ) );
			return;
		}

		$this->mark_delete_completed();
	}

	public function schedule_background_post_sync( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		if ( ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) || ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) ) {
			return;
		}

		if ( ! $this->settings->get_api_is_reachable() || ! $this->scheduler_available() || ! $this->is_selected_post_type( $post->post_type ) ) {
			return;
		}

		if ( ! $this->is_indexable_post( $post, $post->post_type ) ) {
			$rid = $this->get_resource_id( $post_id );
			if ( '' !== $rid ) {
				$background_id = $this->queue_background_sync( $this->entity_label( $post ) );
				$this->schedule_single_action(
					time() + 5,
					self::HOOK_BACKGROUND_DELETE,
					[
						'post_id'       => $post_id,
						'rid'           => $rid,
						'background_id' => $background_id,
					],
					self::GROUP_BACKGROUND
				);
			}

			return;
		}

		if ( $this->is_background_sync_scheduled( $post_id, $post->post_type ) ) {
			return;
		}

		$background_id = $this->queue_background_sync( $this->entity_label( $post ) );
		$this->schedule_single_action(
			time() + 5,
			self::HOOK_BACKGROUND_SYNC,
			[
				'post_id'       => $post_id,
				'post_type'     => $post->post_type,
				'background_id' => $background_id,
			],
			self::GROUP_BACKGROUND
		);
	}

	public function schedule_background_attachment_sync( int $post_id ): void {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$this->schedule_background_post_sync( $post_id, $post, true );
		}
	}

	public function schedule_background_delete( int $post_id, WP_Post $post ): void {
		if ( ! $this->settings->get_api_is_reachable() || ! $this->scheduler_available() ) {
			return;
		}

		$rid = $this->get_resource_id( $post_id );
		if ( '' === $rid ) {
			return;
		}

		$background_id = $this->queue_background_sync( $this->entity_label( $post ) );
		$this->schedule_single_action(
			time() + 5,
			self::HOOK_BACKGROUND_DELETE,
			[
				'post_id'       => $post_id,
				'rid'           => $rid,
				'background_id' => $background_id,
			],
			self::GROUP_BACKGROUND
		);
	}

	public function process_background_post( int $post_id, string $post_type, string $background_id = '' ): void {
		if ( ! $this->settings->get_api_is_reachable() ) {
			$this->mark_background_failed( __( 'Progress Agentic RAG connection is not validated.', 'progress-agentic-rag-connector' ), $background_id );
			throw new \RuntimeException( 'Progress Agentic RAG connection is not validated.' );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! $this->is_selected_post_type( $post_type ) ) {
			$this->mark_background_completed( $background_id );
			return;
		}

		$this->update_background_current( $this->entity_label( $post ), $background_id );

		if ( ! $this->is_indexable_post( $post, $post_type ) ) {
			$rid = $this->get_resource_id( $post_id );
			if ( '' !== $rid ) {
					$result = $this->api_client->delete_resource( $post_id, $rid );
					if ( is_wp_error( $result ) ) {
						$this->mark_background_failed( $this->entity_label( $post ) . ': ' . $this->error_message( $result ), $background_id );
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is sanitized by error_message().
						throw new \RuntimeException( $this->error_message( $result ) );
					}
				}

			$this->mark_background_completed( $background_id );
			return;
		}

		$result = $this->api_client->sync_post( $post );
		if ( is_wp_error( $result ) ) {
			$error = $this->error_message( $result );
			$this->settings->add_failed_sync_item( $post_id, $post_type, $this->entity_label( $post ), $error, 'automatic' );
			$this->mark_background_failed( $this->entity_label( $post ) . ': ' . $error, $background_id );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is sanitized by error_message().
			throw new \RuntimeException( $error );
		}

		$this->settings->remove_failed_sync_item( $post_id, $post_type );
		$this->mark_background_completed( $background_id );
	}

	public function process_background_delete( int $post_id, string $rid, string $background_id = '' ): void {
		if ( $post_id <= 0 || '' === $rid ) {
			$this->mark_background_completed( $background_id );
			return;
		}

		if ( ! $this->settings->get_api_is_reachable() ) {
			$this->mark_background_failed( __( 'Progress Agentic RAG connection is not validated.', 'progress-agentic-rag-connector' ), $background_id );
			throw new \RuntimeException( 'Progress Agentic RAG connection is not validated.' );
		}

		$result = $this->api_client->delete_resource( $post_id, $rid );
		if ( is_wp_error( $result ) ) {
			$this->mark_background_failed( $this->error_message( $result ), $background_id );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is sanitized by error_message().
			throw new \RuntimeException( $this->error_message( $result ) );
		}

		$this->mark_background_completed( $background_id );
	}

	/**
	 * @param list<string> $post_types
	 *
	 * @return list<array{post_id:int,post_type:string}>
	 */
	private function unindexed_entities( array $post_types ): array {
		global $wpdb;

		$entities        = [];
		$sync_table_name = $this->sync_table_name();

		foreach ( $post_types as $post_type ) {
			$post_status = 'attachment' === $post_type ? 'inherit' : 'publish';
			$limit       = 500;
			$offset      = 0;

			do {
					// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reads plugin-owned sync table; values are prepared and table names are escaped.
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT p.ID FROM {$wpdb->posts} AS p
						 LEFT JOIN {$sync_table_name} AS idx ON ( p.ID = idx.post_id )
						 WHERE idx.post_id IS NULL
						   AND p.post_type = %s
						   AND p.post_status = %s
						   AND p.post_password = %s
						 LIMIT %d OFFSET %d",
						$post_type,
						$post_status,
						'',
						$limit,
						$offset
						)
					);
					// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

				foreach ( $results as $result ) {
					$entities[] = [
						'post_id'   => (int) $result->ID,
						'post_type' => $post_type,
					];
				}

				$offset += $limit;
			} while ( count( $results ) === $limit );
		}

		return $entities;
	}

	/**
	 * @param list<array{post_id:int,post_type:string}> $entities
	 */
	private function schedule_background_entities( array $entities ): int {
		$scheduled = 0;

		foreach ( $entities as $entity ) {
			$post_id   = (int) $entity['post_id'];
			$post_type = (string) $entity['post_type'];

			if ( $this->is_background_sync_scheduled( $post_id, $post_type ) ) {
				continue;
			}

			$post  = get_post( $post_id );
			$label = $post instanceof WP_Post ? $this->entity_label( $post ) : sprintf(
				/* translators: %d is the WordPress post ID for an entity being automatically synced. */
				__( 'Entity #%d', 'progress-agentic-rag-connector' ),
				$post_id
			);
			$background_id = $this->queue_background_sync( $label );

			$this->schedule_single_action(
				time() + ( $scheduled * 2 ),
				self::HOOK_BACKGROUND_SYNC,
				[
					'post_id'       => $post_id,
					'post_type'     => $post_type,
					'background_id' => $background_id,
				],
				self::GROUP_BACKGROUND
			);

			$scheduled++;
		}

		return $scheduled;
	}

	private function is_background_sync_scheduled( int $post_id, string $post_type ): bool {
		foreach ( [ $this->action_status( 'STATUS_PENDING', 'pending' ), $this->action_status( 'STATUS_RUNNING', 'in-progress' ) ] as $status ) {
			foreach ( $this->scheduler->scheduled_actions( self::HOOK_BACKGROUND_SYNC, self::GROUP_BACKGROUND, $status ) as $action ) {
				$args = $this->scheduled_action_args( $action );
				if ( $post_id === (int) ( $args['post_id'] ?? 0 ) && $post_type === (string) ( $args['post_type'] ?? '' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function cancel_pending_post_type_actions( string $hook, string $group, string $post_type, string $id_key, string $id ): int {
		if ( '' === $id ) {
			return 0;
		}

		$cancelled = 0;
		foreach ( $this->scheduler->scheduled_actions( $hook, $group, $this->action_status( 'STATUS_PENDING', 'pending' ) ) as $action ) {
			$args = $this->scheduled_action_args( $action );
			if ( $post_type !== (string) ( $args['post_type'] ?? '' ) || $id !== (string) ( $args[ $id_key ] ?? '' ) ) {
				continue;
			}

			$this->scheduler->unschedule_all_actions( $hook, $args, $group );
			$cancelled++;
		}

		return $cancelled;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function scheduled_action_args( mixed $action ): array {
		if ( is_object( $action ) && method_exists( $action, 'get_args' ) ) {
			$args = $action->get_args();
			return is_array( $args ) ? $args : [];
		}

		if ( is_object( $action ) && isset( $action->args ) && is_array( $action->args ) ) {
			return $action->args;
		}

		if ( is_array( $action ) && isset( $action['args'] ) && is_array( $action['args'] ) ) {
			return $action['args'];
		}

		return [];
	}

	private function count_indexable_entities( string $post_type ): int {
		global $wpdb;

		$post_status = 'attachment' === $post_type ? 'inherit' : 'publish';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Counts eligible public posts for the admin sync summary.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s
				   AND post_status = %s
				   AND post_password = %s",
				$post_type,
				$post_status,
				''
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $count;
	}

	private function count_synced_entities( string $post_type ): int {
		global $wpdb;

		$sync_table_name = $this->sync_table_name();

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Counts plugin-owned sync rows; values are prepared and table names are escaped.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$sync_table_name} AS idx
				 INNER JOIN {$wpdb->posts} AS p ON ( p.ID = idx.post_id )
				 WHERE p.post_type = %s
				   AND idx.nuclia_rid IS NOT NULL
				   AND idx.nuclia_rid != %s",
				$post_type,
				''
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $count;
	}

	private function count_synced_resources(): int {
		global $wpdb;

		$sync_table_name = $this->sync_table_name();

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Counts plugin-owned sync rows; values are prepared and the table name is escaped.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$sync_table_name} WHERE nuclia_rid IS NOT NULL AND nuclia_rid != %s",
				''
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $count;
	}

	/**
	 * @param list<string> $post_types
	 *
	 * @return list<string>
	 */
	private function sanitize_post_types( array $post_types ): array {
		$allowed = array_keys( $this->indexable_post_type_objects() );
		$clean   = [];

		foreach ( $post_types as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( in_array( $post_type, $allowed, true ) ) {
				$clean[] = $post_type;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	private function is_indexable_post( WP_Post $post, string $post_type ): bool {
		if ( $post->post_type !== $post_type || '' !== $post->post_password ) {
			return false;
		}

		if ( 'attachment' === $post_type ) {
			return 'inherit' === $post->post_status;
		}

		return 'publish' === $post->post_status;
	}

	private function is_selected_post_type( string $post_type ): bool {
		$selected = $this->settings->get_indexable_post_types();

		return isset( $selected[ $post_type ] ) && 1 === (int) $selected[ $post_type ];
	}

	private function get_resource_id( int $post_id ): string {
		global $wpdb;

		$sync_table_name = $this->sync_table_name();

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reads plugin-owned sync table; values are prepared and the table name is escaped.
			$resource_id = (string) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT nuclia_rid FROM {$sync_table_name} WHERE post_id = %d",
				$post_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $resource_id;
	}

	private function sync_table_name(): string {
		global $wpdb;

		return esc_sql( $wpdb->prefix . SettingsRepository::SYNC_TABLE_NAME );
	}

	private function error_message( WP_Error $error ): string {
		return sanitize_text_field( $error->get_error_message() );
	}

	private function entity_label( WP_Post $post ): string {
		$title = wp_strip_all_tags( get_the_title( $post ) );

		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %d is the WordPress post ID for an entity with no title. */
				__( 'Entity #%d', 'progress-agentic-rag-connector' ),
				(int) $post->ID
			);
		}

		return sprintf( '%s: %s', $this->entity_type_label( $post->post_type ), $title );
	}

	private function update_current( string $current ): void {
		$state               = $this->state();
		$state['current']    = $current;
		$state['updated_at'] = time();

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
	}

	private function mark_completed(): void {
		$state               = $this->state();
		$state['completed']  = (int) ( $state['completed'] ?? 0 ) + 1;
		$state['updated_at'] = time();

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
	}

	private function mark_failed( string $current ): void {
		$state               = $this->state();
		$state['failed']     = (int) ( $state['failed'] ?? 0 ) + 1;
		$state['current']    = $current;
		$state['updated_at'] = time();

		update_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, $state );
	}

	private function update_delete_current( string $current ): void {
		$state               = $this->delete_state();
		$state['current']    = $current;
		$state['updated_at'] = time();

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, $state );
	}

	private function mark_delete_completed(): void {
		$state               = $this->delete_state();
		$state['deleted']    = (int) ( $state['deleted'] ?? 0 ) + 1;
		$state['updated_at'] = time();

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, $state );
	}

	private function mark_delete_failed( string $current ): void {
		$state               = $this->delete_state();
		$state['failed']     = (int) ( $state['failed'] ?? 0 ) + 1;
		$state['current']    = $current;
		$state['updated_at'] = time();

		update_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, $state );
	}

	private function queue_background_sync( string $current ): string {
		$state = $this->background_state();

		if ( 'running' !== ( $state['status'] ?? '' ) ) {
			$state = [
				'id'         => wp_generate_uuid4(),
				'status'     => 'running',
				'total'      => 0,
				'completed'  => 0,
				'failed'     => 0,
				'current'    => $current,
				'message'    => __( 'Automatic background sync queued.', 'progress-agentic-rag-connector' ),
				'started_at' => time(),
				'updated_at' => time(),
			];
		}

		$state['total']      = (int) ( $state['total'] ?? 0 ) + 1;
		$state['current']    = $current;
		$state['updated_at'] = time();
		update_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $state );

		return (string) $state['id'];
	}

	private function update_background_current( string $current, string $background_id ): void {
		if ( '' === $background_id ) {
			return;
		}

		$state = $this->background_state();
		if ( $background_id !== (string) ( $state['id'] ?? '' ) ) {
			return;
		}

		$state['current']    = $current;
		$state['updated_at'] = time();
		update_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $state );
	}

	private function mark_background_completed( string $background_id ): void {
		if ( '' === $background_id ) {
			return;
		}

		$state = $this->background_state();
		if ( $background_id !== (string) ( $state['id'] ?? '' ) ) {
			return;
		}

		if ( (int) ( $state['completed'] ?? 0 ) + (int) ( $state['failed'] ?? 0 ) < (int) ( $state['total'] ?? 0 ) ) {
			$state['completed'] = (int) ( $state['completed'] ?? 0 ) + 1;
		}

		$state['updated_at'] = time();
		update_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $state );
	}

	private function mark_background_failed( string $current, string $background_id ): void {
		if ( '' === $background_id ) {
			return;
		}

		$state = $this->background_state();
		if ( $background_id !== (string) ( $state['id'] ?? '' ) ) {
			return;
		}

		if ( (int) ( $state['completed'] ?? 0 ) + (int) ( $state['failed'] ?? 0 ) < (int) ( $state['total'] ?? 0 ) ) {
			$state['failed'] = (int) ( $state['failed'] ?? 0 ) + 1;
		}

		$state['current']    = $current;
		$state['updated_at'] = time();
		update_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, $state );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function state(): array {
		$state = get_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE, [] );

		return is_array( $state ) ? $state : [];
	}

	private function delete_state(): array {
		$state = get_option( SettingsRepository::OPTION_DELETE_SYNC_STATE, [] );

		return is_array( $state ) ? $state : [];
	}

	private function background_state(): array {
		$state = get_option( SettingsRepository::OPTION_BACKGROUND_SYNC_STATE, [] );

		return is_array( $state ) ? $state : [];
	}

	private function reduce_sync_total( string $option_name, int $count ): void {
		if ( $count <= 0 ) {
			return;
		}

		$state = get_option( $option_name, [] );
		if ( ! is_array( $state ) || 'running' !== ( $state['status'] ?? '' ) ) {
			return;
		}

		$processed           = (int) ( $state['completed'] ?? 0 ) + (int) ( $state['failed'] ?? 0 );
		$state['total']      = max( $processed, (int) ( $state['total'] ?? 0 ) - $count );
		$state['updated_at'] = time();

		update_option( $option_name, $state );
	}

	/**
	 * @param array<string, mixed> $state Sync state.
	 *
	 * @return array<string, mixed>
	 */
	private function record_history( array $state, string $type, int $completed ): array {
		if ( ! empty( $state['history_recorded'] ) ) {
			return $state;
		}

		$this->settings->add_sync_history_entry(
			[
				'id'          => (string) ( $state['id'] ?? '' ),
				'type'        => $type,
				'status'      => (int) ( $state['failed'] ?? 0 ) > 0 ? 'failed' : 'complete',
				'total'       => (int) ( $state['total'] ?? 0 ),
				'completed'   => $completed,
				'failed'      => (int) ( $state['failed'] ?? 0 ),
				'message'     => (string) ( $state['message'] ?? '' ),
				'current'     => (string) ( $state['current'] ?? '' ),
				'started_at'  => (int) ( $state['started_at'] ?? 0 ),
				'finished_at' => time(),
			]
		);

		$state['history_recorded'] = true;

		return $state;
	}

	public function scheduler_available(): bool {
		return $this->scheduler->available();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function scheduler_status(): array {
		return [
			'available' => $this->scheduler->available(),
			'backend'   => $this->scheduler->backend(),
			'label'     => $this->scheduler->backend_label(),
			'message'   => $this->scheduler->backend_message(),
		];
	}

	private function schedule_single_action( int $timestamp, string $hook, array $args, string $group ): void {
		$this->scheduler->schedule_single_action( $timestamp, $hook, $args, $group );
	}

	private function count_actions( string $status ): int {
		return $this->scheduler->count_actions( self::HOOK_REPROCESS_LABELS, self::GROUP_LABEL_REPROCESSOR, $status );
	}

	private function count_background_actions( string $status ): int {
		return $this->scheduler->count_actions( self::HOOK_BACKGROUND_SYNC, self::GROUP_BACKGROUND, $status )
			+ $this->scheduler->count_actions( self::HOOK_BACKGROUND_DELETE, self::GROUP_BACKGROUND, $status );
	}

	private function count_background_actions_for_id( string $status, string $background_id ): int {
		$count = 0;

		foreach ( [ self::HOOK_BACKGROUND_SYNC, self::HOOK_BACKGROUND_DELETE ] as $hook ) {
			foreach ( $this->scheduler->scheduled_actions( $hook, self::GROUP_BACKGROUND, $status ) as $action ) {
				$args = $this->scheduled_action_args( $action );
				if ( $background_id === (string) ( $args['background_id'] ?? '' ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	private function count_delete_actions_for_id( string $status, string $delete_id ): int {
		$count = 0;

		foreach ( $this->scheduler->scheduled_actions( self::HOOK_DELETE_RESOURCE, self::GROUP_DELETE, $status ) as $action ) {
			$args = $this->scheduled_action_args( $action );
			if ( $delete_id === (string) ( $args['delete_id'] ?? '' ) ) {
				$count++;
			}
		}

		return $count;
	}

	private function action_status( string $constant, string $fallback ): string {
		if ( class_exists( '\ActionScheduler_Store' ) && defined( 'ActionScheduler_Store::' . $constant ) ) {
			return (string) constant( 'ActionScheduler_Store::' . $constant );
		}

		return $fallback;
	}

	private function post_type_label( string $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );

		return null !== $post_type_object && isset( $post_type_object->labels->name ) ? (string) $post_type_object->labels->name : $post_type;
	}

	private function entity_type_label( string $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );
		if ( null !== $post_type_object && isset( $post_type_object->labels->singular_name ) && '' !== (string) $post_type_object->labels->singular_name ) {
			return (string) $post_type_object->labels->singular_name;
		}

		return $this->post_type_label( $post_type );
	}

	/**
	 * @return array<string, \WP_Post_Type>
	 */
	private function indexable_post_type_objects(): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		if ( ! isset( $post_types['attachment'] ) ) {
			$attachment = get_post_type_object( 'attachment' );
			if ( null !== $attachment ) {
				$post_types['attachment'] = $attachment;
			}
		}

		ksort( $post_types );

		return $post_types;
	}
}
