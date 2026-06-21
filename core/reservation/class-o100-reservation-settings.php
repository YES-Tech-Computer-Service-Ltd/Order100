<?php
/**
 * Reservation Settings
 *
 * Configures the CMB2 fields for the Reservation tab.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Reservation_Settings {

	public static function init() {
		add_action( 'cmb2_admin_init', array( __CLASS__, 'register_settings' ), 20 );
		add_action( 'cmb2_save_options-page_fields_o100_reservation', array( __CLASS__, 'save_reservation_rooms' ), 10, 2 );
		add_action( 'cmb2_save_options-page_fields_o100_reservation', array( __CLASS__, 'save_reservation_form_fields' ), 11, 2 );
	}

	public static function register_settings() {
		// TAB: Reservation
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_reservation',
			'title'        => __( 'Reservation', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_reservation',
			'display_cb'   => '__return_false',
		) );

		// ── Card 1: Basic Settings ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_basic_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<style>
				/* ═══ Reservation Settings: CMB2 → Loyalty-style block layout ═══ */

				/* Force full-width: every container from form down to card */
				.cmb-form,
				.cmb2-wrap,
				.cmb2-metabox,
				.cmb2-metabox.cmb-field-list,
				div[x-show*="settings"],
				.o100-settings-group-card,
				.o100-settings-group-content,
				.o100-settings-group-content .cmb2-metabox {
					max-width: 100% !important;
					width: 100% !important;
				}
				.o100-settings-group-content .cmb2-metabox {
					border: none !important;
					box-shadow: none !important;
					padding: 0 !important;
				}
				/* Reset .o100-half - full-width rows in reservation context */
				.o100-settings-group-content .cmb-row.o100-half {
					flex: none !important;
					width: 100% !important;
				}

				/* Base: non-checkbox .cmb-row - block layout */
				.o100-settings-group-content .cmb-row:not(.cmb-type-checkbox) {
					display: block !important;
					padding: 0 !important;
					margin: 0 0 20px 0 !important;
					border: none !important;
				}
				/* Fix: Allow jQuery .hide() and CMB2 conditionals to work despite the !important above */
				.o100-settings-group-content .cmb-row[style*="display: none"],
				.o100-settings-group-content .cmb-row[style*="display:none"] {
					display: none !important;
				}
				/* Checkbox row: horizontal layout (label left, toggle right) */
				.o100-settings-group-content .cmb-row.cmb-type-checkbox {
					display: flex !important;
					align-items: center !important;
					justify-content: space-between !important;
					padding: 12px 0 !important;
					margin: 0 0 8px 0 !important;
					border: none !important;
					border-bottom: 1px solid #f1f5f9 !important;
				}
				.o100-settings-group-content .cmb-type-checkbox .cmb-th {
					flex: 1 !important;
					padding: 0 !important;
					float: none !important;
					width: auto !important;
				}
				.o100-settings-group-content .cmb-type-checkbox .cmb-td {
					flex: 0 0 auto !important;
					width: auto !important;
					padding: 0 !important;
					float: none !important;
					display: flex !important;
					align-items: center !important;
				}
				.o100-settings-group-content .cmb-row:last-child {
					margin-bottom: 0 !important;
				}

				/* Label: block, above input, bold (non-checkbox) */
				.o100-settings-group-content .cmb-row:not(.cmb-type-checkbox) .cmb-th {
					display: block !important;
					width: 100% !important;
					padding: 0 0 8px 0 !important;
					float: none !important;
				}
				.o100-settings-group-content .cmb-row .cmb-th label {
					font-size: 14px !important;
					font-weight: 700 !important;
					color: #334155 !important;
					line-height: 1.4 !important;
				}

				/* Field container: block, full width */
				.o100-settings-group-content .cmb-row .cmb-td {
					display: flex !important;
					flex-wrap: wrap !important;
					align-items: stretch !important;
					width: 100% !important;
					padding: 0 !important;
					float: none !important;
				}

				/* All inputs: full-width — override WP .regular-text max-width:25em */
				.o100-settings-group-content .cmb-td input.regular-text,
				.o100-settings-group-content .cmb-td input[type="text"],
				.o100-settings-group-content .cmb-td input[type="number"],
				.o100-settings-group-content .cmb-td input[type="email"],
				.o100-settings-group-content .cmb-td input[type="url"] {
					order: 1 !important;
					flex: 1 1 auto !important;
					width: 100% !important;
					max-width: 100% !important;
					height: 40px !important;
					padding: 8px 14px !important;
					font-size: 14px !important;
					border: 1px solid #cbd5e1 !important;
					border-radius: 6px !important;
					box-shadow: none !important;
					box-sizing: border-box !important;
					margin: 0 !important;
					transition: border-color 0.15s ease !important;
				}
				.o100-settings-group-content .cmb-td input:focus {
					border-color: #F59322 !important;
					outline: none !important;
					box-shadow: 0 0 0 2px rgba(99,102,241,0.15) !important;
				}

				/* Select: full-width */
				.o100-settings-group-content .cmb-td select,
				.o100-settings-group-content .cmb-td select.cmb2_select {
					order: 1 !important;
					width: 100% !important;
					max-width: 100% !important;
					height: 40px !important;
					padding: 8px 32px 8px 14px !important;
					font-size: 14px !important;
					border: 1px solid #cbd5e1 !important;
					border-radius: 6px !important;
					box-shadow: none !important;
					box-sizing: border-box !important;
					margin: 0 !important;
					background-color: #fff !important;
				}

				/* Textarea: full-width */
				.o100-settings-group-content .cmb-td textarea {
					order: 1 !important;
					width: 100% !important;
					max-width: 100% !important;
					padding: 10px 14px !important;
					font-size: 14px !important;
					border: 1px solid #cbd5e1 !important;
					border-radius: 6px !important;
					box-shadow: none !important;
					box-sizing: border-box !important;
					margin: 0 !important;
					resize: vertical !important;
				}

				/* ═══ Input suffix: flush-attached to input right side ═══ */
				.o100-settings-group-content .cmb-td .o100-input-suffix {
					order: 2 !important;
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					background: #f8fafc !important;
					border: 1px solid #cbd5e1 !important;
					border-left: none !important;
					padding: 0 15px !important;
					height: 40px !important;
					color: #64748b !important;
					font-size: 13px !important;
					border-radius: 0 6px 6px 0 !important;
					box-sizing: border-box !important;
					margin: 0 !important;
				}
				/* When suffix exists, flatten input right border and let it fill space */
				.o100-settings-group-content .cmb-td input:has(~ .o100-input-suffix) {
					flex: 1 1 auto !important;
					width: auto !important;
					max-width: 100% !important;
					border-radius: 6px 0 0 6px !important;
					border-right: none !important;
					margin: 0 !important;
				}

				/* ═══ Reminder field: input+select combo (like Loyalty Expiry) ═══ */
				.o100-settings-group-content .o100-resv-input-combo {
					display: flex !important;
					align-items: stretch !important;
					max-width: 280px !important;
				}
				.o100-settings-group-content .cmb-td .o100-resv-input-combo input[type="number"],
.o100-settings-group-content .cmb-td .o100-resv-input-combo input[type="text"] {
					border-color: #d1d5db !important;
					box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05) !important;

					flex: 1 1 auto !important;
					width: auto !important;
					border-radius: 6px 0 0 6px !important;
					border-right: none !important;
					height: 40px !important;
					padding: 8px 14px !important;
					font-size: 14px !important;
					border: 1px solid #cbd5e1 !important;
					box-shadow: none !important;
					margin: 0 !important;
					box-sizing: border-box !important;
				}
				.o100-settings-group-content .cmb-td .o100-resv-input-combo select {
					border-color: #d1d5db !important;
					box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05) !important;

					width: auto !important;
					border-radius: 0 6px 6px 0 !important;
					background-color: #f8fafc !important;
					height: 40px !important;
					padding: 8px 28px 8px 12px !important;
					font-size: 14px !important;
					border: 1px solid #cbd5e1 !important;
					color: #64748b !important;
					box-shadow: none !important;
					margin: 0 !important;
					box-sizing: border-box !important;
				}

				
				.o100-settings-group-content .cmb-td .o100-resv-input-combo input:focus,
				.o100-settings-group-content .cmb-td .o100-resv-input-combo select:focus {
					position: relative;
					z-index: 10;
					border-color: #F59322 !important;
					box-shadow: 0 0 0 3px rgba(245, 147, 34, 0.1) !important;
				}

				/* Description text: small gray below input */
				.o100-settings-group-content .cmb-td p.cmb2-metabox-description,
				.o100-settings-group-content .cmb-td span.cmb2-metabox-description {
					order: 3 !important;
					width: 100% !important;
					margin: 8px 0 0 0 !important;
					padding: 0 !important;
					font-size: 12px !important;
					color: #94a3b8 !important;
					line-height: 1.5 !important;
					border: none !important;
					box-shadow: none !important;
				}

				/* Checkbox: toggle style */
				.o100-settings-group-content .cmb-type-checkbox .cmb-td input[type="checkbox"] {
					width: 44px !important;
					height: 24px !important;
					flex: 0 0 auto !important;
					border-radius: 12px !important;
					appearance: none !important;
					-webkit-appearance: none !important;
					background-color: #cbd5e1 !important;
					position: relative !important;
					cursor: pointer !important;
					transition: background-color 0.2s ease !important;
					border: none !important;
					outline: none !important;
					box-shadow: none !important;
					padding: 0 !important;
				}
				.o100-settings-group-content .cmb-type-checkbox .cmb-td input[type="checkbox"]::after {
					content: "" !important;
					position: absolute !important;
					top: 2px !important;
					left: 2px !important;
					width: 20px !important;
					height: 20px !important;
					background: #fff !important;
					border-radius: 50% !important;
					transition: transform 0.2s ease !important;
					box-shadow: 0 1px 3px rgba(0,0,0,0.15) !important;
				}
				.o100-settings-group-content .cmb-type-checkbox .cmb-td input[type="checkbox"]:checked {
					background-color: #F59322 !important;
				}
				.o100-settings-group-content .cmb-type-checkbox .cmb-td input[type="checkbox"]:checked::after {
					transform: translateX(20px) !important;
				}
				.o100-settings-group-content .cmb-type-checkbox .cmb-td label {
					display: none !important;
				}

				/* ═══ Subtab focus states ═══ */
				.o100-resv-subtab:focus,
				.o100-resv-subtab:active {
					outline: none !important;
					box-shadow: none !important;
					border-color: transparent !important;
					border-bottom-color: transparent !important;
				}
				.o100-resv-subtab.active:focus,
				.o100-resv-subtab.active:active {
					border-bottom-color: #F59322 !important;
				}
				</style>';
				// ── Wrap Settings Fields ──
				echo '<div x-show="viewMode === \'settings\'" x-cloak>';
				echo '<div class="o100-settings-group-card o100-resv-settings-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Basic Settings', 'order100') . '</h3><p>' . esc_html__('Core reservation configuration for your restaurant.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Reservation', 'order100' ),
			'desc' => esc_html__( 'Allow customers to book tables through your website.', 'order100' ),
			'id'   => 'o100_enable_reservation',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Confirmation Mode', 'order100' ),
			'desc' => esc_html__( 'Auto: reservation is immediately confirmed. Manual: requires staff approval.', 'order100' ),
			'id'   => 'o100_resv_confirmation',
			'type' => 'select',
			'default' => 'auto',
			'options' => array(
				'auto'   => esc_html__( 'Auto Confirm', 'order100' ),
				'manual' => esc_html__( 'Manual Confirm', 'order100' ),
			),
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Max Party Size', 'order100' ),
			'id'   => 'o100_resv_max_party',
			'type' => 'text',
			'default' => '10',
			'attributes' => array( 'type' => 'number', 'min' => '1', 'max' => '100' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">guests</span>',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Minimum Lead Time', 'order100' ),
			'desc' => esc_html__( 'How many hours in advance must a reservation be made.', 'order100' ),
			'id'   => 'o100_resv_lead_time',
			'type' => 'text',
			'default' => '2',
			'attributes' => array( 'type' => 'number', 'min' => '0', 'max' => '72' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">hours</span>',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Max Advance Booking', 'order100' ),
			'desc' => esc_html__( 'How many days ahead customers can book.', 'order100' ),
			'id'   => 'o100_resv_max_advance',
			'type' => 'text',
			'default' => '30',
			'attributes' => array( 'type' => 'number', 'min' => '1', 'max' => '365' ),
			'classes' => 'o100-half',
			'after' => '<span class="o100-input-suffix">days</span>',
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_basic_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div style="clear:both;"></div></div></div>'; }
		) );

		// ── Card 2: Private Rooms & Events ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_rooms_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Private Rooms & Events', 'order100') . '</h3><p>' . esc_html__('Configure private dining rooms and event spaces available for booking.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Private Room Booking', 'order100' ),
			'desc' => esc_html__( 'Allow customers to book private rooms and event spaces.', 'order100' ),
			'id'   => 'o100_resv_enable_rooms',
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Private Room Party Size Prompt', 'order100' ),
			'desc' => esc_html__( 'Automatically prompt customers to book a private room if their party size is greater than or equal to this number.', 'order100' ),
			'id'   => 'o100_resv_private_room_threshold',
			'type' => 'text',
			'default' => '8',
			'attributes' => array(
				'type' => 'number', 'min' => '1', 'max' => '100',
				'data-conditional-id'    => 'o100_resv_enable_rooms',
				'data-conditional-value' => 'on',
			),
			'classes' => 'o100-half',
		) );


		// Custom room list + modal (replaces CMB2 group repeater)
		$cmb->add_field( array(
			'id'   => 'o100_resv_rooms',
			'type' => 'title',
			'render_row_cb' => array( __CLASS__, 'render_rooms_table' ),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_rooms_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '<div style="clear:both;"></div></div></div>'; }
		) );

		// ── Card 3: Notifications ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_notify_start',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title"><h3>' . esc_html__('Notifications & Messages', 'order100') . '</h3><p>' . esc_html__('Configure reminder timing and confirmation messages.', 'order100') . '</p></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Reminder Before Reservation', 'order100' ),
			'desc' => esc_html__( 'Send a reminder email this much time before the reservation. Set 0 to disable.', 'order100' ),
			'id'   => 'o100_resv_reminder_hours',
			'type' => 'text',
			'default' => '2',
			'render_row_cb' => function( $field_args, $field ) {
				$val_num = $field->value();
				$opts = get_option('o100_reservation');
				$val_unit = isset($opts['o100_resv_reminder_unit']) ? $opts['o100_resv_reminder_unit'] : 'hours';
				
				echo '<div class="cmb-row cmb-type-text cmb2-id-o100-resv-reminder-hours table-layout">';
				echo '<div class="cmb-th"><label for="o100_resv_reminder_hours">' . esc_html( $field->args('name') ) . '</label></div>';
				echo '<div class="cmb-td">';
				echo '<div class="o100-resv-input-combo">';
				echo '<input type="number" min="0" name="o100_reservation[o100_resv_reminder_hours]" id="o100_resv_reminder_hours" value="' . esc_attr( $val_num ) . '" />';
				echo '<select name="o100_reservation[o100_resv_reminder_unit]">';
				echo '<option value="hours" ' . selected( $val_unit, 'hours', false ) . '>' . esc_html__('Hours', 'order100') . '</option>';
				echo '<option value="days" ' . selected( $val_unit, 'days', false ) . '>' . esc_html__('Days', 'order100') . '</option>';
				echo '</select>';
				echo '</div>';
				echo '<p class="cmb2-metabox-description">' . esc_html( $field->args('desc') ) . '</p>';
				echo '</div></div>';
			}
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_reminder_unit',
			'type' => 'text',
			'default' => 'hours',
			'render_row_cb' => '__return_empty_string',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Admin Notification Email', 'order100' ),
			'desc' => esc_html__( 'Email address to receive new reservation alerts. Defaults to site admin email.', 'order100' ),
			'id'   => 'o100_resv_admin_email',
			'type' => 'text_email',
			'default' => get_option( 'admin_email' ),
			'classes' => 'o100-half',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Success Message', 'order100' ),
			'desc' => esc_html__( 'Displayed after a customer submits a reservation.', 'order100' ),
			'id'   => 'o100_resv_success_msg',
			'type' => 'textarea_small',
			'default' => esc_html__( 'Thank you! Your reservation has been received. We will contact you shortly to confirm.', 'order100' ),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_resv_notify_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '<div style="clear:both;"></div></div></div>'; // close card 3
			}
		) );

		// ── Close Settings Wrapper ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_settings_wrap_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div>'; }
		) );



		// ── Form Builder sub-tab ──
		$cmb->add_field( array(
			'id'   => 'o100_resv_form_builder',
			'type' => 'title',
			'render_row_cb' => array( __CLASS__, 'render_form_builder' ),
		) );

		
	}

	public static function render_form_builder() {
		echo '<div x-show="viewMode === \'form_builder\'" x-cloak>';
		wp_enqueue_script( 'jquery-ui-sortable' );
		$opts   = get_option( 'o100_reservation', array() );
		$fields = isset( $opts['o100_resv_form_fields'] ) && is_array( $opts['o100_resv_form_fields'] )
			? $opts['o100_resv_form_fields']
			: self::get_default_form_fields();

		// Auto-migrate legacy booking_type to occasion
		foreach ( $fields as &$f ) {
			if ( isset( $f['type'] ) && $f['type'] === 'booking_type' ) {
				$f['type'] = 'occasion';
				$f['id']   = 'occasion';
				$f['label'] = __( 'Occasion', 'order100' );
			}
		}
		unset( $f );
		?>
		<div id="o100-resv-tab-form" class="o100-resv-tab-content" style="width:100%;">

		<div class="o100-settings-group-card">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'Form Customization', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Configure which fields appear in the reservation form and their properties.', 'order100' ); ?></p>
				<div style="margin-top:8px; display:flex; align-items:center; gap:8px;">
					<code id="o100-resv-shortcode" style="background:#f1f5f9; padding:6px 12px; border-radius:6px; font-size:13px; color:#334155; border:1px solid #e2e8f0;">[o100_reservation]</code>
					<button type="button" id="o100-resv-copy-sc" class="button" style="padding:4px 10px; font-size:12px;" title="Copy shortcode">
						<span class="dashicons dashicons-clipboard" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
					</button>
					<div style="position:relative; display:inline-block;" id="o100-preview-wrap">
						<button type="button" id="o100-resv-preview-btn" class="button" style="padding:4px 14px; font-size:12px; display:inline-flex; align-items:center; gap:4px;" title="Preview Form">
							<span class="dashicons dashicons-visibility" style="font-size:14px;width:14px;height:14px;"></span>
							<?php esc_html_e( 'Preview', 'order100' ); ?>
						</button>
						<div id="o100-preview-dropdown" style="display:none; position:absolute; top:100%; left:0; margin-top:4px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); min-width:180px; z-index:99; overflow:hidden;">
							<a href="<?php echo esc_url( home_url( '?o100_resv_preview=blank' ) ); ?>" target="_blank" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#334155; text-decoration:none; font-size:13px; font-weight:500; transition:background 0.15s;">
								<span class="dashicons dashicons-media-default" style="font-size:16px;width:16px;height:16px;color:#94a3b8;"></span>
								<?php esc_html_e( 'Blank Page', 'order100' ); ?>
							</a>
							<a href="<?php echo esc_url( home_url( '?o100_resv_preview=theme' ) ); ?>" target="_blank" style="display:flex; align-items:center; gap:8px; padding:10px 14px; color:#334155; text-decoration:none; font-size:13px; font-weight:500; border-top:1px solid #f1f5f9; transition:background 0.15s;">
								<span class="dashicons dashicons-admin-appearance" style="font-size:16px;width:16px;height:16px;color:#94a3b8;"></span>
								<?php esc_html_e( 'Theme Page', 'order100' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
			<div class="o100-settings-group-content">

			<input type="hidden" name="o100_resv_form_fields" id="o100_resv_form_fields_data" value="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>">

			<table class="o100-fb-table o100-menu-table" id="o100-fb-table" style="margin-bottom: 0;">
				<thead>
					<tr>
						<th style="width:5%; padding-left:14px;"></th>
						<th style="width:35%;"><?php esc_html_e( 'Field Name', 'order100' ); ?></th>
						<th style="width:15%;text-align:center;"><?php esc_html_e( 'Type', 'order100' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Width', 'order100' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Required', 'order100' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Visible', 'order100' ); ?></th>
						<th style="width:15%;text-align:right;"><?php esc_html_e( 'Action', 'order100' ); ?></th>
					</tr>
				</thead>
				<tbody id="o100-fb-tbody">
				</tbody>
			</table>

			<button type="button" id="o100-fb-add-btn" class="button button-primary" style="margin-top:14px; background:#F59322; border-color:#F59322; box-shadow:none; display:inline-flex; align-items:center; gap:4px; padding:6px 16px; font-size:13px; font-weight:500; border-radius:6px;">
				<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
				<?php esc_html_e( 'Add Custom Field', 'order100' ); ?>
			</button>

			</div>
		</div>

		<!-- Form Footer Settings -->
		<?php
		$default_terms  = __( 'By selecting "Confirm Reservation" you are agreeing to the terms and conditions of our User Agreement and Privacy Policy. The website will automatically use your contact information to register an account and then send promotional information to your email.', 'order100' );
		$default_dining = __( "We have a 15 minute grace period. Please call us if you are running later than 15 minutes after your reservation time.\nWe may contact you about this reservation, so please ensure your email and phone number are up to date.", 'order100' );
		$terms_val   = isset( $opts['o100_resv_terms_text'] ) ? $opts['o100_resv_terms_text'] : $default_terms;
		$dining_val  = isset( $opts['o100_resv_dining_info'] ) ? $opts['o100_resv_dining_info'] : $default_dining;
		$note_val    = isset( $opts['o100_resv_restaurant_note'] ) ? $opts['o100_resv_restaurant_note'] : '';
		?>
		<div class="o100-settings-group-card" style="margin-top:20px;">
			<div class="o100-settings-group-title">
				<h3><?php esc_html_e( 'Form Footer Content', 'order100' ); ?></h3>
				<p><?php esc_html_e( 'Content displayed below the reservation form fields.', 'order100' ); ?></p>
			</div>
			<div class="o100-settings-group-content">
				<div style="display:grid; gap:18px;">
					<div>
						<label style="display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:6px;">
							<span class="dashicons dashicons-yes-alt" style="font-size:16px; width:16px; height:16px; color:#F59322; vertical-align:text-bottom;"></span>
							<?php esc_html_e( 'Terms & Conditions Checkbox', 'order100' ); ?>
						</label>
						<textarea name="o100_resv_terms_text" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="<?php esc_attr_e( 'Leave empty to hide the terms checkbox', 'order100' ); ?>"><?php echo esc_textarea( $terms_val ); ?></textarea>
						<p style="font-size:12px; color:#94a3b8; margin:4px 0 0;"><?php esc_html_e( 'Required checkbox shown before submit. Leave empty to hide.', 'order100' ); ?></p>
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:6px;">
							<span class="dashicons dashicons-info-outline" style="font-size:16px; width:16px; height:16px; color:#f59e0b; vertical-align:text-bottom;"></span>
							<?php esc_html_e( 'Important Dining Information', 'order100' ); ?>
						</label>
						<textarea name="o100_resv_dining_info" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="<?php esc_attr_e( 'e.g. We have a 15 minute grace period...', 'order100' ); ?>"><?php echo esc_textarea( $dining_val ); ?></textarea>
						<p style="font-size:12px; color:#94a3b8; margin:4px 0 0;"><?php esc_html_e( 'Shown below the form. Use line breaks for multiple lines.', 'order100' ); ?></p>
					</div>
					<div>
						<label style="display:block; font-weight:600; font-size:13px; color:#374151; margin-bottom:6px;">
							<span class="dashicons dashicons-format-quote" style="font-size:16px; width:16px; height:16px; color:#10b981; vertical-align:text-bottom;"></span>
							<?php esc_html_e( 'A Note from the Restaurant', 'order100' ); ?>
						</label>
						<textarea name="o100_resv_restaurant_note" rows="3" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; font-size:14px; font-family:inherit; resize:vertical;" placeholder="<?php esc_attr_e( 'e.g. Thank you for choosing our restaurant...', 'order100' ); ?>"><?php echo esc_textarea( $note_val ); ?></textarea>
						<p style="font-size:12px; color:#94a3b8; margin:4px 0 0;"><?php esc_html_e( 'Personal message shown after dining info. Leave empty to hide.', 'order100' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Edit Field Modal -->
		<div id="o100-fb-modal-overlay" class="o100-room-modal-overlay" style="display:none;">
			<div class="o100-room-modal" style="width:480px;">
				<div class="o100-room-modal-header">
					<h3 id="o100-fb-modal-title"><?php esc_html_e( 'Edit Field', 'order100' ); ?></h3>
					<button type="button" id="o100-fb-modal-close" class="o100-room-modal-close">&times;</button>
				</div>
				<div class="o100-room-modal-body">
					<div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Field Type', 'order100' ); ?></label>
							<select id="o100-fb-f-type">
								<option value="text">Text</option>
								<option value="email">Email</option>
								<option value="tel">Phone</option>
								<option value="number">Number</option>
								<option value="textarea">Textarea</option>
								<option value="dropdown">Dropdown</option>
								<option value="select">Select</option>
								<option value="checkbox">Checkbox</option>
								<option value="date">Date</option>
								<option value="time">Time</option>
								<option value="branch">Branch</option>
								<option value="occasion">Occasion Dropdown</option>
							</select>
						</div>
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Width', 'order100' ); ?></label>
							<select id="o100-fb-f-width">
								<option value="half"><?php esc_html_e( 'Half (50%)', 'order100' ); ?></option>
								<option value="third"><?php esc_html_e( 'One-Third (33%)', 'order100' ); ?></option>
								<option value="full"><?php esc_html_e( 'Full (100%)', 'order100' ); ?></option>
							</select>
						</div>
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label style="display:flex; align-items:center; gap:6px; cursor:pointer; text-transform:none; letter-spacing:0; font-size:14px;">
							<input type="checkbox" id="o100-fb-f-required" style="width:auto;margin:0;">
							<?php esc_html_e( 'Required field', 'order100' ); ?>
						</label>
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label><?php esc_html_e( 'Field Label', 'order100' ); ?></label>
						<input type="text" id="o100-fb-f-label" placeholder="<?php esc_attr_e( 'e.g. Your Name', 'order100' ); ?>">
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label><?php esc_html_e( 'Placeholder Text', 'order100' ); ?></label>
						<input type="text" id="o100-fb-f-placeholder" placeholder="<?php esc_attr_e( 'e.g. Enter your name', 'order100' ); ?>">
					</div>
					<div class="o100-room-modal-field" id="o100-fb-options-wrap" style="margin-top:12px; display:none;">
						<label><?php esc_html_e( 'Options (one per line)', 'order100' ); ?></label>
						<textarea id="o100-fb-f-options" rows="4" placeholder="<?php esc_attr_e( "Option 1\nOption 2\nOption 3", 'order100' ); ?>"></textarea>
					</div>
				</div>
				<div class="o100-room-modal-footer">
					<button type="button" id="o100-fb-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" id="o100-fb-modal-save" class="button button-primary" style="background:#F59322; border-color:#F59322;"><?php esc_html_e( 'Save Changes', 'order100' ); ?></button>
				</div>
			</div>
		</div>

		</div><!-- close #o100-resv-tab-form -->

		<style>
		/* Unified Table Styles matching Tailwind lists */
		.o100-fb-table { width:100%; border-collapse:collapse; margin:0; }
		.o100-fb-table th { 
			background-color: #f8fafc; /* bg-slate-50 */
			border-bottom: 1px solid #e2e8f0; /* border-slate-200 */
			padding: 12px 16px; /* py-3 px-4 */
			font-size: 12px; /* text-xs */
			font-weight: 600; /* font-semibold */
			color: #64748b; /* text-slate-500 */
			text-transform: uppercase; /* uppercase */
			letter-spacing: 0.05em; /* tracking-wider */
			text-align: left;
		}
		.o100-fb-table td { 
			padding: 16px; /* py-4 px-4 */
			font-size: 14px; /* text-sm */
			color: #0f172a; /* text-slate-900 */
			border-bottom: 1px solid #f1f5f9; /* border-slate-100 */
			vertical-align: middle; 
		}
		.o100-fb-table tbody tr:last-child td { border-bottom:none; }
		.o100-fb-table tbody tr:hover td { background:#f8fafc; }
		.o100-fb-table td strong { font-weight:600; color:#0f172a; display:block; }
		.o100-fb-table .o100-fb-drag { cursor:grab; color:#cbd5e1; font-size:16px; }
		.o100-fb-table .o100-fb-drag:active { cursor:grabbing; }
		.o100-fb-table .o100-fb-type-badge {
			display:inline-block; padding:2px 8px; background:#f1f5f9;
			border-radius:4px; font-size:12px; color:#64748b; font-weight:500;
		}
		.o100-fb-table .o100-fb-width-badge {
			display:inline-block; padding:2px 8px; background:#eff6ff;
			border-radius:4px; font-size:12px; color:#F59322; font-weight:500;
		}
		.o100-fb-table .o100-fb-actions { display:flex; justify-content:flex-end; gap:12px; align-items:center; }
		.o100-fb-table .o100-fb-actions a {
			font-size:14px; text-decoration:none; cursor:pointer; font-weight:500; display:inline-flex; align-items:center; justify-content:center;
		}
		.o100-fb-table .o100-fb-edit { color:#F59322; }
		.o100-fb-table .o100-fb-edit:hover { color:#F59322; }
		.o100-fb-table .o100-fb-delete { color:#94a3b8; }
		.o100-fb-table .o100-fb-delete:hover { color:#ef4444; }
		/* Toggle switch */
		.o100-fb-toggle { position:relative; display:inline-block; width:36px; height:20px; }
		.o100-fb-toggle input { opacity:0; width:0; height:0; }
		.o100-fb-toggle .o100-fb-slider {
			position:absolute; cursor:pointer; inset:0;
			background:#cbd5e1; border-radius:20px; transition:0.2s;
		}
		.o100-fb-toggle .o100-fb-slider:before {
			content:''; position:absolute; height:16px; width:16px;
			left:2px; bottom:2px; background:#fff; border-radius:50%; transition:0.2s;
		}
		.o100-fb-toggle input:checked + .o100-fb-slider { background:#F59322; } /* Indigo to match toggle style */
		.o100-fb-toggle input:checked + .o100-fb-slider:before { transform:translateX(16px); }
		/* Required dot */
		.o100-fb-req-dot {
			display:inline-block; width:8px; height:8px; border-radius:50%;
			background:#ef4444;
		}
		.o100-fb-req-dot.off { background:#e2e8f0; }
		/* Sortable placeholder */
		.o100-fb-table tbody tr.ui-sortable-placeholder {
			background:#eff6ff !important; border:2px dashed #93c5fd !important;
			visibility:visible !important;
		}
		.o100-fb-table tbody tr.ui-sortable-helper {
			background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.1);
		}
		/* Modal select */
		.o100-room-modal-field select {
			width:100%; padding:8px 32px 8px 12px; border:1px solid #cbd5e1;
			border-radius:6px; font-size:14px; color:#1e293b;
			box-sizing:border-box; background:#fff;
			-webkit-appearance:none; -moz-appearance:none; appearance:none;
			background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M2 4l4 4 4-4'/%3E%3C/svg%3E") !important;
			background-repeat:no-repeat !important; background-position:right 10px center !important;
			background-size:12px !important;
		}
		.o100-room-modal-field select:disabled {
			background-color:#f1f5f9 !important; color:#94a3b8; cursor:not-allowed; opacity:1;
		}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var fields = [];
			try { fields = JSON.parse( $('#o100_resv_form_fields_data').val() ) || []; } catch(e) { fields = []; }
			if (!Array.isArray(fields) || fields.length === 0) fields = <?php echo wp_json_encode( self::get_default_form_fields() ); ?>;

			var editIdx = -1;

			// Feature flags from PHP settings
			var branchEnabled = <?php echo get_option('o100_locations_status') === 'on' ? 'true' : 'false'; ?>;
			var roomsEnabled = <?php
				$resv_opts = get_option('o100_reservation', array());
				echo (!empty($resv_opts['o100_resv_enable_rooms']) && $resv_opts['o100_resv_enable_rooms'] === 'on') ? 'true' : 'false';
			?>;

			function renderTable() {
				var $tbody = $('#o100-fb-tbody');
				$tbody.empty();

				// Ensure branch is always at index 0
				var branchIdx = -1;
				for (var bi = 0; bi < fields.length; bi++) {
					if (fields[bi].type === 'branch') { branchIdx = bi; break; }
				}
				if (branchIdx > 0) {
					var branchField = fields.splice(branchIdx, 1)[0];
					fields.unshift(branchField);
				}

				fields.forEach(function(f, i) {
					var isBuiltin = f.is_builtin ? true : false;
					var isPinned = (f.type === 'branch'); // Branch is pinned to first row
					var toggleChecked = f.enabled ? 'checked' : '';
					var toggleDisabled = '';

					// Auto-disable logic: Branch if locations off, Seating Preference if rooms off
					if (f.type === 'branch' && !branchEnabled) {
						f.enabled = false;
						toggleChecked = '';
						toggleDisabled = ' disabled';
					}

					var reqClass = f.required ? '' : ' off';
					var deleteBtn = isBuiltin ? '' : '<a class="o100-fb-delete" data-idx="' + i + '">Delete</a>';
					var dragHandle = isPinned
						? '<span class="dashicons dashicons-lock" style="color:#cbd5e1;cursor:default;" title="Pinned to first position"></span>'
						: '<span class="o100-fb-drag dashicons dashicons-menu"></span>';
					var pinnedClass = isPinned ? ' class="o100-fb-pinned"' : '';
					var autoNote = '';
					if (f.type === 'branch' && !branchEnabled) autoNote = ' <span style="font-size:10px;color:#f59e0b;" title="Enable Branches in Settings to activate">(Branches off)</span>';

					$tbody.append(
						'<tr data-idx="' + i + '"' + pinnedClass + '>' +
						'<td>' + dragHandle + '</td>' +
						'<td><strong>' + $('<span>').text(f.label || f.id).html() + '</strong>' +
							(isBuiltin ? ' <span style="font-size:11px;color:#94a3b8;">(built-in)</span>' : '') +
							autoNote +
						'</td>' +
						'<td style="text-align:center;"><span class="o100-fb-type-badge">' + (f.type || 'text') + '</span></td>' +
						'<td style="text-align:center;"><span class="o100-fb-width-badge">' + (f.width || 'half') + '</span></td>' +
						'<td style="text-align:center;"><span class="o100-fb-req-dot' + reqClass + '"></span></td>' +
						'<td style="text-align:center;">' +
							'<label class="o100-fb-toggle"><input type="checkbox" data-idx="' + i + '" ' + toggleChecked + toggleDisabled + '><span class="o100-fb-slider"></span></label>' +
						'</td>' +
						'<td class="o100-fb-actions" style="text-align:right;">' +
							'<a class="o100-fb-edit" data-idx="' + i + '">Edit</a>' +
							deleteBtn +
						'</td>' +
						'</tr>'
					);
				});
				syncHidden();
				initSortable();
			}

			function syncHidden() {
				$('#o100_resv_form_fields_data').val( JSON.stringify(fields) );
			}

			function initSortable() {
				$('#o100-fb-tbody').sortable({
					handle: '.o100-fb-drag',
					axis: 'y',
					placeholder: 'ui-sortable-placeholder',
					items: 'tr:not(.o100-fb-pinned)', // Exclude pinned Branch row
					update: function() {
						var newOrder = [];
						$('#o100-fb-tbody tr').each(function() {
							var idx = parseInt($(this).data('idx'));
							if (!isNaN(idx) && fields[idx]) newOrder.push(fields[idx]);
						});
						fields = newOrder;
						renderTable();
					}
				});
			}

			function openModal(idx) {
				editIdx = idx;
				var f = idx >= 0 ? fields[idx] : null;
				var isBuiltin = f && f.is_builtin;

				$('#o100-fb-modal-title').text(idx >= 0 ? '<?php echo esc_js( __( 'Edit Field', 'order100' ) ); ?>' : '<?php echo esc_js( __( 'Add Custom Field', 'order100' ) ); ?>');

				$('#o100-fb-f-type').val(f ? f.type : 'text').prop('disabled', isBuiltin);
				$('#o100-fb-f-width').val(f ? (f.width || 'half') : 'half');
				$('#o100-fb-f-required').prop('checked', f ? f.required : false);
				$('#o100-fb-f-label').val(f ? f.label : '');
				$('#o100-fb-f-placeholder').val(f ? f.placeholder : '');
				$('#o100-fb-f-options').val(f && f.options ? f.options.replace(/,/g, '\n') : '');

				toggleOptionsField();
				$('#o100-fb-modal-overlay').fadeIn(150);
				setTimeout(function(){ $('#o100-fb-f-label').focus(); }, 200);
			}

			function toggleOptionsField() {
				var t = $('#o100-fb-f-type').val();
				$('#o100-fb-options-wrap').toggle(t === 'select' || t === 'dropdown');
			}

			$('#o100-fb-f-type').on('change', toggleOptionsField);

			function closeModal() { $('#o100-fb-modal-overlay').fadeOut(150); }

			// Toggle enabled
			$(document).on('change', '.o100-fb-toggle input', function() {
				var idx = parseInt($(this).data('idx'));
				fields[idx].enabled = $(this).is(':checked');
				syncHidden();
			});

			// Edit
			$(document).on('click', '.o100-fb-edit', function(e) {
				e.preventDefault();
				openModal(parseInt($(this).data('idx')));
			});

			// Delete
			$(document).on('click', '.o100-fb-delete', function(e) {
				e.preventDefault();
				var idx = parseInt($(this).data('idx'));
				if (confirm('<?php echo esc_js( __( 'Delete this field?', 'order100' ) ); ?>')) {
					fields.splice(idx, 1);
					renderTable();
				}
			});

			// Add
			$('#o100-fb-add-btn').on('click', function() { openModal(-1); });

			// Save
			$('#o100-fb-modal-save').on('click', function() {
				var label = $.trim($('#o100-fb-f-label').val());
				if (!label) { $('#o100-fb-f-label').focus(); return; }

				var type = $('#o100-fb-f-type').val();
				var fieldData = {
					type:        type,
					label:       label,
					placeholder: $.trim($('#o100-fb-f-placeholder').val()),
					width:       $('#o100-fb-f-width').val(),
					required:    $('#o100-fb-f-required').is(':checked'),
					enabled:     true,
				};

				if (type === 'select' || type === 'dropdown') {
					fieldData.options = $.trim($('#o100-fb-f-options').val()).replace(/\n/g, ',');
				}

				if (editIdx >= 0) {
					// Preserve built-in properties
					fieldData.id = fields[editIdx].id;
					fieldData.is_builtin = fields[editIdx].is_builtin || false;
					fieldData.icon = fields[editIdx].icon || '';
					if (fieldData.is_builtin) fieldData.type = fields[editIdx].type; // can't change built-in type
					fieldData.enabled = fields[editIdx].enabled;
					fields[editIdx] = fieldData;
				} else {
					fieldData.id = 'custom_' + Date.now();
					fieldData.is_builtin = false;
					fieldData.icon = '';
					fields.push(fieldData);
				}
				renderTable();
				closeModal();
			});

			// Cancel / close
			$('#o100-fb-modal-cancel, #o100-fb-modal-close').on('click', closeModal);
			$('#o100-fb-modal-overlay').on('click', function(e) {
				if (e.target === this) closeModal();
			});

			// Copy shortcode
			$('#o100-resv-copy-sc').on('click', function() {
				var sc = '[o100_reservation]';
				navigator.clipboard.writeText(sc).then(function() {
					var $btn = $('#o100-resv-copy-sc');
					$btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
					setTimeout(function() {
						$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
					}, 1500);
				});
			});

			// Preview dropdown toggle
			$('#o100-resv-preview-btn').on('click', function(e) {
				e.stopPropagation();
				$('#o100-preview-dropdown').toggle();
			});
			$(document).on('click', function(e) {
				if (!$(e.target).closest('#o100-preview-wrap').length) {
					$('#o100-preview-dropdown').hide();
				}
			});
			// Hover effect on dropdown items
			$('#o100-preview-dropdown a').on('mouseenter', function() {
				$(this).css('background', '#f8fafc');
			}).on('mouseleave', function() {
				$(this).css('background', '#fff');
			});

			renderTable();
		});
		</script>
		</div><!-- x-show="viewMode === 'form_builder'" -->
		<?php
	}

	public static function get_default_form_fields() {
		return array(
			array(
				'id' => 'guest_name', 'type' => 'text', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Your Name', 'order100' ), 'placeholder' => __( 'Enter your name', 'order100' ),
				'width' => 'half', 'icon' => 'dashicons-admin-users',
			),
			array(
				'id' => 'guest_email', 'type' => 'email', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Email', 'order100' ), 'placeholder' => 'your@email.com',
				'width' => 'half', 'icon' => 'dashicons-email-alt',
			),
			array(
				'id' => 'guest_phone', 'type' => 'tel', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Phone', 'order100' ), 'placeholder' => '(555) 123-4567',
				'width' => 'half', 'icon' => 'dashicons-phone',
			),
			array(
				'id' => 'party_size', 'type' => 'number', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Party Size', 'order100' ), 'placeholder' => '2',
				'width' => 'half', 'icon' => 'dashicons-groups',
			),
			array(
				'id' => 'reservation_date', 'type' => 'date', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Date', 'order100' ), 'placeholder' => __( 'Select date', 'order100' ),
				'width' => 'half', 'icon' => 'dashicons-calendar-alt',
			),
			array(
				'id' => 'reservation_time', 'type' => 'time', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Time', 'order100' ), 'placeholder' => __( 'Select time', 'order100' ),
				'width' => 'half', 'icon' => 'dashicons-clock',
			),
			array(
				'id' => 'branch', 'type' => 'branch', 'is_builtin' => true,
				'enabled' => true, 'required' => true,
				'label' => __( 'Branch', 'order100' ), 'placeholder' => __( 'Select a branch', 'order100' ),
				'width' => 'full', 'icon' => 'dashicons-location',
			),
			array(
				'id' => 'occasion', 'type' => 'occasion', 'is_builtin' => true,
				'enabled' => true, 'required' => false,
				'label' => __( 'Occasion', 'order100' ), 'placeholder' => '',
				'width' => 'full', 'icon' => 'dashicons-star-filled',
			),
			array(
				'id' => 'special_requests', 'type' => 'textarea', 'is_builtin' => true,
				'enabled' => true, 'required' => false,
				'label' => __( 'Special Requests', 'order100' ), 'placeholder' => __( 'Any dietary requirements or special needs...', 'order100' ),
				'width' => 'full', 'icon' => 'dashicons-edit',
			),
		);
	}

	public static function save_reservation_form_fields( $object_id, $updated ) {
		if ( 'o100_reservation' !== $object_id ) {
			return;
		}
		$raw = isset( $_POST['o100_resv_form_fields'] ) ? wp_unslash( $_POST['o100_resv_form_fields'] ) : '';
		if ( empty( $raw ) ) {
			return;
		}
		$fields = json_decode( $raw, true );
		if ( ! is_array( $fields ) ) {
			return;
		}
		$valid_types = array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'dropdown', 'checkbox', 'date', 'time', 'branch', 'occasion' );
		$clean = array();
		foreach ( $fields as $f ) {
			if ( empty( $f['label'] ) && empty( $f['id'] ) ) continue;
			$item = array(
				'id'          => sanitize_key( isset( $f['id'] ) ? $f['id'] : 'custom_' . time() ),
				'type'        => in_array( $f['type'], $valid_types, true ) ? $f['type'] : 'text',
				'is_builtin'  => ! empty( $f['is_builtin'] ),
				'enabled'     => ! empty( $f['enabled'] ),
				'required'    => ! empty( $f['required'] ),
				'label'       => sanitize_text_field( isset( $f['label'] ) ? $f['label'] : '' ),
				'placeholder' => sanitize_text_field( isset( $f['placeholder'] ) ? $f['placeholder'] : '' ),
				'width'       => in_array( isset( $f['width'] ) ? $f['width'] : 'half', array( 'half', 'third', 'full' ), true ) ? $f['width'] : 'half',
				'icon'        => sanitize_text_field( isset( $f['icon'] ) ? $f['icon'] : '' ),
			);
			if ( ( $f['type'] === 'select' || $f['type'] === 'dropdown' ) && ! empty( $f['options'] ) ) {
				$item['options'] = sanitize_textarea_field( $f['options'] );
			}
			$clean[] = $item;
		}
		$opts = get_option( 'o100_reservation', array() );
		$opts['o100_resv_form_fields'] = $clean;
		update_option( 'o100_reservation', $opts );
	}

	public static function render_rooms_table() {
		$opts  = get_option( 'o100_reservation', array() );
		$rooms = isset( $opts['o100_resv_rooms'] ) && is_array( $opts['o100_resv_rooms'] ) ? $opts['o100_resv_rooms'] : array();
		?>
		<div class="cmb-row" data-conditional-id="o100_resv_enable_rooms" data-conditional-value="on" style="padding:0;border:none;">
		<!-- Hidden field to store rooms JSON — CMB2 saves this -->
		<input type="hidden" name="o100_resv_rooms" id="o100_resv_rooms_data" value="<?php echo esc_attr( wp_json_encode( $rooms ) ); ?>">

		<!-- Table -->
		<table class="o100-rooms-table" id="o100-rooms-table">
			<thead>
				<tr>
					<th style="width:35%;"><?php esc_html_e( 'Room Name', 'order100' ); ?></th>
					<th style="width:15%;text-align:center;"><?php esc_html_e( 'Capacity', 'order100' ); ?></th>
					<th style="width:10%;text-align:center;"><?php esc_html_e( 'Qty', 'order100' ); ?></th>
					<th style="width:30%;"><?php esc_html_e( 'Description', 'order100' ); ?></th>
					<th style="width:10%;text-align:right;"><?php esc_html_e( 'Actions', 'order100' ); ?></th>
				</tr>
			</thead>
			<tbody id="o100-rooms-tbody">
				<!-- JS renders rows -->
			</tbody>
		</table>

		<div id="o100-rooms-empty" style="display:none; text-align:center; padding:32px 20px; color:#94a3b8; font-size:14px;">
			<span class="dashicons dashicons-admin-home" style="font-size:36px;width:36px;height:36px;color:#cbd5e1;display:block;margin:0 auto 10px;"></span>
			<?php esc_html_e( 'No rooms configured yet. Click the button below to add one.', 'order100' ); ?>
		</div>

		<button type="button" id="o100-rooms-add-btn" class="button button-primary" style="margin-top:12px; background:#F59322; border-color:#F59322; box-shadow:none; display:inline-flex; align-items:center; gap:4px; padding:6px 16px; font-size:13px; font-weight:500; border-radius:6px;">
			<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
			<?php esc_html_e( 'Add Room', 'order100' ); ?>
		</button>

		<!-- Modal Overlay -->
		<div id="o100-room-modal-overlay" class="o100-room-modal-overlay" style="display:none;">
			<div class="o100-room-modal">
				<div class="o100-room-modal-header">
					<h3 id="o100-room-modal-title"><?php esc_html_e( 'Add Room', 'order100' ); ?></h3>
					<button type="button" id="o100-room-modal-close" class="o100-room-modal-close">&times;</button>
				</div>
				<div class="o100-room-modal-body">
					<div class="o100-room-modal-grid">
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Room Name', 'order100' ); ?> <span style="color:#ef4444;">*</span></label>
							<input type="text" id="o100-room-f-name" placeholder="<?php esc_attr_e( 'e.g. VIP Room A', 'order100' ); ?>">
						</div>
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Max Capacity', 'order100' ); ?></label>
							<input type="number" id="o100-room-f-capacity" min="1" placeholder="12">
						</div>
						<div class="o100-room-modal-field">
							<label><?php esc_html_e( 'Quantity', 'order100' ); ?></label>
							<input type="number" id="o100-room-f-quantity" min="1" placeholder="1">
						</div>
					</div>
					<div class="o100-room-modal-field" style="margin-top:12px;">
						<label><?php esc_html_e( 'Description', 'order100' ); ?></label>
						<textarea id="o100-room-f-desc" rows="2" placeholder="<?php esc_attr_e( 'Short description shown to customers...', 'order100' ); ?>"></textarea>
					</div>
				</div>
				<div class="o100-room-modal-footer">
					<button type="button" id="o100-room-modal-cancel" class="button"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
					<button type="button" id="o100-room-modal-save" class="button button-primary"><?php esc_html_e( 'Save Room', 'order100' ); ?></button>
				</div>
			</div>
		</div>
		</div>

		<style>
		/* ═══ Rooms Table ═══ */
		.o100-rooms-table {
			width: 100%; border-collapse: separate; border-spacing: 0;
			border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;
			background: #fff;
		}
		.o100-rooms-table th {
			background: #f8fafc; border-bottom: 2px solid #e2e8f0;
			padding: 10px 16px; font-size: 11px; font-weight: 600;
			text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;
			text-align: left;
		}
		.o100-rooms-table td {
			padding: 14px 16px; border-bottom: 1px solid #f1f5f9;
			font-size: 14px; color: #334155; vertical-align: middle;
		}
		.o100-rooms-table tbody tr:last-child td { border-bottom: none; }
		.o100-rooms-table tbody tr:hover td { background: #f8fafc; }
		.o100-rooms-table td strong { font-weight: 600; color: #0f172a; }
		.o100-rooms-table .o100-room-desc {
			font-size: 13px; color: #94a3b8; max-width: 220px;
			white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
			display: block;
		}
		.o100-rooms-table .o100-room-actions { white-space: nowrap; }
		.o100-rooms-table .o100-room-actions a {
			font-size: 13px; text-decoration: none; margin-left: 12px; cursor: pointer;
			font-weight: 500;
		}
		.o100-rooms-table .o100-room-actions .o100-room-edit { color: #F59322; }
		.o100-rooms-table .o100-room-actions .o100-room-edit:hover { color: #d97b06; }
		.o100-rooms-table .o100-room-actions .o100-room-delete { color: #94a3b8; }
		.o100-rooms-table .o100-room-actions .o100-room-delete:hover { color: #ef4444; }
		.o100-rooms-table .o100-room-badge {
			display: inline-flex; align-items: center; justify-content: center;
			min-width: 32px; height: 26px; padding: 0 10px;
			background: #f1f5f9; border-radius: 6px;
			font-size: 13px; font-weight: 600; color: #475569;
		}
		/* ═══ Room Modal ═══ */
		.o100-room-modal-overlay {
			position: fixed; inset: 0; background: rgba(15,23,42,0.45);
			z-index: 100000; display: flex; align-items: center; justify-content: center;
		}
		.o100-room-modal {
			background: #fff; border-radius: 12px; width: 520px; max-width: 90vw;
			box-shadow: 0 20px 60px rgba(0,0,0,0.15); overflow: hidden;
		}
		.o100-room-modal-header {
			display: flex; align-items: center; justify-content: space-between;
			padding: 16px 20px; border-bottom: 1px solid #e5e7eb;
		}
		.o100-room-modal-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #0f172a; }
		.o100-room-modal-close {
			background: none; border: none; font-size: 22px; color: #94a3b8;
			cursor: pointer; padding: 0; line-height: 1;
		}
		.o100-room-modal-close:hover { color: #475569; }
		.o100-room-modal-body { padding: 20px; }
		.o100-room-modal-grid {
			display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 14px;
		}
		.o100-room-modal-field label {
			display: block; font-size: 12px; font-weight: 600; color: #64748b;
			text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 6px;
		}
		.o100-room-modal-field input,
		.o100-room-modal-field textarea {
			width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1;
			border-radius: 6px; font-size: 14px; color: #1e293b;
			box-sizing: border-box; transition: border-color 0.15s;
		}
		.o100-room-modal-field input:focus,
		.o100-room-modal-field textarea:focus {
			outline: none; border-color: #F59322; box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
		}
		.o100-room-modal-footer {
			display: flex; justify-content: flex-end; gap: 10px;
			padding: 14px 20px; border-top: 1px solid #e5e7eb; background: #f8fafc;
		}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var rooms = [];
			try { rooms = JSON.parse( $('#o100_resv_rooms_data').val() ) || []; } catch(e) { rooms = []; }
			// Ensure array
			if (!Array.isArray(rooms)) rooms = [];

			var editIdx = -1; // -1 = adding new

			function renderTable() {
				var $tbody = $('#o100-rooms-tbody');
				$tbody.empty();
				if (rooms.length === 0) {
					$('#o100-rooms-table').hide();
					$('#o100-rooms-empty').show();
				} else {
					$('#o100-rooms-table').show();
					$('#o100-rooms-empty').hide();
					rooms.forEach(function(r, i) {
						var desc = r.desc ? '<span class="o100-room-desc">' + $('<span>').text(r.desc).html() + '</span>' : '<span style="color:#cbd5e1;">—</span>';
						$tbody.append(
							'<tr data-idx="' + i + '">' +
							'<td><strong>' + $('<span>').text(r.name || '').html() + '</strong></td>' +
							'<td style="text-align:center;"><span class="o100-room-badge">' + (parseInt(r.capacity) || '—') + '</span></td>' +
							'<td style="text-align:center;"><span class="o100-room-badge">' + (parseInt(r.quantity) || 1) + '</span></td>' +
							'<td>' + desc + '</td>' +
							'<td class="o100-room-actions" style="text-align:right;">' +
								'<a class="o100-room-edit" data-idx="' + i + '">Edit</a>' +
								'<a class="o100-room-delete" data-idx="' + i + '">Delete</a>' +
							'</td>' +
							'</tr>'
						);
					});
				}
				syncHidden();
			}

			function syncHidden() {
				$('#o100_resv_rooms_data').val( JSON.stringify(rooms) );
			}

			function openModal(idx) {
				editIdx = idx;
				if (idx >= 0 && rooms[idx]) {
					$('#o100-room-modal-title').text('<?php echo esc_js( __( 'Edit Room', 'order100' ) ); ?>');
					$('#o100-room-f-name').val(rooms[idx].name || '');
					$('#o100-room-f-capacity').val(rooms[idx].capacity || '');
					$('#o100-room-f-quantity').val(rooms[idx].quantity || '');
					$('#o100-room-f-desc').val(rooms[idx].desc || '');
				} else {
					$('#o100-room-modal-title').text('<?php echo esc_js( __( 'Add Room', 'order100' ) ); ?>');
					$('#o100-room-f-name').val('');
					$('#o100-room-f-capacity').val('');
					$('#o100-room-f-quantity').val('');
					$('#o100-room-f-desc').val('');
				}
				$('#o100-room-modal-overlay').fadeIn(150);
				setTimeout(function(){ $('#o100-room-f-name').focus(); }, 200);
			}

			function closeModal() {
				$('#o100-room-modal-overlay').fadeOut(150);
			}

			// Add button
			$('#o100-rooms-add-btn').on('click', function(){ openModal(-1); });

			// Edit
			$(document).on('click', '.o100-room-edit', function(e){
				e.preventDefault();
				openModal( parseInt($(this).data('idx')) );
			});

			// Delete
			$(document).on('click', '.o100-room-delete', function(e){
				e.preventDefault();
				var idx = parseInt($(this).data('idx'));
				if (confirm('<?php echo esc_js( __( 'Delete this room?', 'order100' ) ); ?>')) {
					rooms.splice(idx, 1);
					renderTable();
				}
			});

			// Save
			$('#o100-room-modal-save').on('click', function(){
				var name = $.trim( $('#o100-room-f-name').val() );
				if (!name) { $('#o100-room-f-name').focus(); return; }
				var room = {
					name:     name,
					capacity: $('#o100-room-f-capacity').val() || '',
					quantity: $('#o100-room-f-quantity').val() || '1',
					desc:     $.trim( $('#o100-room-f-desc').val() )
				};
				if (editIdx >= 0) {
					rooms[editIdx] = room;
				} else {
					rooms.push(room);
				}
				renderTable();
				closeModal();
			});

			// Cancel / close
			$('#o100-room-modal-cancel, #o100-room-modal-close').on('click', closeModal);
			$('#o100-room-modal-overlay').on('click', function(e){
				if (e.target === this) closeModal();
			});

			// Initial render
			renderTable();
		});
		</script>
		<?php
	}

	public static function save_reservation_rooms( $object_id, $updated ) {
		if ( 'o100_reservation' !== $object_id ) {
			return;
		}
		$raw = isset( $_POST['o100_resv_rooms'] ) ? wp_unslash( $_POST['o100_resv_rooms'] ) : '[]';
		$rooms = json_decode( $raw, true );
		if ( ! is_array( $rooms ) ) {
			$rooms = array();
		}
		// Sanitize each room
		$clean = array();
		foreach ( $rooms as $r ) {
			if ( empty( $r['name'] ) ) continue;
			$clean[] = array(
				'name'     => sanitize_text_field( $r['name'] ),
				'capacity' => absint( isset( $r['capacity'] ) ? $r['capacity'] : 0 ),
				'quantity' => max( 1, absint( isset( $r['quantity'] ) ? $r['quantity'] : 1 ) ),
				'desc'     => sanitize_textarea_field( isset( $r['desc'] ) ? $r['desc'] : '' ),
			);
		}
		$opts = get_option( 'o100_reservation', array() );
		$opts['o100_resv_rooms'] = $clean;
		update_option( 'o100_reservation', $opts );
	}
}

O100_Reservation_Settings::init();
