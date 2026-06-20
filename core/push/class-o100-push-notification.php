<?php
/**
 * Woo Push Notification Class
 *
 * Observes WooCommerce native order status transitions and forwards them to
 * the Order100 Cloud API to trigger FCM push notifications for the connected App.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Push_Notification {

	public function __construct() {
		// Hook into order creation
		add_action( 'woocommerce_new_order', array( $this, 'push_new_order' ), 20, 2 );

		// Hook into order status changes to notify App of aborts/cancellations
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_refunded',  array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_failed',    array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'push_status_change' ), 20, 2 );
		add_action( 'woocommerce_order_status_on-hold',   array( $this, 'push_status_change' ), 20, 2 );
	}

	private function is_valid_shop_order( $order ) {
		if ( ! ( $order instanceof WC_Order ) ) {
			return false;
		}
		return $order->get_type() === 'shop_order';
	}

	private function get_fcm_tokens() {
		$devices = get_option( 'o100_fcm_tokens', array() );
		if ( empty( $devices ) || ! is_array( $devices ) ) {
			return array();
		}

		$target_tokens = array();
		foreach ( $devices as $device ) {
			if ( ! empty( $device['token'] ) ) {
				$target_tokens[] = $device['token'];
			}
		}
		return $target_tokens;
	}

	public function push_new_order( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $this->is_valid_shop_order( $order ) ) {
			return;
		}

		$tokens = $this->get_fcm_tokens();
		if ( empty( $tokens ) ) {
			return;
		}

		$branch_id = get_post_meta( $order_id, 'exwoofood_location', true );
		$payload = array(
			'action'   => 'new_order',
			'id'       => (string) $order_id,
			'branch_id'=> (string) $branch_id,
			'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
			'event_id' => 'order:' . wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . $order_id,
		);

		O100_Cloud_API::send_fcm_push( $tokens, $payload );
	}

	public function push_status_change( $order_id, $order ) {
		if ( ! $this->is_valid_shop_order( $order ) ) {
			return;
		}

		$tokens = $this->get_fcm_tokens();
		if ( empty( $tokens ) ) {
			return;
		}

		$branch_id = get_post_meta( $order_id, 'exwoofood_location', true );
		$payload = array(
			'action'       => 'order_status_changed',
			'id'           => (string) $order_id,
			'branch_id'    => (string) $branch_id,
			'order_status' => $order->get_status(),
			'domain'       => wp_parse_url( home_url(), PHP_URL_HOST ),
			'event_id'     => 'status:' . wp_parse_url( home_url(), PHP_URL_HOST ) . ':' . $order_id . ':' . time(),
		);

		O100_Cloud_API::send_fcm_push( $tokens, $payload );
	}
}
