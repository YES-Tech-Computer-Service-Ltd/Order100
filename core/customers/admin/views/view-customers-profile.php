<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$customer_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
if ( $customer_id <= 0 ) {
	echo '<div class="p-4 bg-red-50 text-red-700 rounded-md">Invalid customer ID.</div>';
	return;
}

global $wpdb;
$tbl_customers = O100_Customers_DB::get_table_customers();
$customer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_customers} WHERE id = %d", $customer_id ) );

if ( ! $customer ) {
	echo '<div class="p-4 bg-red-50 text-red-700 rounded-md">Customer not found.</div>';
	return;
}

// 1. Data Aggregation
$lists = O100_Customers_DB::get_customer_lists( $customer_id );
$tags = O100_Customers_DB::get_customer_tags( $customer_id );

// Fetch all available for the UI
$all_lists = O100_Customers_DB::get_lists();
$all_tags = O100_Customers_DB::get_tags();
$manual_tags = array_filter( $all_tags, function($t) { return empty($t->is_system); });

$system_tags = array_filter( $tags, function( $t ) { return $t->is_system == 1; } );

$aov = $customer->total_orders > 0 ? ( $customer->total_spent / $customer->total_orders ) : 0;
$months_ago = max( 0, floor( ( time() - strtotime( $customer->created_at ) ) / MONTH_IN_SECONDS ) );
$time_text = $months_ago == 0 ? 'Recently added' : "Added {$months_ago} months ago";

// WooCommerce Orders
$woo_orders = [];
$latest_address = 'No address found.';
if ( function_exists('wc_get_orders') ) {
	$woo_orders = wc_get_orders( [
		'customer' => $customer->email,
		'limit'    => 50,
		'orderby'  => 'date',
		'order'    => 'DESC'
	] );
	
	if ( ! empty( $woo_orders ) ) {
		$latest_order = $woo_orders[0];
		$formatted_address = $latest_order->get_formatted_billing_address();
		if ( $formatted_address ) {
			$latest_address = strip_tags( str_replace('<br/>', "\n", $formatted_address) );
		}
	}
}

// Loyalty Ledger & Account
$loyalty_account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}o100_loyalty_accounts WHERE email = %s", $customer->email ) );
$loyalty_ledger = [];
if ( $loyalty_account ) {
	$loyalty_ledger = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}o100_loyalty_transactions WHERE account_id = %d ORDER BY created_at DESC LIMIT 50", $loyalty_account->id ) );
}

$back_url = admin_url('admin.php?page=o100-customers&tab=customers');
?>
<div class="mb-4">
	<a href="<?php echo esc_url($back_url); ?>" class="text-slate-500 hover:text-slate-800 text-sm font-medium flex items-center transition-colors">
		<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
		Back to Contacts
	</a>
</div>

<!-- Main Container -->
<div x-data="{ activeTab: 'overview' }" class="grid grid-cols-1 lg:grid-cols-12 gap-6">

	<!-- ================= 左侧悬浮侧边栏 (Floating Sidebar) ================= -->
	<div class="lg:col-span-3 space-y-6">
		<div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-100 overflow-hidden">
			<!-- Identity Section -->
			<div class="p-6 text-center border-b border-slate-100 relative">
				<div class="absolute top-4 right-4 cursor-pointer text-slate-400 hover:text-slate-600">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
				</div>
				<div class="h-24 w-24 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-600 mx-auto flex items-center justify-center text-3xl text-white font-bold mb-4 shadow-inner shadow-indigo-900/20">
					<?php echo esc_html( strtoupper( substr( $customer->first_name ?: $customer->email, 0, 1 ) ) ); ?>
				</div>
				<h2 class="text-xl font-bold text-slate-800 tracking-tight flex justify-center items-center gap-2">
					<?php echo esc_html( trim( $customer->first_name . ' ' . $customer->last_name ) ?: 'Guest' ); ?>
					<?php if ( $customer->status === 'subscribed' ) : ?>
						<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 uppercase tracking-wide">Subscribed</span>
					<?php else : ?>
						<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 uppercase tracking-wide">Unsub</span>
					<?php endif; ?>
				</h2>
				<p class="text-slate-500 text-sm mt-1 flex justify-center items-center gap-1">
					<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
					<?php echo esc_html( $customer->email ); ?>
				</p>
				<p class="text-slate-400 text-xs mt-3"><?php echo esc_html($time_text); ?></p>
				
				<div class="mt-4 flex flex-wrap justify-center gap-2">
					<?php 
						$source = !empty( $customer->acquisition_source ) ? $customer->acquisition_source : 'woocommerce';
						if ( $source === 'reservation' ) {
							$source_label = __( 'Reservation', 'order100' );
							$source_icon = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
							$source_class = 'bg-amber-50 text-amber-700 border-amber-200';
						} elseif ( $source === 'manual' ) {
							$source_label = __( 'Manual', 'order100' );
							$source_icon = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>';
							$source_class = 'bg-slate-50 text-slate-700 border-slate-200';
						} else {
							$source_label = __( 'Order', 'order100' );
							$source_icon = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>';
							$source_class = 'bg-indigo-50 text-indigo-700 border-indigo-200';
						}
					?>
					
					<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border <?php echo $source_class; ?>" title="<?php esc_attr_e( 'Acquisition Source', 'order100' ); ?>">
						<?php echo $source_icon; ?>
						<?php echo esc_html( $source_label ); ?>
					</span>

					<?php if ( $customer->wp_user_id > 0 ) : ?>
						<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border border-emerald-200 bg-emerald-50 text-emerald-700" title="<?php esc_attr_e( 'Registered WordPress User', 'order100' ); ?>">
							<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
							WP: #<?php echo esc_html( $customer->wp_user_id ); ?>
						</span>
					<?php else : ?>
						<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border border-slate-200 bg-slate-50 text-slate-500" title="<?php esc_attr_e( 'Guest Customer', 'order100' ); ?>">
							<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
							Guest
						</span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Tags & Lists Control -->
			<div class="p-6 space-y-5" x-data="customerSidebar(
				<?php echo $customer_id; ?>,
				<?php echo htmlspecialchars( json_encode( array_values( $lists ) ), ENT_QUOTES, 'UTF-8' ); ?>,
				<?php echo htmlspecialchars( json_encode( array_values( $all_lists ) ), ENT_QUOTES, 'UTF-8' ); ?>,
				<?php echo htmlspecialchars( json_encode( array_values( $tags ) ), ENT_QUOTES, 'UTF-8' ); ?>,
				<?php echo htmlspecialchars( json_encode( array_values( $manual_tags ) ), ENT_QUOTES, 'UTF-8' ); ?>
			)" x-cloak>
				
				<!-- Lists -->
				<div>
					<div class="flex items-center justify-between mb-3 relative">
						<h3 class="text-sm font-semibold text-slate-800">Lists</h3>
						<button @click="showListDropdown = !showListDropdown" @click.away="showListDropdown = false" class="text-slate-400 hover:text-indigo-600 transition-colors focus:outline-none">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
						</button>
						
						<!-- Add List Dropdown -->
						<div x-show="showListDropdown" x-transition style="display: none;" class="absolute right-0 top-6 mt-1 w-48 bg-white rounded-md shadow-lg border border-slate-200 z-10 py-1">
							<template x-if="unassignedLists.length === 0">
								<div class="px-4 py-2 text-xs text-slate-500">No lists available</div>
							</template>
							<template x-for="list in unassignedLists" :key="list.id">
								<button @click="manageRel(list.id, 'list', 'add')" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-indigo-600" x-text="list.title">
								</button>
							</template>
						</div>
					</div>
					<div class="flex flex-wrap gap-2">
						<template x-if="lists.length === 0">
							<span class="text-xs text-slate-400 italic">No lists assigned</span>
						</template>
						<template x-for="list in lists" :key="list.id">
							<span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
								<span x-text="list.title"></span>
								<template x-if="list.is_auto == 1">
									<button @click="confirmRemoveAutoTag(list.id, 'list')" class="ml-1.5 text-slate-400 hover:text-red-500 focus:outline-none"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
								</template>
								<template x-if="list.is_auto != 1">
									<button @click="manageRel(list.id, 'list', 'remove')" class="ml-1.5 text-slate-400 hover:text-red-500 focus:outline-none"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
								</template>
							</span>
						</template>
					</div>
				</div>

				<!-- Tags -->
				<div>
					<div class="flex items-center justify-between mb-3 relative">
						<h3 class="text-sm font-semibold text-slate-800">Tags</h3>
						<button @click="showTagDropdown = !showTagDropdown" @click.away="showTagDropdown = false" class="text-slate-400 hover:text-indigo-600 transition-colors focus:outline-none">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
						</button>
						
						<!-- Add Tag Dropdown -->
						<div x-show="showTagDropdown" x-transition style="display: none;" class="absolute right-0 top-6 mt-1 w-48 bg-white rounded-md shadow-lg border border-slate-200 z-10 py-1 max-h-48 overflow-y-auto">
							<template x-if="unassignedTags.length === 0">
								<div class="px-4 py-2 text-xs text-slate-500">No manual tags available</div>
							</template>
							<template x-for="tag in unassignedTags" :key="tag.id">
								<button @click="manageRel(tag.id, 'tag', 'add')" class="w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-indigo-600" x-text="tag.title">
								</button>
							</template>
						</div>
					</div>
					<div class="flex flex-wrap gap-2">
						<template x-if="tags.length === 0">
							<span class="text-xs text-slate-400 italic">No tags assigned</span>
						</template>
						<template x-for="tag in tags" :key="tag.id">
							<span :class="tag.is_system == 1 ? 'bg-purple-50 text-purple-700 border-purple-100' : 'bg-slate-100 text-slate-700 border-slate-200'" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border">
								<span x-text="tag.title"></span>
								<template x-if="tag.is_system == 1">
									<svg class="w-3 h-3 ml-1.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7a4 4 0 00-8 0v4h8z"></path></svg>
								</template>
								<template x-if="tag.is_system != 1 && tag.is_auto == 1">
									<button @click="confirmRemoveAutoTag(tag.id, 'tag')" class="ml-1.5 text-slate-400 hover:text-red-500 focus:outline-none"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
								</template>
								<template x-if="tag.is_system != 1 && tag.is_auto != 1">
									<button @click="manageRel(tag.id, 'tag', 'remove')" class="ml-1.5 text-slate-400 hover:text-red-500 focus:outline-none"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
								</template>
							</span>
						</template>
					</div>
				</div>
				
				<script>
				function customerSidebar(customerId, initialLists, allLists, initialTags, manualTags) {
					return {
						customerId: customerId,
						lists: initialLists,
						allLists: allLists,
						tags: initialTags,
						manualTags: manualTags,
						showListDropdown: false,
						showTagDropdown: false,
						
						get unassignedLists() {
							return this.allLists.filter(list => !this.lists.some(l => l.id == list.id));
						},
						
						get unassignedTags() {
							return this.manualTags.filter(tag => !this.tags.some(t => t.id == tag.id));
						},

						manageRel(objectId, objectType, actionType) {
							jQuery.post(ajaxurl, {
								action: 'o100_crm_manage_relationship',
								customer_id: this.customerId,
								object_id: objectId,
								object_type: objectType,
								action_type: actionType
							}, (response) => {
								if (response.success && response.data.lists && response.data.tags) {
									this.lists = response.data.lists;
									this.tags = response.data.tags;
									this.showListDropdown = false;
									this.showTagDropdown = false;
								} else {
									alert('Error: ' + (response.data.message || 'Unknown error'));
								}
							});
						},
						
						confirmRemoveAutoTag(objectId, objectType) {
							if (confirm("Warning: This item is automatically managed by system rules.\n\nManually removing it will permanently disable future automation for this item on this customer. Are you sure you want to proceed?")) {
								this.manageRel(objectId, objectType, 'remove');
							}
						}
					}
				}
				</script>
			</div>
		</div>
	</div>

	<!-- ================= 右侧主内容区 (Right Main Area) ================= -->
	<div class="lg:col-span-9 space-y-6">
		
		<!-- 右上角高光数据看板 (Highlight Metric Grid) -->
		<div class="grid grid-cols-2 md:grid-cols-5 gap-4">
			<div class="bg-white rounded-2xl p-5 shadow-sm shadow-slate-200/50 border border-slate-100 flex flex-col justify-center">
				<p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Customer Since</p>
				<p class="text-base font-semibold text-slate-800"><?php echo $customer->created_at ? date_i18n( 'M j, Y', strtotime( $customer->created_at ) ) : '-'; ?></p>
			</div>
			<div class="bg-white rounded-2xl p-5 shadow-sm shadow-slate-200/50 border border-slate-100 flex flex-col justify-center">
				<p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Last Order</p>
				<p class="text-base font-semibold text-slate-800"><?php echo $customer->last_order_date ? date_i18n( 'M j, Y', strtotime( $customer->last_order_date ) ) : 'Never'; ?></p>
			</div>
			<div class="bg-white rounded-2xl p-5 shadow-sm shadow-slate-200/50 border border-slate-100 flex flex-col justify-center">
				<p class="text-xs font-medium text-slate-500 uppercase tracking-wider mb-1">Orders</p>
				<p class="text-xl font-bold text-slate-800"><?php echo intval( $customer->total_orders ); ?></p>
			</div>
			
			<!-- 高光模块：LTV 和 AOV -->
			<div class="md:col-span-2 bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-5 shadow-md border border-slate-800 flex justify-between items-center relative overflow-hidden">
				<div class="absolute -right-4 -top-4 opacity-10">
					<svg class="w-32 h-32 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1.41 16.09V20h-2.67v-1.93c-1.71-.36-3.16-1.46-3.27-3.4h1.96c.1 1.05.82 1.87 2.65 1.87 1.96 0 2.4-.98 2.4-1.59 0-.83-.44-1.61-2.67-2.14-2.48-.6-4.18-1.62-4.18-3.67 0-1.72 1.39-2.84 3.11-3.21V4h2.67v1.95c1.86.45 2.79 1.86 2.85 3.39H14.3c-.05-1.11-.64-1.87-2.22-1.87-1.5 0-2.4.68-2.4 1.64 0 .84.65 1.39 2.67 1.91 2.84.73 4.18 1.92 4.18 3.91-.01 1.83-1.38 2.83-3.12 3.16z"/></svg>
				</div>
				<div>
					<p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">Lifetime Value</p>
					<p class="text-3xl font-bold text-white tracking-tight"><?php echo wc_price( $customer->total_spent ); ?></p>
				</div>
				<div class="text-right z-10 border-l border-slate-700 pl-4">
					<p class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-1">AOV</p>
					<p class="text-lg font-semibold text-white"><?php echo wc_price( $aov ); ?></p>
				</div>
			</div>
		</div>

		<!-- 胶囊型选项卡 (Pill-shaped Tabs) -->
		<div class="bg-white rounded-2xl shadow-sm shadow-slate-200/50 border border-slate-100 overflow-hidden min-h-[500px]">
			
			<div class="px-6 pt-4 border-b border-slate-100">
				<nav class="flex space-x-1 pb-4 overflow-x-auto hide-scrollbar" aria-label="Tabs">
					<button @click="activeTab = 'overview'" 
							:class="activeTab === 'overview' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'" 
							class="px-4 py-2 text-sm font-medium rounded-full whitespace-nowrap transition-all duration-200">
						Overview & Profiling
					</button>
					<button @click="activeTab = 'purchase'" 
							:class="activeTab === 'purchase' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'" 
							class="px-4 py-2 text-sm font-medium rounded-full whitespace-nowrap transition-all duration-200">
						Purchase History
					</button>
					<button @click="activeTab = 'points'" 
							:class="activeTab === 'points' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'" 
							class="px-4 py-2 text-sm font-medium rounded-full whitespace-nowrap transition-all duration-200">
						Points & Rewards
					</button>
					<button @click="activeTab = 'coupons'" 
							:class="activeTab === 'coupons' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'" 
							class="px-4 py-2 text-sm font-medium rounded-full whitespace-nowrap transition-all duration-200">
						Coupons & Campaigns
					</button>
					<button @click="activeTab = 'notes'" 
							:class="activeTab === 'notes' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100'" 
							class="px-4 py-2 text-sm font-medium rounded-full whitespace-nowrap transition-all duration-200">
						Notes
					</button>
				</nav>
			</div>

			<div class="p-6">
				<!-- Tab 1: Overview -->
				<div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
					<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
						<!-- Basic Info Form UI -->
						<div class="space-y-8">
							<div>
								<h3 class="text-base font-bold text-slate-900 mb-4">Basic Information</h3>
								<div class="space-y-4">
									<div class="grid grid-cols-2 gap-4">
										<div>
											<label class="block text-xs font-medium text-slate-500 mb-1">First Name</label>
											<input type="text" readonly value="<?php echo esc_attr($customer->first_name); ?>" class="block w-full bg-slate-50 border-slate-200 rounded-md text-sm text-slate-700 shadow-sm focus:ring-0 focus:border-slate-200">
										</div>
										<div>
											<label class="block text-xs font-medium text-slate-500 mb-1">Last Name</label>
											<input type="text" readonly value="<?php echo esc_attr($customer->last_name); ?>" class="block w-full bg-slate-50 border-slate-200 rounded-md text-sm text-slate-700 shadow-sm focus:ring-0 focus:border-slate-200">
										</div>
									</div>
									<div>
										<label class="block text-xs font-medium text-slate-500 mb-1">Email Address</label>
										<input type="text" readonly value="<?php echo esc_attr($customer->email); ?>" class="block w-full bg-slate-50 border-slate-200 rounded-md text-sm text-slate-700 shadow-sm focus:ring-0 focus:border-slate-200">
									</div>
									<div class="grid grid-cols-2 gap-4">
										<div>
											<label class="block text-xs font-medium text-slate-500 mb-1">Phone / Mobile</label>
											<input type="text" readonly value="<?php echo esc_attr($customer->phone); ?>" class="block w-full bg-slate-50 border-slate-200 rounded-md text-sm text-slate-700 shadow-sm focus:ring-0 focus:border-slate-200">
										</div>
										<div>
											<label class="block text-xs font-medium text-slate-500 mb-1">Date of Birth</label>
											<input type="text" readonly value="<?php echo (isset($loyalty_account) && $loyalty_account->birthday) ? date_i18n( get_option('date_format'), strtotime($loyalty_account->birthday) ) : 'Not provided'; ?>" class="block w-full bg-slate-50 border-slate-200 rounded-md text-sm text-slate-700 shadow-sm focus:ring-0 focus:border-slate-200">
										</div>
									</div>
								</div>
							</div>

							<div>
								<h3 class="text-base font-bold text-slate-900 mb-4">Address Information</h3>
								<div>
									<label class="block text-xs font-medium text-slate-500 mb-1">Latest Billing Address (from orders)</label>
									<textarea readonly rows="3" class="block w-full bg-slate-50 border-slate-200 rounded-md text-sm text-slate-700 shadow-sm focus:ring-0 focus:border-slate-200"><?php echo esc_textarea( $latest_address ); ?></textarea>
								</div>
							</div>
						</div>

						<!-- Smart Profiling Data -->
						<div>
							<h3 class="text-base font-bold text-slate-900 mb-4 flex items-center">
								Smart Profiling
								<span class="ml-2 px-2 py-0.5 rounded-full bg-purple-100 text-purple-600 text-[10px] font-bold uppercase tracking-wider">AI Powered</span>
							</h3>
							<?php if ( empty($system_tags) ) : ?>
								<div class="bg-slate-50 rounded-lg border border-slate-200 p-6 text-center text-slate-500 text-sm">
									Not enough data to generate smart profiles yet.
								</div>
							<?php else : ?>
								<div class="grid grid-cols-1 gap-3">
									<?php foreach ( $system_tags as $tag ) : ?>
										<div class="bg-white border border-slate-200 rounded-lg p-3 flex items-center shadow-sm">
											<div class="h-8 w-8 rounded bg-purple-50 text-purple-500 flex items-center justify-center mr-3">
												<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
											</div>
											<div>
												<p class="text-sm font-semibold text-slate-800"><?php echo esc_html($tag->title); ?></p>
												<p class="text-xs text-slate-500"><?php echo esc_html($tag->description); ?></p>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Tab 2: Purchase History -->
				<div x-show="activeTab === 'purchase'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
					<h3 class="text-base font-bold text-slate-900 mb-6">WooCommerce Purchase History</h3>
					<?php if ( empty($woo_orders) ) : ?>
						<div class="text-center py-12 border-2 border-dashed border-slate-200 rounded-xl">
							<svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
							<h3 class="mt-2 text-sm font-medium text-slate-900">No Orders</h3>
							<p class="mt-1 text-sm text-slate-500">This customer hasn't placed any orders yet.</p>
						</div>
					<?php else : ?>
						<div class="flow-root">
							<ul role="list" class="-mb-8">
								<?php foreach ( array_values($woo_orders) as $idx => $order ) : ?>
									<li>
										<div class="relative pb-8">
											<?php if ( $idx !== count($woo_orders) - 1 ) : ?>
												<span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
											<?php endif; ?>
											<div class="relative flex space-x-3">
												<div>
													<span class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center ring-8 ring-white">
														<svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
													</span>
												</div>
												<div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
													<div>
														<p class="text-sm text-slate-800">
															Order <a href="<?php echo esc_url($order->get_edit_order_url()); ?>" class="font-medium text-indigo-600 hover:text-indigo-800">#<?php echo $order->get_id(); ?></a> placed
															<span class="text-slate-500 font-normal"> (<?php echo $order->get_item_count(); ?> items)</span>
														</p>
														<div class="mt-1 flex items-center space-x-2">
															<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 border border-slate-200"><?php echo wc_get_order_status_name( $order->get_status() ); ?></span>
															<span class="text-sm font-bold text-slate-900"><?php echo $order->get_formatted_order_total(); ?></span>
														</div>
													</div>
													<div class="text-right text-sm whitespace-nowrap text-slate-500">
														<time datetime="<?php echo $order->get_date_created()->format('c'); ?>"><?php echo $order->get_date_created()->date_i18n( get_option('date_format') ); ?></time>
													</div>
												</div>
											</div>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>

				<!-- Tab 3: Points & Rewards -->
				<div x-show="activeTab === 'points'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
					
					<?php if ( ! $loyalty_account ) : ?>
						<div class="text-center py-12 border-2 border-dashed border-slate-200 rounded-xl">
							<svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
							<h3 class="mt-2 text-sm font-medium text-slate-900">No Loyalty Account</h3>
							<p class="mt-1 text-sm text-slate-500">This customer has not earned any points yet.</p>
						</div>
					<?php else: ?>
						<!-- Virtual Credit Card for Points -->
						<div class="w-full max-w-sm bg-gradient-to-tr from-amber-400 to-orange-500 rounded-xl p-6 shadow-lg shadow-orange-500/30 text-white mb-8 relative overflow-hidden">
							<div class="absolute right-0 top-0 opacity-20">
								<svg class="w-32 h-32 transform translate-x-8 -translate-y-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
							</div>
							<p class="text-white/80 text-sm font-medium uppercase tracking-wider mb-1 relative z-10">Available Points</p>
							<h2 class="text-5xl font-bold tracking-tight mb-4 relative z-10"><?php echo number_format($loyalty_account->points_balance); ?></h2>
							<div class="flex justify-between items-end relative z-10 pt-4 border-t border-white/20">
								<div>
									<p class="text-white/70 text-xs">Total Earned</p>
									<p class="font-semibold text-lg"><?php echo number_format($loyalty_account->points_earned); ?></p>
								</div>
								<div>
									<p class="text-white/70 text-xs">Total Spent</p>
									<p class="font-semibold text-lg"><?php echo number_format($loyalty_account->points_spent); ?></p>
								</div>
							</div>
						</div>

						<h3 class="text-base font-bold text-slate-900 mb-6">Points Ledger</h3>
						<?php if ( empty($loyalty_ledger) ) : ?>
							<p class="text-sm text-slate-500">No transaction history.</p>
						<?php else: ?>
							<div class="flow-root">
								<ul role="list" class="-mb-8">
									<?php foreach ( array_values($loyalty_ledger) as $idx => $entry ) : 
										$is_earn = in_array( $entry->type, ['earn', 'adjust'] ) && $entry->points > 0;
										$icon_color = $is_earn ? 'text-green-600 bg-green-100' : 'text-red-600 bg-red-100';
										$icon_svg = $is_earn ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>' : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>';
									?>
										<li>
											<div class="relative pb-8">
												<?php if ( $idx !== count($loyalty_ledger) - 1 ) : ?>
													<span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
												<?php endif; ?>
												<div class="relative flex space-x-3">
													<div>
														<span class="h-8 w-8 rounded-full <?php echo $icon_color; ?> flex items-center justify-center ring-8 ring-white">
															<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?php echo $icon_svg; ?></svg>
														</span>
													</div>
													<div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
														<div>
															<p class="text-sm font-medium text-slate-900">
																<?php echo $is_earn ? '+' : ''; ?><?php echo $entry->points; ?> Points
																<span class="font-normal text-slate-500"> via <?php echo esc_html($entry->source); ?></span>
															</p>
															<?php if ( $entry->note ) : ?>
																<p class="text-xs text-slate-500 mt-1"><?php echo esc_html($entry->note); ?></p>
															<?php endif; ?>
														</div>
														<div class="text-right text-sm whitespace-nowrap text-slate-500">
															<time datetime="<?php echo date('c', strtotime($entry->created_at)); ?>"><?php echo date_i18n( get_option('date_format'), strtotime($entry->created_at) ); ?></time>
														</div>
													</div>
												</div>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<!-- Tab 4: Coupons & Campaigns -->
				<div x-show="activeTab === 'coupons'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
					<h3 class="text-base font-bold text-slate-900 mb-6">Exclusive Coupons</h3>
					<!-- Mockup for Phase 6 -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
						<div class="border-2 border-dashed border-indigo-300 bg-indigo-50 rounded-xl p-4 flex items-center justify-between">
							<div>
								<p class="font-bold text-indigo-800 uppercase tracking-widest text-lg">VIP-WELCOME-10</p>
								<p class="text-sm text-indigo-600 mt-1">10% Off entire store</p>
							</div>
							<div class="text-right">
								<span class="inline-flex px-2 py-1 text-[10px] font-bold bg-white text-indigo-700 rounded shadow-sm">ACTIVE</span>
							</div>
						</div>
					</div>

					<h3 class="text-base font-bold text-slate-900 mb-6">Punchcard Campaigns</h3>
					<div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
						<p class="text-sm text-slate-500 text-center">No active campaigns found for this customer.</p>
					</div>
				</div>

				<!-- Tab 5: Notes -->
				<div x-show="activeTab === 'notes'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
					<h3 class="text-base font-bold text-slate-900 mb-6">Contact Notes</h3>
					
					<div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-6">
						<label class="sr-only">Add Note</label>
						<textarea rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-slate-300 rounded-md bg-white p-3" placeholder="Add a note about this customer..."></textarea>
						<div class="mt-3 flex justify-end">
							<button type="button" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded shadow-sm text-white bg-indigo-600 hover:bg-indigo-700">
								Add Note
							</button>
						</div>
					</div>

					<div class="text-center py-8">
						<p class="text-sm text-slate-500">No notes recorded yet.</p>
					</div>
				</div>

			</div>
		</div>
	</div>
</div>
