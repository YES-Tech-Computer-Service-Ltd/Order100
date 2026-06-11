<?php
/**
 * Class WC_Email_O100_Loyalty_Birthday
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Birthday extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_birthday';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Birthday Greeting', 'order100' );
		$this->description    = __( 'Automated birthday greetings and rewards sent to customers.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-birthday.php';
		$this->template_plain = 'emails/plain/o100-loyalty-birthday.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{coupon_code}'    => '',
			'{discount_value}' => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_birthday_email_notification', [ $this, 'trigger' ], 10, 2 );
	}

	public function trigger( $user_id, $data = [] ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']      = $user->display_name;
		$this->placeholders['{coupon_code}']    = isset( $data['coupon_code'] ) ? $data['coupon_code'] : '';
		$this->placeholders['{discount_value}'] = isset( $data['discount_value'] ) ? $data['discount_value'] : '';

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Happy Birthday, {user_name}! Here is a gift for you', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Happy Birthday!', 'order100' );
	}
}
