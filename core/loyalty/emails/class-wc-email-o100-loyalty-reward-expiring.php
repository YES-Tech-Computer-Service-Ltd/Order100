<?php
/**
 * Class WC_Email_O100_Loyalty_Reward_Expiring
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Reward_Expiring extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_reward_expiring';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Reward Expiring', 'order100' );
		$this->description    = __( 'Sent before a customer\'s reward/coupon expires.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-reward-expiring.php';
		$this->template_plain = 'emails/plain/o100-loyalty-reward-expiring.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{coupon_code}'    => '',
			'{discount_value}' => '',
			'{days_left}'      => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_reward_expiring_email_notification', [ $this, 'trigger' ], 10, 3 );
	}

	public function trigger( $user_id, $code, $days_left ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']      = $user->display_name;
		$this->placeholders['{coupon_code}']    = $code;
		$this->placeholders['{days_left}']      = $days_left;
		
		// Typically the discount value is needed, we will pass it dynamically if available, otherwise it relies on generic message.
		$this->placeholders['{discount_value}'] = 'your reward'; 

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Your reward is expiring in {days_left} days!', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Reward Expiring Soon!', 'order100' );
	}
}
