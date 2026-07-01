<?php
/**
 * Admin settings notice.
 *
 * @package ProgressAgenticRag
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}
?>
<div class="progress-agentic-rag__alert progress-agentic-rag__alert--<?php echo esc_attr( $notice_type ); ?>" role="<?php echo esc_attr( $notice_role ); ?>">
	<p><?php echo esc_html( $notice_message ); ?></p>
</div>
