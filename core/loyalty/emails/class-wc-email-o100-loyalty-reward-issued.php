<?php
/**
 * Class WC_Email_O100_Loyalty_Reward_Issued
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Reward_Issued extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_reward_issued';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Reward Issued', 'order100' );
		$this->description    = __( 'Sent when a customer redeems points for a reward or receives an auto-reward.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-reward-issued.php';
		$this->template_plain = 'emails/plain/o100-loyalty-reward-issued.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{coupon_code}'    => '',
			'{discount_value}' => '',
			'{expiry_date}'    => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_reward_issued_email_notification', [ $this, 'trigger' ], 10, 3 );
	}

	public function trigger( $user_id, $code, $rule ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$discount_value = '';
		if ( isset( $rule['discount_config']['type'] ) ) {
			$discount_value = $rule['discount_config']['type'] === 'percentage' 
				? $rule['discount_config']['value'] . '%' 
				: wc_price( $rule['discount_config']['value'] );
		}

		$expiry_date = '';
		if ( isset( $rule['discount_config']['expiry_type'] ) ) {
			$expiry_type = $rule['discount_config']['expiry_type'];
			$expiry_days = intval( $rule['discount_config']['expiry_days'] ?? 0 );
			if ( $expiry_type === 'end_of_month' ) {
				$expiry_date = gmdate( 'Y-m-t 23:59:59' );
			} elseif ( $expiry_type === 'days' && $expiry_days > 0 ) {
				$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( "+{$expiry_days} days" ) );
			}
			if ( ! empty( $expiry_date ) ) {
				$expiry_date = wc_format_datetime( new \WC_DateTime( $expiry_date, new \DateTimeZone('UTC') ) );
			}
		}

		$this->placeholders['{user_name}']      = $user->display_name;
		$this->placeholders['{coupon_code}']    = $code;
		$this->placeholders['{discount_value}'] = $discount_value;
		$this->placeholders['{expiry_date}']    = $expiry_date;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Your reward is here! Enjoy {discount_value} off', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Reward Unlocked!', 'order100' );
	}
}
