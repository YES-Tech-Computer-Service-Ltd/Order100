<?php
$file = '/Users/kevinqi/development/antigravity/order100/core/email/src/Engine/Backend/SettingsPage.php';
$content = file_get_contents($file);

$search = "wp_localize_script(";
$replace = "error_log('LOCALIZE JS VARS DATA DUMP: ' . print_r(array_merge([\n                    'is_rtl' => is_rtl(),\n                ], \$_wc_emails), true));\n        wp_localize_script(";

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
