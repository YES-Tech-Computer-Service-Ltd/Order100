<?php
/**
 * REST API Controller for Search operations (MCD, Categories, Tags, etc.)
 * Replaces old wp_ajax_ calls for searching data for Select2/dropdowns.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class O100_Search_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$namespace = 'o100/v1';

		register_rest_route( $namespace, '/search/(?P<type>[a-zA-Z0-9_-]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE, // GET
				'callback'            => array( __CLASS__, 'perform_search' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			),
		) );
	}

	public static function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	public static function perform_search( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$term = $request->get_param( 'term' ) ? sanitize_text_field( $request->get_param( 'term' ) ) : '';
		$exclude_str = $request->get_param( 'exclude' ) ? sanitize_text_field( $request->get_param( 'exclude' ) ) : '';
		$exclude = ! empty( $exclude_str ) ? array_map( 'intval', explode( ',', $exclude_str ) ) : array();

		switch ( $type ) {
			case 'products':
				return self::search_products( $term, $exclude );
			case 'categories':
				return self::search_categories( $term, $exclude );
			case 'crm-tags':
				return self::search_crm_tags( $term );
			case 'crm-lists':
				return self::search_crm_lists( $term );
			default:
				return new WP_Error( 'invalid_type', 'Invalid search type.', array( 'status' => 400 ) );
		}
	}

	private static function search_products( $term, $exclude ) {
		$product_ids = array();
		
		if ( empty( $term ) ) {
			// Fetch 30 latest products if term is empty
			$args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC'
			);
			$query = new WP_Query( $args );
			$product_ids = $query->posts;
		} else {
			// Search products via WooCommerce Data Store
			$data_store = WC_Data_Store::load( 'product' );
			$product_ids = $data_store->search_products( $term, '', true ); // Search by name/sku
			
			// Fallback to standard WP_Query if data store returns nothing
			if ( empty( $product_ids ) ) {
				$args = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 30,
					's'              => $term,
					'fields'         => 'ids'
				);
				$query = new WP_Query( $args );
				$product_ids = $query->posts;
			}
		}
		
		$results = array();
		
		if ( ! empty( $product_ids ) && is_array( $product_ids ) ) {
			foreach ( $product_ids as $post_id ) {
				if ( ! empty( $exclude ) && in_array( $post_id, $exclude ) ) {
					continue;
				}
				$product = wc_get_product( $post_id );
				if ( $product ) {
					$results[] = array(
						'id'   => $product->get_id(),
						'text' => $product->get_formatted_name()
					);
				}
			}
		}
		
		if ( empty( $results ) ) {
			$results[] = array(
				'id'   => 0,
				'text' => 'No results found'
			);
		}
		
		return rest_ensure_response( array( 'success' => true, 'data' => $results ) );
	}

	private static function search_categories( $term, $exclude ) {
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 50,
			'exclude'    => $exclude
		);
		
		if ( ! empty( $term ) ) {
			$args['search'] = $term;
		}
		
		$terms = get_terms( $args );
		$results = array();
		
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $cat ) {
				$results[] = array(
					'id'   => $cat->term_id,
					'text' => $cat->name
				);
			}
		}
		
		if ( empty( $results ) ) {
			$results[] = array(
				'id'   => 0,
				'text' => 'No results found'
			);
		}
		
		return rest_ensure_response( array( 'success' => true, 'data' => $results ) );
	}

	private static function search_crm_tags( $term ) {
		if ( ! class_exists( 'O100_Customers_DB' ) ) {
			return new WP_Error( 'missing_db', 'CRM DB not found', array( 'status' => 500 ) );
		}

		$tags = O100_Customers_DB::get_tags();
		$system_tags = array();
		$manual_tags = array();
		
		foreach ( $tags as $t ) {
			if ( empty( $term ) || stripos( $t->title, $term ) !== false || stripos( $t->slug, $term ) !== false ) {
				if ( $t->is_system == '1' ) {
					$system_tags[] = array( 'id' => $t->slug, 'text' => $t->title, 'is_system' => 1 );
				} else {
					$manual_tags[] = array( 'id' => $t->slug, 'text' => $t->title, 'is_system' => 0 );
				}
			}
		}
		
		$results = array();
		if ( ! empty( $system_tags ) ) {
			$results[] = array( 'is_header' => true, 'text' => 'Smart Tags (System)' );
			$results = array_merge( $results, $system_tags );
		}
		if ( ! empty( $manual_tags ) ) {
			$results[] = array( 'is_header' => true, 'text' => 'Custom Tags (Manual)' );
			$results = array_merge( $results, $manual_tags );
		}
		
		return rest_ensure_response( array( 'success' => true, 'data' => $results ) );
	}

	private static function search_crm_lists( $term ) {
		if ( ! class_exists( 'O100_Customers_DB' ) ) {
			return new WP_Error( 'missing_db', 'CRM DB not found', array( 'status' => 500 ) );
		}

		$lists = O100_Customers_DB::get_lists();
		$results = array();
		
		foreach ( $lists as $l ) {
			if ( empty( $term ) || stripos( $l->title, $term ) !== false || stripos( $l->slug, $term ) !== false ) {
				$results[] = array( 'id' => $l->slug, 'text' => $l->title );
			}
		}
		
		return rest_ensure_response( array( 'success' => true, 'data' => $results ) );
	}
}
