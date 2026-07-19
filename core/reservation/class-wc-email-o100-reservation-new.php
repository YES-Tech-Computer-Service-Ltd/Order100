<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'WC_Email' ) ) return;
class WC_Email_O100_Reservation_New extends WC_Email {
	public function __construct() {
		$this->id             = 'o100_reservation_new';
		$this->customer_email = true;
		$this->title          = __( 'Reservation: New', 'order100' );
		$this->description    = __( 'Sent to guest when they submit a new reservation.', 'order100' );
		$this->template_html  = 'emails/o100-reservation-new.php';
		$this->template_plain = 'emails/plain/o100-reservation-new.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{guest_name}'    => '',
			'{guest_email}'   => '',
			'{guest_phone}'   => '',
			'{party_size}'    => '',
			'{resv_date}'     => '',
			'{resv_time}'     => '',
			'{location}'      => '',
		];
		parent::__construct();
	}
	public function get_default_subject() { return __( 'Reservation Received', 'order100' ); }
	public function get_default_heading() { return __( 'Reservation Received', 'order100' ); }
}
