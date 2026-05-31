<?php

namespace Order100\Notification\Engine\Emails;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * CustomerResetPassword Class
 *
 * @method static CustomerResetPassword get_instance()
 */
class CustomerResetPassword extends BaseEmail {
    use SingletonTrait;

    public $email_types = [ O100NE_NON_ORDER_EMAILS ];

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_Reset_Password'];
        if ( ! $email ) {
            return;
        }

        $this->id         = $email->id;
        $this->title      = $email->get_title();
        $this->root_email = $email;
        $this->recipient  = function_exists( 'o100_get_email_recipient_zone' ) ? o100ne_get_email_recipient_zone( $email ) : '';

        $this->render_priority = apply_filters( 'o100_email_render_priority', $this->render_priority, $this->id );
        add_filter( 'wc_get_template', [ $this, 'get_template_file' ], $this->render_priority ?? 10, 3 );
        $this->maybe_disable_block_email_editor();
    }

    public function get_default_elements() {
        $email_title = __( 'Password Reset Request', 'woocommerce' );
        // translators: customer username.
        $email_hi = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[o100_customer_username]' );
        // translators: site name.
        $email_text      = sprintf( esc_html__( 'Someone has requested a new password for the following account on %s:,', 'woocommerce' ), '[o100_site_name]' );
        $email_text_1    = esc_html__( 'If you didn\'t make this request, just ignore this email. If you\'d like to proceed:', 'woocommerce' );
        $text_username   = __( 'Username', 'woocommerce' );
        $additional_text = __( 'Thanks for reading.', 'woocommerce' );

        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type' => 'Logo',
                ],
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => $email_title,
                    ],
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p style=\"margin: 0 0 16px;\"><span>' . $email_hi . '</span></p><p style=\"margin: 0 0 16px;\"><span>' . $email_text . '</span></p><p style=\"margin: 0 0 16px;\"><span>' . $text_username . ': [o100_customer_username]</span></p><p style=\"margin: 0 0 16px;\"><span>' . $email_text_1 . '</span></p><p style=\"margin: 0 0 16px;\"><span>[o100_password_reset_link]</span></p><p style=\"margin: 0 0 16px;\"><span>' . $additional_text . '</span></p>',
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
        return o100ne_get_template( 'emails/customer-reset-password.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}




// TS: 20260104123439

// TS: 20260214215521

// TS: 20260531164604
