<?php
/**
 * Class WC_Email_O100_Loyalty_Points_Expiring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Points_Expiring extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_points_expiring';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Points Expiring', 'order100' );
		$this->description    = __( 'Sent before a customer\'s points expire.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-points-expiring.php';
		$this->template_plain = 'emails/plain/o100-loyalty-points-expiring.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{points}'         => '',
			'{expiry_date}'    => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_wc_loyalty_points_expiring', [ $this, 'trigger' ], 10, 3 );
	}

	public function trigger( $user_id, $points, $expiry_date ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']   = $user->display_name;
		$this->placeholders['{points}']      = $points;
		$this->placeholders['{expiry_date}'] = $expiry_date;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Your {points} points are expiring soon!', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Points Expiring Soon!', 'order100' );
	}
}
