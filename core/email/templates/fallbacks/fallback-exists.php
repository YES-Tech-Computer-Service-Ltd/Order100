<?php

defined( 'ABSPATH' ) || exit;
add_action(
    'admin_notices',
    function() {
        if ( current_user_can( 'activate_plugins' ) ) {
            ?>
                <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'It looks like you have another O100ne version installed, please delete it before activating this new version. All current settings and data are still preserved.', 'order100' ); ?>
                    <a href="https://docs.o100ne.com/o100ne/getting-started/how-to-update-o100ne"><?php esc_html_e( 'Read more details.', 'order100' ); ?></a>
                    </strong>
                </p>
                </div>
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( isset( $_GET['activate'] ) ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                unset( $_GET['activate'] );
            }
        }
    }
);

// TS: 20260311161318
