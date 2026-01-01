<?php
define('WP_USE_THEMES', false);
require_once("/Users/kevinqi/Local Sites/cmaa/app/public/wp-load.php");
echo "Loaded WP\n";
try {
    echo do_shortcode('[order100_menu]');
    echo "\nRender Success\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
} catch (Error $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

