<?php
/**
 * Admin taxonomy labeling panel.
 *
 * @package ProgressAgenticRag
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-taxonomy-labeling-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Classification rules', 'progress-agentic-rag' ); ?></div>
			<h2 id="progress-agentic-rag-taxonomy-labeling-title"><?php esc_html_e( 'Taxonomy labeling', 'progress-agentic-rag' ); ?></h2>
		</div>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="progress_agentic_rag_save_taxonomy_labeling" />
		<?php wp_nonce_field( 'progress_agentic_rag_save_taxonomy_labeling' ); ?>
		<?php require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/taxonomy-mapping.php'; ?>
		<div class="progress-agentic-rag__label-reprocess" aria-live="polite">
			<div>
				<h3><?php esc_html_e( 'Label reprocessing', 'progress-agentic-rag' ); ?></h3>
				<p><?php esc_html_e( 'Update labels on already synced resources with the current taxonomy mapping. Content files are not re-uploaded.', 'progress-agentic-rag' ); ?></p>
				<p>
					<strong><?php esc_html_e( 'Synced resources:', 'progress-agentic-rag' ); ?></strong>
					<span data-progress-agentic-rag-label-synced-count data-progress-agentic-rag-synced-count><?php echo esc_html( (string) ( $label_reprocess_status['synced'] ?? 0 ) ); ?></span>
				</p>
				<p class="progress-agentic-rag__reprocess-status">
					<?php
					printf(
						/* translators: 1: pending count, 2: running count, 3: failed count. */
						esc_html__( 'Queue: %1$d pending, %2$d running, %3$d failed', 'progress-agentic-rag' ),
						(int) $label_reprocess_status['pending'],
						(int) $label_reprocess_status['running'],
						(int) $label_reprocess_status['failed']
					);
					?>
				</p>
			</div>
			<div class="progress-agentic-rag__label-actions">
				<?php if ( ! empty( $label_reprocess_status['is_active'] ) ) : ?>
					<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-label-reprocess-cancel>
						<?php esc_html_e( 'Cancel reprocessing', 'progress-agentic-rag' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-label-reprocess <?php disabled( ! $api_connected || ! $scheduler_available || empty( $sync_status['total_mapped'] ) || $delete_running ); ?>>
						<?php esc_html_e( 'Reprocess all labels', 'progress-agentic-rag' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<div class="progress-agentic-rag__actions">
			<button type="submit" class="progress-agentic-rag__button progress-agentic-rag__button--primary"><?php esc_html_e( 'Save taxonomy labels', 'progress-agentic-rag' ); ?></button>
		</div>
	</form>
</section>
