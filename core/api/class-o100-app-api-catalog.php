<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_App_API_Catalog {

	public function __construct() {
		// Register /branches endpoint
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Hook into standard WooCommerce REST API to apply branch visibility logic
		add_filter( 'woocommerce_rest_product_object_query', array( $this, 'filter_rest_products_by_branch' ), 10, 2 );
		add_filter( 'woocommerce_rest_product_cat_query', array( $this, 'filter_rest_terms_by_branch' ), 10, 2 );
	}

	public function register_routes() {
		register_rest_route( 'order100/v1', '/app/branches', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_branches' ),
			'permission_callback' => '__return_true', // Rely on interceptor auth
		) );
	}

	public function get_branches( WP_REST_Request $request ) {
		$opts = get_option( 'o100_locations', array() );
		$is_loc_enabled = !empty( $opts['o100_enable_loc'] ) && $opts['o100_enable_loc'] === 'on';

		if ( ! $is_loc_enabled ) {
			return rest_ensure_response( array() );
		}

		$args = array(
			'post_type'      => 'o100_location',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC'
		);

		$posts = get_posts( $args );
		$branches = array();

		foreach ( $posts as $post ) {
			$branches[] = array(
				'id'   => $post->ID,
				'name' => $post->post_title
			);
		}

		return rest_ensure_response( $branches );
	}

	public function filter_rest_products_by_branch( $args, $request ) {
		$branch_id = $request->get_param( 'branch_id' );
		if ( empty( $branch_id ) ) {
			return $args;
		}

		$hidden_json = get_post_meta( intval( $branch_id ), 'o100_hidden_products', true );
		$hidden_ids  = json_decode( $hidden_json, true );

		if ( is_array( $hidden_ids ) && ! empty( $hidden_ids ) ) {
			if ( isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ) {
				$args['post__not_in'] = array_merge( $args['post__not_in'], $hidden_ids );
			} else {
				$args['post__not_in'] = $hidden_ids;
			}
		}

		return $args;
	}

	public function filter_rest_terms_by_branch( $args, $request ) {
		$branch_id = $request->get_param( 'branch_id' );
		if ( empty( $branch_id ) ) {
			return $args;
		}

		$hidden_json = get_post_meta( intval( $branch_id ), 'o100_hidden_cats', true );
		$hidden_ids  = json_decode( $hidden_json, true );

		if ( is_array( $hidden_ids ) && ! empty( $hidden_ids ) ) {
			if ( isset( $args['exclude'] ) && is_array( $args['exclude'] ) ) {
				$args['exclude'] = array_merge( $args['exclude'], $hidden_ids );
			} else {
				$args['exclude'] = $hidden_ids;
			}
		}

		return $args;
	}
}
