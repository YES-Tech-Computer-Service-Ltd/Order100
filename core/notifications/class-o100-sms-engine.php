<?php
/**
 * Order100 SMS Engine
 *
 * Handles SMS notification templates, gateway integration, and triggering based on order/app events.
 *
 * @package Order100
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load gateways
require_once dirname( __FILE__ ) . '/gateways/interface-sms-gateway.php';
require_once dirname( __FILE__ ) . '/gateways/class-twilio-gateway.php';
require_once dirname( __FILE__ ) . '/gateways/class-plivo-gateway.php';
require_once dirname( __FILE__ ) . '/gateways/class-vonage-gateway.php';

// Load scheduler & logger & REST endpoints
require_once dirname( __FILE__ ) . '/class-o100-sms-scheduler.php';
require_once dirname( __FILE__ ) . '/class-o100-notification-log.php';
require_once dirname( __FILE__ ) . '/class-o100-voice-twiml-rest.php';

/**
 * Main SMS Engine Class
 */
class O100_SMS_Engine {

	/**
	 * Boot the engine by registering triggers if SMS is enabled globally.
	 */
	public static function init() {
		$options = get_option( 'o100_notifications', array() );
		
		// Global disable switch
		if ( empty( $options['o100_sms_enable'] ) || $options['o100_sms_enable'] !== 'on' ) {
			return;
		}

		// Order Triggers
		add_action( 'woocommerce_new_order', array( __CLASS__, 'trigger_new_order_admin' ), 10, 2 );
		add_action( 'o100_app_order_confirmed', array( __CLASS__, 'trigger_order_confirmed' ), 10, 1 );
		add_action( 'o100_app_order_ready', array( __CLASS__, 'trigger_ready_pickup' ), 10, 1 );
		add_action( 'o100_app_out_for_delivery', array( __CLASS__, 'trigger_out_delivery' ), 10, 1 );
		add_action( 'o100_app_driver_dispatch', array( __CLASS__, 'trigger_driver_dispatch' ), 10, 1 );

		// Activity Triggers
		add_action( 'o100_loyalty_points_awarded', array( __CLASS__, 'trigger_loyalty_update' ), 10, 3 );
		add_action( 'o100_reservation_confirmed', array( __CLASS__, 'trigger_reservation_confirmed' ), 10, 1 );
		
		// Scheduler Triggers
		add_action( 'o100_sms_coupon_issued', array( __CLASS__, 'trigger_coupon_issued' ), 10, 3 );
		add_action( 'o100_loyalty_points_expiring_sms', array( __CLASS__, 'trigger_points_expiring_sms' ), 10, 3 );
		add_action( 'o100_sms_birthday', array( __CLASS__, 'trigger_birthday' ), 10, 2 );
		add_action( 'o100_send_scheduled_sms', array( __CLASS__, 'send_custom_sms' ), 10, 2 );

		// AJAX Endpoints
		add_action( 'wp_ajax_o100_test_voice_call', array( __CLASS__, 'ajax_test_voice_call' ) );
		add_action( 'wp_ajax_o100_test_sms', array( __CLASS__, 'ajax_test_sms' ) );
		
		// Init Logs
		O100_Notification_Log::init();

		// Init Scheduler
		O100_SMS_Scheduler::init();
	}

	/**
	 * Get the configured gateway instance (Factory Pattern)
	 */
	private static function get_gateway() {
		$options = get_option( 'o100_notifications', array() );
		$provider = isset( $options['o100_sms_gateway'] ) ? $options['o100_sms_gateway'] : 'twilio';
		
		$key    = isset( $options['o100_sms_api_key'] ) ? $options['o100_sms_api_key'] : '';
		$secret = isset( $options['o100_sms_api_secret'] ) ? $options['o100_sms_api_secret'] : '';
		$sender = isset( $options['o100_sms_sender_number'] ) ? $options['o100_sms_sender_number'] : '';
		
		switch ( $provider ) {
			case 'twilio':
				return new O100_Twilio_Gateway( $key, $secret, $sender );
			case 'plivo':
				return new O100_Plivo_Gateway( $key, $secret, $sender );
			case 'vonage':
				return new O100_Vonage_Gateway( $key, $secret, $sender );
			default:
				return null;
		}
	}

	/**
	 * Send a generic SMS message directly
	 *
	 * @param string $phone
	 * @param string $message
	 */
	public static function send_custom_sms( $phone, $message ) {
		if ( empty( $phone ) || empty( $message ) ) return;
		$gateway = self::get_gateway();
		if ( $gateway ) {
			$result = $gateway->send( $phone, $message );
			if ( is_wp_error( $result ) ) {
				O100_Notification_Log::log( 'sms', $phone, $message, 'failed', $result );
			} else {
				O100_Notification_Log::log( 'sms', $phone, $message, 'sent', 'OK' );
			}
		}
	}

	/**
	 * Replace shortcodes with order data
	 */
	private static function process_template( $template, $order ) {
		if ( empty( $template ) ) {
			return '';
		}

		// Basic mappings
		$store_name = get_option( 'blogname' );
		
		$replacements = array(
			'[o100_order_id]'      => $order->get_id(),
			'{order_id}'           => $order->get_id(),
			'[o100_store_name]'    => $store_name,
			'{store_name}'         => $store_name,
			'[o100_order_type]'    => $order->get_meta( 'o100_order_type' ) ?: 'Delivery',
			'{order_type}'         => $order->get_meta( 'o100_order_type' ) ?: 'Delivery',
			'[o100_customer_name]' => $order->get_billing_first_name(),
			'{customer_name}'      => $order->get_billing_first_name(),
		);

		// Specific mappings
		if ( strpos( $template, '[o100_estimated_ready]' ) !== false ) {
			$prep_time = $order->get_meta( 'o100_prep_time_minutes' ) ?: '30';
			$replacements['[o100_estimated_ready]'] = $prep_time . ' minutes';
		}

		if ( strpos( $template, '[o100_delivery_address]' ) !== false ) {
			$replacements['[o100_delivery_address]'] = $order->get_shipping_address_1() . ' ' . $order->get_shipping_city();
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Replace shortcodes with custom variable map
	 */
	private static function process_custom_template( $template, $vars ) {
		if ( empty( $template ) ) return '';
		
		$vars['[o100_store_name]'] = get_option( 'blogname' );

		// Normalise keys to have brackets if they don't
		$replacements = array();
		foreach ( $vars as $k => $v ) {
			$key = strpos($k, '[') === 0 ? $k : '[' . $k . ']';
			$replacements[$key] = $v;
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Trigger: New Order (To Admin)
	 */
	public static function trigger_new_order_admin( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_new_order'] ) ? $options['o100_sms_tpl_new_order'] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$message = self::process_template( $template, $order );
		$admin_phone = isset( $options['o100_sms_sender_number'] ) ? $options['o100_sms_sender_number'] : '';
		
		self::send_custom_sms( $admin_phone, $message );
	}

	/**
	 * Trigger: Order Confirmed (To Customer)
	 */
	public static function trigger_order_confirmed( $order_id ) {
		self::send_to_customer( $order_id, 'o100_sms_tpl_order_confirmed' );
	}

	/**
	 * Trigger: Ready for Pickup (To Customer)
	 */
	public static function trigger_ready_pickup( $order_id ) {
		self::send_to_customer( $order_id, 'o100_sms_tpl_ready_pickup' );
	}

	/**
	 * Trigger: Out for Delivery (To Customer)
	 */
	public static function trigger_out_delivery( $order_id ) {
		self::send_to_customer( $order_id, 'o100_sms_tpl_out_delivery' );
	}

	/**
	 * Trigger: Driver Dispatch (To Driver)
	 */
	public static function trigger_driver_dispatch( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_driver_dispatch'] ) ? $options['o100_sms_tpl_driver_dispatch'] : '';
		$driver_phone = isset( $options['o100_sms_driver_number'] ) ? $options['o100_sms_driver_number'] : '';
		
		if ( empty( trim( $template ) ) || empty( $driver_phone ) ) return;

		$message = self::process_template( $template, $order );
		self::send_custom_sms( $driver_phone, $message );
	}

	/**
	 * Trigger: Reservation Confirmed & Schedule Reminder
	 */
	public static function trigger_reservation_confirmed( $reservation_id ) {
		// Mock logic for getting reservation data (assuming it's a post or custom table)
		$reservation = get_post( $reservation_id );
		if ( ! $reservation ) return;

		$phone = get_post_meta( $reservation_id, 'o100_phone', true );
		$name  = get_post_meta( $reservation_id, 'o100_first_name', true );
		$date  = get_post_meta( $reservation_id, 'o100_date', true );
		$time  = get_post_meta( $reservation_id, 'o100_time', true );
		$size  = get_post_meta( $reservation_id, 'o100_party_size', true );

		if ( empty( $phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_reservation'] ) ? $options['o100_sms_tpl_reservation'] : '';
		
		$vars = array(
			'o100_customer_name' => $name,
			'o100_reserve_date'  => $date,
			'o100_reserve_time'  => $time,
			'o100_party_size'    => $size,
		);

		if ( ! empty( trim( $template ) ) ) {
			$message = self::process_custom_template( $template, $vars );
			self::send_custom_sms( $phone, $message );
		}

		// Schedule Reminder
		$remind_minutes = isset( $options['o100_sms_reserve_remind_time'] ) ? (int) $options['o100_sms_reserve_remind_time'] : 0;
		$remind_template = isset( $options['o100_sms_tpl_reserve_remind'] ) ? $options['o100_sms_tpl_reserve_remind'] : '';

		if ( $remind_minutes > 0 && ! empty( trim( $remind_template ) ) ) {
			$reserve_timestamp = strtotime( $date . ' ' . $time );
			if ( $reserve_timestamp ) {
				$remind_timestamp = $reserve_timestamp - ( $remind_minutes * 60 );
				if ( $remind_timestamp > time() ) {
					$remind_message = self::process_custom_template( $remind_template, $vars );
					// Enqueue into Action Scheduler with a specific group string for easy unscheduling
					$group = 'reservation_' . $reservation_id;
					O100_SMS_Scheduler::schedule_sms( $remind_timestamp, $phone, $remind_message, $group );
				}
			}
		}
	}

	/**
	 * Trigger: Loyalty Points Update
	 */
	public static function trigger_loyalty_update( $customer_id, $points_earned, $new_balance ) {
		$customer = get_userdata( $customer_id );
		if ( ! $customer ) return;

		$phone = get_user_meta( $customer_id, 'billing_phone', true );
		if ( empty( $phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_loyalty_update'] ) ? $options['o100_sms_tpl_loyalty_update'] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$vars = array(
			'o100_customer_name'   => $customer->first_name ?: $customer->display_name,
			'o100_points_earned'   => $points_earned,
			'o100_loyalty_balance' => $new_balance,
		);

		$message = self::process_custom_template( $template, $vars );
		self::send_custom_sms( $phone, $message );
	}

	/**
	 * Trigger: Coupon Issued
	 */
	public static function trigger_coupon_issued( $customer_id, $coupon_code, $coupon_value ) {
		$customer = get_userdata( $customer_id );
		if ( ! $customer ) return;

		$phone = get_user_meta( $customer_id, 'billing_phone', true );
		if ( empty( $phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_coupon_issued'] ) ? $options['o100_sms_tpl_coupon_issued'] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$vars = array(
			'o100_customer_name' => $customer->first_name ?: $customer->display_name,
			'o100_coupon_code'   => $coupon_code,
			'o100_coupon_value'  => $coupon_value,
		);

		$message = self::process_custom_template( $template, $vars );
		self::send_custom_sms( $phone, $message );
	}

	/**
	 * Trigger: Points Expiring SMS
	 */
	public static function trigger_points_expiring_sms( $customer_id, $points, $expiry_date ) {
		$customer = get_userdata( $customer_id );
		if ( ! $customer ) return;

		$phone = get_user_meta( $customer_id, 'billing_phone', true );
		if ( empty( $phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_points_expire'] ) ? $options['o100_sms_tpl_points_expire'] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$vars = array(
			'o100_customer_name' => $customer->first_name ?: $customer->display_name,
			'o100_points_amount' => $points,
			'o100_points_expiry' => $expiry_date,
		);

		// Also map to generic custom variables for robust replacement
		$vars['customer_name'] = $vars['o100_customer_name'];
		$vars['points_amount'] = $vars['o100_points_amount'];
		$vars['points_expiry'] = $vars['o100_points_expiry'];

		$message = self::process_custom_template( $template, $vars );
		self::send_custom_sms( $phone, $message );
	}

	/**
	 * Trigger: Birthday
	 */
	public static function trigger_birthday( $customer_id, $balance ) {
		$customer = get_userdata( $customer_id );
		if ( ! $customer ) return;

		$phone = get_user_meta( $customer_id, 'billing_phone', true );
		if ( empty( $phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_birthday'] ) ? $options['o100_sms_tpl_birthday'] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$vars = array(
			'o100_customer_name'   => $customer->first_name ?: $customer->display_name,
			'o100_loyalty_balance' => $balance,
		);

		$message = self::process_custom_template( $template, $vars );
		self::send_custom_sms( $phone, $message );
	}

	/**
	 * Helper: Send a specific template to the customer of an order
	 */
	private static function send_to_customer( $order_id, $template_key ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		$customer_phone = $order->get_billing_phone();
		if ( empty( $customer_phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options[$template_key] ) ? $options[$template_key] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$message = self::process_template( $template, $order );
		self::send_custom_sms( $customer_phone, $message );
	}

	/**
	 * AJAX: Test Voice Call
	 */
	public static function ajax_test_voice_call() {
		check_ajax_referer( 'o100_voice_test', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'order100' ) );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		if ( empty( $phone ) ) {
			wp_send_json_error( __( 'Phone number is required.', 'order100' ) );
		}
		
		// Set custom test message into transient so TwiML REST can fetch it
		$custom_message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( ! empty( $custom_message ) ) {
			set_transient( 'o100_voice_test_message_' . $phone, $custom_message, 5 * MINUTE_IN_SECONDS );
			set_transient( 'o100_voice_test_message_0', $custom_message, 5 * MINUTE_IN_SECONDS ); // Since order_id is 0 for test calls
		}

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			wp_send_json_error( __( 'No valid SMS/Voice gateway configured.', 'order100' ) );
		}
		
		if ( ! method_exists( $gateway, 'make_voice_call' ) ) {
			wp_send_json_error( __( 'This gateway does not support Voice Calls.', 'order100' ) );
		}

		$result = $gateway->make_voice_call( $phone, '0' );
		
		if ( is_wp_error( $result ) ) {
			O100_Notification_Log::log( 'voice', $phone, 'Test Call Triggered', 'failed', $result );
			wp_send_json_error( $result->get_error_message() );
		}

		O100_Notification_Log::log( 'voice', $phone, 'Test Call Triggered', 'sent', 'OK' );
		wp_send_json_success( __( 'Voice call initiated successfully!', 'order100' ) );
	}

	/**
	 * AJAX: Test SMS
	 */
	public static function ajax_test_sms() {
		check_ajax_referer( 'o100_sms_test', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'order100' ) );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		if ( empty( $phone ) ) {
			wp_send_json_error( __( 'Phone number is required.', 'order100' ) );
		}
		
		$custom_message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$message = ! empty( $custom_message ) ? $custom_message : sprintf(
			__( 'Test message from %s — your SMS integration is working!', 'order100' ),
			get_option( 'blogname' )
		);

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			wp_send_json_error( __( 'No valid SMS gateway configured.', 'order100' ) );
		}

		$result = $gateway->send( $phone, $message );
		
		if ( is_wp_error( $result ) ) {
			O100_Notification_Log::log( 'sms', $phone, $message, 'failed', $result );
			wp_send_json_error( $result->get_error_message() );
		}

		O100_Notification_Log::log( 'sms', $phone, $message, 'sent', 'OK' );
		wp_send_json_success( __( 'Test SMS sent successfully!', 'order100' ) );
	}

	/**
	 * Trigger: Schedule Coupon Expiry Warning
	 * Call this when a coupon is issued, to schedule an alert X days before expiry.
	 *
	 * @param int    $customer_id
	 * @param string $coupon_code
	 * @param string $coupon_value
	 * @param int    $expire_timestamp Unix timestamp of when the coupon expires
	 * @param int    $remind_days_before How many days before expiry to warn
	 */
	public static function schedule_coupon_expire( $customer_id, $coupon_code, $coupon_value, $expire_timestamp, $remind_days_before = 2 ) {
		$customer = get_userdata( $customer_id );
		if ( ! $customer ) return;

		$phone = get_user_meta( $customer_id, 'billing_phone', true );
		if ( empty( $phone ) ) return;

		$options = get_option( 'o100_notifications', array() );
		$template = isset( $options['o100_sms_tpl_coupon_expire'] ) ? $options['o100_sms_tpl_coupon_expire'] : '';
		
		if ( empty( trim( $template ) ) ) return;

		$vars = array(
			'o100_customer_name' => $customer->first_name ?: $customer->display_name,
			'o100_coupon_code'   => $coupon_code,
			'o100_coupon_value'  => $coupon_value,
		);

		$message = self::process_custom_template( $template, $vars );
		
		// Calculate reminder time
		$remind_timestamp = $expire_timestamp - ( $remind_days_before * DAY_IN_SECONDS );
		
		if ( $remind_timestamp > time() ) {
			$group = 'coupon_expire_' . $coupon_code;
			O100_SMS_Scheduler::schedule_sms( $remind_timestamp, $phone, $message, $group );
		}
	}

}
