<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Order100\Notification\Engine\Utils\Helpers;

if ( ! isset( $args['order'] ) || ! ( Helpers::is_woocommerce_order( $args['order'] ) ) ) {
    return;
}

$order_instance  = $args['order'];
$billing_address = $order_instance->get_formatted_billing_address();

// Strip recipient name — WooCommerce includes first+last as first line
if ( ! empty( $billing_address ) ) {
    $name_line = trim( $order_instance->get_billing_first_name() . ' ' . $order_instance->get_billing_last_name() );
    if ( ! empty( $name_line ) ) {
        $billing_address = preg_replace( '/^\s*' . preg_quote( $name_line, '/' ) . '\s*(<br\s*\/?>)?/i', '', $billing_address );
        $billing_address = ltrim( $billing_address );
    }
}

if ( ! empty( $billing_address ) ) :
    $billing_phone = $order_instance->get_billing_phone();
    $billing_email = $order_instance->get_billing_email();
    ?>
    <address>
            <?php echo wp_kses_post( $billing_address ); ?>
            <?php if ( ! empty( $billing_phone ) ) : ?>
                <br/>
                <a href='tel:<?php echo esc_attr( $billing_phone ); ?>' style="font-family: inherit">
                    <?php echo esc_html( $billing_phone ); ?>
                </a>
            <?php endif; ?>
            <?php if ( ! empty( $billing_email ) ) : ?>
                <br/>
                <a href='mailto:<?php echo esc_attr( $billing_email ); ?>' style="font-family: inherit">
                    <?php echo esc_html( $billing_email ); ?>
                </a>
            <?php endif; ?>
    </address>
        <?php
endif;



