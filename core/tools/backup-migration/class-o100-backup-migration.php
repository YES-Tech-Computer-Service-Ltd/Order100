<?php
/**
 * Order100 Backup & Migration Manager
 *
 * Handles export/import of full configuration, category-specific data,
 * and integration with GloriaFood importer.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Backup_Migration {

	/**
	 * Options keys included in full config export/restore
	 */
	private static $config_keys = array(
		'o100_delivery',
		'o100_pickup',
		'o100_reservation',
		'o100_store_hours',
		'o100_product_options',
		'o100_menu_rules',
		'o100_menu_shortcodes',
		'o100_misc',
		'o100_seo',
		'o100_tip_delivery_enable',
		'o100_tip_pickup_enable',
		'o100_tip_delivery_val',
		'o100_tip_pickup_val',
		'o100_locations_status',
		'o100_crm_default_list',
		'o100_crm_default_tag',
		'o100_crm_cart_abandoned_time',
		'o100_crm_cart_lost_time',
		'o100_crm_one_click_unsubscribe',
		'o100_crm_data_deletion',
		'o100_crm_enable_optin',
		'o100_crm_optin_label',
		'o100_crm_optin_default',
		'o100_crm_optin_location',
		'o100_crm_double_optin',
		'o100_crm_double_optin_subject',
		'o100_crm_double_optin_body',
		'o100_crm_double_optin_action',
		'o100_crm_double_optin_val',
		'o100_crm_smart_tag_rules'
	);

	/**
	 * Initialize actions and AJAX endpoints
	 */
	public static function init() {
		// AJAX exports (triggered via direct links)
		add_action( 'wp_ajax_o100_export_full_config', array( __CLASS__, 'ajax_export_full_config' ) );
		add_action( 'wp_ajax_o100_export_category', array( __CLASS__, 'ajax_export_category' ) );

		// AJAX imports (triggered via FormData uploads)
		add_action( 'wp_ajax_o100_import_full_config', array( __CLASS__, 'ajax_import_full_config' ) );
		add_action( 'wp_ajax_o100_import_category', array( __CLASS__, 'ajax_import_category' ) );
		add_action( 'wp_ajax_o100_import_gloriafood', array( __CLASS__, 'ajax_import_gloriafood' ) );
	}

	/**
	 * Export all system configurations as JSON file
	 */
	public static function ajax_export_full_config() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'order100' ) );
		}

		$config_data = array();
		foreach ( self::$config_keys as $key ) {
			$config_data[ $key ] = get_option( $key );
		}

		$payload = array(
			'type'      => 'order100_full_config',
			'version'   => '1.5',
			'timestamp' => time(),
			'domain'    => parse_url( site_url(), PHP_URL_HOST ),
			'data'      => $config_data,
		);

		$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$filename = 'order100-config-export-' . date( 'Y-m-d-H-i' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		echo $json;
		exit;
	}

	/**
	 * Import and overwrite system configurations
	 */
	public static function ajax_import_full_config() {
		check_ajax_referer( 'o100_backup_migration', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'order100' ) ) );
		}

		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'order100' ) ) );
		}

		$file_path = $_FILES['file']['tmp_name'];
		$content   = file_get_contents( $file_path );
		$payload   = json_decode( $content, true );

		if ( empty( $payload ) || ! is_array( $payload ) || empty( $payload['type'] ) || $payload['type'] !== 'order100_full_config' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup file format.', 'order100' ) ) );
		}

		// Check internal data
		$data = isset( $payload['data'] ) ? $payload['data'] : array();
		if ( empty( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'No configuration data found in backup.', 'order100' ) ) );
		}

		// Perform automatic configuration backup before overwriting (Rollback prevention)
		$current_backup = array();
		foreach ( self::$config_keys as $key ) {
			$current_backup[ $key ] = get_option( $key );
		}
		update_option( 'o100_config_rollback_temp', array(
			'timestamp' => time(),
			'data'      => $current_backup,
		) );

		// Update options
		$updated_count = 0;
		foreach ( self::$config_keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				update_option( $key, $data[ $key ] );
				$updated_count++;
			}
		}

		wp_send_json_success( array(
			'message' => sprintf( __( 'Configuration restored successfully. Updated %d keys. A temporary rollback backup was saved.', 'order100' ), $updated_count )
		) );
	}

	/**
	 * Export category-specific data
	 */
	public static function ajax_export_category() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'order100' ) );
		}

		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		$format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'json';
		$data = array();

		if ( $type === 'catalog' ) {
			// Export Categories
			$categories = get_terms( array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			) );
			$cat_list = array();
			foreach ( $categories as $cat ) {
				$cat_list[] = array(
					'term_id'     => $cat->term_id,
					'name'        => $cat->name,
					'slug'        => $cat->slug,
					'description' => $cat->description,
					'parent'      => $cat->parent,
				);
			}

			// Export Products
			$products = get_posts( array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			) );
			$prod_list = array();
			foreach ( $products as $prod ) {
				$product_id = $prod->ID;
				
				// Get associated category slugs
				$terms = wp_get_object_terms( $product_id, 'product_cat' );
				$cat_slugs = array();
				$cat_names = array();
				foreach ( $terms as $t ) {
					$cat_slugs[] = $t->slug;
					$cat_names[] = $t->name;
				}

				$prod_list[] = array(
					'title'         => $prod->post_title,
					'description'   => $prod->post_content,
					'status'        => $prod->post_status,
					'price'         => get_post_meta( $product_id, '_price', true ),
					'regular_price' => get_post_meta( $product_id, '_regular_price', true ),
					'sale_price'    => get_post_meta( $product_id, '_sale_price', true ),
					'stock_status'  => get_post_meta( $product_id, '_stock_status', true ),
					'sku'           => get_post_meta( $product_id, '_sku', true ),
					'categories'    => $cat_slugs,
					'category_names'=> $cat_names,
					'addons'        => get_post_meta( $product_id, '_product_addons', true ),
				);
			}

			if ( $format === 'csv' ) {
				// Generate CSV content
				$headers = array( 'SKU', 'Name', 'Regular Price', 'Sale Price', 'Stock Status', 'Categories', 'Description' );
				$output  = fopen( 'php://temp', 'r+' );
				fputcsv( $output, $headers );

				foreach ( $prod_list as $prod ) {
					fputcsv( $output, array(
						$prod['sku'],
						$prod['title'],
						$prod['regular_price'],
						$prod['sale_price'],
						$prod['stock_status'],
						implode( ', ', $prod['category_names'] ),
						wp_strip_all_tags( $prod['description'] )
					) );
				}

				rewind( $output );
				$csv_content = stream_get_contents( $output );
				fclose( $output );

				$filename = 'order100-catalog-export-' . date( 'Y-m-d-H-i' ) . '.csv';
				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
				echo $csv_content;
				exit;
			}

			$data = array(
				'categories' => $cat_list,
				'products'   => $prod_list,
				'modifiers'  => get_option( 'o100_product_options', array() ),
			);

		} elseif ( $type === 'customers' ) {
			global $wpdb;
			
			$tbl_customers     = $wpdb->prefix . 'o100_customers';
			$tbl_lists         = $wpdb->prefix . 'o100_crm_lists';
			$tbl_tags          = $wpdb->prefix . 'o100_crm_tags';
			$tbl_relationships = $wpdb->prefix . 'o100_crm_relationships';
			$tbl_rules         = $wpdb->prefix . 'o100_customer_rules';
			$tbl_loyalty_acct  = $wpdb->prefix . 'o100_loyalty_accounts';

			$data = array(
				'customers'     => $wpdb->get_results( "SELECT * FROM {$tbl_customers}", ARRAY_A ),
				'crm_lists'     => $wpdb->get_results( "SELECT * FROM {$tbl_lists}", ARRAY_A ),
				'crm_tags'      => $wpdb->get_results( "SELECT * FROM {$tbl_tags}", ARRAY_A ),
				'relationships' => $wpdb->get_results( "SELECT * FROM {$tbl_relationships}", ARRAY_A ),
				'rules'         => $wpdb->get_results( "SELECT * FROM {$tbl_rules}", ARRAY_A ),
				'loyalty_accts' => $wpdb->get_results( "SELECT * FROM {$tbl_loyalty_acct}", ARRAY_A ),
			);

		} elseif ( $type === 'promotions' ) {
			global $wpdb;
			
			$tbl_promotions = $wpdb->prefix . 'o100_promotions';
			$tbl_loy_rules  = $wpdb->prefix . 'o100_loyalty_rules';
			$tbl_loy_rewds  = $wpdb->prefix . 'o100_loyalty_rewards';
			$tbl_automations = $wpdb->prefix . 'o100_automations';

			$data = array(
				'promotions'      => $wpdb->get_results( "SELECT * FROM {$tbl_promotions}", ARRAY_A ),
				'loyalty_rules'   => $wpdb->get_results( "SELECT * FROM {$tbl_loy_rules}", ARRAY_A ),
				'loyalty_rewards' => $wpdb->get_results( "SELECT * FROM {$tbl_loy_rewds}", ARRAY_A ),
				'automations'     => $wpdb->get_results( "SELECT * FROM {$tbl_automations}", ARRAY_A ),
			);
		}

		$payload = array(
			'type'      => 'order100_category_data',
			'category'  => $type,
			'timestamp' => time(),
			'data'      => $data,
		);

		$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$filename = 'order100-' . $type . '-export-' . date( 'Y-m-d-H-i' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $json;
		exit;
	}

	/**
	 * Import category-specific data
	 */
	public static function ajax_import_category() {
		check_ajax_referer( 'o100_backup_migration', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'order100' ) ) );
		}

		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'order100' ) ) );
		}

		$file_path = $_FILES['file']['tmp_name'];
		$content   = file_get_contents( $file_path );
		$payload   = json_decode( $content, true );

		if ( empty( $payload ) || ! is_array( $payload ) || empty( $payload['type'] ) || $payload['type'] !== 'order100_category_data' || $payload['category'] !== $type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid category data file format.', 'order100' ) ) );
		}

		$data = isset( $payload['data'] ) ? $payload['data'] : array();

		if ( $type === 'catalog' ) {
			// Import catalog categories
			$cats = isset( $data['categories'] ) ? $data['categories'] : array();
			$cat_slug_map = array();
			foreach ( $cats as $cat ) {
				$existing = get_term_by( 'slug', $cat['slug'], 'product_cat' );
				if ( $existing ) {
					$cat_slug_map[ $cat['slug'] ] = $existing->term_id;
				} else {
					$inserted = wp_insert_term( $cat['name'], 'product_cat', array(
						'slug'        => $cat['slug'],
						'description' => $cat['description'],
					) );
					if ( ! is_wp_error( $inserted ) ) {
						$cat_slug_map[ $cat['slug'] ] = $inserted['term_id'];
					}
				}
			}

			// Import products
			$prods = isset( $data['products'] ) ? $data['products'] : array();
			$prod_count = 0;
			foreach ( $prods as $prod ) {
				$existing_prod = get_page_by_title( $prod['title'], OBJECT, 'product' );
				$product_id    = 0;

				if ( $existing_prod ) {
					$product_id = $existing_prod->ID;
					wp_update_post( array(
						'ID'           => $product_id,
						'post_content' => $prod['description'],
						'post_status'  => $prod['status'],
					) );
				} else {
					$product_id = wp_insert_post( array(
						'post_title'   => $prod['title'],
						'post_content' => $prod['description'],
						'post_status'  => $prod['status'],
						'post_type'    => 'product',
					) );
				}

				if ( $product_id ) {
					update_post_meta( $product_id, '_price', $prod['price'] );
					update_post_meta( $product_id, '_regular_price', $prod['regular_price'] );
					update_post_meta( $product_id, '_sale_price', $prod['sale_price'] );
					update_post_meta( $product_id, '_stock_status', $prod['stock_status'] );
					update_post_meta( $product_id, '_sku', $prod['sku'] );
					update_post_meta( $product_id, '_product_addons', $prod['addons'] );

					// Assign categories
					$cat_ids = array();
					foreach ( $prod['categories'] as $slug ) {
						if ( isset( $cat_slug_map[ $slug ] ) ) {
							$cat_ids[] = $cat_slug_map[ $slug ];
						}
					}
					if ( ! empty( $cat_ids ) ) {
						wp_set_object_terms( $product_id, $cat_ids, 'product_cat' );
					}
					$prod_count++;
				}
			}

			// Restore modifiers
			if ( ! empty( $data['modifiers'] ) ) {
				update_option( 'o100_product_options', $data['modifiers'] );
			}

			wp_send_json_success( array(
				'message' => sprintf( __( 'Catalog imported successfully. Restored %d products.', 'order100' ), $prod_count )
			) );

		} elseif ( $type === 'customers' ) {
			global $wpdb;
			
			$tbl_customers     = $wpdb->prefix . 'o100_customers';
			$tbl_lists         = $wpdb->prefix . 'o100_crm_lists';
			$tbl_tags          = $wpdb->prefix . 'o100_crm_tags';
			$tbl_relationships = $wpdb->prefix . 'o100_crm_relationships';
			$tbl_rules         = $wpdb->prefix . 'o100_customer_rules';
			$tbl_loyalty_acct  = $wpdb->prefix . 'o100_loyalty_accounts';

			// Truncate tables for a clean sync
			$wpdb->query( "TRUNCATE TABLE {$tbl_customers}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_lists}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_tags}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_relationships}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_rules}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_loyalty_acct}" );

			// Re-insert
			$cust_count = 0;
			if ( ! empty( $data['customers'] ) ) {
				foreach ( $data['customers'] as $row ) {
					$wpdb->insert( $tbl_customers, $row );
					$cust_count++;
				}
			}
			if ( ! empty( $data['crm_lists'] ) ) {
				foreach ( $data['crm_lists'] as $row ) {
					$wpdb->insert( $tbl_lists, $row );
				}
			}
			if ( ! empty( $data['crm_tags'] ) ) {
				foreach ( $data['crm_tags'] as $row ) {
					$wpdb->insert( $tbl_tags, $row );
				}
			}
			if ( ! empty( $data['relationships'] ) ) {
				foreach ( $data['relationships'] as $row ) {
					$wpdb->insert( $tbl_relationships, $row );
				}
			}
			if ( ! empty( $data['rules'] ) ) {
				foreach ( $data['rules'] as $row ) {
					$wpdb->insert( $tbl_rules, $row );
				}
			}
			if ( ! empty( $data['loyalty_accts'] ) ) {
				foreach ( $data['loyalty_accts'] as $row ) {
					$wpdb->insert( $tbl_loyalty_acct, $row );
				}
			}

			wp_send_json_success( array(
				'message' => sprintf( __( 'Customers CRM database restored. Imported %d customer records.', 'order100' ), $cust_count )
			) );

		} elseif ( $type === 'promotions' ) {
			global $wpdb;
			
			$tbl_promotions  = $wpdb->prefix . 'o100_promotions';
			$tbl_loy_rules   = $wpdb->prefix . 'o100_loyalty_rules';
			$tbl_loy_rewds   = $wpdb->prefix . 'o100_loyalty_rewards';
			$tbl_automations = $wpdb->prefix . 'o100_automations';

			// Truncate tables for a clean sync
			$wpdb->query( "TRUNCATE TABLE {$tbl_promotions}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_loy_rules}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_loy_rewds}" );
			$wpdb->query( "TRUNCATE TABLE {$tbl_automations}" );

			$promo_count = 0;
			if ( ! empty( $data['promotions'] ) ) {
				foreach ( $data['promotions'] as $row ) {
					$wpdb->insert( $tbl_promotions, $row );
					$promo_count++;
				}
			}
			if ( ! empty( $data['loyalty_rules'] ) ) {
				foreach ( $data['loyalty_rules'] as $row ) {
					$wpdb->insert( $tbl_loy_rules, $row );
				}
			}
			if ( ! empty( $data['loyalty_rewards'] ) ) {
				foreach ( $data['loyalty_rewards'] as $row ) {
					$wpdb->insert( $tbl_loy_rewds, $row );
				}
			}
			if ( ! empty( $data['automations'] ) ) {
				foreach ( $data['automations'] as $row ) {
					$wpdb->insert( $tbl_automations, $row );
				}
			}

			wp_send_json_success( array(
				'message' => sprintf( __( 'Promotions and Campaign rules restored. Imported %d campaigns.', 'order100' ), $promo_count )
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Invalid category selection.', 'order100' ) ) );
	}

	/**
	 * Import GloriaFood menu JSON
	 */
	public static function ajax_import_gloriafood() {
		check_ajax_referer( 'o100_backup_migration', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'order100' ) ) );
		}

		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'order100' ) ) );
		}

		$file_path = $_FILES['file']['tmp_name'];
		$content   = file_get_contents( $file_path );
		$payload   = json_decode( $content, true );

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid GloriaFood JSON file structure.', 'order100' ) ) );
		}

		require_once O100_PATH . 'core/tools/backup-migration/class-o100-gloriafood-importer.php';
		$result = O100_GloriaFood_Importer::import( $payload );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}
}
