<?php
/**
 * Admin search widget status panel.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template locals are scoped by the including admin renderer.
$dashboard_url    = 'https://rag.progress.cloud';
$default_features = implode( ',', \ProgressAgenticRag\Frontend\SearchWidget::default_features() );
$feature_options  = implode( ', ', array_keys( \ProgressAgenticRag\Frontend\SearchWidget::feature_options() ) );
$shortcode        = '[progress_agentic_rag_search features="' . $default_features . '"]';
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-widget-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Frontend search', 'progress-agentic-rag' ); ?></div>
			<h2 id="progress-agentic-rag-widget-title"><?php esc_html_e( 'Search widget', 'progress-agentic-rag' ); ?></h2>
		</div>
		<div class="progress-agentic-rag__token-state"><?php echo esc_html( (string) $widget_status['label'] ); ?></div>
	</div>
	<p><?php echo esc_html( (string) $widget_status['message'] ); ?></p>
	<ul class="progress-agentic-rag__check-list">
		<li><?php echo esc_html( $api_connected ? __( 'Connection is validated.', 'progress-agentic-rag' ) : __( 'Connection is not validated.', 'progress-agentic-rag' ) ); ?></li>
		<li><?php echo esc_html( $token_saved ? __( 'Service token is stored server-side.', 'progress-agentic-rag' ) : __( 'Service token is missing.', 'progress-agentic-rag' ) ); ?></li>
		<li><?php esc_html_e( 'Widget requests use the WordPress proxy route.', 'progress-agentic-rag' ); ?></li>
	</ul>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-dashboard-title">
		<div class="progress-agentic-rag__admin-section-header">
			<h3 id="progress-agentic-rag-widget-dashboard-title"><?php esc_html_e( 'Configure the widget in Progress Agentic RAG', 'progress-agentic-rag' ); ?></h3>
		</div>
		<p><?php esc_html_e( 'Open the Progress Agentic RAG dashboard, go to Widgets, customize the search experience, then place it in WordPress with the shortcode, Gutenberg block, or Elementor widget. WordPress supplies the Zone, Knowledge Box, backend, and proxy settings from the saved connection.', 'progress-agentic-rag' ); ?></p>
		<a class="progress-agentic-rag__button progress-agentic-rag__button--secondary" href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open Progress Agentic RAG dashboard', 'progress-agentic-rag' ); ?>
		</a>
	</div>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-options-title">
		<div class="progress-agentic-rag__admin-section-header">
			<h3 id="progress-agentic-rag-widget-options-title"><?php esc_html_e( 'Widget options', 'progress-agentic-rag' ); ?></h3>
		</div>
		<p><?php esc_html_e( 'The shortcode, Gutenberg block, and Elementor widget all support the same display option:', 'progress-agentic-rag' ); ?></p>
		<ul class="progress-agentic-rag__check-list">
			<li><?php printf( esc_html__( 'Features: choose any of %s.', 'progress-agentic-rag' ), '<code>' . esc_html( $feature_options ) . '</code>' ); ?></li>
			<li><?php esc_html_e( 'Zone, Knowledge Box, backend, and proxy are injected from the saved connection so credentials stay server-side.', 'progress-agentic-rag' ); ?></li>
		</ul>
	</div>
	<div class="progress-agentic-rag__admin-grid">
		<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-gutenberg-title">
			<div class="progress-agentic-rag__admin-section-header">
				<h3 id="progress-agentic-rag-widget-gutenberg-title"><?php esc_html_e( 'Gutenberg block', 'progress-agentic-rag' ); ?></h3>
			</div>
			<p><?php esc_html_e( 'In the block editor, add the Progress Agentic RAG Search block and select widget features in the block sidebar.', 'progress-agentic-rag' ); ?></p>
		</div>
		<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-elementor-title">
			<div class="progress-agentic-rag__admin-section-header">
				<h3 id="progress-agentic-rag-widget-elementor-title"><?php esc_html_e( 'Elementor widget', 'progress-agentic-rag' ); ?></h3>
			</div>
			<p><?php esc_html_e( 'In Elementor, drag in the Progress Agentic RAG Search widget and select widget features in the Content controls.', 'progress-agentic-rag' ); ?></p>
		</div>
		<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-shortcode-title">
			<div class="progress-agentic-rag__admin-section-header">
				<h3 id="progress-agentic-rag-widget-shortcode-title"><?php esc_html_e( 'Shortcode', 'progress-agentic-rag' ); ?></h3>
			</div>
			<p><?php esc_html_e( 'Paste this shortcode into a Shortcode block, classic editor field, widget area, or builder field and adjust the features attribute when needed:', 'progress-agentic-rag' ); ?></p>
			<p><code><?php echo esc_html( $shortcode ); ?></code></p>
		</div>
	</div>
</section>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
