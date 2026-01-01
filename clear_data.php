<?php
$wp_load_path = '/Users/kevinqi/Local Sites/local-test/app/public/wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Could not find wp-load.php at " . $wp_load_path);
}
require_once $wp_load_path;

global $wpdb;

echo "<h2>Order100 Database Cleanup Utility - Phase 5 (Promo Module Wipe)</h2>";

if (class_exists('O100_Promotions_DB')) {
    $promo_table = O100_Promotions_DB::table_name();
    if ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $promo_table WHERE source = 'loyalty_punch'");
        if ($count > 0) {
            $wpdb->query("DELETE FROM $promo_table WHERE source = 'loyalty_punch'");
            echo "<p>Deleted <strong>$count</strong> punch card coupons from the native Order100 Promotions module (<strong>$promo_table</strong>).</p>";
        } else {
            echo "<p>No loyalty_punch coupons found in $promo_table.</p>";
        }
    }
} else {
    // Hard fallback if class isn't loaded
    $promo_table = $wpdb->prefix . 'o100_promotions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$promo_table'") === $promo_table) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $promo_table WHERE source = 'loyalty_punch'");
        if ($count > 0) {
            $wpdb->query("DELETE FROM $promo_table WHERE source = 'loyalty_punch'");
            echo "<p>Deleted <strong>$count</strong> punch card coupons from $promo_table.</p>";
        }
    }
}

echo "<h3>Promo Wipe Complete!</h3>";
echo "<p>This was the ultimate hidden boss. The coupon should now be permanently gone.</p>";
