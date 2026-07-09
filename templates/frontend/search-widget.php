<?php
/**
 * Frontend search widget markup.
 *
 * @package ProgressAgenticRag
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="progress-agentic-rag-search-widget" data-progress-agentic-rag-search-widget>
	<nuclia-search-bar
		knowledgebox="<?php echo esc_attr( $kbid ); ?>"
		zone="<?php echo esc_attr( $zone ); ?>"
		backend="<?php echo esc_url( $proxy_url ); ?>"
		proxy="true"
		features="<?php echo esc_attr( $widget_options['features'] ); ?>"
	></nuclia-search-bar>
	<nuclia-search-results></nuclia-search-results>
</div>
