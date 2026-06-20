<?php
$file = '/Users/kevinqi/development/antigravity/order100/core/email/src/Engine/Backend/SettingsPage.php';
$content = file_get_contents($file);

// Replace localize_js_vars entirely
$search = 'public function localize_js_vars() {';
$replace = 'public function localize_js_vars() { error_log("localize_js_vars CALLED!"); wp_add_inline_script("module/o100ne/src/main.tsx", "var o100neData = {test: 123};", "before"); ';

$content = preg_replace('/public function localize_js_vars\(\) \{/', $replace, $content, 1);
file_put_contents($file, $content);
