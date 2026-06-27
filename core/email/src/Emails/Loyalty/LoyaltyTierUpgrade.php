<?php

namespace Order100\Notification\Engine\Emails\Loyalty;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

class LoyaltyTierUpgrade extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = isset( $emails['WC_Email_O100_Loyalty_Tier_Upgrade'] ) ? $emails['WC_Email_O100_Loyalty_Tier_Upgrade'] : null;
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
                    'rich_text' => '<div style="text-align:center; padding: 20px 0;"><img src="https://cdn-icons-png.flaticon.com/512/5403/5403487.png" width="64" alt="VIP Tier" /></div>',
                ],
            ],
            [
                'type'       => 'Heading',
                'attributes' => [
                    'rich_text' => '<h1 style="text-align:center; font-size:28px; color:#1e293b; margin:0;">Congratulations, {user_name}! 🏆</h1>',
                ],
            ],
            [
                'type'       => 'Text',
                'attributes' => [
                    'rich_text' => '<p style="text-align:center; font-size:16px; color:#475569; line-height:1.6; margin-top:10px;">You have just reached a new loyalty tier. Thank you for your continued support!</p>',
                    'padding'   => [ 'top' => '0', 'right' => '40', 'bottom' => '20', 'left' => '40' ],
                ],
            ],
            [
                'type'       => 'Text',
                'attributes' => [
                    'rich_text' => '<div style="background: linear-gradient(135deg, #F59322, #c2410c); border-radius:12px; padding:30px; text-align:center; color: white;">
                        <div style="font-size:14px; font-weight:bold; text-transform:uppercase; letter-spacing: 2px; margin-bottom:5px; opacity: 0.9;">You are now</div>
                        <div style="font-size:36px; font-weight:900; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{new_tier}</div>
                    </div>',
                    'padding'   => [ 'top' => '10', 'right' => '40', 'bottom' => '30', 'left' => '40' ],
                ],
            ],
            [
                'type'       => 'Button',
                'attributes' => [
                    'text'             => 'View Your New Perks',
                    'link'             => '{site_url}',
                    'background_color' => '#1E293B',
                    'color'            => '#ffffff',
                    'border_radius'    => '8px',
                    'font_weight'      => 'bold',
                    'padding'          => [ 'top' => '0', 'right' => '40', 'bottom' => '40', 'left' => '40' ],
                ],
            ],
            [ 'type' => 'Footer' ],
        ]);
    }

    public function get_template_path() {
        return o100ne_get_template( 'emails/o100-loyalty-tier-upgrade.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}
