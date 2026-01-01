<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Abstracts\BaseShortcode;

/**
 * @method: static BillingShortcodes get_instance()
 */
class BillingShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'o100_billing_address',
            'description' => __( 'Billing Address', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_address' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_address_1',
            'description' => __( 'Billing Address 1', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_address_1' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_address_2',
            'description' => __( 'Billing Address 2', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_address_2' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_first_name',
            'description' => __( 'Billing First Name', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_first_name' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_last_name',
            'description' => __( 'Billing Last Name', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_last_name' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_company',
            'description' => __( 'Billing Company', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_company' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_city',
            'description' => __( 'Billing City', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_city' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_country',
            'description' => __( 'Billing Country', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_country' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_state',
            'description' => __( 'Billing State', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_state' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_postcode',
            'description' => __( 'Billing Postal Code', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_postcode' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_phone',
            'description' => __( 'Billing Phone', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_phone' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_email',
            'description' => __( 'Billing Email', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_email' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_billing_shipping_address',
            'description' => __( 'Billing Shipping Address', 'order100' ),
            'group'       => 'billings',
            'callback'    => [ $this, 'o100_billing_shipping_address' ],
        ];
        return $shortcodes;
    }

    /**
     * Render order billing shortcode
     *
     * @param $args includes
     * $render_data
     * $element
     * $settings
     * $is_placeholder
     */
    public function o100ne_billing_shipping_address( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $template = ! empty( $data['template'] ) ? $data['template'] : null;
        if ( empty( $template ) ) {
            $text_link_color = O100NE_COLOR_WC_DEFAULT;
        } else {
            $text_link_color = $template->get_text_link_color();
        }

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $args = [
                'text_link_color' => $text_link_color,
            ];
            $html = o100ne_get_content( 'templates/shortcodes/billing-shipping-address/sample.php', $args );
            return $html;
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $args = [
            'order'           => $order,
            'text_link_color' => $text_link_color,
        ];
        $html = o100ne_get_content( 'templates/shortcodes/billing-shipping-address/main.php', $args );
        return $html;
    }

    public function o100ne_billing_address( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = o100ne_get_content( 'templates/shortcodes/billing-address/sample.php' );
            return $html;
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $args = [
            'order' => $order,
        ];
        $html = o100ne_get_content( 'templates/shortcodes/billing-address/main.php', $args );
        return $html;
    }

    public function o100ne_billing_address_1( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '7400 Edwards Rd', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_address_1() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_address_1();
    }

    public function o100ne_billing_address_2( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '7400 Edwards Rd', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_address_2() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_address_2();
    }

    public function o100ne_billing_first_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'John', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_first_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_first_name();
    }

    public function o100ne_billing_last_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Doe', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_last_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_last_name();
    }

    public function o100ne_billing_company( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'O100ne', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_company() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_company();
    }

    public function o100ne_billing_city( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Edwards Rd', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_city() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_city();
    }

    public function o100ne_billing_country( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'United States', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_country() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $order_billing_country_code = $order->get_billing_country();
        $wc_countries               = \WC()->countries;
        return $wc_countries->countries[ $order_billing_country_code ];
    }

    public function o100ne_billing_state( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Random', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_state() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_state();
    }

    public function o100ne_billing_postcode( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '48744', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_postcode() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_postcode();
    }

    public function o100ne_billing_phone( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '(910) 529-1147', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_phone() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_billing_phone();
    }

    public function o100ne_billing_email( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $template = ! empty( $data['template'] ) ? $data['template'] : null;
        if ( empty( $template ) ) {
            $text_link_color = O100NE_COLOR_WC_DEFAULT;
        } else {
            $text_link_color = $template->get_text_link_color();
        }

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '<a href="#">' . __( 'johndoe@gmail.com', 'order100' ) . '</a>';

        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_billing_email() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return '<a href="mailto:' . esc_url( $order->get_billing_email() ) . '">' . $order->get_billing_email() . '</a>';
    }
}

