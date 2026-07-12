<?php
/**
 * Order100 CRM Rules Database Operations
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customer_Rules_DB {

	public static function get_rules( $args = [] ) {
		global $wpdb;
		$table = O100_Customers_DB::get_table_rules();

		$defaults = [
			'status' => '', // active, inactive
			'orderby' => 'priority',
			'order' => 'DESC'
		];
		$args = wp_parse_args( $args, $defaults );

		$where = "WHERE 1=1";
		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( " AND status = %s", $args['status'] );
		}

		$orderby = esc_sql( $args['orderby'] );
		$order = esc_sql( $args['order'] );

		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}";
		return $wpdb->get_results( $sql );
	}

	public static function get_rule( $id ) {
		global $wpdb;
		$table = O100_Customers_DB::get_table_rules();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function insert_rule( $data ) {
		global $wpdb;
		$table = O100_Customers_DB::get_table_rules();

		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->insert( $table, $data );
		if ( $result ) {
			return $wpdb->insert_id;
		}
		return false;
	}

	public static function update_rule( $id, $data ) {
		global $wpdb;
		$table = O100_Customers_DB::get_table_rules();

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update( $table, $data, [ 'id' => $id ] );
		return $result !== false;
	}

	public static function delete_rule( $id ) {
		global $wpdb;
		$table = O100_Customers_DB::get_table_rules();
		return $wpdb->delete( $table, [ 'id' => $id ] );
	}
}
