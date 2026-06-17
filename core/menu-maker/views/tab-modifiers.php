<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div x-data="modifierManager()" class="o100-mm-modifiers space-y-6">
	<!-- Top Bar -->
	<div class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-slate-200">
		<div class="flex items-center space-x-4">
			<div>
				<h2 class="text-xl font-semibold text-slate-800"><?php esc_html_e( 'Options & Add-ons', 'order100' ); ?></h2>
				<p class="text-sm text-slate-500 mt-1 mb-0"><?php esc_html_e( 'Create shared customizations like sizes, toppings, and cooking preferences.', 'order100' ); ?></p>
			</div>
			<div class="relative flex items-center mb-4 sm:mb-0 mr-3">
				<select x-model="sortOrder" class="o100-select-unified text-sm shadow-sm w-48">
					<option value="name_asc"><?php esc_html_e( 'Name: A to Z', 'order100' ); ?></option>
					<option value="name_desc"><?php esc_html_e( 'Name: Z to A', 'order100' ); ?></option>
				</select>
			</div>
			<div class="relative flex items-center mb-4 sm:mb-0">
				<input type="search" x-model="searchQuery" placeholder="<?php esc_attr_e( 'Search options...', 'order100' ); ?>" class="o100-search-unified text-sm w-64 shadow-sm">
				<svg style="width: 16px; height: 16px;" class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
			</div>
		</div>
		<button @click="openModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
			<svg style="width: 16px; height: 16px;" class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
			<?php esc_html_e( 'Add Option', 'order100' ); ?>
		</button>
	</div>

	<!-- Loading State -->
	<div x-show="loading" class="p-10 text-center text-slate-500 bg-white rounded-lg border border-slate-200">
		<svg style="width: 32px; height: 32px;" class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
			<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
			<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
		</svg>
		<?php esc_html_e( 'Loading options...', 'order100' ); ?>
	</div>

	<!-- Empty State -->
	<div x-show="!loading && filteredModifiers.length === 0" class="p-12 text-center bg-slate-50 border border-dashed border-slate-300 rounded-lg text-slate-500">
		<svg style="width: 48px; height: 48px;" class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
		<p class="text-lg font-medium text-slate-900 mb-1"><?php esc_html_e( 'No options found', 'order100' ); ?></p>
		<p><?php esc_html_e( 'Create your first option group to get started.', 'order100' ); ?></p>
	</div>

	<!-- List View Container -->
	<div x-show="!loading && filteredModifiers.length > 0" class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-x-auto overflow-y-hidden o100-table-responsive-wrapper">
		<table class="min-w-full divide-y divide-slate-200">
			<thead class="bg-slate-50">
				<tr class="divide-x divide-slate-200">
					<th scope="col" class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Name', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Type', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Assigned To', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider text-center"><?php esc_html_e( 'Choices', 'order100' ); ?></th>
					<th scope="col" class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Actions', 'order100' ); ?></th>
				</tr>
			</thead>
			<tbody class="bg-white divide-y divide-slate-200">
				<template x-for="mod in filteredModifiers" :key="mod._id">
					<tr class="o100-table-row hover:bg-slate-50 transition-colors duration-150 divide-x divide-slate-200">
						<td class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-4">
							<div class="text-sm font-semibold text-slate-900 flex items-center">
								<span x-text="mod._name"></span>
								<template x-if="mod._required === 'yes'">
									<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Required</span>
								</template>
							</div>
							<div class="text-xs text-slate-500 mt-1" x-show="mod._is_woo_var === 'yes'">Imported from Woo Variations</div>
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500" x-text="getTypeLabel(mod._type)"></td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500">
							<template x-if="!mod._apply_to || mod._apply_to === 'all'">
								<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">All Products</span>
							</template>
							<template x-if="mod._apply_to === 'categories'">
								<div class="flex flex-col gap-1 items-start">
									<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Specific Categories</span>
									<span class="text-[11px] text-slate-400" x-text="getAssignedText(mod)"></span>
								</div>
							</template>
							<template x-if="mod._apply_to === 'products'">
								<div class="flex flex-col gap-1 items-start">
									<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Specific Products</span>
									<span class="text-[11px] text-slate-400" x-text="getAssignedText(mod)"></span>
								</div>
							</template>
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center" x-text="Array.isArray(mod._options) ? mod._options.length : 0"></td>
						<td class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
							<div class="flex items-center justify-center gap-2">
							<button @click="openModal(mod)" class="group relative text-blue-600 hover:text-blue-900">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
								<span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-[100] shadow-md">Edit Option</span>
							</button>
							<button @click="duplicateModifier(mod)" class="group relative text-indigo-600 hover:text-indigo-900">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
								<span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-[100] shadow-md">Duplicate Option</span>
							</button>
							<button @click="deleteModifier(mod._id)" class="group relative text-red-600 hover:text-red-900">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
								<span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-[100] shadow-md">Delete Option</span>
							</button>
							</div>
						</td>
					</tr>
				</template>
			</tbody>
		</table>
	</div>

	<!-- Modifier Modal -->
	<div x-show="modalOpen" class="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display:none;">
		<div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
			<div x-show="modalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeModal()"></div>
			<span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
			<div x-show="modalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-slate-50 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full" style="max-width: 900px;">
				
				<!-- Modal Header -->
				<div class="bg-white px-6 py-4 border-b border-slate-200 flex justify-between items-center">
					<h3 class="text-lg leading-6 font-bold text-slate-900" id="modal-title" x-text="form._id ? 'Edit Option: ' + (form._name || '') : 'Add Option'"></h3>
					<button @click="closeModal()" type="button" class="text-slate-400 hover:text-slate-500 focus:outline-none">
						<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
					</button>
				</div>

				<!-- Modal Navigation -->
				<div class="bg-white px-6 border-b border-slate-200">
					<nav class="-mb-px flex space-x-8">
						<a href="#" @click.prevent="activeModalTab = 'general'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'general', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'general'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">General</a>
						<a href="#" @click.prevent="activeModalTab = 'options'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'options', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'options'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Options & Choices</a>
						<a href="#" @click.prevent="activeModalTab = 'conditions'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'conditions', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'conditions'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Conditional Rules</a>
					</nav>
				</div>

				<!-- Modal Body -->
				<div class="px-6 py-6" style="min-height: 400px; max-height: 60vh; overflow-y: auto;">
					
					<!-- General Tab -->
					<div x-show="activeModalTab === 'general'" class="space-y-4">
						<p class="text-sm text-slate-500 mb-4 pb-4 border-b border-slate-100">Configure the fundamental settings for this modifier group. Define its type, display method, and behavior rules.</p>
						
						<!-- Warning for Woo Variants -->
						<template x-if="form._is_woo_var === 'yes'">
							<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
								<div class="flex">
									<div class="ml-3">
										<p class="text-sm text-yellow-700">
											This group was imported from WooCommerce Variations. Modifying structure here may break sync.
										</p>
									</div>
								</div>
							</div>
						</template>

						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Group Name <span class="text-red-500">*</span></label>
								<input type="text" x-model="form._name" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Field Type</label>
								<select x-model="form._type" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="checkbox">Checkbox (Multi-select)</option>
									<option value="radio">Radio (Single-select)</option>
									<option value="select">Dropdown Select</option>
									<option value="text">Short Text</option>
									<option value="textarea">Long Text / Instructions</option>
									<option value="quantity">Quantity Input</option>
								</select>
							</div>
						</div>

						<div class="grid grid-cols-2 gap-6 mt-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Price Display Format</label>
								<select x-model="form._price_display" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="diff">Difference (e.g. +$2.00)</option>
									<option value="actual">Actual Price (e.g. $12.00)</option>
								</select>
								<p class="text-xs text-slate-500 mt-1">How prices are displayed next to choices.</p>
							</div>
						</div>

						<div class="grid grid-cols-3 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Required?</label>
								<select x-model="form._required" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="no">No (Optional)</option>
									<option value="yes">Yes (Required)</option>
								</select>
							</div>
							<div x-show="['checkbox', 'radio'].includes(form._type)">
								<label class="block text-sm font-medium text-slate-700 mb-1">Display Layout</label>
								<select x-model="form._display_type" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="">Default (List)</option>
									<option value="dropdown">Dropdown (if applicable)</option>
									<option value="accor">Accordion / Collapsible</option>
									<option value="inline">Inline Display</option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Assign To</label>
								<select x-model="form._apply_to" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="all">All Products</option>
									<option value="categories">Specific Categories</option>
									<option value="products">Specific Products</option>
								</select>
							</div>
						</div>

						<div x-show="form._apply_to === 'categories'" class="bg-slate-100 p-4 rounded border border-slate-200">
							<label class="block text-sm font-medium text-slate-700 mb-2">Select Categories</label>
							<div class="max-h-32 overflow-y-auto bg-white border border-slate-300 rounded p-2">
								<template x-if="categories && categories.length > 0">
									<template x-for="cat in categories" :key="cat.id">
										<label class="flex items-center space-x-2 mb-1">
											<input type="checkbox" :value="cat.id" x-model="form._category_ids" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
											<span class="text-sm text-slate-700" x-text="cat.name"></span>
										</label>
									</template>
								</template>
								<template x-if="!categories || categories.length === 0">
									<p class="text-xs text-slate-500 italic">No categories loaded or found.</p>
								</template>
							</div>
						</div>
						<div x-show="form._apply_to === 'products'" class="bg-slate-100 p-4 rounded border border-slate-200">
							<label class="block text-sm font-medium text-slate-700 mb-2">Specific Product IDs</label>
							<input type="text" x-model="form._product_ids" placeholder="e.g. 15, 23, 99" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							<p class="text-xs text-slate-500 mt-1">Comma separated list of product IDs.</p>
						</div>

						<!-- Advanced Limits -->
						<div class="border-t border-slate-200 pt-6">
							<h4 class="text-md font-semibold text-slate-800 mb-4">Selection & Quantity Limits</h4>
							
							<div class="grid grid-cols-2 gap-6 mb-4" x-show="form._type === 'checkbox'">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Min Checkboxes</label>
									<input type="number" x-model="form._min_op" placeholder="0" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<p class="text-xs text-slate-500 mt-1">Min different choices user must select.</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Max Checkboxes</label>
									<input type="number" x-model="form._max_op" placeholder="0" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<p class="text-xs text-slate-500 mt-1">Max different choices user can select.</p>
								</div>
							</div>

							<div class="grid grid-cols-2 gap-6 mb-4">
								<div x-show="['checkbox', 'radio'].includes(form._type)">
									<label class="block text-sm font-medium text-slate-700 mb-1">Enable Quantity Selectors</label>
									<select x-model="form._enb_qty" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="no">No</option>
										<option value="yes">Yes</option>
									</select>
									<p class="text-xs text-slate-500 mt-1">Allow customers to choose quantities per item (+/-).</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Enable Images</label>
									<select x-model="form._enb_img" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="no">No</option>
										<option value="yes">Yes</option>
									</select>
									<p class="text-xs text-slate-500 mt-1">Show thumbnail next to each choice.</p>
								</div>
							</div>

							<div x-show="form._enb_qty === 'yes' && ['checkbox', 'radio'].includes(form._type)" class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded border border-slate-200">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Min Total Quantity</label>
									<input type="number" x-model="form._min_opqty" placeholder="0" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<p class="text-xs text-slate-500 mt-1">Sum of all quantities must be at least this number.</p>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Max Total Quantity</label>
									<input type="number" x-model="form._max_opqty" placeholder="0" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<p class="text-xs text-slate-500 mt-1">Sum of all quantities cannot exceed this number.</p>
								</div>
							</div>
						</div>

						<!-- Text/Quantity Pricing -->
						<div x-show="['text', 'textarea', 'quantity'].includes(form._type)" class="border-t border-slate-200 pt-6">
							<h4 class="text-md font-semibold text-slate-800 mb-4">Input Pricing</h4>
							<div class="grid grid-cols-2 gap-6">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Price Type</label>
									<select x-model="form._price_type" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="fixed">Fixed Amount</option>
										<option value="quantity_based">Quantity Based (Multiply by Qty)</option>
									</select>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Price Amount</label>
									<input type="text" x-model="form._price" placeholder="0.00" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
								</div>
							</div>
						</div>
					</div>

					<!-- Options Tab -->
					<div x-show="activeModalTab === 'options'" class="space-y-4">
						<p class="text-sm text-slate-500 mb-4 pb-4 border-b border-slate-100">Define the specific choices available for this modifier group. You can assign prices, configure limits, and set defaults for each choice.</p>
						<div x-show="['checkbox', 'radio', 'select'].includes(form._type)">
							<div class="flex justify-between items-center mb-4">
								<h4 class="text-md font-semibold text-slate-800">Choices</h4>
								<button @click="addOption()" type="button" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded border border-blue-200 hover:bg-blue-100">+ Add Choice</button>
							</div>
							
							<div class="space-y-3" id="o100-options-sortable">
								<template x-for="(opt, index) in form._options" :key="index">
									<div class="flex items-end gap-3 bg-white p-3 border border-slate-200 rounded shadow-sm">
										
										<div class="flex-1 grid grid-cols-12 gap-3 items-end">
											<div class="col-span-4">
												<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Name</label>
												<input type="text" x-model="opt.name" placeholder="Choice name" class="w-full h-9 px-3 text-sm border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
											</div>
											<div class="col-span-2">
												<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Price</label>
												<input type="text" x-model="opt.price" placeholder="0.00" class="w-full h-9 px-3 text-sm border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
											</div>
											<div class="col-span-2">
												<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Sale Price</label>
												<input type="text" x-model="opt.sale_price" placeholder="0.00" class="w-full h-9 px-3 text-sm border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
											</div>
											<div class="col-span-3">
												<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Price Adjust</label>
												<select x-model="opt.type" class="w-full h-9 text-sm border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
													<option value="flat">Per Item</option>
													<option value="fixed">Once</option>
												</select>
											</div>
											<div class="col-span-1 flex justify-center pb-2">
												<label class="flex flex-col items-center cursor-pointer" title="Make this choice default">
													<span class="text-[9px] text-slate-400 uppercase font-bold mb-1">Def</span>
													<input type="checkbox" :checked="opt.def === 'yes'" @change="opt.def = $el.checked ? 'yes' : 'no'" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4 shadow-sm">
												</label>
											</div>
										</div>

										<!-- Optional fields based on General settings -->
										<div class="flex-shrink-0 flex gap-2 items-end" x-show="form._enb_qty === 'yes'">
											<div class="w-14">
												<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Min</label>
												<input type="number" x-model="opt.min" placeholder="0" class="w-full h-9 px-2 text-sm border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
											</div>
											<div class="w-14">
												<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Max</label>
												<input type="number" x-model="opt.max" placeholder="0" class="w-full h-9 px-2 text-sm border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500">
											</div>
										</div>

										<div class="flex-shrink-0 flex flex-col" x-show="form._enb_img === 'yes'" style="width:100px;">
											<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Image</label>
											<div class="flex items-center space-x-2 h-9">
												<template x-if="opt.image">
													<img :src="opt.image" class="w-7 h-7 rounded object-cover border border-slate-200">
												</template>
												<button @click="openMediaLib(opt)" type="button" class="text-[10px] uppercase font-bold tracking-wider bg-slate-100 text-slate-700 border border-slate-300 rounded hover:bg-slate-200 shadow-sm transition-colors whitespace-nowrap h-7 px-2 flex items-center">Choose</button>
												<template x-if="opt.image">
													<button @click="opt.image=''" type="button" class="text-red-500 hover:text-red-700 p-0" title="Remove Image">
														<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
													</button>
												</template>
											</div>
										</div>

										<div class="flex items-center h-9 space-x-1 shrink-0 pb-1">
											<button @click="duplicateOption(index)" type="button" class="text-slate-400 hover:text-blue-600 p-1 transition-colors" title="Duplicate Choice">
												<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
											</button>
											<button @click="removeOption(index)" type="button" class="text-red-400 hover:text-red-600 p-1 transition-colors" title="Delete Choice">
												<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
											</button>
										</div>
									</div>
								</template>
								<template x-if="!form._options || form._options.length === 0">
									<div class="p-6 text-center text-slate-500 border border-dashed border-slate-300 rounded bg-slate-50">
										No choices added yet.
									</div>
								</template>
							</div>
						</div>
						<div x-show="!['checkbox', 'radio', 'select'].includes(form._type)" class="p-6 text-center text-slate-500 bg-slate-50 rounded">
							Options are not applicable for the selected Field Type (<span x-text="form._type"></span>).
						</div>
					</div>

					<!-- Conditions Tab -->
					<div x-show="activeModalTab === 'conditions'" class="space-y-4">
						<p class="text-sm text-slate-500 mb-4 pb-4 border-b border-slate-100">Set up rules to show or hide this modifier group based on other selections.</p>
						
						<div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
							<div class="flex">
								<div class="ml-3">
									<p class="text-sm text-blue-700">
										<strong>Note:</strong> Please ensure the target modifier is also assigned to the same products or categories. If a product does not have the condition modifier assigned, this group will be hidden.
									</p>
								</div>
							</div>
						</div>

						<div>
							<label class="flex items-center space-x-2">
								<input type="checkbox" :checked="form._enb_logic === 'yes'" @change="form._enb_logic = $el.checked ? 'yes' : 'no'" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4 shadow-sm">
								<span class="text-sm font-medium text-slate-700">Enable Conditional Logic</span>
							</label>
						</div>

						<div x-show="form._enb_logic === 'yes'" class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded">
							<div class="flex items-center space-x-2 mb-4">
								<span class="text-sm text-slate-700">Show this group if</span>
								<select x-model="form._con_tlogic" class="border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-8 py-0">
									<option value="any">Any</option>
									<option value="all">All</option>
								</select>
								<span class="text-sm text-slate-700">of these rules match:</span>
							</div>

							<div class="space-y-2">
								<template x-for="(rule, idx) in form._con_logic" :key="idx">
									<div class="flex items-center space-x-2 bg-white p-2 border border-slate-200 rounded">
										<span class="text-xs font-semibold text-slate-500 uppercase tracking-wider w-8 text-center" x-text="idx === 0 ? 'If' : (form._con_tlogic === 'any' ? 'OR' : 'AND')"></span>
										<select x-model="rule.type_op" class="flex-1 border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-8 py-0">
											<option value="">Select Modifier...</option>
											<template x-for="mod in availableModifiersForLogic" :key="mod._id">
												<option :value="mod._id" x-text="mod._name"></option>
											</template>
										</select>
										<span class="text-xs text-slate-500 px-2">is</span>
										<select x-model="rule.val" class="flex-1 border-slate-300 rounded shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm h-8 py-0">
											<option value="">Select Choice...</option>
											<template x-for="opt in getChoicesForModifier(rule.type_op)" :key="opt.name">
												<option :value="opt.name" x-text="opt.name"></option>
											</template>
										</select>
										<button @click="removeCondition(idx)" type="button" class="text-red-400 hover:text-red-600 p-1">
											<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
										</button>
									</div>
								</template>
							</div>

							<button @click="addCondition()" type="button" class="mt-4 text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded border border-blue-200 hover:bg-blue-100">+ Add Rule</button>
						</div>
					</div>

				</div>

				<!-- Modal Footer -->
				<div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-between items-center">
					<div>
						<button @click="closeModal()" type="button" class="px-4 py-2 bg-white border border-slate-300 rounded-md shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200">
							Cancel
						</button>
					</div>
					<div class="flex space-x-3 items-center">
						<button x-show="activeModalTab !== 'conditions'" @click="activeModalTab = (activeModalTab === 'general' ? 'options' : 'conditions')" type="button" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white !bg-blue-600 hover:!bg-blue-700 focus:outline-none flex items-center transition-colors">
							Next Step
							<svg class="ml-2 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
						</button>
						<!-- Save button (Keep modal open for General & Choices tabs) -->
						<button x-show="['general', 'options'].includes(activeModalTab)" @click="saveModifier(false)" :disabled="saving" type="button" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white !bg-indigo-600 hover:!bg-indigo-700 focus:outline-none flex items-center disabled:opacity-50 transition-colors">
							<template x-if="saving">
								<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
							</template>
							Save
						</button>
						<!-- Save & Close button (Close modal for last tab: Conditional Rules) -->
						<button x-show="activeModalTab === 'conditions'" @click="saveModifier(true)" :disabled="saving" type="button" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white !bg-indigo-600 hover:!bg-indigo-700 focus:outline-none flex items-center disabled:opacity-50 transition-colors">
							<template x-if="saving">
								<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
							</template>
							Save & Close
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<script>
document.addEventListener('alpine:init', () => {
	Alpine.data('modifierManager', () => ({
		modifiers: [],
		loading: false,
		async apiRequest(route, method = 'GET', data = {}) {
			const rootEl = document.querySelector('.o100-menu-maker-wrap');
			if (rootEl && typeof Alpine !== 'undefined' && Alpine.$data) {
				const parentData = Alpine.$data(rootEl);
				if (parentData && typeof parentData.apiRequest === 'function') {
					return parentData.apiRequest(route, method, data);
				}
			}
			return null;
		},
		saving: false,
		modalOpen: false,
		activeModalTab: 'general',
		searchQuery: '',
		sortOrder: 'name_asc',
		form: {
			_id: '', _name: '', _type: 'checkbox', _required: 'no', _display_type: '',
			_apply_to: 'all', _category_ids: [], _product_ids: '',
			_min_op: '', _max_op: '', _enb_img: 'no', _enb_qty: 'no',
			_min_opqty: '', _max_opqty: '', _price_type: 'fixed', _price: '',
			_options: [], _enb_logic: '', _con_tlogic: 'any', _con_logic: [],
			_price_display: 'diff'
		},
		
		init() {
			this.$watch('activeTab', (val) => {
				if (val === 'modifiers' && this.modifiers.length === 0) {
					this.fetchModifiers();
				}
			});
			if (this.activeTab === 'modifiers') {
				this.fetchModifiers();
			}
		},

		get filteredModifiers() {
			let result = [...this.modifiers];
			if (this.searchQuery.trim() !== '') {
				const sq = this.searchQuery.toLowerCase();
				result = result.filter(m => (m._name && m._name.toLowerCase().includes(sq)));
			}
			if (this.sortOrder === 'name_asc') {
				result.sort((a, b) => (a._name || '').localeCompare(b._name || ''));
			} else {
				result.sort((a, b) => (b._name || '').localeCompare(a._name || ''));
			}
			return result;
		},

		getTypeLabel(type) {
			const map = {
				'checkbox': 'Checkbox (Multi)',
				'radio': 'Radio (Single)',
				'select': 'Dropdown',
				'text': 'Short Text',
				'textarea': 'Long Text',
				'quantity': 'Quantity'
			};
			return map[type] || 'Checkbox';
		},



		emptyForm() {
			return {
				_id: '', _name: '', _type: 'checkbox', _required: 'no', _display_type: '',
				_apply_to: 'all', _category_ids: [], _product_ids: '',
				_min_op: '', _max_op: '', _enb_img: 'no', _enb_qty: 'no',
				_min_opqty: '', _max_opqty: '', _price_type: 'fixed', _price: '',
				_options: [], _enb_logic: '', _con_tlogic: 'any', _con_logic: [],
				_price_display: 'diff'
			};
		},

		async fetchModifiers() {
			this.loading = true;
			try {
				let data = await this.apiRequest('/modifiers', 'GET');
				if (data) {
					// Handle cases where backend returns object instead of array
					if (!Array.isArray(data) && typeof data === 'object') {
						data = Object.values(data);
					}
					if (Array.isArray(data)) {
						this.modifiers = data;
					}
				}
			} catch (e) {
				console.error(e);
			} finally {
				this.loading = false;
			}
		},

		getChoicesForMod(modId) {
			if (!modId) return [];
			const mod = this.modifiers.find(m => String(m._id) === String(modId));
			return mod && mod._options ? mod._options : [];
		},

		getAssignedText(mod) {
			if (!mod._apply_to || mod._apply_to === 'all') return '';
			if (mod._apply_to === 'categories') {
				if (!mod._category_ids || mod._category_ids.length === 0) return 'None selected';
				// _category_ids may be a comma-separated string (from backend) or array (from form)
				const catIds = Array.isArray(mod._category_ids)
					? mod._category_ids
					: String(mod._category_ids).split(',').map(s => s.trim()).filter(s => s);
				if (catIds.length === 0) return 'None selected';
				const names = catIds.map(id => {
					const cat = this.categories ? this.categories.find(c => String(c.id) === String(id)) : null;
					return cat && cat.name ? cat.name : 'ID: ' + id;
				});
				if (names.length <= 4) return names.join(', ');
				return names.slice(0, 4).join(', ') + '...';
			}
			if (mod._apply_to === 'products') {
				if (!mod._product_ids) return 'None selected';
				const ids = String(mod._product_ids).split(',').map(s => s.trim()).filter(s => s);
				if (ids.length === 0) return 'None selected';
				if (ids.length <= 4) return 'IDs: ' + ids.join(', ');
				return 'IDs: ' + ids.slice(0, 4).join(', ') + '...';
			}
			return '';
		},

		resetTimeout: null,

		openModal(mod = null) {
			if (this.resetTimeout) {
				clearTimeout(this.resetTimeout);
				this.resetTimeout = null;
			}
			if (mod) {
				// Deep copy to prevent live edit issues before save
				this.form = JSON.parse(JSON.stringify(mod));
				if (this.form._category_ids && typeof this.form._category_ids === 'string') {
					this.form._category_ids = this.form._category_ids.split(',').map(s=>s.trim()).filter(s=>s);
				}
				if (this.form._category_ids && typeof this.form._category_ids === 'object' && !Array.isArray(this.form._category_ids)) {
					this.form._category_ids = Object.values(this.form._category_ids);
				}
				if (!Array.isArray(this.form._category_ids)) this.form._category_ids = [];
				
				if (this.form._options && typeof this.form._options === 'object' && !Array.isArray(this.form._options)) {
					this.form._options = Object.values(this.form._options);
				}
				if (!Array.isArray(this.form._options)) this.form._options = [];
				
				if (this.form._con_logic && typeof this.form._con_logic === 'object' && !Array.isArray(this.form._con_logic)) {
					this.form._con_logic = Object.values(this.form._con_logic);
				}
				if (!Array.isArray(this.form._con_logic)) this.form._con_logic = [];
			} else {
				this.form = this.emptyForm();
			}
			this.activeModalTab = 'general';
			this.modalOpen = true;
			

		},

		closeModal() {
			this.modalOpen = false;
			// Delay form reset until after the modal transition finishes to prevent visual glitches and Alpine crashes
			this.resetTimeout = setTimeout(() => {
				this.form = this.emptyForm();
			}, 300);
		},

		addOption() {
			if (!Array.isArray(this.form._options)) this.form._options = [];
			this.form._options.push({
				name: '', price: '', sale_price: '', type: 'fixed', min: '', max: '', def: 'no', dis: 'no', image: ''
			});
		},
		removeOption(index) {
			this.form._options.splice(index, 1);
		},
		duplicateOption(index) {
			let dup = JSON.parse(JSON.stringify(this.form._options[index]));
			dup.name = dup.name + ' (Copy)';
			this.form._options.splice(index + 1, 0, dup);
		},

		get availableModifiersForLogic() {
			return this.modifiers.filter(m => String(m._id) !== String(this.form._id));
		},
		getChoicesForModifier(modId) {
			if (!modId) return [];
			const mod = this.modifiers.find(m => String(m._id) === String(modId));
			if (!mod || !Array.isArray(mod._options)) return [];
			return mod._options;
		},
		addCondition() {
			if (!Array.isArray(this.form._con_logic)) this.form._con_logic = [];
			this.form._con_logic.push({ type_op: '', type_con: 'is', val: '' });
		},
		removeCondition(index) {
			this.form._con_logic.splice(index, 1);
		},



		openMediaLib(opt) {
			if (typeof wp === 'undefined' || !wp.media) {
				alert('Media library not available.');
				return;
			}
			
			// CRITICAL: Store frame on window, NOT on Alpine's reactive proxy (this).
			// Alpine v3 wraps 'this' properties in a Proxy, which corrupts
			// the Backbone-based wp.media Frame's internal jQuery methods.
			if (!window.__o100_mod_media_frame) {
				window.__o100_mod_media_frame = wp.media({
					title: 'Select Choice Image',
					button: { text: 'Use this image' },
					multiple: false,
					library: { type: 'image' }
				});
			}
			
			var frame = window.__o100_mod_media_frame;
			frame.off('select');
			frame.on('select', () => {
				const attachment = frame.state().get('selection').first().toJSON();
				opt.image = attachment.url;
			});
			
			frame.open();
		},

		duplicateModifier(mod) {
			let dup = JSON.parse(JSON.stringify(mod));
			dup._id = '';
			dup._name = dup._name + ' (Copy)';
			dup._is_woo_var = '';
			this.openModal(dup);
		},

		showErrorToast(msg) {
			if (typeof jQuery !== 'undefined') {
				var $toast = jQuery('<div class="o100-toast" style="border-color: #ef4444; z-index: 9999;"><div class="o100-toast-icon" style="background: #ef4444;">✗</div><div class="o100-toast-body"><h4>Error</h4><p>' + msg + '</p></div><button class="o100-toast-close" type="button">×</button></div>');
				jQuery('body').append($toast);
				setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
				var removeToast = function() { $toast.removeClass('o100-toast--visible'); setTimeout(function() { $toast.remove(); }, 300); };
				setTimeout(removeToast, 3000);
				$toast.find('.o100-toast-close').on('click', removeToast);
			} else {
				console.error(msg);
			}
		},

		async saveModifier(shouldClose = true) {
			if (this.saving) return;
			// DOM-direct read to bypass Alpine proxy syncing delays
			const nameInput = document.querySelector('input[x-model="form._name"]');
			if (nameInput && nameInput.value.trim() !== '') {
				this.form._name = nameInput.value;
			}
			if (!this.form._name || String(this.form._name).trim() === '') {
				this.showErrorToast('Group Name is required.');
				return;
			}
			this.saving = true;
			const data = await this.apiRequest('/modifiers', 'POST', this.form);
			if (data && data.success) {
				this.form._id = data.group._id;
				const existingIndex = this.modifiers.findIndex(m => m._id === data.group._id);
				if (existingIndex > -1) {
					this.modifiers[existingIndex] = data.group;
				} else {
					this.modifiers.push(data.group);
				}
				
				if (shouldClose) {
					this.closeModal();
				} else {
					// Show a success toast indicating changes are saved
					if (typeof jQuery !== 'undefined') {
						var $toast = jQuery('<div class="o100-toast" style="border-color: #10b981; z-index: 9999;"><div class="o100-toast-icon" style="background: #10b981;">✓</div><div class="o100-toast-body"><h4>Success</h4><p>Option saved successfully.</p></div><button class="o100-toast-close" type="button">×</button></div>');
						jQuery('body').append($toast);
						setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
						var removeToast = function() { $toast.removeClass('o100-toast--visible'); setTimeout(function() { $toast.remove(); }, 300); };
						setTimeout(removeToast, 3000);
						$toast.find('.o100-toast-close').on('click', removeToast);
					}
				}
			}
			this.saving = false;
		},

		async deleteModifier(id) {
			if (!await window.o100Confirm('Delete Option', 'Are you sure you want to delete this shared modifier group? It will be removed from all assigned items.')) return;
			const data = await this.apiRequest('/modifiers?id=' + id, 'DELETE');
			if (data && data.success) {
				this.modifiers = this.modifiers.filter(m => m._id !== id);
			} else {
				this.showErrorToast('Failed to delete the modifier group.');
			}
		}
	}));
});
</script>
