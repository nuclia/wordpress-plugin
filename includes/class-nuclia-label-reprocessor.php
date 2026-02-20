<?php
/**
 * Nuclia_Label_Reprocessor class file.
 *
 * Handles background reprocessing of labels for already synced resources.
 *
 * @since   1.4.0
 *
 */

/**
 * Class Nuclia_Label_Reprocessor
 *
 * @since 1.4.0
 */
class Nuclia_Label_Reprocessor {

	/**
	 * Action hook for processing a single label update.
	 *
	 * @since 1.4.0
	 */
	const HOOK_PROCESS_SINGLE = 'nuclia_reprocess_single_labels';

	/**
	 * Action Scheduler group name for all label reprocessing jobs.
	 *
	 * @since 1.4.0
	 */
	const GROUP = 'nuclia-label-reprocessing';

	/**
	 * Maximum batch size for scheduling reprocessing jobs.
	 *
	 * @since 1.4.0
	 */
	const MAX_BATCH_SIZE = 500;

	/**
	 * The Nuclia_Plugin instance.
	 *
	 * @since 1.4.0
	 *
	 * @var Nuclia_Plugin
	 */
	private Nuclia_Plugin $plugin;

	/**
	 * Nuclia_Label_Reprocessor constructor.
	 *
	 * @since 1.4.0
	 *
	 * @param Nuclia_Plugin $plugin The Nuclia_Plugin instance.
	 */
	public function __construct( Nuclia_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks for Action Scheduler.
	 *
	 * @since 1.4.0
	 */
	public function register_hooks(): void {
		add_action( self::HOOK_PROCESS_SINGLE, [ $this, 'process_single_labels' ], 10, 2 );
	}

	/**
	 * Schedule label reprocessing for all indexed posts.
	 *
	 * @since 1.4.0
	 *
	 * @return int Number of posts scheduled for reprocessing.
	 */
	public function schedule_full_reprocess(): int {
		// Check if Action Scheduler is available
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			nuclia_error_log( 'Action Scheduler not available. Cannot schedule label reprocessing.' );
			return 0;
		}

		// Check if already running
		$current_pending = $this->get_pending_count();
		$current_running = $this->get_running_count();
		if ( $current_pending > 0 || $current_running > 0 ) {
			nuclia_log( "Reprocessing already in progress ({$current_pending} pending, {$current_running} running)." );
			return 0;
		}

		nuclia_log( 'Scheduling full label reprocessing' );

		$indexed_posts = $this->plugin->get_api()->get_all_indexed_posts();

		if ( empty( $indexed_posts ) ) {
			nuclia_log( 'No indexed posts found for label reprocessing.' );
			return 0;
		}

		$total = count( $indexed_posts );

		// Warn if large batch
		if ( $total > self::MAX_BATCH_SIZE ) {
			nuclia_log( "Large batch detected ({$total} posts). Processing may take a while." );
		}

		$scheduled_count = 0;

		foreach ( $indexed_posts as $row ) {
			$post_id = (int) $row->post_id;
			$rid = $row->nuclia_rid;

			if ( empty( $rid ) ) {
				continue;
			}

			// Check if this post is already scheduled
			if ( $this->is_post_scheduled( $post_id ) ) {
				continue;
			}

			// Schedule the post for label reprocessing
			as_schedule_single_action(
				time() + ( $scheduled_count * 2 ), // Stagger by 2 seconds to avoid rate limiting
				self::HOOK_PROCESS_SINGLE,
				[
					'post_id' => $post_id,
					'rid'     => $rid,
				],
				self::GROUP
			);

			$scheduled_count++;
		}

		nuclia_log( "Scheduled {$scheduled_count} posts for label reprocessing." );
		return $scheduled_count;
	}

	/**
	 * Process a single post's labels.
	 *
	 * This is the callback for Action Scheduler.
	 *
	 * @since 1.4.0
	 *
	 * @param int    $post_id The post ID.
	 * @param string $rid     The Nuclia resource UUID.
	 *
	 * @throws Exception If update fails and should be retried.
	 */
	public function process_single_labels( int $post_id, string $rid ): void {
		// Validate input parameters
		if ( $post_id <= 0 ) {
			nuclia_error_log( "Invalid post_id: {$post_id}. Skipping label reprocessing." );
			return; // Invalid data, don't retry
		}

		if ( empty( $rid ) ) {
			nuclia_error_log( "Empty RID for post {$post_id}. Skipping label reprocessing." );
			return; // Invalid data, don't retry
		}

		// Validate RID format (UUID-like: 36 chars with hyphens)
		if ( ! preg_match( '/^[a-f0-9\-]{36}$/i', $rid ) ) {
			nuclia_error_log( "Invalid RID format for post {$post_id}: {$rid}. Skipping label reprocessing." );
			return; // Invalid data, don't retry
		}

		nuclia_log( "Processing labels for post {$post_id} (rid: {$rid}) via Action Scheduler." );

		// Check if API is still reachable
		if ( ! $this->plugin->get_settings()->get_api_is_reachable() ) {
			nuclia_log( 'API not reachable, skipping label reprocessing.' );
			throw new Exception( 'Nuclia API not reachable. Will retry later.' );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			nuclia_log( "Post {$post_id} not found, skipping label reprocessing." );
			return; // Post deleted, skip without error
		}

		try {
			$result = $this->plugin->get_api()->update_resource_labels( $post_id, $rid, $post );

			if ( ! $result['success'] ) {
				throw new Exception( $result['message'] ?? "Failed to update labels for post {$post_id}" );
			}

			nuclia_log( "Successfully updated labels for post {$post_id}." );
		} catch ( Exception $e ) {
			nuclia_error_log( "Failed to update labels for post {$post_id}: " . $e->getMessage() );
			// Re-throw to trigger Action Scheduler retry
			throw $e;
		}
	}

	/**
	 * Check if a post is already scheduled for label reprocessing.
	 *
	 * @since 1.4.0
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if already scheduled.
	 */
	private function is_post_scheduled( int $post_id ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false; // Safe default - allow scheduling if Action Scheduler not available
		}

		return as_has_scheduled_action(
			self::HOOK_PROCESS_SINGLE,
			[
				'post_id' => $post_id,
			],
			self::GROUP
		);
	}

	/**
	 * Get the count of pending label reprocessing actions.
	 *
	 * @since 1.4.0
	 *
	 * @return int The number of pending actions.
	 */
	public function get_pending_count(): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$count = $this->as_count_scheduled_actions(
			[
				'hook'   => self::HOOK_PROCESS_SINGLE,
				'group'  => self::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			]
		);

		return $count;
	}

	/**
	 * Get the count of running/in-progress label reprocessing actions.
	 *
	 * @since 1.4.0
	 *
	 * @return int The number of running actions.
	 */
	public function get_running_count(): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$actions = as_get_scheduled_actions(
			[
				'hook'   => self::HOOK_PROCESS_SINGLE,
				'group'  => self::GROUP,
				'status' => ActionScheduler_Store::STATUS_RUNNING,
			],
			'ids'
		);

		return count( $actions );
	}

	/**
	 * Get the count of failed label reprocessing actions.
	 *
	 * @since 1.4.0
	 *
	 * @return int The number of failed actions.
	 */
	public function get_failed_count(): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$actions = as_get_scheduled_actions(
			[
				'hook'   => self::HOOK_PROCESS_SINGLE,
				'group'  => self::GROUP,
				'status' => ActionScheduler_Store::STATUS_FAILED,
			],
			'ids'
		);

		return count( $actions );
	}

	/**
	 * Cancel all pending label reprocessing actions.
	 *
	 * @since 1.4.0
	 */
	public function cancel_all(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::HOOK_PROCESS_SINGLE, null, self::GROUP );

		nuclia_log( 'Cancelled all pending Nuclia label reprocessing actions.' );
	}

	/**
	 * Get status information for the admin UI.
	 *
	 * @since 1.4.0
	 *
	 * @return array{pending: int, running: int, failed: int, is_active: bool} Status information array.
	 */
	public function get_status(): array {
		return [
			'pending'   => $this->get_pending_count(),
			'running'   => $this->get_running_count(),
			'failed'    => $this->get_failed_count(),
			'is_active' => $this->get_pending_count() > 0 || $this->get_running_count() > 0,
		];
	}

	/**
	 * Count scheduled actions using Action Scheduler.
	 *
	 * @since 1.4.0
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	protected function as_count_scheduled_actions( $args = [] ): int {
		if ( ! ActionScheduler::is_initialized( __FUNCTION__ ) ) {
			return 0;
		}

		$store = ActionScheduler::store();

		foreach ( [ 'date', 'modified' ] as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$args[ $key ] = as_get_datetime_object( $args[ $key ] );
			}
		}

		return $store->query_actions( $args, 'count' );
	}
}
