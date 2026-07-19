<?php
add_action('admin_footer', function() {
    echo "<script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                var el = document.getElementById('o100ne-main-pages');
                if (el && el.innerHTML.trim() === '') {
                    var debugBox = document.createElement('div');
                    debugBox.style.cssText = 'position:fixed;top:0;left:0;width:100%;background:red;color:white;z-index:999999;padding:20px;';
                    debugBox.innerHTML = '<h1>REACT IS BLANK!</h1><pre>' + JSON.stringify(window._o100_errors, null, 2) + '</pre>';
                    document.body.appendChild(debugBox);
                }
            }, 3000);
        });
        window._o100_errors = [];
        window.addEventListener('error', function(e) { window._o100_errors.push(e.message); });
        window.addEventListener('unhandledrejection', function(e) { window._o100_errors.push(e.reason ? e.reason.message : 'Promise rejection'); });
    </script>";
});
