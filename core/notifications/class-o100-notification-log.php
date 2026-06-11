<?php
/**
 * Order100 Notification Log
 *
 * Handles logging of SMS and Voice calls to the database.
 *
 * @package Order100
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Notification_Log {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install_table' ) );
	}

	public static function maybe_install_table() {
		if ( ! get_option( 'o100_notification_log_installed' ) ) {
			self::install_table();
			update_option( 'o100_notification_log_installed', '1' );
		}
	}

	/**
	 * Table name
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'o100_notification_logs';
	}

	/**
	 * Create the table (called on plugin activation or init)
	 */
	public static function install_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL,
			target varchar(100) NOT NULL,
			message text NOT NULL,
			status varchar(20) NOT NULL,
			api_response text DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY target (target),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log a message
	 *
	 * @param string $type   'sms' or 'voice'
	 * @param string $target Target phone number
	 * @param string $message The message body or TwiML content
	 * @param string $status  'sent', 'failed', 'test'
	 * @param mixed  $api_response Raw response from gateway
	 */
	public static function log( $type, $target, $message, $status, $api_response = '' ) {
		global $wpdb;
		
		if ( is_array( $api_response ) || is_object( $api_response ) ) {
			$api_response = wp_json_encode( $api_response );
		}
		
		if ( is_wp_error( $api_response ) ) {
			$api_response = $api_response->get_error_message();
		}

		$wpdb->insert(
			self::get_table_name(),
			array(
				'type'         => $type,
				'target'       => $target,
				'message'      => $message,
				'status'       => $status,
				'api_response' => $api_response,
				'created_at'   => current_time( 'mysql' ),
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}
}
