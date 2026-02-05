<?php
/**
 * Nuclia_Background_Processor class file.
 *
 * Handles background processing of posts for indexing using Action Scheduler.
 *
 * @since   1.1.0
 *
 */

/**
 * Class Nuclia_Background_Processor
 *
 * @since 1.1.0
 */
class Nuclia_Background_Processor {

	/**
	 * Action hook for processing a single post.
	 *
	 * @since 1.1.0
	 */
	const HOOK_PROCESS_SINGLE = 'nuclia_process_single_post';

	/**
	 * Action hook for scheduling a batch of posts.
	 *
	 * @since 1.1.0
	 */
	const HOOK_SCHEDULE_BATCH = 'nuclia_schedule_batch';

	/**
	 * Action Scheduler group name for all Nuclia indexing jobs.
	 *
	 * @since 1.1.0
	 */
	const GROUP = 'nuclia-indexing';

	/**
	 * The Nuclia_Plugin instance.
	 *
	 * @since 1.1.0
	 *
	 * @var Nuclia_Plugin
	 */
	private Nuclia_Plugin $plugin;

	/**
	 * Nuclia_Background_Processor constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param Nuclia_Plugin $plugin The Nuclia_Plugin instance.
	 */
	public function __construct( Nuclia_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register hooks for Action Scheduler.
	 *
	 * @since 1.1.0
	 */
	public function register_hooks(): void {
		// Hook for processing individual posts
		add_action( self::HOOK_PROCESS_SINGLE, [ $this, 'process_single_post' ], 10, 2 );
		
		// Hook for scheduling batches
		add_action( self::HOOK_SCHEDULE_BATCH, [ $this, 'schedule_post_type' ], 10, 1 );
		
		// Hook for full reindex trigger
		add_action( 'nuclia_schedule_full_reindex', [ $this, 'schedule_full_reindex' ] );
	}

	/**
	 * Schedule indexing for all enabled post types.
	 *
	 * @since 1.1.0
	 */
	public function schedule_full_reindex(): void {
		nuclia_log( 'Scheduling full reindex for all post types' );
		$indexable_post_types = $this->plugin->get_indexable_post_types();
		
		foreach ( array_keys( $indexable_post_types ) as $post_type ) {
			// Schedule batch scheduling for each post type with a slight delay between them
			$this->schedule_post_type( $post_type );
		}
		
		nuclia_log( 'Scheduled full reindex for all post types: ' . implode( ', ', array_keys( $indexable_post_types ) ) );
	}

	/**
	 * Schedule indexing for a specific post type.
	 *
	 * @since 1.1.0
	 *
	 * @param string $post_type The post type to schedule indexing for.
	 */
	public function schedule_post_type( string $post_type ): void {
		if ( ! post_type_exists( $post_type ) ) {
			nuclia_log( "Post type {$post_type} does not exist, skipping." );
			return;
		}

		// Get unindexed posts for this post type
		$unindexed_posts = $this->get_unindexed_posts( $post_type );
		
		if ( empty( $unindexed_posts ) ) {
			nuclia_log( "No unindexed posts found for {$post_type}." );
			return;
		}

		$scheduled_count = 0;
		
		foreach ( $unindexed_posts as $post_id ) {
			// Check if this post is already scheduled
			if ( $this->is_post_scheduled( $post_id, $post_type ) ) {
				continue;
			}

			// Schedule the post for processing
			as_schedule_single_action(
				time() + ( $scheduled_count * 2 ), // Stagger by 2 seconds to avoid rate limiting
				self::HOOK_PROCESS_SINGLE,
				[
					'post_id'   => $post_id,
					'post_type' => $post_type,
				],
				self::GROUP
			);
			
			$scheduled_count++;
		}

		nuclia_log( "Scheduled {$scheduled_count} posts for indexing (post type: {$post_type})." );
	}

	/**
	 * Process a single post for indexing.
	 *
	 * This is the callback for Action Scheduler.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id   The post ID to index.
	 * @param string $post_type The post type.
	 *
	 * @throws Exception If indexing fails and should be retried.
	 */
	public function process_single_post( int $post_id, string $post_type ): void {
		nuclia_log( "Processing post {$post_id} (type: {$post_type}) via Action Scheduler." );

		// Check if API is still reachable
		if ( ! $this->plugin->get_settings()->get_api_is_reachable() ) {
			nuclia_log( 'API not reachable, skipping post processing.' );
			throw new Exception( 'Nuclia API not reachable. Will retry later.' );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			nuclia_log( "Post {$post_id} not found, skipping." );
			return;
		}

		// Check if post type is still enabled
		$indexable_post_types = $this->plugin->get_indexable_post_types();
		if ( ! array_key_exists( $post_type, $indexable_post_types ) ) {
			nuclia_log( "Post type {$post_type} is no longer enabled for indexing, skipping." );
			return;
		}

		// Check if already indexed
		$rid = $this->plugin->get_api()->get_rid( $post_id );
		if ( $rid ) {
			nuclia_log( "Post {$post_id} is already indexed (rid: {$rid}), skipping." );
			return;
		}

		try {
			if ( $post_type === 'attachment' ) {
				// Hack: attachments have 'inherit' status
				$post->post_status = 'publish';
				$this->plugin->get_api()->create_resource( $post_id, $post );
			} else {
				// Check post status
				if ( $post->post_status !== 'publish' || $post->post_password ) {
					nuclia_log( "Post {$post_id} is not public or is password protected, skipping." );
					return;
				}
				
				$this->plugin->get_api()->create_resource( $post_id, $post );
			}

			nuclia_log( "Successfully indexed post {$post_id}." );
		} catch ( Exception $e ) {
			nuclia_error_log( "Failed to index post {$post_id}: " . $e->getMessage() );
			// Re-throw to trigger Action Scheduler retry
			throw $e;
		}
	}

	/**
	 * Get unindexed posts for a specific post type.
	 *
	 * @since 1.1.0
	 *
	 * @param string $post_type The post type to query.
	 *
	 * @return array Array of post IDs that are not yet indexed.
	 */
	private function get_unindexed_posts( string $post_type ): array {
		global $wpdb;

		$post_status = ( $post_type !== 'attachment' ) ? 'publish' : 'inherit';
		$limit       = 500; // Process in batches to avoid memory issues
		$offset      = 0;
		$all_posts   = [];

		do {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} AS p
					 LEFT JOIN {$wpdb->prefix}agentic_rag_for_wp AS idx ON ( p.ID = idx.post_id )
					 WHERE idx.post_id IS NULL 
					   AND p.post_type = %s 
					   AND p.post_status = %s
					   AND p.post_password = ''
					 LIMIT %d OFFSET %d",
					$post_type,
					$post_status,
					$limit,
					$offset
				)
			);

			if ( ! empty( $wpdb->last_error ) ) {
				nuclia_error_log( 'Database error in get_unindexed_posts: ' . $wpdb->last_error );
				break;
			}

			$all_posts = array_merge( $all_posts, wp_list_pluck( $results, 'ID' ) );
			$offset   += $limit;
		} while ( count( $results ) === $limit );

		return $all_posts;
	}

	/**
	 * Check if a post is already scheduled for processing.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $post_id   The post ID.
	 * @param string $post_type The post type.
	 *
	 * @return bool True if already scheduled.
	 */
	private function is_post_scheduled( int $post_id, string $post_type ): bool {
		return as_has_scheduled_action(
			self::HOOK_PROCESS_SINGLE,
			[
				'post_id'   => $post_id,
				'post_type' => $post_type,
			],
			self::GROUP
		);
	}

	/**
	 * Get the count of pending actions.
	 *
	 * @since 1.1.0
	 *
	 * @return int The number of pending actions.
	 */
	public function get_pending_count(): int {
		$count = $this->as_count_scheduled_actions(
			[
				'hook'   => self::HOOK_PROCESS_SINGLE,
				'group'  => self::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING
			]
		);

		return $count;
	}

	/**
	 * Get the count of running/in-progress actions.
	 *
	 * @since 1.1.0
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
	 * Get the count of failed actions.
	 *
	 * @since 1.1.0
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
	 * Cancel all pending indexing actions.
	 *
	 * @since 1.1.0
	 */
	public function cancel_all(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::HOOK_PROCESS_SINGLE, null, self::GROUP );
		
		nuclia_log( 'Cancelled all pending Nuclia indexing actions.' );
	}

	/**
	 * Cancel pending actions for a specific post type.
	 *
	 * @since 1.1.0
	 *
	 * @param string $post_type The post type to cancel actions for.
	 */
	public function cancel_post_type( string $post_type ): void {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}

		$actions = as_get_scheduled_actions(
			[
				'hook'   => self::HOOK_PROCESS_SINGLE,
				'group'  => self::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);

		foreach ( $actions as $action_id ) {
			$action = ActionScheduler::store()->fetch_action( $action_id );
			$args   = $action->get_args();
			
			if ( isset( $args['post_type'] ) && $args['post_type'] === $post_type ) {
				as_unschedule_action( self::HOOK_PROCESS_SINGLE, $args, self::GROUP );
			}
		}

		nuclia_log( "Cancelled pending Nuclia indexing actions for post type: {$post_type}." );
	}

	/**
	 * Get status information for the admin UI.
	 *
	 * @since 1.1.0
	 *
	 * @return array Status information array.
	 */
	public function get_status(): array {
		return [
			'pending'  => $this->get_pending_count(),
			'running'  => $this->get_running_count(),
			'failed'   => $this->get_failed_count(),
			'is_active' => $this->get_pending_count() > 0 || $this->get_running_count() > 0,
		];
	}
	
	protected function as_count_scheduled_actions( $args = array() ) {
		if ( ! ActionScheduler::is_initialized( __FUNCTION__ ) ) {
			return 0;
		}
		$store = ActionScheduler::store();
		foreach ( array( 'date', 'modified' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$args[ $key ] = as_get_datetime_object( $args[ $key ] );
			}
		}
		$count = $store->query_actions( $args, 'count' );
		return $count;
	}
	

	/**
	 * Get pending count for a specific post type.
	 *
	 * @since 1.1.0
	 *
	 * @param string $post_type The post type.
	 *
	 * @return int The number of pending actions for this post type.
	 */
	public function get_pending_count_for_post_type( string $post_type ): int {
		$count = $this->as_count_scheduled_actions(
			[
				'hook'   => self::HOOK_PROCESS_SINGLE,
				'group'  => self::GROUP,
				'status' => ActionScheduler_Store::STATUS_PENDING,
				'partial_args_matching' => 'json',
				'args'   => [
					'post_type' => $post_type
				]
			],
		);

		return $count;
	}
}
