<?php
require_once("/Users/kevinqi/Local Sites/cmaa/app/public/wp-load.php");
$active_plugins = get_option('active_plugins');
print_r($active_plugins);
echo "Class exists? " . (class_exists('O100_Menu_Renderer') ? "YES" : "NO");


// TS: 20260510221958
