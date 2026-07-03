<?php
/**
 * Inbound API for HQ Order Confirmation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class O100_Inbound_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'o100-addon/v1', '/order-confirm', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle_order_confirm' ),
			'permission_callback' => array( $this, 'verify_request' ),
		) );
	}

	/**
	 * Verify IP and HMAC signature
	 */
	public function verify_request( WP_REST_Request $request ) {
		$options = get_option( 'o100_options' );

		// 1. IP Validation
		$master_url = ! empty( $options['o100_push_master_url'] ) ? $options['o100_push_master_url'] : '';
		if ( empty( $master_url ) ) {
			return new WP_Error( 'o100_unauthorized', 'Master URL not configured.', array( 'status' => 401 ) );
		}

		$parsed_url = parse_url( $master_url );
		$host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		
		if ( ! empty( $host ) ) {
			$allowed_ips = gethostbynamel( $host );
			$remote_ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
			
			// Optional: Also check HTTP_X_FORWARDED_FOR if behind proxy
			if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$forwarded_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
				$remote_ip = trim( $forwarded_ips[0] );
			}

			if ( ! $allowed_ips || ! in_array( $remote_ip, $allowed_ips, true ) ) {
				return new WP_Error( 'o100_unauthorized', 'Invalid IP Address.', array( 'status' => 401 ) );
			}
		}

		// 2. Signature Validation
		$license_key = ! empty( $options['o100_push_license_key'] ) ? $options['o100_push_license_key'] : '';
		if ( empty( $license_key ) ) {
			return new WP_Error( 'o100_unauthorized', 'License Key not configured.', array( 'status' => 401 ) );
		}

		$signature_header = $request->get_header( 'x_o100_signature' );
		if ( empty( $signature_header ) ) {
			$signature_header = $request->get_header( 'x_wfa_signature' ); // Legacy header fallback
		}
		if ( empty( $signature_header ) ) {
			return new WP_Error( 'o100_unauthorized', 'Missing Signature Header.', array( 'status' => 401 ) );
		}

		$payload      = $request->get_body();
		$expected_sig = hash_hmac( 'sha256', $payload, $license_key );

		if ( ! hash_equals( $expected_sig, $signature_header ) ) {
			return new WP_Error( 'o100_unauthorized', 'Invalid Signature.', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Handle the order confirmation or delay
	 */
	public function handle_order_confirm( WP_REST_Request $request ) {
		$params            = $request->get_json_params();
		$order_id          = isset( $params['order_id'] ) ? absint( $params['order_id'] ) : 0;
		$action            = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : ''; // 'confirm' or 'delay'
		$prep_time_minutes = isset( $params['prep_time_minutes'] ) ? absint( $params['prep_time_minutes'] ) : 0;
		$reason            = isset( $params['reason'] ) ? sanitize_text_field( $params['reason'] ) : '';

		if ( ! $order_id ) {
			return new WP_Error( 'o100_invalid_order', 'Invalid Order ID.', array( 'status' => 400 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'o100_invalid_order', 'Order not found.', array( 'status' => 404 ) );
		}

		$current_time = current_time( 'mysql' );

		// Update Order Meta
		$order->update_meta_data( '_wooauto_merchant_confirmed', 1 );
		$order->update_meta_data( '_wooauto_confirmed_prep_time', $prep_time_minutes );
		$order->update_meta_data( '_wooauto_last_update_at', $current_time );
		if ( ! empty( $reason ) ) {
			$order->update_meta_data( '_wooauto_delay_reason', $reason );
		}
		$order->save();

		// Trigger CRM Sync
		if ( class_exists( 'O100_Fluent_CRM_Adapter' ) ) {
			$crm_adapter = new O100_Fluent_CRM_Adapter();
			if ( $crm_adapter->is_active() ) {
				if ( $action === 'delay' ) {
					$crm_adapter->sync_delay_modification( $order, $prep_time_minutes, $reason );
				} else {
					$crm_adapter->sync_initial_confirmation( $order, $prep_time_minutes );
				}
			}
		}

		// Fire notification triggers for confirmed orders
		if ( $action === 'confirm' || empty( $action ) ) {
			do_action( 'o100_app_order_confirmed', $order_id );

			// If delivery order, also dispatch the driver SMS notification
			$order_type = $order->get_meta( 'o100_order_type' ) ?: 'Delivery';
			if ( strtolower( $order_type ) === 'delivery' ) {
				do_action( 'o100_app_driver_dispatch', $order_id );
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Order ' . ( $action === 'delay' ? 'delayed' : 'confirmed' ) . ' successfully.',
		) );
	}
}

