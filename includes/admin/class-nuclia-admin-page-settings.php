<?php
/**
 * Nuclia_Admin_Page_Settings class file.
 *
 * @since   1.0.0
 *
 */

/**
 * Class Nuclia_Admin_Page_Settings
 *
 * @since 1.0.0
 */
class Nuclia_Admin_Page_Settings {

	/**
	 * The Nuclia_Plugin instance.
	 *
	 * @since  1.0.0
	 *
	 * @var Nuclia_Plugin
	 */
	private Nuclia_Plugin $plugin;

	/**
	 * The Nuclia_Settings instance.
	 *
	 * @since  1.0.0
	 *
	 * @var Nuclia_Settings
	 */
	private Nuclia_Settings $settings;

	/**
	 * Admin page slug.
	 *
	 * @since  1.0.0
	 *
	 * @var string
	 */
	private string $slug = 'progress-agentic-rag';

	/**
	 * Admin page capabilities.
	 *
	 * @since  1.0.0
	 *
	 * @var string
	 */
	private string $capability = 'manage_options';

	
	/**
	 * Nuclia_Admin_Page_Settings constructor.
	 *
	 * @since  1.0.0
	 *
	 * @param Nuclia_Plugin $plugin The Nuclia_Plugin instance.
	 */
	public function __construct( Nuclia_Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->settings = $plugin->get_settings();
		
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_init', [ $this, 'add_settings' ] );
		add_action( 'admin_notices', [ $this, 'display_errors' ] );

		// Display a link to this page from the plugins page.
		add_filter( 'plugin_action_links_' . PROGRESS_NUCLIA_PLUGIN_BASENAME, [ $this, 'add_action_links' ] );

		// AJAX handlers for background processing
		add_action( 'wp_ajax_nuclia_schedule_indexing', [ $this, 'ajax_schedule_indexing' ] );
		add_action( 'wp_ajax_nuclia_cancel_indexing', [ $this, 'ajax_cancel_indexing' ] );
		add_action( 'wp_ajax_nuclia_get_indexing_status', [ $this, 'ajax_get_indexing_status' ] );
		add_action( 'wp_ajax_nuclia_get_labelset_labels', [ $this, 'ajax_get_labelset_labels' ] );
	}

	/**
	 * Add action links.
	 *
	 * @since  1.0.0
	 *
	 * @param array $links Array of action links.
	 *
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		return array_merge(
			$links,
			[
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->slug ) ) . '">' . esc_html__( 'Settings', 'progress-agentic-rag' ) . '</a>',
			]
		);
	}

	/**
	 * Add admin menu page.
	 *
	 * @since  1.0.0
	 *
	 * @return void The resulting page's hook_suffix or false on failure.
	 */
	public function add_page(): void {
		
		add_menu_page(
			'Progress Agentic RAG',
			esc_html__( 'Progress Agentic RAG', 'progress-agentic-rag' ),
			$this->capability,
			$this->slug,
			[ $this, 'display_page' ],
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPHN2ZyB2aWV3Qm94PSI3LjE0OCAxMy40NTYgOTEuMDM1IDk0LjAzNyIgd2lkdGg9IjkxLjAzNSIgaGVpZ2h0PSI5NC4wMzciIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CiAgPGRlZnM+CiAgICA8c3R5bGU+LmNscy0xe2ZpbGw6I2ZmZDkxYjt9LmNscy0ye2ZpbGw6IzI1MDBmZjt9LmNscy0ze2ZpbGw6I2ZmMDA2YTt9PC9zdHlsZT4KICA8L2RlZnM+CiAgPHBhdGggY2xhc3M9ImNscy0xIiBkPSJNOTEuNjYsMzUuNzgsNTMuNDcsMTQuNDNhLjE5LjE5LDAsMCwwLS4xOCwwTDE0Ljk0LDM1LjQ5YS4xOS4xOSwwLDAsMCwwLC4zM0w1MC40LDU1LjUyYS4xOS4xOSwwLDAsMCwuMTgsMCw1LjQ3LDUuNDcsMCwwLDEsNS43MS4xMy4xNy4xNywwLDAsMCwuMTgsMEw5MS42NiwzNi4xMUEuMTkuMTksMCwwLDAsOTEuNjYsMzUuNzhaIi8+CiAgPHBhdGggY2xhc3M9ImNscy0yIiBkPSJNNTguNzcsNjAuMDhhLjcxLjcxLDAsMCwxLDAsLjE0QTUuNDcsNS40NywwLDAsMSw1Niw2NWEuMTYuMTYsMCwwLDAtLjA5LjE1djQxYS4xOS4xOSwwLDAsMCwuMjguMTZMOTQuNDEsODUuMTFhLjIuMiwwLDAsMCwuMDktLjE3VjQwLjU1YS4xOC4xOCwwLDAsMC0uMjctLjE3WiIvPgogIDxwYXRoIGNsYXNzPSJjbHMtMyIgZD0iTTUxLjA1LDY1LjI5djQxYS4xOC4xOCwwLDAsMS0uMjcuMTZMMTIuMjEsODVhLjIxLjIxLDAsMCwxLS4xLS4xN1Y0MC4yN2EuMTkuMTksMCwwLDEsLjI4LS4xNkw0Ny45LDU5LjgzYzAsLjEzLDAsLjI2LDAsLjM5QTUuNDYsNS40NiwwLDAsMCw1MSw2NS4xMy4xOC4xOCwwLDAsMSw1MS4wNSw2NS4yOVoiLz4KPC9zdmc+'
		);

	}

	/**
	 * Add settings.
	 *
	 * @since  1.0.0
	 */
	public function add_settings(): void {
		add_settings_section(
			'nuclia_section_settings',
			null,
			[ $this, 'print_settings_section' ],
			$this->slug
		);

		add_settings_field(
			'nuclia_zone',
			esc_html__( 'Zone', 'progress-agentic-rag' ),
			[ $this, 'zone_callback' ],
			$this->slug,
			'nuclia_section_settings'
		);

		add_settings_field(
			'nuclia_kbid',
			esc_html__( 'Knowledge Box ID', 'progress-agentic-rag' ),
			[ $this, 'kbid_callback' ],
			$this->slug,
			'nuclia_section_settings'
		);
		
		add_settings_field(
			'nuclia_token',
			esc_html__( 'Token', 'progress-agentic-rag' ),
			[ $this, 'token_callback' ],
			$this->slug,
			'nuclia_section_settings'
		);


		add_settings_field(
			'nuclia_indexable_post_types',
			esc_html__( 'Post types to index', 'progress-agentic-rag' ),
			[ $this, 'indexable_post_types_callback' ],
			$this->slug,
			'nuclia_section_settings'
		);

		add_settings_field(
			'nuclia_taxonomy_label_map',
			esc_html__( 'Taxonomy label mapping', 'progress-agentic-rag' ),
			[ $this, 'taxonomy_label_map_callback' ],
			$this->slug,
			'nuclia_section_settings'
		);
		
		register_setting(
			'nuclia_settings',
			'nuclia_zone',
			[
				'type' => 'text',
				'sanitize_callback' => [ $this, 'sanitize_zone' ]
			]
		);
		
		register_setting(
			'nuclia_settings',
			'nuclia_kbid',
			[
				'type' => 'text',
				'sanitize_callback' => [ $this, 'sanitize_kbid' ]
			]
		);	
			
		register_setting(
			'nuclia_settings',
			'nuclia_token',
			[
				'type' => 'text',
				'sanitize_callback' => [ $this, 'sanitize_token' ]
			]
		);

		register_setting(
			'nuclia_settings',
			'nuclia_indexable_post_types',
			[
				'type' => 'array',
				'sanitize_callback' => [ $this, 'sanitize_indexable_post_types' ]
			]
		);

		register_setting(
			'nuclia_settings',
			'nuclia_taxonomy_label_map',
			[
				'type' => 'array',
				'sanitize_callback' => [ $this, 'sanitize_taxonomy_label_map' ]
			]
		);
	}

	/**
	 * Zone callback.
	 *
	 * @since  1.0.0
	 */
	public function zone_callback(): void {
		$settings      = $this->plugin->get_settings();
		$setting       = $settings->get_zone();
		?>
<input type="text" name="nuclia_zone" class="regular-text" value="<?php echo esc_attr( $setting ); ?>"/>
<p class="description" id="home-description">
  <?php esc_html_e( 'Your Progress Agentic RAG Zone. Default: europe-1', 'progress-agentic-rag' ); ?>
</p>
<?php
	}

	/**
	 * Token callback.
	 *
	 * @since  1.0.0
	 */
	public function token_callback(): void {
		$settings      = $this->plugin->get_settings();
		$setting       = $settings->get_token();
		?>
<input type="password" name="nuclia_token" class="regular-text" value="<?php echo esc_attr( $setting ); ?>" autocomplete="new-password" />
<p class="description" id="home-description">
  <?php esc_html_e( 'Your Progress Agentic RAG Service Access token with Contributor access (kept private).', 'progress-agentic-rag' ); ?>
</p>
<?php
	}

	/**
	 * Admin Knowledge box UID callback.
	 *
	 * @since  1.0.0
	 */
	public function kbid_callback(): void {
		$settings      = $this->plugin->get_settings();
		$setting       = $settings->get_kbid();
		?>
<input type="text" name="nuclia_kbid" class="regular-text" value="<?php echo esc_attr( $setting ); ?>" autocomplete="off" />
<p class="description" id="home-description">
  <?php esc_html_e( 'Your Progress Agentic RAG Knowledge box UID (must be public).', 'progress-agentic-rag' ); ?>
</p>
<?php
	}

	/**
	 * Indexable post_types callback.
	 *
	 * @since  1.0.0
	 */
	public function indexable_post_types_callback(): void {

		$settings = $this->plugin->get_settings();
		$background_processor = $this->plugin->get_background_processor();
		
		// current value
		$indexable_post_types = $this->plugin->get_indexable_post_types();
		
		// registered searchable post types
		$args = apply_filters( 'nuclia_searchable_post_types',
			[
				'public' => true,
				'exclude_from_search' => false
			]
		);
		
		$searchable_post_types = get_post_types(
			$args,
			'names'
		);

		// Get overall background processing status
		$bg_status = $background_processor->get_status();
		
		foreach ( $searchable_post_types as $post_type ) :
			$indexed = $this->count_indexed_posts( $post_type );
			$indexables = $this->count_indexable_posts( $post_type );
			$pending_for_type = $background_processor->get_pending_count_for_post_type( $post_type );
		?>
            <p class="nuclia-post-type-row" data-post-type="<?php echo esc_attr( $post_type ); ?>">
                <label for="nuclia_<?php echo esc_attr( $post_type ); ?>_enable">
                    <input
                            id="nuclia_<?php echo esc_attr( $post_type ); ?>_enable"
                            type="checkbox"
                            name="nuclia_indexable_post_types[<?php echo esc_attr( $post_type ); ?>]"
                            value="1"
                            <?php echo ! empty( $indexable_post_types[ $post_type ] ) ? 'checked="checked"' : ''; ?>
                    />
                    &nbsp;<?php echo esc_html( $this->get_post_type_name( $post_type ) ); ?>
                </label>
                <?php printf( esc_html__( ' ( %1s indexed, %2s indexable )', 'progress-agentic-rag' ), esc_html( $indexed ), esc_html( $indexables ) ); ?>
                
                <?php if ( $settings->get_api_is_reachable() ) : ?>
                    &nbsp;
                    <span class="nuclia-pending-status" data-post-type="<?php echo esc_attr( $post_type ); ?>">
                        <?php if ( $pending_for_type > 0 ) : ?>
                            <span class="spinner is-active" style="float: none; margin: 0 5px;"></span>
                            <span class="nuclia-pending-count"><?php printf( esc_html__( '%d pending', 'progress-agentic-rag' ), $pending_for_type ); ?></span>
                        <?php endif; ?>
                    </span>
                    
                    <?php if ( $indexables > 0 ) : ?>
                        <button
                                class="nuclia-schedule-button button button-primary"
                                data-post-type="<?php echo esc_attr( $post_type ); ?>"
                                data-total="<?php echo esc_attr( $indexables ); ?>"
                                <?php echo $pending_for_type > 0 ? 'disabled' : ''; ?>
                        >
                            <?php esc_html_e( 'Schedule indexing', 'progress-agentic-rag' ); ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ( $pending_for_type > 0 ) : ?>
                        <button
                                class="nuclia-cancel-button button"
                                data-post-type="<?php echo esc_attr( $post_type ); ?>"
                        >
                            <?php esc_html_e( 'Cancel', 'progress-agentic-rag' ); ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
<?php
		endforeach;
		
		if ( $settings->get_api_is_reachable() ) :
			$total_pending = $bg_status['pending'];
			$total_running = $bg_status['running'];
			$total_failed = $bg_status['failed'];
		?>
<div class="nuclia-overall-status" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
    <p style="margin: 0;">
        <strong><?php esc_html_e( 'Background Indexing Status:', 'progress-agentic-rag' ); ?></strong>
        <span id="nuclia-status-pending"><?php printf( esc_html__( '%d pending', 'progress-agentic-rag' ), $total_pending ); ?></span> |
        <span id="nuclia-status-running"><?php printf( esc_html__( '%d running', 'progress-agentic-rag' ), $total_running ); ?></span>
        <?php if ( $total_failed > 0 ) : ?>
            | <span id="nuclia-status-failed" style="color: #d63638;"><?php printf( esc_html__( '%d failed', 'progress-agentic-rag' ), $total_failed ); ?></span>
        <?php endif; ?>
    </p>
    <?php if ( $total_pending > 0 || $total_running > 0 ) : ?>
        <p style="margin: 10px 0 0 0;">
            <button class="nuclia-cancel-all-button button" type="button">
                <?php esc_html_e( 'Cancel all pending jobs', 'progress-agentic-rag' ); ?>
            </button>
        </p>
    <?php endif; ?>
</div>

<div class="notice notice-success" style="margin-top: 15px;">
    <p><strong><span class="dashicons dashicons-saved" style="color:#090;"></span>
        <?php esc_html_e( 'API connected. Indexing runs automatically in the background.', 'progress-agentic-rag' ); ?>
    </strong></p>
</div>
<?php
		endif;
	}

	/**
	 * Taxonomy label mapping callback.
	 *
	 * @since 1.2.0
	 */
	public function taxonomy_label_map_callback(): void {
		$settings = $this->plugin->get_settings();
		$mapping = $settings->get_taxonomy_label_map();
		$labelsets = $this->plugin->get_api()->get_labelsets();

		$taxonomies = get_taxonomies(
			[
				'public' => true,
			],
			'objects'
		);

		if ( empty( $taxonomies ) ) {
			echo '<p>' . esc_html__( 'No public taxonomies available for mapping.', 'progress-agentic-rag' ) . '</p>';
			return;
		}

		echo '<p class="description">' . esc_html__( 'Map WordPress taxonomy terms to Nuclia labels. Labelset suggestions come from your Nuclia Knowledge Box.', 'progress-agentic-rag' ) . '</p>';

		$mapped_taxonomies = array_keys( $mapping );

		echo '<div style="margin: 10px 0;">';
		echo '<label for="nuclia_add_taxonomy_select">' . esc_html__( 'Add taxonomy mapping', 'progress-agentic-rag' ) . ':</label> ';
		echo '<select id="nuclia_add_taxonomy_select" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Select a taxonomy', 'progress-agentic-rag' ) . '</option>';
		foreach ( $taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy->name, $mapped_taxonomies, true ) ) {
				continue;
			}
			echo '<option value="' . esc_attr( $taxonomy->name ) . '">' . esc_html( $taxonomy->labels->name ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="button" class="button nuclia-add-mapping">' . esc_html__( 'Add mapping', 'progress-agentic-rag' ) . '</button>';
		echo '</div>';

		echo '<div id="nuclia-mapping-container">';

		foreach ( $mapping as $taxonomy_key => $config ) {
			if ( ! isset( $taxonomies[ $taxonomy_key ] ) ) {
				continue;
			}

			$taxonomy = $taxonomies[ $taxonomy_key ];
			$taxonomy_labelset = $config['labelset'] ?? '';
			$term_map = $config['terms'] ?? [];
			$fallback_config = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
			$fallback_labelset = $fallback_config['labelset'] ?? '';
			$fallback_labels = is_array( $fallback_config['labels'] ?? null ) ? $fallback_config['labels'] : [];

			echo '<div class="nuclia-mapping-block" data-taxonomy="' . esc_attr( $taxonomy_key ) . '" style="margin-top: 15px; padding: 10px; background: #fff; border: 1px solid #dcdcde;">';
			echo '<div style="display: flex; align-items: center; justify-content: space-between;">';
			echo '<div>';
			echo '<h4 style="margin: 0 0 8px 0;">' . esc_html( $taxonomy->labels->name ) . '</h4>';
			echo '<p style="margin: 0 0 8px 0;">' . esc_html( $taxonomy->name ) . '</p>';
			echo '</div>';
			echo '<button type="button" class="button link-delete nuclia-remove-mapping">' . esc_html__( 'Remove', 'progress-agentic-rag' ) . '</button>';
			echo '</div>';

			echo '<label for="nuclia_labelset_' . esc_attr( $taxonomy_key ) . '">';
			echo esc_html__( 'Labelset', 'progress-agentic-rag' ) . ':</label> ';
			echo '<select class="regular-text nuclia-labelset-select" data-taxonomy="' . esc_attr( $taxonomy_key ) . '" id="nuclia_labelset_' . esc_attr( $taxonomy_key ) . '" ';
			echo 'name="nuclia_taxonomy_label_map[' . esc_attr( $taxonomy_key ) . '][labelset]">';
			echo '<option value="">' . esc_html__( 'Select a labelset', 'progress-agentic-rag' ) . '</option>';
			foreach ( $labelsets as $labelset ) {
				$selected = ( $taxonomy_labelset === $labelset ) ? 'selected="selected"' : '';
				echo '<option value="' . esc_attr( $labelset ) . '" ' . $selected . '>' . esc_html( $labelset ) . '</option>';
			}
			echo '</select>';

			if ( empty( $labelsets ) ) {
				echo '<p style="margin: 8px 0 0 0;">' . esc_html__( 'No labelsets available. Check your Nuclia credentials.', 'progress-agentic-rag' ) . '</p>';
			}

			$terms = get_terms(
				[
					'taxonomy' => $taxonomy_key,
					'hide_empty' => false,
				]
			);

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				echo '<p style="margin: 8px 0 0 0;">' . esc_html__( 'No terms available for this taxonomy.', 'progress-agentic-rag' ) . '</p>';
			} else {
				$labels = $this->plugin->get_api()->get_labelset_labels( (string) $taxonomy_labelset );
				echo '<table class="widefat striped" style="margin-top: 10px;">';
				echo '<thead><tr><th>' . esc_html__( 'Term', 'progress-agentic-rag' ) . '</th><th>' . esc_html__( 'Nuclia labels', 'progress-agentic-rag' ) . '</th></tr></thead>';
				echo '<tbody>';

				foreach ( $terms as $term ) {
					$term_labels = $term_map[ $term->term_id ] ?? [];
					if ( ! is_array( $term_labels ) ) {
						$term_labels = $term_labels !== '' ? [ (string) $term_labels ] : [];
					}
					echo '<tr>';
					echo '<td>' . esc_html( $term->name ) . '</td>';
					echo '<td>';
					echo '<div class="nuclia-label-checkboxes" data-taxonomy="' . esc_attr( $taxonomy_key ) . '" data-term-id="' . esc_attr( $term->term_id ) . '">';
					foreach ( $labels as $label ) {
						$checked = in_array( $label, $term_labels, true ) ? 'checked="checked"' : '';
						echo '<label style="display: block; margin: 2px 0;">';
						echo '<input type="checkbox" class="nuclia-label-checkbox" value="' . esc_attr( $label ) . '" ' . $checked . ' ';
						echo 'name="nuclia_taxonomy_label_map[' . esc_attr( $taxonomy_key ) . '][terms][' . esc_attr( $term->term_id ) . '][]"> ';
						echo esc_html( $label ) . '</label>';
					}
					if ( empty( $labels ) ) {
						echo '<em>' . esc_html__( 'No labels available.', 'progress-agentic-rag' ) . '</em>';
					}
					echo '</div>';
					echo '</td>';
					echo '</tr>';
				}

				echo '</tbody></table>';
				if ( $taxonomy_labelset !== '' && empty( $labels ) ) {
					echo '<p style="margin: 8px 0 0 0; color: #d63638;">' . esc_html__( 'No labels found for the selected labelset. Please verify the labelset exists in Nuclia and reload the page.', 'progress-agentic-rag' ) . '</p>';
				}
			}

			echo '<div class="nuclia-fallback-section" style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #dcdcde;">';
			echo '<p style="margin: 0 0 6px 0;"><strong>' . esc_html__( 'Fallback labels (when no terms assigned)', 'progress-agentic-rag' ) . '</strong></p>';
			echo '<label for="nuclia_fallback_labelset_' . esc_attr( $taxonomy_key ) . '">';
			echo esc_html__( 'Labelset', 'progress-agentic-rag' ) . ':</label> ';
			echo '<select class="regular-text nuclia-fallback-labelset-select" data-taxonomy="' . esc_attr( $taxonomy_key ) . '" id="nuclia_fallback_labelset_' . esc_attr( $taxonomy_key ) . '" ';
			echo 'name="nuclia_taxonomy_label_map[' . esc_attr( $taxonomy_key ) . '][fallback][labelset]">';
			echo '<option value="">' . esc_html__( 'Select a labelset', 'progress-agentic-rag' ) . '</option>';
			foreach ( $labelsets as $labelset ) {
				$selected = ( $fallback_labelset === $labelset ) ? 'selected="selected"' : '';
				echo '<option value="' . esc_attr( $labelset ) . '" ' . $selected . '>' . esc_html( $labelset ) . '</option>';
			}
			echo '</select>';

			$fallback_available_labels = $fallback_labelset !== '' ? $this->plugin->get_api()->get_labelset_labels( (string) $fallback_labelset ) : [];
			echo '<div class="nuclia-fallback-labels" data-taxonomy="' . esc_attr( $taxonomy_key ) . '" style="margin-top: 8px;">';
			if ( $fallback_labelset === '' ) {
				echo '<em>' . esc_html__( 'Select a labelset to load labels.', 'progress-agentic-rag' ) . '</em>';
			} elseif ( empty( $fallback_available_labels ) ) {
				echo '<em>' . esc_html__( 'No labels available.', 'progress-agentic-rag' ) . '</em>';
			} else {
				foreach ( $fallback_available_labels as $label ) {
					$checked = in_array( $label, $fallback_labels, true ) ? 'checked="checked"' : '';
					echo '<label style="display: block; margin: 2px 0;">';
					echo '<input type="checkbox" class="nuclia-fallback-label-checkbox" value="' . esc_attr( $label ) . '" ' . $checked . ' ';
					echo 'name="nuclia_taxonomy_label_map[' . esc_attr( $taxonomy_key ) . '][fallback][labels][]"> ';
					echo esc_html( $label ) . '</label>';
				}
			}
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Get post type name
	 *
	 * @since   1.0.0
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return string
	 */
	public function get_post_type_name( string $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );
		if ( $post_type_object !== NULL ) {
			return $post_type_object->labels->name;
		};
		return '';
	}
	/**
	 * Returns the number of indexed posts
	 *
	 * @since   1.1.0
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return string
	 */
	public function count_indexed_posts( string $post_type ): string {
        if ( ! post_type_exists( $post_type ) ) {
            return '0';
        }

        global $wpdb;

        $post_status = ( $post_type !== 'attachment' ) ? 'publish' : 'inherit';

        $req = $wpdb->prepare(
                "SELECT COUNT( DISTINCT p.ID )
		 FROM {$wpdb->posts} AS p
		 INNER JOIN {$wpdb->prefix}agentic_rag_for_wp AS pm
			 ON ( p.ID = pm.post_id )
		 WHERE p.post_type = %s
		   AND p.post_status = %s",
                $post_type,
                $post_status
        );

        $count = $wpdb->get_var( $req );

        if ( ! empty( $wpdb->last_error ) ) {
            return 'X';
        }

        return $count;
    }
	
	/**
	 * Returns the number of indexable posts
	 *
	 * @since   1.1.0
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return int
	 */
    public function count_indexable_posts( string $post_type ): int {
		nuclia_log( 'count_indexable_posts: '.$post_type );
        if ( ! post_type_exists( $post_type ) ) {
            return 0;
        }

        global $wpdb;
        $post_status = ( $post_type !== 'attachment' ) ? 'publish' : 'inherit';

        $limit = 100;
        $offset = 0;
        $indexable_posts = [];

        do {
            $results = $wpdb->get_results(
                    $wpdb->prepare(
                            "SELECT p.ID FROM {$wpdb->posts} AS p
                 LEFT JOIN {$wpdb->prefix}agentic_rag_for_wp AS pm ON ( p.ID = pm.post_id )
                 WHERE pm.post_id IS NULL AND p.post_type = %s AND p.post_status = %s
                 LIMIT %d OFFSET %d",
                            $post_type,
                            $post_status,
                            $limit,
                            $offset
                    )
            );

            if ( ! empty( $wpdb->last_error ) ) {
                return 0;
            }

            $indexable_posts = array_merge( $indexable_posts, wp_list_pluck( $results, 'ID' ) );
            $offset += $limit;
        } while ( count( $results ) === $limit );

        update_option( 'nuclia_indexable_' . $post_type, $indexable_posts );

        return count( $indexable_posts );
    }


	/**
	 * Sanitize Knowledge box UID.
	 *
	 * @since  1.0.0
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_kbid( string $value ): string {

		$value = sanitize_text_field( $value );
		
		$settings = $this->plugin->get_settings();

		if ( empty( $value ) ) {
			add_settings_error(
				'nuclia_settings',
				'empty',
				esc_html__( 'Knowledge box UID should not be empty.', 'progress-agentic-rag' )
			);
			$settings->set_api_is_reachable( false );
			return $value;
		}


		$valid_credentials = true;
		try {
			self::is_valid_credentials( $settings->get_zone(), $value, $settings->get_token() );
		} catch ( Exception $exception ) {
			$valid_credentials = false;
			add_settings_error(
				'nuclia_settings',
				'login_exception',
				$exception->getMessage()
			);
		}

		if ( ! $valid_credentials ) {
			add_settings_error(
				'nuclia_settings',
				'no_connection',
				esc_html__(
					'We were unable to authenticate you against the Progress Agentic RAG servers with the provided information. Please ensure that you used a valid Zone and Knowledge Box ID.',
					'progress-agentic-rag'
				)
			);
			$settings->set_api_is_reachable( false );
		};

		return $value;
	}	
		
	/**
	 * Sanitize zone.
	 *
	 * @since  1.0.0
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_zone( string $value ): string {

		$value = sanitize_text_field( $value );
		
		if ( empty( $value ) ) {
			add_settings_error(
				'nuclia_settings',
				'empty',
				esc_html__( 'Zone should not be empty.', 'progress-agentic-rag' )
			);
			$settings = $this->plugin->get_settings();
			$settings->set_api_is_reachable( false );
		}

		return $value;
	}

	/**
	 * Sanitize Service Access token.
	 *
	 * @since  1.0.0
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_token( string $value ): string {

		$value = sanitize_text_field( $value );
		
		$settings = $this->plugin->get_settings();

		if ( empty( $value ) ) {
			add_settings_error(
				'nuclia_settings',
				'empty',
				esc_html__( 'Service Access token should not be empty.', 'progress-agentic-rag' )
			);
			$settings->set_api_is_reachable( false );
			return $value;
		}
		
		
		if ( ! $this->is_valid_token( $settings->get_zone(), $settings->get_kbid(), $value ) ) {
			add_settings_error(
				'nuclia_settings',
				'wrong_token',
				esc_html__(
					'It looks like your token is wrong.',
					'progress-agentic-rag'
				)
			);
			$settings->set_api_is_reachable( false );
		} else {
			add_settings_error(
				'nuclia_settings',
				'connection_success',
				esc_html__( 'We succesfully managed to connect to the Progress Agentic RAG servers with the provided information. Background indexing has been scheduled.', 'progress-agentic-rag' ),
				'updated'
			);
			$settings->set_api_is_reachable( true );
			
			// Trigger background indexing for all enabled post types
			do_action( 'nuclia_schedule_full_reindex' );
		}
		
		return $value;
	}

	/**
	 * Sanitize indexable post_types
	 *
	 * @since  1.0.0
	 *
	 * @param array|mixed $value The data to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_indexable_post_types( mixed $value ): array {
		$settings = $this->plugin->get_settings();

		if ( is_array( $value ) ) {

			foreach( $value as $post_type => $checked ) {
				// remove disabled post types
				if ( !$checked ) {
					unset( $value[$post_type] );
				}
			}

		} else {
			$value = [];
		}

		// no post type selected, display a notice
		if ( empty( $value ) ) {
			add_settings_error(
				'nuclia_settings',
				'nothing_to_index',
				esc_html__(
					'No post type selected. No indexing will take place.',
					'progress-agentic-rag'
				)
			);
			$settings->set_api_is_reachable( false );
		}

		return $value;
	}

	/**
	 * Sanitize taxonomy label map.
	 *
	 * @since 1.2.0
	 *
	 * @param array|mixed $value
	 *
	 * @return array
	 */
	public function sanitize_taxonomy_label_map( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $value as $taxonomy => $config ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $config ) ) {
				continue;
			}

			$labelset = isset( $config['labelset'] ) ? sanitize_text_field( (string) $config['labelset'] ) : '';
			$terms = is_array( $config['terms'] ?? null ) ? $config['terms'] : [];
			$clean_terms = [];
			$fallback = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
			$fallback_labelset = isset( $fallback['labelset'] ) ? sanitize_text_field( (string) $fallback['labelset'] ) : '';
			$fallback_labels = is_array( $fallback['labels'] ?? null ) ? $fallback['labels'] : [];
			$clean_fallback_labels = [];

			foreach ( $terms as $term_id => $labels ) {
				$term_id = (int) $term_id;
				if ( $term_id <= 0 || ! term_exists( $term_id, $taxonomy ) ) {
					continue;
				}

				if ( ! is_array( $labels ) ) {
					$labels = $labels !== '' ? [ (string) $labels ] : [];
				}

				$clean_labels = [];
				foreach ( $labels as $label ) {
					$label = sanitize_text_field( (string) $label );
					if ( $label === '' ) {
						continue;
					}
					$clean_labels[] = $label;
				}

				$clean_labels = array_values( array_unique( $clean_labels ) );
				if ( empty( $clean_labels ) ) {
					continue;
				}

				$clean_terms[ $term_id ] = $clean_labels;
			}

			foreach ( $fallback_labels as $label ) {
				$label = sanitize_text_field( (string) $label );
				if ( $label === '' ) {
					continue;
				}
				$clean_fallback_labels[] = $label;
			}

			$clean_fallback_labels = array_values( array_unique( $clean_fallback_labels ) );
			$has_term_mapping = ( $labelset !== '' && ! empty( $clean_terms ) );
			$has_fallback = ( $fallback_labelset !== '' && ! empty( $clean_fallback_labels ) );

			if ( $has_term_mapping || $has_fallback ) {
				$sanitized[ $taxonomy ] = [];
				if ( $has_term_mapping ) {
					$sanitized[ $taxonomy ]['labelset'] = $labelset;
					$sanitized[ $taxonomy ]['terms'] = $clean_terms;
				}
				if ( $has_fallback ) {
					$sanitized[ $taxonomy ]['fallback'] = [
						'labelset' => $fallback_labelset,
						'labels' => $clean_fallback_labels,
					];
				}
			}
		}

		return $sanitized;
	}
	
	/**
	 * Assert that the credentials are valid.
	 *
	 * @since  1.0.0
	 *
	 * @param string $zone The Nuclia Zone.
	 * @param string $kbid The Nuclia KBID.
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function is_valid_credentials( string $zone, string $kbid, string $token ): bool {
		$endpoint = sprintf( 'https://%1s.rag.progress.cloud/api/v1/kb/%2s',$zone,$kbid);
		$headers = [
			'X-NUCLIA-SERVICEACCOUNT' => "Bearer {$token}"
		];
		$args = [
			'headers' => $headers
		];
		$response = wp_remote_get( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			//bad zone
			throw new Exception(
				__('Cannot connect to Progress Agentic RAG API, please check your Nuclia zone : '.$response->get_error_message(), 'progress-agentic-rag')
			);
		}
		
		$response_code 	= wp_remote_retrieve_response_code( $response );
		if( $response_code === 200 ) {
			return true;
		} elseif( $response_code === 422 ) {
			throw new Exception(
				__('Cannot connect to Progress Agentic RAG API, please check your Knowledge Box ID.', 'progress-agentic-rag')
			);
		} else {
			throw new Exception(
				__('Cannot connect to Progress Agentic RAG API, no response from the server.', 'progress-agentic-rag')
			);
		};
	}

	/**
	 * Check if the token is valid.
	 *
	 * @since  1.0.0
	 *
	 * @param string $zone The Nuclia Zone.
	 * @param string $kbid The Nuclia KBID.
	 * @param string $token The Nuclia Search API Key.
	 *
	 * @return bool
	 */
	public static function is_valid_token( string $zone, string $kbid, string $token ): bool {

		$endpoint = sprintf( 'https://%1s.rag.progress.cloud/api/v1/kb/%2s',$zone,$kbid);
		$args = [
			'headers' => [
				'X-NUCLIA-SERVICEACCOUNT' => "Bearer {$token}"
			]
		];
		$response = wp_remote_get( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		};
		$response_code 	= wp_remote_retrieve_response_code( $response );
		return $response_code === 200;
	}
	
	
	/**
	 * Display the page.
	 *
	 * @since  1.0.0
	 */
	public function display_page(): void {
		require_once dirname( __FILE__ ) . '/partials/page-settings.php';
	}

	/**
	 * Display errors.
	 *
	 * @since  1.0.0
	 */
	public function display_errors(): void {
		settings_errors( 'nuclia_settings' );
	}

	/**
	 * Print the settings section header.
	 *
	 * @since  1.0.0
	 */
	public function print_settings_section(): void {
		echo '<p>' . wp_kses_post( sprintf( __('The zone, token, knowledge base id can be found or configured at your Progress Agentic RAG cloud account. Please sign up at %1s and sign in at %2s.', 'progress-agentic-rag'),
			'<a href="https://rag.progress.cloud/user/signup" target="blank">https://rag.progress.cloud/user/signup</a>',
            '<a href="https://rag.progress.cloud/user/login" target="blank">https://rag.progress.cloud/user/login</a>'
        )) . '</p>';
		echo '<p>' . esc_html__( 'Once you provide your Progress Agentic RAG Zone and API key, this plugin will be able to securely communicate with Progress Agentic RAG servers.', 'progress-agentic-rag' ) . ' ' . esc_html__( 'We ensure your information is correct by testing them against the Progress Agentic RAG servers upon save.', 'progress-agentic-rag' ) . '</p>';
		$settings = $this->plugin->get_settings();
		$zone = $settings->get_zone() ?: 'your-zone';
		$kbid = $settings->get_kbid() ?: 'your-kbid';
		echo '<h3>Widget</h3>';
		echo '<p>'.esc_html__( 'You can put the Progress Agentic RAG Searchbox widget in any widget area.', 'progress-agentic-rag').'</p>';
		echo '<h3>'.esc_html__('Shortcode', 'progress-agentic-rag').'</h3>';
		echo '<p>';
		echo esc_html__( 'Copy and paste this shortcode into any content. For the features, you can choose:', 'progress-agentic-rag').'<br>';
		echo ' - "navigateToLink" : '. esc_html__("clicking on a result will open the original page rather than rendering it in the viewer." , 'progress-agentic-rag' ).'<br>';
		echo ' - "permalink" : '. esc_html__("add extra parameters in URL allowing direct opening of a resource or search results." , 'progress-agentic-rag' ).'<br>';
		echo ' - "suggestions" : '. esc_html__("suggest results while typing search query." , 'progress-agentic-rag' );
		echo '</p>';
		echo '<p><code>[agentic_rag_searchbox zone="'.$zone.'" kbid="'.$kbid.'" features="navigateToLink,permalink,suggestions"]</code></p>';
		echo '<h3>'. esc_html__("Your Progress Agentic RAG credentials", 'progress-agentic-rag').'</h3>';
	}

	/**
	 * AJAX handler to schedule indexing for a post type.
	 *
	 * @since 1.1.0
	 */
	public function ajax_schedule_indexing(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclia_reindex_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		$post_type = sanitize_text_field( $_POST['post_type'] ?? '' );

		if ( empty( $post_type ) ) {
			// Schedule all post types
			do_action( 'nuclia_schedule_full_reindex' );
			wp_send_json_success( [
				'message' => __( 'Background indexing scheduled for all post types.', 'progress-agentic-rag' ),
				'status'  => $this->plugin->get_background_processor()->get_status(),
			] );
		} else {
			// Schedule specific post type
			$this->plugin->get_background_processor()->schedule_post_type( $post_type );
			wp_send_json_success( [
				'message'   => sprintf( __( 'Background indexing scheduled for %s.', 'progress-agentic-rag' ), $post_type ),
				'status'    => $this->plugin->get_background_processor()->get_status(),
				'post_type' => $post_type,
				'pending'   => $this->plugin->get_background_processor()->get_pending_count_for_post_type( $post_type ),
			] );
		}
	}

	/**
	 * AJAX handler to cancel all pending indexing.
	 *
	 * @since 1.1.0
	 */
	public function ajax_cancel_indexing(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclia_reindex_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		$post_type = sanitize_text_field( $_POST['post_type'] ?? '' );

		if ( empty( $post_type ) ) {
			// Cancel all
			$this->plugin->get_background_processor()->cancel_all();
			wp_send_json_success( [
				'message' => __( 'All pending indexing jobs cancelled.', 'progress-agentic-rag' ),
				'status'  => $this->plugin->get_background_processor()->get_status(),
			] );
		} else {
			// Cancel specific post type
			$this->plugin->get_background_processor()->cancel_post_type( $post_type );
			wp_send_json_success( [
				'message'   => sprintf( __( 'Pending indexing jobs cancelled for %s.', 'progress-agentic-rag' ), $post_type ),
				'status'    => $this->plugin->get_background_processor()->get_status(),
				'post_type' => $post_type,
			] );
		}
	}

	/**
	 * AJAX handler to get current indexing status.
	 *
	 * @since 1.1.0
	 */
	public function ajax_get_indexing_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclia_reindex_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		$status = $this->plugin->get_background_processor()->get_status();

		// Get per-post-type pending counts
		$indexable_post_types = $this->plugin->get_indexable_post_types();
		$per_type_pending     = [];

		foreach ( array_keys( $indexable_post_types ) as $post_type ) {
			$per_type_pending[ $post_type ] = $this->plugin->get_background_processor()->get_pending_count_for_post_type( $post_type );
		}

		wp_send_json_success( [
			'status'           => $status,
			'per_type_pending' => $per_type_pending,
		] );
	}

	/**
	 * AJAX handler to get labelset labels.
	 *
	 * @since 1.2.0
	 */
	public function ajax_get_labelset_labels(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nuclia_labels_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce', 403 );
		}

		$labelset = sanitize_text_field( $_POST['labelset'] ?? '' );
		if ( $labelset === '' ) {
			wp_send_json_success( [ 'labels' => [] ] );
		}

		$labels = $this->plugin->get_api()->get_labelset_labels( $labelset );
		wp_send_json_success( [ 'labels' => $labels ] );
	}
	
}
