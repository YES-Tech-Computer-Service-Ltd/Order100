(function ($) {
    var tabIds = o100Settings.tabIds;
    var storageKey = 'o100_settings_active_tab';

    // Safely group fields into cards based on title
    function o100GroupCards() {
        try {
            var titles = document.querySelectorAll('.cmb2-wrap .cmb-row.cmb-type-title:not(.o100-hidden-field)');
            titles.forEach(function (titleRow) {
                if (!titleRow || !titleRow.parentNode) return;
                if (titleRow.parentNode.classList.contains('o100-setting-card')) return;

                var wrapper = document.createElement('div');
                // Stricter regex: ONLY match actual ID strings
                var tabClassMatch = titleRow.className.match(/(o100-tab-(time|checkout|api|nav|discount|loyalty|seo|notification))/);
                var tabClass = tabClassMatch && tabClassMatch[1] ? tabClassMatch[1] : '';

                // Add default o100-setting-card and specific tab class
                wrapper.className = 'o100-setting-card ' + tabClass;
                // Hide by default
                wrapper.style.display = 'none';

                titleRow.parentNode.insertBefore(wrapper, titleRow);

                var next = titleRow;
                while (next) {
                    if (next !== titleRow && next && next.classList && next.classList.contains('cmb-type-title')) break;
                    if (next && next.classList && next.classList.contains('submit')) break;
                    if (next.tagName === 'SCRIPT' || next.tagName === 'STYLE') break;

                    var current = next;
                    next = next.nextElementSibling;
                    if (current) {
                        wrapper.appendChild(current);
                    }
                }
            });

            // Inject toggle text safely and wrap in a rigid flex container
            document.querySelectorAll('.cmb-type-checkbox input[type="checkbox"]').forEach(function (cb) {
                if (!cb || !cb.parentNode) return;
                if (cb.closest('.o100-no-switch')) return; // Ignore horizontal checkboxes

                if (!cb.parentNode.querySelector('.o100-toggle-wrap')) {
                    var toggleWrap = document.createElement('div');
                    toggleWrap.className = 'o100-toggle-wrap';

                    var span = document.createElement('span');
                    span.className = 'o100-toggle-status ' + (cb.checked ? 'is-enabled' : '');
                    span.innerText = cb.checked ? 'Enable' : 'Disable';

                    cb.parentNode.insertBefore(toggleWrap, cb);
                    toggleWrap.appendChild(span);
                    toggleWrap.appendChild(cb);

                    cb.addEventListener('change', function () {
                        span.className = 'o100-toggle-status ' + (this.checked ? 'is-enabled' : '');
                        span.innerText = this.checked ? 'Enable' : 'Disable';
                    });
                }
            });

            // Deep-hoist all descriptions strictly underneath parent labels
            document.querySelectorAll('.cmb2-wrap .cmb-row:not(.cmb-type-title)').forEach(function (row) {
                // Look for description tags inside the row broadly
                var descElements = row.querySelectorAll('.cmb2-metabox-description');
                descElements.forEach(function (desc) {
                    var th = row.querySelector('.cmb-th');
                    if (th) {
                        th.appendChild(desc);
                    }
                });
            });

            // Remove CMB2's auto-injected "Version X.X.X" footer
            var allEls = document.querySelectorAll('.cmb2-wrap *');
            allEls.forEach(function (el) {
                if (el.childNodes.length === 1 && el.childNodes[0].nodeType === 3) {
                    var txt = el.textContent.trim();
                    if (/^Version\s+\d+\.\d+/i.test(txt) && !el.classList.contains('o100-version')) {
                        el.style.display = 'none';
                    }
                }
            });
        } catch (e) {
            console.error("Woo ExFood Addon Error rendering cards: ", e);
        }
    }

    function activate(id) {
        try {
            var links = document.querySelectorAll('.o100-tabs-nav a');
            links.forEach(function (a) { if (a && a.classList) a.classList.toggle('o100-tab-active', a.getAttribute('data-tab') === id); });

            // First, hide all cards and rows to reset state
            document.querySelectorAll('.o100-setting-card').forEach(function (c) {
                c.style.display = 'none';
                c.querySelectorAll('.cmb-row').forEach(function (r) { if (r.classList) r.classList.remove('o100-tab-visible'); });
            });
            document.querySelectorAll('.cmb2-wrap > form .cmb-row:not(.o100-hidden-field)').forEach(function (r) {
                if (!r.closest('.o100-setting-card') && r.classList) r.classList.remove('o100-tab-visible');
            });

            // Then strictly un-hide the active tab content
            var targetClass = '.o100-tab-' + id;

            // Show matching cards
            document.querySelectorAll('.o100-setting-card' + targetClass).forEach(function (c) {
                c.style.display = 'block';
                // Ensure child rows inherit display visibility
                c.querySelectorAll('.cmb-row').forEach(function (r) { if (r.classList) r.classList.add('o100-tab-visible'); });
            });

            // Show ungrouped rows matching target class
            document.querySelectorAll('.cmb2-wrap > form .cmb-row' + targetClass).forEach(function (r) {
                if (!r.closest('.o100-setting-card') && r.classList) {
                    r.classList.add('o100-tab-visible');
                }
            });

            // Hide the default framework submit buttons on tabs with their own save
            document.querySelectorAll('.cmb2-wrap > form > .submit, p.submit').forEach(function (submitBtn) {
                if (id === 'discount' || id === 'loyalty' || id === 'seo' || id === 'notification') {
                    submitBtn.style.display = 'none';
                } else {
                    submitBtn.style.display = 'block';
                }
            });

            try { localStorage.setItem(storageKey, id); } catch (e) { }
        } catch (e) {
            console.error("Woo ExFood Addon Error activating tab: ", e);
        }
    }

    // Bind click events
    document.querySelectorAll('.o100-tabs-nav a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var tabId = this.getAttribute('data-tab');
            activate(tabId);
        });
    });

    // Loyalty module toggle — saves via independent AJAX, NOT through CMB2's form
    var loyaltyToggle = document.getElementById('o100-loyalty-global-toggle');
    if (loyaltyToggle) {
        loyaltyToggle.addEventListener('change', function () {
            var cb = this;
            var label = document.getElementById('o100-loyalty-toggle-label');
            var feedback = document.getElementById('o100-loyalty-toggle-feedback');

            if (label) {
                label.className = 'o100-toggle-status ' + (cb.checked ? 'is-enabled' : '');
                label.innerText = 'Saving...';
            }
            if (feedback) feedback.innerText = '';

            var formData = new FormData();
            formData.append('action', 'o100_toggle_loyalty');
            formData.append('nonce', o100Settings.loyaltyToggleNonce);
            formData.append('enabled', cb.checked ? '1' : '0');

            fetch(o100Settings.ajaxurl, { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        if (label) label.innerText = cb.checked ? 'Enable' : 'Disable';
                        if (feedback) {
                            feedback.style.color = '#46b450';
                            feedback.innerText = '✓ Saved. Reloading...';
                        }
                        // Reload to reflect the loyalty module's enabled/disabled state
                        setTimeout(function () { location.reload(); }, 800);
                    } else {
                        if (label) label.innerText = cb.checked ? 'Enable' : 'Disable';
                        if (feedback) {
                            feedback.style.color = '#dc3232';
                            feedback.innerText = '✗ Save failed: ' + (res.data || 'Unknown error');
                        }
                        // Revert checkbox
                        cb.checked = !cb.checked;
                    }
                })
                .catch(function () {
                    if (label) label.innerText = cb.checked ? 'Enable' : 'Disable';
                    if (feedback) {
                        feedback.style.color = '#dc3232';
                        feedback.innerText = '✗ Network error';
                    }
                    cb.checked = !cb.checked;
                });
        });
    }

    // Initialize: wait for CMB2 rows to be in the DOM
    function initTabs() {
        // If we are in the new Fluent UI, bypass the old JS tab logic completely!
        if (document.querySelector('.o100-fluent-container')) {
            return;
        }

        var hasRows = document.querySelector('.cmb-row.o100-tab-field');
        if (!hasRows) {
            // Give up after a while to avoid infinite loop on pages without tabs
            if (!window.initTabsAttempts) window.initTabsAttempts = 0;
            window.initTabsAttempts++;
            if (window.initTabsAttempts > 40) return; // Stop after 2 seconds
            
            setTimeout(initTabs, 50);
            return;
        }
        o100GroupCards(); // Group the cards first

        var saved = '';
        try { saved = localStorage.getItem(storageKey); } catch (e) { }
        if (saved && tabIds.indexOf(saved) !== -1) {
            activate(saved);
        } else {
            activate(tabIds[0]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }

    // SEO batch processing is handled by o100-seo.js

    // Unified UI Confirm Modal
    window.o100Confirm = function(title, message, callback) {
        var modal = document.createElement('div');
        modal.className = 'o100-confirm-modal-overlay';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.4); z-index:999999; display:flex; align-items:center; justify-content:center; backdrop-filter:blur(4px); transition:opacity 0.2s; opacity:0;';
        
        var content = document.createElement('div');
        content.className = 'o100-confirm-modal-content';
        content.style.cssText = 'background:#fff; border-radius:12px; padding:24px; max-width:360px; width:90%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); transform:scale(0.95); transition:transform 0.2s;';
        
        content.innerHTML = `
            <div style="display:flex; align-items:center; margin-bottom:16px; color:#ef4444;">
                <i class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-right:8px;"></i>
                <h3 style="margin:0; font-size:18px; font-weight:600; color:#0f172a;">${title}</h3>
            </div>
            <p style="margin:0 0 24px; color:#475569; font-size:14px; line-height:1.5;">${message}</p>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button class="o100-cancel-btn" style="padding:8px 16px; border:1px solid #e2e8f0; background:#fff; border-radius:6px; cursor:pointer; color:#475569; font-weight:500;">Cancel</button>
                <button class="o100-confirm-btn" style="padding:8px 16px; border:none; background:#ef4444; color:#fff; border-radius:6px; cursor:pointer; font-weight:500; box-shadow:0 1px 2px rgba(0,0,0,0.05);">Confirm</button>
            </div>
        `;
        
        modal.appendChild(content);
        document.body.appendChild(modal);
        
        setTimeout(function() {
            modal.style.opacity = '1';
            content.style.transform = 'scale(1)';
        }, 10);
        
        var close = function() {
            modal.style.opacity = '0';
            content.style.transform = 'scale(0.95)';
            setTimeout(function() { if(modal.parentNode) modal.parentNode.removeChild(modal); }, 200);
        };
        
        content.querySelector('.o100-cancel-btn').addEventListener('click', function(e) {
            e.preventDefault();
            close();
            if (callback) callback(false);
        });
        
        content.querySelector('.o100-confirm-btn').addEventListener('click', function(e) {
            e.preventDefault();
            close();
            if (callback) callback(true);
        });
    };

    $(document).ready(function () {

        // ── Mobile Responsive Sidebar Toggle ──────────────
        if ($('.o100-fluent-sidebar').length && !$('.o100-fluent-mobile-toggle').length) {
            var $toggleBtn = $('<div class="o100-fluent-mobile-toggle"><span class="dashicons dashicons-menu"></span> Menu</div>');
            $('.o100-fluent-sidebar').prepend($toggleBtn);
            
            $toggleBtn.on('click', function() {
                $(this).toggleClass('o100-nav-open');
                $('.o100-fluent-nav').slideToggle(250);
            });
            
            $(window).on('resize', function() {
                if ($(window).width() > 900) {
                    $('.o100-fluent-nav').css('display', '');
                    $('.o100-fluent-mobile-toggle').removeClass('o100-nav-open');
                }
            });
        }

        // Notification sub-tab switching (Email / SMS)
        var $notifyTabs = $('.o100-notify-subtabs a');
        $notifyTabs.on('click', function (e) {
            e.preventDefault();
            var id = $(this).data('subtab');
            $notifyTabs.removeClass('o100-subtab-active');
            $(this).addClass('o100-subtab-active');
            $('.o100-notify-subtab-content').hide();
            $('.o100-notify-subtab-content[data-subtab="' + id + '"]').show();
        });

        // ── Store Features: Auto-focus on new row ──────────────
        var $featuresWrap = $('#o100_store_features_repeat');
        if ($featuresWrap.length) {
            // Watch for new rows added by CMB2
            $featuresWrap.on('cmb2_add_row', function () {
                var $lastInput = $featuresWrap.find('.cmb-repeat-row:last textarea, .cmb-repeat-row:last input[type="text"]').last();
                if ($lastInput.length) {
                    $lastInput.focus();
                }
            });

            // Fallback: also listen on the Add button click (CMB2 event may not fire in all versions)
            $featuresWrap.parent().on('click', '.cmb-add-row-button, .cmb-add-row button', function () {
                setTimeout(function () {
                    var $lastInput = $featuresWrap.find('.cmb-repeat-row:last textarea, .cmb-repeat-row:last input[type="text"]').last();
                    if ($lastInput.length) {
                        $lastInput.focus();
                    }
                }, 150);
            });
        }

        // ── Store Features: Character limit warning (100 chars) ──
        var CHAR_LIMIT = 100;
        $(document).on('keyup input', '#o100_store_features_repeat textarea, #o100_store_features_repeat input[type="text"]', function () {
            var len = $(this).val().length;
            if (len > CHAR_LIMIT) {
                $(this).addClass('o100-char-warn');
            } else {
                $(this).removeClass('o100-char-warn');
            }
        });

        // ── Custom Timeslot toggles (Delivery & Takeaway) ──────────────
        function toggleCustomTimeslots(type) {
            var $checkbox = $('#o100_' + type + '_custom_timeslots');
            if (!$checkbox.length) return;
            
            // CMB2 uses dashes instead of underscores for row classes
            var $advGroup = $('.cmb2-id-o100-' + type + '-adv-time-slots');
            var $deliTime = $('.cmb2-id-o100-' + type + '-deli-time');
            
            if ($checkbox.is(':checked')) {
                $advGroup.slideDown(200);
                $deliTime.slideDown(200);
            } else {
                $advGroup.slideUp(200);
                $deliTime.slideUp(200);
            }
        }
        
        ['delivery', 'takeaway'].forEach(function(type) {
            // Initial load (use .hide()/.show() without animation to avoid glitch on load)
            var $checkbox = $('#o100_' + type + '_custom_timeslots');
            if ($checkbox.length) {
                if (!$checkbox.is(':checked')) {
                    $('.cmb2-id-o100-' + type + '-adv-time-slots').hide();
                    $('.cmb2-id-o100-' + type + '-deli-time').hide();
                }
            }
            
            $(document).on('change', '#o100_' + type + '_custom_timeslots', function() {
                toggleCustomTimeslots(type);
            });
        });

        // ── Custom Multi-select Checkbox Dropdown ──────────────
        function updateMcdHiddenInput($wrapper) {
            var vals = [];
            var labels = [];
            $wrapper.find('.o100-mcd-list input[type="checkbox"]:checked').each(function() {
                vals.push($(this).val());
                labels.push($(this).siblings('span').text());
                $(this).closest('.o100-mcd-item').addClass('is-selected');
            });
            $wrapper.find('.o100-mcd-list input[type="checkbox"]:not(:checked)').closest('.o100-mcd-item').removeClass('is-selected');
            
            $wrapper.find('.o100-mcd-hidden-input').val(vals.join(',')).trigger('change');
            
            var $headerText = $wrapper.find('.o100-mcd-header-text');
            $headerText.empty();
            
            if (vals.length === 0) {
                $headerText.html('<span class="o100-mcd-placeholder">Selecting...</span>');
            } else {
                var maxPills = 3;
                var html = '';
                for (var i = 0; i < Math.min(labels.length, maxPills); i++) {
                    html += '<span class="o100-mcd-pill" data-val="' + vals[i] + '">' + labels[i] + ' <i class="dashicons dashicons-no-alt"></i></span>';
                }
                if (labels.length > maxPills) {
                    html += '<span class="o100-mcd-more">+' + (labels.length - maxPills) + ' more</span>';
                }
                $headerText.html(html);
            }
        }

        $(document).on('click', '.o100-mcd-pill .dashicons-no-alt', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $wrapper = $(this).closest('.o100-mcd-wrapper');
            var val = $(this).parent('.o100-mcd-pill').data('val');
            var $checkbox = $wrapper.find('.o100-mcd-list input[type="checkbox"][value="' + val + '"]');
            if ($checkbox.length) {
                $checkbox.prop('checked', false).trigger('change');
            }
        });

        $(document).on('click', '.o100-mcd-header', function(e) {
            if ($(e.target).hasClass('dashicons-no-alt')) return; // handled above
            e.preventDefault();
            e.stopPropagation();
            var $wrapper = $(this).closest('.o100-mcd-wrapper');
            $('.o100-mcd-wrapper').not($wrapper).removeClass('is-open').css('margin-bottom', '');
            $wrapper.toggleClass('is-open');
            if ($wrapper.hasClass('is-open')) {
                $wrapper.css('margin-bottom', '280px');
                var $searchInput = $wrapper.find('.o100-mcd-search input');
                $searchInput.focus();
                if ($searchInput.val() === '') {
                    $searchInput.trigger('input');
                }
            } else {
                $wrapper.css('margin-bottom', '');
            }
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.o100-mcd-wrapper').length) {
                $('.o100-mcd-wrapper').removeClass('is-open').css('margin-bottom', '');
            }
        });

        $(document).on('change', '.o100-mcd-list input[type="checkbox"]', function() {
            var $wrapper = $(this).closest('.o100-mcd-wrapper');
            var $item = $(this).closest('.o100-mcd-item');
            
            // If checked, move it to the pinned section so it doesn't get overwritten by search results
            if ($(this).is(':checked')) {
                var $resultsArea = $(this).closest('.o100-mcd-results');
                if ($resultsArea.length) {
                    var $divider = $wrapper.find('.o100-mcd-divider');
                    if (!$divider.length) {
                        $wrapper.find('.o100-mcd-results').before('<div class="o100-mcd-divider"></div>');
                        $divider = $wrapper.find('.o100-mcd-divider');
                    }
                    $item.insertBefore($divider);
                }
            }
            updateMcdHiddenInput($wrapper);
        });

        var mcdSearchTimer;
        $(document).on('input', '.o100-mcd-search input', function() {
            clearTimeout(mcdSearchTimer);
            var $input = $(this);
            var term = $input.val();
            var $wrapper = $input.closest('.o100-mcd-wrapper');
            var type = $wrapper.data('type');
            var $results = $wrapper.find('.o100-mcd-results');
            
            // Allow empty string to fetch defaults, else require at least 2 chars
            if (term.length > 0 && term.length < 2) return;
            
            mcdSearchTimer = setTimeout(function() {
                $results.html('<div class="o100-mcd-loader">Searching...</div>');
                
                var action = type === 'category' ? 'o100_mcd_search_categories' : 'o100_mcd_search_products';
                var exclude = $wrapper.find('.o100-mcd-hidden-input').val();
                
                $.ajax({
                    url: o100Settings.ajaxurl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: action,
                        nonce: o100Settings.adminNonce,
                        term: term,
                        exclude: exclude,
                        _cb: new Date().getTime() // Cache buster
                    },
                    success: function(res) {
                        if (res.success && res.data) {
                            if (res.data.length === 0) {
                                $results.html('<div class="o100-mcd-loader">No results found (V3)</div>');
                                return;
                            }
                            
                            var html = '';
                            $.each(res.data, function(i, item) {
                                html += '<label class="o100-mcd-item">';
                                html += '<input type="checkbox" value="' + item.id + '">';
                                html += '<span>' + item.text + '</span>';
                                html += '</label>';
                            });
                            $results.html(html);
                        } else {
                            $results.html('<div class="o100-mcd-loader">Error loading results</div>');
                        }
                    },
                    error: function() {
                        $results.html('<div class="o100-mcd-loader">Network error</div>');
                    }
                });
            }, 400);
        });

    });

})(jQuery);

