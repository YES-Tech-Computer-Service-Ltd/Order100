<?php
/**
 * Admin View: Reservation Calendar & List
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script src="https://cdn.tailwindcss.com"></script>
<script>
	tailwind.config = {
		theme: {
			extend: {
				colors: {
					primary: '#F59322', // Indigo 600
					'primary-dark': '#d97b06', // Indigo 700
				}
			}
		}
	}
</script>
<style>
	/* FullCalendar Overrides to match FluentCRM style */
	.fc { font-family: inherit !important; }
	.fc-theme-standard .fc-scrollgrid { border-color: #e2e8f0 !important; }
	.fc-theme-standard td, .fc-theme-standard th { border-color: #e2e8f0 !important; }
	.fc-col-header-cell-cushion { padding: 12px 8px !important; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; }
	.fc .fc-button-primary { background-color: #fff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; font-size: 0.875rem !important; font-weight: 500 !important; text-transform: capitalize !important; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important; transition: all 0.2s !important; padding: 6px 12px !important; }
	.fc .fc-button-primary:not(:disabled):active, .fc .fc-button-primary:not(:disabled).fc-button-active { background-color: #f1f5f9 !important; color: #0f172a !important; border-color: #cbd5e1 !important; }
	.fc .fc-button-primary:hover { background-color: #f8fafc !important; color: #334155 !important; }
	.fc .fc-toolbar-title { font-size: 1.25rem !important; font-weight: 700 !important; color: #0f172a !important; }
	.fc-event { border: none !important; padding: 2px 4px !important; font-size: 0.75rem !important; font-weight: 600 !important; border-radius: 4px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important; margin-bottom: 2px !important; }
</style>
<div class="o100-proxy-wrap o100-wrap" x-data="o100ResvAdminApp()" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
	
	<!-- UNIFIED HEADER -->
	<div class="o100-promotions-page-header mb-8">
		<div class="w-full px-8">
			<div class="mb-6 pt-8 flex items-center justify-between">
				<div>
					<h1 class="text-2xl font-bold text-slate-900 m-0 pb-1" style="font-size:1.5rem !important; font-weight:700 !important; color:#0f172a !important;">Reservations</h1>
					<p class="text-sm text-slate-500 m-0 mt-1">Manage your table and private room bookings.</p>
				</div>
			</div>
		</div>
		<div class="border-b border-gray-300 px-8">
			<div class="flex items-center justify-between -mb-px">
				<div class="flex gap-6">
					<button @click="viewMode = 'list'" :class="{'border-primary text-primary font-bold': viewMode === 'list', 'border-transparent text-slate-500 font-medium hover:text-slate-700': viewMode !== 'list'}" class="pb-3 border-b-2 text-sm transition-colors">List View</button>
					<button @click="viewMode = 'calendar'" :class="{'border-primary text-primary font-bold': viewMode === 'calendar', 'border-transparent text-slate-500 font-medium hover:text-slate-700': viewMode !== 'calendar'}" class="pb-3 border-b-2 text-sm transition-colors">Calendar View</button>
					<button @click="viewMode = 'settings'" :class="{'border-primary text-primary font-bold': viewMode === 'settings', 'border-transparent text-slate-500 font-medium hover:text-slate-700': viewMode !== 'settings'}" class="pb-3 border-b-2 text-sm transition-colors">Settings</button>
					<button @click="viewMode = 'form_builder'" :class="{'border-primary text-primary font-bold': viewMode === 'form_builder', 'border-transparent text-slate-500 font-medium hover:text-slate-700': viewMode !== 'form_builder'}" class="pb-3 border-b-2 text-sm transition-colors">Form Builder</button>
				</div>
				<button type="button" x-show="viewMode === 'settings' || viewMode === 'form_builder'" @click="saveSettings()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl font-bold shadow-sm transition-colors text-sm mb-2" style="display:none;" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Save Settings'">Save Settings</button>
			</div>
		</div>
	</div>

	<!-- Notifications -->
	<div class="px-8">
		<div x-show="message.text" :class="{'bg-emerald-50 text-emerald-700 border-emerald-200': message.type === 'success', 'bg-red-50 text-red-700 border-red-200': message.type === 'error'}" class="mb-6 p-4 rounded-lg border flex justify-between items-center shadow-sm" style="display:none;" x-transition>
			<p x-text="message.text" class="m-0 font-medium text-sm"></p>
			<button @click="message.text = ''" class="text-slate-400 hover:text-slate-600">&times;</button>
		</div>
	</div>

	<!-- List View -->
	<div class="w-full px-8 pb-12" x-show="viewMode === 'list'" style="display:none;">
		<!-- Toolbar: Search / Filter / Sort -->
		<div class="mb-4 flex flex-wrap items-center justify-between gap-4">
			<div>
				<h2 class="text-lg font-bold text-slate-800 m-0 whitespace-nowrap">All Reservations (<span x-text="listData.length"></span>)</h2>
			</div>
			
			<div class="flex flex-wrap items-center gap-3 flex-1 justify-end">
				<!-- Search box -->
				<div class="relative w-64">
					<svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
					<input type="text" x-model="searchQuery" @keydown.enter="fetchList()" placeholder="Search Name, Email, Phone..." class="w-full pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition" style="padding-left: 2.5rem !important;">
				</div>
				
				<select x-model="statusFilter" @change="fetchList()" class="border border-slate-300 rounded-lg py-2 text-sm text-slate-700 bg-white" style="padding-left:12px;padding-right:32px;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%222%22><polyline points=%226 9 12 15 18 9%22/></svg>');background-repeat:no-repeat;background-position:right 10px center;">
					<option value="">All Statuses</option>
					<option value="pending">Pending</option>
					<option value="confirmed">Confirmed</option>
					<option value="completed">Completed</option>
					<option value="cancelled">Cancelled</option>
					<option value="no_show">No Show</option>
				</select>
				
				<select x-model="bulkAction" class="border border-slate-300 rounded-lg py-2 text-sm text-slate-700 bg-white" style="padding-left:12px;padding-right:32px;appearance:none;-webkit-appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%222%22><polyline points=%226 9 12 15 18 9%22/></svg>');background-repeat:no-repeat;background-position:right 10px center;">
					<option value="">Bulk Actions</option>
					<option value="confirmed">Confirm</option>
					<option value="cancelled">Cancel</option>
					<option value="no_show">Mark No-Show</option>
					<option value="completed">Mark Completed</option>
					<option value="delete">Delete</option>
				</select>
				<button @click="applyBulkAction()" :disabled="!bulkAction || selectedIds.length === 0" :class="{'opacity-50 cursor-not-allowed': !bulkAction || selectedIds.length === 0}" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">Apply</button>
			</div>
		</div>

		<!-- Active Reservations Table -->
		<div>
			<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto relative">
				<table class="w-full divide-y divide-slate-200" style="min-width: 1000px;">
					<thead>
						<tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
							<th class="py-3 px-6 w-10 text-center sticky left-0 bg-slate-50 z-20 border-r border-slate-200/50"><input type="checkbox" class="rounded border-slate-300" @change="toggleAll($event)" :checked="selectedIds.length > 0 && selectedIds.length === listData.length"></th>
							<th class="py-3 px-6 text-center">#</th>
							<th class="py-3 px-6 text-center">Type</th>
							<th class="py-3 px-6 text-center">Status</th>
							<th class="py-3 px-6">Guest</th>
							<th class="py-3 px-6 text-center">Party</th>
							<th class="py-3 px-6 w-40">Date & Time</th>
							<th class="py-3 px-6 text-center">Room</th>
							<th class="py-3 px-6 text-center">Source</th>
							<th class="py-3 px-6 text-right sticky right-0 bg-slate-50 z-20 border-l border-slate-200/50 shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)]">Actions</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-slate-100 text-slate-700 bg-white">
						<tr x-show="loadingList"><td colspan="10" class="p-8 text-center text-slate-500 text-sm">Loading reservations...</td></tr>
						<tr x-show="!loadingList && listData.length === 0">
							<td colspan="10" class="p-16 text-center text-slate-400">
								<svg class="w-12 h-12 mx-auto mb-3 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
								No reservations found.
							</td>
						</tr>
						
						<template x-for="item in listData" :key="item.id">
							<tr class="hover:bg-slate-50 transition-colors group">
								<td class="py-4 px-6 text-center sticky left-0 bg-white group-hover:bg-slate-50 z-10 border-r border-slate-100"><input type="checkbox" class="rounded border-slate-300" :value="item.id" x-model="selectedIds"></td>
								<td class="py-4 px-6 text-center font-mono text-sm text-slate-500" x-text="item.id"></td>
								<td class="py-4 px-6 text-center">
									<span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold bg-slate-100 text-slate-600 rounded" x-text="item.booking_type === 'private_room' ? 'Room' : 'Table'"></span>
								</td>
								<td class="py-4 px-6 text-center">
									<span :style="'background-color:'+getStatusColor(item.status)+'15; color:'+getStatusColor(item.status)+'; border: 1px solid '+getStatusColor(item.status)+'40;'" 
										class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md inline-block whitespace-nowrap" 
										x-text="item.status"></span>
								</td>
								<td class="py-4 px-6">
									<div class="text-sm font-bold text-slate-900" x-text="item.guest_name"></div>
									<div class="text-primary hover:underline text-xs mt-1">
										<a :href="'mailto:'+item.guest_email" x-text="item.guest_email"></a>
									</div>
									<div class="text-slate-500 text-xs mt-1" x-text="item.guest_phone"></div>
								</td>
								<td class="py-4 px-6 text-center">
									<span class="font-bold text-slate-700 text-sm" x-text="item.party_size"></span>
								</td>
								<td class="py-4 px-6 text-sm">
									<div class="font-bold text-slate-900" x-text="item.reservation_date"></div>
									<div class="text-slate-500 text-xs mt-1 flex items-center gap-1">
										<svg class="text-slate-400" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
										<span x-text="item.reservation_time"></span>
									</div>
								</td>
								<td class="py-4 px-6 text-center">
									<span class="text-sm text-slate-500">-</span>
								</td>
								<td class="py-4 px-6 text-center">
									<span class="text-sm text-slate-500 capitalize" x-text="item.source"></span>
								</td>
								<td class="px-6 py-4 text-right whitespace-nowrap sticky right-0 bg-white group-hover:bg-slate-50 z-10 border-l border-slate-100 shadow-[-4px_0_6px_-1px_rgba(0,0,0,0.05)]">
									<div class="flex justify-end space-x-2">
										<div class="relative group/btn" x-show="item.status === 'pending'">
											<button @click="updateStatus(item.id, 'confirmed')" class="inline-flex items-center p-1.5 border border-transparent rounded shadow-sm text-white bg-emerald-500 hover:bg-emerald-600 focus:outline-none">
												<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
											</button>
											<div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover/btn:block bg-gray-800 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap z-50">Confirm</div>
										</div>
										<div class="relative group/btn" x-show="item.status === 'confirmed'">
											<button @click="updateStatus(item.id, 'completed')" class="inline-flex items-center p-1.5 border border-transparent rounded shadow-sm text-white bg-blue-500 hover:bg-blue-600 focus:outline-none">
												<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
											</button>
											<div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover/btn:block bg-gray-800 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap z-50">Complete</div>
										</div>
										<div class="relative group/btn" x-show="item.status !== 'cancelled' && item.status !== 'completed'">
											<button @click="updateStatus(item.id, 'cancelled')" class="inline-flex items-center p-1.5 border border-transparent rounded shadow-sm text-white bg-red-500 hover:bg-red-600 focus:outline-none">
												<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
											</button>
											<div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover/btn:block bg-gray-800 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap z-50">Cancel</div>
										</div>
										<div class="relative group/btn" x-show="item.status === 'pending' || item.status === 'confirmed'">
											<button @click="updateStatus(item.id, 'no_show')" class="inline-flex items-center p-1.5 border border-transparent rounded shadow-sm text-white bg-slate-500 hover:bg-slate-600 focus:outline-none">
												<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 0 0-4-4H5c-2.2 0-4 1.8-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="18" y1="8" x2="23" y2="13"></line><line x1="23" y1="8" x2="18" y2="13"></line></svg>
											</button>
											<div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover/btn:block bg-gray-800 text-white text-[10px] font-bold px-2 py-1 rounded shadow-lg whitespace-nowrap z-50">No Show</div>
										</div>
									</div>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Calendar View -->
	<div class="w-full px-8 pb-12" x-show="viewMode === 'calendar'" style="display:none;">
		<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 overflow-hidden">
			<div id="o100-resv-calendar" x-ref="calendarEl"></div>
		</div>
	</div>

	<!-- Settings & Form Builder View -->
	<div class="w-full px-8 pb-12" x-show="viewMode === 'settings' || viewMode === 'form_builder'" style="display:none;">
		<h2 class="text-xl font-bold text-slate-800 mb-6" x-show="viewMode === 'settings'">Reservation Settings</h2>
		<h2 class="text-xl font-bold text-slate-800 mb-6" x-show="viewMode === 'form_builder'">Form Builder</h2>
		<div>
			<?php 
			if ( function_exists('cmb2_metabox_form') ) {
				cmb2_metabox_form( 'o100_reservation', 'o100_reservation', array(
					'save_button' => __('Save Reservation Settings', 'order100'),
					'form_format' => '<form class="cmb-form" method="post" id="%1$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="object_id" value="%2$s"><input type="hidden" name="submit-cmb" value="1">%3$s<input type="submit" name="submit-cmb-btn" value="%4$s" style="display:none;"></form>',
				) ); 
			}
			?>
		</div>
	</div>

	<!-- Reservation Details Modal -->
	<div x-show="showModal" style="display:none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
		<div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
			<!-- Background overlay -->
			<div x-show="showModal" x-transition.opacity class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showModal = false"></div>

			<!-- This element is to trick the browser into centering the modal contents. -->
			<span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

			<!-- Modal panel -->
			<div x-show="showModal" 
				x-transition:enter="ease-out duration-300"
				x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
				x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
				x-transition:leave="ease-in duration-200"
				x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
				x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
				class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
				
				<template x-if="selectedEvent">
					<div>
						<!-- Header -->
						<div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex justify-between items-center">
							<h3 class="text-lg leading-6 font-bold text-slate-900" id="modal-title">Reservation Details</h3>
							<button type="button" class="text-slate-400 hover:text-slate-500 focus:outline-none" @click="showModal = false">
								<span class="sr-only">Close</span>
								<svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
								</svg>
							</button>
						</div>
						
						<!-- Body -->
						<div class="px-6 py-5">
							<div class="flex items-center justify-between mb-6">
								<div class="flex items-center gap-3">
									<div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center text-primary text-lg font-bold" x-text="selectedEvent.extendedProps.party_size"></div>
									<div>
										<h4 class="text-xl font-bold text-slate-900 m-0 leading-tight" x-text="selectedEvent.extendedProps.guest_name"></h4>
										<p class="text-sm text-slate-500 m-0">Guests</p>
									</div>
								</div>
								<span :style="'background-color:'+getStatusColor(selectedEvent.extendedProps.status)+'15; color:'+getStatusColor(selectedEvent.extendedProps.status)+'; border: 1px solid '+getStatusColor(selectedEvent.extendedProps.status)+'40;'" 
									  class="px-3 py-1 text-xs font-bold uppercase tracking-wider rounded-md" 
									  x-text="selectedEvent.extendedProps.status"></span>
							</div>

							<div class="space-y-4">
								<div class="flex items-start gap-3">
									<svg class="w-5 h-5 text-slate-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
									<div>
										<p class="text-sm font-medium text-slate-900 m-0" x-text="formatDateRange(selectedEvent.start, selectedEvent.end)"></p>
										<p class="text-xs text-slate-500 m-0">Date & Time</p>
									</div>
								</div>
								<div class="flex items-start gap-3">
									<svg class="w-5 h-5 text-slate-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
									<div>
										<p class="text-sm font-medium text-slate-900 m-0" x-text="selectedEvent.extendedProps.guest_phone || 'N/A'"></p>
										<p class="text-xs text-slate-500 m-0">Phone</p>
									</div>
								</div>
								<div class="flex items-start gap-3">
									<svg class="w-5 h-5 text-slate-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
									<div>
										<p class="text-sm font-medium text-slate-900 m-0">
											<a :href="'mailto:'+selectedEvent.extendedProps.guest_email" class="text-primary hover:underline" x-text="selectedEvent.extendedProps.guest_email"></a>
										</p>
										<p class="text-xs text-slate-500 m-0">Email</p>
									</div>
								</div>
								<div class="flex items-start gap-3 pt-3 border-t border-slate-100" x-show="selectedEvent.extendedProps.special_requests">
									<svg class="w-5 h-5 text-slate-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
									<div class="w-full">
										<p class="text-sm font-medium text-slate-900 m-0 bg-amber-50 p-3 rounded-lg border border-amber-100" x-text="selectedEvent.extendedProps.special_requests"></p>
										<p class="text-xs text-slate-500 m-0 mt-1">Special Requests</p>
									</div>
								</div>
							</div>
						</div>
						
						<!-- Footer Actions -->
						<div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-between">
							<button @click="showModal = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm transition">
								Close
							</button>
							<div class="flex gap-2">
								<button x-show="selectedEvent.extendedProps.status === 'pending'" @click="updateStatus(selectedEvent.id, 'confirmed'); showModal = false;" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-emerald-600 text-base font-medium text-white hover:bg-emerald-700 focus:outline-none sm:w-auto sm:text-sm transition">
									Confirm
								</button>
								<button x-show="selectedEvent.extendedProps.status === 'confirmed'" @click="updateStatus(selectedEvent.id, 'completed'); showModal = false;" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:w-auto sm:text-sm transition">
									Complete
								</button>
								<button x-show="selectedEvent.extendedProps.status !== 'cancelled' && selectedEvent.extendedProps.status !== 'completed'" @click="updateStatus(selectedEvent.id, 'cancelled'); showModal = false;" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-red-600 hover:bg-red-50 focus:outline-none sm:w-auto sm:text-sm transition">
									Cancel
								</button>
								<button x-show="selectedEvent.extendedProps.status === 'pending' || selectedEvent.extendedProps.status === 'confirmed'" @click="updateStatus(selectedEvent.id, 'no_show'); showModal = false;" type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none sm:w-auto sm:text-sm transition">
									No Show
								</button>
							</div>
						</div>
					</div>
				</template>
			</div>
		</div>
	</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
	function o100ResvAdminApp() {
		return {
			viewMode: 'list',
			listData: [],
			searchQuery: '',
			statusFilter: '',
			loadingList: true,
			message: { text: '', type: '' },
			calendar: null,
			showModal: false,
			selectedEvent: null,
			bulkAction: '',
			selectedIds: [],
			isSaving: false,

			init() {
				this.fetchList();
				
				this.$watch('viewMode', value => {
					if (value === 'calendar') {
						this.$nextTick(() => {
							this.initCalendar();
						});
					} else {
						this.fetchList();
					}
				});
			},

			saveSettings() {
				const form = document.getElementById('o100_reservation');
				if (!form) return;
				
				this.isSaving = true;
				const formData = new FormData(form);
				
				fetch(window.location.href, {
					method: 'POST',
					body: formData,
				})
				.then(res => res.text())
				.then(html => {
					this.isSaving = false;
					
					// Clear dirty flag for standard WP beforeunload
					window.onbeforeunload = null;
					if (typeof jQuery !== 'undefined') {
						jQuery(window).off('beforeunload.edit-post');
					}
					
					// Show standard global toast
					if (typeof jQuery !== 'undefined') {
						var $toast = jQuery('<div class="o100-toast">' +
							'<div class="o100-toast-icon">✓</div>' +
							'<div class="o100-toast-body"><h4>Great!</h4><p>Settings Updated.</p></div>' +
							'<button class="o100-toast-close" type="button">×</button>' +
						'</div>');
						jQuery('body').append($toast);
						setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
						var removeToast = function() {
							$toast.removeClass('o100-toast--visible');
							setTimeout(function() { $toast.remove(); }, 300);
						};
						setTimeout(removeToast, 3000);
						$toast.find('.o100-toast-close').on('click', removeToast);
					}
				})
				.catch(err => {
					this.isSaving = false;
					console.error('Save error:', err);
					
					if (typeof jQuery !== 'undefined') {
						var $toast = jQuery('<div class="o100-toast" style="border-color: #ef4444;">' +
							'<div class="o100-toast-icon" style="background: #ef4444;">✗</div>' +
							'<div class="o100-toast-body"><h4>Error</h4><p>Failed to update settings.</p></div>' +
							'<button class="o100-toast-close" type="button">×</button>' +
						'</div>');
						jQuery('body').append($toast);
						setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
						var removeToast = function() {
							$toast.removeClass('o100-toast--visible');
							setTimeout(function() { $toast.remove(); }, 300);
						};
						setTimeout(removeToast, 3000);
						$toast.find('.o100-toast-close').on('click', removeToast);
					}
				});
			},

			formatDateRange(start, end) {
				const opts = { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
				let text = start.toLocaleString('en-US', opts);
				if (end) {
					text += ' - ' + end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
				}
				return text;
			},

			fetchData() {
				if (this.viewMode === 'list') {
					this.fetchList();
				} else {
					if (this.calendar) this.calendar.refetchEvents();
				}
			},

			fetchList() {
				this.loadingList = true;
				const params = new URLSearchParams({
					search: this.searchQuery,
					status: this.statusFilter
				});
				
				fetch('<?php echo esc_url_raw( rest_url( 'o100/v1/reservations' ) ); ?>?' + params.toString(), {
					headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
				})
				.then(r => r.json())
				.then(data => {
					this.listData = data.items || [];
				})
				.catch(err => console.error(err))
				.finally(() => {
					this.loadingList = false;
					this.selectedIds = [];
				});
			},

			toggleAll(e) {
				if (e.target.checked) {
					this.selectedIds = this.listData.map(i => i.id);
				} else {
					this.selectedIds = [];
				}
			},

			applyBulkAction() {
				if (!this.bulkAction || this.selectedIds.length === 0) return;
				
				if (this.bulkAction === 'delete' && !confirm('Are you sure you want to delete the selected reservations?')) {
					return;
				}

				fetch('<?php echo esc_url_raw( rest_url( 'o100/v1/reservations/bulk' ) ); ?>', {
					method: 'PATCH',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
					},
					body: JSON.stringify({
						action: this.bulkAction,
						ids: this.selectedIds
					})
				})
				.then(r => r.json())
				.then(data => {
					if (data.success) {
						this.message = { text: 'Bulk action applied successfully.', type: 'success' };
						this.fetchList();
						this.bulkAction = '';
						this.selectedIds = [];
					} else {
						this.message = { text: data.message || 'Bulk action failed.', type: 'error' };
					}
					setTimeout(() => this.message.text = '', 4000);
				})
				.catch(err => {
					console.error(err);
					this.message = { text: 'Error applying bulk action.', type: 'error' };
					setTimeout(() => this.message.text = '', 4000);
				});
			},

			updateStatus(id, status) {
				fetch('<?php echo esc_url_raw( rest_url( 'o100/v1/reservations/' ) ); ?>' + id + '/status', {
					method: 'PATCH',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
					},
					body: JSON.stringify({ status: status })
				})
				.then(r => r.json())
				.then(res => {
					if (res.success) {
						this.message = { text: 'Status updated to ' + status, type: 'success' };
						this.fetchData();
						setTimeout(() => this.message.text = '', 3000);
					} else {
						this.message = { text: res.message || 'Error updating status', type: 'error' };
					}
				});
			},

			getStatusColor(status) {
				const colors = {
					pending: '#f59e0b', // amber
					confirmed: '#10b981', // emerald
					completed: '#F59322', // blue
					cancelled: '#ef4444', // red
					no_show: '#64748b' // slate
				};
				return colors[status] || '#94a3b8';
			},

			initCalendar() {
				if (this.calendar) return; // Already initialized

				const el = this.$refs.calendarEl;
				this.calendar = new FullCalendar.Calendar(el, {
					initialView: 'timeGridWeek',
					headerToolbar: {
						left: 'prev,next today',
						center: 'title',
						right: 'dayGridMonth,timeGridWeek,timeGridDay'
					},
					height: 'auto',
					allDaySlot: false,
					slotMinTime: '08:00:00',
					slotMaxTime: '23:00:00',
					events: (info, successCallback, failureCallback) => {
						const params = new URLSearchParams({
							date_from: info.startStr.split('T')[0],
							date_to: info.endStr.split('T')[0]
						});
						fetch('<?php echo esc_url_raw( rest_url( 'o100/v1/reservations' ) ); ?>?' + params.toString(), {
							headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
						})
						.then(r => r.json())
						.then(data => {
							const events = (data.items || []).map(item => {
								const start = item.reservation_date + 'T' + item.reservation_time;
								// Assume 2 hour duration for display
								const d = new Date(start);
								d.setHours(d.getHours() + 2);
								const end = d.toISOString();
								
								return {
									id: item.id,
									title: item.guest_name + ' (' + item.party_size + ' guests)',
									start: start,
									end: end,
									backgroundColor: this.getStatusColor(item.status),
									borderColor: 'transparent',
									extendedProps: item
								};
							});
							successCallback(events);
						})
						.catch(failureCallback);
					},
					eventClick: (info) => {
						this.selectedEvent = info.event;
						this.showModal = true;
					}
				});
				this.calendar.render();
			}
		}
	}
</script>
