<div class="o100-mm-categories space-y-6">
	<!-- Top Bar -->
	<div class="flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 bg-white p-4 rounded-lg shadow-sm border border-slate-200">
		<div>
			<h2 class="text-xl font-semibold text-slate-800"><?php esc_html_e( 'Categories', 'order100' ); ?></h2>
			<p class="text-sm text-slate-500 mt-1 mb-0"><?php esc_html_e( 'Organize your menu by creating sections (e.g., Appetizers, Main Courses).', 'order100' ); ?></p>
		</div>
		<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto">
			<div class="relative min-w-[160px]">
				<select x-model="filters.catSort" class="o100-select-unified text-sm shadow-sm w-full">
					<option value="order_asc"><?php esc_html_e( 'Order: Low to High', 'order100' ); ?></option>
					<option value="order_desc"><?php esc_html_e( 'Order: High to Low', 'order100' ); ?></option>
					<option value="name_asc"><?php esc_html_e( 'Name: A to Z', 'order100' ); ?></option>
					<option value="name_desc"><?php esc_html_e( 'Name: Z to A', 'order100' ); ?></option>
				</select>
			</div>
			<div class="relative flex-1 sm:flex-initial min-w-[200px]">
				<input type="search" x-model="filters.catSearch" placeholder="<?php esc_attr_e( 'Search categories...', 'order100' ); ?>" class="o100-search-unified text-sm w-full !pl-10 shadow-sm py-2">
				<svg style="width: 16px; height: 16px;" class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
			</div>
			<button @click="openCategoryModal()" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none whitespace-nowrap">
				<svg style="width: 16px; height: 16px;" class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
				<?php esc_html_e( 'Add Category', 'order100' ); ?>
			</button>
		</div>
	</div>

	<!-- Loading State -->
	<div x-show="loadingCategories" class="p-10 text-center text-slate-500 bg-white rounded-lg border border-slate-200">
		<svg style="width: 32px; height: 32px;" class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
			<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
			<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
		</svg>
		<?php esc_html_e( 'Loading categories...', 'order100' ); ?>
	</div>

	<!-- Empty State -->
	<div x-show="!loadingCategories && filteredCategories.length === 0" class="p-12 text-center bg-slate-50 border border-dashed border-slate-300 rounded-lg text-slate-500">
		<svg style="width: 48px; height: 48px;" class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
		<p class="text-lg font-medium text-slate-900 mb-1"><?php esc_html_e( 'No categories found', 'order100' ); ?></p>
		<p><?php esc_html_e( 'Create your first category to get started.', 'order100' ); ?></p>
	</div>

	<!-- List View Container -->
	<div x-show="!loadingCategories && categories.length > 0" class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-x-auto overflow-y-hidden o100-table-responsive-wrapper">
		<table class="min-w-full divide-y divide-slate-200">
			<thead class="bg-slate-50">
				<tr class="divide-x divide-slate-200">
					<th scope="col" class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
						<div class="flex items-center gap-2">
							<span class="w-5"></span> <!-- Spacer for drag handle alignment -->
							<span><?php esc_html_e( 'Category Name', 'order100' ); ?></span>
						</div>
					</th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-24"><?php esc_html_e( 'Order', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Branches', 'order100' ); ?></th>
					<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Items', 'order100' ); ?></th>
					<th scope="col" class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Actions', 'order100' ); ?></th>
				</tr>
			</thead>
			<tbody id="o100-cat-sortable" class="bg-white divide-y divide-slate-200">
				<template x-for="cat in filteredCategories" :key="cat.id">
					<tr :data-id="cat.id" class="o100-table-row hover:bg-slate-50 transition-colors duration-150 divide-x divide-slate-200">
						<td class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-4">
							<div class="flex items-center gap-2">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 flex-shrink-0 text-slate-300 o100-drag-handle cursor-grab hover:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
								<div class="flex items-center space-x-3">
									<div class="flex-shrink-0 h-10 w-10 rounded bg-slate-100 flex items-center justify-center border border-slate-200 overflow-hidden">
										<template x-if="cat.icon_type === 'image' && cat.image_url">
											<img :src="cat.image_url" class="h-10 w-10 object-cover">
										</template>
										<template x-if="cat.icon_type === 'icon' && cat.icon && !cat.icon.startsWith('svg:')">
											<i :class="cat.icon" class="text-xl text-slate-500"></i>
										</template>
										<template x-if="cat.icon_type === 'icon' && cat.icon && cat.icon.startsWith('svg:')">
											<div class="w-6 h-6 text-slate-500" x-html="cat.icon_content"></div>
										</template>
										<template x-if="cat.icon_type === 'none' || (!cat.image_url && !cat.icon)">
											<svg style="width: 24px; height: 24px;" class="h-6 w-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
										</template>
									</div>
									<div>
										<div class="text-sm font-semibold text-slate-900" x-text="cat.name"></div>
									</div>
								</div>
							</div>
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap">
							<input type="number" x-model.lazy="cat.order" @change="quickSaveCategoryOrder(cat)" class="o100-hide-spin-buttons w-24 px-3 py-1.5 text-sm border border-slate-300 rounded-md text-center shadow-sm">
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap">
							<template x-if="cat.branches.includes('all')">
								<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800"><?php esc_html_e( 'All Branches', 'order100' ); ?></span>
							</template>
							<template x-if="!cat.branches.includes('all')">
								<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800" x-text="cat.branches.length + ' <?php esc_attr_e( 'Branches', 'order100' ); ?>'"></span>
							</template>
						</td>
						<td class="px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-sm text-slate-500" x-text="cat.count"></td>
						<td class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
							<div class="flex items-center justify-center gap-2">
							<button @click.stop="openCategoryModal(cat)" class="o100-action-icon-btn edit" data-tooltip="<?php esc_attr_e( 'Edit', 'order100' ); ?>">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
							</button>
							<button @click.stop="duplicateCategory(cat)" class="o100-action-icon-btn duplicate" data-tooltip="<?php esc_attr_e( 'Duplicate', 'order100' ); ?>">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
							</button>
							<button @click.stop="deleteCategory(cat.id)" class="o100-action-icon-btn o100-delete-btn" data-tooltip="<?php esc_attr_e( 'Delete', 'order100' ); ?>">
								<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
							</button>
							</div>
						</td>
					</tr>
				</template>
			</tbody>
		</table>
	</div>

	<!-- Category Edit Modal Overlay -->
	<div x-show="modals.category.open" style="display: none;" class="fixed inset-0 z-[99999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
		<div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
			<!-- Background backdrop -->
			<div x-show="modals.category.open" class="fixed inset-0 bg-slate-900 bg-opacity-50 transition-opacity" aria-hidden="true" @click="modals.category.open = false"></div>
			<!-- Centering trick -->
			<span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
			
			<!-- Modal Panel -->
			<div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full">
				<!-- Header -->
				<div class="bg-slate-50 px-4 py-3 sm:px-6 border-b border-slate-200 flex justify-between items-center">
					<h3 class="text-lg leading-6 font-medium text-slate-900" x-text="modals.category.data.id ? '<?php esc_attr_e( 'Edit Category', 'order100' ); ?>' : '<?php esc_attr_e( 'Add Category', 'order100' ); ?>'"></h3>
					<button @click="modals.category.open = false" class="text-slate-400 hover:text-slate-500 focus:outline-none">
						<svg style="width: 24px; height: 24px;" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
					</button>
				</div>
				<!-- Body -->
				<div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[70vh] overflow-y-auto">
					
					<div class="flex gap-4 mb-4">
						<div class="flex-grow">
							<label class="block text-sm font-medium text-slate-700 mb-1"><?php esc_html_e( 'Category Name', 'order100' ); ?> <span class="text-red-500">*</span></label>
							<input type="text" x-model="modals.category.data.name" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-slate-300 rounded-md">
						</div>
						<div class="w-1/4">
							<label class="block text-sm font-medium text-slate-700 mb-1"><?php esc_html_e( 'Display Order', 'order100' ); ?></label>
							<input type="number" x-model="modals.category.data.order" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-slate-300 rounded-md">
						</div>
					</div>

					<div class="mb-4">
						<label class="block text-sm font-medium text-slate-700 mb-1"><?php esc_html_e( 'Description (Optional)', 'order100' ); ?></label>
						<textarea x-model="modals.category.data.description" rows="2" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-slate-300 rounded-md"></textarea>
					</div>

					<!-- Visual Icon -->
					<div class="mb-5 bg-slate-50 p-4 border border-slate-200 rounded-lg">
						<label class="block text-sm font-medium text-slate-700 mb-3"><?php esc_html_e( 'Visual Icon', 'order100' ); ?></label>
						<div class="flex space-x-2 mb-4">
							<button type="button" @click="modals.category.data.icon_type = 'none'" :style="modals.category.data.icon_type === 'none' ? 'background-color: #1e293b !important; color: #ffffff !important; border-color: #1e293b !important; background-image: none !important;' : 'background-color: #ffffff !important; color: #334155 !important; border-color: #cbd5e1 !important; background-image: none !important;'" class="flex-1 py-2 px-4 border rounded-md text-sm font-medium transition-colors">
								<?php esc_html_e( 'None', 'order100' ); ?>
							</button>
							<button type="button" @click="modals.category.data.icon_type = 'icon'" :style="modals.category.data.icon_type === 'icon' ? 'background-color: #1e293b !important; color: #ffffff !important; border-color: #1e293b !important; background-image: none !important;' : 'background-color: #ffffff !important; color: #334155 !important; border-color: #cbd5e1 !important; background-image: none !important;'" class="flex-1 py-2 px-4 border rounded-md text-sm font-medium transition-colors">
								<?php esc_html_e( 'Icon Class', 'order100' ); ?>
							</button>
							<button type="button" @click="modals.category.data.icon_type = 'image'" :style="modals.category.data.icon_type === 'image' ? 'background-color: #1e293b !important; color: #ffffff !important; border-color: #1e293b !important; background-image: none !important;' : 'background-color: #ffffff !important; color: #334155 !important; border-color: #cbd5e1 !important; background-image: none !important;'" class="flex-1 py-2 px-4 border rounded-md text-sm font-medium transition-colors">
								<?php esc_html_e( 'Custom Image', 'order100' ); ?>
							</button>
						</div>

						<div x-show="modals.category.data.icon_type === 'icon'" class="bg-white p-4 border border-slate-200 rounded-md">
							<div class="flex items-center space-x-4 mb-4">
								<div class="text-sm font-medium text-slate-700"><?php esc_html_e( 'Selected Icon:', 'order100' ); ?></div>
								<div class="h-12 w-12 bg-slate-100 rounded-md border border-slate-200 flex items-center justify-center">
									<template x-if="modals.category.data.icon && modals.category.data.icon.startsWith('svg:')">
										<div class="w-8 h-8 text-blue-600" x-html="modals.category.data.icon_content"></div>
									</template>
									<template x-if="!modals.category.data.icon">
										<div class="text-xs text-slate-400"><?php esc_html_e( 'None', 'order100' ); ?></div>
									</template>
								</div>
							</div>
							
							<div class="border-t border-slate-100 pt-4">
								<div class="flex justify-between items-center mb-3">
									<div class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Icon Library', 'order100' ); ?></div>
									<input type="text" x-model="iconSearch" @input="iconLimit = 100" placeholder="<?php esc_attr_e( 'Search icons...', 'order100' ); ?>" class="text-sm py-1.5 px-3 border border-slate-300 rounded-md w-48 focus:ring-blue-500 focus:border-blue-500 shadow-sm">
								</div>
								
								<div class="grid grid-cols-8 gap-2 max-h-56 overflow-y-auto p-2 bg-slate-50 border border-slate-200 rounded-md shadow-inner">
									<!-- Custom SVGs -->
									<template x-for="iconObj in filteredCustomIcons" :key="iconObj.id">
										<button @click="modals.category.data.icon = iconObj.id; modals.category.data.icon_content = iconObj.content" type="button" class="w-10 h-10 rounded-md border bg-white flex items-center justify-center hover:border-blue-400 hover:shadow-md transition-all" :class="modals.category.data.icon === iconObj.id ? 'border-blue-500 ring-2 ring-blue-200 text-blue-600 shadow-sm' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" :title="iconObj.name">
											<div class="w-6 h-6" x-html="iconObj.content"></div>
										</button>
									</template>
									
									<template x-if="filteredCustomIcons.length === 0">
										<div class="col-span-8 py-6 text-center text-sm text-slate-400"><?php esc_html_e( 'No icons found.', 'order100' ); ?></div>
									</template>

									<template x-if="hasMoreIcons">
										<div class="col-span-8 py-2 text-center">
											<button type="button" @click="iconLimit += 100" class="text-xs font-medium text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-full transition-colors border border-blue-200 shadow-sm">
												<?php esc_html_e( 'Load More Icons', 'order100' ); ?>
											</button>
										</div>
									</template>
								</div>
							</div>
						</div>

						<div x-show="modals.category.data.icon_type === 'image'" class="bg-white p-4 border border-slate-200 rounded-md flex items-center space-x-4">
							<div class="h-16 w-16 bg-slate-100 rounded border border-dashed border-slate-300 flex items-center justify-center overflow-hidden">
								<template x-if="modals.category.data.image_url">
									<img :src="modals.category.data.image_url" class="h-full w-full object-cover">
								</template>
								<template x-if="!modals.category.data.image_url">
									<svg style="width: 32px; height: 32px;" class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
								</template>
							</div>
							<div class="flex flex-col space-y-2">
								<button @click="openMediaUploader('category')" type="button" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-xs font-medium rounded text-slate-700 bg-white hover:bg-slate-50 focus:outline-none"><?php esc_html_e( 'Select Image', 'order100' ); ?></button>
								<template x-if="modals.category.data.image_url">
									<button @click="modals.category.data.image_url = ''; modals.category.data.image_id = 0;" type="button" class="inline-flex items-center px-3 py-1.5 border border-red-300 shadow-sm text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 focus:outline-none"><?php esc_html_e( 'Remove', 'order100' ); ?></button>
								</template>
							</div>
						</div>
					</div>

					<!-- Branches Selector -->
					<div class="mb-2 bg-slate-50 p-4 border border-slate-200 rounded-lg">
						<label class="block text-sm font-medium text-slate-700 mb-3"><?php esc_html_e( 'Available at Branches', 'order100' ); ?></label>
						
						<template x-if="branches && branches.length > 0">
							<div class="flex flex-wrap gap-2">
								<label class="inline-flex items-center cursor-pointer px-3 py-1.5 rounded-md border text-sm transition-colors" :class="modals.category.data.branches && modals.category.data.branches.includes('all') ? 'bg-blue-50 border-blue-500 text-blue-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'">
									<input type="checkbox" class="sr-only" :checked="modals.category.data.branches && modals.category.data.branches.includes('all')" @change="toggleBranchSelection('all')">
									<span><?php esc_html_e( 'All Branches', 'order100' ); ?></span>
								</label>
								
								<template x-for="branch in branches" :key="branch.id">
									<label class="inline-flex items-center cursor-pointer px-3 py-1.5 rounded-md border text-sm transition-colors" :class="modals.category.data.branches && !modals.category.data.branches.includes('all') && modals.category.data.branches.includes(String(branch.id)) ? 'bg-blue-50 border-blue-500 text-blue-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'">
										<input type="checkbox" class="sr-only" :checked="modals.category.data.branches && !modals.category.data.branches.includes('all') && modals.category.data.branches.includes(String(branch.id))" @change="toggleBranchSelection(branch.id)">
										<span x-text="branch.name"></span>
									</label>
								</template>
							</div>
						</template>

						<template x-if="!branches || branches.length === 0">
							<div class="text-sm text-slate-500 bg-white p-3 rounded border border-slate-200">
								<?php esc_html_e( 'No specific branches configured. This category is available globally.', 'order100' ); ?>
							</div>
						</template>
					</div>

				</div>
				<!-- Footer -->
				<div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-slate-200">
					<button @click="saveCategory()" :disabled="modals.category.saving" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50" style="background-color: #F59322 !important; color: #ffffff !important;">
						<svg style="width: 16px; height: 16px;" x-show="modals.category.saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
						<span x-text="modals.category.saving ? '<?php esc_attr_e( 'Saving...', 'order100' ); ?>' : '<?php esc_attr_e( 'Save Category', 'order100' ); ?>'"></span>
					</button>
					<button @click="modals.category.open = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
						<?php esc_html_e( 'Cancel', 'order100' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
