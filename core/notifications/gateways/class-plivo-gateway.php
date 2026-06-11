<?php
/**
 * Plivo SMS Gateway
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Plivo_Gateway implements O100_SMS_Gateway_Interface {
	private $auth_id;
	private $auth_token;
	private $from_number;

	public function __construct( $auth_id, $auth_token, $from_number ) {
		$this->auth_id     = $auth_id;
		$this->auth_token  = $auth_token;
		$this->from_number = $from_number;
	}

	public function send( $to, $message ) {
		if ( empty( $this->auth_id ) || empty( $this->auth_token ) || empty( $this->from_number ) ) {
			return new \WP_Error( 'missing_credentials', 'Plivo credentials not configured.' );
		}

		$url = 'https://api.plivo.com/v1/Account/' . $this->auth_id . '/Message/';
		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->auth_id . ':' . $this->auth_token ),
				'Content-Type'  => 'application/json',
			),
			'body' => json_encode(array(
				'src'  => $this->from_number,
				'dst'  => $to,
				'text' => $message,
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
		return new \WP_Error( 'plivo_error', 'Plivo API Error: ' . $body );
	}

	public function make_voice_call( $to, $order_id ) {
		if ( empty( $this->auth_id ) || empty( $this->auth_token ) || empty( $this->from_number ) ) {
			return new \WP_Error( 'missing_credentials', 'Plivo credentials not configured.' );
		}

		$url = 'https://api.plivo.com/v1/Account/' . $this->auth_id . '/Call/';
		$twiml_url = get_rest_url( null, 'o100/v1/voice/twiml' ) . '?order_id=' . $order_id . '&sig=' . wp_hash( $order_id . 'o100_voice' );

		$args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->auth_id . ':' . $this->auth_token ),
				'Content-Type'  => 'application/json',
			),
			'body' => json_encode(array(
				'to'         => $to,
				'from'       => $this->from_number,
				'answer_url' => $twiml_url,
				'answer_method' => 'GET',
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
		return new \WP_Error( 'plivo_voice_error', 'Plivo Voice API Error: ' . $body );
	}
}
