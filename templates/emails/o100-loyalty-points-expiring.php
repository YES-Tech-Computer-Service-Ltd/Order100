<?php
/**
 * Email Template: o100-loyalty-points-expiring
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Hello {user_name},', 'order100' ); ?></p>
<p><?php esc_html_e( 'This is a friendly reminder that you have {points} loyalty points expiring on {expiry_date}.', 'order100' ); ?></p>
<p><?php esc_html_e( 'Don\'t miss out! Visit our store to redeem them before they disappear.', 'order100' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
