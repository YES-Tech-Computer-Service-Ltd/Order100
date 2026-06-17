<div class="o100-mm-items space-y-4">
	
	<!-- Top Header: Title & Actions -->
	<div class="flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-slate-200">
		<div class="flex items-center space-x-2">
			<div>
				<h2 class="text-xl font-semibold text-slate-800"><?php esc_html_e( 'Menu Items', 'order100' ); ?> <span class="text-sm text-slate-500 font-normal" x-text="`(${filteredItems.length})`"></span></h2>
				<p class="text-sm text-slate-500 mt-1 mb-0"><?php esc_html_e( 'Manage your catalog of dishes, drinks, and products.', 'order100' ); ?></p>
			</div>
		</div>
		<div class="flex space-x-2 mt-4 sm:mt-0 items-center">
			<!-- View Toggles -->
			<div class="flex bg-slate-200/70 p-1 rounded-lg border border-slate-200 mr-2 items-center space-x-1">
				<button @click="viewMode = 'grid'" type="button" :class="{ 'bg-white shadow-sm ring-1 ring-black/5 text-blue-600': viewMode === 'grid', 'text-slate-500 hover:text-slate-700': viewMode !== 'grid' }" class="p-1.5 rounded-md focus:outline-none transition-all duration-200">
					<svg style="width: 18px; height: 18px;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
				</button>
				<button @click="viewMode = 'list'" type="button" :class="{ 'bg-white shadow-sm ring-1 ring-black/5 text-blue-600': viewMode === 'list', 'text-slate-500 hover:text-slate-700': viewMode !== 'list' }" class="p-1.5 rounded-md focus:outline-none transition-all duration-200">
					<svg style="width: 18px; height: 18px;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
				</button>
			</div>
			
			<button @click="openItemModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none">
				<svg style="width: 16px; height: 16px;" class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
				<?php esc_html_e( 'Add Item', 'order100' ); ?>
			</button>
		</div>
	</div>

	<!-- Main Table Container -->
	<div class="bg-white rounded-lg shadow-sm border border-slate-200">
		
		<!-- Filter Toolbar -->
		<div class="p-4 border-b border-slate-200" x-data="{ showFilters: false, showSort: false }">
			
			<!-- Main Bar: Search + Filter + Sort -->
			<div class="flex items-center gap-3">
				<!-- Search -->
				<div class="relative flex-grow max-w-md">
					<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
						<svg style="width: 16px; height: 16px;" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
					</div>
					<input type="search" x-model="filters.search" placeholder="<?php esc_attr_e( 'Search by name, ID...', 'order100' ); ?>" class="o100-search-unified focus:ring-blue-500 focus:border-blue-500 block w-full !pl-10 sm:text-sm border-slate-300 rounded-md py-2">
				</div>

				<!-- Filter Button -->
				<button @click="showFilters = !showFilters; showSort = false" type="button" 
					:class="showFilters ? 'bg-blue-50 border-blue-300 text-blue-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
					class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md transition-colors whitespace-nowrap">
					<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
					Filter
					<span x-show="filters.category !== '0' || filters.branch !== 'all' || filters.stock !== 'all' || filters.promo !== 'all' || filters.rules !== 'all'" class="ml-1.5 w-2 h-2 rounded-full bg-blue-500 inline-block" style="display:none;"></span>
				</button>

				<!-- Sort Button -->
				<div class="relative" @click.outside="showSort = false">
					<button @click="showSort = !showSort; showFilters = false" type="button" class="inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium rounded-md text-slate-700 hover:bg-slate-50 transition-colors whitespace-nowrap">
						<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
						<span x-text="'Sort by ' + {date: 'Date', name: 'Name', price: 'Price', stock: 'Stock', rules: 'Rules'}[filters.sortField || 'date']">Sort by Date</span>
						<svg style="width: 12px; height: 12px;" class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
					</button>
					<!-- Sort Dropdown Panel -->
					<div x-show="showSort" x-transition class="absolute right-0 top-full mt-1 w-52 bg-white rounded-lg shadow-xl border border-slate-200 py-2 z-50">
						<div class="px-3 py-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Sort By</div>
						<template x-for="opt in [{v:'date',l:'Last Updated'},{v:'name',l:'Name'},{v:'price',l:'Price'},{v:'stock',l:'Stock Status'},{v:'rules',l:'Selling Rules'}]" :key="opt.v">
							<button type="button" @click="filters.sortField = opt.v" 
								:class="filters.sortField === opt.v ? 'text-blue-700 bg-blue-50 font-semibold' : 'text-slate-700 hover:bg-slate-50'"
								class="w-full text-left px-4 py-2 text-sm flex items-center justify-between transition-colors">
								<span x-text="opt.l"></span>
								<svg x-show="filters.sortField === opt.v" style="width: 14px; height: 14px;" class="text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
							</button>
						</template>
						<div class="border-t border-slate-100 my-1.5"></div>
						<div class="flex items-center gap-1 px-3 py-1">
							<button type="button" @click="filters.sortDir = 'asc'" 
								:class="filters.sortDir === 'asc' ? 'bg-blue-100 border-blue-400 text-blue-700' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
								class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors">
								Asc <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
							</button>
							<button type="button" @click="filters.sortDir = 'desc'" 
								:class="filters.sortDir === 'desc' ? 'bg-blue-100 border-blue-400 text-blue-700' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
								class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors">
								Desc <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Expandable Filter Row -->
			<div x-show="showFilters" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="mt-3 pt-3 border-t border-slate-100" style="display: none;">
				<div class="flex flex-wrap gap-3 items-center">
					<select x-model="filters.category" class="o100-select-unified block w-40 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
						<option value="0"><?php esc_html_e( 'All Categories', 'order100' ); ?></option>
						<template x-for="cat in categories" :key="cat.id">
							<option :value="cat.id" x-text="cat.name"></option>
						</template>
					</select>

					<select x-model="filters.branch" class="o100-select-unified block w-40 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
						<option value="all"><?php esc_html_e( 'All Branches', 'order100' ); ?></option>
						<template x-for="b in branches" :key="b.id">
							<option :value="b.id" x-text="b.name"></option>
						</template>
					</select>

					<select x-model="filters.stock" class="o100-select-unified block w-36 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" style="width: 140px !important; min-width: 140px !important; max-width: 140px !important;">
						<option value="all"><?php esc_html_e( 'All Stock', 'order100' ); ?></option>
						<option value="instock"><?php esc_html_e( 'In Stock', 'order100' ); ?></option>
						<option value="outofstock"><?php esc_html_e( 'Sold Out', 'order100' ); ?></option>
					</select>

					<select x-model="filters.promo" class="o100-select-unified block w-40 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
						<option value="all"><?php esc_html_e( 'All Promotions', 'order100' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Active Promos', 'order100' ); ?></option>
						<option value="no"><?php esc_html_e( 'No Promos', 'order100' ); ?></option>
					</select>

					<select x-model="filters.rules" class="o100-select-unified block w-44 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
						<option value="all"><?php esc_html_e( 'All Rules', 'order100' ); ?></option>
						<option value="has_rules"><?php esc_html_e( 'Has Time/Date Rules', 'order100' ); ?></option>
						<option value="no_rules"><?php esc_html_e( 'No Rules', 'order100' ); ?></option>
					</select>

					<button x-show="filters.category !== '0' || filters.branch !== 'all' || filters.stock !== 'all' || filters.promo !== 'all' || filters.rules !== 'all'" @click="filters.category = '0'; filters.branch = 'all'; filters.stock = 'all'; filters.promo = 'all'; filters.rules = 'all'" type="button" class="ml-auto px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 rounded-md border border-red-200 transition-colors" style="display: none;">Clear All</button>
				</div>
			</div>
		</div>


		<!-- Bulk Actions Toolbar (List View Only) -->
		<div x-show="!loadingItems && viewMode === 'list' && selectedItems.length > 0" class="flex justify-between items-center bg-blue-50 p-3 border-b border-blue-200" style="display: none;">
			<div class="text-sm text-blue-800 font-medium px-2">
				<span x-text="selectedItems.length"></span> <?php esc_html_e( 'items selected', 'order100' ); ?>
			</div>
			<div class="flex gap-2">
				<button @click="bulkEditItems('instock')" class="inline-flex items-center px-3 py-1.5 border border-slate-300 text-xs font-medium rounded text-slate-700 bg-white hover:bg-slate-50 shadow-sm">
					<span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> <?php esc_html_e( 'Mark In Stock', 'order100' ); ?>
				</button>
				<button @click="bulkEditItems('outofstock')" class="inline-flex items-center px-3 py-1.5 border border-slate-300 text-xs font-medium rounded text-slate-700 bg-white hover:bg-slate-50 shadow-sm">
					<span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span> <?php esc_html_e( 'Mark Sold Out', 'order100' ); ?>
				</button>
				<button @click="bulkEditItems('delete')" class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded text-red-700 bg-white hover:bg-red-50 shadow-sm">
					<svg style="width: 16px; height: 16px;" class="-ml-0.5 mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
					<?php esc_html_e( 'Delete', 'order100' ); ?>
				</button>
			</div>
		</div>

		<!-- Content Area (Grid or List) -->
		<div class="p-4 bg-slate-50 rounded-b-lg">
			
			<!-- Loading State -->
			<div x-show="loadingItems" class="py-10 text-center text-slate-500">
				<svg style="width: 32px; height: 32px;" class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
					<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
					<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
				</svg>
				<?php esc_html_e( 'Loading items...', 'order100' ); ?>
			</div>

			<!-- Empty State -->
			<div x-show="!loadingItems && filteredItems.length === 0" class="py-12 text-center bg-white border border-dashed border-slate-300 rounded-lg text-slate-500">
				<svg style="width: 48px; height: 48px;" class="mx-auto h-12 w-12 text-slate-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
				<p class="text-lg font-medium text-slate-900 mb-1"><?php esc_html_e( 'No items found', 'order100' ); ?></p>
				<p><?php esc_html_e( 'Create items or adjust your filters.', 'order100' ); ?></p>
			</div>

			<!-- Grid View -->
			<template x-if="viewMode === 'grid'">
				<div x-show="!loadingItems && filteredItems.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
					<template x-for="item in paginatedItems" :key="item.id + '-' + currentPage">
						<div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow relative flex flex-col group">
							<div class="w-full h-48 bg-slate-100 relative">
								<template x-if="item.image_url">
									<img :src="item.image_url" class="object-cover w-full h-full" :class="item.stock_status === 'outofstock' ? 'opacity-50 grayscale' : ''">
								</template>
								<template x-if="!item.image_url">
									<div class="flex items-center justify-center w-full h-full bg-slate-100">
										<svg style="width: 40px; height: 40px;" class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
									</div>
								</template>
								
								<!-- Status Badge overlay -->
								<template x-if="item.stock_status === 'outofstock'">
									<div class="absolute top-2 right-2 bg-red-100 text-red-800 text-xs px-2 py-1 rounded font-semibold border border-red-200 shadow-sm z-20">
										<?php esc_html_e( 'Sold Out', 'order100' ); ?>
									</div>
								</template>

								<!-- Promotion Badges -->
								<template x-if="item.promotions && item.promotions.length > 0">
									<div class="absolute top-2 left-2 flex flex-row items-center gap-1 z-10 pointer-events-none max-w-[85%]">
										<span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-bold bg-orange-500/90 backdrop-blur-sm text-white shadow-sm border border-orange-400/50 max-w-full">
											<svg style="width: 12px; height: 12px; flex-shrink: 0;" class="mr-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
											<span class="truncate" style="max-width: 110px;" x-text="item.promotions[0]"></span>
										</span>
										</span>
										<template x-if="item.promotions.length > 1">
											<span class="flex items-center justify-center w-5 h-5 rounded-full text-[9px] font-bold bg-slate-900/80 backdrop-blur-sm text-white shadow-sm flex-shrink-0" x-text="'+' + (item.promotions.length - 1)"></span>
										</template>
									</div>
								</template>

								<!-- Categories Overlay at Bottom -->
								<div class="absolute bottom-2 left-2 flex flex-wrap gap-1 max-w-[90%]" x-html="renderCategoryBadges(item.categories, false)">
								</div>
							</div>
							
							<div class="p-3 px-4 flex flex-col flex-1 pb-10 relative">
								<div class="mb-1.5">
									<h3 class="text-sm font-semibold text-slate-900 leading-tight line-clamp-2 mb-1.5" x-text="item.title" :title="item.title"></h3>
									<div class="text-xs font-bold whitespace-nowrap bg-slate-50 px-2 py-0.5 rounded border border-slate-100 inline-flex items-center">
										<!-- Price Display Logic -->
										<template x-if="item.sale_price">
											<div class="flex items-center gap-1.5">
												<del class="text-[10px] text-slate-400 font-medium" x-text="'$' + item.regular_price"></del>
												<span class="text-red-600" x-text="'$' + item.sale_price"></span>
											</div>
										</template>
										<template x-if="!item.sale_price">
											<span class="text-slate-900" x-text="'$' + item.regular_price"></span>
										</template>
									</div>
								</div>
								
								<div class="text-xs text-slate-500 line-clamp-2 mt-1 mb-1 leading-relaxed" x-show="item.excerpt" x-text="item.excerpt"></div>
								
								<!-- Large Bottom Action Buttons -->
								<div class="absolute bottom-0 left-0 w-full flex border-t border-slate-100 bg-slate-50">
									<button @click="openItemModal(item)" class="flex-1 py-2.5 text-xs font-medium text-slate-600 hover:text-blue-600 hover:bg-blue-50 border-r border-slate-200 transition-colors flex justify-center items-center">
										<svg style="width: 16px; height: 16px;" class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
										<?php esc_html_e( 'Edit', 'order100' ); ?>
									</button>
									<button @click="duplicateItem(item)" class="flex-1 py-2.5 text-xs font-medium text-slate-600 hover:text-indigo-600 hover:bg-indigo-50 border-r border-slate-200 transition-colors flex justify-center items-center">
										<svg style="width: 16px; height: 16px;" class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
										<?php esc_html_e( 'Dup', 'order100' ); ?>
									</button>
									<button @click="deleteItem(item.id)" class="flex-1 py-2.5 text-xs font-medium text-slate-600 hover:text-red-600 hover:bg-red-50 transition-colors flex justify-center items-center">
										<svg style="width: 16px; height: 16px;" class="mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
										<?php esc_html_e( 'Delete', 'order100' ); ?>
									</button>
								</div>
							</div>
						</div>
					</template>
				</div>
			</template>

			<!-- List View -->
			<template x-if="viewMode === 'list'">
				<div x-show="!loadingItems && filteredItems.length > 0" class="overflow-x-auto overflow-y-hidden rounded-lg border border-slate-200 bg-white shadow-sm o100-table-responsive-wrapper">
					<table class="min-w-full divide-y divide-slate-200">
					<thead class="bg-slate-50">
					<tr class="divide-x divide-slate-200">
						<th scope="col" class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
							<div class="flex items-center gap-4">
								<input type="checkbox" :checked="allSelected" @change="toggleSelectAll($event.target.checked)" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
								<span><?php esc_html_e( 'Item', 'order100' ); ?></span>
							</div>
						</th>
						<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Category', 'order100' ); ?></th>
						<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Price', 'order100' ); ?></th>
						<th scope="col" class="px-3 sm:px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Status', 'order100' ); ?></th>
						<th scope="col" class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-3 text-center text-xs font-medium text-slate-500 uppercase tracking-wider"><?php esc_html_e( 'Actions', 'order100' ); ?></th>
					</tr>
				</thead>
				<tbody class="bg-white divide-y divide-slate-200">
					<template x-for="item in paginatedItems" :key="item.id + '-' + currentPage">
						<tr class="o100-table-row transition-colors duration-150 divide-x divide-slate-200" :class="selectedItems.includes(item.id) ? 'bg-blue-50' : ''">
							<td class="o100-col-fixed-left px-3 sm:px-4 md:px-6 py-4">
								<div class="flex items-center gap-4">
									<input type="checkbox" :value="item.id" x-model="selectedItems" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
									<div class="flex items-center">
										<div class="flex-shrink-0 h-10 w-10 bg-slate-100 rounded border border-slate-200 overflow-hidden mr-4">
											<template x-if="item.image_url">
												<img :src="item.image_url" class="h-10 w-10 object-cover" :class="item.stock_status === 'outofstock' ? 'opacity-50 grayscale' : ''">
											</template>
											<template x-if="!item.image_url">
												<div class="h-full w-full flex items-center justify-center">
													<svg style="width: 24px; height: 24px;" class="h-6 w-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
												</div>
											</template>
										</div>
										<div class="text-sm font-semibold text-slate-900" x-text="item.title"></div>
									</div>
								</div>
							</td>
							<td class="px-3 sm:px-4 md:px-6 py-4">
								<div class="flex flex-wrap gap-1 max-w-full" x-html="renderCategoryBadges(item.categories, true)">
								</div>
							</td>
							<td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900">
								<template x-if="item.sale_price">
									<div class="flex items-center gap-1.5">
										<del class="text-[11px] text-slate-400 font-medium" x-text="'$' + item.regular_price"></del>
										<span class="text-red-600" x-text="'$' + item.sale_price"></span>
									</div>
								</template>
								<template x-if="!item.sale_price">
									<span class="text-slate-900" x-text="'$' + item.regular_price"></span>
								</template>
							</td>
							<td class="px-6 py-4 whitespace-nowrap">
								<template x-if="item.stock_status === 'instock'">
									<span class="inline-flex items-center text-sm font-medium text-emerald-600">
										<span class="w-2 h-2 rounded-full bg-emerald-500 mr-2"></span> <?php esc_html_e( 'In Stock', 'order100' ); ?>
									</span>
								</template>
								<template x-if="item.stock_status === 'outofstock'">
									<span class="inline-flex items-center text-sm font-medium text-red-600">
										<span class="w-2 h-2 rounded-full bg-red-500 mr-2"></span> <?php esc_html_e( 'Sold Out', 'order100' ); ?>
									</span>
								</template>
							</td>
							<td class="o100-col-fixed-right px-3 sm:px-4 md:px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
							<div class="flex items-center justify-center gap-2">
								<button @click="openItemModal(item)" class="o100-action-icon-btn edit" data-tooltip="<?php esc_attr_e( 'Edit', 'order100' ); ?>">
									<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
								</button>
								<button @click="duplicateItem(item)" class="o100-action-icon-btn duplicate" data-tooltip="<?php esc_attr_e( 'Duplicate', 'order100' ); ?>">
									<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
								</button>
								<button @click="deleteItem(item.id)" class="o100-action-icon-btn o100-delete-btn" data-tooltip="<?php esc_attr_e( 'Delete', 'order100' ); ?>">
									<svg style="width: 20px; height: 20px;" class="w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
								</button>
							</div>
							</td>
						</tr>
					</template>
				</tbody>
			</table>
		</div>
	</template>

	<!-- Pagination Controls -->
	<div class="px-4 py-3 flex items-center justify-between border-t border-slate-200 sm:px-6 bg-white rounded-b-lg" x-show="filteredItems.length > 0">
		<div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
			<div>
				<p class="text-sm text-slate-700">
					Showing <span class="font-medium" x-text="itemsPerPage === 'all' ? 1 : ((currentPage - 1) * itemsPerPage) + 1"></span> to 
					<span class="font-medium" x-text="itemsPerPage === 'all' ? filteredItems.length : Math.min(currentPage * itemsPerPage, filteredItems.length)"></span> of 
					<span class="font-medium" x-text="filteredItems.length"></span> results
				</p>
			</div>
			<div class="flex items-center space-x-4">
				<div class="flex items-center space-x-2">
					<label for="items-per-page" class="text-sm text-slate-700">Items per page:</label>
					<select id="items-per-page" x-model="itemsPerPage" class="o100-select-unified block pl-3 pr-8 py-1 text-sm border-slate-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md" style="width: auto !important; min-width: 80px !important;">
						<option value="10">10</option>
						<option value="20">20</option>
						<option value="50">50</option>
						<option value="100">100</option>
						<option value="all">All</option>
					</select>
				</div>
				<nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination" x-show="totalPages > 1">
					<button @click="if(currentPage > 1) currentPage--" :disabled="currentPage === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
						<span class="sr-only">Previous</span>
						<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
					</button>
					<span class="relative inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium text-slate-700">
						Page <span x-text="currentPage" class="mx-1"></span> of <span x-text="totalPages" class="mx-1"></span>
					</span>
					<button @click="if(currentPage < totalPages) currentPage++" :disabled="currentPage === totalPages" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-slate-300 bg-white text-sm font-medium text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
						<span class="sr-only">Next</span>
						<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
					</button>
				</nav>
			</div>
		</div>
		
		<!-- Mobile pagination UI -->
		<div class="flex items-center justify-between sm:hidden w-full">
			<button @click="if(currentPage > 1) currentPage--" :disabled="currentPage === 1" class="relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
				Previous
			</button>
			<span class="text-sm text-slate-700">
				<span x-text="currentPage"></span> / <span x-text="totalPages"></span>
			</span>
			<button @click="if(currentPage < totalPages) currentPage++" :disabled="currentPage === totalPages" class="relative inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed">
				Next
			</button>
		</div>
	</div>

			<!-- Item Edit Wizard Modal Overlay -->
	<div x-show="modals.item.open" style="display: none;" class="fixed inset-0 z-[99999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
		<div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
			<!-- Background backdrop -->
			<div x-show="modals.item.open" x-transition.opacity class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="modals.item.open = false"></div>
			<!-- Centering trick -->
			<span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
			
			<!-- Modal Panel -->
			<div class="relative inline-flex flex-col align-bottom bg-white rounded-xl text-left shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl w-full max-h-[90vh] border border-slate-200">
				
				<!-- Header -->
				<div class="bg-white px-6 py-4 border-b border-slate-200 flex justify-between items-center shrink-0 rounded-t-xl">
					<div class="flex items-center gap-3">
						<div class="p-2 bg-blue-50 rounded-lg">
							<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
						</div>
						<div>
							<h3 class="text-xl font-bold text-slate-900" x-text="modals.item.data.id ? '<?php esc_attr_e( 'Edit Item', 'order100' ); ?>' : '<?php esc_attr_e( 'Add New Item', 'order100' ); ?>'"></h3>
						</div>
					</div>
					<button @click="modals.item.open = false" class="text-slate-400 hover:text-slate-600 focus:outline-none bg-slate-50 hover:bg-slate-100 p-2 rounded-full transition-colors">
										<svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
					</button>
				</div>

				<!-- Tabs Navigation -->
				<div class="px-6 border-b border-slate-200 bg-white">
					<nav class="-mb-px flex space-x-6 overflow-x-auto" style="scrollbar-width: none; -ms-overflow-style: none;">
						<style>nav::-webkit-scrollbar { display: none; }</style>
						<button @click="setStep(1)" :class="modals.item.step === 1 ? 'border-blue-500 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
							Product Details
						</button>
						<button @click="setStep(4)" :class="modals.item.step === 4 ? 'border-blue-500 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
							Options & Add-ons
							<span x-show="modals.item.data.addon_options && modals.item.data.addon_options.length > 0" class="ml-2 bg-blue-100 text-blue-600 py-0.5 px-2 rounded-full text-xs" x-text="modals.item.data.addon_options.length"></span>
						</button>
						<button @click="setStep(5)" :class="modals.item.step === 5 ? 'border-blue-500 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
							Selling Rules
						</button>
						<button @click="setStep(6)" :class="modals.item.step === 6 ? 'border-blue-500 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
							Involved Campaigns
							<span x-show="modals.item.data.impacts && modals.item.data.impacts.length > 0" class="ml-2 bg-blue-100 text-blue-600 py-0.5 px-2 rounded-full text-xs" x-text="modals.item.data.impacts ? modals.item.data.impacts.length : 0"></span>
						</button>
					</nav>
				</div>

				<!-- Loading Overlay -->
				<div x-show="modals.item.loading" class="absolute inset-0 z-10 bg-white/80 backdrop-blur-sm flex flex-col items-center justify-center rounded-b-xl">
					<svg class="animate-spin h-10 w-10 text-blue-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
					<p class="text-slate-600 font-medium">Loading item configurations...</p>
				</div>

				<!-- Form Content Area -->
				<div id="o100-item-modal-body" class="flex-1 bg-white overflow-y-auto p-6 relative">
					
					<!-- Tab 1: General Info (Merged Steps 1, 2, 3) -->
					<div x-show="modals.item.step === 1" class="space-y-8">
						
						<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
							<div class="md:col-span-2 space-y-6">
								<div>
									<label class="block text-sm font-semibold text-slate-700 mb-2"><?php esc_html_e( 'Item Name', 'order100' ); ?> <span class="text-red-500">*</span></label>
									<input type="text" x-model="modals.item.data.title" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full text-base border-slate-300 rounded-md py-2.5">
								</div>

								<div class="grid grid-cols-2 gap-6" x-show="!modals.item.data.is_variable">
									<div>
										<label class="block text-sm font-semibold text-slate-700 mb-2">
											<?php esc_html_e( 'Base Price', 'order100' ); ?> 
											<span class="text-red-500">*</span>
										</label>
										<div class="o100-flex-input-wrap has-prefix w-full">
											<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
											<input type="text" x-model="modals.item.data.regular_price" class="o100-modal-input" placeholder="0.00">
										</div>
									</div>
									<div>
										<label class="block text-sm font-semibold text-slate-700 mb-2">
											<?php esc_html_e( 'Sale Price', 'order100' ); ?> 
											<span class="text-slate-400 font-normal">(Optional)</span>
										</label>
										<div class="o100-flex-input-wrap has-prefix w-full">
											<span class="o100-flex-prefix"><?php echo get_woocommerce_currency_symbol(); ?></span>
											<input type="text" x-model="modals.item.data.sale_price" class="o100-modal-input" placeholder="0.00">
										</div>
									</div>
								</div>
								
								<div x-show="modals.item.data.is_variable" class="bg-blue-50/50 p-6 rounded-lg border border-blue-100 flex flex-col items-center justify-center text-center space-y-3">
									<div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-1">
										<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
									</div>
									<h4 class="text-sm font-bold text-slate-800">Variable Product</h4>
									<p class="text-xs text-slate-600 max-w-sm">This is a WooCommerce variable product. Base and sale prices are managed at the variation level.</p>
									<button type="button" @click="setStep(4)" class="mt-2 inline-flex items-center px-4 py-1.5 border border-blue-200 text-xs font-medium rounded text-blue-700 bg-white hover:bg-blue-50 transition-colors">
										Manage Variations in Options Tab
										<svg class="ml-1.5 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
									</button>
								</div>

								<div class="grid grid-cols-2 gap-6">
									<div x-data="{ open: false, search: '' }" class="relative">
										<label class="block text-sm font-semibold text-slate-700 mb-2"><?php esc_html_e( 'Categories', 'order100' ); ?></label>
										<div @click="open = !open" @click.away="open = false" class="bg-white border border-slate-300 rounded-md py-2.5 px-3 cursor-pointer shadow-sm flex items-center justify-between hover:border-slate-400 transition-colors">
											<span class="text-sm text-slate-700 truncate" x-text="modals.item.data.category_ids.length > 0 ? modals.item.data.category_ids.length + ' category(s) selected' : 'Select categories...'"></span>
											<svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
										</div>
										<div x-show="open" style="display:none;" class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg p-2">
											<input type="text" x-model="search" placeholder="Search categories..." class="w-full text-sm border-slate-300 rounded-md py-1.5 mb-2 focus:ring-blue-500 focus:border-blue-500" @click.stop>
											<div class="max-h-48 overflow-y-auto space-y-1">
												<template x-for="cat in categories.filter(c => c.name.toLowerCase().includes(search.toLowerCase()))" :key="cat.id">
													<label class="flex items-center px-2 py-1.5 hover:bg-slate-50 rounded cursor-pointer">
														<input type="checkbox" :value="String(cat.id)" x-model="modals.item.data.category_ids" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mr-2">
														<span class="text-sm text-slate-700" x-text="cat.name"></span>
													</label>
												</template>
											</div>
										</div>
									</div>

									<div>
										<label class="block text-sm font-semibold text-slate-700 mb-2"><?php esc_html_e( 'Inventory Status', 'order100' ); ?></label>
										<select x-model="modals.item.data.stock_status" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border-slate-300 rounded-md py-2.5">
											<option value="instock"><?php esc_html_e( 'In Stock', 'order100' ); ?></option>
											<option value="outofstock"><?php esc_html_e( 'Out of Stock', 'order100' ); ?></option>
										</select>
									</div>
								</div>
								
								<div>
									<label class="block text-sm font-bold text-slate-700 mb-2"><?php esc_html_e( 'Short Description', 'order100' ); ?></label>
									<textarea x-model="modals.item.data.excerpt" rows="2" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border border-slate-300 rounded-md p-3" placeholder="Brief description for menu list view..."></textarea>
								</div>
								
								<div x-data="{ expanded: true }">
									<div class="flex items-center cursor-pointer text-blue-600 hover:text-blue-800 font-medium text-sm" @click="expanded = !expanded">
										<svg class="w-4 h-4 mr-1 transform transition-transform" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
										<?php esc_html_e( 'Add Detailed Description', 'order100' ); ?>
									</div>
									<div x-show="expanded" x-collapse class="mt-3">
										<textarea x-model="modals.item.data.description" rows="4" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full text-sm border border-slate-300 rounded-md p-3" placeholder="Long description for item details page..."></textarea>
									</div>
								</div>

							</div>

							<!-- Sidebar Media Section -->
							<div class="md:col-span-1 border-l border-slate-100 pl-8">
								<div class="mb-6">
									<label class="block text-sm font-semibold text-slate-700 mb-3"><?php esc_html_e( 'Main Image', 'order100' ); ?></label>
									<div class="border-2 border-dashed border-slate-300 rounded-xl p-2 text-center hover:bg-slate-50 transition-colors group cursor-pointer relative" @click="if(!modals.item.data.image_url) openMediaUploader('item_main')">
										<template x-if="modals.item.data.image_url">
											<div class="relative w-full aspect-square rounded-lg overflow-hidden">
												<img :src="modals.item.data.image_url" class="w-full h-full object-cover">
												<div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity flex-col gap-2">
													<button @click.stop="openMediaUploader('item_main')" class="bg-white text-slate-900 px-3 py-1.5 rounded-md text-xs font-medium shadow-sm hover:bg-slate-100 w-24">Change</button>
													<button @click.stop="modals.item.data.image_url=''; modals.item.data.image_id=0;" class="bg-red-500 text-white px-3 py-1.5 rounded-md text-xs font-medium shadow-sm hover:bg-red-600 w-24">Remove</button>
												</div>
											</div>
										</template>
										<template x-if="!modals.item.data.image_url">
											<div class="py-12 aspect-square flex flex-col items-center justify-center">
												<svg class="mx-auto h-10 w-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
												<span class="text-xs font-medium text-slate-500">Upload Image</span>
											</div>
										</template>
									</div>
								</div>

								<!-- Image Gallery -->
								<div>
									<div class="flex items-center justify-between mb-2">
										<label class="block text-sm font-semibold text-slate-700"><?php esc_html_e( 'Gallery', 'order100' ); ?></label>
										<button @click="openMediaUploader('item_gallery')" class="text-xs text-blue-600 font-medium hover:text-blue-800">Add Image</button>
									</div>
									<div class="grid grid-cols-2 gap-2">
										<template x-for="(img, index) in modals.item.data.gallery" :key="index">
											<div class="relative w-full aspect-square rounded overflow-hidden border border-slate-200 group">
												<img :src="img.url" class="w-full h-full object-cover">
												<button @click="modals.item.data.gallery.splice(index, 1)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600 shadow-sm">
													<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
												</button>
											</div>
										</template>
										
										<!-- Placeholder 1 (Shows when 0 images) -->
										<template x-if="!modals.item.data.gallery || modals.item.data.gallery.length === 0">
											<div @click="openMediaUploader('item_gallery')" class="w-full aspect-square rounded border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-100 transition-colors">
												<svg class="h-6 w-6 text-slate-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
												<span class="text-[10px] font-medium text-slate-400">Add Image</span>
											</div>
										</template>

										<!-- Placeholder 2 (Shows when 0 or 1 images) -->
										<template x-if="!modals.item.data.gallery || modals.item.data.gallery.length <= 1">
											<div @click="openMediaUploader('item_gallery')" class="w-full aspect-square rounded border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-100 transition-colors">
												<svg class="h-6 w-6 text-slate-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
												<span class="text-[10px] font-medium text-slate-400">Add Image</span>
											</div>
										</template>
									</div>
								</div>
								<!-- Food Labels -->
								<div class="mt-6 relative" x-data="{ open: false }" @click.outside="open = false">
									<label class="block text-sm font-semibold text-slate-700 mb-2"><?php esc_html_e( 'Food Labels', 'order100' ); ?></label>
									
									<div @click="open = !open" class="bg-white border border-slate-300 rounded-md py-2 px-3 cursor-pointer shadow-sm flex items-center justify-between hover:border-slate-400 transition-colors min-h-[42px]">
										<div class="flex items-center flex-wrap gap-1.5">
											<template x-if="!modals.item.data.labels || modals.item.data.labels.length === 0">
												<span class="text-sm text-slate-500">Select labels...</span>
											</template>
											<template x-for="lname in modals.item.data.labels" :key="lname">
												<div class="flex items-center bg-white border border-slate-200 shadow-sm rounded px-2 py-1 text-xs font-medium">
													<template x-if="globalLabels.find(l => l.name === lname)?.icon">
														<img :src="globalLabels.find(l => l.name === lname).icon" class="w-4 h-4 object-contain mr-1.5">
													</template>
													<span x-text="lname" class="text-slate-700"></span>
												</div>
											</template>
										</div>
										<svg class="w-4 h-4 text-slate-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
									</div>

									<div x-show="open" @click.stop style="display:none;" class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-md shadow-lg p-2 max-h-48 overflow-y-auto">
										<div class="space-y-1">
											<template x-for="label in globalLabels" :key="label.name">
												<label class="flex items-center px-2 py-1.5 hover:bg-slate-50 rounded cursor-pointer border border-transparent hover:border-slate-200 transition-colors" :class="modals.item.data.labels.includes(label.name) ? 'bg-blue-50 border-blue-100' : ''">
													<input type="checkbox" :value="label.name" x-model="modals.item.data.labels" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 mr-2.5">
													<template x-if="label.icon">
														<img :src="label.icon" class="w-5 h-5 object-contain mr-2">
													</template>
													<span class="text-sm text-slate-700 font-medium" x-text="label.name"></span>
												</label>
											</template>
											<template x-if="!globalLabels || globalLabels.length === 0">
												<div class="text-xs text-slate-500 italic p-2">No global labels configured.</div>
											</template>
										</div>
										<div class="mt-2 pt-2 border-t border-slate-100">
											<a href="<?php echo admin_url('admin.php?page=o100_hidden_menu&tab=o100_misc'); ?>" target="_blank" class="flex items-center text-sm text-blue-600 hover:text-blue-800 font-medium px-2 py-1.5 hover:bg-slate-50 rounded transition-colors">
												<svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
												<?php esc_html_e( 'Manage Global Labels', 'order100' ); ?>
											</a>
										</div>
									</div>
								</div>

							</div>
						</div>
					</div>

					<!-- Tab 4: Modifiers -->
					<div x-show="modals.item.step === 4" style="display:none;" class="space-y-6">
						<div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 flex items-start gap-3">
							<div class="mt-0.5">
								<input type="checkbox" x-model="modals.item.data.addon_exclude" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500 h-4 w-4">
							</div>
							<div>
								<label class="text-sm font-bold text-amber-900 block mb-1">Exclude Global Modifiers</label>
								<p class="text-xs text-amber-700">Check this box to prevent any global modifier templates from applying to this product. Only the custom modifiers defined below will be available.</p>
							</div>
						</div>

						<div class="border border-slate-200 rounded-xl overflow-hidden mb-6" x-show="getApplicableGlobalModifiers().length > 0">
							<div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
								<h5 class="font-medium text-slate-800">Inherited Global Modifiers</h5>
								<span class="text-xs bg-slate-200 text-slate-600 px-2 py-1 rounded font-medium">Read Only</span>
							</div>
							<div class="p-4 space-y-4 bg-slate-50/50">
								<template x-for="gmod in getApplicableGlobalModifiers()" :key="gmod._id">
									<div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm opacity-70">
										<div class="flex justify-between items-start mb-2">
											<div>
												<h6 class="text-sm font-bold text-slate-800" x-text="gmod._name"></h6>
												<div class="text-xs text-slate-500 mt-1">
													<span x-text="gmod._type === 'checkbox' ? 'Checkboxes (Multi)' : (gmod._type === 'radio' ? 'Radio (Single)' : 'Dropdown')"></span>
													<span class="mx-1">•</span>
													<span x-text="gmod._required === 'yes' ? 'Required' : 'Optional'"></span>
												</div>
											</div>
											<div class="text-xs font-medium bg-slate-100 text-slate-600 px-2 py-1 rounded">
												Global
											</div>
										</div>
										<div class="mt-3 flex flex-wrap gap-2">
											<template x-if="Array.isArray(gmod._options)">
												<template x-for="opt in gmod._options" :key="opt.name">
													<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-700">
														<span x-text="opt.name"></span>
														<template x-if="opt.price && parseFloat(opt.price) > 0">
															<span class="ml-1 text-slate-500" x-text="'+$' + parseFloat(opt.price).toFixed(2)"></span>
														</template>
													</span>
												</template>
											</template>
										</div>
									</div>
								</template>
							</div>
						</div>

						<div class="border border-slate-200 rounded-xl overflow-hidden">
							<div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center">
								<h5 class="font-medium text-slate-800">Custom Modifiers</h5>
								<button @click="modals.item.data.addon_options.push({ _id: 'o100-id'+Math.floor(Math.random()*1000000), _name: '', _type: 'checkbox', _required: 'no', _min_op: '', _max_op: '', _enb_img: 'no', _enb_qty: 'no', _min_opqty: '', _max_opqty: '', _price_type: '', _price: '', _expanded: true, _options: [{name: '', price: '', type: 'fixed', min: '', max: '', def: 'no', dis: 'no', image: ''}] })" class="text-sm bg-white border border-slate-300 px-3 py-1.5 rounded-md hover:bg-slate-50 font-medium text-blue-600 shadow-sm">+ Add Group</button>
							</div>
							
							<div class="p-4 space-y-6 bg-slate-50/50 min-h-[300px]">
								<template x-if="modals.item.data.addon_options.length === 0">
									<div class="text-center py-12 text-slate-400 text-sm flex flex-col items-center">
										<svg class="w-12 h-12 mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
										No custom modifiers configured. Click "Add Group" to create sizes, addons, or choices.
									</div>
								</template>

								<template x-for="(group, gIndex) in modals.item.data.addon_options" :key="gIndex">
									<div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm relative group/card" :class="group._is_woo_var === 'yes' ? 'border-blue-200 bg-blue-50/10' : ''">
										<!-- Collapsible Header Bar -->
										<div @click="group._expanded = (group._expanded === false) ? true : false" :class="group._expanded !== false ? 'pb-2 border-b border-slate-100 mb-4' : ''" class="flex justify-between items-center cursor-pointer select-none">
											<div class="flex items-center gap-2">
												<!-- Chevron Icon -->
												<svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200" :class="(group._expanded === false) ? '-rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
												</svg>
												<span class="font-semibold text-slate-700 text-sm" x-text="group._is_woo_var === 'yes' ? '<?php esc_attr_e( 'WooCommerce Native Variations', 'order100' ); ?>' : (group._name || '<?php esc_attr_e( 'Unnamed Modifier Group', 'order100' ); ?>')"></span>
												<template x-if="group._is_woo_var === 'yes'">
													<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 border border-blue-200 ml-1">
														<?php esc_html_e( 'Read-only', 'order100' ); ?>
													</span>
												</template>
											</div>
											<div class="flex items-center gap-3" @click.stop>
												<button x-show="group._is_woo_var !== 'yes'" @click="modals.item.data.addon_options.splice(gIndex, 1)" class="text-slate-300 hover:text-red-500 transition-colors">
													<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
												</button>
											</div>
										</div>
										
										<!-- Collapsible Body -->
										<div x-show="group._expanded !== false" class="space-y-4">

										<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 pr-8">
											<div>
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Group Name</label>
												<input type="text" x-model="group._name" placeholder="e.g. Choose Size" class="w-full text-sm border-slate-300 rounded-md py-2 focus:ring-blue-500 focus:border-blue-500" :readonly="group._is_woo_var === 'yes'" :class="group._is_woo_var === 'yes' ? 'bg-slate-100 text-slate-500 cursor-not-allowed opacity-70' : ''">
											</div>
											<div>
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Type</label>
												<select x-model="group._type" class="w-full text-sm border-slate-300 rounded-md py-2 focus:ring-blue-500 focus:border-blue-500" :disabled="group._is_woo_var === 'yes'" :class="group._is_woo_var === 'yes' ? 'bg-slate-100 text-slate-500 cursor-not-allowed opacity-70' : ''">
													<option value="checkbox">Checkboxes (Multiple choice)</option>
													<option value="radio">Radio Buttons (Single choice)</option>
													<option value="select">Dropdown Select</option>
													<option value="text">Text Input</option>
													<option value="textarea">Textarea</option>
													<option value="quantity">Quantity Input</option>
												</select>
											</div>
											<div>
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">UI Layout Style</label>
												<select x-model="group._display_type" class="w-full text-sm border-slate-300 rounded-md py-2 focus:ring-blue-500 focus:border-blue-500">
													<option value=""><?php esc_html_e( 'Default', 'order100' ); ?></option>
													<option value="nor"><?php esc_html_e( 'Normal (Always expanded)', 'order100' ); ?></option>
													<option value="accor"><?php esc_html_e( 'Accordion (Collapsible)', 'order100' ); ?></option>
													<option value="inline"><?php esc_html_e( 'Inline (Same row)', 'order100' ); ?></option>
												</select>
											</div>
										</div>
										
										<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 bg-slate-50 p-3 rounded border border-slate-100">
											<div>
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Required</label>
												<select x-model="group._required" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
													<option value="no">No (Optional)</option>
													<option value="yes">Yes (Mandatory)</option>
												</select>
											</div>
											<div x-show="group._type === 'checkbox' || group._type === ''">
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Min Selections</label>
												<input type="number" x-model="group._min_op" placeholder="0" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
											</div>
											<div x-show="group._type === 'checkbox' || group._type === ''">
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Max Selections</label>
												<input type="number" x-model="group._max_op" placeholder="0" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
											</div>
										</div>

										<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 bg-slate-50 p-3 rounded border border-slate-100" x-show="group._type !== 'text' && group._type !== 'textarea' && group._type !== 'quantity'">
											<div>
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Enable Images</label>
												<select x-model="group._enb_img" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
													<option value="no">No</option>
													<option value="yes">Yes</option>
												</select>
											</div>
											<div>
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Enable Quantities</label>
												<select x-model="group._enb_qty" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
													<option value="no">No</option>
													<option value="yes">Yes</option>
												</select>
											</div>
											<div x-show="group._enb_qty === 'yes'">
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Min Total Qty</label>
												<input type="number" x-model="group._min_opqty" placeholder="0" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
											</div>
											<div x-show="group._enb_qty === 'yes'">
												<label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Max Total Qty</label>
												<input type="number" x-model="group._max_opqty" placeholder="0" class="w-full text-sm border-slate-300 rounded-md py-1.5 focus:ring-blue-500 focus:border-blue-500">
											</div>
										</div>

										<div class="bg-orange-50 p-3 rounded border border-orange-200 mb-4" x-show="group._type === 'text' || group._type === 'textarea' || group._type === 'quantity'">
											<p class="text-xs font-semibold text-orange-700 mb-2">Text/Quantity Input Pricing</p>
											<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
												<div>
													<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Price Type</label>
													<select x-model="group._price_type" class="w-full text-sm border-orange-300 rounded-md py-1.5 focus:ring-orange-500 focus:border-orange-500">
														<option value="">Quantity Based</option>
														<option value="fixed">Fixed Amount</option>
													</select>
												</div>
												<div>
													<label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Price</label>
													<input type="text" x-model="group._price" placeholder="0.00" class="w-full text-sm border-orange-300 rounded-md py-1.5 focus:ring-orange-500 focus:border-orange-500">
												</div>
											</div>
										</div>

										<div class="border border-slate-200 rounded bg-slate-50 p-3" x-show="group._type !== 'text' && group._type !== 'textarea' && group._type !== 'quantity'">
											<div class="space-y-2 mb-3" :key="'var-opts-' + group._id + '-' + modals.item.data.id">
												<template x-for="(opt, oIndex) in (group._options || [])" :key="group._id + '-' + (opt.vid || '') + '-' + oIndex">
													<div class="flex items-center gap-2 bg-white p-2 border border-slate-200 rounded shadow-sm">
														<div x-show="group._is_woo_var !== 'yes'" class="flex items-center justify-center text-slate-300 cursor-move hover:text-slate-500 px-1 shrink-0" title="Drag to reorder">
															<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path></svg>
														</div>
														<!-- Image Upload Preview (Optional) -->
														<template x-if="group._enb_img === 'yes'">
															<div @click="openMediaUploader((url) => { group._options[oIndex].image = url; })" class="w-8 h-8 rounded border border-slate-300 bg-slate-100 flex items-center justify-center cursor-pointer overflow-hidden shrink-0" title="Set Image">
																<template x-if="opt.image">
																	<img :src="opt.image" class="w-full h-full object-cover">
																</template>
																<template x-if="!opt.image">
																	<svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
																</template>
															</div>
														</template>
														
														<input type="text" x-model="opt.name" placeholder="Option Name (e.g. Extra Cheese)" class="flex-1 text-sm border-slate-300 rounded py-1.5 px-2 focus:ring-blue-500" :readonly="group._is_woo_var === 'yes'" :class="group._is_woo_var === 'yes' ? 'bg-slate-100 text-slate-500 cursor-not-allowed opacity-70' : ''">
														
														<div class="flex flex-col items-center justify-center px-2" title="Default Option">
															<span class="text-[10px] text-slate-500 uppercase font-semibold mb-0.5">Default</span>
															<input type="checkbox" :checked="opt.def === 'yes'" @change="opt.def = $el.checked ? 'yes' : 'no'" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-3.5 w-3.5">
														</div>
														<div class="flex flex-col items-center justify-center px-2 border-r border-slate-200" title="Disable Option">
															<span class="text-[10px] text-slate-500 uppercase font-semibold mb-0.5">Disable</span>
															<input type="checkbox" :checked="opt.dis === 'yes'" @change="opt.dis = $el.checked ? 'yes' : 'no'" class="rounded border-slate-300 text-red-600 focus:ring-red-500 h-3.5 w-3.5">
														</div>

														<div class="o100-flex-input-wrap has-prefix flex-1 min-w-[70px] max-w-[120px]" title="<?php esc_attr_e( 'Regular Price', 'order100' ); ?>">
															<span class="o100-flex-prefix" style="padding: 0 8px;"><?php echo get_woocommerce_currency_symbol(); ?></span>
															<input type="number" step="0.01" x-model="opt.price" placeholder="0.00" class="o100-modal-input" style="padding: 6px 8px; width: 100%;">
														</div>
														<div class="o100-flex-input-wrap has-prefix flex-1 min-w-[70px] max-w-[120px]" title="<?php esc_attr_e( 'Sale Price', 'order100' ); ?>">
															<span class="o100-flex-prefix text-red-500" style="padding: 0 8px;"><?php echo get_woocommerce_currency_symbol(); ?></span>
															<input type="number" step="0.01" x-model="opt.sale_price" placeholder="Sale" class="o100-modal-input text-red-600 placeholder-red-300" style="padding: 6px 8px; width: 100%;">
														</div>
														
														<template x-if="group._enb_qty === 'yes'">
															<div class="flex items-center gap-1 shrink-0">
																<input type="number" x-model="opt.min" placeholder="Min" class="text-sm border-slate-300 rounded py-1.5 px-1.5 text-center focus:ring-blue-500" style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; flex: none !important; padding-left: 4px; padding-right: 4px;" title="Min Qty">
																<input type="number" x-model="opt.max" placeholder="Max" class="text-sm border-slate-300 rounded py-1.5 px-1.5 text-center focus:ring-blue-500" style="width: 80px !important; min-width: 80px !important; max-width: 80px !important; flex: none !important; padding-left: 4px; padding-right: 4px;" title="Max Qty">
															</div>
														</template>

														<button x-show="group._is_woo_var !== 'yes'" @click="group._options.splice(oIndex, 1)" class="text-slate-400 hover:text-red-500 p-1 shrink-0" title="Remove Option">
															<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
														</button>
													</div>
												</template>
											</div>
											<button x-show="group._is_woo_var !== 'yes'" @click="group._options.push({name:'', price:'', sale_price:'', type:'fixed', min:'', max:'', def:'no', dis:'no', image:''})" class="text-xs font-medium text-blue-600 hover:text-blue-800 flex items-center">
												<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Add Option
											</button>
										</div>
										</div>
									</div>
								</template>
							</div>
						</div>
					</div>

					<!-- Tab 5: Selling Rules -->
					<div x-show="modals.item.step === 5" style="display:none;" class="space-y-6">
						
						<!-- Order Methods & Branches (Side by Side on large screens) -->
						<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
							<div class="bg-white border border-slate-200 rounded-lg p-5">
								<h5 class="text-sm font-bold text-slate-800 mb-1">Allowed Order Methods</h5>
								<p class="text-xs text-slate-500 mb-4">Select allowed order methods for this item. If none are selected, the item will be unavailable for purchase.</p>
								<div class="space-y-2">
									<label class="flex items-center text-sm cursor-pointer">
										<input type="checkbox" value="delivery" 
											:checked="(modals.item.data.rule_methods || []).includes('delivery')"
											@change="if(!modals.item.data.rule_methods) modals.item.data.rule_methods = []; $event.target.checked ? (!modals.item.data.rule_methods.includes('delivery') && modals.item.data.rule_methods.push('delivery')) : modals.item.data.rule_methods = modals.item.data.rule_methods.filter(m => m !== 'delivery')"
											class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4 mr-2">
										Delivery
									</label>
									<label class="flex items-center text-sm cursor-pointer">
										<input type="checkbox" value="pickup" 
											:checked="(modals.item.data.rule_methods || []).includes('pickup')"
											@change="if(!modals.item.data.rule_methods) modals.item.data.rule_methods = []; $event.target.checked ? (!modals.item.data.rule_methods.includes('pickup') && modals.item.data.rule_methods.push('pickup')) : modals.item.data.rule_methods = modals.item.data.rule_methods.filter(m => m !== 'pickup')"
											class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4 mr-2">
										Pickup
									</label>
								</div>
							</div>

							<div class="bg-white border border-slate-200 rounded-lg p-5" x-show="branches && branches.length > 0">
								<h5 class="text-sm font-bold text-slate-800 mb-1">Available Branches</h5>
								<p class="text-xs text-slate-500 mb-4">Select branches where this item is available. By default, it is available at all branches.</p>
								<div class="space-y-2 max-h-[120px] overflow-y-auto pr-2">
									<label class="flex items-center text-sm cursor-pointer">
										<input type="checkbox" value="all" 
											:checked="(modals.item.data.rule_branches || []).includes('all')"
											@change="if($event.target.checked) { modals.item.data.rule_branches = ['all']; } else { modals.item.data.rule_branches = modals.item.data.rule_branches.filter(id => id !== 'all'); }"
											class="rounded border-slate-300 text-blue-600 h-4 w-4 mr-2">
										All Branches
									</label>
									<template x-for="b in branches" :key="b.id">
										<label class="flex items-center text-sm cursor-pointer">
											<input type="checkbox" :value="String(b.id)" 
												:checked="(modals.item.data.rule_branches || []).includes(String(b.id))"
												@change="
													if(!modals.item.data.rule_branches) modals.item.data.rule_branches = [];
													if($event.target.checked) {
														if(!modals.item.data.rule_branches.includes(String(b.id))) modals.item.data.rule_branches.push(String(b.id));
														modals.item.data.rule_branches = modals.item.data.rule_branches.filter(id => id !== 'all');
													} else {
														modals.item.data.rule_branches = modals.item.data.rule_branches.filter(id => id !== String(b.id));
													}
												"
												class="rounded border-slate-300 text-blue-600 h-4 w-4 mr-2">
											<span x-text="b.name"></span>
										</label>
									</template>
								</div>
							</div>
						</div>

						<div class="bg-white border border-slate-200 rounded-lg p-5">
							<h5 class="text-sm font-bold text-slate-800 mb-1">Time & Date Availability</h5>
							<p class="text-xs text-slate-500 mb-4">Set specific days, dates, or times when this item can be ordered. Leave empty for 24/7 availability.</p>
							
							<!-- Weekdays -->
							<div class="mb-5">
								<label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Days of Week</label>
								<div class="flex flex-wrap gap-2">
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('mon')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'mon'); else modals.item.data.rule_days.push('mon');"
										:class="(modals.item.data.rule_days || []).includes('mon') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Mon</div>
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('tue')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'tue'); else modals.item.data.rule_days.push('tue');"
										:class="(modals.item.data.rule_days || []).includes('tue') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Tue</div>
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('wed')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'wed'); else modals.item.data.rule_days.push('wed');"
										:class="(modals.item.data.rule_days || []).includes('wed') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Wed</div>
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('thu')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'thu'); else modals.item.data.rule_days.push('thu');"
										:class="(modals.item.data.rule_days || []).includes('thu') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Thu</div>
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('fri')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'fri'); else modals.item.data.rule_days.push('fri');"
										:class="(modals.item.data.rule_days || []).includes('fri') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Fri</div>
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('sat')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'sat'); else modals.item.data.rule_days.push('sat');"
										:class="(modals.item.data.rule_days || []).includes('sat') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Sat</div>
									<div @click="if (!modals.item.data.rule_days) modals.item.data.rule_days = []; if (modals.item.data.rule_days.includes('sun')) modals.item.data.rule_days = modals.item.data.rule_days.filter(d => d !== 'sun'); else modals.item.data.rule_days.push('sun');"
										:class="(modals.item.data.rule_days || []).includes('sun') ? '!bg-blue-600 !text-white !border-blue-600' : '!bg-white !text-slate-600 !border-slate-300 hover:!bg-slate-50'" 
										class="px-4 py-2 border rounded-md cursor-pointer text-sm font-medium transition-colors select-none">Sun</div>
								</div>
							</div>

							<!-- Time & Date Grid -->
							<div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
								<!-- Start Time -->
								<div class="relative" x-data="{ open: false }">
									<label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Start Time</label>
									<div class="relative">
										<input type="text" readonly @click="open = !open"
											:value="modals.item.data.rule_time_start ? (getTimeH('rule_time_start') + ':' + getTimeM('rule_time_start') + ' ' + getTimePeriod('rule_time_start')) : ''"
											class="w-full text-sm !border-slate-300 rounded-md focus:ring-blue-500 focus:border-blue-500 !bg-white cursor-pointer !pl-10 py-2" placeholder="Click to select">
										<svg class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
									</div>
									<!-- Dropdown Panel -->
									<div x-show="open" x-transition @click.outside="open = false"
										class="absolute left-0 top-full mt-2 bg-white rounded-xl shadow-xl border border-slate-200 p-4 z-[1000001] w-[220px]">
										<div class="flex items-center justify-between gap-3">
											<div class="flex items-center gap-1.5">
												<div class="flex flex-col items-center gap-1.5">
													<button type="button" @click="adjustTime('rule_time_start','h',1)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">+</button>
													<span class="text-lg font-bold text-slate-800 w-8 text-center tabular-nums" x-text="getTimeH('rule_time_start')"></span>
													<button type="button" @click="adjustTime('rule_time_start','h',-1)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">&minus;</button>
												</div>
												<span class="text-lg font-bold text-slate-400 pb-1">:</span>
												<div class="flex flex-col items-center gap-1.5">
													<button type="button" @click="adjustTime('rule_time_start','m',15)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">+</button>
													<span class="text-lg font-bold text-slate-800 w-8 text-center tabular-nums" x-text="getTimeM('rule_time_start')"></span>
													<button type="button" @click="adjustTime('rule_time_start','m',-15)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">&minus;</button>
												</div>
											</div>
											<div class="flex flex-col gap-1.5">
												<button type="button" @click="setTimePeriod('rule_time_start','am')" :class="getTimePeriod('rule_time_start')==='am' ? '!bg-blue-600 !border-blue-600 !text-white font-semibold' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'" class="px-3.5 py-1 rounded-md border text-xs transition-colors">AM</button>
												<button type="button" @click="setTimePeriod('rule_time_start','pm')" :class="getTimePeriod('rule_time_start')==='pm' ? '!bg-blue-600 !border-blue-600 !text-white font-semibold' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'" class="px-3.5 py-1 rounded-md border text-xs transition-colors">PM</button>
											</div>
										</div>
										<div class="flex justify-between mt-3 pt-2.5 border-t border-slate-100">
											<button type="button" @click="clearTime('rule_time_start'); open = false" class="text-xs text-slate-400 hover:text-red-500 transition-colors font-medium">Clear</button>
											<button type="button" @click="open = false" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-semibold shadow-sm transition-colors">Done</button>
										</div>
									</div>
								</div>
								<!-- End Time -->
								<div class="relative" x-data="{ open: false }">
									<label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">End Time</label>
									<div class="relative">
										<input type="text" readonly @click="open = !open"
											:value="modals.item.data.rule_time_end ? (getTimeH('rule_time_end') + ':' + getTimeM('rule_time_end') + ' ' + getTimePeriod('rule_time_end')) : ''"
											class="w-full text-sm !border-slate-300 rounded-md focus:ring-blue-500 focus:border-blue-500 !bg-white cursor-pointer !pl-10 py-2" placeholder="Click to select">
										<svg class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
									</div>
									<!-- Dropdown Panel -->
									<div x-show="open" x-transition @click.outside="open = false"
										class="absolute left-0 top-full mt-2 bg-white rounded-xl shadow-xl border border-slate-200 p-4 z-[1000001] w-[220px]">
										<div class="flex items-center justify-between gap-3">
											<div class="flex items-center gap-1.5">
												<div class="flex flex-col items-center gap-1.5">
													<button type="button" @click="adjustTime('rule_time_end','h',1)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">+</button>
													<span class="text-lg font-bold text-slate-800 w-8 text-center tabular-nums" x-text="getTimeH('rule_time_end')"></span>
													<button type="button" @click="adjustTime('rule_time_end','h',-1)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">&minus;</button>
												</div>
												<span class="text-lg font-bold text-slate-400 pb-1">:</span>
												<div class="flex flex-col items-center gap-1.5">
													<button type="button" @click="adjustTime('rule_time_end','m',15)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">+</button>
													<span class="text-lg font-bold text-slate-800 w-8 text-center tabular-nums" x-text="getTimeM('rule_time_end')"></span>
													<button type="button" @click="adjustTime('rule_time_end','m',-15)" class="w-8 h-8 rounded-lg border border-slate-200 text-slate-600 hover:text-blue-600 hover:border-blue-300 hover:bg-slate-50 flex items-center justify-center text-md font-bold transition-colors">&minus;</button>
												</div>
											</div>
											<div class="flex flex-col gap-1.5">
												<button type="button" @click="setTimePeriod('rule_time_end','am')" :class="getTimePeriod('rule_time_end')==='am' ? '!bg-blue-600 !border-blue-600 !text-white font-semibold' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'" class="px-3.5 py-1 rounded-md border text-xs transition-colors">AM</button>
												<button type="button" @click="setTimePeriod('rule_time_end','pm')" :class="getTimePeriod('rule_time_end')==='pm' ? '!bg-blue-600 !border-blue-600 !text-white font-semibold' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'" class="px-3.5 py-1 rounded-md border text-xs transition-colors">PM</button>
											</div>
										</div>
										<div class="flex justify-between mt-3 pt-2.5 border-t border-slate-100">
											<button type="button" @click="clearTime('rule_time_end'); open = false" class="text-xs text-slate-400 hover:text-red-500 transition-colors font-medium">Clear</button>
											<button type="button" @click="open = false" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-semibold shadow-sm transition-colors">Done</button>
										</div>
									</div>
								</div>
								<!-- Start Date -->
								<div>
									<label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Start Date</label>
									<div class="relative">
										<input type="text" x-model="modals.item.data.rule_start" class="o100-fp-date w-full text-sm !border-slate-300 rounded-md focus:ring-blue-500 focus:border-blue-500 !bg-white cursor-pointer !pl-10 py-2" placeholder="Click to select" readonly>
										<svg class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
									</div>
								</div>
								<!-- End Date -->
								<div>
									<label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">End Date</label>
									<div class="relative">
										<input type="text" x-model="modals.item.data.rule_end" class="o100-fp-date w-full text-sm !border-slate-300 rounded-md focus:ring-blue-500 focus:border-blue-500 !bg-white cursor-pointer !pl-10 py-2" placeholder="Click to select" readonly>
										<svg class="absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Tab 6: Involved Campaigns -->
					<div x-show="modals.item.step === 6" style="display:none;" class="space-y-6">
						<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
							<div class="mt-0.5 shrink-0">
								<svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
							</div>
							<div>
								<h4 class="text-sm font-semibold text-blue-900">Product Impact Registry</h4>
								<p class="text-xs text-blue-700 mt-1">This panel shows all active system rules and marketing campaigns that currently affect this product. This is a read-only view. Click Edit Rule to modify the source campaign.</p>
							</div>
						</div>

						<div x-show="!modals.item.data.impacts || modals.item.data.impacts.length === 0" style="display:none;">
							<div class="border-2 border-dashed border-slate-200 rounded-lg p-10 text-center bg-slate-50">
								<svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
								<h3 class="mt-2 text-sm font-medium text-slate-900">No Active Rules</h3>
								<p class="mt-1 text-sm text-slate-500">This product is not currently affected by any external campaigns or rules.</p>
							</div>
						</div>

						<div x-show="modals.item.data.impacts && modals.item.data.impacts.length > 0" style="display:none;" class="space-y-3">
							<template x-for="(impact, index) in modals.item.data.impacts" :key="'imp-'+index">
								<div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all hover:border-slate-300">
									<div class="flex items-start gap-3">
										<div class="bg-slate-100 p-2 rounded-lg shrink-0">
											<svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
										</div>
										<div>
											<div class="flex items-center gap-2">
												<span class="text-[10px] font-bold uppercase tracking-wider text-slate-500" x-text="impact.module || 'System'"></span>
												<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium" :class="impact.type === 'positive' ? 'bg-green-100 text-green-800' : (impact.type === 'negative' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800')" x-text="impact.status || 'Active'"></span>
											</div>
											<h5 class="text-sm font-bold text-slate-900 mt-0.5" x-text="impact.title || ''"></h5>
											<p class="text-xs text-slate-600 mt-0.5" x-text="impact.description || ''"></p>
										</div>
									</div>
									<div class="shrink-0 flex sm:justify-end">
										<template x-if="impact.action_url">
											<a :href="impact.action_url" target="_blank" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-xs font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 focus:outline-none transition-colors">
												Edit Rule
												<svg class="ml-1.5 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
											</a>
										</template>
									</div>
								</div>
							</template>
						</div>
					</div>

				</div>
				
				<!-- Footer Navigation -->
				<div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-between shrink-0 rounded-b-xl">
					<button @click="modals.item.open = false" type="button" class="inline-flex items-center px-4 py-2 border border-slate-300 shadow-sm text-sm font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 focus:outline-none transition-colors">
						Cancel
					</button>
					<button @click="saveItem()" :disabled="modals.item.saving" type="button" class="inline-flex items-center px-8 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white hover:bg-blue-700 focus:outline-none transition-colors disabled:opacity-50" style="background-color: #F59322 !important; color: #ffffff !important;">
						<svg style="width: 16px; height: 16px;" x-show="modals.item.saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
						<span x-text="modals.item.saving ? 'Saving...' : 'Save Item'"></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div></div></div>
