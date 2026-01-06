<?php
$content = file_get_contents('/Users/kevinqi/development/antigravity/order100/core/class-o100-menu-renderer.php');
preg_match('/echo \'<script>(.*?)<\/script>\';/s', $content, $matches);
if (!empty($matches[1])) {
    $js = $matches[1];
    // Remove PHP string interpolation variables or syntax that might break JS linting
    $js = str_replace('\\\'', '\'', $js); // PHP unescapes \'
    $js = str_replace('\\\\', '\\', $js); // PHP unescapes \\
    file_put_contents('/Users/kevinqi/development/antigravity/order100/test.js', $js);
    echo "Extracted JS. Linting...\n";
    system('node -c /Users/kevinqi/development/antigravity/order100/test.js 2>&1');
} else {
    echo "No JS found.";
}

// TS: 20260106104601
