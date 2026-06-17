<script>
	window.tailwind = {
		config: {
			theme: {
				extend: {
					colors: {
						primary: '#F59322',
						'primary-dark': '#d97b06',
						blue: {
							50: '#fffaf5',
							100: '#fff7ed',
							200: '#fde8cd',
							300: '#fbb75c',
							400: '#f9a03c',
							500: '#F59322',
							600: '#F59322',
							700: '#d97b06',
							800: '#9a5c06',
							900: '#7a4a05',
							950: '#5c3704',
						},
						indigo: {
							50: '#fffaf5',
							100: '#fff7ed',
							200: '#fde8cd',
							300: '#fbb75c',
							400: '#f9a03c',
							500: '#F59322',
							600: '#F59322',
							700: '#d97b06',
							800: '#9a5c06',
							900: '#7a4a05',
							950: '#5c3704',
						},
					}
				}
			}
		}
	};
</script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
		/* Explicit overrides for Tailwind blue/indigo classes in Menu Maker */
		.o100-menu-maker-wrap .bg-blue-600 { background-color: #F59322 !important; }
		.o100-menu-maker-wrap .bg-blue-700 { background-color: #d97b06 !important; }
		.o100-menu-maker-wrap .bg-blue-50 { background-color: #fff7ed !important; }
		.o100-menu-maker-wrap .text-blue-600 { color: #F59322 !important; }
		.o100-menu-maker-wrap .text-blue-700 { color: #d97b06 !important; }
		.o100-menu-maker-wrap .text-blue-800 { color: #9a5c06 !important; }
		.o100-menu-maker-wrap .text-blue-400 { color: #f9a03c !important; }
		.o100-menu-maker-wrap .border-blue-500 { border-color: #F59322 !important; }
		.o100-menu-maker-wrap .border-blue-200 { border-color: #fbb75c !important; }
		.o100-menu-maker-wrap .ring-blue-500 { --tw-ring-color: #F59322 !important; }
		.o100-menu-maker-wrap .hover\:bg-blue-700:hover { background-color: #d97b06 !important; }
		.o100-menu-maker-wrap .hover\:bg-blue-600:hover { background-color: #F59322 !important; }
		.o100-menu-maker-wrap .hover\:text-blue-800:hover { color: #9a5c06 !important; }
		
		/* Indigo equivalents just in case */
		.o100-menu-maker-wrap .bg-indigo-600 { background-color: #F59322 !important; }
		.o100-menu-maker-wrap .bg-indigo-700 { background-color: #d97b06 !important; }
		.o100-menu-maker-wrap .text-indigo-600 { color: #F59322 !important; }
		.o100-menu-maker-wrap .text-indigo-900 { color: #9a5c06 !important; }

		/* Scoped Preflight for Tailwind */
		:where(.o100-menu-maker-wrap) *, :where(.o100-menu-maker-wrap) ::before, :where(.o100-menu-maker-wrap) ::after {
			box-sizing: border-box;
			border-width: 0;
			border-style: solid;
			border-color: #e5e7eb;
		}
		:where(.o100-menu-maker-wrap) button, :where(.o100-menu-maker-wrap) [type='button'], :where(.o100-menu-maker-wrap) [type='reset'], :where(.o100-menu-maker-wrap) [type='submit'] {
			-webkit-appearance: button;
			background-color: transparent;
			background-image: none;
		}
		:where(.o100-menu-maker-wrap) a {
			color: inherit;
			text-decoration: inherit;
		}
		/* Revert the WordPress global button reset for our internal ones that need border */
		.o100-menu-maker-wrap input.o100-modal-input {
			border-width: 1px !important;
		}
		/* Force flatpickr calendar above modal overlay */
		.flatpickr-calendar {
			z-index: 1000000 !important;
			border-radius: 0.5rem !important;
			box-shadow: 0 10px 25px -5px rgba(0,0,0,.15), 0 4px 6px -4px rgba(0,0,0,.1) !important;
			border: 1px solid #e2e8f0 !important;
			font-family: inherit !important;
		}
		
		/* Unify Checkboxes Style */
		.o100-menu-maker-wrap input[type="checkbox"] {
			border: 1px solid #cbd5e1 !important;
			background-color: #ffffff !important;
			border-radius: 0.25rem !important;
			cursor: pointer !important;
			width: 1rem !important;
			height: 1rem !important;
			position: relative !important;
			-webkit-appearance: none !important;
			appearance: none !important;
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			margin-right: 0.5rem !important;
			vertical-align: middle !important;
			transition: all 0.15s ease-in-out !important;
		}
		.o100-menu-maker-wrap input[type="checkbox"]::before {
			content: none !important; /* Hide WP default dashicons tick */
		}
		.o100-menu-maker-wrap input[type="checkbox"]:checked {
			background-color: #F59322 !important;
			border-color: #F59322 !important;
		}
		.o100-menu-maker-wrap input[type="checkbox"]:checked::after {
			content: '' !important;
			display: block !important;
			width: 0.25rem !important;
			height: 0.5rem !important;
			border: solid #ffffff !important;
			border-width: 0 2px 2px 0 !important;
			transform: rotate(45deg) translate(-1px, -1px) !important;
		}
		
		/* Responsive Table Sticky Columns */
		.o100-table-responsive-wrapper {
			position: relative;
			z-index: 1;
		}
		.o100-col-fixed-left {
			position: sticky !important;
			left: 0;
			z-index: 10 !important;
			background-color: inherit;
			/* We use a box-shadow to simulate border-right since divide-x might not render properly on sticky elements */
			box-shadow: inset -1px 0 0 #e2e8f0 !important;
		}
		.o100-col-fixed-right {
			position: sticky !important;
			right: 0;
			z-index: 10 !important;
			background-color: inherit;
			box-shadow: inset 1px 0 0 #e2e8f0 !important;
		}
		/* Ensure headers maintain a solid background, otherwise scrolling content shows underneath */
		thead .o100-col-fixed-left, 
		thead .o100-col-fixed-right {
			background-color: #f8fafc !important; /* bg-slate-50 */
			z-index: 20 !important; /* Keep header above sticky body cells */
		}
		tbody .o100-table-row {
			background-color: #ffffff; /* Default bg-white */
		}
		tbody .o100-table-row:hover {
			background-color: #f8fafc !important; /* hover:bg-slate-50 */
		}
		/* Also force the sticky cells to inherit the row background on hover */
		tbody .o100-table-row:hover .o100-col-fixed-left,
		tbody .o100-table-row:hover .o100-col-fixed-right {
			background-color: #f8fafc !important;
		}
		/* ═══ Prefix / Suffix Input Groups ═══ */
		.o100-flex-input-wrap {
			display: flex !important;
			align-items: stretch !important;
			width: 100% !important;
		}
		.o100-flex-input-wrap input.o100-modal-input {
			flex: 1 1 0% !important;
			min-width: 60px !important;
			margin: 0 !important;
			border: 1px solid #cbd5e1 !important;
			height: 42px !important;
			box-sizing: border-box !important;
		}
		.o100-flex-input-wrap .o100-flex-prefix,
		.o100-flex-input-wrap .o100-flex-suffix {
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			background: #f8fafc !important;
			border: 1px solid #cbd5e1 !important;
			padding: 0 15px !important;
			color: #64748b !important;
			font-size: 14px !important;
			font-weight: 500 !important;
			box-sizing: border-box !important;
			height: 42px !important;
		}
		/* With Prefix */
		.o100-flex-input-wrap.has-prefix input.o100-modal-input {
			border-radius: 0 6px 6px 0 !important;
			border-left: none !important;
		}
		.o100-flex-input-wrap.has-prefix .o100-flex-prefix {
			border-right: none !important;
			border-radius: 6px 0 0 6px !important;
		}
		/* With Suffix */
		.o100-flex-input-wrap.has-suffix input.o100-modal-input {
			border-radius: 6px 0 0 6px !important;
			border-right: none !important;
		}
		.o100-flex-input-wrap.has-suffix .o100-flex-suffix {
			border-left: none !important;
			border-radius: 0 6px 6px 0 !important;
		}
		/* Focus States */
		.o100-flex-input-wrap:focus-within input.o100-modal-input {
			box-shadow: none !important;
			border-color: #F59322 !important;
		}
		.o100-flex-input-wrap:focus-within .o100-flex-prefix,
		.o100-flex-input-wrap:focus-within .o100-flex-suffix {
			border-color: #F59322 !important;
		}
		.o100-flex-input-wrap:focus-within {
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
			border-radius: 6px !important;
		}

		/* Premium Select input override to clear WP styles */
		.o100-menu-maker-wrap select {
			width: 100% !important;
			max-width: 100% !important;
			height: 42px !important;
			line-height: normal !important;
			border: 1px solid #cbd5e1 !important;
			border-radius: 6px !important;
			padding: 8px 36px 8px 12px !important;
			font-size: 14px !important;
			color: #0f172a !important;
			background-color: #ffffff !important;
			transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
			box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
			box-sizing: border-box !important;
			-webkit-appearance: none !important;
			appearance: none !important;
			background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E") !important;
			background-position: right 12px center !important;
			background-repeat: no-repeat !important;
			background-size: 1.25rem !important;
			cursor: pointer !important;
		}
		.o100-menu-maker-wrap select:focus {
			border-color: #F59322 !important;
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
			outline: none !important;
		}

		/* Unify Select and Input styling to override WP Core Admin CSS */
	.o100-menu-maker-wrap select.o100-select-unified {
		height: 38px !important;
		border: 1px solid #cbd5e1 !important; /* border-slate-300 */
		border-radius: 0.375rem !important; /* rounded-md */
		color: #1e293b !important;
		padding-top: 0.5rem !important;
		padding-bottom: 0.5rem !important;
		padding-left: 0.75rem !important;
		padding-right: 2.5rem !important; /* Space for the chevron */
		background-color: #fff !important;
		background-position: right 8px center !important;
		width: 160px !important;
		max-width: 160px !important;
		min-width: 160px !important;
		display: inline-block !important;
		flex-shrink: 0 !important;
	}
	.o100-menu-maker-wrap select.o100-select-unified:focus {
		border-color: #F59322 !important; /* focus:border-blue-500 */
		box-shadow: 0 0 0 1px #F59322 !important; /* focus:ring-blue-500 */
		outline: none !important;
	}
	.o100-menu-maker-wrap input[type="search"].o100-search-unified {
		height: 38px !important;
		border: 1px solid #cbd5e1 !important;
		border-radius: 0.375rem !important;
		padding-top: 0.5rem !important;
		padding-bottom: 0.5rem !important;
		padding-left: 2.25rem !important; /* Space for the search icon */
		background-color: #fff !important;
	}
	.o100-menu-maker-wrap input[type="search"].o100-search-unified:focus {
		border-color: #F59322 !important;
		box-shadow: 0 0 0 1px #F59322 !important;
		outline: none !important;
	}
	
	/* Force hide spin buttons on number inputs */
	.o100-hide-spin-buttons::-webkit-outer-spin-button,
	.o100-hide-spin-buttons::-webkit-inner-spin-button {
		-webkit-appearance: none !important;
		margin: 0 !important;
		display: none !important;
	}
	.o100-hide-spin-buttons {
		-moz-appearance: textfield !important;
	}
	
	/* Prevent Alpine/Tailwind FOUC */
	[x-cloak] { display: none !important; }

	/* Foolproof tab visibility */
	.o100-tab-content { display: none !important; }
	.o100-tab-content.is-active { display: block !important; }

	/* Scoped Tab Bar - immune to WP admin CSS overrides */
	.o100-mm-tabs-bar { border-bottom: 1px solid #e2e8f0; padding: 0 32px; }
	.o100-mm-tabs-bar a.o100-tab {
		display: inline-flex !important; align-items: center !important;
		padding: 16px 4px !important; margin: 0 32px -1px 0 !important;
		font-size: 14px !important; font-weight: 500 !important;
		text-decoration: none !important; background: transparent !important;
		border: none !important; border-bottom: 2px solid transparent !important;
		color: #64748b !important; transition: all 0.15s !important;
		outline: none !important; box-shadow: none !important; cursor: pointer !important;
	}
	.o100-mm-tabs-bar a.o100-tab:hover { color: #334155 !important; border-bottom-color: #cbd5e1 !important; }
	.o100-mm-tabs-bar a.o100-tab.active { color: #F59322 !important; font-weight: 600 !important; border-bottom-color: #F59322 !important; }
	.o100-mm-tabs-bar a.o100-tab:focus { outline: none !important; box-shadow: none !important; }
</style>
<div class="o100-menu-maker-wrap" x-data="o100MenuMaker()" x-cloak>
	
	<!-- UNIFIED HEADER -->
	<div class="o100-menu-maker-page-header mb-2">
		<script>
		window.addEventListener('error', function(event) {
			fetch('/wp-content/plugins/order100/diag-log.php', {
				method: 'POST',
				body: JSON.stringify({ message: event.message, filename: event.filename, lineno: event.lineno, error: event.error ? event.error.stack : null })
			});
		});
		window.addEventListener('unhandledrejection', function(event) {
			fetch('/wp-content/plugins/order100/diag-log.php', {
				method: 'POST',
				body: JSON.stringify({ message: 'Unhandled Rejection: ' + event.reason })
			});
		});
		</script>
		<div class="w-full px-8">
			<div class="mb-6 pt-8">
				<h1 class="text-2xl font-bold text-slate-900 m-0 pb-1" style="font-size:1.5rem !important; font-weight:700 !important; color:#0f172a !important;"><?php esc_html_e( 'Menu Management', 'order100' ); ?></h1>
				<p class="text-sm text-slate-500 m-0 mt-1"><?php esc_html_e( 'Centrally manage your categories, menu items, and options.', 'order100' ); ?></p>
			</div>
		</div>
		
		<div class="o100-mm-tabs-bar flex justify-between items-center pr-8">
			<nav class="flex">
				<?php
				$tabs = array(
					'categories' => __( 'Categories', 'order100' ),
					'items'      => __( 'Menu Items', 'order100' ),
					'modifiers'  => __( 'Options & Add-ons', 'order100' ),
					'publish'    => __( 'Storefront', 'order100' ),
					'menu_rules' => __( 'Menu Rules', 'order100' ),
				);
				foreach ( $tabs as $tab_id => $tab_label ) {
					?>
					<a href="#"
						class="o100-tab"
						:class="activeTab === '<?php echo esc_js( $tab_id ); ?>' ? 'active' : ''"
						@click.prevent="activeTab = '<?php echo esc_js( $tab_id ); ?>'"
						><?php echo esc_html( $tab_label ); ?></a>
					<?php
				}
				?>
			</nav>
			<style>
				.o100-mm-save-btn {
					background-color: #F59322 !important;
					color: #ffffff !important;
					font-weight: 700 !important;
					font-size: 14px !important;
					padding: 8px 16px !important;
					border-radius: 12px !important;
					border: none !important;
					box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
					cursor: pointer !important;
					transition: background-color 0.15s ease !important;
					margin-bottom: 8px !important;
				}
				.o100-mm-save-btn:hover { background-color: #d97b06 !important; }
				.o100-mm-save-btn:disabled { opacity: 0.7 !important; cursor: not-allowed !important; }
			</style>
			<button type="button" x-show="activeTab === 'menu_rules'" @click="saveMenuRulesSettings()" class="o100-mm-save-btn" style="display:none;" :disabled="isSaving" x-text="isSaving ? 'Saving...' : 'Save Settings'">Save Settings</button>
		</div>
	</div>

	<!-- Main Content Area -->
	<div class="w-full pt-4 pb-12" style="padding-left:40px;padding-right:40px;">
		
		<!-- Categories Tab -->
		<div class="o100-tab-content" :class="{'is-active': activeTab === 'categories'}" x-cloak>
			<?php 
			if ( file_exists( O100_PATH . 'core/menu-maker/views/tab-categories.php' ) ) {
				include O100_PATH . 'core/menu-maker/views/tab-categories.php'; 
			} else {
				echo '<p>Categories management coming soon...</p>';
			}
			?>
		</div>

		<!-- Items Tab -->
		<div class="o100-tab-content" :class="{'is-active': activeTab === 'items'}" x-cloak>
			<?php 
			if ( file_exists( O100_PATH . 'core/menu-maker/views/tab-items.php' ) ) {
				include O100_PATH . 'core/menu-maker/views/tab-items.php'; 
			} else {
				echo '<p>Card-based items management coming soon...</p>';
			}
			?>
		</div>

		<!-- Modifiers Tab -->
		<div class="o100-tab-content" :class="{'is-active': activeTab === 'modifiers'}" x-cloak>
			<?php 
			if ( file_exists( O100_PATH . 'core/menu-maker/views/tab-modifiers.php' ) ) {
				include O100_PATH . 'core/menu-maker/views/tab-modifiers.php'; 

			} else {
				// Fallback to legacy CMB2 rendering if we just decoupled it
				echo '<p>Modifiers management loading...</p>';
			}
			?>
		</div>

		<!-- Publish Tab -->
		<div class="o100-tab-content" :class="{'is-active': activeTab === 'publish'}" x-cloak>
			<?php 
			if ( file_exists( O100_PATH . 'core/menu-maker/views/tab-publish.php' ) ) {
				include O100_PATH . 'core/menu-maker/views/tab-publish.php'; 
			} else {
				echo '<p>Menu Builder loading...</p>';
			}
			?>
		</div>

		<!-- Menu Rules Tab -->
		<div class="o100-tab-content" :class="{'is-active': activeTab === 'menu_rules'}" x-cloak>
			<h2 class="text-xl font-bold text-slate-800 mb-4">Menu Rules</h2>
			<div class="o100-cmb2-settings-wrap">
				<?php 
				if ( function_exists('cmb2_metabox_form') ) {
					cmb2_metabox_form( 'o100_menu_rules', 'o100_menu_rules', array(
						'save_button' => __('Save Menu Rules', 'order100'),
						'form_format' => '<form class="cmb-form" method="post" id="%1$s" enctype="multipart/form-data" encoding="multipart/form-data"><input type="hidden" name="object_id" value="%2$s"><input type="hidden" name="submit-cmb" value="1">%3$s</form>',
					) ); 
				}
				?>
			</div>
		</div>

	</div>

	<!-- Unsaved Changes Modal -->
	<div id="o100-unsaved-overlay" x-cloak x-show="showUnsavedModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.5); z-index:999998; backdrop-filter:blur(4px);">
		<div id="o100-unsaved-modal" @click.away="showUnsavedModal = false" style="position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:16px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:32px; width:420px; max-width:90vw; z-index:999999;">
			<div style="text-align:center; margin-bottom:20px;">
				<div style="width:48px; height:48px; border-radius:50%; background:#fef3c7; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
					<span class="dashicons dashicons-warning" style="color:#f59e0b; font-size:24px; width:24px; height:24px;"></span>
				</div>
				<h3 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;">Unsaved Changes</h3>
				<p style="margin:0; font-size:14px; color:#64748b; line-height:1.5;">You have unsaved changes on this page.<br>What would you like to do?</p>
			</div>
			<div style="display:flex; gap:10px; justify-content:center;">
				<button type="button" @click="showUnsavedModal = false" style="padding:10px 20px; border:1px solid #e2e8f0; background:#fff; color:#475569; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer;">Cancel</button>
				<button type="button" @click="discardAndGo()" style="padding:10px 20px; border:1px solid #fca5a5; background:#fef2f2; color:#dc2626; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer;">Discard</button>
				<button type="button" @click="saveAndGo()" style="padding:10px 20px; border:none; background:#F59322; color:#fff; font-size:14px; font-weight:600; border-radius:8px; cursor:pointer;">Save & Go</button>
			</div>
		</div>
	</div>


</div>
<script>
// Wrap or define window.o100Confirm to support both callback and promise-based invocation
(function() {
	if (typeof window.o100Confirm === 'function') {
		const originalConfirm = window.o100Confirm;
		// If it's already wrapped, don't wrap it again
		if (!originalConfirm.isWrapped) {
			window.o100Confirm = function(title, message, callback) {
				return new Promise((resolve) => {
					originalConfirm(title, message, function(confirmed) {
						if (typeof callback === 'function') {
							callback(confirmed);
						}
						resolve(confirmed);
					});
				});
			};
			window.o100Confirm.isWrapped = true;
		}
	} else {
		window.o100Confirm = function(title, message, callback) {
			const result = confirm(message);
			if (typeof callback === 'function') {
				callback(result);
			}
			return Promise.resolve(result);
		};
	}
})();

document.addEventListener('alpine:init', () => {
		Alpine.data('o100MenuMaker', () => ({
			activeTab: '<?php echo esc_js( $active_tab ); ?>',
			
			isSaving: false,
			isDirty: false,
			showUnsavedModal: false,
			pendingUrl: '',


			
			discardAndGo() {
				if (this.pendingUrl) {
					window.onbeforeunload = null;
					if(typeof jQuery !== 'undefined') jQuery(window).off('beforeunload.edit-post');
					window.location.href = this.pendingUrl;
				}
			},

			async saveAndGo() {
				await this.saveMenuRulesSettings();
				if (!this.isDirty && this.pendingUrl) {
					window.onbeforeunload = null;
					if(typeof jQuery !== 'undefined') jQuery(window).off('beforeunload.edit-post');
					window.location.href = this.pendingUrl;
				}
			},

			saveMenuRulesSettings() {
				const form = document.getElementById('o100_menu_rules');
				if (!form) return;
				
				this.isSaving = true;
				const formData = new FormData(form);
				
				fetch(window.location.href, {
					method: 'POST',
					body: formData,
				})
				.then(res => res.text())
				.then(html => {
					this.isSaving = false; this.isDirty = false;
					window.onbeforeunload = null;
					if (typeof jQuery !== 'undefined') {
						jQuery(window).off('beforeunload.edit-post');
						var $toast = jQuery('<div class="o100-toast"><div class="o100-toast-icon">✓</div><div class="o100-toast-body"><h4>Great!</h4><p>Settings Updated.</p></div><button class="o100-toast-close" type="button">×</button></div>');
						jQuery('body').append($toast);
						setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
						var removeToast = function() { $toast.removeClass('o100-toast--visible'); setTimeout(function() { $toast.remove(); }, 300); };
						setTimeout(removeToast, 3000);
						$toast.find('.o100-toast-close').on('click', removeToast);
					}
				})
				.catch(err => {
					this.isSaving = false; this.isDirty = false;
					if (typeof jQuery !== 'undefined') {
						var $toast = jQuery('<div class="o100-toast" style="border-color: #ef4444;"><div class="o100-toast-icon" style="background: #ef4444;">✗</div><div class="o100-toast-body"><h4>Error</h4><p>Failed to update settings.</p></div><button class="o100-toast-close" type="button">×</button></div>');
						jQuery('body').append($toast);
						setTimeout(function() { $toast.addClass('o100-toast--visible'); }, 50);
						var removeToast = function() { $toast.removeClass('o100-toast--visible'); setTimeout(function() { $toast.remove(); }, 300); };
						setTimeout(removeToast, 3000);
						$toast.find('.o100-toast-close').on('click', removeToast);
					}
				});
			},

			// Categories state
			categories: [],
			branches: [],
			tags: [],
			items: [], // Global branches
			globalLabels: <?php echo wp_json_encode( $labels ); ?>,
			globalModifiers: [],
			loadingCategories: false,

			// Items state
			items: [],
			loadingItems: false,
			filters: { category: '0', branch: 'all', stock: 'all', promo: 'all', rules: 'all', search: '', sortField: 'date', sortDir: 'desc', catSearch: '', catSort: 'order_asc' },
			viewMode: 'grid', // grid or list
			selectedItems: [],

			// Pagination
			currentPage: 1,
			itemsPerPage: '20',
			iconSearch: '',
			iconLimit: 100,
			get filteredCustomIcons() {
				let results = this.customIcons;
				if(this.iconSearch.trim() !== '') {
					const search = this.iconSearch.toLowerCase();
					results = results.filter(icon => icon.name.toLowerCase().includes(search));
				}
				return results.slice(0, this.iconLimit);
			},
			get hasMoreIcons() {
				let results = this.customIcons;
				if(this.iconSearch.trim() !== '') {
					const search = this.iconSearch.toLowerCase();
					results = results.filter(icon => icon.name.toLowerCase().includes(search));
				}
				return results.length > this.iconLimit;
			},


			// Modals state
			modals: {
				confirm: { open: false, loading: false, type: '', id: 0, message: '' },
				category: {
					open: false,
					saving: false,
					data: { id: 0, name: '', description: '', image_id: 0, image_url: '', order: 0, icon_type: 'none', icon: '', branches: ['all'] }
				},
				item: {
					open: false,
					saving: false,
					loading: false,
					step: 1,
					data: {
						id: 0, title: '', excerpt: '', description: '', regular_price: '', sale_price: '', stock_status: 'instock', category_ids: [], image_id: 0, image_url: '',
						gallery: [], labels: [], addon_exclude: false, addon_options: [], 
						rule_methods: ['delivery', 'pickup', 'dinein'], rule_start: '', rule_end: '', impacts: [],
						rule_days: ['mon','tue','wed','thu','fri','sat','sun'], rule_time_start: '', rule_time_end: '', rule_branches: ['all']
					}
				}
			},

			get filteredItems() {
				let result = JSON.parse(JSON.stringify(this.items || []));
				
				if (this.filters.category !== '0') {
					result = result.filter(i => i.category_id == this.filters.category);
				}

				if (this.filters.search.trim() !== '') {
					const searchLower = this.filters.search.toLowerCase();
					result = result.filter(i => i.title.toLowerCase().includes(searchLower) || i.category_name.toLowerCase().includes(searchLower));
				}

				if (this.filters.stock !== 'all') {
					result = result.filter(i => i.stock_status === this.filters.stock);
				}

				if (this.filters.promo !== 'all') {
					if (this.filters.promo === 'yes') {
						result = result.filter(i => i.promotions && i.promotions.length > 0);
					} else {
						result = result.filter(i => !i.promotions || i.promotions.length === 0);
					}
				}

				if (this.filters.rules === 'has_rules') {
					result = result.filter(i => i.has_rules);
				} else if (this.filters.rules === 'no_rules') {
					result = result.filter(i => !i.has_rules);
				}

				if (this.filters.branch !== 'all') {
					// Items inherit branches from their category
					result = result.filter(i => {
						const cat = this.categories.find(c => c.id == i.category_id);
						return cat && (cat.branches.includes('all') || cat.branches.includes(this.filters.branch));
					});
				}

				const sf = this.filters.sortField || 'date';
				const asc = this.filters.sortDir === 'asc';
				result.sort((a, b) => {
					let cmp = 0;
					if (sf === 'name') {
						cmp = (a.title || '').localeCompare(b.title || '');
					} else if (sf === 'price') {
						cmp = parseFloat(a.price || 0) - parseFloat(b.price || 0);
					} else if (sf === 'stock') {
						cmp = (a.stock_status || '').localeCompare(b.stock_status || '');
					} else if (sf === 'rules') {
						cmp = (a.has_rules === b.has_rules) ? 0 : (a.has_rules ? -1 : 1);
					} else {
						cmp = parseInt(a.id || 0) - parseInt(b.id || 0); // date = by ID
					}
					return asc ? cmp : -cmp;
				});

				return result;
			},

			get filteredCategories() {
				// Deep clone to ensure Alpine proxies are not mutated by sort
				let result = JSON.parse(JSON.stringify(this.categories || []));
				if (this.filters.catSearch.trim() !== '') {
					const searchLower = this.filters.catSearch.toLowerCase();
					result = result.filter(c => (c.name || '').toLowerCase().includes(searchLower));
				}
				
				if (this.filters.catSort === 'name_asc') {
					result.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
				} else if (this.filters.catSort === 'name_desc') {
					result.sort((a, b) => (b.name || '').localeCompare(a.name || ''));
				} else if (this.filters.catSort === 'order_asc') {
					result.sort((a, b) => parseInt(a.order || 0) - parseInt(b.order || 0));
				} else if (this.filters.catSort === 'order_desc') {
					result.sort((a, b) => parseInt(b.order || 0) - parseInt(a.order || 0));
				} else {
					result.sort((a, b) => parseInt(a.order || 0) - parseInt(b.order || 0)); // default order
				}
				
				return result;
			},

			get paginatedItems() {
				if (this.itemsPerPage === 'all') return this.filteredItems;
				const limit = parseInt(this.itemsPerPage);
				const start = (this.currentPage - 1) * limit;
				return this.filteredItems.slice(start, start + limit);
			},

			get totalPages() {
				if (this.itemsPerPage === 'all') return 1;
				const limit = parseInt(this.itemsPerPage);
				return Math.max(1, Math.ceil(this.filteredItems.length / limit));
			},

			get allSelected() {
				return this.filteredItems.length > 0 && this.selectedItems.length === this.filteredItems.length;
			},

			toggleSelectAll() {
				if (this.allSelected) {
					this.selectedItems = [];
				} else {
					this.selectedItems = this.filteredItems.map(i => i.id);
				}
			},

			init() {
				// Update URL when tab changes
				this.$watch('activeTab', (value) => {
					const url = new URL(window.location);
					url.searchParams.set('tab', value);
					window.history.pushState({}, '', url);

					// Lazy load data
					if (value === 'categories' && this.categories.length === 0) this.fetchCategories();
					if (value === 'modifiers' && this.categories.length === 0) this.fetchCategories();
					if (value === 'publish' && this.categories.length === 0) this.fetchCategories();
					if (value === 'items' && this.items.length === 0) {
						this.fetchCategories();
						this.fetchItems();
					}
				});

				// Reset pagination on filter or itemsPerPage change
				this.$watch('filters', () => { this.currentPage = 1; }, { deep: true });
				this.$watch('itemsPerPage', () => { this.currentPage = 1; });

				// Initial loadfetch
				if (this.activeTab === 'categories') this.fetchCategories();
				if (this.activeTab === 'modifiers') this.fetchCategories();
				if (this.activeTab === 'publish') this.fetchCategories();
				if (this.activeTab === 'items') {
					this.fetchCategories();
					this.fetchItems();
				}
			},

			renderCategoryBadges(categories, isList = false) {
				if (!categories || !Array.isArray(categories) || categories.length === 0) {
					return isList 
						? '<span class="bg-slate-100 text-slate-700 text-[11px] px-1.5 py-0.5 rounded font-medium truncate max-w-full">Uncategorized</span>'
						: '<span class="bg-slate-900/70 backdrop-blur-sm text-white text-xs px-2 py-1 rounded font-medium shadow-sm truncate max-w-full">Uncategorized</span>';
				}
				const uniqueMap = new Map();
				categories.forEach(c => {
					if (c && c.name) {
						uniqueMap.set(c.name, c.name);
					}
				});
				const unique = Array.from(uniqueMap.values());
				
				if (isList) {
					return unique.map(name => '<span class="bg-slate-100 text-slate-700 text-[11px] px-1.5 py-0.5 rounded font-medium truncate max-w-full">' + name + '</span>').join('');
				} else {
					return unique.map(name => '<span class="bg-slate-900/70 backdrop-blur-sm text-white text-xs px-2 py-1 rounded font-medium shadow-sm truncate max-w-full">' + name + '</span>').join('');
				}
			},

			initSortable() {
				if (typeof jQuery !== 'undefined' && jQuery.fn.sortable) {
					jQuery('#o100-cat-sortable').sortable({
						handle: '.o100-drag-handle',
						axis: 'y',
						update: (event, ui) => {
							const sortedIds = jQuery('#o100-cat-sortable').sortable('toArray', { attribute: 'data-id' });
							this.saveCategoryOrder(sortedIds);
						}
					});
				}
			},

			async apiRequest(route, method = 'GET', data = {}) {
				let url = '/wp-json/o100/v1/menu-maker' + route;
				let options = {
					method: method,
					headers: {
						'X-WP-Nonce': '<?php echo wp_create_nonce( "wp_rest" ); ?>'
					}
				};

				if (method === 'GET' && Object.keys(data).length > 0) {
					const params = new URLSearchParams();
					for (const key in data) {
						if (Array.isArray(data[key])) {
							if (data[key].length > 0 && typeof data[key][0] === 'object' && data[key][0] !== null) {
								params.append(key, JSON.stringify(data[key]));
							} else if (key === '_options' || key === '_con_logic' || key === 'addon_options') {
								params.append(key, JSON.stringify(data[key]));
							} else {
								params.append(key, data[key].join(','));
							}
						} else {
							params.append(key, data[key]);
						}
					}
					url += '?' + params.toString();
				} else if (method !== 'GET') {
					options.headers['Content-Type'] = 'application/json';
					let finalData = {};
					for (const key in data) {
						if (Array.isArray(data[key])) {
							if (data[key].length > 0 && typeof data[key][0] === 'object' && data[key][0] !== null) {
								finalData[key] = data[key];
							} else if (key === '_options' || key === '_con_logic' || key === 'addon_options') {
								finalData[key] = data[key];
							} else {
								finalData[key] = data[key].join(',');
							}
						} else {
							finalData[key] = data[key];
						}
					}
					options.body = JSON.stringify(finalData);
				}

				try {
					const response = await fetch(url, options);
					const result = await response.json();
					if (!response.ok) {
						alert('Error: ' + (result.message || 'Unknown error'));
						return null;
					}
					return result;
				} catch (error) {
					alert('Network error: ' + error.message);
					return null;
				}
			},
			customIcons: [],

			async fetchCategories() {
				this.loadingCategories = true;
				const [data, iconsData, modsData] = await Promise.all([
					this.apiRequest('/categories', 'GET'),
					this.apiRequest('/icons', 'GET'),
					this.apiRequest('/modifiers', 'GET')
				]);
				
				if (data) {
					this.categories = data.categories;
					this.branches = data.branches;
					this.tags = data.tags || [];
					setTimeout(() => { this.initSortable(); }, 0);
				}
				if (iconsData) {
					this.customIcons = iconsData;
				}
				if (modsData) {
					let parsedMods = modsData;
					if (!Array.isArray(parsedMods) && typeof parsedMods === 'object') {
						parsedMods = Object.values(parsedMods);
					}
					if (Array.isArray(parsedMods)) {
						this.globalModifiers = parsedMods;
					}
				}
				this.loadingCategories = false;
			},

			getDatePart(field, part) {
				const v = this.modals.item.data[field] || '';
				if (!v || !v.includes('-')) return '';
				const p = v.split('-');
				if (part === 'y') return p[0] || '';
				if (part === 'm') return p[1] || '';
				if (part === 'd') return p[2] || '';
				return '';
			},
			setDatePart(field, part, value) {
				const current = this.modals.item.data[field] || '';
				let p = current && current.includes('-') ? current.split('-') : ['', '', ''];
				if (part === 'y') p[0] = value;
				if (part === 'm') p[1] = value;
				if (part === 'd') p[2] = value;
				if (p[0] && p[1] && p[2]) {
					this.modals.item.data[field] = p.join('-');
				} else if (!value) {
					this.modals.item.data[field] = '';
				}
			},

			getTimeH(field) {
				const v = this.modals.item.data[field] || '';
				if (!v) return '--';
				const h = parseInt(v.split(':')[0]);
				return h === 0 ? 12 : (h > 12 ? h - 12 : h);
			},
			getTimeM(field) {
				const v = this.modals.item.data[field] || '';
				if (!v) return '--';
				return String(parseInt(v.split(':')[1]) || 0).padStart(2, '0');
			},
			getTimePeriod(field) {
				const v = this.modals.item.data[field] || '';
				if (!v) return 'am';
				return parseInt(v.split(':')[0]) >= 12 ? 'pm' : 'am';
			},
			adjustTime(field, part, delta) {
				let v = this.modals.item.data[field];
				if (!v) v = '08:00';
				let [h, m] = v.split(':').map(Number);
				if (part === 'h') {
					h = (h + delta + 24) % 24;
				} else {
					m = (m + delta + 60) % 60;
				}
				this.modals.item.data[field] = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
			},
			setTimePeriod(field, period) {
				let v = this.modals.item.data[field];
				if (!v) v = '08:00';
				let [h, m] = v.split(':').map(Number);
				const cur = h >= 12 ? 'pm' : 'am';
				if (cur !== period) h = (h + 12) % 24;
				this.modals.item.data[field] = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
			},
			clearTime(field) {
				this.modals.item.data[field] = '';
			},

			initDatePickers() {
				if (typeof flatpickr === 'undefined') return;
				const self = this;
				// Date pickers only (time is now inline component)
				document.querySelectorAll('.o100-fp-date').forEach(el => {
					if (el._flatpickr) el._flatpickr.destroy();
					const field = el.getAttribute('x-model').replace('modals.item.data.', '');
					flatpickr(el, {
						dateFormat: 'Y-m-d',
						allowInput: false,
						onChange: function(selectedDates, dateStr) {
							self.modals.item.data[field] = dateStr;
						}
					});
				});
				// Close all date pickers on any scroll event (using capture phase to catch scroll inside modal overlay or modal body)
				if (!window._fpGlobalScrollBound) {
					window._fpGlobalScrollBound = true;
					document.addEventListener('scroll', () => {
						document.querySelectorAll('.o100-fp-date').forEach(el => {
							if (el._flatpickr && el._flatpickr.isOpen) el._flatpickr.close();
						});
					}, true);
				}
			},

			async saveCategoryOrder(sortedIds) {
				await this.apiRequest('/categories/reorder', 'PATCH', { orders: sortedIds.join(',') });
			},

			async fetchItems() {
				this.loadingItems = true;
				const data = await this.apiRequest('/items', 'GET');
				if (data) {
					// CRITICAL: Deep clone to break all Proxy references, completely avoiding Alpine v3 DOM duplication bugs
					this.items = JSON.parse(JSON.stringify(data));
					this.selectedItems = [];
				}
				this.loadingItems = false;
			},

			openCategoryModal(cat = null) {
				if (cat) {
					this.modals.category.data = JSON.parse(JSON.stringify(cat));
					this.modals.category.data.branches = Array.isArray(this.modals.category.data.branches) ? this.modals.category.data.branches : ['all'];
				} else {
					const nextOrder = this.categories.length > 0 ? this.categories[this.categories.length - 1].order + 1 : 0;
					this.modals.category.data = { id: 0, name: '', description: '', image_id: 0, image_url: '', order: nextOrder, icon_type: 'none', icon: '', branches: ['all'] };
				}
				this.modals.category.open = true;
			},

			toggleBranchSelection(branchId) {
				let branches = this.modals.category.data.branches || [];
				branchId = String(branchId);
				branches = branches.map(String);
				
				if (branchId === 'all') {
					branches = ['all'];
				} else {
					branches = branches.filter(b => b !== 'all');
					if (branches.includes(branchId)) {
						branches = branches.filter(b => b !== branchId);
					} else {
						branches.push(branchId);
					}
					if (branches.length === 0) branches = ['all'];
				}
				this.modals.category.data.branches = branches;
			},

			async saveCategory() {
				if (!this.modals.category.data.name) {
					alert('<?php esc_attr_e( 'Category name is required.', 'order100' ); ?>');
					return;
				}
				this.modals.category.saving = true;
				const result = await this.apiRequest('/categories', 'POST', this.modals.category.data);
				if (result) {
					this.modals.category.open = false;
					await this.fetchCategories();
				}
				this.modals.category.saving = false;
			},

			async quickSaveCategoryOrder(cat) {
				await this.apiRequest('/categories', 'POST', cat);
				// We don't fetchCategories to avoid interrupting the user's focus, 
				// but sorting handles it nicely.
			},

			async deleteCategory(id) {
				if (!await window.o100Confirm('Delete Category', '<?php esc_attr_e( 'Are you sure you want to delete this category?', 'order100' ); ?>')) return;
				const result = await this.apiRequest('/categories', 'DELETE', { id: id });
				if (result !== null) {
					this.categories = this.categories.filter(c => c.id != id);
				}
			},

			duplicateCategory(cat) {
				let dup = JSON.parse(JSON.stringify(cat));
				dup.id = 0;
				dup.name = dup.name + ' (Copy)';
				this.openCategoryModal(dup);
			},

			async duplicateItem(item) {
				await this.openItemModal(item);
				this.modals.item.data.id = 0;
				this.modals.item.data.title = this.modals.item.data.title + ' (Copy)';
			},

			async openItemModal(item = null) {
				if (item) {
					this.modals.item.step = 1;
					this.modals.item.open = true;
					this.modals.item.loading = true;
					
					// Clone deeply to prevent reactive state leak on cancel
					this.modals.item.data = JSON.parse(JSON.stringify(item));
					
					// Guarantee array initialization to prevent rendering crashes
					this.modals.item.data.category_ids = Array.isArray(this.modals.item.data.category_ids) ? this.modals.item.data.category_ids.map(String) : [];
					this.modals.item.data.labels = Array.isArray(this.modals.item.data.labels) ? this.modals.item.data.labels : [];
					this.modals.item.data.gallery = Array.isArray(this.modals.item.data.gallery) ? this.modals.item.data.gallery : [];
					this.modals.item.data.addon_options = [];
					this.modals.item.data.impacts = Array.isArray(this.modals.item.data.impacts) ? this.modals.item.data.impacts : [];
					this.modals.item.data.rule_methods = Array.isArray(this.modals.item.data.rule_methods) ? this.modals.item.data.rule_methods : ['delivery', 'pickup', 'dinein'];
					this.modals.item.data.rule_branches = Array.isArray(this.modals.item.data.rule_branches) ? this.modals.item.data.rule_branches : ['all'];
					this.modals.item.data.rule_days = Array.isArray(this.modals.item.data.rule_days) ? this.modals.item.data.rule_days : ['mon','tue','wed','thu','fri','sat','sun'];
					
					// Fetch full details
					const details = await this.apiRequest('/items/details', 'GET', { id: item.id });
					if (details) {
						let newAddonOptions = Array.isArray(details.addon_options) ? details.addon_options.map(g => {
							// Migrate old schema to new schema
							return {
								_id: g._id || 'o100-id' + Math.floor(Math.random()*1000000),
								_name: g._name || g.name || '',
								_type: g._type || g.type || 'radio',
								_display_type: g._display_type || '',
								_is_woo_var: g._is_woo_var || 'no',
								_required: g._required === 'yes' || g.required ? 'yes' : 'no',
								_min_op: g._min_op || g.min_op || '1',
								_max_op: g._max_op || g.max_op || '1',
								_enb_img: g._enb_img || g.enb_img || 'no',
								_enb_qty: g._enb_qty || g.enb_qty || 'no',
								_min_opqty: g._min_opqty || g.min_opqty || '',
								_max_opqty: g._max_opqty || g.max_opqty || '',
								_price_type: g._price_type || '',
								_price: g._price || '',
								_expanded: true,
								_options: Array.isArray(g._options || g.options) ? (g._options || g.options).map(o => ({
									vid: o.vid || 0,
									name: o.name || '',
									price: o.price || '',
									sale_price: o.sale_price || '',
									type: o.type || 'fixed',
									min: o.min || '',
									max: o.max || '',
									def: o.def || o.default || 'no',
									dis: o.dis || o.disable || 'no',
									image: o.image || ''
								})) : []
							};
						}) : [];

						// Merge arrays properly to trigger Alpine reactivity
						this.modals.item.data = { 
							...this.modals.item.data, 
							...details,
							impacts: Array.isArray(details.impacts) ? details.impacts : (details.impacts && typeof details.impacts === 'object' ? Object.values(details.impacts) : []),
							category_ids: Array.isArray(details.category_ids) ? details.category_ids.map(String) : [],
							rule_methods: Array.isArray(details.rule_methods) && details.rule_methods.length > 0 ? details.rule_methods : ['delivery', 'pickup', 'dinein'],
							rule_branches: (Array.isArray(details.rule_branches) && details.rule_branches.filter(v => String(v).trim() !== '').length > 0) ? details.rule_branches.map(String).filter(v => v.trim() !== '') : ['all'],
							rule_days: Array.isArray(details.rule_days) && details.rule_days.length > 0 ? details.rule_days : ['mon','tue','wed','thu','fri','sat','sun'],
							rule_time_start: details.rule_time_start || '',
							rule_time_end: details.rule_time_end || '',
							labels: Array.isArray(details.labels) ? details.labels : [],
							gallery: Array.isArray(details.gallery) ? details.gallery : [],
							addon_options: newAddonOptions
						};
					}
					this.modals.item.loading = false;
				} else {
					this.modals.item.data = { 
						id: 0, title: '', excerpt: '', description: '', regular_price: '', sale_price: '', stock_status: 'instock', 
						category_ids: this.filters.category !== '0' ? [this.filters.category] : [], 
						image_id: 0, image_url: '', gallery: [], labels: [], addon_exclude: false, addon_options: [], 
						rule_methods: ['delivery', 'pickup'], rule_start: '', rule_end: '',
						rule_days: ['mon','tue','wed','thu','fri','sat','sun'], rule_time_start: '', rule_time_end: '',
						rule_branches: ['all']
					};
					this.modals.item.step = 1;
					this.modals.item.open = true;
				}
				setTimeout(() => { this.initDatePickers(); }, 0);
			},

			nextStep() {
				if (this.modals.item.step < 5) this.modals.item.step++;
			},

			prevStep() {
				if (this.modals.item.step > 1) this.modals.item.step--;
			},

			setStep(step) {
				this.modals.item.step = step;
			},

			getApplicableGlobalModifiers() {
				if (!this.globalModifiers) return [];
				if (this.modals.item.data.addon_exclude) return [];
				return this.globalModifiers.filter(mod => {
					if (!mod._apply_to || mod._apply_to === 'all') return true;
					if (mod._apply_to === 'categories') {
						const modCats = Array.isArray(mod._category_ids) ? mod._category_ids : String(mod._category_ids).split(',').map(s=>s.trim()).filter(s=>s);
						const itemCats = Array.isArray(this.modals.item.data.categories) ? this.modals.item.data.categories.map(c => String(typeof c === 'object' ? c.id : c)) : [];
						return modCats.some(catId => itemCats.includes(String(catId)));
					}
					if (mod._apply_to === 'products') {
						const modProds = Array.isArray(mod._product_ids) ? mod._product_ids : String(mod._product_ids).split(',').map(s=>s.trim()).filter(s=>s);
						return modProds.includes(String(this.modals.item.data.id));
					}
					return false;
				});
			},

			async saveItem() {
				if (!this.modals.item.data.title || !this.modals.item.data.regular_price) {
					alert('<?php esc_attr_e( 'Name and regular price are required.', 'order100' ); ?>');
					return;
				}
				this.modals.item.saving = true;
				
				const payload = { ...this.modals.item.data };
				// Stringify addon options before sending, since apiRequest will flatten arrays
				payload.addon_options = JSON.stringify(payload.addon_options);
				// Arrays like category_ids, gallery, labels will be automatically joined by comma in apiRequest

				const result = await this.apiRequest('/items', 'POST', payload);
				if (result) {
					this.modals.item.open = false;
					await this.fetchItems();
				}
				this.modals.item.saving = false;
			},

			async deleteItem(id) {
				if (!await window.o100Confirm('Delete Item', '<?php esc_attr_e( 'Are you sure you want to delete this item?', 'order100' ); ?>')) return;
				const result = await this.apiRequest('/items', 'DELETE', { id: id });
				if (result !== null) {
					this.items = this.items.filter(i => i.id != id);
				}
			},

			async bulkEditItems(action) {
				if (this.selectedItems.length === 0) return;
				
				if (action === 'delete') {
					if (!await window.o100Confirm('Delete Items', `<?php esc_attr_e( 'Are you sure you want to delete', 'order100' ); ?> ${this.selectedItems.length} <?php esc_attr_e( 'items?', 'order100' ); ?>`)) return;
					const result = await this.apiRequest('/items/bulk', 'PATCH', { ids: this.selectedItems.join(','), bulk_action: 'delete' });
					if (result !== null) {
						this.items = this.items.filter(i => !this.selectedItems.includes(i.id));
						this.selectedItems = [];
					}
					return;
				}

				const result = await this.apiRequest('/items/bulk', 'PATCH', { ids: this.selectedItems.join(','), bulk_action: action });
				if (result !== null) {
					this.selectedItems = [];
					await this.fetchItems();
				}
			},

			openMediaUploader(type) {
				const isGallery = type === 'item_gallery';
				const modalData = type === 'category' ? this.modals.category.data : this.modals.item.data;
				
				const frame = wp.media({
					title: isGallery ? '<?php esc_attr_e( 'Select Gallery Images', 'order100' ); ?>' : '<?php esc_attr_e( 'Select or Upload Image', 'order100' ); ?>',
					button: { text: isGallery ? '<?php esc_attr_e( 'Add to gallery', 'order100' ); ?>' : '<?php esc_attr_e( 'Use this image', 'order100' ); ?>' },
					multiple: isGallery ? 'add' : false
				});

				frame.on('select', () => {
					if (isGallery) {
						const attachments = frame.state().get('selection').toJSON();
						attachments.forEach(attachment => {
							if (!modalData.gallery.find(img => img.id === attachment.id)) {
								modalData.gallery.push({ id: attachment.id, url: attachment.url });
							}
						});
					} else {
						const attachment = frame.state().get('selection').first().toJSON();
						modalData.image_id = attachment.id;
						modalData.image_url = attachment.url;
					}
				});

				frame.open();
			},

		}))
	});
</script>

