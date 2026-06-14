<?php

defined( 'ABSPATH' ) || exit;

?>
<div class='o100ne-product-image'>
    <?php
        empty( $item ) ? o100ne_kses_post_e( $image ) : o100ne_kses_post_e( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
    ?>
    </div>
<?php

