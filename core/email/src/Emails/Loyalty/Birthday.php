<?php

namespace Order100\Notification\Engine\Emails\Loyalty;

use Order100\Notification\Engine\Emails\BaseEmail;

class Birthday extends BaseEmail {
    public function __construct() {
        $this->id             = 'o100_loyalty_birthday';
        $this->customer_email = true;
        $this->title          = __( 'Birthday Greeting', 'order100' );
        $this->description    = __( 'Automated birthday greetings and rewards sent to customers.', 'order100' );
        $this->template_html  = 'emails/o100-loyalty-birthday.php';
        $this->template_plain = 'emails/plain/o100-loyalty-birthday.php';

        parent::__construct();

        // Trigger action hook
        add_action( 'o100_loyalty_send_birthday_greeting_notification', [ $this, 'trigger' ], 10, 2 );
    }

    public function trigger( $user_id, $data = [] ) {
        $this->setup_locale();

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $this->recipient = $user->user_email;

        // Custom placeholders
        $this->placeholders['{user_name}']      = $user->display_name;
        $this->placeholders['{discount_value}'] = isset( $data['discount_value'] ) ? $data['discount_value'] : '';
        $this->placeholders['{coupon_code}']    = isset( $data['coupon_code'] ) ? $data['coupon_code'] : '';
        
        $this->object = $user; // Store user object for MJML rendering

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

    public function get_default_mjml() {
        return '<mjml>
  <mj-body background-color="#f8fafc">
    <mj-section background-color="#ffffff" padding="40px 20px" border-radius="12px">
      <mj-column>
        <mj-image src="[o100ne_asset_url]loyalty-birthday.png" width="80px" alt="Birthday" />
        <mj-text font-family="Helvetica, Arial, sans-serif" font-size="24px" font-weight="bold" color="#1e293b" align="center">
          Happy Birthday, {user_name}! 🎂
        </mj-text>
        <mj-text font-family="Helvetica, Arial, sans-serif" font-size="16px" color="#475569" align="center" line-height="24px">
          Wishing you a fantastic day filled with joy. As a special treat, we have added a birthday gift to your account!
        </mj-text>
        <mj-spacer height="20px" />
        <mj-section background-color="#fef2f2" border="2px dashed #f87171" border-radius="8px" padding="20px">
          <mj-column>
            <mj-text font-family="Helvetica, Arial, sans-serif" font-size="14px" color="#991b1b" align="center" font-weight="bold" text-transform="uppercase">
              Your Birthday Reward
            </mj-text>
            <mj-text font-family="Helvetica, Arial, sans-serif" font-size="32px" color="#ef4444" align="center" font-weight="900" padding="10px 0">
              {discount_value}
            </mj-text>
            <mj-text font-family="Helvetica, Arial, sans-serif" font-size="14px" color="#7f1d1d" align="center">
              Use code: <span style="background:#fee2e2; padding:4px 8px; border-radius:4px; font-family:monospace; font-weight:bold;">{coupon_code}</span>
            </mj-text>
          </mj-column>
        </mj-section>
        <mj-spacer height="30px" />
        <mj-button background-color="#ef4444" color="#ffffff" font-family="Helvetica, Arial, sans-serif" font-size="16px" font-weight="bold" border-radius="8px" href="{site_url}">
          Claim Your Gift Now
        </mj-button>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>';
    }
}
