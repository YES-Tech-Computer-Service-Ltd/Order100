<?php
/**
 * Email Template: o100-loyalty-points-expiring
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

?>
Hello {user_name},

This is a friendly reminder that you have {points} loyalty points expiring on {expiry_date}.
Don't miss out! Visit our store to redeem them before they disappear.

<?php echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n"; ?>
