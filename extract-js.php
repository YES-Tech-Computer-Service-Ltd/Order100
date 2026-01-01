<?php
ob_start();
require_once("core/class-o100-menu-renderer.php");
// Mock class to avoid fatal errors if Order100 is not loaded
class O100_Public { public static function get_options() { return []; } public static function get_food_labels_html() { return ""; } }
try {
    O100_Menu_Renderer::render_shortcode(array());
} catch (Throwable $e) {}
$html = ob_get_clean();

preg_match('/<script>(.*?)<\/script>/s', $html, $matches);
if (!empty($matches[1])) {
    file_put_contents('test.js', $matches[1]);
    echo "Extracted JS.\n";
} else {
    echo "No JS found.\n";
}


