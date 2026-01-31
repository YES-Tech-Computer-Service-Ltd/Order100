<?php
defined( 'ABSPATH' ) || exit;

if ( ! $show_hyper_links || $is_placeholder ) {
    ?>
        <div class="o100ne-product-name">
            <?php
            if ( empty( $item ) ) {
                echo wp_kses_post( $product_name );
            } else {
                echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $product_name, $item, false ) );
            }
            ?>
        </div>
    <?php
}

if ( $show_hyper_links || $is_placeholder ) {
    ?>
        <div class="o100ne-product-name o100ne-product-name__hyper-link">
            <?php
            if ( empty( $item ) ) {
                echo wp_kses_post( $product_hyper_link );
            } else {
                echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $product_hyper_link, $item, false ) );
            }
            ?>
        </div>
    <?php
}






// TS: 20260130215710
