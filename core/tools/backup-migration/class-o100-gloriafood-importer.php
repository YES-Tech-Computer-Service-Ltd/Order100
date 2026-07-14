<?php
/**
 * GloriaFood Menu Importer
 *
 * Parses GloriaFood menu JSON exports and creates WooCommerce products, categories,
 * and Order100 modifier options.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_GloriaFood_Importer {

	/**
	 * Perform the import from GloriaFood JSON data
	 *
	 * @param array $data The parsed JSON array from GloriaFood.
	 * @return array Import statistics and status.
	 */
	public static function import( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or empty JSON data provided.', 'order100' ),
			);
		}

		// Increase execution limits
		@set_time_limit( 300 );
		@ini_set( 'memory_limit', '512M' );

		global $wpdb;
		$stats = array(
			'categories_created' => 0,
			'categories_skipped' => 0,
			'products_created'   => 0,
			'products_updated'   => 0,
			'options_created'    => 0,
			'errors'             => array(),
		);

		// Maps for tracking GF ID -> WP ID
		$cat_map    = array(); // gf_cat_id => wc_term_id
		$product_map = array(); // gf_item_id => wc_product_id

		// --- 1. Import Categories ---
		$categories = isset( $data['categories'] ) ? $data['categories'] : array();
		if ( empty( $categories ) && isset( $data['menu']['categories'] ) ) {
			$categories = $data['menu']['categories'];
		}

		foreach ( $categories as $gf_cat ) {
			if ( empty( $gf_cat['name'] ) ) {
				continue;
			}

			$cat_name = sanitize_text_field( $gf_cat['name'] );
			$cat_desc = isset( $gf_cat['description'] ) ? sanitize_textarea_field( $gf_cat['description'] ) : '';
			
			// Check if exists
			$existing_term = get_term_by( 'name', $cat_name, 'product_cat' );
			if ( $existing_term ) {
				$cat_map[ $gf_cat['id'] ] = $existing_term->term_id;
				$stats['categories_skipped']++;
			} else {
				$inserted = wp_insert_term( $cat_name, 'product_cat', array(
					'description' => $cat_desc,
				) );

				if ( ! is_wp_error( $inserted ) ) {
					$cat_map[ $gf_cat['id'] ] = $inserted['term_id'];
					$stats['categories_created']++;
				} else {
					$stats['errors'][] = sprintf( __( 'Category "%s" creation failed: %s', 'order100' ), $cat_name, $inserted->get_error_message() );
				}
			}
		}

		// --- 2. Import Products ---
		$items = isset( $data['items'] ) ? $data['items'] : array();
		if ( empty( $items ) && isset( $data['menu']['items'] ) ) {
			$items = $data['menu']['items'];
		}

		foreach ( $items as $gf_item ) {
			if ( empty( $gf_item['name'] ) ) {
				continue;
			}

			$item_name = sanitize_text_field( $gf_item['name'] );
			$item_desc = isset( $gf_item['description'] ) ? sanitize_textarea_field( $gf_item['description'] ) : '';
			$item_price = isset( $gf_item['price'] ) ? floatval( $gf_item['price'] ) : 0.00;

			// Check if product already exists by title
			$existing_product = get_page_by_title( $item_name, OBJECT, 'product' );
			$product_id       = 0;
			$is_update        = false;

			if ( $existing_product ) {
				$product_id = $existing_product->ID;
				$is_update  = true;
				
				// Update existing product
				wp_update_post( array(
					'ID'           => $product_id,
					'post_content' => $item_desc,
				) );
				$stats['products_updated']++;
			} else {
				// Insert new simple product CPT
				$product_id = wp_insert_post( array(
					'post_title'   => $item_name,
					'post_content' => $item_desc,
					'post_status'  => 'publish',
					'post_type'    => 'product',
				) );
				
				if ( ! is_wp_error( $product_id ) && $product_id ) {
					$stats['products_created']++;
				} else {
					$stats['errors'][] = sprintf( __( 'Product "%s" creation failed.', 'order100' ), $item_name );
					continue;
				}
			}

			if ( $product_id ) {
				$product_map[ $gf_item['id'] ] = $product_id;

				// Set WooCommerce basic metadata
				update_post_meta( $product_id, '_price', $item_price );
				update_post_meta( $product_id, '_regular_price', $item_price );
				update_post_meta( $product_id, '_visibility', 'visible' );
				update_post_meta( $product_id, '_stock_status', 'instock' );
				update_post_meta( $product_id, '_virtual', 'no' );
				update_post_meta( $product_id, '_downloadable', 'no' );

				// Bind to category
				$gf_cat_id = isset( $gf_item['category_id'] ) ? $gf_item['category_id'] : '';
				if ( ! empty( $gf_cat_id ) && isset( $cat_map[ $gf_cat_id ] ) ) {
					wp_set_object_terms( $product_id, $cat_map[ $gf_cat_id ], 'product_cat' );
				}
			}
		}

		// --- 3. Import Option Groups (Modifiers) ---
		$option_groups = isset( $data['option_groups'] ) ? $data['option_groups'] : array();
		if ( empty( $option_groups ) && isset( $data['menu']['option_groups'] ) ) {
			$option_groups = $data['menu']['option_groups'];
		}

		if ( ! empty( $option_groups ) ) {
			// Retrieve existing Order100 modifiers
			$o100_product_options = get_option( 'o100_product_options', array() );
			$o100_addon_groups    = isset( $o100_product_options['o100_addon_groups'] ) ? $o100_product_options['o100_addon_groups'] : array();

			foreach ( $option_groups as $gf_group ) {
				if ( empty( $gf_group['name'] ) ) {
					continue;
				}

				$group_name = sanitize_text_field( $gf_group['name'] );
				$min_select = isset( $gf_group['min_select'] ) ? intval( $gf_group['min_select'] ) : 0;
				$max_select = isset( $gf_group['max_select'] ) ? intval( $gf_group['max_select'] ) : 1;
				$type       = ( $max_select > 1 ) ? 'checkbox' : 'radio';
				$required   = ( $min_select > 0 ) ? 'yes' : 'no';

				// Parse choices/options
				$choices = array();
				$gf_opts = isset( $gf_group['options'] ) ? $gf_group['options'] : array();
				foreach ( $gf_opts as $gf_opt ) {
					$choices[] = array(
						'name'       => sanitize_text_field( $gf_opt['name'] ),
						'price'      => isset( $gf_opt['price'] ) ? number_format( floatval( $gf_opt['price'] ), 2, '.', '' ) : '0.00',
						'sale_price' => '',
						'type'       => 'flat', // Per Item/Flat fee (Woo standard default)
						'min'        => '',
						'max'        => '',
						'def'        => ( isset( $gf_opt['is_default'] ) && $gf_opt['is_default'] ) ? 'yes' : 'no',
						'dis'        => 'no',
					);
				}

				// Find associated WC products
				$target_product_ids = array();
				$gf_item_ids        = isset( $gf_group['item_ids'] ) ? $gf_group['item_ids'] : array();
				foreach ( $gf_item_ids as $gf_item_id ) {
					if ( isset( $product_map[ $gf_item_id ] ) ) {
						$target_product_ids[] = $product_map[ $gf_item_id ];
					}
				}

				// Check if matching mod group exists
				$found_group_index = -1;
				foreach ( $o100_addon_groups as $index => $group ) {
					if ( $group['_name'] === $group_name ) {
						$found_group_index = $index;
						break;
					}
				}

				$addon_id = 'o100_gf_' . uniqid();

				if ( $found_group_index !== -1 ) {
					// Update existing group by appending target products
					$existing_pids = ! empty( $o100_addon_groups[ $found_group_index ]['_product_ids'] ) 
						? explode( ',', $o100_addon_groups[ $found_group_index ]['_product_ids'] ) 
						: array();
					$merged_pids   = array_unique( array_merge( $existing_pids, $target_product_ids ) );
					$o100_addon_groups[ $found_group_index ]['_product_ids'] = implode( ',', $merged_pids );
				} else {
					// Create new modifier group
					$new_group = array(
						'_id'           => $addon_id,
						'_name'         => $group_name,
						'_type'         => $type,
						'_required'     => $required,
						'_apply_to'     => 'products',
						'_product_ids'  => implode( ',', $target_product_ids ),
						'_category_ids' => array(),
						'_min_op'       => $min_select,
						'_max_op'       => $max_select,
						'_enb_img'      => 'no',
						'_enb_qty'      => 'no',
						'_options'      => $choices,
					);
					$o100_addon_groups[] = $new_group;
					$stats['options_created']++;
				}
			}

			$o100_product_options['o100_addon_groups'] = $o100_addon_groups;
			update_option( 'o100_product_options', $o100_product_options );
		}

		return array(
			'success' => true,
			'stats'   => $stats,
			'message' => sprintf(
				__( 'Import completed. Created %d categories, %d products (%d updated), and %d modifier groups.', 'order100' ),
				$stats['categories_created'],
				$stats['products_created'],
				$stats['products_updated'],
				$stats['options_created']
			),
		);
	}
}
