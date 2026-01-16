<?php
/**
 * Fluent Add-ons Manager
 * Handles the React/Vue style AJAX modal UI for managing Global Product Add-ons.
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Fluent_Addons {

	public static function init() {
		add_action( 'wp_ajax_o100_get_addon_groups', array( __CLASS__, 'ajax_get_groups' ) );
		add_action( 'wp_ajax_o100_save_addon_group', array( __CLASS__, 'ajax_save_group' ) );
		add_action( 'wp_ajax_o100_delete_addon_group', array( __CLASS__, 'ajax_delete_group' ) );
		add_action( 'wp_ajax_o100_duplicate_addon_group', array( __CLASS__, 'ajax_duplicate_group' ) );
		add_action( 'wp_ajax_o100_search_products', array( __CLASS__, 'ajax_search_products' ) );
		add_action( 'wp_ajax_o100_get_products_by_ids', array( __CLASS__, 'ajax_get_products_by_ids' ) );
		add_action( 'wp_ajax_o100_save_groups_order', array( __CLASS__, 'ajax_save_groups_order' ) );
	}

	public static function render_manager() {
		?>
		<div class="o100-fluent-addons-app" style="margin-top:20px; padding:20px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; width:100%; box-sizing:border-box;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">
				<h3 style="margin:0; font-size:16px; color:#0f172a;"><?php esc_html_e( 'Global Option Templates', 'order100' ); ?></h3>
				<button type="button" class="button button-primary o100-add-addon-btn" style="background:#2563eb; border-color:#2563eb; box-shadow:none;">
					<span class="dashicons dashicons-plus-alt2" style="line-height:inherit; margin-right:4px;"></span>
					<?php esc_html_e( 'Add New Option', 'order100' ); ?>
				</button>
			</div>
			
			<div class="o100-addons-list-container">
				<p style="color:#64748b; font-size:14px; text-align:center; padding: 40px 0; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
					<?php esc_html_e( 'Loading options...', 'order100' ); ?>
				</p>
			</div>

			<!-- Modal -->
			<div class="o100-addon-modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
				<div class="o100-addon-modal-content" style="background:#fff; width:100%; max-width:800px; border-radius:8px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1); display:flex; flex-direction:column; max-height:90vh;">
					<!-- Header -->
					<div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
						<h3 style="margin:0; font-size:16px; font-weight:600; color:#0f172a;" id="o100-addon-modal-title"><?php esc_html_e( 'Manage Option Template', 'order100' ); ?></h3>
						<span class="dashicons dashicons-no-alt o100-close-addon-modal" style="cursor:pointer; color:#64748b;"></span>
					</div>
					
					<!-- Tabs -->
					<div style="display:flex; border-bottom:1px solid #e2e8f0; background:#f8fafc; padding:0 20px;">
						<a href="#" class="o100-addon-tab active" data-tab="general" style="padding:12px 16px; font-size:14px; font-weight:500; color:#2563eb; border-bottom:2px solid #2563eb; text-decoration:none; margin-bottom:-1px;"><?php esc_html_e( 'General', 'order100' ); ?></a>
						<a href="#" class="o100-addon-tab" data-tab="conditions" style="padding:12px 16px; font-size:14px; font-weight:500; color:#64748b; border-bottom:2px solid transparent; text-decoration:none; margin-bottom:-1px;"><?php esc_html_e( 'Conditional logic', 'order100' ); ?></a>
					</div>

					<!-- Body -->
					<div style="padding:20px; overflow-y:auto; flex-grow:1; background:#fbfbfb;">
						<input type="hidden" id="o100_ad_id" value="">
						
						<!-- GENERAL TAB -->
						<div id="o100-addon-tab-general" class="o100-addon-tab-content">
							<div class="o100-modal-row" style="display:flex; gap:15px; margin-bottom:15px;">
								<div class="o100-modal-col" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Group Name', 'order100' ); ?></label>
									<input type="text" id="o100_ad_name" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;" placeholder="e.g. Select Size">
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Give this option group a descriptive title.', 'order100'); ?></p>
								</div>
								<div class="o100-modal-col" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Selection Type', 'order100' ); ?></label>
									<select id="o100_ad_type" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;">
										<option value=""><?php esc_html_e( 'Checkboxes (Multiple choice)', 'order100' ); ?></option>
										<option value="radio"><?php esc_html_e( 'Radio Buttons (Single choice)', 'order100' ); ?></option>
										<option value="select"><?php esc_html_e( 'Dropdown Select', 'order100' ); ?></option>
										<option value="text"><?php esc_html_e( 'Text Input', 'order100' ); ?></option>
										<option value="textarea"><?php esc_html_e( 'Textarea', 'order100' ); ?></option>
										<option value="quantity"><?php esc_html_e( 'Quantity Input', 'order100' ); ?></option>
									</select>
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('How should customers interact with these choices?', 'order100'); ?></p>
								</div>
							</div>

							<div class="o100-modal-row" style="display:flex; gap:15px; margin-bottom:15px;">
								<div class="o100-modal-col" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Is Required?', 'order100' ); ?></label>
									<select id="o100_ad_req" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;">
										<option value=""><?php esc_html_e( 'No (Optional)', 'order100' ); ?></option>
										<option value="yes"><?php esc_html_e( 'Yes (Mandatory)', 'order100' ); ?></option>
									</select>
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Must the customer make a selection?', 'order100'); ?></p>
								</div>
								<div class="o100-modal-col" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'UI Layout Style', 'order100' ); ?></label>
									<select id="o100_ad_display" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;">
										<option value=""><?php esc_html_e( 'Default', 'order100' ); ?></option>
										<option value="nor"><?php esc_html_e( 'Normal (Always expanded)', 'order100' ); ?></option>
										<option value="accor"><?php esc_html_e( 'Accordion (Collapsible)', 'order100' ); ?></option>
									</select>
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('How this group visually appears on the product page.', 'order100'); ?></p>
								</div>
							</div>

							<div class="o100-modal-row" style="display:flex; gap:15px; margin-bottom:15px; padding:15px; background:#eff6ff; border-radius:6px; border:1px solid #bfdbfe;">
								<div class="o100-modal-col" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px; color:#1e40af;"><?php esc_html_e( 'Assign To', 'order100' ); ?></label>
									<select id="o100_ad_applyto" style="width:100%; padding:6px 10px; border:1px solid #93c5fd; border-radius:4px;">
										<option value="all"><?php esc_html_e( 'All Products', 'order100' ); ?></option>
										<option value="categories"><?php esc_html_e( 'Specific Categories', 'order100' ); ?></option>
										<option value="products"><?php esc_html_e( 'Specific Products', 'order100' ); ?></option>
									</select>
									<p style="margin:4px 0 0; font-size:11px; color:#1e3a8a;"><?php esc_html_e('Where should this option group appear?', 'order100'); ?></p>
								</div>
								
								<div class="o100-modal-col o100-apply-cat" style="flex:2; display:none;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px; color:#1e40af;"><?php esc_html_e( 'Select Categories', 'order100' ); ?></label>
									<div style="max-height:100px; overflow-y:auto; border:1px solid #93c5fd; background:#fff; padding:5px; border-radius:4px;">
										<?php
										$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
										if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
											foreach ( $cats as $cat ) {
												echo '<label style="display:inline-block; margin-right:10px; margin-bottom:5px;"><input type="checkbox" class="o100_ad_cats" value="' . esc_attr( $cat->term_id ) . '"> ' . esc_html( $cat->name ) . '</label>';
											}
										}
										?>
									</div>
								</div>

								<div class="o100-modal-col o100-apply-prod" style="flex:2; display:none;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px; color:#1e40af;"><?php esc_html_e( 'Select Products', 'order100' ); ?></label>
									<select id="o100_ad_prods" multiple="multiple" style="width:100%; padding:6px 10px; border:1px solid #93c5fd; border-radius:4px;"></select>
									<p style="margin:4px 0 0; font-size:11px; color:#1e3a8a;"><?php esc_html_e('Type 2 or more characters to search products.', 'order100'); ?></p>
								</div>
							</div>

							<div style="border-top:1px solid #e2e8f0; margin:20px 0;"></div>

							<div class="o100-modal-row" style="display:flex; gap:15px; margin-bottom:15px;">
								<div class="o100-modal-col o100-field-min-op" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Minimum Checkboxes (Selection Limit)', 'order100' ); ?></label>
									<input type="number" id="o100_ad_min" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;" placeholder="0">
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Min number of different choices the user must select.', 'order100'); ?></p>
								</div>
								<div class="o100-modal-col o100-field-max-op" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Maximum Checkboxes (Selection Limit)', 'order100' ); ?></label>
									<input type="number" id="o100_ad_max" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;" placeholder="0">
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Max number of different choices the user can select.', 'order100'); ?></p>
								</div>
							</div>

							<div class="o100-modal-row" style="display:flex; gap:15px; margin-bottom:15px;">
								<div class="o100-modal-col o100-field-min-qty" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Minimum Total Quantity', 'order100' ); ?></label>
									<input type="number" id="o100_ad_min_qty" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;" placeholder="0">
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Sum of all quantities must be at least this number.', 'order100'); ?></p>
								</div>
								<div class="o100-modal-col o100-field-max-qty" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Maximum Total Quantity', 'order100' ); ?></label>
									<input type="number" id="o100_ad_max_qty" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;" placeholder="0">
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Sum of all quantities cannot exceed this number.', 'order100'); ?></p>
								</div>
							</div>

							<div class="o100-modal-row" style="display:flex; gap:15px; margin-bottom:15px;">
								<div class="o100-modal-col o100-field-enb-img" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Enable Images', 'order100' ); ?></label>
									<select id="o100_ad_img" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;">
										<option value=""><?php esc_html_e( 'No', 'order100' ); ?></option>
										<option value="yes"><?php esc_html_e( 'Yes', 'order100' ); ?></option>
									</select>
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Show an image thumbnail next to each choice.', 'order100'); ?></p>
								</div>
								<div class="o100-modal-col o100-field-enb-qty" style="flex:1;">
									<label style="display:block; font-weight:600; margin-bottom:5px; font-size:13px;"><?php esc_html_e( 'Enable Quantity Selectors', 'order100' ); ?></label>
									<select id="o100_ad_qty" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:4px;">
										<option value=""><?php esc_html_e( 'No', 'order100' ); ?></option>
										<option value="yes"><?php esc_html_e( 'Yes', 'order100' ); ?></option>
									</select>
									<p style="margin:4px 0 0; font-size:11px; color:#64748b;"><?php esc_html_e('Allow customers to choose quantities per item.', 'order100'); ?></p>
								</div>
							</div>

							<!-- Fixed fields for text/quantity type -->
							<div class="o100-textqty-fields" style="display:none; background:#fff7ed; padding:15px; border-radius:6px; border:1px solid #fed7aa; margin-bottom:15px;">
								<p style="margin:0 0 10px 0; font-size:13px; color:#c2410c; font-weight:600;"><?php esc_html_e( 'Text/Quantity Input Pricing', 'order100' ); ?></p>
								<div style="display:flex; gap:15px;">
									<div style="flex:1;">
										<label style="display:block; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Price Type', 'order100' ); ?></label>
										<select id="o100_ad_pricetype" style="width:100%; padding:6px; border:1px solid #fdba74; border-radius:4px;">
											<option value=""><?php esc_html_e( 'Quantity Based', 'order100' ); ?></option>
											<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'order100' ); ?></option>
										</select>
									</div>
									<div style="flex:1;">
										<label style="display:block; font-size:12px; margin-bottom:3px;"><?php esc_html_e( 'Price', 'order100' ); ?></label>
										<input type="text" id="o100_ad_price" style="width:100%; padding:6px; border:1px solid #fdba74; border-radius:4px;" placeholder="0.00">
									</div>
								</div>
							</div>
							<div style="border-top:1px solid #e2e8f0; margin:30px 0;"></div>

							<!-- CHOICES SECTION INSIDE GENERAL TAB -->
							<div class="o100-choices-section">
								<div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
									<div>
										<h4 style="margin:0 0 5px 0; font-size:14px; color:#0f172a;"><?php esc_html_e( 'Options', 'order100' ); ?></h4>
										<p style="margin:0; font-size:12px; color:#64748b;"><?php esc_html_e( 'Set name and price for each option.', 'order100' ); ?></p>
									</div>
									<button type="button" class="button button-secondary o100-add-choice-btn"><?php esc_html_e( 'Add Row', 'order100' ); ?></button>
								</div>
								
								<div class="o100-choices-table-wrap" style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden;">
									<table class="wp-list-table widefat fixed striped" style="margin:0; border:none; box-shadow:none;">
										<thead>
											<tr>
												<th style="width:30px;"></th>
												<th style="width:40px;">Img</th>
												<th><?php esc_html_e( 'Option name', 'order100' ); ?></th>
												<th style="width:60px; text-align:center;"><?php esc_html_e( 'Default', 'order100' ); ?></th>
												<th style="width:60px; text-align:center;"><?php esc_html_e( 'Disable ?', 'order100' ); ?></th>
												<th style="width:70px;"><?php esc_html_e( 'Price', 'order100' ); ?></th>
												<th style="width:110px;"><?php esc_html_e( 'Type of price', 'order100' ); ?></th>
												<th style="width:80px;"><?php esc_html_e( 'Min qty', 'order100' ); ?></th>
												<th style="width:80px;"><?php esc_html_e( 'Max qty', 'order100' ); ?></th>
												<th style="width:40px; text-align:center;"></th>
											</tr>
										</thead>
										<tbody id="o100-choices-list">
											<!-- AJAX Injected -->
										</tbody>
									</table>
								</div>
							</div>
						</div> <!-- End General Tab -->

						<!-- CONDITIONS TAB -->
						<div id="o100-addon-tab-conditions" class="o100-addon-tab-content" style="display:none;">
							<div class="o100-modal-field" style="margin-bottom:20px;">
								<label style="display:flex; align-items:center; gap:10px; font-weight:600; font-size:14px; color:#0f172a;">
									<input type="checkbox" id="o100_ad_cond_enable" value="yes">
									<?php esc_html_e( 'Enable Conditional Logic for this option', 'order100' ); ?>
								</label>
							</div>
							
							<div class="o100-conditions-builder" style="display:none; padding:15px; border:1px solid #cbd5e1; border-radius:6px; background:#f8fafc;">
								<div class="o100-modal-field" style="margin-bottom:15px; display:flex; align-items:center; gap:10px;">
									<select id="o100_ad_con_tlogic" style="width:180px;">
										<option value=""><?php esc_html_e( 'Show this option if', 'order100' ); ?></option>
										<option value="hide"><?php esc_html_e( 'Hide this option if', 'order100' ); ?></option>
									</select>
									<span style="font-size:13px; color:#64748b;"><?php esc_html_e( 'any of the following rules match:', 'order100' ); ?></span>
								</div>
								
								<div id="o100-cond-rules-list" style="margin-bottom:15px;">
									<!-- Rules Injected Here -->
								</div>
								
								<button type="button" class="button button-secondary o100-add-cond-rule-btn"><?php esc_html_e( 'Add Rule', 'order100' ); ?></button>
							</div>
						</div> <!-- End Conditions Tab -->

					</div>
					
					<!-- Footer -->
					<div style="padding:15px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; border-radius:0 0 8px 8px; display:flex; justify-content:space-between; align-items:center;">
						<span id="o100-addon-modal-status" style="font-size:13px; color:#16a34a; font-weight:500; display:none;">Saved successfully!</span>
						<div style="margin-left:auto;">
							<button type="button" class="button o100-close-addon-modal" style="margin-right:10px;"><?php esc_html_e( 'Cancel', 'order100' ); ?></button>
							<button type="button" class="button button-primary o100-save-addon-action" style="background:#3b82f6; border-color:#3b82f6; padding:0 20px; height:36px;">
								<?php esc_html_e( 'Save Option', 'order100' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Template for Choice Row -->
		<script type="text/template" id="tmpl-o100-choice-row">
			<tr class="o100-choice-row">
				<td style="cursor:move; color:#cbd5e1; text-align:center;"><span class="dashicons dashicons-menu"></span></td>
				<td>
					<div class="o100-choice-img-preview" style="width:24px; height:24px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; cursor:pointer; background-size:cover; background-position:center;" title="Image"></div>
					<input type="hidden" class="c-img" value="">
				</td>
				<td><input type="text" class="c-name" value="" style="width:100%; padding:4px 8px;" placeholder="Name"></td>
				<td style="text-align:center;"><input type="checkbox" class="c-def" value="yes"></td>
				<td style="text-align:center;"><input type="checkbox" class="c-dis" value="yes"></td>
				<td><input type="text" class="c-price" value="" style="width:100%; padding:4px 8px;" placeholder="0.00"></td>
				<td>
					<select class="c-type" style="width:100%; padding:4px;">
						<option value="">Qty Based</option>
						<option value="fixed">Fixed</option>
					</select>
				</td>
				<td><input type="number" class="c-min" value="" style="width:100%; padding:4px 8px;" placeholder="0"></td>
				<td><input type="number" class="c-max" value="" style="width:100%; padding:4px 8px;" placeholder="0"></td>
				<td style="text-align:center;"><span class="dashicons dashicons-trash o100-del-choice" style="color:#ef4444; cursor:pointer;"></span></td>
			</tr>
		</script>

		<!-- Template for Conditional Logic Rule Row -->
		<script type="text/template" id="tmpl-o100-cond-rule-row">
			<div class="o100-cond-rule-row" style="display:flex; align-items:center; gap:10px; margin-bottom:10px; background:#fff; padding:10px; border:1px solid #e2e8f0; border-radius:4px;">
				<div class="rule-or" style="font-weight:600; color:#64748b; width:30px; text-align:center;">OR</div>
				
				<select class="rule-target-op" style="width:200px;">
					<option value=""><?php esc_html_e( '-- Select Option --', 'order100' ); ?></option>
				</select>
				
				<select class="rule-condition" style="width:100px;">
					<option value=""><?php esc_html_e( 'is', 'order100' ); ?></option>
					<option value="is_not"><?php esc_html_e( 'is not', 'order100' ); ?></option>
				</select>
				
				<select class="rule-target-val" style="width:200px;">
					<option value=""><?php esc_html_e( '-- Select Choice --', 'order100' ); ?></option>
				</select>
				
				<span class="dashicons dashicons-no-alt o100-del-cond-rule" style="color:#ef4444; cursor:pointer; margin-left:auto;"></span>
			</div>
		</script>

		<?php
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		if ( wp_script_is( 'selectWoo', 'registered' ) ) {
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		}
		?>
		<style>
			/* Fix Select2 height in WP Admin */
			.select2-container .select2-selection--multiple { min-height: 32px; border: 1px solid #93c5fd; border-radius: 4px; }
		</style>
		<?php
		$settings = get_option( 'o100_product_options', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$initial_groups = isset( $settings['o100_addon_groups'] ) && is_array( $settings['o100_addon_groups'] ) ? array_values( $settings['o100_addon_groups'] ) : array();
		$addons_json = wp_json_encode( $initial_groups );
		if ( ! $addons_json ) {
			$addons_json = '[]';
		}
		?>
		<script>
		window.o100_addons_initial_data = <?php echo $addons_json; ?>;
		jQuery(document).ready(function($) {
			var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			
			// Initialize Select2
			var selectFn = $.fn.selectWoo ? 'selectWoo' : ($.fn.select2 ? 'select2' : null);
			if (selectFn) {
				$('#o100_ad_prods')[selectFn]({
					ajax: {
						url: ajaxurl,
						dataType: 'json',
						delay: 250,
						data: function (params) {
							return {
								q: params.term,
								action: 'o100_search_products',
								nonce: (typeof o100Settings !== 'undefined') ? o100Settings.adminNonce : ''
							};
						},
						processResults: function (data) {
							return { results: data.data };
						},
						cache: true
					},
					minimumInputLength: 2,
					placeholder: "Search for products...",
					closeOnSelect: false,
					templateResult: function(result) {
						if (result.loading) return result.text;
						var selectedIds = $('#o100_ad_prods').val() || [];
						var isSelected = (selectedIds.indexOf(result.id.toString()) !== -1 || selectedIds.indexOf(result.id) !== -1);
						
						if (isSelected) {
							setTimeout(function() {
								$('.o100-select2-item[data-id="'+result.id+'"]').closest('li').addClass('o100-disabled-li');
							}, 0);
						}
						
						return jQuery('<div class="o100-select2-item" data-id="' + result.id + '" style="display:flex; align-items:center;"><span class="o100-custom-cb"></span><span>' + result.text + '</span></div>');
					},
					templateSelection: function(result) {
						return result.text;
					}
				}).on('select2:select select2:unselect', function(e) {
					var id = e.params.data.id;
					var $li = $('.o100-select2-item[data-id="'+id+'"]').closest('li');
					if (e.type === 'select2:select') {
						$li.addClass('o100-disabled-li');
					} else {
						$li.removeClass('o100-disabled-li');
					}
				});
			}

			// Load Addons List
			function renderAddonsList(data) {
				window.o100_addons_data = data;
				var html = '';
				if (data.length === 0) {
					html = '<p style="color:#64748b; font-size:14px; text-align:center; padding: 40px 0; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">No global options found. Add one above.</p>';
				} else {
					html = '<div style="width:100%; overflow-x:auto; border:1px solid #e2e8f0; border-radius:8px;"><table class="o100-menu-table" style="width:100%; table-layout:auto; border-collapse:collapse; min-width:400px;"><thead><tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">';
					html += '<th style="width:40px; text-align:center; border-right:1px solid #e2e8f0;"></th>';
					html += '<th style="padding:12px 16px; text-align:left; font-weight:600; color:#475569; width:auto; border-right:1px solid #e2e8f0;">Option Name</th>';
					html += '<th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; border-right:1px solid #e2e8f0;" class="o100-hide-xs">Type</th>';
					html += '<th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; border-right:1px solid #e2e8f0;" class="o100-hide-sm">Apply To</th>';
					html += '<th style="padding:12px 16px; text-align:center; font-weight:600; color:#475569; border-right:1px solid #e2e8f0;" class="o100-hide-md">Choices</th>';
					html += '<th style="padding:12px 16px; text-align:right; font-weight:600; color:#475569; width:120px;">Actions</th></tr></thead><tbody>';
					
					$.each(data, function(i, grp) {
						var typeLabel = grp._type || 'checkbox';
						var applyLabel = grp._apply_to === 'all' ? 'All Products' : (grp._apply_to === 'categories' ? 'Specific Categories' : 'Specific Products');
						var choicesCount = grp._options ? grp._options.length : 0;
						
						html += '<tr style="border-bottom:1px solid #e2e8f0; transition: background 0.2s;" data-id="' + grp._id + '">';
						html += '<td class="o100-drag-handle" style="cursor:move; color:#cbd5e1; text-align:center; vertical-align:middle; border-right:1px solid #e2e8f0;"><span class="dashicons dashicons-menu"></span></td>';
						html += '<td style="padding:12px 16px; text-align:left; white-space:nowrap; vertical-align:middle; border-right:1px solid #e2e8f0;"><strong style="color:#0f172a;">' + grp._name + '</strong></td>';
						html += '<td style="padding:12px 16px; text-align:center; vertical-align:middle; border-right:1px solid #e2e8f0;" class="o100-hide-xs"><span style="background:#e0e7ff; color:#3730a3; padding:2px 8px; border-radius:12px; font-size:12px;">' + typeLabel + '</span></td>';
						html += '<td style="padding:12px 16px; text-align:center; vertical-align:middle; color:#64748b; border-right:1px solid #e2e8f0;" class="o100-hide-sm">' + applyLabel + '</td>';
						html += '<td style="padding:12px 16px; text-align:center; vertical-align:middle; color:#64748b; border-right:1px solid #e2e8f0;" class="o100-hide-md">' + choicesCount + ' items</td>';
						html += '<td style="padding:12px 16px; text-align:right; white-space:nowrap; vertical-align:middle;">';
						html += '<a href="#" class="o100-edit-addon o100-tooltip" data-id="' + grp._id + '" style="color:#2563eb; font-weight:500; margin-right:12px; text-decoration:none; font-size:16px;" data-tooltip="Edit option"><span class="dashicons dashicons-edit"></span></a>';
						html += '<a href="#" class="o100-duplicate-addon o100-tooltip" data-id="' + grp._id + '" style="color:#059669; font-weight:500; margin-right:12px; text-decoration:none; font-size:16px;" data-tooltip="Duplicate option"><span class="dashicons dashicons-admin-page"></span></a>';
						html += '<a href="#" class="o100-delete-addon o100-tooltip" data-id="' + grp._id + '" style="color:#ef4444; font-weight:500; text-decoration:none; font-size:16px;" data-tooltip="Delete option"><span class="dashicons dashicons-trash"></span></a>';
						html += '</td></tr>';
					});
					html += '</tbody></table></div>';
				}
				$('.o100-addons-list-container').html(html);
				
				// Make it sortable
				$('.o100-menu-table tbody').sortable({
					handle: '.o100-drag-handle',
					helper: function(e, ui) {
						ui.children().each(function() {
							$(this).width($(this).width());
						});
						return ui;
					},
					update: function(event, ui) {
						var order = [];
						$(this).find('tr').each(function() {
							order.push($(this).data('id'));
						});
						$.post(ajaxurl, { action: 'o100_save_groups_order', order: order });
					}
				});
			}

			function loadAddons() {
				$.post(ajaxurl, { action: 'o100_get_addon_groups' }, function(res) {
					if (res.success) {
						renderAddonsList(res.data);
					}
				});
			}

			// Render instantly on page load using PHP injected data
			renderAddonsList(window.o100_addons_initial_data);

			// Apply To Logic
			$('#o100_ad_applyto').on('change', function() {
				var v = $(this).val();
				$('.o100-apply-cat, .o100-apply-prod').hide();
				if (v === 'categories') $('.o100-apply-cat').show();
				if (v === 'products') $('.o100-apply-prod').show();
			});

			// Type Logic
			$('#o100_ad_type').on('change', function() {
				var v = $(this).val();
				
				// Hide all first
				$('.o100-field-min-op, .o100-field-max-op, .o100-field-enb-img, .o100-field-enb-qty, .o100-field-min-qty, .o100-field-max-qty').hide();
				$('.o100-choices-section').hide();
				$('.o100-textqty-fields').hide();

				switch(v) {
					case '': // Checkboxes
						$('.o100-field-min-op, .o100-field-max-op, .o100-field-enb-img, .o100-field-enb-qty, .o100-field-min-qty, .o100-field-max-qty').show();
						$('.o100-choices-section').show();
						break;
					case 'radio': // Radio buttons
						$('.o100-field-enb-img, .o100-field-enb-qty').show();
						$('.o100-choices-section').show();
						break;
					case 'select': // Select dropdown
						$('.o100-choices-section').show();
						break;
					case 'text':
					case 'textarea':
					case 'quantity':
						$('.o100-textqty-fields').show();
						break;
				}
			});

			// Toggle Quantity columns
			$('#o100_ad_qty').on('change', function() {
				if ($(this).val() === 'yes') {
					$('.o100-choices-table-wrap th:nth-child(8), .o100-choices-table-wrap th:nth-child(9)').show();
					$('.o100-choice-row td:nth-child(8), .o100-choice-row td:nth-child(9)').show();
				} else {
					$('.o100-choices-table-wrap th:nth-child(8), .o100-choices-table-wrap th:nth-child(9)').hide();
					$('.o100-choice-row td:nth-child(8), .o100-choice-row td:nth-child(9)').hide();
				}
			});

			// Toggle Image columns
			$('#o100_ad_img').on('change', function() {
				if ($(this).val() === 'yes') {
					$('.o100-choices-table-wrap th:nth-child(2)').show();
					$('.o100-choice-row td:nth-child(2)').show();
				} else {
					$('.o100-choices-table-wrap th:nth-child(2)').hide();
					$('.o100-choice-row td:nth-child(2)').hide();
				}
			});

			// Tabs
			$('.o100-addon-tab').on('click', function(e) {
				e.preventDefault();
				if ($(this).css('display') === 'none') return;
				$('.o100-addon-tab').removeClass('active').css({'color': '#64748b', 'border-bottom-color': 'transparent'});
				$(this).addClass('active').css({'color': '#2563eb', 'border-bottom-color': '#2563eb'});
				$('.o100-addon-tab-content').hide();
				$('#o100-addon-tab-' + $(this).data('tab')).show();
			});

			// Media Uploader
			var file_frame;
			var $active_img_btn;
			var $active_img_input;
			$(document).on('click', '.o100-choice-img-preview', function(e) {
				e.preventDefault();
				$active_img_btn = $(this);
				$active_img_input = $active_img_btn.next('.c-img');
				
				if ( file_frame ) { 
					file_frame.open(); 
					return; 
				}
				
				file_frame = wp.media.frames.file_frame = wp.media({ 
					title: 'Select Image', 
					button: { text: 'Use this image' }, 
					multiple: false 
				});
				
				file_frame.on( 'select', function() {
					var attachment = file_frame.state().get('selection').first().toJSON();
					if ($active_img_input && $active_img_btn) {
						$active_img_input.val(attachment.url);
						$active_img_btn.css('background-image', 'url(' + attachment.url + ')');
					}
				});
				
				file_frame.open();
			});

			// Sortable choices
			$('#o100-choices-list').sortable({ handle: 'td:first-child' });

			// Add Choice
			$('.o100-add-choice-btn').on('click', function() {
				var $row = $($('#tmpl-o100-choice-row').html());
				$('#o100-choices-list').append($row);
				// Apply current visibility state to new row
				if ($('#o100_ad_qty').val() !== 'yes') {
					$row.find('td:nth-child(8), td:nth-child(9)').hide();
				}
				if ($('#o100_ad_img').val() !== 'yes') {
					$row.find('td:nth-child(2)').hide();
				}
			});

			// Delete Choice
			$(document).on('click', '.o100-del-choice', function() {
				$(this).closest('tr').remove();
			});

			// Open Modal (Add New)
			$('.o100-add-addon-btn').on('click', function(e) {
				e.preventDefault();
				$('#o100_ad_id').val('o100-id' + Math.floor(Math.random() * 1000000000));
				$('#o100_ad_name, #o100_ad_min, #o100_ad_max, #o100_ad_price').val('');
				$('#o100_ad_type, #o100_ad_req, #o100_ad_display, #o100_ad_img, #o100_ad_qty, #o100_ad_pricetype').val('');
				$('#o100_ad_min_qty, #o100_ad_max_qty').val('');
				$('#o100_ad_cond_enable').prop('checked', false);
				$('.o100-conditions-builder').hide();
				$('#o100-cond-rules-list').empty();
				$('#o100_ad_applyto').val('all').trigger('change');
				$('.o100_ad_cats').prop('checked', false);
				
				if (selectFn) $('#o100_ad_prods').empty().trigger('change');
				else $('#o100_ad_prods').val('');
				
				$('#o100-choices-list').empty();
				$('#o100_ad_type').trigger('change');
				$('#o100_ad_qty').trigger('change');
				$('#o100_ad_img').trigger('change');
				$('.o100-addon-tab[data-tab="general"]').click();
				$('.o100-addon-modal-overlay').css('display', 'flex');
			});

			// Edit Addon
			$(document).on('click', '.o100-edit-addon', function(e) {
				e.preventDefault();
				var id = $(this).data('id');
				var grp = window.o100_addons_data.find(function(g) { return g._id == id; });
				if (!grp) return;

				$('#o100_ad_id').val(grp._id);
				$('#o100_ad_name').val(grp._name || '');
				$('#o100_ad_type').val(grp._type || '').trigger('change');
				$('#o100_ad_req').val(grp._required || '');
				$('#o100_ad_display').val(grp._display_type || '');
				$('#o100_ad_applyto').val(grp._apply_to || 'all').trigger('change');
				
				$('.o100_ad_cats').prop('checked', false);
				if (grp._category_ids && Array.isArray(grp._category_ids)) {
					$.each(grp._category_ids, function(i, c) { $('.o100_ad_cats[value="'+c+'"]').prop('checked', true); });
				}
				
				// Product Search pre-populate
				if (selectFn) {
					$('#o100_ad_prods').empty().trigger('change');
					if (grp._product_ids) {
						var ids = Array.isArray(grp._product_ids) ? grp._product_ids.join(',') : grp._product_ids;
						if (ids) {
							$.post(ajaxurl, { action: 'o100_get_products_by_ids', ids: ids, nonce: (typeof o100Settings !== 'undefined') ? o100Settings.adminNonce : '' }, function(res) {
								if (res.success && res.data) {
									$.each(res.data, function(i, prod) {
										var option = new Option(prod.text, prod.id, true, true);
										$('#o100_ad_prods').append(option);
									});
									$('#o100_ad_prods').trigger('change');
								}
							});
						}
					}
				} else {
					$('#o100_ad_prods').val(Array.isArray(grp._product_ids) ? grp._product_ids.join(',') : grp._product_ids || '');
				}
				$('#o100_ad_min').val(grp._min_op || '');
				$('#o100_ad_max').val(grp._max_op || '');
				$('#o100_ad_img').val(grp._enb_img || '');
				$('#o100_ad_qty').val(grp._enb_qty || '');
				
				// New fields
				$('#o100_ad_min_qty').val(grp._min_opqty || '');
				$('#o100_ad_max_qty').val(grp._max_opqty || '');

				$('#o100_ad_pricetype').val(grp._price_type || '');
				$('#o100_ad_price').val(grp._price || '');

				// Populate Conditional Logic rules
				$('#o100-cond-rules-list').empty();
				if (grp._enb_logic === 'yes') {
					$('#o100_ad_cond_enable').prop('checked', true);
					$('.o100-conditions-builder').show();
					if (grp._con_logic && Array.isArray(grp._con_logic)) {
						$.each(grp._con_logic, function(i, rule) {
							o100_add_cond_rule_row(rule);
						});
					}
				} else {
					$('#o100_ad_cond_enable').prop('checked', false);
					$('.o100-conditions-builder').hide();
				}
				$('#o100_ad_con_tlogic').val(grp._con_tlogic || '');

				// Populate choices
				$('#o100-choices-list').empty();
				if (grp._options && Array.isArray(grp._options)) {
					$.each(grp._options, function(i, opt) {
						var $row = $($('#tmpl-o100-choice-row').html());
						$row.find('.c-name').val(opt.name || '');
						$row.find('.c-price').val(opt.price || '');
						$row.find('.c-type').val(opt.type || '');
						$row.find('.c-min').val(opt.min || '');
						$row.find('.c-max').val(opt.max || '');
						if (opt.def === 'yes') $row.find('.c-def').prop('checked', true);
						if (opt.dis === 'yes') $row.find('.c-dis').prop('checked', true);
						if (opt.image) {
							$row.find('.c-img').val(opt.image);
							$row.find('.o100-choice-img-preview').css('background-image', 'url(' + opt.image + ')');
						}
						$('#o100-choices-list').append($row);
					});
				}

				$('#o100_ad_type').trigger('change');
				$('#o100_ad_qty').trigger('change');
				$('#o100_ad_img').trigger('change');

				$('.o100-addon-tab[data-tab="general"]').click();
				$('.o100-addon-modal-overlay').css('display', 'flex');
			});

			// Close
			$('.o100-close-addon-modal').on('click', function() {
				$('.o100-addon-modal-overlay').hide();
			});

			// Save
			$('.o100-save-addon-action').on('click', function(e) {
				e.preventDefault();
				var $btn = $(this);
				var name = $('#o100_ad_name').val().trim();
				if (!name) { alert('Option Name is required.'); return; }

				var cats = [];
				$('.o100_ad_cats:checked').each(function() { cats.push($(this).val()); });

				var opts = [];
				$('#o100-choices-list tr').each(function() {
					opts.push({
						name: $(this).find('.c-name').val(),
						price: $(this).find('.c-price').val(),
						type: $(this).find('.c-type').val(),
						def: $(this).find('.c-def').is(':checked') ? 'yes' : '',
						dis: $(this).find('.c-dis').is(':checked') ? 'yes' : '',
						image: $(this).find('.c-img').val(),
						min: $(this).find('.c-min').val(),
						max: $(this).find('.c-max').val()
					});
				});

				var con_rules = [];
				$('#o100-cond-rules-list .o100-cond-rule-row').each(function() {
					con_rules.push({
						type_rel: '',
						type_op: $(this).find('.rule-target-op').val(),
						type_con: $(this).find('.rule-condition').val(),
						val: $(this).find('.rule-target-val').val()
					});
				});

				var payload = {
					action: 'o100_save_addon_group',
					_id: $('#o100_ad_id').val(),
					_name: name,
					_type: $('#o100_ad_type').val(),
					_required: $('#o100_ad_req').val(),
					_display_type: $('#o100_ad_display').val(),
					_apply_to: $('#o100_ad_applyto').val(),
					_category_ids: cats,
					_product_ids: $('#o100_ad_prods').val(), // Now an array from Select2
					_min_op: $('#o100_ad_min').val(),
					_max_op: $('#o100_ad_max').val(),
					_enb_img: $('#o100_ad_img').val(),
					_enb_qty: $('#o100_ad_qty').val(),
					_min_opqty: $('#o100_ad_min_qty').val(),
					_max_opqty: $('#o100_ad_max_qty').val(),
					_price_type: $('#o100_ad_pricetype').val(),
					_price: $('#o100_ad_price').val(),
					_enb_logic: $('#o100_ad_cond_enable').is(':checked') ? 'yes' : '',
					_con_tlogic: $('#o100_ad_con_tlogic').val() || '',
					_con_logic: con_rules,
					_options: opts
				};

				$btn.text('Saving...').prop('disabled', true);
				$.post(ajaxurl, payload, function(res) {
					$btn.text('Save Option').prop('disabled', false);
					if (res.success) {
						$('#o100-addon-modal-status').show().delay(2000).fadeOut();
						loadAddons();
						setTimeout(function() { $('.o100-addon-modal-overlay').hide(); }, 600);
					} else {
						alert('Save failed.');
					}
				});
			});

			// Delete
			$(document).on('click', '.o100-delete-addon', function(e) {
				e.preventDefault();
				if (!confirm('Are you sure you want to delete this option?')) return;
				var id = $(this).data('id');
				var $tr = $(this).closest('tr');
				$tr.css('opacity', '0.5');
				$.post(ajaxurl, { action: 'o100_delete_addon_group', _id: id }, function(res) {
					if (res.success) {
						loadAddons();
					} else {
						$tr.css('opacity', '1');
					}
				});
			});

			// Duplicate
			$(document).on('click', '.o100-duplicate-addon', function(e) {
				e.preventDefault();
				var id = $(this).data('id');
				var $tr = $(this).closest('tr');
				$tr.css('opacity', '0.5');
				$.post(ajaxurl, { action: 'o100_duplicate_addon_group', _id: id }, function(res) {
					if (res.success) {
						loadAddons();
					} else {
						$tr.css('opacity', '1');
						alert('Duplicate failed.');
					}
				});
			});
			// Toggle Conditional Logic section
			$(document).on('change', '#o100_ad_cond_enable', function() {
				if ($(this).is(':checked')) {
					$('.o100-conditions-builder').slideDown(200);
				} else {
					$('.o100-conditions-builder').slideUp(200);
				}
			});
			// Add Conditional Logic Rule
			$('.o100-add-cond-rule-btn').on('click', function() {
				o100_add_cond_rule_row(null);
			});

			// Delete Conditional Logic Rule
			$(document).on('click', '.o100-del-cond-rule', function() {
				$(this).closest('.o100-cond-rule-row').remove();
				// Refresh OR visibility
				$('#o100-cond-rules-list .o100-cond-rule-row').first().find('.rule-or').css('visibility', 'hidden');
			});

			// Populate Choices when Option changes in rules
			$(document).on('change', '.rule-target-op', function() {
				var grpId = $(this).val();
				var $valSelect = $(this).closest('.o100-cond-rule-row').find('.rule-target-val');
				$valSelect.find('option:not(:first)').remove();
				if (grpId) {
					var grp = window.o100_addons_data.find(function(g) { return g._id == grpId; });
					if (grp && grp._options) {
						$.each(grp._options, function(i, opt) {
							var valKey = i + '-' + (opt.name || '');
							$valSelect.append($('<option>', { value: valKey, text: opt.name || 'Choice ' + (i+1) }));
						});
					}
				}
			});

			function o100_add_cond_rule_row(ruleData) {
				var $row = $($('#tmpl-o100-cond-rule-row').html());
				$('#o100-cond-rules-list').append($row);
				
				if ($('#o100-cond-rules-list .o100-cond-rule-row').length === 1) {
					$row.find('.rule-or').css('visibility', 'hidden');
				}

				var currentEditId = $('#o100_ad_id').val();
				var $opSelect = $row.find('.rule-target-op');
				$.each(window.o100_addons_data, function(i, g) {
					if (g._id !== currentEditId) {
						var title = (g._name || 'Option ' + (i+1)) + ' - ' + g._id;
						$opSelect.append($('<option>', { value: g._id, text: title }));
					}
				});

				if (ruleData) {
					$opSelect.val(ruleData.type_op || '');
					$row.find('.rule-condition').val(ruleData.type_con || '');
					$opSelect.trigger('change');
					$row.find('.rule-target-val').val(ruleData.val || '');
				}
			}
		});
		</script>
		<?php
	}

	public static function ajax_get_groups() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		wp_send_json_success( array_values( $groups ) );
	}

	public static function ajax_delete_group() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		
		$id = sanitize_text_field( $_POST['_id'] );
		
		foreach ( $groups as $k => $grp ) {
			if ( isset( $grp['_id'] ) && $grp['_id'] === $id ) {
				unset( $groups[ $k ] );
				break;
			}
		}
		
		$settings['o100_addon_groups'] = array_values( $groups );
		update_option( 'o100_product_options', $settings );
		wp_send_json_success();
	}

	public static function ajax_duplicate_group() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		
		$id = sanitize_text_field( $_POST['_id'] );
		
		foreach ( $groups as $k => $grp ) {
			if ( isset( $grp['_id'] ) && $grp['_id'] === $id ) {
				$new_group = $grp;
				$new_group['_id'] = 'o100-id' . wp_rand( 100000000, 999999999 );
				$new_group['_name'] = $new_group['_name'] . ' (Copy)';
				$groups[] = $new_group;
				break;
			}
		}
		
		$settings['o100_addon_groups'] = $groups;
		update_option( 'o100_product_options', $settings );
		wp_send_json_success();
	}

	public static function ajax_save_groups_order() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
		
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'sanitize_text_field', $_POST['order'] ) : array();
		
		if ( ! empty( $order ) && ! empty( $groups ) ) {
			$ordered_groups = array();
			$groups_by_id = array();
			foreach ( $groups as $grp ) {
				if ( isset( $grp['_id'] ) ) {
					$groups_by_id[ $grp['_id'] ] = $grp;
				}
			}
			
			foreach ( $order as $id ) {
				if ( isset( $groups_by_id[ $id ] ) ) {
					$ordered_groups[] = $groups_by_id[ $id ];
					unset( $groups_by_id[ $id ] );
				}
			}
			
			// Append any remaining groups that were not in the order array
			foreach ( $groups_by_id as $grp ) {
				$ordered_groups[] = $grp;
			}
			
			$settings['o100_addon_groups'] = array_values( $ordered_groups );
			update_option( 'o100_product_options', $settings );
		}
		
		wp_send_json_success();
	}

	public static function ajax_save_group() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
		
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		
		$id = sanitize_text_field( $_POST['_id'] );
		$prods = isset( $_POST['_product_ids'] ) ? $_POST['_product_ids'] : '';
		if ( is_array( $prods ) ) {
			$prods = implode( ',', array_map( 'intval', $prods ) );
		} else {
			$prods = sanitize_text_field( $prods );
		}

		$new_group = array(
			'_id'           => $id,
			'_name'         => sanitize_text_field( $_POST['_name'] ),
			'_type'         => sanitize_text_field( $_POST['_type'] ),
			'_required'     => sanitize_text_field( $_POST['_required'] ),
			'_display_type' => sanitize_text_field( $_POST['_display_type'] ),
			'_apply_to'     => sanitize_text_field( $_POST['_apply_to'] ),
			'_category_ids' => isset( $_POST['_category_ids'] ) ? array_map( 'intval', $_POST['_category_ids'] ) : array(),
			'_product_ids'  => $prods,
			'_min_op'       => sanitize_text_field( $_POST['_min_op'] ),
			'_max_op'       => sanitize_text_field( $_POST['_max_op'] ),
			'_enb_img'      => sanitize_text_field( $_POST['_enb_img'] ),
			'_enb_qty'      => sanitize_text_field( $_POST['_enb_qty'] ),
			'_min_opqty'    => isset( $_POST['_min_opqty'] ) ? sanitize_text_field( $_POST['_min_opqty'] ) : '',
			'_max_opqty'    => isset( $_POST['_max_opqty'] ) ? sanitize_text_field( $_POST['_max_opqty'] ) : '',
			'_price_type'   => sanitize_text_field( $_POST['_price_type'] ),
			'_price'        => sanitize_text_field( $_POST['_price'] ),
			'_enb_logic'    => isset( $_POST['_enb_logic'] ) ? sanitize_text_field( $_POST['_enb_logic'] ) : '',
			'_con_tlogic'   => isset( $_POST['_con_tlogic'] ) ? sanitize_text_field( $_POST['_con_tlogic'] ) : '',
			'_con_logic'    => isset( $_POST['_con_logic'] ) ? self::sanitize_con_logic( $_POST['_con_logic'] ) : array(),
			'_options'      => isset( $_POST['_options'] ) ? self::sanitize_choices( $_POST['_options'] ) : array(),
		);

		// Find and update or append
		$found = false;
		foreach ( $groups as $k => $grp ) {
			if ( isset( $grp['_id'] ) && $grp['_id'] === $id ) {
				$groups[ $k ] = $new_group;
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$groups[] = $new_group;
		}

		$settings['o100_addon_groups'] = $groups;
		update_option( 'o100_product_options', $settings );
		wp_send_json_success();
	}



	public static function ajax_search_products() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		if ( empty( $term ) ) { wp_send_json_success( array() ); }

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $term,
		);
		$query = new WP_Query( $args );
		$results = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( $product ) {
					$results[] = array(
						'id'   => $product->get_id(),
						'text' => $product->get_name() . ' (#' . $product->get_id() . ')'
					);
				}
			}
		}
		wp_send_json_success( $results );
	}

	public static function ajax_get_products_by_ids() {
		check_ajax_referer( 'o100_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }

		$ids_raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
		$ids = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );
		if ( empty( $ids ) ) { wp_send_json_success( array() ); }

		$results = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$results[] = array(
					'id'   => $product->get_id(),
					'text' => $product->get_name() . ' (#' . $product->get_id() . ')'
				);
			}
		}
		wp_send_json_success( $results );
	}

	private static function sanitize_con_logic( $rules ) {
		if ( ! is_array( $rules ) ) return array();
		$clean = array();
		foreach ( $rules as $r ) {
			if ( empty( $r['type_op'] ) || empty( $r['val'] ) ) continue;
			$clean[] = array(
				'type_rel' => '',
				'type_op'  => sanitize_text_field( $r['type_op'] ),
				'type_con' => sanitize_text_field( $r['type_con'] ),
				'val'      => sanitize_text_field( $r['val'] ),
			);
		}
		return $clean;
	}

	private static function sanitize_choices( $choices ) {
		if ( ! is_array( $choices ) ) return array();
		$clean = array();
		foreach ( $choices as $c ) {
			if ( empty( $c['name'] ) ) continue;
			$clean[] = array(
				'name'  => sanitize_text_field( $c['name'] ),
				'price' => sanitize_text_field( $c['price'] ),
				'type'  => sanitize_text_field( $c['type'] ),
				'def'   => sanitize_text_field( $c['def'] ),
				'dis'   => sanitize_text_field( $c['dis'] ),
				'image' => esc_url_raw( $c['image'] ),
				'min'   => sanitize_text_field( $c['min'] ),
				'max'   => sanitize_text_field( $c['max'] ),
			);
		}
		return $clean;
	}
}
O100_Fluent_Addons::init();



// TS: 20260115202946
