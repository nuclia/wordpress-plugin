<?php
/**
 * Admin indexation panel.
 *
 * @package ProgressAgenticRag
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-indexation-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Content scope', 'progress-agentic-rag' ); ?></div>
			<h2 id="progress-agentic-rag-indexation-title"><?php esc_html_e( 'Indexation', 'progress-agentic-rag' ); ?></h2>
		</div>
			<div class="progress-agentic-rag__status-grid">
				<div class="progress-agentic-rag__status">
					<span><?php esc_html_e( 'Connection', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( $connection_status_label ); ?></strong>
				</div>
				<div class="progress-agentic-rag__status">
					<span><?php esc_html_e( 'Background jobs', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( (string) $scheduler_status['label'] ); ?></strong>
				</div>
				<div class="progress-agentic-rag__status">
					<span><?php esc_html_e( 'Enabled types', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( (string) $selected_count ); ?></strong>
				</div>
				<div class="progress-agentic-rag__status">
					<span><?php esc_html_e( 'Synced', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( (string) ( $sync_status['total_synced'] ?? 0 ) ); ?></strong>
				</div>
				<div class="progress-agentic-rag__status">
					<span><?php esc_html_e( 'Remaining', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( (string) ( $sync_status['total_remaining'] ?? 0 ) ); ?></strong>
				</div>
			</div>
		</div>
		<p class="progress-agentic-rag__scheduler-note"><?php echo esc_html( (string) $scheduler_status['message'] ); ?></p>
		<?php $background_percent = max( 0, min( 100, (int) ( $background_sync_status['percent'] ?? 100 ) ) ); ?>
		<div class="progress-agentic-rag__indexation-layout <?php echo empty( $background_sync_status['is_active'] ) ? '' : 'progress-agentic-rag__indexation-layout--has-background'; ?>">
		<aside class="progress-agentic-rag__background-sync" aria-labelledby="progress-agentic-rag-background-sync-title" aria-live="polite" data-progress-agentic-rag-background-sync <?php echo empty( $background_sync_status['is_active'] ) ? 'hidden' : ''; ?>>
			<div class="progress-agentic-rag__background-sync-header">
				<div>
					<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Action Scheduler', 'progress-agentic-rag' ); ?></div>
					<h3 id="progress-agentic-rag-background-sync-title"><?php esc_html_e( 'Automatic sync', 'progress-agentic-rag' ); ?></h3>
				</div>
				<strong data-progress-agentic-rag-background-percent><?php echo esc_html( (string) $background_percent ); ?>%</strong>
			</div>
			<p data-progress-agentic-rag-background-message><?php echo esc_html( (string) ( $background_sync_status['message'] ?? '' ) ); ?></p>
			<div class="progress-agentic-rag__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $background_percent ); ?>" data-progress-agentic-rag-background-track>
				<div class="progress-agentic-rag__progress-bar" style="width: <?php echo esc_attr( (string) $background_percent ); ?>%;" data-progress-agentic-rag-background-bar></div>
			</div>
			<div class="progress-agentic-rag__background-sync-meta">
				<span>
					<?php esc_html_e( 'Processed', 'progress-agentic-rag' ); ?>
					<strong data-progress-agentic-rag-background-processed><?php echo esc_html( (string) ( $background_sync_status['processed'] ?? 0 ) ); ?></strong>/<strong data-progress-agentic-rag-background-total><?php echo esc_html( (string) ( $background_sync_status['total'] ?? 0 ) ); ?></strong>
				</span>
				<span>
					<?php esc_html_e( 'Failed', 'progress-agentic-rag' ); ?>
					<strong data-progress-agentic-rag-background-failed><?php echo esc_html( (string) ( $background_sync_status['failed'] ?? 0 ) ); ?></strong>
				</span>
				<span>
					<?php esc_html_e( 'Queue', 'progress-agentic-rag' ); ?>
					<strong data-progress-agentic-rag-background-pending><?php echo esc_html( (string) ( $background_sync_status['pending'] ?? 0 ) ); ?></strong>
					<?php esc_html_e( 'pending,', 'progress-agentic-rag' ); ?>
					<strong data-progress-agentic-rag-background-running><?php echo esc_html( (string) ( $background_sync_status['running'] ?? 0 ) ); ?></strong>
					<?php esc_html_e( 'running', 'progress-agentic-rag' ); ?>
				</span>
			</div>
			<p class="progress-agentic-rag__background-sync-current">
				<?php esc_html_e( 'Current:', 'progress-agentic-rag' ); ?>
				<span data-progress-agentic-rag-background-current><?php echo esc_html( (string) ( $background_sync_status['current'] ?? '' ) ); ?></span>
			</p>
		</aside>
		<form class="progress-agentic-rag__indexation-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="progress_agentic_rag_save_indexation" />
			<?php wp_nonce_field( 'progress_agentic_rag_save_indexation' ); ?>
			<div class="progress-agentic-rag__checkbox-grid">
				<?php foreach ( $post_types as $post_type_name => $post_type ) : ?>
					<label class="progress-agentic-rag__checkbox" for="progress-agentic-rag-post-type-<?php echo esc_attr( $post_type_name ); ?>">
						<input
							type="checkbox"
						id="progress-agentic-rag-post-type-<?php echo esc_attr( $post_type_name ); ?>"
						name="indexable_post_types[]"
						value="<?php echo esc_attr( $post_type_name ); ?>"
						<?php checked( ! empty( $selected_types[ $post_type_name ] ) ); ?>
					/>
						<span><?php echo esc_html( $post_type->labels->name ); ?></span>
						<code><?php echo esc_html( $post_type_name ); ?></code>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="progress-agentic-rag__sync-overview" aria-labelledby="progress-agentic-rag-sync-overview-title">
				<div class="progress-agentic-rag__sync-overview-header">
					<div>
						<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Current sync state', 'progress-agentic-rag' ); ?></div>
						<h3 id="progress-agentic-rag-sync-overview-title"><?php esc_html_e( 'Synchronized content', 'progress-agentic-rag' ); ?></h3>
					</div>
					<div class="progress-agentic-rag__sync-total">
						<span><?php esc_html_e( 'Stored mappings', 'progress-agentic-rag' ); ?></span>
						<strong data-progress-agentic-rag-synced-count><?php echo esc_html( (string) ( $sync_status['total_mapped'] ?? 0 ) ); ?></strong>
					</div>
				</div>
				<table class="progress-agentic-rag__sync-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Type', 'progress-agentic-rag' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Synced', 'progress-agentic-rag' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Indexable', 'progress-agentic-rag' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Remaining', 'progress-agentic-rag' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sync_status['rows'] as $row ) : ?>
							<tr>
								<th scope="row">
									<span><?php echo esc_html( $row['label'] ); ?></span>
									<code><?php echo esc_html( $row['post_type'] ); ?></code>
								</th>
								<td><?php echo esc_html( (string) $row['synced'] ); ?></td>
								<td><?php echo esc_html( (string) $row['indexable'] ); ?></td>
								<td><?php echo esc_html( (string) $row['remaining'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="progress-agentic-rag__actions">
				<button type="submit" class="progress-agentic-rag__button progress-agentic-rag__button--primary"><?php esc_html_e( 'Save indexation settings', 'progress-agentic-rag' ); ?></button>
				<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-manual-sync <?php disabled( $manual_sync_disabled ); ?>><?php echo esc_html( $manual_sync_button_text ); ?></button>
				<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--danger" data-progress-agentic-rag-delete-synced <?php disabled( $delete_disabled ); ?>><?php echo esc_html( $delete_button_text ); ?></button>
			</div>
		</form>
		</div>
	</section>
<?php require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/manual-sync-modal.php'; ?>
