<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form save
if ( isset( $_POST['o100_crm_settings_nonce'] ) && wp_verify_nonce( $_POST['o100_crm_settings_nonce'], 'o100_crm_settings_save' ) ) {
	
	update_option( 'o100_crm_default_list', isset( $_POST['default_list'] ) ? intval( $_POST['default_list'] ) : 0 );
	update_option( 'o100_crm_default_tag', isset( $_POST['default_tag'] ) ? intval( $_POST['default_tag'] ) : 0 );
	update_option( 'o100_crm_cart_abandoned_time', isset( $_POST['cart_abandoned_time'] ) ? intval( $_POST['cart_abandoned_time'] ) : 30 );
	update_option( 'o100_crm_cart_lost_time', isset( $_POST['cart_lost_time'] ) ? intval( $_POST['cart_lost_time'] ) : 24 );
	update_option( 'o100_crm_one_click_unsubscribe', isset( $_POST['one_click_unsubscribe'] ) ? 1 : 0 );
	update_option( 'o100_crm_data_deletion', isset( $_POST['data_deletion'] ) ? 1 : 0 );
	update_option( 'o100_crm_enable_optin', isset( $_POST['enable_optin'] ) ? 1 : 0 );
	update_option( 'o100_crm_optin_label', isset( $_POST['optin_label'] ) ? sanitize_text_field( $_POST['optin_label'] ) : 'Subscribe to our newsletter for exclusive offers!' );
	update_option( 'o100_crm_optin_default', isset( $_POST['optin_default'] ) ? 1 : 0 );
	update_option( 'o100_crm_optin_location', isset( $_POST['optin_location'] ) ? sanitize_text_field( $_POST['optin_location'] ) : 'woocommerce_checkout_terms_and_conditions' );
	
	// Double Opt-in
	update_option( 'o100_crm_double_optin', isset( $_POST['double_optin'] ) ? 1 : 0 );
	update_option( 'o100_crm_double_optin_subject', isset( $_POST['double_optin_subject'] ) ? sanitize_text_field( $_POST['double_optin_subject'] ) : 'Please confirm your subscription' );
	if ( isset( $_POST['double_optin_body'] ) ) {
		update_option( 'o100_crm_double_optin_body', wp_kses_post( wp_unslash( $_POST['double_optin_body'] ) ) );
	}
	update_option( 'o100_crm_double_optin_action', isset( $_POST['double_optin_action'] ) ? sanitize_text_field( $_POST['double_optin_action'] ) : 'message' );
	if ( isset( $_POST['double_optin_val'] ) ) {
		update_option( 'o100_crm_double_optin_val', wp_kses_post( wp_unslash( $_POST['double_optin_val'] ) ) );
	}
}

$current_list = get_option( 'o100_crm_default_list', 0 );
$current_tag = get_option( 'o100_crm_default_tag', 0 );
$cart_abandoned = get_option( 'o100_crm_cart_abandoned_time', 30 );
$cart_lost = get_option( 'o100_crm_cart_lost_time', 24 );
$unsubscribe = get_option( 'o100_crm_one_click_unsubscribe', 1 );
$data_deletion = get_option( 'o100_crm_data_deletion', 0 );

$enable_optin = get_option( 'o100_crm_enable_optin', 1 );
$optin_label = get_option( 'o100_crm_optin_label', 'Subscribe to our newsletter for exclusive offers!' );
$optin_default = get_option( 'o100_crm_optin_default', 0 );
$optin_location = get_option( 'o100_crm_optin_location', 'woocommerce_checkout_terms_and_conditions' );

$double_optin = get_option( 'o100_crm_double_optin', 0 );
$double_optin_subject = get_option( 'o100_crm_double_optin_subject', 'Please confirm your subscription' );
$double_optin_body = get_option( 'o100_crm_double_optin_body', 'Click the link below to confirm your subscription:<br><br><a href="{{confirm_link}}" style="padding:10px 20px; background:#F59322; color:#fff; text-decoration:none; border-radius:5px;">Confirm Subscription</a>' );
$double_optin_action = get_option( 'o100_crm_double_optin_action', 'message' );
$double_optin_val = get_option( 'o100_crm_double_optin_val', 'Thank you! Your subscription has been confirmed.' );

// Fetch Manual Lists & Tags
$all_lists = O100_Customers_DB::get_lists();
$all_tags = O100_Customers_DB::get_tags();

$manual_lists = array_filter( $all_lists, function($l) { return empty($l->is_system); });
$manual_tags = array_filter( $all_tags, function($t) { return empty($t->is_system); });
?>

<div class="bg-white rounded-xl overflow-hidden shadow-sm border border-slate-200" x-data="crmSettingsForm()">
	<div class="px-6 py-5 border-b border-slate-200 bg-white flex justify-between items-center">
		<div>
			<h3 class="text-lg leading-6 font-medium text-slate-900">CRM Settings</h3>
			<p class="mt-1 text-sm text-slate-500">Configure WooCommerce synchronization, abandoned cart logic, and compliance preferences.</p>
		</div>
		<div>
			<button type="submit" form="crm-settings-form" :disabled="saving" class="inline-flex justify-center items-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-500 hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors disabled:opacity-50">
				<svg x-show="!saving" class="w-4 h-4 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
				<svg x-show="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
				<span class="text-white" x-text="saving ? 'Saving...' : (saved ? 'Saved!' : 'Save CRM Settings')"></span>
			</button>
		</div>
	</div>

	<div class="p-6 bg-slate-50">
		<form id="crm-settings-form" method="post" @submit.prevent="saveSettings">
			<?php wp_nonce_field( 'o100_crm_settings_save', 'o100_crm_settings_nonce' ); ?>
			
			<div class="space-y-6 w-full">
				
				<!-- Section 1: WooCommerce Integration -->
				<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
					<div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
						<h3 class="text-base font-semibold text-slate-800">WooCommerce Integration</h3>
					</div>
					<div class="p-6">
						<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
							<div>
								<label for="default_list" class="block text-sm font-medium text-slate-700">Default List</label>
								<div class="mt-1">
									<select id="default_list" name="default_list" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white">
										<option value="0">-- Do not assign --</option>
										<?php foreach ( $manual_lists as $list ) : ?>
											<option value="<?php echo esc_attr( $list->id ); ?>" <?php selected( $current_list, $list->id ); ?>><?php echo esc_html( $list->title ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<p class="mt-1 text-xs text-slate-500">Assigned automatically to newly synced customers.</p>
							</div>

							<div>
								<label for="default_tag" class="block text-sm font-medium text-slate-700">Default Tag</label>
								<div class="mt-1">
									<select id="default_tag" name="default_tag" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white">
										<option value="0">-- Do not assign --</option>
										<?php foreach ( $manual_tags as $tag ) : ?>
											<option value="<?php echo esc_attr( $tag->id ); ?>" <?php selected( $current_tag, $tag->id ); ?>><?php echo esc_html( $tag->title ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<p class="mt-1 text-xs text-slate-500">Assigned automatically to newly synced customers.</p>
							</div>
						</div>

						<div class="mt-6 p-5 bg-slate-50 border border-slate-200 rounded-lg" x-data="bulkSyncHandler()">
							<div class="flex items-center justify-between">
								<div>
									<h4 class="text-sm font-medium text-slate-900">Manual Bulk Sync</h4>
									<p class="text-xs text-slate-500 mt-1">Scan existing WooCommerce orders and generate CRM profiles/tags.</p>
								</div>
								<button type="button" @click="startSync()" :disabled="syncing" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition-colors">
									<svg x-show="syncing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
									<span x-text="syncing ? 'Syncing...' : 'Sync Now'"></span>
								</button>
							</div>
							<div x-show="syncing" class="w-full bg-gray-200 rounded-full h-1.5 mt-4 overflow-hidden">
								<div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300 ease-out" :style="`width: ${progress}%`"></div>
							</div>
							<p x-show="syncing" class="text-xs text-indigo-600 font-medium mt-2" x-text="statusMsg"></p>
						</div>
						
						<script>
						function bulkSyncHandler() {
							return {
								syncing: false,
								progress: 0,
								offset: 0,
								statusMsg: '',
								
								startSync() {
									this.syncing = true;
									this.progress = 5;
									this.offset = 0;
									this.statusMsg = 'Starting synchronization...';
									this.processBatch();
								},
								
								processBatch() {
									jQuery.post(ajaxurl, {
										action: 'o100_crm_bulk_sync',
										offset: this.offset,
										nonce: '<?php echo wp_create_nonce("o100_crm_admin_nonce"); ?>'
									}, (response) => {
										if ( response.success ) {
											if ( response.data.finished ) {
												this.progress = 100;
												this.statusMsg = 'Sync complete!';
												setTimeout(() => { this.syncing = false; }, 2000);
											} else {
												this.offset = response.data.next_offset;
												this.statusMsg = response.data.message;
												this.progress = Math.min( 95, this.progress + 5 );
												this.processBatch();
											}
										} else {
											alert('Sync failed: ' + response.data.message);
											this.syncing = false;
										}
									}).fail(() => {
										alert('Network error during sync.');
										this.syncing = false;
									});
								}
							}
						}
						</script>
					</div>
				</div>

				<!-- Section 2: Abandoned Cart Settings -->
				<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
					<div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
						<h3 class="text-base font-semibold text-slate-800">Abandoned Cart Settings</h3>
					</div>
					<div class="p-6">
						<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
							<div>
								<label for="cart_abandoned_time" class="block text-sm font-medium text-slate-700">Cart Abandoned Cut-off</label>
								<div class="mt-1 flex rounded-md shadow-sm">
									<input type="number" name="cart_abandoned_time" id="cart_abandoned_time" min="1" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-slate-300 border" value="<?php echo esc_attr( $cart_abandoned ); ?>">
									<span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-slate-300 bg-slate-50 text-slate-500 sm:text-sm">
										minutes
									</span>
								</div>
								<p class="mt-1 text-xs text-slate-500">Time unpaid before a cart is considered "Abandoned".</p>
							</div>

							<div>
								<label for="cart_lost_time" class="block text-sm font-medium text-slate-700">Cart Lost Time</label>
								<div class="mt-1 flex rounded-md shadow-sm">
									<input type="number" name="cart_lost_time" id="cart_lost_time" min="1" class="flex-1 min-w-0 block w-full px-3 py-2 rounded-none rounded-l-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border-slate-300 border" value="<?php echo esc_attr( $cart_lost ); ?>">
									<span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-slate-300 bg-slate-50 text-slate-500 sm:text-sm">
										hours
									</span>
								</div>
								<p class="mt-1 text-xs text-slate-500">Time unpaid before a cart is permanently marked "Lost".</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Section 3: Compliance -->
				<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
					<div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
						<h3 class="text-base font-semibold text-slate-800">Compliance</h3>
					</div>
					<div class="p-6">
						<div class="space-y-5">
							<div class="flex items-start">
								<div class="flex items-center h-5">
									<input id="one_click_unsubscribe" name="one_click_unsubscribe" type="checkbox" value="1" <?php checked( $unsubscribe, 1 ); ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
								</div>
								<div class="ml-3 text-sm">
									<label for="one_click_unsubscribe" class="font-medium text-slate-700">One-click Unsubscribe</label>
									<p class="text-slate-500">Enable universal unsubscribe headers and links for all outbound CRM emails.</p>
								</div>
							</div>

							<div class="flex items-start">
								<div class="flex items-center h-5">
									<input id="data_deletion" name="data_deletion" type="checkbox" value="1" <?php checked( $data_deletion, 1 ); ?> class="focus:ring-red-500 h-4 w-4 text-red-600 border-slate-300 rounded">
								</div>
								<div class="ml-3 text-sm">
									<label for="data_deletion" class="font-medium text-slate-700">Data Deletion Sync</label>
									<p class="text-slate-500">When a user account is deleted in WordPress, immediately delete all associated CRM profiles and histories.</p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Section 4: Email Opt-in Settings -->
				<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
					<div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
						<h3 class="text-base font-semibold text-slate-800">Email Opt-in</h3>
					</div>
					<div class="p-6">
						<div class="space-y-6">
							<!-- Enable Opt-in -->
							<div class="flex items-start">
								<div class="flex items-center h-5">
									<input id="enable_optin" name="enable_optin" type="checkbox" value="1" <?php checked( $enable_optin, 1 ); ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
								</div>
								<div class="ml-3 text-sm">
									<label for="enable_optin" class="font-medium text-slate-700">Enable Checkout Opt-in</label>
									<p class="text-slate-500">Display an email subscription checkbox on the WooCommerce checkout page.</p>
								</div>
							</div>

							<!-- Opt-in Label -->
							<div>
								<label for="optin_label" class="block text-sm font-medium text-slate-700">Checkbox Label</label>
								<div class="mt-1">
									<input type="text" name="optin_label" id="optin_label" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white" value="<?php echo esc_attr( $optin_label ); ?>">
								</div>
								<p class="mt-1 text-xs text-slate-500">The text displayed next to the checkbox.</p>
							</div>

							<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 pt-2">
								<!-- Checked by Default -->
								<div class="flex items-start">
									<div class="flex items-center h-5">
										<input id="optin_default" name="optin_default" type="checkbox" value="1" <?php checked( $optin_default, 1 ); ?> class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
									</div>
									<div class="ml-3 text-sm">
										<label for="optin_default" class="font-medium text-slate-700">Checked by Default</label>
										<p class="text-slate-500">Pre-check the subscription box.</p>
									</div>
								</div>
							</div>

							<div x-data="{ enableDoubleOptin: <?php echo $double_optin ? 'true' : 'false'; ?>, actionType: '<?php echo esc_js( $double_optin_action ); ?>' }" class="mt-8 border-t border-slate-200 pt-8">
							<h4 class="text-sm font-semibold text-slate-800 mb-4">Double Opt-in</h4>
							
							<div class="flex items-start mb-6">
								<div class="flex items-center h-5">
									<input id="double_optin" name="double_optin" type="checkbox" value="1" x-model="enableDoubleOptin" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
								</div>
								<div class="ml-3 text-sm">
									<label for="double_optin" class="font-medium text-slate-700">Require Email Confirmation</label>
									<p class="text-slate-500">Send a confirmation email to new subscribers before adding them to lists/tags.</p>
								</div>
							</div>

							<div x-show="enableDoubleOptin" x-transition class="space-y-6 bg-slate-50 p-5 rounded-lg border border-slate-200">
								<div>
									<label for="double_optin_subject" class="block text-sm font-medium text-slate-700">Confirmation Email Subject</label>
									<div class="mt-1">
										<input type="text" name="double_optin_subject" id="double_optin_subject" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white" value="<?php echo esc_attr( $double_optin_subject ); ?>">
									</div>
								</div>

								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Confirmation Email Body</label>
									<p class="text-xs text-slate-500 mb-2">Use the variable <code>{{confirm_link}}</code> for the confirmation URL.</p>
									<div class="bg-white">
										<?php 
										/*
										wp_editor( $double_optin_body, 'double_optin_body', [
											'media_buttons' => false,
											'textarea_rows' => 6,
											'teeny'         => true,
											'quicktags'     => false
										] ); 
										*/
										echo '<textarea name="double_optin_body" rows="6" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white">' . esc_textarea($double_optin_body) . '</textarea>';
										?>
									</div>
								</div>

								<div class="pt-4 border-t border-slate-200 mt-4">
									<label class="block text-sm font-medium text-slate-700 mb-2">After Confirmation Action</label>
									<div class="flex items-center space-x-6 mb-4">
										<label class="flex items-center text-sm text-slate-700 cursor-pointer">
											<input type="radio" name="double_optin_action" value="message" x-model="actionType" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 mr-2"> Show Message
										</label>
										<label class="flex items-center text-sm text-slate-700 cursor-pointer">
											<input type="radio" name="double_optin_action" value="redirect" x-model="actionType" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 mr-2"> Redirect to URL
										</label>
									</div>

									<div x-show="actionType === 'message'">
										<textarea name="double_optin_val" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white" placeholder="Thank you for confirming..."><?php echo $double_optin_action === 'message' ? esc_textarea( $double_optin_val ) : ''; ?></textarea>
									</div>

									<div x-show="actionType === 'redirect'">
										<input type="url" name="double_optin_val" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white" placeholder="https://yourdomain.com/thank-you" value="<?php echo $double_optin_action === 'redirect' ? esc_url( $double_optin_val ) : ''; ?>">
									</div>
								</div>
							</div>
						</div>

								<!-- Opt-in Location -->
								<div>
									<label for="optin_location" class="block text-sm font-medium text-slate-700">Display Location</label>
									<div class="mt-1">
										<select id="optin_location" name="optin_location" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md p-2 border bg-white">
											<option value="woocommerce_review_order_before_submit" <?php selected( $optin_location, 'woocommerce_review_order_before_submit' ); ?>>Right Before "Place Order" Button (Highly Recommended)</option>
											<option value="woocommerce_checkout_terms_and_conditions" <?php selected( $optin_location, 'woocommerce_checkout_terms_and_conditions' ); ?>>Inside Terms & Conditions Wrapper</option>
											<option value="woocommerce_after_order_notes" <?php selected( $optin_location, 'woocommerce_after_order_notes' ); ?>>After Order Notes</option>
											<option value="woocommerce_after_checkout_billing_form" <?php selected( $optin_location, 'woocommerce_after_checkout_billing_form' ); ?>>After Billing Form</option>
											<option value="woocommerce_checkout_before_customer_details" <?php selected( $optin_location, 'woocommerce_checkout_before_customer_details' ); ?>>Top of Checkout Page</option>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

			</form>
		</div>
	</div>

	<script>
	function crmSettingsForm() {
		return {
			saving: false,
			saved: false,
			saveSettings() {
				if (this.saving) return;
				this.saving = true;
				this.saved = false;
				
				const form = document.getElementById('crm-settings-form');
				const formData = new FormData(form);
				
				const data = {
					action: 'o100_crm_save_settings',
					nonce: formData.get('o100_crm_settings_nonce'),
					default_list: formData.get('default_list'),
					default_tag: formData.get('default_tag'),
					cart_abandoned_time: formData.get('cart_abandoned_time'),
					cart_lost_time: formData.get('cart_lost_time'),
					one_click_unsubscribe: document.getElementById('one_click_unsubscribe').checked,
					data_deletion: document.getElementById('data_deletion').checked,
					enable_optin: document.getElementById('enable_optin').checked,
					optin_label: formData.get('optin_label'),
					optin_default: document.getElementById('optin_default').checked,
					optin_location: formData.get('optin_location'),
					double_optin: document.getElementById('double_optin').checked,
					double_optin_subject: formData.get('double_optin_subject'),
					double_optin_body: formData.get('double_optin_body'),
					double_optin_action: formData.get('double_optin_action'),
					double_optin_val: formData.get('double_optin_val')
				};

				jQuery.post(ajaxurl, data, (response) => {
					this.saving = false;
					if (response.success) {
						this.saved = true;
						setTimeout(() => { this.saved = false; }, 3000);
					} else {
						alert('Error saving settings: ' + (response.data?.message || 'Unknown error'));
					}
				}).fail(() => {
					this.saving = false;
					alert('Network error occurred while saving.');
				});
			}
		}
	}
	</script>
</div>
