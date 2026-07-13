<?php
/**
 * Class WC_Email_O100_Promo_Win_Back
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Promo_Win_Back extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_promo_win_back';
		$this->customer_email = true;
		$this->title          = __( 'Promo: Win-Back / We Miss You', 'order100' );
		$this->description    = __( 'Sent to inactive customers with a special coupon to win them back.', 'order100' );
		$this->template_html  = 'emails/o100-promo-win-back.php';
		$this->template_plain = 'emails/plain/o100-promo-win-back.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{coupon_code}'    => '',
			'{discount_value}' => '',
			'{days_inactive}'  => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_promo_win_back_email_notification', [ $this, 'trigger' ], 10, 2 );
	}

	public function trigger( $user_id, $promo_data = [] ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']      = $user->display_name;
		$this->placeholders['{coupon_code}']    = isset( $promo_data['coupon_code'] ) ? $promo_data['coupon_code'] : '';
		$this->placeholders['{discount_value}'] = isset( $promo_data['discount_value'] ) ? $promo_data['discount_value'] : '';
		$this->placeholders['{days_inactive}']  = isset( $promo_data['days_inactive'] ) ? $promo_data['days_inactive'] : 'a while';

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'We miss you! Here is {discount_value} off your next order', 'order100' );
	}

	public function get_default_heading() {
		return __( 'We Miss You!', 'order100' );
	}
}
