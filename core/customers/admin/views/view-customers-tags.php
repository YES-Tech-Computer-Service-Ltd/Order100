<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$tags = O100_Customers_DB::get_tags();
$system_tags = array_filter( $tags, function( $t ) { return $t->is_system == 1; } );
$manual_tags = array_filter( $tags, function( $t ) { return $t->is_system == 0; } );
$customers_url = admin_url('admin.php?page=o100-customers');

$smart_rules = O100_Customers_Sync::get_smart_tag_rules();
global $wpdb;
$tbl_rel = O100_Customers_DB::get_table_relationships();
$tag_counts = [];

// Determine valid tags to show in the tag cloud
$valid_rule_titles = array_column( $smart_rules, 'title' );
$dietary_tags_list = ['Vegan', 'Gluten-Free', 'Non-Spicy', 'No Cilantro'];

foreach ($system_tags as $t) {
	// Filter out old legacy tags (One-time Buyer, Delivery Only, etc)
	$is_valid = false;
	if ( in_array( $t->title, $valid_rule_titles ) ) $is_valid = true;
	if ( in_array( $t->title, $dietary_tags_list ) ) $is_valid = true;
	if ( strpos( $t->title, ' Lover' ) !== false ) $is_valid = true; // Category Affinity
	
	if ( $is_valid ) {
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tbl_rel} WHERE object_type = 'tag' AND object_id = %d", $t->id));
		$tag_counts[$t->title] = [ 'id' => $t->id, 'count' => intval($count) ];
	}
}
?>
<style>
	#wpfooter { display: none !important; }
</style>
<div x-data="customersTags()" class="space-y-4">
	<!-- Top Header: Title & Actions -->
	<div class="flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-slate-200 relative">
		
		<!-- Toast Notification -->
		<div x-show="toast.show" 
			 x-transition:enter="transition ease-out duration-300 transform"
			 x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:translate-x-2"
			 x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
			 x-transition:leave="transition ease-in duration-100"
			 x-transition:leave-start="opacity-100"
			 x-transition:leave-end="opacity-0"
			 style="display: none;"
			 class="fixed top-24 right-6 z-[9999] pointer-events-none w-full max-w-sm overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
			<div class="p-4">
				<div class="flex items-start">
					<div class="flex-shrink-0">
						<svg x-show="toast.type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
						<svg x-show="toast.type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
					</div>
					<div class="ml-3 w-0 flex-1 pt-0.5">
						<p class="text-sm font-medium text-gray-900" x-text="toast.message"></p>
					</div>
				</div>
			</div>
		</div>

		<div class="flex items-center space-x-2">
			<h2 class="text-xl font-semibold text-slate-800">Contact Tags</h2>
		</div>
		<div class="flex space-x-2 mt-4 sm:mt-0">
			<div class="relative">
				<button @click="showCols = !showCols" @click.away="showCols = false" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded text-slate-700 bg-white hover:bg-slate-50 focus:outline-none">
					<svg class="mr-2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path></svg>
					Columns
				</button>
				<div x-show="showCols" style="display: none;" class="origin-top-right absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50">
					<div class="py-1">
						<label class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">
							<input type="checkbox" x-model="cols.title" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 mr-3"> Title
						</label>
						<label class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">
							<input type="checkbox" x-model="cols.slug" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 mr-3"> Slug
						</label>
						<label class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">
							<input type="checkbox" x-model="cols.desc" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 mr-3"> Description
						</label>
					</div>
				</div>
			</div>
			<button @click="showModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
				<svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
					<path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
				</svg>
				Create Tag
			</button>
		</div>
	</div>

	<!-- Main Container -->
	<div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">

	<!-- Tabs Navigation -->
	<div class="border-b border-slate-200 px-6 pt-4 bg-slate-50">
		<nav class="-mb-px flex space-x-6">
			<button @click="activeTab = 'manual'" :class="activeTab === 'manual' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm focus:outline-none transition-colors">
				Custom Tags
			</button>
			<button @click="activeTab = 'system'" :class="activeTab === 'system' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm focus:outline-none transition-colors">
				Smart Tags
			</button>
		</nav>
	</div>

	<!-- System Tags Tab -->
	<div x-show="activeTab === 'system'" style="display: none;" x-cloak>
		<div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
			<div class="text-xs text-slate-500">
				Smart profiling models automatically tag customers based on historical behaviors.
			</div>
			<button @click="saveSmartRules" :disabled="isSavingRules" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none transition-colors">
				<span x-show="!isSavingRules">Save Configuration</span>
				<span x-show="isSavingRules">Saving...</span>
			</button>
		</div>
		<div class="flex flex-col-reverse xl:flex-row gap-6 p-6 bg-slate-50/50">
			<!-- Left side: Cards -->
			<div class="xl:w-3/4">
				<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
					<template x-for="(rule, key) in smartRules" :key="key">
						<div x-data="{ showSettings: false }" class="bg-white border-l-4 border-y border-r border-slate-200 rounded-xl shadow-sm p-4 relative hover:shadow-md transition-shadow flex flex-col h-full" :class="rule.enabled ? 'border-l-blue-500' : 'border-l-slate-300'">
							<!-- Header -->
							<div class="flex justify-between items-start mb-3">
								<div class="flex items-center gap-2 group">
									<h4 class="text-sm font-bold text-slate-900 m-0 flex items-center gap-1">
										<span x-text="rule.title"></span>
										<!-- Tooltip Icon -->
										<div class="relative cursor-help text-slate-400 hover:text-indigo-500">
											<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
											<div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-56 p-2 bg-slate-800 text-white text-xs rounded shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-10 pointer-events-none" x-text="rule.desc"></div>
										</div>
									</h4>
								</div>
								<!-- Toggle Switch (using direct style to avoid JIT issues) -->
								<button @click="rule.enabled = !rule.enabled" type="button" :style="rule.enabled ? 'background-color: #22c55e;' : 'background-color: #e2e8f0;'" class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
									<span :class="rule.enabled ? 'translate-x-3' : 'translate-x-0'" class="pointer-events-none inline-block h-3 w-3 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
								</button>
							</div>

							<!-- Config Area -->
							<div class="mb-3 flex-1">
								<div x-show="rule.enabled">
									<button @click="showSettings = !showSettings" type="button" class="text-xs text-indigo-500 hover:text-indigo-700 flex items-center focus:outline-none mb-2">
										<svg class="w-3 h-3 mr-1 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{'rotate-180': showSettings}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
										Configure Threshold
									</button>
									<div x-show="showSettings" style="display: none;">
										<template x-if="key === 'time_night' || key === 'time_morning'">
											<div class="flex flex-col gap-2 p-3 bg-slate-50 rounded border border-slate-100 mb-2">
												<div class="flex items-center justify-between text-xs text-slate-600">
													<span>Start Time:</span>
													<input type="time" x-model="rule.start" class="w-20 text-xs border-slate-300 rounded p-1">
												</div>
												<div class="flex items-center justify-between text-xs text-slate-600">
													<span>End Time:</span>
													<input type="time" x-model="rule.end" class="w-20 text-xs border-slate-300 rounded p-1">
												</div>
												<div class="flex items-center justify-between text-xs text-slate-600 mt-1">
													<span>Threshold:</span>
													<div class="relative rounded-md shadow-sm w-20">
														<input type="number" x-model="rule.threshold" class="w-full text-xs border-slate-300 rounded p-1 pr-6 focus:ring-indigo-500 focus:border-indigo-500">
														<div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
															<span class="text-slate-400 text-xs">%</span>
														</div>
													</div>
												</div>
											</div>
										</template>
										<template x-if="key !== 'time_night' && key !== 'time_morning' && key !== 'dietary'">
											<div class="flex items-center justify-between text-xs text-slate-600 p-3 bg-slate-50 rounded border border-slate-100 mb-2">
												<span>Threshold Value:</span>
												<div class="relative rounded-md shadow-sm w-24">
													<input type="number" x-model="rule.threshold" class="w-full text-xs border-slate-300 rounded p-1 pr-10 focus:ring-indigo-500 focus:border-indigo-500">
													<div class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none">
														<span class="text-slate-400 text-xs" x-text="key === 'high_roller' || key === 'budget' ? '$' : (key === 'promo' ? '%' : 'Orders')"></span>
													</div>
												</div>
											</div>
										</template>
										<template x-if="key === 'dietary'">
											<div class="text-xs text-slate-500 italic p-3 bg-slate-50 rounded border border-slate-100 mb-2">
												Automatic NLP processing active.
											</div>
										</template>
									</div>
								</div>
								<div x-show="!rule.enabled" class="flex items-center">
									<span class="text-xs text-slate-400 italic">Model Disabled</span>
								</div>
							</div>

							<!-- Footer Stats -->
							<div class="pt-3 border-t border-slate-100 flex items-center justify-between mt-auto">
								<span class="text-[11px] text-slate-500 font-medium uppercase tracking-wide">Customers</span>
								<template x-if="key !== 'category' && key !== 'dietary' && tagCounts[rule.title]">
									<a :href="'<?php echo esc_url( $customers_url ); ?>&filter_tag=' + tagCounts[rule.title].id" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-indigo-100 text-indigo-800 hover:bg-indigo-200 transition-colors">
										<span x-text="tagCounts[rule.title].count"></span>
									</a>
								</template>
								<template x-if="key !== 'category' && key !== 'dietary' && !tagCounts[rule.title]">
									<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-600">
										0
									</span>
								</template>
								<template x-if="key === 'category' || key === 'dietary'">
									<span class="text-xs text-slate-400 italic">See Tag Cloud ➔</span>
								</template>
							</div>
						</div>
					</template>
				</div>
			</div>
			
			<!-- Right side: Tag Cloud -->
			<div class="xl:w-1/4">
				<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 sticky top-6">
					<h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center uppercase tracking-wide">
						<svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
						Tag Cloud
					</h3>
					<div class="flex flex-wrap gap-2 items-center">
						<template x-for="[tagTitle, tagData] in Object.entries(tagCounts)" :key="tagTitle">
							<a :href="'<?php echo esc_url($customers_url); ?>&filter_tag=' + tagData.id"
								class="inline-block rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 hover:bg-indigo-100 hover:border-indigo-200 transition-colors whitespace-nowrap"
								:style="`font-size: ${ 0.7 + (tagData.count / maxTagCount) * 0.6 }rem; padding: ${0.15 + (tagData.count / maxTagCount) * 0.1}rem ${0.4 + (tagData.count / maxTagCount) * 0.4}rem; opacity: ${tagData.count === 0 ? 0.5 : 1};`"
							>
								<span x-text="tagTitle" class="font-medium"></span> <span class="opacity-60 ml-0.5">(<span x-text="tagData.count"></span>)</span>
							</a>
						</template>
						<div x-show="Object.keys(tagCounts).length === 0" class="text-sm text-slate-500 italic">No system tags generated yet.</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Manual Tags Section -->
	<div x-show="activeTab === 'manual'">
		<div class="px-6 py-3 bg-slate-50 border-b border-slate-200 text-xs text-slate-500">
			Created and managed by merchants for custom segmentation.
		</div>
		<div class="overflow-x-auto">
			<table class="table-resizable min-w-full divide-y divide-x divide-slate-200">
				<thead class="bg-slate-50">
					<tr>
						<th x-show="cols.title" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/3">Title</th>
						<th x-show="cols.slug" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/4 hidden sm:table-cell">Slug</th>
						<th x-show="cols.desc" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden md:table-cell">Description</th>
						<th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-slate-200">
					<?php if ( empty( $manual_tags ) ) : ?>
						<tr>
							<td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center">No manual tags found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $manual_tags as $tag ) : ?>
							<tr class="hover:bg-slate-50">
								<td x-show="cols.title" class="px-6 py-4 whitespace-nowrap text-sm font-medium">
									<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'customers', 'filter_tag' => $tag->id ], $customers_url ) ); ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">
										<?php echo esc_html( $tag->title ); ?>
									</a>
								</td>
								<td x-show="cols.slug" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 hidden sm:table-cell"><?php echo esc_html( $tag->slug ); ?></td>
								<td x-show="cols.desc" class="px-6 py-4 text-sm text-slate-500 hidden md:table-cell"><?php echo esc_html( $tag->description ); ?></td>
								<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
									<div class="flex justify-end space-x-2">
										<button @click="alert('Edit functionality coming soon.')" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-sm font-medium rounded text-indigo-700 bg-white hover:bg-slate-50 focus:outline-none" title="Edit">
											Edit
										</button>
										<button @click="deleteTag(<?php echo intval( $tag->id ); ?>)" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-sm font-medium rounded text-red-700 bg-white hover:bg-slate-50 focus:outline-none" title="Delete">
											Delete
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

		<!-- Create Tag Modal (o100-modal style) -->
		<div x-show="showModal" style="display: none;" class="fixed inset-0 z-[99999] bg-black bg-opacity-60 flex items-center justify-center" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="showModal" @click.away="showModal = false" x-transition class="bg-white w-[600px] max-w-full rounded-xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
				<!-- Header -->
				<div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0">
					<h3 class="m-0 text-base font-bold text-gray-900 uppercase tracking-wide" id="modal-title">CREATE NEW TAG</h3>
					<span @click="showModal = false" class="dashicons dashicons-no-alt cursor-pointer text-gray-400 hover:text-gray-600"></span>
				</div>
				<!-- Body -->
				<div class="p-5 overflow-y-auto">
							<div class="mt-4">
								<div class="mb-4">
									<label class="block text-sm font-medium text-gray-700">Tag Title <span class="text-red-500">*</span></label>
									<input type="text" x-model="newTagTitle" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
								</div>
								<div class="mb-4">
									<label class="block text-sm font-medium text-gray-700">Description</label>
									<textarea x-model="newTagDesc" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500"></textarea>
								</div>
								
								<div class="mt-6 border-t pt-4">
									<div class="flex items-center justify-between mb-4">
										<div>
											<h4 class="text-sm font-medium text-gray-900">Automate Assignment</h4>
											<p class="text-xs text-gray-500">Automatically apply this tag based on rules.</p>
										</div>
										<button type="button" @click="isAuto = !isAuto" :style="isAuto ? 'background-color: #F59322 !important;' : 'background-color: #e5e7eb !important;'" class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
											<span aria-hidden="true" :class="isAuto ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200"></span>
										</button>
									</div>

									<div x-show="isAuto" class="bg-slate-50 p-4 rounded-md border border-slate-200">
										<div class="flex items-center mb-4">
											<span class="text-sm text-gray-700 mr-2">Match</span>
											<select x-model="autoLogic" class="block w-24 shadow-sm sm:text-sm border-gray-300 rounded-md p-1 border focus:ring-indigo-500 focus:border-indigo-500">
												<option value="all">ALL</option>
												<option value="any">ANY</option>
											</select>
											<span class="text-sm text-gray-700 ml-2">of the following conditions:</span>
										</div>

										<template x-for="(cond, index) in autoConditions" :key="index">
											<div class="flex items-start gap-2 mb-3">
												<div class="w-1/3">
													<select x-model="cond.field" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
														<option value="total_spent">Total Spent ($)</option>
														<option value="total_orders">Total Orders</option>
														<option value="aov">Average Order Value ($)</option>
														<option value="account_age">Account Age (Days)</option>
														<option value="recent_orders">Orders in Timeframe</option>
														<option value="purchased_product">Purchased Specific Product</option>
													</select>
												</div>
												
												<div class="w-1/4">
													<select x-show="cond.field !== 'purchased_product'" x-model="cond.operator" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
														<option value=">">Greater than</option>
														<option value=">=">Greater or equal</option>
														<option value="<">Less than</option>
														<option value="<=">Less or equal</option>
														<option value="==">Equal</option>
													</select>
													<select x-show="cond.field === 'purchased_product'" x-model="cond.operator" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500" style="display: none;">
														<option value="includes">Includes</option>
														<option value="excludes">Excludes</option>
													</select>
												</div>
												
												<div class="flex-1">
													<input type="number" x-show="cond.field !== 'recent_orders' && cond.field !== 'purchased_product'" x-model="cond.value" placeholder="Value" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
													
													<div x-show="cond.field === 'recent_orders'" class="flex gap-2" style="display: none;">
														<input type="number" x-model="cond.timeframe_days" placeholder="Days (e.g. 30)" class="block w-1/2 shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
														<input type="number" x-model="cond.value" placeholder="Order Count" class="block w-1/2 shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
													</div>
													
													<input type="text" x-show="cond.field === 'purchased_product'" x-model="cond.value" placeholder="Product ID" class="block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500" style="display: none;">
												</div>
												
												<button @click="removeCondition(index)" type="button" class="mt-1 text-red-500 hover:text-red-700 focus:outline-none">
													<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
												</button>
											</div>
										</template>
										
										<button @click="addCondition" type="button" class="mt-2 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none">
											+ Add Condition
										</button>
									</div>
								</div>
							</div>
						</div>
				<!-- Footer -->
				<div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50 shrink-0">
					<button @click="showModal = false" type="button" class="button !rounded-md px-4 py-2">
						Cancel
					</button>
					<button @click="saveTag" :disabled="isSaving" type="button" class="button button-primary !rounded-md px-4 py-2 !bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700">
						<span x-show="!isSaving">Save Tag</span>
						<span x-show="isSaving">Saving...</span>
					</button>
				</div>
		</div>
	</div>
	</div><!-- /.bg-white MAIN CONTAINER -->
</div><!-- /x-data customersTags ROOT -->

<script>
function customersTags() {
	return {
		smartRules: <?php echo json_encode( $smart_rules ); ?>,
		tagCounts: <?php echo json_encode( $tag_counts ); ?>,
		isSavingRules: false,

		get maxTagCount() {
			const counts = Object.values(this.tagCounts).map(t => t.count);
			return counts.length ? Math.max(1, ...counts) : 1;
		},

		activeTab: 'manual',
		showCols: false,
		cols: {
			title: true,
			slug: true,
			desc: true
		},
		showModal: false,
		newTagTitle: '',
		newTagDesc: '',
		isSaving: false,
		
		toast: {
			show: false,
			message: '',
			type: 'success'
		},

		showToast(message, type = 'success') {
			this.toast.message = message;
			this.toast.type = type;
			this.toast.show = true;
			setTimeout(() => {
				this.toast.show = false;
			}, 3000);
		},
		
		isAuto: false,
		autoLogic: 'all',
		autoConditions: [
			{ field: 'total_spent', operator: '>', value: '' }
		],
		
		addCondition() {
			this.autoConditions.push({ field: 'total_spent', operator: '>', value: '' });
		},
		removeCondition(index) {
			this.autoConditions.splice(index, 1);
		},
		
		saveTag() {
			if (!this.newTagTitle) {
				this.showToast('Please enter a tag title.', 'error');
				return;
			}
			
			this.isSaving = true;
			
			jQuery.post(ajaxurl, {
				action: 'o100_crm_add_tag',
				title: this.newTagTitle,
				description: this.newTagDesc,
				is_auto: this.isAuto ? 1 : 0,
				auto_logic: this.autoLogic,
				auto_conditions: JSON.stringify(this.autoConditions)
			}, (response) => {
				this.isSaving = false;
				if (response.success) {
					this.showModal = false;
					this.newTagTitle = '';
					this.newTagDesc = '';
					
					// Fetch current page and replace table body
					jQuery.get(window.location.href, function(html) {
						var newDoc = new DOMParser().parseFromString(html, 'text/html');
						var newTbody = jQuery(newDoc).find('.table-resizable tbody').html();
						if (newTbody) {
							jQuery('.table-resizable tbody').html(newTbody);
						}
					});
				} else {
					this.showToast(response.data.message || 'Error occurred', 'error');
				}
			}).fail(() => {
				this.isSaving = false;
				this.showToast('Network error occurred. Please try again.', 'error');
			});
		},
		
		deleteTag(id) {
			if (!confirm('Are you sure you want to delete this tag?')) return;
			
			jQuery.post(ajaxurl, {
				action: 'o100_crm_delete_tag',
				id: id
			}, (response) => {
				if (response.success) {
					// Fetch current page and replace table body
					jQuery.get(window.location.href, function(html) {
						var newDoc = new DOMParser().parseFromString(html, 'text/html');
						var newTbody = jQuery(newDoc).find('.table-resizable tbody').html();
						if (newTbody) {
							jQuery('.table-resizable tbody').html(newTbody);
						}
					});
				} else {
					alert(response.data.message || 'Error occurred');
				}
			});
		},
		
		saveSmartRules() {
			this.isSavingRules = true;
			jQuery.post(ajaxurl, {
				action: 'o100_crm_save_smart_tag_rules',
				rules: JSON.stringify(this.smartRules)
			}, (response) => {
				this.isSavingRules = false;
				if (response.success) {
					this.showToast('Configuration saved successfully. Tags will update for new orders.', 'success');
				} else {
					this.showToast(response.data.message || 'Error occurred', 'error');
				}
			}).fail(() => {
				this.isSavingRules = false;
				this.showToast('Network error occurred. Please try again.', 'error');
			});
		}
	}
}
</script>
