<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Order100\Notification\Engine\Utils\Helpers;

if ( ! isset( $args['order'] ) || ! ( Helpers::is_woocommerce_order( $args['order'] ) ) ) {
    return;
}

$order_instance   = $args['order'];
$shipping_address = apply_filters( 'o100_shipping_address_content', $order_instance->get_formatted_shipping_address(), $order_instance );

// Strip recipient name from the address — WooCommerce includes first+last name
// as the first line, but [o100_shipping_address] should only contain the physical address
if ( ! empty( $shipping_address ) ) {
    $name_line = trim( $order_instance->get_shipping_first_name() . ' ' . $order_instance->get_shipping_last_name() );
    if ( ! empty( $name_line ) ) {
        // Remove the name line (with optional trailing <br/> or <br>)
        $shipping_address = preg_replace( '/^\s*' . preg_quote( $name_line, '/' ) . '\s*(<br\s*\/?>)?/i', '', $shipping_address );
        $shipping_address = ltrim( $shipping_address );
    }
}

if ( ! empty( $shipping_address ) ) :
    $shipping_phone = $order_instance->get_shipping_phone();
    ?>
    <address>
            <?php echo wp_kses_post( $shipping_address ); ?>
            <?php if ( ! empty( $shipping_phone ) ) : ?>
            <br/>
            <a href='tel:<?php echo esc_attr( $shipping_phone ); ?>' style="font-family: inherit">
                <?php echo esc_html( $shipping_phone ); ?>
            </a>
        <?php endif; ?>
    </address>
        <?php
endif;

