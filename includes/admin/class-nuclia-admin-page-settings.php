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
	
}
