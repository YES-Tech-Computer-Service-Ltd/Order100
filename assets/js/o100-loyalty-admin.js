jQuery(document).ready(function ($) {
	'use strict';

	// Global AJAX Error Handler for WFA Loyalty
	$(document).ajaxError(function (event, jqXHR, ajaxSettings, thrownError) {
		// Check if this was an AJAX request specifically intended for our loyalty plugin
		var isOurAjax = false;
		if (ajaxSettings.data && typeof ajaxSettings.data === 'string' && (ajaxSettings.data.indexOf('action=o100_') !== -1 || ajaxSettings.data.indexOf('action=wll_') !== -1 || ajaxSettings.data.indexOf('action=o100_') !== -1)) {
			isOurAjax = true;
		} else if (jqXHR.responseText && jqXHR.responseText.indexOf('o100_') !== -1) {
			isOurAjax = true;
		}

		if (isOurAjax) {
			console.error('Order100 AJAX Error:', thrownError, jqXHR.responseText);
			// Do NOT show an alert() here. If a user's computer goes to sleep and wakes up, 
			// pending AJAX requests (like WordPress Heartbeat) will fail with 504/502 and trigger a global alert.
		}
	});

	// Helper to manually clear "form" divs since they don't have .reset()
	function o100ClearForm($container) {
		$container.find(':input').not(':button, :submit, :reset, [name="action"], [name="o100_loyalty_nonce"], [name="id"], [name="campaign_type"], [name="action_type"], [name="discount_type"]').each(function () {
			var $el = $(this);
			if ($el.is(':checkbox, :radio')) {
				$el.prop('checked', false);
			} else {
				$el.val('');
			}
		});
		// Set defaults after clearing
		$container.find('input[name="campaign_type"]').val('point');
		$container.find('input[name="reward_type"][value="redeem_point"]').prop('checked', true);
		$container.find('select[name="is_show_way_to_earn"]').val('1');
		$container.find('select[name="is_show_reward"]').val('1');
		$container.find('input[name="active"]').prop('checked', true);
		$container.find('.o100-image-url').val('');
		$container.find('.o100-image-preview').empty();
		$container.find('.o100-conditions-wrapper').empty();
		if (typeof $.fn.select2 !== 'undefined') {
			$container.find('.o100-select2, .o100-select-product').val(null).trigger('change');
		}
	}

	// 0. Configuration & Constants - v3.3.0: Added 18+ missing condition types
	const O100_CONDITION_TYPES = {
		// Basic Conditions
		'user_role': { label: 'User Role', type: 'select', options: 'roles', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'user_point': { label: 'Customer Points', type: 'number', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'user_level': { label: 'User Level', type: 'ajax', ajaxAction: 'o100_condition_data', method: 'userLevel', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'user_total_earn_point': { label: 'Total Earned Points', type: 'number', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'customer': { label: 'Member (Customer)', type: 'ajax', ajaxAction: 'o100_get_customer_list', method: 'customer', isMultiple: true, operators: ['in_list', 'not_in_list'] },

		// Cart Conditions
		'cart_subtotal': { label: 'Cart Subtotal', type: 'number', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'cart_line_items_count': { label: 'Line Item Count', type: 'number', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'cart_weights': { label: 'Cart Weight', type: 'number', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },

		// Product Conditions
		'products': { label: 'Products', type: 'ajax', ajaxAction: 'o100_free_product_options', method: 'products', isMultiple: true, hasQty: true, hasCondition: true, operators: ['in_list', 'not_in_list'] },
		'product_category': { label: 'Product Category', type: 'ajax', ajaxAction: 'o100_condition_data', method: 'productCategory', isMultiple: true, hasQty: true, hasCondition: true, operators: ['in_list', 'not_in_list'] },
		'product_tags': { label: 'Product Tags', type: 'ajax', ajaxAction: 'o100_condition_data', method: 'productTags', isMultiple: true, hasQty: true, hasCondition: true, operators: ['in_list', 'not_in_list'] },
		'product_attributes': { label: 'Product Attributes', type: 'ajax', ajaxAction: 'o100_condition_data', method: 'productAttributes', isMultiple: true, hasQty: true, hasCondition: true, operators: ['in_list', 'not_in_list'] },
		'product_sku': { label: 'Product SKU', type: 'ajax', ajaxAction: 'o100_condition_data', method: 'productSku', isMultiple: true, hasQty: true, hasCondition: true, operators: ['in_list', 'not_in_list'] },
		'product_on_sale': { label: 'Product On Sale', type: 'select', options: 'yes_no', operators: ['in_list', 'not_in_list'] },

		// Order Conditions
		'payment_method': { label: 'Payment Method', type: 'select', options: 'gateways', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'order_method': { label: 'Order Method (Delivery/Dine-in/Pickup)', type: 'select', options: 'order_methods', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'order_status': { label: 'Order Status', type: 'select', options: 'order_statuses', isMultiple: true, operators: ['in_list', 'not_in_list'] },

		// Purchase History Conditions
		'purchase_history': { label: 'Purchase History (Order Count)', type: 'number', hasOrderStatus: 'order_status', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'purchase_history_qty': { label: 'Purchase History (Total Quantity)', type: 'number', hasOrderStatus: 'order_status', hasTime: 'purchase_before', hasProductActionType: true, hasCondition: true, hasQty: true, operators: ['in_list', 'not_in_list'] },
		'purchase_spent': { label: 'Total Amount Spent', type: 'number', hasOrderStatus: 'status', hasTime: 'time', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'life_time_sale_value': { label: 'Lifetime Sale Value', type: 'number', hasOrderStatus: 'order_status', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'purchase_first_order': { label: 'First Order', type: 'select', options: 'yes_no', operators: ['in_list', 'not_in_list'] },
		'purchase_last_order': { label: 'Last Order (Days Ago)', type: 'number', hasOrderStatus: 'status', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'purchase_last_order_amount': { label: 'Last Order Amount', type: 'number', hasOrderStatus: 'status', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'purchase_previous_orders': { label: 'Previous Orders Count', type: 'number', hasOrderStatus: 'status', hasTime: 'time', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'purchase_previous_orders_with_amount': { label: 'Previous Orders with Amount', type: 'number', hasOrderStatus: 'status', hasTime: 'time', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] },
		'purchase_previous_orders_for_specific_product': { label: 'Orders for Specific Product', type: 'ajax', ajaxAction: 'o100_free_product_options', method: 'products', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'purchase_quantities_for_specific_product': { label: 'Quantity Purchased for Product', type: 'ajax', ajaxAction: 'o100_free_product_options', method: 'products', isMultiple: true, operators: ['in_list', 'not_in_list'] },

		// Other Conditions
		'currency': { label: 'Currency', type: 'select', options: 'currencies', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'language': { label: 'Language', type: 'select', options: 'languages', isMultiple: true, operators: ['in_list', 'not_in_list'] },
		'usage_limits': { label: 'Usage Limits', type: 'number', operators: ['equal_to', 'not_equal_to', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'] }
	};

	const O100_OPERATORS = {
		'equal_to': 'Is equal to',
		'not_equal_to': 'Is not equal to',
		'greater_than': 'Is greater than',
		'less_than': 'Is less than',
		'greater_than_or_equal': 'Is greater than or equal to',
		'less_than_or_equal': 'Is less than or equal to',
		'in_list': 'Is in list',
		'not_in_list': 'Is not in list'
	};

	function o100AddConditionRow($wrapper, data) {
		data = data || {};
		// If options is missing, it might be flat data from an older save or different structure
		var options = data.options || data;
		var index = $wrapper.find('.o100-condition-block').length;
		var type = data.type || 'products';
		var config = O100_CONDITION_TYPES[type] || {};

		var html = '<div class="o100-condition-block" data-index="' + index + '">';
		html += '<span class="o100-condition-remove dashicons dashicons-no-alt"></span>';

		// Type Select
		html += '<div class="o100-form-group"><label>Type</label><select name="conditions[' + index + '][type]" class="o100-condition-type-select">';
		$.each(O100_CONDITION_TYPES, function (k, v) {
			html += '<option value="' + k + '"' + (type === k ? ' selected' : '') + '>' + v.label + '</option>';
		});
		html += '</select></div>';

		// Operator & Value Container
		html += '<div class="o100-condition-fields-container">';
		html += o100GetConditionFieldsHtml(index, type, options);
		html += '</div>';

		html += '</div>';

		var $row = $(html);
		$wrapper.append($row);

		// Initialize Select2 if needed
		WfaInitConditionInputs($row);
	}

	function o100GetConditionFieldsHtml(index, type, options) {
		var config = O100_CONDITION_TYPES[type] || {};
		var html = '<div style="display:flex; gap:10px; margin-top:10px;">';

		// Operator
		var ops = config.operators || ['in_list', 'not_in_list'];
		var isOperatorUseless = (config.options === 'yes_no' || type === 'usage_limits');

		if (isOperatorUseless) {
			html += '<input type="hidden" name="conditions[' + index + '][options][operator]" value="equal_to" />';
		} else {
			html += '<div style="flex:1;"><label>Operator</label><select name="conditions[' + index + '][options][operator]">';
			$.each(ops, function (i, op) {
				html += '<option value="' + op + '"' + (options.operator === op ? ' selected' : '') + '>' + (O100_OPERATORS[op] || op) + '</option>';
			});
			html += '</select></div>';
		}

		// Value
		html += '<div style="flex:2;"><label>Value</label>';
		var multipleAttr = config.isMultiple ? ' multiple="multiple"' : '';
		var nameSuffix = config.isMultiple ? '[]' : '';
		var nameAttr = 'name="conditions[' + index + '][options][value]' + nameSuffix + '"';

		if (config.type === 'ajax') {
			html += '<select ' + nameAttr + ' class="o100-condition-value-ajax" ' + multipleAttr + ' style="width:100%;"></select>';
		} else if (config.type === 'select') {
			html += '<select ' + nameAttr + ' class="o100-select2" ' + multipleAttr + ' style="width:100%;">';
			var opts = o100_loyalty[config.options] || [];
			$.each(opts, function (i, opt) {
				var isSelected = false;
				if (Array.isArray(options.value)) {
					isSelected = options.value.indexOf(opt.id) !== -1;
				} else {
					isSelected = options.value == opt.id;
				}
				html += '<option value="' + opt.id + '"' + (isSelected ? ' selected' : '') + '>' + opt.text + '</option>';
			});
			html += '</select>';
		} else {
			// Fix for value 0: (options.value || '') would treat 0 as empty.
			var displayValue = (options.value !== undefined && options.value !== null) ? options.value : '';
			html += '<input type="' + config.type + '" name="conditions[' + index + '][options][value]" value="' + displayValue + '" style="width:100%;" />';
		}
		html += '</div></div>';

		// Conditional Sub-fields (OrderStatus, Time, Qty, Condition)
		if (config.hasOrderStatus || config.hasTime || config.hasCondition || config.hasQty) {
			html += '<div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:10px;">';

			if (config.hasOrderStatus) {
				var fieldName = config.hasOrderStatus; // e.g. 'order_status' or 'status'
				var currentStatuses = options[fieldName] || [];
				// Default to completed/processing if new and empty
				if (currentStatuses.length === 0 && !options.operator) {
					currentStatuses = ['completed', 'processing'];
				}

				html += '<div style="flex:1; min-width:200px;"><label>Order Statuses</label>';
				html += '<select name="conditions[' + index + '][options][' + fieldName + '][]" class="o100-select2" multiple="multiple" style="width:100%;">';
				var statuses = o100_loyalty.order_statuses || [];
				$.each(statuses, function (i, st) {
					var isSelected = currentStatuses.indexOf(st.id) !== -1;
					html += '<option value="' + st.id + '"' + (isSelected ? ' selected' : '') + '>' + st.text + '</option>';
				});
				html += '</select></div>';
			}

			if (config.hasProductActionType) {
				var currentActionType = options.product_action_type || 'products';
				html += '<div style="flex:1; min-width:150px;"><label>Filter Products By</label>';
				html += '<select name="conditions[' + index + '][options][product_action_type]" class="o100-select2" style="width:100%;">';
				var actionTypes = [
					{ id: 'products', text: 'Specific Products' },
					{ id: 'productCategory', text: 'Product Categories' },
					{ id: 'productSku', text: 'Product SKUs' },
					{ id: 'productTags', text: 'Product Tags' }
				];
				$.each(actionTypes, function (i, at) {
					html += '<option value="' + at.id + '"' + (currentActionType === at.id ? ' selected' : '') + '>' + at.text + '</option>';
				});
				html += '</select></div>';
			}

			if (config.hasTime) {
				var currentTime = options.time || 'all_time';
				html += '<div style="flex:1; min-width:150px;"><label>Time Range</label>';
				html += '<select name="conditions[' + index + '][options][time]" class="o100-select2" style="width:100%;">';
				var times = o100_loyalty.time_ranges || [
					{ id: 'all_time', text: 'All Time' },
					{ id: 'this_month', text: 'This Month' },
					{ id: 'last_month', text: 'Last Month' },
					{ id: 'ninety_days', text: 'Last 90 Days' },
					{ id: 'last_year', text: 'Last Year' },
					{ id: 'custom', text: 'Custom' }
				];
				$.each(times, function (i, t) {
					html += '<option value="' + t.id + '"' + (currentTime === t.id ? ' selected' : '') + '>' + t.text + '</option>';
				});
				html += '</select></div>';
			}

			if (config.hasCondition) {
				html += '<div style="flex:1; min-width:100px;"><label>Condition</label><select name="conditions[' + index + '][options][condition]" style="width:100%;">';
				html += '<option value="allow"' + (options.condition === 'allow' ? ' selected' : '') + '>Allow</option>';
				html += '<option value="disallow"' + (options.condition === 'disallow' ? ' selected' : '') + '>Disallow</option>';
				html += '</select></div>';
			}
			if (config.hasQty) {
				html += '<div style="flex:1; min-width:80px;"><label>Qty</label><input type="number" name="conditions[' + index + '][options][qty]" value="' + (options.qty || 1) + '" min="1" style="width:100%;" /></div>';
			}
			html += '</div>';
		}

		return html;
	}

	function WfaInitConditionInputs($row) {
		var index = $row.data('index');
		var type = $row.find('.o100-condition-type-select').val();
		var config = O100_CONDITION_TYPES[type] || {};

		if (typeof $.fn.select2 !== 'undefined') {
			$row.find('.o100-select2').select2({ width: '100%' });
		}

		if (config.type === 'ajax') {
			var $valSelect = $row.find('.o100-condition-value-ajax');
			$valSelect.select2({
				width: '100%',
				ajax: {
					url: o100_loyalty.ajax_url,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						var $nonceField = $row.closest('.o100-loyalty-wrap').find('input[name="o100_loyalty_nonce"]');
						if (!$nonceField.length) $nonceField = $('input[name="o100_loyalty_nonce"]');
						return {
							q: params.term,
							action: config.ajaxAction || 'o100_condition_data',
							method: config.method,
							o100_loyalty_nonce: $nonceField.val()
						};
					},
					processResults: function (data) {
						var results = data.data || [];
						// Map value/label to id/text if needed for Select2 compatibility
						results = results.map(function (item) {
							return {
								id: item.id || item.value || item,
								text: item.text || item.label || item
							};
						});
						return { results: results };
					},
					cache: true
				},
				minimumInputLength: 3,
				placeholder: 'Search...'
			});
		}
	}

	// Condition Builder Event Handlers
	$(document).on('click', '.o100-add-condition-btn', function () {
		var $wrapper = $(this).closest('.o100-conditions-panel').find('.o100-conditions-wrapper');
		o100AddConditionRow($wrapper);
	});

	$(document).on('click', '.o100-condition-remove', function () {
		$(this).closest('.o100-condition-block').remove();
	});

	$(document).on('change', '.o100-condition-type-select', function () {
		var $row = $(this).closest('.o100-condition-block');
		var index = $row.data('index');
		var type = $(this).val();

		$row.find('.o100-condition-fields-container').html(o100GetConditionFieldsHtml(index, type, {}));
		WfaInitConditionInputs($row);
	});



	// 1. Sidebar Subtab Switching
	$('.o100-loyalty-subtabs a').on('click', function (e) {
		e.preventDefault();
		var $this = $(this);
		var tabId = $this.data('subtab');

		// Active state on sidebar nav
		$('.o100-loyalty-subtabs a').removeClass('active');
		$this.addClass('active');

		// Show corresponding content panel
		$('.o100-loyalty-subtab-content').hide();
		$('.o100-loyalty-subtab-content[data-subtab="' + tabId + '"]').fadeIn(200);

		// Store last active subtab in localStorage
		try {
			localStorage.setItem('o100_loyalty_active_subtab', tabId);
		} catch (err) { }

		// Special handling for React-based tabs (Restore original WPLoyalty functionality)
		if (tabId === 'customers') {
			window.location.hash = '#/point_users';
			// Delay resize slightly to let subtab render
			setTimeout(function () {
				window.dispatchEvent(new Event('resize'));
			}, 100);
		}
	});

	// Restore tab state on load
	try {
		var lastSubtab = localStorage.getItem('o100_loyalty_active_subtab');
		if (lastSubtab && $('.o100-loyalty-subtabs a[data-subtab="' + lastSubtab + '"]').length) {
			$('.o100-loyalty-subtabs a[data-subtab="' + lastSubtab + '"]').click();
		} else {
			$('.o100-loyalty-subtabs a').first().addClass('active');
		}
	} catch (err) {
		$('.o100-loyalty-subtabs a').first().addClass('active');
	}

	// Addon Tabs inside 'Advanced' (Horizontal)
	$(document).on('click', '.o100-loyalty-addon-tab-link', function (e) {
		e.preventDefault();
		var $this = $(this);
		var tabId = $this.data('addon-tab');

		// 1. Update active state of tab buttons
		$this.siblings().removeClass('active');
		$this.addClass('active');

		// 2. Toggle content containers
		var $container = $this.closest('.o100-loyalty-advanced-container');
		$container.find('.o100-loyalty-addon-content').hide();
		$container.find('#o100-loyalty-addon-' + tabId).fadeIn(200);

		// 3. Update Sticky Header Title
		const titles = {
			'referral': 'Guest Referral Settings',
			'optin': 'Lead Generation (Opt-in)'
		};
		$('.o100-sticky-title').text(titles[tabId] || 'Loyalty Settings');

		// 4. Update Save Button Visibility
		const $saveBtn = $('.o100-btn-save-sticky');
		if (tabId === 'optin') {
			$saveBtn.toggle($('.o100-loyalty-nested-nav [data-optin-tab="settings"]').hasClass('active'));
		} else if (tabId === 'referral') {
			$saveBtn.show();
		} else {
			$saveBtn.show();
		}
	});

	// 2b. Nested Tabs inside 'Referral' or 'Opt-in' (Sub-navigation)
	$(document).on('click', '.o100-loyalty-nested-nav a', function (e) {
		e.preventDefault();
		var $this = $(this);
		var referralTabId = $this.data('referral-tab');
		var optinTabId = $this.data('optin-tab');

		$this.siblings().removeClass('active');
		$this.addClass('active');

		if (referralTabId) {
			var $container = $this.closest('#o100-loyalty-addon-referral');
			$container.find('.o100-referral-view-container').hide();
			$container.find('#o100-referral-' + referralTabId).fadeIn(200);
		} else if (optinTabId) {
			var $container = $this.closest('#o100-loyalty-addon-optin');
			$container.find('.o100-optin-view-container').hide();
			$container.find('#o100-optin-' + optinTabId).fadeIn(200);
		}
	});

	// 2c. Robustly hide/remove "Back to WPLoyalty" buttons (especially for React addons)
	function hideBackButtons() {
		// 1. Hide/Remove by specific known classes (High Performance)
		var selectors = [
			'.wlcr-back-to-apps',
			'.wlopt-back-to-apps',
			'.wll-back-to-apps',
			'.back-to-apps',
			'[class*="back-to-apps"]',
			'.wlr-back-button-container',
			'.wlr-back-link',
			'.wlr-back-button',
			'.wlcr-heading-data', // Hide original referral header
			'.wlopt-settings-header', // Hide original optin header
			'.wlopt-button-block' // Hide original save block
		];

		$(selectors.join(', ')).hide().css('display', 'none');

		// 2. Hide by text content (Handle License Keys and special buttons)
		var $container = $('.o100-loyalty-advanced-container');
		if (!$container.length) return;

		$container.find('p, h3, h2, label, a, button').each(function () {
			var $this = $(this);
			var txt = $this.text().trim().toLowerCase();

			// Hide License Key blocks
			if (txt.indexOf('license key') !== -1 || txt.indexOf('shortcode') !== -1) {
				$this.closest('.wlcr-field-block, .wlopt-field-block').hide();
			}

			// Hide "Back to WPLoyalty" remnants
			if (txt.indexOf('back to wployalty') !== -1) {
				$this.closest('a, button, div.wlcr-button-action').hide();
			}
		});
	}

	// Run on load and whenever a tab is clicked
	hideBackButtons();
	$(document).on('click', '.o100-loyalty-addon-tabs a, .o100-loyalty-nested-nav a', function () {
		setTimeout(hideBackButtons, 100);
		setTimeout(hideBackButtons, 500);
		setTimeout(hideBackButtons, 1500);
	});

	// Use an observer for dynamic content changes - Debounced for performance
	if ($('.o100-loyalty-advanced-container').length) {
		var hideTimeout;
		var observer = new MutationObserver(function () {
			clearTimeout(hideTimeout);
			hideTimeout = setTimeout(hideBackButtons, 300);
		});
		observer.observe($('.o100-loyalty-advanced-container')[0], { childList: true, subtree: true });
	}

	// 3. Settings Form Submit via AJAX
	$('#o100-loyalty-save-settings').on('click', function (e) {
		e.preventDefault();
		var $form = $('#o100-loyalty-settings-form');
		var $btn = $(this);
		var $status = $form.find('.o100-loyalty-save-status');

		$btn.prop('disabled', true).text(o100_loyalty.i18n.saving);
		$status.text('').removeClass('success error');

		// Get all form data
		var formData = $form.find(':input').serializeArray();
		var settingsData = {};

		// The WPLoyalty save endpoint expects the data mapping a specific way
		$.each(formData, function () {
			if (this.name.indexOf('[]') !== -1) {
				var key = this.name.replace('[]', '');
				if (!settingsData[key]) {
					settingsData[key] = [];
				}
				settingsData[key].push(this.value);
			} else if (this.name !== 'o100_loyalty_nonce') {
				settingsData[this.name] = this.value;
			}
		});

		var requestData = {
			action: 'o100_save_loyalty_settings_all',
			o100_loyalty_nonce: $form.find('input[name="o100_loyalty_nonce"]').val() || o100_loyalty.nonce
		};

		// Merge flattened settings into requestData
		$.extend(requestData, settingsData);

		$.ajax({
			url: o100_loyalty.ajax_url,
			type: 'POST',
			data: requestData,
			success: function (response) {
				$btn.prop('disabled', false).text('Save Settings');
				if (response.success) {
					$status.text(o100_loyalty.i18n.saved).addClass('success').show().fadeOut(3000);
				} else {
					var errorMsg = response.data && response.data.message ? response.data.message : (typeof response.data === 'string' ? response.data : o100_loyalty.i18n.error);

					// If we have field-specific errors, append them for better UX
					if (response.data && response.data.field_error) {
						var fieldErrors = [];
						$.each(response.data.field_error, function (field, messages) {
							fieldErrors.push(field + ': ' + messages.join(', '));
						});
						if (fieldErrors.length > 0) {
							errorMsg += ' (' + fieldErrors.join('; ') + ')';
						}
					}

					$status.text(errorMsg).addClass('error');
				}
			},
			error: function (xhr) {
				$btn.prop('disabled', false).text('Save Settings');
				var errorMsg = o100_loyalty.i18n.error;
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMsg = xhr.responseJSON.data.message;
				}
				$status.text(errorMsg).addClass('error');
			}
		});
	});

	// 4. Dashboard Logic
	if ($('.o100-loyalty-dashboard-full').length) {
		var revenueChart, pointsChart, couponsChart;

		function o100LoyaltyInitCharts() {
			var commonOptions = {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: {
					y: { beginAtZero: true, border: { display: false }, grid: { borderDash: [2, 4] } },
					x: { grid: { display: false } }
				}
			};

			var ctxRev = document.getElementById('o100-loyalty-revenue-chart').getContext('2d');
			revenueChart = new Chart(ctxRev, { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: '#4f46e5', backgroundColor: 'rgba(79, 70, 229, 0.1)', fill: true, tension: 0.1 }] }, options: commonOptions });

			var ctxPts = document.getElementById('o100-loyalty-points-chart').getContext('2d');
			pointsChart = new Chart(ctxPts, { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: '#10b981', backgroundColor: 'transparent', tension: 0.1 }] }, options: commonOptions });

			var ctxCpn = document.getElementById('o100-loyalty-coupons-chart').getContext('2d');
			couponsChart = new Chart(ctxCpn, { type: 'line', data: { labels: [], datasets: [{ data: [], borderColor: '#f59e0b', backgroundColor: 'transparent', tension: 0.1 }] }, options: commonOptions });
		}

		function o100LoyaltyUpdateDashboard() {
			var filterType = $('#o100-loyalty-date-filter').val();
			var currency = $('#o100-loyalty-currency-filter').val();
			var fromDate = '', toDate = '';

			if (filterType === 'custom') {
				fromDate = $('#o100-loyalty-date-from').val();
				toDate = $('#o100-loyalty-date-to').val();
				if (!fromDate || !toDate) return;
			}

			var baseData = {
				o100_loyalty_nonce: o100_loyalty.dashboard_nonce,
				currency: currency,
				fil_type: filterType,
				from_date: fromDate,
				to_date: toDate
			};

			// Load Stats
			$.post(o100_loyalty.ajax_url, $.extend({ action: 'o100_loyalty_dashboard_analytic_data' }, baseData), function (res) {
				if (res.success && res.data) {
					$('#stat-orders-val').text(res.data.total_order_count || 0);
					$('#stat-orders-value-val').html(res.data.total_order_value || '$0.00');
					$('#stat-points-val').text(res.data.total_points || 0);
					$('#stat-coupons-val').text(res.data.total_coupons || 0);
					$('#stat-redeemed-val').html(res.data.total_redeem_reward || '$0.00');
				}
			}).fail(function (xhr) { console.error("WFA AJAX Error: Dashboard Analytics", xhr.status, xhr.statusText); });

			// Load Charts
			$.post(o100_loyalty.ajax_url, $.extend({ action: 'o100_loyalty_chart_data' }, baseData), function (res) {
				if (res.success && res.data) {
					var revData = res.data.revenue || [];
					if (revData.length > 0) revData.shift();
					revenueChart.data.labels = revData.map(function (i) { return i[0]; });
					revenueChart.data.datasets[0].data = revData.map(function (i) { return i[1]; });
					revenueChart.update();

					var ptsData = res.data.point || [];
					if (ptsData.length > 0) ptsData.shift();
					pointsChart.data.labels = ptsData.map(function (i) { return i[0]; });
					pointsChart.data.datasets[0].data = ptsData.map(function (i) { return i[1]; });
					pointsChart.update();

					var rwdData = res.data.reward || [];
					if (rwdData.length > 0) rwdData.shift();
					couponsChart.data.labels = rwdData.map(function (i) { return i[0]; });
					couponsChart.data.datasets[0].data = rwdData.map(function (i) { return i[1]; });
					couponsChart.update();
				}
			}).fail(function (xhr) { console.error("WFA AJAX Error: Chart Data", xhr.status, xhr.statusText); });

			// Load Activities
			$.post(o100_loyalty.ajax_url, $.extend({ action: 'o100_loyalty_all_customer_activities', limit: 10, offset: 0 }, baseData), function (res) {
				var $tl = $('#o100-loyalty-activities-timeline');
				if (res.success && res.data && res.data.items && res.data.items.length > 0) {
					var html = '';
					res.data.items.forEach(function (item, index) {
						var title = (item.action_type || '').replace(/_/g, ' ');
						html += '<div style="position:relative; margin-bottom:20px;">';
						html += '<div style="position:absolute; left:-30px; top:5px; width:12px; height:12px; border-radius:50%; border:2px solid #4f46e5; background:#fff; z-index:2;">';
						if (index !== res.data.items.length - 1) {
							html += '<div style="position:absolute; left:5px; top:15px; width:2px; height:calc(100% + 5px); background:#e5e7eb; z-index:1;"></div>';
						}
						html += '</div>'; // Close the inner div for the circle
						html += '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-left:15px;">'; // Adjusted margin-left
						html += '<div><strong style="color:#111827; font-size:14px;">' + (item.user_email || 'Guest') + '</strong><br><span style="color:#6b7280; font-size:13px;">' + item.points + ' points (' + title + ')</span></div>';
						html += '<div style="color:#9ca3af; font-size:12px;">' + (item.created_at || '') + '</div>';
						html += '</div></div>';
					});
					$tl.html(html);
				} else {
					$tl.html('<p style="color:#6b7280; font-size:13px;">No recent activities found.</p>');
				}
			}).fail(function (xhr) { console.error("WFA AJAX Error: Activities", xhr.status, xhr.statusText); });
		}

		o100LoyaltyInitCharts();
		setTimeout(o100LoyaltyUpdateDashboard, 100);

		$('#o100-loyalty-date-filter, #o100-loyalty-currency-filter').on('change', function () {
			if ($('#o100-loyalty-date-filter').val() === 'custom') {
				$('#o100-loyalty-custom-date-popup').show();
			} else {
				$('#o100-loyalty-custom-date-popup').hide();
				o100LoyaltyUpdateDashboard();
			}
		});

		$('#o100-loyalty-custom-date-btn').on('click', function () {
			$('#o100-loyalty-custom-date-popup').hide();
			o100LoyaltyUpdateDashboard();
		});
	}

	// ==============================================
	// 5. Campaigns Module Logic
	// ==============================================
	if ($('.o100-campaigns-container').length) {

		// Show Type Grid
		$(document).on('click', '.o100-btn-create-campaign', function (e) {
			e.preventDefault();
			$('#o100-campaigns-list-view').hide();
			$('#o100-campaigns-editor-view').hide();
			$('#o100-campaigns-type-view').fadeIn(200);
		});

		// Back to List
		$(document).on('click', '.o100-btn-back-to-list', function (e) {
			e.preventDefault();
			$('#o100-campaigns-type-view').hide();
			$('#o100-campaigns-editor-view').hide();
			$('#o100-campaigns-list-view').fadeIn(200);
		});

		// Select Campaign Type -> Show Editor
		$(document).on('click', '.o100-btn-select-type', function (e) {
			e.preventDefault();
			var typeId = $(this).data('type');
			var typeTitle = $(this).siblings('h3').text();

			// Reset Form thoroughly (Manual since it's a div)
			var $formContainer = $('#o100-campaign-form');
			if ($formContainer.length) {
				o100ClearForm($formContainer);
				$formContainer.find('input[name="active"]').prop('checked', true);
			}

			$('#o100-campaign-id').val(0);
			$('#o100-campaign-action-type').val(typeId);
			$('#o100-editor-title-type').text(typeTitle);

			// Inject dynamic rules block
			var $rulesContainer = $('#o100-campaign-dynamic-rules');
			$rulesContainer.empty();

			var templateId = '#tmpl-o100-rules-' + typeId;
			if ($(templateId).length) {
				$rulesContainer.html($(templateId).html());
			} else {
				// Fallback to standard point for purchase structure if no specific template exists 
				$rulesContainer.html($('#tmpl-o100-rules-point_for_purchase').html());
			}

			// Clear Conditions
			$('#o100-campaign-editor-view .o100-conditions-wrapper').empty();

			// Re-initialize Select2 for dynamic elements
			if (typeof $.fn.select2 !== 'undefined') {
				$rulesContainer.find('.o100-select2').select2({ width: '100%' });
			}

			$('#o100-campaigns-type-view').hide();
			$('#o100-campaigns-editor-view').fadeIn(200);

			// Force visibility default after showing
			$('#o100-campaigns-editor-view select[name="is_show_way_to_earn"]').val('1');
		});

		// Checkbox slider visual toggle text (Include end date) - USE DELEGATION for dynamic editor
		$(document).on('change', '.o100-include-end-date', function () {
			if ($(this).is(':checked')) {
				$('.o100-end-date-group').slideDown();
			} else {
				$('.o100-end-date-group').slideUp();
				$('input[name="end_at"]').val('');
			}
		});

		// Reward Type select (Points vs Discount) to show/hide the point select type
		$(document).on('change', '.o100-campaign-reward-type-select', function () {
			var $block = $(this).closest('.o100-dynamic-rule-block');
			var $pointGroup = $block.find('.o100-point-reward-type-group');
			var $couponGroup = $block.find('.o100-coupon-reward-type-group');

			if ($(this).val() === 'point') {
				$pointGroup.show();
				$couponGroup.hide();
			} else {
				$pointGroup.hide();
				$couponGroup.show();
			}
		});

		// Custom Coupon Creator toggle: when "Create Custom" is selected in the coupon dropdown
		$(document).on('change', '.o100-referral-coupon-select', function () {
			var $parent = $(this).closest('.o100-coupon-reward-type-group, .o100-form-row');
			var $customFields = $parent.find('.o100-custom-coupon-fields');
			if ($(this).val() === '__custom__') {
				$customFields.slideDown(200);
			} else {
				$customFields.slideUp(200);
			}
		});

		// Form Submission (manual since form tag is stripped by CMB2)
		$(document).on('click', '.o100-btn-save-campaign, .o100-btn-save-close-campaign', function (e) {
			e.preventDefault();
			var $btn = $(this);
			// Use specific container ID for serialization
			var $form = $('#o100-campaign-form');
			if (!$form.length) {
				$form = $btn.closest('#o100-campaigns-editor-view, .o100-loyalty-settings');
			}
			var closeAfter = $btn.hasClass('o100-btn-save-close-campaign');

			console.log("WFA Debug: Saving campaign. Container:", $form.attr('id'), "Inputs:", $form.find(':input').length);

			// Basic HTML5 validation polyfill
			var isValid = true;
			var campaignName = $('#o100-campaign-name').val() || '';
			if (!campaignName.trim()) {
				alert("Campaign name is required.");
				$('#o100-campaign-name').focus();
				return;
			}

			$form.find(':input[required]:visible').each(function () {
				if (!$(this).val()) {
					isValid = false;
					$(this).focus();
					alert("Required field missing: " + ($(this).attr('name') || 'Input'));
					return false;
				}
			});
			if (!isValid) return;

			var btnText = $btn.html();
			$btn.prop('disabled', true).text(o100_loyalty.i18n.saving);

			// Serialize form into nested objects for WPLoyalty REST APIs
			var formData = $form.find(':input').serializeArray();
			var requestData = {};

			// Custom deeply nested parser
			$.each(formData, function () {
				var name = this.name;
				var value = this.value;

				// Handle array notation a[b][c]
				if (name.indexOf('[') !== -1) {
					var keys = name.replace(/\]/g, '').split('[');
					var current = requestData;

					for (var i = 0; i < keys.length; i++) {
						var key = keys[i];
						if (i === keys.length - 1) {
							// Check if it's an array push []
							if (key === '') {
								current.push(value);
							} else {
								current[key] = value;
							}
						} else {
							// If it's a number, make it an array, else object
							var nextKey = keys[i + 1];
							var isArray = (nextKey === '' || !isNaN(nextKey));

							if (typeof current[key] === 'undefined') {
								current[key] = isArray ? [] : {};
							}
							current = current[key];
						}
					}
				} else {
					requestData[name] = value;
				}
			});

			// Force action and name if missing
			requestData.action = 'o100_save_campaign';
			if (!requestData.name) requestData.name = $('#o100-campaign-name').val();
			if (!requestData.description) requestData.description = $('#o100-campaign-description').val();

			var vNonce = o100_loyalty.campaign_nonce;
			requestData.o100_loyalty_nonce = vNonce;
			requestData.is_show_way_to_earn = 1; // Force display in frontend

			// WPLoyalty expects JSON strings for conditions and point_rule
			if (requestData.conditions) {
				// Convert object map to array if needed
				var condArray = [];
				for (var k in requestData.conditions) {
					condArray.push(requestData.conditions[k]);
				}
				requestData.conditions = JSON.stringify(condArray);
			} else {
				requestData.conditions = "[]";
			}

			if (requestData.point_rule) {
				requestData.point_rule = JSON.stringify(requestData.point_rule);
			} else {
				requestData.point_rule = "{}";
			}

			// Force active status to 0 if not checked
			if (!requestData.active) {
				requestData.active = 0;
			}

			console.log("WFA Debug: Saving campaign with payload:", requestData);

			$.ajax({
				url: o100_loyalty.ajax_url,
				type: 'POST',
				data: requestData,
				success: function (response) {
					$btn.prop('disabled', false).html(btnText);
					console.log("WFA Debug: Save response:", response);
					if (response.success) {
						if (closeAfter) {
							location.reload();
						} else {
							alert(o100_loyalty.i18n.saved);
							if (response.data && response.data.redirect) {
								var urlParams = new URLSearchParams(response.data.redirect.split('?')[1]);
								$('#o100-campaign-id').val(urlParams.get('id'));
							}
						}
					} else {
						console.error("WFA Loyalty Save Error:", response.data);
						var errorMsg = response.data.message || o100_loyalty.i18n.error;
						if (response.data.field_error) {
							var fields = Object.keys(response.data.field_error).join(', ');
							errorMsg += " (Fields: " + fields + ")";
						}
						alert(errorMsg);
					}
				},
				error: function () {
					$btn.prop('disabled', false).html(btnText);
					alert(o100_loyalty.i18n.error);
				}
			});
		});

		// Image Upload Integration for Campaign/Reward UI
		$(document).on('click', '.o100-upload-image-btn', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $wrap = $btn.closest('.o100-image-upload-wrap');
			var $input = $wrap.find('.o100-image-url');
			var $preview = $wrap.find('.o100-image-preview');

			var mediaUploader = wp.media({
				title: 'Choose Image',
				button: { text: 'Select Image' },
				multiple: false
			}).on('select', function () {
				var attachment = mediaUploader.state().get('selection').first().toJSON();
				$input.val(attachment.url);
				$preview.html('<img src="' + attachment.url + '" style="max-width:80px; max-height:80px; border-radius:4px;" /><a href="#" class="o100-remove-image dashicons dashicons-no-alt" style="color:red; text-decoration:none;"></a>');
			}).open();
		});

		$(document).on('click', '.o100-remove-image', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.o100-image-upload-wrap');
			$wrap.find('.o100-image-url').val('');
			$wrap.find('.o100-image-preview').empty();
		});

		// ----------------------------------------------
		// List Table Actions (Toggle, Delete, Clone, Edit)
		// ----------------------------------------------
		// Toggle Status
		$(document).on('change', '.o100-campaign-status-toggle', function () {
			var id = $(this).data('id');
			var active = $(this).is(':checked') ? 1 : 0;
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_toggle_campaign_active',
				o100_loyalty_nonce: o100_loyalty.campaign_nonce,
				id: id,
				active: active
			}, function (res) {
				if (!res.success) {
					alert(res.data ? res.data.message : o100_loyalty.i18n.error);
				}
			});
		});

		// Delete Campaign
		$(document).on('click', '.o100-delete-campaign', function (e) {
			e.preventDefault();
			if (!confirm(o100_loyalty.i18n.confirm_delete)) return;
			var id = $(this).data('id');
			var $row = $(this).closest('tr');
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_delete_campaign',
				id: id,
				o100_loyalty_nonce: o100_loyalty.campaign_nonce
			}, function (res) {
				if (res.success) {
					$row.fadeOut(300, function () { $(this).remove(); });
				} else {
					alert(res.data ? res.data.message : o100_loyalty.i18n.error);
				}
			});
		});

		// Clone Campaign
		$(document).on('click', '.o100-clone-campaign', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_duplicate_campaign',
				campaign_id: id,
				o100_loyalty_nonce: o100_loyalty.campaign_nonce
			}, function (res) {
				if (res.success) {
					location.reload();
				} else {
					alert(res.data ? res.data.message : o100_loyalty.i18n.error);
				}
			});
		});

		// Edit Campaign
		$(document).on('click', '.o100-edit-campaign', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var typeStr = $(this).closest('tr').find('.column-type').text();
			var nonce = o100_loyalty.campaign_nonce;

			// Show Loading indicator
			var $btn = $(this);
			$btn.css('opacity', '0.5');

			$.post(o100_loyalty.ajax_url, { action: 'o100_loyalty_get_campaign', id: id, o100_loyalty_nonce: nonce }, function (res) {
				console.log("WFA Debug: Loading campaign data:", res);
				if (res.success && res.data) {
					var campaign = res.data;
					console.log("WFA Debug: Campaign object:", campaign);

					// Populate basic fields
					var $form = $('#o100-campaigns-editor-view'); // Use stable view container instead of form tag
					var $formTag = $('#o100-campaign-form');

					if ($formTag.length) {
						o100ClearForm($formTag);
					}

					$('#o100-campaign-id').val(campaign.id);
					$('#o100-campaign-action-type').val(campaign.action_type);
					$('#o100-editor-title-type').text(typeStr);

					console.log("WFA Debug: Populating name/desc/active...");
					$('#o100-campaign-name').val(campaign.name).trigger('change').trigger('input');
					$('#o100-campaign-description').val(campaign.description).trigger('change').trigger('input');
					$('#o100-campaign-active').prop('checked', campaign.active === 1 || campaign.active === '1').trigger('change').trigger('input');

					console.log("WFA Debug: Value check - Name:", $('#o100-campaign-name').val(), "Desc:", $('#o100-campaign-description').val(), "Active:", $('#o100-campaign-active').is(':checked'));

					$form.find('select[name="is_show_way_to_earn"]').val(campaign.is_show_way_to_earn).trigger('change').trigger('input');
					$form.find('input[name="ordering"]').val(campaign.ordering).trigger('change').trigger('input');

					// Condition Relationship
					if (campaign.condition_relationship) {
						$form.find('input[name="condition_relationship"][value="' + campaign.condition_relationship + '"]').prop('checked', true).trigger('change');
					}

					// Image
					if (campaign.icon) {
						$form.find('.o100-image-url').val(campaign.icon).trigger('change');
						$form.find('.o100-image-preview').html('<img src="' + campaign.icon + '" style="max-width:80px; max-height:80px; border-radius:4px;" /><a href="#" class="o100-remove-image dashicons dashicons-no-alt" style="color:red; text-decoration:none;"></a>');
					} else {
						$form.find('.o100-image-url').val('').trigger('change');
						$form.find('.o100-image-preview').empty();
					}

					console.log("WFA Debug: Basic fields populated.");

					// Dates
					if (campaign.start_at && campaign.start_at !== '0') {
						$form.find('input[name="start_at"]').val(campaign.start_at);
					}
					if (campaign.end_at && campaign.end_at !== '0' && campaign.end_at_format !== 'N/A') {
						$form.find('input[name="end_at"]').val(campaign.end_at);
						$form.find('.o100-include-end-date').prop('checked', true).trigger('change');
					} else {
						$form.find('.o100-include-end-date').prop('checked', false).trigger('change');
					}

					// Inject Dynamic Rules Blocks based on action_type
					var templateId = '#tmpl-o100-rules-' + campaign.action_type;
					var $rulesContainer = $('#o100-campaign-dynamic-rules');
					if ($(templateId).length) {
						$rulesContainer.html($(templateId).html());
					} else {
						$rulesContainer.html($('#tmpl-o100-rules-point_for_purchase').html());
					}

					// Populate DYNAMIC RULES recursively by matching name attributes
					if (campaign.point_rule_object) {
						var pointRules = campaign.point_rule_object;
						if (typeof pointRules === 'string') {
							try { pointRules = JSON.parse(pointRules); } catch (e) { }
						}

						// Flatten JSON for easier population
						var flattenPointRule = function (obj, prefix) {
							if (typeof obj !== 'object' || obj === null) return;
							$.each(obj, function (k, v) {
								var name = prefix ? prefix + '[' + k + ']' : 'point_rule[' + k + ']';
								if (typeof v === 'object' && v !== null && !Array.isArray(v)) {
									flattenPointRule(v, name);
								} else {
									var $input = $form.find('[name="' + name + '"]');
									if ($input.length) {
										if ($input.is(':checkbox') || $input.is(':radio')) {
											$input.filter('[value="' + v + '"]').prop('checked', true);
										} else {
											$input.val(v).trigger('change');
										}
									}
								}
							});
						};
						flattenPointRule(pointRules, '');

						// Make sure root campaign_type dropdown is also set (some rules put it here)
						if (campaign.campaign_type) {
							$form.find('.o100-campaign-reward-type-select').first().val(campaign.campaign_type).trigger('change');
						}
						
						// Trigger change on ALL reward type selectors to update visibility per-block
						// This handles referral where advocate and friend have independent selectors
						$rulesContainer.find('.o100-campaign-reward-type-select').each(function() {
							$(this).trigger('change');
						});

						// Trigger change on coupon selectors to show/hide custom coupon fields
						$rulesContainer.find('.o100-referral-coupon-select').each(function() {
							$(this).trigger('change');
						});
					}

					// Conditions List
					var $condWrapper = $('#o100-campaign-editor-view .o100-conditions-wrapper');
					$condWrapper.empty();
					var conditions = campaign.conditions_object || campaign.conditions;
					if (conditions) {
						if (typeof conditions === 'string') {
							try { conditions = JSON.parse(conditions); } catch (e) { conditions = []; }
						}
						$.each(conditions, function (i, cond) {
							try {
								o100AddConditionRow($condWrapper, cond);
								var options = cond.options || cond;
								if (options.value) {
									var $row = $condWrapper.find('.o100-condition-block').last();
									var $select = $row.find('.o100-condition-value-ajax');
									if ($select.length) {
										var values = Array.isArray(options.value) ? options.value : [options.value];
										var labels = Array.isArray(options.value_label) ? options.value_label : (options.value_label ? [options.value_label] : []);

										$.each(values, function (j, val) {
											var label = labels[j] || 'ID: ' + val;
											var option = new Option(label, val, true, true);
											$select.append(option);
										});
										$select.trigger('change');
									}
								}
							} catch (err) {
								console.error("WFA Debug: Error populating condition row " + i, err, cond);
							}
						});
					}

					// Switch View
					$('#o100-campaigns-list-view').hide();
					$('#o100-campaigns-type-view').hide();
					$('#o100-campaigns-editor-view').fadeIn(200);
				} else {
					alert(res.data ? res.data.message : o100_loyalty.i18n.error);
				}
			});
		});

	}

	// ==============================================
	// 6. Levels Module Logic
	// ==============================================
	if ($('.o100-levels-container').length) {

		// Initialize Color Picker
		if ($.fn.wpColorPicker) {
			$('.o100-color-picker').wpColorPicker();
		}

		// Grace Period Toggle
		$(document).on('change', '#o100-grace-enabled', function () {
			if ($(this).is(':checked')) {
				$('.o100-grace-days-row').slideDown();
			} else {
				$('.o100-grace-days-row').slideUp();
			}
		});

		// Save Global Level Settings
		$(document).on('click', '.o100-btn-save-level-settings', function () {
			var $btn = $(this);
			var $form = $('#o100-level-settings-form');
			var btnText = $btn.html();

			$btn.prop('disabled', true).text(o100_loyalty.i18n.saving);

			var data = {
				action: 'o100_loyalty_save_level_settings',
				o100_loyalty_nonce: o100_loyalty.nonce, // Global apps nonce
				levels_from_which_point_based: $('#o100-levels-based-on').val(),
				grace_period_enabled: $('#o100-grace-enabled').is(':checked') ? 1 : 0,
				grace_period_days: $('#o100-grace-period-days').val()
			};

			$.post(o100_loyalty.ajax_url, data, function (res) {
				$btn.prop('disabled', false).html(btnText);
				if (res.success) {
					alert(res.data.message);
				} else {
					alert(res.data.message || o100_loyalty.i18n.error);
				}
			});
		});

		// Create New Level
		$(document).on('click', '.o100-btn-create-level', function () {
			var $form = $('#o100-level-form');
			o100ClearForm($form);
			$('#o100-level-id').val(0);
			$('#o100-level-active').prop('checked', true);
			$('#o100-level-editor-view h2').text('ADD NEW LEVEL');

			$('#o100-levels-list-view').hide();
			$('#o100-level-editor-view').fadeIn(200);
		});

		// Back to Levels List
		$(document).on('click', '.o100-btn-back-to-levels', function () {
			$('#o100-level-editor-view').hide();
			$('#o100-levels-list-view').fadeIn(200);
		});

		// Edit Level
		$(document).on('click', '.o100-edit-level', function (e) {
			e.preventDefault();
			var id = $(this).data('id');
			var $btn = $(this);
			$btn.css('opacity', '0.5');

			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_get_levels', // This returns all levels, we filter in JS or add a specific get backend
				o100_loyalty_nonce: o100_loyalty.nonce
			}, function (res) {
				$btn.css('opacity', '1');
				if (res.success && res.data) {
					var levels = res.data;
					var level = levels.find(l => l.id == id);
					if (level) {
						var $view = $('#o100-level-editor-view');
						$('#o100-level-id').val(level.id);
						$('#o100-level-name').val(level.name);
						$('#o100-level-description').val(level.description);
						$('#o100-level-from-points').val(level.from_points);
						$('#o100-level-to-points').val(level.to_points);
						$('#o100-level-active').prop('checked', level.active == 1);

						if (level.text_color) {
							$('#o100-level-text-color').val(level.text_color);
							if ($.fn.wpColorPicker) {
								$('#o100-level-text-color').wpColorPicker('color', level.text_color);
							}
						}

						if (level.badge) {
							$view.find('.o100-image-url').val(level.badge);
							$view.find('.o100-image-preview').html('<img src="' + level.badge + '" style="max-width:80px; max-height:80px; border-radius:4px;" /><a href="#" class="o100-remove-image dashicons dashicons-no-alt" style="color:red; text-decoration:none;"></a>');
						} else {
							$view.find('.o100-image-url').val('');
							$view.find('.o100-image-preview').empty();
						}

						$('#o100-level-editor-view h2').text('EDIT LEVEL');
						$('#o100-levels-list-view').hide();
						$('#o100-level-editor-view').fadeIn(200);
					}
				}
			});
		});

		// Delete Level
		$(document).on('click', '.o100-delete-level', function (e) {
			e.preventDefault();
			if (!confirm(o100_loyalty.i18n.confirm_delete)) return;
			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_delete_level',
				id: id,
				o100_loyalty_nonce: o100_loyalty.nonce
			}, function (res) {
				if (res.success) {
					$row.fadeOut(300, function () { $(this).remove(); });
				} else {
					alert(res.data.message || o100_loyalty.i18n.error);
				}
			});
		});

		// Save Level
		$(document).on('click', '.o100-btn-save-level', function () {
			var $btn = $(this);
			var $form = $('#o100-level-form');
			var btnText = $btn.html();

			if (!$('#o100-level-name').val().trim()) {
				alert("Level name is required.");
				$('#o100-level-name').focus();
				return;
			}

			$btn.prop('disabled', true).text(o100_loyalty.i18n.saving);

			var formData = {
				action: 'o100_loyalty_save_level',
				id: $('#o100-level-id').val(),
				name: $('#o100-level-name').val(),
				description: $('#o100-level-description').val(),
				from_points: $('#o100-level-from-points').val(),
				to_points: $('#o100-level-to-points').val(),
				badge: $('#o100-level-badge').val(),
				text_color: $('#o100-level-text-color').val(),
				active: $('#o100-level-active').is(':checked') ? 1 : 0,
				o100_loyalty_nonce: $form.find('input[name="o100_loyalty_nonce"]').val()
			};

			$.post(o100_loyalty.ajax_url, formData, function (res) {
				$btn.prop('disabled', false).html(btnText);
				if (res.success) {
					alert(res.data.message || o100_loyalty.i18n.saved);
					location.reload(); // Simplest way to refresh the list for now
				} else {
					alert(res.data.message || o100_loyalty.i18n.error);
				}
			});
		});
	}

	// 7. Coupons Module Logic
	if ($('.o100-loyalty-coupons-wrap').length) {

		// 7.1 View Switching
		$(document).on('click', '.o100-btn-show-coupon-types', function () {
			$('#o100-coupons-list-view').hide();
			$('#o100-coupon-types-view').fadeIn(200);
		});

		$(document).on('click', '.o100-btn-back-to-list', function () {
			var $wrap = $(this).closest('.o100-loyalty-coupons-wrap, .o100-loyalty-campaigns-wrap');
			if (!$wrap.length) return;

			// Clear forms to avoid stale data
			var $formContainer = $wrap.find('.o100-loyalty-settings');
			if ($formContainer.length) {
				o100ClearForm($formContainer);
			}

			$wrap.find('#o100-coupon-types-view, #o100-coupon-editor-view, #o100-campaigns-type-view, #o100-campaigns-editor-view').hide();
			$wrap.find('#o100-coupons-list-view, #o100-campaigns-list-view').fadeIn(200);
		});

		// 7.2 Create Coupon (Show Editor with Type)
		$(document).on('click', '.o100-create-coupon-type', function () {
			var type = $(this).data('type');
			var typeLabel = $(this).find('h3').text();

			// Reset form (Div based)
			var $form = $('#o100-coupon-form');
			o100ClearForm($form);
			$form.find('input[name="active"]').prop('checked', true);
			$('#o100-coupon-id').val(0);
			$('#o100-coupon-discount-type').val(type);
			$('#o100-coupon-title-type').text(typeLabel);

			// Load type-specific fields
			var templateId = '#tmpl-o100-coupon-' + type;
			if ($(templateId).length) {
				$('#o100-coupon-dynamic-fields').html($(templateId).html());
			} else {
				$('#o100-coupon-dynamic-fields').empty();
			}

			// Visibility logic for Point Conversion
			if (type === 'points_conversion') {
				$('.o100-global-require-point').hide().find(':input').prop('disabled', true);
				$('.o100-global-reward-type').hide(); // Hide but don't disable to allow serialization
				$form.find('input[name="reward_type"][value="redeem_point"]').prop('checked', true).prop('disabled', false);
				$('#o100-coupon-display-name').prop('disabled', false).prop('readonly', false);

				// Initialize conversion UI
				o100UpdateConversionUI($form, 'fixed_cart');
			} else {
				$('.o100-global-reward-type, .o100-global-require-point').show().find(':input').prop('disabled', false);
				$('#o100-coupon-display-name').prop('disabled', false).prop('readonly', false);
			}

			// Initialize specialized fields (e.g. product search)
			if (type === 'free_product') {
				o100InitProductSearch($form.find('.o100-select-product'));
			}

			$('#o100-coupon-types-view').hide();
			$('#o100-coupon-editor-view').fadeIn(200);
		});

		// 7.3 Free Product Row Handlers
		$(document).on('click', '.o100-btn-add-free-product-row', function () {
			var $container = $('.o100-free-products-rows-container');
			var $firstRow = $container.find('.o100-free-product-row').first();
			var $newRow = $firstRow.clone();

			// Reset select2 in new row
			$newRow.find('.select2-container').remove();
			$newRow.find('select').attr('class', 'o100-select-product').val('').empty().append('<option value="">Select a product</option>');
			$newRow.find('.o100-btn-remove-free-product-row').show();

			$container.append($newRow);
			o100InitProductSearch($newRow.find('.o100-select-product'));
		});

		$(document).on('click', '.o100-btn-remove-free-product-row', function () {
			$(this).closest('.o100-free-product-row').remove();
		});

		// 7.4 Edit Coupon
		$(document).on('click', '.o100-btn-edit-coupon', function () {
			var id = $(this).data('id');
			var $btn = $(this);
			$btn.css('opacity', '0.5');

			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_get_reward',
				id: id,
				o100_loyalty_nonce: o100_loyalty.reward_nonce
			}, function (res) {
				$btn.css('opacity', '1');
				console.log("WFA Debug: Loading reward data:", res);
				if (res.success && res.data) {
					var reward = res.data;
					console.log("WFA Debug: Reward object:", reward);
					var $form = $('#o100-coupon-form');

					// Reset and set basics
					o100ClearForm($form);
					$('#o100-coupon-id').val(reward.id);
					$('#o100-coupon-discount-type').val(reward.discount_type);

					// Set Title Type Label
					var typeLabels = {
						'points_conversion': 'Points Conversion',
						'fixed_cart': 'Fixed Discount',
						'percent': 'Percentage Discount',
						'free_product': 'Free Product',
						'free_shipping': 'Free Shipping'
					};
					$('#o100-coupon-title-type').text(typeLabels[reward.discount_type] || reward.discount_type);

					// Load dynamic fields
					var templateId = '#tmpl-o100-coupon-' + reward.discount_type;
					if ($(templateId).length) {
						$('#o100-coupon-dynamic-fields').html($(templateId).html());
					}

					// Visibility logic for Point Conversion
					if (reward.discount_type === 'points_conversion') {
						$('.o100-global-require-point').hide().find(':input').prop('disabled', true);
						$('.o100-global-reward-type').hide(); // Hide but don't disable
						$form.find('input[name="reward_type"]').prop('disabled', false); // Ensure it's not disabled
						$('#o100-coupon-display-name').prop('disabled', false).prop('readonly', false);
						$('.o100-coupon-editor-view-title').text('Edit Points Conversion');
						// Initial UI state for conversion type
						o100UpdateConversionUI($form, reward.coupon_type || 'fixed_cart');
					} else {
						$('.o100-global-reward-type, .o100-global-require-point').show().find(':input').prop('disabled', false);
						$('#o100-coupon-display-name').prop('disabled', false).prop('readonly', false);
						$('.o100-coupon-editor-view-title').text('Edit Coupon');

						// Toggle require_point based on reward_type
						o100ToggleRequirePoint($form, reward.reward_type || 'redeem_point');
					}

					// Special handling for product search BEFORE populating values
					if (reward.discount_type === 'free_product') {
						var $container = $form.find('.o100-free-products-rows-container');
						$container.empty();

						var freeProds = reward.free_product_object || reward.free_product || [];
						if (typeof freeProds === 'string') {
							try { freeProds = JSON.parse(freeProds); } catch (e) { freeProds = []; }
						}

						if (!Array.isArray(freeProds) || freeProds.length === 0) {
							freeProds = ['']; // At least one empty row
						}

						$.each(freeProds, function (i, pId) {
							var html = '<div class="o100-free-product-row" style="display: flex; gap: 10px; margin-bottom: 5px;">';
							html += '<div style="flex: 1;"><select name="free_product[]" class="o100-select-product" style="width:100%;"><option value=""><?php esc_html_e( "Select a product", "order100" ); ?></option></select></div>';
							html += '<button type="button" class="button o100-btn-remove-free-product-row"' + (i === 0 ? ' style="display:none;"' : '') + '><span class="dashicons dashicons-no-alt"></span></button></div>';

							var $row = $(html);
							$container.append($row);
							var $select = $row.find('.o100-select-product');
							o100InitProductSearch($select);

							if (pId) {
								var option = new Option('ID: ' + pId, pId, true, true);
								$select.append(option).trigger('change');
							}
						});
					}

					$('#o100-coupon-name').val(reward.name).trigger('change').trigger('input');
					$('#o100-coupon-description').val(reward.description).trigger('change').trigger('input');
					$('#o100-coupon-active').prop('checked', reward.active == 1).trigger('change').trigger('input');

					// Expiry UI state
					if (reward.expire_after > 0) {
						$form.find('#o100-coupon-expiry-type').val('limited');
						$form.find('.o100-expiry-value-wrap').show();
					} else {
						$form.find('#o100-coupon-expiry-type').val('unlimited');
						$form.find('.o100-expiry-value-wrap').hide();
					}

					// Expiry Email state
					if (reward.enable_expiry_email == 1) {
						$form.find('.o100-expiry-email-days-group').show();
					} else {
						$form.find('.o100-expiry-email-days-group').hide();
					}

					// Populate fields
					$.each(reward, function (key, val) {
						var $el = $form.find('[name="' + key + '"]');
						if ($el.length && key !== 'name' && key !== 'description' && key !== 'active') {
							if ($el.is(':checkbox')) {
								$el.prop('checked', val == 1).trigger('change');
							} else if ($el.is(':radio')) {
								$form.find('[name="' + key + '"][value="' + val + '"]').prop('checked', true).trigger('change');
							} else {
								$el.val(val).trigger('change');
							}
						}
					});

					// Populate nested arrays (free_product, etc)
					if (reward.free_product && typeof reward.free_product === 'object') {
						$.each(reward.free_product, function (idx, pId) {
							$form.find('[name="free_product[' + idx + ']"]').val(pId).trigger('change');
						});
					}

					// Conditions List
					var $condWrapper = $('#o100-coupon-editor-view .o100-conditions-wrapper');
					$condWrapper.empty();
					var conditions = reward.conditions_object || reward.conditions;
					if (conditions) {
						if (typeof conditions === 'string') {
							try { conditions = JSON.parse(conditions); } catch (e) { conditions = []; }
						}
						$.each(conditions, function (i, cond) {
							try {
								o100AddConditionRow($condWrapper, cond);
								var options = cond.options || cond;
								if (options.value) {
									var $row = $condWrapper.find('.o100-condition-block').last();
									var $select = $row.find('.o100-condition-value-ajax');
									if ($select.length) {
										var values = Array.isArray(options.value) ? options.value : [options.value];
										var labels = Array.isArray(options.value_label) ? options.value_label : (options.value_label ? [options.value_label] : []);

										$.each(values, function (j, val) {
											var label = labels[j] || 'ID: ' + val;
											var option = new Option(label, val, true, true);
											$select.append(option);
										});
										$select.trigger('change');
									}
								}
							} catch (err) {
								console.error("WFA Debug: Error populating reward condition row " + i, err, cond);
							}
						});
					}

					// Switch View
					$('#o100-coupons-list-view').hide();
					$('#o100-coupon-editor-view').fadeIn(200);
				}
			});
		});

		$(document).on('click', '.o100-btn-save-coupon, .o100-btn-save-close-coupon', function () {
			var $btn = $(this);
			var $form = $('#o100-coupon-form');
			if (!$form.length) {
				$form = $btn.closest('#o100-coupon-editor-view, .o100-loyalty-settings');
			}
			var closeAfter = $btn.hasClass('o100-btn-save-close-coupon');

			console.log("WFA Debug: Coupon save clicked. Container found:", $form.length, "Inputs:", $form.find(':input').length);

			// Manual validation for div-based container
			var isValid = true;
			$form.find(':input[required]:visible').each(function () {
				if (!$(this).val()) {
					isValid = false;
					$(this).focus();
					alert("Required field missing: " + ($(this).attr('name') || 'Input'));
					return false;
				}
			});
			if (!isValid) return;

			$btn.prop('disabled', true).css('opacity', '0.5');

			// Use find(:input) because $form is now a div
			var formData = $form.find(':input').serializeArray();
			var requestData = {};
			$.each(formData, function (_, field) {
				var name = field.name;
				var value = field.value;

				if (name.indexOf('[') !== -1) {
					var keys = name.replace(/\]/g, '').split('[');
					var current = requestData;
					for (var i = 0; i < keys.length; i++) {
						var key = keys[i];
						if (i === keys.length - 1) {
							if (key === '') current.push(value);
							else current[key] = value;
						} else {
							var nextKey = keys[i + 1];
							var isArray = (nextKey === '' || !isNaN(nextKey));
							if (typeof current[key] === 'undefined') current[key] = isArray ? [] : {};
							current = current[key];
						}
					}
				} else {
					requestData[name] = value;
				}
			});

			// FORCE Action and Nonce
			requestData.action = 'o100_save_reward';
			requestData.o100_loyalty_nonce = o100_loyalty.reward_nonce;

			if (requestData.conditions) {
				var condArray = [];
				for (var k in requestData.conditions) condArray.push(requestData.conditions[k]);
				requestData.conditions = JSON.stringify(condArray);
			} else {
				requestData.conditions = "[]";
			}

			if (requestData.free_product) {
				var fpArray = [];
				if (Array.isArray(requestData.free_product)) {
					fpArray = requestData.free_product.filter(id => id !== "");
				} else {
					for (var k in requestData.free_product) {
						if (requestData.free_product[k] !== "") fpArray.push(requestData.free_product[k]);
					}
				}
				requestData.free_product = JSON.stringify(fpArray);
			} else {
				requestData.free_product = "[]";
			}

			$.post(o100_loyalty.ajax_url, requestData, function (res) {
				$btn.prop('disabled', false).css('opacity', '1');
				if (res.success) {
					if (closeAfter) {
						location.reload();
					} else {
						if (res.data && res.data.id) {
							$('#o100-coupon-id').val(res.data.id);
						}
						alert(res.data.message || 'Saved successfully');
					}
				} else {
					var msg = 'Save failed';
					if (res.data) {
						if (typeof res.data === 'string') {
							msg = res.data;
						} else if (res.data.message) {
							msg = res.data.message;
						}

						if (res.data.field_error) {
							var errs = [];
							$.each(res.data.field_error, function (f, e) {
								errs.push(f + ': ' + (Array.isArray(e) ? e.join(', ') : e));
							});
							msg += "\n\n" + errs.join("\n");
						}
					}
					alert(msg);
				}
			});
		});

		// 7.5 Specialized Logic for Rewards
		$(document).on('change', '#o100-coupon-enable-expiry-email', function () {
			if ($(this).val() == '1') {
				$('.o100-expiry-email-days-group').slideDown();
			} else {
				$('.o100-expiry-email-days-group').slideUp();
			}
		});

		$(document).on('change', '.o100-conversion-type', function () {
			var type = $(this).val();
			var $form = $(this).closest('.o100-loyalty-settings');
			o100UpdateConversionUI($form, type);
		});

		function o100UpdateConversionUI($form, type) {
			var $desc = $form.find('.o100-conversion-rate-desc');
			var $capsPercentage = $form.find('.o100-conversion-caps-percentage');
			var $capsFixed = $form.find('.o100-conversion-caps-fixed');

			if (type === 'percent') {
				$desc.text('NOTE: You can set percentage discounts for specific point values, allowing customers to redeem points for that configured percentage discount value. For example, you can set 100 points equal to 1% discount. So that, customers can redeem 100 points for a 1% discount.');
				$capsPercentage.slideDown();
				$capsFixed.hide();
			} else {
				$desc.text('NOTE: The above values will be used to calculate the conversion ratio. WPLoyalty will automatically calculate the value of each point using the following formula: value of the discount / number of points required = value of each point. Example: If you set 500 points for $5 value, then each point is worth: $5 / 500 = 0.01 . If a customer redeems 100 points, then he will be given $1 based on the conversion ratio.');
				$capsPercentage.hide();
				$capsFixed.hide(); // USER REQUEST: Hide caps for fixed conversion
			}
		}

		// Handle reward_type radio change
		$(document).on('change', 'input[name="reward_type"]', function () {
			var $form = $(this).closest('.o100-loyalty-settings');
			o100ToggleRequirePoint($form, $(this).val());
		});

		function o100ToggleRequirePoint($form, type) {
			if (type === 'redeem_coupon') {
				$form.find('.o100-require-point-wrapper').hide().find(':input').prop('disabled', true);
			} else {
				$form.find('.o100-require-point-wrapper').show().find(':input').prop('disabled', false);
			}
		}

		// Handle max_discount mapping for fixed conversion
		$(document).on('change', '.o100-field-max-discount-fixed', function () {
			var $form = $(this).closest('.o100-loyalty-settings');
			$form.find('input[name="max_discount"]').val($(this).val());
		});

		// Handle Expiry type toggle
		$(document).on('change', '#o100-coupon-expiry-type', function () {
			var $wrap = $(this).siblings('.o100-expiry-value-wrap');
			if ($(this).val() === 'limited') {
				$wrap.css('display', 'flex');
			} else {
				$wrap.hide();
				$wrap.find('input').val(0);
			}
		});

		// 7.6 Helper: Product Search Initialization
		function o100InitProductSearch($el) {
			if (typeof $.fn.select2 === 'undefined') return;

			$el.select2({
				ajax: {
					url: o100_loyalty.ajax_url,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							q: params.term,
							action: 'o100_loyalty_free_product_options',
							o100_loyalty_nonce: o100_loyalty.reward_nonce
						};
					},
					processResults: function (data) {
						return {
							results: data.data || []
						};
					},
					cache: true
				},
				minimumInputLength: 3,
				placeholder: 'Search for a product...'
			});
		}

		// 7.6 Toggle Status
		$(document).on('change', '.o100-toggle-coupon-status', function () {
			var id = $(this).data('id');
			var active = $(this).is(':checked') ? 1 : 0;
			
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_toggle_reward_active',
				id: id,
				active: active,
				o100_loyalty_nonce: o100_loyalty.reward_nonce
			}, function (res) {
				if (!res.success) {
					alert(res.data ? res.data.message : 'Toggle failed');
					location.reload();
				}
			});
		});

		// 7.7 Delete Coupon
		$(document).on('click', '.o100-btn-delete-coupon', function () {
			if (!confirm('Are you sure you want to delete this coupon?')) return;

			var id = $(this).data('id');
			
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_delete_reward',
				id: id,
				o100_loyalty_nonce: o100_loyalty.reward_nonce
			}, function (res) {
				if (res.success) {
					$('tr[data-id="' + id + '"]').fadeOut(300, function () { $(this).remove(); });
				} else {
					alert(res.data ? res.data.message : 'Delete failed');
				}
			});
		});

	}

	// ==============================================
	// 8. Customer Module Logic
	// ==============================================
	let customerPage = 1;
	let customerSearch = '';
	let currentSortField = 'id';
	let currentSortDir = 'DESC';

	if ($('.o100-customers-container').length) {

		// Initial Load
		o100LoadCustomers();

		// Search Input (Debounced)
		let searchTimer;
		$(document).on('keyup', '#o100-customer-search', function () {
			clearTimeout(searchTimer);
			customerSearch = $(this).val();
			searchTimer = setTimeout(function () {
				customerPage = 1;
				o100LoadCustomers();
			}, 500);
		});

		// Sorting
		$(document).on('click', '.o100-customers-table th.sortable', function () {
			const field = $(this).data('sort');
			if (currentSortField === field) {
				currentSortDir = currentSortDir === 'ASC' ? 'DESC' : 'ASC';
			} else {
				currentSortField = field;
				currentSortDir = 'DESC';
			}

			// Update UI Icons
			$('.o100-customers-table th.sortable .dashicons').removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2').addClass('dashicons-sort');
			const iconClass = currentSortDir === 'ASC' ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';
			$(this).find('.dashicons').removeClass('dashicons-sort').addClass(iconClass);

			o100LoadCustomers();
		});

		// Import Customers Trigger
		$(document).on('click', '#o100-btn-import-customers', function () {
			$('#o100-import-customer-file').trigger('click');
		});

		// Handle File Selection and Upload
		$(document).on('change', '#o100-import-customer-file', function (e) {
			const file = e.target.files[0];
			if (!file) return;

			const formData = new FormData();
			formData.append('action', 'o100_import_customers');
			formData.append('o100_loyalty_nonce', o100_loyalty.customer_nonce);
			formData.append('csv_file', file);

			const $btn = $('#o100-btn-import-customers');
			const originalHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Importing...');

			$.ajax({
				url: o100_loyalty.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (res) {
					$btn.prop('disabled', false).html(originalHtml);
					if (res.success) {
						alert(res.data.message || 'Import successful!');
						o100LoadCustomers();
					} else {
						alert(res.data.message || 'Import failed');
					}
					$('#o100-import-customer-file').val('');
				},
				error: function () {
					$btn.prop('disabled', false).html(originalHtml);
					alert('Network error during import');
					$('#o100-import-customer-file').val('');
				}
			});
		});

		// Export Customers

		$(document).on('click', '#o100-btn-export-customers', function () {
			const $btn = $(this);
			const originalHtml = $btn.html();
			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Exporting...');

			// Trigger download via window.location (the PHP will handle headers)
			const exportUrl = `${o100_loyalty.ajax_url}?action=o100_export_customers&o100_loyalty_nonce=${o100_loyalty.customer_nonce}`;
			window.location.href = exportUrl;

			setTimeout(() => {
				$btn.prop('disabled', false).html(originalHtml);
			}, 2000);
		});

		// Pagination
		$(document).on('click', '.o100-btn-prev-customers', function () {
			if (customerPage > 1) {
				customerPage--;
				o100LoadCustomers();
			}
		});

		$(document).on('click', '.o100-btn-next-customers', function () {
			customerPage++;
			o100LoadCustomers();
		});

		// Back to Customers List
		$(document).on('click', '.o100-btn-back-to-customers', function (e) {
			e.preventDefault();
			$('#o100-customer-detail-view').hide();
			$('#o100-customers-list-view').fadeIn(200);
		});

		// View Details
		$(document).on('click', '.o100-view-customer', function (e) {
			e.preventDefault();
			const id = $(this).data('id');
			o100LoadCustomerDetail(id);
		});

		// Adjust Points Trigger (from list)
		$(document).on('click', '.o100-adjust-points', function (e) {
			e.preventDefault();
			const id = $(this).data('id');
			const email = $(this).data('email');
			o100OpenAdjustPointsModal(id, email);
		});

		// Adjust Points Trigger (from detail)
		$(document).on('click', '.o100-btn-adjust-points-inline', function () {
			const id = $('#edit-customer-id').val(); // Stored hidden in detail view too
			const email = $('#detail-customer-email').text();
			o100OpenAdjustPointsModal(id, email);
		});

		function o100OpenAdjustPointsModal(id, email) {
			$('#adjust-customer-id').val(id);
			$('#adjust-customer-email').val(email);
			$('#adjust-points-value').val('');
			$('#adjust-admin-note').val('');
			$('#o100-adjust-points-modal').fadeIn(200);
		}

		// Edit Customer Trigger
		$(document).on('click', '.o100-edit-customer, .o100-btn-edit-profile', function (e) {
			e.preventDefault();
			const id = $(this).data('id') || $('#edit-customer-id').val();
			const email = $(this).data('email') || $('#detail-customer-email').text();
			const birthday = $(this).data('birthday') || $('#detail-birthday-display').text();

			$('#edit-customer-id').val(id);
			$('#edit-customer-email').val(email);
			$('#edit-customer-birthday').val(birthday !== '-' ? birthday : '');
			$('#o100-edit-customer-modal').fadeIn(200);
		});

		// Confirm Edit Customer
		$(document).on('click', '.o100-btn-confirm-edit', function () {
			const $btn = $(this);
			const btnText = $btn.text();
			const birthday = $('#edit-customer-birthday').val();
			const id = $('#edit-customer-id').val();

			$btn.prop('disabled', true).text("Saving...");

			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_save_customer',
				id: id,
				birthday_date: birthday,
				o100_loyalty_nonce: o100_loyalty.customer_nonce
			}, function (res) {
				$btn.prop('disabled', false).text(btnText);
				if (res.success) {
					$('#o100-edit-customer-modal').fadeOut(200);
					if ($('#o100-customer-detail-view').is(':visible')) {
						o100LoadCustomerDetail(id); // Reload detail
					} else {
						o100LoadCustomers(); // Reload list
					}
				} else {
					alert(res.data.message || "Failed to update customer");
				}
			});
		});

		// Ban/Unban Toggle (from list)
		$(document).on('click', '.o100-toggle-ban', function (e) {
			e.preventDefault();
			const id = $(this).data('id');
			const currentStatus = $(this).data('banned');
			o100ToggleCustomerStatus(id, !currentStatus);
		});

		// Ban Toggle (from detail)
		$(document).on('change', '#detail-ban-toggle', function () {
			const id = $('#edit-customer-id').val();
			const banned = $(this).prop('checked');
			o100ToggleCustomerStatus(id, banned, true);
		});

		// Opt-in Toggle
		$(document).on('change', '#detail-optin-toggle', function () {
			const id = $('#edit-customer-id').val();
			const optin = $(this).prop('checked');

			const nonce = o100_loyalty.customer_nonce;
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_toggle_customer_optin',
				id: id,
				is_allow_send_email: optin ? 1 : 0,
				o100_loyalty_nonce: nonce
			});
		});

		function o100ToggleCustomerStatus(id, newBanned, isSelfChange = false) {
			const msg = newBanned ? "Are you sure you want to ban this customer?" : "Are you sure you want to unban this customer?";
			if (!confirm(msg)) {
				if (isSelfChange) $('#detail-ban-toggle').prop('checked', !newBanned);
				return;
			}

			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_toggle_customer_status',
				id: id,
				is_banned: newBanned ? 1 : 0,
				o100_loyalty_nonce: o100_loyalty.customer_nonce
			}, function (res) {
				if (res.success) {
					if ($('#o100-customer-detail-view').is(':visible')) {
						o100LoadCustomerDetail(id);
					} else {
						o100LoadCustomers();
					}
				} else {
					alert(res.data.message || "Failed to update status");
					if (isSelfChange) $('#detail-ban-toggle').prop('checked', !newBanned);
				}
			});
		}

		// Copy Referral Code
		$(document).on('click', '#o100-copy-refer-btn', function () {
			const code = $('#detail-referral-code').val();
			const $btn = $(this);
			navigator.clipboard.writeText(code).then(() => {
				const originalHtml = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes" style="font-size:16px;"></span>');
				setTimeout(() => $btn.html(originalHtml), 2000);
			});
		});

		// Modal Close
		$(document).on('click', '.o100-modal-close, .o100-modal-close-btn', function () {
			$('.o100-modal').fadeOut(200);
		});

		// Confirm Adjust Points
		$(document).on('click', '.o100-btn-confirm-adjust', function () {
			const $btn = $(this);
			const btnText = $btn.text();
			const points = $('#adjust-points-value').val();
			const note = $('#adjust-admin-note').val();
			const id = $('#adjust-customer-id').val();

			if (!points || points <= 0) {
				alert("Please enter a valid point value.");
				return;
			}

			$btn.prop('disabled', true).text("Processing...");

			const nonce = o100_loyalty.customer_nonce;
			$.post(o100_loyalty.ajax_url, {
				action: 'o100_loyalty_adjust_points',
				id: id,
				points: points,
				action_type: type,
				comments: note,
				o100_loyalty_nonce: nonce
			}, function (res) {
				$btn.prop('disabled', false).text(btnText);
				if (res.success) {
					$('.o100-modal').fadeOut(200);
					if ($('#o100-customer-detail-view').is(':visible')) {
						o100LoadCustomerDetail(id);
					} else {
						o100LoadCustomers();
					}
				} else {
					alert(res.data.message || "Failed to adjust points");
				}
			});
		});
	}

	function o100LoadCustomers() {
		const $tbody = $('#o100-customers-tbody');
		$tbody.html('<tr><td colspan="7" class="o100-loyalty-empty">Loading customers...</td></tr>');

		const nonce = o100_loyalty.nonce || (window.o100_params ? window.o100_params.nonce : '');
		$.post(o100_loyalty.ajax_url, {
			action: 'o100_loyalty_get_customers',
			paged: customerPage,
			search: customerSearch,
			filter_order: currentSortField,
			filter_order_dir: currentSortDir,
			o100_loyalty_nonce: nonce
		}, function (res) {
			if (res.success && res.data.items) {
				const items = res.data.items;
				if (items.length === 0) {
					$tbody.html('<tr><td colspan="7" class="o100-loyalty-empty">No customers found.</td></tr>');
					return;
				}

				let html = '';
				items.forEach(function (item) {
					const banLabel = item.is_banned ? 'Unban' : 'Ban';
					const banClass = item.is_banned ? 'status-banned' : 'status-active';
					const statusText = item.is_banned ? 'Banned' : 'Active';

					html += `<tr>
						<td class="column-email"><strong>${item.user_email}</strong></td>
						<td class="column-points text-center"><span class="points-badge positive">${item.points}</span></td>
						<td class="column-level text-center">${item.level_name}</td>
						<td class="column-total-earned text-center">${item.earn_total_point}</td>
						<td class="column-total-used text-center">${item.used_total_points}</td>
						<td class="column-status text-center"><span class="o100-badge ${banClass}">${statusText}</span></td>
						<td class="column-actions text-right">
							<a href="#" class="o100-view-customer action-link" data-id="${item.id}">View Details</a> |
							<a href="#" class="o100-edit-customer action-link" data-id="${item.id}" data-email="${item.user_email}" data-birthday="${item.birthday_date || ''}">Edit</a> |
							<a href="#" class="o100-adjust-points action-link" data-id="${item.id}" data-email="${item.user_email}" data-points="${item.points}">Adjust Points</a> |
							<a href="#" class="o100-toggle-ban action-link" data-id="${item.id}" data-banned="${item.is_banned}" style="color:${item.is_banned ? 'green' : 'red'}">${banLabel}</a>
						</td>
					</tr>`;
				});
				$tbody.html(html);

				// Update pagination
				$('#o100-customer-count-display').text(res.data.total_count);
				$('.o100-btn-prev-customers').prop('disabled', customerPage <= 1);
				$('.o100-btn-next-customers').prop('disabled', (customerPage * res.data.limit) >= res.data.total_count);
			} else {
				$tbody.html('<tr><td colspan="7" class="o100-loyalty-empty" style="color:red;">Failed to load customers.</td></tr>');
			}
		}).fail(function () {
			$tbody.html('<tr><td colspan="7" class="o100-loyalty-empty" style="color:red;">Network error while loading customers.</td></tr>');
		});
	}

	function o100LoadCustomerDetail(id) {
		$('#o100-customers-list-view').hide();
		$('#o100-customer-detail-view').show();

		// Reset & Show Loading
		$('#o100-customer-detail-view').find('.o100-detail-main-info h1').text('Loading...');
		$('#o100-customer-detail-view').css('opacity', '0.6');

		// Use localized nonce with fallback to core o100_params if available
		const nonce = o100_loyalty.nonce || (window.o100_params ? window.o100_params.nonce : '');

		$.post(o100_loyalty.ajax_url, {
			action: 'o100_loyalty_get_customer_full_data',
			id: id,
			o100_loyalty_nonce: nonce
		}, function (res) {
			$('#o100-customer-detail-view').css('opacity', '1');
			if (res.success) {
				const d = res.data;
				const user = d.profile;

				// Header
				$('#detail-customer-email').text(user.user_email);
				$('#edit-customer-id').val(user.id);

				// Badges
				$('#detail-customer-level-badge').text(user.level_name);
				const statusLabel = user.is_banned ? 'BANNED' : 'ACTIVE';
				const statusClass = user.is_banned ? 'status-banned' : 'status-active';
				$('#detail-customer-status-badge').text(statusLabel).removeClass('status-active status-banned').addClass(statusClass);

				// Profile Card
				$('#detail-referral-code').val(user.refer_code || '-');
				$('#detail-birthday-display').text(user.birthday_date || '-');
				$('#detail-optin-toggle').prop('checked', !!user.is_allow_send_email);
				$('#detail-ban-toggle').prop('checked', !!user.is_banned);

				// Statistics
				$('#stat-current-points').text(user.points);
				$('#stat-rewards-earned').text(d.stats.rewards_earned);
				$('#stat-rewards-used').text(d.stats.rewards_used);
				$('#stat-reward-value').html(d.stats.reward_value_display);

				// Transactions Table
				let txHtml = '';
				if (d.transactions && d.transactions.length > 0) {
					d.transactions.forEach(tx => {
						const pointClass = tx.type === 'credit' ? 'positive' : 'negative';
						const pointSign = tx.type === 'credit' ? '+' : '-';
						txHtml += `<tr>
							<td style="padding-left:15px; color:#64748b; font-size:13px;">${tx.date_display}</td>
							<td style="font-weight:600; color:#334155;">${tx.activity}</td>
							<td class="text-center"><span class="points-badge ${pointClass}">${pointSign}${tx.points}</span></td>
							<td class="text-center" style="font-weight:700; color:#334155;">${tx.order_total_display || '-'}</td>
						</tr>`;
					});
				} else {
					txHtml = '<tr><td colspan="4" class="o100-loyalty-empty">No transactions found.</td></tr>';
				}
				$('#o100-customer-transactions-tbody').html(txHtml);

				// Rewards Table
				let rwHtml = '';
				if (d.rewards && d.rewards.length > 0) {
					d.rewards.forEach(rw => {
						rwHtml += `<tr>
							<td style="padding-left:15px; color:#64748b; font-size:13px;">${rw.date_display}</td>
							<td style="font-weight:600; color:#334155;">${rw.reward_name}</td>
							<td class="text-center"><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:700;">${rw.coupon_code}</code></td>
							<td class="text-center" style="font-size:12px; color:#64748b;">${rw.expiry_date || '-'}</td>
							<td class="text-center"><span class="o100-badge ${rw.status === 'used' ? 'status-banned' : 'status-active'}">${rw.status.toUpperCase()}</span></td>
						</tr>`;
					});
				} else {
					rwHtml = '<tr><td colspan="5" class="o100-loyalty-empty">No rewards found.</td></tr>';
				}
				$('#o100-customer-rewards-tbody').html(rwHtml);

			} else {
				alert(res.data.message || 'Failed to load details');
				$('.o100-btn-back-to-customers').trigger('click');
			}
		});
	}


	// ----------------------------------------------
	// 8. Advanced / Addon Settings Logic
	// ----------------------------------------------

	// Trigger Load Settings if clicking on Advanced
	$('.o100-loyalty-subtabs a').on('click', function () {
		const tabId = $(this).data('subtab');
		if (tabId === 'advanced') {
			setTimeout(function () {
				const activeNested = $('.o100-loyalty-nested-tab-link.active').data('nested-tab');
				if (activeNested === 'launcher') {
					o100LoadLauncherSettings();
				} else if (activeNested === 'referral') {
					o100LoadReferralSettings();
				}
			}, 300);
		}
	});

	// Nested Tab Switching for Advanced
	$(document).on('click', '.o100-loyalty-nested-tab-link', function (e) {
		e.preventDefault();
		const $this = $(this);
		const tabId = $this.data('nested-tab');

		$this.siblings().removeClass('active');
		$this.addClass('active');

		$('.o100-loyalty-nested-tab-content').hide();
		$(`#o100-loyalty-nested-tab-${tabId}`).fadeIn(200);

		// Load data if switching to launcher or referral
		if (tabId === 'launcher') {
			o100LoadLauncherSettings();
		} else if (tabId === 'referral') {
			o100LoadReferralSettings();
		}
	});

	// Launcher Icon Type Toggle
	$(document).on('change', '#o100-launcher-icon-type', function () {
		if ($(this).val() === 'custom') {
			$('.o100-launcher-custom-icon-group').slideDown();
		} else {
			$('.o100-launcher-custom-icon-group').slideUp();
		}
	});

	// Generic Image Upload for Advanced Tab
	$(document).on('click', '.o100-loyalty-upload-image', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const $preview = $($btn.data('preview'));
		const $input = $($btn.data('input'));

		const mediaUploader = wp.media({
			title: 'Select Image',
			button: { text: 'Use this image' },
			multiple: false
		}).on('select', function () {
			const attachment = mediaUploader.state().get('selection').first().toJSON();
			$input.val(attachment.url);
			$preview.attr('src', attachment.url).show();
		}).open();
	});

	// Copy to Clipboard
	$(document).on('click', '.o100-copy-to-clipboard', function () {
		const text = $(this).data('text');
		const $btn = $(this);
		const originalText = $btn.text();

		navigator.clipboard.writeText(text).then(() => {
			$btn.text('Copied!');
			setTimeout(() => $btn.text(originalText), 2000);
		});
	});

	function o100LoadLauncherSettings() {
		const $form = $('#o100-loyalty-launcher-settings-form');
		$form.css('opacity', '0.5');

		$.post(o100_loyalty.ajax_url, {
			action: 'o100_loyalty_get_launcher_settings',
			o100_loyalty_nonce: o100_loyalty.nonce
		}, function (res) {
			$form.css('opacity', '1');
			if (res.success && res.data) {
				const d = res.data.design;
				const i = res.data.icon;

				if (d.colors && d.colors.theme) {
					$form.find('input[name="theme_color"]').val(d.colors.theme.primary).trigger('change');
				}

				if (i.appearance) {
					$form.find('input[name="launcher_text"]').val(i.appearance.text);

					const iconSelected = i.appearance.icon.selected;
					if (iconSelected === 'image') {
						$form.find('select[name="icon_type"]').val('custom').trigger('change');
						$form.find('input[name="custom_icon_url"]').val(i.appearance.icon.image);
						if (i.appearance.icon.image) {
							$('#o100-launcher-icon-preview').attr('src', i.appearance.icon.image).show();
						}
					} else {
						$form.find('select[name="icon_type"]').val(i.appearance.icon.icon || 'gift').trigger('change');
					}
				}

				if (i.placement) {
					$form.find('select[name="position"]').val(i.placement.position);
				}

				// Initialize/Update color picker
				if ($.fn.wpColorPicker) {
					$form.find('.o100-color-picker').wpColorPicker();
				}
			}
		});
	}

	function o100LoadReferralSettings() {
		const $form = $('#o100-loyalty-referral-settings-form');
		$form.css('opacity', '0.5');

		$.post(o100_loyalty.ajax_url, {
			action: 'o100_loyalty_get_referral_settings',
			o100_loyalty_nonce: o100_loyalty.nonce
		}, function (res) {
			$form.css('opacity', '1');
			if (res.success && res.data) {
				const s = res.data.settings;
				const p = res.data.popup;
				const list = res.data.campaign_list;

				// Populate campaign select
				let options = '<option value="0">Select a campaign...</option>';
				if (list && list.length > 0) {
					list.forEach(c => {
						options += `<option value="${c.id}" ${s.campaign_id == c.id ? 'selected' : ''}>${c.name}</option>`;
					});
				}
				$('#o100-referral-campaign-select').html(options);

				// Populate popup fields
				$form.find('input[name="popup_title"]').val(p.title_content || '');
				$form.find('textarea[name="popup_subtitle"]').val(p.sub_title_content || '');
				$form.find('input[name="popup_image_url"]').val(p.popup_image || '');
				if (p.popup_image) {
					$('#o100-referral-popup-preview').attr('src', p.popup_image).show();
				}
			}
		});
	}

	// Launcher Submit
	$(document).on('submit', '#o100-loyalty-launcher-settings-form', function (e) {
		e.preventDefault();
		const $btn = $(this).find('button[type="submit"]');
		const btnText = $btn.text();
		$btn.prop('disabled', true).text('Saving...');

		const data = $(this).serialize() + '&action=o100_save_launcher_settings&o100_loyalty_nonce=' + o100_loyalty.nonce;

		$.post(o100_loyalty.ajax_url, data, function (res) {
			$btn.prop('disabled', false).text(btnText);
			if (res.success) {
				alert('Launcher settings saved!');
			} else {
				alert(res.data.message || 'Error saving settings');
			}
		});
	});

	// Referral & Opt-in WFA logic
	// Live Preview Handlers for Referral
	$(document).on('keyup change', '#wlcr-main-page input, #wlcr-main-page textarea, #wlcr-main-page select', function () {
		const $field = $(this);
		const name = $field.attr('name');
		const val = $field.val();
		const $preview = $('#wlcr-main-page .wlcr-preview');

		if (name === 'title_content') $preview.find('.wlcr-title-content').text(val);
		if (name === 'sub_title_content') $preview.find('.wlcr-sub-title-content, .wlcr-prompt-content').text(val);
		if (name === 'claim_button_text') $preview.find('#wlcr_popup_button').text(val);
		if (name === 'no_thanks_text') $preview.find('#wlcr_no_thanks').text(val);
		if (name === 'place_holder_content') $preview.find('.wlcr-email-input-field').attr('placeholder', val);

		if (name === 'enable_image_content') {
			if (val === 'yes') {
				$preview.find('.wlcr-popup-img').show();
				$('#wlcr-popup-image-field').show();
			} else {
				$preview.find('.wlcr-popup-img').hide();
				$('#wlcr-popup-image-field').hide();
			}
		}

		if (name === 'enable_sub_title_content') {
			if (val === 'yes') {
				$preview.find('.wlcr-sub-title').show();
				$('#wlcr-subtitle-field').show();
			} else {
				$preview.find('.wlcr-sub-title').hide();
				$('#wlcr-subtitle-field').hide();
			}
		}

		if (name === 'enable_prompt_content') {
			if (val === 'yes') {
				$preview.find('.wlcr-prompt-content').show();
				$('#wlcr-prompt-content-field').show();
			} else {
				$preview.find('.wlcr-prompt-content').hide();
				$('#wlcr-prompt-content-field').hide();
			}
		}
	});

	// Color Picker Initialization & Change Handlers
	function o100InitAddonColorPickers() {
		const $pickers = $('.wlr-color-picker');
		if ($.fn.wpColorPicker && $pickers.length) {
			$pickers.wpColorPicker({
				change: function (event, ui) {
					const color = ui.color.toString();
					const $input = $(this);
					const id = $input.attr('id');
					const $preview = $('#wlcr-main-page .wlcr-preview');

					if (id === 'wlcr-background-color') $preview.find('#wlcr-popup-form, #wlcr-guest-coupon-popup').css('background-color', color);
					if (id === 'wlcr-title-text-color') $preview.find('.wlcr-title-content').css('color', color);
					if (id === 'wlcr-subtitle-text-color') $preview.find('.wlcr-sub-title-content, .wlcr-prompt-content, #wlcr_no_thanks').css('color', color);
					if (id === 'wlcr-claim-button-background-color') $preview.find('#wlcr_popup_button').css('background-color', color);
					if (id === 'wlcr-claim-button-text-color') $preview.find('#wlcr_popup_button').css('color', color);

					// Coupon Template
					if (id === 'wlcr-coupon-color') {
						$preview.find('.wlcr-discount-code').css({ 'color': color, 'border-color': color });
					}
					if (id === 'wlcr-background-coupon-color') $preview.find('.wlcr-discount-code').css('background-color', color);
					if (id === 'wlcr-shop-button-background-color') $preview.find('#wlcr_shop_now_button').css('background-color', color);
					if (id === 'wlcr-shop-button-text-color') $preview.find('#wlcr_shop_now_button').css('color', color);
				}
			});
		}
	}

	// Media Uploader for Referral
	$(document).on('click', '.wlcr-choose-image-btn', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const $container = $btn.closest('.wlcr-input');
		const $body = $btn.closest('.wlcr-coupon-template-body, .wlcr-popup-template-body, .wlopt-settings-body'); // Scope for preview
		const media = wp.media({
			multiple: false,
			library: { type: 'image' }
		}).on('select', function () {
			const attachment = media.state().get('selection').first().toJSON();
			// Scope URL update to the current container so we don't accidentally update popup while in coupon
			$container.find('input[type="hidden"]').val(attachment.url);
			$container.find('.wlcr-image-preview img').attr('src', attachment.url);
			// Scope preview update to the current active tab body
			if ($body.length) {
				$body.find('.wlcr-popup-img img, .wlcr-preview img').attr('src', attachment.url);
			} else {
				// Fallback
				$('.wlcr-image-preview img, .wlcr-popup-img img').attr('src', attachment.url);
			}
		}).open();
	});

	// Unified Sticky Save Click
	$(document).on('click', '.o100-btn-save-sticky', function (e) {
		const $btn = $(this);
		const activeAddon = $('.o100-loyalty-addon-tab-link.active').data('addon-tab');

		if (activeAddon === 'referral') {
			o100SaveReferralSettings($btn);
		} else if (activeAddon === 'optin') {
			o100SaveOptinSettings($btn);
		}
	});

	function o100SaveReferralSettings($btn) {
		const $spinner = $('.o100-save-spinner');
		$btn.prop('disabled', true).find('.dashicons').addClass('spin');
		$spinner.show();

		const data = {
			action: 'o100_loyalty_save_referral_settings',
			o100_loyalty_nonce: o100_loyalty.nonce,
			settings_data: $('#o100-referral-settings :input').serialize(),
			popup_data: $('#o100-referral-popup-template :input').serialize(),
			coupon_data: $('#o100-referral-coupon-template :input').serialize()
		};

		$.post(o100_loyalty.ajax_url, data, function (res) {
			$btn.prop('disabled', false).find('.dashicons').removeClass('spin');
			$spinner.hide();
			if (res.success) {
				alertify.success('Referral settings saved successfully!');
			} else {
				alertify.error(res.data.message || 'Error saving settings');
			}
		});
	}

	function o100SaveOptinSettings($btn) {
		const $container = $('#o100-loyalty-optin-settings-form');
		const $spinner = $('.o100-save-spinner');

		$btn.prop('disabled', true).find('.dashicons').addClass('spin');
		$spinner.show();

		const data = $container.find(':input').serialize() + '&action=o100_save_optin_settings&o100_loyalty_nonce=' + o100_loyalty.nonce;

		$.post(o100_loyalty.ajax_url, data, function (res) {
			$btn.prop('disabled', false).find('.dashicons').removeClass('spin');
			$spinner.hide();
			if (res.success) {
				alertify.success('Opt-in settings saved successfully!');
			} else {
				alertify.error(res.data.message || 'Error saving settings');
			}
		});
	}

	// Initialize Addon logic on tab switch
	$(document).on('o100_addon_tab_switched', function (e, tab) {
		if (tab === 'referral' || tab === 'optin') {
			o100InitAddonColorPickers();
		}
	});
});

