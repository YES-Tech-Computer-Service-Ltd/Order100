<?php
/**
 * Custom CMB2 Field Type: Open/Close Time
 * 
 * Fully custom implementation bypassing CMB2's repeatable logic to achieve
 * a clean, WPCafe-style schedule layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_CMB2_Field_OpenClose {

	private static $assets_enqueued = false;
	/**
	 * Cache of raw POST data. CMB2 clears $_POST['o100_store_hours'] after processing
	 * openclose fields, so by the time the timeslot generator runs, the data is gone.
	 * We snapshot it on first access.
	 */
	private static $cached_post_data = null;

	/**
	 * Get the raw POST data for o100_store_hours, cached on first call.
	 * The snapshot is taken in admin_init (priority 1) BEFORE CMB2 touches $_POST.
	 */
	private static function get_post_data() {
		if ( self::$cached_post_data === null ) {
			// Fallback: try reading now (in case admin_init snapshot missed)
			self::snapshot_post_data();
		}
		return self::$cached_post_data;
	}

	/**
	 * Snapshot $_POST['o100_store_hours'] before CMB2 consumes it.
	 */
	public static function snapshot_post_data() {
		if ( self::$cached_post_data !== null ) {
			return; // Already captured
		}
		// CMB2 Options Pages put fields directly in $_POST root level,
		// NOT nested under $_POST['o100_store_hours'].
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['object_id']) && $_POST['object_id'] === 'o100_store_hours' ) {
			self::$cached_post_data = wp_unslash($_POST);
		} else {
			self::$cached_post_data = array();
		}
	}

	public function __construct() {
		// Snapshot POST data ASAP, before CMB2 processes and clears it
		add_action( 'admin_init', array( __CLASS__, 'snapshot_post_data' ), 1 );
		add_action( 'cmb2_render_openclose', array( $this, 'render_field' ), 10, 5 );
		add_filter( 'cmb2_sanitize_openclose', array( $this, 'sanitize_field' ), 10, 5 );
		add_filter( 'cmb2_escape_openclose', array( $this, 'escape_field' ), 10, 5 );
		add_action( 'cmb2_render_o100_generated_timeslots', array( $this, 'render_generated_timeslots_field' ), 10, 5 );
		add_filter( 'cmb2_sanitize_o100_generated_timeslots', array( $this, 'sanitize_generated_timeslots_field' ), 10, 5 );
		add_action( 'wp_ajax_o100_generate_timeslots', array( $this, 'ajax_generate_timeslots' ) );
		add_action( 'admin_footer', array( $this, 'enqueue_assets' ), 99 );
	}

	/**
	 * Force the CSS to be enqueued even if the field wasn't explicitly rendered.
	 */
	public static function force_enqueue_assets() {
		self::$assets_enqueued = true;
	}

	/**
	 * Render the custom WPCafe style field.
	 */
	public function render_field( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		self::$assets_enqueued = true;
		$name = $field_type_object->_name();
		// Bypass CMB2's escaping because its default `esc_attr` destroys multi-dimensional arrays
		$raw_value = $field_type_object->field->value();
		
		$slots = is_array( $raw_value ) ? $raw_value : array();
		
		// Determine if active
		$is_active = false;
		
		if ( $raw_value === 'closed' || (is_array($raw_value) && current($raw_value) === 'closed') ) {
			// Explicitly closed by user
			$slots = array( array('open-time' => '', 'close-time' => '') );
			$is_active = false;
		} elseif ( ! is_array( $raw_value ) || empty( $raw_value ) ) {
			// First time load, default to enabled and 10:00 to 21:00
			$slots = array( array('open-time' => '10:00', 'close-time' => '21:00') );
			$is_active = true;
		} else {
			// Convert raw POST data to standard format if sanitization was bypassed
			foreach ( $slots as $k => $slot ) {
				if (isset($slot['open-hr']) && !isset($slot['open-time'])) {
					$slots[$k]['open-time'] = ($slot['open-hr'] !== '' && isset($slot['open-min']) && $slot['open-min'] !== '') ? $slot['open-hr'] . ':' . $slot['open-min'] : '';
				}
				if (isset($slot['close-hr']) && !isset($slot['close-time'])) {
					$slots[$k]['close-time'] = ($slot['close-hr'] !== '' && isset($slot['close-min']) && $slot['close-min'] !== '') ? $slot['close-hr'] . ':' . $slot['close-min'] : '';
				}
			}

			// Find if there is any valid slot
			foreach ( $slots as $slot ) {
				if ( ! empty( $slot['open-time'] ) || ! empty( $slot['close-time'] ) ) {
					$is_active = true;
					break;
				}
			}
			
			// Even if there's no status key, if we found valid slots, the day is active
			if ($is_active) {
				$status = 'open';
			} else {
				$status = 'closed';
			}
		}
		
		if (empty($slots)) {
			$slots = array( array('open-time' => '', 'close-time' => '') );
		}
		
		$day_name = $field->args('name');

		// Generate time options
		$render_hours = function($selected_hr = '') {
			$html = '<option value="">--</option>';
			for ($i = 0; $i < 24; $i++) {
				$val = sprintf('%02d', $i);
				$html .= sprintf('<option value="%s" %s>%s</option>', $val, selected($selected_hr, $val, false), $val);
			}
			if ($selected_hr !== '' && intval($selected_hr) >= 24) {
				$html .= sprintf('<option value="%s" selected="selected">%s</option>', esc_attr($selected_hr), esc_html($selected_hr));
			}
			return $html;
		};

		$render_mins = function($selected_min = '') {
			$html = '<option value="">--</option>';
			$mins = array('00', '15', '30', '45');
			$found = false;
			foreach ($mins as $val) {
				$is_selected = selected($selected_min, $val, false);
				if ($is_selected) $found = true;
				$html .= sprintf('<option value="%s" %s>%s</option>', $val, $is_selected, $val);
			}
			if (!$found && $selected_min !== '') {
				$html .= sprintf('<option value="%s" selected="selected">%s</option>', esc_attr($selected_min), esc_html($selected_min));
			}
			return $html;
		};
		?>
		<div class="o100-custom-schedule-wrapper" data-field-name="<?php echo esc_attr($name); ?>">
			<!-- Left Column: Toggle & Day Name -->
			<div class="o100-schedule-left">
				<label class="o100-switch">
					<input type="checkbox" class="o100-day-toggle" <?php checked($is_active, true); ?>>
					<span class="o100-slider"></span>
				</label>
				<span class="o100-day-name"><?php echo esc_html($day_name); ?></span>
			</div>
			
			<!-- Right Column: Slots -->
			<div class="o100-schedule-right">
				<div class="o100-closed-pill" style="<?php echo $is_active ? 'display:none;' : 'display:inline-flex;'; ?>">Closed</div>
				
				<div class="o100-slots-container" style="<?php echo $is_active ? 'display:flex;' : 'display:none;'; ?>">
					<?php foreach ($slots as $index => $slot) : 
						// Split existing "HH:MM" into hours and minutes
						$open_hr = ''; $open_min = '';
						if (!empty($slot['open-time'])) {
							$parts = explode(':', $slot['open-time']);
							$open_hr = isset($parts[0]) ? $parts[0] : '';
							$open_min = isset($parts[1]) ? $parts[1] : '';
						}
						$close_hr = ''; $close_min = '';
						if (!empty($slot['close-time'])) {
							$parts = explode(':', $slot['close-time']);
							$close_hr = isset($parts[0]) ? $parts[0] : '';
							$close_min = isset($parts[1]) ? $parts[1] : '';
						}
					?>
						<div class="o100-time-slot-row">
							<div class="o100-time-split">
								<select name="<?php echo esc_attr($name); ?>[<?php echo $index; ?>][open-hr]" class="o100-time-select">
									<?php echo $render_hours($open_hr); ?>
								</select>
								<span class="o100-split-colon">:</span>
								<select name="<?php echo esc_attr($name); ?>[<?php echo $index; ?>][open-min]" class="o100-time-select">
									<?php echo $render_mins($open_min); ?>
								</select>
							</div>
							
							<span class="o100-time-separator">to</span>
							
							<div class="o100-time-split">
								<select name="<?php echo esc_attr($name); ?>[<?php echo $index; ?>][close-hr]" class="o100-time-select">
									<?php echo $render_hours($close_hr); ?>
								</select>
								<span class="o100-split-colon">:</span>
								<select name="<?php echo esc_attr($name); ?>[<?php echo $index; ?>][close-min]" class="o100-time-select">
									<?php echo $render_mins($close_min); ?>
								</select>
							</div>
							
							<div class="o100-schedule-actions">
								<?php if ( $index === 0 ) : ?>
									<button type="button" class="o100-copy-to-all-btn" data-tooltip="<?php echo esc_attr__('Apply to all active days', 'order100'); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
								<?php else : ?>
									<button type="button" class="o100-remove-slot-btn" title="Remove">
										<span class="dashicons dashicons-trash"></span>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				
				<div class="o100-add-slot-wrap" style="<?php echo $is_active ? 'display:flex;' : 'display:none;'; ?>">
					<button type="button" class="o100-add-slot-btn">
						<span class="dashicons dashicons-plus"></span> Add Slot
					</button>
				</div>
			</div>
		</div>

		<!-- Template for JS cloning -->
		<script type="text/template" class="o100-slot-template-<?php echo esc_attr($name); ?>">
			<div class="o100-time-slot-row">
				<div class="o100-time-split">
					<select name="<?php echo esc_attr($name); ?>[{INDEX}][open-hr]" class="o100-time-select">
						<?php echo $render_hours('10'); ?>
					</select>
					<span class="o100-split-colon">:</span>
					<select name="<?php echo esc_attr($name); ?>[{INDEX}][open-min]" class="o100-time-select">
						<?php echo $render_mins('00'); ?>
					</select>
				</div>
				
				<span class="o100-time-separator">to</span>
				
				<div class="o100-time-split">
					<select name="<?php echo esc_attr($name); ?>[{INDEX}][close-hr]" class="o100-time-select">
						<?php echo $render_hours('21'); ?>
					</select>
					<span class="o100-split-colon">:</span>
					<select name="<?php echo esc_attr($name); ?>[{INDEX}][close-min]" class="o100-time-select">
						<?php echo $render_mins('00'); ?>
					</select>
				</div>
				
				<div class="o100-schedule-actions">
					<button type="button" class="o100-remove-slot-btn" title="Remove"><span class="dashicons dashicons-trash"></span></button>
				</div>
			</div>
		</script>
		<?php
	}

	/**
	 * Sanitize field before saving.
	 */
	public function sanitize_field( $override_value, $value, $object_id, $field_args, $sanitize_object ) {
		$field_id = $sanitize_object->field->id();

		// ── OVERRIDE SWITCH PROTECTION ──
		// When an Override master switch is OFF, the fallback_override_fields_to_global filter
		// injects Global data into the hidden Override HTML. On save, those hidden selects submit
		// Global data as Override data, silently overwriting the real Override values.
		// Fix: if the override switch is OFF for this field's group, preserve the existing DB value.
		$post_data = self::get_post_data();
		if ( strpos($field_id, 'o100_delivery_') === 0 && strpos($field_id, '_opcl_time') !== false ) {
			$switch_on = !empty($post_data['o100_delivery_override_schedule']) && $post_data['o100_delivery_override_schedule'] === 'on';
			if ( ! $switch_on ) {
				// Switch is OFF – don't touch DB. Return existing value.
				$existing = get_option('o100_store_hours', array());
				return isset($existing[$field_id]) ? $existing[$field_id] : 'closed';
			}
		}
		if ( strpos($field_id, 'o100_pickup_') === 0 && strpos($field_id, '_opcl_time') !== false ) {
			$switch_on = !empty($post_data['o100_pickup_override_schedule']) && $post_data['o100_pickup_override_schedule'] === 'on';
			if ( ! $switch_on ) {
				$existing = get_option('o100_store_hours', array());
				return isset($existing[$field_id]) ? $existing[$field_id] : 'closed';
			}
		}

		// ── BYPASS CMB2's reset() ──
		// CMB2 calls reset($value) on non-repeatable array fields, stripping our outer array.
		// Read directly from $_POST to get the full, intact data.
		$raw_value = null;
		if ( isset($post_data[$field_id]) ) {
			$raw_value = $post_data[$field_id];
		} elseif ( isset($_POST[$field_id]) ) {
			$raw_value = wp_unslash($_POST[$field_id]);
		}

		if ( $raw_value !== null ) {
			$value = $raw_value;
		}

		// If the form submitted an array, parse it.
		// If CMB2 passed us something else, or if the user removed all slots, we treat it as closed.
		if ( ! is_array( $value ) || empty( $value ) ) {
			return 'closed';
		}
		$clean_slots = array();
		foreach ($value as $slot) {
			if (!is_array($slot)) continue; // safeguard against malformed post data

			$open_hr = isset($slot['open-hr']) ? sanitize_text_field($slot['open-hr']) : '';
			$open_min = isset($slot['open-min']) ? sanitize_text_field($slot['open-min']) : '';
			$close_hr = isset($slot['close-hr']) ? sanitize_text_field($slot['close-hr']) : '';
			$close_min = isset($slot['close-min']) ? sanitize_text_field($slot['close-min']) : '';
			
			$open_time = ($open_hr !== '' && $open_min !== '') ? $open_hr . ':' . $open_min : '';
			$close_time = ($close_hr !== '' && $close_min !== '') ? $close_hr . ':' . $close_min : '';

			if (!empty($open_time) || !empty($close_time)) {
				$clean_slots[] = array(
					'open-time' => $open_time,
					'close-time' => $close_time,
				);
			}
		}
		
		return empty($clean_slots) ? 'closed' : $clean_slots;
	}

	/**
	 * Bypass CMB2's default escaping to prevent multi-dimensional arrays from turning into the string "Array".
	 */
	public function escape_field( $override_value, $value, $object_id, $field_args, $escape_object ) {
		// Just return the raw value, the escaping is handled during render/output.
		return $value;
	}

	/**
	 * Enqueue completely isolated CSS and JS.
	 */
	public function enqueue_assets() {
		if ( ! self::$assets_enqueued ) {
			return;
		}
		?>
		<style type="text/css">
			/* Hide native CMB2 labels and padding for these fields */
			.o100-weekday-schedule-field {
				padding: 0 !important;
				border-bottom: 1px solid #f1f5f9 !important;
				background: transparent !important;
			}
			.o100-weekday-schedule-field:last-child {
				border-bottom: none !important;
			}
			.o100-weekday-schedule-field .cmb-th {
				display: none !important; /* Hide CMB2 original label entirely */
			}
			.o100-weekday-schedule-field .cmb-td {
				padding: 0 !important;
				width: 100% !important;
				border: none !important;
			}

			/* Custom UI Container */
			.o100-custom-schedule-wrapper {
				display: flex;
				padding: 16px 0;
				align-items: flex-start;
			}

			/* Left: Toggle & Name */
			.o100-schedule-left {
				width: 140px;
				display: flex;
				align-items: center;
				gap: 15px;
				padding-top: 8px; /* align with the first input */
			}
			.o100-weekday-schedule-field {
				padding: 0 !important;
			}
			
			.o100-day-name {
				font-size: 14px;
				font-weight: 600;
				color: #0f172a;
			}

			/* Right: Slots container */
			.o100-schedule-right {
				flex: 1;
				display: flex;
				flex-wrap: wrap;
				align-items: flex-start;
				gap: 15px;
			}
			@media (max-width: 900px) {
				.o100-schedule-right {
					flex-direction: column;
				}
			}

			.o100-slots-container {
				display: flex;
				flex-direction: column;
				gap: 12px;
			}

			/* Single Slot Row */
			.o100-time-slot-row {
				display: flex;
				align-items: center;
				gap: 15px;
			}

			/* Time Inputs Split */
			.o100-time-split {
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.o100-split-colon {
				font-weight: 600;
				color: #475569;
			}
			.o100-time-select {
				appearance: none;
				-webkit-appearance: none;
				border: 1px solid #cbd5e1 !important;
				border-radius: 6px !important;
				padding: 0 24px 0 12px !important;
				height: 38px !important;
				font-size: 13px !important;
				color: #334155 !important;
				background: #fff url('data:image/svg+xml;utf8,<svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L9 1" stroke="%2364748B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>') no-repeat right 10px center !important;
				min-width: 70px !important;
				box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
			}
			.o100-time-separator {
				color: #64748b;
				font-size: 14px;
				font-weight: 500;
			}

			/* Action Icons */
			.o100-schedule-actions {
				display: flex;
				align-items: center;
				gap: 6px;
				margin-left: 8px;
			}
			.o100-schedule-actions button {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 32px;
				height: 32px;
				border-radius: 6px;
				background: white;
				border: 1px solid #cbd5e1;
				cursor: pointer;
				color: #64748b;
				transition: 0.2s;
			}
			.o100-schedule-actions button .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}
			.o100-copy-to-all-btn:hover {
				background: #f1f5f9;
				color: #0f172a;
			}
			.o100-remove-slot-btn {
				color: #ef4444 !important;
				border-color: #fca5a5 !important;
			}
			.o100-remove-slot-btn:hover {
				color: #dc2626 !important;
				background-color: #fef2f2 !important;
				border-color: #f87171 !important;
			}

			/* Tooltip for Copy Button */
			.o100-copy-to-all-btn[data-tooltip] {
				position: relative;
			}
			.o100-copy-to-all-btn[data-tooltip]::after {
				content: attr(data-tooltip);
				position: absolute;
				bottom: 100%;
				left: 50%;
				transform: translateX(-50%);
				background: #1e293b;
				color: #fff;
				padding: 5px 10px;
				border-radius: 4px;
				font-size: 11px;
				white-space: nowrap;
				opacity: 0;
				pointer-events: none;
				transition: opacity 0.2s, transform 0.2s;
				margin-bottom: 8px;
				z-index: 100;
			}
			.o100-copy-to-all-btn[data-tooltip]::before {
				content: '';
				position: absolute;
				bottom: 100%;
				left: 50%;
				transform: translateX(-50%);
				border-width: 5px;
				border-style: solid;
				border-color: #1e293b transparent transparent transparent;
				opacity: 0;
				pointer-events: none;
				transition: opacity 0.2s;
				margin-bottom: -2px;
				z-index: 100;
			}
			.o100-copy-to-all-btn[data-tooltip]:hover::after,
			.o100-copy-to-all-btn[data-tooltip]:hover::before {
				opacity: 1;
				transform: translateX(-50%) translateY(0);
			}

			/* Add Slot Button */
			.o100-add-slot-wrap {
				/* Drop margin so it flexes nicely */
				margin-top: 2px;
			}
			.o100-add-slot-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 6px 16px;
				border-radius: 6px;
				background: white;
				border: 1px dashed #cbd5e1;
				color: #3b82f6; /* Order100 Blue */
				font-weight: 600;
				font-size: 13px;
				cursor: pointer;
				transition: 0.2s;
				height: 34px;
			}
			.o100-add-slot-btn:hover {
				background: #eff6ff;
				border-color: #93c5fd;
			}
			.o100-add-slot-btn .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}

			/* Toggle Switch */
			.o100-day-name {
				font-size: 15px;
				font-weight: 600;
				color: #0f172a;
			}
			.o100-switch {
				position: relative;
				display: inline-block;
				width: 44px;
				height: 24px;
				margin: 0;
				flex-shrink: 0;
			}
			.o100-switch input {
				opacity: 0;
				width: 0;
				height: 0;
				position: absolute;
			}
			.o100-slider {
				position: absolute;
				cursor: pointer;
				top: 0; left: 0; right: 0; bottom: 0;
				background-color: #cbd5e1;
				transition: .3s;
				border-radius: 34px;
			}
			.o100-slider:before {
				position: absolute;
				content: "";
				height: 18px;
				width: 18px;
				left: 3px;
				bottom: 3px;
				background-color: white;
				transition: .3s;
				border-radius: 50%;
			}
			.o100-switch input:checked + .o100-slider {
				background-color: #3b82f6; /* Order100 Blue */
			}
			.o100-switch input:checked + .o100-slider:before {
				transform: translateX(20px);
			}

			/* Closed Pill */
			.o100-closed-pill {
				display: inline-flex;
				align-items: center;
				padding: 0 16px;
				border-radius: 6px;
				background: #f1f5f9;
				color: #64748b;
				font-size: 13px;
				font-weight: 600;
				height: 34px;
				border: 1px solid #e2e8f0;
				width: max-content;
			}
		</style>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				
				// 1. Toggle On/Off
				$(document).on('change', '.o100-day-toggle', function() {
					var $wrapper = $(this).closest('.o100-custom-schedule-wrapper');
					var isChecked = $(this).is(':checked');
					var $slotsContainer = $wrapper.find('.o100-slots-container');
					var $addWrap = $wrapper.find('.o100-add-slot-wrap');
					var $closedPill = $wrapper.find('.o100-closed-pill');
					
					if (isChecked) {
						$closedPill.hide();
						$slotsContainer.css('display', 'flex');
						$addWrap.css('display', 'flex');
						
						// If no slots exist, click add slot automatically
						if ($slotsContainer.children('.o100-time-slot-row').length === 0) {
							$wrapper.find('.o100-add-slot-btn').trigger('click');
						}
					} else {
						$slotsContainer.hide();
						$addWrap.hide();
						$closedPill.css('display', 'inline-flex');
						
						// Clear inputs when closed so they don't save
						$slotsContainer.find('select').val('');
						// Optional: remove all but the first slot to keep it tidy
						$slotsContainer.children('.o100-time-slot-row:not(:first)').remove();
					}
				});

				// 2. Add Slot
				$(document).on('click', '.o100-add-slot-btn', function() {
					var $wrapper = $(this).closest('.o100-custom-schedule-wrapper');
					var fieldName = $wrapper.data('field-name');
					var $slotsContainer = $wrapper.find('.o100-slots-container');
					
					// Find the highest index to avoid name collisions
					var maxIndex = -1;
					$slotsContainer.find('.o100-time-slot-row').each(function() {
						var match = $(this).find('select').attr('name').match(/\[(\d+)\]/);
						if (match && parseInt(match[1]) > maxIndex) {
							maxIndex = parseInt(match[1]);
						}
					});
					var newIndex = maxIndex + 1;
					
					// Get template HTML (escape the brackets in the fieldName for jQuery)
					var safeFieldName = fieldName.replace(/\[/g, '\\[').replace(/\]/g, '\\]');
					var templateHtml = $('.o100-slot-template-' + safeFieldName).html();
					// Replace {INDEX} placeholder
					templateHtml = templateHtml.replace(/{INDEX}/g, newIndex);
					
					$slotsContainer.append(templateHtml);
				});

				// 3. Remove Slot
				$(document).on('click', '.o100-remove-slot-btn', function() {
					var $wrapper = $(this).closest('.o100-custom-schedule-wrapper');
					var $slotsContainer = $wrapper.find('.o100-slots-container');
					
					$(this).closest('.o100-time-slot-row').remove();
					
					// If last slot removed, toggle off
					if ($slotsContainer.children('.o100-time-slot-row').length === 0) {
						$wrapper.find('.o100-day-toggle').prop('checked', false).trigger('change');
					}
				});

				// 4. Copy to all active days (Sync entire day's slots)
				$(document).on('click', '.o100-copy-to-all-btn', function() {
					var $sourceWrapper = $(this).closest('.o100-custom-schedule-wrapper');
					var $sourceRows = $sourceWrapper.find('.o100-time-slot-row');
					
					$('.o100-custom-schedule-wrapper').not($sourceWrapper).each(function() {
						var $targetWrapper = $(this);
						var isTargetActive = $targetWrapper.find('.o100-day-toggle').is(':checked');
						
						if (isTargetActive) {
							var $targetContainer = $targetWrapper.find('.o100-slots-container');
							
							// Clear existing slots
							$targetContainer.empty();
							
							// Clone each slot from source
							$sourceRows.each(function() {
								var $srcRow = $(this);
								var openHr = $srcRow.find('select[name*="[open-hr]"]').val();
								var openMin = $srcRow.find('select[name*="[open-min]"]').val();
								var closeHr = $srcRow.find('select[name*="[close-hr]"]').val();
								var closeMin = $srcRow.find('select[name*="[close-min]"]').val();
								
								// Trigger add slot to use the template
								$targetWrapper.find('.o100-add-slot-btn').trigger('click');
								
								var $newRow = $targetContainer.find('.o100-time-slot-row').last();
								$newRow.find('select[name*="[open-hr]"]').val(openHr);
								$newRow.find('select[name*="[open-min]"]').val(openMin);
								$newRow.find('select[name*="[close-hr]"]').val(closeHr);
								$newRow.find('select[name*="[close-min]"]').val(closeMin);
							});
						}
					});
					
					// Visual feedback
					var $btn = $(this);
					$btn.css('background', '#22c55e').css('color', 'white');
					setTimeout(function() {
						$btn.css('background', '').css('color', '');
					}, 500);
				});

			});
		</script>
		<?php
	}

	/**
	 * Sanitize generated timeslots field: just save JSON as-is.
	 * Timeslot generation is now triggered by the AJAX "Generate" button, not during save.
	 */
	public function sanitize_generated_timeslots_field( $override_value, $value, $object_id, $field_args, $sanitize_obj ) {
		$field_name = $sanitize_obj->field->id();

		// ── OVERRIDE SWITCH PROTECTION ──
		// When switch is OFF, force-clear override timeslots from DB.
		// CMB2 ignores empty string returns, so we must update_option directly.
		$post_data = self::get_post_data();
		if ( $field_name === 'o100_delivery_generated_timeslots' ) {
			$switch_on = !empty($post_data['o100_delivery_override_schedule']) && $post_data['o100_delivery_override_schedule'] === 'on';
			if ( ! $switch_on ) {
				$opts = get_option('o100_store_hours', array());
				unset($opts['o100_delivery_generated_timeslots']);
				update_option('o100_store_hours', $opts);
				return '';
			}
		}
		if ( $field_name === 'o100_pickup_generated_timeslots' ) {
			$switch_on = !empty($post_data['o100_pickup_override_schedule']) && $post_data['o100_pickup_override_schedule'] === 'on';
			if ( ! $switch_on ) {
				$opts = get_option('o100_store_hours', array());
				unset($opts['o100_pickup_generated_timeslots']);
				update_option('o100_store_hours', $opts);
				return '';
			}
		}

		// Just pass-through the JSON from the hidden input
		return wp_unslash( $value );
	}

	/**
	 * AJAX: Generate timeslots from schedule data.
	 * Saves schedule + interval + max_order to DB, generates timeslots, returns HTML.
	 */
	public function ajax_generate_timeslots() {
		check_ajax_referer( 'o100_generate_timeslots', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$prefix      = sanitize_text_field( $_POST['prefix'] ?? 'o100_' );
		$interval    = intval( $_POST['interval'] ?? 30 );
		$default_max = intval( $_POST['default_max'] ?? 4 );
		$schedule    = isset( $_POST['schedule'] ) ? wp_unslash( $_POST['schedule'] ) : array();

		if ( $interval <= 0 ) $interval = 30;
		if ( $default_max <= 0 ) $default_max = 4;

		$options  = get_option( 'o100_store_hours', array() );
		$weekdays = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');

		// Save interval & max_order
		if ( $prefix === 'o100_' ) {
			$options['o100_global_interval']  = $interval;
			$options['o100_global_max_order'] = $default_max;
		} else {
			$options[$prefix . 'interval']  = $interval;
			$options[$prefix . 'max_order'] = $default_max;
		}

		// Save each day's schedule & generate timeslots
		$new_slots = array();
		foreach ( $weekdays as $day ) {
			$day_key = $prefix . $day . '_opcl_time';

			if ( isset( $schedule[$day] ) && is_array( $schedule[$day] ) ) {
				$clean = array();
				foreach ( $schedule[$day] as $slot ) {
					$oh = sanitize_text_field($slot['open-hr'] ?? '');
					$om = sanitize_text_field($slot['open-min'] ?? '');
					$ch = sanitize_text_field($slot['close-hr'] ?? '');
					$cm = sanitize_text_field($slot['close-min'] ?? '');
					$ot = ($oh !== '' && $om !== '') ? "$oh:$om" : '';
					$ct = ($ch !== '' && $cm !== '') ? "$ch:$cm" : '';
					if ( $ot !== '' || $ct !== '' ) {
						$clean[] = array( 'open-time' => $ot, 'close-time' => $ct );
					}
				}
				$options[$day_key] = empty($clean) ? 'closed' : $clean;
			} else {
				$options[$day_key] = 'closed';
			}

			// Generate timeslots for this day
			$day_data = $options[$day_key];
			if ( !is_array($day_data) ) continue;

			$day_slots = array();
			foreach ( $day_data as $slot ) {
				if ( empty($slot['open-time']) || empty($slot['close-time']) ) continue;
				$st = strtotime( $slot['open-time'] );
				$et = strtotime( $slot['close-time'] );
				if ( !$st || !$et || $st >= $et ) continue;

				$cur = $st;
				while ( $cur + ($interval * 60) <= $et ) {
					$day_slots[] = array(
						'start-time' => date('H:i', $cur),
						'end-time'   => date('H:i', $cur + ($interval * 60)),
						'max-odts'   => $default_max,
					);
					$cur += ($interval * 60);
				}
			}
			if ( !empty($day_slots) ) {
				$new_slots[$day] = $day_slots;
			}
		}

		// Save generated timeslots to DB
		$ts_key = ($prefix === 'o100_') ? 'o100_global_generated_timeslots' : $prefix . 'generated_timeslots';
		$options[$ts_key] = wp_json_encode( $new_slots );
		update_option( 'o100_store_hours', $options );

		wp_send_json_success( array(
			'slots' => $new_slots,
			'html'  => self::render_timeslots_html( $new_slots ),
		) );
	}

	/**
	 * Render timeslots grid HTML (shared between page render and AJAX response).
	 */
	public static function render_timeslots_html( $slots ) {
		$weekdays = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
		// Check if any day has actual data
		$any_data = false;
		foreach ( $weekdays as $d ) {
			if ( !empty($slots[$d]) && is_array($slots[$d]) ) { $any_data = true; break; }
		}
		if ( !$any_data ) return '';

		ob_start();
		echo '<div style="margin-bottom:10px; font-weight:600; font-size:14px; color:#0f172a;">' . esc_html__('Generated Timeslots (Expand to edit Max Orders):', 'order100') . '</div>';
		echo '<div class="o100-timeslots-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px;">';
		foreach ( $weekdays as $day ) {
			if ( !isset($slots[$day]) || empty($slots[$day]) ) continue;
			echo '<div class="o100-timeslot-day-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden;">';
			echo '<div class="o100-timeslot-day-header" style="background:#f1f5f9; padding:10px 15px; font-weight:600; cursor:pointer; display:flex; justify-content:space-between; align-items:center;" onclick="jQuery(this).next().slideToggle();">';
			echo '<span>' . esc_html($day) . '</span><span style="font-size:12px; color:#64748b; font-weight:normal;">' . count($slots[$day]) . ' slots &#9662;</span></div>';
			echo '<div class="o100-timeslot-day-content" style="display:none; padding:10px; max-height:250px; overflow-y:auto;">';
			foreach ( $slots[$day] as $idx => $s ) {
				echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f1f5f9;">';
				echo '<span style="font-size:13px; color:#334155;">' . esc_html($s['start-time'] . ' - ' . $s['end-time']) . '</span>';
				echo '<div style="display:flex; align-items:center; gap:5px;"><span style="font-size:11px; color:#94a3b8;">Max:</span>';
				echo '<input type="number" class="o100-slot-max-order-input" data-day="' . esc_attr($day) . '" data-idx="' . esc_attr($idx) . '" value="' . esc_attr($s['max-odts']) . '" style="width:60px; padding:2px 5px; font-size:13px; text-align:center;" /></div></div>';
			}
			echo '</div></div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render the generated timeslots field with a "Generate" button.
	 */
	public function render_generated_timeslots_field( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		$name     = $field_type_object->_name();
		$field_id = $field->args['id'];
		$raw_value = $field_type_object->field->value();
		$slots = json_decode( $raw_value ? $raw_value : '{}', true );
		if ( !is_array($slots) ) $slots = array();

		$prefix = 'o100_';
		if ( $field_id === 'o100_delivery_generated_timeslots' ) $prefix = 'o100_delivery_';
		if ( $field_id === 'o100_pickup_generated_timeslots' )  $prefix = 'o100_pickup_';

		echo '<div class="o100-generated-timeslots-wrapper" data-prefix="' . esc_attr($prefix) . '" data-field-id="' . esc_attr($field_id) . '" style="margin-top:20px;">';
		echo '<input type="hidden" name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" value="' . esc_attr(wp_json_encode($slots)) . '" class="o100-generated-timeslots-data" />';

		// Check if any day actually has timeslot data
		$has_slots = false;
		foreach ( $slots as $day_slots ) {
			if ( !empty($day_slots) && is_array($day_slots) ) { $has_slots = true; break; }
		}
		$btn_label = $has_slots
			? __('Refresh Timeslots', 'order100')
			: __('Generate Timeslots', 'order100');
		$btn_icon = $has_slots ? 'dashicons-update' : 'dashicons-clock';
		echo '<div style="margin-bottom:15px; padding-bottom:15px; border-bottom:1px dashed #cbd5e1;">';
		echo '<button type="button" class="o100-generate-timeslots-btn button button-primary" data-has-slots="' . ($has_slots ? '1' : '0') . '" style="width:100%; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;">';
		echo '<span class="dashicons ' . $btn_icon . '" style="font-size:18px;"></span> ' . esc_html($btn_label);
		echo '</button></div>';

		// Timeslots display (below button)
		echo '<div class="o100-timeslots-display">';
		if ( $has_slots ) {
			echo self::render_timeslots_html( $slots );
		}
		echo '</div>';
		echo '</div>';

		// JS (output once)
		static $js_done = false;
		if ( !$js_done ) {
			$js_done = true;
			$nonce = wp_create_nonce('o100_generate_timeslots');
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {

				// ── Change detection: turn button orange "Regenerate" when schedule changes ──
				function markDirty($wrapper) {
					var $btn = $wrapper.find('.o100-generate-timeslots-btn');
					if ($btn.data('has-slots') == '1') {
						$btn.html('<span class="dashicons dashicons-warning" style="font-size:18px;"></span> Regenerate Timeslots')
							.removeClass('button-primary').addClass('o100-btn-regenerate');
					}
				}
				// Watch toggles & time selects within schedule wrapper
				$(document).on('change', '.o100-day-toggle, .o100-custom-schedule-wrapper select', function() {
					// Find which timeslots wrapper belongs to the same section
					var $card = $(this).closest('.o100-settings-group-content');
					var $w = $card.find('.o100-generated-timeslots-wrapper');
					if ($w.length) markDirty($w);
				});
				// Watch interval & max_order inputs
				$(document).on('input change', 'input[name$="_interval"], input[name$="_max_order"]', function() {
					var name = $(this).attr('name') || '';
					var prefix = 'o100_';
					if (name.indexOf('delivery') !== -1) prefix = 'o100_delivery_';
					else if (name.indexOf('pickup') !== -1) prefix = 'o100_pickup_';
					var $w = $('.o100-generated-timeslots-wrapper[data-prefix="' + prefix + '"]');
					if ($w.length) markDirty($w);
				});

				// ── Generate button click ──
				$(document).on('click', '.o100-generate-timeslots-btn', function(e) {
					e.preventDefault();
					var $btn = $(this), $w = $btn.closest('.o100-generated-timeslots-wrapper');
					var prefix = $w.data('prefix');
					var schedule = {}, days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

					days.forEach(function(day) {
						var fname = prefix + day + '_opcl_time';
						var $dw = $('[data-field-name$="[' + fname + ']"]');
						if (!$dw.length) $dw = $('[data-field-name="' + fname + '"]');
						if (!$dw.length) return;
						if (!$dw.find('.o100-day-toggle').is(':checked')) return;
						var slots = [];
						$dw.find('.o100-time-slot-row').each(function() {
							var $r = $(this);
							var oh = $r.find('select[name*="[open-hr]"]').val() || '';
							var om = $r.find('select[name*="[open-min]"]').val() || '';
							var ch = $r.find('select[name*="[close-hr]"]').val() || '';
							var cm = $r.find('select[name*="[close-min]"]').val() || '';
							if (oh !== '' && om !== '') slots.push({'open-hr':oh,'open-min':om,'close-hr':ch,'close-min':cm});
						});
						if (slots.length) schedule[day] = slots;
					});

					var iKey = (prefix==='o100_') ? 'o100_global_interval' : prefix+'interval';
					var mKey = (prefix==='o100_') ? 'o100_global_max_order' : prefix+'max_order';
					var interval = $('input[name="'+iKey+'"], #'+iKey).val() || 30;
					var maxOrder = $('input[name="'+mKey+'"], #'+mKey).val() || 4;

					$btn.prop('disabled',true)
						.removeClass('o100-btn-regenerate').addClass('button-primary')
						.html('<span class="dashicons dashicons-update o100-spin"></span> Saving & Generating...');

					$.post(ajaxurl, {
						action: 'o100_generate_timeslots',
						nonce: '<?php echo $nonce; ?>',
						prefix: prefix, interval: interval, default_max: maxOrder, schedule: schedule
					}, function(res) {
						if (res.success) {
							$w.find('.o100-generated-timeslots-data').val(JSON.stringify(res.data.slots));
							$w.find('.o100-timeslots-display').html(res.data.html);
							$btn.data('has-slots', '1');
							$btn.html('<span class="dashicons dashicons-yes-alt" style="color:#22c55e"></span> Saved & Generated! Reloading...');
							setTimeout(function(){
								var $form = $w.closest('form');
								if ($form.length) {
									$form.find('[name="submit-cmb"]').click();
								} else {
									location.reload();
								}
							}, 800);
						} else {
							$btn.html('<span class="dashicons dashicons-warning"></span> Error').prop('disabled',false);
						}
					}).fail(function(){ $btn.html('<span class="dashicons dashicons-warning"></span> Network Error').prop('disabled',false); });
				});

				// ── Max-order per-slot editing ──
				$(document).on('change', '.o100-slot-max-order-input', function() {
					var $i = $(this), $d = $i.closest('.o100-generated-timeslots-wrapper').find('.o100-generated-timeslots-data');
					var data = {}; try { data = JSON.parse($d.val()); } catch(e) {}
					var day = $i.data('day'), idx = $i.data('idx');
					if (data[day] && data[day][idx]) { data[day][idx]['max-odts'] = $i.val(); $d.val(JSON.stringify(data)); }
				});
			});
			</script>
			<style>
			@keyframes o100spin { from{transform:rotate(0)} to{transform:rotate(360deg)} }
			.o100-spin { animation: o100spin .8s linear infinite; }
			.o100-btn-regenerate {
				background: #f59e0b !important;
				border-color: #d97706 !important;
				color: #fff !important;
			}
			.o100-btn-regenerate:hover {
				background: #d97706 !important;
			}
			</style>
			<?php
		}
	}
}
new O100_CMB2_Field_OpenClose();



