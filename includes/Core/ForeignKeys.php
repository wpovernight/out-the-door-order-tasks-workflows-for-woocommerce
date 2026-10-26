<?php

namespace WPO\OTD\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the foreign key constraints dbDelta() cannot, each deleting its child rows with the parent.
 */
final class ForeignKeys {
	/**
	 * Add each foreign key that is not in place yet.
	 *
	 * A key whose tables are missing or cannot be converted to InnoDB is not attempted, since a
	 * non-InnoDB table accepts the constraint and silently ignores it.
	 *
	 * @param array<int, array{table: string, column: string, parent_table: string, parent_column: string}> $foreign_keys Unprefixed table names.
	 *
	 * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string, error: string}> The keys still missing, each with the database error it ended on ('' when none was reported).
	 */
	public static function apply( array $foreign_keys ): array {
		global $wpdb;

		$missing = array();

		foreach ( $foreign_keys as $foreign_key ) {
			$table         = $wpdb->prefix . $foreign_key['table'];
			$parent_table  = $wpdb->prefix . $foreign_key['parent_table'];
			$column        = $foreign_key['column'];
			$parent_column = $foreign_key['parent_column'];
			$constraint    = $table . '_' . $column . '_fk';

			if ( self::exists( $table, $column, $parent_table, $parent_column ) ) {
				continue;
			}

			if ( ! self::ensure_innodb( $table ) || ! self::ensure_innodb( $parent_table ) ) {
				$missing[] = $foreign_key + array( 'error' => (string) $wpdb->last_error );
				continue;
			}

			// Rows orphaned while the constraint was absent would reject it.
			self::delete_orphans( $table, $column, $parent_table, $parent_column );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Install-time schema change; every identifier is plugin-owned and table/column names cannot be bound as prepared-statement placeholders.
			$wpdb->query(
				"ALTER TABLE `{$table}`
				ADD CONSTRAINT `{$constraint}`
					FOREIGN KEY (`{$column}`)
					REFERENCES `{$parent_table}` (`{$parent_column}`)
					ON DELETE CASCADE"
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

			// Read the error before the check below runs a query of its own.
			$error = (string) $wpdb->last_error;

			if ( ! self::exists( $table, $column, $parent_table, $parent_column ) ) {
				$missing[] = $foreign_key + array( 'error' => $error );
			}
		}

		return $missing;
	}

	/**
	 * Ensure a table uses InnoDB, converting it if it does not.
	 *
	 * @param string $table Full table name.
	 *
	 * @return bool False when the table is missing or the conversion failed.
	 */
	public static function ensure_innodb( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema introspection.
		$engine = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$table
			)
		);

		// Missing, or the lookup failed.
		if ( null === $engine ) {
			return false;
		}

		if ( 0 === strcasecmp( (string) $engine, 'InnoDB' ) ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Install-time schema change; $table is plugin-owned and cannot be bound as a prepared-statement placeholder.
		return false !== $wpdb->query( "ALTER TABLE `{$table}` ENGINE=InnoDB" );
	}

	/**
	 * Check whether a column already has a foreign key to the given parent.
	 *
	 * @param string $table         Full child table name.
	 * @param string $column        Child column holding the reference.
	 * @param string $parent_table  Full parent table name.
	 * @param string $parent_column Referenced parent column.
	 *
	 * @return bool
	 */
	private static function exists( string $table, string $column, string $parent_table, string $parent_column ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Install-time schema introspection.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
				WHERE CONSTRAINT_SCHEMA = DATABASE()
				AND TABLE_NAME = %s
				AND COLUMN_NAME = %s
				AND REFERENCED_TABLE_NAME = %s
				AND REFERENCED_COLUMN_NAME = %s',
				$table,
				$column,
				$parent_table,
				$parent_column
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Delete child rows whose parent no longer exists.
	 *
	 * @param string $table         Full child table name.
	 * @param string $column        Child column holding the reference.
	 * @param string $parent_table  Full parent table name.
	 * @param string $parent_column Referenced parent column.
	 *
	 * @return void
	 */
	private static function delete_orphans( string $table, string $column, string $parent_table, string $parent_column ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Install-time cleanup; every identifier is plugin-owned and cannot be bound as a prepared-statement placeholder.
		$wpdb->query(
			"DELETE child FROM `{$table}` AS child
			LEFT JOIN `{$parent_table}` AS parent ON child.`{$column}` = parent.`{$parent_column}`
			WHERE parent.`{$parent_column}` IS NULL"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}
}
