<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $customers ) ) : ?>
	<tr>
		<td colspan="10" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center">No customers found.</td>
	</tr>
<?php else : ?>
	<?php foreach ( $customers as $customer ) : 
		$c_tags = O100_Customers_DB::get_customer_tags($customer->id);
		$c_lists = O100_Customers_DB::get_customer_lists($customer->id);
	?>
		<tr class="hover:bg-slate-50">
			<td class="px-6 py-4 whitespace-nowrap w-1">
				<input type="checkbox" value="<?php echo intval( $customer->id ); ?>" x-model="selectedItems" @change="updateSelectAll" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
			</td>
			<td x-show="cols.email" class="px-6 py-4 whitespace-nowrap">
				<div class="flex items-center">
					<div class="h-8 w-8 rounded-full bg-slate-200 flex-shrink-0 flex items-center justify-center text-xs font-bold text-slate-500">
						<?php echo esc_html( strtoupper( substr( $customer->first_name ?: $customer->email, 0, 1 ) ) ); ?>
					</div>
					<div class="ml-3">
						<a href="?page=o100-customers&tab=customers&action=profile&id=<?php echo intval( $customer->id ); ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
							<?php echo esc_html( $customer->email ); ?>
						</a>
					</div>
				</div>
			</td>
			<td x-show="cols.fullName" class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">
				<?php echo esc_html( trim( $customer->first_name . ' ' . $customer->last_name ) ); ?>
			</td>
			<td x-show="cols.lists" class="px-6 py-4 text-sm text-slate-500">
				<?php echo esc_html( implode(', ', wp_list_pluck( $c_lists, 'title' ) ) ); ?>
			</td>
			<td x-show="cols.tags" class="px-6 py-4 text-sm text-slate-500">
				<?php echo esc_html( implode(', ', wp_list_pluck( $c_tags, 'title' ) ) ); ?>
			</td>
			<td x-show="cols.status" class="px-6 py-4 whitespace-nowrap w-1">
				<?php if ( $customer->status === 'subscribed' ) : ?>
					<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Subscribed</span>
				<?php elseif ( $customer->status === 'bounced' ) : ?>
					<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Invalid Email</span>
				<?php else : ?>
					<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Unsubscribed</span>
				<?php endif; ?>
			</td>
			<td x-show="cols.phone" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 w-1">
				<?php echo esc_html( $customer->phone ); ?>
			</td>
			<td x-show="cols.orders" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 w-1">
				<?php echo intval( $customer->total_orders ); ?>
			</td>
			<td x-show="cols.spent" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 w-1">
				<?php echo wc_price( $customer->total_spent ); ?>
			</td>
			<td x-show="cols.lastOrder" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 w-1">
				<?php echo $customer->last_order_date ? date_i18n( get_option('date_format'), strtotime( $customer->last_order_date ) ) : 'N/A'; ?>
			</td>
			<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium w-1">
				<a href="?page=o100-customers&tab=customers&action=profile&id=<?php echo intval( $customer->id ); ?>" class="text-indigo-600 hover:text-indigo-900 border border-indigo-200 rounded px-3 py-1 hover:bg-indigo-50">View</a>
			</td>
		</tr>
	<?php endforeach; ?>
<?php endif; ?>
