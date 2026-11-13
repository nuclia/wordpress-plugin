<?php
/**
 * Isolated scaling environment only. Never install on a real WordPress site.
 */

use ProgressAgenticRag\Api\ApiClient;
use ProgressAgenticRag\Indexing\ManualSync;
use ProgressAgenticRag\Settings\SettingsRepository;

if ( ! defined( 'PROGRESS_AGENTIC_RAG_SCALING_TESTS' ) || ! PROGRESS_AGENTIC_RAG_SCALING_TESTS ) {
	return;
}

const RAG_SCALE_STATE = 'rag_scale_state';
const RAG_SCALE_LOG = 'rag_scale_log';
const RAG_SCALE_TYPE = 'rag_scale';
const RAG_SCALE_SIZE = 10000;
const RAG_SCALE_REQUEST_BUDGET = 45;

add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_action( 'init', static function (): void {
	register_post_type( RAG_SCALE_TYPE, [ 'public' => true, 'label' => 'Scaling fixtures' ] );
} );
add_filter( 'pre_http_request', 'rag_scale_http', PHP_INT_MAX, 3 );
add_filter( 'query', static function ( string $sql ): string {
	if ( str_contains( $sql, 'actionscheduler_actions' ) && preg_match( '/^\s*SELECT\b/i', $sql ) && ! preg_match( '/\bCOUNT\s*\(/i', $sql ) ) {
		$GLOBALS['rag_scale_queue_reads'] = ( $GLOBALS['rag_scale_queue_reads'] ?? 0 ) + 1;
	}
	return $sql;
} );

add_action( 'rest_api_init', static function (): void {
	foreach ( [ 'seed', 'clear', 'metrics', 'lookup', 'run-one', 'probe', 'interrupt', 'replay' ] as $operation ) {
		register_rest_route( 'rag-scaling/v1', '/' . $operation, [
			'methods'             => 'POST',
			'callback'            => 'rag_scale_' . str_replace( '-', '_', $operation ),
			'permission_callback' => static function ( WP_REST_Request $request ): bool {
				$key = (string) get_option( 'progress_agentic_rag_e2e_key', '' );
				return '' !== $key && hash_equals( $key, (string) $request->get_header( 'x-rag-scaling-key' ) );
			},
		] );
	}
} );

add_action( 'shutdown', static function (): void {
	if ( empty( $_POST['action'] ) || ! str_starts_with( (string) $_POST['action'], 'progress_agentic_rag_' ) ) {
		return;
	}
	update_option( 'rag_scale_last_ajax', [
		'action'      => sanitize_key( $_POST['action'] ),
		'queue_reads' => $GLOBALS['rag_scale_queue_reads'] ?? 0,
	], false );
} );

function rag_scale_services(): array {
	$settings = new SettingsRepository();
	$api      = new ApiClient( $settings );
	return [ $api, new ManualSync( $settings, $api ) ];
}

function rag_scale_clear(): array {
	update_option( RAG_SCALE_LOG, [], false );
	delete_option( 'rag_scale_last_ajax' );
	return [ 'ok' => true ];
}

function rag_scale_seed( WP_REST_Request $request ): array {
	global $wpdb;
	$sync_table = $wpdb->prefix . SettingsRepository::SYNC_TABLE_NAME;

	// All rows in this database are disposable: the compose project has its own volumes.
	$wpdb->query( "DELETE FROM {$wpdb->prefix}actionscheduler_logs" );
	$wpdb->query( "DELETE FROM {$wpdb->prefix}actionscheduler_actions" );
	$wpdb->query( "DELETE FROM {$sync_table}" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}agentic_rag_preparation" );
	wp_clear_scheduled_hook( 'progress_agentic_rag_recover_manual_sync' );
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID", RAG_SCALE_TYPE ) );
	if ( count( $ids ) !== RAG_SCALE_SIZE ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type = %s", RAG_SCALE_TYPE ) );
		for ( $offset = 0; $offset < RAG_SCALE_SIZE; $offset += 250 ) {
			$rows = [];
			for ( $i = $offset; $i < $offset + 250; $i++ ) {
				$rows[] = $wpdb->prepare(
					"(%s, 'publish', %s, %s, '', '', '', '', '')",
					RAG_SCALE_TYPE,
					'Scale item ' . $i,
					'scale-item-' . $i
				);
			}
			$wpdb->query( "INSERT INTO {$wpdb->posts} (post_type, post_status, post_title, post_name, post_content, post_excerpt, to_ping, pinged, post_content_filtered) VALUES " . implode( ',', $rows ) );
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID", RAG_SCALE_TYPE ) );
	}
	$ids = array_map( 'intval', $ids );
	if ( RAG_SCALE_SIZE !== count( $ids ) ) {
		throw new RuntimeException( 'Could not seed 10,000 scaling posts: ' . $wpdb->last_error );
	}

	$mapped = max( 0, min( RAG_SCALE_SIZE, (int) ( $request['mapped'] ?? 9000 ) ) );
	for ( $offset = 0; $offset < $mapped; $offset += 250 ) {
		$rows = [];
		foreach ( array_slice( $ids, $offset, min( 250, $mapped - $offset ) ) as $id ) {
			$rows[] = $wpdb->prepare( '(%d, %s, %s)', $id, 'scale-rid-' . $id, '0' );
		}
		$wpdb->query( "INSERT INTO {$sync_table} (post_id, nuclia_rid, nuclia_seqid) VALUES " . implode( ',', $rows ) );
	}
	if ( $mapped !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sync_table}" ) ) {
		throw new RuntimeException( 'Could not seed sync mappings: ' . $wpdb->last_error );
	}
	update_option( 'nuclia_zone', 'scaling' );
	update_option( 'nuclia_kbid', 'scaling-kb' );
	update_option( 'nuclia_token', 'fake-scaling-token' );
	update_option( 'nuclia_api_is_reachable', 'yes' );
	update_option( 'nuclia_indexable_post_types', [ RAG_SCALE_TYPE => true ] );
	foreach ( [ 'manual_sync_state', 'background_sync_state', 'delete_sync_state', 'sync_history', 'failed_sync_items' ] as $option ) {
		delete_option( 'progress_agentic_rag_' . $option );
	}
	update_option( RAG_SCALE_STATE, [
		'ids'        => $ids,
		'delay_ms'   => min( 2000, max( 0, (int) ( $request['delay_ms'] ?? 0 ) ) ),
		'mode'       => (string) ( $request['mode'] ?? 'normal' ),
		'upstream'   => (int) ( $request['upstream'] ?? RAG_SCALE_SIZE ),
	], false );
	rag_scale_clear();

	if ( ! empty( $request['queue'] ) ) {
		$template_id = as_schedule_single_action( time(), 'progress_agentic_rag_background_sync_post', [], 'progress-agentic-rag-background' );
		$template = $wpdb->get_row( $wpdb->prepare( "SELECT schedule, group_id FROM {$wpdb->prefix}actionscheduler_actions WHERE action_id = %d", $template_id ) );
		for ( $offset = 0; $offset < RAG_SCALE_SIZE; $offset += 250 ) {
			$rows = [];
			foreach ( array_slice( $ids, $offset, 250 ) as $id ) {
				$rows[] = $wpdb->prepare( '(%s, %s, %s, %s, %d)', 'progress_agentic_rag_background_sync_post', 'complete', wp_json_encode( [
					'post_id' => $id, 'post_type' => RAG_SCALE_TYPE, 'background_id' => 'scale-active',
				] ), $template->schedule, $template->group_id );
			}
			$wpdb->query( "INSERT INTO {$wpdb->prefix}actionscheduler_actions (hook, status, args, schedule, group_id) VALUES " . implode( ',', $rows ) );
		}
		update_option( 'progress_agentic_rag_background_sync_state', [
			'id' => 'scale-active', 'status' => 'running', 'total' => RAG_SCALE_SIZE + 1, 'completed' => RAG_SCALE_SIZE, 'failed' => 0,
		] );
	}

	return [ 'posts' => count( $ids ), 'mapped' => $mapped ];
}

function rag_scale_metrics(): array {
	global $wpdb;
	$sync_table = $wpdb->prefix . SettingsRepository::SYNC_TABLE_NAME;
	return [
		'requests' => get_option( RAG_SCALE_LOG, [] ),
		'ajax'     => get_option( 'rag_scale_last_ajax', [] ),
		'pending'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'pending' AND hook LIKE 'progress_agentic_rag_%'" ),
		'mapped'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sync_table}" ),
		'staged'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}agentic_rag_preparation" ),
	];
}

function rag_scale_lookup(): array {
	[ $api ] = rag_scale_services();
	$state = get_option( RAG_SCALE_STATE );
	$method = new ReflectionMethod( $api, 'find_resource_by_slug' );
	$result = $method->invoke( $api, (string) $state['ids'][8999] );
	return [
		'ok'    => ! is_wp_error( $result ),
		'rid'   => is_wp_error( $result ) ? '' : $result['rid'],
		'error' => is_wp_error( $result ) ? $result->get_error_message() : '',
	];
}

function rag_scale_probe( WP_REST_Request $request ): array {
	$response = wp_remote_get( 'https://scaling.rag.progress.cloud/api/v1/kb/scaling-kb/resources?' . (string) $request['query'] );
	return is_wp_error( $response ) ? [ 'error' => $response->get_error_message() ] : json_decode( wp_remote_retrieve_body( $response ), true );
}

function rag_scale_run_one( WP_REST_Request $request ): array {
	global $wpdb;
	$store = ActionScheduler::store();
	$id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT action_id FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'pending' AND hook LIKE %s ORDER BY action_id LIMIT 1",
		$wpdb->esc_like( 'progress_agentic_rag_' ) . '%'
	) );
	if ( 0 === $id ) {
		return [ 'ran' => false ];
	}
	if ( ! empty( $request['preparation'] ) ) {
		$id = (int) $wpdb->get_var( "SELECT action_id FROM {$wpdb->prefix}actionscheduler_actions WHERE status = 'pending' AND hook = 'progress_agentic_rag_prepare_manual_sync' ORDER BY action_id LIMIT 1" );
		if ( 0 === $id ) {
			return [ 'ran' => false ];
		}
	}
	$action = $store->fetch_action( $id );
	ActionScheduler::runner()->process_action( $id, 'scaling-test' );
	[ , $sync ] = rag_scale_services();
	return [
		'ran'    => true,
		'hook'   => $action->get_hook(),
		'args'   => $action->get_args(),
		'yielded' => apply_filters( 'action_scheduler_maximum_execution_time_likely_to_be_exceeded', false ),
		'status' => $store->get_status( $id ),
		'manual' => $sync->status(),
	];
}

function rag_scale_interrupt(): array {
	$before = get_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE );
	as_unschedule_all_actions( 'progress_agentic_rag_prepare_manual_sync', [
		'sync_id' => $before['id'], 'step' => $before['step'],
	], 'progress-agentic-rag-indexing' );
	do_action( 'progress_agentic_rag_recover_manual_sync' );
	$after = get_option( SettingsRepository::OPTION_MANUAL_SYNC_STATE );
	return [
		'before' => [ 'step' => $before['step'], 'page' => $before['page'] ],
		'after' => [ 'step' => $after['step'], 'page' => $after['page'] ],
		'next_attempt' => $after['next_attempt'] ?? 0,
		'recovery_scheduled' => (bool) wp_next_scheduled( 'progress_agentic_rag_recover_manual_sync' ),
	];
}

function rag_scale_replay( WP_REST_Request $request ): array {
	$args = $request['args'];
	if ( isset( $args['post_id'] ) ) {
		do_action( 'progress_agentic_rag_manual_sync_post', (int) $args['post_id'], (string) $args['post_type'], (string) $args['sync_id'] );
	} else {
		do_action( 'progress_agentic_rag_prepare_manual_sync', (string) $args['sync_id'], (int) $args['step'] );
	}
	[ , $sync ] = rag_scale_services();
	return $sync->status();
}

function rag_scale_response( int $code, array $body ): array {
	return [
		'headers'  => [ 'Content-Type' => 'application/json', 'Retry-After' => '60' ],
		'response' => [ 'code' => $code, 'message' => 'Scaling fixture' ],
		'body'     => wp_json_encode( $body ),
		'cookies'  => [],
	];
}

function rag_scale_http( mixed $preempt, array $args, string $url ): mixed {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( ! str_ends_with( $host, '.rag.progress.cloud' ) ) {
		return $preempt;
	}
	// No request from this test environment is allowed to reach a real KB.
	$state = get_option( RAG_SCALE_STATE, [] );
	if ( empty( $state['ids'] ) ) {
		return new WP_Error( 'scaling_not_seeded', 'Fake KB has not been seeded.' );
	}
	static $calls = 0;
	static $request_id = null;
	$request_id ??= wp_generate_uuid4();
	$calls++;
	$log = get_option( RAG_SCALE_LOG, [] );
	$log[] = [
		'request' => $request_id,
		'action'  => sanitize_key( $_POST['action'] ?? 'fixture' ),
		'method'  => $args['method'] ?? 'GET',
		'url'     => $url,
	];
	update_option( RAG_SCALE_LOG, array_slice( $log, -500 ), false );
	if ( $calls > RAG_SCALE_REQUEST_BUDGET ) {
		return new WP_Error( 'scaling_request_budget', 'Fake KB stopped a runaway request after 45 API calls.' );
	}
	if ( 'rate-limit' === $state['mode'] ) {
		return rag_scale_response( 429, [ 'detail' => [ 'period' => 'minute', 'limit' => 2400 ] ] );
	}
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( preg_match( '#/slug/(\d+)$#', $path, $slug ) ) {
		$known = in_array( (int) $slug[1], array_slice( $state['ids'], 0, $state['upstream'] ), true );
		return $known
			? rag_scale_response( 200, [ 'id' => 'scale-rid-' . $slug[1], 'slug' => $slug[1], 'last_seqid' => 0 ] )
			: rag_scale_response( 404, [ 'detail' => 'not found' ] );
	}
	if ( str_ends_with( $path, '/resources' ) && 'GET' === ( $args['method'] ?? 'GET' ) ) {
		usleep( $state['delay_ms'] * 1000 );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$page = 'repeat-page' === $state['mode'] ? 0 : max( 0, (int) ( $query['page'] ?? 0 ) );
		$size = max( 1, min( 250, (int) ( $query['size'] ?? 250 ) ) );
		$resources = [];
		for ( $i = $page * $size; $i < min( $state['upstream'], ( $page + 1 ) * $size ); $i++ ) {
			$id = $state['ids'][ $i ];
			$resources[] = [ 'id' => 'scale-rid-' . $id, 'slug' => (string) $id, 'last_seqid' => 0 ];
		}
		return rag_scale_response( 200, [
			'resources' => $resources,
			'pagination' => [ 'page' => $page, 'size' => $size, 'last' => ( $page + 1 ) * $size >= $state['upstream'] ],
		] );
	}
	if ( str_ends_with( $path, '/resources' ) && 'POST' === ( $args['method'] ?? '' ) ) {
		$body = json_decode( (string) ( $args['body'] ?? '' ), true );
		return rag_scale_response( 201, [ 'uuid' => 'scale-rid-' . $body['slug'], 'seqid' => 0 ] );
	}
	return rag_scale_response( 200, [ 'labelsets' => [] ] );
}
