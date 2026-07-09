<?php
/**
 * Admin sync history panel.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped by the including admin renderer.
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-history-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Recent operations', 'progress-agentic-rag' ); ?></div>
			<h2 id="progress-agentic-rag-history-title"><?php esc_html_e( 'Sync history', 'progress-agentic-rag' ); ?></h2>
		</div>
		<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-retry-failed-sync <?php disabled( empty( $failed_sync_items ) || ! $api_connected || ! $scheduler_available ); ?>>
			<?php esc_html_e( 'Retry failed items', 'progress-agentic-rag' ); ?>
		</button>
	</div>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-queue-title">
		<div class="progress-agentic-rag__admin-section-header">
			<div>
				<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Action Scheduler', 'progress-agentic-rag' ); ?></div>
				<h3 id="progress-agentic-rag-queue-title"><?php esc_html_e( 'Queue health', 'progress-agentic-rag' ); ?></h3>
			</div>
			<strong><?php echo esc_html( (string) $scheduler_status['label'] ); ?></strong>
		</div>
		<p><?php echo esc_html( (string) $scheduler_status['message'] ); ?></p>
		<ul class="progress-agentic-rag__check-list">
			<li><?php printf( esc_html__( 'Automatic sync: %s', 'progress-agentic-rag' ), esc_html( (string) ( $background_status['message'] ?? '' ) ) ); ?></li>
			<li><?php printf( esc_html__( 'Manual sync: %s', 'progress-agentic-rag' ), esc_html( (string) ( $manual_status['message'] ?? __( 'No manual sync running.', 'progress-agentic-rag' ) ) ) ); ?></li>
			<li><?php printf( esc_html__( 'Delete state: %s', 'progress-agentic-rag' ), esc_html( (string) ( $delete_status['message'] ?? __( 'No delete running.', 'progress-agentic-rag' ) ) ) ); ?></li>
			<li><?php printf( esc_html__( 'Label queue: %1$d pending, %2$d running, %3$d failed.', 'progress-agentic-rag' ), (int) $label_reprocess_status['pending'], (int) $label_reprocess_status['running'], (int) $label_reprocess_status['failed'] ); ?></li>
		</ul>
	</div>
	<p data-progress-agentic-rag-retry-status>
		<?php
		printf(
			/* translators: %d: failed sync item count. */
			esc_html__( '%d failed sync item(s) are available for retry.', 'progress-agentic-rag' ),
			count( $failed_sync_items )
		);
		?>
	</p>
	<?php if ( empty( $history_rows ) ) : ?>
		<p class="progress-agentic-rag__muted"><?php esc_html_e( 'No sync history has been recorded yet.', 'progress-agentic-rag' ); ?></p>
	<?php else : ?>
		<table class="progress-agentic-rag__sync-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Run', 'progress-agentic-rag' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'progress-agentic-rag' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Finished', 'progress-agentic-rag' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Totals', 'progress-agentic-rag' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last detail', 'progress-agentic-rag' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $history_rows as $entry ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $entry['type'] ); ?></th>
						<td><?php echo esc_html( (string) $entry['status'] ); ?></td>
						<td><?php echo esc_html( (string) $entry['finished'] ); ?></td>
						<td>
							<?php
							printf(
								/* translators: 1: completed count, 2: total count, 3: failed count. */
								esc_html__( '%1$d/%2$d complete, %3$d failed', 'progress-agentic-rag' ),
								(int) ( $entry['complete'] ?? 0 ),
								(int) ( $entry['total'] ?? 0 ),
								(int) ( $entry['failed'] ?? 0 )
							);
							?>
						</td>
						<td><?php echo esc_html( (string) ( $entry['message'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php if ( ! empty( $failed_sync_items ) ) : ?>
		<ul class="progress-agentic-rag__failure-list">
			<?php foreach ( $failed_sync_items as $item ) : ?>
				<li>
					<strong><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></strong>
					<span><?php echo esc_html( (string) ( $item['message'] ?? '' ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
