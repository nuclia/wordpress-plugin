<?php
/**
 * Admin settings page shell.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Admin;

use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

final class AdminPage {
	private const MENU_SLUG = 'progress-agentic-rag';
	private const MENU_ICON = 'assets/img/menu-icon.svg';
	private const TAB_INDEXATION = 'indexation';
	private const TAB_TAXONOMY_LABELING = 'taxonomy-labeling';
	private const TAB_CONNECTION = 'connection';

	private string $page_hook = '';

	public function __construct(
		private readonly SettingsRepository $settings,
		private readonly ManualSync $manual_sync,
		private readonly ApiClient $api_client
	) {
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_progress_agentic_rag_save_connection', [ $this, 'save_connection_settings' ] );
		add_action( 'admin_post_progress_agentic_rag_save_indexation', [ $this, 'save_indexation_settings' ] );
		add_action( 'admin_post_progress_agentic_rag_save_taxonomy_labeling', [ $this, 'save_taxonomy_labeling_settings' ] );
		add_action( 'wp_ajax_progress_agentic_rag_manual_sync_start', [ $this, 'start_manual_sync' ] );
		add_action( 'wp_ajax_progress_agentic_rag_manual_sync_status', [ $this, 'manual_sync_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_background_sync_status', [ $this, 'background_sync_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_delete_synced_resources', [ $this, 'delete_synced_resources' ] );
		add_action( 'wp_ajax_progress_agentic_rag_delete_synced_resources_status', [ $this, 'delete_synced_resources_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_label_reprocess_start', [ $this, 'start_label_reprocess' ] );
		add_action( 'wp_ajax_progress_agentic_rag_label_reprocess_cancel', [ $this, 'cancel_label_reprocess' ] );
		add_action( 'wp_ajax_progress_agentic_rag_label_reprocess_status', [ $this, 'label_reprocess_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_get_labelset_labels', [ $this, 'get_labelset_labels' ] );
	}

	public function add_menu(): void {
		$this->page_hook = (string) add_menu_page(
			__( 'Progress Agentic RAG', 'progress-agentic-rag' ),
			__( 'Progress Agentic RAG', 'progress-agentic-rag' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render' ],
			PROGRESS_AGENTIC_RAG_URL . self::MENU_ICON
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'progress-agentic-rag-admin',
			PROGRESS_AGENTIC_RAG_URL . 'assets/css/admin.css',
			[],
			PROGRESS_AGENTIC_RAG_VERSION
		);

		wp_enqueue_script(
			'progress-agentic-rag-admin',
			PROGRESS_AGENTIC_RAG_URL . 'assets/js/admin.js',
			[],
			PROGRESS_AGENTIC_RAG_VERSION,
			true
		);

		wp_localize_script(
			'progress-agentic-rag-admin',
			'progressAgenticRagAdmin',
			[
				'ajaxUrl'                     => admin_url( 'admin-ajax.php' ),
				'nonce'                       => wp_create_nonce( 'progress_agentic_rag_manual_sync' ),
				'initialSyncStatus'           => $this->manual_sync->status(),
				'initialDeleteStatus'         => $this->manual_sync->delete_status(),
					'initialBackgroundSyncStatus' => $this->manual_sync->ensure_automatic_sync(),
					'mapping'                     => $this->mapping_data(),
					'strings'                     => [
					'closeDeleteProgress'  => __( 'Close delete progress', 'progress-agentic-rag' ),
					'closeSyncProgress'    => __( 'Close sync progress', 'progress-agentic-rag' ),
					/* translators: %d: synced resource count. */
					'confirmDelete'        => __( 'Delete %d synced resource(s) from Progress Agentic RAG and clear their local sync mappings? This cannot be undone.', 'progress-agentic-rag' ),
					/* translators: %d: synced resource count. */
					'confirmReprocess'     => __( 'Update labels for %d synced resource(s) with the current taxonomy mapping?', 'progress-agentic-rag' ),
					'deleteFailed'         => __( 'Synced resources could not be deleted.', 'progress-agentic-rag' ),
					'deleteModalLabel'     => __( 'Synced resource delete', 'progress-agentic-rag' ),
					'deleteModalTitle'     => __( 'Deleting synced resources', 'progress-agentic-rag' ),
					'deleteTotalLabel'     => __( 'Total resources', 'progress-agentic-rag' ),
					'deleteDoneLabel'      => __( 'Deleted', 'progress-agentic-rag' ),
					'deleteCurrentLabel'   => __( 'Current resource', 'progress-agentic-rag' ),
					'deleteRunning'        => __( 'Delete in progress', 'progress-agentic-rag' ),
					'deleting'             => __( 'Preparing synced resource deletion.', 'progress-agentic-rag' ),
					'failed'               => __( 'Manual sync could not start.', 'progress-agentic-rag' ),
					'running'              => __( 'Sync in progress', 'progress-agentic-rag' ),
					'syncModalLabel'       => __( 'Manual sync', 'progress-agentic-rag' ),
					'syncModalTitle'       => __( 'Syncing selected content', 'progress-agentic-rag' ),
					'syncTotalLabel'       => __( 'Total entities', 'progress-agentic-rag' ),
					'syncDoneLabel'        => __( 'Synced', 'progress-agentic-rag' ),
					'syncCurrentLabel'     => __( 'Current entity', 'progress-agentic-rag' ),
					'sync'                 => __( 'Sync manually', 'progress-agentic-rag' ),
					'reprocessFailed'      => __( 'Label reprocessing could not start.', 'progress-agentic-rag' ),
					'starting'             => __( 'Preparing manual sync.', 'progress-agentic-rag' ),
					'complete'             => __( 'Manual sync complete.', 'progress-agentic-rag' ),
					'mappingSelectLabelset' => __( 'Select a labelset', 'progress-agentic-rag' ),
					'mappingRemove'        => __( 'Remove', 'progress-agentic-rag' ),
					'mappingLabelset'      => __( 'Labelset', 'progress-agentic-rag' ),
					'mappingTerm'          => __( 'Term', 'progress-agentic-rag' ),
					'mappingLabels'        => __( 'Progress Agentic RAG labels', 'progress-agentic-rag' ),
					'mappingFallback'      => __( 'Fallback labels (when no terms assigned)', 'progress-agentic-rag' ),
					'mappingSelectLabels'  => __( 'Select a labelset to load labels.', 'progress-agentic-rag' ),
					'mappingLoadingLabels' => __( 'Loading labels...', 'progress-agentic-rag' ),
					'mappingNoLabels'      => __( 'No labels available.', 'progress-agentic-rag' ),
					'mappingLabelsFailed'  => __( 'Labels could not be loaded.', 'progress-agentic-rag' ),
					'mappingNoTerms'       => __( 'No terms available for this taxonomy.', 'progress-agentic-rag' ),
				],
			]
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab              = $this->active_tab();
		$connection_status_label = $this->connection_status_label();
		$token_status_label      = $this->settings->has_token() ? __( 'Saved', 'progress-agentic-rag' ) : __( 'Missing', 'progress-agentic-rag' );

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/page-open.php';
		$this->render_tabs( $active_tab );
		if ( self::TAB_CONNECTION === $active_tab ) {
			$this->render_connection_settings();
		} elseif ( self::TAB_TAXONOMY_LABELING === $active_tab ) {
			$this->render_taxonomy_labeling();
		} else {
			$this->render_indexation();
		}
		$this->render_updated_notice();
		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/page-close.php';
	}

	public function save_connection_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Progress Agentic RAG settings.', 'progress-agentic-rag' ) );
		}

		check_admin_referer( 'progress_agentic_rag_save_connection' );

		$zone       = $this->posted_text( SettingsRepository::OPTION_ZONE );
		$kbid       = $this->posted_text( SettingsRepository::OPTION_KBID );
		$account_id = $this->posted_text( SettingsRepository::OPTION_ACCOUNT_ID );
		$token      = $this->posted_text( SettingsRepository::OPTION_TOKEN );

		$this->settings->update_connection_settings( $zone, $kbid, $account_id, $token );
		$connected = $this->validate_connection_settings();
		$this->settings->set_api_is_reachable( $connected );
		if ( $connected ) {
			$this->manual_sync->ensure_automatic_sync( true );
		}
		$this->redirect_to_tab( self::TAB_CONNECTION, $connected ? 'connected' : 'failed' );
	}

	public function save_indexation_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Progress Agentic RAG settings.', 'progress-agentic-rag' ) );
		}

		check_admin_referer( 'progress_agentic_rag_save_indexation' );

		$allowed_post_types = array_keys( $this->indexable_post_type_objects() );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; values are sanitized before use.
		$raw_post_types     = isset( $_POST['indexable_post_types'] ) && is_array( $_POST['indexable_post_types'] ) ? wp_unslash( $_POST['indexable_post_types'] ) : [];
		$selected           = [];

		foreach ( $raw_post_types as $post_type ) {
			$post_type = sanitize_key( $post_type );
			if ( in_array( $post_type, $allowed_post_types, true ) ) {
				$selected[] = $post_type;
			}
		}

		$this->settings->update_indexable_post_types( array_values( array_unique( $selected ) ) );
		$this->manual_sync->ensure_automatic_sync( true );
		$this->redirect_to_tab( self::TAB_INDEXATION );
	}

	public function save_taxonomy_labeling_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Progress Agentic RAG settings.', 'progress-agentic-rag' ) );
		}

		check_admin_referer( 'progress_agentic_rag_save_taxonomy_labeling' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; SettingsRepository sanitizes the nested map.
		$raw_taxonomy_map = isset( $_POST[ SettingsRepository::OPTION_TAXONOMY_LABEL_MAP ] ) && is_array( $_POST[ SettingsRepository::OPTION_TAXONOMY_LABEL_MAP ] ) ? wp_unslash( $_POST[ SettingsRepository::OPTION_TAXONOMY_LABEL_MAP ] ) : [];
		$this->settings->update_taxonomy_label_map( $raw_taxonomy_map );
		$this->manual_sync->ensure_automatic_sync( true );
		$this->redirect_to_tab( self::TAB_TAXONOMY_LABELING );
	}

	public function start_manual_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to sync content.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Ajax nonce checked above; values are sanitized before use.
		$raw_post_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ? wp_unslash( $_POST['post_types'] ) : [];
		$post_types     = [];

		foreach ( $raw_post_types as $post_type ) {
			$post_types[] = sanitize_key( $post_type );
		}

		$result = $this->manual_sync->start( $post_types );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => sanitize_text_field( $result->get_error_message() ) ], 400 );
		}

		wp_send_json_success( $result );
	}

	public function delete_synced_resources_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to view synced resource deletion status.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		wp_send_json_success( $this->manual_sync->delete_status() );
	}

	public function manual_sync_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to view sync status.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		wp_send_json_success( $this->manual_sync->status() );
	}

	public function background_sync_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to view automatic sync status.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		wp_send_json_success( $this->manual_sync->ensure_automatic_sync() );
	}

	public function delete_synced_resources(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to delete synced resources.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		$result = $this->manual_sync->delete_synced_resources();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => sanitize_text_field( $result->get_error_message() ) ], 400 );
		}

		wp_send_json_success( $result );
	}

	public function start_label_reprocess(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to reprocess labels.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		$result = $this->manual_sync->start_label_reprocess();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => sanitize_text_field( $result->get_error_message() ) ], 400 );
		}

		wp_send_json_success( $result );
	}

	public function cancel_label_reprocess(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to cancel label reprocessing.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		wp_send_json_success( $this->manual_sync->cancel_label_reprocess() );
	}

	public function label_reprocess_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to view label reprocessing status.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		wp_send_json_success( $this->manual_sync->label_reprocess_status() );
	}

	public function get_labelset_labels(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to load labels.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		$labelset = isset( $_POST['labelset'] ) ? sanitize_text_field( wp_unslash( $_POST['labelset'] ) ) : '';

		wp_send_json_success( [ 'labels' => $this->api_client->get_labelset_labels( $labelset ) ] );
	}

	private function active_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab is read-only admin navigation state.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : self::TAB_INDEXATION;

		if ( in_array( $tab, [ self::TAB_INDEXATION, self::TAB_TAXONOMY_LABELING, self::TAB_CONNECTION ], true ) ) {
			return $tab;
		}

		return self::TAB_INDEXATION;
	}

	private function render_tabs( string $active_tab ): void {
		$tabs = [
			[
				'key'   => self::TAB_INDEXATION,
				'label' => __( 'Indexation', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_INDEXATION ),
			],
			[
				'key'   => self::TAB_TAXONOMY_LABELING,
				'label' => __( 'Taxonomy labeling', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_TAXONOMY_LABELING ),
			],
			[
				'key'   => self::TAB_CONNECTION,
				'label' => __( 'Connection settings', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_CONNECTION ),
			],
		];

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/tabs.php';
	}

	private function render_updated_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice flag is read-only redirect state.
		if ( ! isset( $_GET['progress-agentic-rag-updated'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Connection status is read-only redirect state.
		$connection_status = isset( $_GET['connection-status'] ) ? sanitize_key( wp_unslash( $_GET['connection-status'] ) ) : '';

		if ( 'connected' === $connection_status ) {
			$notice_type    = 'success';
			$notice_role    = 'status';
			$notice_message = __( 'Connection settings saved. Progress Agentic RAG validated the connection successfully.', 'progress-agentic-rag' );
			require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/notice.php';
			return;
		}

		if ( 'failed' === $connection_status ) {
			$notice_type    = 'error';
			$notice_role    = 'alert';
			$notice_message = __( 'Connection settings saved, but Progress Agentic RAG could not validate the connection. Check the Zone, Knowledge Box ID, and Service token with Writer permissions.', 'progress-agentic-rag' );
			require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/notice.php';
			return;
		}

		$notice_type    = 'success';
		$notice_role    = 'status';
		$notice_message = __( 'Settings saved.', 'progress-agentic-rag' );
		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/notice.php';
	}

	private function render_indexation(): void {
		$post_types                = $this->indexable_post_type_objects();
		$selected_types            = $this->settings->get_indexable_post_types();
		$selected_count            = count( array_filter( $selected_types ) );
		$connection_status_label   = $this->connection_status_label();
		$api_connected             = $this->settings->get_api_is_reachable();
		$scheduler_available       = $this->manual_sync->scheduler_available();
		$scheduler_status          = $this->manual_sync->scheduler_status();
		$background_sync_status    = $this->manual_sync->ensure_automatic_sync();
		$manual_sync_status        = $this->manual_sync->status();
		$delete_status             = $this->manual_sync->delete_status();
		$delete_running            = 'running' === ( $delete_status['status'] ?? '' );
		$manual_sync_disabled      = 'running' !== ( $manual_sync_status['status'] ?? '' ) && ( ! $api_connected || ! $scheduler_available || $delete_running );
		$manual_sync_button_text   = __( 'Sync manually', 'progress-agentic-rag' );
		if ( 'running' === ( $manual_sync_status['status'] ?? '' ) ) {
			$manual_sync_button_text = sprintf(
				/* translators: 1: processed entity count, 2: total entity count, 3: completion percent. */
				__( 'Sync in progress (%1$d/%2$d, %3$d%%)', 'progress-agentic-rag' ),
				(int) ( $manual_sync_status['processed'] ?? 0 ),
				(int) ( $manual_sync_status['total'] ?? 0 ),
				(int) ( $manual_sync_status['percent'] ?? 0 )
			);
		}
		$sync_status               = $this->manual_sync->sync_status( array_keys( $post_types ) );
		$label_reprocess_status    = $this->manual_sync->label_reprocess_status();
		$delete_button_text        = __( 'Delete synced resources', 'progress-agentic-rag' );
		if ( $delete_running ) {
			$delete_button_text = sprintf(
				/* translators: 1: processed resource count, 2: total resource count, 3: completion percent. */
				__( 'Delete in progress (%1$d/%2$d, %3$d%%)', 'progress-agentic-rag' ),
				(int) ( $delete_status['processed'] ?? 0 ),
				(int) ( $delete_status['total'] ?? 0 ),
				(int) ( $delete_status['percent'] ?? 0 )
			);
		}
		$delete_disabled           = ! $delete_running && ( ! $api_connected || ! $scheduler_available || empty( $sync_status['total_mapped'] ) );

			require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/indexation.php';
	}

	private function render_taxonomy_labeling(): void {
		$post_types                = $this->indexable_post_type_objects();
		$api_connected             = $this->settings->get_api_is_reachable();
		$scheduler_available       = $this->manual_sync->scheduler_available();
		$delete_status             = $this->manual_sync->delete_status();
		$delete_running            = 'running' === ( $delete_status['status'] ?? '' );
		$sync_status               = $this->manual_sync->sync_status( array_keys( $post_types ) );
		$label_reprocess_status    = $this->manual_sync->label_reprocess_status();
		$taxonomies                = get_taxonomies( [ 'public' => true ], 'objects' );
		$taxonomy_label_map        = $this->settings->get_taxonomy_label_map();
		$taxonomy_label_map_option = SettingsRepository::OPTION_TAXONOMY_LABEL_MAP;
		$labelsets                 = $this->api_client->get_labelsets();
		$taxonomy_terms            = $this->taxonomy_terms( array_keys( $taxonomies ) );
		$labelset_labels           = $this->labelset_labels_for_mapping( $taxonomy_label_map );

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/taxonomy-labeling.php';
	}

	private function render_connection_settings(): void {
		$zone                   = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid                   = $this->settings->get_string( SettingsRepository::OPTION_KBID );
		$account_id             = $this->settings->get_string( SettingsRepository::OPTION_ACCOUNT_ID );
		$connection_state_label = sprintf(
			/* translators: %s: current connection status. */
			__( 'Connection: %s', 'progress-agentic-rag' ),
			$this->connection_status_label()
		);
		$token_state_label      = $this->settings->has_token() ? __( 'Token saved', 'progress-agentic-rag' ) : __( 'Token missing', 'progress-agentic-rag' );
		$zone_option_name       = SettingsRepository::OPTION_ZONE;
		$kbid_option_name       = SettingsRepository::OPTION_KBID;
		$account_id_option_name = SettingsRepository::OPTION_ACCOUNT_ID;
		$token_option_name      = SettingsRepository::OPTION_TOKEN;

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/connection-settings.php';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function mapping_data(): array {
		$taxonomies = [];

		foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $taxonomy_name => $taxonomy ) {
			$terms = [];
			foreach ( $this->taxonomy_terms( [ $taxonomy_name ] )[ $taxonomy_name ] ?? [] as $term ) {
				$terms[] = [
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
				];
			}

			$taxonomies[ $taxonomy_name ] = [
				'label' => isset( $taxonomy->labels->name ) ? (string) $taxonomy->labels->name : (string) $taxonomy_name,
				'terms' => $terms,
			];
		}

		return [
			'taxonomies' => $taxonomies,
			'labelsets'  => $this->api_client->get_labelsets(),
		];
	}

	/**
	 * @param list<string> $taxonomy_names Taxonomy names.
	 *
	 * @return array<string, list<object>>
	 */
	private function taxonomy_terms( array $taxonomy_names ): array {
		$taxonomy_terms = [];

		foreach ( $taxonomy_names as $taxonomy_name ) {
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy_name,
					'hide_empty' => false,
				]
			);

			$taxonomy_terms[ $taxonomy_name ] = is_wp_error( $terms ) || ! is_array( $terms ) ? [] : array_values( $terms );
		}

		return $taxonomy_terms;
	}

	/**
	 * @param array<string, mixed> $mapping Taxonomy label mapping.
	 *
	 * @return array<string, list<string>>
	 */
	private function labelset_labels_for_mapping( array $mapping ): array {
		$labelsets = [];

		foreach ( $mapping as $config ) {
			if ( ! is_array( $config ) ) {
				continue;
			}

			$labelset = isset( $config['labelset'] ) ? trim( (string) $config['labelset'] ) : '';
			if ( '' !== $labelset ) {
				$labelsets[ $labelset ] = true;
			}

			$fallback          = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
			$fallback_labelset = isset( $fallback['labelset'] ) ? trim( (string) $fallback['labelset'] ) : '';
			if ( '' !== $fallback_labelset ) {
				$labelsets[ $fallback_labelset ] = true;
			}
		}

		$labels = [];
		foreach ( array_keys( $labelsets ) as $labelset ) {
			$labels[ $labelset ] = $this->api_client->get_labelset_labels( $labelset );
		}

		return $labels;
	}

	private function connection_status_label(): string {
		if ( $this->settings->get_api_is_reachable() ) {
			return __( 'Connected', 'progress-agentic-rag' );
		}

		if ( '' === $this->settings->get_string( SettingsRepository::OPTION_ZONE ) || '' === $this->settings->get_string( SettingsRepository::OPTION_KBID ) || ! $this->settings->has_token() ) {
			return __( 'Not configured', 'progress-agentic-rag' );
		}

		return __( 'Connection failed', 'progress-agentic-rag' );
	}

	private function validate_connection_settings(): bool {
		$zone       = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid       = $this->settings->get_string( SettingsRepository::OPTION_KBID );
		$token      = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );

		if ( '' === $zone || '' === $kbid || '' === $token || ! preg_match( '/^[a-z0-9-]+$/', $zone ) ) {
			return false;
		}

		$response = wp_remote_post(
			sprintf(
				'https://%s.rag.progress.cloud/api/v1/kb/%s/resources',
				rawurlencode( $zone ),
				rawurlencode( $kbid )
			),
			[
				'headers'     => [
					'Content-Type'             => 'application/json',
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'body'        => wp_json_encode( [ 'texts' => 'progress-agentic-rag-validation' ] ),
				'redirection' => 0,
				'timeout'     => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 422 === (int) wp_remote_retrieve_response_code( $response );
	}

	private function tab_url( string $tab ): string {
		return add_query_arg(
			[
				'page' => self::MENU_SLUG,
				'tab'  => $tab,
			],
			admin_url( 'admin.php' )
		);
	}

	private function redirect_to_tab( string $tab, string $connection_status = '' ): void {
		$args = [
			'progress-agentic-rag-updated' => 'true',
		];

		if ( '' !== $connection_status ) {
			$args['connection-status'] = $connection_status;
		}

		wp_safe_redirect(
			add_query_arg(
				$args,
				$this->tab_url( $tab )
			)
		);
		exit;
	}

	private function posted_text( string $key ): string {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the matching admin nonce before reading posted values.
			return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		}

	/**
	 * @return array<string, \WP_Post_Type>
	 */
	private function indexable_post_type_objects(): array {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		if ( ! isset( $post_types['attachment'] ) ) {
			$attachment = get_post_type_object( 'attachment' );
			if ( null !== $attachment ) {
				$post_types['attachment'] = $attachment;
			}
		}

		ksort( $post_types );

		return $post_types;
	}
}
