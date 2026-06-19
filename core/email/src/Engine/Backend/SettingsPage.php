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
            null,
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
        file_put_contents('/Users/kevinqi/development/antigravity/puppeteer_test/hook_suffix_debug.log', $hook_suffix . PHP_EOL, FILE_APPEND);
        wp_add_inline_script('jquery-core', 'console.log("SettingsPage Hook Suffix: ' . esc_js($hook_suffix) . '");');

        if ( in_array( $hook_suffix, [ $this->o100ne_hook_surfix, 'toplevel_page_order100', 'order100_page_o100-notifications', 'admin_page_o100_notifications' ], true ) && function_exists( 'WC' ) ) {
            // Ensure emails are loaded
            WC()->mailer();
            do_action( 'o100_before_enqueue_settings_page_scripts' );
            O100neViteApp::get_instance()->enqueue_entry( 'src/main.tsx', [ 'wp-element', 'wp-i18n' ] );
            add_action( 'o100ne_after_enqueue_scripts', [ $this, 'localize_js_vars' ] );

            wp_enqueue_media();
            wp_enqueue_editor();
            wp_enqueue_script( 'accounting' );
            do_action( 'o100_after_enqueue_settings_page_scripts' );

            // Runtime shim: Intercept toast to notify parent iframe on save
            add_action( 'admin_footer', [ $this, 'inject_iframe_toast_hook' ], 99 );
            
            if ( $hook_suffix === $this->o100ne_hook_surfix ) {
                // Disable admin notices to prevent flash/layout shifts in iframe
                remove_all_actions( 'admin_notices' );
                remove_all_actions( 'all_admin_notices' );
                remove_all_actions( 'network_admin_notices' );

                // Ensure WP Media Library modal renders above any remaining overlays
                add_action( 'admin_head', function() {
                    echo '<style>
                        .media-modal { z-index: 200000 !important; }
                        .media-modal-backdrop { z-index: 199999 !important; }
                        /* Hide WordPress Admin UI when in iframe */
                        html.wp-toolbar { padding-top: 0 !important; }
                        #wpadminbar, #adminmenumain, #wpfooter, .update-nag, .notice, .error, .updated, .is-dismissible { display: none !important; }
                        #wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
                        #wpbody { padding-top: 0 !important; }
                        /* Hide the internal "Back to Templates" arrow since we have an external close button */
                        button[title="Back to Templates"] { display: none !important; }
                    </style>';
                }, 1 );
            }
        }
    }

    /**
     * Inject runtime JS to intercept the save toast when running in an iframe (Automation mode)
     */
    public function inject_iframe_toast_hook() {
        ?>
        <script>
        (function() {
            'use strict';
            window.o100ShowToast = function(msg, type) {
                if (type === 'success' && msg.toLowerCase().indexOf('saved') !== -1) {
                    if (window.parent && window.parent !== window) {
                        var m = window.location.hash.match(/#\/editor\/(\d+)/);
                        var tid = m ? m[1] : null;
                        if (tid) {
                            window.parent.postMessage({
                                type: 'o100_template_saved',
                                template_id: tid
                            }, '*');
                        }
                    }
                }
            };
            
            // Preview modal: wire up header icons & hide "Device" toggle
            var observer = new MutationObserver(function() {
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
    public function localize_js_vars() { error_log("localize_js_vars CALLED!"); wp_add_inline_script("module/o100ne/src/main.tsx", "var o100neData = {test: 123};", "before"); 

        $all_templates_data = class_exists( '\Order100\Notification\Engine\Models\TemplateModel' ) 
            ? \Order100\Notification\Engine\Models\TemplateModel::find_all() 
            : [];
            
        $_wc_emails = array_map(
            function( $tpl ) {
                return (object) [
                    'id'               => $tpl['name'],
                    'title'            => $tpl['template_title'],
                    'enabled'          => $tpl['status'] === 'active' ? 'yes' : 'no',
                    'description'      => '',
                    'template_base'    => '',
                    'recipient'        => $tpl['recipient_type'] === 'admin' ? 'admin@store.com' : 'customer@email.com',
                    'content_type'     => 'text/html',
                    'setting_page_url' => '',
                ];
            },
            $all_templates_data
        );

        error_log('LOCALIZE JS VARS DATA DUMP: ' . print_r([ 'is_rtl' => is_rtl(), 'wc_emails' => $_wc_emails ], true));
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
                        'store_logo_url'         => \Order100\Notification\Engine\Shortcodes\CommonShortcodes::get_instance()->o100ne_store_logo_url([]),
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
                    'site_name'                      => get_option( 'blogname' ),
                    'store_profile'                  => get_option( 'o100_store_profile', [] ),
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
        $url = admin_url( 'admin.php?page=o100-notifications' );
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
        if ( in_array( $screen->id, [ $this->o100ne_hook_surfix, 'order100_page_o100-settings', 'toplevel_page_order100', 'order100_page_o100-notifications', 'admin_page_o100_notifications' ], true ) ) {
            wp_dequeue_style( 'real-media-library-lite-rml' );
            wp_dequeue_script( 'real-media-library-lite-rml' );
            wp_dequeue_style( 'real-media-library-rml' );
            wp_dequeue_script( 'real-media-library-rml' );
            wp_dequeue_style( 'real-category-library-admin' );
        }
    }
}


