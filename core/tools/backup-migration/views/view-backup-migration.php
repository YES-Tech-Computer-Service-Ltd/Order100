<?php
/**
 * View: Backup & Migration tab in Tools
 *
 * Configures the HTML & JavaScript for the data backup, category migration,
 * and GloriaFood import tool.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ajax_nonce = wp_create_nonce( 'o100_backup_migration' );
?>

<div class="o100-backup-migration-wrapper" x-data="o100BackupMigration()" x-cloak>
	
	<!-- CSS Styles Overrides for Premium Aesthetic -->
	<style>
		.o100-btn-primary {
			background-color: #F59322 !important;
			color: #ffffff !important;
			border: none !important;
			border-radius: 8px !important;
			font-weight: 600 !important;
			font-size: 13px !important;
			padding: 8px 16px !important;
			cursor: pointer !important;
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			gap: 6px !important;
			transition: all 0.2s ease !important;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
			height: 38px !important;
			text-decoration: none !important;
			line-height: 1 !important;
		}
		.o100-btn-primary:hover {
			background-color: #d97b06 !important;
			color: #ffffff !important;
		}
		.o100-btn-primary:disabled {
			opacity: 0.5 !important;
			cursor: not-allowed !important;
		}

		.o100-btn-secondary {
			background-color: #ffffff !important;
			color: #374151 !important;
			border: 1px solid #d1d5db !important;
			border-radius: 8px !important;
			font-weight: 500 !important;
			font-size: 13px !important;
			padding: 8px 16px !important;
			cursor: pointer !important;
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			gap: 6px !important;
			transition: all 0.15s ease !important;
			height: 38px !important;
			text-decoration: none !important;
			line-height: 1 !important;
		}
		.o100-btn-secondary:hover {
			background-color: #f9fafb !important;
			border-color: #cbd5e1 !important;
			color: #111827 !important;
		}
		.o100-btn-secondary:disabled {
			opacity: 0.5 !important;
			cursor: not-allowed !important;
		}

		.o100-btn-danger {
			background-color: #ef4444 !important;
			color: #ffffff !important;
			border: none !important;
			border-radius: 8px !important;
			font-weight: 600 !important;
			font-size: 13px !important;
			padding: 8px 16px !important;
			cursor: pointer !important;
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			gap: 6px !important;
			transition: all 0.2s ease !important;
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
			height: 38px !important;
			text-decoration: none !important;
			line-height: 1 !important;
		}
		.o100-btn-danger:hover {
			background-color: #dc2626 !important;
			color: #ffffff !important;
		}
		.o100-btn-danger:disabled {
			opacity: 0.5 !important;
			cursor: not-allowed !important;
		}

		.o100-btn-small {
			height: 32px !important;
			padding: 4px 12px !important;
			font-size: 12px !important;
			border-radius: 6px !important;
		}

		.o100-status-alert.success {
			background-color: #ecfdf5;
			border: 1px solid #10b981;
			color: #065f46;
		}
		.o100-status-alert.error {
			background-color: #fef2f2;
			border: 1px solid #ef4444;
			color: #991b1b;
		}
	</style>
	
	<!-- 1. Full Backup Option (Refactored to Grid Column Layout) -->
	<div class="o100-settings-group-card">
		<div class="o100-settings-group-title">
			<h3><?php esc_html_e( '1. Full Configuration Backup & Restore', 'order100' ); ?></h3>
			<p><?php esc_html_e( 'Safely export or restore all Order100 core configurations. The systems are visually isolated below for maximum precision.', 'order100' ); ?></p>
		</div>
		<div class="o100-settings-group-content">
			<div class="grid grid-cols-1 md:grid-cols-2 gap-8 divide-y md:divide-y-0 md:divide-x divide-slate-200">
				<!-- Export column -->
				<div class="pb-6 md:pb-0 md:pr-8">
					<h4 class="text-sm font-bold text-slate-800 mb-2"><?php esc_html_e( 'Export Configurations', 'order100' ); ?></h4>
					<p class="text-xs text-slate-500 mb-6" style="line-height:1.6; min-height:42px;">
						<?php esc_html_e( 'Download a complete JSON package of your store configurations (hours, bounds, timeslots, and customization rules) to store or migrate to another site.', 'order100' ); ?>
					</p>
					
					<div>
						<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=o100_export_full_config' ) ); ?>" class="o100-btn-primary">
							<span class="dashicons dashicons-download" style="vertical-align:middle; margin-top:-1px;"></span>
							<?php esc_html_e( 'Download Configuration Backup', 'order100' ); ?>
						</a>
					</div>
				</div>
				
				<!-- Restore/Import column -->
				<div class="pt-6 md:pt-0 md:pl-8">
					<h4 class="text-sm font-bold text-slate-800 mb-2"><?php esc_html_e( 'Restore Configurations', 'order100' ); ?></h4>
					<p class="text-xs text-slate-500 mb-6" style="line-height:1.6; min-height:42px;">
						<?php esc_html_e( 'Upload an exported JSON backup file to overwrite current options. A temporary auto-backup will be captured first for rollback safety.', 'order100' ); ?>
					</p>
					
					<div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
						<label class="o100-btn-secondary" style="position:relative; overflow:hidden;">
							<span class="dashicons dashicons-upload" style="vertical-align:middle; margin-top:-1px;"></span>
							<span><?php esc_html_e( 'Select File', 'order100' ); ?></span>
							<input type="file" accept=".json" @change="onConfigFileSelected($event)" style="position:absolute; left:0; top:0; opacity:0; cursor:pointer; width:100%; height:100%;">
						</label>
						
						<!-- File Chip -->
						<template x-if="configFileName">
							<div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
								<span class="dashicons dashicons-document" style="font-size:14px; width:14px; height:14px; line-height:14px; color:#64748b;"></span>
								<span x-text="configFileName" class="max-w-[150px] truncate font-semibold"></span>
							</div>
						</template>

						<button type="button" class="o100-btn-danger" :disabled="!configFile || isRestoring" @click="restoreFullConfig()">
							<span x-text="isRestoring ? 'Restoring...' : 'Run Restore'">Run Restore</span>
						</button>
					</div>
				</div>
			</div>
			
			<template x-if="configStatus">
				<div class="o100-status-alert" :class="configStatusSuccess ? 'success' : 'error'" style="margin-top:20px; padding:12px 16px; border-radius:6px; font-weight:600; font-size:13px;" x-text="configStatus"></div>
			</template>
		</div>
	</div>

	<!-- 2. Category-Specific Data Migration -->
	<div class="o100-settings-group-card">
		<div class="o100-settings-group-title">
			<h3><?php esc_html_e( '2. Category-Specific Data Migration', 'order100' ); ?></h3>
			<p><?php esc_html_e( 'Independently export or import specific chunks of data, such as product catalog, customer CRM records (including loyalty balances), and marketing discount campaigns.', 'order100' ); ?></p>
		</div>
		<div class="o100-settings-group-content">
			<table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none;">
				<thead>
					<tr>
						<th style="padding:12px; font-weight:700; color:#374151;"><?php esc_html_e( 'Data Category', 'order100' ); ?></th>
						<th style="padding:12px; font-weight:700; color:#374151;"><?php esc_html_e( 'Export Action', 'order100' ); ?></th>
						<th style="padding:12px; font-weight:700; color:#374151;"><?php esc_html_e( 'Import File Select', 'order100' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<!-- Product Catalog -->
					<tr>
						<td style="padding:12px; font-weight:600; color:#1f2937; vertical-align:middle;"><?php esc_html_e( 'Product Catalog & Modifiers', 'order100' ); ?></td>
						<td style="padding:12px; vertical-align:middle;">
							<div style="display:inline-flex; align-items:center; gap:8px;">
								<select x-model="catalogFormat" class="o100-fluent-select" style="height:32px; padding:0 8px; border-radius:6px; border:1px solid #d1d5db; font-size:12px; background-color:#fff; color:#374151; cursor:pointer; outline:none;">
									<option value="json">JSON</option>
									<option value="csv">CSV</option>
								</select>
								<a :href="'<?php echo esc_url( admin_url( 'admin-ajax.php?action=o100_export_category&type=catalog' ) ); ?>&format=' + catalogFormat" class="o100-btn-secondary o100-btn-small">
									<span class="dashicons dashicons-download" style="font-size:16px; line-height:20px;"></span> <?php esc_html_e( 'Export Catalog', 'order100' ); ?>
								</a>
							</div>
						</td>
						<td style="padding:12px; vertical-align:middle;">
							<div style="display:flex; align-items:center; gap:8px;">
								<label class="o100-btn-secondary o100-btn-small" style="position:relative; overflow:hidden;">
									<span x-text="catalogFileName ? catalogFileName : 'Choose File'">Choose File</span>
									<input type="file" accept=".json" @change="onCatFileSelected($event, 'catalog')" style="position:absolute; left:0; top:0; opacity:0; cursor:pointer; width:100%; height:100%;">
								</label>
								
								<!-- File Chip -->
								<template x-if="catalogFileName">
									<div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
										<span class="dashicons dashicons-document" style="font-size:13px; width:13px; height:13px; line-height:13px; color:#64748b;"></span>
										<span x-text="catalogFileName" class="max-w-[120px] truncate font-semibold"></span>
									</div>
								</template>

								<button type="button" class="o100-btn-primary o100-btn-small" :disabled="!catalogFile || isImportingCat['catalog']" @click="importCategory('catalog')">
									<span x-text="isImportingCat['catalog'] ? 'Importing...' : 'Import'">Import</span>
								</button>
							</div>
						</td>
					</tr>
					
					<!-- Customer CRM -->
					<tr>
						<td style="padding:12px; font-weight:600; color:#1f2937; vertical-align:middle;"><?php esc_html_e( 'Customer CRM & Loyalty Records', 'order100' ); ?></td>
						<td style="padding:12px; vertical-align:middle;">
							<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=o100_export_category&type=customers' ) ); ?>" class="o100-btn-secondary o100-btn-small">
								<span class="dashicons dashicons-download" style="font-size:16px; line-height:20px;"></span> <?php esc_html_e( 'Export Customers', 'order100' ); ?>
							</a>
						</td>
						<td style="padding:12px; vertical-align:middle;">
							<div style="display:flex; align-items:center; gap:8px;">
								<label class="o100-btn-secondary o100-btn-small" style="position:relative; overflow:hidden;">
									<span x-text="customersFileName ? customersFileName : 'Choose File'">Choose File</span>
									<input type="file" accept=".json" @change="onCatFileSelected($event, 'customers')" style="position:absolute; left:0; top:0; opacity:0; cursor:pointer; width:100%; height:100%;">
								</label>
								
								<!-- File Chip -->
								<template x-if="customersFileName">
									<div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
										<span class="dashicons dashicons-document" style="font-size:13px; width:13px; height:13px; line-height:13px; color:#64748b;"></span>
										<span x-text="customersFileName" class="max-w-[120px] truncate font-semibold"></span>
									</div>
								</template>

								<button type="button" class="o100-btn-primary o100-btn-small" :disabled="!customersFile || isImportingCat['customers']" @click="importCategory('customers')">
									<span x-text="isImportingCat['customers'] ? 'Importing...' : 'Import'">Import</span>
								</button>
							</div>
						</td>
					</tr>
					
					<!-- Campaign & Marketing -->
					<tr>
						<td style="padding:12px; font-weight:600; color:#1f2937; vertical-align:middle;"><?php esc_html_e( 'Promotions & Campaign Rules', 'order100' ); ?></td>
						<td style="padding:12px; vertical-align:middle;">
							<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=o100_export_category&type=promotions' ) ); ?>" class="o100-btn-secondary o100-btn-small">
								<span class="dashicons dashicons-download" style="font-size:16px; line-height:20px;"></span> <?php esc_html_e( 'Export Campaigns', 'order100' ); ?>
							</a>
						</td>
						<td style="padding:12px; vertical-align:middle;">
							<div style="display:flex; align-items:center; gap:8px;">
								<label class="o100-btn-secondary o100-btn-small" style="position:relative; overflow:hidden;">
									<span x-text="promotionsFileName ? promotionsFileName : 'Choose File'">Choose File</span>
									<input type="file" accept=".json" @change="onCatFileSelected($event, 'promotions')" style="position:absolute; left:0; top:0; opacity:0; cursor:pointer; width:100%; height:100%;">
								</label>
								
								<!-- File Chip -->
								<template x-if="promotionsFileName">
									<div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
										<span class="dashicons dashicons-document" style="font-size:13px; width:13px; height:13px; line-height:13px; color:#64748b;"></span>
										<span x-text="promotionsFileName" class="max-w-[120px] truncate font-semibold"></span>
									</div>
								</template>

								<button type="button" class="o100-btn-primary o100-btn-small" :disabled="!promotionsFile || isImportingCat['promotions']" @click="importCategory('promotions')">
									<span x-text="isImportingCat['promotions'] ? 'Importing...' : 'Import'">Import</span>
								</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			
			<template x-if="catStatus">
				<div class="o100-status-alert" :class="catStatusSuccess ? 'success' : 'error'" style="margin-top:20px; padding:12px 16px; border-radius:6px; font-weight:600; font-size:13px;" x-text="catStatus"></div>
			</template>
		</div>
	</div>

	<!-- 3. Migrate from Third-Party Platforms (GloriaFood) -->
	<div class="o100-settings-group-card" style="border-left:4px solid #10b981;">
		<div class="o100-settings-group-title">
			<h3 style="color:#0f766e;"><span class="dashicons dashicons-migrate" style="vertical-align:middle; margin-top:-2px; margin-right:4px;"></span><?php esc_html_e( '3. Migrate from GloriaFood', 'order100' ); ?></h3>
			<p><?php esc_html_e( 'Directly import a GloriaFood menu JSON export. The system will automatically recreate categories, products (SKU check mapping), and option groups/toppings, assigning options to respective items instantly.', 'order100' ); ?></p>
		</div>
		<div class="o100-settings-group-content">
			<div style="display:flex; align-items:center; flex-wrap:wrap; gap:16px;">
				<label class="o100-btn-secondary" style="cursor:pointer; position:relative; overflow:hidden;">
					<span class="dashicons dashicons-media-text" style="vertical-align:middle; margin-top:-1px;"></span>
					<span x-text="gfFileName ? gfFileName : 'Select GloriaFood Menu JSON'">Select GloriaFood Menu JSON</span>
					<input type="file" accept=".json" @change="onGfFileSelected($event)" style="position:absolute; left:0; top:0; opacity:0; cursor:pointer; width:100%; height:100%;">
				</label>
				
				<!-- File Chip -->
				<template x-if="gfFileName">
					<div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
						<span class="dashicons dashicons-document" style="font-size:14px; width:14px; height:14px; line-height:14px; color:#64748b;"></span>
						<span x-text="gfFileName" class="max-w-[200px] truncate font-semibold"></span>
					</div>
				</template>

				<button type="button" class="o100-btn-primary" style="background:#10b981 !important; border-color:#10b981 !important;" :disabled="!gfFile || isImportingGf" @click="importGloriaFood()">
					<span x-text="isImportingGf ? 'Running Migration...' : 'Run Migration'">Run Migration</span>
				</button>
			</div>
			
			<template x-if="gfStatus">
				<div class="o100-status-alert" :class="gfStatusSuccess ? 'success' : 'error'" style="margin-top:20px; padding:12px 16px; border-radius:6px; font-weight:600; font-size:13px;" x-text="gfStatus"></div>
			</template>
		</div>
	</div>

</div>

<script>
function o100BackupMigration() {
	return {
		// Full config state
		configFile: null,
		configFileName: '',
		isRestoring: false,
		configStatus: '',
		configStatusSuccess: true,

		// Category migration state
		catalogFormat: 'json',
		catalogFile: null,
		catalogFileName: '',
		customersFile: null,
		customersFileName: '',
		promotionsFile: null,
		promotionsFileName: '',
		isImportingCat: {
			catalog: false,
			customers: false,
			promotions: false
		},
		catStatus: '',
		catStatusSuccess: true,

		// GloriaFood importer state
		gfFile: null,
		gfFileName: '',
		isImportingGf: false,
		gfStatus: '',
		gfStatusSuccess: true,

		init() {
			// Check if OPcache clear output is needed
		},

		onConfigFileSelected(e) {
			const files = e.target.files;
			if (files.length) {
				this.configFile = files[0];
				this.configFileName = files[0].name;
			}
		},

		onCatFileSelected(e, cat) {
			const files = e.target.files;
			if (files.length) {
				this[cat + 'File'] = files[0];
				this[cat + 'FileName'] = files[0].name;
			}
		},

		onGfFileSelected(e) {
			const files = e.target.files;
			if (files.length) {
				this.gfFile = files[0];
				this.gfFileName = files[0].name;
			}
		},

		restoreFullConfig() {
			if (!this.configFile) return;
			if (!confirm('Are you sure you want to overwrite all system configurations? This will replace your operating schedules, delivery boundaries, and options configurations.')) {
				return;
			}

			this.isRestoring = true;
			this.configStatus = 'Uploading and restoring configurations...';
			this.configStatusSuccess = true;

			const formData = new FormData();
			formData.append('action', 'o100_import_full_config');
			formData.append('nonce', '<?php echo esc_js( $ajax_nonce ); ?>');
			formData.append('file', this.configFile);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				this.isRestoring = false;
				if (data.success) {
					this.configStatus = data.data.message;
					this.configStatusSuccess = true;
					this.configFile = null;
					this.configFileName = '';
					setTimeout(() => { window.location.reload(); }, 2000);
				} else {
					this.configStatus = data.data.message;
					this.configStatusSuccess = false;
				}
			})
			.catch(err => {
				this.isRestoring = false;
				this.configStatus = 'Error restoring configuration: ' + err.message;
				this.configStatusSuccess = false;
			});
		},

		importCategory(cat) {
			const file = this[cat + 'File'];
			if (!file) return;

			this.isImportingCat[cat] = true;
			this.catStatus = 'Importing ' + cat + ' data...';
			this.catStatusSuccess = true;

			const formData = new FormData();
			formData.append('action', 'o100_import_category');
			formData.append('type', cat);
			formData.append('nonce', '<?php echo esc_js( $ajax_nonce ); ?>');
			formData.append('file', file);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				this.isImportingCat[cat] = false;
				if (data.success) {
					this.catStatus = data.data.message;
					this.catStatusSuccess = true;
					this[cat + 'File'] = null;
					this[cat + 'FileName'] = '';
				} else {
					this.catStatus = data.data.message;
					this.catStatusSuccess = false;
				}
			})
			.catch(err => {
				this.isImportingCat[cat] = false;
				this.catStatus = 'Error importing data: ' + err.message;
				this.catStatusSuccess = false;
			});
		},

		importGloriaFood() {
			if (!this.gfFile) return;

			this.isImportingGf = true;
			this.gfStatus = 'Parsing GloriaFood menu export and migrating items...';
			this.gfStatusSuccess = true;

			const formData = new FormData();
			formData.append('action', 'o100_import_gloriafood');
			formData.append('nonce', '<?php echo esc_js( $ajax_nonce ); ?>');
			formData.append('file', this.gfFile);

			fetch(ajaxurl, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				this.isImportingGf = false;
				if (data.success) {
					this.gfStatus = data.data.message;
					this.gfStatusSuccess = true;
					this.gfFile = null;
					this.gfFileName = '';
				} else {
					this.gfStatus = data.data.message;
					this.gfStatusSuccess = false;
				}
			})
			.catch(err => {
				this.isImportingGf = false;
				this.gfStatus = 'Error migrating from GloriaFood: ' + err.message;
				this.gfStatusSuccess = false;
			});
		}
	};
}
</script>
