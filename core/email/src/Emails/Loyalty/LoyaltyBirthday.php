<?php

namespace Order100\Notification\Engine\Emails\Loyalty;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * LoyaltyBirthday Class
 *
 * @method static LoyaltyBirthday get_instance()
 */
class LoyaltyBirthday extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = isset( $emails['WC_Email_O100_Loyalty_Birthday'] ) ? $emails['WC_Email_O100_Loyalty_Birthday'] : null;
        if ( ! $email ) {
            return;
        }

        $this->id         = $email->id;
        $this->title      = $email->get_title();
        $this->root_email = $email;
        $this->recipient  = function_exists( 'o100ne_get_email_recipient_zone' ) ? o100ne_get_email_recipient_zone( $email ) : 'customer@email.com';

        $this->render_priority = apply_filters( 'o100_email_render_priority', $this->render_priority, $this->id );
        add_filter( 'wc_get_template', [ $this, 'get_template_file' ], $this->render_priority ?? 10, 3 );
        $this->maybe_disable_block_email_editor();
    }

    public function get_default_elements() {
        // High-end minimalist design with purple/indigo highlights
        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type' => 'Logo',
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<div style="text-align:center; padding: 20px 0;"><img src="https://cdn-icons-png.flaticon.com/512/3159/3159424.png" width="64" alt="Birthday" /></div>',
                    ],
                ],
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => '<h1 style="text-align:center; font-size:28px; color:#1e293b; margin:0;">Happy Birthday, {user_name}! 🎂</h1>',
                    ],
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p style="text-align:center; font-size:16px; color:#475569; line-height:1.6; margin-top:10px;">Wishing you a fantastic day filled with joy. As a special treat from us, we have added a birthday gift to your account!</p>',
                        'padding'   => [ 'top' => '0', 'right' => '40', 'bottom' => '20', 'left' => '40' ],
                    ],
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<div style="background-color:#EEF2FF; border:2px dashed #F59322; border-radius:12px; padding:30px; text-align:center;">
                            <div style="font-size:14px; color:#4338CA; font-weight:bold; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px;">Your Birthday Reward</div>
                            <div style="font-size:36px; color:#F59322; font-weight:900; margin-bottom:15px;">{discount_value}</div>
                            <div style="font-size:14px; color:#475569;">Use promo code: <span style="background-color:#fff7ed; padding:4px 10px; border-radius:6px; font-family:monospace; font-weight:bold; color:#3730A3;">{coupon_code}</span></div>
                        </div>',
                        'padding'   => [ 'top' => '10', 'right' => '40', 'bottom' => '30', 'left' => '40' ],
                    ],
                ],
                [
                    'type'       => 'Button',
                    'attributes' => [
                        'text'             => 'Claim Your Gift Now',
                        'link'             => '{site_url}',
                        'background_color' => '#F59322',
                        'color'            => '#ffffff',
                        'border_radius'    => '8px',
                        'font_weight'      => 'bold',
                        'padding'          => [ 'top' => '0', 'right' => '40', 'bottom' => '40', 'left' => '40' ],
                    ],
                ],
                [
                    'type' => 'Footer',
                ],
            ]
        );

        return $default_elements;
    }

    public function get_template_path() {
        return o100ne_get_template( 'emails/o100-loyalty-birthday.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}
