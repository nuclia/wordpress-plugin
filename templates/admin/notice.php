<?php
/**
 * Admin settings notice.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="progress-agentic-rag__alert progress-agentic-rag__alert--<?php echo esc_attr( $notice_type ); ?>" role="<?php echo esc_attr( $notice_role ); ?>">
	<p><?php echo esc_html( $notice_message ); ?></p>
</div>
