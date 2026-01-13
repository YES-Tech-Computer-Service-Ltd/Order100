<?php

namespace Order100\Notification\Engine\Emails;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\O100neTemplate;

/**
 * CustomerRefundedOrder Class
 *
 * @method static CustomerRefundedOrder get_instance()
 */
class CustomerRefundedOrder extends BaseEmail {
    use SingletonTrait;

    protected function __construct() {
        $emails = \WC_Emails::instance()->get_emails();
        $email  = $emails['WC_Email_Customer_Refunded_Order'];
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
        $email_title = '[o100_get_heading]';
        // translators: customer name.
        $email_hi = sprintf( esc_html__( 'Hi %s,', 'woocommerce' ), '[o100_billing_first_name]' );
        // translators: site name.
        $email_text      = sprintf( esc_html__( 'Your order on %s has been %s. There are more details below for your reference:', 'woocommerce' ), '[o100_site_name]', '[o100_refund_type]' );
        $additional_text = __( 'We hope to see you again soon.', 'woocommerce' );

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
                        'rich_text' => '<p><span>' . $email_hi . '<br /></span></p><p><span>' . $email_text . '</span></p>',
                    ],
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

    public function get_template_file( $located, $template_name, $args ) {
        if ( ! isset( $args['email'] ) ) {
            return $located;
        }
        if ( ! $args['email'] instanceof \WC_Email || ! $args['email'] instanceof \WC_Email_Customer_Refunded_Order ) {
            return $located;
        }
        $template_path = $this->get_template_path();
        if ( ! file_exists( $template_path ) ) {
            return $located;
        }

        $order = apply_filters( 'o100_order_for_language', isset( $args['order'] ) ? $args['order'] : null, $args );

        $language = $this->get_language( $order );

        $this->template = new O100neTemplate( $this->id, $language );

        if ( ! $this->template->is_enabled() ) {
            return $located;
        }

        return $template_path;
    }

    public function get_template_path() {
        return o100ne_get_template( 'emails/customer-refunded-order.php', '', O100NE_PLUGIN_PATH . 'templates/' );
    }
}


// TS: 20260113143610
