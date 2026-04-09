<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Abstracts\BaseShortcode;

/**
 * @method: static ShippingShortcodes get_instance()
 */
class ShippingShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'o100_shipping_address',
            'description' => __( 'Shipping Address', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_address' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_address_1',
            'description' => __( 'Shipping Address 1', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_address_1' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_address_2',
            'description' => __( 'Shipping Address 2', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_address_2' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_first_name',
            'description' => __( 'Shipping First Name', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_first_name' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_last_name',
            'description' => __( 'Shipping Last Name', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_last_name' ],
        ];

        $shortcodes[] = [
            'name'        => 'o100_shipping_company',
            'description' => __( 'Shipping Company', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_company' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_city',
            'description' => __( 'Shipping City', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_city' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_country',
            'description' => __( 'Shipping Country', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_country' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_state',
            'description' => __( 'Shipping State', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_state' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_postcode',
            'description' => __( 'Shipping Postal Code', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_postcode' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_phone',
            'description' => __( 'Shipping Phone', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_phone' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_method',
            'description' => __( 'Shipping Method', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_method' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_shipping_total',
            'description' => __( 'Shipping Total', 'order100' ),
            'group'       => 'shippings',
            'callback'    => [ $this, 'o100_shipping_total' ],
        ];
        return $shortcodes;
    }

    /**
     * Render order shipping shortcode
     *
     * @param $args includes
     * $render_data
     * $element
     * $settings
     * $is_placeholder
     */
    public function o100ne_shipping_address( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = o100ne_get_content( 'templates/shortcodes/shipping-address/sample.php' );
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
        $html = o100ne_get_content( 'templates/shortcodes/shipping-address/main.php', $args );
        return $html;
    }

    public function o100ne_shipping_address_1( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '755 E North Grove Rd', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_address_1() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_address_1();
    }

    public function o100ne_shipping_address_2( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '755 E North Grove Rd', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_address_2() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_address_2();
    }

    public function o100ne_shipping_first_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'John', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_first_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_first_name();
    }

    public function o100ne_shipping_last_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Doe', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_last_name() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_last_name();
    }

    public function o100ne_shipping_company( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'O100ne', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_company() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_company();
    }

    public function o100ne_shipping_city( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Mayville, Michigan', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_city() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_city();
    }

    public function o100ne_shipping_country( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'United States', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_country() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $order_shipping_country_code = $order->get_shipping_country();
        $wc_countries                = \WC()->countries;
        return $wc_countries->countries[ $order_shipping_country_code ];
    }

    public function o100ne_shipping_state( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Random', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_state() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_state();
    }

    public function o100ne_shipping_postcode( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '48744', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_postcode() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_postcode();
    }

    public function o100ne_shipping_phone( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '(910) 529-1147', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( ! empty( $order ) && method_exists( $order, 'get_shipping_phone' ) && ! empty( $order->get_shipping_phone() ) ) {
            return $order->get_shipping_phone();
        }

        // Not having order_id or empty shipping phone
        return '';
    }

    public function o100ne_shipping_method( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'Free shipping', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->get_shipping_method() ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_shipping_method();
    }

    public function o100ne_shipping_total( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( 0 );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) || empty( $order->calculate_shipping() ) ) {
            /**
             * Not having order_id
             */
            return wc_price( 0 );
        }

        if ( isset( $order->get_data()['shipping_total'] ) && ! empty( $order->get_data()['shipping_total'] ) ) {
            return wc_price( $order->get_data()['shipping_total'] + $order->get_data()['shipping_tax'], [ 'currency' => $order->get_currency() ] );
        } else {
            return wc_price( 0, [ 'currency' => $order->get_currency() ] );
        }
    }
}


// TS: 20260329130231

// TS: 20260408195903
