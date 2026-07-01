<?php
/**
 * Runtime requirements notice.
 *
 * @package ProgressAgenticRag
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'PROGRESS_AGENTIC_RAG_TESTS' ) ) {
	exit;
}
?>
<div class="notice notice-error"><p><?php echo esc_html( implode( ' ', $messages ) ); ?></p></div>
