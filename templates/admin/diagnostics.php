<?php
/**
 * Admin diagnostics panel.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped by the including admin renderer.
$indexation_diagnostics = is_array( $diagnostics['indexation'] ?? null ) ? $diagnostics['indexation'] : [];
$background_status      = is_array( $indexation_diagnostics['background_sync'] ?? null ) ? $indexation_diagnostics['background_sync'] : [];
$failed_sync_items      = is_array( $indexation_diagnostics['failed_items'] ?? null ) ? $indexation_diagnostics['failed_items'] : [];
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-diagnostics-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Support', 'progress-agentic-rag-connector' ); ?></div>
			<h2 id="progress-agentic-rag-diagnostics-title"><?php esc_html_e( 'Diagnostics', 'progress-agentic-rag-connector' ); ?></h2>
		</div>
	</div>
	<p><?php esc_html_e( 'Export redacted plugin state for troubleshooting. Service tokens are never included.', 'progress-agentic-rag-connector' ); ?></p>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-diagnostics-failures-title">
		<div class="progress-agentic-rag__admin-section-header">
			<div>
				<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Indexation', 'progress-agentic-rag-connector' ); ?></div>
				<h3 id="progress-agentic-rag-diagnostics-failures-title"><?php esc_html_e( 'Sync failures', 'progress-agentic-rag-connector' ); ?></h3>
			</div>
			<strong><?php echo esc_html( (string) count( $failed_sync_items ) ); ?></strong>
		</div>
		<?php if ( ! empty( $background_status['message'] ) ) : ?>
			<p><?php echo esc_html( (string) $background_status['message'] ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $failed_sync_items ) ) : ?>
			<p class="progress-agentic-rag__muted"><?php esc_html_e( 'No failed sync item details are currently recorded.', 'progress-agentic-rag-connector' ); ?></p>
		<?php else : ?>
			<ul class="progress-agentic-rag__failure-list">
				<?php foreach ( $failed_sync_items as $item ) : ?>
					<li>
						<strong><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></strong>
						<span><?php echo esc_html( (string) ( $item['message'] ?? '' ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! empty( $indexation_diagnostics['failed_items_truncated'] ) ) : ?>
				<p class="progress-agentic-rag__muted"><?php esc_html_e( 'Only the first 20 failed items are shown.', 'progress-agentic-rag-connector' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<div class="progress-agentic-rag__actions">
		<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-export-diagnostics><?php esc_html_e( 'Copy diagnostics', 'progress-agentic-rag-connector' ); ?></button>
	</div>
	<p class="progress-agentic-rag__muted" data-progress-agentic-rag-diagnostics-status></p>
	<textarea class="progress-agentic-rag__diagnostics-output" data-progress-agentic-rag-diagnostics-output readonly></textarea>
</section>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
