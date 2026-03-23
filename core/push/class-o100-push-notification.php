<?php
/**
 * Woo Push Notification Class
 *
 * Observes WooCommerce native order status transitions and forwards them to
 * the WooAuto Core Manager (master) server. This module does NOT register,
 * add, or modify any custom WooCommerce order status — the order status
 * dropdown in wp-admin remains the default set provided by WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Push_Notification {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register REST API endpoint for Android App token syncing
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Hook into order status change to "processing" (successfully paid or accepted)
		add_action( 'woocommerce_order_status_processing', array( $this, 'push_order_to_master' ), 20, 2 );

		// Hook into order status changes to notify Master Site.
		// Priority 20 ensures WC core handlers (stock restore at p10, email at p10)
		// run first. We only observe — never mutate state.
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_refunded',  array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_failed',    array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_on-hold',   array( $this, 'push_status_change' ), 20, 2 );
	}

	/**
	 * Check if push is enabled
	 */
	private function is_enabled() {
		$options = get_option( 'o100_options' );
		return isset( $options['o100_enable_push'] ) && $options['o100_enable_push'] === 'on';
	}

	/**
	 * Check if status-change sync is enabled.
	 * Defaults to ON so existing sites keep the confirmation flow working after upgrade;
	 * merchants can flip this off in settings if they need to disable status forwarding.
	 */
	private function is_status_sync_enabled() {
		$options = get_option( 'o100_options' );
		// If the key has never been saved, default ON.
		if ( ! isset( $options['o100_enable_status_sync'] ) ) {
			return true;
		}
		return isset( $options['o100_enable_status_sync'] ) && $options['o100_enable_status_sync'] === 'on';
	}

	/**
	 * Reject anything that isn't a real shop order.
	 * Excludes refund objects (shop_order_refund) and subscription renewals.
	 */
	private function is_valid_shop_order( $order ) {
		if ( ! ( $order instanceof WC_Order ) ) {
			return false;
		}
		return $order->get_type() === 'shop_order';
	}

	/**
	 * Register REST API route for token syncing
	 */
	public function register_rest_routes() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		register_rest_route( 'o100-api/v1', '/sync-token', array(
			'methods'             => WP_REST_Server::CREATABLE, // POST
			'callback'            => array( $this, 'api_sync_token' ),
			'permission_callback' => array( $this, 'check_api_permission' ),
		) );
	}

	/**
	 * Permission callback for REST API
	 */
	public function check_api_permission() {
		// Rely on WooCommerce API keys configured in the App
		// Our custom namespace authentication handler in order100.php will log the user in
		return current_user_can( 'read' ); // Basic auth check, can be tightened to 'manage_woocommerce' if needed
	}

	/**
	 * API Callback: Save device FCM token
	 */
	public function api_sync_token( WP_REST_Request $request ) {
		$device_id = sanitize_text_field( $request->get_param( 'device_id' ) );
		$fcm_token = sanitize_text_field( $request->get_param( 'fcm_token' ) );

		if ( empty( $device_id ) || empty( $fcm_token ) ) {
			return new WP_Error( 'missing_params', 'device_id or fcm_token is missing.', array( 'status' => 400 ) );
		}

		$tokens = get_option( 'o100_fcm_device_tokens', array() );
		if ( ! is_array( $tokens ) ) {
			$tokens = array();
		}

		$tokens[ $device_id ] = array(
			'token'       => $fcm_token,
			'last_update' => time(),
		);

		// Optional: clean up old tokens (e.g., > 30 days old)
		foreach ( $tokens as $id => $data ) {
			if ( isset( $data['last_update'] ) && ( time() - $data['last_update'] > 30 * DAY_IN_SECONDS ) ) {
				unset( $tokens[ $id ] );
			}
		}

		update_option( 'o100_fcm_device_tokens', $tokens, false );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Token saved successfully',
		) );
	}

	/**
	 * Push new order data to master server
	 */
	public function push_order_to_master( $order_id, $order ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->is_valid_shop_order( $order ) ) {
			return; // Skip refund objects, subscription renewals, etc.
		}

		$options     = get_option( 'o100_options' );
		$master_url  = isset( $options['o100_push_master_url'] ) ? esc_url_raw( $options['o100_push_master_url'] ) : '';
		$license_key = isset( $options['o100_push_license_key'] ) ? sanitize_text_field( $options['o100_push_license_key'] ) : '';

		if ( empty( $master_url ) || empty( $license_key ) ) {
			return;
		}

		$devices = get_option( 'o100_fcm_device_tokens', array() );
		if ( empty( $devices ) || ! is_array( $devices ) ) {
			return; // No devices registered to receive push
		}

		// Extract just the token strings
		$target_tokens = array();
		foreach ( $devices as $device ) {
			if ( ! empty( $device['token'] ) ) {
				$target_tokens[] = $device['token'];
			}
		}

		if ( empty( $target_tokens ) ) {
			return;
		}

		// Build order payload
		$order_data = $order->get_data();

		// Add line items
		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$item_data = $item->get_data();

			// Extract item meta nicely
			$item_meta = array();
			$meta_data = $item->get_meta_data();
			foreach ( $meta_data as $meta ) {
				$item_meta[ $meta->key ] = $meta->value;
			}
			$item_data['meta_data_formatted'] = $item_meta;

			$items[] = $item_data;
		}
		$order_data['line_items'] = $items;

		// Extract all order meta
		$order_meta = array();
		foreach ( $order->get_meta_data() as $meta ) {
			$order_meta[ $meta->key ] = $meta->value;
		}

		// Main payload structure requested by master server.
		// Use home_url() instead of $_SERVER['HTTP_HOST'] so reverse-proxy /
		// CDN setups report the canonical site domain.
		$payload_array = array(
			'domain'        => wp_parse_url( home_url(), PHP_URL_HOST ),
			'license_key'   => $license_key,
			'order_id'      => $order_id,
			'order_number'  => $order->get_order_number(),
			'total'         => $order->get_total(),
			'order_status'  => $order->get_status(),
			'customer_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'tokens'        => array_values( array_unique( $target_tokens ) ),
			'order_data'    => $order_data,
			'order_meta'    => $order_meta,
			'timestamp'     => time(),
		);

		$payload_json = wp_json_encode( $payload_array );

		// Generate HMAC SHA256 Signature
		$signature = hash_hmac( 'sha256', $payload_json, $license_key );

		// Send async POST request to master server
		// Use a short timeout to prevent blocking the checkout flow
		$args = array(
			'body'        => $payload_json,
			'headers'     => array(
				'Content-Type'        => 'application/json; charset=utf-8',
				'X-YesTech-Signature' => $signature,
			),
			'timeout'     => 5,
			'blocking'    => false, // Essential: do not wait for a response!
			'sslverify'   => false, // Prevent issues if local/staging environments have self-signed certs
		);

		wp_remote_post( $master_url, $args );
	}

	/**
	 * Push order status change (cancel/refund/failed/completed/on-hold) to Master Site.
	 *
	 * This allows the Master Site to abort pending phone notifications when an
	 * order is cancelled, and to keep the master order-report dashboard in sync
	 * with the merchant site.
	 *
	 * This callback ONLY observes native WooCommerce status transitions — it
	 * does not register any custom order status, nor mutate the order object.
	 */
	public function push_status_change( $order_id, $order ) {
		if ( ! $this->is_enabled() || ! $this->is_status_sync_enabled() ) {
			return;
		}

		if ( ! $this->is_valid_shop_order( $order ) ) {
			return; // Skip refund objects, subscription renewals, etc.
		}

		$options     = get_option( 'o100_options' );
		$master_url  = isset( $options['o100_push_master_url'] ) ? esc_url_raw( $options['o100_push_master_url'] ) : '';
		$license_key = isset( $options['o100_push_license_key'] ) ? sanitize_text_field( $options['o100_push_license_key'] ) : '';

		if ( empty( $master_url ) || empty( $license_key ) ) {
			return;
		}

		$payload_array = array(
			'action'      => 'order_status_change',
			'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
			'license_key' => $license_key,
			'order_id'    => $order_id,
			'new_status'  => $order->get_status(),
			'timestamp'   => time(),
		);

		$payload_json = wp_json_encode( $payload_array );
		$signature    = hash_hmac( 'sha256', $payload_json, $license_key );

		wp_remote_post( $master_url, array(
			'body'      => $payload_json,
			'headers'   => array(
				'Content-Type'        => 'application/json; charset=utf-8',
				'X-YesTech-Signature' => $signature,
			),
			'timeout'   => 5,
			'blocking'  => false,
			'sslverify' => false,
		) );
	}
}





// TS: 20260209121200

// TS: 20260323174411
