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
