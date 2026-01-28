/**
 * Discount Frontend JS
 * Injects promotional badges, sale badges, and modified prices
 * into WooFood product cards and popup modals.
 *
 * ============================================================
 * WooFood DOM Reference (from plugin source templates)
 * ============================================================
 *
 * PRODUCT CARD WRAPPERS (shortcode-rendered):
 *  List:     <div class="fditem-list item-grid" data-id="ex_id-{cat}-{pid}" data-id_food="{pid}">
 *  Grid:     <div class="item-grid" data-id="ex_id-{cat}-{pid}" data-id_food="{pid}">
 *  Carousel: <div class="item-grid" data-id="ex_id-{cat}-{pid}" data-id_food="{pid}">
 *
 * LIST TEMPLATES (figure is inside the wrapper div):
 *  List 1/4/5: figure.fdstyle-list-1 > .fdlist_1_detail > .fdlist_1_title > .fdlist_1_name + .fdlist_1_price
 *  List 2:     figure.fdstyle-list-2 > .fdlist_2_detail > .fdlist_2_title > .fdlist_2_name + .fdlist_2_price
 *  List 3:     figure.fdstyle-list-3 > .fdlist_3_title + .fdlist_3_des + .fdlist_3_price
 *
 * GRID TEMPLATES (figure is inside the wrapper div):
 *  Grid 1: figure.exstyle-1 > .exstyle-1-image + figcaption > h3(title) + h5 > span(price)
 *  Grid 2: figure.exstyle-2 > .exstyle-2-image + figcaption > h3(title) + h5 > span(price)
 *  Grid 3: figure.exstyle-3 > .exstyle-3-image + figcaption > h3(title) + h5(price)
 *  Grid 4/5: figure.exstyle-4 > .exstyle-4-image + figcaption > h3(title) + h5(price)
 *
 * SHARED ELEMENTS:
 *  Image:        .exf-img (list), .exstyle-{N}-image (grid)
 *  Click trigger: .exfd_modal_click
 *
 * MODAL (content-modal.php):
 *  Wrapper:  #food_modal (gets class .exfd-modal-active when open)
 *  Inner:    .ex-modal-big#product-{pid}
 *  Title:    .fd_modal_des h3
 *  Price:    .fd_modal_des h5
 *  ATC btn:  button[name="add-to-cart"] (value = pid)
 * ============================================================
 */
(function ($) {
    'use strict';

    var VARS = typeof o100_discount_vars !== 'undefined' ? o100_discount_vars : {};
    var promoData = null;

    function log() {
        if (VARS.debug && window.console) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[WFA Discount]');
            console.log.apply(console, args);
        }
    }

    // ─── Data Loading ────────────────────────────────────────────
    function loadPromoData() {
        var $el = $('#o100-discount-data');
        if (!$el.length) {
            log('No #o100-discount-data element found');
            return {};
        }
        try {
            var data = JSON.parse($el.text());
            log('Loaded promo data for', Object.keys(data).length, 'products:', data);
            return data;
        } catch (e) {
            log('Error parsing promo data:', e);
            return {};
        }
    }

    // ─── Utility ─────────────────────────────────────────────────
    function formatPrice(price) {
        var symbol = VARS.currency || '$';
        return symbol + parseFloat(price).toFixed(2);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Get product ID from a WooFood product card wrapper
     * The wrapper div has data-id_food="{numeric_product_id}"
     */
    function getProductId($card) {
        // Method 1: data-id_food (direct numeric ID, set by all WooFood shortcodes)
        var idFood = $card.attr('data-id_food') || $card.data('id_food');
        if (idFood) return String(idFood);

        // Method 2: Parse from data-id="ex_id-{cat}-{pid}" (fallback)
        var dataId = $card.attr('data-id') || $card.data('id');
        if (dataId) {
            var parts = String(dataId).split('-');
            if (parts.length >= 3) return parts[parts.length - 1];
        }

        // Method 3: CSS class tppost-{pid} on the figure element
        var $figure = $card.find('figure[class*="tppost-"]');
        if ($figure.length) {
            var match = $figure.attr('class').match(/tppost-(\d+)/);
            if (match) return match[1];
        }

        // Method 4: From add-to-cart button
        var $btn = $card.find('button[name="add-to-cart"]');
        if ($btn.length) return String($btn.val());

        return null;
    }

    // ─── Badge Builders ──────────────────────────────────────────
    function buildPromoBadge(rules) {
        var html = '';
        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];
            var text = rule.promo_text;
            if (!text) {
                if (rule.rule_type === 'bogo') {
                    text = 'Buy ' + rule.buy_qty + ' Get ' + rule.get_qty + ' Free';
                } else if (rule.rule_type === 'buy_x_get_y') {
                    text = 'Buy ' + rule.buy_qty + ' Get ' + rule.get_qty + ' Free';
                } else if (rule.discount_type === 'percentage') {
                    text = rule.discount_value + '% OFF';
                } else if (rule.discount_type === 'fixed') {
                    text = formatPrice(rule.discount_value) + ' OFF';
                } else if (rule.discount_type === 'free_item') {
                    text = 'FREE ITEM!';
                }
            }
            if (text) {
                html += '<span class="o100-promo-badge">' + escapeHtml(text) + '</span> ';
            }
        }
        return html;
    }

    function buildSaleBadge(rules) {
        for (var i = 0; i < rules.length; i++) {
            if (rules[i].show_badge && rules[i].badge_text) {
                var style = VARS.badge_style || 'pill';
                return '<span class="o100-sale-badge o100-badge-' + style + '">' + escapeHtml(rules[i].badge_text) + '</span>';
            }
        }
        return '';
    }

    function buildPriceDisplay(data) {
        if (data.total_discount <= 0) return '';
        return '<span class="o100-price-wrap">'
            + '<span class="o100-price-discounted">' + formatPrice(data.discounted_price) + '</span> '
            + '<span class="o100-price-original">' + formatPrice(data.original_price) + '</span>'
            + '</span>';
    }

    // ─── Product Cards ───────────────────────────────────────────
    function applyToProductCards() {
        if (!promoData || $.isEmptyObject(promoData)) return;

        // Select all WooFood product card wrappers
        var $cards = $('.fditem-list, .item-grid');
        log('Found', $cards.length, 'product cards');

        $cards.each(function () {
            var $card = $(this);
            if ($card.data('o100-promo-applied')) return;

            var productId = getProductId($card);
            if (!productId || !promoData[productId]) return;

            var data = promoData[productId];
            $card.data('o100-promo-applied', true);
            log('Applying promo to card for product', productId);

            var promoBadge = buildPromoBadge(data.rules);
            var saleBadge = buildSaleBadge(data.rules);
            var priceHtml = data.total_discount > 0 ? buildPriceDisplay(data) : '';

            // Detect which template mode from the <figure> inside
            var $figure = $card.find('figure').first();
            if (!$figure.length) return;

            var figClass = $figure.attr('class') || '';

            // ──── LIST STYLE 1 / 4 / 5 ────
            if (figClass.indexOf('fdstyle-list-1') !== -1) {
                injectListStyle1($card, $figure, promoBadge, saleBadge, priceHtml);
            }
            // ──── LIST STYLE 2 ────
            else if (figClass.indexOf('fdstyle-list-2') !== -1) {
                injectListStyle2($card, $figure, promoBadge, saleBadge, priceHtml);
            }
            // ──── LIST STYLE 3 ────
            else if (figClass.indexOf('fdstyle-list-3') !== -1) {
                injectListStyle3($card, $figure, promoBadge, saleBadge, priceHtml);
            }
            // ──── GRID STYLE 1 ────
            else if (figClass.indexOf('exstyle-1') !== -1) {
                injectGridStyle($card, $figure, promoBadge, saleBadge, priceHtml, '.exstyle-1-image');
            }
            // ──── GRID STYLE 2 ────
            else if (figClass.indexOf('exstyle-2') !== -1) {
                injectGridStyle($card, $figure, promoBadge, saleBadge, priceHtml, '.exstyle-2-image');
            }
            // ──── GRID STYLE 3 ────
            else if (figClass.indexOf('exstyle-3') !== -1) {
                injectGridStyle($card, $figure, promoBadge, saleBadge, priceHtml, '.exstyle-3-image');
            }
            // ──── GRID STYLE 4 / 5 ────
            else if (figClass.indexOf('exstyle-4') !== -1) {
                injectGridStyle($card, $figure, promoBadge, saleBadge, priceHtml, '.exstyle-4-image');
            }
            // ──── FALLBACK: unknown template ────
            else {
                injectFallback($card, $figure, promoBadge, saleBadge, priceHtml);
            }
        });
    }

    /**
     * List Style 1 / 4 / 5:
     * figure.fdstyle-list-1 > .fdlist_1_detail > .fdlist_1_title > .fdlist_1_name + .fdlist_1_price
     */
    function injectListStyle1($card, $figure, promoBadge, saleBadge, priceHtml) {
        // Sale badge on image
        if (saleBadge) {
            var $img = $figure.find('.exf-img').first();
            if ($img.length && !$img.find('.o100-sale-badge').length) {
                $img.css('position', 'relative').prepend(saleBadge);
            }
        }
        // Promo badge after name
        if (promoBadge) {
            var $name = $figure.find('.fdlist_1_name').first();
            if ($name.length && !$name.find('.o100-promo-line').length) {
                $name.append('<div class="o100-promo-line">' + promoBadge + '</div>');
            }
        }
        // Price modification in .fdlist_1_price
        if (priceHtml) {
            var $price = $figure.find('.fdlist_1_price').first();
            if ($price.length) {
                $price.html(priceHtml);
            }
        }
    }

    /**
     * List Style 2:
     * figure.fdstyle-list-2 > .fdlist_2_detail > .fdlist_2_title > .fdlist_2_name + .fdlist_2_price
     */
    function injectListStyle2($card, $figure, promoBadge, saleBadge, priceHtml) {
        if (saleBadge) {
            var $img = $figure.find('.exf-img').first();
            if ($img.length && !$img.find('.o100-sale-badge').length) {
                $img.css('position', 'relative').prepend(saleBadge);
            }
        }
        if (promoBadge) {
            var $name = $figure.find('.fdlist_2_name').first();
            if ($name.length && !$name.find('.o100-promo-line').length) {
                $name.append('<div class="o100-promo-line">' + promoBadge + '</div>');
            }
        }
        if (priceHtml) {
            var $price = $figure.find('.fdlist_2_price').first();
            if ($price.length) {
                // Keep the add-to-cart button (exwoofood-addicon) if present
                var $addIcon = $price.find('.exwoofood-addicon').detach();
                $price.html(priceHtml);
                if ($addIcon.length) $price.append($addIcon);
            }
        }
    }

    /**
     * List Style 3:
     * figure.fdstyle-list-3 > .fdlist_3_title > .fdlist_3_name + .fdlist_3_price
     */
    function injectListStyle3($card, $figure, promoBadge, saleBadge, priceHtml) {
        if (saleBadge) {
            var $img = $figure.find('.exf-img').first();
            if ($img.length && !$img.find('.o100-sale-badge').length) {
                $img.css('position', 'relative').prepend(saleBadge);
            }
        }
        if (promoBadge) {
            var $name = $figure.find('.fdlist_3_name').first();
            if ($name.length && !$name.find('.o100-promo-line').length) {
                $name.after('<div class="o100-promo-line">' + promoBadge + '</div>');
            }
        }
        if (priceHtml) {
            var $price = $figure.find('.fdlist_3_price').first();
            if ($price.length) {
                $price.html(priceHtml);
            }
        }
    }

    /**
     * Grid Styles 1-5:
     * figure.exstyle-{N} > .exstyle-{N}-image + figcaption > h3(title) + h5(price)
     */
    function injectGridStyle($card, $figure, promoBadge, saleBadge, priceHtml, imageSelector) {
        // Sale badge on image container
        if (saleBadge) {
            var $img = $figure.find(imageSelector).first();
            if ($img.length && !$img.find('.o100-sale-badge').length) {
                $img.css('position', 'relative').prepend(saleBadge);
            }
        }
        // Promo badge after the h3 title in figcaption
        if (promoBadge) {
            var $title = $figure.find('figcaption h3').first();
            if ($title.length && !$title.siblings('.o100-promo-line').length) {
                $title.after('<div class="o100-promo-line">' + promoBadge + '</div>');
            }
        }
        // Price modification in h5
        if (priceHtml) {
            var $price = $figure.find('figcaption h5').first();
            if ($price.length) {
                $price.html(priceHtml);
            }
        }
    }

    /**
     * Fallback for unknown templates
     */
    function injectFallback($card, $figure, promoBadge, saleBadge, priceHtml) {
        log('Using fallback injection for figure class:', $figure.attr('class'));
        if (promoBadge) {
            var $name = $figure.find('[class*="name"], h3').first();
            if ($name.length) {
                $name.after('<div class="o100-promo-line">' + promoBadge + '</div>');
            }
        }
        if (priceHtml) {
            var $price = $figure.find('[class*="price"], h5').first();
            if ($price.length) {
                $price.html(priceHtml);
            }
        }
    }

    // ─── Popup Modal ─────────────────────────────────────────────
    function applyToPopupModal() {
        var $modal = $('#food_modal');
        if (!$modal.length || !$modal.hasClass('exfd-modal-active')) return;

        // Get product ID from the inner .ex-modal-big#product-{pid} or add-to-cart button
        var productId = null;

        // Method 1: .ex-modal-big id
        var $inner = $modal.find('.ex-modal-big[id^="product-"]');
        if ($inner.length) {
            productId = $inner.attr('id').replace('product-', '');
        }

        // Method 2: add-to-cart button value
        if (!productId) {
            var $btn = $modal.find('button[name="add-to-cart"]');
            if ($btn.length) productId = String($btn.val());
        }

        log('Modal product ID:', productId);
        if (!productId || !promoData[productId]) return;

        // Avoid duplicate injection
        var $des = $modal.find('.fd_modal_des');
        if ($des.find('.o100-popup-promo').length) return;

        var data = promoData[productId];
        log('Applying promo to modal for product', productId);

        // 1. Promo badge after the h3 title in .fd_modal_des
        var promoBadge = buildPromoBadge(data.rules);
        if (promoBadge && $des.length) {
            var $title = $des.find('h3').first();
            if ($title.length) {
                $title.after('<div class="o100-popup-promo">' + promoBadge + '</div>');
            }
        }

        // 2. Modify price in .fd_modal_des h5
        if (data.total_discount > 0) {
            var priceHtml = buildPriceDisplay(data);
            if (priceHtml && $des.length) {
                var $price = $des.find('h5').first();
                if ($price.length) {
                    $price.html(priceHtml);
                }
            }
        }
    }

    // ─── First-Time Popup ─────────────────────────────────────────
    function initFirstTimePopup() {
        if (!VARS.is_first_time || !VARS.first_time_text) {
            return;
        }

        log('Injecting first-time customer popup');

        var $popup = $('<div class="o100-first-time-popup">' + VARS.first_time_text + '</div>');
        $('body').append($popup);

        // Slide up animation
        setTimeout(function () {
            $popup.addClass('o100-ftp-visible');
        }, 500);

        // Auto hide after duration
        var durationMs = (VARS.first_time_duration || 5) * 1000;
        setTimeout(function () {
            $popup.removeClass('o100-ftp-visible').addClass('o100-ftp-hiding');
            setTimeout(function () {
                $popup.remove();
            }, 600); // wait for CSS transition
        }, durationMs + 500);
    }

    // ─── Checkout Shipping Strikethrough ───────────────────────
    function applyShippingStrikethrough() {
        // Find the "Shipping fee" row and the "Free Shipping Promo" row added by our backend
        var $feeRows = $('.cart_totals tr.fee, .shop_table.woocommerce-checkout-review-order-table tr.fee');
        
        var $shippingRow = null;
        var $promoRow = null;

        if ($feeRows.length) {
            $feeRows.each(function() {
                var labelText = $(this).find('th').text().trim();
                if (labelText === 'Shipping fee' || labelText.indexOf('Shipping fee') !== -1) {
                    $shippingRow = $(this);
                } else if (labelText === 'Free Shipping Promo' || labelText.indexOf('Free Shipping Promo') !== -1) {
                    $promoRow = $(this);
                }
            });
        }

        if ($shippingRow && $promoRow) {
            // We have both the original shipping fee and our promo fee negating it
            var $shippingVal = $shippingRow.find('td .woocommerce-Price-amount');
            var $promoVal = $promoRow.find('td .woocommerce-Price-amount');
            
            if ($shippingVal.length && !$shippingVal.hasClass('o100-price-original')) {
                // Strike through the original shipping fee
                $shippingVal.addClass('o100-price-original').css({
                    'text-decoration': 'line-through',
                    'opacity': '0.7',
                    'margin-right': '6px'
                });
                
                // Set shipping to 0 or free
                var freeText = VARS.free_shipping_text || 'Free';
                $shippingVal.after('<span class="o100-price-discounted" style="color: #4caf50; font-weight: bold;">' + freeText + '</span>');
                
                // We can optionally add a small badge or text below the shipping fee
                var promoText = VARS.free_shipping_promo || 'First Order Free Shipping!';
                $shippingRow.find('td').append('<div class="o100-shipping-promo" style="font-size: 0.85em; color: #e91e63; margin-top: 4px;">' + promoText + '</div>');

                // Hide the backend promo row so it doesn't clutter the totals (it still applies mathematically)
                $promoRow.hide();
            }

            // Dynamically ensure it is hidden
            $('body').addClass('o100-free-shipping-active');
            $('.exwf-min-free-ship, .exwf-mini-amount').hide();

            // Hide the WooCommerce Error list item for WooFood
            $('.woocommerce-error li').each(function() {
                var txt = $(this).text() || '';
                if (txt.indexOf('amount more to get free delivery') !== -1) {
                    $(this).hide();
                    if ($(this).parent().children('li:visible').length === 0) {
                        $(this).closest('.woocommerce-error').hide();
                    }
                }
            });

        } else {
            // Free shipping no longer applies (or never did). Ensure WooFood's messages are visible.
            $('body').removeClass('o100-free-shipping-active');
            $('.exwf-min-free-ship, .exwf-mini-amount').show();

            // Show the WooCommerce Error list item for WooFood
            $('.woocommerce-error li').each(function() {
                var txt = $(this).text() || '';
                if (txt.indexOf('amount more to get free delivery') !== -1) {
                    $(this).show();
                    $(this).closest('.woocommerce-error').show();
                    $(this).closest('.woocommerce-notices-wrapper').show();
                }
            });
        }
    }

    // ─── Init ────────────────────────────────────────────────────
    function init() {
        promoData = loadPromoData();
        if ($.isEmptyObject(promoData)) {
            // Even if no promo data for products, we might still have shipping promos
            applyShippingStrikethrough();
            
            // Re-apply after AJAX content updates (checkout update etc.)
            $(document).ajaxComplete(function () {
                setTimeout(applyShippingStrikethrough, 100);
            });
            // Also bind to Woo update checkout event
            $(document.body).on('updated_checkout updated_cart_totals', function() {
                setTimeout(applyShippingStrikethrough, 100);
            });
            return;
        }

        log('Initializing with', Object.keys(promoData).length, 'products');

        // Apply to cards already on page
        applyToProductCards();
        
        // Apply to shipping
        applyShippingStrikethrough();

        // First time popup
        initFirstTimePopup();

        // Watch for #food_modal class changes (WooFood adds/removes exfd-modal-active)
        var foodModal = document.getElementById('food_modal');
        if (foodModal) {
            var observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        var $fm = $(mutation.target);
                        if ($fm.hasClass('exfd-modal-active')) {
                            // New product opened — clear old promo and re-apply
                            setTimeout(function () {
                                $fm.find('.o100-popup-promo').remove();
                                applyToPopupModal();
                            }, 350);
                        }
                    }
                });
            });
            observer.observe(foodModal, { attributes: true, attributeFilter: ['class'] });
            log('Watching #food_modal for class changes');
        } else {
            // Modal may not exist yet, watch for it
            var bodyObs = new MutationObserver(function (muts) {
                var fm = document.getElementById('food_modal');
                if (fm) {
                    bodyObs.disconnect();
                    var obs2 = new MutationObserver(function (mm) {
                        mm.forEach(function (m) {
                            if (m.type === 'attributes' && $(m.target).hasClass('exfd-modal-active')) {
                                setTimeout(function () {
                                    $(m.target).find('.o100-popup-promo').remove();
                                    applyToPopupModal();
                                }, 350);
                            }
                        });
                    });
                    obs2.observe(fm, { attributes: true, attributeFilter: ['class'] });
                    log('#food_modal appeared, now watching');
                }
            });
            bodyObs.observe(document.body, { childList: true, subtree: true });
        }

        // Backup: click handler for .exfd_modal_click
        $(document).on('click', '.exfd_modal_click', function () {
            setTimeout(function () {
                var $fm = $('#food_modal');
                if ($fm.hasClass('exfd-modal-active') && !$fm.find('.o100-popup-promo').length) {
                    applyToPopupModal();
                }
            }, 600);
        });

        // Re-apply after AJAX content updates (category tab switching etc.)
        $(document).ajaxComplete(function () {
            setTimeout(applyToProductCards, 400);
            setTimeout(applyShippingStrikethrough, 100);
        });
        
        // Also bind to Woo update checkout event
        $(document.body).on('updated_checkout updated_cart_totals', function() {
            setTimeout(applyShippingStrikethrough, 100);
        });

        // Trigger WooCommerce checkout update when email changes to recalc free shipping
        $(document.body).on('change', '#billing_email', function () {
            if ($('form.checkout').length) {
                $('body').trigger('update_checkout');
            }
        });
    }

    $(document).ready(init);

})(jQuery);

/* TS: 20260125175237 */
