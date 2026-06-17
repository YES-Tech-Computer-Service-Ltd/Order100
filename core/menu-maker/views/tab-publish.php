<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div x-data="publishManager()" class="o100-mm-publish space-y-6">
	<!-- Top Bar -->
	<div class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-slate-200">
		<div class="flex items-center space-x-4">
			<div>
				<h2 class="text-xl font-semibold text-slate-800"><?php esc_html_e( 'Storefront', 'order100' ); ?></h2>
				<p class="text-sm text-slate-500 mt-1 mb-0"><?php esc_html_e( 'Configure how your menu is displayed on your website.', 'order100' ); ?></p>
			</div>
			<div class="relative flex items-center mb-4 sm:mb-0 mr-3">
				<select x-model="sortOrder" class="o100-select-unified text-sm shadow-sm w-48">
					<option value="name_asc"><?php esc_html_e( 'Name: A to Z', 'order100' ); ?></option>
					<option value="name_desc"><?php esc_html_e( 'Name: Z to A', 'order100' ); ?></option>
				</select>
			</div>
			<div class="relative flex items-center mb-4 sm:mb-0">
				<input type="search" x-model="searchQuery" placeholder="<?php esc_attr_e( 'Search displays...', 'order100' ); ?>" class="o100-search-unified text-sm w-64 shadow-sm">
				<svg style="width: 16px; height: 16px;" class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
			</div>
		</div>
		<button @click="openModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
			<svg style="width: 16px; height: 16px;" class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
			<?php esc_html_e( 'Create Display', 'order100' ); ?>
		</button>
	</div>

	<!-- Loading State -->
	<div x-show="loading" class="p-10 text-center text-slate-500 bg-white rounded-lg border border-slate-200">
		<svg style="width: 32px; height: 32px;" class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
			<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
			<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
		</svg>
		<?php esc_html_e( 'Loading displays...', 'order100' ); ?>
	</div>

	<!-- Empty State -->
	<div x-show="!loading && filteredDisplays.length === 0" class="p-12 text-center bg-slate-50 border border-dashed border-slate-300 rounded-lg text-slate-500">
		<svg style="width: 48px; height: 48px;" class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
		<p class="text-lg font-medium text-slate-900 mb-1"><?php esc_html_e( 'No displays found', 'order100' ); ?></p>
		<p><?php esc_html_e( 'Create your first display shortcode to publish your menu.', 'order100' ); ?></p>
	</div>

	<!-- List View Container -->
	<div x-show="!loading && displays.length > 0" class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-x-auto overflow-y-hidden o100-table-responsive-wrapper">
		<table class="min-w-full divide-y divide-slate-200">
			<thead class="bg-slate-50">
				<tr class="divide-x divide-slate-200">
					<th scope="col" class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Name', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Shortcode', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Type', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Data Source', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider text-center"><?php esc_html_e( 'Columns', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Usage Detected', 'order100' ); ?></th>
					<th scope="col" class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Actions', 'order100' ); ?></th>
				</tr>
			</thead>
			<tbody class="bg-white divide-y divide-slate-200">
				<template x-for="disp in filteredDisplays" :key="disp.id">
					<tr class="o100-table-row transition-colors duration-150 divide-x divide-slate-200">
						<td class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-4">
							<div class="text-sm font-semibold text-slate-900 flex items-center">
								<span x-text="disp.name"></span>
							</div>
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap">
							<div class="flex items-center space-x-2">
								<code class="bg-slate-100 text-slate-800 px-2 py-1 rounded border border-slate-200 text-xs font-mono select-all" x-text="'[o100_menu id=&quot;' + disp.id + '&quot;]'"></code>
								<button @click="copyShortcode(disp.id)" class="text-slate-400 hover:text-slate-600 focus:outline-none" title="Copy to clipboard">
									<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
								</button>
							</div>
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500" x-text="getTypeLabel(disp.sc_type)"></td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500" x-html="getAssignedText(disp)"></td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center" x-text="disp.columns || 3"></td>
						<td class="px-3 sm:px-4 md:px-6 py-4">
							<template x-if="!disp._usage || disp._usage.length === 0">
								<span class="text-xs text-slate-400 italic">Not detected</span>
							</template>
							<template x-if="disp._usage && disp._usage.length > 0">
								<div class="flex flex-col gap-1 items-start">
									<template x-for="u in disp._usage">
										<a :href="u.url" target="_blank" class="text-[11px] text-blue-600 hover:text-blue-800 hover:underline inline-flex items-center" title="View Page">
											<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
											<span x-text="u.title"></span>
										</a>
									</template>
								</div>
							</template>
						</td>
						<td class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
							<button @click="openModal(disp)" class="group relative text-blue-600 hover:text-blue-900 mr-3" title="Edit">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
								<span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-[100] shadow-md">Edit Display</span>
							</button>
							<button @click="duplicateDisplay(disp)" class="group relative text-indigo-600 hover:text-indigo-900 mr-3" title="Duplicate">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
								<span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-[100] shadow-md">Duplicate Display</span>
							</button>
							<button @click="deleteDisplay(disp.id)" class="group relative text-red-600 hover:text-red-900" title="Delete">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
								<span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-1 hidden group-hover:block bg-slate-800 text-white text-[10px] px-2 py-1 rounded whitespace-nowrap z-[100] shadow-md">Delete Display</span>
							</button>
						</td>
					</tr>
				</template>
			</tbody>
		</table>
	</div>

	<!-- Display Modal -->
	<div x-show="modalOpen" class="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" style="display:none;">
		<div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
			<div x-show="modalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeModal()"></div>
			<span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
			<div x-show="modalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-slate-50 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full" style="max-width: 900px;">
				
				<!-- Modal Header -->
				<div class="bg-white px-6 py-4 border-b border-slate-200 flex justify-between items-center">
					<h3 class="text-lg leading-6 font-bold text-slate-900" id="modal-title" x-text="form.id ? 'Edit Display: ' + form.name : 'Create Display'"></h3>
					<button @click="closeModal()" type="button" class="text-slate-400 hover:text-slate-500 focus:outline-none">
						<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
					</button>
				</div>

				<!-- Modal Navigation -->
				<div class="bg-white px-6 border-b border-slate-200">
					<nav class="-mb-px flex space-x-8">
						<a href="#" @click.prevent="activeModalTab = 'layout'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'layout', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'layout'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Layout & Style</a>
						<a href="#" @click.prevent="activeModalTab = 'query'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'query', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'query'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Query & Filtering</a>
						<a href="#" @click.prevent="activeModalTab = 'pagination'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'pagination', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'pagination'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Pagination</a>
						<a href="#" x-show="form.sc_type === 'carousel'" @click.prevent="activeModalTab = 'carousel'" :class="{'border-blue-500 text-blue-600': activeModalTab === 'carousel', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeModalTab !== 'carousel'}" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Carousel Options</a>
					</nav>
				</div>

				<!-- Modal Body -->
				<div class="px-6 py-6" style="max-height: 60vh; overflow-y: auto;">
					
					<!-- Layout Tab -->
					<div x-show="activeModalTab === 'layout'" class="space-y-6">
						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Display Name <span class="text-red-500">*</span></label>
								<input type="text" x-model="form.name" placeholder="e.g. Homepage Menu" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Architecture Type</label>
								<select x-model="form.sc_type" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="mn_group">Menu Groups (Categorized)</option>
									<option value="grid">Grid View</option>
									<option value="list">List View</option>
									<option value="table">Table View</option>
									<option value="carousel">Carousel Slider</option>
								</select>
							</div>
						</div>

						<div class="grid grid-cols-2 gap-6" x-show="['mn_group', 'carousel'].includes(form.sc_type)">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Item Card Style</label>
								<select x-model="form.sc_layout" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="list">List Style</option>
									<option value="grid">Grid Style</option>
									<option value="table">Table Style</option>
								</select>
							</div>
							<div x-show="form.sc_type === 'mn_group'">
								<label class="block text-sm font-medium text-slate-700 mb-1">Group Heading Title</label>
								<input type="text" x-model="form.sc_heading" placeholder="Leave empty for category name" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							</div>
						</div>

						<!-- Columns Grid -->
						<div class="border-t border-slate-200 pt-6">
							<h4 class="text-md font-semibold text-slate-800 mb-4">Responsive Columns</h4>
							<div class="grid grid-cols-3 gap-6">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Desktop Columns</label>
									<select x-model="form.columns" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="1">1 Column</option>
										<option value="2">2 Columns</option>
										<option value="3">3 Columns</option>
										<option value="4">4 Columns</option>
										<option value="5">5 Columns</option>
										<option value="6">6 Columns</option>
									</select>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Tablet Columns</label>
									<select x-model="form.column_tablet" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="1">1 Column</option>
										<option value="2">2 Columns</option>
										<option value="3">3 Columns</option>
										<option value="4">4 Columns</option>
									</select>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Mobile Columns</label>
									<select x-model="form.column_mobile" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="1">1 Column</option>
										<option value="2">2 Columns</option>
									</select>
								</div>
							</div>
						</div>

						<div class="border-t border-slate-200 pt-6">
							<h4 class="text-md font-semibold text-slate-800 mb-4">Item Appearance</h4>
							<div class="grid grid-cols-2 gap-6">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Image Size</label>
									<select x-model="form.img_size" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="thumbnail">Thumbnail</option>
										<option value="medium">Medium</option>
										<option value="large">Large</option>
										<option value="full">Full</option>
									</select>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Excerpt Word Limit</label>
									<input type="number" x-model="form.number_excerpt" placeholder="e.g. 20" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
								</div>
							</div>
						</div>
					</div>

					<!-- Query Tab -->
					<div x-show="activeModalTab === 'query'" class="space-y-6">
						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Enable Category Filter</label>
								<select x-model="form.menu_filter" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="yes">Yes</option>
									<option value="no">No</option>
								</select>
							</div>
							<div x-show="form.menu_filter === 'yes'">
								<label class="block text-sm font-medium text-slate-700 mb-1">Filter Style</label>
								<select x-model="form.filter_style" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="text">Text Based</option>
									<option value="icon">Icon Based</option>
								</select>
							</div>
						</div>

						<div class="grid grid-cols-2 gap-6" x-show="form.menu_filter === 'yes'">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Filter Location</label>
								<select x-model="form.menu_pos" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="top">Top</option>
									<option value="left">Left Sidebar</option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Hide "All" Option</label>
								<select x-model="form.hide_ftall" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="no">No</option>
									<option value="yes">Yes</option>
								</select>
							</div>
						</div>

						<div class="border-t border-slate-200 pt-6">
							<h4 class="text-md font-semibold text-slate-800 mb-4">Data Source</h4>
							
							<div class="grid grid-cols-1 gap-6">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-1">Select Products By</label>
									<select x-model="form.data_source_type" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
										<option value="all">All Products</option>
										<option value="categories">Specific Categories</option>
										<option value="tags">Specific Tags</option>
										<option value="products">Specific Products</option>
										<option value="featured">Featured Products Only</option>
									</select>
								</div>

								<div x-show="form.data_source_type === 'categories'" class="bg-slate-100 p-4 rounded border border-slate-200">
									<label class="block text-sm font-medium text-slate-700 mb-2">Include Categories</label>
									<div class="max-h-40 overflow-y-auto bg-white border border-slate-300 rounded p-2">
										<template x-if="categories && categories.length > 0">
											<template x-for="c in categories" :key="c.id">
												<label class="flex items-center space-x-2 mb-1">
													<input type="checkbox" :value="c.id" x-model="form.cat" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
													<span class="text-sm text-slate-700" x-text="c.name"></span>
												</label>
											</template>
										</template>
										<template x-if="!categories || categories.length === 0">
											<p class="text-xs text-slate-500 italic">No categories loaded or found.</p>
										</template>
									</div>
								</div>

								<div x-show="form.data_source_type === 'tags'" class="bg-slate-100 p-4 rounded border border-slate-200">
									<label class="block text-sm font-medium text-slate-700 mb-2">Include Tags</label>
									<div class="max-h-40 overflow-y-auto bg-white border border-slate-300 rounded p-2">
										<template x-if="tags && tags.length > 0">
											<template x-for="t in tags" :key="t.id">
												<label class="flex items-center space-x-2 mb-1">
													<input type="checkbox" :value="t.id" x-model="form.tags" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
													<span class="text-sm text-slate-700" x-text="t.name"></span>
												</label>
											</template>
										</template>
										<template x-if="!tags || tags.length === 0">
											<p class="text-xs text-slate-500 italic">No tags loaded or found.</p>
										</template>
									</div>
								</div>
								
								<div x-show="form.data_source_type === 'products'" class="bg-slate-100 p-4 rounded border border-slate-200" x-data="{ search: '', showDropdown: false }">
									<label class="block text-sm font-medium text-slate-700 mb-1">Select Specific Products</label>
									<div class="relative">
										<!-- Search Input -->
										<div class="relative">
											<input type="text" x-model="search" @focus="showDropdown = true; if(items.length===0){ fetchItems(); }" @click.outside="showDropdown = false" placeholder="Search products..." style="padding-left: 36px !important;" class="w-full pr-3 py-2 border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
											<svg style="position: absolute; left: 12px; top: 10px; width: 16px; height: 16px; color: #94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
										</div>
										
										<!-- Dropdown -->
										<div x-show="showDropdown" class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
											<template x-if="loadingItems">
												<div class="p-3 text-center text-sm text-slate-500">Loading products...</div>
											</template>
											<template x-if="!loadingItems && items.length === 0">
												<div class="p-3 text-center text-sm text-slate-500">No products found.</div>
											</template>
											<template x-if="!loadingItems && items.length > 0">
												<div>
													<template x-for="item in items.filter(i => search === '' || i.title.toLowerCase().includes(search.toLowerCase()) || String(i.id).includes(search))" :key="item.id">
														<label class="flex items-center px-3 py-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0">
															<input type="checkbox" :value="String(item.id)" x-model="selectedProducts" @change="form.ids = selectedProducts.join(',')" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mr-3">
															<img x-show="item.image_url" :src="item.image_url" class="w-6 h-6 object-cover rounded mr-2 border border-slate-200">
															<div class="w-6 h-6 bg-slate-100 rounded mr-2 border border-slate-200 flex items-center justify-center" x-show="!item.image_url">
																<svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
															</div>
															<span class="text-sm text-slate-700 flex-1 truncate" x-text="item.title"></span>
															<span class="text-xs text-slate-400 ml-2">#<span x-text="item.id"></span></span>
														</label>
													</template>
												</div>
											</template>
										</div>
									</div>
									<div class="mt-3" x-show="selectedProducts.length > 0">
										<p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Selected Products:</p>
										<div class="flex flex-wrap gap-2">
											<template x-for="id in selectedProducts" :key="id">
												<span class="inline-flex items-center px-2 py-1 rounded bg-blue-50 text-blue-700 border border-blue-200 text-xs shadow-sm">
													<span class="truncate max-w-[150px]" x-text="(items.find(i => String(i.id) === String(id)) || {}).title || ('ID: ' + id)"></span>
													<button @click.prevent="selectedProducts = selectedProducts.filter(i => String(i) !== String(id)); form.ids = selectedProducts.join(',')" class="ml-1.5 text-blue-400 hover:text-blue-600 focus:outline-none">
														<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
													</button>
												</span>
											</template>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Pagination Tab -->
					<div x-show="activeModalTab === 'pagination'" class="space-y-6">
						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Total Products Limit</label>
								<input type="number" x-model="form.count" placeholder="Total number of items" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
								<p class="text-xs text-slate-500 mt-1">Leave empty for no limit.</p>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Products Per Page</label>
								<input type="number" x-model="form.posts_per_page" placeholder="e.g. 12" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							</div>
						</div>
						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Pagination Style</label>
								<select x-model="form.page_navi" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="standard">Standard Numbers (1, 2, 3)</option>
									<option value="loadmore">Load More Button</option>
									<option value="infinite">Infinite Scroll</option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Enable Skeleton Loading</label>
								<select x-model="form.loading_effect" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="yes">Yes</option>
									<option value="no">No</option>
								</select>
							</div>
						</div>
					</div>

					<!-- Carousel Tab -->
					<div x-show="activeModalTab === 'carousel' && form.sc_type === 'carousel'" class="space-y-6">
						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Slides to Show</label>
								<input type="number" x-model="form.slidesshow" placeholder="e.g. 3" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Infinite Loop</label>
								<select x-model="form.infinite" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="yes">Yes</option>
									<option value="no">No</option>
								</select>
							</div>
						</div>
						<div class="grid grid-cols-2 gap-6">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-1">Autoplay</label>
								<select x-model="form.autoplay" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
									<option value="yes">Yes</option>
									<option value="no">No</option>
								</select>
							</div>
							<div x-show="form.autoplay === 'yes'">
								<label class="block text-sm font-medium text-slate-700 mb-1">Autoplay Speed (ms)</label>
								<input type="number" x-model="form.autoplayspeed" placeholder="e.g. 3000" class="w-full border-slate-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
							</div>
						</div>
					</div>

				</div>

				<!-- Modal Footer -->
				<div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-between items-center">
					<div>
						<button @click="closeModal()" type="button" class="px-4 py-2 bg-white border border-slate-300 rounded-md shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200 transition-colors">
							Cancel
						</button>
					</div>
					<div class="flex space-x-3 items-center">
						<button x-show="activeModalTab !== (form.sc_type === 'carousel' ? 'carousel' : 'pagination')" @click="nextTab()" type="button" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white !bg-blue-600 hover:!bg-blue-700 focus:outline-none flex items-center transition-colors">
							Next Step
							<svg class="ml-2 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
						</button>
						<button x-show="activeModalTab === (form.sc_type === 'carousel' ? 'carousel' : 'pagination')" @click="saveDisplay()" :disabled="saving" type="button" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white !bg-indigo-600 hover:!bg-indigo-700 focus:outline-none flex items-center disabled:opacity-50 transition-colors">
							<template x-if="saving">
								<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
							</template>
							Save Display
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>

</div>

<script>
document.addEventListener('alpine:init', () => {
	Alpine.data('publishManager', () => ({
		displays: [],
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
		activeModalTab: 'layout',
		searchQuery: '',
		sortOrder: 'name_asc',
		form: {},
		selectedProducts: [],
		
		init() {
			this.$watch('activeTab', (val) => {
				if (val === 'publish' && this.displays.length === 0) {
					this.fetchDisplays();
				}
			});
			if (this.activeTab === 'publish') {
				this.fetchDisplays();
			}
		},

		get filteredDisplays() {
			let result = JSON.parse(JSON.stringify(this.displays || []));
			if (this.searchQuery.trim() !== '') {
				const sq = this.searchQuery.toLowerCase();
				result = result.filter(d => (d.name && d.name.toLowerCase().includes(sq)));
			}
			if (this.sortOrder === 'name_asc') {
				result.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
			} else {
				result.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
			}
			return result;
		},

		getTypeLabel(type) {
			const map = {
				'mn_group': 'Menu Groups',
				'grid': 'Grid',
				'list': 'List',
				'table': 'Table',
				'carousel': 'Carousel'
			};
			return map[type] || 'Menu Groups';
		},

		emptyForm() {
			return {
				id: '', name: '', sc_type: 'mn_group', sc_layout: 'list', 
				columns: '3', column_tablet: '2', column_mobile: '1',
				sc_heading: '', heading_align: 'left', img_size: 'medium', number_excerpt: '',
				menu_filter: 'yes', menu_pos: 'top', filter_style: 'text', active_filter: '', order_cat: '', hide_ftall: 'no',
				data_source_type: 'all', cat: [], tags: [], ids: '', featured: 'no', count: '', posts_per_page: '', page_navi: 'standard',
				slidesshow: '3', autoplay: 'yes', autoplayspeed: '3000', infinite: 'yes', loading_effect: 'yes'
			};
		},

		nextTab() {
			if(this.activeModalTab === 'layout') this.activeModalTab = 'query';
			else if(this.activeModalTab === 'query') this.activeModalTab = 'pagination';
			else if(this.activeModalTab === 'pagination' && this.form.sc_type === 'carousel') this.activeModalTab = 'carousel';
		},

		getAssignedText(disp) {
			if (disp.featured === 'yes') {
				return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-800">Featured Products</span>';
			}

			let catIds = [];
			if (Array.isArray(disp.cat)) {
				catIds = disp.cat;
			} else if (typeof disp.cat === 'string' && disp.cat.trim() !== '') {
				catIds = disp.cat.split(',').map(s => s.trim()).filter(s => s);
			}

			let tagIds = [];
			if (Array.isArray(disp.tags)) {
				tagIds = disp.tags;
			} else if (typeof disp.tags === 'string' && disp.tags.trim() !== '') {
				tagIds = disp.tags.split(',').map(s => s.trim()).filter(s => s);
			}

			let prodIds = [];
			if (typeof disp.ids === 'string' && disp.ids.trim() !== '') {
				prodIds = disp.ids.split(',').map(s => s.trim()).filter(s => s);
			}

			let parts = [];
			
			// Safe parent access helper
			const getParentData = () => {
				const wrap = document.querySelector('.o100-menu-maker-wrap');
				return wrap && wrap._x_dataStack ? wrap._x_dataStack[0] : null;
			};
			const pData = getParentData() || {};

			if (catIds.length > 0) {
				const names = catIds.map(id => {
					const cat = pData.categories ? pData.categories.find(c => String(c.id) === String(id)) : null;
					return cat && cat.name ? cat.name : 'Cat: ' + id;
				});
				let catStr = names.length <= 4 ? names.join(', ') : names.slice(0, 4).join(', ') + '...';
				parts.push('<div class="mb-1"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-blue-100 text-blue-800 mr-1">Categories</span><span class="text-[11px] text-slate-500">' + catStr + '</span></div>');
			}

			if (tagIds.length > 0) {
				const names = tagIds.map(id => {
					const tag = pData.tags ? pData.tags.find(t => String(t.id) === String(id)) : null;
					return tag && tag.name ? tag.name : 'Tag: ' + id;
				});
				let tagStr = names.length <= 4 ? names.join(', ') : names.slice(0, 4).join(', ') + '...';
				parts.push('<div class="mb-1"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-teal-100 text-teal-800 mr-1">Tags</span><span class="text-[11px] text-slate-500">' + tagStr + '</span></div>');
			}
			
			if (prodIds.length > 0) {
				let prodStr = prodIds.length <= 4 ? 'IDs: ' + prodIds.join(', ') : 'IDs: ' + prodIds.slice(0, 4).join(', ') + '...';
				parts.push('<div><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-purple-100 text-purple-800 mr-1">Products</span><span class="text-[11px] text-slate-500">' + prodStr + '</span></div>');
			}

			if (parts.length === 0) {
				return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-800">All Products</span>';
			}

			return '<div class="flex flex-col items-start">' + parts.join('') + '</div>';
		},

		async fetchDisplays() {
			this.loading = true;
			const data = await this.apiRequest('/displays', 'GET');
			if (data) {
				this.displays = data;
			}
			this.loading = false;
		},

		openModal(disp = null) {
			if (disp) {
				this.form = JSON.parse(JSON.stringify(disp));

				// Backwards compatibility for old values
				if (this.form.menu_filter === 'show' || this.form.menu_filter === '1' || this.form.menu_filter === true) {
					this.form.menu_filter = 'yes';
				} else if (this.form.menu_filter === 'hide' || this.form.menu_filter === '0' || this.form.menu_filter === false) {
					this.form.menu_filter = 'no';
				}

				if (!Array.isArray(this.form.cat) && this.form.cat) {
					this.form.cat = this.form.cat.split(',');
				} else if (!this.form.cat) {
					this.form.cat = [];
				}

				if (!Array.isArray(this.form.tags) && this.form.tags) {
					this.form.tags = this.form.tags.split(',');
				} else if (!this.form.tags) {
					this.form.tags = [];
				}

				if (this.form.ids && this.form.ids.trim() !== '') {
					this.form.data_source_type = 'products';
					this.selectedProducts = this.form.ids.split(',').map(s=>s.trim()).filter(s=>s);
				} else if (this.form.cat && this.form.cat.length > 0) {
					this.form.data_source_type = 'categories';
					this.selectedProducts = [];
				} else if (this.form.tags && this.form.tags.length > 0) {
					this.form.data_source_type = 'tags';
					this.selectedProducts = [];
				} else if (this.form.featured === 'yes') {
					this.form.data_source_type = 'featured';
					this.selectedProducts = [];
				} else {
					this.form.data_source_type = 'all';
					this.selectedProducts = [];
				}
			} else {
				this.form = this.emptyForm();
				this.selectedProducts = [];
			}
			this.activeModalTab = 'layout';
			this.modalOpen = true;
		},

		duplicateDisplay(disp) {
			let dup = JSON.parse(JSON.stringify(disp));
			dup.id = '';
			dup.name = (dup.name || 'Display') + ' (Copy)';
			this.openModal(dup);
		},

		closeModal() {
			this.modalOpen = false;
			this.resetTimeout = setTimeout(() => {
				this.form = this.emptyForm();
			}, 300);
		},

		async saveDisplay() {
			if (!this.form.name) {
				alert('Please enter a Display Name.');
				return;
			}

			// Clean up data_source_type
			if (this.form.data_source_type === 'all') {
				this.form.cat = []; this.form.tags = []; this.form.ids = ''; this.form.featured = 'no';
			} else if (this.form.data_source_type === 'categories') {
				this.form.tags = []; this.form.ids = ''; this.form.featured = 'no';
			} else if (this.form.data_source_type === 'tags') {
				this.form.cat = []; this.form.ids = ''; this.form.featured = 'no';
			} else if (this.form.data_source_type === 'products') {
				this.form.cat = []; this.form.tags = []; this.form.featured = 'no';
				this.form.ids = this.selectedProducts.join(',');
			} else if (this.form.data_source_type === 'featured') {
				this.form.cat = []; this.form.tags = []; this.form.ids = ''; this.form.featured = 'yes';
			}

			this.saving = true;
			// Generate ID if new
			if (!this.form.id) {
				this.form.id = 'o100_' + Math.random().toString(36).substr(2, 9);
			}

			// Clean up array for saving - the original stored arrays as strings often or arrays. We'll use arrays and the PHP backend saves them properly.
			const data = await this.apiRequest('/displays', 'POST', this.form);
			if (data && data.success) {
				const existingIndex = this.displays.findIndex(d => d.id === data.display.id);
				if (existingIndex > -1) {
					this.displays[existingIndex] = data.display;
				} else {
					this.displays.push(data.display);
				}
				this.closeModal();
			}
			this.saving = false;
		},

		async deleteDisplay(id) {
			if (!await window.o100Confirm('Delete Display', 'Are you sure you want to delete this display? The shortcode will stop rendering menus on any page it was placed.')) return;
			const data = await this.apiRequest('/displays?id=' + id, 'DELETE');
			if (data && data.success) {
				this.displays = this.displays.filter(d => d.id !== id);
			}
		},

		copyShortcode(id) {
			const sc = '[o100_menu id="' + id + '"]';
			navigator.clipboard.writeText(sc).then(() => {
				alert('Shortcode copied to clipboard!');
			});
		}
	}));
});
</script>
