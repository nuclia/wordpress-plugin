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
$appearance_name  = \ProgressAgenticRag\Settings\SettingsRepository::OPTION_WIDGET_APPEARANCE;
?>
<section class="progress-agentic-rag__panel" aria-labelledby="progress-agentic-rag-widget-title">
	<div class="progress-agentic-rag__panel-header">
		<div>
			<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Frontend search', 'progress-agentic-rag-connector' ); ?></div>
			<h2 id="progress-agentic-rag-widget-title"><?php esc_html_e( 'Search widget', 'progress-agentic-rag-connector' ); ?></h2>
		</div>
		<div class="progress-agentic-rag__token-state"><?php echo esc_html( (string) $widget_status['label'] ); ?></div>
	</div>
	<p><?php echo esc_html( (string) $widget_status['message'] ); ?></p>
	<ul class="progress-agentic-rag__check-list">
		<li><?php echo esc_html( $api_connected ? __( 'Connection is validated.', 'progress-agentic-rag-connector' ) : __( 'Connection is not validated.', 'progress-agentic-rag-connector' ) ); ?></li>
		<li><?php echo esc_html( $token_saved ? __( 'Service token is stored server-side.', 'progress-agentic-rag-connector' ) : __( 'Service token is missing.', 'progress-agentic-rag-connector' ) ); ?></li>
		<li><?php esc_html_e( 'Widget requests use the WordPress proxy route.', 'progress-agentic-rag-connector' ); ?></li>
	</ul>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-dashboard-title">
		<div class="progress-agentic-rag__admin-section-header">
			<h3 id="progress-agentic-rag-widget-dashboard-title"><?php esc_html_e( 'Configure the widget in Progress Agentic RAG', 'progress-agentic-rag-connector' ); ?></h3>
		</div>
		<p><?php esc_html_e( 'Open the Progress Agentic RAG dashboard, go to Widgets, customize the search experience, then place it in WordPress with the shortcode, Gutenberg block, or Elementor widget. WordPress supplies the Zone, Knowledge Box, backend, and proxy settings from the saved connection.', 'progress-agentic-rag-connector' ); ?></p>
		<a class="progress-agentic-rag__button progress-agentic-rag__button--secondary" href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Open Progress Agentic RAG dashboard', 'progress-agentic-rag-connector' ); ?>
		</a>
	</div>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-options-title">
		<div class="progress-agentic-rag__admin-section-header">
			<h3 id="progress-agentic-rag-widget-options-title"><?php esc_html_e( 'Widget options', 'progress-agentic-rag-connector' ); ?></h3>
		</div>
		<p><?php esc_html_e( 'The shortcode, Gutenberg block, and Elementor widget all support the same display option:', 'progress-agentic-rag-connector' ); ?></p>
		<ul class="progress-agentic-rag__check-list">
			<?php /* translators: %s: comma-separated list of supported widget features. */ ?>
			<li><?php printf( esc_html__( 'Features: choose any of %s.', 'progress-agentic-rag-connector' ), '<code>' . esc_html( $feature_options ) . '</code>' ); ?></li>
			<li><?php esc_html_e( 'Zone, Knowledge Box, backend, and proxy are injected from the saved connection so credentials stay server-side.', 'progress-agentic-rag-connector' ); ?></li>
		</ul>
	</div>
	<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-appearance-title">
		<div class="progress-agentic-rag__admin-section-header">
			<h3 id="progress-agentic-rag-widget-appearance-title"><?php esc_html_e( 'Response appearance', 'progress-agentic-rag-connector' ); ?></h3>
		</div>
		<p><?php esc_html_e( 'Customize the answer and search result cards. Defaults follow the Progress Sistema design tokens.', 'progress-agentic-rag-connector' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-progress-agentic-rag-widget-appearance-form>
			<input type="hidden" name="action" value="progress_agentic_rag_save_widget_appearance">
			<?php wp_nonce_field( 'progress_agentic_rag_save_widget_appearance' ); ?>
			<div class="progress-agentic-rag__widget-appearance-layout">
				<div class="progress-agentic-rag__field-grid">
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-accent-color">
					<span><?php esc_html_e( 'Accent color', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-accent-color" type="color" name="<?php echo esc_attr( $appearance_name ); ?>[accent_color]" value="<?php echo esc_attr( (string) $widget_appearance['accent_color'] ); ?>">
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-text-color">
					<span><?php esc_html_e( 'Text color', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-text-color" type="color" name="<?php echo esc_attr( $appearance_name ); ?>[text_color]" value="<?php echo esc_attr( (string) $widget_appearance['text_color'] ); ?>">
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-muted-color">
					<span><?php esc_html_e( 'Muted text color', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-muted-color" type="color" name="<?php echo esc_attr( $appearance_name ); ?>[muted_color]" value="<?php echo esc_attr( (string) $widget_appearance['muted_color'] ); ?>">
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-surface-color">
					<span><?php esc_html_e( 'Card background', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-surface-color" type="color" name="<?php echo esc_attr( $appearance_name ); ?>[surface_color]" value="<?php echo esc_attr( (string) $widget_appearance['surface_color'] ); ?>">
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-border-color">
					<span><?php esc_html_e( 'Border color', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-border-color" type="color" name="<?php echo esc_attr( $appearance_name ); ?>[border_color]" value="<?php echo esc_attr( (string) $widget_appearance['border_color'] ); ?>">
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-font-family">
					<span><?php esc_html_e( 'Font family', 'progress-agentic-rag-connector' ); ?></span>
					<select id="progress-agentic-rag-widget-font-family" name="<?php echo esc_attr( $appearance_name ); ?>[font_family]">
						<option value="roboto" <?php selected( 'roboto', $widget_appearance['font_family'] ); ?>><?php esc_html_e( 'Roboto', 'progress-agentic-rag-connector' ); ?></option>
						<option value="inter" <?php selected( 'inter', $widget_appearance['font_family'] ); ?>><?php esc_html_e( 'Inter', 'progress-agentic-rag-connector' ); ?></option>
						<option value="system" <?php selected( 'system', $widget_appearance['font_family'] ); ?>><?php esc_html_e( 'System font', 'progress-agentic-rag-connector' ); ?></option>
					</select>
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-font-size">
					<span><?php esc_html_e( 'Base font size', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-font-size" type="number" min="12" max="24" step="1" name="<?php echo esc_attr( $appearance_name ); ?>[font_size]" value="<?php echo esc_attr( (string) $widget_appearance['font_size'] ); ?>">
					<small><?php esc_html_e( '12–24 pixels', 'progress-agentic-rag-connector' ); ?></small>
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-line-height">
					<span><?php esc_html_e( 'Line height', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-line-height" type="number" min="1.2" max="2" step="0.1" name="<?php echo esc_attr( $appearance_name ); ?>[line_height]" value="<?php echo esc_attr( (string) $widget_appearance['line_height'] ); ?>">
					<small><?php esc_html_e( '1.2–2.0', 'progress-agentic-rag-connector' ); ?></small>
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-border-radius">
					<span><?php esc_html_e( 'Card corner radius', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-border-radius" type="number" min="0" max="24" step="1" name="<?php echo esc_attr( $appearance_name ); ?>[border_radius]" value="<?php echo esc_attr( (string) $widget_appearance['border_radius'] ); ?>">
					<small><?php esc_html_e( '0–24 pixels', 'progress-agentic-rag-connector' ); ?></small>
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-card-padding">
					<span><?php esc_html_e( 'Card padding', 'progress-agentic-rag-connector' ); ?></span>
					<input id="progress-agentic-rag-widget-card-padding" type="number" min="8" max="48" step="1" name="<?php echo esc_attr( $appearance_name ); ?>[card_padding]" value="<?php echo esc_attr( (string) $widget_appearance['card_padding'] ); ?>">
					<small><?php esc_html_e( '8–48 pixels', 'progress-agentic-rag-connector' ); ?></small>
				</label>
				<label class="progress-agentic-rag__field" for="progress-agentic-rag-widget-shadow">
					<span><?php esc_html_e( 'Card shadow', 'progress-agentic-rag-connector' ); ?></span>
					<select id="progress-agentic-rag-widget-shadow" name="<?php echo esc_attr( $appearance_name ); ?>[shadow]">
						<option value="none" <?php selected( 'none', $widget_appearance['shadow'] ); ?>><?php esc_html_e( 'None', 'progress-agentic-rag-connector' ); ?></option>
						<option value="subtle" <?php selected( 'subtle', $widget_appearance['shadow'] ); ?>><?php esc_html_e( 'Subtle', 'progress-agentic-rag-connector' ); ?></option>
						<option value="soft" <?php selected( 'soft', $widget_appearance['shadow'] ); ?>><?php esc_html_e( 'Soft', 'progress-agentic-rag-connector' ); ?></option>
					</select>
				</label>
				</div>
				<aside class="progress-agentic-rag__widget-preview" data-progress-agentic-rag-widget-preview aria-labelledby="progress-agentic-rag-widget-preview-title">
					<div class="progress-agentic-rag__section-label"><?php esc_html_e( 'Live preview', 'progress-agentic-rag-connector' ); ?></div>
					<div class="progress-agentic-rag__widget-preview-card" data-progress-agentic-rag-widget-preview-card>
						<h4 id="progress-agentic-rag-widget-preview-title" data-progress-agentic-rag-widget-preview-accent><?php esc_html_e( 'Answer', 'progress-agentic-rag-connector' ); ?></h4>
						<p><?php esc_html_e( 'Here is an example answer using your selected response appearance.', 'progress-agentic-rag-connector' ); ?></p>
						<ol>
							<li><?php esc_html_e( 'Review the relevant information.', 'progress-agentic-rag-connector' ); ?></li>
							<li><?php esc_html_e( 'Open a source for more detail.', 'progress-agentic-rag-connector' ); ?></li>
						</ol>
						<div class="progress-agentic-rag__widget-preview-meta" data-progress-agentic-rag-widget-preview-muted><?php esc_html_e( '3 sources', 'progress-agentic-rag-connector' ); ?></div>
					</div>
					<div class="progress-agentic-rag__widget-preview-card" data-progress-agentic-rag-widget-preview-card>
						<h4 data-progress-agentic-rag-widget-preview-accent><?php esc_html_e( 'Example search result', 'progress-agentic-rag-connector' ); ?></h4>
						<p><?php esc_html_e( 'A matching document excerpt appears here.', 'progress-agentic-rag-connector' ); ?></p>
						<div class="progress-agentic-rag__widget-preview-meta" data-progress-agentic-rag-widget-preview-muted><?php esc_html_e( 'Product documentation', 'progress-agentic-rag-connector' ); ?></div>
					</div>
				</aside>
			</div>
			<div class="progress-agentic-rag__actions">
				<button type="submit" class="progress-agentic-rag__button progress-agentic-rag__button--primary"><?php esc_html_e( 'Save response appearance', 'progress-agentic-rag-connector' ); ?></button>
			</div>
		</form>
	</div>
	<div class="progress-agentic-rag__admin-grid">
		<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-gutenberg-title">
			<div class="progress-agentic-rag__admin-section-header">
				<h3 id="progress-agentic-rag-widget-gutenberg-title"><?php esc_html_e( 'Gutenberg block', 'progress-agentic-rag-connector' ); ?></h3>
			</div>
			<p><?php esc_html_e( 'In the block editor, add the Progress Agentic RAG Search block and select widget features in the block sidebar.', 'progress-agentic-rag-connector' ); ?></p>
		</div>
		<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-elementor-title">
			<div class="progress-agentic-rag__admin-section-header">
				<h3 id="progress-agentic-rag-widget-elementor-title"><?php esc_html_e( 'Elementor widget', 'progress-agentic-rag-connector' ); ?></h3>
			</div>
			<p><?php esc_html_e( 'In Elementor, drag in the Progress Agentic RAG Search widget and select widget features in the Content controls.', 'progress-agentic-rag-connector' ); ?></p>
		</div>
		<div class="progress-agentic-rag__admin-section" aria-labelledby="progress-agentic-rag-widget-shortcode-title">
			<div class="progress-agentic-rag__admin-section-header">
				<h3 id="progress-agentic-rag-widget-shortcode-title"><?php esc_html_e( 'Shortcode', 'progress-agentic-rag-connector' ); ?></h3>
			</div>
			<p><?php esc_html_e( 'Paste this shortcode into a Shortcode block, classic editor field, widget area, or builder field and adjust the features attribute when needed:', 'progress-agentic-rag-connector' ); ?></p>
			<p><code><?php echo esc_html( $shortcode ); ?></code></p>
		</div>
	</div>
</section>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
