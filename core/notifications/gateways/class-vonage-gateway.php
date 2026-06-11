<?php
/**
 * Vonage (Nexmo) SMS Gateway
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Vonage_Gateway implements O100_SMS_Gateway_Interface {
	private $api_key;
	private $api_secret;
	private $from_number;

	public function __construct( $api_key, $api_secret, $from_number ) {
		$this->api_key     = $api_key;
		$this->api_secret  = $api_secret;
		$this->from_number = $from_number;
	}

	public function send( $to, $message ) {
		if ( empty( $this->api_key ) || empty( $this->api_secret ) || empty( $this->from_number ) ) {
			return new \WP_Error( 'missing_credentials', 'Vonage credentials not configured.' );
		}

		$url = 'https://rest.nexmo.com/sms/json';
		$args = array(
			'body' => array(
				'api_key'    => $this->api_key,
				'api_secret' => $this->api_secret,
				'to'         => $to,
				'from'       => $this->from_number,
				'text'       => $message,
			),
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode($body, true);

		if ( $code >= 200 && $code < 300 && isset($data['messages'][0]['status']) && $data['messages'][0]['status'] == '0' ) {
			return true;
		}

		return new \WP_Error( 'vonage_error', 'Vonage API Error: ' . $body );
	}

	public function make_voice_call( $to, $order_id ) {
		if ( empty( $this->api_key ) || empty( $this->api_secret ) || empty( $this->from_number ) ) {
			return new \WP_Error( 'missing_credentials', 'Vonage credentials not configured.' );
		}

		$options = get_option( 'o100_notifications', array() );
		
		// Check transient for custom test message first
		$custom_msg = get_transient( 'o100_voice_test_message_' . $to );
		if ( empty( $custom_msg ) ) {
			$custom_msg = get_transient( 'o100_voice_test_message_' . $order_id );
		}
		
		$tpl = ! empty( $custom_msg ) ? $custom_msg : ( isset( $options['o100_voice_tpl_content'] ) ? $options['o100_voice_tpl_content'] : 'You have a new order.' );
		
		// Very simple replacement for vonage voice
		if ( empty( $custom_msg ) ) {
			$tpl = str_replace( '[o100_order_id]', $order_id, $tpl );
			$tpl = str_replace( '{order_id}', $order_id, $tpl );
		}

		$url = 'https://api.nexmo.com/v1/calls';
		
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret ),
				'Content-Type'  => 'application/json',
			),
			'body' => json_encode(array(
				'to'   => array( array( 'type' => 'phone', 'number' => $to ) ),
				'from' => array( 'type' => 'phone', 'number' => $this->from_number ),
				'ncco' => array(
					array(
						'action' => 'talk',
						'text'   => $tpl,
						'loop'   => 2
					)
				)
			)),
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		$body = wp_remote_retrieve_body( $response );
		return new \WP_Error( 'vonage_voice_error', 'Vonage Voice API Error: ' . $body );
	}
}
