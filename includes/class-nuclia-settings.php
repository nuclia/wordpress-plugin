<?php
/**
 * Nuclia_Settings class file.
 *
 * @since   1.0.0
 *
 */

/**
 * Class Nuclia_Settings
 *
 * @since 1.0.0
 */
class Nuclia_Settings {

	/**
	 * Nuclia_Settings constructor.
	 *
	 * @since   1.0.0
	 */
	public function __construct() {
		add_option( 'nuclia_zone', '' );
		add_option( 'nuclia_token', '' );
		add_option( 'nuclia_kbid', '' );
		add_option( 'nuclia_api_is_reachable', 'no' );
		add_option( 'nuclia_taxonomy_label_map', [] );
		add_option( 'nuclia_labelsets_cache', [
			'fetched_at' => 0,
			'labelsets' => [],
			'labels' => []
		] );
		add_option( 'nuclia_indexable_post_types', [
			'post' => 1 ,
			'page' => 1
		]);
	}

	/**
	 * Get the Nuclia Zone.
	 *
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public function get_zone(): string {
		return (string) get_option( 'nuclia_zone', '' );
	}

	/**
	 * Get the Nuclia token.
	 *
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public function get_token(): string {
		return (string) get_option( 'nuclia_token', '' );
		
	}

	/**
	 * Get the Nuclia Knowledge Box ID
	 *
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public function get_kbid(): string {
		return (string) get_option( 'nuclia_kbid', '' );

	}

	/**
	 * Get the indexable post types
	 *
	 * @since   1.0.0
	 *
	 * @return array
	 */
	public function get_indexable_post_types(): array {
		return (array) get_option( 'nuclia_indexable_post_types', [] );
	}

	/**
	 * Get taxonomy -> label mapping configuration.
	 *
	 * @since   1.2.0
	 *
	 * @return array
	 */
	public function get_taxonomy_label_map(): array {
		return (array) get_option( 'nuclia_taxonomy_label_map', [] );
	}

	/**
	 * Get cached Nuclia labelsets.
	 *
	 * @since   1.2.0
	 *
	 * @return array
	 */
	public function get_labelsets_cache(): array {
		$cache = get_option( 'nuclia_labelsets_cache', [] );
		return is_array( $cache ) ? $cache : [];
	}

	/**
	 * Set cached Nuclia labelsets.
	 *
	 * @since   1.2.0
	 *
	 * @param array $labelsets
	 */
	public function set_labelsets_cache( array $labelsets ): void {
		$cache = $this->get_labelsets_cache();
		$labels = is_array( $cache['labels'] ?? null ) ? $cache['labels'] : [];

		update_option( 'nuclia_labelsets_cache', [
			'fetched_at' => time(),
			'labelsets' => array_values( $labelsets ),
			'labels' => $labels
		] );
	}

	/**
	 * Set labelsets cache including labels map.
	 *
	 * @since 1.2.0
	 *
	 * @param array $labelsets
	 * @param array $labels_map
	 */
	public function set_labelsets_cache_with_labels( array $labelsets, array $labels_map ): void {
		$labels = [];
		foreach ( $labels_map as $labelset => $labels_list ) {
			if ( ! is_string( $labelset ) || ! is_array( $labels_list ) ) {
				continue;
			}
			$labels[ $labelset ] = array_values( $labels_list );
		}

		update_option( 'nuclia_labelsets_cache', [
			'fetched_at' => time(),
			'labelsets' => array_values( $labelsets ),
			'labels' => $labels,
		] );
	}

	/**
	 * Get cached labels for a labelset.
	 *
	 * @since 1.2.0
	 *
	 * @param string $labelset
	 * @return array
	 */
	public function get_labelset_labels_cache( string $labelset ): array {
		$cache = $this->get_labelsets_cache();
		$labels = is_array( $cache['labels'] ?? null ) ? $cache['labels'] : [];
		return is_array( $labels[ $labelset ] ?? null ) ? $labels[ $labelset ] : [];
	}

	/**
	 * Store cached labels for a labelset.
	 *
	 * @since 1.2.0
	 *
	 * @param string $labelset
	 * @param array  $labels
	 */
	public function set_labelset_labels_cache( string $labelset, array $labels ): void {
		$cache = $this->get_labelsets_cache();
		$labelsets = is_array( $cache['labelsets'] ?? null ) ? $cache['labelsets'] : [];
		$stored_labels = is_array( $cache['labels'] ?? null ) ? $cache['labels'] : [];
		$stored_labels[ $labelset ] = array_values( $labels );

		update_option( 'nuclia_labelsets_cache', [
			'fetched_at' => time(),
			'labelsets' => $labelsets,
			'labels' => $stored_labels,
		] );
	}

	/**
	 * Get the API is reachable option setting.
	 *
	 * @since   1.0.0
	 *
	 * @return bool
	 */
	public function get_api_is_reachable(): bool {
		$enabled = get_option( 'nuclia_api_is_reachable', 'no' );

		return 'yes' === $enabled;
	}

	/**
	 * Set the API is reachable option setting.
	 *
	 * @since   1.0.0
	 *
	 * @param bool $flag If the API is reachable or not, 'yes' or 'no'.
	 */
	public function set_api_is_reachable( bool $flag ): void {
		$value = $flag ? 'yes' : 'no';
		update_option( 'nuclia_api_is_reachable', $value );
	}

}
