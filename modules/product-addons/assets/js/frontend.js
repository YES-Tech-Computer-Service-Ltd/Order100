/**
 * Product Add-ons — Frontend JS
 * Handles real-time price calculation, qty steppers, and conditional logic.
 *
 * @package Order100
 */

jQuery(document).ready(function($) {
	'use strict';

	// Toggle Qty display when checkbox is checked/unchecked
	$(document).on('change', '.o100-product-addons input[type="checkbox"].o100-options, .o100-product-addons input[type="radio"].o100-options', function(e) {
		var $this = $(this);
		var $group = $this.closest('.o100-addon-group');
		var $wrap = $this.closest('.o100-addon-choice-wrap');
		var $qtyWrap = $wrap.find('.o100-qty-op');
		
		// Validate limits before allowing check
		if ($this.is(':checked') && $this.is('input[type="checkbox"]')) {
			var maxsl = parseInt($group.data('maxsl')) || 0;
			var maxopqty = parseInt($group.data('maxopqty')) || 0;
			
			if (maxsl > 0) {
				var checkedCount = $group.find('input[type="checkbox"].o100-options:checked').length;
				if (checkedCount > maxsl) {
					$this.prop('checked', false);
					return false;
				}
			}
			
			if (maxopqty > 0) {
				var currentTotal = 0;
				$group.find('input[type="checkbox"].o100-options:checked').each(function() {
					if (this === $this[0]) return; // exclude current for calculation
					var $q = $(this).closest('.o100-addon-choice-wrap').find('.o100-qty-op-input');
					if ($q.length) {
						var qVal = parseInt($q.val());
						currentTotal += !isNaN(qVal) ? qVal : 1;
					} else {
						currentTotal += 1;
					}
				});
				var minQty = parseInt($qtyWrap.find('.o100-qty-op-input').attr('min')) || 1;
				if (currentTotal + minQty > maxopqty) {
					$this.prop('checked', false);
					return false;
				}
			}
		}
		
		if ($qtyWrap.length) {
			if ($this.is(':checked')) {
				$qtyWrap.slideDown(200);
				// Set value to min if empty or 0
				var $input = $qtyWrap.find('.o100-qty-op-input');
				var min = parseInt($input.attr('min')) || 1;
				if (parseInt($input.val()) < min) {
					$input.val(min).trigger('change');
				}
			} else {
				$qtyWrap.slideUp(200);
			}
		}

		if ($this.is('input[type="radio"]')) {
			// Hide other qty inputs in the same group
			var name = $this.attr('name');
			$('input[name="' + name + '"]').not(this).each(function() {
				$(this).closest('.o100-addon-choice-wrap').find('.o100-qty-op').slideUp(200);
			});
		}
	});

	// Qty Stepper Logic (+ / -)
	$(document).on('click', '.o100-product-addons .o100-addon-qty-minus, .o100-product-addons .o100-addon-qty-plus', function() {
		var $btn = $(this);
		var $wrap = $btn.closest('.o100-qty-op, .o100-qty-wrap');
		var $input = $wrap.find('input[type="number"]');
		var val = parseInt($input.val()) || 0;
		var min = parseInt($input.attr('min')) || 0;
		var max = parseInt($input.attr('max'));
		var step = parseInt($input.attr('step')) || 1;

		if ($btn.hasClass('o100-addon-qty-plus')) {
			if ($btn.prop('disabled')) return;
			
			var $group = $wrap.closest('.o100-addon-group');
			var maxopqty = parseInt($group.data('maxopqty')) || 0;
			if (maxopqty > 0) {
				var currentTotal = 0;
				$group.find('input[type="checkbox"].o100-options:checked').each(function() {
					var $q = $(this).closest('.o100-addon-choice-wrap').find('.o100-qty-op-input');
					if ($q.length) {
						var qVal = parseInt($q.val());
						currentTotal += !isNaN(qVal) ? qVal : 1;
					} else {
						currentTotal += 1;
					}
				});
				if (currentTotal + step > maxopqty) {
					return;
				}
			}

			if (!isNaN(max) && val >= max) return;
			$input.val(val + step).trigger('change');
		} else {
			var newVal = val - step;
			
			// If reducing to 0 or less, uncheck the checkbox if it exists
			if (newVal <= 0) {
				var $checkbox = $wrap.closest('.o100-addon-choice-wrap').find('input[type="checkbox"].o100-options');
				if ($checkbox.length) {
					$checkbox.prop('checked', false).trigger('change');
				}
				$input.val(min > 0 ? min : 1);
				return;
			}
			
			// Prevent going below min if min is strictly > 0 and we haven't hit <= 0
			if (min > 0 && newVal < min) return;
			
			$input.val(newVal).trigger('change');
		}
	});

	function calculateTotal($container) {
		if (!$container || !$container.length) return;
		
		var additionalPrice = 0;
		
		$container.find('input:checked, select option:selected, input[type="number"], input[type="text"], textarea').each(function() {
			var $el = $(this);
			
			// For number/text/textarea, only count if there's a value
			if ($el.is('input[type="number"], input[type="text"], textarea') && !$el.val()) {
				return;
			}

			// If this is an option that has an associated qty input, read that qty input
			var qty = 1;
			var $qtyInput;
			if ($el.is('input[type="checkbox"].o100-options, input[type="radio"].o100-options')) {
				$qtyInput = $el.closest('.o100-addon-choice-wrap').find('.o100-qty-op-input');
				if ($qtyInput.length) {
					var parsedQty = parseFloat($qtyInput.val());
					qty = !isNaN(parsedQty) ? parsedQty : 1;
				}
			} else if ($el.is('input[type="number"].o100-addon-quantity')) {
				var parsedElQty = parseFloat($el.val());
				qty = !isNaN(parsedElQty) ? parsedElQty : 0;
			}

			var price = parseFloat($el.data('price')) || 0;
			if (price > 0) {
				var priceType = $el.data('type');
				if (priceType === undefined || priceType === null) {
					priceType = $el.data('pricetype');
				}
				if (priceType === undefined || priceType === null) {
					priceType = '';
				}
				if (priceType !== 'fixed' && qty > 0) {
					additionalPrice += (price * qty);
				} else {
					additionalPrice += price;
				}
			}
		});

		// Enforce group UI limits
		$container.find('.o100-addon-group').each(function() {
			var $group = $(this);
			var maxsl = parseInt($group.data('maxsl')) || 0;
			var maxopqty = parseInt($group.data('maxopqty')) || 0;
			
			var checkedCount = $group.find('input[type="checkbox"].o100-options:checked').length;
			var totalQty = 0;

			$group.find('input[type="checkbox"].o100-options:checked').each(function() {
				var $q = $(this).closest('.o100-addon-choice-wrap').find('.o100-qty-op-input');
				if ($q.length) {
					var qVal = parseInt($q.val());
					totalQty += !isNaN(qVal) ? qVal : 1;
				} else {
					totalQty += 1;
				}
			});

			var limitReached = false;
			
			if (maxsl > 0 && checkedCount >= maxsl) {
				limitReached = true;
			}
			if (maxopqty > 0 && totalQty >= maxopqty) {
				limitReached = true;
				$group.find('.o100-addon-qty-plus').prop('disabled', true).css('opacity', '0.3');
			} else {
				$group.find('.o100-addon-qty-plus').prop('disabled', false).css('opacity', '1');
			}

			if (limitReached) {
				$group.find('input[type="checkbox"].o100-options:not(:checked)').prop('disabled', true).closest('.o100-addon-choice-label').css('opacity', '0.5');
			} else {
				$group.find('input[type="checkbox"].o100-options:not(:checked)').prop('disabled', false).closest('.o100-addon-choice-label').css('opacity', '1');
			}
		});

		$container.data('calculated-price', additionalPrice);
		$(document).trigger('o100_addons_price_calculated', [additionalPrice, $container]);
	}

	$(document).on('change input', '.o100-product-addons input, .o100-product-addons select, .o100-product-addons textarea', function() {
		calculateTotal($(this).closest('.o100-product-addons'));
	});

	// When modal is loaded, initialize state
	$(document).on('o100_modal_loaded', function(e, $modal) {
		var $addonsContainer = $modal.find('.o100-product-addons');
		if ($addonsContainer.length) {
			$addonsContainer.find('input[type="checkbox"].o100-options:checked, input[type="radio"].o100-options:checked').trigger('change');
			calculateTotal($addonsContainer);
		}
	});

	// For non-modal (standard product pages)
	var $staticContainer = $('.o100-product-addons');
	if ($staticContainer.length) {
		$staticContainer.find('input[type="checkbox"].o100-options:checked, input[type="radio"].o100-options:checked').trigger('change');
		calculateTotal($staticContainer);
	}

	// DoorDash style textarea panel logic
	$(document).on('click', '.o100-textarea-trigger', function() {
		var $group = $(this).closest('.o100-addon-group');
		var $panel = $group.find('.o100-textarea-slide-panel');
		var $textarea = $panel.find('.o100-addon-textarea');
		
		// Store original value to detect unsaved changes
		$panel.data('original-value', $textarea.val());
		
		// Open panel
		$panel.css('transform', 'translateX(0)').addClass('o100-panel-open');
		
		// Focus text area after animation
		setTimeout(function() {
			$textarea.focus();
		}, 300);
	});
	
	function closeSlidePanel($panel, saveChanges) {
		if (!saveChanges) {
			// Revert to original value if not saving
			var originalValue = $panel.data('original-value') || '';
			$panel.find('.o100-addon-textarea').val(originalValue);
		}
		$panel.css('transform', 'translateX(100%)').removeClass('o100-panel-open');
	}
	
	function showCustomConfirm(message, onSave, onDiscard) {
		var $overlay = $('.o100-confirm-overlay');
		if (!$overlay.length) {
			var html = '<div class="o100-confirm-overlay">' +
				'<div class="o100-confirm-modal">' +
				'<div class="o100-confirm-title">Unsaved Changes</div>' +
				'<div class="o100-confirm-message"></div>' +
				'<div class="o100-confirm-actions">' +
				'<div class="o100-confirm-btn o100-confirm-discard">Discard</div>' +
				'<div class="o100-confirm-btn o100-confirm-save">Save</div>' +
				'</div></div></div>';
			$('body').append(html);
			$overlay = $('.o100-confirm-overlay');
		}
		
		$overlay.find('.o100-confirm-message').text(message);
		$overlay.addClass('o100-active');
		
		$overlay.off('click').on('click', function(e) {
			e.stopPropagation();
			if ($(e.target).hasClass('o100-confirm-overlay')) {
				$overlay.removeClass('o100-active');
			}
		});
		
		$overlay.find('.o100-confirm-save').off('click').on('click', function(e) {
			e.stopPropagation();
			e.preventDefault();
			$overlay.removeClass('o100-active');
			if(typeof onSave === 'function') onSave();
		});
		
		$overlay.find('.o100-confirm-discard').off('click').on('click', function(e) {
			e.stopPropagation();
			e.preventDefault();
			$overlay.removeClass('o100-active');
			if(typeof onDiscard === 'function') onDiscard();
		});
	}

	function attemptClosePanel($panel) {
		var $textarea = $panel.find('.o100-addon-textarea');
		var originalValue = $panel.data('original-value') || '';
		var currentValue = $textarea.val();
		
		if (originalValue !== currentValue) {
			showCustomConfirm(
				'You have unsaved changes. Do you want to save them before leaving?',
				function() {
					closeSlidePanel($panel, true);
				},
				function() {
					closeSlidePanel($panel, false);
				}
			);
		} else {
			closeSlidePanel($panel, false);
		}
	}

	$(document).on('click', '.o100-slide-panel-back', function(e) {
		e.stopPropagation();
		e.preventDefault();
		var $panel = $(this).closest('.o100-textarea-slide-panel');
		attemptClosePanel($panel);
	});
	
	$(document).on('click', '.o100-slide-panel-save', function(e) {
		e.stopPropagation();
		e.preventDefault();
		var $panel = $(this).closest('.o100-textarea-slide-panel');
		// Trigger calculation in case text causes price logic (though normally text doesn't, but standardizing)
		$panel.find('.o100-addon-textarea').trigger('change');
		closeSlidePanel($panel, true);
	});
	
	// Touch swipe to go back
	$(document).on('touchstart', '.o100-textarea-slide-panel', function(e) {
		if (e.originalEvent && e.originalEvent.changedTouches) {
			$(this).data('touchStartX', e.originalEvent.changedTouches[0].screenX);
		}
	});
	$(document).on('touchend', '.o100-textarea-slide-panel', function(e) {
		var touchStartX = $(this).data('touchStartX');
		if (touchStartX === undefined || !e.originalEvent || !e.originalEvent.changedTouches) return;
		var touchEndX = e.originalEvent.changedTouches[0].screenX;
		var swipeThreshold = 50; // minimum distance to be considered a swipe
		if (touchEndX - touchStartX > swipeThreshold) {
			// Swiped from left to right
			attemptClosePanel($(this));
		}
		$(this).removeData('touchStartX');
	});
	
	// Intercept global clicks to prevent native modal from closing when slide panel is open
	document.addEventListener('click', function(e) {
		var $openPanel = $('.o100-panel-open');
		if ($openPanel.length > 0) {
			var $confirmOverlay = $('.o100-confirm-overlay.o100-active');
			if ($confirmOverlay.length > 0) {
				// If custom confirm is open, prevent clicks outside its modal from closing product modal natively
				if (!$(e.target).closest('.o100-confirm-modal').length) {
					e.stopPropagation();
					e.preventDefault();
				}
			} else {
				// Panel is open. If click is outside panel AND outside trigger, intercept it
				if (!$(e.target).closest('.o100-textarea-slide-panel').length && !$(e.target).closest('.o100-textarea-trigger').length) {
					e.stopPropagation();
					e.preventDefault();
					attemptClosePanel($openPanel);
				}
			}
		}
	}, true); // use capture phase
});

/* TS: 20260325224507 */

/* TS: 20260508022905 */

/* TS: 20260510221958 */
