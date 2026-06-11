<?php
/**
 * Class WC_Email_O100_Loyalty_Points_Earned
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Points_Earned extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_points_earned';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Points Earned', 'order100' );
		$this->description    = __( 'Sent when a customer earns new loyalty points.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-points-earned.php';
		$this->template_plain = 'emails/plain/o100-loyalty-points-earned.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{points_earned}'  => '',
			'{total_points}'   => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_points_earned_email_notification', [ $this, 'trigger' ], 10, 2 );
	}

	public function trigger( $user_id, $points ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']      = $user->display_name;
		$this->placeholders['{points_earned}']  = $points;
		
		$total_points = class_exists('O100_Loyalty_Customers_DB') ? \O100_Loyalty_Customers_DB::get_customer_points($user_id) : 0;
		$this->placeholders['{total_points}']   = $total_points;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'You just earned {points_earned} points!', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Points Earned!', 'order100' );
	}
}
