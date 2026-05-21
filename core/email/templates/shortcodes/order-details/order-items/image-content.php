<?php

defined( 'ABSPATH' ) || exit;

?>
<div class='o100ne-product-image'>
    <?php
        empty( $item ) ? o100ne_kses_post_e( $image ) : o100ne_kses_post_e( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
    ?>
    </div>
<?php


// TS: 20260107122214

// TS: 20260116121926

// TS: 20260215005541

// TS: 20260521164429
