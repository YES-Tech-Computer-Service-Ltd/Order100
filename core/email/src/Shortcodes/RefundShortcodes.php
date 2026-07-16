<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Abstracts\BaseShortcode;

/**
 * @since 4.0.6
 * @method: static RefundShortcodes get_instance()
 */
class RefundShortcodes extends BaseShortcode {
    use SingletonTrait;

    protected function __construct() {
        $this->available_email_ids = [ 'customer_refunded_order', 'customer_pos_refunded_order' ];
        parent::__construct();
    }

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'o100_refund_type',
            'description' => __( 'Refund Type', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100ne_refund_type' ],
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
    public function o100ne_refund_type( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( '(partially) refunded', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order
             */
            return '';
        }

        return ! empty( $render_data['partial_refund'] ) ? __( 'partially refunded', 'woocommerce' ) : __( 'refunded', 'woocommerce' );
    }
}



