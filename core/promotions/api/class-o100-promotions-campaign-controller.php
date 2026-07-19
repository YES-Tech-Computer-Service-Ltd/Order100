<?php
/**
 * Promotions Admin UI
 *
 * Renders the Step-by-Step UI and List Table for Promotions.
 *
 * @package Order100
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Promotions_Campaign_Controller {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	public static function register_rest_routes() {
		if ( ! class_exists( 'O100_REST_Controller' ) ) {
			require_once O100_PATH . 'core/class-o100-rest-controller.php';
		}
		
		register_rest_route( 'o100/v1', '/promotions', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'rest_get' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_save' ],
				'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
			]
		] );

		
		register_rest_route( 'o100/v1', '/promotions/reports', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'rest_get_reports' ],
			'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
		] );

		register_rest_route( 'o100/v1', '/promotions/(?P<id>[\d]+)', [
			'methods'             => 'DELETE',
			'callback'            => [ __CLASS__, 'rest_delete' ],
			'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
		] );

		register_rest_route( 'o100/v1', '/promotions/(?P<id>[\d]+)/status', [
			'methods'             => 'PATCH',
			'callback'            => [ __CLASS__, 'rest_toggle' ],
			'permission_callback' => [ 'O100_REST_Controller', 'check_admin_permissions' ],
		] );
	}


	public static function handle_save() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			check_ajax_referer( 'o100_promotions_admin', 'nonce' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return O100_REST_Controller::error( 'Permission denied' );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

		$data = [
			'title'            => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'      => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'priority'         => isset( $_POST['priority'] ) ? intval( $_POST['priority'] ) : 10,
			'is_exclusive'     => isset( $_POST['is_exclusive'] ) ? intval( $_POST['is_exclusive'] ) : 0,
			'rule_type'        => isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : 'simple',
			'promo_code'       => ! empty( $_POST['promo_code'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_code'] ) ) : null,
			'apply_to'         => isset( $_POST['apply_to'] ) ? sanitize_text_field( wp_unslash( $_POST['apply_to'] ) ) : 'all_products',
			'apply_to_items'   => isset( $_POST['apply_to_items'] ) ? wp_unslash( $_POST['apply_to_items'] ) : '[]', // Sent as JSON string from JS
			'conditions_logic' => isset( $_POST['conditions_logic'] ) ? sanitize_text_field( wp_unslash( $_POST['conditions_logic'] ) ) : 'all',
			'conditions'       => isset( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : '[]',
			'action_config'    => isset( $_POST['action_config'] ) ? wp_unslash( $_POST['action_config'] ) : '{}',
			'status'           => 'active',
			'start_date'       => ! empty( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) . ' 00:00:00' : null,
			'end_date'         => ! empty( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) . ' 23:59:59' : null,
			'usage_limit'      => isset( $_POST['usage_limit'] ) ? intval( $_POST['usage_limit'] ) : 0,
		];

		if ( $id > 0 ) {
			// Update existing — don't overwrite status (toggle manages it)
			unset( $data['status'] );
			O100_Promotions_DB::update( $id, $data );
			return O100_REST_Controller::success( [ 'id' => $id, 'message' => 'Promotion updated successfully.' ] );
		} else {
			// Limit logic for new active promotions
			$active_count = O100_Promotions_DB::count( [ 'status' => 'active' ] );
			if ( $active_count >= 1 ) {
				if ( ! function_exists('O100_License') || ! O100_License()->is_premium() ) {
					return O100_REST_Controller::error( 'Free version is limited to 1 active promotion. Please upgrade to Pro.' );
				}
			}

			// Insert new
			$data['source'] = 'manual';
			$new_id = O100_Promotions_DB::insert( $data );
			if ( $new_id ) {
				return O100_REST_Controller::success( [ 'id' => $new_id, 'message' => 'Promotion created successfully.' ] );
			} else {
				return O100_REST_Controller::error( 'Failed to create promotion.' );
			}
		}
	}

	public static function handle_delete() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			check_ajax_referer( 'o100_promotions_admin', 'nonce' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return O100_REST_Controller::error( 'Permission denied' );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			O100_Promotions_DB::delete( $id );
			return O100_REST_Controller::success( 'Promotion deleted.' );
		} else {
			return O100_REST_Controller::error( 'Invalid ID.' );
		}
	}

	public static function handle_toggle() {
		check_ajax_referer( 'o100_promotions_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return O100_REST_Controller::error( 'Permission denied' );
		}

		$id      = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$status_val = isset( $_POST['status'] ) ? intval( $_POST['status'] ) : 1;
		$status  = $status_val ? 'active' : 'disabled';

		if ( $id > 0 ) {
			require_once O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
			
			if ( $status === 'active' ) {
				$active_count = O100_Promotions_DB::count( [ 'status' => 'active' ] );
				if ( $active_count >= 1 ) {
					if ( ! function_exists('O100_License') || ! O100_License()->is_premium() ) {
						return O100_REST_Controller::error( 'Free version is limited to 1 active promotion. Please upgrade to Pro.' );
					}
				}
			}

			O100_Promotions_DB::update( $id, [ 'status' => $status ] );
			return O100_REST_Controller::success( [ 'id' => $id, 'status' => $status ] );
		} else {
			return O100_REST_Controller::error( 'Invalid ID.' );
		}
	}

	public static function handle_get() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			check_ajax_referer( 'o100_promotions_admin', 'nonce' );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return O100_REST_Controller::error( 'Permission denied' );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			$promo = O100_Promotions_DB::get( $id );
			if ( $promo ) {
				// Format items for the MCD UI
				$apply_to = $promo['apply_to'];
				$items_raw = json_decode( $promo['apply_to_items'], true );
				$formatted_items = [];
				if ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
					foreach ( $items_raw as $item_id ) {
						if ( $apply_to === 'specific_products' ) {
							$product = wc_get_product( $item_id );
							if ( $product ) {
								$formatted_items[] = [ 'id' => $item_id, 'text' => wp_strip_all_tags( $product->get_formatted_name() ) ];
							}
						} elseif ( $apply_to === 'specific_categories' ) {
							$term = get_term( $item_id, 'product_cat' );
							if ( $term && ! is_wp_error( $term ) ) {
								$formatted_items[] = [ 'id' => $item_id, 'text' => $term->name ];
							}
						}
					}
				}
				$promo['formatted_items'] = $formatted_items;

				$action_config = json_decode( $promo['action_config'], true );
				
				// Format Free Item (Legacy Support)
				$formatted_free_item = null;
				if ( ! empty( $action_config['free_item_id'] ) ) {
					$free_prod = wc_get_product( $action_config['free_item_id'] );
					if ( $free_prod ) {
						$formatted_free_item = [ 'id' => $action_config['free_item_id'], 'text' => wp_strip_all_tags( $free_prod->get_formatted_name() ) ];
					}
				}
				$promo['formatted_free_item'] = $formatted_free_item;

				// Format Product Y for Buy X Get Y
				$formatted_product_y = null;
				if ( $promo['rule_type'] === 'buy_x_get_y' && ! empty( $action_config['product_y'] ) ) {
					$prod_y = wc_get_product( $action_config['product_y'] );
					if ( $prod_y ) {
						$formatted_product_y = [ 'id' => $action_config['product_y'], 'text' => wp_strip_all_tags( $prod_y->get_formatted_name() ) ];
					}
				}
				$promo['formatted_product_y'] = $formatted_product_y;

				return O100_REST_Controller::success( $promo );
			} else {
				return O100_REST_Controller::error( 'Promotion not found.' );
			}
		} else {
			return O100_REST_Controller::error( 'Invalid ID.' );
		}
	}

	/**
	 * Get reports for issued coupons
	 */
	public static function rest_get_reports( $request ) {
		if ( ! class_exists( 'O100_Promotions_DB' ) ) {
			return new WP_Error( 'db_error', 'Database class not found', array( 'status' => 500 ) );
		}

		$page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
		$limit = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20;
		$offset = ( $page - 1 ) * $limit;

		$args = array(
			'limit'     => $limit,
			'offset'    => $offset,
			'search'    => sanitize_text_field( $request->get_param( 'search' ) ),
			'status'    => sanitize_text_field( $request->get_param( 'status' ) ),
			'sourceFilter' => sanitize_text_field( $request->get_param( 'sourceFilter' ) ),
			'expiryFilter' => sanitize_text_field( $request->get_param( 'expiryFilter' ) ),
			'sortField' => sanitize_text_field( $request->get_param( 'sortField' ) ),
			'sortDir'   => sanitize_text_field( $request->get_param( 'sortDir' ) ),
		);

		$results = O100_Promotions_DB::get_reports( $args );
		$total_items = O100_Promotions_DB::get_reports_count( $args );

		return rest_ensure_response( array(
			'success'      => true,
			'data'         => $results,
			'page'         => $page,
			'total'        => $total_items,
			'totalPages'   => ceil($total_items / $limit),
		) );
	}

	public static function rest_get( WP_REST_Request $request ) {
		$_POST['id'] = $request->get_param( 'id' );
		return self::handle_get();
	}

	public static function rest_save( WP_REST_Request $request ) {
		$params = $request->get_params();
		foreach($params as $k => $v) { $_POST[$k] = $v; }
		return self::handle_save();
	}

	public static function rest_delete( WP_REST_Request $request ) {
		$_POST['id'] = $request->get_param( 'id' );
		return self::handle_delete();
	}

	public static function rest_toggle( WP_REST_Request $request ) {
		$_POST['id'] = $request->get_param( 'id' );
		$_POST['status'] = $request->get_param( 'status' );
		return self::handle_toggle();
	}
}


