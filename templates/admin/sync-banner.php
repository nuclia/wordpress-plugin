<?php
/**
 * Sync progress banner shown on every admin tab.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped by the including admin renderer.

$banner_running = 'running' === ( $manual_sync_status['status'] ?? '' );
$banner_percent = max( 0, min( 100, (int) ( $manual_sync_status['percent'] ?? 0 ) ) );
?>
<div class="progress-agentic-rag__sync-banner" role="status" aria-live="polite" data-progress-agentic-rag-sync-banner <?php echo $banner_running ? '' : 'hidden'; ?>>
	<span class="progress-agentic-rag__spinner" aria-hidden="true" data-progress-agentic-rag-banner-spinner></span>
	<div class="progress-agentic-rag__sync-banner-body">
		<div class="progress-agentic-rag__sync-banner-text">
			<strong data-progress-agentic-rag-banner-title><?php esc_html_e( 'Sync in progress', 'progress-agentic-rag-connector' ); ?></strong>
			<span data-progress-agentic-rag-banner-message><?php echo esc_html( (string) ( $manual_sync_status['message'] ?? '' ) ); ?></span>
		</div>
		<div class="progress-agentic-rag__progress-track" aria-hidden="true">
			<div class="progress-agentic-rag__progress-bar" style="width: <?php echo esc_attr( (string) $banner_percent ); ?>%;" data-progress-agentic-rag-banner-bar></div>
		</div>
	</div>
	<strong class="progress-agentic-rag__sync-banner-percent" data-progress-agentic-rag-banner-percent><?php echo esc_html( (string) $banner_percent ); ?>%</strong>
	<button type="button" class="progress-agentic-rag__button progress-agentic-rag__button--secondary" data-progress-agentic-rag-sync-details><?php esc_html_e( 'View progress', 'progress-agentic-rag-connector' ); ?></button>
</div>
