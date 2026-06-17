<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Menu_Maker_API {

	protected $namespace;
	protected $rest_base;

	public function __construct() {
		$this->namespace = 'o100/v1';
		$this->rest_base = 'menu-maker';
	}

	public static function init() {
		$instance = new self();
		add_action( 'rest_api_init', array( $instance, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/categories', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_categories' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_category' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_category' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/categories/reorder', array(
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'reorder_categories' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/items', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_items' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_item' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_item' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/items/details', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_item_details' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/items/bulk', array(
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'bulk_edit_items' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/icons', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_custom_icons' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
		
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/modifiers', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_modifiers' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_modifier' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_modifier' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/displays', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_displays' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'save_display' ), 'permission_callback' => array( $this, 'check_auth' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_display' ), 'permission_callback' => array( $this, 'check_auth' ) ),
		) );
	}

	public function check_auth() {
		return current_user_can( 'manage_woocommerce' );
	}



	public function get_svg_content( $filename ) {
		$dir = O100_PATH . 'assets/icons/custom/';
		$path = $dir . $filename;
		if ( ! file_exists( $path ) ) return '';
		
		$content = file_get_contents( $path );
		if ( empty( $content ) ) return '';

		// Strip XML header and DOCTYPE
		$content = preg_replace( '/<\?xml.*?\?>/i', '', $content );
		$content = preg_replace( '/<!DOCTYPE.*?>/i', '', $content );
		
		// Strip comments
		$content = preg_replace( '/<!--.*?-->/s', '', $content );
		
		// Optional: Remove hardcoded fills so it inherits Tailwind text color
		// $content = preg_replace( '/\sfill="[^"]*"/', '', $content );
		// Ensure it inherits currentColor and scales properly
		$content = str_replace( '<svg ', '<svg fill="currentColor" class="w-full h-full" ', $content );
		
		return trim( $content );
	}

	public function get_custom_icons( WP_REST_Request $request ) {
		
		$dir = O100_PATH . 'assets/icons/custom/';
		$icons = array();

		if ( is_dir( $dir ) ) {
			$files = scandir( $dir );
			foreach ( $files as $file ) {
				if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'svg' ) {
					$content = $this->get_svg_content( $file );
					if ( $content ) {
						$icons[] = array(
							'id'      => 'svg:' . $file,
							'name'    => pathinfo( $file, PATHINFO_FILENAME ),
							'content' => $content
						);
					}
				}
			}
		}

		return rest_ensure_response( $icons  );
	}

	public function get_categories( WP_REST_Request $request ) {
		

		$terms = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'meta_key'   => 'order',
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		) );

		$categories = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
				$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
				
				$order        = get_term_meta( $term->term_id, 'order', true );
				$icon_type    = get_term_meta( $term->term_id, 'o100_cat_icon_type', true );
				$icon         = get_term_meta( $term->term_id, 'o100_cat_icon', true );
				$icon_content = '';
				if ( $icon_type === 'icon' && strpos( $icon, 'svg:' ) === 0 ) {
					$filename = substr( $icon, 4 );
					$icon_content = $this->get_svg_content( $filename );
				}

				$branches     = get_term_meta( $term->term_id, 'o100_cat_branches', true );
				if ( ! is_array( $branches ) ) $branches = array( 'all' );

				$categories[] = array(
					'id'           => $term->term_id,
					'name'         => html_entity_decode($term->name),
					'description'  => html_entity_decode($term->description),
					'image_id'     => $thumbnail_id,
					'image_url'    => $image_url,
					'count'        => $term->count,
					'order'        => $order ? intval($order) : 0,
					'icon_type'    => $icon_type ? $icon_type : 'none', // none, icon, image
					'icon'         => $icon,
					'icon_content' => $icon_content,
					'branches'     => $branches,
				);
			}
		}

		$branches = array();
		$loc_query = new WP_Query( array(
			'post_type'      => 'o100_location',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );
		
		if ( $loc_query->have_posts() ) {
			foreach ( $loc_query->posts as $post ) {
				$branches[] = array(
					'id'   => $post->ID,
					'name' => html_entity_decode( $post->post_title )
				);
			}
		}

		$tag_terms = get_terms( array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
		) );
		$tags = array();
		if ( ! is_wp_error( $tag_terms ) && ! empty( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = array(
					'id'   => $term->term_id,
					'name' => html_entity_decode($term->name)
				);
			}
		}

		return rest_ensure_response( array(
			'categories' => $categories,
			'branches'   => $branches,
			'tags'       => $tags,
		)  );
	}

	public function save_category( WP_REST_Request $request ) {
		

		$id          = $request->has_param( 'id' ) ? intval( $request->get_param( 'id' ) ) : 0;
		$name        = $request->has_param( 'name' ) ? sanitize_text_field( wp_unslash( $request->get_param( 'name' ) ) ) : '';
		$description = $request->has_param( 'description' ) ? sanitize_textarea_field( wp_unslash( $request->get_param( 'description' ) ) ) : '';
		$image_id    = $request->has_param( 'image_id' ) ? intval( $request->get_param( 'image_id' ) ) : 0;
		$order       = $request->has_param( 'order' ) ? intval( $request->get_param( 'order' ) ) : 0;
		$icon_type   = $request->has_param( 'icon_type' ) ? sanitize_text_field( $request->get_param( 'icon_type' ) ) : 'none';
		$icon        = $request->has_param( 'icon' ) ? sanitize_text_field( $request->get_param( 'icon' ) ) : '';
		
		$branches_raw = $request->has_param( 'branches' ) ? $request->get_param( 'branches' ) : '';
		$branches     = array();
		if ( !empty( $branches_raw ) ) {
			$branches = array_map( 'sanitize_text_field', explode( ',', $branches_raw ) );
		}
		if ( empty( $branches ) ) {
			$branches = array( 'all' ); // Default
		}

		if ( empty( $name ) ) {
			return new WP_Error( 'menu_maker_error', 'Category name is required.' , array( 'status' => 400 ) );
		}

		$args = array(
			'name'        => $name,
			'description' => $description,
		);

		if ( $id > 0 ) {
			$term = wp_update_term( $id, 'product_cat', $args );
		} else {
			$term = wp_insert_term( $name, 'product_cat', $args );
		}

		if ( is_wp_error( $term ) ) {
			return new WP_Error( 'menu_maker_error', $term->get_error_message() , array( 'status' => 400 ) );
		}

		$term_id = $term['term_id'];

		if ( $image_id > 0 ) {
			update_term_meta( $term_id, 'thumbnail_id', $image_id );
		} else {
			delete_term_meta( $term_id, 'thumbnail_id' );
		}

		update_term_meta( $term_id, 'order', $order );
		update_term_meta( $term_id, 'o100_cat_icon_type', $icon_type );
		update_term_meta( $term_id, 'o100_cat_icon', $icon );
		update_term_meta( $term_id, 'o100_cat_branches', $branches );

		return rest_ensure_response( array( 'id' => $term_id )  );
	}

	public function reorder_categories( WP_REST_Request $request ) {
		
		$orders = $request->has_param( 'orders' ) ? $request->get_param( 'orders' ) : '';
		if ( ! empty( $orders ) ) {
			$orders = array_map( 'intval', explode( ',', $orders ) );
			foreach ( $orders as $index => $term_id ) {
				update_term_meta( $term_id, 'order', $index );
			}
			return rest_ensure_response( array( 'success' => true ) );
		}
		return new WP_Error( 'menu_maker_error', 'No orders provided.' , array( 'status' => 400 ) );
	}

	public function delete_category( WP_REST_Request $request ) {
		

		$id = $request->has_param( 'id' ) ? intval( $request->get_param( 'id' ) ) : 0;
		if ( $id > 0 ) {
			$result = wp_delete_term( $id, 'product_cat' );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'menu_maker_error', $result->get_error_message() , array( 'status' => 400 ) );
			}
			return rest_ensure_response( array( 'success' => true ) );
		}
		return new WP_Error( 'menu_maker_error', 'Invalid ID' , array( 'status' => 400 ) );
	}

	public function get_items( WP_REST_Request $request ) {
		

		$category_id = $request->has_param( 'category_id' ) ? intval( $request->get_param( 'category_id' ) ) : 0;

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( $category_id > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			);
		}

		$query = new WP_Query( $args );
		$items = array();

		// Fetch active promotions
		$active_promos = array();
		if ( class_exists( 'O100_Promotions_DB' ) ) {
			$active_promos = O100_Promotions_DB::query( array( 'status' => 'active' ) );
		}

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$product = wc_get_product( $post->ID );
				if ( ! $product ) continue;

				$image_id  = $product->get_image_id();
				$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

				$terms = get_the_terms( $post->ID, 'product_cat' );
				
				$categories = array();
				$seen_cat_names = array();
				if ( $terms && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$raw_name = html_entity_decode( $term->name );
						$key = strtolower( trim( $raw_name ) );
						if ( ! isset( $seen_cat_names[$key] ) ) {
							$seen_cat_names[$key] = true;
							$categories[] = array(
								'id'   => $term->term_id,
								'name' => $raw_name
							);
						}
					}
				}
				
				$cat_id = ! empty( $categories ) ? $categories[0]['id'] : 0;
				$cat_name = ! empty( $categories ) ? $categories[0]['name'] : '';

				// Check Promotions using exact Impacts registry to ensure accuracy with detail view
				$item_promos = array();
				$impacts = apply_filters( 'o100_get_product_impacts', array(), $post->ID, $product->get_category_ids() );
				if ( !empty($impacts) ) {
					foreach ($impacts as $imp) {
						if (isset($imp['title']) && $imp['module'] !== 'System') { // System impacts like Order Method Restriction don't count as promo badges
							$item_promos[] = $imp['title'];
						}
					}
				}
				
				// Foolproof array unique for 1D array
				$item_promos = array_values(array_unique($item_promos));

					$price = $product->get_price();
					$regular_price = $product->get_regular_price();
					$sale_price = $product->get_sale_price();

					if ( $product->is_type( 'variable' ) ) {
						$min_price = $product->get_variation_price( 'min', true );
						$max_price = $product->get_variation_price( 'max', true );
						if ( $min_price != $max_price ) {
							// If we prepend $ in frontend, we only need to add $ to the second part here
							$regular_price = $min_price . ' - $' . $max_price;
						} else {
							$regular_price = $min_price;
						}
						$price = $regular_price;
						$sale_price = '';
					}

					// Check Selling Rules
					$has_rules = false;
					$time_start = get_post_meta( $post->ID, 'o100_menu_rule_time_start', true );
					$time_end = get_post_meta( $post->ID, 'o100_menu_rule_time_end', true );
					$date_start = get_post_meta( $post->ID, 'o100_menu_rule_date_start', true );
					$date_end = get_post_meta( $post->ID, 'o100_menu_rule_date_end', true );
					$rule_days = get_post_meta( $post->ID, 'o100_menu_rule_days', true );
					if ( ! empty( $time_start ) || ! empty( $time_end ) || ! empty( $date_start ) || ! empty( $date_end ) ) {
						$has_rules = true;
					}
					if ( is_array( $rule_days ) && count( $rule_days ) < 7 && count( $rule_days ) > 0 ) {
						$has_rules = true;
					}

					$items[] = array(
						'id'            => $post->ID,
						'title'         => html_entity_decode($post->post_title),
						'description'   => html_entity_decode($post->post_content),
						'excerpt'       => html_entity_decode($post->post_excerpt),
						'price'         => $price, // active price
						'regular_price' => $regular_price,
						'sale_price'    => $sale_price,
						'stock_status'  => $product->get_stock_status(), // 'instock' or 'outofstock'
					'image_id'      => $image_id,
					'image_url'     => $image_url,
					'category_id'   => $cat_id,
					'category_name' => $cat_name,
					'categories'    => $categories,
					'promotions'    => $item_promos,
					'has_rules'     => $has_rules,
				);
			}
		}

		return rest_ensure_response( $items  );
	}

	public function get_item_details( WP_REST_Request $request ) {
		

		$id = $request->has_param( 'id' ) ? intval( $request->get_param( 'id' ) ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'menu_maker_error', 'Invalid ID' , array( 'status' => 400 ) );
		}

		$product = wc_get_product( $id );
		if ( ! $product ) {
			return new WP_Error( 'menu_maker_error', 'Product not found' , array( 'status' => 400 ) );
		}

		$image_id = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		
		$gallery_ids = $product->get_gallery_image_ids();
		$gallery = array();
		foreach ( $gallery_ids as $g_id ) {
			$gallery[] = array(
				'id' => $g_id,
				'url' => wp_get_attachment_image_url( $g_id, 'thumbnail' )
			);
		}

		$meta_methods = get_post_meta( $id, 'o100_menu_rule_method', true );
		$meta_days    = get_post_meta( $id, 'o100_menu_rule_days', true );
		$meta_branch  = get_post_meta( $id, 'o100_menu_rule_branches', true );

		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();
		$addon_options = get_post_meta( $id, 'o100_addon_groups', true ) ?: array();
		if ( ! is_array( $addon_options ) ) {
			$addon_options = array();
		}

		// Completely remove ANY existing woo_var groups from the database data
		// to ensure there is strictly ONLY ONE variations block and no ghosts
		// This must run for ALL products, even if they are no longer variable!
		$cleaned_options = array();
		$found_existing = false;
		$existing_display_type = '';
		$existing_name = 'Variations';
		
		foreach ( $addon_options as $grp ) {
			$is_legacy = false;
			if ( $product->is_type( 'variable' ) && isset($grp['_name']) && strtolower(trim($grp['_name'])) === 'variations' ) {
				$is_legacy = true;
			}
			if ( isset($grp['_is_woo_var']) && $grp['_is_woo_var'] === 'yes' ) {
				$is_legacy = true;
			}

			if ( $is_legacy ) {
				$found_existing = true;
				if (isset($grp['_display_type'])) $existing_display_type = $grp['_display_type'];
				if (isset($grp['_name'])) $existing_name = $grp['_name'];
				continue; // Skip it so it doesn't get added to cleaned_options
			}
			$cleaned_options[] = $grp;
		}
		
		$addon_options = $cleaned_options;

		if ( $product->is_type( 'variable' ) ) {
			$min_price   = $product->get_variation_price( 'min', true );
			
			$regular_price = $min_price;
			$sale_price    = '';

			$variations = $product->get_available_variations();
			if ( ! empty( $variations ) ) {
				foreach ( $variations as $var ) {
					if ( floatval($var['display_price']) < floatval($var['display_regular_price']) ) {
						$sale_price = $min_price;
						break;
					}
				}
				$options_map = array();
				$is_first = true;
				foreach ( $variations as $var ) {
					$vid = isset($var['variation_id']) ? $var['variation_id'] : 0;
					
					$attr_names = array();
					foreach ( $var['attributes'] as $key => $val ) {
						$taxonomy = str_replace( 'attribute_', '', $key );
						if ( taxonomy_exists( $taxonomy ) ) {
							$term = get_term_by( 'slug', $val, $taxonomy );
							if ( $term && ! is_wp_error( $term ) ) {
								$val = $term->name;
							}
						}
						// Fallback to formatting the slug if it's a custom attribute or term not found
						$attr_names[] = ucwords( str_replace( '-', ' ', $val ) );
					}
					$name = implode( ' - ', $attr_names );
					if ( empty( $name ) ) {
						$name = 'Option';
					}

					// Foolproof deduplication key: strip all non-alphanumeric characters
					$dedup_key = preg_replace('/[^a-z0-9]/', '', strtolower($name));
					
					// Only keep the first valid variation for this option name
					if ( ! isset( $options_map[$dedup_key] ) ) {
						$options_map[$dedup_key] = array(
							'vid'   => $vid,
							'name'  => $name,
							'price' => (string)floatval( $var['display_regular_price'] ),
							'sale_price' => (floatval($var['display_price']) < floatval($var['display_regular_price'])) ? (string)floatval($var['display_price']) : '',
							'type'  => 'fixed',
							'def'   => $is_first ? 'yes' : 'no',
							'dis'   => 'no',
							'image' => ''
						);
						$is_first = false;
					}
				}
				$options = array_values($options_map);

				$converted_group = array(
					'_id'       => 'o100-var-' . mt_rand(10000, 99999),
					'_name'     => 'Variations',
					'_type'     => 'radio',
					'_required' => 'yes',
					'_min_op'   => '1',
					'_max_op'   => '1',
					'_options'  => $options,
					'_is_woo_var'=> 'yes'
				);
				
				if ($found_existing) {
					$converted_group['_display_type'] = $existing_display_type;
					$converted_group['_name'] = $existing_name;
				}

				array_unshift( $addon_options, $converted_group );
			}
		}

			$clean_methods = is_array($meta_methods) ? array_filter($meta_methods) : array();
			$clean_days = is_array($meta_days) ? array_filter($meta_days) : array();
			$clean_branches = is_array($meta_branch) ? array_filter($meta_branch) : array();

			$data = array(
				'id'             => $id,
				'title'          => $product->get_name(),
				'excerpt'        => $product->get_short_description(),
				'description'    => $product->get_description(),
				'regular_price'  => $regular_price,
				'sale_price'     => $sale_price,
				'stock_status'   => $product->get_stock_status(),
				'category_ids'   => $product->get_category_ids(),
				'image_id'       => $image_id,
				'image_url'      => $image_url,
				'gallery'        => $gallery,
				'labels'         => get_post_meta( $id, 'o100_product_labels', true ) ?: array(),
				'addon_exclude'  => get_post_meta( $id, 'o100_addon_exclude', true ) === 'on',
				'addon_options'  => $addon_options,
				'is_variable'    => $product->is_type('variable'),
				'rule_methods'   => empty($clean_methods) ? array('delivery', 'pickup') : array_values($clean_methods),
				'rule_start'     => get_post_meta( $id, 'o100_menu_rule_date_start', true ),
				'rule_end'       => get_post_meta( $id, 'o100_menu_rule_date_end', true ),
				'rule_days'      => empty($clean_days) ? array('mon','tue','wed','thu','fri','sat','sun') : array_values($clean_days),
				'rule_time_start'=> get_post_meta( $id, 'o100_menu_rule_time_start', true ),
				'rule_time_end'  => get_post_meta( $id, 'o100_menu_rule_time_end', true ),
				'rule_branches'  => empty($clean_branches) ? array('all') : array_values($clean_branches),
				'impacts'        => apply_filters( 'o100_get_product_impacts', array(), $id, $product->get_category_ids() ),
			);

		// Append Native Menu Rules
		$rule_methods = $data['rule_methods'];
		if ( is_array( $rule_methods ) && count( $rule_methods ) < 2 && count( $rule_methods ) > 0 ) {
			$methods_label = array_map( 'ucfirst', $rule_methods );
			$data['impacts'][] = array(
				'module'      => 'System',
				'title'       => 'Order Method Restriction',
				'status'      => 'Active',
				'description' => 'Only available for ' . implode( ' and ', $methods_label ) . '.',
				'action_url'  => '#', // Usually edited in the same modal
				'type'        => 'neutral',
			);
		} elseif ( is_array( $rule_methods ) && count( $rule_methods ) === 0 ) {
			$data['impacts'][] = array(
				'module'      => 'System',
				'title'       => 'Order Method Restriction',
				'status'      => 'Disabled',
				'description' => 'Not available for any order method.',
				'action_url'  => '#',
				'type'        => 'negative',
			);
		}

		$rule_start = $data['rule_start'];
		$rule_end = $data['rule_end'];
		if ( ! empty( $rule_start ) || ! empty( $rule_end ) ) {
			$date_str = '';
			if ( $rule_start && $rule_end ) $date_str = $rule_start . ' to ' . $rule_end;
			elseif ( $rule_start ) $date_str = 'from ' . $rule_start;
			elseif ( $rule_end ) $date_str = 'until ' . $rule_end;
			
			$data['impacts'][] = array(
				'module'      => 'System',
				'title'       => 'Date Restriction',
				'status'      => 'Scheduled',
				'description' => 'Only available ' . $date_str . '.',
				'action_url'  => '#', 
				'type'        => 'neutral',
			);
		}

		$rule_time_start = $data['rule_time_start'];
		$rule_time_end = $data['rule_time_end'];
		if ( ! empty( $rule_time_start ) || ! empty( $rule_time_end ) ) {
			$time_str = '';
			if ( $rule_time_start && $rule_time_end ) $time_str = $rule_time_start . ' - ' . $rule_time_end;
			elseif ( $rule_time_start ) $time_str = 'after ' . $rule_time_start;
			elseif ( $rule_time_end ) $time_str = 'before ' . $rule_time_end;
			
			$data['impacts'][] = array(
				'module'      => 'System',
				'title'       => 'Time Restriction',
				'status'      => 'Scheduled',
				'description' => 'Only available ' . $time_str . '.',
				'action_url'  => '#', 
				'type'        => 'neutral',
			);
		}

		$rule_days = $data['rule_days'];
		if ( is_array( $rule_days ) && count( $rule_days ) < 7 ) {
			$days_label = array_map( 'ucfirst', $rule_days );
			$data['impacts'][] = array(
				'module'      => 'System',
				'title'       => 'Weekday Restriction',
				'status'      => 'Scheduled',
				'description' => 'Only available on ' . implode( ', ', $days_label ) . '.',
				'action_url'  => '#', 
				'type'        => 'neutral',
			);
		}

		$rule_branches = $data['rule_branches'];
		if ( is_array( $rule_branches ) && count( $rule_branches ) > 0 && ! in_array( 'all', $rule_branches ) ) {
			$data['impacts'][] = array(
				'module'      => 'System',
				'title'       => 'Branch Restriction',
				'status'      => 'Active',
				'description' => 'Only available at selected branches.',
				'action_url'  => '#', 
				'type'        => 'neutral',
			);
		}

		return rest_ensure_response( $data  );
	}

	public function save_item( WP_REST_Request $request ) {
		

		$id           = $request->has_param( 'id' ) ? intval( $request->get_param( 'id' ) ) : 0;
		$title        = $request->has_param( 'title' ) ? sanitize_text_field( wp_unslash( $request->get_param( 'title' ) ) ) : '';
		$excerpt      = $request->has_param( 'excerpt' ) ? wp_kses_post( wp_unslash( $request->get_param( 'excerpt' ) ) ) : '';
		$description  = $request->has_param( 'description' ) ? wp_kses_post( wp_unslash( $request->get_param( 'description' ) ) ) : '';
		
		// If only one is provided, sync it to the other
		if ( empty( $excerpt ) && ! empty( $description ) ) {
			$excerpt = $description;
		} elseif ( empty( $description ) && ! empty( $excerpt ) ) {
			$description = $excerpt;
		}

		$regular_price= $request->has_param( 'regular_price' ) ? sanitize_text_field( $request->get_param( 'regular_price' ) ) : '';
		$sale_price   = $request->has_param( 'sale_price' ) ? sanitize_text_field( $request->get_param( 'sale_price' ) ) : '';
		$stock_status = $request->has_param( 'stock_status' ) && $request->get_param( 'stock_status' ) === 'outofstock' ? 'outofstock' : 'instock';
		$image_id     = $request->has_param( 'image_id' ) ? intval( $request->get_param( 'image_id' ) ) : 0;
		
		$category_ids = $request->has_param( 'category_ids' ) && !empty($request->get_param( 'category_ids' )) ? array_map( 'intval', explode(',', $request->get_param( 'category_ids' )) ) : array();
		$gallery      = $request->has_param( 'gallery' ) && !empty($request->get_param( 'gallery' )) ? array_map( 'intval', explode(',', $request->get_param( 'gallery' )) ) : array();
		$labels       = $request->has_param( 'labels' ) && !empty($request->get_param( 'labels' )) ? array_map( 'sanitize_text_field', explode(',', $request->get_param( 'labels' )) ) : array();
		
		$addon_exclude= $request->has_param( 'addon_exclude' ) && $request->get_param( 'addon_exclude' ) === 'true' ? 'on' : '';
		// We expect addon_options as a JSON string from frontend
		$addon_options_raw = $request->has_param( 'addon_options' ) ? wp_unslash( $request->get_param( 'addon_options' ) ) : '';
		$addon_options = !empty($addon_options_raw) ? json_decode($addon_options_raw, true) : array();
		if ( ! is_array( $addon_options ) ) {
			$addon_options = array();
		} else {
			foreach ( $addon_options as &$grp ) {
				if ( isset( $grp['_options'] ) && is_string( $grp['_options'] ) ) {
					$decoded = json_decode( stripslashes( $grp['_options'] ), true );
					$grp['_options'] = is_array( $decoded ) ? $decoded : array();
				} elseif ( ! isset( $grp['_options'] ) || ! is_array( $grp['_options'] ) ) {
					$grp['_options'] = array();
				}
			}
		}

		$rule_methods    = $request->has_param( 'rule_methods' ) ? ( $request->get_param( 'rule_methods' ) === '' ? array() : array_map( 'sanitize_text_field', explode(',', $request->get_param( 'rule_methods' )) ) ) : array('delivery', 'pickup');
		$rule_start      = $request->has_param( 'rule_start' ) ? sanitize_text_field( $request->get_param( 'rule_start' ) ) : '';
		$rule_end        = $request->has_param( 'rule_end' ) ? sanitize_text_field( $request->get_param( 'rule_end' ) ) : '';
		
		$rule_days       = $request->has_param( 'rule_days' ) ? ( $request->get_param( 'rule_days' ) === '' ? array() : array_map( 'sanitize_text_field', explode(',', $request->get_param( 'rule_days' )) ) ) : array('mon','tue','wed','thu','fri','sat','sun');
		$rule_time_start = $request->has_param( 'rule_time_start' ) ? sanitize_text_field( $request->get_param( 'rule_time_start' ) ) : '';
		$rule_time_end   = $request->has_param( 'rule_time_end' ) ? sanitize_text_field( $request->get_param( 'rule_time_end' ) ) : '';
		$rule_branches   = $request->has_param( 'rule_branches' ) ? ( $request->get_param( 'rule_branches' ) === '' ? array() : array_map( 'sanitize_text_field', explode(',', $request->get_param( 'rule_branches' )) ) ) : array('all');

		if ( empty( $title ) ) {
			return new WP_Error( 'menu_maker_error', 'Item title is required.' , array( 'status' => 400 ) );
		}

		$post_data = array(
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
			'post_content' => $description,
			'post_status'  => 'publish',
			'post_type'    => 'product',
		);

		if ( $id > 0 ) {
			$post_data['ID'] = $id;
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error( 'menu_maker_error', $post_id->get_error_message() , array( 'status' => 400 ) );
		}

		// Update WooCommerce Meta via CRUD
		$product = wc_get_product( $post_id );
		if ( $product ) {
			$product->set_regular_price( $regular_price );
			$product->set_sale_price( $sale_price );
			
			if ( $id == 0 ) {
				$product->set_catalog_visibility( 'visible' );
			}
			$product->set_stock_status( $stock_status );
			$product->set_manage_stock( false ); // Don't track quantities
			$product->set_image_id( $image_id > 0 ? $image_id : '' );
			$product->set_gallery_image_ids( $gallery );
			
			$product->set_category_ids( $category_ids );
			
			// Save other meta
			$product->update_meta_data( 'o100_product_labels', $labels );
			$product->update_meta_data( 'o100_addon_exclude', $addon_exclude );
			// Sync variation prices if _is_woo_var is present
			if ( is_array( $addon_options ) && $product->is_type( 'variable' ) ) {
				foreach ( $addon_options as $group ) {
					if ( isset( $group['_is_woo_var'] ) && $group['_is_woo_var'] === 'yes' && ! empty( $group['_options'] ) ) {
						foreach ( $group['_options'] as $opt ) {
							if ( ! empty( $opt['vid'] ) ) {
								$variation = wc_get_product( $opt['vid'] );
								if ( $variation && $variation->get_parent_id() == $post_id ) {
									$price = isset( $opt['price'] ) ? $opt['price'] : '';
									$sale_price = isset( $opt['sale_price'] ) ? $opt['sale_price'] : '';
									
									if ( $price !== '' ) {
										$variation->set_regular_price( $price );
									}
									$variation->set_sale_price( $sale_price );
									if ( $sale_price === '' ) {
										$variation->set_price( $price );
									} else {
										$variation->set_price( $sale_price );
									}
									$variation->save();
								}
							}
						}
					}
				}
			}

			// Filter out WooCommerce native variations from being saved locally
			// to avoid duplicate data and ghosts, they are dynamically generated during GET
			if ( is_array( $addon_options ) ) {
				$clean_addon_options = array();
				foreach ( $addon_options as $group ) {
					$is_ghost = false;
					if ( isset( $group['_is_woo_var'] ) && $group['_is_woo_var'] === 'yes' ) {
						$is_ghost = true;
					}
					if ( $product->is_type( 'variable' ) && isset( $group['_name'] ) && strtolower(trim($group['_name'])) === 'variations' ) {
						$is_ghost = true;
					}
					if ( ! $is_ghost ) {
						$clean_addon_options[] = $group;
					}
				}
				$product->update_meta_data( 'o100_addon_groups', $clean_addon_options );
			}
			
			$product->update_meta_data( 'o100_menu_rule_method', $rule_methods );
			$product->update_meta_data( 'o100_menu_rule_date_start', $rule_start );
			$product->update_meta_data( 'o100_menu_rule_date_end', $rule_end );
			$product->update_meta_data( 'o100_menu_rule_days', $rule_days );
			$product->update_meta_data( 'o100_menu_rule_time_start', $rule_time_start );
			$product->update_meta_data( 'o100_menu_rule_time_end', $rule_time_end );
			$product->update_meta_data( 'o100_menu_rule_branches', $rule_branches );

			$product->save();

			// Sync variation prices if _is_woo_var is present
			if ( is_array( $addon_options ) && $product->is_type( 'variable' ) ) {
				foreach ( $addon_options as $group ) {
					if ( isset( $group['_is_woo_var'] ) && $group['_is_woo_var'] === 'yes' && ! empty( $group['_options'] ) ) {
						foreach ( $group['_options'] as $opt ) {
							if ( ! empty( $opt['vid'] ) ) {
								$variation = wc_get_product( $opt['vid'] );
								if ( $variation && $variation->get_parent_id() == $post_id ) {
									$price = isset( $opt['price'] ) ? $opt['price'] : '';
									$sale_price = isset( $opt['sale_price'] ) ? $opt['sale_price'] : '';
									
									if ( $price !== '' ) {
										$variation->set_regular_price( $price );
									}
									$variation->set_sale_price( $sale_price );
									if ( $sale_price === '' ) {
										$variation->set_price( $price );
									} else {
										$variation->set_price( $sale_price );
									}
									$variation->save();
								}
							}
						}
					}
				}
			}

		}

		return rest_ensure_response( array( 'id' => $post_id )  );
	}

	public function delete_item( WP_REST_Request $request ) {
		

		$id = $request->has_param( 'id' ) ? intval( $request->get_param( 'id' ) ) : 0;
		if ( $id > 0 ) {
			// Move to trash
			$result = wp_trash_post( $id );
			if ( ! $result ) {
				return new WP_Error( 'menu_maker_error', 'Failed to delete item.' , array( 'status' => 400 ) );
			}
			return rest_ensure_response( array( 'success' => true ) );
		}
		return new WP_Error( 'menu_maker_error', 'Invalid ID' , array( 'status' => 400 ) );
	}

	public function bulk_edit_items( WP_REST_Request $request ) {
		
		
		$ids = $request->has_param( 'ids' ) ? $request->get_param( 'ids' ) : '';
		$bulk_action = $request->has_param( 'bulk_action' ) ? sanitize_text_field( $request->get_param( 'bulk_action' ) ) : '';
		
		if ( empty( $ids ) || empty( $bulk_action ) ) {
			return new WP_Error( 'menu_maker_error', 'Missing data.' , array( 'status' => 400 ) );
		}

		$ids = array_map( 'intval', explode( ',', $ids ) );

		foreach ( $ids as $id ) {
			if ( $bulk_action === 'delete' ) {
				wp_trash_post( $id );
			} elseif ( $bulk_action === 'instock' || $bulk_action === 'outofstock' ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$product->set_stock_status( $bulk_action );
					$product->save();
				}
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	// ==========================================
	// MODIFIERS ENDPOINTS
	// ==========================================
	
	public function get_modifiers( WP_REST_Request $request ) {
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) && is_array( $settings['o100_addon_groups'] ) ? array_values( $settings['o100_addon_groups'] ) : array();
		
		// Backward compatibility mapping
		foreach ( $groups as &$group ) {
			if ( ! isset( $group['_id'] ) && isset( $group['id'] ) ) { $group['_id'] = $group['id']; }
			
			// Heal corrupted empty IDs
			if ( empty( $group['_id'] ) ) {
				$group['_id'] = 'o100_healed_' . uniqid();
			}

			// Fix for corrupted _type data being saved as empty string
			if ( ! isset( $group['_type'] ) && isset( $group['type'] ) ) { $group['_type'] = $group['type']; }
			if ( empty( $group['_type'] ) ) { $group['_type'] = 'checkbox'; }
			
			if ( ! isset( $group['_options'] ) && isset( $group['options'] ) ) { $group['_options'] = $group['options']; }
			if ( isset( $group['_options'] ) && is_string( $group['_options'] ) ) {
				$decoded = json_decode( stripslashes( $group['_options'] ), true );
				$group['_options'] = is_array( $decoded ) ? $decoded : array();
			} elseif ( ! isset( $group['_options'] ) || ! is_array( $group['_options'] ) ) {
				$group['_options'] = array();
			}

			if ( ! isset( $group['_apply_to'] ) && isset( $group['apply_to'] ) ) { $group['_apply_to'] = $group['apply_to']; }
			if ( ! isset( $group['_required'] ) && isset( $group['required'] ) ) { $group['_required'] = $group['required']; }

			if ( ! isset( $group['_category_ids'] ) && isset( $group['category_ids'] ) ) { $group['_category_ids'] = $group['category_ids']; }
			if ( isset( $group['_category_ids'] ) && is_string( $group['_category_ids'] ) ) {
				$group['_category_ids'] = array_filter( array_map( 'trim', explode( ',', $group['_category_ids'] ) ) );
			} elseif ( ! isset( $group['_category_ids'] ) || ! is_array( $group['_category_ids'] ) ) {
				$group['_category_ids'] = array();
			}

			if ( ! isset( $group['_product_ids'] ) && isset( $group['product_ids'] ) ) { $group['_product_ids'] = $group['product_ids']; }
			if ( ! isset( $group['_min_op'] ) && isset( $group['min_op'] ) ) { $group['_min_op'] = $group['min_op']; }
			if ( ! isset( $group['_max_op'] ) && isset( $group['max_op'] ) ) { $group['_max_op'] = $group['max_op']; }
			if ( ! isset( $group['_enb_qty'] ) && isset( $group['enb_qty'] ) ) { $group['_enb_qty'] = $group['enb_qty']; }
			if ( ! isset( $group['_enb_img'] ) && isset( $group['enb_img'] ) ) { $group['_enb_img'] = $group['enb_img']; }
			if ( ! isset( $group['_con_logic'] ) && isset( $group['con_logic'] ) ) { $group['_con_logic'] = $group['con_logic']; }
			if ( ! isset( $group['_enb_logic'] ) && isset( $group['enb_logic'] ) ) { $group['_enb_logic'] = $group['enb_logic']; }
		}

		return rest_ensure_response( $groups );
	}

	public function save_modifier( WP_REST_Request $request ) {
		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) && is_array( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			return new WP_Error( 'invalid_data', 'No data provided', array( 'status' => 400 ) );
		}

		$id = ! empty( $data['_id'] ) ? sanitize_text_field( $data['_id'] ) : 'o100_' . uniqid();
		$new_group = $data;
		$new_group['_id'] = $id;

		// ENFORCE STRICT TYPING: Ensure _options is an array
		if ( isset( $new_group['_options'] ) && is_string( $new_group['_options'] ) ) {
			$decoded = json_decode( stripslashes( $new_group['_options'] ), true );
			$new_group['_options'] = is_array( $decoded ) ? $decoded : array();
		} elseif ( ! isset( $new_group['_options'] ) || ! is_array( $new_group['_options'] ) ) {
			$new_group['_options'] = array();
		}

		// ENFORCE STRICT TYPING: Ensure _category_ids is an array
		if ( isset( $new_group['_category_ids'] ) && is_string( $new_group['_category_ids'] ) ) {
			$new_group['_category_ids'] = array_filter( array_map( 'trim', explode( ',', $new_group['_category_ids'] ) ) );
		} elseif ( ! isset( $new_group['_category_ids'] ) || ! is_array( $new_group['_category_ids'] ) ) {
			$new_group['_category_ids'] = array();
		}

		// ENFORCE STRICT TYPING: Ensure _product_ids is a string
		if ( isset( $new_group['_product_ids'] ) && is_array( $new_group['_product_ids'] ) ) {
			$new_group['_product_ids'] = implode( ',', $new_group['_product_ids'] );
		}

		$found = false;
		foreach ( $groups as $k => $grp ) {
			$existing_id = isset( $grp['_id'] ) ? $grp['_id'] : ( isset( $grp['id'] ) ? $grp['id'] : '' );
			if ( $existing_id !== '' && $existing_id === $id ) {
				$groups[ $k ] = $new_group;
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$groups[] = $new_group;
		}

		$settings['o100_addon_groups'] = array_values( $groups );
		update_option( 'o100_product_options', $settings );
		return rest_ensure_response( array( 'success' => true, 'group' => $new_group ) );
	}

	public function delete_modifier( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );
		if ( empty( $id ) ) {
			return new WP_Error( 'invalid_data', 'Missing ID', array( 'status' => 400 ) );
		}

		$settings = get_option( 'o100_product_options', array() );
		$groups = isset( $settings['o100_addon_groups'] ) && is_array( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();
		
		$filtered = array();
		foreach ( $groups as $grp ) {
			if ( isset( $grp['_id'] ) && $grp['_id'] === $id ) {
				continue;
			}
			$filtered[] = $grp;
		}

		$settings['o100_addon_groups'] = $filtered;
		update_option( 'o100_product_options', $settings );
		return rest_ensure_response( array( 'success' => true ) );
	}

	// ==========================================
	// DISPLAYS (MENU BUILDER) ENDPOINTS
	// ==========================================

	public function get_displays( WP_REST_Request $request ) {
		$configs = get_option( 'o100_menu_shortcodes', array() );
		$formatted = array();
		if ( is_array( $configs ) ) {
			global $wpdb;
			foreach ( $configs as $id => $config ) {
				if ( ! is_array( $config ) ) continue;
				if ( empty( $config['id'] ) ) {
					$config['id'] = (string) $id;
				}
				if ( empty( $config['name'] ) ) {
					// Fallback: older configs did not have a 'name' field, so we use the ID
					$config['name'] = (string) $id;
				}

				// Detect shortcode usage in pages/posts/templates (including Elementor data)
				$search_id = esc_sql( $config['id'] );
				
				// Using a very tolerant LIKE pattern to match both [o100_menu ... id="xxx" ...] 
				// and its JSON-escaped or entity-encoded variants like id=\"xxx\" or id=\&quot;xxx\&quot;
				$like_general = '%' . $wpdb->esc_like( '[o100_menu' ) . '%' . $wpdb->esc_like( $search_id ) . '%' . $wpdb->esc_like( ']' ) . '%';
				$like_legacy  = '%' . $wpdb->esc_like( '[order100_food_menu' ) . '%' . $wpdb->esc_like( $search_id ) . '%' . $wpdb->esc_like( ']' ) . '%';
				
				$usage_query = $wpdb->prepare(
					"SELECT DISTINCT p.post_title, p.ID 
					 FROM {$wpdb->posts} p 
					 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_elementor_data'
					 WHERE p.post_type IN ('post', 'page', 'elementor_library', 'product') 
					   AND p.post_status = 'publish' 
					   AND (
					     p.post_content LIKE %s OR 
					     p.post_content LIKE %s OR 
					     pm.meta_value LIKE %s OR 
					     pm.meta_value LIKE %s
					   ) LIMIT 5",
					$like_general, $like_legacy, $like_general, $like_legacy
				);
				$usage_results = $wpdb->get_results( $usage_query );
				
				$config['_usage'] = array();
				if ( $usage_results ) {
					foreach( $usage_results as $u ) {
						$config['_usage'][] = array(
							'title' => $u->post_title,
							'url'   => get_permalink( $u->ID )
						);
					}
				}

				$formatted[] = $config;
			}
		}
		return rest_ensure_response( $formatted );
	}

	public function save_display( WP_REST_Request $request ) {
		$configs = get_option( 'o100_menu_shortcodes', array() );
		$data = $request->get_json_params();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new WP_Error( 'invalid_data', 'Missing data or ID', array( 'status' => 400 ) );
		}

		$id = sanitize_text_field( $data['id'] );
		$configs[ $id ] = $data; // Menu builder saves by associative key originally
		
		update_option( 'o100_menu_shortcodes', $configs );
		return rest_ensure_response( array( 'success' => true, 'display' => $data ) );
	}

	public function delete_display( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );
		if ( empty( $id ) ) {
			return new WP_Error( 'invalid_data', 'Missing ID', array( 'status' => 400 ) );
		}

		$configs = get_option( 'o100_menu_shortcodes', array() );
		if ( isset( $configs[ $id ] ) ) {
			unset( $configs[ $id ] );
			update_option( 'o100_menu_shortcodes', $configs );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
