<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$lists = O100_Customers_DB::get_lists();
$customers_url = admin_url('admin.php?page=o100-customers');
?>
<div x-data="customersLists()" class="space-y-4">
	<!-- Top Header: Title & Actions -->
	<div class="flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-slate-200">
		<div class="flex items-center space-x-2">
			<h2 class="text-xl font-semibold text-slate-800">Contact Lists</h2>
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
						<label class="flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 cursor-pointer">
							<input type="checkbox" x-model="cols.type" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 mr-3"> Type
						</label>
					</div>
				</div>
			</div>
			<button @click="showModal = true" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
				<svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
					<path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
				</svg>
				Create List
			</button>
		</div>
	</div>

	<!-- Main Table Container -->
	<div class="bg-white rounded-lg shadow-sm border border-slate-200">
		<div class="overflow-x-auto">
			<table class="table-resizable min-w-full divide-y divide-x divide-slate-200">
				<thead class="bg-slate-50">
					<tr>
						<th x-show="cols.title" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Title</th>
						<th x-show="cols.slug" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden sm:table-cell">Slug</th>
						<th x-show="cols.desc" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden md:table-cell">Description</th>
						<th x-show="cols.type" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden sm:table-cell">Type</th>
						<th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-slate-200">
					<?php if ( empty( $lists ) ) : ?>
						<tr>
							<td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center">No lists found.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $lists as $list ) : ?>
							<tr class="hover:bg-slate-50">
								<td x-show="cols.title" class="px-6 py-4 whitespace-nowrap text-sm font-medium">
									<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'customers', 'filter_list' => $list->id ], $customers_url ) ); ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">
										<?php echo esc_html( $list->title ); ?>
									</a>
								</td>
								<td x-show="cols.slug" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 hidden sm:table-cell"><?php echo esc_html( $list->slug ); ?></td>
								<td x-show="cols.desc" class="px-6 py-4 text-sm text-slate-500 hidden md:table-cell"><?php echo esc_html( $list->description ); ?></td>
								<td x-show="cols.type" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 hidden sm:table-cell">
									<?php if ( $list->is_system ) : ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">System</span>
									<?php else : ?>
										<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Custom</span>
									<?php endif; ?>
								</td>
								<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
									<div class="flex justify-end space-x-2">
										<button @click="alert('Edit functionality coming soon.')" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-sm font-medium rounded text-indigo-700 bg-white hover:bg-slate-50 focus:outline-none" title="Edit">
											Edit
										</button>
										<button @click="alert('Settings functionality coming soon.')" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-sm font-medium rounded text-indigo-700 bg-white hover:bg-slate-50 focus:outline-none" title="Settings">
											Settings
										</button>
										<?php if ( ! $list->is_system ) : ?>
											<button @click="deleteList(<?php echo intval( $list->id ); ?>)" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-sm font-medium rounded text-red-700 bg-white hover:bg-slate-50 focus:outline-none" title="Delete">
												Delete
											</button>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Modal -->
	<div x-show="showModal" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display: none;">
		<!-- Create List Modal (o100-modal style) -->
		<div x-show="showModal" style="display: none;" class="fixed inset-0 z-[99999] bg-black bg-opacity-60 flex items-center justify-center" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="showModal" @click.away="showModal = false" x-transition class="bg-white w-[600px] max-w-full rounded-xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
				<!-- Header -->
				<div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0">
					<h3 class="m-0 text-base font-bold text-gray-900 uppercase tracking-wide" id="modal-title">CREATE NEW LIST</h3>
					<span @click="showModal = false" class="dashicons dashicons-no-alt cursor-pointer text-gray-400 hover:text-gray-600"></span>
				</div>
				<!-- Body -->
				<div class="p-5 overflow-y-auto">
							<div class="mt-4">
								<div class="mb-4">
									<label class="block text-sm font-medium text-gray-700">List Title <span class="text-red-500">*</span></label>
									<input type="text" x-model="newListTitle" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500">
								</div>
								<div class="mb-4">
									<label class="block text-sm font-medium text-gray-700">Description</label>
									<textarea x-model="newListDesc" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md p-2 border focus:ring-indigo-500 focus:border-indigo-500"></textarea>
								</div>

								<div class="mt-6 border-t pt-4">
									<div class="flex items-center justify-between mb-4">
										<div>
											<h4 class="text-sm font-medium text-gray-900">Automate Assignment</h4>
											<p class="text-xs text-gray-500">Automatically add customers to this list based on rules.</p>
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
					<button @click="saveList" :disabled="isSaving" type="button" class="button button-primary !rounded-md px-4 py-2 !bg-indigo-600 !border-indigo-600 hover:!bg-indigo-700">
						<span x-show="!isSaving">Save List</span>
						<span x-show="isSaving">Saving...</span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
function customersLists() {
	return {
		showCols: false,
		cols: {
			title: true,
			slug: true,
			desc: true,
			type: true
		},
		showModal: false,
		newListTitle: '',
		newListDesc: '',
		isSaving: false,
		
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
		
		saveList() {
			if (!this.newListTitle) {
				alert('Please enter a list title.');
				return;
			}
			
			this.isSaving = true;
			
			jQuery.post(ajaxurl, {
				action: 'o100_crm_add_list',
				title: this.newListTitle,
				description: this.newListDesc,
				is_auto: this.isAuto ? 1 : 0,
				auto_logic: this.autoLogic,
				auto_conditions: JSON.stringify(this.autoConditions)
			}, (response) => {
				this.isSaving = false;
				if (response.success) {
					this.showModal = false;
					this.newListTitle = '';
					this.newListDesc = '';
					
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
			}).fail(() => {
				this.isSaving = false;
				alert('Network error occurred. Please try again.');
			});
		},
		
		deleteList(id) {
			if (!confirm('Are you sure you want to delete this list?')) return;
			
			jQuery.post(ajaxurl, {
				action: 'o100_crm_delete_list',
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
		}
	}
}
</script>
