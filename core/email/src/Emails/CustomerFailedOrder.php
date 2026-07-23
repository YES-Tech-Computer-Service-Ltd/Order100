<?php

namespace Order100\Notification\Engine\Emails;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * CustomerFailedOrder Class
 *
 * @method static CustomerFailedOrder get_instance()
 */
class CustomerFailedOrder extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_Failed_Order'] ?? null;
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
        $email_title  = __( 'Sorry, your order was unsuccessful', 'woocommerce' );
        $email_text_1 = __( 'Hi [o100_billing_first_name]', 'woocommerce' );
        $email_text_2 = __( 'Unfortunately, we couldn\'t complete your order due to an issue with your payment method.', 'woocommerce' );
        $email_text_3 = __( 'If you\'d like to continue with your purchase, please return to [o100_site_name] and try a different method of payment.', 'woocommerce' );
        $email_text_4 = __( 'Your order details are as follows:', 'woocommerce' );

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
                        'rich_text' => '<p><span>' . $email_text_1 . '</span></p><br/><p><span>' . $email_text_2 . '</span></p><br/><p><span>' . $email_text_3 . '</span></p><br/><p><span>' . $email_text_4 . '</span></p>',
                    ],
                ],
                [
                    'type' => 'OrderDetails',
                ],
                [
                    'type' => 'BillingShippingAddress',
                ],
                [
                    'type' => 'Footer',
                ],
            ]
        );
        return $default_elements;
    }

    public function get_template_path() {
        return o100ne_get_template( 'emails/customer-failed-order.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}
