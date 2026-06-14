<?php

namespace Order100\Notification\Engine\Emails\Promo;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

class PromoCampaign extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = isset( $emails['WC_Email_O100_Promo_Campaign'] ) ? $emails['WC_Email_O100_Promo_Campaign'] : null;
        if ( ! $email ) return;

        $this->id         = $email->id;
        $this->title      = $email->get_title();
        $this->root_email = $email;
        $this->recipient  = function_exists( 'o100ne_get_email_recipient_zone' ) ? o100ne_get_email_recipient_zone( $email ) : 'customer@email.com';

        $this->render_priority = apply_filters( 'o100_email_render_priority', $this->render_priority, $this->id );
        add_filter( 'wc_get_template', [ $this, 'get_template_file' ], $this->render_priority ?? 10, 3 );
        $this->maybe_disable_block_email_editor();
    }

    public function get_default_elements() {
        return ElementsLoader::load_elements([
            [ 'type' => 'Logo' ],
            [
                'type'       => 'Text',
                'attributes' => [
                    'rich_text' => '<div style="text-align:center; padding: 20px 0;"><img src="https://cdn-icons-png.flaticon.com/512/3063/3063822.png" width="80" alt="Special Offer" /></div>',
                ],
            ],
            [
                'type'       => 'Heading',
                'attributes' => [
                    'rich_text' => '<h1 style="text-align:center; font-size:32px; color:#1e293b; margin:0;">{campaign_name} 🚀</h1>',
                ],
            ],
            [
                'type'       => 'Text',
                'attributes' => [
                    'rich_text' => '<p style="text-align:center; font-size:16px; color:#475569; line-height:1.6; margin-top:10px;">Hi {user_name}, our special promotion is live now! Don\'t miss out on this fantastic opportunity.</p>',
                    'padding'   => [ 'top' => '0', 'right' => '40', 'bottom' => '20', 'left' => '40' ],
                ],
            ],
            [
                'type'       => 'Text',
                'attributes' => [
                    'rich_text' => '<div style="background-color:#1E293B; border-radius:12px; padding:30px; text-align:center; color: white;">
                        <div style="font-size:14px; font-weight:bold; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; color: #94A3B8;">Get</div>
                        <div style="font-size:42px; font-weight:900; margin-bottom:15px; color: #38BDF8;">{discount_value}</div>
                        <div style="font-size:14px; color:#CBD5E1;">Use code: <span style="background-color:#334155; padding:6px 12px; border-radius:6px; font-family:monospace; font-weight:bold; color:#E2E8F0; letter-spacing:2px;">{coupon_code}</span></div>
                    </div>',
                    'padding'   => [ 'top' => '10', 'right' => '40', 'bottom' => '30', 'left' => '40' ],
                ],
            ],
            [
                'type'       => 'Button',
                'attributes' => [
                    'text'             => 'Shop The Sale',
                    'link'             => '{site_url}',
                    'background_color' => '#38BDF8',
                    'color'            => '#0F172A',
                    'border_radius'    => '8px',
                    'font_weight'      => 'bold',
                    'padding'          => [ 'top' => '0', 'right' => '40', 'bottom' => '40', 'left' => '40' ],
                ],
            ],
            [ 'type' => 'Footer' ],
        ]);
    }

    public function get_template_path() {
        return o100ne_get_template( 'emails/o100-promo-campaign.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}
