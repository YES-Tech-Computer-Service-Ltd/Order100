<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rules = O100_Customer_Rules_DB::get_rules();
$tags = O100_Customers_DB::get_tags();
$lists = O100_Customers_DB::get_lists();

$tag_map = [];
foreach ($tags as $t) $tag_map[$t->id] = $t->title;

$list_map = [];
foreach ($lists as $l) $list_map[$l->id] = $l->title;

$menu_categories = get_terms([
	'taxonomy' => 'product_cat',
	'hide_empty' => false,
]);

$rewards = [];
if ( post_type_exists( 'o100_loyalty_reward' ) ) {
	$rewards = get_posts([
		'post_type' => 'o100_loyalty_reward',
		'posts_per_page' => -1,
		'post_status' => 'publish'
	]);
}

$is_multi_branch = get_option('o100_locations_status') === 'on';
$branches = [];
if ( $is_multi_branch ) {
	$branches_query = get_posts([
		'post_type'      => 'o100_branch',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]);
	foreach ( $branches_query as $b ) {
		$branches[] = [
			'id'    => $b->ID,
			'title' => $b->post_title,
		];
	}
}

$rules_data_array = [];
if ( ! empty( $rules ) ) {
	foreach ( $rules as $r ) {
		$rules_data_array[] = [
			'id' => $r->id,
			'title' => $r->title,
			'status' => $r->status,
			'priority' => $r->priority,
			'target_type' => $r->target_type,
			'target_ids' => json_decode( $r->target_ids, true ) ?: [],
			'privileges' => json_decode( $r->privileges, true ) ?: [],
			'restrictions' => json_decode( $r->restrictions, true ) ?: []
		];
	}
}
?>

<style>
/* Wizard Modal Baseline (Matching Promo/Loyalty) */
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

/* Cards Style */
.o100-scenario-card { transition: all 0.2s ease; border: 2px solid transparent; }
.o100-scenario-card:hover { transform: translateY(-2px); border-color: #F59322; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); cursor: pointer; }

/* Rule Status Toggle */
.o100-rule-toggle { display:inline-flex !important; align-items:center !important; cursor:pointer !important; margin:0 !important; }
.o100-rule-toggle input[type="checkbox"] { display:none !important; }
.o100-rule-toggle .o100-rule-switch { position:relative !important; width:46px !important; height:26px !important; min-width:46px !important; background:#cbd5e1 !important; border-radius:13px !important; cursor:pointer !important; transition:background 0.2s ease, box-shadow 0.2s ease !important; display:inline-block !important; border:none !important; padding:0 !important; margin:0 !important; }
.o100-rule-toggle .o100-rule-switch::after { content:"" !important; position:absolute !important; top:3px !important; left:3px !important; width:20px !important; height:20px !important; background:#fff !important; border-radius:50% !important; box-shadow:0 1px 3px rgba(0,0,0,0.25) !important; transition:transform 0.2s ease !important; display:block !important; }
.o100-rule-toggle input[type="checkbox"]:checked + .o100-rule-switch { background:#F59322 !important; box-shadow:0 0 0 3px rgba(245,147,34,0.2) !important; }
.o100-rule-toggle input[type="checkbox"]:checked + .o100-rule-switch::after { transform:translateX(20px) !important; }
</style>

<div x-data="customersRules()">
	
	<!-- Alpine Toast -->
	<div x-show="toastShow" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2" style="position: fixed; top: 40px; right: 20px; z-index: 999999; display: none;" class="shadow-xl rounded-lg pointer-events-auto">
		<div class="rounded-lg p-4 flex items-center" :class="toastType === 'success' ? 'bg-indigo-600 text-white' : 'bg-red-500 text-white'">
			<svg x-show="toastType === 'success'" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
			</svg>
			<svg x-show="toastType === 'error'" class="h-5 w-5 mr-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
			</svg>
			<p class="text-sm font-medium" x-text="toastMsg"></p>
		</div>
	</div>

	<!-- Header Title without any top margin -->
	<div class="mb-4 flex justify-between items-center">
		<h2 class="text-xl font-bold text-slate-800">Privileges & Rules</h2>
	</div>

	<!-- Scenario Cards -->
	<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
		<div @click="openWizard(null, 'loyalty')" class="o100-scenario-card bg-white rounded-xl shadow-sm p-6 relative overflow-hidden group">
			<div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-indigo-600">
				<svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20"><path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z"></path></svg>
			</div>
			<h3 class="text-lg font-bold text-gray-900 mb-2 relative z-10">Loyalty & Rewards</h3>
			<p class="text-sm text-gray-500 relative z-10">Configure points multipliers and unlock secret rewards for VIPs.</p>
			<div class="mt-4 text-sm font-medium text-indigo-600 flex items-center relative z-10">
				Create Rule <span class="ml-1">→</span>
			</div>
		</div>

		<div @click="openWizard(null, 'menu')" class="o100-scenario-card bg-white rounded-xl shadow-sm p-6 relative overflow-hidden group">
			<div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-indigo-600">
				<svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"></path></svg>
			</div>
			<h3 class="text-lg font-bold text-gray-900 mb-2 relative z-10">Menu & Pricing</h3>
			<p class="text-sm text-gray-500 relative z-10">Enable B2B catering modes or reveal hidden menu categories.</p>
			<div class="mt-4 text-sm font-medium text-indigo-600 flex items-center relative z-10">
				Create Rule <span class="ml-1">→</span>
			</div>
		</div>

		<div @click="openWizard(null, 'checkout')" class="o100-scenario-card bg-white rounded-xl shadow-sm p-6 relative overflow-hidden group">
			<div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-green-600">
				<svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20"><path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"></path></svg>
			</div>
			<h3 class="text-lg font-bold text-gray-900 mb-2 relative z-10">Checkout & Shipping</h3>
			<p class="text-sm text-gray-500 relative z-10">Offer free shipping, lower order minimums, or restrict payment methods.</p>
			<div class="mt-4 text-sm font-medium text-green-600 flex items-center relative z-10">
				Create Rule <span class="ml-1">→</span>
			</div>
		</div>

		<div @click="openWizard(null, 'reservation')" class="o100-scenario-card bg-white rounded-xl shadow-sm p-6 relative overflow-hidden group">
			<div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-orange-600">
				<svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path></svg>
			</div>
			<h3 class="text-lg font-bold text-gray-900 mb-2 relative z-10">Reservation</h3>
			<p class="text-sm text-gray-500 relative z-10">Bypass booking constraints or enforce strict no-show prevention.</p>
			<div class="mt-4 text-sm font-medium text-orange-600 flex items-center relative z-10">
				Create Rule <span class="ml-1">→</span>
			</div>
		</div>
	</div>

	<!-- Rules List -->
	<div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
		<div class="px-6 py-5 border-b border-slate-200 bg-white">
			<h3 class="text-lg leading-6 font-medium text-slate-900">Active Rules</h3>
		</div>
		<div class="overflow-x-auto">
			<table class="min-w-full text-left border-collapse">
				<thead class="bg-slate-50 border-b border-slate-200">
					<tr>
						<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/3">Rule Details</th>
						<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden sm:table-cell">Scenario</th>
						<th scope="col" class="px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider" style="width:60px;">Status</th>
						<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden md:table-cell">Priority</th>
						<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden lg:table-cell">Target Audience</th>
						<th scope="col" class="relative px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-slate-200">
					<?php if ( empty( $rules ) ) : ?>
						<tr>
							<td colspan="6" class="px-6 py-8 whitespace-nowrap text-sm text-gray-500 text-center">
								No rules found. Click a scenario card above to create your first rule.
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rules as $rule ) : ?>
							<?php
							$target_ids = json_decode( $rule->target_ids, true );
							$target_names = [];
							if ( $rule->target_type === 'tags' && ! empty( $target_ids ) ) {
								foreach ($target_ids as $tid) {
									if (isset($tag_map[$tid])) $target_names[] = $tag_map[$tid];
								}
							} elseif ( $rule->target_type === 'lists' && ! empty( $target_ids ) ) {
								foreach ($target_ids as $lid) {
									if (isset($list_map[$lid])) $target_names[] = $list_map[$lid];
								}
							}
							
							// Determine scenario for display
							$privileges = json_decode( $rule->privileges, true ) ?: [];
							$display_scenario = 'loyalty';
							if ( !empty($privileges['menu']['b2b_pricing']) || !empty($privileges['menu']['secret_menu']) ) {
								$display_scenario = 'menu';
							} elseif ( !empty($privileges['delivery']['free_shipping']) || !empty($privileges['delivery']['lower_min_order']) || !empty($privileges['delivery']['payment_gateways']) ) {
								$display_scenario = 'checkout';
							} elseif ( !empty($privileges['reservation']['bypass_limits']) || !empty($privileges['reservation']['no_show_prevention']) ) {
								$display_scenario = 'reservation';
							}
							?>
							<tr class="hover:bg-slate-50 transition-colors duration-150 ease-in-out">
								<td class="px-6 py-4">
									<div class="text-sm font-bold text-slate-900"><?php echo esc_html( $rule->title ); ?></div>
								</td>
								<td class="px-6 py-4 hidden sm:table-cell">
									<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize bg-slate-100 text-slate-800">
										<?php echo esc_html( $display_scenario ); ?>
									</span>
								</td>
								<td class="px-6 py-4 text-center">
									<label class="o100-rule-toggle" data-rule-id="<?php echo $rule->id; ?>">
										<input type="checkbox" <?php echo $rule->status === 'active' ? 'checked' : ''; ?> @change="toggleStatus(<?php echo $rule->id; ?>, $event.target.checked ? 'active' : 'inactive', $event.target)">
										<span class="o100-rule-switch"></span>
									</label>
								</td>
								<td class="px-6 py-4 text-sm text-slate-500 font-mono hidden md:table-cell">
									<?php echo intval( $rule->priority ); ?>
								</td>
								<td class="px-6 py-4 text-sm text-slate-500 hidden lg:table-cell">
									<div class="flex flex-wrap gap-2">
										<?php if (empty($target_names)): ?>
											<span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-800">All Customers</span>
										<?php else: ?>
											<span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-indigo-100 text-indigo-800">
												<?php echo esc_html($target_names[0]); ?>
											</span>
											<?php if (count($target_names) > 1): ?>
												<span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-600">
													+<?php echo count($target_names) - 1; ?>
												</span>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</td>
								<td class="px-6 py-4 text-right whitespace-nowrap">
									<div class="flex justify-end space-x-2">
										<button type="button" @click="openWizard(<?php echo $rule->id; ?>)" class="inline-flex items-center p-1.5 border border-transparent rounded shadow-sm text-white bg-indigo-500 hover:bg-indigo-600 focus:outline-none" title="Edit">
											<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
										</button>
										<button type="button" @click="deleteRule(<?php echo $rule->id; ?>)" class="inline-flex items-center p-1.5 border border-transparent rounded shadow-sm text-white bg-red-500 hover:bg-red-600 focus:outline-none" title="Delete">
											<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
										</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- THE WIZARD MODAL -->
	<div class="o100-wizard-overlay" :class="{'is-open': isWizardOpen}">
		<div class="o100-wizard-modal relative">
			
			<!-- Left Sidebar: Stepper -->
			<div class="w-72 bg-slate-50 border-r border-slate-200 p-8 flex flex-col">
				<h2 class="text-xl font-bold text-slate-900 mb-2" x-text="rule.id === 0 ? 'Create Rule' : 'Edit Rule'"></h2>
				<p class="text-xs font-medium uppercase tracking-wide text-indigo-600 mb-8" x-text="rule.scenario + ' Scenario'"></p>
				
				<div class="flex-1">
					<div class="o100-step-item cursor-pointer" :class="getStepClass(1)" @click="activeStep = 1">
						<div class="flex items-start">
							<div class="o100-step-indicator">1</div>
							<div class="ml-4">
								<h4 class="text-sm font-bold text-slate-900">Details & Target</h4>
							</div>
						</div>
					</div>
					<div class="o100-step-item cursor-pointer" :class="getStepClass(2)" @click="activeStep = 2">
						<div class="flex items-start">
							<div class="o100-step-indicator">2</div>
							<div class="ml-4">
								<h4 class="text-sm font-bold text-slate-900">Privileges</h4>
							</div>
						</div>
					</div>
					<div class="o100-step-item cursor-pointer" :class="getStepClass(3)" @click="activeStep = 3">
						<div class="flex items-start">
							<div class="o100-step-indicator">3</div>
							<div class="ml-4">
								<h4 class="text-sm font-bold text-slate-900">Limits</h4>
							</div>
						</div>
					</div>
				</div>
				
				<div class="pt-6 border-t border-slate-200">
					<button class="text-sm text-slate-500 hover:text-slate-900 font-medium" @click="closeWizard()">Cancel & Exit</button>
				</div>
			</div>

			<!-- Right Content -->
			<div class="flex-1 bg-white p-10 flex flex-col relative overflow-y-auto">
				
				<!-- Step 1: Details & Target -->
				<div class="o100-form-step" :class="{'is-active': activeStep === 1}">
					<h3 class="text-2xl font-bold text-slate-900 mb-6">Details & Target</h3>
					<div class="space-y-6">
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-2">Rule Name <span class="text-red-500">*</span></label>
							<input type="text" x-model="rule.title" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
						</div>
						<div class="grid grid-cols-2 gap-4">
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Status</label>
								<select x-model="rule.status" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
									<option value="active">Active</option>
									<option value="inactive">Inactive</option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-bold text-slate-700 mb-2">Priority</label>
								<input type="number" x-model="rule.priority" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="0">
								<p class="text-xs text-slate-500 mt-1">Lower number = higher priority.</p>
							</div>
						</div>
						
						<div class="border-t border-slate-100 pt-6">
							<label class="block text-sm font-bold text-slate-700 mb-2">Target Audience</label>
							<p class="text-sm text-slate-500 mb-4">Who should receive these privileges? You can select customer Tags or Lists.</p>
							
							<div class="mb-4">
								<label class="block text-sm font-medium text-slate-700 mb-1">Target Type</label>
								<select x-model="rule.target_type" @change="rule.target_ids = []" class="block w-full border border-slate-300 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
									<option value="tags">Customer Tags</option>
									<option value="lists">Customer Lists</option>
								</select>
							</div>

							<!-- Fixed Multi Select UI (Integrated State) -->
							<div class="relative" @click.away="dropdownOpen = false">
								<label class="block text-sm font-medium text-slate-700 mb-1" x-text="rule.target_type === 'tags' ? 'Select Tags' : 'Select Lists'"></label>
								<div @click="dropdownOpen = !dropdownOpen" class="relative w-full bg-white border border-slate-300 rounded-lg shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
									<span class="block truncate" x-show="rule.target_ids.length === 0" x-text="'Select ' + (rule.target_type === 'tags' ? 'tags' : 'lists')"></span>
									<div class="flex flex-wrap gap-1" x-show="rule.target_ids.length > 0">
										<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
											<span x-text="rule.target_ids.length + ' selected'"></span>
										</span>
									</div>
									<span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
										<svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
									</span>
								</div>

								<!-- Dropdown Popover -->
								<div x-show="dropdownOpen" x-transition class="absolute z-20 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto sm:text-sm" style="display: none;">
									<div class="sticky top-0 z-10 bg-white px-2 py-2 border-b border-gray-200">
										<input type="text" x-model="dropdownSearch" class="w-full border border-gray-300 rounded-md py-1 px-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Search...">
									</div>
									<div class="px-3 py-2 flex justify-between items-center bg-gray-50 border-b border-gray-100">
										<span class="text-xs text-gray-500" x-text="filteredOptions.length + ' options found'"></span>
										<button type="button" @click="selectAllOptions()" class="text-xs text-indigo-600 hover:text-indigo-800" x-text="rule.target_ids.length === filteredOptions.length && filteredOptions.length > 0 ? 'Deselect all' : 'Select all'"></button>
									</div>
									<ul class="py-1">
										<li x-show="filteredOptions.length === 0" class="text-gray-500 cursor-default select-none relative py-2 pl-3 pr-9">No options found.</li>
										<template x-for="option in filteredOptions" :key="option.id">
											<li class="text-gray-900 cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-50" @click="toggleOption(option.id)">
												<div class="flex items-center">
													<input type="checkbox" :checked="rule.target_ids.includes(option.id)" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded pointer-events-none">
													<span class="ml-3 block truncate" :class="{'font-semibold': rule.target_ids.includes(option.id), 'font-normal': !rule.target_ids.includes(option.id)}" x-text="option.title"></span>
												</div>
											</li>
										</template>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Step 2: Privileges -->
				<div class="o100-form-step" :class="{'is-active': activeStep === 2}">
					<h3 class="text-2xl font-bold text-slate-900 mb-6">Privileges Config</h3>
					<div class="bg-indigo-50 p-4 rounded-lg mb-6 border border-indigo-100">
						<p class="text-sm text-indigo-800 font-medium">Currently configuring <span x-text="rule.scenario"></span> privileges.</p>
					</div>

					<!-- 1. Loyalty -->
					<div x-show="rule.scenario === 'loyalty'" class="space-y-6">
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-2">Points Multiplier</label>
							<div class="flex items-center">
								<input type="number" step="0.1" min="0" x-model="rule.privileges.loyalty.points_multiplier" class="w-32 border border-slate-300 rounded-l-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="1.0">
								<span class="inline-flex items-center px-3 rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 text-slate-500 py-2">x</span>
							</div>
							<p class="mt-1 text-xs text-slate-500">Example: 1.5 for 1.5x points on purchases.</p>
						</div>
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-2">Unlock Secret Rewards</label>
							<p class="text-xs text-slate-500 mb-3">Select rewards that are exclusively available to this group.</p>
							<div class="border border-slate-200 rounded-lg max-h-48 overflow-y-auto p-2 space-y-1 bg-slate-50">
								<?php if ( empty( $rewards ) ) : ?>
									<p class="text-xs text-slate-500 p-2">No rewards found. Please create rewards in the Loyalty module first.</p>
								<?php else : ?>
									<?php foreach ( $rewards as $reward ) : ?>
									<label class="flex items-center p-2 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-slate-200">
										<input type="checkbox" value="<?php echo $reward->ID; ?>" x-model="rule.privileges.loyalty.secret_rewards" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
										<span class="ml-3 text-sm text-slate-700 font-medium"><?php echo esc_html( $reward->post_title ); ?></span>
									</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- 2. Menu -->
					<div x-show="rule.scenario === 'menu'" class="space-y-6" style="display: none;">
						<label class="flex items-start p-4 border border-slate-200 rounded-lg bg-slate-50 cursor-pointer hover:border-indigo-300 transition-colors">
							<div class="flex items-center h-5 mt-0.5">
								<input type="checkbox" x-model="rule.privileges.menu.b2b_pricing" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
							</div>
							<div class="ml-3 text-sm">
								<span class="font-bold text-slate-700">Enable B2B/Catering Pricing Mode</span>
								<p class="text-slate-500 mt-1">Show specific corporate catering sizes and negotiated prices on the menu.</p>
							</div>
						</label>
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-2">Secret Menu Access</label>
							<p class="text-xs text-slate-500 mb-3">Select menu categories that will be exclusively visible to this group.</p>
							<div class="border border-slate-200 rounded-lg max-h-48 overflow-y-auto p-2 space-y-1 bg-slate-50">
								<?php if ( empty( $menu_categories ) || is_wp_error( $menu_categories ) ) : ?>
									<p class="text-xs text-slate-500 p-2">No menu categories found.</p>
								<?php else : ?>
									<?php foreach ( $menu_categories as $cat ) : ?>
									<label class="flex items-center p-2 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-slate-200">
										<input type="checkbox" value="<?php echo $cat->term_id; ?>" x-model="rule.privileges.menu.secret_menu" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
										<span class="ml-3 text-sm text-slate-700 font-medium"><?php echo esc_html( $cat->name ); ?></span>
									</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- 3. Checkout & Shipping -->
					<div x-show="rule.scenario === 'checkout'" class="space-y-6" style="display: none;">
						<label class="flex items-start p-4 border border-slate-200 rounded-lg bg-slate-50 cursor-pointer hover:border-indigo-300 transition-colors">
							<div class="flex items-center h-5 mt-0.5">
								<input type="checkbox" x-model="rule.privileges.delivery.free_shipping" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
							</div>
							<div class="ml-3 text-sm">
								<span class="font-bold text-slate-700">Free Delivery Fee</span>
								<p class="text-slate-500 mt-1">Waive delivery fees for this customer group.</p>
							</div>
						</label>
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-2">Lower Minimum Order Value</label>
							<div class="flex items-center w-full max-w-xs">
								<span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 text-slate-500 py-2 font-medium">$</span>
								<input type="number" step="0.01" min="0" x-model="rule.privileges.delivery.lower_min_order" class="flex-1 w-full border border-slate-300 rounded-r-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 15.00">
							</div>
							<p class="text-xs text-slate-500 mt-2">Leave empty for no change. Useful for giving VIPs a lower delivery threshold.</p>
						</div>
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-3">Payment Gateway Filtering</label>
							<div class="grid grid-cols-1 gap-3 p-4 border border-slate-200 rounded-lg bg-slate-50">
								<label class="flex items-center p-2 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-slate-200">
									<input type="checkbox" value="cod" x-model="rule.privileges.delivery.payment_gateways" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 h-4 w-4">
									<span class="ml-3 text-sm font-medium text-slate-700">Hide Cash on Delivery</span>
								</label>
								<label class="flex items-center p-2 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-slate-200">
									<input type="checkbox" value="invoice" x-model="rule.privileges.delivery.payment_gateways" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 h-4 w-4">
									<span class="ml-3 text-sm font-medium text-slate-700">Force Credit Card (Hide Offline Payments)</span>
								</label>
							</div>
						</div>
					</div>

					<!-- 4. Reservation -->
					<div x-show="rule.scenario === 'reservation'" class="space-y-6" style="display: none;">
						<label class="flex items-start p-4 border border-slate-200 rounded-lg bg-slate-50 cursor-pointer hover:border-indigo-300 transition-colors">
							<div class="flex items-center h-5 mt-0.5">
								<input type="checkbox" x-model="rule.privileges.reservation.bypass_limits" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-slate-300 rounded">
							</div>
							<div class="ml-3 text-sm">
								<span class="font-bold text-slate-700">Bypass Global Limits</span>
								<p class="text-slate-500 mt-1">Ignore "min advance notice" or "max guests" restrictions for this group.</p>
							</div>
						</label>
						<label class="flex items-start p-4 border border-red-200 rounded-lg bg-red-50 cursor-pointer hover:border-red-400 transition-colors">
							<div class="flex items-center h-5 mt-0.5">
								<input type="checkbox" x-model="rule.privileges.reservation.no_show_prevention" class="focus:ring-red-500 h-4 w-4 text-red-600 border-red-300 rounded">
							</div>
							<div class="ml-3 text-sm">
								<span class="font-bold text-red-700">No-Show Prevention / Blacklist</span>
								<p class="text-red-500 mt-1">Immediately reject online bookings from this group and ask them to call the restaurant.</p>
							</div>
						</label>
					</div>
				</div>

				<!-- Step 3: Limits & Restrictions (Dynamically Adapted per Scenario) -->
				<div class="o100-form-step" :class="{'is-active': activeStep === 3}">
					<h3 class="text-2xl font-bold text-slate-900 mb-6">Limits & Restrictions</h3>
					
					<div class="bg-amber-50 p-4 rounded-lg mb-6 border border-amber-200">
						<p class="text-sm text-amber-800">By default, privileges apply to ALL branches, order types, and times. Configure these to tightly control when this rule is active.</p>
					</div>
					
					<div class="space-y-8">
						<!-- Condition 1: Branches (Universal) -->
						<div x-show="o100IsMultiBranch">
							<label class="block text-sm font-bold text-slate-700 mb-2">Target Branches</label>
							<p class="text-xs text-slate-500 mb-3">Leave unselected to apply to all branches.</p>
							<div class="border border-slate-200 rounded-lg max-h-48 overflow-y-auto p-2 space-y-1 bg-slate-50">
								<template x-if="o100BranchesList.length === 0">
									<p class="text-xs text-slate-500 p-2">No branches found.</p>
								</template>
								<template x-for="branch in o100BranchesList" :key="branch.id">
									<label class="flex items-center p-2 hover:bg-white rounded cursor-pointer transition-colors border border-transparent hover:border-slate-200">
										<input type="checkbox" :value="branch.id" x-model="rule.restrictions.branches" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
										<span class="ml-3 text-sm text-slate-700 font-medium" x-text="branch.title"></span>
									</label>
								</template>
							</div>
						</div>

						<!-- Condition 2: Order Types (Hidden for Reservation since reservation IS the order type) -->
						<div x-show="rule.scenario !== 'reservation'">
							<label class="block text-sm font-bold text-slate-700 mb-2">Order Types</label>
							<p class="text-xs text-slate-500 mb-3">Which order channels does this apply to?</p>
							<div class="flex flex-wrap gap-4 p-4 border border-slate-200 bg-slate-50 rounded-lg">
								<label class="inline-flex items-center cursor-pointer">
									<input type="checkbox" value="delivery" x-model="rule.restrictions.order_types" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
									<span class="ml-2 text-sm font-medium text-slate-700">Delivery</span>
								</label>
								<label class="inline-flex items-center cursor-pointer">
									<input type="checkbox" value="pickup" x-model="rule.restrictions.order_types" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
									<span class="ml-2 text-sm font-medium text-slate-700">Pickup</span>
								</label>
							</div>
						</div>

						<!-- Condition 3: Schedule / Time Blocks (Universal) -->
						<div>
							<label class="block text-sm font-bold text-slate-700 mb-2">Active Schedule</label>
							<p class="text-xs text-slate-500 mb-3">Restrict this rule to specific days of the week or time blocks (e.g., Happy Hour).</p>
							
							<div class="bg-white border border-slate-200 rounded-lg shadow-sm">
								<div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-4">
									<div class="flex-1">
										<label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Days of Week</label>
										<div class="flex flex-wrap gap-3">
											<template x-for="(day, idx) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="idx">
												<label class="relative inline-flex items-center cursor-pointer bg-slate-50 hover:bg-slate-100 border border-slate-200 px-3 py-1.5 rounded-full transition-colors">
													<input type="checkbox" :value="day.toLowerCase()" x-model="rule.restrictions.days" class="sr-only peer">
													<div class="w-4 h-4 mr-2 border border-slate-300 rounded bg-white peer-checked:bg-indigo-600 peer-checked:border-indigo-600 flex items-center justify-center transition-colors">
														<svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
													</div>
													<span class="text-sm font-medium text-slate-700" x-text="day"></span>
												</label>
											</template>
										</div>
									</div>
								</div>
								
								<div class="p-4 flex flex-col sm:flex-row items-start sm:items-center gap-4 bg-slate-50 rounded-b-lg">
									<div class="flex items-center gap-2">
										<span class="text-sm font-bold text-slate-700 w-12">From</span>
										<select x-model="timeStartHr" class="border border-slate-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 w-16 text-center">
											<template x-for="h in 24">
												<option :value="(h-1).toString().padStart(2, '0')" x-text="(h-1).toString().padStart(2, '0')"></option>
											</template>
										</select>
										<span class="font-bold text-slate-400">:</span>
										<select x-model="timeStartMin" class="border border-slate-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 w-16 text-center">
											<option value="00">00</option>
											<option value="15">15</option>
											<option value="30">30</option>
											<option value="45">45</option>
										</select>
									</div>
									<div class="hidden sm:block text-slate-300 font-bold mx-2">→</div>
									<div class="flex items-center gap-2">
										<span class="text-sm font-bold text-slate-700 w-12">To</span>
										<select x-model="timeEndHr" class="border border-slate-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 w-16 text-center">
											<template x-for="h in 24">
												<option :value="(h-1).toString().padStart(2, '0')" x-text="(h-1).toString().padStart(2, '0')"></option>
											</template>
										</select>
										<span class="font-bold text-slate-400">:</span>
										<select x-model="timeEndMin" class="border border-slate-300 rounded px-2 py-1.5 text-sm bg-white focus:ring-indigo-500 focus:border-indigo-500 w-16 text-center">
											<option value="00">00</option>
											<option value="15">15</option>
											<option value="30">30</option>
											<option value="45">45</option>
										</select>
									</div>
									<button @click="timeStartHr='';timeStartMin='';timeEndHr='';timeEndMin='';" type="button" class="ml-auto text-xs font-medium text-slate-500 hover:text-slate-800 underline decoration-slate-300 underline-offset-2">Clear Time</button>
								</div>
							</div>
						</div>

						<!-- Condition 4: Minimum Order Value (Loyalty & Checkout Only) -->
						<div x-show="rule.scenario === 'loyalty' || rule.scenario === 'checkout'">
							<label class="block text-sm font-bold text-slate-700 mb-2">Minimum Order Value</label>
							<p class="text-xs text-slate-500 mb-3" x-text="rule.scenario === 'loyalty' ? 'Minimum spend required to earn points multiplier.' : 'Minimum cart subtotal required to trigger checkout privileges.'"></p>
							<div class="flex items-center w-full max-w-xs">
								<span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 text-slate-500 py-2 font-medium">$</span>
								<input type="number" step="0.01" min="0" x-model="rule.restrictions.min_spend" class="flex-1 w-full border border-slate-300 rounded-r-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 50.00">
							</div>
						</div>

						<!-- Condition 5: Party Size (Reservation Only) -->
						<div x-show="rule.scenario === 'reservation'">
							<label class="block text-sm font-bold text-slate-700 mb-2">Maximum Party Size</label>
							<p class="text-xs text-slate-500 mb-3">Bypass limits only apply if the reservation party size is under this amount.</p>
							<div class="flex items-center w-full max-w-xs">
								<input type="number" step="1" min="1" x-model="rule.restrictions.max_party" class="flex-1 w-full border border-slate-300 rounded-l-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 4">
								<span class="inline-flex items-center px-3 rounded-r-lg border border-l-0 border-slate-300 bg-slate-50 text-slate-500 py-2 font-medium">Guests</span>
							</div>
						</div>

					</div>
				</div>

				<!-- Absolute Footer with Navigation Buttons -->
				<div class="absolute bottom-0 left-0 right-0 bg-slate-50 border-t border-slate-200 p-6 flex items-center justify-between z-10" style="margin-top:auto;">
					<div>
						<button x-show="activeStep > 1" @click="activeStep--" class="text-sm font-medium text-slate-600 hover:text-slate-900 border border-slate-300 bg-white px-4 py-2 rounded-lg shadow-sm">← Previous</button>
					</div>
					<div>
						<button x-show="activeStep < 3" @click="activeStep++" class="text-sm font-medium !text-white !bg-indigo-600 hover:!bg-indigo-700 px-6 py-2 rounded-lg shadow-sm">Next →</button>
						<button x-show="activeStep === 3" @click="saveRule" :disabled="isSaving" class="text-sm font-medium !text-white !bg-indigo-600 hover:!bg-indigo-700 px-6 py-2 rounded-lg shadow-sm flex items-center">
							<span x-show="!isSaving">Save Rule</span>
							<span x-show="isSaving">Saving...</span>
						</button>
					</div>
				</div>
				<!-- padding to prevent footer overlapping content -->
				<div class="pb-24"></div>

			</div>
		</div>
	</div>

	<script>
	const o100TagsList = <?php echo json_encode( array_values( $tags ) ); ?>;
	const o100ListsList = <?php echo json_encode( array_values( $lists ) ); ?>;
	const o100RulesList = <?php echo json_encode( $rules_data_array ); ?>;
	const o100BranchesList = <?php echo json_encode( $branches ); ?>;
	const o100IsMultiBranch = <?php echo $is_multi_branch ? 'true' : 'false'; ?>;

	function customersRules() {
		return {
			toastShow: false,
			toastMsg: '',
			toastType: 'success',
			toastTimeout: null,
			isWizardOpen: false,
			activeStep: 1,
			isSaving: false,
			rule: getEmptyRule(),

			// MultiSelect integrated state
			dropdownOpen: false,
			dropdownSearch: '',
			
			// Schedule Helpers
			timeStartHr: '',
			timeStartMin: '',
			timeEndHr: '',
			timeEndMin: '',

			get options() {
				return this.rule.target_type === 'tags' ? o100TagsList : o100ListsList;
			},
			get filteredOptions() {
				if (this.dropdownSearch === '') return this.options;
				return this.options.filter(opt => opt.title.toLowerCase().includes(this.dropdownSearch.toLowerCase()));
			},
			toggleOption(id) {
				const index = this.rule.target_ids.indexOf(id);
				if (index === -1) {
					this.rule.target_ids.push(id);
				} else {
					this.rule.target_ids.splice(index, 1);
				}
			},
			selectAllOptions() {
				if (this.rule.target_ids.length === this.filteredOptions.length && this.filteredOptions.length > 0) {
					this.rule.target_ids = [];
				} else {
					this.rule.target_ids = this.filteredOptions.map(opt => opt.id);
				}
			},

			openWizard(id = null, presetScenario = 'loyalty') {
				if (id) {
					// Edit existing rule
					const existingRule = o100RulesList.find(r => r.id === id);
					if (existingRule) {
						this.rule = JSON.parse(JSON.stringify(existingRule)); // Deep copy
						// Determine scenario based on existing privileges
						let initialScenario = 'loyalty';
						if (this.rule.privileges && this.rule.privileges.menu && (this.rule.privileges.menu.b2b_pricing || (this.rule.privileges.menu.secret_menu && this.rule.privileges.menu.secret_menu.length > 0))) {
							initialScenario = 'menu';
						} else if (this.rule.privileges && this.rule.privileges.delivery && (this.rule.privileges.delivery.free_shipping || this.rule.privileges.delivery.lower_min_order !== '' || (this.rule.privileges.delivery.payment_gateways && this.rule.privileges.delivery.payment_gateways.length > 0))) {
							initialScenario = 'checkout';
						} else if (this.rule.privileges && this.rule.privileges.reservation && (this.rule.privileges.reservation.bypass_limits || this.rule.privileges.reservation.no_show_prevention)) {
							initialScenario = 'reservation';
						}
						this.rule.scenario = initialScenario;
						
						// Ensure restrictions are initialized with new fields if missing
						if (!this.rule.restrictions) this.rule.restrictions = {};
						if (!this.rule.restrictions.days) this.rule.restrictions.days = [];
						if (!this.rule.restrictions.time_start) this.rule.restrictions.time_start = '';
						if (!this.rule.restrictions.time_end) this.rule.restrictions.time_end = '';
						if (!this.rule.restrictions.min_spend) this.rule.restrictions.min_spend = '';
						if (!this.rule.restrictions.max_party) this.rule.restrictions.max_party = '';
						
						if (this.rule.restrictions.time_start) {
							const parts = this.rule.restrictions.time_start.split(':');
							if (parts.length >= 2) {
								this.timeStartHr = parts[0];
								this.timeStartMin = parts[1];
							}
						}
						if (this.rule.restrictions.time_end) {
							const parts = this.rule.restrictions.time_end.split(':');
							if (parts.length >= 2) {
								this.timeEndHr = parts[0];
								this.timeEndMin = parts[1];
							}
						}
					}
				} else {
					// Create new rule
					this.rule = getEmptyRule();
					this.rule.scenario = presetScenario;
					this.timeStartHr = '';
					this.timeStartMin = '';
					this.timeEndHr = '';
					this.timeEndMin = '';
				}
				this.dropdownSearch = '';
				this.activeStep = 1;
				this.isWizardOpen = true;
			},

			closeWizard() {
				this.isWizardOpen = false;
			},

			getStepClass(step) {
				if (this.activeStep === step) return 'is-active';
				if (this.activeStep > step) return 'is-completed';
				return 'is-pending';
			},

			saveRule() {
				if (!this.rule.title.trim()) {
					alert('Please enter a Rule Name.');
					this.activeStep = 1;
					return;
				}
				if (this.rule.target_ids.length === 0) {
					alert('Please select at least one Target Audience.');
					this.activeStep = 1;
					return;
				}

				this.isSaving = true;

				// Only save the privileges for the selected scenario
				const finalPrivileges = {
					loyalty: { points_multiplier: 1, secret_rewards: [] },
					menu: { secret_menu: [], b2b_pricing: false },
					delivery: { lower_min_order: '', free_shipping: false, payment_gateways: [] },
					reservation: { bypass_limits: false, no_show_prevention: false }
				};

				// Map the selected scenario to the final privileges
				if (this.rule.scenario === 'loyalty') finalPrivileges.loyalty = this.rule.privileges.loyalty;
				if (this.rule.scenario === 'menu') finalPrivileges.menu = this.rule.privileges.menu;
				if (this.rule.scenario === 'checkout') finalPrivileges.delivery = this.rule.privileges.delivery;
				if (this.rule.scenario === 'reservation') finalPrivileges.reservation = this.rule.privileges.reservation;

				// Combine time fields before saving
				if (this.timeStartHr && this.timeStartMin) {
					this.rule.restrictions.time_start = this.timeStartHr + ':' + this.timeStartMin;
				} else {
					this.rule.restrictions.time_start = '';
				}
				if (this.timeEndHr && this.timeEndMin) {
					this.rule.restrictions.time_end = this.timeEndHr + ':' + this.timeEndMin;
				} else {
					this.rule.restrictions.time_end = '';
				}

				jQuery.post(ajaxurl, {
					action: 'o100_crm_save_rule',
					id: this.rule.id,
					title: this.rule.title,
					status: this.rule.status,
					target_type: this.rule.target_type,
					target_ids: JSON.stringify(this.rule.target_ids.map(Number)),
					privileges: JSON.stringify(finalPrivileges),
					restrictions: JSON.stringify(this.rule.restrictions),
					priority: this.rule.priority
				}, (response) => {
					this.isSaving = false;
					if (response.success) {
						window.location.reload();
					} else {
						alert('Error saving rule: ' + response.data.message);
					}
				});
			},

			deleteRule(id) {
				if (!confirm('Are you sure you want to delete this rule? This action cannot be undone.')) {
					return;
				}
				jQuery.post(ajaxurl, {
					action: 'o100_crm_delete_rule',
					id: id
				}, function(response) {
					if (response.success) {
						window.location.reload();
					} else {
						alert('Error: ' + response.data.message);
					}
				});
			},

			toggleStatus(id, newStatus, el) {
				jQuery.post(ajaxurl, {
					action: 'o100_crm_toggle_rule_status',
					id: id,
					status: newStatus
				}, (response) => {
					if (response.success) {
						this.showToast(newStatus === 'active' ? 'Rule activated' : 'Rule deactivated', 'success');
					} else {
						// Revert toggle
						if (el) el.checked = !el.checked;
						this.showToast('Error: ' + (response.data?.message || 'Unknown error'), 'error');
					}
				}).fail(() => {
					if (el) el.checked = !el.checked;
					this.showToast('Network error, please try again', 'error');
				});
			},
			showToast(msg, type) {
				this.toastMsg = msg;
				this.toastType = type;
				this.toastShow = true;
				if (this.toastTimeout) clearTimeout(this.toastTimeout);
				this.toastTimeout = setTimeout(() => {
					this.toastShow = false;
				}, 2500);
			}
		}
	}

	function getEmptyRule() {
		return {
			id: 0,
			title: '',
			status: 'active',
			priority: 0,
			target_type: 'tags',
			target_ids: [],
			scenario: 'loyalty',
			privileges: {
				loyalty: { points_multiplier: 1, secret_rewards: [] },
				menu: { secret_menu: [], b2b_pricing: false },
				delivery: { lower_min_order: '', free_shipping: false, payment_gateways: [] },
				reservation: { bypass_limits: false, no_show_prevention: false }
			},
			restrictions: { 
				branches: [], 
				order_types: [], 
				days: [],
				time_start: '',
				time_end: '',
				min_spend: '',
				max_party: ''
			}
		};
	}
	</script>
</div>
