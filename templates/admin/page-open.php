<?php
/**
 * Admin page shell opening markup.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap progress-agentic-rag">
	<div class="progress-agentic-rag__shell">
		<header class="progress-agentic-rag__masthead">
			<div class="progress-agentic-rag__brand">
				<img class="progress-agentic-rag__brand-logo" src="<?php echo esc_url( PROGRESS_AGENTIC_RAG_URL . 'assets/img/progress-logo.svg' ); ?>" alt="<?php esc_attr_e( 'Progress', 'progress-agentic-rag' ); ?>" />
				<h1><?php esc_html_e( 'Agentic RAG', 'progress-agentic-rag' ); ?></h1>
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
