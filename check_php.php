<?php
$file = __DIR__ . '/core/class-o100-settings.php';
$content = file_get_contents($file);
if (strpos($content, 'DEBUG: Found IDs:') !== false) {
    echo "YES! The PHP file contains the DEBUG logic.\n";
} else {
    echo "NO! The PHP file DOES NOT contain the DEBUG logic.\n";
}


