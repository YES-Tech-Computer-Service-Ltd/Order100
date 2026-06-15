		<script src="https://cdn.tailwindcss.com"></script>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
		<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
		<script>
			tailwind.config = {
				theme: {
					extend: {
						colors: {
							primary: '#F59322', // Blue 500
							'primary-dark': '#F59322', // Blue 600
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
			
			/* Fallback utility classes for JIT compiler misses */
			.bg-blue-500 { background-color: #F59322 !important; }
			.hover\:bg-blue-600:hover { background-color: #F59322 !important; }
			.bg-red-500 { background-color: #ef4444 !important; }
			.hover\:bg-red-600:hover { background-color: #dc2626 !important; }
			.bg-green-600 { background-color: #16a34a !important; }
			.bg-slate-200 { background-color: #e2e8f0 !important; }
			.text-white { color: #ffffff !important; }
			
			/* Custom Toggle Switch (Blue) */
			.o100-toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
			.o100-toggle input { opacity: 0; width: 0; height: 0; }
			.o100-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 20px; transition: 0.3s; }
			.o100-toggle-slider:before { content: ''; position: absolute; height: 14px; width: 14px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
			.o100-toggle input:checked + .o100-toggle-slider { background: #F59322; }
			.o100-toggle input:checked + .o100-toggle-slider:before { transform: translateX(16px); }
			
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
			.o100-step-item.is-active .o100-step-indicator { border-color: #F59322; color: #F59322; background: #EEF2FF; }
			.o100-step-item.is-completed .o100-step-indicator { background: #F59322; border-color: #F59322; color: white; }
			.o100-step-item.is-pending .o100-step-indicator { border-color: #E2E8F0; color: #94A3B8; background: white; }
			
			/* Form Steps Visibility */
			.o100-form-step { display: none; }
			.o100-form-step.is-active { display: block; animation: fadeInRight 0.3s ease; }
			
			@keyframes fadeInRight { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
		</style>

		<div class="o100-proxy-wrap">


			<!-- UNIFIED HEADER -->
			<div class="o100-promotions-page-header mb-8">
				<div class="w-full px-8">
					<div class="mb-6 pt-8 flex items-center justify-between">
						<div>
							<h1 class="text-2xl font-bold text-slate-900 m-0 pb-1" style="font-size:1.5rem !important; font-weight:700 !important; color:#0f172a !important;">
								Promotions & Marketing
								<?php echo function_exists('O100_License') ? O100_License()->get_pro_badge('Limited to 1 campaign in Free version') : ''; ?>
							</h1>
							<p class="text-sm text-slate-500 m-0 mt-1">Manage global discounts, automated coupons, and customer rewards.</p>
						</div>
						<div>
							<button onclick="o100PromoWizard.open(0)" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-medium shadow-sm hover:bg-indigo-700 transition-colors">
								+ Create Promotion
							</button>
						</div>
					</div>
				</div>
				<div class="border-b border-gray-300 px-8">
					<nav class="-mb-px flex space-x-8" aria-label="Tabs" id="promo-tabs">
						<a href="#" class="tab-link border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-target="tab-campaigns" onclick="o100PromoTabs.switchTab('tab-campaigns'); return false;">
							Campaigns
						</a>
						<a href="#" class="tab-link border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" data-target="tab-reports" onclick="o100PromoTabs.switchTab('tab-reports'); return false;">
							Coupons History
						</a>
					</nav>
				</div>
			</div>

			<div class="w-full px-8 pb-12">
			
			<div id="tab-campaigns" class="o100-tab-content" style="display: block;">
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
						<div class="o100-table-responsive-wrapper overflow-x-auto">
							<table class="min-w-full divide-y divide-slate-200 min-w-[800px]">
						<thead>
							<tr class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wider">
								<th class="py-3 px-6 o100-col-fixed-left">Name</th>
								<th class="py-3 px-6 hidden sm:table-cell">Type</th>
								<th class="py-3 px-6 hidden sm:table-cell">Discount</th>
								<th class="py-3 px-6 hidden md:table-cell">Code</th>
								<th class="py-3 px-6 hidden lg:table-cell">Target</th>
								<th class="py-3 px-6 hidden xl:table-cell">Priority</th>
								<th class="py-3 px-6 hidden xl:table-cell">Usage</th>
								<th class="py-3 px-6 text-center" style="width:60px;">Status</th>
								<th class="py-3 px-6 text-center o100-col-fixed-right">Actions</th>
							</tr>
						</thead>
						<tbody id="promo-table-body" class="bg-white divide-y divide-slate-200">
							<?php
							require_once O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
							$promotions = O100_Promotions_DB::query( [ 'parent_id' => null ] );
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

									echo '<tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors' . $row_opacity . ' o100-promo-row" id="promo-row-' . intval( $promo['id'] ) . '" data-source="' . esc_attr( $promo['source'] ) . '" data-name="' . esc_attr( strtolower( $promo['title'] ) ) . '" data-type="' . esc_attr( $type_label ) . '" data-status="' . ( $is_disabled ? 'disabled' : 'enabled' ) . '" data-priority="' . intval( $promo['priority'] ) . '" data-id="' . intval( $promo['id'] ) . '">';
									echo '<td class="px-6 py-4 o100-col-fixed-left"><div class="text-sm font-bold text-slate-900">' . esc_html( $promo['title'] ) . $locked_label . '</div></td>';
									echo '<td class="px-6 py-4 text-sm text-slate-600 hidden sm:table-cell">' . esc_html( $type_label ) . '</td>';
									echo '<td class="px-6 py-4 text-sm text-slate-600 font-semibold hidden sm:table-cell">' . $discount_summary . '</td>';
									echo '<td class="px-6 py-4 text-sm text-slate-600 font-mono hidden md:table-cell">' . $code_label . '</td>';
									echo '<td class="px-6 py-4 text-sm text-slate-600 hidden lg:table-cell">' . esc_html( $target_label ) . '</td>';
									echo '<td class="px-6 py-4 text-sm text-slate-500 hidden xl:table-cell">' . intval( $promo['priority'] ) . '</td>';
									echo '<td class="px-6 py-4 text-sm text-slate-600 hidden xl:table-cell">' . intval( $promo['usage_count'] ) . ( $promo['usage_limit'] > 0 ? '/' . intval( $promo['usage_limit'] ) : '/∞' ) . '</td>';
									echo '<td class="px-6 py-4 text-center">';
									echo '<label class="o100-toggle">';
									echo '<input type="checkbox" ' . ( ! $is_disabled ? 'checked' : '' ) . ' onchange="o100PromoWizard.toggleStatus(' . intval( $promo['id'] ) . ', this.checked)">';
									echo '<span class="o100-toggle-slider"></span>';
									echo '</label>';
									echo '</td>';
									echo '<td class="px-6 py-4 text-center whitespace-nowrap o100-col-fixed-right">';
									echo '<div class="flex items-center justify-center gap-2">';
									echo '<button type="button" class="o100-action-icon-btn edit" onclick="o100PromoWizard.open(' . intval( $promo['id'] ) . ', false)" data-tooltip="' . esc_attr__( 'Edit', 'order100' ) . '"><svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></button>';
									echo '<button type="button" class="o100-action-icon-btn duplicate" onclick="o100PromoWizard.open(' . intval( $promo['id'] ) . ', true)" data-tooltip="' . esc_attr__( 'Duplicate', 'order100' ) . '"><svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg></button>';
									echo '<button type="button" class="o100-action-icon-btn delete" onclick="o100PromoWizard.deletePromo(' . intval( $promo['id'] ) . ')" data-tooltip="' . esc_attr__( 'Delete', 'order100' ) . '"><svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>';
									echo '</div></td>';
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
			</div> <!-- End tab-campaigns -->
			
			<div id="tab-reports" class="o100-tab-content" style="display: none;">
				<!-- Reports Toolbar -->
				<div class="mb-4 flex flex-wrap items-center gap-3">
					<h2 class="text-lg font-semibold text-slate-800 flex-1">Issued Coupons & Vouchers</h2>
					<button type="button" onclick="o100PromoReports.load()" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium px-3 py-1.5 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors flex items-center gap-1">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
						Refresh
					</button>
				</div>				<!-- Reports Table -->
				<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-visible">
					<div class="p-4 border-b border-slate-200" id="promo-reports-toolbar">
						<!-- Main Bar: Search + Filter + Sort -->
						<div class="flex items-center gap-3">
							<!-- Search -->
							<div class="relative flex-grow max-w-md">
								<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
									<svg style="width: 16px; height: 16px;" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
								</div>
								<input type="search" id="promo-reports-search" placeholder="Search by name, code..." class="o100-search-unified focus:ring-indigo-500 focus:border-indigo-500 block w-full !pl-10 sm:text-sm border-slate-300 rounded-md py-2 text-slate-800" onkeyup="if(event.key === 'Enter') o100PromoReports.applyFilters()">
							</div>

							<!-- Filter Button -->
							<button type="button" onclick="document.getElementById('promo-reports-filters-row').classList.toggle('hidden'); this.classList.toggle('bg-indigo-50'); this.classList.toggle('border-indigo-300'); this.classList.toggle('text-indigo-700')" class="inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium rounded-md text-slate-700 hover:bg-slate-50 transition-colors">
								<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
								Filter
							</button>

							<!-- Sort Dropdown -->
							<div class="relative" id="promo-reports-sort-wrap">
								<button type="button" onclick="document.getElementById('promo-reports-sort-panel').classList.toggle('hidden')" class="inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium rounded-md text-slate-700 hover:bg-slate-50 transition-colors whitespace-nowrap">
									<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
									<span id="promo-reports-sort-label">Sort by Date</span>
									<svg style="width: 12px; height: 12px;" class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
								</button>
								<!-- Sort Dropdown Panel -->
								<div id="promo-reports-sort-panel" class="hidden absolute right-0 top-full mt-1 w-52 bg-white rounded-lg shadow-xl border border-slate-200 py-2 z-50">
									<div class="px-3 py-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Sort By</div>
									<button type="button" onclick="o100PromoReports.setSort('date', 'Date')" class="w-full text-left px-4 py-2 text-sm flex items-center justify-between text-indigo-700 bg-indigo-50 font-semibold" id="promo-reports-sort-date">
										<span>Issued Date</span>
									</button>
									<button type="button" onclick="o100PromoReports.setSort('expiry', 'Expiry Date')" class="w-full text-left px-4 py-2 text-sm flex items-center justify-between text-slate-700 hover:bg-slate-50" id="promo-reports-sort-expiry">
										<span>Expiry Date</span>
									</button>
									<button type="button" onclick="o100PromoReports.setSort('name', 'Name')" class="w-full text-left px-4 py-2 text-sm flex items-center justify-between text-slate-700 hover:bg-slate-50" id="promo-reports-sort-name">
										<span>Campaign Name</span>
									</button>
									<button type="button" onclick="o100PromoReports.setSort('code', 'Code')" class="w-full text-left px-4 py-2 text-sm flex items-center justify-between text-slate-700 hover:bg-slate-50" id="promo-reports-sort-code">
										<span>Coupon Code</span>
									</button>
									<div class="border-t border-slate-100 my-1.5"></div>
									<div class="flex items-center gap-1 px-3 py-1">
										<button type="button" onclick="o100PromoReports.setSortDir('asc')" id="promo-reports-sort-asc" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors bg-white border-slate-200 text-slate-500 hover:bg-slate-50">
											Asc <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
										</button>
										<button type="button" onclick="o100PromoReports.setSortDir('desc')" id="promo-reports-sort-desc" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors bg-indigo-100 border-indigo-400 text-indigo-700">
											Desc <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
										</button>
									</div>
								</div>
							</div>
						</div>
						
						<!-- Expandable Filter Row -->
						<div id="promo-reports-filters-row" class="hidden mt-3 pt-3 border-t border-slate-100">
							<div class="flex flex-wrap gap-3 items-center">
								<select id="promo-reports-status" onchange="o100PromoReports.applyFilters()" class="o100-select-unified block w-40 pl-3 pr-10 py-2 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
									<option value="all">All Status</option>
									<option value="active">Active (Valid)</option>
									<option value="inactive">Inactive</option>
									<option value="used">Used / Depleted</option>
								</select>
								
								<select id="promo-reports-source" onchange="o100PromoReports.applyFilters()" class="o100-select-unified block w-48 pl-3 pr-10 py-2 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
									<option value="all">All Sources</option>
									<option value="loyalty_auto">Loyalty Auto Rewards</option>
									<option value="issued">Manual Issued</option>
								</select>

								<select id="promo-reports-expiry" onchange="o100PromoReports.applyFilters()" class="o100-select-unified block w-48 pl-3 pr-10 py-2 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
									<option value="all">Any Expiry</option>
									<option value="has_expiry">Has Expiry Date</option>
									<option value="no_expiry">Never Expires</option>
								</select>
							</div>
						</div>
					</div>
					<div class="overflow-x-auto">
						<table class="w-full text-left border-collapse text-sm text-slate-600 min-w-[800px]">
							<thead>
								<tr class="bg-slate-50 border-b border-slate-200 text-xs font-medium text-slate-500 uppercase tracking-wider">
									<th class="py-3 px-6 o100-col-fixed-left">Coupon Code</th>
									<th class="py-3 px-6">Campaign / Source</th>
									<th class="py-3 px-6">Customer Name</th>
									<th class="py-3 px-6">Discount Content</th>
									<th class="py-3 px-6">Issued At</th>
									<th class="py-3 px-6">Expiry Date</th>
									<th class="py-3 px-6">Status</th>
									<th class="py-3 px-6 text-center o100-col-fixed-right">Actions</th>
								</tr>
							</thead>
							<tbody id="promo-reports-table-body" class="divide-y divide-slate-100">
								<!-- Dynamically populated -->
								<tr><td colspan="8" class="text-center py-8 text-slate-400">Loading reports...</td></tr>
							</tbody>
						</table>
					</div>
					<!-- Pagination Controls -->
					<div id="promo-reports-pagination" class="px-4 py-3 flex items-center justify-between border-t border-slate-200 sm:px-6 bg-white rounded-b-lg">
						<div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
							<div>
								<p class="text-sm text-slate-700">
									Showing <span class="font-medium" id="promo-reports-start">0</span> to 
									<span class="font-medium" id="promo-reports-end">0</span> of 
									<span class="font-medium" id="promo-reports-total">0</span> results
								</p>
							</div>
							<div class="flex items-center space-x-4">
								<div class="flex items-center space-x-2">
									<label for="promo-reports-per-page" class="text-sm text-slate-700">Items per page:</label>
									<select id="promo-reports-per-page" class="o100-select-unified block pl-3 pr-8 py-1 text-sm border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md" style="width: auto !important; min-width: 80px !important;" onchange="o100PromoReports.changePerPage(this.value)">
										<option value="10">10</option>
										<option value="20" selected>20</option>
										<option value="50">50</option>
										<option value="100">100</option>
									</select>
								</div>
								<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
									<button type="button" onclick="o100PromoReports.prevPage(); return false;" id="promo-reports-prev-btn" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
										<span class="sr-only">Previous</span>
										<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
									</button>
									<span class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700">
										Page <span id="promo-reports-current-page" class="mx-1">1</span> of <span id="promo-reports-total-pages" class="mx-1">1</span>
									</span>
									<button type="button" onclick="o100PromoReports.nextPage(); return false;" id="promo-reports-next-btn" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
										<span class="sr-only">Next</span>
										<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
									</button>
								</nav>
							</div>
						</div>
					</div>
				</div>
			</div> <!-- End tab-reports -->

			</div><!-- end .w-full px-8 -->
		</div><!-- end .o100-proxy-wrap -->
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
								<div id="promo_code_input_wrap" class="mt-3 hidden">
									<input type="text" id="promo_code" class="w-full border border-slate-300 rounded-lg px-3 py-2 font-mono" placeholder="e.g. SUMMER2026">
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
		var o100PromoWizard = {
			currentStep: 1,
			totalSteps: 5,

			open: function(id, isDuplicate = false) {
				document.getElementById('o100-promo-wizard').classList.add('is-open');
				this.goToStep(1);
				this.resetForm(); // Always reset form to prevent showing previous data
				if (id > 0) {
					document.getElementById('promo-wizard-title').innerText = 'Loading...';
					this.loadPromo(id, isDuplicate);
				} else {
					document.getElementById('promo-wizard-title').innerText = 'Create Promotion';
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

			loadPromo: function(id, isDuplicate = false) {
				fetch('/wp-json/o100/v1/promotions?id=' + id, {
					method: 'GET',
					headers: {
						'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
					}
				})
				.then(res => res.json())
				.then(response => {
					if (response.success) {
						let data = response.data;
												if (isDuplicate) {
							document.getElementById('promo-wizard-title').innerText = 'Create Promotion';
							document.getElementById('promo_id').value = 0;
							document.getElementById('promo_title').value = '';
						} else {
							document.getElementById('promo-wizard-title').innerText = 'Edit Promotion';
							document.getElementById('promo_id').value = data.id;
							document.getElementById('promo_title').value = data.title;
						}
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
						} else {
							document.querySelector('input[name="promo_code_type"][value="auto"]').checked = true;
							document.getElementById('promo_code_input_wrap').classList.add('hidden');
							document.getElementById('promo_code').value = '';
						}

						// Load apply_to_items
						document.getElementById('promo_apply_to').dispatchEvent(new Event('change'));
						let items = JSON.parse(data.apply_to_items || '[]');
						if (data.apply_to === 'specific_products') {
							document.getElementById('promo_apply_items_products').value = items.join(',');
							if (data.formatted_items && data.formatted_items.length > 0) {
								let headerText = document.querySelector('#promo_apply_items_products').closest('.o100-mcd-wrapper').querySelector('.o100-mcd-header-text');
								headerText.innerHTML = '';
								data.formatted_items.forEach(item => {
									headerText.innerHTML += '<span class="o100-mcd-pill" data-val="' + item.id + '">' + item.text + ' <i class="dashicons dashicons-no-alt"></i></span>';
								});
							}
						}
						if (data.apply_to === 'specific_categories') {
							document.getElementById('promo_apply_items_categories').value = items.join(',');
							if (data.formatted_items && data.formatted_items.length > 0) {
								let headerText = document.querySelector('#promo_apply_items_categories').closest('.o100-mcd-wrapper').querySelector('.o100-mcd-header-text');
								headerText.innerHTML = '';
								data.formatted_items.forEach(item => {
									headerText.innerHTML += '<span class="o100-mcd-pill" data-val="' + item.id + '">' + item.text + ' <i class="dashicons dashicons-no-alt"></i></span>';
								});
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
						{ id: 'customer_tag', label: 'Customer Tag', ops: ['in', 'not_in'], input: 'search_tags' },
						{ id: 'customer_list', label: 'Customer List', ops: ['in', 'not_in'], input: 'search_lists' },
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
					} else if (def.input === 'search_products' || def.input === 'search_categories' || def.input === 'search_tags' || def.input === 'search_lists') {
						const uid = 'mcs_' + Date.now() + '_' + Math.random().toString(36).substr(2,4);
						const isProducts = def.input === 'search_products';
						const isTags = def.input === 'search_tags';
						const isLists = def.input === 'search_lists';
						let placeholder = 'Search categories...';
						let searchType = 'categories';
						if (isProducts) { placeholder = 'Search products...'; searchType = 'products'; }
						else if (isTags) { placeholder = 'Search tags...'; searchType = 'tags'; }
						else if (isLists) { placeholder = 'Search lists...'; searchType = 'lists'; }
						
						valContainer.innerHTML = `<div class="o100-mcs-wrap relative" id="${uid}"><input type="hidden" class="promo-cond-value" value="${initVal || ''}"><div class="o100-mcs-tags flex flex-wrap gap-1 mb-1"></div><input type="text" class="o100-mcs-input w-full border border-slate-300 rounded px-2 py-1 text-sm" placeholder="${placeholder}" autocomplete="off"><div class="o100-mcs-dd absolute left-0 right-0 top-full bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-40 overflow-y-auto hidden"></div></div>`;
						if (typeof window.o100InitMCS !== 'undefined') {
							window.o100InitMCS(uid, searchType);
							const wrap = document.getElementById(uid);
							if (wrap && wrap._mcsSetValues && initVal) {
								wrap._mcsSetValues(initVal);
							}
						}
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
				let self = this;
				let promoId = document.getElementById('promo_id').value;
				if (promoId) {
					let row = document.getElementById('promo-row-' + promoId);
					if (row) {
						let source = row.getAttribute('data-source');
						if (source === 'loyalty' || source === 'automation') {
							if (typeof window.o100Confirm === 'function') {
								window.o100Confirm('Warning', 'This promotion is bound to a Loyalty / Automation campaign. Manual modifications might cause unexpected behavior. Are you sure you want to save?', function(confirmed) {
									if (confirmed) self._doSave();
								});
								return;
							} else {
								if (!confirm('WARNING: This promotion is bound to a Loyalty / Automation campaign. Manual modifications might cause unexpected behavior. Are you sure you want to save?')) {
									return;
								}
							}
						}
					}
				}
				this._doSave();
			},

			_doSave: function() {
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

				fetch('/wp-json/o100/v1/promotions', {
					method: 'POST',
					headers: {
						'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
					},
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
						if (typeof window.o100ShowToast === 'function') {
							window.o100ShowToast(response.data.data.message || 'Saved successfully.', 'success');
						}
						// Close immediately so user doesn't wait
						o100PromoWizard.close();
						
						// Dynamically update table body without full page refresh
						fetch(window.location.href)
						.then(r => r.text())
						.then(html => {
							let parser = new DOMParser();
							let doc = parser.parseFromString(html, 'text/html');
							let newTbody = doc.getElementById('promo-table-body');
							if (newTbody) {
								document.getElementById('promo-table-body').innerHTML = newTbody.innerHTML;
							}
							btn.innerText = 'Save Promotion';
							btn.disabled = false;
						});
					} else {
						let errMsg = 'Unknown error';
						if (!response.isJson) {
							errMsg = 'Invalid JSON: ' + response.raw.substring(0, 100);
						} else if (response.data && response.data.data) {
							errMsg = response.data.data;
						} else {
							errMsg = 'Raw JSON: ' + JSON.stringify(response.data);
						}
						
						let isProLimitError = (typeof errMsg === 'string' && errMsg.indexOf('upgrade to Pro') !== -1);
						
						if (isProLimitError && typeof window.o100ShowProModal === 'function') {
							o100PromoWizard.close();
							window.o100ShowProModal('Unlimited Promotions', 'The Free version is limited to 1 active promotion. Upgrade to Order100 Pro to create unlimited campaigns and scale your marketing.');
						} else {
							if (typeof window.o100ShowToast === 'function') {
								window.o100ShowToast('Error saving promotion: ' + errMsg, 'error');
							} else {
								alert('Error saving promotion: ' + errMsg);
							}
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

					fetch('/wp-json/o100/v1/promotions/' + id, {
						method: 'DELETE',
						headers: {
							'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
						}
					})
					.then(res => res.json())
					.then(response => {
						if (response.success) {
							let row = document.getElementById('promo-row-' + id);
							if (row) row.remove();
							if (typeof window.o100ShowToast === 'function') {
								window.o100ShowToast('Promotion deleted successfully.', 'success');
							}
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
				formData.append('status', enabled ? '1' : '0');

				fetch('/wp-json/o100/v1/promotions/' + id + '/status', {
					method: 'PATCH',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>'
					},
					body: JSON.stringify({ status: enabled ? '1' : '0' })
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
			
			document.addEventListener('click', function(e) {
				const sortWrap = document.getElementById('promo-reports-sort-wrap');
				if (sortWrap && !sortWrap.contains(e.target)) {
					document.getElementById('promo-reports-sort-panel').classList.add('hidden');
				}
			});
		});
		</script>

<script>
		const o100PromoTabs = {
			switchTab: function(tabId) {
				document.querySelectorAll('.o100-tab-content').forEach(el => {
					el.style.display = 'none';
				});
				
				document.querySelectorAll('#promo-tabs .tab-link').forEach(el => {
					el.classList.remove('border-indigo-500', 'text-indigo-600');
					el.classList.add('border-transparent', 'text-slate-500');
				});
				
				document.getElementById(tabId).style.display = 'block';
				
				const activeLink = document.querySelector(`#promo-tabs .tab-link[data-target="${tabId}"]`);
				if(activeLink) {
					activeLink.classList.remove('border-transparent', 'text-slate-500');
					activeLink.classList.add('border-indigo-500', 'text-indigo-600');
				}

				if(tabId === 'tab-reports') {
					o100PromoReports.load();
				}
			}
		};

		const o100PromoReports = {
			currentPage: 1,
			perPage: 20,
			total: 0,
			totalPages: 1,
			sortField: 'date',
			sortDir: 'desc',
			search: '',
			status: 'all',
			sourceFilter: 'all',
			expiryFilter: 'all',
			
			load: function() {
				const tbody = document.getElementById('promo-reports-table-body');
				// If table is empty (first load), show loading text, otherwise just dim it
				if (tbody.children.length === 0 || tbody.innerHTML.includes('No issued coupons')) {
					tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Loading reports...</td></tr>';
				} else {
					tbody.style.opacity = '0.5';
					tbody.style.pointerEvents = 'none';
				}
				
				fetch(`/wp-json/o100/v1/promotions/reports?page=${this.currentPage}&per_page=${this.perPage}&search=${encodeURIComponent(this.search)}&status=${this.status}&sourceFilter=${this.sourceFilter}&expiryFilter=${this.expiryFilter}&sortField=${this.sortField}&sortDir=${this.sortDir}`, {
					headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
				})
				.then(res => res.json())
				.then(res => {
					tbody.style.opacity = '1';
					tbody.style.pointerEvents = 'auto';
					if(!res.success) throw new Error(res.message || 'Failed to load');
					this.total = res.total || 0;
					this.totalPages = res.totalPages || 1;
					this.render(res.data);
					this.updatePagination();
				})
				.catch(err => {
					tbody.style.opacity = '1';
					tbody.style.pointerEvents = 'auto';
					tbody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-red-500">Error: ${err.message}</td></tr>`;
				});
			},
			
			render: function(data) {
				const tbody = document.getElementById('promo-reports-table-body');
				if(!data || data.length === 0) {
					tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-500">No issued coupons found.</td></tr>';
					return;
				}
				
				tbody.innerHTML = data.map(row => {
					// Parse config for display
					let configStr = row.rule_type;
					try {
						let cfg = JSON.parse(row.action_config || '{}');
						if(cfg.discount_type === 'percentage') configStr = `${cfg.discount_value}% OFF`;
						if(cfg.discount_type === 'fixed_cart') configStr = `$${cfg.discount_value} OFF`;
						if(cfg.discount_type === 'fixed_product') configStr = `$${cfg.discount_value} OFF Item`;
						if(cfg.discount_type === 'free_item') configStr = `Free Item`;
					} catch(e) {}

					// Status logic
					let statusBadge = '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Active</span>';
					if (row.usage_limit > 0 && row.usage_count >= row.usage_limit) {
						statusBadge = '<span class="px-2 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-full">Used</span>';
					} else if (row.status === 'inactive') {
						statusBadge = '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full">Inactive</span>';
					}

					let customerName = row.customer_name || 'General / Anonymous';
					let codeDisplay = row.promo_code ? `<span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 select-all">${row.promo_code}</span>` : '<span class="text-slate-400">-</span>';
					let expiryDisplay = row.end_date ? new Date(row.end_date).toLocaleDateString() : '<span class="text-slate-400">Never</span>';
					let issuedDisplay = row.created_at ? new Date(row.created_at).toLocaleString() : '-';

					return `
						<tr class="hover:bg-slate-50 transition-colors">
							<td class="px-6 py-4">${codeDisplay}</td>
							<td class="px-6 py-4">
								<div class="font-medium text-slate-800">${row.campaign_name || 'Manual Generation'}</div>
								<div class="text-xs text-slate-400">Src: ${row.source}</div>
							</td>
							<td class="px-6 py-4 font-medium">${customerName}</td>
							<td class="px-6 py-4"><span class="inline-block bg-indigo-50 text-indigo-700 px-2 py-1 rounded text-xs font-medium">${configStr}</span></td>
							<td class="px-6 py-4 text-xs">${issuedDisplay}</td>
							<td class="px-6 py-4">${expiryDisplay}</td>
							<td class="px-6 py-4">${statusBadge}</td>
							<td class="px-6 py-4 text-center">
								<div class="flex items-center justify-center gap-1">
									<button type="button" onclick="o100PromoReports.delete(${row.id})" class="o100-action-icon-btn group relative p-1.5 rounded-md hover:bg-red-50 text-slate-400 hover:text-red-500 transition-colors" title="Delete">
										<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
									</button>
								</div>
							</td>
						</tr>
					`;
				}).join('');
			},
			
			delete: function(id) {
				if(!confirm('Are you sure you want to delete this issued coupon?')) return;
				
				fetch(`/wp-json/o100/v1/promotions/${id}`, {
					method: 'DELETE',
					headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>' }
				})
				.then(res => res.json())
				.then(res => {
					if(!res.success) throw new Error(res.message);
					this.load();
				})
				.catch(err => alert(err.message));
			},

			nextPage: function() {
				if(this.hasMore) {
					this.currentPage++;
					this.load();
				}
			},

			prevPage: function() {
				if(this.currentPage > 1) {
					this.currentPage--;
					this.load();
				}
			},

			applyFilters: function() {
				this.search = document.getElementById('promo-reports-search').value;
				this.status = document.getElementById('promo-reports-status').value;
				this.sourceFilter = document.getElementById('promo-reports-source').value;
				this.expiryFilter = document.getElementById('promo-reports-expiry').value;
				this.currentPage = 1;
				this.load();
			},
			
			setSort: function(field, label) {
				this.sortField = field;
				document.getElementById('promo-reports-sort-label').textContent = 'Sort by ' + label;
				
				// Update active styles
				['date', 'expiry', 'name', 'code'].forEach(f => {
					const el = document.getElementById('promo-reports-sort-' + f);
					if (f === field) {
						el.className = 'w-full text-left px-4 py-2 text-sm flex items-center justify-between text-blue-700 bg-blue-50 font-semibold';
					} else {
						el.className = 'w-full text-left px-4 py-2 text-sm flex items-center justify-between text-slate-700 hover:bg-slate-50';
					}
				});
				
				document.getElementById('promo-reports-sort-panel').classList.add('hidden');
				this.currentPage = 1;
				this.load();
			},
			
			setSortDir: function(dir) {
				this.sortDir = dir;
				
				const ascEl = document.getElementById('promo-reports-sort-asc');
				const descEl = document.getElementById('promo-reports-sort-desc');
				
				if (dir === 'asc') {
					ascEl.className = 'flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors bg-blue-100 border-blue-400 text-blue-700';
					descEl.className = 'flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors bg-white border-slate-200 text-slate-500 hover:bg-slate-50';
				} else {
					descEl.className = 'flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors bg-blue-100 border-blue-400 text-blue-700';
					ascEl.className = 'flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors bg-white border-slate-200 text-slate-500 hover:bg-slate-50';
				}
				
				document.getElementById('promo-reports-sort-panel').classList.add('hidden');
				this.currentPage = 1;
				this.load();
			},
			
			updatePagination: function() {
				const start = (this.currentPage - 1) * this.perPage + 1;
				const end = Math.min(this.currentPage * this.perPage, this.total);
				
				document.getElementById('promo-reports-start').textContent = this.total === 0 ? 0 : start;
				document.getElementById('promo-reports-end').textContent = end;
				document.getElementById('promo-reports-total').textContent = this.total;
				
				document.getElementById('promo-reports-current-page').textContent = this.currentPage;
				document.getElementById('promo-reports-total-pages').textContent = this.totalPages;
				
				document.getElementById('promo-reports-prev-btn').disabled = this.currentPage <= 1;
				document.getElementById('promo-reports-next-btn').disabled = this.currentPage >= this.totalPages;
			},
			
			changePerPage: function(val) {
				this.perPage = parseInt(val, 10);
				this.currentPage = 1;
				this.load();
			},
			
			prevPage: function() {
				if(this.currentPage > 1) {
					this.currentPage--;
					this.load();
				}
			},
			
			nextPage: function() {
				if(this.currentPage < this.totalPages) {
					this.currentPage++;
					this.load();
				}
			}
		};
</script>
