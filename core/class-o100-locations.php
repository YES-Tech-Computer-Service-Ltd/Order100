<?php
/**
 * Order100 Locations Management
 *
 * Handles the o100_location Custom Post Type and related AJAX CRUD operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Locations {

	/**
	 * Init function
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );

		// AJAX Endpoints
		add_action( 'wp_ajax_o100_get_locations', array( __CLASS__, 'ajax_get_locations' ) );
		add_action( 'wp_ajax_o100_save_location', array( __CLASS__, 'ajax_save_location' ) );
		add_action( 'wp_ajax_o100_delete_location', array( __CLASS__, 'ajax_delete_location' ) );
		add_action( 'wp_ajax_o100_loc_preview_timeslots', array( __CLASS__, 'ajax_loc_preview_timeslots' ) );

		// Frontend Filters for Hidden Categories & Products
		add_action( 'woocommerce_product_query', array( __CLASS__, 'filter_product_query' ) );
		add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'filter_shortcode_query' ), 10, 3 );
		add_filter( 'get_terms', array( __CLASS__, 'filter_get_terms' ), 10, 4 );
	}

	/**
	 * Register the o100_location Custom Post Type
	 * This is a hidden data store.
	 */
	public static function register_post_type() {
		$args = array(
			'label'               => __( 'Locations', 'order100' ),
			'public'              => false,
			'show_ui'             => false, // Hidden from normal WP admin menu
			'show_in_menu'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'supports'            => array( 'title' ), // We only need the title for the name
		);
		register_post_type( 'o100_location', $args );
	}

	/**
	 * AJAX: Preview Timeslots for Location Modal
	 */
	public static function ajax_loc_preview_timeslots() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$interval    = intval( $_POST['interval'] ?? 30 );
		$default_max = intval( $_POST['max_order'] ?? 4 );
		$schedule    = isset( $_POST['hours'] ) ? wp_unslash( $_POST['hours'] ) : array();

		if ( $interval <= 0 ) $interval = 30;
		if ( $default_max <= 0 ) $default_max = 4;

		$weekdays = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
		$new_slots = array();

		foreach ( $weekdays as $day ) {
			if ( isset( $schedule[$day] ) && is_array( $schedule[$day] ) ) {
				$day_slots = array();
				foreach ( $schedule[$day] as $slot ) {
					if ( empty($slot['open']) || empty($slot['close']) ) continue;
					$st = strtotime( $slot['open'] );
					$et = strtotime( $slot['close'] );
					if ( !$st || !$et || $st >= $et ) continue;

					$cur = $st;
					while ( $cur + ($interval * 60) <= $et ) {
						$day_slots[] = array(
							'start-time' => date('H:i', $cur),
							'end-time'   => date('H:i', $cur + ($interval * 60)),
							'max-odts'   => $default_max,
						);
						$cur += ($interval * 60);
					}
				}
				if ( !empty($day_slots) ) {
					$new_slots[$day] = $day_slots;
				}
			}
		}

		$html = '';
		if ( class_exists( 'O100_CMB2_Field_OpenClose' ) ) {
			$html = O100_CMB2_Field_OpenClose::render_timeslots_html( $new_slots );
		}

		wp_send_json_success( array(
			'slots' => $new_slots,
			'html'  => $html
		) );
	}

	/**
	 * AJAX: Get all locations
	 */
	public static function ajax_get_locations() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$args = array(
			'post_type'      => 'o100_location',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);
		
		$query = new WP_Query( $args );
		$data  = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$loc_id = $post->ID;
				$data[] = array(
					'id'            => $loc_id,
					'name'          => $post->post_title,
					'address'       => get_post_meta( $loc_id, 'o100_address', true ),
					'latlng'        => get_post_meta( $loc_id, 'o100_latlng', true ),
					'emails'        => get_post_meta( $loc_id, 'o100_emails', true ),
					'phone_code'    => get_post_meta( $loc_id, 'o100_phone_code', true ),
					'phone'         => get_post_meta( $loc_id, 'o100_phone', true ),
					'distance'      => get_post_meta( $loc_id, 'o100_distance_restrict', true ),
					'min_order'       => get_post_meta( $loc_id, 'o100_min_order', true ),
					'enable_delivery' => get_post_meta( $loc_id, 'o100_enable_delivery', true ),
					'enable_pickup'   => get_post_meta( $loc_id, 'o100_enable_pickup', true ),
					'fee_action'      => get_post_meta( $loc_id, 'o100_fee_action', true ),
					'fee_type'        => get_post_meta( $loc_id, 'o100_fee_type', true ),
					'fee_val'         => get_post_meta( $loc_id, 'o100_fee_val', true ),
					'closed'          => get_post_meta( $loc_id, 'o100_closed', true ),
					'override_hours'  => get_post_meta( $loc_id, 'o100_override_hours', true ),
					'hidden_cats'     => self::get_terms_for_select2( $loc_id, 'o100_hidden_cats', 'product_cat' ),
					'hidden_prods'    => self::get_products_for_select2( $loc_id, 'o100_hidden_products' ),
					'hours'           => get_post_meta( $loc_id, 'o100_hours', true ), // Array
					'interval'        => get_post_meta( $loc_id, 'o100_interval', true ),
					'max_order'       => get_post_meta( $loc_id, 'o100_max_order', true ),
					'generated_slots' => get_post_meta( $loc_id, 'o100_generated_timeslots', true ) // Array
				);
			}
		}

		$opts = get_option('o100_store_hours', array());
		
		$global_hours = array();
		$days = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
		foreach ($days as $day) {
			$key = 'o100_' . $day . '_opcl_time';
			if (isset($opts[$key]) && is_array($opts[$key])) {
				$global_hours[$day] = $opts[$key];
			}
		}

		$opts_delivery = get_option( 'o100_delivery', array() );
		$opts_pickup   = get_option( 'o100_pickup', array() );

		$global_config = array(
			'interval'  => isset($opts['o100_global_interval']) ? intval($opts['o100_global_interval']) : 30,
			'max_order' => isset($opts['o100_global_max_order']) ? intval($opts['o100_global_max_order']) : 4,
			'hours'     => $global_hours,
			'enable_delivery' => ! empty( $opts_delivery['o100_enable_delivery'] ) && $opts_delivery['o100_enable_delivery'] === 'on' ? 'yes' : 'no',
			'enable_pickup'   => ! empty( $opts_pickup['o100_enable_pickup'] ) && $opts_pickup['o100_enable_pickup'] === 'on' ? 'yes' : 'no',
		);

		wp_send_json_success( array(
			'locations' => $data,
			'global_config' => $global_config
		) );
	}

	/**
	 * Helper: Get terms formatted for Select2
	 */
	private static function get_terms_for_select2( $loc_id, $meta_key, $taxonomy ) {
		$ids_json = get_post_meta( $loc_id, $meta_key, true );
		$ids = json_decode( $ids_json, true );
		$result = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$term = get_term( $id, $taxonomy );
				if ( ! is_wp_error( $term ) && $term ) {
					$result[] = array( 'id' => $term->term_id, 'text' => $term->name );
				}
			}
		}
		return $result;
	}

	/**
	 * Helper: Get products formatted for Select2
	 */
	private static function get_products_for_select2( $loc_id, $meta_key ) {
		$ids_json = get_post_meta( $loc_id, $meta_key, true );
		$ids = json_decode( $ids_json, true );
		$result = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$result[] = array( 'id' => $product->get_id(), 'text' => $product->get_formatted_name() );
				}
			}
		}
		return $result;
	}

	/**
	 * AJAX: Save a location
	 */
	public static function ajax_save_location() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$loc_id = isset( $_POST['loc_id'] ) ? intval( $_POST['loc_id'] ) : 0;
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( 'Name is required' );
		}

		$post_data = array(
			'post_title'  => $name,
			'post_status' => 'publish',
			'post_type'   => 'o100_location',
		);

		if ( $loc_id > 0 ) {
			$post_data['ID'] = $loc_id;
			$loc_id = wp_update_post( $post_data );
		} else {
			$loc_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $loc_id ) || ! $loc_id ) {
			wp_send_json_error( 'Failed to save location' );
		}

		// Update Meta
		$meta_fields = array(
			'address'         => 'o100_address',
			'latlng'          => 'o100_latlng',
			'emails'          => 'o100_emails',
			'phone_code'      => 'o100_phone_code',
			'phone'           => 'o100_phone',
			'distance'        => 'o100_distance_restrict',
			'min_order'       => 'o100_min_order',
			'enable_delivery' => 'o100_enable_delivery',
			'enable_pickup'   => 'o100_enable_pickup',
			'fee_action'      => 'o100_fee_action',
			'fee_type'        => 'o100_fee_type',
			'fee_val'         => 'o100_fee_val',
			'closed'          => 'o100_closed',
			'override_hours'  => 'o100_override_hours',
		);

		foreach ( $meta_fields as $post_key => $meta_key ) {
			if ( isset( $_POST[ $post_key ] ) ) {
				update_post_meta( $loc_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
			}
		}
		
		if ( isset( $_POST['hidden_cats'] ) ) {
			$raw_cats = wp_unslash( $_POST['hidden_cats'] );
			$arr_cats = json_decode( $raw_cats, true );
			if ( is_array( $arr_cats ) && ! empty( $arr_cats ) ) {
				$arr_cats = array_map( 'intval', $arr_cats );
				update_post_meta( $loc_id, 'o100_hidden_cats', wp_json_encode( $arr_cats ) );
			} else {
				delete_post_meta( $loc_id, 'o100_hidden_cats' );
			}
		}

		if ( isset( $_POST['hidden_products'] ) ) {
			$raw_prods = wp_unslash( $_POST['hidden_products'] );
			$arr_prods = json_decode( $raw_prods, true );
			if ( is_array( $arr_prods ) && ! empty( $arr_prods ) ) {
				$arr_prods = array_map( 'intval', $arr_prods );
				update_post_meta( $loc_id, 'o100_hidden_products', wp_json_encode( $arr_prods ) );
			} else {
				delete_post_meta( $loc_id, 'o100_hidden_products' );
			}
		}

		// Hours
		if ( isset( $_POST['hours'] ) ) {
			$raw_hours = wp_unslash( $_POST['hours'] );
			$clean_hours = array();
			if ( is_array( $raw_hours ) ) {
				foreach ( $raw_hours as $day => $slots ) {
					$clean_slots = array();
					if ( is_array( $slots ) ) {
						foreach ( $slots as $slot ) {
							$clean_slots[] = array(
								'start' => sanitize_text_field( $slot['open'] ?? '' ),
								'end'   => sanitize_text_field( $slot['close'] ?? '' )
							);
						}
					}
					$clean_hours[ sanitize_text_field( $day ) ] = $clean_slots;
				}
			}
			update_post_meta( $loc_id, 'o100_hours', $clean_hours );
		} else {
			delete_post_meta( $loc_id, 'o100_hours' );
		}

		if ( isset( $_POST['interval'] ) ) {
			update_post_meta( $loc_id, 'o100_interval', intval( $_POST['interval'] ) );
		}
		if ( isset( $_POST['max_order'] ) ) {
			update_post_meta( $loc_id, 'o100_max_order', intval( $_POST['max_order'] ) );
		}
		if ( isset( $_POST['generated_slots'] ) ) {
			// This is a JSON string of generated slots, save as is, but ensure valid JSON
			$raw_slots = wp_unslash( $_POST['generated_slots'] );
			$decoded = json_decode( $raw_slots, true );
			if ( is_array( $decoded ) ) {
				update_post_meta( $loc_id, 'o100_generated_timeslots', wp_json_encode( $decoded ) );
			} else {
				delete_post_meta( $loc_id, 'o100_generated_timeslots' );
			}
		} else {
			delete_post_meta( $loc_id, 'o100_generated_timeslots' );
		}

		wp_send_json_success( 'Saved successfully' );
	}

	/**
	 * AJAX: Delete a location
	 */
	public static function ajax_delete_location() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$loc_id = isset( $_POST['loc_id'] ) ? intval( $_POST['loc_id'] ) : 0;
		if ( ! $loc_id ) {
			wp_send_json_error( 'Invalid ID' );
		}

		$result = wp_delete_post( $loc_id, true );
		if ( ! $result ) {
			wp_send_json_error( 'Failed to delete' );
		}

		wp_send_json_success( 'Deleted' );
	}

	/**
	 * Frontend: Get hidden categories for the current location
	 */
	private static function get_current_hidden_cats() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return array();
		}
		$loc_id = WC()->session->get( 'ex_userloc' );
		if ( ! $loc_id ) {
			return array();
		}
		$hidden_cats_json = get_post_meta( $loc_id, 'o100_hidden_cats', true );
		if ( ! $hidden_cats_json ) {
			return array();
		}
		$hidden_cats = json_decode( $hidden_cats_json, true );
		return is_array( $hidden_cats ) ? array_map( 'intval', $hidden_cats ) : array();
	}

	/**
	 * Frontend: Get hidden products for the current location
	 */
	private static function get_current_hidden_prods() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->session ) ) {
			return array();
		}
		$loc_id = WC()->session->get( 'ex_userloc' );
		if ( ! $loc_id ) {
			return array();
		}
		$hidden_prods_json = get_post_meta( $loc_id, 'o100_hidden_products', true );
		if ( ! $hidden_prods_json ) {
			return array();
		}
		$hidden_prods = json_decode( $hidden_prods_json, true );
		return is_array( $hidden_prods ) ? array_map( 'intval', $hidden_prods ) : array();
	}

	/**
	 * Filter WooCommerce main product query
	 */
	public static function filter_product_query( $q ) {
		if ( is_admin() ) {
			return;
		}

		// Only apply to WooCommerce product queries
		if ( ! is_post_type_archive( 'product' ) && ! is_product_category() && ! is_product_tag() ) {
			if ( $q->get( 'post_type' ) !== 'product' && $q->get( 'post_type' ) !== array( 'product' ) ) {
				// If not explicitly a product query or WooCommerce archive, let it pass (e.g. Nav Menus)
				return;
			}
		}

		$hidden_cats = self::get_current_hidden_cats();
		if ( ! empty( $hidden_cats ) ) {
			$tax_query = (array) $q->get( 'tax_query' );
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $hidden_cats,
				'operator' => 'NOT IN',
			);
			$q->set( 'tax_query', $tax_query );
		}

		$hidden_prods = self::get_current_hidden_prods();
		if ( ! empty( $hidden_prods ) ) {
			$post__not_in = (array) $q->get( 'post__not_in' );
			$q->set( 'post__not_in', array_merge( $post__not_in, $hidden_prods ) );
		}
	}

	/**
	 * Filter WooCommerce shortcode product query
	 */
	public static function filter_shortcode_query( $args, $atts, $type ) {
		$hidden_cats = self::get_current_hidden_cats();
		if ( ! empty( $hidden_cats ) ) {
			if ( ! isset( $args['tax_query'] ) ) {
				$args['tax_query'] = array();
			}
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $hidden_cats,
				'operator' => 'NOT IN',
			);
		}

		$hidden_prods = self::get_current_hidden_prods();
		if ( ! empty( $hidden_prods ) ) {
			if ( ! isset( $args['post__not_in'] ) ) {
				$args['post__not_in'] = array();
			}
			$args['post__not_in'] = array_merge( (array) $args['post__not_in'], $hidden_prods );
		}

		return $args;
	}

	/**
	 * Filter global category term queries
	 */
	public static function filter_get_terms( $terms, $taxonomies, $args, $term_query ) {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return $terms;
		}

		// Only filter if product_cat is queried
		if ( ! in_array( 'product_cat', (array) $taxonomies, true ) ) {
			return $terms;
		}

		// Don't filter if terms aren't objects (e.g. counting)
		if ( empty( $terms ) || ! is_object( current( $terms ) ) ) {
			return $terms;
		}

		$hidden_cats = self::get_current_hidden_cats();
		if ( empty( $hidden_cats ) ) {
			return $terms;
		}

		$filtered_terms = array();
		foreach ( $terms as $term ) {
			if ( isset( $term->term_id ) && in_array( (int) $term->term_id, $hidden_cats, true ) ) {
				continue;
			}
			$filtered_terms[] = $term;
		}

		return $filtered_terms;
	}
}

O100_Locations::init();

// TS: 20260123020407

// Update TS: 20260609165000
