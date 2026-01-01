<?php
add_filter('woocommerce_coupon_error', function($err, $err_code, $coupon) {
    error_log("Coupon Error: " . $err . " | Code: " . $err_code . " | Coupon: " . ($coupon ? $coupon->get_code() : 'none'));
    return $err;
}, 10, 3);
add_filter('wlr_reward_coupon_is_valid', function($is_valid, $coupon, $user_reward) {
    error_log("WLR is_valid: " . ($is_valid ? 'true' : 'false') . " | Coupon: " . $coupon->get_code());
    return $is_valid;
}, 10, 3);
