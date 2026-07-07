<?php
/**
 * Manual sync progress modal.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="progress-agentic-rag__modal" data-progress-agentic-rag-sync-modal hidden>
	<div class="progress-agentic-rag__modal-backdrop" data-progress-agentic-rag-sync-close></div>
	<section class="progress-agentic-rag__modal-panel" role="dialog" aria-modal="true" aria-labelledby="progress-agentic-rag-sync-title">
		<div class="progress-agentic-rag__modal-header">
			<div>
				<div class="progress-agentic-rag__section-label" data-progress-agentic-rag-modal-label><?php esc_html_e( 'Manual sync', 'progress-agentic-rag' ); ?></div>
				<h2 id="progress-agentic-rag-sync-title" data-progress-agentic-rag-modal-title><?php esc_html_e( 'Syncing selected content', 'progress-agentic-rag' ); ?></h2>
			</div>
			<button type="button" class="progress-agentic-rag__modal-close" data-progress-agentic-rag-sync-close data-progress-agentic-rag-modal-close aria-label="<?php esc_attr_e( 'Close sync progress', 'progress-agentic-rag' ); ?>">&times;</button>
		</div>
		<div class="progress-agentic-rag__progress">
			<div class="progress-agentic-rag__progress-meta">
				<strong data-progress-agentic-rag-sync-percent>0%</strong>
				<span data-progress-agentic-rag-sync-message><?php esc_html_e( 'Preparing manual sync.', 'progress-agentic-rag' ); ?></span>
			</div>
			<div class="progress-agentic-rag__progress-track" aria-hidden="true">
				<div class="progress-agentic-rag__progress-bar" data-progress-agentic-rag-sync-bar></div>
			</div>
		</div>
		<div class="progress-agentic-rag__sync-stats">
			<div>
				<span data-progress-agentic-rag-sync-total-label><?php esc_html_e( 'Total entities', 'progress-agentic-rag' ); ?></span>
				<strong data-progress-agentic-rag-sync-total>0</strong>
			</div>
			<div>
				<span data-progress-agentic-rag-sync-completed-label><?php esc_html_e( 'Synced', 'progress-agentic-rag' ); ?></span>
				<strong data-progress-agentic-rag-sync-completed>0</strong>
			</div>
			<div>
				<span><?php esc_html_e( 'Failed', 'progress-agentic-rag' ); ?></span>
				<strong data-progress-agentic-rag-sync-failed>0</strong>
			</div>
		</div>
		<div class="progress-agentic-rag__sync-current">
			<span data-progress-agentic-rag-sync-current-label><?php esc_html_e( 'Current entity', 'progress-agentic-rag' ); ?></span>
			<strong data-progress-agentic-rag-sync-current><?php esc_html_e( 'Waiting to sync.', 'progress-agentic-rag' ); ?></strong>
		</div>
	</section>
</div>
