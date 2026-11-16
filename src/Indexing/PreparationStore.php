<?php
/**
 * Durable staging for resumable manual sync.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Indexing;

use ProgressAgenticRag\Settings\SettingsRepository;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

// All SQL runs through execute(), rows() and value(), the only places that call $wpdb directly. Table names are bound with the %i placeholder.
class PreparationStore {
	public const TABLE = 'agentic_rag_preparation';

	public function lock( int $wait = 0 ): bool {
		global $wpdb;
		$name = 'rag-preparation-' . md5( ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . $wpdb->prefix );
		$locked = '1' === (string) $this->value( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, max( 0, $wait ) ) );
		if ( $locked && function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( SettingsRepository::OPTION_MANUAL_SYNC_STATE, 'options' );
		}
		return $locked;
	}

	public function unlock(): void {
		global $wpdb;
		$name = 'rag-preparation-' . md5( ( defined( 'DB_NAME' ) ? DB_NAME : '' ) . $wpdb->prefix );
		$this->value( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
	}

	public function initialize(): void {
		global $wpdb;
		$table = $this->table();
		$charset = $wpdb->get_charset_collate();
		$this->query( $wpdb->prepare( "CREATE TABLE IF NOT EXISTS %i (
			run_id varchar(36) NOT NULL,
			post_id bigint unsigned NOT NULL,
			original_id bigint unsigned NOT NULL DEFAULT 0,
			original_rid varchar(255) NOT NULL DEFAULT '',
			original_seqid varchar(255) DEFAULT NULL,
			upstream_rid varchar(255) NOT NULL DEFAULT '',
			upstream_seqid varchar(255) NOT NULL DEFAULT '',
			queued tinyint NOT NULL DEFAULT 0,
			attempts tinyint unsigned NOT NULL DEFAULT 0,
			outcome varchar(16) NOT NULL DEFAULT '',
			recovered tinyint NOT NULL DEFAULT 0,
			created_at bigint unsigned NOT NULL,
			PRIMARY KEY (run_id, post_id),
			KEY cleanup (created_at)
		) ", $table ) . $charset );
		$this->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %d LIMIT 1000', $table, time() - WEEK_IN_SECONDS ) );
	}

	// Best effort: a failed purge must not break finishing the run; initialize() trims leftovers.
	public function purge( string $run ): void {
		global $wpdb;
		$table = $this->table();
		for ( $i = 0; $i < 20; $i++ ) {
			$deleted = $this->execute( $wpdb->prepare( 'DELETE FROM %i WHERE run_id = %s LIMIT 5000', $table, $run ) );
			if ( ! is_int( $deleted ) || $deleted < 5000 ) {
				return;
			}
		}
	}

	public function bounds(): array {
		global $wpdb;
		return [
			'mapping_limit' => (int) $this->value( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $this->sync_table() ) ),
			'post_limit'    => (int) $this->value( $wpdb->prepare( 'SELECT MAX(ID) FROM %i', $wpdb->posts ) ),
		];
	}

	public function snapshot_mappings( string $run, int $cursor, int $limit ): array {
		global $wpdb;
		$rows = $this->rows( $wpdb->prepare(
			"SELECT id, post_id, nuclia_rid, nuclia_seqid FROM %i
			 WHERE id > %d AND id <= %d AND post_id > 0 AND nuclia_rid != ''
			 ORDER BY id LIMIT 1000", $this->sync_table(), $cursor, $limit
		) );
		$values = [];
		foreach ( $rows as $row ) {
			$values[] = $this->stage_values( $run, (int) $row->post_id, (int) $row->id, $row->nuclia_rid, $row->nuclia_seqid );
			$cursor = (int) $row->id;
		}
		$this->stage( $values );
		return [ 'cursor' => $cursor, 'done' => count( $rows ) < 1000 ];
	}

	public function snapshot_candidates( string $run, int $cursor, int $limit, array $types ): array {
		global $wpdb;
		if ( empty( $types ) ) {
			return [ 'cursor' => $cursor, 'done' => true ];
		}
		$in = $this->placeholders( $types );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in is only a list of %s placeholders sized to $types; every value is bound.
		$rows = $this->rows( $wpdb->prepare(
			"SELECT p.ID FROM %i p LEFT JOIN %i idx ON idx.post_id = p.ID
			 WHERE p.ID > %d AND p.ID <= %d AND idx.post_id IS NULL
			   AND p.post_type IN ({$in}) AND p.post_password = ''
			   AND ((p.post_type = 'attachment' AND p.post_status = 'inherit')
			        OR (p.post_type != 'attachment' AND p.post_status = 'publish'))
			 ORDER BY p.ID LIMIT 1000", $wpdb->posts, $this->sync_table(), $cursor, $limit, ...$types
		) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$values = [];
		foreach ( $rows as $row ) {
			$values[] = $this->stage_values( $run, (int) $row->ID );
			$cursor = (int) $row->ID;
		}
		$this->stage( $values );
		return [ 'cursor' => $cursor, 'done' => count( $rows ) < 1000 ];
	}

	private function stage_values( string $run, int $id, int $original = 0, string $rid = '', ?string $seqid = null ): string {
		global $wpdb;
		return $wpdb->prepare( "(%s, %d, %d, %s, NULLIF(%s, ''), %d)", $run, $id, $original, $rid, $seqid ?? '', time() );
	}

	private function stage( array $values ): void {
		global $wpdb;
		if ( empty( $values ) ) {
			return;
		}
		$table = $this->table();
		$this->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (run_id, post_id, original_id, original_rid, original_seqid, created_at) VALUES ', $table ) . implode( ',', $values ) );
	}

	public function record_page( string $run, array $resources ): void {
		global $wpdb;
		$table = $this->table();
		foreach ( $resources as $resource ) {
			$slug = (string) $resource['slug'];
			if ( (string) (int) $slug !== $slug || (int) $slug <= 0 ) {
				continue;
			}
			$this->query( $wpdb->prepare(
				'UPDATE %i SET upstream_rid = %s, upstream_seqid = %s WHERE run_id = %s AND post_id = %d',
				$table, $resource['rid'], $resource['seqid'], $run, (int) $slug
			) );
		}
	}

	// Mappings the KB listing did not return; each one is confirmed individually before apply_page() may delete it.
	public function unseen( string $run, int $cursor, int $limit ): array {
		global $wpdb;
		return $this->rows( $wpdb->prepare(
			"SELECT post_id FROM %i WHERE run_id = %s AND original_id > 0 AND upstream_rid = '' AND post_id > %d ORDER BY post_id LIMIT %d",
			$this->table(), $run, $cursor, $limit
		) );
	}

	public function apply_page( string $run, int $cursor, array $types ): array {
		global $wpdb;
		$table = $this->table();
		$sync = $this->sync_table();
		$rows = $this->rows( $wpdb->prepare(
			'SELECT post_id FROM %i WHERE run_id = %s AND post_id > %d ORDER BY post_id LIMIT 1000', $table, $run, $cursor
		) );
		if ( empty( $rows ) ) {
			return [ 'cursor' => $cursor, 'done' => true ];
		}
		$next = (int) end( $rows )->post_id;
		// The join compares the entire original mapping so newer background-sync results are never overwritten.
		$this->query( $wpdb->prepare(
			"DELETE idx FROM %i idx INNER JOIN %i s ON idx.id = s.original_id AND idx.post_id = s.post_id AND idx.nuclia_rid = s.original_rid
			   AND COALESCE(idx.nuclia_seqid, '') = COALESCE(s.original_seqid, '')
			 WHERE s.run_id = %s AND s.post_id > %d AND s.post_id <= %d AND s.upstream_rid = ''",
			$sync, $table, $run, $cursor, $next
		) );
		$this->query( $wpdb->prepare(
			"UPDATE %i idx INNER JOIN %i s ON idx.id = s.original_id AND idx.post_id = s.post_id AND idx.nuclia_rid = s.original_rid
			   AND COALESCE(idx.nuclia_seqid, '') = COALESCE(s.original_seqid, '')
			 SET idx.nuclia_rid = s.upstream_rid, idx.nuclia_seqid = IF(s.upstream_seqid != '', s.upstream_seqid, COALESCE(s.original_seqid, ''))
			 WHERE s.run_id = %s AND s.post_id > %d AND s.post_id <= %d AND s.upstream_rid != ''",
			$sync, $table, $run, $cursor, $next
		) );
		if ( ! empty( $types ) ) {
			$in = $this->placeholders( $types );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in is only a list of %s placeholders sized to $types; every value is bound.
			$this->query( $wpdb->prepare(
				"INSERT INTO %i (post_id, nuclia_rid, nuclia_seqid)
				SELECT s.post_id, s.upstream_rid, s.upstream_seqid FROM %i s
				INNER JOIN %i p ON p.ID = s.post_id LEFT JOIN %i idx ON idx.post_id = s.post_id
				WHERE s.run_id = %s AND s.post_id > %d AND s.post_id <= %d AND s.original_id = 0 AND s.upstream_rid != '' AND idx.post_id IS NULL
				AND p.post_type IN ({$in}) AND p.post_password = ''
				AND ((p.post_type = 'attachment' AND p.post_status = 'inherit')
				     OR (p.post_type != 'attachment' AND p.post_status = 'publish'))",
				$sync, $table, $wpdb->posts, $sync, $run, $cursor, $next, ...$types
			) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$this->query( $wpdb->prepare(
				'UPDATE %i s INNER JOIN %i idx ON idx.post_id = s.post_id AND idx.nuclia_rid = s.upstream_rid
				SET s.recovered = 1 WHERE s.run_id = %s AND s.post_id > %d AND s.post_id <= %d AND s.original_id = 0',
				$table, $sync, $run, $cursor, $next
			) );
		}
		return [ 'cursor' => $next, 'done' => count( $rows ) < 1000 ];
	}

	public function candidates( string $run, int $cursor, array $types ): array {
		global $wpdb;
		if ( empty( $types ) ) {
			return [];
		}
		$in = $this->placeholders( $types );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in is only a list of %s placeholders sized to $types; every value is bound.
		return $this->rows( $wpdb->prepare(
			"SELECT s.post_id, p.post_type FROM %i s
			 INNER JOIN %i p ON p.ID = s.post_id
			 LEFT JOIN %i idx ON idx.post_id = s.post_id
			 WHERE s.run_id = %s AND s.post_id > %d AND idx.post_id IS NULL
			   AND p.post_type IN ({$in}) AND p.post_password = ''
			   AND ((p.post_type = 'attachment' AND p.post_status = 'inherit')
			        OR (p.post_type != 'attachment' AND p.post_status = 'publish'))
			 ORDER BY s.post_id LIMIT 100", $this->table(), $wpdb->posts, $this->sync_table(), $run, $cursor, ...$types
		) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	public function mark_queued( string $run, int $id ): void {
		global $wpdb;
		$this->query( $wpdb->prepare( 'UPDATE %i SET queued = 1 WHERE run_id = %s AND post_id = %d', $this->table(), $run, $id ) );
	}

	public function outcome( string $run, int $id ): ?string {
		global $wpdb;
		$value = $this->value( $wpdb->prepare(
			"SELECT IF(outcome = '', 'pending', outcome) FROM %i WHERE run_id = %s AND post_id = %d AND queued = 1", $this->table(), $run, $id
		) );
		// A missing row and an empty SQL string both yield null.
		return 'pending' === $value ? '' : ( null === $value ? null : (string) $value );
	}

	public function finish_item( string $run, int $id, bool $success ): void {
		global $wpdb;
		$this->query( $wpdb->prepare(
			"UPDATE %i SET outcome = %s WHERE run_id = %s AND post_id = %d AND outcome = ''",
			$this->table(), $success ? 'complete' : 'failed', $run, $id
		) );
	}

	public function begin_item( string $run, int $id ): bool {
		global $wpdb;
		return $this->query( $wpdb->prepare(
			"UPDATE %i SET attempts = attempts + 1
			 WHERE run_id = %s AND post_id = %d AND queued = 1 AND outcome = '' AND attempts < 3", $this->table(), $run, $id
		) ) > 0;
	}

	public function stats( string $run ): array {
		global $wpdb;
		$rows = $this->rows( $wpdb->prepare(
			"SELECT COALESCE(SUM(queued),0) total,
			        COALESCE(SUM(queued = 1 AND outcome = 'complete'),0) completed,
			        COALESCE(SUM(queued = 1 AND outcome = 'failed'),0) failed,
			        COALESCE(SUM(recovered),0) recovered
			 FROM %i WHERE run_id = %s", $this->table(), $run
		) );
		return array_map( 'intval', (array) ( $rows[0] ?? (object) [ 'total' => 0, 'completed' => 0, 'failed' => 0, 'recovered' => 0 ] ) );
	}

	public function unfinished( string $run ): array {
		global $wpdb;
		return $this->rows( $wpdb->prepare(
			"SELECT s.post_id, COALESCE(p.post_type, '') post_type FROM %i s LEFT JOIN %i p ON p.ID = s.post_id
			 WHERE run_id = %s AND queued = 1 AND outcome = '' ORDER BY s.post_id LIMIT 100", $this->table(), $wpdb->posts, $run
		) );
	}

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	private function sync_table(): string {
		global $wpdb;
		return $wpdb->prefix . SettingsRepository::SYNC_TABLE_NAME;
	}

	private function placeholders( array $values ): string {
		return implode( ',', array_fill( 0, count( $values ), '%s' ) );
	}

	// $sql must already be built with $wpdb->prepare().
	private function execute( string $sql ): int|bool {
		global $wpdb;
		return $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned staging table; caching would return stale run state.
	}

	private function value( string $sql ): ?string {
		global $wpdb;
		return $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned staging table; caching would return stale run state.
	}

	private function query( string $sql ): int {
		$result = $this->execute( $sql );
		if ( false === $result ) {
			throw new RuntimeException( esc_html__( 'Could not save sync preparation. Retry after checking the WordPress database.', 'progress-agentic-rag-connector' ) );
		}
		return (int) $result;
	}

	private function rows( string $sql ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned staging table; caching would return stale run state.
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
			throw new RuntimeException( esc_html__( 'Could not read sync preparation. Retry after checking the WordPress database.', 'progress-agentic-rag-connector' ) );
		}
		return $rows;
	}
}
