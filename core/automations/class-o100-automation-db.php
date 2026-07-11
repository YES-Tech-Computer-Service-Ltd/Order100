<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Automation_DB {

	/**
	 * Table name
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'o100_automations';
	}

	public static function get_table_logs() {
		global $wpdb;
		return $wpdb->prefix . 'o100_automation_logs';
	}

	/**
	 * Create database table
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$table_logs = self::get_table_logs();

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text NULL,
			status varchar(20) NOT NULL DEFAULT 'paused',
			trigger_type varchar(50) NOT NULL,
			trigger_config longtext NULL,
			conditions longtext NOT NULL,
			actions longtext NOT NULL,
			rules_and_goals longtext NULL,
			delay_minutes int(11) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql_logs = "CREATE TABLE $table_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			automation_id bigint(20) NOT NULL,
			customer_id bigint(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'completed',
			details longtext NULL,
			run_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY automation_id (automation_id),
			KEY customer_id (customer_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql_logs );
	}

	/**
	 * Insert a new automation
	 */
	public static function insert_automation( $data ) {
		global $wpdb;
		
		$defaults = array(
			'title'           => '',
			'status'          => 'paused',
			'trigger_type'    => '',
			'conditions'      => '[]',
			'actions'         => '[]',
			'rules_and_goals' => '{}',
			'delay_minutes'   => 0,
			'created_at'      => current_time( 'mysql' ),
		);
		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			self::get_table_name(),
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Update an automation
	 */
	public static function update_automation( $id, $data ) {
		global $wpdb;

		$wpdb->update(
			self::get_table_name(),
			$data,
			array( 'id' => $id )
		);

		return true;
	}

	/**
	 * Delete an automation
	 */
	public static function delete_automation( $id ) {
		global $wpdb;
		return $wpdb->delete( self::get_table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get all automations
	 */
	public static function get_automations( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$where = '1=1';
		if ( isset( $args['status'] ) ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}
		if ( isset( $args['trigger_type'] ) ) {
			$where .= $wpdb->prepare( " AND trigger_type = %s", $args['trigger_type'] );
		}

		$sql = "SELECT * FROM $table_name WHERE $where ORDER BY id DESC";
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get a single automation
	 */
	public static function get_automation( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
	}
}

// Temporary hook to update tables during development
add_action( 'admin_init', array( 'O100_Automation_DB', 'create_table' ) );
