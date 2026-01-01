<?php
/**
 * Promotions Admin UI
 *
 * Renders the Step-by-Step UI and List Table for Promotions.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Admin {

	public static function init() {
		add_action( 'wp_ajax_o100_save_promotion', [ __CLASS__, 'handle_save' ] );
		add_action( 'wp_ajax_o100_delete_promotion', [ __CLASS__, 'handle_delete' ] );
		add_action( 'wp_ajax_o100_toggle_promotion', [ __CLASS__, 'handle_toggle' ] );
		add_action( 'wp_ajax_o100_get_promotion', [ __CLASS__, 'handle_get' ] );
	}

	public static function render_page() {
		?>
		<script src="https://cdn.tailwindcss.com"></script>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
		<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
		<script>
			tailwind.config = {
				theme: {
					extend: {
						colors: {
							primary: '#4F46E5', // Indigo 600
							'primary-dark': '#4338CA', // Indigo 700
						}
					}
				}
			}
		</script>
		<style>
tt.o100-mcd-wrapper { padding: 4px; min-height: 36px; border-radius: 6px; }
tt.o100-mcd-pill { padding: 2px 8px; font-size: 12px; margin: 2px; }
tt.o100-mcd-input { padding: 0 4px; height: 28px; }
			.o100-proxy-wrap { padding: 2rem; background: #F8FAFC; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			.o100-proxy-wrap * { box-sizing: border-box; }
			
			/* Wizard Modal Baseline */
			.o100-wizard-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 99999; display: none; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
			.o100-wizard-overlay.is-open { display: flex; opacity: 1; }
			.o100-wizard-modal { background: #fff; width: 100%; max-width: 900px; max-height: 90vh; border-radius: 1.5rem; overflow: hidden; display: flex; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: translateY(20px); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
			.o100-wizard-overlay.is-open .o100-wizard-modal { transform: translateY(0); }
			
			/* Stepper Styles */
			.o100-step-item { position: relative; padding-bottom: 2rem; }
			.o100-step-item:last-child { padding-bottom: 0; }
			.o100-step-item:not(:last-child)::after { content: ''; position: absolute; left: 1rem; top: 2.5rem; bottom: 0; width: 2px; background: #E2E8F0; transform: translateX(-50%); }
			.o100-step-indicator { width: 2rem; height: 2rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem; z-index: 10; position: relative; border: 2px solid; }
			
			/* States */
			.o100-step-item.is-active .o100-step-indicator { border-color: #4F46E5; color: #4F46E5; background: #EEF2FF; }
			.o100-step-item.is-completed .o100-step-indicator { background: #4F46E5; border-color: #4F46E5; color: white; }
			.o100-step-item.is-pending .o100-step-indicator { border-color: #E2E8F0; color: #94A3B8; background: white; }
			
			/* Form Steps Visibility */
			.o100-form-step { display: none; }
			.o100-form-step.is-active { display: block; animation: fadeInRight 0.3s ease; }
			
			@keyframes fadeInRight { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
		</style>

		<div class="o100-proxy-wrap">
			<div class="max-w-6xl mx-auto">
				<!-- Header -->
				<div class="mb-8 flex items-center justify-between">
					<div>
						<h1 class="text-3xl font-bold text-slate-900 tracking-tight">Promotions & Marketing</h1>
						<p class="text-slate-500 mt-2 text-sm">Create and manage discounts, BOGO offers, and coupons.</p>
					</div>
					<div>
						<button onclick="o100PromoWizard.open(0)" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-medium shadow-sm hover:bg-indigo-700 transition-colors">
							+ Create Promotion
						</button>
					</div>
				</div>

				<!-- Toolbar: Search / Filter / Sort -->
				<div class="mb-4 flex flex-wrap items-center gap-3">
					<div class="relative flex-1 min-w-[200px] max-w-xs">
						<input type="text" id="o100-promo-search" placeholder="Search rules..." class="w-full border border-slate-300 rounded-lg py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="padding-left:36px;padding-right:12px;" oninput="o100PromoFilter()">
						<svg class="absolute w-4 h-4 text-slate-400" style="left:12px;top:50%;transform:translateY(-50%);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
					</div>
					<select id="o100-promo-filter-type" class="border border-slate-300 rounded-lg py-2 text-sm text-slate-700 bg-white" style="padding-left:12px;padding-right:32px;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%222%22><polyline points=%226 9 12 15 18 9%22/></svg>');background-repeat:no-repeat;background-position:right 10px center;" onchange="o100PromoFilter()">
						<option value="">All Types</option>
						<option value="Simple">Simple</option>
						<option value="BOGO">BOGO</option>
						<option value="Buy X Get Y">Buy X Get Y</option>
						<option value="Bulk/Tiered">Bulk/Tiered</option>
						<option value="Bundle">Bundle</option>
					</select>
					<select id="o100-promo-filter-status" class="border border-slate-300 rounded-lg py-2 text-sm text-slate-700 bg-white" style="padding-left:12px;padding-right:32px;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%222%22><polyline points=%226 9 12 15 18 9%22/></svg>');background-repeat:no-repeat;background-position:right 10px center;" onchange="o100PromoFilter()">
						<option value="">All Status</option>
						<option value="enabled">Enabled</option>
						<option value="disabled">Disabled</option>
					</select>
					<select id="o100-promo-sort" class="border border-slate-300 rounded-lg py-2 text-sm text-slate-700 bg-white" style="padding-left:12px;padding-right:32px;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%222%22><polyline points=%226 9 12 15 18 9%22/></svg>');background-repeat:no-repeat;background-position:right 10px center;" onchange="o100PromoSort()">
						<option value="newest">Sort: Newest</option>
						<option value="oldest">Sort: Oldest</option>
						<option value="name-asc">Sort: Name A→Z</option>
						<option value="name-desc">Sort: Name Z→A</option>
						<option value="priority">Sort: Priority</option>
					</select>
				</div>

				<!-- Active Promotions Table -->
				<div>
					<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
						<style>
						/* Responsive column priority system — more granular breakpoints */
						@media (max-width: 1100px) { .o100-col-p7 { display: none; } }
						@media (max-width: 1000px) { .o100-col-p6 { display: none; } }
						@media (max-width: 900px)  { .o100-col-p5 { display: none; } }
						@media (max-width: 750px)  { .o100-col-p4 { display: none; } }
						@media (max-width: 550px)  { .o100-col-p3 { display: none; } }
						/* p1 = Name, p2 = Actions — always visible */
						
						/* Toggle Switch */
						.o100-toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
						.o100-toggle input { opacity: 0; width: 0; height: 0; }
						.o100-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 20px; transition: 0.3s; }
						.o100-toggle-slider:before { content: ''; position: absolute; height: 14px; width: 14px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
						.o100-toggle input:checked + .o100-toggle-slider { background: #22c55e; }
						.o100-toggle input:checked + .o100-toggle-slider:before { transform: translateX(16px); }
					</style>
					<div style="overflow-x: auto;">
					<table class="w-full text-left border-collapse" style="min-width: 360px;">
						<thead>
							<tr class="bg-slate-50 border-b border-slate-200 text-sm text-slate-500">
								<th class="py-3 px-4 font-medium o100-col-p1">Name</th>
								<th class="py-3 px-4 font-medium o100-col-p3">Type</th>
								<th class="py-3 px-4 font-medium o100-col-p3">Discount</th>
								<th class="py-3 px-4 font-medium o100-col-p4">Code</th>
								<th class="py-3 px-4 font-medium o100-col-p5">Target</th>
								<th class="py-3 px-4 font-medium o100-col-p6">Priority</th>
								<th class="py-3 px-4 font-medium o100-col-p7">Usage</th>
								<th class="py-3 px-4 font-medium text-center o100-col-p3" style="width:60px;">Enabled</th>
								<th class="py-3 px-4 font-medium text-right o100-col-p2">Actions</th>
							</tr>
						</thead>
						<tbody id="promo-table-body">
							<?php
							require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
							$promotions = O100_Promotions_DB::query();
							if ( ! empty( $promotions ) ) {
								foreach ( $promotions as $promo ) {
									$type_labels = [
										'simple' => 'Simple',
										'bogo' => 'BOGO',
										'buy_x_get_y' => 'Buy X Get Y',
										'bulk_tiered' => 'Bulk/Tiered',
										'bundle' => 'Bundle'
									];
									$type_label = isset( $type_labels[ $promo['rule_type'] ] ) ? $type_labels[ $promo['rule_type'] ] : $promo['rule_type'];
									
									// Is it locked?
									$is_locked = ( $promo['source'] !== 'manual' );
									$locked_label = '';
									if ( $promo['source'] === 'loyalty' ) $locked_label = '<span class="ml-2 text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">Loyalty Reward</span>';
									if ( $promo['source'] === 'automation' ) $locked_label = '<span class="ml-2 text-xs bg-pink-100 text-pink-700 px-2 py-0.5 rounded-full">Automation</span>';

									$code_label = ! empty( $promo['promo_code'] ) ? $promo['promo_code'] : '<span class="text-slate-400 italic">Auto</span>';
									
									// Parse action_config for discount summary
									$config = json_decode( $promo['action_config'], true );
									$discount_summary = '—';
									if ( $promo['rule_type'] === 'simple' && ! empty( $config ) ) {
										$dt = isset( $config['discount_type'] ) ? $config['discount_type'] : '';
										$dv = isset( $config['discount_value'] ) ? $config['discount_value'] : '';
										if ( $dt === 'percentage' && $dv ) $discount_summary = $dv . '%';
										elseif ( $dt === 'fixed' && $dv ) $discount_summary = '$' . $dv;
										elseif ( $dt === 'free_shipping' ) $discount_summary = 'Free Ship';
										elseif ( $dt === 'free_item' ) $discount_summary = 'Free Item';
										elseif ( $dt === 'fixed_set_price' && $dv ) $discount_summary = 'Set $' . $dv;
									} elseif ( $promo['rule_type'] === 'bogo' && ! empty( $config ) ) {
										$bq = isset( $config['buy_qty'] ) ? $config['buy_qty'] : 1;
										$gq = isset( $config['get_qty'] ) ? $config['get_qty'] : 1;
										$discount_summary = 'B' . $bq . 'G' . $gq;
									} elseif ( $promo['rule_type'] === 'buy_x_get_y' && ! empty( $config ) ) {
										$discount_summary = 'B' . ( isset( $config['buy_qty'] ) ? $config['buy_qty'] : '?' ) . 'G' . ( isset( $config['get_qty'] ) ? $config['get_qty'] : '?' );
									} elseif ( $promo['rule_type'] === 'bulk_tiered' && ! empty( $config ) ) {
										$tiers = isset( $config['tiers'] ) ? $config['tiers'] : [];
										$discount_summary = count( $tiers ) . ' tiers';
									} elseif ( $promo['rule_type'] === 'bundle' && ! empty( $config ) ) {
										$dv = isset( $config['discount_value'] ) ? $config['discount_value'] : '';
										$dt = isset( $config['discount_type'] ) ? $config['discount_type'] : '';
										$sq = isset( $config['set_qty'] ) ? $config['set_qty'] : '';
										$discount_summary = $sq . 'pcs ' . ( $dt === 'percentage' ? $dv . '%' : '$' . $dv );
									}
									
									$is_disabled = ( $promo['status'] === 'disabled' );
									$row_opacity = $is_disabled ? ' opacity-50' : '';

									// Determine Target
									$target_label = 'All';
									if ( $promo['apply_to'] === 'specific_products' ) {
										$target_label = 'Products';
									} elseif ( $promo['apply_to'] === 'specific_categories' ) {
										$target_label = 'Categories';
									}

									echo '<tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors' . $row_opacity . ' o100-promo-row" id="promo-row-' . intval( $promo['id'] ) . '" data-name="' . esc_attr( strtolower( $promo['title'] ) ) . '" data-type="' . esc_attr( $type_label ) . '" data-status="' . ( $is_disabled ? 'disabled' : 'enabled' ) . '" data-priority="' . intval( $promo['priority'] ) . '" data-id="' . intval( $promo['id'] ) . '">';
									echo '<td class="py-3 px-4 o100-col-p1"><div class="font-bold text-slate-900">' . esc_html( $promo['title'] ) . $locked_label . '</div></td>';
									echo '<td class="py-3 px-4 text-sm text-slate-600 o100-col-p3">' . esc_html( $type_label ) . '</td>';
									echo '<td class="py-3 px-4 text-sm text-slate-600 font-semibold o100-col-p3">' . $discount_summary . '</td>';
									echo '<td class="py-3 px-4 text-sm text-slate-600 font-mono o100-col-p4">' . $code_label . '</td>';
									echo '<td class="py-3 px-4 text-sm text-slate-600 o100-col-p5">' . esc_html( $target_label ) . '</td>';
									echo '<td class="py-3 px-4 text-sm text-slate-500 o100-col-p6">' . intval( $promo['priority'] ) . '</td>';
									echo '<td class="py-3 px-4 text-sm text-slate-600 o100-col-p7">' . intval( $promo['usage_count'] ) . ( $promo['usage_limit'] > 0 ? '/' . intval( $promo['usage_limit'] ) : '/∞' ) . '</td>';
									echo '<td class="py-3 px-4 text-center o100-col-p3">';
									echo '<label class="o100-toggle">';
									echo '<input type="checkbox" ' . ( ! $is_disabled ? 'checked' : '' ) . ' onchange="o100PromoWizard.toggleStatus(' . intval( $promo['id'] ) . ', this.checked)">';
									echo '<span class="o100-toggle-slider"></span>';
									echo '</label>';
									echo '</td>';
									echo '<td class="py-3 px-4 text-right o100-col-p2">';
									if ( ! $is_locked ) {
										echo '<button onclick="o100PromoWizard.open(' . intval( $promo['id'] ) . ')" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm mr-3">Edit</button>';
									}
									echo '<button onclick="o100PromoWizard.deletePromo(' . intval( $promo['id'] ) . ')" class="text-red-500 hover:text-red-700 font-medium text-sm">Delete</button>';
									echo '</td>';
									echo '</tr>';
								}
							} else {
								echo '<tr><td colspan="9" class="py-8 text-center text-slate-500">No active promotions found. Click "+ Create Promotion" to start!</td></tr>';
							}
							?>
						<tr id="o100-promo-no-results" style="display:none;"><td colspan="9" class="py-8 text-center text-slate-400">No matching promotions found.</td></tr>
						</tbody>
					</table>
					</div>
					</div>
				</div>
			</div>
		</div>

		<!-- The Wizard Modal -->
		<div id="o100-promo-wizard" class="o100-wizard-overlay">
			<div class="o100-wizard-modal relative">
				<!-- Left Sidebar: Stepper -->
				<div class="w-72 bg-slate-50 border-r border-slate-200 p-8 flex flex-col">
					<h2 class="text-xl font-bold text-slate-900 mb-8" id="promo-wizard-title">Create Promotion</h2>
					
					<div class="flex-1">
						<div class="o100-step-item is-active cursor-pointer" id="promo-step-nav-1" onclick="o100PromoWizard.goToStep(1)">
							<div class="flex items-start">
								<div class="o100-step-indicator">1</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Basic & Type</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending cursor-pointer" id="promo-step-nav-2" onclick="o100PromoWizard.goToStep(2)">
							<div class="flex items-start">
								<div class="o100-step-indicator">2</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Discount Value</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending cursor-pointer" id="promo-step-nav-3" onclick="o100PromoWizard.goToStep(3)">
							<div class="flex items-start">
								<div class="o100-step-indicator">3</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Target & Common Rules</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending cursor-pointer" id="promo-step-nav-4" onclick="o100PromoWizard.goToStep(4)">
							<div class="flex items-start">
								<div class="o100-step-indicator">4</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Advanced Conditions</h4>
								</div>
							</div>
						</div>
						<div class="o100-step-item is-pending cursor-pointer" id="promo-step-nav-5" onclick="o100PromoWizard.goToStep(5)">
							<div class="flex items-start">
								<div class="o100-step-indicator">5</div>
								<div class="ml-4">
									<h4 class="text-sm font-bold text-slate-900">Promo Display</h4>
								</div>
							</div>
						</div>
					</div>
					
					<div class="pt-6 border-t border-slate-200">
						<button class="text-sm text-slate-500 hover:text-slate-900 font-medium" onclick="o100PromoWizard.close()">Cancel & Exit</button>
					</div>
				</div>

				<!-- Right Content -->
				<div class="flex-1 bg-white p-10 flex flex-col relative overflow-y-auto">
					<input type="hidden" id="promo_id" value="0">
					
					<!-- Step 1 -->
					<div class="o100-form-step is-active" id="promo-step-content-1">
						<h3 class="text-2xl font-bold text-slate-900 mb-6">Basic Information</h3>
						<div class="space-y-6">
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Rule Name <span class="text-red-500">*</span></label>
								<input type="text" id="promo_title" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. Lunch BOGO Deal">
							</div>
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Priority</label>
								<input type="number" id="promo_priority" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="10" value="10">
								<p class="text-xs text-slate-500 mt-1">Lower number = higher priority</p>
							</div>
							<div>
								<label class="flex items-center space-x-2">
									<input type="checkbox" id="promo_is_exclusive" value="1" class="w-4 h-4 text-indigo-600 border-slate-300 rounded">
									<span class="text-sm font-bold text-slate-700">Exclusive (cannot stack with other rules)</span>
								</label>
							</div>
							<div class="border-t border-slate-100 pt-6">
								<label class="block text-sm font-bold text-slate-700 mb-3">Promo Code Setup</label>
								<div class="flex items-center space-x-6">
									<label class="flex items-center space-x-2">
										<input type="radio" name="promo_code_type" value="auto" checked class="w-4 h-4 text-indigo-600">
										<span class="text-sm font-medium text-slate-700">Auto-Apply at Checkout</span>
									</label>
									<label class="flex items-center space-x-2">
										<input type="radio" name="promo_code_type" value="code" class="w-4 h-4 text-indigo-600">
										<span class="text-sm font-medium text-slate-700">Require Code</span>
									</label>
								</div>
								<div id="promo_code_input_wrap" class="mt-3 hidden space-y-4">
									<input type="text" id="promo_code" class="w-full border border-slate-300 rounded-lg px-3 py-2 font-mono" placeholder="e.g. SUMMER2026" onkeyup="if(document.getElementById('promo_url_coupon_enabled').checked) document.getElementById('promo_url_input').value = window.location.origin + '/?coupon=' + this.value;">
									<div class="bg-indigo-50 p-4 rounded-lg border border-indigo-100">
										<label class="flex items-center space-x-2 cursor-pointer mb-3">
											<input type="checkbox" id="promo_url_coupon_enabled" class="w-4 h-4 text-indigo-600 border-indigo-300 rounded" onchange="document.getElementById('promo_url_box').style.display = this.checked ? 'block' : 'none'; document.getElementById('promo_url_input').value = window.location.origin + '/?coupon=' + document.getElementById('promo_code').value;">
											<span class="text-sm font-bold text-indigo-900">Enable URL Coupon</span>
										</label>
										<div id="promo_url_box" style="display:none;">
											<p class="text-xs text-indigo-700 mb-2">Share this link with your customers. The coupon will be automatically applied when they visit.</p>
											<div class="flex items-center gap-0 overflow-hidden border border-indigo-200 rounded-lg shadow-sm">
												<input type="text" id="promo_url_input" readonly class="flex-1 border-none text-sm px-3 py-2 bg-white text-indigo-600 font-mono focus:ring-0 outline-none">
												<button type="button" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 text-sm transition-colors border-none" onclick="navigator.clipboard.writeText(document.getElementById('promo_url_input').value); var orig=this.innerText; this.innerText='Copied!'; setTimeout(()=>this.innerText=orig, 2000);">Copy</button>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					
					<!-- Step 2 -->
					<div class="o100-form-step" id="promo-step-content-2">
						<h3 class="text-2xl font-bold text-slate-900 mb-6">Discount Configuration</h3>
						
						<div class="grid grid-cols-2 gap-4 mb-6">
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Scope</label>
								<select id="promo_level" class="w-full border border-slate-300 rounded-lg px-3 py-2">
									<option value="cart">Cart Level (Applies to Order Total)</option>
									<option value="product">Product Level (Applies to Item Price)</option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Rule Type</label>
								<select id="promo_rule_type" class="w-full border border-slate-300 rounded-lg px-3 py-2">
									<option value="simple">Simple Discount</option>
									<option value="bogo">BOGO (Buy X Get X)</option>
									<option value="buy_x_get_y">Buy X Get Y</option>
									<option value="bulk_tiered">Bulk / Tiered Discount</option>
									<option value="bundle">Set / Bundle Discount</option>
								</select>
							</div>
						</div>
						<hr class="border-slate-100 mb-6">

						<!-- Simple Discount Block -->
						<div id="promo_wrap_simple" class="space-y-6 promo-type-wrap hidden">
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Discount Type</label>
								<select id="promo_simple_discount_type" class="w-full border border-slate-300 rounded-lg px-3 py-2">
									<option value="percentage">Percentage Off</option>
									<option value="fixed">Fixed Amount Off</option>
									<option value="free_item">Free Item</option>
									<option value="free_shipping">Free Delivery</option>
									<option value="fixed_set_price">Fixed Set Price</option>
								</select>
							</div>
							<div id="promo_simple_discount_value_wrap">
								<label class="block text-sm font-bold text-slate-700 mb-2">Discount Value</label>
								<input type="number" id="promo_simple_discount_value" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. 10">
							</div>
							<!-- Custom o100 MCD wrapper for free products (Simple Mode) -->
							<div id="promo_simple_free_item_wrap" class="hidden">
								<label class="block text-sm font-bold text-slate-700 mb-2">Select Free Item</label>
								<div class="o100-mcd-wrapper" data-type="product" data-field-id="promo_free_item_id">
									<input type="hidden" name="promo_free_item_id" id="promo_free_item_id" value="" class="o100-mcd-hidden-input">
									<div class="o100-mcd-header">
										<div class="o100-mcd-header-text"><span class="o100-mcd-placeholder">Selecting...</span></div>
										<i class="dashicons dashicons-arrow-down-alt2"></i>
									</div>
									<div class="o100-mcd-popover">
										<div class="o100-mcd-search"><input type="text" placeholder="Search unselected..."><span class="dashicons dashicons-search"></span></div>
										<div class="o100-mcd-list"><div class="o100-mcd-results"></div></div>
									</div>
								</div>
							</div>
						</div>

						<!-- BOGO Block -->
						<div id="promo_wrap_bogo" class="space-y-6 promo-type-wrap hidden">
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Buy Quantity</label>
									<input type="number" id="promo_bogo_buy_qty" class="w-full border border-slate-300 rounded-lg px-3 py-2" value="1" min="1">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Get Quantity</label>
									<input type="number" id="promo_bogo_get_qty" class="w-full border border-slate-300 rounded-lg px-3 py-2" value="1" min="1">
								</div>
							</div>
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Get Item Discount Type</label>
									<select id="promo_bogo_discount_type" class="w-full border border-slate-300 rounded-lg px-3 py-2">
										<option value="free">Free (100% off)</option>
										<option value="percentage">Percentage Off (%)</option>
										<option value="fixed">Fixed Amount Off ($)</option>
									</select>
								</div>
								<div id="promo_bogo_discount_value_wrap" class="hidden">
									<label class="block text-sm font-bold text-slate-700 mb-2">Discount Value</label>
									<input type="number" id="promo_bogo_discount_value" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. 50">
								</div>
							</div>
						</div>

						<!-- Buy X Get Y Block -->
						<div id="promo_wrap_buy_x_get_y" class="space-y-6 promo-type-wrap hidden">
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Buy Quantity (of X)</label>
									<input type="number" id="promo_bxgy_buy_qty" class="w-full border border-slate-300 rounded-lg px-3 py-2" value="1" min="1">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Get Quantity (of Y)</label>
									<input type="number" id="promo_bxgy_get_qty" class="w-full border border-slate-300 rounded-lg px-3 py-2" value="1" min="1">
								</div>
							</div>
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Get Item Discount Type</label>
									<select id="promo_bxgy_discount_type" class="w-full border border-slate-300 rounded-lg px-3 py-2">
										<option value="free">Free (100% off)</option>
										<option value="percentage">Percentage Off (%)</option>
										<option value="fixed">Fixed Amount Off ($)</option>
									</select>
								</div>
								<div id="promo_bxgy_discount_value_wrap" class="hidden">
									<label class="block text-sm font-bold text-slate-700 mb-2">Discount Value</label>
									<input type="number" id="promo_bxgy_discount_value" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. 50">
								</div>
							</div>
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Select Product Y</label>
								<div class="o100-mcd-wrapper" data-type="product" data-field-id="promo_bxgy_product_y">
									<input type="hidden" name="promo_bxgy_product_y" id="promo_bxgy_product_y" value="" class="o100-mcd-hidden-input">
									<div class="o100-mcd-header"><div class="o100-mcd-header-text"><span class="o100-mcd-placeholder">Selecting...</span></div><i class="dashicons dashicons-arrow-down-alt2"></i></div>
									<div class="o100-mcd-popover"><div class="o100-mcd-search"><input type="text" placeholder="Search product Y..."><span class="dashicons dashicons-search"></span></div><div class="o100-mcd-list"><div class="o100-mcd-results"></div></div></div>
								</div>
							</div>
						</div>

						<!-- Bulk / Tiered Discount Block -->
						<div id="promo_wrap_bulk_tiered" class="space-y-6 promo-type-wrap hidden">
							<label class="block text-sm font-bold text-slate-700 mb-2">Bulk Discount Tiers</label>
							<div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
								<div id="promo-tiers-container" class="space-y-3 mb-4">
									<div class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white text-slate-500 text-sm">No tiers added.</div>
								</div>
								<button type="button" onclick="o100PromoWizard.addTierUI()" class="text-indigo-600 font-bold text-sm hover:text-indigo-800">
									+ Add Tier
								</button>
							</div>
						</div>

						<!-- Bundle Block -->
						<div id="promo_wrap_bundle" class="space-y-6 promo-type-wrap hidden">
							<p class="text-sm text-slate-500 mb-4">Customer must buy exactly the "Set Quantity" to get the discount.</p>
							<div class="grid grid-cols-3 gap-4">
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Set Quantity</label>
									<input type="number" id="promo_bundle_qty" class="w-full border border-slate-300 rounded-lg px-3 py-2" value="2" min="2">
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Discount Type</label>
									<select id="promo_bundle_discount_type" class="w-full border border-slate-300 rounded-lg px-3 py-2">
										<option value="percentage">Percentage (%)</option>
										<option value="fixed">Fixed Amount ($)</option>
									</select>
								</div>
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2">Discount Value</label>
									<input type="number" id="promo_bundle_discount_value" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. 10">
								</div>
							</div>
						</div>
					</div>

					<!-- Step 3 -->
					<div class="o100-form-step" id="promo-step-content-3">
						<h3 class="text-2xl font-bold text-slate-900 mb-6">Target Scope & Common Rules</h3>
						<div class="space-y-6">
							<div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
								<h4 class="font-bold text-slate-900 mb-4">Target Products/Categories</h4>
								<div class="mb-4">
									<label class="block text-sm font-bold text-slate-700 mb-2">Apply To</label>
									<select id="promo_apply_to" class="w-full border border-slate-300 rounded-lg px-3 py-2">
										<option value="all_products">All Products</option>
										<option value="specific_products">Specific Products</option>
										<option value="specific_categories">Specific Categories</option>
									</select>
								</div>
								
								<!-- Custom Selectors -->
								<div id="promo_apply_items_wrap" class="hidden">
									<label class="block text-sm font-bold text-slate-700 mb-2">Select Items</label>
									<!-- Product Selector -->
									<div id="promo_apply_products_mcd" class="o100-mcd-wrapper" data-type="product" data-field-id="promo_apply_items_products">
										<input type="hidden" name="promo_apply_items_products" id="promo_apply_items_products" value="" class="o100-mcd-hidden-input">
										<div class="o100-mcd-header"><div class="o100-mcd-header-text"><span class="o100-mcd-placeholder">Selecting products...</span></div><i class="dashicons dashicons-arrow-down-alt2"></i></div>
										<div class="o100-mcd-popover"><div class="o100-mcd-search"><input type="text" placeholder="Search products..."><span class="dashicons dashicons-search"></span></div><div class="o100-mcd-list"><div class="o100-mcd-results"></div></div></div>
									</div>
									<!-- Category Selector -->
									<div id="promo_apply_categories_mcd" class="hidden o100-mcd-wrapper" data-type="category" data-field-id="promo_apply_items_categories">
										<input type="hidden" name="promo_apply_items_categories" id="promo_apply_items_categories" value="" class="o100-mcd-hidden-input">
										<div class="o100-mcd-header"><div class="o100-mcd-header-text"><span class="o100-mcd-placeholder">Selecting categories...</span></div><i class="dashicons dashicons-arrow-down-alt2"></i></div>
										<div class="o100-mcd-popover"><div class="o100-mcd-search"><input type="text" placeholder="Search categories..."><span class="dashicons dashicons-search"></span></div><div class="o100-mcd-list"><div class="o100-mcd-results"></div></div></div>
									</div>
								</div>
							</div>

							<div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
								<h4 class="font-bold text-slate-900 mb-4">Common Rules (Optional)</h4>
								<div class="grid grid-cols-2 gap-4 mb-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Start Date</label>
										<input type="date" id="promo_start_date" class="w-full border border-slate-300 rounded-lg px-3 py-2">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">End Date</label>
										<input type="date" id="promo_end_date" class="w-full border border-slate-300 rounded-lg px-3 py-2">
									</div>
								</div>
								<div class="grid grid-cols-2 gap-4 mb-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Minimum Order Amount ($)</label>
										<input type="number" id="promo_min_order" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. 50">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Usage Limit</label>
										<input type="number" id="promo_usage_limit" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="Max total uses">
									</div>
								</div>
								<div class="grid grid-cols-2 gap-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Order Method</label>
										<select id="promo_order_method" class="w-full border border-slate-300 rounded-lg px-3 py-2">
											<option value="both">Both (Delivery & Pickup)</option>
											<option value="delivery">Delivery Only</option>
											<option value="pickup">Pickup Only</option>
										</select>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Location/Branch</label>
										<select id="promo_branch" class="w-full border border-slate-300 rounded-lg px-3 py-2">
											<option value="all">All Locations</option>
											<?php 
												$locs = get_option('o100_locations', []);
												if(!empty($locs['o100_loc_items'])) {
													foreach($locs['o100_loc_items'] as $loc) {
														echo '<option value="' . esc_attr($loc['id']) . '">' . esc_html($loc['name']) . '</option>';
													}
												}
											?>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Step 4 -->
					<div class="o100-form-step" id="promo-step-content-4">
						<h3 class="text-2xl font-bold text-slate-900 mb-6">Conditions</h3>
						<div class="mb-4">
							<label class="flex items-center space-x-2">
								<span class="text-sm font-bold text-slate-700">Condition Logic:</span>
								<select id="promo_conditions_logic" class="border border-slate-300 rounded-lg px-2 py-1 text-sm">
									<option value="all">Match ALL Conditions</option>
									<option value="any">Match ANY Condition</option>
								</select>
							</label>
						</div>
						<div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
							<div id="promo-conditions-container" class="space-y-3 mb-4">
								<div class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white text-slate-500 text-sm">
									No conditions set. Promotion applies to all valid orders.
								</div>
							</div>
							<button type="button" onclick="o100PromoWizard.addConditionUI()" class="text-indigo-600 font-bold text-sm hover:text-indigo-800">
								+ Add Condition
							</button>
						</div>
					</div>

					<!-- Step 5 -->
					<div class="o100-form-step" id="promo-step-content-5">
						<h3 class="text-2xl font-bold text-slate-900 mb-6">Promo Display & Notifications</h3>
						
						<div class="space-y-6">
							<div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
								<h4 class="font-bold text-slate-900 mb-4">Basic Display</h4>
								<div class="space-y-4">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Promo Subtitle</label>
										<input type="text" id="promo_desc" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. Add 2 eligible items">
										<p class="text-xs text-slate-500 mt-1">Displayed below price on product cards and popups.</p>
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Show On-Sale Badge</label>
										<input type="text" id="promo_badge" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. SALE or 20% OFF">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Show Promotional Banner</label>
										<input type="text" id="promo_banner" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. Limited time offer!">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Terms & Conditions (Optional)</label>
										<textarea id="promo_terms_conditions" class="w-full border border-slate-300 rounded-lg px-3 py-2" rows="3" placeholder="e.g. Limit 3 eligible free items per order."></textarea>
									</div>
								</div>
							</div>

							<div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
								<div class="flex items-center justify-between mb-4">
									<h4 class="font-bold text-slate-900">Popup Notification</h4>
									<label class="flex items-center space-x-2 cursor-pointer">
										<input type="checkbox" id="promo_popup_enabled" value="1" class="w-4 h-4 text-indigo-600 border-slate-300 rounded">
										<span class="text-sm font-bold text-slate-700">Enable Popup</span>
									</label>
								</div>
								
								<div id="promo_popup_fields" class="space-y-4 opacity-50 pointer-events-none transition-opacity">
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Popup Title</label>
										<input type="text" id="promo_popup_title" class="w-full border border-slate-300 rounded-lg px-3 py-2" placeholder="e.g. 20% off on CA$15+">
									</div>
									<div>
										<label class="block text-sm font-bold text-slate-700 mb-2">Popup Text (HTML allowed)</label>
										<textarea id="promo_popup_text" class="w-full border border-slate-300 rounded-lg px-3 py-2" rows="3" placeholder="e.g. Enjoy free delivery on your first order!"></textarea>
									</div>
									<div class="grid grid-cols-2 gap-4">
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Show Popup On</label>
											<select id="promo_popup_location" class="w-full border border-slate-300 rounded-lg px-3 py-2">
												<option value="all">All pages</option>
												<option value="shop">Shop & Menu Pages Only</option>
												<option value="checkout">Checkout Page Only</option>
												<option value="home">Homepage Only</option>
											</select>
										</div>
										<div>
											<label class="block text-sm font-bold text-slate-700 mb-2">Popup Duration (seconds)</label>
											<input type="number" id="promo_popup_duration" class="w-full border border-slate-300 rounded-lg px-3 py-2" value="5" min="1">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Footer Navigation -->
					<div class="mt-auto pt-8 border-t border-slate-100 flex justify-between items-center">
						<button id="promo-wizard-prev" class="px-6 py-2 border border-slate-300 rounded-xl font-medium text-slate-700 hover:bg-slate-50 opacity-0 pointer-events-none transition-opacity" onclick="o100PromoWizard.prevStep()">Back</button>
						<button id="promo-wizard-next" class="px-6 py-2 bg-indigo-600 text-white rounded-xl font-bold shadow-sm hover:bg-indigo-700 transition-colors" onclick="o100PromoWizard.nextStep()">Continue</button>
					</div>
				</div>
			</div>
		</div>

		<script>
		var o100PromoAjaxUrl = "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>";
		var o100PromoNonce = "<?php echo esc_js( wp_create_nonce( 'o100_promotions_admin' ) ); ?>";

		// Client-side filter & sort
		function o100PromoFilter() {
			var search = document.getElementById('o100-promo-search').value.toLowerCase();
			var typeFilter = document.getElementById('o100-promo-filter-type').value;
			var statusFilter = document.getElementById('o100-promo-filter-status').value;
			var rows = document.querySelectorAll('.o100-promo-row');
			var visible = 0;

			rows.forEach(function(row) {
				var name = row.getAttribute('data-name') || '';
				var type = row.getAttribute('data-type') || '';
				var status = row.getAttribute('data-status') || '';

				var matchSearch = !search || name.indexOf(search) !== -1;
				var matchType = !typeFilter || type === typeFilter;
				var matchStatus = !statusFilter || status === statusFilter;

				if (matchSearch && matchType && matchStatus) {
					row.style.display = '';
					visible++;
				} else {
					row.style.display = 'none';
				}
			});

			// Show/hide no-results message
			var noResults = document.getElementById('o100-promo-no-results');
			if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
		}

		function o100PromoSort() {
			var sortVal = document.getElementById('o100-promo-sort').value;
			var tbody = document.getElementById('promo-table-body');
			var rows = Array.from(tbody.querySelectorAll('.o100-promo-row'));

			rows.sort(function(a, b) {
				switch (sortVal) {
					case 'newest':
						return parseInt(b.getAttribute('data-id')) - parseInt(a.getAttribute('data-id'));
					case 'oldest':
						return parseInt(a.getAttribute('data-id')) - parseInt(b.getAttribute('data-id'));
					case 'name-asc':
						return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
					case 'name-desc':
						return (b.getAttribute('data-name') || '').localeCompare(a.getAttribute('data-name') || '');
					case 'priority':
						return parseInt(a.getAttribute('data-priority')) - parseInt(b.getAttribute('data-priority'));
					default:
						return 0;
				}
			});

			rows.forEach(function(row) { tbody.appendChild(row); });
		}

		// Vanilla JS implementation of the wizard
		// Global Multi-Select Search Component for Products & Categories
			function o100InitMCS(wrapId, searchType) {
				const wrap = document.getElementById(wrapId);
				if (!wrap) return;
				const hidden = wrap.querySelector('.promo-cond-value') || wrap.querySelector('.o100-cond-val');
				const tags = wrap.querySelector('.o100-mcs-tags');
				const input = wrap.querySelector('.o100-mcs-input');
				const dd = wrap.querySelector('.o100-mcs-dd');
				let selected = {};
				let timer = null;
				let fetchedData = null;
				let isFetching = false;
				let theAjaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : (typeof o100PromoAjaxUrl !== 'undefined' ? o100PromoAjaxUrl : '');

				function renderTags() {
					tags.innerHTML = '';
					Object.entries(selected).forEach(([id, name]) => {
						const t = document.createElement('span');
						t.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800';
						t.innerHTML = name + ' <button type="button" class="ml-0.5 text-blue-500 hover:text-red-600 font-bold" data-id="'+id+'">&times;</button>';
						t.querySelector('button').onclick = function(e) { e.stopPropagation(); delete selected[this.dataset.id]; renderTags(); renderDD(fetchedData); };
						tags.appendChild(t);
					});
					hidden.value = Object.keys(selected).join(',');
				}

				function loadOptions(term = '') {
					if (isFetching) return;
					isFetching = true;
					dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Loading...</div>';
					dd.classList.remove('hidden');
					
					const fd = new FormData();
					if (searchType === 'products') {
						fd.append('action', 'o100_mcd_search_products');
					} else {
						fd.append('action', 'o100_mcd_search_categories');
					}
					fd.append('term', term);
					const n = (typeof o100Settings!=='undefined') ? o100Settings.adminNonce : ((typeof o100PromoNonce!=='undefined') ? o100PromoNonce : '');
					fd.append('nonce', n);
					
					fetch(theAjaxUrl, {method:'POST', body:fd})
						.then(r => r.json())
						.then(res => {
							isFetching = false;
							if (res.success && res.data) {
								const mapped = {};
								res.data.forEach(item => { mapped[item.id] = item.text; });
								if (term === '') fetchedData = mapped;
								renderDD(term === '' ? fetchedData : mapped);
							} else {
								dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Error or empty</div>';
							}
						}).catch(() => { isFetching = false; dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">Error</div>'; });
				}

				function renderDD(data) {
					if (!data) return;
					dd.innerHTML = '';
					if (Object.keys(data).length) {
						Object.entries(data).forEach(([id, text]) => {
							const clean = (typeof text === 'string') ? text.replace(/<[^>]*>/g,'') : text;
							const isSelected = !!selected[id];
							const item = document.createElement('label');
							item.className = 'flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 border-b border-slate-100 last:border-0' + (isSelected ? ' bg-blue-50' : '');
							item.innerHTML = '<input type="checkbox" class="rounded" '+(isSelected?'checked':'')+' value="'+id+'"> <span>'+clean+'</span>';
							item.querySelector('input').onchange = function() {
								if (this.checked) { selected[id] = clean; } else { delete selected[id]; }
								renderTags();
								renderDD(data); // Re-render to update background colors
							};
							dd.appendChild(item);
						});
					} else {
						dd.innerHTML = '<div class="px-3 py-2 text-sm text-slate-400">No results</div>';
					}
					dd.classList.remove('hidden');
				}

				input.addEventListener('focus', function() { 
					if (!fetchedData) loadOptions('');
					else { if (!this.value.trim()) renderDD(fetchedData); dd.classList.remove('hidden'); }
				});
				
				input.addEventListener('click', function(e) { 
					e.stopPropagation();
					if (!fetchedData) loadOptions('');
					else { if (!this.value.trim()) renderDD(fetchedData); dd.classList.remove('hidden'); }
				});

				input.addEventListener('input', function() {
					clearTimeout(timer);
					const term = this.value.trim();
					if (term.length === 0) { 
						if (fetchedData) renderDD(fetchedData);
						else loadOptions('');
						return; 
					}
					timer = setTimeout(() => { loadOptions(term); }, 300);
				});

				document.addEventListener('click', function(e) { if (!e.target.closest('#'+wrapId)) dd.classList.add('hidden'); });

				// Expose setValues for refill
				wrap._mcsSetValues = function(ids, names) {
					selected = {};
					if (Array.isArray(ids)) {
						ids.forEach((id, i) => { selected[id] = (names && names[i]) ? names[i] : ('Item #'+id); });
					} else if (typeof ids === 'string' && ids) {
						ids.split(',').forEach(id => { selected[id.trim()] = (names && names[id.trim()]) ? names[id.trim()] : ('Item #'+id.trim()); });
					}
					renderTags();
				};
			}

		var o100PromoWizard = {
			currentStep: 1,
			totalSteps: 5,

			open: function(id) {
				document.getElementById('o100-promo-wizard').classList.add('is-open');
				this.goToStep(1);
				if (id > 0) {
					document.getElementById('promo-wizard-title').innerText = 'Edit Promotion';
					this.loadPromo(id);
				} else {
					document.getElementById('promo-wizard-title').innerText = 'Create Promotion';
					this.resetForm();
				}
			},

			close: function() {
				document.getElementById('o100-promo-wizard').classList.remove('is-open');
			},

			goToStep: function(step) {
				// Hide all
				for (let i = 1; i <= this.totalSteps; i++) {
					document.getElementById('promo-step-content-' + i).classList.remove('is-active');
					let navItem = document.getElementById('promo-step-nav-' + i);
					navItem.classList.remove('is-active', 'is-completed', 'is-pending');
					
					if (i < step) {
						navItem.classList.add('is-completed');
					} else if (i === step) {
						navItem.classList.add('is-active');
					} else {
						navItem.classList.add('is-pending');
					}
				}

				document.getElementById('promo-step-content-' + step).classList.add('is-active');
				this.currentStep = step;

				// Buttons
				let prevBtn = document.getElementById('promo-wizard-prev');
				let nextBtn = document.getElementById('promo-wizard-next');

				if (step === 1) {
					prevBtn.classList.add('opacity-0', 'pointer-events-none');
				} else {
					prevBtn.classList.remove('opacity-0', 'pointer-events-none');
				}

				if (step === this.totalSteps) {
					nextBtn.innerText = 'Save Promotion';
					nextBtn.classList.replace('bg-indigo-600', 'bg-green-600');
					nextBtn.classList.replace('hover:bg-indigo-700', 'hover:bg-green-700');
				} else {
					nextBtn.innerText = 'Continue';
					nextBtn.classList.replace('bg-green-600', 'bg-indigo-600');
					nextBtn.classList.replace('hover:bg-green-700', 'hover:bg-indigo-700');
				}
			},

			nextStep: function() {
				if (this.currentStep < this.totalSteps) {
					this.goToStep(this.currentStep + 1);
				} else {
					this.save();
				}
			},

			initFlatpickr: function() {
				if (typeof flatpickr !== 'undefined') {
					flatpickr('#promo_start_date', { dateFormat: 'Y-m-d' });
					flatpickr('#promo_end_date', { dateFormat: 'Y-m-d' });
				}
			},

			prevStep: function() {
				if (this.currentStep > 1) {
					this.goToStep(this.currentStep - 1);
				}
			},

			resetForm: function() {
				document.getElementById('promo_id').value = 0;
				document.getElementById('promo_title').value = '';
				document.getElementById('promo_desc').value = '';
				document.getElementById('promo_priority').value = '10';
				document.getElementById('promo_is_exclusive').checked = false;
				
				document.getElementById('promo_level').value = 'cart';
				document.getElementById('promo_rule_type').value = 'simple';
				document.getElementById('promo_rule_type').dispatchEvent(new Event('change'));

				// Reset all rule specific fields
				document.getElementById('promo_simple_discount_type').value = 'percentage';
				document.getElementById('promo_simple_discount_value').value = '';
				
				document.getElementById('promo_bogo_buy_qty').value = '1';
				document.getElementById('promo_bogo_get_qty').value = '1';
				document.getElementById('promo_bogo_discount_type').value = 'free';
				document.getElementById('promo_bogo_discount_type').dispatchEvent(new Event('change'));
				document.getElementById('promo_bogo_discount_value').value = '';

				document.getElementById('promo_bxgy_buy_qty').value = '1';
				document.getElementById('promo_bxgy_get_qty').value = '1';
				document.getElementById('promo_bxgy_discount_type').value = 'free';
				document.getElementById('promo_bxgy_discount_type').dispatchEvent(new Event('change'));
				document.getElementById('promo_bxgy_discount_value').value = '';
				document.getElementById('promo_bxgy_product_y').value = '';

				document.getElementById('promo-tiers-container').innerHTML = '<div class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white text-slate-500 text-sm">No tiers added.</div>';

				document.getElementById('promo_bundle_qty').value = '2';
				document.getElementById('promo_bundle_discount_type').value = 'percentage';
				document.getElementById('promo_bundle_discount_value').value = '';
				
				document.getElementById('promo_apply_to').value = 'all_products';
				document.getElementById('promo_apply_items_products').value = '';
				document.getElementById('promo_apply_items_categories').value = '';
				document.getElementById('promo_apply_items_wrap').classList.add('hidden');
				
				// Common rules
				document.getElementById('promo_start_date').value = '';
				document.getElementById('promo_end_date').value = '';
				document.getElementById('promo_min_order').value = '';
				document.getElementById('promo_usage_limit').value = '';
				document.getElementById('promo_order_method').value = 'both';
				document.getElementById('promo_branch').value = 'all';

				document.getElementById('promo_conditions_logic').value = 'all';
				document.getElementById('promo_code').value = '';
				document.querySelector('input[name="promo_code_type"][value="auto"]').checked = true;
				document.getElementById('promo_code_input_wrap').classList.add('hidden');
				
				// Reset MCD pills (simple cleanup)
				document.querySelectorAll('.o100-mcd-header-text').forEach(el => el.innerHTML = '<span class="o100-mcd-placeholder">Selecting...</span>');

				// Reset conditions
				document.getElementById('promo-conditions-container').innerHTML = '<div class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white text-slate-500 text-sm">No conditions set. Promotion applies to all valid orders.</div>';
				
				// Reset Display
				document.getElementById('promo_desc').value = '';
				document.getElementById('promo_badge').value = '';
				document.getElementById('promo_banner').value = '';
				document.getElementById('promo_terms_conditions').value = '';
				document.getElementById('promo_popup_enabled').checked = false;
				document.getElementById('promo_popup_title').value = '';
				document.getElementById('promo_popup_text').value = '';
				document.getElementById('promo_popup_location').value = 'all';
				document.getElementById('promo_popup_duration').value = '5';
				document.getElementById('promo_popup_enabled').dispatchEvent(new Event('change'));
			},

			loadPromo: function(id) {
				let formData = new FormData();
				formData.append('action', 'o100_get_promotion');
				formData.append('nonce', o100PromoNonce);
				formData.append('id', id);

				fetch(o100PromoAjaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(res => res.json())
				.then(response => {
					if (response.success) {
						let data = response.data;
						document.getElementById('promo_id').value = data.id;
						document.getElementById('promo_title').value = data.title;
						document.getElementById('promo_desc').value = data.description;
						document.getElementById('promo_priority').value = data.priority || 10;
						document.getElementById('promo_is_exclusive').checked = (data.is_exclusive == 1);

						document.getElementById('promo_rule_type').value = data.rule_type;
						document.getElementById('promo_apply_to').value = data.apply_to;
						document.getElementById('promo_conditions_logic').value = data.conditions_logic;
						
						// Start/End/Usage Limit
						if (data.start_date) document.getElementById('promo_start_date').value = data.start_date.substring(0,10);
						if (data.end_date) document.getElementById('promo_end_date').value = data.end_date.substring(0,10);
						if (data.usage_limit) document.getElementById('promo_usage_limit').value = data.usage_limit;

						// Parse Action Config
						let config = JSON.parse(data.action_config || '{}');
						if (config.level) {
							document.getElementById('promo_level').value = config.level;
						}
						document.getElementById('promo_level').dispatchEvent(new Event('change'));
						
						// Trigger rule type change to show correct wrap
						document.getElementById('promo_rule_type').dispatchEvent(new Event('change'));

						if (data.rule_type === 'simple') {
							document.getElementById('promo_simple_discount_type').value = config.discount_type || 'percentage';
							document.getElementById('promo_simple_discount_type').dispatchEvent(new Event('change'));
							document.getElementById('promo_simple_discount_value').value = config.discount_value || '';
							
							if (config.free_item_id) {
								document.getElementById('promo_free_item_id').value = config.free_item_id;
								if (data.formatted_free_item) {
									let headerText = document.querySelector('#promo_free_item_id').closest('.o100-mcd-wrapper').querySelector('.o100-mcd-header-text');
									headerText.innerHTML = '<span class="o100-mcd-pill" data-val="' + data.formatted_free_item.id + '">' + data.formatted_free_item.text + ' <i class="dashicons dashicons-no-alt"></i></span>';
								}
							}
						} else if (data.rule_type === 'bogo') {
							document.getElementById('promo_bogo_buy_qty').value = config.buy_qty || '1';
							document.getElementById('promo_bogo_get_qty').value = config.get_qty || '1';
							document.getElementById('promo_bogo_discount_type').value = config.discount_type || 'free';
							document.getElementById('promo_bogo_discount_type').dispatchEvent(new Event('change'));
							document.getElementById('promo_bogo_discount_value').value = config.discount_value || '';
						} else if (data.rule_type === 'buy_x_get_y') {
							document.getElementById('promo_bxgy_buy_qty').value = config.buy_qty || '1';
							document.getElementById('promo_bxgy_get_qty').value = config.get_qty || '1';
							document.getElementById('promo_bxgy_discount_type').value = config.discount_type || 'free';
							document.getElementById('promo_bxgy_discount_type').dispatchEvent(new Event('change'));
							document.getElementById('promo_bxgy_discount_value').value = config.discount_value || '';
							document.getElementById('promo_bxgy_product_y').value = config.product_y || '';
							if (data.formatted_product_y) {
								let headerText = document.querySelector('#promo_bxgy_product_y').closest('.o100-mcd-wrapper').querySelector('.o100-mcd-header-text');
								headerText.innerHTML = '<span class="o100-mcd-pill" data-val="' + data.formatted_product_y.id + '">' + data.formatted_product_y.text + ' <i class="dashicons dashicons-no-alt"></i></span>';
							}
						} else if (data.rule_type === 'bulk_tiered') {
							let tiers = config.tiers || [];
							if (tiers.length > 0) {
								document.getElementById('promo-tiers-container').innerHTML = '';
								tiers.forEach(t => {
									this.addTierUI(t);
								});
							} else {
								document.getElementById('promo-tiers-container').innerHTML = '<div class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white text-slate-500 text-sm">No tiers added.</div>';
							}
						} else if (data.rule_type === 'bundle') {
							document.getElementById('promo_bundle_qty').value = config.set_qty || '2';
							document.getElementById('promo_bundle_discount_type').value = config.discount_type || 'percentage';
							document.getElementById('promo_bundle_discount_value').value = config.discount_value || '';
						}
						if (config.min_order) document.getElementById('promo_min_order').value = config.min_order;
						if (config.order_method) document.getElementById('promo_order_method').value = config.order_method;
						if (config.branch) document.getElementById('promo_branch').value = config.branch;

						if (data.promo_code && data.promo_code !== '') {
							document.querySelector('input[name="promo_code_type"][value="code"]').checked = true;
							document.getElementById('promo_code_input_wrap').classList.remove('hidden');
							document.getElementById('promo_code').value = data.promo_code;
							
							if (config.url_coupon_enabled) {
								document.getElementById('promo_url_coupon_enabled').checked = true;
								document.getElementById('promo_url_box').style.display = 'block';
								document.getElementById('promo_url_input').value = window.location.origin + '/?coupon=' + data.promo_code;
							} else {
								document.getElementById('promo_url_coupon_enabled').checked = false;
								document.getElementById('promo_url_box').style.display = 'none';
							}
						} else {
							document.querySelector('input[name="promo_code_type"][value="auto"]').checked = true;
							document.getElementById('promo_code_input_wrap').classList.add('hidden');
							document.getElementById('promo_code').value = '';
							
							if (document.getElementById('promo_url_coupon_enabled')) {
								document.getElementById('promo_url_coupon_enabled').checked = false;
								document.getElementById('promo_url_box').style.display = 'none';
							}
						}

						// Load apply_to_items
						document.getElementById('promo_apply_to').dispatchEvent(new Event('change'));
						let items = JSON.parse(data.apply_to_items || '[]');
						if (data.apply_to === 'specific_products') {
							document.getElementById('promo_apply_items_products').value = items.join(',');
							if (data.formatted_items && data.formatted_items.length > 0) {
								let wrapper = document.querySelector('#promo_apply_items_products').closest('.o100-mcd-wrapper');
								let headerText = wrapper.querySelector('.o100-mcd-header-text');
								let listContainer = wrapper.querySelector('.o100-mcd-list');
								headerText.innerHTML = '';
								
								// Remove any existing pinned container
								let existingPinned = listContainer.querySelector('.o100-mcd-pinned');
								if (existingPinned) existingPinned.remove();
								
								let pinnedHtml = '<div class="o100-mcd-pinned" style="border-bottom:1px solid #e2e8f0; margin-bottom:4px; padding-bottom:4px;">';
								
								data.formatted_items.forEach(item => {
									headerText.innerHTML += '<span class="o100-mcd-pill" data-val="' + item.id + '">' + item.text + ' <i class="dashicons dashicons-no-alt"></i></span>';
									pinnedHtml += '<label class="o100-mcd-item is-selected"><input type="checkbox" value="' + item.id + '" checked><span>' + item.text + '</span></label>';
								});
								pinnedHtml += '</div>';
								listContainer.insertAdjacentHTML('afterbegin', pinnedHtml);
							}
						}
						if (data.apply_to === 'specific_categories') {
							document.getElementById('promo_apply_items_categories').value = items.join(',');
							if (data.formatted_items && data.formatted_items.length > 0) {
								let wrapper = document.querySelector('#promo_apply_items_categories').closest('.o100-mcd-wrapper');
								let headerText = wrapper.querySelector('.o100-mcd-header-text');
								let listContainer = wrapper.querySelector('.o100-mcd-list');
								headerText.innerHTML = '';
								
								let existingPinned = listContainer.querySelector('.o100-mcd-pinned');
								if (existingPinned) existingPinned.remove();
								
								let pinnedHtml = '<div class="o100-mcd-pinned" style="border-bottom:1px solid #e2e8f0; margin-bottom:4px; padding-bottom:4px;">';
								
								data.formatted_items.forEach(item => {
									headerText.innerHTML += '<span class="o100-mcd-pill" data-val="' + item.id + '">' + item.text + ' <i class="dashicons dashicons-no-alt"></i></span>';
									pinnedHtml += '<label class="o100-mcd-item is-selected"><input type="checkbox" value="' + item.id + '" checked><span>' + item.text + '</span></label>';
								});
								pinnedHtml += '</div>';
								listContainer.insertAdjacentHTML('afterbegin', pinnedHtml);
							}
						}

						// Load conditions
						let conditions = JSON.parse(data.conditions || '[]');
						if (conditions.length > 0) {
							document.getElementById('promo-conditions-container').innerHTML = '';
							conditions.forEach(c => {
								this.addConditionUI(c);
							});
						} else {
							document.getElementById('promo-conditions-container').innerHTML = '<div class="text-center py-6 border-2 border-dashed border-slate-300 rounded-lg bg-white text-slate-500 text-sm">No conditions set. Promotion applies to all valid orders.</div>';
						}

						// Load display config
						if (config.display) {
							document.getElementById('promo_badge').value = config.display.badge_text || '';
							document.getElementById('promo_banner').value = config.display.banner_text || '';
							document.getElementById('promo_terms_conditions').value = config.display.terms_conditions || '';
							document.getElementById('promo_popup_enabled').checked = !!config.display.popup_enabled;
							document.getElementById('promo_popup_title').value = config.display.popup_title || '';
							document.getElementById('promo_popup_text').value = config.display.popup_text || '';
							if (config.display.popup_location) document.getElementById('promo_popup_location').value = config.display.popup_location;
							if (config.display.popup_duration) document.getElementById('promo_popup_duration').value = config.display.popup_duration;
							document.getElementById('promo_popup_enabled').dispatchEvent(new Event('change'));
						}
					}
				});
			},

			addConditionUI: function(existingCond = null) {
				let container = document.getElementById('promo-conditions-container');
				if (container.querySelector('.text-center')) {
					container.innerHTML = '';
				}
				
				let typeVal = existingCond ? existingCond.type : 'cart_subtotal';
				let opVal = existingCond ? existingCond.operator : '>=';
				let valVal = existingCond ? existingCond.value : '';

				let row = document.createElement('div');
				row.className = 'flex flex-wrap items-center gap-3 bg-white p-3 rounded-lg border border-slate-200 promo-condition-row';
				
				const schema = {
					'Cart': [
						{ id: 'cart_subtotal', label: 'Cart Subtotal', ops: ['>', '>=', '<', '<=', '=='], input: 'number' },
						{ id: 'cart_items_count', label: 'Cart Items Count', ops: ['>', '>=', '<', '<=', '=='], input: 'number' },
						{ id: 'cart_total_qty', label: 'Cart Total Quantity', ops: ['>', '>=', '<', '<=', '=='], input: 'number' },
						{ id: 'cart_coupon', label: 'Cart Coupon Applied', ops: ['is', 'is_not'], input: 'text', placeholder: 'Coupon code' }
					],
					'Product': [
						{ id: 'products', label: 'Products', ops: ['in', 'not_in'], input: 'search_products' },
						{ id: 'product_cat', label: 'Product Category', ops: ['in', 'not_in'], input: 'search_categories' },
						{ id: 'product_on_sale', label: 'Product On Sale', ops: ['yes', 'no'], input: 'none' }
					],
					'Customer': [
						{ id: 'user_role', label: 'User Role', ops: ['in', 'not_in'], input: 'text', placeholder: 'e.g. customer' },
						{ id: 'user_logged_in', label: 'User Logged In', ops: ['yes', 'no'], input: 'none' },
						{ id: 'first_order', label: 'First Order', ops: ['yes', 'no'], input: 'none' }
					],
					'Purchase History': [
						{ id: 'prev_orders_count', label: 'Previous Orders Count', ops: ['>', '>=', '<', '<=', '=='], input: 'number' },
						{ id: 'total_spent', label: 'Total Spent Amount', ops: ['>', '>=', '<', '<=', '=='], input: 'number' }
					],
					'Order': [
						{ id: 'order_method', label: 'Order Method', ops: ['is', 'is_not'], input: 'select_method' },
						{ id: 'payment_method', label: 'Payment Method', ops: ['in', 'not_in'], input: 'text', placeholder: 'Gateway IDs' },
						{ id: 'location_branch', label: 'Location/Branch', ops: ['in', 'not_in'], input: 'text', placeholder: 'Location IDs' }
					],
					'Schedule': [
						{ id: 'time_of_day', label: 'Time of Day', ops: ['between'], input: 'text', placeholder: 'e.g. 14:00-17:00' },
						{ id: 'day_of_week', label: 'Day of Week', ops: ['in', 'not_in'], input: 'text', placeholder: '0=Sun, 1=Mon... (comma sep)' }
					],
					'Shipping / Delivery': [
						{ id: 'delivery_distance', label: 'Delivery Distance', ops: ['>', '>=', '<', '<=', '=='], input: 'number' },
						{ id: 'shipping_zip', label: 'Shipping Zip Code', ops: ['in', 'not_in'], input: 'text', placeholder: 'Zip Codes' }
					]
				};

				let typeSelectHTML = `<select class="promo-cond-type border border-slate-300 rounded px-2 py-1 text-sm flex-1 min-w-[150px]">`;
				for (let group in schema) {
					typeSelectHTML += `<optgroup label="${group}">`;
					schema[group].forEach(c => {
						typeSelectHTML += `<option value="${c.id}" ${typeVal === c.id ? 'selected' : ''}>${c.label}</option>`;
					});
					typeSelectHTML += `</optgroup>`;
				}
				typeSelectHTML += `</select>`;

				row.innerHTML = `
					${typeSelectHTML}
					<select class="promo-cond-operator border border-slate-300 rounded px-2 py-1 text-sm flex-1 min-w-[150px]"></select>
					<div class="promo-cond-val-container flex-1 min-w-[150px]"></div>
					<button type="button" class="text-red-500 hover:text-red-700 shrink-0" onclick="this.parentElement.remove()">
						<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
					</button>
				`;

				container.appendChild(row);

				let typeSelect = row.querySelector('.promo-cond-type');
				let opSelect = row.querySelector('.promo-cond-operator');
				let valContainer = row.querySelector('.promo-cond-val-container');

				const opLabels = {
					'>': 'Greater than', '>=': 'Greater or equal', '<': 'Less than', '<=': 'Less or equal', '==': 'Is exactly',
					'is': 'Is', 'is_not': 'Is Not', 'in': 'Is in list', 'not_in': 'Not in list', 'yes': 'Yes', 'no': 'No', 'between': 'Is between'
				};

				const updateRowUI = (initOp, initVal) => {
					let currType = typeSelect.value;
					let def = null;
					for (let g in schema) {
						let found = schema[g].find(c => c.id === currType);
						if (found) { def = found; break; }
					}
					if (!def) return;

					let currentOp = initOp || def.ops[0];
					if (!def.ops.includes(currentOp)) currentOp = def.ops[0];
					opSelect.innerHTML = def.ops.map(o => `<option value="${o}" ${o === currentOp ? 'selected' : ''}>${opLabels[o]}</option>`).join('');

					if (def.input === 'none') {
						valContainer.innerHTML = `<input type="hidden" class="promo-cond-value" value="1">`;
					} else if (def.input === 'select_method') {
						valContainer.innerHTML = `<select class="promo-cond-value w-full border border-slate-300 rounded px-2 py-1 text-sm">
							<option value="delivery" ${initVal === 'delivery' ? 'selected' : ''}>Delivery</option>
							<option value="pickup" ${initVal === 'pickup' ? 'selected' : ''}>Pickup</option>
						</select>`;
					} else if (def.input === 'search_products' || def.input === 'search_categories') {
						const uid = 'pmcs_' + Date.now() + '_' + Math.random().toString(36).substr(2,4);
						const isProducts = def.input === 'search_products';
						const placeholder = isProducts ? 'Search products...' : 'Search categories...';
						valContainer.innerHTML = `<div class="o100-mcs-wrap relative" id="${uid}"><input type="hidden" class="promo-cond-value" value="${initVal || ''}"><div class="o100-mcs-tags flex flex-wrap gap-1 mb-1"></div><input type="text" class="o100-mcs-input w-full text-sm border border-slate-300 rounded px-2 py-1" placeholder="${placeholder}" autocomplete="off"><div class="o100-mcs-dd absolute left-0 right-0 top-full bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-40 overflow-y-auto hidden"></div></div>`;
						o100InitMCS(uid, isProducts ? 'products' : 'categories');
						if (initVal) { document.getElementById(uid)._mcsSetValues(initVal); }
					} else {
						valContainer.innerHTML = `<input type="${def.input === 'number' ? 'number' : 'text'}" class="promo-cond-value w-full border border-slate-300 rounded px-2 py-1 text-sm" placeholder="${def.placeholder || 'Value'}" value="${initVal || ''}">`;
					}
				};

				typeSelect.addEventListener('change', () => updateRowUI('', ''));
				updateRowUI(opVal, valVal);
			},

			addTierUI: function(existingTier = null) {
				let container = document.getElementById('promo-tiers-container');
				if (container.querySelector('.text-center')) {
					container.innerHTML = '';
				}
				let minVal = existingTier ? existingTier.min : '1';
				let maxVal = existingTier ? existingTier.max : '5';
				let typeVal = existingTier ? existingTier.discount_type : 'percentage';
				let discVal = existingTier ? existingTier.discount_value : '';

				let row = document.createElement('div');
				row.className = 'flex flex-wrap items-center gap-2 promo-tier-row bg-white p-2 rounded border border-slate-200';
				row.innerHTML = `
					<input type="number" class="promo-tier-min w-20 border border-slate-300 rounded px-2 py-1 text-sm" placeholder="Min Qty" value="${minVal}">
					<span class="text-slate-500 text-sm">to</span>
					<input type="number" class="promo-tier-max w-20 border border-slate-300 rounded px-2 py-1 text-sm" placeholder="Max Qty" value="${maxVal}">
					<span class="text-slate-500 text-sm">gets</span>
					<select class="promo-tier-type border border-slate-300 rounded px-2 py-1 text-sm">
						<option value="percentage" ${typeVal === 'percentage' ? 'selected' : ''}>% Off</option>
						<option value="fixed" ${typeVal === 'fixed' ? 'selected' : ''}>$ Off</option>
					</select>
					<input type="number" class="promo-tier-val w-24 border border-slate-300 rounded px-2 py-1 text-sm" placeholder="Value" value="${discVal}">
					<button type="button" class="text-red-500 hover:text-red-700 ml-auto" onclick="this.parentElement.remove()">
						<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
					</button>
				`;
				container.appendChild(row);
			},

			save: function() {
				let btn = document.getElementById('promo-wizard-next');
				btn.innerText = 'Saving...';
				btn.disabled = true;

				let formData = new FormData();
				formData.append('action', 'o100_save_promotion');
				formData.append('nonce', o100PromoNonce);
				
				formData.append('id', document.getElementById('promo_id').value);
				formData.append('title', document.getElementById('promo_title').value);
				formData.append('description', document.getElementById('promo_desc').value);
				formData.append('priority', document.getElementById('promo_priority').value);
				formData.append('is_exclusive', document.getElementById('promo_is_exclusive').checked ? '1' : '0');
				formData.append('rule_type', document.getElementById('promo_rule_type').value);
				formData.append('apply_to', document.getElementById('promo_apply_to').value);
				formData.append('conditions_logic', document.getElementById('promo_conditions_logic').value);
				
				// Standard fields
				formData.append('start_date', document.getElementById('promo_start_date').value);
				formData.append('end_date', document.getElementById('promo_end_date').value);
				formData.append('usage_limit', document.getElementById('promo_usage_limit').value);

				// Apply to items (parse string to array)
				let itemsStr = '';
				if (document.getElementById('promo_apply_to').value === 'specific_products') {
					itemsStr = document.getElementById('promo_apply_items_products').value;
				} else if (document.getElementById('promo_apply_to').value === 'specific_categories') {
					itemsStr = document.getElementById('promo_apply_items_categories').value;
				}
				let itemsArr = itemsStr ? itemsStr.split(',').map(s => s.trim()) : [];
				formData.append('apply_to_items', JSON.stringify(itemsArr));

				if (document.querySelector('input[name="promo_code_type"]:checked').value === 'code') {
					formData.append('promo_code', document.getElementById('promo_code').value);
				}

				// Action Config - Build dynamically based on type
				let ruleType = document.getElementById('promo_rule_type').value;
				let actionConfig = {
					level: document.getElementById('promo_level').value,
					min_order: document.getElementById('promo_min_order').value,
					order_method: document.getElementById('promo_order_method').value,
					branch: document.getElementById('promo_branch').value,
				};

				if (ruleType === 'simple') {
					actionConfig.discount_type = document.getElementById('promo_simple_discount_type').value;
					actionConfig.discount_value = document.getElementById('promo_simple_discount_value').value;
					actionConfig.free_item_id = document.getElementById('promo_free_item_id').value;
				} else if (ruleType === 'bogo') {
					actionConfig.buy_qty = document.getElementById('promo_bogo_buy_qty').value;
					actionConfig.get_qty = document.getElementById('promo_bogo_get_qty').value;
					actionConfig.discount_type = document.getElementById('promo_bogo_discount_type').value;
					actionConfig.discount_value = document.getElementById('promo_bogo_discount_value').value;
				} else if (ruleType === 'buy_x_get_y') {
					actionConfig.buy_qty = document.getElementById('promo_bxgy_buy_qty').value;
					actionConfig.get_qty = document.getElementById('promo_bxgy_get_qty').value;
					actionConfig.discount_type = document.getElementById('promo_bxgy_discount_type').value;
					actionConfig.discount_value = document.getElementById('promo_bxgy_discount_value').value;
					actionConfig.product_y = document.getElementById('promo_bxgy_product_y').value;
				} else if (ruleType === 'bulk_tiered') {
					let tiers = [];
					document.querySelectorAll('.promo-tier-row').forEach(row => {
						tiers.push({
							min: row.querySelector('.promo-tier-min').value,
							max: row.querySelector('.promo-tier-max').value,
							discount_type: row.querySelector('.promo-tier-type').value,
							discount_value: row.querySelector('.promo-tier-val').value
						});
					});
					actionConfig.tiers = tiers;
				} else if (ruleType === 'bundle') {
					actionConfig.set_qty = document.getElementById('promo_bundle_qty').value;
					actionConfig.discount_type = document.getElementById('promo_bundle_discount_type').value;
					actionConfig.discount_value = document.getElementById('promo_bundle_discount_value').value;
				}
				
				// Display settings
				actionConfig.display = {
					badge_text: document.getElementById('promo_badge').value,
					banner_text: document.getElementById('promo_banner').value,
					terms_conditions: document.getElementById('promo_terms_conditions').value,
					popup_enabled: document.getElementById('promo_popup_enabled').checked,
					popup_title: document.getElementById('promo_popup_title').value,
					popup_text: document.getElementById('promo_popup_text').value,
					popup_location: document.getElementById('promo_popup_location').value,
					popup_duration: document.getElementById('promo_popup_duration').value
				};
				
				formData.append('action_config', JSON.stringify(actionConfig));

				// Build Conditions
				let conditions = [];
				document.querySelectorAll('.promo-condition-row').forEach(row => {
					conditions.push({
						type: row.querySelector('.promo-cond-type').value,
						operator: row.querySelector('.promo-cond-operator').value,
						value: row.querySelector('.promo-cond-value').value
					});
				});
				formData.append('conditions', JSON.stringify(conditions));

				fetch(o100PromoAjaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(async res => {
					const text = await res.text();
					try {
						const data = JSON.parse(text);
						return { isJson: true, data: data, raw: text };
					} catch (e) {
						return { isJson: false, data: null, raw: text };
					}
				})
				.then(response => {
					if (response.isJson && response.data && response.data.success) {
						location.reload();
					} else {
						let errMsg = 'Unknown error';
						if (!response.isJson) {
							errMsg = 'Invalid JSON: ' + response.raw.substring(0, 100);
						} else if (response.data && response.data.data) {
							errMsg = response.data.data;
						} else {
							errMsg = 'Raw JSON: ' + JSON.stringify(response.data);
						}
						
						if (typeof window.o100ShowToast === 'function') {
							window.o100ShowToast('Error saving promotion: ' + errMsg, 'error');
						} else {
							alert('Error saving promotion: ' + errMsg);
						}
						btn.innerText = 'Save Promotion';
						btn.disabled = false;
					}
				})
				.catch(err => {
					if (typeof window.o100ShowToast === 'function') {
						window.o100ShowToast('Server fetch error: ' + err.message, 'error');
					} else {
						alert('Server fetch error: ' + err.message);
					}
					btn.innerText = 'Save Promotion';
					btn.disabled = false;
				});
			},
			
			deletePromo: function(id) {
				const doDelete = function() {
					let formData = new FormData();
					formData.append('action', 'o100_delete_promotion');
					formData.append('nonce', o100PromoNonce);
					formData.append('id', id);

					fetch(o100PromoAjaxUrl, {
						method: 'POST',
						body: formData
					})
					.then(res => res.json())
					.then(response => {
						if (response.success) {
							location.reload();
						} else {
							if (typeof window.o100ShowToast === 'function') {
								window.o100ShowToast('Error deleting promotion: ' + response.data, 'error');
							} else {
								alert('Error deleting promotion: ' + response.data);
							}
						}
					});
				};

				if (typeof window.o100Confirm === 'function') {
					window.o100Confirm('Delete Promotion', 'Are you sure you want to delete this promotion? This action cannot be undone.', function(confirmed) {
						if (confirmed) doDelete();
					});
				} else {
					if (confirm('Are you sure you want to delete this promotion? This action cannot be undone.')) {
						doDelete();
					}
				}
			},

			toggleStatus: function(id, enabled) {
				let formData = new FormData();
				formData.append('action', 'o100_toggle_promotion');
				formData.append('nonce', o100PromoNonce);
				formData.append('id', id);
				formData.append('enabled', enabled ? '1' : '0');

				fetch(o100PromoAjaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(res => res.json())
				.then(response => {
					if (response.success) {
						let row = document.getElementById('promo-row-' + id);
						if (row) {
							if (enabled) {
								row.classList.remove('opacity-50');
							} else {
								row.classList.add('opacity-50');
							}
							// Update status badge — Status is the 7th visible td (index 6)
							let cells = row.querySelectorAll('td');
							if (cells[6]) {
								if (!enabled) {
									cells[6].innerHTML = '<span class="bg-slate-100 text-slate-500 px-2 py-1 rounded-full text-xs font-bold">Disabled</span>';
								} else {
									cells[6].innerHTML = '<span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Active</span>';
								}
							}
						}
					} else {
						alert('Error toggling promotion.');
					}
				});
			},

			generateDefaultTexts: function() {
				var title = document.getElementById('promo_title').value;
				var ruleType = document.getElementById('promo_rule_type').value;
				
				var defTitle = title ? title : "Special Promotion";
				var defBadge = "PROMO";
				var defSubtitle = "Save on your order";
				var defTerms = "Offer valid for participating items while supplies last. Not valid with other offers. See store for details.";

				if (ruleType === 'bogo') {
					defBadge = "Buy 1 Get 1";
					defSubtitle = "Add eligible items to cart";
					defTerms = "Buy one eligible item at regular price, get a second identical or qualifying item free or discounted. Limit applies.";
				} else if (ruleType === 'simple') {
					var val = document.getElementById('promo_simple_discount_value').value;
					var type = document.getElementById('promo_simple_discount_type').value;
					if (val) {
						defBadge = type === 'percentage' ? val + "% OFF" : "CA$" + val + " OFF";
						defSubtitle = "Save " + defBadge + " today";
					} else {
						defBadge = "DISCOUNT";
					}
				}

				if (!document.getElementById('promo_badge').value) document.getElementById('promo_badge').value = defBadge;
				if (!document.getElementById('promo_desc').value) document.getElementById('promo_desc').value = defSubtitle;
				if (!document.getElementById('promo_banner').value) document.getElementById('promo_banner').value = defTitle;
				if (!document.getElementById('promo_popup_title').value) document.getElementById('promo_popup_title').value = defTitle;
				if (!document.getElementById('promo_popup_text').value) document.getElementById('promo_popup_text').value = "<p>" + defSubtitle + "</p>";
				if (!document.getElementById('promo_terms_conditions').value) document.getElementById('promo_terms_conditions').value = defTerms;
				
				// Make sure popup is enabled by default to encourage usage if it's a new promo
				if (document.getElementById('promo_id').value === "0" && !document.getElementById('promo_popup_enabled').checked) {
					document.getElementById('promo_popup_enabled').checked = true;
					document.getElementById('promo_popup_enabled').dispatchEvent(new Event('change'));
				}
			}
		};

		// Auto generate text when reaching step 5
		function handleStepChange(step) {
			if (step === 5) {
				o100PromoWizard.generateDefaultTexts();
			}
		}

		// Update the goToStep function to trigger handleStepChange
		var originalGoToStep = o100PromoWizard.goToStep;
		o100PromoWizard.goToStep = function(step) {
			originalGoToStep.call(o100PromoWizard, step);
			handleStepChange(step);
		};

		// Event listeners
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('input[name="promo_code_type"]').forEach(radio => {
				radio.addEventListener('change', function() {
					if (this.value === 'code') {
						document.getElementById('promo_code_input_wrap').classList.remove('hidden');
					} else {
						document.getElementById('promo_code_input_wrap').classList.add('hidden');
					}
				});
			});
		
			document.getElementById('promo_popup_enabled').addEventListener('change', function() {
				let fields = document.getElementById('promo_popup_fields');
				if (this.checked) {
					fields.classList.remove('opacity-50', 'pointer-events-none');
				} else {
					fields.classList.add('opacity-50', 'pointer-events-none');
				}
			});

			document.getElementById('promo_apply_to').addEventListener('change', function() {
				if (this.value === 'all_products') {
					document.getElementById('promo_apply_items_wrap').style.display = 'none';
				} else {
					document.getElementById('promo_apply_items_wrap').style.display = 'block';
					if (this.value === 'specific_products') {
						document.getElementById('promo_apply_products_mcd').style.display = 'block';
						document.getElementById('promo_apply_categories_mcd').style.display = 'none';
					} else {
						document.getElementById('promo_apply_products_mcd').style.display = 'none';
						document.getElementById('promo_apply_categories_mcd').style.display = 'block';
					}
				}
			});

			document.getElementById('promo_rule_type').addEventListener('change', function() {
				document.querySelectorAll('.promo-type-wrap').forEach(el => el.classList.add('hidden'));
				let wrap = document.getElementById('promo_wrap_' + this.value);
				if (wrap) wrap.classList.remove('hidden');
			});

			document.getElementById('promo_simple_discount_type').addEventListener('change', function() {
				if (this.value === 'free_item') {
					document.getElementById('promo_simple_free_item_wrap').classList.remove('hidden');
					document.getElementById('promo_simple_discount_value_wrap').classList.add('hidden');
				} else if (this.value === 'free_shipping') {
					document.getElementById('promo_simple_free_item_wrap').classList.add('hidden');
					document.getElementById('promo_simple_discount_value_wrap').classList.add('hidden');
				} else {
					document.getElementById('promo_simple_free_item_wrap').classList.add('hidden');
					document.getElementById('promo_simple_discount_value_wrap').classList.remove('hidden');
				}
			});

			document.getElementById('promo_level').addEventListener('change', function() {
				let ruleTypeSelect = document.getElementById('promo_rule_type');
				let applyToSelect = document.getElementById('promo_apply_to');
				
				if (this.value === 'cart') {
					// Lock to Simple Discount
					ruleTypeSelect.value = 'simple';
					Array.from(ruleTypeSelect.options).forEach(opt => {
						if (opt.value !== 'simple') opt.disabled = true;
					});
					ruleTypeSelect.dispatchEvent(new Event('change'));

					// Lock Apply To
					applyToSelect.value = 'all_products';
					applyToSelect.disabled = true;
					applyToSelect.dispatchEvent(new Event('change'));
				} else {
					// Enable all
					Array.from(ruleTypeSelect.options).forEach(opt => {
						opt.disabled = false;
					});
					applyToSelect.disabled = false;
				}
			});

			document.getElementById('promo_bogo_discount_type').addEventListener('change', function() {
				if (this.value === 'free') {
					document.getElementById('promo_bogo_discount_value_wrap').classList.add('hidden');
				} else {
					document.getElementById('promo_bogo_discount_value_wrap').classList.remove('hidden');
				}
			});

			document.getElementById('promo_bxgy_discount_type').addEventListener('change', function() {
				if (this.value === 'free') {
					document.getElementById('promo_bxgy_discount_value_wrap').classList.add('hidden');
				} else {
					document.getElementById('promo_bxgy_discount_value_wrap').classList.remove('hidden');
				}
			});
		});
		
		// Initialize Flatpickr when modal opens or DOM loads
		document.addEventListener('DOMContentLoaded', function() {
			o100PromoWizard.initFlatpickr();
		});
		</script>
		<?php
	}

	public static function handle_save() {
		check_ajax_referer( 'o100_promotions_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

		$data = [
			'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'      => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'priority'         => isset( $_POST['priority'] ) ? intval( $_POST['priority'] ) : 10,
			'is_exclusive'     => isset( $_POST['is_exclusive'] ) ? intval( $_POST['is_exclusive'] ) : 0,
			'rule_type'        => isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : 'simple',
			'promo_code'       => ! empty( $_POST['promo_code'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_code'] ) ) : null,
			'apply_to'         => isset( $_POST['apply_to'] ) ? sanitize_text_field( wp_unslash( $_POST['apply_to'] ) ) : 'all_products',
			'apply_to_items'   => isset( $_POST['apply_to_items'] ) ? wp_unslash( $_POST['apply_to_items'] ) : '[]', // Sent as JSON string from JS
			'conditions_logic' => isset( $_POST['conditions_logic'] ) ? sanitize_text_field( wp_unslash( $_POST['conditions_logic'] ) ) : 'all',
			'conditions'       => isset( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : '[]',
			'action_config'    => isset( $_POST['action_config'] ) ? wp_unslash( $_POST['action_config'] ) : '{}',
			'status'           => 'active',
			'start_date'       => ! empty( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) . ' 00:00:00' : null,
			'end_date'         => ! empty( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) . ' 23:59:59' : null,
			'usage_limit'      => isset( $_POST['usage_limit'] ) ? intval( $_POST['usage_limit'] ) : 0,
		];

		if ( $id > 0 ) {
			// Update existing — don't overwrite status (toggle manages it)
			unset( $data['status'] );
			O100_Promotions_DB::update( $id, $data );
			wp_send_json_success( [ 'id' => $id, 'message' => 'Promotion updated successfully.' ] );
		} else {
			// Insert new
			$data['source'] = 'manual';
			$new_id = O100_Promotions_DB::insert( $data );
			if ( $new_id ) {
				wp_send_json_success( [ 'id' => $new_id, 'message' => 'Promotion created successfully.' ] );
			} else {
				wp_send_json_error( 'Failed to create promotion.' );
			}
		}
	}

	public static function handle_delete() {
		check_ajax_referer( 'o100_promotions_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			O100_Promotions_DB::delete( $id );
			wp_send_json_success( 'Promotion deleted.' );
		} else {
			wp_send_json_error( 'Invalid ID.' );
		}
	}

	public static function handle_toggle() {
		check_ajax_referer( 'o100_promotions_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$id      = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$enabled = isset( $_POST['enabled'] ) ? intval( $_POST['enabled'] ) : 1;
		$status  = $enabled ? 'active' : 'disabled';

		if ( $id > 0 ) {
			require_once O100_PATH . 'core/promotions/class-o100-promotions-db.php';
			O100_Promotions_DB::update( $id, [ 'status' => $status ] );
			wp_send_json_success( [ 'id' => $id, 'status' => $status ] );
		} else {
			wp_send_json_error( 'Invalid ID.' );
		}
	}

	public static function handle_get() {
		check_ajax_referer( 'o100_promotions_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			$promo = O100_Promotions_DB::get( $id );
			if ( $promo ) {
				// Format items for the MCD UI
				$apply_to = $promo['apply_to'];
				$items_raw = json_decode( $promo['apply_to_items'], true );
				$formatted_items = [];
				if ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
					foreach ( $items_raw as $item_id ) {
						if ( $apply_to === 'specific_products' ) {
							$product = wc_get_product( $item_id );
							if ( $product ) {
								$formatted_items[] = [ 'id' => $item_id, 'text' => wp_strip_all_tags( $product->get_formatted_name() ) ];
							}
						} elseif ( $apply_to === 'specific_categories' ) {
							$term = get_term( $item_id, 'product_cat' );
							if ( $term && ! is_wp_error( $term ) ) {
								$formatted_items[] = [ 'id' => $item_id, 'text' => $term->name ];
							}
						}
					}
				}
				$promo['formatted_items'] = $formatted_items;

				$action_config = json_decode( $promo['action_config'], true );
				
				// Format Free Item (Legacy Support)
				$formatted_free_item = null;
				if ( ! empty( $action_config['free_item_id'] ) ) {
					$free_prod = wc_get_product( $action_config['free_item_id'] );
					if ( $free_prod ) {
						$formatted_free_item = [ 'id' => $action_config['free_item_id'], 'text' => wp_strip_all_tags( $free_prod->get_formatted_name() ) ];
					}
				}
				$promo['formatted_free_item'] = $formatted_free_item;

				// Format Product Y for Buy X Get Y
				$formatted_product_y = null;
				if ( $promo['rule_type'] === 'buy_x_get_y' && ! empty( $action_config['product_y'] ) ) {
					$prod_y = wc_get_product( $action_config['product_y'] );
					if ( $prod_y ) {
						$formatted_product_y = [ 'id' => $action_config['product_y'], 'text' => wp_strip_all_tags( $prod_y->get_formatted_name() ) ];
					}
				}
				$promo['formatted_product_y'] = $formatted_product_y;

				wp_send_json_success( $promo );
			} else {
				wp_send_json_error( 'Promotion not found.' );
			}
		} else {
			wp_send_json_error( 'Invalid ID.' );
		}
	}
}


