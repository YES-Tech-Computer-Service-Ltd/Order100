<?php
/**
 * Class WC_Email_O100_Loyalty_Referral_Invite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Referral_Invite extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_referral_invite';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Referral Invite', 'order100' );
		$this->description    = __( 'Sent to a friend when a customer refers them.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-referral-invite.php';
		$this->template_plain = 'emails/plain/o100-loyalty-referral-invite.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{advocate_name}'  => '',
			'{referral_link}'  => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_referral_invite_email_notification', [ $this, 'trigger' ], 10, 3 );
	}

	public function trigger( $advocate_id, $friend_email, $referral_link ) {
		$this->setup_locale();

		$advocate = get_userdata( $advocate_id );
		if ( ! $advocate ) {
			return;
		}

		$this->recipient = $friend_email;

		$this->placeholders['{advocate_name}'] = $advocate->display_name;
		$this->placeholders['{referral_link}'] = $referral_link;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( '{advocate_name} invited you to join us!', 'order100' );
	}

	public function get_default_heading() {
		return __( 'You\'ve been invited!', 'order100' );
	}
}
