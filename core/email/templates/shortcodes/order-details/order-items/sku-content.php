<?php

defined( 'ABSPATH' ) || exit;

?>
    <div class="o100ne-product-sku">
<?php
    echo wp_kses_post( ' (#' . $sku . ')' );
?>
    </div>
<?php

