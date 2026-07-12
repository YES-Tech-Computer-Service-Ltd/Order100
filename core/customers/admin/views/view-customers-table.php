<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$tbl_customers = O100_Customers_DB::get_table_customers();

// Pagination
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = isset( $_GET['per_page'] ) ? max( 10, intval( $_GET['per_page'] ) ) : 20;

$is_premium = function_exists('O100_License') && O100_License()->is_premium();

$offset = ( $paged - 1 ) * $per_page;

// Basic Filters
$filter_tag = isset( $_GET['filter_tag'] ) ? intval( $_GET['filter_tag'] ) : 0;
$filter_list = isset( $_GET['filter_list'] ) ? intval( $_GET['filter_list'] ) : 0;
$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
$search_query = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

$where = "1=1";
$join = "";

// Apply tag filter
if ( $filter_tag > 0 ) {
	$tbl_rel = O100_Customers_DB::get_table_relationships();
	$join .= " INNER JOIN {$tbl_rel} r_tag ON {$tbl_customers}.id = r_tag.customer_id AND r_tag.object_type = 'tag' AND r_tag.object_id = " . intval($filter_tag);
}
// Apply list filter
if ( $filter_list > 0 ) {
	$tbl_rel = O100_Customers_DB::get_table_relationships();
	$join .= " INNER JOIN {$tbl_rel} r_list ON {$tbl_customers}.id = r_list.customer_id AND r_list.object_type = 'list' AND r_list.object_id = " . intval($filter_list);
}
// Apply status filter
if ( ! empty( $filter_status ) ) {
	$where .= $wpdb->prepare( " AND {$tbl_customers}.status = %s", $filter_status );
}
// Apply Search
if ( ! empty( $search_query ) ) {
	$search_like = '%' . $wpdb->esc_like( $search_query ) . '%';
	$where .= $wpdb->prepare( " AND ({$tbl_customers}.email LIKE %s OR {$tbl_customers}.first_name LIKE %s OR {$tbl_customers}.last_name LIKE %s)", $search_like, $search_like, $search_like );
}

// TODO: Advanced Filter JSON Parsing will go here

$total_items = $wpdb->get_var( "SELECT COUNT(DISTINCT {$tbl_customers}.id) FROM {$tbl_customers} {$join} WHERE {$where}" );
$total_pages = ceil( $total_items / $per_page );

$show_fake_data = false;
if ( ! $is_premium && ($paged > 1 || $per_page > 50) ) {
	$show_fake_data = true;
}

if ( $show_fake_data ) {
	$customers = array();
	$limit = min($per_page, 50);
	for ($i = 0; $i < $limit; $i++) {
		$dummy = new stdClass();
		$dummy->id = 999900 + $i;
		$dummy->first_name = 'Premium';
		$dummy->last_name = 'Customer ' . str_pad($i, 3, '0', STR_PAD_LEFT);
		$dummy->email = 'hidden' . $i . '@upgrade.pro';
		$dummy->phone = '+1 (555) ***-****';
		$dummy->status = 'active';
		$dummy->total_orders = rand(1, 100);
		$dummy->total_spent = rand(50, 1000) . '.00';
		$dummy->last_order_date = date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'));
		$dummy->created_at = date('Y-m-d H:i:s', strtotime('-' . rand(30, 365) . ' days'));
		$customers[] = $dummy;
	}
} else {
	$customers = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT {$tbl_customers}.* FROM {$tbl_customers} {$join} WHERE {$where} ORDER BY {$tbl_customers}.last_order_date DESC, {$tbl_customers}.created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
}

// Helper for UI Dropdowns
$all_lists = O100_Customers_DB::get_lists();
$all_tags = O100_Customers_DB::get_tags();

$base_url = '?page=o100-customers&tab=customers';
if ( $filter_tag > 0 ) $base_url .= '&filter_tag=' . $filter_tag;
if ( $filter_list > 0 ) $base_url .= '&filter_list=' . $filter_list;
if ( ! empty($filter_status) ) $base_url .= '&filter_status=' . $filter_status;
if ( ! empty($search_query) ) $base_url .= '&s=' . urlencode($search_query);
if ( $per_page !== 20 ) $base_url .= '&per_page=' . intval($per_page);
$base_url .= '&paged=';

?>
<div x-data="customersTable()" class="space-y-4">

	<!-- Top Header: Title & Actions -->
	<div class="flex flex-col sm:flex-row justify-between items-center bg-white p-4 rounded-lg shadow-sm border border-slate-200">
		<div class="flex items-center space-x-2">
			<h2 class="text-xl font-semibold text-slate-800">Contacts <span class="text-sm text-slate-500 font-normal">(<?php echo intval($total_items); ?>)</span></h2>
		</div>
		<div class="flex space-x-2 mt-4 sm:mt-0">
			<button @click="alert('Add Contact coming soon')" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
				<svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
				Add Contact
			</button>
			<button @click="alert('Import coming soon')" class="inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded shadow-sm text-slate-700 bg-white hover:bg-slate-50">
				<svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
				Import
			</button>
			<a href="?page=o100-customers&o100_crm_action=export_csv" class="inline-flex items-center px-4 py-2 border border-slate-300 text-sm font-medium rounded shadow-sm text-slate-700 bg-white hover:bg-slate-50">
				<svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
				Export
			</a>
		</div>
	</div>

	<!-- Main Table Container -->
	<?php if ( ! $is_premium ) : ?>
		<div class="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-lg shadow-sm mb-4">
			<div class="flex">
				<div class="flex-shrink-0">
					<svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
				</div>
				<div class="ml-3 flex-1">
					<p class="text-sm text-amber-800">
						<strong>Free Plan Limit:</strong> You have <?php echo intval($total_items); ?> total customers safely stored. The free version displays only the first page (max 50 customers). <a href="#" onclick="showCRMProModal(); return false;" class="font-medium underline hover:text-amber-600">Upgrade to PRO</a> to unlock unlimited browsing and advanced CRM features.
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>
	<div class="bg-white rounded-lg shadow-sm border border-slate-200">
		
		<!-- Filter Toolbar -->
		<div class="p-4 border-b border-slate-200" x-data="{ showFilters: false, showSort: false }">
			
			<!-- Main Bar: Search + Filter + Sort + Columns -->
			<div class="flex items-center gap-3">
				<!-- Search -->
				<div class="relative flex-grow max-w-md">
					<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
						<svg style="width: 16px; height: 16px;" class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
					</div>
					<input type="text" x-model.debounce.500ms="searchQuery" placeholder="Search by name, email..." class="focus:ring-indigo-500 focus:border-indigo-500 block w-full !pl-10 sm:text-sm border-slate-300 rounded-md py-2" style="padding-left: 2.5rem !important;" @keydown.enter="fetchData(1)">
				</div>

				<!-- Filter Button -->
				<button @click="showFilters = !showFilters; showSort = false" type="button" 
					:class="showFilters ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50'"
					class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md transition-colors whitespace-nowrap">
					<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
					Filter
					<span x-show="filterList || filterTag || filterStatus" class="ml-1.5 w-2 h-2 rounded-full bg-indigo-500 inline-block" style="display:none;"></span>
				</button>

				<!-- Sort Button -->
				<div class="relative" @click.outside="showSort = false">
					<button @click="showSort = !showSort; showFilters = false" type="button" class="inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium rounded-md text-slate-700 hover:bg-slate-50 transition-colors whitespace-nowrap">
						<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
						<span x-text="'Sort by ' + {last_order_date: 'Last Order', created_at: 'Created', email: 'Email', first_name: 'Name', total_order_count: 'Orders', total_order_value: 'Spent'}[orderBy] || 'Sort'">Sort</span>
						<svg style="width: 12px; height: 12px;" class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
					</button>
					<!-- Sort Dropdown Panel -->
					<div x-show="showSort" x-transition class="absolute right-0 top-full mt-1 w-52 bg-white rounded-lg shadow-xl border border-slate-200 py-2 z-50">
						<div class="px-3 py-1.5 text-xs font-semibold text-slate-400 uppercase tracking-wider">Sort By</div>
						<template x-for="opt in [{v:'last_order_date',l:'Last Order'},{v:'created_at',l:'Created Date'},{v:'email',l:'Email'},{v:'first_name',l:'Name'},{v:'total_order_count',l:'Total Orders'},{v:'total_order_value',l:'Total Spent'}]" :key="opt.v">
							<button type="button" @click="orderBy = opt.v; fetchData(1)" 
								:class="orderBy === opt.v ? 'text-indigo-700 bg-indigo-50 font-semibold' : 'text-slate-700 hover:bg-slate-50'"
								class="w-full text-left px-4 py-2 text-sm flex items-center justify-between transition-colors">
								<span x-text="opt.l"></span>
								<svg x-show="orderBy === opt.v" style="width: 14px; height: 14px;" class="text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
							</button>
						</template>
						<div class="border-t border-slate-100 my-1.5"></div>
						<div class="flex items-center gap-1 px-3 py-1">
							<button type="button" @click="orderDir = 'ASC'; fetchData(1)" 
								:class="orderDir === 'ASC' ? 'bg-indigo-100 border-indigo-400 text-indigo-700' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
								class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors">
								Asc <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
							</button>
							<button type="button" @click="orderDir = 'DESC'; fetchData(1)" 
								:class="orderDir === 'DESC' ? 'bg-indigo-100 border-indigo-400 text-indigo-700' : 'bg-white border-slate-200 text-slate-500 hover:bg-slate-50'"
								class="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md border transition-colors">
								Desc <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
							</button>
						</div>
					</div>
				</div>

				<!-- Columns Button -->
				<div class="relative" @click.outside="showColumns = false">
					<button @click="showColumns = !showColumns" type="button" class="inline-flex items-center px-4 py-2 border border-slate-300 bg-white text-sm font-medium rounded-md text-slate-700 hover:bg-slate-50 transition-colors whitespace-nowrap">
						<svg style="width: 16px; height: 16px;" class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
						Columns
					</button>
					<div x-show="showColumns" x-transition class="absolute right-0 top-full mt-1 w-56 bg-white rounded-lg shadow-xl border border-slate-200 z-50">
						<div class="p-4 space-y-3" role="menu">
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.email" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Email</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.fullName" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Full Name</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.lists" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Lists</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.tags" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Tags</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.status" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Status</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.phone" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Phone</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.orders" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Total Orders</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.spent" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Total Spent</span>
							</label>
							<label class="flex items-center">
								<input type="checkbox" x-model="cols.lastOrder" @change="saveCols" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
								<span class="ml-2 text-sm text-slate-700">Last Order Date</span>
							</label>
						</div>
					</div>
				</div>
			</div>

			<!-- Expandable Filter Row -->
			<div x-show="showFilters" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2" class="mt-3 pt-3 border-t border-slate-100" style="display: none;">
				<div class="flex flex-wrap gap-3 items-center">
					<select x-model="filterList" @change="fetchData(1)" class="block w-40 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
						<option value="">Filtered by Lists</option>
						<?php foreach ( $all_lists as $l ) : ?>
							<option value="<?php echo esc_attr($l->id); ?>" <?php selected($filter_list, $l->id); ?>><?php echo esc_html($l->title); ?></option>
						<?php endforeach; ?>
					</select>

					<select x-model="filterTag" @change="fetchData(1)" class="block w-40 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
						<option value="">Filtered by Tags</option>
						<?php foreach ( $all_tags as $t ) : ?>
							<option value="<?php echo esc_attr($t->id); ?>" <?php selected($filter_tag, $t->id); ?>><?php echo esc_html($t->title); ?></option>
						<?php endforeach; ?>
					</select>

					<select x-model="filterStatus" @change="fetchData(1)" class="block w-44 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
						<option value="">Filtered by Statuses</option>
						<option value="subscribed" <?php selected($filter_status, 'subscribed'); ?>>Subscribed</option>
						<option value="unsubscribed" <?php selected($filter_status, 'unsubscribed'); ?>>Unsubscribed</option>
						<option value="bounced" <?php selected($filter_status, 'bounced'); ?>>Invalid Email</option>
					</select>

					<select x-model="perPage" @change="fetchData(1)" class="block w-36 pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
						<option value="20" <?php selected($per_page, 20); ?>>20 per page</option>
						<option value="50" <?php selected($per_page, 50); ?>>50 per page</option>
						<option value="100" <?php selected($per_page, 100); ?>>100 per page</option>
						<option value="200" <?php selected($per_page, 200); ?>>200 per page</option>
					</select>

					<!-- Advanced Filter Toggle -->
					<style>
						.o100-adv-toggle { display:flex !important; align-items:center !important; margin-left:auto !important; gap:10px !important; }
						.o100-adv-toggle input[type="checkbox"] { display:none !important; }
						.o100-adv-toggle .o100-switch { position:relative !important; width:46px !important; height:26px !important; min-width:46px !important; background:#cbd5e1 !important; border-radius:13px !important; cursor:pointer !important; transition:background 0.2s ease, box-shadow 0.2s ease !important; display:inline-block !important; border:none !important; padding:0 !important; margin:0 !important; }
						.o100-adv-toggle .o100-switch::after { content:"" !important; position:absolute !important; top:3px !important; left:3px !important; width:20px !important; height:20px !important; background:#fff !important; border-radius:50% !important; box-shadow:0 1px 3px rgba(0,0,0,0.25) !important; transition:transform 0.2s ease !important; display:block !important; }
						.o100-adv-toggle input[type="checkbox"]:checked + .o100-switch { background:#F59322 !important; box-shadow:0 0 0 3px rgba(245,147,34,0.25) !important; }
						.o100-adv-toggle input[type="checkbox"]:checked + .o100-switch::after { transform:translateX(20px) !important; }
						.o100-adv-toggle .o100-switch-label { font-size:13px !important; font-weight:500 !important; color:#475569 !important; cursor:pointer !important; white-space:nowrap !important; }
					</style>
					<label class="o100-adv-toggle">
						<input type="checkbox" x-model="advancedFilter">
						<span class="o100-switch"></span>
						<span class="o100-switch-label">Advanced</span>
					</label>

					<button x-show="filterList || filterTag || filterStatus" @click="filterList = ''; filterTag = ''; filterStatus = ''; fetchData(1)" type="button" class="px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50 rounded-md border border-red-200 transition-colors" style="display: none;">Clear All</button>
				</div>
			</div>
		</div>

		<!-- Advanced Filter UI (Conditional) -->
		<div x-show="advancedFilter" x-transition class="p-6 bg-slate-50 border-b border-slate-200">
			<!-- Note: Actual advanced querying will be fully hooked up in backend next. UI Mockup matching FluentCRM screenshot for now. -->
			<div class="space-y-4">
				<div class="flex items-center space-x-4">
					<select class="block w-64 pl-3 pr-10 py-2 text-base bg-white text-slate-700 border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm">
						<option>Subscriber / General Properties</option>
						<option>Segment / Tags</option>
					</select>
					<select class="block w-40 pl-3 pr-10 py-2 text-base bg-white text-slate-700 border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md shadow-sm">
						<option>includes</option>
						<option>does not include</option>
					</select>
					<input type="text" placeholder="Condition Value" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm bg-white text-slate-700 border-slate-300 rounded-md py-2 shadow-sm">
					<button class="p-2 text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 rounded border border-red-200">
						<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
					</button>
				</div>
				<button @click="alert('Adding complex conditions coming soon')" class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-sm font-medium rounded text-slate-700 bg-white hover:bg-slate-50">
					<svg class="-ml-1 mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
					Add Filter
				</button>
				<div class="mt-4 pt-4 border-t border-slate-200">
					<button class="px-4 py-2 bg-indigo-500 text-white font-medium rounded text-sm hover:bg-indigo-600">Apply Filter</button>
				</div>
			</div>
		</div>

		<!-- Bulk Action Toolbar -->
		<div x-show="selectedItems.length > 0" x-transition class="p-3 bg-indigo-50 border-b border-indigo-100 flex flex-wrap gap-4 items-center justify-between">
			<div class="flex items-center space-x-4">
				<div class="text-sm text-slate-700">
					<span class="font-bold text-slate-900" x-text="selectedItems.length"></span> contacts on this page selected.
					<button type="button" @click="selectAll = true; toggleAll()" x-show="!selectAll" class="text-indigo-600 hover:text-indigo-800 font-medium ml-1">Select all</button>
				</div>
				
				<div class="flex items-center space-x-2">
					<select x-model="bulkAction" @change="bulkTarget = ''" class="block w-48 pl-3 pr-10 py-1.5 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
						<option value="">Select Bulk Action</option>
						<option value="add_tag">Add To Tags</option>
						<option value="add_list">Add To Lists</option>
						<option value="remove_tag">Remove From Tags</option>
						<option value="remove_list">Remove From Lists</option>
						<option value="change_status">Change Contact Status</option>
						<option value="delete">Delete Contacts</option>
					</select>

					<template x-if="bulkAction === 'add_tag' || bulkAction === 'remove_tag'">
						<select x-model="bulkTarget" class="block w-48 pl-3 pr-10 py-1.5 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
							<option value="">Select Tag</option>
							<?php foreach($all_tags as $t): ?>
							<option value="<?php echo esc_attr($t->id); ?>"><?php echo esc_html($t->title); ?></option>
							<?php endforeach; ?>
						</select>
					</template>

					<template x-if="bulkAction === 'add_list' || bulkAction === 'remove_list'">
						<select x-model="bulkTarget" class="block w-48 pl-3 pr-10 py-1.5 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
							<option value="">Select List</option>
							<?php foreach($all_lists as $l): ?>
							<option value="<?php echo esc_attr($l->id); ?>"><?php echo esc_html($l->title); ?></option>
							<?php endforeach; ?>
						</select>
					</template>

					<template x-if="bulkAction === 'change_status'">
						<select x-model="bulkTarget" class="block w-48 pl-3 pr-10 py-1.5 text-sm border-slate-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md">
							<option value="">Select Status</option>
							<option value="subscribed">Subscribed</option>
							<option value="unsubscribed">Unsubscribed</option>
							<option value="bounced">Invalid Email</option>
						</select>
					</template>

					<button x-show="bulkAction && (bulkTarget || bulkAction === 'delete')" @click="executeBulkAction" :disabled="isProcessingBulk" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
						<span x-show="isProcessingBulk" class="mr-2">
							<svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
								<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
								<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
							</svg>
						</span>
						Confirm
					</button>
				</div>
			</div>
		</div>

		<!-- Table Data -->
		<div class="overflow-x-auto relative">
			<template x-if="isFakeData">
				<div class="absolute inset-0 z-20 backdrop-blur-md bg-white/50 flex items-center justify-center" style="min-height: 400px;">
					<div class="bg-white p-8 rounded-2xl shadow-2xl text-center max-w-md mx-auto border border-slate-100 flex flex-col items-center">
						<div class="w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center mb-6">
							<svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
						</div>
						<h3 class="text-2xl font-bold text-slate-900 mb-2">Premium Feature</h3>
						<p class="text-slate-500 mb-8 leading-relaxed">Upgrade to PRO to unlock unlimited customer browsing, advanced filtering, and full CRM capabilities.</p>
						<a href="#" @click.prevent="if(typeof o100ShowProModal !== 'undefined'){o100ShowProModal('Order100 Pro CRM', 'Unlock limitless marketing possibilities. Upgrade now to exceed your limits and access valuable tools that fuel your business.');}" class="w-full inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white shadow-sm transition-all" style="background:#0f172a;" onmouseover="this.style.background='#F59322'" onmouseout="this.style.background='#0f172a'">
							Upgrade to PRO
						</a>
					</div>
				</div>
			</template>
			<table class="table-resizable min-w-full divide-y divide-x divide-slate-200" :class="{ 'blur-sm select-none pointer-events-none': isFakeData }">
				<thead class="bg-slate-50">
					<tr>
						<th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1">
							<input type="checkbox" x-model="selectAll" @change="toggleAll" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
						</th>
						<th x-show="cols.email" @click="setOrder('email')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hover:text-slate-800">
							<div class="flex items-center">Email <span x-html="getSortIcon('email')" class="ml-1"></span></div>
						</th>
						<th x-show="cols.fullName" @click="setOrder('fullName')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/6 hover:text-slate-800">
							<div class="flex items-center">Full Name <span x-html="getSortIcon('fullName')" class="ml-1"></span></div>
						</th>
						<th x-show="cols.lists" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/6">Lists</th>
						<th x-show="cols.tags" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1/4">Tags</th>
						<th x-show="cols.status" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1 whitespace-nowrap">Status</th>
						<th x-show="cols.phone" scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1 whitespace-nowrap">Phone</th>
						<th x-show="cols.orders" @click="setOrder('orders')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1 whitespace-nowrap hover:text-slate-800">
							<div class="flex items-center">Orders <span x-html="getSortIcon('orders')" class="ml-1"></span></div>
						</th>
						<th x-show="cols.spent" @click="setOrder('spent')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1 whitespace-nowrap hover:text-slate-800">
							<div class="flex items-center">Spent <span x-html="getSortIcon('spent')" class="ml-1"></span></div>
						</th>
						<th x-show="cols.lastOrder" @click="setOrder('last_order_date')" class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider w-1 whitespace-nowrap hover:text-slate-800">
							<div class="flex items-center">Last Order <span x-html="getSortIcon('last_order_date')" class="ml-1"></span></div>
						</th>
						<th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider w-1 whitespace-nowrap">Actions</th>
					</tr>
				</thead>
								<tbody id="customers-table-body" class="bg-white divide-y divide-slate-200 transition-opacity" :class="{ 'opacity-50 pointer-events-none': isFetching }">
					<?php require O100_PATH . 'core/customers/admin/views/view-customers-table-rows.php'; ?>
				</tbody>
			</table>
		</div>

		<div id="customers-table-pagination" class="transition-opacity" :class="{ 'opacity-50 pointer-events-none': isFetching }">
			<?php require O100_PATH . 'core/customers/admin/views/view-customers-table-pagination.php'; ?>
		</div>
	</div>
</div>

<script>
function customersTable() {
	return {
		searchQuery: '<?php echo esc_js($search_query); ?>',
		filterList: '<?php echo esc_js($filter_list); ?>',
		filterTag: '<?php echo esc_js($filter_tag); ?>',
		filterStatus: '<?php echo esc_js($filter_status); ?>',
		perPage: '<?php echo esc_js($per_page); ?>',
		paged: <?php echo esc_js($paged); ?>,
		isFakeData: <?php echo (! $is_premium && ($paged > 1 || $per_page > 50)) ? 'true' : 'false'; ?>,
		orderBy: 'last_order_date',
		orderDir: 'DESC',
		isFetching: false,
		advancedFilter: false,
		showColumns: false,
		selectAll: false,
		selectedItems: [],
		allIds: <?php echo json_encode(array_values(array_map('strval', wp_list_pluck((array)$customers, 'id')))); ?>,
		bulkAction: '',
		bulkTarget: '',
		isProcessingBulk: false,
		cols: {
			email: true,
			fullName: true,
			lists: true,
			tags: true,
			status: true,
			phone: false,
			orders: false,
			spent: false,
			lastOrder: false
		},
		toggleAll() {
			if (this.selectAll) {
				this.selectedItems = [...this.allIds];
			} else {
				this.selectedItems = [];
			}
		},
		updateSelectAll() {
			this.selectAll = this.selectedItems.length === this.allIds.length && this.allIds.length > 0;
		},
		goToPage(page) {
			this.fetchData(page);
		},
		setOrder(col) {
			if (this.orderBy === col) {
				this.orderDir = this.orderDir === 'ASC' ? 'DESC' : 'ASC';
			} else {
				this.orderBy = col;
				this.orderDir = 'DESC'; // Default to DESC when switching
			}
			this.fetchData(1);
		},
		getSortIcon(col) {
			if (this.orderBy !== col) return '<svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>';
			if (this.orderDir === 'ASC') return '<svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>';
			return '<svg class="w-3 h-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
		},
		fetchData(page = 1) {
			this.paged = page;
			this.isFetching = true;
			jQuery.post(ajaxurl, {
				action: 'o100_crm_get_customers_table',
				s: this.searchQuery,
				filter_list: this.filterList,
				filter_tag: this.filterTag,
				filter_status: this.filterStatus,
				per_page: this.perPage,
				paged: this.paged,
				orderby: this.orderBy,
				order: this.orderDir
			}, (res) => {
				this.isFetching = false;
				if (res.success) {
					jQuery('#customers-table-body').html(res.data.rows);
					jQuery('#customers-table-pagination').html(res.data.pagination);
					this.isFakeData = res.data.is_fake_data || false;
				}
			});
		},
		init() {
			this.$watch('searchQuery', (val) => {
				this.fetchData(1);
			});
			const savedCols = localStorage.getItem('o100_crm_table_cols');
			if (savedCols) {
				try {
					this.cols = { ...this.cols, ...JSON.parse(savedCols) };
				} catch (e) {}
			}
		},
		executeBulkAction() {
			if (!this.bulkAction || this.selectedItems.length === 0) return;
			if (this.bulkAction !== 'delete' && !this.bulkTarget) return;

			if (this.bulkAction === 'delete') {
				if (!confirm('Are you sure you want to completely delete ' + this.selectedItems.length + ' contacts? This cannot be undone.')) return;
			}

			this.isProcessingBulk = true;
			
			jQuery.post(ajaxurl, {
				action: 'o100_crm_bulk_action',
				bulk_action: this.bulkAction,
				bulk_target: this.bulkTarget,
				customer_ids: this.selectedItems
			}, (response) => {
				this.isProcessingBulk = false;
				if (response.success) {
					location.reload();
				} else {
					alert('Bulk action failed: ' + (response.data.message || 'Unknown error'));
				}
			}).fail(() => {
				this.isProcessingBulk = false;
				alert('Server Error.');
			});
		},
		saveCols() {
			localStorage.setItem('o100_crm_table_cols', JSON.stringify(this.cols));
		}
	}
}
</script>
<?php if ( function_exists('O100_License') && ! O100_License()->is_premium() ) : ?>
<div id="o100-crm-upgrade-template" style="display:none;">
	<?php O100_License()->render_upgrade_notice( 'Advanced CRM & Marketing', 'Want to view unlimited customer profiles, access advanced loyalty reports, and send mass marketing campaigns? Upgrade to Pro!' ); ?>
</div>
<script>
	function showCRMProModal() {
		if ( document.getElementById('o100-pro-modal') === null ) {
			var modalHtml = '<div id="o100-pro-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); z-index:99999; display:flex; align-items:center; justify-content:center;">' +
				'<div style="background:#fff; border-radius:12px; position:relative; width:90%; max-width:500px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden;">' +
					'<button type="button" class="o100-pro-close" onclick="document.getElementById(\'o100-pro-modal\').style.display=\'none\';" style="position:absolute; top:12px; right:12px; background:none; border:none; cursor:pointer; color:#64748b; padding:4px; z-index:10;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>' +
					'<div class="o100-pro-content-wrap" style="padding:40px; text-align:center;">' + document.getElementById('o100-crm-upgrade-template').innerHTML + '</div>' +
				'</div>' +
			'</div>';
			document.body.insertAdjacentHTML('beforeend', modalHtml);
			
			var innerCard = document.querySelector('#o100-pro-modal .o100-pro-content-wrap > div');
			if (innerCard) {
				innerCard.style.boxShadow = 'none';
				innerCard.style.border = 'none';
				innerCard.style.padding = '0';
				innerCard.style.margin = '0';
			}
		}
		document.getElementById('o100-pro-modal').style.display = 'flex';
	}
</script>
<?php endif; ?>
