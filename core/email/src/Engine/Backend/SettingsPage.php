<?php

namespace Order100\Notification\Engine\Engine\Backend;

use Order100\Notification\Engine\Controllers\RevisionController;
use Order100\Notification\Engine\TemplatePatterns\PatternService;
use Order100\Notification\Engine\Models\MigrationModel;
use Order100\Notification\Engine\SupportedPlugins;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplateService;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Utils\O100neViteApp;
use Order100\Notification\Engine\Utils\Localize;
use Order100\Notification\Engine\Utils\Helpers;
/**
 *  O100ne Page
 */
class SettingsPage {
    use SingletonTrait;

    private $o100ne_hook_surfix = null;

    /**
     * Constructor
     */
    protected function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks when class init
     */
    protected function init_hooks() {
        // Register hidden admin page for the email editor
        add_action( 'admin_menu', [ $this, 'add_o100ne_menu' ], 99 );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ], 30 );

        add_filter( 'mce_external_plugins', [ $this, 'register_wp_editor_plugins_script' ] );

        // Fix conflict plugins styles in Settings page
        add_action( 'admin_enqueue_scripts', [ $this, 'fix_conflict_plugins_styles' ], PHP_INT_MAX );
    }

    /**
     * Register the YayMAil sub menu to WordPress O100ne menu.
     */
    public function add_o100ne_menu() {
        // Hidden page — no parent menu, accessed via direct URL only
        $this->o100ne_hook_surfix = add_submenu_page(
            'woocommerce',
            __( 'Email Template Editor', 'order100' ),
            __( 'Email Editor', 'order100' ),
            'manage_woocommerce',
            O100NE_PREFIX . '-settings',
            [ $this, 'render_o100ne_page' ]
        );
    }

    /**
     * Render the settings page
     */
    public function render_o100ne_page() {
        include_once O100NE_PLUGIN_PATH . 'templates/pages/settings.php';
    }

    /**
     * Enqueue scripts using in settings page
     */
    public function register_wp_editor_plugins_script( $plugin_array ) {

        $plugin_array['advlist']        = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/advlist/plugin.min.js';
        $plugin_array['autolink']       = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/autolink/plugin.min.js';
        $plugin_array['searchreplace']  = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/searchreplace/plugin.min.js';
        $plugin_array['code']           = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/code/plugin.min.js';
        $plugin_array['visualblocks']   = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/visualblocks/plugin.min.js';
        $plugin_array['table']          = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/table/plugin.min.js';
        $plugin_array['insertdatetime'] = O100NE_PLUGIN_URL . 'assets/scripts/wp-editor-plugins/insertdatetime/plugin.min.js';

        return $plugin_array;
    }

    public function admin_enqueue_scripts( $hook_suffix ) {
        if ( in_array( $hook_suffix, [ $this->o100ne_hook_surfix ], true ) && class_exists( 'WC_Emails' ) ) {
            do_action( 'o100_before_enqueue_settings_page_scripts' );
            // Enqueue script here
            O100neViteApp::get_instance()->enqueue_entry( 'src/main.tsx', [ 'react', 'react-dom', 'wp-i18n' ] );
            add_action( 'o100ne_after_enqueue_scripts', [ $this, 'localize_js_vars' ] );

            wp_enqueue_media();
            wp_enqueue_editor();
            wp_enqueue_script( 'accounting' );
            do_action( 'o100_after_enqueue_settings_page_scripts' );

            // Runtime shim: force-enable Save button (bypasses hasChanges guard in compiled JS)
            add_action( 'admin_footer', [ $this, 'inject_save_button_shim' ], 99 );

            // Ensure WP Media Library modal renders above any remaining overlays
            add_action( 'admin_head', function() {
                echo '<style>
                    .media-modal { z-index: 200000 !important; }
                    .media-modal-backdrop { z-index: 199999 !important; }
                </style>';
            });
        }
    }

    /**
     * Inject runtime JS to force-enable Save and handle save logic directly.
     * Uses React Fiber traversal to find GrapesJS editor from component state.
     */
    public function inject_save_button_shim() {
        ?>
        <script>
        (function() {
            'use strict';

            var _gjsEditor = null;
            var _templateNumericId = null;

            // ── 1. Capture template numeric ID from REST response ──
            // Wrap the existing fetch (which settings.php already wrapped)
            var _prevFetch = window.fetch;
            window.fetch = function(url, options) {
                var promise = _prevFetch.apply(this, arguments);
                if (typeof url === 'string' && url.indexOf('get-template-by-name') !== -1) {
                    promise.then(function(resp) {
                        return resp.clone().json();
                    }).then(function(data) {
                        if (data && data.id) {
                            _templateNumericId = data.id;
                            console.log('[Shim] Captured template ID:', _templateNumericId);
                        }
                    }).catch(function(){});
                }
                return promise;
            };

            // ── 2. Find GrapesJS editor via React Fiber ──
            function findEditorViaFiber() {
                if (_gjsEditor) return _gjsEditor;

                var editor = null;

                // Strategy A: Bottom-up from .gjs-editor DOM element
                editor = findEditorBottomUp();
                if (editor) {
                    _gjsEditor = editor;
                    window.__gjsEditor = editor;
                    console.log('[Shim] Found editor via Strategy A (bottom-up from .gjs-editor)');
                    return editor;
                }

                // Strategy B: Top-down from React root
                editor = findEditorTopDown();
                if (editor) {
                    _gjsEditor = editor;
                    window.__gjsEditor = editor;
                    console.log('[Shim] Found editor via Strategy B (top-down from root)');
                    return editor;
                }

                return null;
            }

            // Strategy A: Find .gjs-editor element, walk UP fiber.return chain
            function findEditorBottomUp() {
                var gjsEl = document.querySelector('.gjs-editor');
                if (!gjsEl) return null;

                // Walk up DOM to find nearest React-managed element
                var el = gjsEl;
                while (el && el !== document.body) {
                    var fiberKey = getReactFiberKey(el);
                    if (fiberKey) {
                        var fiber = el[fiberKey];
                        // Walk UP through fiber.return (parent components)
                        var visited = 0;
                        while (fiber && visited < 200) {
                            var result = checkFiberForEditor(fiber);
                            if (result) return result;
                            fiber = fiber.return;
                            visited++;
                        }
                    }
                    el = el.parentElement;
                }
                return null;
            }

            // Strategy B: Top-down from React root
            function findEditorTopDown() {
                var root = document.getElementById('o100ne-main-pages');
                if (!root) return null;

                // Try __reactContainer$ (React 18 createRoot)
                var containerKey = Object.keys(root).find(function(k) {
                    return k.startsWith('__reactContainer$');
                });
                if (containerKey) {
                    var containerFiber = root[containerKey];
                    if (containerFiber) {
                        // For container, get the stateNode.current (FiberRoot)
                        var fiberRoot = containerFiber.stateNode;
                        if (fiberRoot && fiberRoot.current) {
                            var result = traverseFiberDown(fiberRoot.current, 0);
                            if (result) return result;
                        }
                        // Also try traversing directly from the container fiber
                        var result2 = traverseFiberDown(containerFiber, 0);
                        if (result2) return result2;
                    }
                }

                // Try __reactFiber$ (standard)
                var fiberKey = getReactFiberKey(root);
                if (fiberKey) {
                    return traverseFiberDown(root[fiberKey], 0);
                }

                // Try first child elements
                for (var i = 0; i < root.children.length && i < 5; i++) {
                    var childKey = getReactFiberKey(root.children[i]);
                    if (childKey) {
                        var r = traverseFiberDown(root.children[i][childKey], 0);
                        if (r) return r;
                    }
                }

                return null;
            }

            function getReactFiberKey(el) {
                return Object.keys(el).find(function(k) {
                    return k.startsWith('__reactFiber$') || k.startsWith('__reactInternalInstance$');
                }) || null;
            }

            function traverseFiberDown(fiber, depth) {
                if (!fiber || depth > 150) return null;
                var result = checkFiberForEditor(fiber);
                if (result) return result;
                return traverseFiberDown(fiber.child, depth + 1) || traverseFiberDown(fiber.sibling, depth + 1);
            }

            function checkFiberForEditor(fiber) {
                if (!fiber || !fiber.memoizedState) return null;
                var hookNode = fiber.memoizedState;
                var hookCount = 0;
                while (hookNode && hookCount < 50) {
                    // useState: value in hookNode.memoizedState
                    if (isGjsEditor(hookNode.memoizedState)) return hookNode.memoizedState;
                    // useRef: value in hookNode.memoizedState.current
                    if (hookNode.memoizedState && typeof hookNode.memoizedState === 'object' &&
                        hookNode.memoizedState.current && isGjsEditor(hookNode.memoizedState.current)) {
                        return hookNode.memoizedState.current;
                    }
                    // queue.lastRenderedState
                    if (hookNode.queue && isGjsEditor(hookNode.queue.lastRenderedState)) {
                        return hookNode.queue.lastRenderedState;
                    }
                    hookNode = hookNode.next;
                    hookCount++;
                }
                return null;
            }

            function isGjsEditor(obj) {
                if (obj === null || obj === undefined || typeof obj !== 'object') return false;
                try {
                    return typeof obj.runCommand === 'function' &&
                           typeof obj.getHtml === 'function' &&
                           typeof obj.getWrapper === 'function';
                } catch(e) { return false; }
            }

            // ── 3. Save function ──
            function doSaveTemplate() {
                // Always re-find editor (React may have re-rendered on route change)
                _gjsEditor = null;
                findEditorViaFiber();
                // Also try global reference set by Editor.tsx
                if (!_gjsEditor && window.__gjsEditor) _gjsEditor = window.__gjsEditor;

                if (!_gjsEditor) {
                    alert('Editor not ready. Please wait for the template to fully load, then try again.');
                    return;
                }
                if (!_templateNumericId) {
                    alert('Template ID not found. Please reload the page.');
                    return;
                }

                var restPath = (window.o100neData || {}).rest_path || {};
                if (!restPath.root || !restPath.base) {
                    alert('REST API config missing.');
                    return;
                }

                // Extract MJML from GrapesJS
                var mjmlResult, mjml, mjmlHtml;
                // Inject conditional data into css-class so it survives MJML compilation
                var allComps = [];
                var walk = function(comp) {
                    allComps.push(comp);
                    comp.components().forEach(walk);
                };
                walk(_gjsEditor.DomComponents.getWrapper());
                
                var condFound = 0;
                allComps.forEach(function(comp) {
                    var attrs = comp.getAttributes();
                    var f = attrs['data-condition-field'] || '';
                    var o = attrs['data-condition-operator'] || '';
                    var v = attrs['data-condition-value'] || '';
                    if (f) {
                        condFound++;
                        var css = attrs['css-class'] || '';
                        css = css.replace(/cond_o100_[^\s]+/g, '').trim();
                        var jsonStr = JSON.stringify({f: f, o: o, v: v});
                        // Base64URL encoding
                        var base64 = btoa(encodeURIComponent(jsonStr).replace(/%([0-9A-F]{2})/g, function(m, p1) { return String.fromCharCode('0x' + p1); })).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
                        css += ' cond_o100_' + base64;
                        comp.addAttributes({ 'css-class': css.trim() });
                        console.log('[Shim] Injected cond for', f, o, v, '→ css-class:', css.trim());
                    }
                });
                console.log('[Shim] Total conditional sections found:', condFound);

                try {
                    window.isExporting = true;
                    mjmlResult = _gjsEditor.runCommand('mjml-get-code');
                    window.isExporting = false;
                    mjml = mjmlResult ? mjmlResult.mjml : null;
                    mjmlHtml = mjmlResult ? mjmlResult.html : null;
                } catch(ex) {
                    window.isExporting = false;
                    console.error('[Shim] mjml-get-code error:', ex);
                }

                if (!mjml) {
                    try { mjml = _gjsEditor.getHtml(); } catch(e2) {}
                }
                if (!mjmlHtml) {
                    mjmlHtml = mjml;
                }
                if (!mjml) {
                    alert('Could not extract template content.');
                    return;
                }

                // Verify cond_o100_ survived compilation
                var condInMjml = (mjml.match(/cond_o100_/g) || []).length;
                var condInHtml = (mjmlHtml.match(/cond_o100_/g) || []).length;
                console.log('[Shim] cond_o100_ in MJML:', condInMjml, '| in compiled HTML:', condInHtml);
                console.log('[Shim] Saving template ID', _templateNumericId, 'MJML length:', mjml.length, 'HTML length:', mjmlHtml.length);

                var saveBtn = document.getElementById('o100ne-shim-save-btn');
                if (saveBtn) { saveBtn.textContent = 'Saving...'; saveBtn.disabled = true; }

                // Use XMLHttpRequest to avoid fetch-wrapper conflicts
                var xhr = new XMLHttpRequest();
                var url = restPath.root + restPath.base + '/templates/' + _templateNumericId;
                xhr.open('PUT', url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('X-WP-Nonce', restPath.nonce);
                xhr.onload = function() {
                    var resp;
                    try { resp = JSON.parse(xhr.responseText); } catch(e) { resp = xhr.responseText; }
                    console.log('[Shim] Save response:', resp);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        if (saveBtn) {
                            saveBtn.textContent = '\u2713 Saved';
                            saveBtn.style.background = '#22c55e';
                            setTimeout(function() {
                                saveBtn.textContent = 'Save';
                                saveBtn.style.background = '#6A4BFF';
                                saveBtn.disabled = false;
                            }, 2000);
                        }
                    } else {
                        alert('Save failed (HTTP ' + xhr.status + '): ' + xhr.responseText.substring(0, 200));
                        if (saveBtn) { saveBtn.textContent = 'Save'; saveBtn.disabled = false; }
                    }
                };
                xhr.onerror = function() {
                    alert('Save network error');
                    if (saveBtn) { saveBtn.textContent = 'Save'; saveBtn.disabled = false; }
                };
                xhr.send(JSON.stringify({
                    data: {
                        template_id: String(_templateNumericId),
                        template_elements: mjml,
                        template_html: mjmlHtml,
                        template_elements_type: 'mjml'
                    }
                }));
            }

            // ── 4. Inject our Save button & hide React's broken one ──
            var observer = new MutationObserver(function() {
                // Periodically try to find editor via fiber
                if (!_gjsEditor) findEditorViaFiber();

                var icons = document.querySelectorAll('.dashicons-saved');
                icons.forEach(function(icon) {
                    var reactBtn = icon.closest('button');
                    if (reactBtn && !reactBtn.dataset.shimHidden) {
                        reactBtn.dataset.shimHidden = 'true';
                        reactBtn.style.display = 'none';

                        var shimBtn = document.createElement('button');
                        shimBtn.id = 'o100ne-shim-save-btn';
                        shimBtn.textContent = 'Save';
                        shimBtn.style.cssText = 'background:#6A4BFF;color:#fff;border:none;padding:8px 24px;border-radius:4px;cursor:pointer;font-weight:600;font-size:14px;display:flex;align-items:center;gap:6px;';
                        shimBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            doSaveTemplate();
                        });
                        reactBtn.parentNode.insertBefore(shimBtn, reactBtn);
                    }
                });

                // ── 5. Preview modal: wire up header icons & hide "Device" toggle ──
                try {
                    var previewH2s = document.querySelectorAll('h2');
                    previewH2s.forEach(function(h2) {
                        if (h2.textContent.trim() !== 'Preview') return;
                        var headerRow = h2.parentElement;
                        if (!headerRow || headerRow.dataset.shimPreviewDone) return;
                        headerRow.dataset.shimPreviewDone = 'true';

                        // Find the modal container (position:fixed overlay)
                        var modal = headerRow.closest('div');
                        while (modal && modal.parentElement && modal.style.position !== 'fixed') {
                            modal = modal.parentElement;
                        }
                        if (!modal) return;

                        // Find the iframe in this modal
                        var iframe = modal.querySelector('iframe');
                        if (!iframe) return;

                        // a) Wire up header desktop/smartphone icons
                        var desktopBtn = null, mobileBtn = null;
                        var headerBtns = headerRow.querySelectorAll('button');
                        headerBtns.forEach(function(btn) {
                            if (btn.querySelector('.dashicons-desktop')) desktopBtn = btn;
                            if (btn.querySelector('.dashicons-smartphone')) mobileBtn = btn;
                        });

                        var activeColor = '#6A4BFF';
                        var inactiveColor = '#94a3b8';

                        if (desktopBtn) {
                            desktopBtn.style.cursor = 'pointer';
                            var dIcon = desktopBtn.querySelector('.dashicons-desktop');
                            if (dIcon) dIcon.style.color = activeColor;
                            desktopBtn.addEventListener('click', function() {
                                iframe.style.maxWidth = '600px';
                                if (dIcon) dIcon.style.color = activeColor;
                                var mIcon = mobileBtn ? mobileBtn.querySelector('.dashicons-smartphone') : null;
                                if (mIcon) mIcon.style.color = inactiveColor;
                            });
                        }

                        if (mobileBtn) {
                            mobileBtn.style.cursor = 'pointer';
                            var mIcon = mobileBtn.querySelector('.dashicons-smartphone');
                            if (mIcon) mIcon.style.color = inactiveColor;
                            mobileBtn.addEventListener('click', function() {
                                iframe.style.maxWidth = '375px';
                                if (mIcon) mIcon.style.color = activeColor;
                                var dIconRef = desktopBtn ? desktopBtn.querySelector('.dashicons-desktop') : null;
                                if (dIconRef) dIconRef.style.color = inactiveColor;
                            });
                        }

                        // b) Hide the "Device: PC | Mobile" toggle row
                        var modalInner = headerRow.parentElement;
                        if (modalInner) {
                            for (var ci = 0; ci < modalInner.children.length; ci++) {
                                var section = modalInner.children[ci];
                                if (section === headerRow) continue;
                                // Use textContent with length cap to find the small Device toggle row
                                // (avoids matching large containers like the actions row or canvas)
                                var sectionText = section.textContent.trim();
                                if (sectionText.indexOf('Device') !== -1 && sectionText.length < 50) {
                                    section.style.display = 'none';
                                }
                            }
                        }
                    });
                } catch(e) {
                    console.warn('[Shim] Preview device toggle error:', e);
                }

                // ── 6. Wire up Test Email "Send" button ──
                try {
                    var sendBtns = document.querySelectorAll('button');
                    sendBtns.forEach(function(btn) {
                        if (btn.dataset.shimTestEmail) return;
                        var emailIcon = btn.querySelector('.dashicons-email');
                        if (!emailIcon) return;
                        if (!btn.textContent || btn.textContent.trim().indexOf('Send') === -1) return;
                        btn.dataset.shimTestEmail = 'true';

                        // Clone and replace to kill the React onClick (alert)
                        var newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);

                        newBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();

                            // Find the email input (sibling in the same flex container)
                            var wrapper = newBtn.parentElement;
                            var emailInput = wrapper ? wrapper.querySelector('input[type="email"]') : null;
                            var email = emailInput ? emailInput.value.trim() : '';

                            if (!email) {
                                alert('Please enter an email address.');
                                return;
                            }

                            // Find the iframe to get its HTML content
                            var modal = newBtn.closest('div[style*="position: fixed"], div[style*="position:fixed"]');
                            if (!modal) {
                                // fallback: walk up to find fixed overlay
                                modal = newBtn;
                                while (modal && modal.parentElement) {
                                    if (modal.style && modal.style.position === 'fixed') break;
                                    modal = modal.parentElement;
                                }
                            }
                            var iframe = modal ? modal.querySelector('iframe') : null;
                            var html = '';
                            if (iframe) {
                                // Prefer srcDoc attribute (React-rendered)
                                html = iframe.getAttribute('srcdoc') || iframe.srcdoc || '';
                                // Fallback: try reading iframe document
                                if (!html) {
                                    try { html = iframe.contentDocument.documentElement.outerHTML; } catch(ex) {}
                                }
                            }

                            if (!html) {
                                alert('Cannot capture email content. Please ensure the preview is loaded.');
                                return;
                            }

                            // Visual feedback: sending
                            var origHTML = newBtn.innerHTML;
                            newBtn.innerHTML = '<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite"></span> Sending...';
                            newBtn.disabled = true;
                            newBtn.style.opacity = '0.7';

                            // POST to REST endpoint
                            var restBase = (window.o100neData && window.o100neData.rest_path)
                                ? window.o100neData.rest_path.root + window.o100neData.rest_path.base
                                : '/wp-json/o100ne/v1';
                            var nonce = (window.o100neData && window.o100neData.rest_path)
                                ? window.o100neData.rest_path.nonce
                                : (window.wpApiSettings && window.wpApiSettings.nonce ? window.wpApiSettings.nonce : '');

                            fetch(restBase + '/templates/send-test-email', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': nonce
                                },
                                body: JSON.stringify({ email: email, html: html })
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    newBtn.innerHTML = '<span class="dashicons dashicons-yes" style="color:#22c55e"></span> Sent!';
                                    newBtn.style.opacity = '1';
                                    setTimeout(function() {
                                        newBtn.innerHTML = origHTML;
                                        newBtn.disabled = false;
                                    }, 2500);
                                } else {
                                    newBtn.innerHTML = '<span class="dashicons dashicons-warning" style="color:#ef4444"></span> Failed';
                                    newBtn.style.opacity = '1';
                                    alert(data.message || 'Send failed.');
                                    setTimeout(function() {
                                        newBtn.innerHTML = origHTML;
                                        newBtn.disabled = false;
                                    }, 2000);
                                }
                            })
                            .catch(function(err) {
                                newBtn.innerHTML = '<span class="dashicons dashicons-warning" style="color:#ef4444"></span> Error';
                                newBtn.style.opacity = '1';
                                alert('Network error: ' + err.message);
                                setTimeout(function() {
                                    newBtn.innerHTML = origHTML;
                                    newBtn.disabled = false;
                                }, 2000);
                            });
                        });
                    });
                } catch(e) {
                    console.warn('[Shim] Test email button error:', e);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            // Note: The experimental product blocks shim has been removed.
            // We are using the native Products block and extending its backend rendering logic.
        })();
        </script>
        <?php
    }

    /**
     * Register localize data
     */
    public function localize_js_vars() {

        $_wc_emails = wc()->mailer()->emails;

        // override template base for wc emails
        foreach ( $_wc_emails as $email ) {
            $reflector            = new \ReflectionClass( $email );
            $email->template_base = $reflector->getFileName();
            unset( $reflector );
        }

        $_wc_emails = array_map(
            function( $email ) {
                return (object) [
                    'id'               => $email->id,
                    'title'            => $email->title,
                    'enabled'          => $email->enabled,
                    'description'      => $email->description,
                    'template_base'    => $email->template_base,
                    'recipient'        => $email->recipient,
                    'content_type'     => $email->get_content_type(),
                    'setting_page_url' => Helpers::o100ne_get_url_email_setting_page( $email->id ),
                ];
            },
            $_wc_emails
        );

        wp_localize_script(
            'module/o100ne/src/main.tsx',
            'o100neData',
            array_merge(
                [
                    'is_rtl'                         => is_rtl(),
                    'urls'                           => [
                        'vite_dynamic_base'      => O100NE_PLUGIN_URL . 'assets/dist/builder/',
                        'asset_url'              => O100NE_PLUGIN_URL . 'assets/images/',
                        'home_url'               => home_url(),
                        'wc_placeholder_img_src' => function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '',
                        'media_picker_url'       => O100NE_PLUGIN_URL . 'media-picker.php',
                    ],
                    'admin_ajax'                     => [
                        'url'   => admin_url( 'admin-ajax.php' ),
                        'nonce' => wp_create_nonce( 'o100_frontend_nonce' ),
                    ],
                    'rest_path'                      => [
                        'root'  => esc_url_raw( rest_url() ),
                        'base'  => O100NE_REST_NAMESPACE,
                        'nonce' => wp_create_nonce( 'wp_rest' ),
                    ],
                    'shared'                         => [
                        'util_functions'   => [],
                        'stores'           => [],
                        'core_components'  => [],
                        'activated_addons' => Localize::get_activated_addons(),
                    ],
                    'list_orders'                    => Localize::get_list_orders(),
                    'i18n'                           => apply_filters(
                        'o100_translations',
                        []
                    ),
                    'builder'                        => [
                        'font_families'          => [
                            '"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif',
                            'Georgia, serif',
                            '"Times New Roman", Times, Serif',
                            'Arial, Helvetica, sans-serif',
                            '"Arial Black", Gadget, sans-serif',
                            '"Comic Sans MS", cursive, sans-serif',
                            'Tahoma, Geneva, sans-serif',
                            '"Trebuchet MS", Helvetica, sans-serif',
                            'Verdana, Geneva, sans-serif',
                            '"Courier New", Courier, monospace',
                            '"Lucida Console", Monaco, monospace',
                        ],
                        'social_icons'           => Localize::get_social_icons_data(),
                        'revision_limit'         => RevisionController::O100NE_TEMPLATE_REVISION_LIMIT,
                        'global_headers_footers' => Localize::get_global_headers_footers(),
                        'section_templates'      => SectionTemplateService::get_instance()->get_list_data(),
                        'patterns'               => PatternService::get_instance()->get_list_data(),
                    ],
                    'colors'                         => [
                        'default_background_color'         => O100NE_COLOR_BACKGROUND_DEFAULT,
                        'default_text_link_color'          => O100NE_COLOR_WC_DEFAULT,
                        'default_content_background_color' => O100NE_COLOR_CONTENT_BACKGROUND_DEFAULT,
                        'default_content_text_color'       => O100NE_COLOR_CONTENT_TEXT_DEFAULT,
                        'default_title_color'              => O100NE_COLOR_TITLE_DEFAULT,
                    ],
                    'smtp'                           => [
                        'link_detail' => '',
                        'setting'     => '',
                        'is_active'   => false,
                    ],
                    'reviewed'                       => true,
                    'ghf_tour'                       => get_option( 'o100_ghf_tour', 'initial' ),
                    'test_email_address'             => get_option( 'o100_default_email_test', wp_get_current_user()->user_email ),
                    'site_title'                     => get_option( 'blogname' ),
                    'wc_emails'                      => $_wc_emails,
                    'is_critical_migration_required' => false,
                    'supported_plugins'              => SupportedPlugins::get_instance()->get_slug_name_supported_plugins(),
                    'show_multi_select_notice'       => 'no',
                    'viewed_new_elements'            => ! empty( get_option( 'o100_viewed_new_elements', [] ) ) ? get_option( 'o100_viewed_new_elements' ) : [],
                ],
                apply_filters( 'o100_additional_localized_variables', [] )
            )
        );
    }

    /**
     * Get the URL for the email editor page
     *
     * @param string $template_id Optional email template ID to pre-select.
     * @return string
     */
    public static function get_editor_url( $template_id = '' ) {
        $url = admin_url( 'admin.php?page=' . O100NE_PREFIX . '-settings' );
        if ( ! empty( $template_id ) ) {
            $url .= '#/editor/' . urlencode( $template_id );
        }
        return $url;
    }

    public function fix_conflict_plugins_styles() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return;
        }
        $screen = get_current_screen();
        if ( $this->o100ne_hook_surfix === $screen->id ) {
            wp_dequeue_style( 'real-media-library-lite-rml' );
            wp_dequeue_script( 'real-media-library-lite-rml' );
            wp_dequeue_style( 'real-media-library-rml' );
            wp_dequeue_script( 'real-media-library-rml' );
            wp_dequeue_style( 'real-category-library-admin' );
        }
    }
}



// TS: 20260125161943

// TS: 20260208175239
