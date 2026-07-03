<?php
/**
 * Register Reservation Email Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class O100_Reservation_Emails {

	public static function init() {
		add_filter( 'woocommerce_email_classes', [ __CLASS__, 'register_email_classes' ] );
	}

	public static function register_email_classes( $emails ) {
		require_once __DIR__ . '/class-wc-email-o100-reservation-new.php';
		require_once __DIR__ . '/class-wc-email-o100-reservation-confirmed.php';
		require_once __DIR__ . '/class-wc-email-o100-reservation-rejected.php';
		require_once __DIR__ . '/class-wc-email-o100-reservation-reminder.php';

		$emails['WC_Email_O100_Reservation_New']       = new WC_Email_O100_Reservation_New();
		$emails['WC_Email_O100_Reservation_Confirmed'] = new WC_Email_O100_Reservation_Confirmed();
		$emails['WC_Email_O100_Reservation_Rejected']  = new WC_Email_O100_Reservation_Rejected();
		$emails['WC_Email_O100_Reservation_Reminder']  = new WC_Email_O100_Reservation_Reminder();

		return $emails;
	}
}

O100_Reservation_Emails::init();
