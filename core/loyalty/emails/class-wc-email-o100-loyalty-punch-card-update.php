<?php
/**
 * Class WC_Email_O100_Loyalty_Punch_Card_Update
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Punch_Card_Update extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_punch_card_update';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Punch Card Update', 'order100' );
		$this->description    = __( 'Sent when a customer earns new punch card stamps.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-punch-card-update.php';
		$this->template_plain = 'emails/plain/o100-loyalty-punch-card-update.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'       => '',
			'{earned_stamps}'   => '',
			'{total_stamps}'    => '',
			'{required_stamps}' => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_punch_card_update_email_notification', [ $this, 'trigger' ], 10, 4 );
	}

	public function trigger( $user_id, $earned_stamps, $total_stamps, $required_stamps ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']       = $user->display_name;
		$this->placeholders['{earned_stamps}']   = $earned_stamps;
		$this->placeholders['{total_stamps}']    = $total_stamps;
		$this->placeholders['{required_stamps}'] = $required_stamps;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'You\'ve earned new stamps! ☕️', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Punch Card Update', 'order100' );
	}
}
