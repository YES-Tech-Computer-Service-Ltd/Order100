<?php
/**
 * Email Template: o100-loyalty-referral-reward
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Hello {user_name},', 'order100' ); ?></p>
<p><?php esc_html_e( 'Here is a special message for you.', 'order100' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
