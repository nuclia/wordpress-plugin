<?php
/**
 * Background job scheduler adapter.
 *
 * @package ProgressAgenticRag
 */

declare(strict_types=1);

namespace ProgressAgenticRag\Indexing;

defined( 'ABSPATH' ) || exit;

/**
 * Uses bundled Action Scheduler for background jobs.
 */
final class Scheduler {
	/**
	 * Get the active scheduler backend.
	 *
	 * @return string
	 */
	public function backend(): string {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			return 'action_scheduler';
		}

		return 'unavailable';
	}

	/**
	 * Whether a scheduler backend is available.
	 *
	 * @return bool
	 */
	public function available(): bool {
		return 'unavailable' !== $this->backend();
	}

	/**
	 * Get a human-readable backend label.
	 *
	 * @return string
	 */
	public function backend_label(): string {
		switch ( $this->backend() ) {
			case 'action_scheduler':
				return __( 'Action Scheduler', 'progress-agentic-rag-connector' );
			default:
				return __( 'Unavailable', 'progress-agentic-rag-connector' );
		}
	}

	/**
	 * Get a backend status message for admin UI.
	 *
	 * @return string
	 */
	public function backend_message(): string {
		switch ( $this->backend() ) {
			case 'action_scheduler':
				return __( 'Background jobs are using Action Scheduler.', 'progress-agentic-rag-connector' );
			default:
				return __( 'Background jobs are unavailable because Action Scheduler could not be loaded.', 'progress-agentic-rag-connector' );
		}
	}

	/**
	 * Schedule a single background action.
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $hook      Action hook.
	 * @param array  $args      Action arguments.
	 * @param string $group     Action Scheduler group.
	 * @return bool
	 */
	public function schedule_single_action( int $timestamp, string $hook, array $args, string $group ): bool {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $timestamp, $hook, $args, $group );
			return true;
		}

		return false;
	}

	/**
	 * Unschedule all matching background actions.
	 *
	 * @param string $hook  Action hook.
	 * @param ?array $args  Action arguments.
	 * @param string $group Action Scheduler group.
	 * @return void
	 */
	public function unschedule_all_actions( string $hook, ?array $args, string $group ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook, $args, $group );
		}
	}

	/**
	 * Count scheduled actions by status.
	 *
	 * @param string $hook   Action hook.
	 * @param string $group  Action Scheduler group.
	 * @param string $status Action status.
	 * @return int
	 */
	public function count_actions( string $hook, string $group, string $status ): int {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		return count(
			as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'group'    => $group,
					'status'   => $status,
					'per_page' => -1,
				)
			)
		);
	}

	/**
	 * Get scheduled actions by status.
	 *
	 * @param string $hook   Action hook.
	 * @param string $group  Action Scheduler group.
	 * @param string $status Action status.
	 * @return array<int, mixed>
	 */
	public function scheduled_actions( string $hook, string $group, string $status ): array {
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return [];
		}

		return as_get_scheduled_actions(
			array(
				'hook'     => $hook,
				'group'    => $group,
				'status'   => $status,
				'per_page' => -1,
			)
		);
	}
}
