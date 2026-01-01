<?php
$content = file_get_contents('/Users/kevinqi/development/antigravity/order100/core/class-o100-menu-renderer.php');
if (preg_match("/echo '<script>([\s\S]*?)<\/script>';/", $content, $matches)) {
    $js = $matches[1];
    $js = str_replace("\\\'", "'", $js);
    file_put_contents('test.js', $js);
}

