<?php
define('WP_USE_THEMES', false);
require_once('/Users/kevinqi/Local Sites/local-test/app/public/wp-load.php');
$opts = get_option('o100_options');
file_put_contents('/Users/kevinqi/development/antigravity/order100/debug_render.txt', json_encode($opts['o100_Mon_opcl_time']));
echo "Done";




// TS: 20260111112425

// TS: 20260403222206

// TS: 20260519122109
