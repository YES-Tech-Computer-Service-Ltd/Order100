<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_App_API_Orders extends O100_App_API_Controller {

	public function register_routes() {
		// GET /orders
		register_rest_route( $this->namespace, '/orders', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_orders' ),
			'permission_callback' => array( 'O100_App_API_Controller', 'check_app_permissions' ),
		) );

		// GET /orders/{id}
		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_order' ),
			'permission_callback' => array( 'O100_App_API_Controller', 'check_app_permissions' ),
		) );

		// PATCH /orders/{id}/status
		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_order_status' ),
			'permission_callback' => array( 'O100_App_API_Controller', 'check_app_permissions' ),
		) );
		
		// POST /orders/batch
		register_rest_route( $this->namespace, '/orders/batch', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'batch_update_orders' ),
			'permission_callback' => array( 'O100_App_API_Controller', 'check_app_permissions' ),
		) );
		
		// POST /orders/{id}/refund
		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/refund', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'refund_order' ),
			'permission_callback' => array( 'O100_App_API_Controller', 'check_app_permissions' ),
		) );
	}

	public function get_orders( WP_REST_Request $request ) {
		$branch_id = $request->get_param( 'branch_id' );
		$status = $request->get_param( 'status' );
		$page = $request->get_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1;
		$per_page = $request->get_param( 'per_page' ) ? intval( $request->get_param( 'per_page' ) ) : 20;
		$after = $request->get_param( 'after' );

		$args = array(
			'limit'  => $per_page,
			'page'   => $page,
			'paginate' => true,
			'return' => 'objects',
		);

		if ( ! empty( $status ) ) {
			if ( $status !== 'any' ) {
				$args['status'] = is_string($status) ? explode(',', $status) : $status;
			}
		}

		if ( ! empty( $after ) ) {
			$args['date_created'] = '>' . strtotime( $after );
		}

		$modified_after = $request->get_param( 'modified_after' );
		if ( ! empty( $modified_after ) ) {
			$args['date_modified'] = '>' . strtotime( $modified_after );
		}
		
		// 结合 branch_id 进行过滤，目前多门店暂未重度基于订单 Meta 过滤，
		// 如果你们用 exwoofood_location meta, 也可以在这里查
		if ( ! empty( $branch_id ) && $branch_id !== 'all' ) {
			$args['meta_key'] = 'exwoofood_location'; // 或 'o100_location'
			$args['meta_value'] = $branch_id;
		}

		$results = wc_get_orders( $args );
		
		$orders_data = array();
		foreach ( $results->orders as $order ) {
			$orders_data[] = $this->format_order( $order );
		}

		$response = rest_ensure_response( $orders_data );
		$response->header( 'X-WP-Total', $results->total );
		$response->header( 'X-WP-TotalPages', $results->max_num_pages );

		return $response;
	}

	public function get_order( WP_REST_Request $request ) {
		$order_id = intval( $request->get_param( 'id' ) );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', 'Invalid order ID', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $this->format_order( $order ) );
	}

	public function update_order_status( WP_REST_Request $request ) {
		$order_id = intval( $request->get_param( 'id' ) );
		$status = $request->get_param( 'status' );
		
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', 'Invalid order ID', array( 'status' => 404 ) );
		}

		if ( ! empty( $status ) ) {
			// strip 'wc-' if present
			$status = str_replace( 'wc-', '', $status );
			$order->update_status( $status, 'Status updated via Order100 App' );
		}

		// Handle preparation time update
		$prep_time = $request->get_param( 'prep_time' );
		if ( $prep_time !== null ) {
			$order->update_meta_data( '_wooauto_prep_time_minutes', intval($prep_time) );
			$order->save();
		}

		// Handle merchant confirm flag
		$confirmed = $request->get_param( 'merchant_confirmed' );
		if ( $confirmed !== null ) {
			$order->update_meta_data( '_wooauto_merchant_confirmed', filter_var($confirmed, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false' );
			$order->update_meta_data( '_wooauto_merchant_confirmed_at', date('Y-m-d\TH:i:s') );
			$order->save();
		}

		return rest_ensure_response( $this->format_order( $order ) );
	}
	
	public function batch_update_orders( WP_REST_Request $request ) {
		$update = $request->get_param( 'update' );
		if ( ! is_array( $update ) ) {
			return new WP_Error( 'invalid_batch', 'Invalid batch update array', array( 'status' => 400 ) );
		}

		$updated_orders = array();
		foreach ( $update as $item ) {
			if ( ! isset( $item['id'] ) ) {
				continue;
			}
			$order_id = intval( $item['id'] );
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			if ( isset( $item['status'] ) ) {
				$status = str_replace( 'wc-', '', $item['status'] );
				$order->update_status( $status, 'Status updated via Order100 App Batch' );
			}

			$updated_orders[] = $this->format_order( $order );
		}

		return rest_ensure_response( array(
			'update' => $updated_orders
		) );
	}
	
	public function refund_order( WP_REST_Request $request ) {
		$order_id = intval( $request->get_param( 'id' ) );
		$amount = $request->get_param( 'amount' );
		$reason = $request->get_param( 'reason' ) ? sanitize_text_field( $request->get_param( 'reason' ) ) : 'Refund via Order100 App';
		$line_items = $request->get_param( 'line_items' ); // optional array of item_id => array('qty'=>..., 'refund_total'=>..., 'refund_tax'=>...)

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', 'Invalid order ID', array( 'status' => 404 ) );
		}
		
		$refund_args = array(
			'amount'         => $amount,
			'reason'         => $reason,
			'order_id'       => $order_id,
			'refund_payment' => true, // direct API refund to gateway
		);
		
		if ( ! empty( $line_items ) && is_array( $line_items ) ) {
			$refund_args['line_items'] = $line_items;
		}
		
		$refund = wc_create_refund( $refund_args );
		
		if ( is_wp_error( $refund ) ) {
			return new WP_Error( 'refund_failed', $refund->get_error_message(), array( 'status' => 500 ) );
		}
		
		// Return updated order
		$order = wc_get_order( $order_id );
		return rest_ensure_response( $this->format_order( $order ) );
	}

	private function format_order( $order ) {
		// Output fully compatible format with WC REST API to avoid breaking App's OrderDto.kt parsing
		$date_created = $order->get_date_created();
		$date_modified = $order->get_date_modified();
		
		$data = array(
			'id' => $order->get_id(),
			'parent_id' => $order->get_parent_id(),
			'number' => $order->get_order_number(),
			'status' => 'wc-' === substr( $order->get_status(), 0, 3 ) ? substr( $order->get_status(), 3 ) : $order->get_status(),
			'date_created' => $date_created ? $date_created->date( 'Y-m-d\TH:i:s' ) : '',
			'date_modified' => $date_modified ? $date_modified->date( 'Y-m-d\TH:i:s' ) : '',
			'total' => $order->get_total(),
			'subtotal' => $order->get_subtotal(),
			'total_tax' => $order->get_total_tax(),
			'discount_total' => $order->get_discount_total(),
			'payment_method' => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
			'customer_note' => $order->get_customer_note(),
		);

		// Customer & Address
		$data['customer'] = array(
			'id' => $order->get_customer_id(),
			'first_name' => $order->get_billing_first_name(),
			'last_name' => $order->get_billing_last_name(),
			'email' => $order->get_billing_email(),
			'phone' => $order->get_billing_phone(),
		);
		
		$data['billing'] = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name' => $order->get_billing_last_name(),
			'company' => $order->get_billing_company(),
			'address_1' => $order->get_billing_address_1(),
			'address_2' => $order->get_billing_address_2(),
			'city' => $order->get_billing_city(),
			'state' => $order->get_billing_state(),
			'postcode' => $order->get_billing_postcode(),
			'country' => $order->get_billing_country(),
			'phone' => $order->get_billing_phone(),
		);
		
		$data['shipping'] = array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name' => $order->get_shipping_last_name(),
			'company' => $order->get_shipping_company(),
			'address_1' => $order->get_shipping_address_1(),
			'address_2' => $order->get_shipping_address_2(),
			'city' => $order->get_shipping_city(),
			'state' => $order->get_shipping_state(),
			'postcode' => $order->get_shipping_postcode(),
			'country' => $order->get_shipping_country(),
			'phone' => $order->get_shipping_phone(),
		);

		// Line items
		$line_items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			
			$meta_data = array();
			foreach ( $item->get_meta_data() as $meta ) {
				$meta_data[] = array(
					'id' => $meta->id,
					'key' => $meta->key,
					'value' => $meta->value
				);
			}
			
			$image = null;
			$prep_stations = array();
			if ( $product ) {
				$image_id = $product->get_image_id();
				if ( $image_id ) {
					$image_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
					$image = array( 'src' => $image_url );
				}
				
				$category_ids = $product->get_category_ids();
				foreach ( $category_ids as $cat_id ) {
					$station = get_term_meta( $cat_id, 'o100_prep_station', true );
					if ( ! empty( $station ) ) {
						$prep_stations[] = $station;
					}
				}
				$prep_stations = array_values( array_unique( $prep_stations ) );
			}

			$line_items[] = array(
				'id' => $item_id,
				'name' => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'quantity' => $item->get_quantity(),
				'subtotal' => $item->get_subtotal(),
				'total' => $item->get_total(),
				'price' => $product ? $product->get_price() : 0,
				'meta_data' => $meta_data,
				'image' => $image,
				'prep_stations' => $prep_stations
			);
		}
		$data['line_items'] = $line_items;

		// Fee lines
		$fee_lines = array();
		foreach ( $order->get_fees() as $item_id => $item ) {
			$fee_lines[] = array(
				'id' => $item_id,
				'name' => $item->get_name(),
				'total' => $item->get_total(),
				'total_tax' => $item->get_total_tax()
			);
		}
		$data['fee_lines'] = $fee_lines;

		// Tax lines
		$tax_lines = array();
		foreach ( $order->get_taxes() as $item_id => $item ) {
			$tax_lines[] = array(
				'id' => $item_id,
				'label' => $item->get_label(),
				'rate_percent' => $item->get_rate_percent(),
				'tax_total' => $item->get_tax_total()
			);
		}
		$data['tax_lines'] = $tax_lines;

		// Meta data
		$meta_data = array();
		foreach ( $order->get_meta_data() as $meta ) {
			$meta_data[] = array(
				'id' => $meta->id,
				'key' => $meta->key,
				'value' => $meta->value
			);
		}
		$data['meta_data'] = $meta_data;

		return $data;
	}
}
