<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'WC_Email' ) ) return;
class WC_Email_O100_Reservation_Reminder extends WC_Email {
	public function __construct() {
		$this->id             = 'o100_reservation_reminder';
		$this->customer_email = true;
		$this->title          = __( 'Reservation: Reminder', 'order100' );
		$this->description    = __( 'Sent to guest before reservation time.', 'order100' );
		$this->template_html  = 'emails/o100-reservation-reminder.php';
		$this->template_plain = 'emails/plain/o100-reservation-reminder.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{guest_name}'    => '',
			'{resv_date}'     => '',
			'{resv_time}'     => '',
			'{location}'      => '',
			'{confirm_url}'   => '',
			'{cancel_url}'    => '',
		];
		parent::__construct();
	}
	public function get_default_subject() { return __( 'Reservation Reminder', 'order100' ); }
	public function get_default_heading() { return __( 'Reservation Reminder', 'order100' ); }
}
