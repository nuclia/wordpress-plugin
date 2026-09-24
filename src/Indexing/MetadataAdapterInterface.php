<?php
/**
 * Contract for plugins that expose supplementary post metadata (e.g. ACF custom
 * fields) that should be indexed alongside a post's primary content.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Indexing;

use WP_Post;

defined( 'ABSPATH' ) || exit;

interface MetadataAdapterInterface {
	/**
	 * Whether the source plugin this adapter targets (e.g. ACF) is active.
	 */
	public function is_active(): bool;

	/**
	 * Build a plain-text representation of a post's supplementary metadata.
	 *
	 * @return string Composed plain text, or '' when there is nothing to index.
	 */
	public function extract_text( WP_Post $post ): string;
}
