<?php
/**
 * Admin page shell opening markup.
 *
 * @package ProgressAgenticRag
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}
?>
<div class="wrap progress-agentic-rag">
	<div class="progress-agentic-rag__shell">
		<header class="progress-agentic-rag__masthead">
			<div>
				<h1><?php esc_html_e( 'Progress Agentic RAG', 'progress-agentic-rag' ); ?></h1>
			</div>
			<div class="progress-agentic-rag__summary" aria-label="<?php esc_attr_e( 'Connection summary', 'progress-agentic-rag' ); ?>">
				<span class="progress-agentic-rag__summary-item">
					<span><?php esc_html_e( 'Connection', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( $connection_status_label ); ?></strong>
				</span>
				<span class="progress-agentic-rag__summary-item">
					<span><?php esc_html_e( 'Token', 'progress-agentic-rag' ); ?></span>
					<strong><?php echo esc_html( $token_status_label ); ?></strong>
				</span>
			</div>
		</header>
