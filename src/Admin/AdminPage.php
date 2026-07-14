<?php
/**
 * Admin settings page shell.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Admin;

use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Proxy\ProxyController;
use ProgressAgenticRag\Settings\SettingsRepository;

defined( 'ABSPATH' ) || exit;

final class AdminPage {
	private const MENU_SLUG = 'progress-agentic-rag';
	private const MENU_ICON = 'assets/img/menu-icon.svg';
	private const TAB_INDEXATION = 'indexation';
	private const TAB_SYNCED_CONTENT = 'synced-content';
	private const TAB_SYNC_HISTORY = 'sync-history';
	private const TAB_SEARCH_WIDGET = 'search-widget';
	private const TAB_DIAGNOSTICS = 'diagnostics';
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
		add_action( 'admin_post_progress_agentic_rag_save_widget_appearance', [ $this, 'save_widget_appearance_settings' ] );
		add_action( 'wp_ajax_progress_agentic_rag_manual_sync_start', [ $this, 'start_manual_sync' ] );
		add_action( 'wp_ajax_progress_agentic_rag_manual_sync_status', [ $this, 'manual_sync_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_background_sync_status', [ $this, 'background_sync_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_delete_synced_resources', [ $this, 'delete_synced_resources' ] );
		add_action( 'wp_ajax_progress_agentic_rag_delete_synced_resources_status', [ $this, 'delete_synced_resources_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_label_reprocess_start', [ $this, 'start_label_reprocess' ] );
		add_action( 'wp_ajax_progress_agentic_rag_label_reprocess_cancel', [ $this, 'cancel_label_reprocess' ] );
		add_action( 'wp_ajax_progress_agentic_rag_label_reprocess_status', [ $this, 'label_reprocess_status' ] );
		add_action( 'wp_ajax_progress_agentic_rag_get_labelset_labels', [ $this, 'get_labelset_labels' ] );
		add_action( 'wp_ajax_progress_agentic_rag_test_connection', [ $this, 'test_connection' ] );
		add_action( 'wp_ajax_progress_agentic_rag_retry_failed_sync', [ $this, 'retry_failed_sync' ] );
		add_action( 'wp_ajax_progress_agentic_rag_delete_synced_resource', [ $this, 'delete_synced_resource' ] );
		add_action( 'wp_ajax_progress_agentic_rag_export_diagnostics', [ $this, 'export_diagnostics' ] );
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
		$style_version  = filemtime( PROGRESS_AGENTIC_RAG_PATH . 'assets/css/admin.css' );
		$script_version = filemtime( PROGRESS_AGENTIC_RAG_PATH . 'assets/js/admin.js' );

		wp_enqueue_style(
			'progress-agentic-rag-admin',
			PROGRESS_AGENTIC_RAG_URL . 'assets/css/admin.css',
			[],
			false !== $style_version ? (string) $style_version : PROGRESS_AGENTIC_RAG_VERSION
		);

		wp_enqueue_script(
			'progress-agentic-rag-admin',
			PROGRESS_AGENTIC_RAG_URL . 'assets/js/admin.js',
			[],
			false !== $script_version ? (string) $script_version : PROGRESS_AGENTIC_RAG_VERSION,
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
					'testConnection'       => __( 'Testing connection...', 'progress-agentic-rag' ),
					'connectionTestFailed' => __( 'Connection test could not run.', 'progress-agentic-rag' ),
					'retryFailed'          => __( 'Failed sync items could not be retried.', 'progress-agentic-rag' ),
					'diagnosticsCopied'    => __( 'Diagnostics copied.', 'progress-agentic-rag' ),
					'diagnosticsFailed'    => __( 'Diagnostics could not be exported.', 'progress-agentic-rag' ),
					'confirmDeleteSingle'  => __( 'Delete this resource from Progress Agentic RAG and clear its local sync mapping?', 'progress-agentic-rag' ),
					'deleteSingleFailed'   => __( 'The synced resource could not be removed.', 'progress-agentic-rag' ),
					'deleteSingleComplete' => __( 'Synced resource removed.', 'progress-agentic-rag' ),
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
		$token_status_label      = __( 'Missing', 'progress-agentic-rag' );
		if ( $this->settings->has_token() ) {
			$token_status_label = $this->settings->get_api_is_reachable() ? __( 'Saved', 'progress-agentic-rag' ) : __( 'Stored, unverified', 'progress-agentic-rag' );
		}

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/page-open.php';
		$this->render_tabs( $active_tab );
		if ( self::TAB_CONNECTION === $active_tab ) {
			$this->render_connection_settings();
		} elseif ( self::TAB_TAXONOMY_LABELING === $active_tab ) {
			$this->render_taxonomy_labeling();
		} elseif ( self::TAB_SYNCED_CONTENT === $active_tab ) {
			$this->render_synced_content();
		} elseif ( self::TAB_SYNC_HISTORY === $active_tab ) {
			$this->render_sync_history();
		} elseif ( self::TAB_SEARCH_WIDGET === $active_tab ) {
			$this->render_search_widget();
		} elseif ( self::TAB_DIAGNOSTICS === $active_tab ) {
			$this->render_diagnostics();
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

		$previous = array_keys( array_filter( $this->settings->get_indexable_post_types() ) );
		$selected = array_values( array_unique( $selected ) );

		$this->settings->update_indexable_post_types( $selected );
		$this->manual_sync->cancel_post_type_sync( array_values( array_diff( $previous, $selected ) ) );
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

	public function save_widget_appearance_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Progress Agentic RAG settings.', 'progress-agentic-rag' ) );
		}

		check_admin_referer( 'progress_agentic_rag_save_widget_appearance' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked above; SettingsRepository validates every appearance value.
		$appearance = isset( $_POST[ SettingsRepository::OPTION_WIDGET_APPEARANCE ] ) && is_array( $_POST[ SettingsRepository::OPTION_WIDGET_APPEARANCE ] ) ? wp_unslash( $_POST[ SettingsRepository::OPTION_WIDGET_APPEARANCE ] ) : [];
		$this->settings->update_widget_appearance( $appearance );
		$this->redirect_to_tab( self::TAB_SEARCH_WIDGET );
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

	public function test_connection(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to test the connection.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		$token = $this->posted_text( SettingsRepository::OPTION_TOKEN );
		if ( '' === $token ) {
			$token = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );
		}

		wp_send_json_success(
			$this->connection_test_result(
				$this->posted_text( SettingsRepository::OPTION_ZONE ),
				$this->posted_text( SettingsRepository::OPTION_KBID ),
				$token
			)
		);
	}

	public function retry_failed_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to retry failed sync items.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		$result = $this->manual_sync->retry_failed_sync_items();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => sanitize_text_field( $result->get_error_message() ) ], 400 );
		}

		wp_send_json_success( $result );
	}

	public function delete_synced_resource(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to remove synced resources.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		$post_id = isset( $_POST['post_id'] ) ? (int) wp_unslash( $_POST['post_id'] ) : 0;
		$rid     = $this->synced_resource_id_for_post( $post_id );
		if ( $post_id <= 0 || '' === $rid ) {
			wp_send_json_error( [ 'message' => __( 'Synced resource mapping was not found.', 'progress-agentic-rag' ) ], 404 );
		}

		$result = $this->api_client->delete_resource( $post_id, $rid );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => sanitize_text_field( $result->get_error_message() ) ], 400 );
		}

		wp_send_json_success(
			[
				'post_id' => $post_id,
				'message' => __( 'Synced resource removed.', 'progress-agentic-rag' ),
			]
		);
	}

	public function export_diagnostics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to export diagnostics.', 'progress-agentic-rag' ) ], 403 );
		}

		check_ajax_referer( 'progress_agentic_rag_manual_sync' );

		wp_send_json_success( [ 'diagnostics' => $this->diagnostics_data() ] );
	}

	private function active_tab(): string {
		if ( ! isset( $_GET['tab'] ) ) {
			if ( ! $this->settings->get_api_is_reachable() || '' === $this->settings->get_string( SettingsRepository::OPTION_ZONE ) || '' === $this->settings->get_string( SettingsRepository::OPTION_KBID ) || ! $this->settings->has_token() ) {
				return self::TAB_CONNECTION;
			}

			return self::TAB_INDEXATION;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab is read-only admin navigation state.
		$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );

		if ( in_array( $tab, [ self::TAB_INDEXATION, self::TAB_SYNCED_CONTENT, self::TAB_SYNC_HISTORY, self::TAB_SEARCH_WIDGET, self::TAB_DIAGNOSTICS, self::TAB_TAXONOMY_LABELING, self::TAB_CONNECTION ], true ) ) {
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
				'key'   => self::TAB_SYNCED_CONTENT,
				'label' => __( 'Synchronized content', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_SYNCED_CONTENT ),
			],
			[
				'key'   => self::TAB_SYNC_HISTORY,
				'label' => __( 'Sync history', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_SYNC_HISTORY ),
			],
			[
				'key'   => self::TAB_SEARCH_WIDGET,
				'label' => __( 'Search widget', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_SEARCH_WIDGET ),
			],
			[
				'key'   => self::TAB_DIAGNOSTICS,
				'label' => __( 'Diagnostics', 'progress-agentic-rag' ),
				'url'   => $this->tab_url( self::TAB_DIAGNOSTICS ),
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

	private function render_synced_content(): void {
		$synced_content_rows = $this->synced_content_rows();

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/synced-content.php';
	}

	private function render_sync_history(): void {
		$scheduler_status       = $this->manual_sync->scheduler_status();
		$background_status      = $this->manual_sync->background_sync_status();
		$manual_status          = $this->manual_sync->status();
		$delete_status          = $this->manual_sync->delete_status();
		$label_reprocess_status = $this->manual_sync->label_reprocess_status();
		$failed_sync_items      = array_values( $this->settings->get_failed_sync_items() );
		$sync_history           = $this->settings->get_sync_history();
		$api_connected          = $this->settings->get_api_is_reachable();
		$scheduler_available    = (bool) $scheduler_status['available'];
		$history_rows           = $this->sync_history_rows( $sync_history );

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/sync-history.php';
	}

	private function render_search_widget(): void {
		$widget_status     = $this->widget_status();
		$widget_appearance = $this->settings->get_widget_appearance();
		$api_connected     = $this->settings->get_api_is_reachable();
		$token_saved       = $this->settings->has_token();
		$zone              = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid              = $this->settings->get_string( SettingsRepository::OPTION_KBID );
		$proxy_url         = '' !== $zone ? ProxyController::proxy_url( $zone ) : '';

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/search-widget-status.php';
	}

	private function render_diagnostics(): void {
		$diagnostics = $this->diagnostics_data();

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/diagnostics.php';
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
		$taxonomy_mapping_warnings = $this->taxonomy_mapping_warnings( $taxonomy_label_map, $labelsets, $taxonomy_terms, $labelset_labels, (int) ( $sync_status['total_mapped'] ?? 0 ) );

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
		$token_state_label      = __( 'Token missing', 'progress-agentic-rag' );
		if ( $this->settings->has_token() ) {
			$token_state_label = $this->settings->get_api_is_reachable() ? __( 'Token saved', 'progress-agentic-rag' ) : __( 'Token stored, unverified', 'progress-agentic-rag' );
		}
		$zone_option_name       = SettingsRepository::OPTION_ZONE;
		$kbid_option_name       = SettingsRepository::OPTION_KBID;
		$account_id_option_name = SettingsRepository::OPTION_ACCOUNT_ID;
		$token_option_name      = SettingsRepository::OPTION_TOKEN;

		require_once PROGRESS_AGENTIC_RAG_PATH . 'templates/admin/connection-settings.php';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function synced_content_rows(): array {
		$rows = [];

		foreach ( $this->api_client->get_synced_resources() as $resource ) {
			$post_id = isset( $resource->post_id ) ? (int) $resource->post_id : 0;
			$rid     = isset( $resource->nuclia_rid ) ? sanitize_text_field( (string) $resource->nuclia_rid ) : '';
			if ( $post_id <= 0 || '' === $rid ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				$title = wp_strip_all_tags( get_the_title( $post ) );
				$rows[] = [
					'post_id' => $post_id,
					'title'   => '' !== $title ? $title : sprintf(
						/* translators: %d: WordPress post ID. */
						__( 'Entity #%d', 'progress-agentic-rag' ),
						$post_id
					),
					'type'    => $this->post_type_label( $post->post_type ),
					'status'  => (string) $post->post_status,
					'url'     => get_permalink( $post ),
					'rid'     => $rid,
					'seqid'   => isset( $resource->nuclia_seqid ) ? sanitize_text_field( (string) $resource->nuclia_seqid ) : '',
				];
				continue;
			}

			$rows[] = [
				'post_id' => $post_id,
				'title'   => sprintf(
					/* translators: %d: WordPress post ID. */
					__( 'Missing entity #%d', 'progress-agentic-rag' ),
					$post_id
				),
				'type'    => __( 'Unknown', 'progress-agentic-rag' ),
				'status'  => __( 'Missing', 'progress-agentic-rag' ),
				'url'     => '',
				'rid'     => $rid,
				'seqid'   => isset( $resource->nuclia_seqid ) ? sanitize_text_field( (string) $resource->nuclia_seqid ) : '',
			];
		}

		return $rows;
	}

	private function synced_resource_id_for_post( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		foreach ( $this->api_client->get_synced_resources() as $resource ) {
			if ( $post_id === (int) ( $resource->post_id ?? 0 ) ) {
				return isset( $resource->nuclia_rid ) ? sanitize_text_field( (string) $resource->nuclia_rid ) : '';
			}
		}

		return '';
	}

	/**
	 * @param list<array<string, mixed>> $sync_history Sync history entries.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function sync_history_rows( array $sync_history ): array {
		$history_rows = [];

		foreach ( $sync_history as $entry ) {
			$history_rows[] = [
				'type'     => $this->history_type_label( (string) ( $entry['type'] ?? '' ) ),
				'status'   => $this->history_status_label( (string) ( $entry['status'] ?? '' ) ),
				'finished' => $this->format_timestamp( (int) ( $entry['finished_at'] ?? 0 ) ),
				'total'    => (int) ( $entry['total'] ?? 0 ),
				'complete' => (int) ( $entry['completed'] ?? 0 ),
				'failed'   => (int) ( $entry['failed'] ?? 0 ),
				'message'  => (string) ( $entry['message'] ?? '' ),
			];
		}

		return $history_rows;
	}

	/**
	 * @return array{ready:bool,label:string,message:string}
	 */
	private function widget_status(): array {
		if ( ! $this->settings->get_api_is_reachable() ) {
			return [
				'ready'   => false,
				'label'   => __( 'Inactive', 'progress-agentic-rag' ),
				'message' => __( 'Validate the Progress Agentic RAG connection before the search widget can render.', 'progress-agentic-rag' ),
			];
		}

		if ( '' === $this->settings->get_string( SettingsRepository::OPTION_ZONE ) || '' === $this->settings->get_string( SettingsRepository::OPTION_KBID ) || ! $this->settings->has_token() ) {
			return [
				'ready'   => false,
				'label'   => __( 'Incomplete', 'progress-agentic-rag' ),
				'message' => __( 'Zone, Knowledge Box ID, and Service token are required for the widget proxy.', 'progress-agentic-rag' ),
			];
		}

		return [
			'ready'   => true,
			'label'   => __( 'Ready', 'progress-agentic-rag' ),
			'message' => __( 'The search widget is ready to render through the shortcode, Gutenberg block, or Elementor widget using the server-side proxy.', 'progress-agentic-rag' ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function diagnostics_data(): array {
		$post_types        = $this->indexable_post_type_objects();
		$sync_status       = $this->manual_sync->sync_status( array_keys( $post_types ) );
		$scheduler_status  = $this->manual_sync->scheduler_status();
		$background_status = $this->manual_sync->background_sync_status();
		$failed_sync_items = array_values( $this->settings->get_failed_sync_items() );
		$wp_version        = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : get_bloginfo( 'version' );

		return [
			'plugin' => [
				'name'    => 'Progress Agentic RAG',
				'version' => PROGRESS_AGENTIC_RAG_VERSION,
			],
			'environment' => [
				'wordpress_version' => $wp_version,
				'php_version'       => PHP_VERSION,
			],
			'connection' => [
				'status'      => $this->connection_status_label(),
				'token_saved' => $this->settings->has_token(),
				'zone'        => $this->redact_value( $this->settings->get_string( SettingsRepository::OPTION_ZONE ) ),
				'kbid'        => $this->redact_value( $this->settings->get_string( SettingsRepository::OPTION_KBID ) ),
			],
			'scheduler' => [
				'available' => (bool) $scheduler_status['available'],
				'backend'   => (string) $scheduler_status['backend'],
				'label'     => (string) $scheduler_status['label'],
			],
			'indexation' => [
				'selected_post_types'    => array_keys( array_filter( $this->settings->get_indexable_post_types() ) ),
				'total_indexable'        => (int) ( $sync_status['total_indexable'] ?? 0 ),
				'total_synced'           => (int) ( $sync_status['total_synced'] ?? 0 ),
				'total_remaining'        => (int) ( $sync_status['total_remaining'] ?? 0 ),
				'failed_retry_items'     => count( $failed_sync_items ),
				'background_sync'        => $background_status,
				'failed_items'           => array_slice( $failed_sync_items, 0, 20 ),
				'failed_items_truncated' => count( $failed_sync_items ) > 20,
			],
			'widget' => $this->widget_status(),
			'recent_history' => $this->settings->get_sync_history(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function connection_test_result( string $zone, string $kbid, string $token ): array {
		$zone = $this->normalize_zone_value( $zone );
		$kbid = $this->normalize_kbid_value( $kbid );
		$token = $this->normalize_token_value( $token );
		$checked_at = time();

		if ( '' === $zone || '' === $kbid || '' === $token || ! preg_match( '/^[a-z0-9-]+$/', $zone ) ) {
			return [
				'connected'        => false,
				'message'          => __( 'Zone, Knowledge Box ID, and Service token are required before testing the connection.', 'progress-agentic-rag' ),
				'checked_at'       => $checked_at,
				'checked_at_label' => $this->format_timestamp( $checked_at ),
			];
		}

		$response = wp_remote_request(
			sprintf(
				'https://%s.rag.progress.cloud/api/v1/kb/%s',
				rawurlencode( $zone ),
				rawurlencode( $kbid )
			),
			[
				'method'      => 'GET',
				'headers'     => [
					'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
				],
				'redirection' => 0,
				'timeout'     => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'connected'        => false,
				'message'          => sanitize_text_field( $response->get_error_message() ),
				'checked_at'       => $checked_at,
				'checked_at_label' => $this->format_timestamp( $checked_at ),
			];
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$connected     = 200 === $response_code;
		$message       = __( 'Progress Agentic RAG validated the connection successfully.', 'progress-agentic-rag' );
		if ( ! $connected ) {
			if ( in_array( $response_code, [ 401, 403 ], true ) ) {
				$message = __( 'Progress Agentic RAG rejected the Service token for this Knowledge Box.', 'progress-agentic-rag' );
			} elseif ( in_array( $response_code, [ 404, 422 ], true ) ) {
				$message = __( 'Progress Agentic RAG could not find that Knowledge Box in the selected Zone.', 'progress-agentic-rag' );
			} elseif ( 0 === $response_code ) {
				$message = __( 'Progress Agentic RAG did not return an HTTP status while validating the connection.', 'progress-agentic-rag' );
			} else {
				$message = sprintf(
					/* translators: %d is the upstream HTTP response code. */
					__( 'Progress Agentic RAG returned HTTP %d while validating the connection.', 'progress-agentic-rag' ),
					$response_code
				);
			}
		}

		return [
			'connected'        => $connected,
			'message'          => $message,
			'checked_at'       => $checked_at,
			'checked_at_label' => $this->format_timestamp( $checked_at ),
		];
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

	/**
	 * @param array<string, mixed>              $mapping Taxonomy label mapping.
	 * @param list<string>                     $labelsets Available labelsets.
	 * @param array<string, list<object>>      $taxonomy_terms Terms keyed by taxonomy.
	 * @param array<string, list<string>>      $labelset_labels Labels keyed by labelset.
	 *
	 * @return list<string>
	 */
	private function taxonomy_mapping_warnings( array $mapping, array $labelsets, array $taxonomy_terms, array $labelset_labels, int $synced_count ): array {
		$warnings = [];

		if ( $this->settings->get_api_is_reachable() && empty( $labelsets ) ) {
			$warnings[] = __( 'No Progress Agentic RAG labelsets could be loaded. Saved taxonomy mappings cannot be validated yet.', 'progress-agentic-rag' );
		}

		foreach ( $mapping as $taxonomy => $config ) {
			if ( ! is_string( $taxonomy ) || ! taxonomy_exists( $taxonomy ) || ! is_array( $config ) ) {
				continue;
			}

			if ( empty( $taxonomy_terms[ $taxonomy ] ) ) {
				$warnings[] = sprintf(
					/* translators: %s: taxonomy name. */
					__( 'The %s taxonomy has no terms available to map.', 'progress-agentic-rag' ),
					$taxonomy
				);
			}

			$labelset = isset( $config['labelset'] ) ? (string) $config['labelset'] : '';
			if ( '' !== $labelset && ! empty( $labelsets ) && ! in_array( $labelset, $labelsets, true ) ) {
				$warnings[] = sprintf(
					/* translators: %s: labelset name. */
					__( 'Mapped labelset "%s" was not found upstream.', 'progress-agentic-rag' ),
					$labelset
				);
			}

			$terms = is_array( $config['terms'] ?? null ) ? $config['terms'] : [];
			foreach ( $terms as $labels ) {
				$labels = is_array( $labels ) ? $labels : [ $labels ];
				foreach ( $labels as $label ) {
					if ( '' !== $labelset && ! empty( $labelset_labels[ $labelset ] ) && ! in_array( (string) $label, $labelset_labels[ $labelset ], true ) ) {
						$warnings[] = sprintf(
							/* translators: 1: label name, 2: labelset name. */
							__( 'Mapped label "%1$s" was not found in labelset "%2$s".', 'progress-agentic-rag' ),
							(string) $label,
							$labelset
						);
					}
				}
			}

			$fallback = is_array( $config['fallback'] ?? null ) ? $config['fallback'] : [];
			$fallback_labelset = isset( $fallback['labelset'] ) ? (string) $fallback['labelset'] : '';
			if ( '' !== $fallback_labelset && ! empty( $labelsets ) && ! in_array( $fallback_labelset, $labelsets, true ) ) {
				$warnings[] = sprintf(
					/* translators: %s: fallback labelset name. */
					__( 'Fallback labelset "%s" was not found upstream.', 'progress-agentic-rag' ),
					$fallback_labelset
				);
			}
		}

		if ( $synced_count > 0 && ! empty( $mapping ) ) {
			$warnings[] = __( 'Run label reprocessing after saving taxonomy mapping changes so existing synced resources receive the latest labels.', 'progress-agentic-rag' );
		}

		return array_values( array_unique( $warnings ) );
	}

	private function post_type_label( string $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );

		return null !== $post_type_object && isset( $post_type_object->labels->name ) ? (string) $post_type_object->labels->name : $post_type;
	}

	private function history_type_label( string $type ): string {
		$labels = [
			'manual_sync'     => __( 'Manual sync', 'progress-agentic-rag' ),
			'automatic_sync'  => __( 'Automatic sync', 'progress-agentic-rag' ),
			'delete_synced'   => __( 'Synced resource delete', 'progress-agentic-rag' ),
			'label_reprocess' => __( 'Label reprocessing', 'progress-agentic-rag' ),
		];

		return $labels[ $type ] ?? $type;
	}

	private function history_status_label( string $status ): string {
		$labels = [
			'complete'  => __( 'Complete', 'progress-agentic-rag' ),
			'failed'    => __( 'Finished with failures', 'progress-agentic-rag' ),
			'scheduled' => __( 'Scheduled', 'progress-agentic-rag' ),
			'cancelled' => __( 'Cancelled', 'progress-agentic-rag' ),
		];

		return $labels[ $status ] ?? $status;
	}

	private function format_timestamp( int $timestamp ): string {
		if ( $timestamp <= 0 ) {
			return __( 'Not recorded', 'progress-agentic-rag' );
		}

		if ( function_exists( 'date_i18n' ) ) {
			return date_i18n( get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ), $timestamp );
		}

		return gmdate( 'Y-m-d H:i', $timestamp );
	}

	private function redact_value( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( strlen( $value ) <= 8 ) {
			return substr( $value, 0, 2 ) . '...';
		}

		return substr( $value, 0, 4 ) . '...' . substr( $value, -4 );
	}

	private function normalize_zone_value( string $zone ): string {
		$zone = strtolower( trim( $zone ) );
		$host = (string) wp_parse_url( $zone, PHP_URL_HOST );

		if ( '' === $host ) {
			$host = (string) strtok( $zone, '/?#' );
		}

		$host = (string) preg_replace( '/:\d+$/', '', $host );
		if ( str_ends_with( $host, '.rag.progress.cloud' ) ) {
			$host = substr( $host, 0, -strlen( '.rag.progress.cloud' ) );
		}

		return $host;
	}

	private function normalize_kbid_value( string $kbid ): string {
		$kbid = trim( $kbid );

		if ( 1 === preg_match( '~/kb/([^/?#]+)~', $kbid, $matches ) ) {
			return $matches[1];
		}

		return trim( $kbid, " \t\n\r\0\x0B/" );
	}

	private function normalize_token_value( string $token ): string {
		$token = trim( $token );

		if ( 1 === preg_match( '/^Bearer\s+/i', $token ) ) {
			$token = trim( (string) preg_replace( '/^Bearer\s+/i', '', $token, 1 ) );
		}

		return $token;
	}

	private function connection_status_label(): string {
		if ( $this->settings->get_api_is_reachable() ) {
			return __( 'Connected', 'progress-agentic-rag' );
		}

		if ( '' === $this->settings->get_string( SettingsRepository::OPTION_ZONE ) || '' === $this->settings->get_string( SettingsRepository::OPTION_KBID ) || ! $this->settings->has_token() ) {
			return __( 'Not configured', 'progress-agentic-rag' );
		}

		return __( 'Stored, unverified', 'progress-agentic-rag' );
	}

	private function validate_connection_settings(): bool {
		$zone       = $this->settings->get_string( SettingsRepository::OPTION_ZONE );
		$kbid       = $this->settings->get_string( SettingsRepository::OPTION_KBID );
		$token      = $this->settings->get_string( SettingsRepository::OPTION_TOKEN );

		return (bool) $this->connection_test_result( $zone, $kbid, $token )['connected'];
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
