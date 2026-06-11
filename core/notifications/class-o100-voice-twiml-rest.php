<?php
/**
 * Order100 Voice TwiML REST API
 *
 * Provides endpoints to serve XML instructions for Plivo and Twilio voice calls.
 *
 * @package Order100
 * @since   4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Voice_TwiML_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( 'o100/v1', '/voice/twiml', array(
			'methods'             => \WP_REST_Server::ALLMETHODS,
			'callback'            => array( __CLASS__, 'handle_twiml' ),
			'permission_callback' => '__return_true', // Validation via signature
		) );
	}

	public static function verify_signature( $order_id, $sig ) {
		return wp_hash( $order_id . 'o100_voice' ) === $sig;
	}

	public static function handle_twiml( WP_REST_Request $request ) {
		$order_id = $request->get_param( 'order_id' );
		$sig      = $request->get_param( 'sig' );
		$to       = $request->get_param( 'To' );

		if ( ! self::verify_signature( $order_id, $sig ) ) {
			return new WP_Error( 'invalid_signature', 'Invalid request signature.', array( 'status' => 403 ) );
		}

		$options = get_option( 'o100_notifications', array() );
		$language = isset( $options['o100_voice_language'] ) ? $options['o100_voice_language'] : 'en-US';
		$loop     = isset( $options['o100_voice_retry_count'] ) ? intval( $options['o100_voice_retry_count'] ) : 1;
		if ( $loop < 1 ) $loop = 1;

		// Check transient for custom test message first
		$custom_msg = '';
		if ( ! empty( $to ) ) {
			$custom_msg = get_transient( 'o100_voice_test_message_' . $to );
		}
		if ( empty( $custom_msg ) ) {
			$custom_msg = get_transient( 'o100_voice_test_message_' . $order_id );
		}

		$tpl = ! empty( $custom_msg ) ? $custom_msg : ( isset( $options['o100_voice_tpl_content'] ) ? $options['o100_voice_tpl_content'] : 'You have a new order.' );

		if ( empty( $custom_msg ) ) {
			$order = wc_get_order( $order_id );
			$store_name = get_option( 'blogname' );
			
			if ( ! $order ) {
				// Fallback mock values for test calls
				$replacements = array(
					'[o100_order_id]'   => '12345',
					'[o100_order_type]' => 'Delivery',
					'[o100_store_name]' => $store_name,
					'{order_id}'        => '12345',
					'{order_type}'      => 'Delivery',
					'{store_name}'      => $store_name,
				);
			} else {
				$replacements = array(
					'[o100_order_id]'   => $order->get_id(),
					'[o100_order_type]' => $order->get_meta( 'o100_order_type' ) ?: 'Delivery',
					'[o100_store_name]' => $store_name,
					'{order_id}'        => $order->get_id(),
					'{order_type}'      => $order->get_meta( 'o100_order_type' ) ?: 'Delivery',
					'{store_name}'      => $store_name,
				);
			}

			$tpl = str_replace( array_keys( $replacements ), array_values( $replacements ), $tpl );
		}

		// XML response for Twilio / Plivo
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<Response>';
		$xml .= '<Say language="' . esc_attr( $language ) . '" loop="' . esc_attr( $loop ) . '">';
		$xml .= esc_html( $tpl );
		$xml .= '</Say>';
		$xml .= '</Response>';

		$response = new WP_REST_Response( $xml );
		$response->header( 'Content-Type', 'text/xml' );
		return $response;
	}
}
O100_Voice_TwiML_REST::init();
