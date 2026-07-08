<?php
/**
 * Email Template: o100-loyalty-punch-card-update (Plain)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

echo "= " . $email_heading . " =\n\n";

echo "Hello {user_name},\n\n";
echo "Great news! You have just earned {earned_stamps} new punch card stamp(s).\n\n";
echo "You currently have {total_stamps} stamps. You need {required_stamps} stamps to unlock your free reward!\n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
