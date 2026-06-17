<div id="o100ne-upgrade-notice" class="notice-info notice is-dismissible">
    <h4 style="color: #000"><?php esc_html_e( 'Recommended: You can use O100ne Pro to integrate with:', 'order100' ); ?></h4>
    <ul style="list-style: inside;">
        <?php

        if ( ! empty( $pro_needed_plugins ) ) {

            foreach ( $pro_needed_plugins as $namespace => $third_party ) {
                ?>
                <li><?php echo esc_html( $third_party['plugin_name'] ); ?></li>
                <?php
            }
        }
        ?>
    </ul>
    <p style="padding-left:0">
        <a href="https://o100ne.com/o100ne-woocommerce-email-customizer/" target="_blank" data="upgradenow" class="button button-primary o100ne-upgrade-pro" style="margin-right: 5px"><?php esc_html_e( 'Upgrade to Pro', 'order100' ); ?></a>
        <a href="javascript:;" data="later" class="o100ne-dismiss-upgrade-notice" style="margin-right: 5px"><?php esc_html_e( 'No, thanks', 'order100' ); ?></a>
    </p>
</div>