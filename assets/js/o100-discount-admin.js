/**
 * Discount Admin JS
 * Handles sub-tab switching, rule CRUD form, AJAX operations,
 * and filter-based product/category selection.
 */
(function ($) {
    'use strict';

    var AJAX = o100_discount_admin;

    // ─── Sub-tab Navigation ───────────────────────────────────────
    function initSubTabs() {
        var $tabs = $('.o100-discount-subtabs a');
        var storageKey = 'o100_discount_subtab';

        function activateSubTab(id) {
            $tabs.removeClass('o100-subtab-active');
            $tabs.filter('[data-subtab="' + id + '"]').addClass('o100-subtab-active');
            $('.o100-discount-subtab-content').hide();
            $('.o100-discount-subtab-content[data-subtab="' + id + '"]').show();
            try { localStorage.setItem(storageKey, id); } catch (e) { }
        }

        $tabs.on('click', function (e) {
            e.preventDefault();
            activateSubTab($(this).data('subtab'));
        });

        var saved = '';
        try { saved = localStorage.getItem(storageKey); } catch (e) { }
        activateSubTab(saved || 'rules');
    }

    // ─── Filter System ────────────────────────────────────────────
    var filterCounter = 0;

    function createFilterRow(filterType, items) {
        filterType = filterType || 'products';
        items = items || [];
        var idx = filterCounter++;

        var html = '<div class="o100-dr-filter-row" data-filter-idx="' + idx + '">'
            + '<select class="o100-dr-filter-type">'
            + '<option value="all_products">All Products</option>'
            + '<option value="products"' + (filterType === 'products' ? ' selected' : '') + '>Products</option>'
            + '<option value="category"' + (filterType === 'category' ? ' selected' : '') + '>Category</option>'
            + '</select>'
            + '<div class="o100-dr-filter-search-area"' + (filterType === 'all_products' ? ' style="display:none;"' : '') + '>'
            + '<div class="o100-dr-search-wrap">'
            + '<input type="text" class="o100-dr-filter-search regular-text" placeholder="Type to search..." autocomplete="off">'
            + '<div class="o100-dr-search-results"></div>'
            + '</div>'
            + '<div class="o100-dr-selected-tags"></div>'
            + '</div>'
            + '<button type="button" class="button button-small o100-dr-remove-filter" title="Remove filter">✕</button>'
            + '</div>';

        var $row = $(html);
        $('#o100-dr-filters').append($row);

        // Add pre-existing items as tags
        if (items.length) {
            var $tags = $row.find('.o100-dr-selected-tags');
            items.forEach(function (item) {
                addTag($tags, item.id, item.label, filterType);
            });
        }

        return $row;
    }

    function addTag($container, id, label, filterType) {
        // Check if already exists
        if ($container.find('.o100-dr-tag[data-id="' + id + '"]').length) return;

        var typeLabel = filterType === 'category' ? 'cat' : 'prod';
        var $tag = $('<span class="o100-dr-tag" data-id="' + id + '" data-type="' + filterType + '">'
            + '<span class="o100-dr-tag-type">' + typeLabel + '</span>'
            + '<span class="o100-dr-tag-label">' + escapeHtml(label) + '</span>'
            + '<span class="o100-dr-tag-id">#' + id + '</span>'
            + '<button type="button" class="o100-dr-tag-remove" title="Remove">✕</button>'
            + '</span>');
        $container.append($tag);
        syncHiddenFields();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function syncHiddenFields() {
        var productIds = [];
        var categoryIds = [];

        $('.o100-dr-filter-row').each(function () {
            var type = $(this).find('.o100-dr-filter-type').val();
            if (type === 'all_products') return;

            $(this).find('.o100-dr-tag').each(function () {
                var id = $(this).data('id');
                var tagType = $(this).data('type');
                if (tagType === 'products') {
                    productIds.push(id);
                } else if (tagType === 'category') {
                    categoryIds.push(id);
                }
            });
        });

        $('#o100-dr-products').val(productIds.join(','));
        $('#o100-dr-categories').val(categoryIds.join(','));
    }

    function initFilters() {
        // Add filter button
        $('#o100-dr-add-filter').on('click', function () {
            createFilterRow();
        });

        // Remove filter
        $(document).on('click', '.o100-dr-remove-filter', function () {
            $(this).closest('.o100-dr-filter-row').remove();
            syncHiddenFields();
        });

        // Filter type change
        $(document).on('change', '.o100-dr-filter-type', function () {
            var $row = $(this).closest('.o100-dr-filter-row');
            var type = $(this).val();
            if (type === 'all_products') {
                $row.find('.o100-dr-filter-search-area').hide();
                $row.find('.o100-dr-selected-tags').empty();
            } else {
                $row.find('.o100-dr-filter-search-area').show();
                $row.find('.o100-dr-selected-tags').empty();
            }
            syncHiddenFields();
        });

        // Remove tag
        $(document).on('click', '.o100-dr-tag-remove', function () {
            $(this).closest('.o100-dr-tag').remove();
            syncHiddenFields();
        });

        // Search input — AJAX with debounce
        var searchTimer = null;
        $(document).on('input', '.o100-dr-filter-search', function () {
            var $input = $(this);
            var $row = $input.closest('.o100-dr-filter-row');
            var $results = $row.find('.o100-dr-search-results');
            var query = $input.val().trim();
            var filterType = $row.find('.o100-dr-filter-type').val();

            if (searchTimer) clearTimeout(searchTimer);

            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            searchTimer = setTimeout(function () {
                doSearch($results, $row, filterType, query);
            }, 300);
        });

        // Hide search results when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.o100-dr-search-wrap').length) {
                $('.o100-dr-search-results').hide();
            }
        });

        // Click search result
        $(document).on('click', '.o100-dr-search-result-item', function () {
            var $item = $(this);
            var $row = $item.closest('.o100-dr-filter-row');
            var $tags = $row.find('.o100-dr-selected-tags');
            var filterType = $row.find('.o100-dr-filter-type').val();

            addTag($tags, $item.data('id'), $item.data('label'), filterType);
            $row.find('.o100-dr-search-results').hide();
            $row.find('.o100-dr-filter-search').val('');
        });

        // Get Product (Y) search — same pattern for Buy X Get Y
        var getProductTimer = null;
        $('#o100-dr-get-product-search').on('input', function () {
            var $input = $(this);
            var $results = $('#o100-dr-get-product-results');
            var query = $input.val().trim();

            if (getProductTimer) clearTimeout(getProductTimer);
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            getProductTimer = setTimeout(function () {
                $.post(AJAX.ajax_url, {
                    action: 'o100_search_items',
                    nonce: AJAX.nonce,
                    filter_type: 'products',
                    query: query
                }, function (resp) {
                    if (!resp.success || !resp.data.length) {
                        $results.html('<div class="o100-dr-search-no-results">No results</div>').show();
                        return;
                    }
                    var html = '';
                    resp.data.forEach(function (item) {
                        html += '<div class="o100-dr-search-result-item o100-dr-get-product-item" data-id="' + item.id + '" data-label="' + escapeHtml(item.label) + '">'
                            + '#' + item.id + ' ' + escapeHtml(item.label)
                            + '</div>';
                    });
                    $results.html(html).show();
                });
            }, 300);
        });

        // Click on Get Product search result
        $(document).on('click', '.o100-dr-get-product-item', function () {
            var id = $(this).data('id');
            var label = $(this).data('label');
            $('#o100-dr-get-product').val(id);
            $('#o100-dr-get-product-tags').html(
                '<span class="o100-dr-tag" data-id="' + id + '">'
                + '<span class="o100-dr-tag-label">' + escapeHtml(label) + '</span>'
                + '<span class="o100-dr-tag-id">#' + id + '</span>'
                + '<button type="button" class="o100-dr-tag-remove o100-dr-get-product-remove" title="Remove">✕</button>'
                + '</span>'
            );
            $('#o100-dr-get-product-search').val('');
            $('#o100-dr-get-product-results').hide();
        });

        $(document).on('click', '.o100-dr-get-product-remove', function () {
            $('#o100-dr-get-product').val(0);
            $('#o100-dr-get-product-tags').empty();
        });
    }

    function doSearch($results, $row, filterType, query) {
        $.post(AJAX.ajax_url, {
            action: 'o100_search_items',
            nonce: AJAX.nonce,
            filter_type: filterType,
            query: query
        }, function (resp) {
            if (!resp.success || !resp.data.length) {
                $results.html('<div class="o100-dr-search-no-results">No results found</div>').show();
                return;
            }
            var html = '';
            resp.data.forEach(function (item) {
                html += '<div class="o100-dr-search-result-item" data-id="' + item.id + '" data-label="' + escapeHtml(item.label) + '">'
                    + '#' + item.id + ' ' + escapeHtml(item.label)
                    + '</div>';
            });
            $results.html(html).show();
        });
    }

    // ─── Rule Form Logic ──────────────────────────────────────────
    function initRuleForm() {
        // Show/hide sections based on rule type
        $('#o100-dr-rule-type').on('change', function () {
            var type = $(this).val();
            $('#o100-dr-section-simple').toggle(type === 'simple' || type === 'set');
            $('#o100-dr-section-bogo').toggle(type === 'bogo' || type === 'buy_x_get_y');
            $('#o100-dr-section-buyxgety').toggle(type === 'buy_x_get_y');
            $('#o100-dr-section-bulk').toggle(type === 'bulk');
            $('#o100-dr-section-set').toggle(type === 'set');
        });

        // Show/hide discount value for free_item and free_shipping
        $('#o100-dr-discount-type').on('change', function () {
            var type = $(this).val();
            $('#o100-dr-field-discount-value').toggle(type !== 'free_item' && type !== 'free_shipping');
        });

        // Show/hide get discount value
        $('#o100-dr-get-discount-type').on('change', function () {
            $('#o100-dr-field-get-discount-value').toggle($(this).val() !== 'free');
        });

        // Add New Rule button
        $('#o100-dr-add-new').on('click', function () {
            resetForm();
            $('#o100-dr-edit-form').slideDown();
            $(this).hide();
        });

        // Cancel
        $('#o100-dr-cancel-edit').on('click', function () {
            $('#o100-dr-edit-form').slideUp();
            $('#o100-dr-add-new').show();
        });

        // Edit button
        $(document).on('click', '.o100-dr-edit', function () {
            var ruleId = $(this).data('id');
            loadRuleForEdit(ruleId);
        });

        // Delete button
        $(document).on('click', '.o100-dr-delete', function () {
            if (!confirm(AJAX.i18n.confirm_delete)) return;
            var ruleId = $(this).data('id');
            $.post(AJAX.ajax_url, {
                action: 'o100_delete_discount_rule',
                nonce: AJAX.nonce,
                rule_id: ruleId
            }, function (resp) {
                if (resp.success) {
                    $('tr[data-rule-id="' + ruleId + '"]').fadeOut(300, function () { $(this).remove(); });
                }
            });
        });

        // Toggle status
        $(document).on('click', '.o100-dr-toggle-status', function () {
            var $btn = $(this);
            var ruleId = $btn.data('id');
            $.post(AJAX.ajax_url, {
                action: 'o100_toggle_discount_rule',
                nonce: AJAX.nonce,
                rule_id: ruleId
            }, function (resp) {
                if (resp.success) {
                    var $row = $('tr[data-rule-id="' + ruleId + '"]');
                    var $dot = $row.find('.o100-dr-status-dot');
                    $dot.removeClass('o100-dr-status-active o100-dr-status-inactive')
                        .addClass('o100-dr-status-' + resp.data.status);
                    $btn.text(resp.data.status === 'active' ? 'Active' : 'Inactive');
                }
            });
        });

        // Duplicate
        $(document).on('click', '.o100-dr-duplicate', function () {
            var ruleId = $(this).data('id');
            $.post(AJAX.ajax_url, {
                action: 'o100_duplicate_discount_rule',
                nonce: AJAX.nonce,
                rule_id: ruleId
            }, function (resp) {
                if (resp.success) {
                    location.reload();
                }
            });
        });

        // Save
        $('#o100-dr-save-rule').on('click', saveRule);

        // Bulk tier add
        $('#o100-dr-add-tier').on('click', function () {
            var idx = $('.o100-dr-bulk-tier').length;
            var html = '<div class="o100-dr-bulk-tier" data-index="' + idx + '">'
                + '<input type="number" class="o100-dr-tier-min" placeholder="Min Qty" min="1">'
                + '<input type="number" class="o100-dr-tier-max" placeholder="Max Qty" min="0">'
                + '<select class="o100-dr-tier-type"><option value="percentage">%</option><option value="fixed">$</option></select>'
                + '<input type="number" class="o100-dr-tier-value" placeholder="Value" min="0" step="0.01">'
                + '<button type="button" class="button button-small o100-dr-remove-tier">×</button>'
                + '</div>';
            $('#o100-dr-bulk-tiers').append(html);
        });

        $(document).on('click', '.o100-dr-remove-tier', function () {
            $(this).closest('.o100-dr-bulk-tier').remove();
        });
    }

    function resetForm() {
        $('#o100-dr-rule-id').val(0);
        $('#o100-dr-title').val('');
        $('#o100-dr-status').val('active');
        $('#o100-dr-scope').val('product');
        $('#o100-dr-rule-type').val('simple').trigger('change');
        $('#o100-dr-discount-type').val('percentage').trigger('change');
        $('#o100-dr-discount-value').val('');
        $('#o100-dr-priority').val(10);
        $('#o100-dr-exclusive').prop('checked', false);
        $('#o100-dr-usage-limit').val(0);
        $('#o100-dr-buy-qty').val(1);
        $('#o100-dr-get-qty').val(1);
        $('#o100-dr-get-product').val(0);
        $('#o100-dr-get-product-search').val('');
        $('#o100-dr-get-product-tags').empty();
        $('#o100-dr-get-discount-type').val('free').trigger('change');
        $('#o100-dr-get-discount-value').val('');
        $('#o100-dr-products').val('');
        $('#o100-dr-categories').val('');
        $('#o100-dr-filters').empty();
        filterCounter = 0;

        // Reset conditions
        $('#o100-dr-conditions-list').html('<p class="o100-dr-no-conditions">' + AJAX.i18n.no_conditions + '</p>');
        $('input[name="o100_dr_condition_relationship"][value="and"]').prop('checked', true);

        // Reset date/promo/popup
        $('#o100-dr-start-date, #o100-dr-end-date').val('');
        $('#o100-dr-promo-text').val('');
        $('#o100-dr-show-badge').prop('checked', false);
        $('#o100-dr-badge-text').val('');
        $('#o100-dr-show-banner').prop('checked', false);
        $('#o100-dr-banner-text').val('');
        $('#o100-dr-popup-text').val('');
        $('#o100-dr-popup-pages').val('all');
        $('#o100-dr-popup-duration').val(5);
        $('#o100-dr-set-qty').val(2);

        $('#o100-dr-bulk-tiers').html(
            '<div class="o100-dr-bulk-tier" data-index="0">'
            + '<input type="number" class="o100-dr-tier-min" placeholder="Min Qty" min="1">'
            + '<input type="number" class="o100-dr-tier-max" placeholder="Max Qty" min="0">'
            + '<select class="o100-dr-tier-type"><option value="percentage">%</option><option value="fixed">$</option></select>'
            + '<input type="number" class="o100-dr-tier-value" placeholder="Value" min="0" step="0.01">'
            + '<button type="button" class="button button-small o100-dr-remove-tier">×</button></div>'
        );
        $('.o100-dr-save-status').text('');
    }

    function loadRuleForEdit(ruleId) {
        $.ajax({
            url: AJAX.ajax_url,
            method: 'POST',
            data: {
                action: 'o100_get_discount_rule',
                nonce: AJAX.nonce,
                rule_id: ruleId
            },
            success: function (resp) {
                if (!resp.success) return;
                var d = resp.data;
                resetForm();
                $('#o100-dr-rule-id').val(ruleId);
                $('#o100-dr-title').val(d.title);
                $('#o100-dr-status').val(d.status);
                $('#o100-dr-scope').val(d.scope);
                $('#o100-dr-rule-type').val(d.rule_type).trigger('change');
                $('#o100-dr-discount-type').val(d.discount_type).trigger('change');
                $('#o100-dr-discount-value').val(d.discount_value);
                $('#o100-dr-priority').val(d.priority);
                $('#o100-dr-exclusive').prop('checked', !!d.exclusive);
                $('#o100-dr-usage-limit').val(d.usage_limit || 0);
                $('#o100-dr-buy-qty').val(d.buy_qty);
                $('#o100-dr-get-qty').val(d.get_qty);
                $('#o100-dr-get-product').val(d.get_product);
                $('#o100-dr-get-discount-type').val(d.get_discount_type).trigger('change');
                $('#o100-dr-get-discount-value').val(d.get_discount_value);

                // Restore Get Product tag
                if (d.get_product && d.get_product > 0 && d.get_product_name) {
                    $('#o100-dr-get-product-tags').html(
                        '<span class="o100-dr-tag" data-id="' + d.get_product + '">'
                        + '<span class="o100-dr-tag-label">' + escapeHtml(d.get_product_name) + '</span>'
                        + '<span class="o100-dr-tag-id">#' + d.get_product + '</span>'
                        + '<button type="button" class="o100-dr-tag-remove o100-dr-get-product-remove" title="Remove">✕</button>'
                        + '</span>'
                    );
                }

                // Restore filter rows for products
                if (d.products && d.products.length) {
                    var productItems = [];
                    if (d.product_names) {
                        d.products.forEach(function (pid, i) {
                            productItems.push({ id: pid, label: d.product_names[i] || '#' + pid });
                        });
                    } else {
                        d.products.forEach(function (pid) {
                            productItems.push({ id: pid, label: '#' + pid });
                        });
                    }
                    createFilterRow('products', productItems);
                }

                // Restore filter rows for categories
                if (d.categories && d.categories.length) {
                    var catItems = [];
                    if (d.category_names) {
                        d.categories.forEach(function (cid, i) {
                            catItems.push({ id: cid, label: d.category_names[i] || '#' + cid });
                        });
                    } else {
                        d.categories.forEach(function (cid) {
                            catItems.push({ id: cid, label: '#' + cid });
                        });
                    }
                    createFilterRow('category', catItems);
                }

                // Restore conditions (new format)
                var conditions = d.conditions || [];
                // If no new conditions, check legacy triggers and convert them for display
                if (!conditions.length && d.triggers && Object.keys(d.triggers).length) {
                    conditions = convertLegacyTriggersForDisplay(d.triggers);
                }
                populateConditions(conditions, d.condition_relationship || 'and');

                // Date/promo
                $('#o100-dr-start-date').val(d.start_date || '');
                $('#o100-dr-end-date').val(d.end_date || '');
                $('#o100-dr-promo-text').val(d.promo_text || '');
                $('#o100-dr-show-badge').prop('checked', !!d.show_badge);
                $('#o100-dr-badge-text').val(d.badge_text || '');
                $('#o100-dr-show-banner').prop('checked', !!d.show_banner);
                $('#o100-dr-banner-text').val(d.banner_text || '');

                // Popup
                $('#o100-dr-popup-text').val(d.popup_text || '');
                $('#o100-dr-popup-pages').val(d.popup_pages || 'all');
                $('#o100-dr-popup-duration').val(d.popup_duration || 5);

                // Set/Bundle
                $('#o100-dr-set-qty').val(d.set_qty || 2);

                // Bulk tiers
                if (d.bulk_tiers && d.bulk_tiers.length) {
                    $('#o100-dr-bulk-tiers').empty();
                    d.bulk_tiers.forEach(function (tier, i) {
                        var html = '<div class="o100-dr-bulk-tier" data-index="' + i + '">'
                            + '<input type="number" class="o100-dr-tier-min" value="' + (tier.min_qty || '') + '" placeholder="Min" min="1">'
                            + '<input type="number" class="o100-dr-tier-max" value="' + (tier.max_qty || '') + '" placeholder="Max" min="0">'
                            + '<select class="o100-dr-tier-type"><option value="percentage"' + (tier.discount_type === 'percentage' ? ' selected' : '') + '>%</option><option value="fixed"' + (tier.discount_type === 'fixed' ? ' selected' : '') + '>$</option></select>'
                            + '<input type="number" class="o100-dr-tier-value" value="' + (tier.discount_value || '') + '" placeholder="Value" min="0" step="0.01">'
                            + '<button type="button" class="button button-small o100-dr-remove-tier">×</button></div>';
                        $('#o100-dr-bulk-tiers').append(html);
                    });
                }

                $('#o100-dr-edit-form').slideDown();
                $('#o100-dr-add-new').hide();
            }
        });
    }

    /**
     * Convert legacy triggers to conditions array for display in the editor
     */
    function convertLegacyTriggersForDisplay(t) {
        var conditions = [];
        if (t.order_type && t.order_type.length) {
            conditions.push({ type: 'order_type', options: { operator: 'in_list', value: t.order_type } });
        }
        if (t.time_based) {
            if (t.time_based.start && t.time_based.end) {
                conditions.push({ type: 'order_time', options: { operator: 'between', value: t.time_based.start, value2: t.time_based.end } });
            }
            if (t.time_based.days && t.time_based.days.length) {
                conditions.push({ type: 'order_days', options: { operator: 'in_list', value: t.time_based.days } });
            }
        }
        if (t.cart_subtotal) {
            if (t.cart_subtotal.min && t.cart_subtotal.max && parseFloat(t.cart_subtotal.max) > 0) {
                conditions.push({ type: 'cart_subtotal', options: { operator: 'between', value: t.cart_subtotal.min, value2: t.cart_subtotal.max } });
            } else if (t.cart_subtotal.min) {
                conditions.push({ type: 'cart_subtotal', options: { operator: 'greater_than_or_equal', value: t.cart_subtotal.min } });
            }
        }
        if (t.quantity) {
            if (t.quantity.min && t.quantity.max && parseInt(t.quantity.max) > 0) {
                conditions.push({ type: 'cart_items_quantity', options: { operator: 'between', value: t.quantity.min, value2: t.quantity.max } });
            } else if (t.quantity.min) {
                conditions.push({ type: 'cart_items_quantity', options: { operator: 'greater_than_or_equal', value: t.quantity.min } });
            }
        }
        if (t.first_time) {
            conditions.push({ type: 'first_order', options: { operator: 'yes', value: '' } });
        }
        return conditions;
    }

    function saveRule() {
        var $status = $('.o100-dr-save-status');
        $status.text(AJAX.i18n.saving);

        // Collect conditions
        var conditions = collectConditions();
        var conditionRelationship = $('input[name="o100_dr_condition_relationship"]:checked').val() || 'and';

        // Collect bulk tiers
        var bulkTiers = [];
        $('.o100-dr-bulk-tier').each(function () {
            var $t = $(this);
            var min = $t.find('.o100-dr-tier-min').val();
            if (min) {
                bulkTiers.push({
                    min_qty: min,
                    max_qty: $t.find('.o100-dr-tier-max').val() || 0,
                    discount_type: $t.find('.o100-dr-tier-type').val(),
                    discount_value: $t.find('.o100-dr-tier-value').val() || 0
                });
            }
        });

        // Parse products and categories from hidden fields
        var productsVal = $('#o100-dr-products').val();
        var categoriesVal = $('#o100-dr-categories').val();
        var products = productsVal ? productsVal.split(',').map(Number).filter(Boolean) : [];
        var categories = categoriesVal ? categoriesVal.split(',').map(Number).filter(Boolean) : [];

        var data = {
            action: 'o100_save_discount_rule',
            nonce: AJAX.nonce,
            rule_id: $('#o100-dr-rule-id').val(),
            title: $('#o100-dr-title').val(),
            status: $('#o100-dr-status').val(),
            scope: $('#o100-dr-scope').val(),
            rule_type: $('#o100-dr-rule-type').val(),
            discount_type: $('#o100-dr-discount-type').val(),
            discount_value: $('#o100-dr-discount-value').val(),
            priority: $('#o100-dr-priority').val(),
            exclusive: $('#o100-dr-exclusive').is(':checked') ? 1 : 0,
            usage_limit: $('#o100-dr-usage-limit').val(),
            buy_qty: $('#o100-dr-buy-qty').val(),
            get_qty: $('#o100-dr-get-qty').val(),
            get_product: $('#o100-dr-get-product').val(),
            get_discount_type: $('#o100-dr-get-discount-type').val(),
            get_discount_value: $('#o100-dr-get-discount-value').val(),
            products: products,
            categories: categories,
            conditions: conditions,
            condition_relationship: conditionRelationship,
            start_date: $('#o100-dr-start-date').val(),
            end_date: $('#o100-dr-end-date').val(),
            promo_text: $('#o100-dr-promo-text').val(),
            show_badge: $('#o100-dr-show-badge').is(':checked') ? 1 : 0,
            badge_text: $('#o100-dr-badge-text').val(),
            show_banner: $('#o100-dr-show-banner').is(':checked') ? 1 : 0,
            banner_text: $('#o100-dr-banner-text').val(),
            popup_text: $('#o100-dr-popup-text').val(),
            popup_pages: $('#o100-dr-popup-pages').val(),
            popup_duration: $('#o100-dr-popup-duration').val(),
            set_qty: $('#o100-dr-set-qty').val(),
            bulk_tiers: bulkTiers
        };

        $.post(AJAX.ajax_url, data, function (resp) {
            if (resp.success) {
                $status.text(AJAX.i18n.saved);
                setTimeout(function () { location.reload(); }, 800);
            } else {
                $status.text(AJAX.i18n.error + ' ' + (resp.data || ''));
            }
        }).fail(function () {
            $status.text(AJAX.i18n.error);
        });
    }

    // ─── Import/Export ────────────────────────────────────────────
    function initIO() {
        $('#o100-dr-export').on('click', function () {
            $.post(AJAX.ajax_url, {
                action: 'o100_export_discount_rules',
                nonce: AJAX.nonce
            }, function (resp) {
                if (resp.success) {
                    var json = JSON.stringify(resp.data, null, 2);
                    var blob = new Blob([json], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'discount-rules-export.json';
                    a.click();
                    URL.revokeObjectURL(url);
                }
            });
        });

        $('#o100-dr-import').on('click', function () {
            var file = $('#o100-dr-import-file')[0].files[0];
            if (!file) { alert('Please select a file.'); return; }

            var reader = new FileReader();
            reader.onload = function (e) {
                $.post(AJAX.ajax_url, {
                    action: 'o100_import_discount_rules',
                    nonce: AJAX.nonce,
                    json_data: e.target.result
                }, function (resp) {
                    var $status = $('.o100-dr-import-status');
                    if (resp.success) {
                        $status.text(resp.data.message).css('color', '#00a32a');
                        setTimeout(function () { location.reload(); }, 1000);
                    } else {
                        $status.text(resp.data || 'Import failed').css('color', '#d63638');
                    }
                });
            };
            reader.readAsText(file);
        });
    }

    // ─── Settings Save ─────────────────────────────────────────
    function initSettings() {
        $('#o100-dr-save-settings').on('click', function () {
            $.post(AJAX.ajax_url, {
                action: 'o100_save_discount_settings',
                nonce: AJAX.nonce,
                enabled: $('#o100-dr-setting-enable').is(':checked') ? 1 : 0,
                badge_style: $('#o100-dr-setting-badge-style').val(),
                promo_color: $('#o100-dr-setting-promo-color').val(),
                promo_bg: $('#o100-dr-setting-promo-bg').val()
            }, function (resp) {
                if (resp.success) {
                    alert('Settings saved!');
                }
            });
        });
    }

    // ─── Condition Builder ─────────────────────────────────────
    var conditionCounter = 0;

    function initConditions() {
        $('#o100-dr-add-condition').on('click', function () {
            addConditionRow();
        });

        $(document).on('click', '.o100-dr-condition-remove', function () {
            $(this).closest('.o100-dr-condition-row').remove();
            toggleNoConditionsMsg();
        });

        $(document).on('change', '.o100-dr-condition-type', function () {
            var $row = $(this).closest('.o100-dr-condition-row');
            var type = $(this).val();
            renderConditionOptions($row, type, '', '', '');
        });
    }

    function addConditionRow(type, operator, value, value2) {
        type = type || '';
        operator = operator || '';
        value = (value !== undefined && value !== null) ? value : '';
        value2 = (value2 !== undefined && value2 !== null) ? value2 : '';

        var idx = conditionCounter++;
        var condDefs = AJAX.conditions || {};

        // Build type dropdown grouped by category
        var groups = {};
        for (var key in condDefs) {
            var g = condDefs[key].group || 'Other';
            if (!groups[g]) groups[g] = [];
            groups[g].push({ key: key, label: condDefs[key].label });
        }

        var typeHtml = '<select class="o100-dr-condition-type">';
        typeHtml += '<option value="">' + AJAX.i18n.select_type + '</option>';
        for (var gName in groups) {
            typeHtml += '<optgroup label="' + escapeHtml(gName) + '">';
            groups[gName].forEach(function (item) {
                typeHtml += '<option value="' + item.key + '"' + (type === item.key ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
            });
            typeHtml += '</optgroup>';
        }
        typeHtml += '</select>';

        var html = '<div class="o100-dr-condition-row" data-idx="' + idx + '">'
            + typeHtml
            + '<span class="o100-dr-condition-operator-wrap"></span>'
            + '<span class="o100-dr-condition-value-wrap"></span>'
            + '<button type="button" class="button button-small o100-dr-condition-remove" title="Remove">✕</button>'
            + '</div>';

        var $row = $(html);
        $('.o100-dr-no-conditions').hide();
        $('#o100-dr-conditions-list').append($row);

        if (type) {
            renderConditionOptions($row, type, operator, value, value2);
        }

        return $row;
    }

    function renderConditionOptions($row, type, operator, value, value2) {
        var condDefs = AJAX.conditions || {};
        var def = condDefs[type];
        if (!def) {
            $row.find('.o100-dr-condition-operator-wrap, .o100-dr-condition-value-wrap').empty();
            return;
        }

        // Operator dropdown
        var opHtml = '<select class="o100-dr-condition-operator">';
        for (var opKey in def.operators) {
            opHtml += '<option value="' + opKey + '"' + (operator === opKey ? ' selected' : '') + '>' + escapeHtml(def.operators[opKey]) + '</option>';
        }
        opHtml += '</select>';
        $row.find('.o100-dr-condition-operator-wrap').html(opHtml);

        // Value input based on value_type
        var valHtml = getValueInput(type, def, value, value2, operator || Object.keys(def.operators)[0]);
        $row.find('.o100-dr-condition-value-wrap').html(valHtml);
    }

    function getValueInput(type, def, value, value2, operator) {
        var vt = def.value_type;

        if (vt === 'none') {
            return ''; // No value needed (e.g., first_order, user_logged_in)
        }

        if (vt === 'number') {
            var html = '<input type="number" class="o100-dr-condition-value" value="' + escapeHtml(String(value)) + '" step="0.01" min="0" placeholder="Value">';
            if (operator === 'between') {
                html += ' <span>–</span> <input type="number" class="o100-dr-condition-value2" value="' + escapeHtml(String(value2)) + '" step="0.01" min="0" placeholder="Max">';
            }
            return html;
        }

        if (vt === 'time_range') {
            return '<input type="time" class="o100-dr-condition-value" value="' + escapeHtml(String(value)) + '"> <span>–</span> <input type="time" class="o100-dr-condition-value2" value="' + escapeHtml(String(value2)) + '">';
        }

        if (vt === 'text') {
            return '<input type="text" class="o100-dr-condition-value regular-text" value="' + escapeHtml(String(value)) + '" placeholder="Value">';
        }

        if (vt === 'text_list') {
            var listVal = Array.isArray(value) ? value.join(', ') : (value || '');
            return '<input type="text" class="o100-dr-condition-value regular-text" value="' + escapeHtml(listVal) + '" placeholder="Comma separated values">';
        }

        // Select-based value types
        if (vt === 'order_type' || vt === 'days' || vt === 'user_role' || vt === 'payment_method') {
            var options = {};
            if (vt === 'user_role') {
                options = AJAX.user_roles || {};
            } else if (vt === 'payment_method') {
                options = AJAX.payment_methods || {};
            } else {
                options = def.value_options || {};
            }

            var selectedValues = Array.isArray(value) ? value : (value ? [value] : []);
            var cbHtml = '<div class="o100-dr-condition-checkboxes">';
            for (var optKey in options) {
                var checked = selectedValues.indexOf(optKey) !== -1 ? ' checked' : '';
                cbHtml += '<label class="o100-dr-inline-check"><input type="checkbox" class="o100-dr-condition-cb" value="' + optKey + '"' + checked + '> ' + escapeHtml(options[optKey]) + '</label>';
            }
            cbHtml += '</div>';
            return cbHtml;
        }

        // Product/category search (simplified - text input for IDs)
        if (vt === 'product_search' || vt === 'category_search') {
            var listVal2 = Array.isArray(value) ? value.join(', ') : (value || '');
            return '<input type="text" class="o100-dr-condition-value regular-text" value="' + escapeHtml(listVal2) + '" placeholder="Enter IDs (comma separated)">';
        }

        return '<input type="text" class="o100-dr-condition-value regular-text" value="' + escapeHtml(String(value || '')) + '" placeholder="Value">';
    }

    function collectConditions() {
        var conditions = [];
        $('.o100-dr-condition-row').each(function () {
            var $row = $(this);
            var type = $row.find('.o100-dr-condition-type').val();
            if (!type) return;

            var operator = $row.find('.o100-dr-condition-operator').val() || '';
            var condDef = (AJAX.conditions || {})[type];
            var vt = condDef ? condDef.value_type : 'text';
            var value = '';
            var value2 = '';

            if (vt === 'none') {
                value = '';
            } else if (vt === 'order_type' || vt === 'days' || vt === 'user_role' || vt === 'payment_method') {
                // Collect checked checkboxes
                var checkedVals = [];
                $row.find('.o100-dr-condition-cb:checked').each(function () {
                    checkedVals.push($(this).val());
                });
                value = checkedVals;
            } else if (vt === 'text_list') {
                var rawVal = $row.find('.o100-dr-condition-value').val() || '';
                value = rawVal.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
            } else if (vt === 'product_search' || vt === 'category_search') {
                var rawVal2 = $row.find('.o100-dr-condition-value').val() || '';
                value = rawVal2.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
            } else {
                value = $row.find('.o100-dr-condition-value').val() || '';
                value2 = $row.find('.o100-dr-condition-value2').val() || '';
            }

            var condition = { 
                type: type, 
                options: {
                    operator: operator, 
                    value: value 
                }
            };
            if (value2) condition.options.value2 = value2;
            conditions.push(condition);
        });
        return conditions;
    }

    function populateConditions(conditions, relationship) {
        $('#o100-dr-conditions-list').find('.o100-dr-condition-row').remove();
        $('input[name="o100_dr_condition_relationship"][value="' + relationship + '"]').prop('checked', true);

        if (conditions && conditions.length) {
            conditions.forEach(function (cond) {
                // Determine operator/value based on nested or flat structure
                var operator = '', value = '', value2 = '';
                
                if (cond.options) {
                    operator = cond.options.operator || '';
                    value = cond.options.value || '';
                    value2 = cond.options.value2 || '';
                } else {
                    operator = cond.operator || '';
                    value = cond.value || '';
                    value2 = cond.value2 || '';
                }
                
                addConditionRow(cond.type, operator, value, value2);
            });
        }
        toggleNoConditionsMsg();
    }

    function toggleNoConditionsMsg() {
        var hasRows = $('#o100-dr-conditions-list .o100-dr-condition-row').length > 0;
        $('.o100-dr-no-conditions').toggle(!hasRows);
    }

    // ─── Init ─────────────────────────────────────────────────────
    $(document).ready(function () {
        if ($('.o100-discount-wrap').length === 0) return;
        initSubTabs();
        initRuleForm();
        initFilters();
        initConditions();
        initIO();
        initSettings();
    });

})(jQuery);





