<?php
defined( 'ABSPATH' ) || exit;
$current_version = get_option( 'o100_version' );
if ( empty( $current_version ) ) {
	update_option( 'o100_version', O100NE_VERSION );
}
?>
<style>
    /* Add any custom WP backend overrides here if needed */
    #o100ne-main-pages {
        margin: 20px 20px 0 0;
        background: #fff;
        min-height: calc(100vh - 100px);
        box-shadow: 0 1px 3px rgba(0,0,0,.13);
    }
</style>

<script>
// Alert on errors
var _o100ne_errors = [];
window.addEventListener('error', function(e) {
    _o100ne_errors.push('JS: ' + e.message + ' @ ' + (e.filename||'').split('/').pop() + ':' + e.lineno);
});
window.addEventListener('unhandledrejection', function(e) {
    _o100ne_errors.push('Promise: ' + (e.reason && e.reason.message ? e.reason.message : String(e.reason)));
});

// Intercept fetch
var _origFetch = window.fetch;
window.fetch = function(url, opts) {
    return _origFetch.apply(this, arguments).then(function(resp) {
        if (resp.status >= 400) {
            resp.clone().text().then(function(body) {
                _o100ne_errors.push('API ' + resp.status + ': ' + (typeof url === 'string' ? url.replace(/^https?:\/\/[^\/]+/, '') : '') + '\n' + body.substring(0, 200));
            });
        }
        return resp;
    }).catch(function(err) {
        _o100ne_errors.push('FETCH FAIL: ' + err.message);
        throw err;
    });
};

// Show errors after 5 seconds
setTimeout(function() {
    if (_o100ne_errors.length > 0) {
        alert('O100NE Debug (' + _o100ne_errors.length + ' errors):\n\n' + _o100ne_errors.join('\n\n'));
    }
}, 5000);
</script>

<!-- Hide branding tabs -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var hide = ['Go Pro', 'Addons', 'Documentation', 'Support'];
    function h() {
        document.querySelectorAll('a, button').forEach(function(el) {
            if (hide.indexOf((el.textContent || '').trim()) !== -1) {
                var p = el.closest('li') || el.parentElement;
                if (p) p.style.display = 'none';
            }
        });
    }
    h();
    var ob = new MutationObserver(h);
    var t = document.getElementById('o100ne-main-pages');
    if (t) { ob.observe(t, {childList:true,subtree:true}); setTimeout(function(){ob.disconnect();},10000); }
});
</script>

<div style="display: none;">
    <?php
    wp_editor( '', 'o100ne-wp-editor-placeholder', [
        'quicktags' => false, 'media_buttons' => true, 'tinymce' => true,
    ]);
    ?>
</div>
<div id="o100ne-main-pages">
    <div class="o100ne-pre-loading" style="width:20px;height:20px;background:url(images/spinner-2x.gif);background-size:contain;background-repeat:no-repeat;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);"></div>
</div>



