<?php

use Order100\Notification\Engine\License\License;

defined( 'ABSPATH' ) || exit;

$class             = 'notice notice-error';
$licensing_plugins = $this->get_licensing_plugins();
foreach ( $licensing_plugins as $_plugin ) {
    $license = new License( $_plugin['slug'] );
    if ( $license->is_expired() ) {
        $message = "Your license key for {$_plugin['name']} has expired.";
        ?>
            <div class="<?php echo esc_attr( $class ); ?>">
                <p>
                    <?php echo esc_html( $message ); ?>
                    <a href="<?php echo esc_url( $license->get_renewal_url() ); ?>" target="_blank">
                        <?php echo esc_html__( 'Please click here to renew your license key and continue receiving automatic updates.', 'order100' ); ?>
                    </a>
                </p>
            </div>
        <?php
    }
}

