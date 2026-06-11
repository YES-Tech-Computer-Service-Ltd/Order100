<?php
/**
 * Twilio SMS Gateway
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Twilio_Gateway implements O100_SMS_Gateway_Interface {
	private $sid;
	private $token;
	private $from_number;

	public function __construct( $sid, $token, $from_number ) {
		$this->sid         = $sid;
		$this->token       = $token;
		$this->from_number = $from_number;
	}

	public function send( $to, $message ) {
		if ( empty( $this->sid ) || empty( $this->token ) || empty( $this->from_number ) ) {
			return new \WP_Error( 'missing_credentials', 'Twilio credentials not configured.' );
		}

		$url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->sid . '/Messages.json';
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->sid . ':' . $this->token ),
			),
			'body' => array(
				'To'   => $to,
				'From' => $this->from_number,
				'Body' => $message,
			),
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
		return new \WP_Error( 'twilio_error', 'Twilio API Error: ' . $body );
	}

	public function make_voice_call( $to, $order_id ) {
		if ( empty( $this->sid ) || empty( $this->token ) || empty( $this->from_number ) ) {
			return new \WP_Error( 'missing_credentials', 'Twilio credentials not configured.' );
		}

		$url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->sid . '/Calls.json';
		$twiml_url = get_rest_url( null, 'o100/v1/voice/twiml' ) . '?order_id=' . $order_id . '&sig=' . wp_hash( $order_id . 'o100_voice' );

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->sid . ':' . $this->token ),
			),
			'body' => array(
				'To'   => $to,
				'From' => $this->from_number,
				'Url'  => $twiml_url,
			),
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
		return new \WP_Error( 'twilio_voice_error', 'Twilio Voice API Error: ' . $body );
	}
}
