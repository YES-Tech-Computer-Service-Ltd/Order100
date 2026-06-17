<?php
/**
 * Menu Rules Settings
 *
 * Configures the CMB2 fields for the Menu Rules tab.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Menu_Rules_Settings {

	public static function init() {
		add_action( 'cmb2_admin_init', array( __CLASS__, 'register_settings' ), 20 );
		add_action( 'admin_footer', array( __CLASS__, 'render_conditional_js' ) );
		// Backend Security: Prevent bypassing JS limits
		add_filter( 'pre_update_option_o100_menu_rules', array( __CLASS__, 'enforce_premium_limits' ), 10, 2 );
	}

	/**
	 * Backend Security Validation
	 * Forcefully slice the rules array if a hacker bypasses the JS limitation.
	 */
	public static function enforce_premium_limits( $value, $old_value ) {
		if ( function_exists('O100_License') && ! O100_License()->is_premium() ) {
			if ( isset($value['o100_global_date_rules']) && is_array($value['o100_global_date_rules']) ) {
				if ( count($value['o100_global_date_rules']) > 2 ) {
					// Hard limit to 2 rules
					$value['o100_global_date_rules'] = array_slice($value['o100_global_date_rules'], 0, 2);
				}
			}
		}
		return $value;
	}

	public static function register_settings() {
		// TAB: Menu Rules
		$cmb = new_cmb2_box( array(
			'id'           => 'o100_menu_rules',
			'title'        => __( 'Menu Rules', 'order100' ),
			'object_types' => array( 'options-page' ),
			'parent_slug'   => 'o100_hidden_menu',
			'option_key'   => 'o100_menu_rules',
			'display_cb'   => '__return_false',
		) );

		// ── Scoped CSS injection for modern card layout ──
		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_style_inject',
			'type' => 'title',
			'render_row_cb' => function() {
				?>
				<style>
					/* 1. Remove excessive whitespace at the top of the tab page */
					#o100_menu_rules_style_inject {
						display: none !important;
						height: 0 !important;
						margin: 0 !important;
						padding: 0 !important;
					}

					/* Header inline toggle switch — override WP admin checkbox styles */
					.o100-inline-switch {
						position: relative !important;
						display: inline-block !important;
						width: 44px !important;
						height: 24px !important;
						cursor: pointer !important;
						flex-shrink: 0 !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb {
						appearance: none !important;
						-webkit-appearance: none !important;
						width: 44px !important;
						height: 24px !important;
						border-radius: 12px !important;
						position: relative !important;
						cursor: pointer !important;
						outline: none !important;
						border: none !important;
						transition: background-color 0.2s ease !important;
						margin: 0 !important;
						padding: 0 !important;
						box-shadow: none !important;
						min-width: 44px !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb::before,
					.o100-inline-switch input[type="checkbox"].o100-header-cb:checked::before {
						display: none !important;
						content: '' !important;
						background: none !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb::after {
						content: '' !important;
						position: absolute !important;
						top: 2px !important;
						left: 2px !important;
						width: 20px !important;
						height: 20px !important;
						background-color: #ffffff !important;
						border-radius: 50% !important;
						transition: transform 0.2s ease !important;
						box-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb:not(:checked) {
						background-color: #d1d5db !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb:checked {
						background-color: #F59322 !important;
						background-image: none !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb:checked::after {
						transform: translateX(20px) !important;
					}
					.o100-inline-switch input[type="checkbox"].o100-header-cb:focus {
						box-shadow: 0 0 0 3px rgba(245, 147, 34, 0.2) !important;
					}
					.o100-inline-switch span {
						display: none !important; /* knob handled by ::after pseudo-element */
					}
					/* Hide original checkbox rows replaced by header toggles */
					.o100-hidden-cb-row {
						display: none !important;
					}
					/* Collapsed card: content hidden, title-only with uniform radius */
					.o100-settings-group-card.o100-card-collapsed .o100-settings-group-content {
						display: none !important;
					}
					.o100-settings-group-card.o100-card-collapsed .o100-settings-group-title {
						border-bottom: none !important;
						border-radius: 8px !important;
					}

					/* 2. Style CMB2 checkboxes as modern toggle switches */
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox {
						display: flex !important;
						justify-content: space-between !important;
						align-items: center !important;
						padding: 16px 20px !important;
						margin: 0 !important;
						border-bottom: 1px solid #e2e8f0 !important;
						background: #fff !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td {
						display: flex !important;
						align-items: center !important;
						margin: 0 !important;
						padding: 0 !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td input[type="checkbox"] {
						width: 44px !important;
						height: 24px !important;
						border-radius: 12px !important;
						background-color: #cbd5e1 !important;
						position: relative !important;
						cursor: pointer !important;
						transition: background-color 0.2s ease !important;
						border: none !important;
						outline: none !important;
						box-shadow: none !important;
						padding: 0 !important;
						display: block !important;
						-webkit-appearance: none !important;
						appearance: none !important;
						margin: 0 !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td input[type="checkbox"]::before {
						content: none !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td input[type="checkbox"]::after {
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
						display: block !important;
						transform: none !important;
						border: none !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td input[type="checkbox"]:checked {
						background-color: #F59322 !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td input[type="checkbox"]:checked::after {
						transform: translateX(20px) !important;
					}
					.o100-cmb2-settings-wrap .cmb-row.cmb-type-checkbox .cmb-td label {
						display: none !important;
					}

					/* 3. Visually split "Order Method" block into Delivery and Pickup groups */
					.o100-cmb2-settings-wrap .cmb2-id-o100-delivery-only-pro {
						padding-top: 20px !important;
					}
					.o100-cmb2-settings-wrap .cmb2-id-o100-delivery-only-pro::before {
						content: "Delivery Restrictions" !important;
						display: block !important;
						font-size: 11px !important;
						font-weight: 700 !important;
						text-transform: uppercase !important;
						color: #64748b !important;
						letter-spacing: 0.05em !important;
						margin-bottom: 12px !important;
						width: 100% !important;
					}
					.o100-cmb2-settings-wrap .cmb2-id-o100-pickup-only-pro {
						border-top: 1px dashed #e2e8f0 !important;
						margin-top: 16px !important;
						padding-top: 24px !important;
					}
					.o100-cmb2-settings-wrap .cmb2-id-o100-pickup-only-pro::before {
						content: "Pickup Restrictions" !important;
						display: block !important;
						font-size: 11px !important;
						font-weight: 700 !important;
						text-transform: uppercase !important;
						color: #64748b !important;
						letter-spacing: 0.05em !important;
						margin-bottom: 12px !important;
						width: 100% !important;
					}

					/* 5. Custom Row Classes for Grid */
					.o100-half-row { width: 50% !important; float: left; box-sizing: border-box; clear: none !important; }
					.o100-half-row-last { width: 50% !important; float: left; box-sizing: border-box; clear: none !important; }
					.cmb-repeatable-grouping::after { content: ""; display: table; clear: both; }

					/* 4. Fix select all button sticking to weekdays label in repeater */
					.o100-cmb2-settings-wrap .cmb-multicheck-toggle {
						display: inline-block !important;
						margin-top: 8px !important;
						margin-bottom: 12px !important;
					}

					/* 5. Full-width layout + spacing fixes */
					.o100-cmb2-settings-wrap {
						width: 100% !important;
						max-width: 100% !important;
						box-sizing: border-box !important;
					}
					.o100-cmb2-settings-wrap .cmb2-wrap,
					.o100-cmb2-settings-wrap .postbox,
					.o100-cmb2-settings-wrap .cmb2-metabox,
					.o100-cmb2-settings-wrap .cmb2-options-page {
						width: 100% !important;
						max-width: 100% !important;
						background: transparent !important;
						border: none !important;
						box-shadow: none !important;
						padding: 0 !important;
						margin: 0 !important;
					}
					.o100-cmb2-settings-wrap form.cmb-form {
						width: 100% !important;
						margin: 0 !important;
						padding: 0 !important;
					}
					/* Hide CMB2 native submit button — we use our own Save in header */
					.o100-cmb2-settings-wrap form.cmb-form > p.submit,
					.o100-cmb2-settings-wrap form.cmb-form > .cmb-submit-wrap,
					.o100-cmb2-settings-wrap form.cmb-form > input[type="submit"] {
						display: none !important;
					}

					/* 6. Settings Group Card styles (copied from General Settings) */
					.o100-settings-group-card {
						border: 1px solid #e2e8f0;
						border-radius: 8px;
						margin-bottom: 24px;
						overflow: hidden;
						background: #fff;
						width: 100% !important;
					}
					.o100-settings-group-title {
						background: #f8fafc;
						padding: 16px 24px;
						border-bottom: 1px solid #e2e8f0;
					}
					.o100-settings-group-title h3 {
						margin: 0 0 4px 0;
						font-size: 16px;
						font-weight: 600;
						color: #0f172a;
					}
					.o100-settings-group-title p {
						margin: 0;
						font-size: 13px;
						color: #64748b;
					}
					.o100-settings-group-content {
						padding: 20px 24px;
					}
					.o100-settings-group-content .cmb-row {
						padding: 10px 0 !important;
						margin: 0 !important;
						border-bottom: 1px solid #f1f5f9;
					}
					.o100-settings-group-content .cmb-row:last-child {
						border-bottom: none;
					}
					/* Group repeater description text */
					.o100-settings-group-content .cmb-row.cmb-type-group > .cmb2-metabox-description {
						font-size: 13px;
						color: #64748b;
						margin: 0 0 16px 0;
						padding: 0;
						font-style: normal;
					}
					/* Group repeater container */
					.o100-settings-group-content .cmb-repeatable-group {
						border: none;
						margin: 0;
						padding: 0;
					}
					.o100-settings-group-content .cmb-repeatable-grouping {
						border: 1px solid #e2e8f0 !important;
						border-radius: 8px !important;
						margin-bottom: 12px !important;
						overflow: hidden;
						background: #fff;
					}
					.o100-settings-group-content .cmb-repeatable-grouping .cmb-group-title {
						background: #f8fafc !important;
						padding: 12px 16px !important;
						border-bottom: 1px solid #e2e8f0 !important;
						font-weight: 600 !important;
						font-size: 14px !important;
						color: #0f172a !important;
					}
					/* Add / Remove buttons */
					.o100-settings-group-content .cmb-add-group-row,
					.o100-settings-group-content .cmb-add-row {
						margin-top: 8px !important;
					}
					.o100-settings-group-content .cmb-add-group-row button,
					.o100-settings-group-content .cmb-add-row button {
						background: #fff !important;
						border: 1px solid #e2e8f0 !important;
						color: #334155 !important;
						border-radius: 6px !important;
						padding: 8px 16px !important;
						font-size: 13px !important;
						font-weight: 500 !important;
						cursor: pointer !important;
						transition: all 0.15s ease !important;
					}
					.o100-settings-group-content .cmb-add-group-row button:hover,
					.o100-settings-group-content .cmb-add-row button:hover {
						background: #f8fafc !important;
						border-color: #cbd5e1 !important;
					}
					/* PRO badge */
					.o100-pro-badge {
						display: inline-block;
						background: linear-gradient(135deg, #F59322, #d97b06);
						color: #fff;
						font-size: 10px;
						font-weight: 700;
						letter-spacing: 0.5px;
						text-transform: uppercase;
						padding: 2px 8px;
						border-radius: 4px;
						margin-left: 8px;
						vertical-align: middle;
						line-height: 18px;
					}
				</style>

				<?php
			},
		) );

		// ── Menu by Order Method section ──
		$_mr_deli_opts = get_option('o100_delivery', array());
		$_mr_pick_opts = get_option('o100_pickup', array());
		$_mr_resv_opts = get_option('o100_reservation', array());
		$_mr_deli_on = !empty($_mr_deli_opts['o100_enable_delivery']) && $_mr_deli_opts['o100_enable_delivery'] === 'on';
		$_mr_pick_on = !empty($_mr_pick_opts['o100_enable_pickup']) && $_mr_pick_opts['o100_enable_pickup'] === 'on';
		$_mr_resv_on = !empty($_mr_resv_opts['o100_enable_reservation']) && $_mr_resv_opts['o100_enable_reservation'] === 'on';
		$_mr_count = (int)$_mr_deli_on + (int)$_mr_pick_on + (int)$_mr_resv_on;

		$_mr_opts = get_option('o100_menu_rules', array());
		$_mr_date_on = !empty($_mr_opts['o100_menu_date']) && $_mr_opts['o100_menu_date'] === 'on';
		$_mr_method_on = !empty($_mr_opts['o100_menu_method']) && $_mr_opts['o100_menu_method'] === 'on';
		$_mr_ecom_on = !empty($_mr_opts['o100_enable_standard_ecom']) && $_mr_opts['o100_enable_standard_ecom'] === 'on';

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_date',
			'type' => 'title',
			'render_row_cb' => function() use ($_mr_date_on) {
				$bg = $_mr_date_on ? '#F59322' : '#d1d5db';
				$pos = $_mr_date_on ? '22px' : '2px';
				$chk = $_mr_date_on ? ' checked' : '';
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title" style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px;"><div><h3 style="margin:0 0 4px 0; font-size:16px; font-weight:600; color:#0f172a;">' . esc_html__('Scheduled Menus', 'order100') . '<span class="o100-pro-badge">PRO</span></h3><p style="margin:0; font-size:13px; color:#64748b;">' . esc_html__('Create scheduled menus — restrict specific products to only be available on certain days or date ranges.', 'order100') . '</p></div><label class="o100-inline-switch" data-sync="o100_menu_date" style="position:relative; display:inline-block; width:44px; height:24px; cursor:pointer; flex-shrink:0;"><input type="checkbox" class="o100-header-cb"' . $chk . ' style="appearance:none; -webkit-appearance:none; width:44px; height:24px; background-color:' . $bg . '; border-radius:12px; position:relative; cursor:pointer; outline:none; border:none; transition:background-color 0.2s; margin:0;"><span style="position:absolute; top:2px; left:' . $pos . '; width:20px; height:20px; background:#fff; border-radius:50%; transition:left 0.2s; box-shadow:0 1px 2px rgba(0,0,0,0.2); pointer-events:none;"></span></label></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Scheduled Menus', 'order100' ),
			'id'   => 'o100_menu_date',
			'type' => 'checkbox',
			'render_row_cb' => function( $field_args, $field ) {
				echo '<div class="o100-hidden-cb-row">';
				echo '<input type="checkbox" class="cmb2-option" name="' . esc_attr( $field->args( '_name' ) ) . '" id="' . esc_attr( $field->args( 'id' ) ) . '" value="on"' . ( $field->escaped_value() === 'on' ? ' checked="checked"' : '' ) . '>';
				echo '</div>';
			},
		) );

		$group_id = $cmb->add_field( array(
			'id'          => 'o100_global_date_rules',
			'type'        => 'group',
			'description' => __( 'Add global schedule rules. Products mapped here will ONLY be available on the dates you specify.', 'order100' ),
			'options'     => array(
				'group_title'       => __( 'Schedule Rule {#}', 'order100' ),
				'add_button'        => __( 'Add Another Rule', 'order100' ),
				'remove_button'     => __( 'Remove Rule', 'order100' ),
				'sortable'          => true,
				'closed'            => true,
			),
			'attributes' => array(
				'data-conditional-id' => 'o100_menu_date',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Step 1: Rule Action (Display Mode)', 'order100' ),
			'desc' => __( 'Choose how this rule affects product visibility and checkout.', 'order100' ),
			'id'   => 'o100_rule_step1_title',
			'type' => 'title',
		) );

		$cmb->add_group_field( $group_id, array(
			'name'             => __( 'Rule Action', 'order100' ),
			'desc'             => __( 'Choose how this rule affects the product availability and checkout restrictions.', 'order100' ),
			'id'               => 'o100_rule_action',
			'type'             => 'radio_inline',
			'default'          => 'flexible_show',
			'options'          => array(
				'strict_show'   => __( 'Strict Show (Only visible & orderable during time)', 'order100' ),
				'flexible_show' => __( 'Flexible Show (Always visible, restricts checkout time)', 'order100' ),
				'hide'          => __( 'Hide (Unavailable during time)', 'order100' ),
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Step 2: Assign Dates', 'order100' ),
			'desc' => __( 'Choose whether this rule applies to recurring weekdays, a specific date range, or both.', 'order100' ),
			'id'   => 'o100_rule_step2_title_dates',
			'type' => 'title',
		) );

		$cmb->add_group_field( $group_id, array(
			'name'             => __( 'Rule Type', 'order100' ),
			'id'               => 'o100_rule_type',
			'type'             => 'radio_inline',
			'default'          => 'weekdays',
			'options'          => array(
				'weekdays'   => __( 'Recurring Weekdays', 'order100' ),
				'date_range' => __( 'Specific Date Range', 'order100' ),
				'both'       => __( 'Both (Intersection)', 'order100' ),
			),
			'attributes' => array(
				'class' => 'o100-rule-type-toggle',
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name'    => __( 'Select Weekdays', 'order100' ),
			'id'      => 'o100_rule_days',
			'type'    => 'multicheck_inline',
			'options' => array(
				'Mon' => __( 'Monday', 'order100' ),
				'Tue' => __( 'Tuesday', 'order100' ),
				'Wed' => __( 'Wednesday', 'order100' ),
				'Thu' => __( 'Thursday', 'order100' ),
				'Fri' => __( 'Friday', 'order100' ),
				'Sat' => __( 'Saturday', 'order100' ),
				'Sun' => __( 'Sunday', 'order100' ),
			),
			'attributes' => array(
				'data-rule-field' => 'weekdays',
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Select Date(s)', 'order100' ),
			'desc' => __( 'Select a specific date or a date range.', 'order100' ),
			'id'   => 'o100_rule_date_range',
			'type' => 'text',
			'attributes' => array(
				'data-rule-field' => 'date_range',
				'placeholder' => 'YYYY-MM-DD to YYYY-MM-DD',
				'class' => 'o100-flatpickr-range',
			),
		) );



		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Step 3: Assign Time', 'order100' ),
			'desc' => __( 'Configure the time of day this rule is active.', 'order100' ),
			'id'   => 'o100_rule_step3_title_time',
			'type' => 'title',
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Start Time (Optional)', 'order100' ),
			'desc' => __( 'Time of day this rule starts (e.g. 10:00). Leave blank for all day.', 'order100' ),
			'id'   => 'o100_rule_time_start',
			'type' => 'text_time',
			'attributes' => array(
				'placeholder' => 'HH:MM',
			),
			'row_classes' => 'o100-half-row',
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'End Time (Optional)', 'order100' ),
			'desc' => __( 'Time of day this rule ends (e.g. 14:00). Leave blank for all day.', 'order100' ),
			'id'   => 'o100_rule_time_end',
			'type' => 'text_time',
			'attributes' => array(
				'placeholder' => 'HH:MM',
			),
			'row_classes' => 'o100-half-row-last',
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Step 4: Assign Target', 'order100' ),
			'desc' => __( 'Select which products or categories this date rule applies to.', 'order100' ),
			'id'   => 'o100_rule_step4_title',
			'type' => 'title',
		) );

		$cmb->add_group_field( $group_id, array(
			'name'             => __( 'Assign To', 'order100' ),
			'id'               => 'o100_rule_assign_type',
			'type'             => 'select',
			'show_option_none' => false,
			'default'          => 'products',
			'options'          => array(
				'products'   => __( 'Specific Products', 'order100' ),
				'categories' => __( 'Specific Categories', 'order100' ),
			),
			'attributes' => array(
				'class' => 'o100-rule-assign-toggle',
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Select Products', 'order100' ),
			'id'   => 'o100_rule_products',
			'type' => 'o100_product_search',
			'attributes' => array(
				'data-assign-field' => 'products',
			),
		) );

		$cmb->add_group_field( $group_id, array(
			'name' => __( 'Select Categories', 'order100' ),
			'id'   => 'o100_rule_categories',
			'type' => 'o100_category_search',
			'attributes' => array(
				'data-assign-field' => 'categories',
			),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_date_end',
			'type' => 'title',
			'render_row_cb' => function() {
				echo '</div></div>';
			}
		) );



		if ( $_mr_count > 1 ) {

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_method',
			'type' => 'title',
			'render_row_cb' => function() use ($_mr_method_on) {
				$bg = $_mr_method_on ? '#F59322' : '#d1d5db';
				$pos = $_mr_method_on ? '22px' : '2px';
				$chk = $_mr_method_on ? ' checked' : '';
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title" style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px;"><div><h3 style="margin:0 0 4px 0; font-size:16px; font-weight:600; color:#0f172a;">' . esc_html__('Menu by Order Method', 'order100') . '</h3><p style="margin:0; font-size:13px; color:#64748b;">' . esc_html__('Configure which products are restricted to specific order methods.', 'order100') . '</p></div><label class="o100-inline-switch" data-sync="o100_menu_method" style="position:relative; display:inline-block; width:44px; height:24px; cursor:pointer; flex-shrink:0;"><input type="checkbox" class="o100-header-cb"' . $chk . ' style="appearance:none; -webkit-appearance:none; width:44px; height:24px; background-color:' . $bg . '; border-radius:12px; position:relative; cursor:pointer; outline:none; border:none; transition:background-color 0.2s; margin:0;"><span style="position:absolute; top:2px; left:' . $pos . '; width:20px; height:20px; background:#fff; border-radius:50%; transition:left 0.2s; box-shadow:0 1px 2px rgba(0,0,0,0.2); pointer-events:none;"></span></label></div><div class="o100-settings-group-content">';
			},
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Order Method Filtering', 'order100' ),
			'id'   => 'o100_menu_method',
			'type' => 'checkbox',
			'render_row_cb' => function( $field_args, $field ) {
				echo '<div class="o100-hidden-cb-row">';
				echo '<input type="checkbox" class="cmb2-option" name="' . esc_attr( $field->args( '_name' ) ) . '" id="' . esc_attr( $field->args( 'id' ) ) . '" value="on"' . ( $field->escaped_value() === 'on' ? ' checked="checked"' : '' ) . '>';
				echo '</div>';
			},
		) );

		if ( $_mr_deli_on ) {
		$cmb->add_field( array(
			'name' => esc_html__( 'Delivery Only Products', 'order100' ),
			'desc' => esc_html__( 'Select products that are ONLY available for Delivery.', 'order100' ),
			'id'   => 'o100_delivery_only_pro',
			'type' => 'o100_product_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method' ),
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Delivery Only Categories', 'order100' ),
			'desc' => esc_html__( 'All products under these categories will ONLY be available for Delivery.', 'order100' ),
			'id'   => 'o100_delivery_only_cat',
			'type' => 'o100_category_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method' ),
		) );
		}

		if ( $_mr_pick_on ) {
		$cmb->add_field( array(
			'name' => esc_html__( 'Pickup Only Products', 'order100' ),
			'desc' => esc_html__( 'Select products that are ONLY available for Pickup.', 'order100' ),
			'id'   => 'o100_pickup_only_pro',
			'type' => 'o100_product_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method' ),
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Pickup Only Categories', 'order100' ),
			'desc' => esc_html__( 'All products under these categories will ONLY be available for Pickup.', 'order100' ),
			'id'   => 'o100_pickup_only_cat',
			'type' => 'o100_category_search',
			'attributes' => array( 'data-conditional-id' => 'o100_menu_method' ),
		) );
		}

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_method_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; },
		) );

		} // end $_mr_count > 1


				$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_disable',
			'type' => 'title',
			'render_row_cb' => function() use ($_mr_ecom_on) {
				$bg = $_mr_ecom_on ? '#F59322' : '#d1d5db';
				$pos = $_mr_ecom_on ? '22px' : '2px';
				$chk = $_mr_ecom_on ? ' checked' : '';
				echo '<div class="o100-settings-group-card"><div class="o100-settings-group-title" style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px;"><div><h3 style="margin:0 0 4px 0; font-size:16px; font-weight:600; color:#0f172a;">' . esc_html__('Standard eCommerce Mode', 'order100') . '</h3><p style="margin:0; font-size:13px; color:#64748b;">' . esc_html__('Select products/categories that should behave like normal eCommerce products (no popup, no extra options).', 'order100') . '</p></div><label class="o100-inline-switch" data-sync="o100_enable_standard_ecom" style="position:relative; display:inline-block; width:44px; height:24px; cursor:pointer; flex-shrink:0;"><input type="checkbox" class="o100-header-cb"' . $chk . ' style="appearance:none; -webkit-appearance:none; width:44px; height:24px; background-color:' . $bg . '; border-radius:12px; position:relative; cursor:pointer; outline:none; border:none; transition:background-color 0.2s; margin:0;"><span style="position:absolute; top:2px; left:' . $pos . '; width:20px; height:20px; background:#fff; border-radius:50%; transition:left 0.2s; box-shadow:0 1px 2px rgba(0,0,0,0.2); pointer-events:none;"></span></label></div><div class="o100-settings-group-content">';
			}
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Standard eCommerce Mode', 'order100' ),
			'id'   => 'o100_enable_standard_ecom',
			'type' => 'checkbox',
			'render_row_cb' => function( $field_args, $field ) {
				echo '<div class="o100-hidden-cb-row">';
				echo '<input type="checkbox" class="cmb2-option" name="' . esc_attr( $field->args( '_name' ) ) . '" id="' . esc_attr( $field->args( 'id' ) ) . '" value="on"' . ( $field->escaped_value() === 'on' ? ' checked="checked"' : '' ) . '>';
				echo '</div>';
			},
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Disable Food fields in Products', 'order100' ),
			'desc' => esc_html__( 'Select specific products to exclude from food ordering fields (Extra Options, delivery selection, etc.)', 'order100' ),
			'id'   => 'o100_disable_food_pro',
			'type' => 'o100_product_search',
			'attributes' => array(
				'data-conditional-id' => 'o100_enable_standard_ecom',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Disable Food fields in Category', 'order100' ),
			'desc' => esc_html__( 'All products under the selected categories will not show food ordering fields.', 'order100' ),
			'id'   => 'o100_disable_food_cat',
			'type' => 'o100_category_search',
			'attributes' => array(
				'data-conditional-id' => 'o100_enable_standard_ecom',
				'data-conditional-value' => 'on',
			),
		) );

		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_sec_disable_end',
			'type' => 'title',
			'render_row_cb' => function() { echo '</div></div>'; }
		) );

		// ── Close the .o100-cmb2-settings-wrap wrapper ──
		$cmb->add_field( array(
			'id'   => 'o100_menu_rules_wrap_end',
			'type' => 'title',
			'render_row_cb' => function() { echo ''; },
		) );
	}

	public static function render_conditional_js() {
		// Only run on the Menu Maker page where CMB2 is loaded and tab=menu_rules
		$page = isset( $_GET['page'] ) ? sanitize_text_field($_GET['page']) : '';
		
		if ( $page !== 'o100-menu-maker' ) {
			return;
		}

		?>
		<script>
			jQuery(document).ready(function($) {
				// Sync header toggles → hidden CMB2 checkboxes + toggle content visibility
				$('.o100-inline-switch').each(function() {
					var $label = $(this);
					var syncId = $label.attr('data-sync');
					var $headerCb = $label.find('.o100-header-cb');
					var $card = $label.closest('.o100-settings-group-card');
					var $originalCb = $('#' + syncId);
					
					// Set initial collapsed state
					if (!$headerCb.is(':checked')) {
						$card.addClass('o100-card-collapsed');
					}
					
					$headerCb.on('change', function() {
						var on = $(this).is(':checked');
						if (on) {
							$card.removeClass('o100-card-collapsed');
						} else {
							$card.addClass('o100-card-collapsed');
						}
						if ($originalCb.length) {
							$originalCb.prop('checked', on).trigger('change');
						}
					});
				});

				// --- Menu Rules Conditional Logic ---
				function updateMenuRulesUI() {
					$('.cmb-repeatable-grouping').each(function() {
						var $group = $(this);
						// Rule Type (Availability)
						var ruleType = $group.find('input[name*="[o100_rule_type]"]:checked').val() || 'weekdays';
						$group.find('[data-rule-field]').closest('.cmb-row').hide();
						if (ruleType === 'weekdays' || ruleType === 'both') {
							$group.find('[data-rule-field="weekdays"]').closest('.cmb-row').show();
						}
						if (ruleType === 'date_range' || ruleType === 'both') {
							$group.find('[data-rule-field="date_range"]').closest('.cmb-row').show();
						}
						
						// Assign Target
						var assignType = $group.find('select[name*="[o100_rule_assign_type]"]').val() || 'products';
						$group.find('[data-assign-field]').closest('.cmb-row').hide();
						if(assignType) {
							$group.find('[data-assign-field="' + assignType + '"]').closest('.cmb-row').show();
						}
					});
				}
				// Bind events for dynamically added groups too by delegating
				$(document).on('change', 'input[name*="[o100_rule_type]"], select[name*="[o100_rule_assign_type]"]', updateMenuRulesUI);
				// Also bind to CMB2's custom event when a new row is added
				$(document).on('cmb2_add_row', updateMenuRulesUI);
				// Initial run
				setTimeout(updateMenuRulesUI, 100);

				// --- Freemius Limits Interception ---
				var isPremium = <?php echo ( function_exists('O100_License') && O100_License()->is_premium() ) ? 'true' : 'false'; ?>;
				var maxRules = 1; 
				
				// Use capture phase to intercept CMB2's click BEFORE jQuery handles it
				document.addEventListener('click', function(e) {
					var target = e.target.closest('.cmb-add-group-row');
					if ( target && ! isPremium ) {
						var $group = $(target).closest('.cmb2-id-o100-global-date-rules');
						if ( $group.length ) {
							var ruleCount = $group.find('.cmb-repeatable-grouping').not('.o100-empty-placeholder').not('.cmb-row-hidden').length;
							if ( ruleCount >= maxRules ) {
								e.stopPropagation();
								e.preventDefault();
								showMenuRuleModal();
							}
						}
					}
				}, true); // true = capture phase!

				function showMenuRuleModal() {
					// Show custom upgrade modal
					if ( $('#o100-pro-modal').length === 0 ) {
						$('body').append('<div id="o100-pro-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:99999; display:flex; align-items:center; justify-content:center;">' +
							'<div style="background:#fff; border-radius:12px; position:relative; width:90%; max-width:500px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">' +
								'<button type="button" class="o100-pro-close" style="position:absolute; top:12px; right:12px; background:none; border:none; cursor:pointer; color:#64748b; padding:4px; z-index:10;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>' +
								'<div class="o100-pro-content-wrap" style="padding:40px; text-align:center;"></div>' +
							'</div>' +
						'</div>');
						
						// Insert the PHP rendered upgrade notice into the modal
						// Override the generic top-border style for this specific modal
						var modalHtml = $('#o100-menu-rules-upgrade-template').html();
						$('#o100-pro-modal .o100-pro-content-wrap').html(modalHtml);
						// Remove the yellow border from the inner card since we are already inside a modal card
						$('#o100-pro-modal .o100-pro-content-wrap > div').css({
							'box-shadow': 'none',
							'border': 'none',
							'padding': '0',
							'margin': '0'
						});
						
						$(document).on('click', '.o100-pro-close', function(){
							$('#o100-pro-modal').fadeOut(200);
						});
					}
					$('#o100-pro-modal').fadeIn(200);
				}

				function checkRuleLimits() {
					var $group = $('.cmb2-id-o100-global-date-rules');
					if ( ! $group.length ) return;
					var $btn = $group.find('.cmb-add-group-row');

					// Remove default empty row if it hasn't been saved yet
					// CMB2 always adds an empty row if the database option is empty.
					var $firstRow = $group.find('.cmb-repeatable-grouping').first();
					if ($firstRow.length === 1 && $group.find('.cmb-repeatable-grouping').length === 1) {
						var timeStart = $firstRow.find('input[name*="[o100_rule_time_start]"]').val();
						var timeEnd = $firstRow.find('input[name*="[o100_rule_time_end]"]').val();
						var dateRange = $firstRow.find('input[name*="[o100_rule_date_range]"]').val();
						var checkboxes = $firstRow.find('input[type="checkbox"]:checked').length;
						var products = $firstRow.find('input[name*="[o100_rule_products]"]').val();
						var cats = $firstRow.find('input[name*="[o100_rule_cats]"]').val();

						if (!timeStart && !timeEnd && !dateRange && checkboxes === 0 && !products && !cats) {
							$firstRow.hide();
							$firstRow.addClass('o100-empty-placeholder'); // Mark it so we don't count it
						}
					}

					if ( ! isPremium ) {
						// Ensure button is visible so they can click it and trigger the modal
						$btn.show();
						
						// Inject beautiful PRO banner
						if ( $group.find('.o100-pro-inline-banner').length === 0 ) {
							var bannerHtml = '<div class="o100-pro-inline-banner" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin-top: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">' +
								'<div style="flex: 1; padding-right: 20px;">' +
									'<h4 style="margin: 0 0 8px 0; font-size: 16px; color: #92400e; display: flex; align-items: center;"><span class="dashicons dashicons-lock" style="margin-right: 5px;"></span> Upgrade to PRO for Unlimited Rules</h4>' +
									'<p style="margin: 0; font-size: 13px; color: #b45309; line-height: 1.5;">The Free version allows <strong>1 scheduled menu rule</strong>. Upgrade to unlock unlimited rules! Perfect for configuring complex holiday schedules, multiple recurring weekly specials, and advanced time-of-day routing.</p>' +
								'</div>' +
								'<div>' +
									'<a href="#" class="button button-primary o100-upgrade-link" style="background: #ea580c; border-color: #ea580c; text-shadow: none; font-weight: 600; padding: 4px 16px;">View PRO Features</a>' +
								'</div>' +
							'</div>';
							$btn.after(bannerHtml);
						}
						return;
					}
					
					var ruleCount = $group.find('.cmb-repeatable-grouping').not('.o100-empty-placeholder').not('.cmb-row-hidden').length;
					$group.find('.o100-rule-limit-warning').remove();
					if ( ruleCount >= 100 ) {
						$btn.hide();
						if ( $group.find('.o100-rule-limit-warning').length === 0 ) {
							$btn.after('<p class="o100-rule-limit-warning" style="color:#d63638; margin-top:10px;">Maximum of 100 rules reached.</p>');
						}
					} else {
						$btn.show();
					}
				}
				
				// Initial check and on row add/remove
				setTimeout(checkRuleLimits, 200);
				$(document).on('cmb2_add_row cmb2_remove_row', checkRuleLimits);
				
				// Handle upgrade link click
				$(document).on('click', '.o100-upgrade-link', function(e) {
					e.preventDefault();
					showMenuRuleModal();
				});

				// Use capture phase to intercept CMB2's click BEFORE jQuery handles it
				document.addEventListener('click', function(e) {
					var target = e.target.closest('.cmb-add-group-row');
					if ( target && ! isPremium ) {
						var $group = $(target).closest('.cmb2-id-o100-global-date-rules');
						if ( $group.length ) {
							var ruleCount = $group.find('.cmb-repeatable-grouping').length;
							if ( ruleCount >= maxRules ) {
								e.stopPropagation();
								e.preventDefault();
								showMenuRuleModal();
							}
						}
					}
				}, true); // true = capture phase!

				function showMenuRuleModal() {
					// Show custom upgrade modal
					if ( $('#o100-pro-modal').length === 0 ) {
						$('body').append('<div id="o100-pro-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:99999; display:flex; align-items:center; justify-content:center;">' +
							'<div style="background:#fff; border-radius:12px; position:relative; width:90%; max-width:500px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">' +
								'<button type="button" class="o100-pro-close" style="position:absolute; top:12px; right:12px; background:none; border:none; cursor:pointer; color:#64748b; padding:4px; z-index:10;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>' +
								'<div class="o100-pro-content-wrap" style="padding:40px; text-align:center;"></div>' +
							'</div>' +
						'</div>');
						
						// Insert the PHP rendered upgrade notice into the modal
						// Override the generic top-border style for this specific modal
						var modalHtml = $('#o100-menu-rules-upgrade-template').html();
						$('#o100-pro-modal .o100-pro-content-wrap').html(modalHtml);
						// Remove the yellow border from the inner card since we are already inside a modal card
						$('#o100-pro-modal .o100-pro-content-wrap > div').css({
							'box-shadow': 'none',
							'border': 'none',
							'padding': '0',
							'margin': '0'
						});
						
						$(document).on('click', '.o100-pro-close', function(){
							$('#o100-pro-modal').fadeOut(200);
						});
					}
					$('#o100-pro-modal').fadeIn(200);
				}

				});
		</script>
		<?php
		if ( function_exists('O100_License') && ! O100_License()->is_premium() ) {
			echo '<div id="o100-menu-rules-upgrade-template" style="display:none;">';
			O100_License()->render_upgrade_notice( 'Unlimited Menu Rules', 'Want to schedule menus for every day of the week or special holidays? Upgrade to Pro to create unlimited Menu by Date rules!' );
			echo '</div>';
		}
	}
}

O100_Menu_Rules_Settings::init();
