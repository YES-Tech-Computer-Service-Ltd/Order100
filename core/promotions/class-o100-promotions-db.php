<?php
/**
 * Promotions Database Operations
 *
 * Handles table creation and CRUD operations for the wp_o100_promotions table.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_DB {

	/**
	 * Get the full table name
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'o100_promotions';
	}

	/**
	 * Create the promotions table on plugin activation or updates.
	 * Safe to call multiple times — dbDelta handles schema diffing.
	 */
	public static function create_table() {
		global $wpdb;

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(50) NOT NULL DEFAULT 'manual',
			parent_id BIGINT(20) DEFAULT NULL,
			title VARCHAR(150) NOT NULL DEFAULT '',
			description TEXT,
			rule_type VARCHAR(50) NOT NULL DEFAULT 'simple',
			action_config LONGTEXT,
			apply_to VARCHAR(50) NOT NULL DEFAULT 'all_products',
			apply_to_items LONGTEXT,
			promo_code VARCHAR(50) DEFAULT NULL,
			conditions_logic VARCHAR(10) NOT NULL DEFAULT 'all',
			conditions LONGTEXT,
			usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
			usage_count INT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			priority INT NOT NULL DEFAULT 10,
			is_exclusive TINYINT(1) NOT NULL DEFAULT 0,
			start_date DATETIME DEFAULT NULL,
			end_date DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_status_dates (status, start_date, end_date),
			KEY idx_promo_code (promo_code),
			KEY idx_source_parent (source, parent_id),
			KEY idx_priority (priority)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert a new promotion
	 */
	public static function insert( $data ) {
		global $wpdb;
		$table = self::table_name();
		
		$defaults = array(
			'source'           => 'manual',
			'parent_id'        => null,
			'title'            => '',
			'description'      => '',
			'rule_type'        => 'simple',
			'action_config'    => '{}',
			'apply_to'         => 'all_products',
			'apply_to_items'   => '[]',
			'promo_code'       => null,
			'conditions_logic' => 'all',
			'conditions'       => '[]',
			'usage_limit'      => 0,
			'usage_count'      => 0,
			'status'           => 'active',
			'priority'         => 10,
			'is_exclusive'     => 0,
			'start_date'       => null,
			'end_date'         => null,
			'created_at'       => current_time('mysql'),
		);

		$data = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert( $table, $data );
		if ( $inserted ) {
			return $wpdb->insert_id;
		}
		return false;
	}

	/**
	 * Update an existing promotion
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->update( $table, $data, array( 'id' => $id ) );
	}

	/**
	 * Delete a promotion
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->delete( $table, array( 'id' => $id ) );
	}

	/**
	 * Get a single promotion by ID
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Query promotions
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = self::table_name();
		
		$defaults = array(
			'status'   => '',
			'source'   => '',
			'orderby'  => 'id',
			'order'    => 'DESC',
			'limit'    => 100,
			'offset'   => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where_sql = 'WHERE 1=1';
		$prepare_args = array();

		if ( ! empty( $args['status'] ) ) {
			$where_sql .= ' AND status = %s';
			$prepare_args[] = $args['status'];
		}

		if ( ! empty( $args['source'] ) ) {
			$where_sql .= ' AND source = %s';
			$prepare_args[] = $args['source'];
		}

		$orderby = esc_sql( $args['orderby'] );
		$order   = esc_sql( $args['order'] );
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		$query = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $prepare_args ) ) {
			$query = $wpdb->prepare( $query, $prepare_args );
		}

		return $wpdb->get_results( $query, ARRAY_A );
	}
}


// TS: 20260308142020

// TS: 20260403222206
