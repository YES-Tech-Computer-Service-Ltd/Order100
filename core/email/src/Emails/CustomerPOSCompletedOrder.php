<?php

namespace Order100\Notification\Engine\Emails;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * CustomerPOSCompletedOrder Class
 * From WC 9.9.3
 *
 * @since 4.0.6
 * @method static CustomerPOSCompletedOrder get_instance()
 */
class CustomerPOSCompletedOrder extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_POS_Completed_Order'] ?? null;
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
        $email_title = __( 'Thanks for shopping with us', 'woocommerce' );
        // translators: customer name.
        $email_hi        = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[o100_billing_first_name]' );
        $email_text      = esc_html__( 'We have finished processing your order.', 'woocommerce' );
        $additional_text = __( 'Thanks for shopping with us.', 'woocommerce' );
        $additional_text = str_replace( '{site_url}!', '', $additional_text );

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
                        'rich_text' => '<p><span>' . $email_hi . '<br /><br /></span></p><p><span>' . $email_text . '</span></p>',
                    ],
                ],
                [
                    'type' => 'OrderDetailsDownload',
                ],
                [
                    'type' => 'OrderDetails',
                ],
                [
                    'type' => 'BillingShippingAddress',
                ],
                [
                    'type'       => 'Text',
                    'attributes' => [
                        'rich_text' => '<p><span>' . $additional_text . '</span></p>',
                        'padding'   => [
                            'top'    => '0',
                            'right'  => '50',
                            'bottom' => '38',
                            'left'   => '50',
                        ],
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
        return o100ne_get_template( 'emails/customer-pos-completed-order.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}

// TS: 20260111142730

// TS: 20260324014806
