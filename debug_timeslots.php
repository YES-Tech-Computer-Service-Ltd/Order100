<?php
define('WP_USE_THEMES', false);
require_once('/Users/kevinqi/Local Sites/local-test/app/public/wp-load.php');
$opts = get_option('o100_options');
file_put_contents('/Users/kevinqi/development/antigravity/order100/debug_ts_out.txt', "Generated Timeslots:\n" . print_r($opts['o100_generated_timeslots'], true));
echo "Done";
