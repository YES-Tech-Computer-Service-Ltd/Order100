<?php
/**
 * Email Template: o100-loyalty-punch-card-update
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Hello {user_name},', 'order100' ); ?></p>
<p><?php esc_html_e( 'Great news! You have just earned {earned_stamps} new punch card stamp(s).', 'order100' ); ?></p>
<p><?php esc_html_e( 'You currently have {total_stamps} stamps. You need {required_stamps} stamps to unlock your free reward!', 'order100' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
