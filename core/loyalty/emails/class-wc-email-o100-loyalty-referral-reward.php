<?php
/**
 * Class WC_Email_O100_Loyalty_Referral_Reward
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Referral_Reward extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_referral_reward';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Referral Reward', 'order100' );
		$this->description    = __( 'Sent to the advocate when their referred friend completes a purchase.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-referral-reward.php';
		$this->template_plain = 'emails/plain/o100-loyalty-referral-reward.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{advocate_name}'  => '',
			'{friend_name}'    => '',
			'{reward_detail}'  => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_referral_reward_email_notification', [ $this, 'trigger' ], 10, 3 );
	}

	public function trigger( $advocate_id, $friend_id, $reward_data ) {
		$this->setup_locale();

		$advocate = get_userdata( $advocate_id );
		$friend   = get_userdata( $friend_id );
		if ( ! $advocate || ! $friend ) {
			return;
		}

		$this->recipient = $advocate->user_email;

		$this->placeholders['{advocate_name}'] = $advocate->display_name;
		$this->placeholders['{friend_name}']   = $friend->display_name;
		$this->placeholders['{reward_detail}'] = isset($reward_data['points']) ? $reward_data['points'] . ' points' : 'a special reward';

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Thank you! You earned a reward for referring {friend_name}', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Referral Successful!', 'order100' );
	}
}
