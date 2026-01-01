<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Abstracts\BaseShortcode;
use Order100\Notification\Engine\Utils\TemplateHelpers;

/**
 * @method: static PaymentsShortcodes get_instance()
 */
class PaymentsShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'o100_order_payment_method',
            'description' => __( 'Payment method', 'order100' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'o100_order_payment_method' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_payment_link',
            'description' => __( 'Payment Link', 'order100' ),
            'attributes'  => [
                'text_link' => __( 'Payment page', 'order100' ),
            ],
            'group'       => 'payments',
            'callback'    => [ $this, 'o100_order_payment_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_payment_url',
            'description' => __( 'Payment URL (String)', 'order100' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'o100_order_payment_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_payment_instructions',
            'description' => __( 'Payment Instructions', 'order100' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'o100_payment_instructions' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_payment_transaction_id',
            'description' => __( 'Payment Transaction ID', 'order100' ),
            'group'       => 'payments',
            'callback'    => [ $this, 'o100_payment_transaction_id' ],
        ];
        return $shortcodes;
    }

    public function o100ne_order_payment_method( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Direct bank transfer', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_item_totals = $order->get_order_item_totals();

        return isset( $order_item_totals['payment_method']['value'] ) ? $order_item_totals['payment_method']['value'] : '';
    }

    public function o100ne_order_payment_link( $data, $shortcode_atts = [] ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Payment page', 'order100' ), $is_placeholder );

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '<a href="#">' . $text_link . '</a>';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . $text_link . '</a>';
    }

    public function o100ne_order_payment_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( wc_get_endpoint_url( 'order-pay', 0, wc_get_checkout_url() ) );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_checkout_payment_url();
    }

    public function o100ne_payment_instructions( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Payment Instructions', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $args = [
            'order' => $order,
        ];

        $html = o100ne_get_content( 'templates/shortcodes/payment-instruction/main.php', $args );
        return $html;
    }

    public function o100ne_payment_transaction_id( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_transaction_id() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_transaction_id();
    }
}

