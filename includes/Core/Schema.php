<?php

namespace WPO\OTD\Core;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's database tables and the foreign keys between them.
 */
final class Schema {
	/**
	 * The CREATE TABLE statements for dbDelta().
	 *
	 * Foreign keys are deliberately absent since dbDelta cannot parse them correctly.
	 * They are listed by foreign_keys() and applied separately.
	 *
	 * @return string
	 */
	public static function tables(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return "
		CREATE TABLE `{$wpdb->prefix}wpo_otd_tasks` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL,
			description TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) ENGINE=InnoDB {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_otd_task_fields` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(255) NOT NULL,
			type VARCHAR(20) NOT NULL,
			slug VARCHAR(255) NOT NULL,
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			is_editable TINYINT(1) NOT NULL DEFAULT 1,
			is_protected TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) ENGINE=InnoDB {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_otd_task_field_options` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			slug VARCHAR(255) NOT NULL,
			label VARCHAR(255) NOT NULL,
			color VARCHAR(7) DEFAULT NULL,
			position INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY field_slug_unique (field_id, slug)
		) ENGINE=InnoDB {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_otd_task_field_values` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			task_id BIGINT(20) UNSIGNED NOT NULL,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			value TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_task_field_lookup (task_id, field_id)
		) ENGINE=InnoDB {$charset_collate};
		CREATE TABLE `{$wpdb->prefix}wpo_otd_custom_statuses` (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			status_key VARCHAR(64) NOT NULL,
			label VARCHAR(255) NOT NULL,
			background VARCHAR(32) DEFAULT NULL,
			is_deleting TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY status_key_unique (status_key)
		) ENGINE=InnoDB {$charset_collate};
		";
	}

	/**
	 * The foreign keys the tables rely on, each deleting its child rows with the parent.
	 *
	 * Table names are unprefixed.
	 *
	 * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string}>
	 */
	public static function foreign_keys(): array {
		return array(
			array(
				'table'         => 'wpo_otd_task_field_options',
				'column'        => 'field_id',
				'parent_table'  => 'wpo_otd_task_fields',
				'parent_column' => 'id',
			),
			array(
				'table'         => 'wpo_otd_task_field_values',
				'column'        => 'task_id',
				'parent_table'  => 'wpo_otd_tasks',
				'parent_column' => 'id',
			),
			array(
				'table'         => 'wpo_otd_task_field_values',
				'column'        => 'field_id',
				'parent_table'  => 'wpo_otd_task_fields',
				'parent_column' => 'id',
			),
		);
	}
}
