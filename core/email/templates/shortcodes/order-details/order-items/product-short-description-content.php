<?php

defined( 'ABSPATH' ) || exit;

?>
<div class="o100ne-product-short-description">
    <?php
        echo wp_kses_post( "(#{$short_description})" );
    ?>
    </div>
<?php

// TS: 20260331004200
