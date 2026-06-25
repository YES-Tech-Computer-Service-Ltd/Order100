<?php
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'order100_page_o100-notifications') {
        echo "<script>
        window.addEventListener('error', function(e) {
            fetch('/wp-admin/admin-ajax.php?action=o100_log_js_error', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'msg=' + encodeURIComponent(e.message + ' at ' + e.filename + ':' + e.lineno)
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                let domHtml = '';
                let el = document.getElementById('o100ne-main-pages');
                if (el) {
                    domHtml = el.outerHTML;
                } else {
                    domHtml = 'NOT_FOUND';
                }
                fetch('/wp-admin/admin-ajax.php?action=o100_log_js_error', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'msg=' + encodeURIComponent('DOM Check: ' + domHtml)
                });
            }, 3000);
        });
        </script>";
    }
});

add_action('wp_ajax_o100_log_js_error', function() {
    $msg = isset($_POST['msg']) ? $_POST['msg'] : '';
    file_put_contents('/Users/kevinqi/development/antigravity/order100/diag_react.txt', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
    wp_send_json_success();
});
