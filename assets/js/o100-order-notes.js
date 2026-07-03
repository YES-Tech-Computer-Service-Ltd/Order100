(function ($) {
    'use strict';

    // ---- Settings ----
    var LS_KEY = 'o100_order_note_cache_v1';

    // Common selectors for classic checkout + various block renderings
    var NOTE_SELECTORS = [
        'textarea[name="order_comments"]',
        'textarea#order_comments',
        // Block-based/possible variants:
        'textarea[id*="order-notes"]',
        'textarea[name*="order-notes"]',
        'textarea[class*="order-notes"]',
        'textarea[class*="wc-block"]'
    ];

    function isCheckoutLikePage() {
        // Classic form or blocks container
        return !!document.querySelector('form.checkout, .wc-block-checkout, .wp-block-woocommerce-checkout');
    }

    function findNoteField() {
        for (var i = 0; i < NOTE_SELECTORS.length; i++) {
            var el = document.querySelector(NOTE_SELECTORS[i]);
            if (el && el.tagName === 'TEXTAREA') return el;
        }
        return null;
    }

    function post(action, data, cb) {
        $.ajax({
            url: o100_order_notes_vars.ajax_url,
            type: 'POST',
            data: $.extend({ action: action, security: o100_order_notes_vars.nonce }, data),
            dataType: 'json',
            success: function (resp) {
                if (cb) cb(resp);
            },
            error: function () {
                if (cb) cb(null);
            }
        });
    }

    // Debounce to avoid too many requests
    var t = null;
    function debounceSave(val) {
        try { localStorage.setItem(LS_KEY, val || ''); } catch (e) { }
        if (t) clearTimeout(t);
        t = setTimeout(function () {
            post('o100_save_order_note', { note: val || '' }, function () { });
        }, 500);
    }

    function restoreIntoField(field) {
        if (!field) return;

        // If user already has content, do not overwrite
        if ((field.value || '').trim() !== '') return;

        // 1) Try server session first
        post('o100_get_order_note', {}, function (resp) {
            var serverNote = '';
            if (resp && resp.success && resp.data && typeof resp.data.note === 'string') {
                serverNote = resp.data.note;
            }

            if (serverNote && serverNote.trim() !== '') {
                field.value = serverNote;

                // Trigger events so React / Woo sees it
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            // 2) Fallback: localStorage
            try {
                var ls = localStorage.getItem(LS_KEY) || '';
                if (ls.trim() !== '') {
                    field.value = ls;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } catch (e) { }
        });
    }

    function bindField(field) {
        if (!field || field.__o100Bound) return;
        field.__o100Bound = true;

        // Restore on first bind
        restoreIntoField(field);

        field.addEventListener('input', function () {
            debounceSave(field.value);
        });

        // Extra safety: save on blur
        field.addEventListener('blur', function () {
            debounceSave(field.value);
        });

        // Extra safety: before unload
        window.addEventListener('beforeunload', function () {
            debounceSave(field.value);
        });
    }

    function init() {
        if (!isCheckoutLikePage()) return;

        var field = findNoteField();
        if (field) bindField(field);

        // Observe DOM changes (needed for Checkout Block React rendering)
        var obs = new MutationObserver(function () {
            var f = findNoteField();
            if (f) bindField(f);
        });

        obs.observe(document.documentElement, { childList: true, subtree: true });
    }

    // Run
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(jQuery);


