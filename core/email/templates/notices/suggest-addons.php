<div id="o100ne-suggest-addons-notice" class="notice-info notice is-dismissible">
    <h4 style="color: #000"><?php esc_html_e( 'Recommended: You can use O100ne to customize all email templates of:', 'order100' ); ?></h4>
    <ul style="list-style: inside;">
        <?php
        if ( ! empty( $addon_needed_plugins ) ) {

            foreach ( $addon_needed_plugins as $namespace => $third_party ) {
                ?>
                <li><?php echo esc_html( sprintf( __( '%s (Addon)', 'order100' ), $third_party['plugin_name'] ) ); ?></li>
    
                <?php
            }
        }
        ?>
    </ul>
    <p style="padding-left:0">
        <a href="https://o100ne.com/o100ne-addons/" target="_blank" data="upgradenow" class="button button-primary o100ne-see-addons" style="margin-right: 5px"><?php esc_html_e( 'See Addons', 'order100' ); ?></a>
        <a href="javascript:;" data="later" class="o100ne-dismiss-suggest-addons-notice" style="margin-right: 5px"><?php esc_html_e( 'No, thanks', 'order100' ); ?></a>
    </p>
</div>



// TS: 20260126122334

// TS: 20260201151221

// TS: 20260220173750
