<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Customers_Admin {

	public static function init() {
		// Menu is registered by O100_Admin_Menu now
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

		// AJAX Endpoints
		add_action( 'wp_ajax_o100_crm_add_list', [ __CLASS__, 'ajax_add_list' ] );
		add_action( 'wp_ajax_o100_crm_delete_list', [ __CLASS__, 'ajax_delete_list' ] );
		add_action( 'wp_ajax_o100_crm_add_tag', [ __CLASS__, 'ajax_add_tag' ] );
		add_action( 'wp_ajax_o100_crm_delete_tag', [ __CLASS__, 'ajax_delete_tag' ] );
		add_action( 'wp_ajax_o100_crm_bulk_action', [ __CLASS__, 'ajax_bulk_action' ] );
		add_action( 'wp_ajax_o100_crm_bulk_sync', [ __CLASS__, 'ajax_bulk_sync' ] );
		add_action( 'wp_ajax_o100_crm_add_contact', [ __CLASS__, 'ajax_add_contact' ] );
		add_action( 'wp_ajax_o100_crm_manage_relationship', [ __CLASS__, 'ajax_manage_relationship' ] );
		add_action( 'wp_ajax_o100_crm_save_smart_tag_rules', [ __CLASS__, 'ajax_save_smart_tag_rules' ] );
		add_action( 'wp_ajax_o100_crm_dom_diag_log', [ __CLASS__, 'ajax_dom_diag_log' ] );
		add_action( 'wp_ajax_nopriv_o100_crm_dom_diag_log', [ __CLASS__, 'ajax_dom_diag_log' ] );
		add_action( 'wp_ajax_o100_crm_save_settings', [ __CLASS__, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_o100_crm_get_customers_table', [ __CLASS__, 'ajax_get_customers_table' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_csv_export' ] );
	}

	public static function register_menu() {
		// Deprecated. Handled by O100_Admin_Menu now.
	}

	public static function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'o100-customers' ) !== false ) {
			wp_enqueue_script( 'alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js', [], null, true );
			
			// Configure Tailwind to disable preflight before loading the CDN
			wp_enqueue_script( 'tailwindcss', 'https://cdn.tailwindcss.com', [], null, false );
			wp_add_inline_script( 'tailwindcss', 'window.tailwind = { config: { corePlugins: { preflight: false } } };', 'before' );
		}
	}

	public static function render_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'customers';
		?>
		<div class="o100-customers-container">
			
			<style>
				/* Scoped Preflight for Tailwind */
				:where(.o100-customers-container) *, :where(.o100-customers-container) ::before, :where(.o100-customers-container) ::after {
					box-sizing: border-box;
					border-width: 0;
					border-style: solid;
					border-color: #e5e7eb;
				}
				:where(.o100-customers-container) button, :where(.o100-customers-container) [type='button'], :where(.o100-customers-container) [type='reset'], :where(.o100-customers-container) [type='submit'] {
					-webkit-appearance: button;
					background-color: transparent;
					background-image: none;
				}
				:where(.o100-customers-container) a {
					color: inherit;
					text-decoration: inherit;
				}
				/* Revert the WordPress global button reset for internal ones that need border */
				.o100-customers-container input[type="text"],
				.o100-customers-container input[type="email"],
				.o100-customers-container input[type="number"],
				.o100-customers-container select,
				.o100-customers-container textarea {
					border-width: 1px !important;
				}

				/* Strip aggressive WordPress admin focus styles */
				.o100-customers-nav a:focus,
				.o100-customers-content a:focus,
				.o100-customers-content button:focus,
				.o100-customers-content input:focus {
					outline: none !important;
					box-shadow: none !important;
				}
				.o100-customers-content input[type="checkbox"]:focus {
					box-shadow: 0 0 0 1px #F59322 !important; /* Retain minimal Tailwind ring for checkboxes if needed, or remove */
				}
				
				/* Fallback utility classes for JIT compiler misses */
				.bg-blue-500 { background-color: #F59322 !important; }
				.hover\:bg-blue-600:hover { background-color: #F59322 !important; }
				.bg-red-500 { background-color: #ef4444 !important; }
				.hover\:bg-red-600:hover { background-color: #dc2626 !important; }
				.bg-green-600 { background-color: #16a34a !important; }
				.bg-slate-200 { background-color: #e2e8f0 !important; }
				.text-white { color: #ffffff !important; }

				/* Table Resizable Styles */
				.table-resizable {
					table-layout: fixed;
				}
				.table-resizable th {
					position: relative;
					background-clip: padding-box;
				}
				.table-resizable th .resizer {
					position: absolute;
					top: 0;
					right: 0;
					bottom: 0;
					width: 4px;
					cursor: col-resize;
					user-select: none;
					z-index: 10;
					transition: background-color 0.2s;
				}
				.table-resizable th .resizer:hover, .table-resizable th .resizing {
					background-color: #94a3b8;
				}
				/* Table Vertical Dividers */
				.table-resizable th, .table-resizable td {
					border-right: 1px solid #e2e8f0;
				}
				.table-resizable th:last-child, .table-resizable td:last-child {
					border-right: none;
				}

				/* Customers Tab Bar – scoped, immune to WP admin overrides */
				.o100-customers-tabs-bar { border-bottom: 1px solid #e2e8f0; padding: 0 32px; }
				.o100-customers-tabs-bar a.o100-tab {
					display: inline-flex !important; align-items: center !important;
					padding: 16px 4px !important; margin: 0 32px -1px 0 !important;
					font-size: 14px !important; font-weight: 500 !important;
					text-decoration: none !important; background: transparent !important;
					border: none !important; border-bottom: 2px solid transparent !important;
					color: #64748b !important; transition: all 0.15s !important;
					outline: none !important; box-shadow: none !important; cursor: pointer !important;
				}
				.o100-customers-tabs-bar a.o100-tab:hover { color: #334155 !important; border-bottom-color: #cbd5e1 !important; }
				.o100-customers-tabs-bar a.o100-tab.active { color: #F59322 !important; font-weight: 600 !important; border-bottom-color: #F59322 !important; }
				.o100-customers-tabs-bar a.o100-tab:focus { outline: none !important; box-shadow: none !important; }
			</style>
			
			<script>
				(function() {
					function initResizers() {
						const tables = document.querySelectorAll('.table-resizable');
						if(tables.length === 0) {
							// If tables not rendered yet, retry
							setTimeout(initResizers, 200);
							return;
						}
						tables.forEach(table => {
							const cols = table.querySelectorAll('th');
							[].forEach.call(cols, function (col) {
								if(col.querySelector('.resizer')) return; // Already initialized
								const resizer = document.createElement('div');
								resizer.classList.add('resizer');
								col.appendChild(resizer);
								createResizableColumn(col, resizer);
							});
						});

						function createResizableColumn(col, resizer) {
							let x = 0;
							let w = 0;
							const mouseDownHandler = function (e) {
								x = e.clientX;
								const styles = window.getComputedStyle(col);
								w = parseInt(styles.width, 10);
								document.addEventListener('mousemove', mouseMoveHandler);
								document.addEventListener('mouseup', mouseUpHandler);
								resizer.classList.add('resizing');
							};
							const mouseMoveHandler = function (e) {
								const dx = e.clientX - x;
								col.style.width = Math.max(30, (w + dx)) + 'px'; // Minimum width 30px
							};
							const mouseUpHandler = function () {
								resizer.classList.remove('resizing');
								document.removeEventListener('mousemove', mouseMoveHandler);
								document.removeEventListener('mouseup', mouseUpHandler);
							};
							resizer.addEventListener('mousedown', mouseDownHandler);
						}
					}

					// Safely initialize
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', initResizers);
					} else {
						initResizers();
					}
					// Also run on Alpine initialized in case components render late
					document.addEventListener('alpine:initialized', initResizers);
					
					// Re-init when tabs are clicked
					document.addEventListener('click', function(e) {
						if(e.target.closest('a') && e.target.closest('a').href.includes('tab=')) {
							setTimeout(initResizers, 300);
						}
					});
				})();
			</script>
			
			<!-- UNIFIED HEADER -->
			<div x-data="{ activeTab: '<?php echo esc_js($tab); ?>' }" class="o100-customers-container">
			<div class="o100-customers-page-header mb-8">
				<div class="w-full px-8">
					<div class="mb-6 pt-8">
						<h1 class="text-2xl font-bold text-slate-900 m-0 pb-1" style="font-size:1.5rem !important; font-weight:700 !important; color:#0f172a !important;"><?php esc_html_e( 'Customers', 'order100' ); ?></h1>
						<p class="text-sm text-slate-500 m-0 mt-1"><?php esc_html_e( 'Manage your customers, lists, tags and rules.', 'order100' ); ?></p>
					</div>
				</div>
				
				<div class="o100-customers-tabs-bar">
					<nav>
						<a href="#" @click.prevent="activeTab = 'customers'; history.replaceState(null, '', '?page=o100-customers&tab=customers')"
						   :class="activeTab === 'customers' ? 'o100-tab active' : 'o100-tab'">Customers</a>
						<a href="#" @click.prevent="activeTab = 'lists'; history.replaceState(null, '', '?page=o100-customers&tab=lists')"
						   :class="activeTab === 'lists' ? 'o100-tab active' : 'o100-tab'">Lists</a>
						<a href="#" @click.prevent="activeTab = 'tags'; history.replaceState(null, '', '?page=o100-customers&tab=tags')"
						   :class="activeTab === 'tags' ? 'o100-tab active' : 'o100-tab'">Tags</a>
						<a href="#" @click.prevent="activeTab = 'rules'; history.replaceState(null, '', '?page=o100-customers&tab=rules')"
						   :class="activeTab === 'rules' ? 'o100-tab active' : 'o100-tab'">Privileges &amp; Rules</a>
						<a href="#" @click.prevent="activeTab = 'settings'; history.replaceState(null, '', '?page=o100-customers&tab=settings')"
						   :class="activeTab === 'settings' ? 'o100-tab active' : 'o100-tab'">Settings</a>
					</nav>
				</div>
			</div>

			<div class="o100-customers-content w-full px-8 pb-12">
				<div x-show="activeTab === 'customers'" x-cloak>
					<?php 
						$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
						if ( $action === 'profile' ) {
							require_once O100_PATH . 'core/customers/admin/views/view-customers-profile.php';
						} else {
							require_once O100_PATH . 'core/customers/admin/views/view-customers-table.php';
						}
					?>
				</div>
				<div x-show="activeTab === 'lists'" x-cloak>
					<?php require_once O100_PATH . 'core/customers/admin/views/view-customers-lists.php'; ?>
				</div>
				<div x-show="activeTab === 'tags'" x-cloak>
					<?php require_once O100_PATH . 'core/customers/admin/views/view-customers-tags.php'; ?>
				</div>
				<div x-show="activeTab === 'rules'" x-cloak>
					<?php require_once O100_PATH . 'core/customers/admin/views/view-customers-rules.php'; ?>
				</div>
				<div x-show="activeTab === 'settings'" x-cloak>
					<?php require_once O100_PATH . 'core/customers/admin/views/view-customers-settings.php'; ?>
				</div>
			</div>
		</div>
		</div>
		<?php
	}

	public static function ajax_save_settings() {
		check_ajax_referer( 'o100_crm_settings_save', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		update_option( 'o100_crm_default_list', isset( $_POST['default_list'] ) ? intval( $_POST['default_list'] ) : 0 );
		update_option( 'o100_crm_default_tag', isset( $_POST['default_tag'] ) ? intval( $_POST['default_tag'] ) : 0 );
		update_option( 'o100_crm_cart_abandoned_time', isset( $_POST['cart_abandoned_time'] ) ? intval( $_POST['cart_abandoned_time'] ) : 30 );
		update_option( 'o100_crm_cart_lost_time', isset( $_POST['cart_lost_time'] ) ? intval( $_POST['cart_lost_time'] ) : 24 );
		update_option( 'o100_crm_one_click_unsubscribe', isset( $_POST['one_click_unsubscribe'] ) && $_POST['one_click_unsubscribe'] === 'true' ? 1 : 0 );
		update_option( 'o100_crm_data_deletion', isset( $_POST['data_deletion'] ) && $_POST['data_deletion'] === 'true' ? 1 : 0 );
		update_option( 'o100_crm_enable_optin', isset( $_POST['enable_optin'] ) && $_POST['enable_optin'] === 'true' ? 1 : 0 );
		update_option( 'o100_crm_optin_label', isset( $_POST['optin_label'] ) ? sanitize_text_field( $_POST['optin_label'] ) : 'Subscribe to our newsletter for exclusive offers!' );
		update_option( 'o100_crm_optin_default', isset( $_POST['optin_default'] ) && $_POST['optin_default'] === 'true' ? 1 : 0 );
		update_option( 'o100_crm_optin_location', isset( $_POST['optin_location'] ) ? sanitize_text_field( $_POST['optin_location'] ) : 'woocommerce_review_order_before_submit' );
		
		// Double Opt-in
		update_option( 'o100_crm_double_optin', isset( $_POST['double_optin'] ) && $_POST['double_optin'] === 'true' ? 1 : 0 );
		update_option( 'o100_crm_double_optin_subject', isset( $_POST['double_optin_subject'] ) ? sanitize_text_field( $_POST['double_optin_subject'] ) : 'Please confirm your subscription' );
		if ( isset( $_POST['double_optin_body'] ) ) {
			update_option( 'o100_crm_double_optin_body', wp_kses_post( wp_unslash( $_POST['double_optin_body'] ) ) );
		}
		update_option( 'o100_crm_double_optin_action', isset( $_POST['double_optin_action'] ) ? sanitize_text_field( $_POST['double_optin_action'] ) : 'message' );
		if ( isset( $_POST['double_optin_val'] ) ) {
			update_option( 'o100_crm_double_optin_val', wp_kses_post( wp_unslash( $_POST['double_optin_val'] ) ) );
		}

		wp_send_json_success( [ 'message' => 'Settings saved successfully.' ] );
	}

	public static function ajax_save_smart_tag_rules() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$rules_json = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : '';
		$rules = json_decode( $rules_json, true );

		if ( ! is_array( $rules ) ) {
			wp_send_json_error( [ 'message' => 'Invalid data format.' ] );
		}

		// Basic validation & sanitization could happen here, 
		// but since it's an admin setting we assume structure matches defaults
		update_option( 'o100_crm_smart_tag_rules', $rules );

		wp_send_json_success( [ 'message' => 'Rules saved successfully.' ] );
	}

	public static function ajax_add_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$is_auto = isset( $_POST['is_auto'] ) ? intval( $_POST['is_auto'] ) : 0;
		$auto_logic = isset( $_POST['auto_logic'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_logic'] ) ) : 'all';
		$auto_conditions = isset( $_POST['auto_conditions'] ) ? wp_unslash( $_POST['auto_conditions'] ) : ''; // JSON

		if ( empty( $title ) ) {
			wp_send_json_error( [ 'message' => 'Title is required.' ] );
		}

		$inserted_id = O100_Customers_DB::add_list( $title, $desc, 0, $is_auto, $auto_logic, $auto_conditions );

		if ( $inserted_id ) {
			wp_send_json_success( [ 'id' => $inserted_id, 'message' => 'List created successfully.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to create list. It might already exist or a database error occurred.' ] );
		}
	}

	public static function ajax_delete_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
		}

		$deleted = O100_Customers_DB::delete_list( $id );
		if ( $deleted ) {
			wp_send_json_success( [ 'message' => 'List deleted successfully.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to delete list. System lists cannot be deleted.' ] );
		}
	}

	public static function ajax_add_tag() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$desc = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$is_auto = isset( $_POST['is_auto'] ) ? intval( $_POST['is_auto'] ) : 0;
		$auto_logic = isset( $_POST['auto_logic'] ) ? sanitize_text_field( wp_unslash( $_POST['auto_logic'] ) ) : 'all';
		$auto_conditions = isset( $_POST['auto_conditions'] ) ? wp_unslash( $_POST['auto_conditions'] ) : ''; // JSON

		if ( empty( $title ) ) {
			wp_send_json_error( [ 'message' => 'Title is required.' ] );
		}

		$inserted_id = O100_Customers_DB::add_tag( $title, $desc, 0, $is_auto, $auto_logic, $auto_conditions );

		if ( $inserted_id ) {
			wp_send_json_success( [ 'id' => $inserted_id, 'message' => 'Tag created successfully.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to create tag. It might already exist or a database error occurred.' ] );
		}
	}

	public static function ajax_delete_tag() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => 'Invalid ID.' ] );
		}

		$deleted = O100_Customers_DB::delete_tag( $id );
		if ( $deleted ) {
			wp_send_json_success( [ 'message' => 'Tag deleted successfully.' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to delete tag. System tags cannot be deleted.' ] );
		}
	}

	public static function ajax_bulk_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$bulk_action  = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$bulk_target  = isset( $_POST['bulk_target'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_target'] ) ) : '';
		$customer_ids = isset( $_POST['customer_ids'] ) ? array_map( 'intval', (array) $_POST['customer_ids'] ) : [];

		if ( empty( $bulk_action ) || empty( $bulk_target ) || empty( $customer_ids ) ) {
			wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
		}

		global $wpdb;
		$tbl_customers = O100_Customers_DB::get_table_customers();
		$success_count = 0;

		foreach ( $customer_ids as $cid ) {
			if ( $cid <= 0 ) continue;

			switch ( $bulk_action ) {
				case 'add_tag':
					if ( O100_Customers_DB::assign_tag_to_customer( $cid, intval( $bulk_target ) ) ) {
						O100_Customers_DB::track_manual_override( $cid, intval( $bulk_target ), 'tag', 'add' );
						$success_count++;
					}
					break;
				case 'remove_tag':
					if ( O100_Customers_DB::remove_tag_from_customer( $cid, intval( $bulk_target ) ) !== false ) {
						O100_Customers_DB::track_manual_override( $cid, intval( $bulk_target ), 'tag', 'remove' );
						$success_count++;
					}
					break;
				case 'add_list':
					if ( O100_Customers_DB::assign_list_to_customer( $cid, intval( $bulk_target ) ) ) {
						O100_Customers_DB::track_manual_override( $cid, intval( $bulk_target ), 'list', 'add' );
						$success_count++;
					}
					break;
				case 'remove_list':
					if ( O100_Customers_DB::remove_list_from_customer( $cid, intval( $bulk_target ) ) !== false ) {
						O100_Customers_DB::track_manual_override( $cid, intval( $bulk_target ), 'list', 'remove' );
						$success_count++;
					}
					break;
				case 'change_status':
					$valid_statuses = [ 'subscribed', 'unsubscribed', 'bounced' ];
					if ( in_array( $bulk_target, $valid_statuses, true ) ) {
						$updated = $wpdb->update(
							$tbl_customers,
							[ 'status' => $bulk_target, 'updated_at' => current_time( 'mysql' ) ],
							[ 'id' => $cid ],
							[ '%s', '%s' ],
							[ '%d' ]
						);
						if ( $updated !== false ) {
							$success_count++;
						}
					}
					break;
				case 'delete':
					$tbl_rel = O100_Customers_DB::get_table_relationships();
					$wpdb->delete( $tbl_rel, [ 'customer_id' => $cid ], [ '%d' ] );
					$deleted = $wpdb->delete( $tbl_customers, [ 'id' => $cid ], [ '%d' ] );
					if ( $deleted ) {
						$success_count++;
					}
					break;
			}
		}

		wp_send_json_success( [ 'message' => "Successfully processed {$success_count} customers." ] );
	}

	public static function ajax_bulk_sync() {
		// Nonce check or permissions check if needed, simplified for speed
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$limit = 20;

		$orders = wc_get_orders([
			'limit'  => $limit,
			'offset' => $offset,
			'status' => [ 'completed', 'processing', 'on-hold' ],
			'orderby' => 'date',
			'order' => 'DESC'
		]);

		if ( empty( $orders ) ) {
			wp_send_json_success( [ 'message' => 'Sync completed!', 'finished' => true ] );
		}

		foreach ( $orders as $order ) {
			if ( class_exists( 'O100_Customers_Sync' ) ) {
				O100_Customers_Sync::process_order( $order );
			}
		}

		wp_send_json_success( [ 
			'message' => sprintf( 'Processed %d orders...', count($orders) ), 
			'next_offset' => $offset + count($orders),
			'finished' => false 
		] );
	}

	public static function ajax_add_contact() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}
		
		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : 'subscribed';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => 'Valid email is required.' ] );
		}

		global $wpdb;
		$tbl_customers = O100_Customers_DB::get_table_customers();
		
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl_customers} WHERE email = %s", $email ) );
		if ( $exists ) {
			wp_send_json_error( [ 'message' => 'Customer with this email already exists.' ] );
		}

		$inserted = $wpdb->insert(
			$tbl_customers,
			[
				'email'              => $email,
				'first_name'         => $first_name,
				'last_name'          => $last_name,
				'phone'              => $phone,
				'status'             => in_array( $status, ['subscribed', 'unsubscribed', 'bounced'] ) ? $status : 'subscribed',
				'acquisition_source' => 'manual',
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' )
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		wp_send_json_success( [ 'message' => 'Contact added successfully.' ] );
	}

	public static function handle_csv_export() {
		if ( isset( $_GET['o100_crm_action'] ) && $_GET['o100_crm_action'] === 'export_csv' && current_user_can( 'manage_woocommerce' ) ) {
			
			global $wpdb;
			$tbl_customers = O100_Customers_DB::get_table_customers();
			
			// For simplicity in Phase 5 export, just dump all customers (or we can apply filters if passed in GET)
			$customers = $wpdb->get_results( "SELECT * FROM {$tbl_customers} ORDER BY last_order_date DESC" );

			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="o100_customers_export_' . date('Y-m-d') . '.csv"');
			
			$output = fopen('php://output', 'w');
			fputcsv($output, ['ID', 'Email', 'First Name', 'Last Name', 'Phone', 'Total Orders', 'Total Spent', 'Status', 'Created At']);
			
			foreach ($customers as $c) {
				fputcsv($output, [
					$c->id,
					$c->email,
					$c->first_name,
					$c->last_name,
					$c->phone,
					$c->total_orders,
					$c->total_spent,
					$c->status,
					$c->created_at
				]);
			}
			
			fclose($output);
			exit;
		}
	}

	public static function ajax_manage_relationship() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		$object_id   = isset( $_POST['object_id'] ) ? intval( $_POST['object_id'] ) : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( wp_unslash( $_POST['object_type'] ) ) : '';
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : 'add';

		if ( $customer_id <= 0 || $object_id <= 0 || ! in_array( $object_type, [ 'list', 'tag' ] ) ) {
			wp_send_json_error( [ 'message' => 'Invalid parameters.' ] );
		}

		O100_Customers_DB::track_manual_override( $customer_id, $object_id, $object_type, $action_type );

		if ( $object_type === 'list' ) {
			if ( $action_type === 'add' ) {
				O100_Customers_DB::assign_list_to_customer( $customer_id, $object_id );
			} else {
				O100_Customers_DB::remove_list_from_customer( $customer_id, $object_id );
			}
		} else {
			if ( $action_type === 'add' ) {
				O100_Customers_DB::assign_tag_to_customer( $customer_id, $object_id );
			} else {
				O100_Customers_DB::remove_tag_from_customer( $customer_id, $object_id );
			}
		}

		$updated_lists = array_values( O100_Customers_DB::get_customer_lists( $customer_id ) );
		$updated_tags  = array_values( O100_Customers_DB::get_customer_tags( $customer_id ) );

		wp_send_json_success( [ 
			'message' => 'Relationship updated successfully.',
			'lists'   => $updated_lists,
			'tags'    => $updated_tags,
		] );
	}

	public static function ajax_dom_diag_log() {
		if ( isset( $_POST['log'] ) ) {
			$filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : 'dom_diagnostic.txt';
			file_put_contents( O100_PATH . $filename, sanitize_textarea_field( wp_unslash( $_POST['log'] ) ) );
		}
		wp_send_json_success();
	}
	public static function ajax_get_customers_table() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		global $wpdb;
		$tbl_customers = O100_Customers_DB::get_table_customers();

		$paged = isset( $_POST['paged'] ) ? max( 1, intval( $_POST['paged'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? max( 10, intval( $_POST['per_page'] ) ) : 20;
		
		$is_premium = function_exists('O100_License') && O100_License()->is_premium();

		$offset = ( $paged - 1 ) * $per_page;

		$filter_tag = isset( $_POST['filter_tag'] ) ? intval( $_POST['filter_tag'] ) : 0;
		$filter_list = isset( $_POST['filter_list'] ) ? intval( $_POST['filter_list'] ) : 0;
		$filter_status = isset( $_POST['filter_status'] ) ? sanitize_text_field( $_POST['filter_status'] ) : '';
		$search_query = isset( $_POST['s'] ) ? sanitize_text_field( $_POST['s'] ) : '';

		$orderby_param = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'last_order_date';
		$order_param = isset( $_POST['order'] ) && strtoupper($_POST['order']) === 'ASC' ? 'ASC' : 'DESC';

		$allowed_orderby = [
			'email' => "{$tbl_customers}.email",
			'fullName' => "{$tbl_customers}.first_name",
			'orders' => "{$tbl_customers}.total_orders",
			'spent' => "{$tbl_customers}.total_spent",
			'last_order_date' => "{$tbl_customers}.last_order_date",
			'created_at' => "{$tbl_customers}.created_at"
		];

		$orderby = isset($allowed_orderby[$orderby_param]) ? $allowed_orderby[$orderby_param] : $allowed_orderby['last_order_date'];
		// Secondary sort
		$orderby .= " $order_param, {$tbl_customers}.created_at DESC";

		$where = "1=1";
		$join = "";

		if ( $filter_tag > 0 ) {
			$tbl_rel = O100_Customers_DB::get_table_relationships();
			$join .= " INNER JOIN {$tbl_rel} r_tag ON {$tbl_customers}.id = r_tag.customer_id AND r_tag.object_type = 'tag' AND r_tag.object_id = " . intval($filter_tag);
		}
		if ( $filter_list > 0 ) {
			$tbl_rel = O100_Customers_DB::get_table_relationships();
			$join .= " INNER JOIN {$tbl_rel} r_list ON {$tbl_customers}.id = r_list.customer_id AND r_list.object_type = 'list' AND r_list.object_id = " . intval($filter_list);
		}
		if ( ! empty( $filter_status ) ) {
			$where .= $wpdb->prepare( " AND {$tbl_customers}.status = %s", $filter_status );
		}
		if ( ! empty( $search_query ) ) {
			$search_like = '%' . $wpdb->esc_like( $search_query ) . '%';
			$where .= $wpdb->prepare( " AND ({$tbl_customers}.email LIKE %s OR {$tbl_customers}.first_name LIKE %s OR {$tbl_customers}.last_name LIKE %s OR {$tbl_customers}.phone LIKE %s)", $search_like, $search_like, $search_like, $search_like );
		}

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
			$customers = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT {$tbl_customers}.* FROM {$tbl_customers} {$join} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d", $per_page, $offset ) );
		}

		// Helper required by partials
		$base_url = '#'; // Disabled base_url since we use JS clicks now

		ob_start();
		require O100_PATH . 'core/customers/admin/views/view-customers-table-rows.php';
		$html_rows = ob_get_clean();

		ob_start();
		require O100_PATH . 'core/customers/admin/views/view-customers-table-pagination.php';
		$html_pagination = ob_get_clean();

		wp_send_json_success([
			'rows' => $html_rows,
			'pagination' => $html_pagination,
			'total' => $total_items,
			'is_fake_data' => $show_fake_data
		]);
	}
}
