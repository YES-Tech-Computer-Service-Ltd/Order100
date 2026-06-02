<?php
$content = file_get_contents('/Users/kevinqi/development/antigravity/order100/core/class-o100-settings.php');
if (strpos($content, '$form[0].submit();') !== false) {
    echo "Found \$form[0].submit();\n";
} else {
    echo "Not found.\n";
}


// TS: 20260602120733
