<?php
define('WP_USE_THEMES', false);
require_once('/Users/kevinqi/Local Sites/local-test/app/public/wp-load.php');
$opts = get_option('o100_options');
$output = "Mon:\n" . print_r($opts['o100_Mon_opcl_time'], true);
$output .= "\n\nSat:\n" . print_r($opts['o100_Sat_opcl_time'], true);
file_put_contents('/Users/kevinqi/development/antigravity/order100/debug_out.txt', $output);
echo "Done";




