<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_CMB2_Field_Holidays {

	public function __construct() {
		add_action( 'cmb2_render_o100_holidays', array( $this, 'render_field' ), 10, 5 );
		add_filter( 'cmb2_sanitize_o100_holidays', array( $this, 'sanitize_field' ), 10, 5 );
		add_filter( 'cmb2_escape_o100_holidays', array( $this, 'escape_field' ), 10, 5 );
		add_action( 'admin_footer', array( $this, 'enqueue_assets' ), 99 );
	}

	public function escape_field( $override_value, $value, $object_id, $field_args, $escape_object ) {
		return $value;
	}

	public function sanitize_field( $override_value, $value, $object_id, $field_args, $sanitize_obj ) {
		// CMB2 passes the raw value array for this specific field in $value
		if (!is_array($value)) {
			return array();
		}

		$clean_slots = array();
		foreach ($value as $slot) {
			if (!is_array($slot)) continue;
			
			$start = isset($slot['start']) ? sanitize_text_field($slot['start']) : '';
			$end = isset($slot['end']) ? sanitize_text_field($slot['end']) : '';
			$annual = isset($slot['annual']) ? sanitize_text_field($slot['annual']) : '0';
			$reason = isset($slot['reason']) ? sanitize_text_field($slot['reason']) : '';
			$reason_custom = isset($slot['reason_custom']) ? sanitize_text_field($slot['reason_custom']) : '';
			
			if (empty($start) && empty($end)) continue;
			
			$clean_slots[] = array(
				'start' => $start,
				'end' => $end,
				'annual' => $annual,
				'reason' => $reason,
				'reason_custom' => $reason_custom
			);
		}
		
		return $clean_slots;
	}

	public function render_field( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		$name = $field_type_object->_name();
		
		// Bypass CMB2 escaping which destroys multidimensional arrays
		$raw_value = $field_type_object->field->value();
		$slots = is_array($raw_value) ? $raw_value : array();
		
		$reasons = array(
			'Experiencing unusually high order volume. Online ordering is paused to ensure quality.' => esc_html__('High order volume (Busy)', 'order100'),
			'Temporarily closed due to equipment maintenance.' => esc_html__('Equipment maintenance', 'order100'),
			'We are sold out for the day. Thank you for your support!' => esc_html__('Sold out for the day', 'order100'),
			'Closed for a private event / holiday.' => esc_html__('Private event / Holiday', 'order100'),
			'Closed for a statutory holiday. We will be back soon!' => esc_html__('Statutory Holiday', 'order100'),
			'custom' => esc_html__('Custom Reason...', 'order100'),
		);
		
		echo '<div class="o100-holidays-wrapper" data-field-name="' . esc_attr($name) . '">';
		
		echo '<div class="o100-holidays-container">';
		
		$idx = 0;
		if (empty($slots)) {
			// Print one empty row by default
			$this->render_row($name, $idx, '', '', '0', 'Closed for a statutory holiday. We will be back soon!', '', $reasons);
			$idx++;
		} else {
			foreach ($slots as $slot) {
				$annual = isset($slot['annual']) ? $slot['annual'] : '0';
				$reason_custom = isset($slot['reason_custom']) ? $slot['reason_custom'] : '';
				$this->render_row($name, $idx, $slot['start'], $slot['end'], $annual, $slot['reason'], $reason_custom, $reasons);
				$idx++;
			}
		}
		
		echo '</div>'; // .o100-holidays-container
		
		echo '<div class="o100-holidays-add-wrap">';
		echo '<button type="button" class="o100-holiday-add-btn"><span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__('Add Dates', 'order100') . '</button>';
		echo '</div>';
		
		// Template for JS
		echo '<script type="text/template" class="o100-holiday-template-' . esc_attr($name) . '">';
		$this->render_row($name, '{INDEX}', '', '', '0', 'Closed for a statutory holiday. We will be back soon!', '', $reasons);
		echo '</script>';
		
		echo '</div>'; // .o100-holidays-wrapper
	}

	private function render_row($name, $index, $start, $end, $annual, $selected_reason, $reason_custom, $reasons) {
		$name_attr = $name . '[' . $index . ']';
		
		echo '<div class="o100-holiday-row">';
		
		echo '<div class="o100-holiday-dates">';
		echo '<input type="text" name="' . esc_attr($name_attr) . '[start]" value="' . esc_attr($start) . '" class="o100-holiday-date-input o100-flatpickr" placeholder="Start Date" autocomplete="off" />';
		echo '<span class="o100-holiday-to">to</span>';
		echo '<input type="text" name="' . esc_attr($name_attr) . '[end]" value="' . esc_attr($end) . '" class="o100-holiday-date-input o100-flatpickr" placeholder="End Date" autocomplete="off" />';
		echo '</div>';
		
		echo '<div class="o100-holiday-annual-wrap">';
		echo '<label class="o100-annual-toggle">';
		echo '<input type="checkbox" name="' . esc_attr($name_attr) . '[annual]" value="1" ' . checked($annual, '1', false) . ' />';
		echo '<span class="o100-annual-slider"></span>';
		echo '<span class="o100-annual-label">Annual</span>';
		echo '</label>';
		echo '</div>';
		
		echo '<div class="o100-holiday-reason-wrap" style="display:flex; gap:8px; align-items:center;">';
		echo '<select name="' . esc_attr($name_attr) . '[reason]" class="o100-holiday-reason-select">';
		foreach ($reasons as $val => $label) {
			$selected = selected($selected_reason, $val, false);
			echo '<option value="' . esc_attr($val) . '" ' . $selected . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		
		$custom_display = ($selected_reason === 'custom') ? 'block' : 'none';
		echo '<input type="text" name="' . esc_attr($name_attr) . '[reason_custom]" value="' . esc_attr($reason_custom) . '" class="o100-holiday-reason-custom-input" placeholder="Type custom reason..." style="display:' . $custom_display . ';" />';
		echo '</div>';
		
		echo '<button type="button" class="o100-holiday-remove"><span class="dashicons dashicons-trash"></span></button>';
		
		echo '</div>';
	}

	public function enqueue_assets() {
		// Only enqueue once
		static $enqueued = false;
		if ($enqueued) return;
		$enqueued = true;
		
		wp_enqueue_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13' );
		wp_enqueue_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true );
		
		?>
		<style>
			/* Prevent UI flashing on load */
			.cmb-row.cmb-type-o100-holidays {
				display: none;
			}
			.cmb-row.cmb-type-o100-holidays.o100-holidays-ready {
				display: block;
			}
			
			.o100-holidays-wrapper {
				width: 100%;
				max-width: 100%;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.o100-holidays-container {
				display: flex;
				flex-direction: column;
				gap: 12px;
				margin-bottom: 15px;
			}
			.o100-holiday-row {
				display: flex;
				align-items: center;
				flex-wrap: wrap;
				gap: 15px;
				background: #fff;
				border: 1px solid #cbd5e1;
				padding: 12px 16px;
				border-radius: 8px;
				box-shadow: 0 1px 2px rgba(0,0,0,0.02);
				transition: all 0.2s;
			}
			.o100-holiday-row:hover {
				border-color: #94a3b8;
				box-shadow: 0 2px 4px rgba(0,0,0,0.04);
			}
			.o100-holiday-dates {
				display: flex;
				align-items: center;
				gap: 10px;
				flex-shrink: 0;
			}
			.o100-holiday-date-input {
				height: 38px !important;
				border: 1px solid #cbd5e1 !important;
				border-radius: 6px !important;
				padding: 0 12px !important;
				font-size: 14px !important;
				color: #334155 !important;
				box-shadow: none !important;
				background: #fff !important;
				min-width: 140px;
			}
			.o100-holiday-date-input:focus {
				border-color: #3b82f6 !important;
				box-shadow: 0 0 0 1px #3b82f6 !important;
			}
			.o100-holiday-to {
				color: #64748b;
				font-size: 14px;
				font-weight: 500;
			}
			.o100-holiday-annual-wrap {
				display: flex;
				align-items: center;
				justify-content: center;
				padding: 0 10px;
				border-left: 1px solid #e2e8f0;
				border-right: 1px solid #e2e8f0;
			}
			.o100-annual-toggle {
				display: flex;
				align-items: center;
				gap: 8px;
				cursor: pointer;
				position: relative;
			}
			.o100-annual-toggle input {
				opacity: 0;
				position: absolute;
				width: 0;
				height: 0;
			}
			.o100-annual-slider {
				position: relative;
				width: 36px;
				height: 20px;
				background-color: #cbd5e1;
				border-radius: 20px;
				transition: .3s;
			}
			.o100-annual-slider:before {
				content: "";
				position: absolute;
				height: 16px;
				width: 16px;
				left: 2px;
				bottom: 2px;
				background-color: white;
				border-radius: 50%;
				transition: .3s;
			}
			.o100-annual-toggle input:checked + .o100-annual-slider {
				background-color: #3b82f6;
			}
			.o100-annual-toggle input:checked + .o100-annual-slider:before {
				transform: translateX(16px);
			}
			.o100-annual-label {
				font-size: 13px;
				color: #475569;
				font-weight: 500;
			}
			.o100-holiday-reason-wrap {
				flex: 1 1 200px;
				min-width: 200px;
			}
			.o100-holiday-reason-select {
				flex: 1 1 120px;
				min-width: 100px !important;
				height: 38px !important;
				border: 1px solid #cbd5e1 !important;
				border-radius: 6px !important;
				padding: 0 30px 0 12px !important;
				font-size: 14px !important;
				color: #334155 !important;
				background: #f8fafc url('data:image/svg+xml;utf8,<svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L9 1" stroke="%2364748B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>') no-repeat right 12px center !important;
				appearance: none !important;
				-webkit-appearance: none !important;
			}
			.o100-holiday-reason-select:focus {
				border-color: #3b82f6 !important;
				background-color: #fff !important;
			}
			.o100-holiday-reason-custom-input {
				flex: 2 1 140px;
				min-width: 120px !important;
				height: 38px !important;
				border: 1px solid #cbd5e1 !important;
				border-radius: 6px !important;
				padding: 0 12px !important;
				font-size: 14px !important;
				color: #334155 !important;
				background: #fff !important;
				box-shadow: none !important;
			}
			.o100-holiday-reason-custom-input:focus {
				border-color: #3b82f6 !important;
				box-shadow: 0 0 0 1px #3b82f6 !important;
			}
			.o100-holiday-remove {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 36px;
				height: 36px;
				border: 1px solid #fca5a5;
				background: #fff;
				color: #ef4444;
				border-radius: 6px;
				cursor: pointer;
				transition: 0.2s;
				flex-shrink: 0;
			}
			.o100-holiday-remove:hover {
				background: #fef2f2;
				border-color: #f87171;
				color: #dc2626;
			}
			.o100-holiday-remove .dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
			}
			.o100-holiday-add-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				background: #fff;
				border: 1px dashed #cbd5e1;
				color: #3b82f6;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: 0.2s;
			}
			.o100-holiday-add-btn:hover {
				background: #f0f9ff;
				border-color: #bfdbfe;
			}
			.o100-holiday-add-btn .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}
			@media (max-width: 768px) {
				.o100-holiday-row {
					flex-direction: column;
					align-items: stretch;
				}
				.o100-holiday-dates {
					justify-content: space-between;
				}
				.o100-holiday-date-input {
					flex: 1;
					min-width: 0;
				}
				.o100-holiday-remove {
					align-self: flex-end;
				}
			}
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Explicitly initialize Flatpickr on all inputs
				function initO100Datepickers($context) {
					if (typeof flatpickr !== 'undefined') {
						flatpickr($context.find('.o100-flatpickr').toArray(), {
							dateFormat: "Y-m-d",
							allowInput: true
						});
					}
				}
				
				// Initialize on existing rows
				initO100Datepickers($('.o100-holidays-wrapper'));

				// Add new row
				$(document).on('click', '.o100-holiday-add-btn', function() {
					var $wrapper = $(this).closest('.o100-holidays-wrapper');
					var fieldName = $wrapper.data('field-name');
					var $container = $wrapper.find('.o100-holidays-container');
					
					var maxIndex = -1;
					$container.find('.o100-holiday-row').each(function() {
						var match = $(this).find('input').attr('name').match(/\[(\d+)\]/);
						if (match && parseInt(match[1]) > maxIndex) {
							maxIndex = parseInt(match[1]);
						}
					});
					var newIndex = maxIndex + 1;
					
					var template = $('.o100-holiday-template-' + fieldName).html();
					template = template.replace(/{INDEX}/g, newIndex);
					
					var $newRow = $(template);
					$container.append($newRow);
					
					// Manually initialize datepicker on the new row
					initO100Datepickers($newRow);
				});

				// Handle custom reason toggle
				$(document).on('change', '.o100-holiday-reason-select', function() {
					var val = $(this).val();
					var $customInput = $(this).siblings('.o100-holiday-reason-custom-input');
					if (val === 'custom') {
						$customInput.show().focus();
					} else {
						$customInput.hide().val('');
					}
				});

				// Remove row
				$(document).on('click', '.o100-holiday-remove', function() {
					var $row = $(this).closest('.o100-holiday-row');
					var $container = $row.closest('.o100-holidays-container');
					
					$row.remove();
					
					// Add an empty row if all are removed
					if ($container.children().length === 0) {
						var $wrapper = $container.closest('.o100-holidays-wrapper');
						$wrapper.find('.o100-holiday-add-btn').trigger('click');
					}
				});
				
				// Handle visibility toggle based on the main checkbox
				function toggleHolidaysVisibility(animate) {
					var isChecked = $("#o100_enable_holidays").is(":checked");
					var $wrapper = $(".o100-holidays-wrapper").closest(".cmb-row");
					
					if (isChecked) {
						if (animate) {
							$wrapper.slideDown(200).addClass('o100-holidays-ready');
						} else {
							$wrapper.show().addClass('o100-holidays-ready');
						}
					} else {
						if (animate) {
							$wrapper.slideUp(200);
						} else {
							$wrapper.hide().removeClass('o100-holidays-ready');
						}
					}
				}
				
				// Initial check (no animation to prevent flash)
				toggleHolidaysVisibility(false);
				
				// Listen for changes (with animation)
				$("#o100_enable_holidays").on("change", function() {
					toggleHolidaysVisibility(true);
				});
			});
		</script>
		<?php
	}
}
new O100_CMB2_Field_Holidays();


