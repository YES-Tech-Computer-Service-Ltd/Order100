<?php
/**
 * Class WC_Email_O100_Promo_Campaign
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Email' ) ) {
	return;
}

class WC_Email_O100_Promo_Campaign extends WC_Email {

	public function __construct() {
		$this->id             = 'o100_promo_campaign';
		$this->customer_email = true;
		$this->title          = __( 'Promo: Flash Sale / Mass Campaign', 'order100' );
		$this->description    = __( 'Generic template for mass promotion and flash sale announcements.', 'order100' );
		$this->template_html  = 'emails/o100-promo-campaign.php';
		$this->template_plain = 'emails/plain/o100-promo-campaign.php';
		$this->template_base  = O100_PATH . 'templates/';
		$this->placeholders   = [
			'{user_name}'      => '',
			'{coupon_code}'    => '',
			'{discount_value}' => '',
			'{campaign_name}'  => '',
		];

		parent::__construct();

		add_action( 'o100_trigger_promo_campaign_email_notification', [ $this, 'trigger' ], 10, 2 );
	}

	public function trigger( $user_id, $campaign_data = [] ) {
		$this->setup_locale();

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$this->recipient = $user->user_email;

		$this->placeholders['{user_name}']      = $user->display_name;
		$this->placeholders['{coupon_code}']    = isset( $campaign_data['coupon_code'] ) ? $campaign_data['coupon_code'] : '';
		$this->placeholders['{discount_value}'] = isset( $campaign_data['discount_value'] ) ? $campaign_data['discount_value'] : '';
		$this->placeholders['{campaign_name}']  = isset( $campaign_data['campaign_name'] ) ? $campaign_data['campaign_name'] : 'Special Offer';

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	public function get_default_subject() {
		return __( 'Exclusive Offer: {campaign_name}', 'order100' );
	}

	public function get_default_heading() {
		return __( 'Special Offer For You', 'order100' );
	}
}
