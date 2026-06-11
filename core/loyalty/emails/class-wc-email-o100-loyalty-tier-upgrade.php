<?php
/**
 * Class WC_Email_O100_Loyalty_Tier_Upgrade
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Loyalty_Tier_Upgrade extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_loyalty_tier_upgrade';
		$this->customer_email = true;
		$this->title          = __( 'Loyalty: Tier Upgrade', 'order100' );
		$this->description    = __( 'Sent when a customer is upgraded to a new loyalty tier.', 'order100' );
		$this->template_html  = 'emails/o100-loyalty-tier-upgrade.php';
		$this->template_plain = 'emails/plain/o100-loyalty-tier-upgrade.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{new_tier}'       => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_loyalty_tier_upgrade_email_notification', [ $this, 'trigger' ], 10, 2 );
	}

	public function trigger( $user_id, $new_tier ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}'] = $user->display_name;
		$this->placeholders['{new_tier}']  = $new_tier;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Congratulations! You reached {new_tier} tier', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Tier Unlocked!', 'order100' );
	}
}
