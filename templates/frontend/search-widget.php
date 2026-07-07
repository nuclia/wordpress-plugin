<?php
/**
 * Front page search widget markup.
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
		features="answers,rephrase,filter,suggestions"
	></nuclia-search-bar>
	<nuclia-search-results></nuclia-search-results>
</div>
