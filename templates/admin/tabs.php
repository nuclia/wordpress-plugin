<?php
/**
 * Admin tab navigation.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="progress-agentic-rag__tabs" aria-label="<?php esc_attr_e( 'Progress Agentic RAG sections', 'progress-agentic-rag' ); ?>">
	<?php foreach ( $tabs as $tab ) : ?>
		<a
			class="progress-agentic-rag__tab <?php echo $tab['key'] === $active_tab ? 'progress-agentic-rag__tab--active' : ''; ?>"
			href="<?php echo esc_url( $tab['url'] ); ?>"
			<?php echo $tab['key'] === $active_tab ? 'aria-current="page"' : ''; ?>
		>
			<?php echo esc_html( $tab['label'] ); ?>
		</a>
	<?php endforeach; ?>
</nav>
