/**
 * Item Modifiers — Admin JS
 * Handles dynamic show/hide of fields based on option type selection.
 *
 * Uses addClass/removeClass('o100-hidden') instead of jQuery .show()/.hide()
 * because the CSS uses #o100_addon_options specificity which overrides inline styles.
 *
 * @package Order100
 */

jQuery(document).ready(function($) {
	'use strict';

	// Initialize selectWoo/select2 on the modifier multiselect dropdown
	var selectFn = $.fn.selectWoo ? 'selectWoo' : ($.fn.select2 ? 'select2' : null);
	if (selectFn && $('.o100-modifier-multiselect').length) {
		$('.o100-modifier-multiselect')[selectFn]({
			placeholder: 'Select modifiers...',
			allowClear: true,
			width: '100%'
		});
	}

	/**
	 * Toggle fields based on the selected Option Type.
	 */
	function toggleAddonFields($groupRow, optionType) {
		var $minOpRow      = $groupRow.find('.o100-addon-min');
		var $maxOpRow      = $groupRow.find('.o100-addon-max');
		var $enbImgRow     = $groupRow.find('.o100-addon-img');
		var $enbQtyRow     = $groupRow.find('.o100-addon-qty');
		var $minOpqtyRow   = $groupRow.find('.o100-addon-minqty');
		var $maxOpqtyRow   = $groupRow.find('.o100-addon-maxqty');
		var $choicesRow    = $groupRow.find('.o100-addon-choices');
		var $priceTitleRow = $groupRow.find('.o100-addon-pricetype-title');
		var $priceTypeRow  = $groupRow.find('.o100-addon-pricetype');
		var $priceRow      = $groupRow.find('.o100-addon-price');

		// Hide all conditional fields first
		$minOpRow.addClass('o100-hidden');
		$maxOpRow.addClass('o100-hidden');
		$enbImgRow.addClass('o100-hidden');
		$enbQtyRow.addClass('o100-hidden');
		$minOpqtyRow.addClass('o100-hidden');
		$maxOpqtyRow.addClass('o100-hidden');
		$choicesRow.addClass('o100-hidden');
		$priceTitleRow.addClass('o100-hidden');
		$priceTypeRow.addClass('o100-hidden');
		$priceRow.addClass('o100-hidden');

		// Show fields based on type
		switch (optionType) {
			case '': // Checkboxes
				$minOpRow.removeClass('o100-hidden');
				$maxOpRow.removeClass('o100-hidden');
				$enbImgRow.removeClass('o100-hidden');
				$enbQtyRow.removeClass('o100-hidden');
				$minOpqtyRow.removeClass('o100-hidden');
				$maxOpqtyRow.removeClass('o100-hidden');
				$choicesRow.removeClass('o100-hidden');
				break;

			case 'radio': // Radio Buttons
				$enbImgRow.removeClass('o100-hidden');
				$enbQtyRow.removeClass('o100-hidden');
				$choicesRow.removeClass('o100-hidden');
				break;

			case 'select': // Dropdown
				$choicesRow.removeClass('o100-hidden');
				break;

			case 'text':
			case 'textarea':
			case 'quantity':
				$priceTitleRow.removeClass('o100-hidden');
				$priceTypeRow.removeClass('o100-hidden');
				$priceRow.removeClass('o100-hidden');
				break;
		}
	}

	/**
	 * Toggle Image and Min/Max columns inside the Options repeatable field.
	 */
	function toggleImageQtyFields($groupRow) {
		var imgVal = $groupRow.find('.o100-addon-img select').val();
		var qtyVal = $groupRow.find('.o100-addon-qty select').val();

		if (imgVal === 'yes') {
			$groupRow.addClass('o100-show-img');
		} else {
			$groupRow.removeClass('o100-show-img');
		}

		if (qtyVal === 'yes') {
			$groupRow.addClass('o100-show-qty');
		} else {
			$groupRow.removeClass('o100-show-qty');
		}
	}

	/**
	 * Initialize all option groups on load.
	 */
	function initAddonGroups() {
		$('#o100_addon_options .cmb-repeatable-grouping').each(function() {
			var $groupRow = $(this);
			var $typeSelect = $groupRow.find('.o100-addon-type select');
			
			if ($typeSelect.length) {
				toggleAddonFields($groupRow, $typeSelect.val());
			}
			
			toggleImageQtyFields($groupRow);
		});
	}

	/**
	 * Inject delete icons into group title bars.
	 */
	function injectGroupDeleteButtons() {
		$('#o100_addon_options .cmb-repeatable-grouping').each(function() {
			var $groupRow = $(this);
			var $title = $groupRow.find('.cmb-group-title');
			
			// Skip if already injected
			if ($title.find('.o100-group-delete-btn').length) return;
			
			var $btn = $('<button type="button" class="o100-group-delete-btn dashicons dashicons-trash" title="Delete this group"></button>');
			$btn.on('click', function(e) {
				e.stopPropagation(); // Don't trigger collapse
				if (confirm('Delete this option group?')) {
					// Trigger CMB2's native remove button (it's the element itself, not a child)
					var $removeBtn = $groupRow.find('.cmb-remove-group-row');
					if ($removeBtn.length) {
						$removeBtn.trigger('click');
					} else {
						// Fallback: just remove the DOM element
						$groupRow.remove();
					}
				}
			});
			$title.append($btn);
		});
	}

	// Run on initial load
	initAddonGroups();
	injectGroupDeleteButtons();

	// Run when the option type is changed
	$(document).on('change', '.o100-addon-type select', function() {
		var $groupRow = $(this).closest('.cmb-repeatable-grouping');
		toggleAddonFields($groupRow, $(this).val());
	});

	// Run when Enable Images or Enable Quantity Selectors change
	$(document).on('change', '.o100-addon-img select, .o100-addon-qty select', function() {
		var $groupRow = $(this).closest('.cmb-repeatable-grouping');
		toggleImageQtyFields($groupRow);
	});

	// Dynamic group title updating based on the name field
	$(document).on('keyup', '.o100-addon-name input', function() {
		var $groupRow = $(this).closest('.cmb-repeatable-grouping');
		var nameVal = $(this).val();
		var $titleSpan = $groupRow.find('.cmb-group-title');
		
		if (nameVal) {
			$titleSpan.text(nameVal);
		} else {
			var rowNum = $groupRow.index() + 1;
			$titleSpan.text('Option ' + rowNum);
		}
	});

	/**
	 * Delete a choice row in the repeatable Options field.
	 */
	$(document).on('click', '.o100-remove-choice-row', function(e) {
		e.preventDefault();
		var $row = $(this).closest('.cmb-repeat-row');
		if ($row.length) {
			// CMB2 repeatable row
			$row.find('.cmb-remove-row-button').click();
		} else {
			// Fallback: just remove the choice-row div
			$(this).closest('.o100-addon-choice-row').remove();
		}
	});

	/**
	 * Toggle Apply To fields
	 */
	function toggleApplyToFields($groupRow, applyTo) {
		var $catRow = $groupRow.find('.cmb2-id--category-ids');
		var $prodRow = $groupRow.find('.cmb2-id--product-ids');

		if (applyTo === 'categories') {
			$catRow.show();
			$prodRow.hide();
		} else if (applyTo === 'products') {
			$catRow.hide();
			$prodRow.show();
		} else {
			$catRow.hide();
			$prodRow.hide();
		}
	}

	function initApplyToGroups() {
		$('#o100_addon_options .cmb-repeatable-grouping').each(function() {
			var $groupRow = $(this);
			var $applyToSelect = $groupRow.find('.o100-addon-applyto select');
			
			if ($applyToSelect.length) {
				toggleApplyToFields($groupRow, $applyToSelect.val());
			}
		});
	}

	initApplyToGroups();

	$(document).on('change', '.o100-addon-applyto select', function() {
		var $groupRow = $(this).closest('.cmb-repeatable-grouping');
		toggleApplyToFields($groupRow, $(this).val());
	});

	// Re-run on new row added
	$(document).on('cmb2_add_row', function(e) {
		initAddonGroups();
		initApplyToGroups();
		injectGroupDeleteButtons();
	});

});

