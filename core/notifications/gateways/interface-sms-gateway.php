<?php
/**
 * SMS Gateway Interface
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface O100_SMS_Gateway_Interface {
	/**
	 * Send an SMS message
	 *
	 * @param string $to Phone number
	 * @param string $message Text content
	 * @return bool|\WP_Error True on success, WP_Error on failure
	 */
	public function send( $to, $message );
}
