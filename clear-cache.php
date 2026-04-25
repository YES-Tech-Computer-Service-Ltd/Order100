<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset successful!<br>";
} else {
    echo "OPcache not enabled or not available.<br>";
}
echo "Time: " . date('Y-m-d H:i:s');


// TS: 20260424233105
