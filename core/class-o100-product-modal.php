<?php
/**
 * Product Details Modal — DoorDash-style vertical layout
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Product_Modal {

	public function __construct() {
		add_action( 'wp_ajax_o100_product_modal_info', array( $this, 'ajax_get_product_info' ) );
		add_action( 'wp_ajax_nopriv_o100_product_modal_info', array( $this, 'ajax_get_product_info' ) );
		add_action( 'wp_footer', array( $this, 'output_modal_assets' ) );
	}

	public function output_modal_assets() {
		$options = get_option( 'o100_options', array() );
		$primary_color = !empty($options['o100_main_color']) ? $options['o100_main_color'] : '#e60023';
		?>
		<?php
		$currency = get_woocommerce_currency();
		$doordash_symbols = array(
			'CAD' => 'CA$',
			'USD' => 'US$',
			'AUD' => 'AU$',
			'NZD' => 'NZ$',
		);
		$currency_symbol = isset( $doordash_symbols[ $currency ] ) ? $doordash_symbols[ $currency ] : get_woocommerce_currency_symbol( $currency );
		?>
		<?php
		$ui_prefs = get_option( 'o100_ui_prefs', array() );
		$auto_close = ( isset( $ui_prefs['o100_close_pop'] ) && $ui_prefs['o100_close_pop'] === 'on' ) ? 'true' : 'false';
		?>
		<script>
		var o100_ajax_object = {
			ajax_url: '<?php echo admin_url( "admin-ajax.php" ); ?>',
			nonce: '<?php echo wp_create_nonce( "o100_modal_nonce" ); ?>',
			currency_symbol: '<?php echo esc_js( $currency_symbol ); ?>',
			auto_close: <?php echo $auto_close; ?>
		};
		jQuery(document).ready(function($) {
			// Variation image swap
			$(document).on('found_variation', '.o100-pm-addtocart .variations_form', function(event, variation) {
				if (variation && variation.image && variation.image.full_src) {
					var $modal = $(this).closest('.o100-product-modal-inner');
					var $mainImage = $modal.find('.o100-pm-main-image');
					$mainImage.data('original-image', $mainImage.css('background-image'));
					$mainImage.css('background-image', 'url(' + variation.image.full_src + ')');
				}
			});
			$(document).on('reset_image', '.o100-pm-addtocart .variations_form', function() {
				var $modal = $(this).closest('.o100-product-modal-inner');
				var $mainImage = $modal.find('.o100-pm-main-image');
				var orig = $mainImage.data('original-image');
				if (orig) {
					$mainImage.css('background-image', orig);
				}
			});
			
			// DoorDash Style Add To Cart Footer Logic
			function updateModalFooter($modal) {
				var $form = $modal.find('form.cart');
				if ($form.length === 0) return;
				
				var missingReq = 0;
				
				// 1. Check WooCommerce Variations (via our custom UI)
				var $varContainer = $modal.find('.o100-var-modifier-container');
				if ($varContainer.length) {
					if ($varContainer.hasClass('o100-var-multi')) {
						// Multi-attribute: need a L2 radio checked somewhere
						if ($varContainer.find('.o100-var-l2-radio:checked').length === 0) {
							missingReq++;
						}
					} else {
						// Single-attribute: need a radio checked
						if ($varContainer.find('.o100-var-radio:checked').length === 0) {
							missingReq++;
						}
					}
				} else if ($form.hasClass('variations_form')) {
					// Fallback: native variation selects
					$form.find('.variations select').each(function() {
						if ($(this).val() === '') missingReq++;
					});
				}
				
				// 2. Check Order100 Addons (exclude variation groups — already counted above)
				$form.find('.o100-addon-group.o100-required').not('.o100-var-group').each(function() {
					var $group = $(this);
					var hasSelection = false;
					if ($group.find('input[type="radio"], input[type="checkbox"]').length) {
						if ($group.find('input:checked').length > 0) hasSelection = true;
					} else if ($group.find('select').length) {
						if ($group.find('select').val() !== '') hasSelection = true;
					} else if ($group.find('input[type="text"], textarea').length) {
						if ($group.find('input, textarea').val().trim() !== '') hasSelection = true;
					}
					if (!hasSelection) missingReq++;
				});

				var $btn = $form.find('button.single_add_to_cart_button');
				var $qtyWrapper = $form.find('.quantity');

				// Calculate Total Price
				var basePrice = parseFloat($form.closest('.woocommerce').data('base-price')) || 0;
				// If variation is selected, get its price
				var varPrice = $form.data('variation-price');
				if (varPrice !== undefined) basePrice = parseFloat(varPrice);

				var additionalPrice = parseFloat($form.find('.o100-product-addons').data('calculated-price')) || 0;

				var totalPrice = basePrice + additionalPrice;
				var qty = parseFloat($qtyWrapper.find('input.qty').val()) || 1;
				totalPrice = totalPrice * qty;

				// Format price based on woo standard (rough client-side representation)
				// Here we just prepend CA$ or formatted format
				var currencySymbol = o100_ajax_object.currency_symbol || '$';
				var formattedPrice = currencySymbol + totalPrice.toFixed(2);

				if (missingReq > 0) {
					$qtyWrapper.hide();
					$btn.html('Make ' + missingReq + ' required selection - ' + currencySymbol + '0.00');
					$btn.addClass('o100-btn-disabled').prop('disabled', true);
				} else {
					$qtyWrapper.css('display', 'flex');
					$btn.html('Add to cart - ' + formattedPrice);
					$btn.removeClass('o100-btn-disabled').prop('disabled', false);
				}
			}

			$(document).on('change input', '.o100-pm-addtocart form.cart input, .o100-pm-addtocart form.cart select, .o100-pm-addtocart form.cart textarea', function() {
				var $modal = $(this).closest('.o100-product-modal-inner');
				updateModalFooter($modal);
			});

			$(document).on('o100_addons_price_calculated', function(e, additionalPrice, $container) {
				if ($container && $container.length) {
					var $modal = $container.closest('.o100-product-modal-inner');
					if ($modal.length) {
						updateModalFooter($modal);
					}
				}
			});

			$(document).on('click', '.o100-qty-minus, .o100-qty-plus', function() {
				var $input = $(this).siblings('input.qty');
				var val = parseFloat($input.val()) || 1;
				var min = parseFloat($input.attr('min')) || 1;
				var max = parseFloat($input.attr('max')) || 9999;
				var step = parseFloat($input.attr('step')) || 1;
				
				if ($(this).hasClass('o100-qty-minus')) {
					if (val > min) $input.val(val - step).trigger('change');
				} else {
					if (val < max) $input.val(val + step).trigger('change');
				}
			});

			$(document).on('found_variation', '.o100-pm-addtocart .variations_form', function(event, variation) {
				var $form = $(this);
				var $modal = $form.closest('.o100-product-modal-inner');
				
				if (variation && variation.display_price) {
					$form.data('variation-price', variation.display_price);
				} else {
					$form.removeData('variation-price');
				}
				updateModalFooter($modal);

				if (variation && variation.image && variation.image.full_src) {
					var $mainImage = $modal.find('.o100-pm-main-image');
					if (!$mainImage.data('original-image')) {
						$mainImage.data('original-image', $mainImage.css('background-image'));
					}
					$mainImage.css('background-image', 'url(' + variation.image.full_src + ')');
				}
			});
			
			$(document).on('reset_image', '.o100-pm-addtocart .variations_form', function() {
				var $form = $(this);
				var $modal = $form.closest('.o100-product-modal-inner');
				$form.removeData('variation-price');
				updateModalFooter($modal);

				var $mainImage = $modal.find('.o100-pm-main-image');
				var orig = $mainImage.data('original-image');
				if (orig) {
					$mainImage.css('background-image', orig);
				}
			});

			/**
			 * Convert WooCommerce variation dropdowns into DoorDash-style modifier UI.
			 * - Single attribute: radio group (like our modifiers)
			 * - Multi attribute: Level-1 rows navigate into a sliding Level-2 panel
			 */
			function convertVariationsToModifiers($modal) {
				var $vForm = $modal.find('.variations_form');
				if (!$vForm.length) return;

				var variations = $vForm.data('product_variations');
				if (!variations || !variations.length) return;

				var $table = $vForm.find('.variations');
				if (!$table.length) return;

				// Global base price = lowest of all variations
				var globalBase = Infinity;
				$.each(variations, function(i, v) {
					if (v.display_price < globalBase) globalBase = v.display_price;
				});

				var currencySymbol = o100_ajax_object.currency_symbol || '$';

				// Collect attribute info
				var attrs = [];
				$table.find('tr').each(function() {
					var $label = $(this).find('td.label label');
					var $select = $(this).find('td.value select');
					if (!$select.length) return;
					var dataAttrName = $select.data('attribute_name') || $select.attr('name');
					var label = $label.text().trim();
					var options = [];
					$select.find('option').each(function() {
						var val = $(this).val();
						if (val === '') return;
						options.push({ value: val, text: $(this).text().trim() });
					});
					attrs.push({ name: dataAttrName, label: label, options: options, $select: $select });
				});
				if (!attrs.length) return;

				// Hide native elements
				$table.hide();
				$vForm.find('.reset_variations').hide();
				$vForm.find('.single_variation_wrap .woocommerce-variation').hide();
				$vForm.find('.single_variation_wrap .woocommerce-variation-price').hide();

				var isMulti = attrs.length > 1;

				// ══════════════════════════════════════════
				// SINGLE ATTRIBUTE — simple radio group
				// ══════════════════════════════════════════
				if (!isMulti) {
					var attr0 = attrs[0];
					var $group = $('<div class="o100-addon-group o100-addon-type-radio o100-required o100-var-group"></div>');
					$group.append('<div class="o100-addon-header-text"><h4 class="o100-addon-title">' + attr0.label + '</h4><div class="o100-addon-subtitle o100-addon-required-subtitle"><span class="o100-req-icon">&#9888;</span> Required &bull; Choose 1</div></div>');

					var $choices = $('<div class="o100-addon-choices"></div>');
					$.each(attr0.options, function(oi, opt) {
						var inputId = 'o100_var_0_' + oi;
						var priceDiff = '';
						$.each(variations, function(vi, v) {
							if (v.attributes[attr0.name] === opt.value || v.attributes[attr0.name] === '') {
								var diff = v.display_price - globalBase;
								if (diff > 0) priceDiff = '<span class="o100-addon-price">+' + currencySymbol + diff.toFixed(2) + '</span>';
								return false;
							}
						});
						var $wrap = $('<div class="o100-addon-choice-wrap"></div>');
						$wrap.append('<label class="o100-addon-choice-label" for="' + inputId + '"><input type="radio" class="o100-var-radio" id="' + inputId + '" name="o100_var_0" value="' + opt.value + '" data-attr-name="' + attr0.name + '"><div class="o100-addon-op-info"><span class="o100-addon-name">' + opt.text + '</span>' + priceDiff + '</div></label>');
						$choices.append($wrap);
					});
					$group.append($choices);

					var $container = $('<div class="o100-var-modifier-container"></div>').append($group);
					var $addons = $vForm.closest('form.cart').find('.o100-product-addons');
					if ($addons.length) $container.insertBefore($addons); else $vForm.after($container);

					// Sync radio → WooCommerce
					$container.on('change', '.o100-var-radio', function() {
						var val = $(this).val();
						attr0.$select.val(val).trigger('change');
						var $m = $container.closest('.o100-product-modal-inner');
						if ($m.length) updateModalFooter($m);
					});
					return;
				}

				// ══════════════════════════════════════════
				// MULTI ATTRIBUTE — sliding panel navigation
				// ══════════════════════════════════════════
				var attr1 = attrs[0];
				var attr2 = attrs[1]; // currently support 2 levels

				// Pre-compute: for each attr1 option, the minimum price across all attr2 options
				var attr1MinPrices = {};
				$.each(attr1.options, function(_, opt1) {
					var minP = Infinity;
					$.each(variations, function(_, v) {
						if ((v.attributes[attr1.name] === opt1.value || v.attributes[attr1.name] === '') && v.display_price < minP) {
							minP = v.display_price;
						}
					});
					attr1MinPrices[opt1.value] = (minP === Infinity) ? globalBase : minP;
				});

				// Sort attr1 options by their minimum price (low → high)
				var sortedAttr1 = attr1.options.slice().sort(function(a, b) {
					return (attr1MinPrices[a.value] || 0) - (attr1MinPrices[b.value] || 0);
				});

				var $container = $('<div class="o100-var-modifier-container o100-var-multi"></div>');

				// ── Level 1 Panel ──
				var $level1 = $('<div class="o100-var-panel o100-var-panel-1 o100-var-panel-active"></div>');
				var $g1 = $('<div class="o100-addon-group o100-addon-type-radio o100-required o100-var-group"></div>');
				$g1.append('<div class="o100-addon-header-text"><h4 class="o100-addon-title">' + attr1.label + '</h4><div class="o100-addon-subtitle o100-addon-required-subtitle"><span class="o100-req-icon">&#9888;</span> Required &bull; Choose 1</div></div>');

				var $choices1 = $('<div class="o100-addon-choices"></div>');
				$.each(sortedAttr1, function(oi, opt) {
					var fromPrice = attr1MinPrices[opt.value];
					var diff = fromPrice - globalBase;
					var priceHtml = diff > 0
						? '<span class="o100-addon-price">from +' + currencySymbol + diff.toFixed(2) + '</span>'
						: '<span class="o100-addon-price">from ' + currencySymbol + fromPrice.toFixed(2) + '</span>';

					var $row = $('<div class="o100-addon-choice-wrap o100-var-l1-row" data-attr1-value="' + opt.value + '" data-sort-price="' + fromPrice + '"></div>');
					$row.append(
						'<div class="o100-addon-choice-label o100-var-l1-trigger">' +
							'<span class="o100-var-l1-radio"></span>' +
							'<div class="o100-addon-op-info"><span class="o100-addon-name">' + opt.text + '</span>' + priceHtml + '</div>' +
							'<span class="o100-var-l1-arrow dashicons dashicons-arrow-right-alt2"></span>' +
						'</div>'
					);
					$choices1.append($row);
				});
				$g1.append($choices1);
				$level1.append($g1);
				$container.append($level1);

				// ── Level 2 Panel (one per attr1 option) ──
				$.each(sortedAttr1, function(oi, opt1) {
					var $level2 = $('<div class="o100-var-panel o100-var-panel-2" data-attr1-value="' + opt1.value + '"></div>');
					var groupMinPrice = attr1MinPrices[opt1.value]; // min price within THIS L1 group

					// Back button
					$level2.append(
						'<div class="o100-var-back-btn">' +
							'<span class="dashicons dashicons-arrow-left-alt2"></span> ' +
							'<span>' + attr1.label + ': <strong>' + opt1.text + '</strong></span>' +
						'</div>'
					);

					var $g2 = $('<div class="o100-addon-group o100-addon-type-radio o100-required o100-var-group"></div>');
					$g2.append('<div class="o100-addon-header-text"><h4 class="o100-addon-title">' + attr2.label + '</h4><div class="o100-addon-subtitle o100-addon-required-subtitle"><span class="o100-req-icon">&#9888;</span> Required &bull; Choose 1</div></div>');

					var $choices2 = $('<div class="o100-addon-choices"></div>');
					// Collect L2 options with prices, then sort by price
					var l2Items = [];
					$.each(attr2.options, function(oi2, opt2) {
						var matchPrice = null;
						$.each(variations, function(_, v) {
							var m1 = (v.attributes[attr1.name] === opt1.value || v.attributes[attr1.name] === '');
							var m2 = (v.attributes[attr2.name] === opt2.value || v.attributes[attr2.name] === '');
							if (m1 && m2) { matchPrice = v.display_price; return false; }
						});
						if (matchPrice !== null) {
							l2Items.push({ opt: opt2, price: matchPrice, origIdx: oi2 });
						}
					});
					// Sort L2 by price low→high
					l2Items.sort(function(a, b) { return a.price - b.price; });

					$.each(l2Items, function(si, item) {
						// Price diff relative to THIS group's minimum
						var diff = item.price - groupMinPrice;
						var priceHtml = diff > 0
							? '<span class="o100-addon-price">+' + currencySymbol + diff.toFixed(2) + '</span>'
							: '';

						var inputId = 'o100_var_l2_' + oi + '_' + item.origIdx;
						var $wrap = $('<div class="o100-addon-choice-wrap"></div>');
						$wrap.append(
							'<label class="o100-addon-choice-label" for="' + inputId + '">' +
								'<input type="radio" class="o100-var-radio o100-var-l2-radio" id="' + inputId + '" name="o100_var_l2_' + oi + '" value="' + item.opt.value + '" data-attr1-value="' + opt1.value + '" data-attr1-name="' + attr1.name + '" data-attr2-name="' + attr2.name + '">' +
								'<div class="o100-addon-op-info"><span class="o100-addon-name">' + item.opt.text + '</span>' + priceHtml + '</div>' +
							'</label>'
						);
						$choices2.append($wrap);
					});
					$g2.append($choices2);
					$level2.append($g2);
					$container.append($level2);
				});

				// Insert
				var $addons = $vForm.closest('form.cart').find('.o100-product-addons');
				if ($addons.length) $container.insertBefore($addons); else $vForm.after($container);

				// ── Navigation: Level 1 → Level 2 ──
				$container.on('click', '.o100-var-l1-trigger', function() {
					var val1 = $(this).closest('.o100-var-l1-row').data('attr1-value');

					// If switching to a DIFFERENT L1 option, clear old L2 selection
					var $prevChecked = $container.find('.o100-var-l2-radio:checked');
					if ($prevChecked.length && $prevChecked.data('attr1-value') !== val1) {
						$prevChecked.prop('checked', false);
						$container.find('.o100-var-l1-row').removeClass('o100-var-l1-selected');
						attr2.$select.val('').trigger('change');
					}

					// Hide all L2 panels, show only the target
					$container.find('.o100-var-panel-2').removeClass('o100-var-panel-active');
					$container.find('.o100-var-panel-1').removeClass('o100-var-panel-active').addClass('o100-var-panel-exit');
					$container.find('.o100-var-panel-2[data-attr1-value="' + val1 + '"]').addClass('o100-var-panel-active');

					// Sync attr1 to WooCommerce
					attr1.$select.val(val1).trigger('change');
				});

				// ── Navigation: Level 2 → Back to Level 1 ──
				$container.on('click', '.o100-var-back-btn', function() {
					$(this).closest('.o100-var-panel-2').removeClass('o100-var-panel-active');
					$container.find('.o100-var-panel-1').removeClass('o100-var-panel-exit').addClass('o100-var-panel-active');
					// Selection is preserved — no clearing
				});

				// ── Sync Level 2 radio → WooCommerce ──
				$container.on('change', '.o100-var-l2-radio', function() {
					var $radio = $(this);
					var a1name = $radio.data('attr1-name');
					var a2name = $radio.data('attr2-name');
					var a1val = $radio.data('attr1-value');
					var a2val = $radio.val();

					// Sync both attrs
					$vForm.find('select[data-attribute_name="' + a1name + '"]').val(a1val).trigger('change');
					$vForm.find('select[data-attribute_name="' + a2name + '"]').val(a2val).trigger('change');

					// Mark selected on L1
					$container.find('.o100-var-l1-row').removeClass('o100-var-l1-selected');
					$container.find('.o100-var-l1-row[data-attr1-value="' + a1val + '"]').addClass('o100-var-l1-selected');

					var $m = $container.closest('.o100-product-modal-inner');
					if ($m.length) updateModalFooter($m);
				});
			}

			// Initialization when modal opens
			$(document).on('o100_modal_loaded', function(e, $modal) {
				var $form = $modal.find('form.cart');
				if ($form.length > 0 && $modal.find('.o100-pm-scrollable').length === 0) {
					// Make modal fixed flex column
					$modal.css({
						'display': 'flex',
						'flex-direction': 'column',
						'overflow': 'hidden'
					});

					// Make form and wrappers flex column to take full height
					$modal.find('.o100-pm-addtocart, .woocommerce').css({
						'display': 'flex',
						'flex-direction': 'column',
						'flex': '1',
						'min-height': '0',
						'padding': '0'
					});

					$form.css({
						'display': 'flex',
						'flex-direction': 'column',
						'flex': '1',
						'min-height': '0',
						'margin': '0'
					});

					// Create scrollable content area
					var $scrollable = $('<div class="o100-pm-scrollable" style="flex: 1; overflow-y: auto;"></div>');

					// Move injected messages (like Loyalty points) that are siblings of form.cart
					$form.siblings().each(function() {
						if ($(this).text().trim() !== '') {
							$(this).addClass('o100-pm-loyalty-msg');
							$(this).insertAfter($modal.find('.o100-pm-price'));
						} else {
							$(this).hide();
						}
					});
					
					// Move top sections into scrollable
					$scrollable.append($modal.find('.o100-pm-media'));
					$scrollable.append($modal.find('.o100-pm-content'));

					// Move addons into padded wrapper inside scrollable
					var $addonsWrapper = $('<div style="padding: 0 20px 20px;"></div>');
					$form.children().each(function() {
						$addonsWrapper.append($(this));
					});
					
					$scrollable.append($addonsWrapper);
					$form.prepend($scrollable);

					// Pull out quantity and button into a fixed flex footer
					var $btn = $addonsWrapper.find('button.single_add_to_cart_button');
					var $qty = $addonsWrapper.find('.quantity');
					
					// Ensure +/- exist
					if ($qty.find('.o100-qty-minus').length === 0) {
						$('<button type="button" class="o100-qty-minus">-</button>').insertBefore($qty.find('input.qty'));
						$('<button type="button" class="o100-qty-plus">+</button>').insertAfter($qty.find('input.qty'));
					}

					var $stickyFooter = $('<div class="o100-pm-sticky-footer"></div>');
					$qty.appendTo($stickyFooter);
					$btn.appendTo($stickyFooter);
					
					$form.append($stickyFooter);

					// Intercept form submission for App-like AJAX Add to Cart
					$form.on('submit', function(e) {
						if ($btn.hasClass('o100-btn-disabled')) {
							e.preventDefault();
							return false;
						}
						e.preventDefault();
						
						var $thisForm = $(this);
						var originalText = $btn.text();
						$btn.prop('disabled', true).text('Adding...');

						var serializedData = $thisForm.serialize();
						// For simple products, ensure button name/value is included
						if ($btn.attr('name') && $btn.attr('value')) {
							serializedData += '&' + encodeURIComponent($btn.attr('name')) + '=' + encodeURIComponent($btn.attr('value'));
						}

						$.ajax({
							url: $thisForm.attr('action'),
							type: 'POST',
							data: serializedData,
							success: function(response) {
								// Check if WooCommerce returned an error (e.g., out of stock)
								if ($(response).find('.woocommerce-error').length > 0) {
									var errorMsg = $(response).find('.woocommerce-error li').first().text();
									alert(errorMsg);
									$btn.prop('disabled', false).text(originalText);
								} else {
									// Success: Close modal if auto-close is enabled and refresh cart
									if (o100_ajax_object.auto_close) {
										var overlay = document.getElementById('o100-pm-overlay');
										if (overlay) {
											overlay.classList.remove('active');
											document.body.style.overflow = '';
										}
									}
									
									// Show success feedback on the button
									$btn.prop('disabled', false)
										.text('✓ Added!')
										.addClass('o100-btn-success');
									
									// Revert button after 2 seconds
									setTimeout(function() {
										// Only revert if the modal is still around and button exists
										if ($btn.length) {
											$btn.text(originalText).removeClass('o100-btn-success');
										}
									}, 2000);
									
									$(document.body).trigger('wc_fragment_refresh');
									$(document.body).trigger('added_to_cart', [null, null, $btn]);
								}
							},
							error: function() {
								$btn.prop('disabled', false).text('Error! Please try again');
							}
						});
					});
				}

				// Convert WooCommerce variations to modifier-style UI
				convertVariationsToModifiers($modal);

				updateModalFooter($modal);
			});
			
			// Gallery thumb click
			$(document).on('click', '.o100-pm-thumb', function() {
				var bg = $(this).css('background-image');
				var $modal = $(this).closest('.o100-product-modal-inner');
				var $mainImage = $modal.find('.o100-pm-main-image');
				$mainImage.data('original-image', bg);
				$mainImage.css('background-image', bg);
			});

			// Accordion toggle for addon groups
			$(document).on('click', '.o100-pm-accordion-header', function() {
				var $group = $(this).closest('.o100-pm-addon-group');
				$group.toggleClass('o100-pm-accordion-open');
				$group.find('.o100-pm-addon-body').slideToggle(200);
			});

			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					var overlay = document.getElementById('o100-pm-overlay');
					if (overlay && overlay.classList.contains('active')) {
						overlay.classList.remove('active');
						document.body.style.overflow = '';
					}
				}
			});
		});
		</script>
		<style>
			/* ===== MODAL OVERLAY ===== */
			.o100-pm-overlay {
				position: fixed;
				top: 0; left: 0; width: 100%; height: 100%;
				background: rgba(0,0,0,0.6);
				backdrop-filter: blur(4px);
				z-index: 999999;
				display: none;
				align-items: center;
				justify-content: center;
				opacity: 0;
				transition: opacity 0.3s;
			}
			.o100-pm-overlay.active {
				display: flex;
				opacity: 1;
			}

			/* ===== LOADING SPINNER ===== */
			.o100-pm-loading {
				display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;
			}
			.o100-pm-spinner {
				width: 40px; height: 40px;
				border: 4px solid rgba(255,255,255,0.3);
				border-top-color: #fff;
				border-radius: 50%;
				animation: o100-spin 0.8s linear infinite;
			}
			@keyframes o100-spin { to { transform: rotate(360deg); } }

			/* ===== MODAL CONTAINER — Always vertical ===== */
			.o100-product-modal-inner {
				background: #fff;
				border-radius: 16px;
				width: 90%;
				max-width: 520px;
				max-height: 90vh;
				overflow: hidden;
				position: relative;
				box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
				animation: o100-pop 0.3s ease-out;
			}
			@keyframes o100-pop {
				from { transform: translateY(20px) scale(0.95); opacity: 0; }
				to { transform: translateY(0) scale(1); opacity: 1; }
			}

			/* ===== CLOSE BUTTON ===== */
			.o100-pm-close {
				position: absolute;
				top: 12px; left: 12px;
				background: rgba(255,255,255,0.92);
				border: none;
				color: #191919;
				cursor: pointer;
				width: 36px; height: 36px;
				border-radius: 50%;
				display: flex; align-items: center; justify-content: center;
				box-shadow: 0 2px 8px rgba(0,0,0,0.15);
				z-index: 10;
				transition: all 0.2s;
				padding: 0;
			}
			.o100-pm-close:hover {
				background: #fff;
				transform: scale(1.08);
				box-shadow: 0 4px 12px rgba(0,0,0,0.2);
			}
			.o100-pm-close svg {
				width: 18px; height: 18px;
				stroke: #191919;
				stroke-width: 2.5;
			}

			/* ===== IMAGE AREA ===== */
			.o100-pm-media {
				position: relative;
				width: 100%;
			}
			.o100-pm-main-image {
				width: 100%;
				padding-bottom: 66%;
				background-size: cover;
				background-position: center;
				border-radius: 16px 16px 0 0;
			}
			.o100-pm-no-image {
				background-color: #f3f4f6;
				padding-bottom: 30%;
			}
			.o100-pm-gallery {
				display: flex;
				gap: 8px;
				padding: 12px 20px 0;
				overflow-x: auto;
			}
			.o100-pm-thumb {
				width: 56px; height: 56px;
				flex-shrink: 0;
				background-size: cover; background-position: center;
				border-radius: 8px;
				cursor: pointer;
				border: 2px solid transparent;
				transition: border-color 0.15s;
			}
			.o100-pm-thumb:hover { border-color: #191919; }

			/* ===== FOOD LABELS — Circular Icon Badges ===== */
			.o100-food-labels-container { position: absolute; top: 12px; right: 12px; display: flex; flex-direction: column; gap: 5px; z-index: 5; }
			.o100-food-label-item { position: relative; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(0,0,0,0.15); border: 1.5px solid var(--o100-theme-color, #ff5722); cursor: pointer; transition: transform 0.2s ease; }
			.o100-food-label-item:hover { transform: scale(1.15); }
			.o100-label-icon { width: 16px; height: 16px; object-fit: contain; display: block; pointer-events: none; }
			/* Tooltip */
			.o100-food-label-item::after { content: attr(title); position: absolute; right: calc(100% + 8px); top: 50%; transform: translateY(-50%); background: #1a1a1a; color: #fff; font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 6px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
			.o100-food-label-item:hover::after { opacity: 1; }

			/* ===== CONTENT AREA (DoorDash typography) ===== */
			.o100-pm-content {
				padding: 24px 20px 20px;
			}
			.o100-pm-title {
				margin: 0 0 8px;
				font-size: 22px;
				font-weight: 700;
				color: #191919;
				line-height: 1.25;
				letter-spacing: -0.01em;
			}
			.o100-pm-price {
				font-size: 16px;
				font-weight: 500;
				color: #191919;
				margin: 0 0 12px;
			}
			.o100-pm-price del {
				color: #999;
				font-weight: 400;
			}
			.o100-pm-price ins {
				text-decoration: none;
				color: #191919;
			}

			/* ===== SOCIAL SHARE ===== */
			.o100-pm-social-share {
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				margin-bottom: 16px;
			}
			.o100-pm-social-btn {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 28px;
				height: 28px;
				border-radius: 50%;
				color: #ffffff !important;
				transition: transform 0.2s, opacity 0.2s;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			.o100-pm-social-btn:hover {
				transform: translateY(-2px);
				opacity: 0.9;
			}
			.o100-pm-social-btn.facebook { background: #1877F2; }
			.o100-pm-social-btn.twitter { background: #1DA1F2; }
			.o100-pm-social-btn.whatsapp { background: #25D366; }
			.o100-pm-social-btn.email { background: #7f8c8d; }
			.o100-pm-social-btn.linkedin { background: #0077B5; }

			/* Injected notices / loyalty points */
			.o100-pm-content .o100-pm-loyalty-msg {
				font-size: 13px;
				font-weight: 600;
				color: var(--o100-notice-promo-txt);
				background: var(--o100-notice-promo-bg);
				border: 1px solid var(--o100-notice-promo-txt);
				padding: 6px 12px;
				border-radius: 8px;
				display: inline-block;
				margin-top: 0;
				margin-bottom: 16px;
			}
			.o100-pm-content .o100-pm-loyalty-msg * {
				color: inherit !important;
				font-size: inherit !important;
				font-weight: inherit !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.o100-pm-desc {
				font-size: 14px;
				color: #767676;
				line-height: 1.55;
				margin-bottom: 0;
				padding-bottom: 16px;
				border-bottom: 1px solid #e8e8e8;
			}
			.o100-pm-desc p {
				margin: 0 0 8px;
			}
			.o100-pm-desc p:last-child {
				margin-bottom: 0;
			}

			/* ===== NUTRITIONAL INFO ===== */
			.o100-pm-foodinfo {
				background: #fafafa;
				padding: 16px;
				border-radius: 10px;
				margin: 16px 0 0;
				border: 1px solid #ebebeb;
			}
			.o100-pm-foodinfo h4 {
				margin: 0 0 10px;
				font-size: 13px;
				font-weight: 700;
				color: #191919;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}
			.o100-pm-foodinfo ul {
				list-style: none;
				padding: 0;
				margin: 0;
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 6px;
			}
			.o100-pm-foodinfo li {
				font-size: 13px;
				color: #767676;
			}
			.o100-pm-foodinfo li strong {
				color: #191919;
			}

			/* ===== ADD-ON GROUPS (DoorDash style with dividers) ===== */
			.o100-pm-addtocart {
				padding: 0 0 4px;
			}

			/* Override native addon styles inside modal */
			.o100-pm-addtocart .o100-product-addons {
				margin: 0;
				padding: 0;
				border-top: none;
			}

			.o100-pm-addtocart .o100-addon-group {
				margin-bottom: 0;
				padding: 16px 0;
				border-bottom: 1px solid #e8e8e8;
			}
			.o100-pm-addtocart .o100-addon-group:last-child {
				border-bottom: none;
			}

			.o100-pm-addtocart .o100-addon-title {
				font-size: 18px;
				font-weight: 700;
				color: #191919;
				text-transform: none;
				letter-spacing: 0;
				margin-bottom: 4px;
			}
			.o100-pm-addtocart .o100-addon-title .required {
				color: #e60023;
				font-size: 14px;
			}

			/* Subtitles (DoorDash style) */
			.o100-pm-addtocart .o100-addon-subtitle {
				font-size: 13px;
				font-weight: 500;
				margin-bottom: 12px;
				display: flex;
				align-items: center;
			}
			.o100-pm-addtocart .o100-addon-required-subtitle {
				color: #c95100;
				font-weight: 600;
				background: transparent;
				padding: 0;
			}
			.o100-pm-addtocart .o100-req-icon {
				margin-right: 4px;
				font-size: 14px;
			}
			.o100-pm-addtocart .o100-addon-optional-subtitle {
				color: #767676;
			}
			.o100-pm-addtocart .o100-addon-limit-text {
				color: #191919;
				margin-left: 4px;
				font-weight: 500;
			}

			/* Choice items — vertical stack with subtle row dividers */
			.o100-pm-addtocart .o100-addon-choice-wrap {
				display: flex;
				align-items: center;
				justify-content: space-between;
			}
			
			.o100-pm-addtocart .o100-addon-choices {
				display: flex;
				flex-direction: column;
				gap: 0;
			}

			.o100-pm-addtocart .o100-addon-choice-label {
				display: flex;
				align-items: center;
				padding: 16px 0;
				border-bottom: 1px solid #ebebeb;
				font-size: 15px;
				color: #191919;
				cursor: pointer;
				transition: background 0.15s;
				flex: 1;
			}
			.o100-pm-addtocart .o100-addon-choice-label:last-child {
				border-bottom: none;
			}
			.o100-pm-addtocart .o100-addon-choice-label:hover {
				background: #fafafa;
				margin: 0 -20px;
				padding-left: 20px;
				padding-right: 20px;
			}

			.o100-pm-addtocart .o100-addon-choice-label input[type="checkbox"],
			.o100-pm-addtocart .o100-addon-choice-label input[type="radio"] {
				-webkit-appearance: none;
				appearance: none;
				width: 22px;
				height: 22px;
				border: 2px solid #191919;
				border-radius: 4px;
				outline: none;
				cursor: pointer;
				position: relative;
				margin-right: 16px;
				flex-shrink: 0;
				background-color: transparent;
				transition: all 0.2s;
			}
			.o100-pm-addtocart .o100-addon-choice-label input[type="radio"] {
				border-radius: 50%;
			}
			.o100-pm-addtocart .o100-addon-choice-label input[type="checkbox"]:checked,
			.o100-pm-addtocart .o100-addon-choice-label input[type="radio"]:checked {
				background-color: #191919;
				border-color: #191919;
			}
			.o100-pm-addtocart .o100-addon-choice-label input[type="checkbox"]:checked::after {
				content: "";
				position: absolute;
				left: 6px;
				top: 2px;
				width: 5px;
				height: 10px;
				border: solid #fff;
				border-width: 0 2px 2px 0;
				transform: rotate(45deg);
			}
			.o100-pm-addtocart .o100-addon-choice-label input[type="radio"]:checked::after {
				content: "";
				position: absolute;
				left: 6px;
				top: 6px;
				width: 6px;
				height: 6px;
				border-radius: 50%;
				background-color: #fff;
			}

			.o100-pm-addtocart .o100-addon-op-info {
				display: flex;
				flex-direction: column;
				flex: 1;
			}

			.o100-pm-addtocart .o100-addon-name {
				font-weight: 600;
				color: #191919;
				font-size: 15px;
			}

			.o100-pm-addtocart .o100-addon-price {
				color: #767676;
				font-weight: 400;
				font-size: 13px;
				margin-left: 0;
				margin-top: 2px;
				white-space: normal;
			}

			/* Select dropdown in modal */
			.o100-pm-addtocart .o100-addon-select {
				width: 100%;
				max-width: none;
				padding: 12px 16px;
				border: 1px solid #d6d6d6;
				border-radius: 8px;
				font-size: 15px;
				color: #191919;
				background: #fff;
				outline: none;
				margin-top: 8px;
			}
			.o100-pm-addtocart .o100-addon-select:focus {
				border-color: #191919;
			}

			/* Text / Textarea / Quantity in modal */
			.o100-pm-addtocart .o100-addon-text,
			.o100-pm-addtocart .o100-addon-textarea,
			.o100-pm-addtocart .o100-addon-quantity {
				width: 100%;
				max-width: none;
				padding: 12px 16px;
				border: 1px solid #d6d6d6;
				border-radius: 8px;
				font-size: 15px;
				color: #191919;
				outline: none;
				margin-top: 8px;
			}
			.o100-pm-addtocart .o100-addon-text:focus,
			.o100-pm-addtocart .o100-addon-textarea:focus,
			.o100-pm-addtocart .o100-addon-quantity:focus {
				border-color: #191919;
			}

			/* ===== ACCORDION MODE ===== */
			.o100-pm-addon-group.o100-pm-accordion-mode .o100-pm-accordion-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				cursor: pointer;
				padding: 16px 0;
				user-select: none;
			}
			.o100-pm-addon-group.o100-pm-accordion-mode .o100-pm-accordion-header:hover {
				opacity: 0.8;
			}
			.o100-pm-addon-group.o100-pm-accordion-mode .o100-pm-accordion-icon {
				width: 24px; height: 24px;
				display: flex; align-items: center; justify-content: center;
				font-size: 22px;
				font-weight: 300;
				color: #191919;
				transition: transform 0.2s;
				flex-shrink: 0;
			}
			.o100-pm-addon-group.o100-pm-accordion-mode.o100-pm-accordion-open .o100-pm-accordion-icon {
				transform: rotate(45deg);
			}
			.o100-pm-addon-group.o100-pm-accordion-mode .o100-pm-addon-body {
				display: none;
				padding-bottom: 8px;
			}
			.o100-pm-addon-group.o100-pm-accordion-mode.o100-pm-accordion-open .o100-pm-addon-body {
				display: block;
			}

			/* ===== WOO VARIATIONS INSIDE MODAL ===== */
			.o100-pm-addtocart .variations_form {
				margin-bottom: 16px;
			}
			.o100-pm-addtocart .variations {
				width: 100%;
				margin-bottom: 12px;
				border-collapse: separate;
				border-spacing: 0 8px;
			}
			.o100-pm-addtocart .variations td.label label {
				font-weight: 700;
				color: #191919;
				font-size: 14px;
				text-transform: uppercase;
				letter-spacing: 0.3px;
			}
			.o100-pm-addtocart .variations td.value select {
				width: 100%;
				padding: 12px 16px;
				border: 1px solid #d6d6d6;
				border-radius: 8px;
				font-size: 15px;
				outline: none;
			}
			.o100-pm-addtocart .variations td.value select:focus {
				border-color: #191919;
			}
			.o100-pm-addtocart .reset_variations {
				font-size: 13px;
				color: #e60023;
				text-decoration: none;
				margin-top: 4px;
				display: inline-block;
			}
			.o100-pm-addtocart .woocommerce-variation-price {
				display: none !important;
			}

			/* ===== VARIATION → MODIFIER CONVERSION ===== */
			.o100-var-modifier-container {
				margin-bottom: 0;
				position: relative;
				overflow: hidden;
			}

			/* ── Panel system ── */
			.o100-var-modifier-container.o100-var-multi {
				/* contains sliding panels */
			}
			.o100-var-panel {
				display: none;
			}
			.o100-var-panel.o100-var-panel-active {
				display: block;
				animation: o100VarSlideIn 0.25s ease-out;
			}
			.o100-var-panel.o100-var-panel-exit {
				display: none;
			}
			/* Slide-in from right for L2 */
			@keyframes o100VarSlideIn {
				from { opacity: 0; transform: translateX(30px); }
				to { opacity: 1; transform: translateX(0); }
			}

			/* ── Level 1 radio indicator ── */
			.o100-var-l1-radio {
				display: inline-block;
				width: 22px;
				height: 22px;
				border: 2px solid #191919;
				border-radius: 50%;
				margin-right: 16px;
				flex-shrink: 0;
				position: relative;
				transition: all 0.2s;
			}
			/* Filled state when L2 selection is made */
			.o100-var-l1-selected .o100-var-l1-radio {
				background-color: #191919;
				border-color: #191919;
			}
			.o100-var-l1-selected .o100-var-l1-radio::after {
				content: "";
				position: absolute;
				left: 6px;
				top: 6px;
				width: 6px;
				height: 6px;
				border-radius: 50%;
				background-color: #fff;
			}

			/* ── Level 1 rows (clickable, not radio) ── */
			.o100-var-l1-trigger {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 16px 0;
				border-bottom: 1px solid #ebebeb;
				cursor: pointer;
				transition: background 0.15s;
			}
			.o100-var-l1-trigger:hover {
				background: #fafafa;
				margin: 0 -20px;
				padding-left: 20px;
				padding-right: 20px;
			}
			.o100-var-l1-row:last-child .o100-var-l1-trigger {
				border-bottom: none;
			}
			.o100-var-l1-arrow {
				color: #767676;
				font-size: 20px !important;
				flex-shrink: 0;
			}
			/* Selected state on L1 after L2 is chosen */
			.o100-var-l1-selected .o100-var-l1-trigger {
				background: #f0fdf4;
				margin: 0 -20px;
				padding-left: 20px;
				padding-right: 20px;
				border-radius: 8px;
			}


			/* ── Back button ── */
			.o100-var-back-btn {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 12px 0 16px;
				cursor: pointer;
				font-size: 14px;
				color: #191919;
				border-bottom: 1px solid #ebebeb;
				margin-bottom: 4px;
				transition: opacity 0.15s;
			}
			.o100-var-back-btn:hover {
				opacity: 0.7;
			}
			.o100-var-back-btn .dashicons {
				font-size: 20px;
				width: 20px;
				height: 20px;
				color: #191919;
			}

			/* Hide native variation elements when our custom UI is active */
			.o100-var-modifier-container ~ .variations_form .variations,
			.o100-var-modifier-container ~ .variations_form .reset_variations {
				display: none !important;
			}

			/* ===== QUANTITY + ATC BUTTON (DoorDash sticky footer style) ===== */
			.o100-pm-addtocart {
				padding: 0;
			}
			.o100-pm-sticky-footer {
				background: #fff;
				padding: 16px 20px;
				border-top: 1px solid #e8e8e8;
				z-index: 10;
				display: flex;
				align-items: center;
				gap: 16px;
				flex-wrap: nowrap;
				flex-shrink: 0;
			}
			.o100-pm-addtocart form.cart > *:not(.o100-pm-scrollable):not(.o100-pm-sticky-footer) {
				width: 100%;
			}
			.o100-pm-addtocart .quantity {
				display: flex;
				align-items: center;
				margin: 0;
			}
			.o100-pm-addtocart .quantity .o100-qty-minus,
			.o100-pm-addtocart .quantity .o100-qty-plus {
				width: 40px; height: 40px;
				border-radius: 50%;
				background: #f3f4f6;
				border: none;
				font-size: 20px;
				color: #191919;
				cursor: pointer;
				display: flex; align-items: center; justify-content: center;
				transition: background 0.2s;
			}
			.o100-pm-addtocart .quantity .o100-qty-minus:hover,
			.o100-pm-addtocart .quantity .o100-qty-plus:hover {
				background: #e5e7eb;
			}
			.o100-pm-addtocart .quantity input.qty {
				width: 48px;
				height: 40px;
				padding: 0;
				margin: 0 8px;
				border: none;
				background: #f3f4f6;
				border-radius: 8px;
				text-align: center;
				font-size: 16px;
				font-weight: 600;
				color: #191919;
				-moz-appearance: textfield;
			}
			.o100-pm-addtocart .quantity input.qty::-webkit-outer-spin-button,
			.o100-pm-addtocart .quantity input.qty::-webkit-inner-spin-button {
				-webkit-appearance: none; margin: 0;
			}

			.o100-pm-addtocart button.single_add_to_cart_button {
				background: <?php echo esc_attr($primary_color); ?> !important;
				color: #fff !important;
				padding: 16px 24px;
				border: none !important;
				border-radius: 24px !important; /* More pill-like for DoorDash */
				font-weight: 700 !important;
				font-size: 16px !important;
				cursor: pointer;
				transition: opacity 0.2s;
				flex: 1;
				letter-spacing: 0.01em;
				text-align: center;
			}
			.o100-pm-addtocart button.single_add_to_cart_button:hover {
				opacity: 0.9;
			}
			.o100-pm-addtocart button.single_add_to_cart_button.o100-btn-disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}
			.o100-pm-addtocart button.single_add_to_cart_button.o100-btn-success {
				background: var(--o100-notice-success-txt, #047857) !important;
				color: #fff !important;
			}

			/* ===== ERROR STATE ===== */
			.o100-pm-error {
				padding: 60px 20px;
				text-align: center;
				font-size: 15px;
				color: #767676;
			}

			/* ===== MOBILE ADJUSTMENTS ===== */
			@media (max-width: 600px) {
				.o100-product-modal-inner {
					width: 95%;
					max-width: 95%;
					height: auto;
					max-height: 90vh;
					margin: auto; /* Vertically and horizontally center */
					border-radius: 16px;
					animation: o100-pop 0.3s ease-out;
				}
				.o100-pm-main-image {
					border-radius: 16px 16px 0 0;
					padding-bottom: 60%;
				}
				.o100-pm-close {
					top: 10px; left: 10px;
				}
				.o100-pm-cart-footer {
					border-radius: 0;
				}
			}
		</style>
		<?php
	}

	public function ajax_get_product_info() {
		check_ajax_referer( 'o100_modal_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( 'Invalid Product ID' );
		}

		// If variation ID is passed, get parent
		if ( get_post_type( $product_id ) === 'product_variation' ) {
			$product_id = wp_get_post_parent_id( $product_id );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( 'Product not found' );
		}

		ob_start();
		$this->render_modal_content( $product );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html' => $html
		) );
	}

	private function render_modal_content( $product ) {
		$product_id = $product->get_id();
		
		$image_url = get_the_post_thumbnail_url( $product_id, 'large' );
		$gallery_ids = $product->get_gallery_image_ids();
		
		// Global Food Info setting check
		$options = get_option( 'o100_options', array() );
		$show_food_info = isset( $options['o100_gl_info'] ) && $options['o100_gl_info'] === 'on';

		// Retrieve food info fields if enabled
		$food_info = array();
		if ( $show_food_info ) {
			$protein = get_post_meta( $product_id, 'exwoofood_protein', true );
			$calo    = get_post_meta( $product_id, 'exwoofood_calo', true );
			$choles  = get_post_meta( $product_id, 'exwoofood_choles', true );
			$fibel   = get_post_meta( $product_id, 'exwoofood_fibel', true );
			$sodium  = get_post_meta( $product_id, 'exwoofood_sodium', true );
			$carbo   = get_post_meta( $product_id, 'exwoofood_carbo', true );
			$fat     = get_post_meta( $product_id, 'exwoofood_fat', true );
			
			if ( $protein ) $food_info[__('Protein', 'order100')] = $protein;
			if ( $calo )    $food_info[__('Calories', 'order100')] = $calo;
			if ( $choles )  $food_info[__('Cholesterol', 'order100')] = $choles;
			if ( $fibel )   $food_info[__('Dietary fibre', 'order100')] = $fibel;
			if ( $sodium )  $food_info[__('Sodium', 'order100')] = $sodium;
			if ( $carbo )   $food_info[__('Carbohydrates', 'order100')] = $carbo;
			if ( $fat )     $food_info[__('Fat total', 'order100')] = $fat;

			// Custom grid data
			$custom_data = get_post_meta( $product_id, 'exwoofood_custom_data_gr', true );
			if ( is_array( $custom_data ) ) {
				foreach ( $custom_data as $data_it ) {
					if ( ! empty( $data_it['_name'] ) && ! empty( $data_it['_value'] ) ) {
						$food_info[ $data_it['_name'] ] = $data_it['_value'];
					}
				}
			}
		}

		$desc = $product->get_description();
		if ( empty( $desc ) ) {
			$desc = $product->get_short_description();
		}

		$ui_prefs = get_option( 'o100_ui_prefs', array() );

		?>
		<div class="o100-product-modal-inner">
			<?php if ( ! isset( $ui_prefs['o100_close_btn'] ) || $ui_prefs['o100_close_btn'] === 'on' ) : ?>
			<button class="o100-pm-close" aria-label="Close modal">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#191919" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
			<?php endif; ?>
			
			<!-- IMAGE — always on top -->
			<div class="o100-pm-media">
				<?php echo O100_Public::get_food_labels_html( $product_id ); ?>
				<?php if ( $image_url ) : ?>
					<div class="o100-pm-main-image" style="background-image: url('<?php echo esc_url( $image_url ); ?>');"></div>
				<?php else : ?>
					<div class="o100-pm-main-image o100-pm-no-image"></div>
				<?php endif; ?>
				
				<?php if ( ! empty( $gallery_ids ) ) : ?>
					<div class="o100-pm-gallery">
						<?php foreach ( $gallery_ids as $attachment_id ) : 
							$gal_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
						?>
							<div class="o100-pm-thumb" style="background-image: url('<?php echo esc_url( $gal_url ); ?>');"></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- CONTENT — title, price, description -->
			<div class="o100-pm-content">
				<h2 class="o100-pm-title"><?php echo esc_html( $product->get_name() ); ?></h2>
				
				<?php
				if ( class_exists( 'O100_Loyalty_Engine' ) && class_exists( 'O100_Loyalty_DB' ) ) {
					$loyalty_engine = new O100_Loyalty_Engine();
					$points = $loyalty_engine->calculate_product_points( $product );
					
					echo '<!-- O100 DEBUG: calculate_product_points returned: ' . esc_html( json_encode( $points ) ) . ' | Price: ' . $product->get_price() . ' | ID: ' . $product->get_id() . ' -->';
					

					if ( $points && ( $points['min'] > 0 || $points['max'] > 0 ) ) {
						$settings = O100_Loyalty_DB::get_settings();
						$label = $settings['point_label_plural'] ?? 'Points';
						
						if ( $points['min'] === $points['max'] ) {
							$points_text = sprintf( '+%s %s', $points['min'], $label );
						} else {
							$points_text = sprintf( '+%s-%s %s', $points['min'], $points['max'], $label );
						}
						
						echo '<div class="o100-pm-loyalty-badge" style="display:inline-flex; align-items:center; background:#f0fdf4; color:#166534; padding:4px 8px; border-radius:6px; font-size:13px; font-weight:600; margin-bottom:12px;">';
						echo '<svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
						echo esc_html( $points_text );
						echo '</div>';
					}
				}
				?>

				<p class="o100-pm-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
				
				<?php if ( ! empty( $desc ) ) : ?>
					<div class="o100-pm-desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
				<?php endif; ?>

				<?php if ( $show_food_info && ! empty( $food_info ) ) : ?>
					<div class="o100-pm-foodinfo">
						<h4><?php esc_html_e( 'Nutritional Information', 'order100' ); ?></h4>
						<ul>
							<?php foreach ( $food_info as $label => $val ) : ?>
								<li><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( $val ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>

			<!-- ADD TO CART + OPTIONS -->
			<div class="o100-pm-addtocart">
				<?php 
				// Load WooCommerce single add to cart template.
				// We must simulate that we are in the single product loop.
				global $post;
				$post = get_post( $product_id );
				setup_postdata( $post );

				// Output form wrapper
				$base_price = $product->get_price();
				echo '<div class="woocommerce" style="width: 100%;" data-base-price="' . esc_attr($base_price) . '">';
				woocommerce_template_single_add_to_cart();
				echo '</div>';

				wp_reset_postdata();
				?>
			</div>

			<!-- SOCIAL SHARE (Moved to bottom) -->
			<?php 
			$enable_social = isset( $ui_prefs['o100_enable_social'] ) && $ui_prefs['o100_enable_social'] === 'on';
			if ( $enable_social ) {
				$enabled_socials = isset( $ui_prefs['o100_enabled_socials'] ) ? (array) $ui_prefs['o100_enabled_socials'] : array('facebook', 'twitter', 'whatsapp', 'email', 'linkedin');
				$product_url = get_permalink( $product_id );
				$product_title = urlencode( $product->get_name() );
				
				echo '<div class="o100-pm-social-share" style="display: flex; align-items: center; justify-content: space-between; margin-top: 0; margin-bottom: 0; padding: 0 20px 20px 20px;">';
				echo '<span style="color:#94a3b8; font-size:13px; font-weight:500;">' . esc_html__('Share with friends', 'order100') . '</span>';
				echo '<div style="display: flex; gap: 8px;">';
				
				if ( in_array( 'facebook', $enabled_socials ) ) {
					echo '<a href="https://www.facebook.com/sharer/sharer.php?u=' . esc_url( $product_url ) . '" target="_blank" class="o100-pm-social-btn facebook" title="Share on Facebook"><svg viewBox="0 0 24 24" width="14" height="14" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg></a>';
				}
				if ( in_array( 'twitter', $enabled_socials ) ) {
					echo '<a href="https://twitter.com/intent/tweet?text=' . esc_attr( $product_title ) . '&url=' . esc_url( $product_url ) . '" target="_blank" class="o100-pm-social-btn twitter" title="Share on Twitter"><svg viewBox="0 0 24 24" width="14" height="14" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg></a>';
				}
				if ( in_array( 'whatsapp', $enabled_socials ) ) {
					echo '<a href="https://api.whatsapp.com/send?text=' . esc_attr( $product_title ) . ' - ' . esc_url( $product_url ) . '" target="_blank" class="o100-pm-social-btn whatsapp" title="Share on WhatsApp"><svg viewBox="0 0 24 24" width="14" height="14" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></a>';
				}
				if ( in_array( 'email', $enabled_socials ) ) {
					echo '<a href="mailto:?subject=' . esc_attr( $product_title ) . '&body=Check out this product: ' . esc_url( $product_url ) . '" class="o100-pm-social-btn email" title="Share via Email"><svg viewBox="0 0 24 24" width="14" height="14" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></a>';
				}
				if ( in_array( 'linkedin', $enabled_socials ) ) {
					echo '<a href="https://www.linkedin.com/sharing/share-offsite/?url=' . esc_url( $product_url ) . '" target="_blank" class="o100-pm-social-btn linkedin" title="Share on LinkedIn"><svg viewBox="0 0 24 24" width="14" height="14" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg></a>';
				}
				echo '</div></div>';
			}
			?>
		</div>
		<script>
			// Trigger loaded event after HTML is injected
			jQuery(document).trigger('o100_modal_loaded', [jQuery('.o100-product-modal-inner').last()]);
		</script>
		<?php
	}
}

new O100_Product_Modal();



