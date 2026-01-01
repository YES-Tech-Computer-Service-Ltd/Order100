<?php
/**
 * Product Add-ons Module Frontend
 * Handles frontend rendering of extra options, cart validation, price calculation,
 * and order meta saving.
 *
 * @package Order100
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class O100_Product_Addons_Frontend {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// 1. Render on product page
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_options_html' ) );
		
		// 2. Validate before add to cart
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 4 );
		
		// 3. Add to cart item data
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		
		// 4. Modify price in cart
		add_filter( 'woocommerce_add_cart_item', array( $this, 'adjust_cart_item_price' ), 30, 1 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'adjust_cart_item_price_session' ), 20, 2 );
		
		// 5. Display in cart / checkout
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		
		// 6. Save to order
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
	}

	/**
	 * Get merged options (Global + Product) for a specific product ID.
	 */
	public function get_product_options( $product_id ) {
		// If product is globally disabled from food fields, it has no Extra Options
		if ( class_exists( 'O100_Menu_Rules' ) && O100_Menu_Rules::is_food_disabled( $product_id ) ) {
			return array();
		}

		$options = array();

		// Check if product excludes global options
		$exclude = get_post_meta( $product_id, 'o100_addon_exclude', true );

		if ( 'on' !== $exclude ) {
			// Find global options from settings
			$settings = get_option( 'o100_product_options' );
			$global_groups = isset( $settings['o100_addon_groups'] ) ? $settings['o100_addon_groups'] : array();

			$product_terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

			foreach ( $global_groups as $group ) {
				$apply_to = isset( $group['_apply_to'] ) ? $group['_apply_to'] : 'all';
				$applies  = false;

				if ( 'all' === $apply_to ) {
					$applies = true;
				} elseif ( 'categories' === $apply_to ) {
					$assigned_cats = isset( $group['_category_ids'] ) ? $group['_category_ids'] : array();
					if ( ! empty( array_intersect( $product_terms, $assigned_cats ) ) ) {
						$applies = true;
					}
				} elseif ( 'products' === $apply_to ) {
					$assigned_prods = isset( $group['_product_ids'] ) ? array_filter( array_map( 'trim', explode( ',', $group['_product_ids'] ) ) ) : array();
					if ( in_array( $product_id, $assigned_prods ) ) {
						$applies = true;
					}
				}

				if ( $applies ) {
					$options[] = $group;
				}
			}

			// Filter by product-level "Include Specific Modifiers" whitelist
			$has_include_saved = metadata_exists( 'post', $product_id, 'o100_addon_include' );
			if ( $has_include_saved ) {
				$include_ids = get_post_meta( $product_id, 'o100_addon_include', true );
				if ( ! is_array( $include_ids ) ) {
					$include_ids = array();
				}
				$options = array_filter( $options, function( $group ) use ( $include_ids ) {
					$gid = isset( $group['_id'] ) ? $group['_id'] : '';
					return in_array( $gid, $include_ids, true );
				} );
				$options = array_values( $options );
			}
		}

		// Get product-specific options
		$product_options = get_post_meta( $product_id, 'o100_addon_groups', true );
		if ( ! is_array( $product_options ) ) {
			$product_options = array();
		}

		// Merge based on position
		$pos = get_post_meta( $product_id, 'o100_addon_pos', true );
		if ( 'before' === $pos ) {
			$final_options = array_merge( $options, $product_options );
		} else {
			$final_options = array_merge( $product_options, $options );
		}

		// De-duplicate if needed (using _id)
		$unique_options = array();
		$seen = array();
		foreach ( $final_options as $opt ) {
			if ( ! isset( $opt['_id'] ) ) continue;
			if ( ! in_array( $opt['_id'], $seen ) ) {
				$unique_options[] = $opt;
				$seen[] = $opt['_id'];
			}
		}

		return $unique_options;
	}

	/**
	 * 1. Render the HTML on the product page
	 */
	public function render_options_html() {
		global $product;
		if ( ! $product ) return;

		$options = $this->get_product_options( $product->get_id() );
		if ( empty( $options ) ) return;

		echo '<div class="o100-product-addons">';
		
		foreach ( $options as $index => $group ) {
			$id   = isset( $group['_id'] ) ? $group['_id'] : 'o100_op_' . $index;
			$type = isset( $group['_type'] ) ? $group['_type'] : ''; // default checkbox
			$name = isset( $group['_name'] ) ? $group['_name'] : '';
			$req  = isset( $group['_required'] ) && $group['_required'] === 'yes';

			$display = isset( $group['_display_type'] ) ? $group['_display_type'] : '';

			$classes = array( 'o100-addon-group', 'o100-addon-type-' . ( $type ? $type : 'checkbox' ) );
			if ( $req ) $classes[] = 'o100-required';
			if ( $display === 'accor' ) {
				$classes[] = 'o100-pm-addon-group';
				$classes[] = 'o100-pm-accordion-mode';
			}

			$min_req = isset( $group['_min_op'] ) && $group['_min_op'] !== '' ? $group['_min_op'] : '';
			$max_req = isset( $group['_max_op'] ) && $group['_max_op'] !== '' ? $group['_max_op'] : '';
			$min_opqty = isset( $group['_min_opqty'] ) && $group['_min_opqty'] !== '' ? $group['_min_opqty'] : '';
			$max_opqty = isset( $group['_max_opqty'] ) && $group['_max_opqty'] !== '' ? $group['_max_opqty'] : '';

			$enb_logic = isset( $group['_enb_logic'] ) ? $group['_enb_logic'] : '';
			if ( $enb_logic === 'on' ) {
				$classes[] = 'o100-logic-on';
			}

			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $id ) . '" data-id="' . esc_attr( $id ) . '" data-minsl="' . esc_attr( $min_req ) . '" data-maxsl="' . esc_attr( $max_req ) . '" data-minopqty="' . esc_attr( $min_opqty ) . '" data-maxopqty="' . esc_attr( $max_opqty ) . '">';
			
			if ( $display === 'accor' ) {
				echo '<div class="o100-pm-accordion-header">';
			}
			
			if ( $name ) {
				$header_classes = 'o100-addon-header-text';
				if ( 'textarea' === $type ) {
					$header_classes .= ' o100-textarea-trigger';
				}
				echo '<div class="' . esc_attr( $header_classes ) . '">';
				
				if ( 'textarea' === $type ) {
					echo '<div class="o100-textarea-trigger-text" style="flex: 1;">';
				}
				
				$price_val_group = 0;
				if ( in_array( $type, array( 'text', 'textarea', 'quantity' ) ) ) {
					$price_val_group = isset( $group['_price'] ) && is_numeric( $group['_price'] ) ? floatval( $group['_price'] ) : 0;
				}
				
				$title_html = esc_html( $name );
				if ( $price_val_group > 0 ) {
					$title_html .= ' <span class="o100-addon-price" style="display:inline-block; margin-left:8px;">+' . $this->format_addon_price_html( $price_val_group ) . '</span>';
				}
				
				echo '<h4 class="o100-addon-title">' . $title_html . '</h4>';

				$max_op = isset( $group['_max_op'] ) && is_numeric( $group['_max_op'] ) ? intval( $group['_max_op'] ) : 0;
				if ( in_array( $type, array( 'radio', 'select' ) ) ) {
					$max_op = 1;
				}
				
				$limits = array();
				
				// Selection limits (checkboxes)
				if ( $max_op > 0 ) {
					if ( $min_req == $max_op ) {
						$limits[] = 'Choose exactly ' . $max_op;
					} else {
						$limits[] = 'Choose up to ' . $max_op;
					}
				} elseif ( $min_req > 0 ) {
					$limits[] = 'Choose at least ' . $min_req;
				}

				// Total Quantity limits
				if ( $max_opqty > 0 ) {
					if ( $min_opqty == $max_opqty ) {
						$limits[] = 'Total qty exactly ' . $max_opqty;
					} else {
						$limits[] = 'Total qty up to ' . $max_opqty;
					}
				} elseif ( $min_opqty > 0 ) {
					$limits[] = 'Total qty at least ' . $min_opqty;
				}

				$max_text = '';
				if ( ! empty( $limits ) ) {
					$max_text = ' <span class="o100-addon-limit-text">&bull; ' . implode( ' &bull; ', $limits ) . '</span>';
				}

				if ( $req ) {
					echo '<div class="o100-addon-subtitle o100-addon-required-subtitle"><span class="o100-req-icon">&#9888;</span> Required' . $max_text . '</div>';
				} else {
					echo '<div class="o100-addon-subtitle o100-addon-optional-subtitle">(Optional)' . $max_text . '</div>';
				}
				
				if ( 'textarea' === $type ) {
					echo '</div>'; // close inner flex wrapper
					echo '<span class="dashicons dashicons-arrow-right-alt2" style="color: #000; font-size: 20px;"></span>';
				}
				
				echo '</div>'; // close .o100-addon-header-text
			}

			if ( $display === 'accor' ) {
				echo '<span class="dashicons dashicons-arrow-down-alt2 o100-pm-accordion-icon"></span>';
				echo '</div>'; // .o100-pm-accordion-header
				echo '<div class="o100-pm-addon-body" style="display:none;">';
			}

			$enb_img = ( in_array( $type, array( '', 'checkbox', 'radio' ) ) && isset( $group['_enb_img'] ) ) ? $group['_enb_img'] === 'yes' : false;
			$enb_qty = ( in_array( $type, array( '', 'checkbox', 'radio' ) ) && isset( $group['_enb_qty'] ) ) ? $group['_enb_qty'] === 'yes' : false;

			$container_classes = array( 'o100-addon-choices' );
			if ( $enb_img ) $container_classes[] = 'o100-img-option';
			if ( $enb_qty ) $container_classes[] = 'o100-qty-option';

			echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '">';
			
			// Render Choices
			if ( in_array( $type, array( '', 'radio', 'select' ) ) ) {
				$choices = isset( $group['_options'] ) && is_array( $group['_options'] ) ? $group['_options'] : array();
				
				if ( 'select' === $type ) {
					echo '<select name="o100_addon_' . esc_attr( $id ) . '[]" class="o100-addon-select o100-options">';
					echo '<option value="">' . __( 'Choose an option', 'order100' ) . '</option>';
					foreach ( $choices as $key => $choice ) {
						if ( isset( $choice['dis'] ) && 'yes' === $choice['dis'] ) continue;
						$price_val = isset( $choice['price'] ) && is_numeric( $choice['price'] ) ? floatval( $choice['price'] ) : 0;
						$price_text = $price_val > 0 ? ' (+' . $this->format_addon_price_html( $price_val ) . ')' : '';
						$op_type = isset( $choice['type'] ) ? $choice['type'] : '';
						echo '<option value="' . esc_attr( $key ) . '" data-price="' . esc_attr( $price_val ) . '" data-type="' . esc_attr( $op_type ) . '">' . esc_html( $choice['name'] ) . wp_strip_all_tags( $price_text ) . '</option>';
					}
					echo '</select>';
				} else {
					$input_type = 'radio' === $type ? 'radio' : 'checkbox';
					foreach ( $choices as $key => $choice ) {
						if ( isset( $choice['dis'] ) && 'yes' === $choice['dis'] ) continue;
						$input_id = esc_attr( $id . '_' . $key );
						$price_val = isset( $choice['price'] ) && is_numeric( $choice['price'] ) ? floatval( $choice['price'] ) : 0;
						$price_text = $price_val > 0 ? ' <span class="o100-addon-price">+' . $this->format_addon_price_html( $price_val ) . '</span>' : '';
						$op_type = isset( $choice['type'] ) ? $choice['type'] : '';
						
						echo '<div class="o100-addon-choice-wrap">';
						echo '<label class="o100-addon-choice-label" for="' . $input_id . '">';
						echo '<input type="' . $input_type . '" class="o100-options" id="' . $input_id . '" name="o100_addon_' . esc_attr( $id ) . '[]" value="' . esc_attr( $key ) . '" data-price="' . esc_attr( $price_val ) . '" data-type="' . esc_attr( $op_type ) . '">';
						
						$img_op = isset( $choice['image'] ) ? $choice['image'] : '';
						if ( $enb_img && $img_op ) {
							echo '<span class="o100-op-img"><img src="' . esc_url( $img_op ) . '" alt=""/></span>';
						}
						
						echo '<div class="o100-addon-op-info">';
						echo '<span class="o100-addon-name">' . esc_html( $choice['name'] ) . '</span>';
						if ( $price_text ) {
							echo $price_text;
						}
						echo '</div>';
						echo '</label>';
						
						if ( $enb_qty ) {
							$minop = isset( $choice['min'] ) && $choice['min'] !== '' && $choice['min'] >= 0 ? $choice['min'] : 1;
							$maxop = isset( $choice['max'] ) && $choice['max'] !== '' && $choice['max'] >= 1 ? $choice['max'] : $max_opqty;
							$def_oqty = $minop;
							echo '<span class="o100-qty-op" style="display:none;"><button type="button" class="o100-addon-qty-minus">-</button><input class="o100-qty-op-input" name="o100_addon_' . esc_attr( $id ) . '_' . esc_attr( $key ) . '_qty" id="qty_' . esc_attr( $input_id ) . '" type="number" min="' . esc_attr( $minop ) . '" max="' . esc_attr( $maxop ) . '" value="' . esc_attr( $def_oqty ) . '"><button type="button" class="o100-addon-qty-plus">+</button></span>';
						}
						echo '</div>';
					}
				}
			} elseif ( in_array( $type, array( 'text', 'textarea', 'quantity' ) ) ) {
				$price_val = isset( $group['_price'] ) && is_numeric( $group['_price'] ) ? floatval( $group['_price'] ) : 0;
				$price_type = isset( $group['_price_type'] ) ? $group['_price_type'] : '';
				
				if ( 'text' === $type ) {
					echo '<input type="text" name="o100_addon_' . esc_attr( $id ) . '" class="o100-addon-text o100-options" data-price="' . esc_attr( $price_val ) . '" data-pricetype="' . esc_attr( $price_type ) . '">';
				} elseif ( 'textarea' === $type ) {
					echo '<div class="o100-textarea-slide-panel" style="transform: translateX(100%);">';
					echo '<div class="o100-slide-panel-header">';
					echo '<div class="o100-slide-panel-back"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></div>';
					echo '<h3 class="o100-slide-panel-title">' . esc_html( $name ? $name : 'User Preferences' ) . '</h3>';
					echo '</div>';
					
					echo '<div class="o100-slide-panel-body">';
					echo '<div class="o100-slide-panel-subtitle">Add Special Instructions</div>';
					echo '<textarea name="o100_addon_' . esc_attr( $id ) . '" class="o100-addon-textarea o100-options" data-price="' . esc_attr( $price_val ) . '" data-pricetype="' . esc_attr( $price_type ) . '"></textarea>';
					echo '</div>';
					
					echo '<div class="o100-slide-panel-footer">';
					echo '<div class="o100-slide-panel-save">Save</div>';
					echo '</div>';
					echo '</div>';
				} elseif ( 'quantity' === $type ) {
					echo '<div class="o100-qty-wrap"><button type="button" class="o100-addon-qty-minus">-</button><input type="number" name="o100_addon_' . esc_attr( $id ) . '" class="o100-addon-quantity o100-options" min="0" placeholder="0" data-price="' . esc_attr( $price_val ) . '" data-pricetype="' . esc_attr( $price_type ) . '"><button type="button" class="o100-addon-qty-plus">+</button></div>';
				}
			}

			echo '</div>'; // .o100-addon-choices
			
			if ( $display === 'accor' ) {
				echo '</div>'; // .o100-pm-addon-body
			}
			
			echo '</div>'; // .o100-addon-group
		}

		echo '</div>'; // .o100-product-addons
	}

	/**
	 * 2. Validate add to cart
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = false ) {
		$options = $this->get_product_options( $product_id );
		if ( empty( $options ) ) return $passed;

		foreach ( $options as $index => $group ) {
			$id  = isset( $group['_id'] ) && !empty( $group['_id'] ) ? $group['_id'] : 'o100_op_' . $index;
			$req = isset( $group['_required'] ) && $group['_required'] === 'yes';
			$val = isset( $_POST['o100_addon_' . $id] ) ? $_POST['o100_addon_' . $id] : null;
			$type = isset( $group['_type'] ) ? $group['_type'] : '';

			$name = isset( $group['_name'] ) ? $group['_name'] : 'Option';

			if ( $req ) {
				if ( empty( $val ) && $val !== '0' ) {
					wc_add_notice( sprintf( __( '"%s" is a required field.', 'order100' ), $name ), 'error' );
					$passed = false;
				} elseif ( is_array( $val ) && count( array_filter( $val, 'strlen' ) ) === 0 ) {
					wc_add_notice( sprintf( __( '"%s" is a required field.', 'order100' ), $name ), 'error' );
					$passed = false;
				}
			}

			if ( in_array( $type, array( '', 'checkbox' ) ) && ! empty( $val ) && is_array( $val ) ) {
				$c_item = count( $val );
				$min_req = isset( $group['_min_op'] ) && $group['_min_op'] !== '' ? intval( $group['_min_op'] ) : 0;
				$max_req = isset( $group['_max_op'] ) && $group['_max_op'] !== '' ? intval( $group['_max_op'] ) : 0;

				if ( $min_req > 0 && $min_req > $c_item ) {
					wc_add_notice( sprintf( __( 'Please choose at least %s options for "%s".', 'order100' ), $min_req, $name ), 'error' );
					$passed = false;
				}
				if ( $max_req > 0 && $max_req < $c_item ) {
					wc_add_notice( sprintf( __( 'You can only select a maximum of %s options for "%s".', 'order100' ), $max_req, $name ), 'error' );
					$passed = false;
				}

				$enb_qty = isset( $group['_enb_qty'] ) && $group['_enb_qty'] === 'yes';
				$min_opqty = isset( $group['_min_opqty'] ) && $group['_min_opqty'] !== '' ? intval( $group['_min_opqty'] ) : 0;
				$max_opqty = isset( $group['_max_opqty'] ) && $group['_max_opqty'] !== '' ? intval( $group['_max_opqty'] ) : 0;

				if ( ( $max_opqty > 0 || $min_opqty > 0 ) && $enb_qty ) {
					$qty_tt = 0;
					foreach ( $val as $v ) {
						$qty_key = 'o100_addon_' . $id . '_' . $v . '_qty';
						$qty_op = isset( $_POST[$qty_key] ) && is_numeric( $_POST[$qty_key] ) ? intval( $_POST[$qty_key] ) : 1;
						$qty_tt += $qty_op;
					}
					if ( $min_opqty > 0 && $qty_tt < $min_opqty ) {
						wc_add_notice( sprintf( __( 'Please choose at least %s total quantity for "%s".', 'order100' ), $min_opqty, $name ), 'error' );
						$passed = false;
					}
					if ( $max_opqty > 0 && $qty_tt > $max_opqty ) {
						wc_add_notice( sprintf( __( 'You can only select a maximum of %s total quantity for "%s".', 'order100' ), $max_opqty, $name ), 'error' );
						$passed = false;
					}
				}
			}
		}

		return $passed;
	}

	/**
	 * 3. Add item data to cart
	 */
	public function add_cart_item_data( $cart_item_data, $product_id ) {
		$options = $this->get_product_options( $product_id );
		if ( empty( $options ) ) return $cart_item_data;

		$addon_data = array();

		foreach ( $options as $index => $group ) {
			$id  = isset( $group['_id'] ) && !empty( $group['_id'] ) ? $group['_id'] : 'o100_op_' . $index;
			$val = isset( $_POST['o100_addon_' . $id] ) ? $_POST['o100_addon_' . $id] : null;
			
			if ( empty( $val ) ) continue;

			$type = isset( $group['_type'] ) ? $group['_type'] : '';

			if ( in_array( $type, array( '', 'radio', 'select', 'checkbox' ) ) ) {
				$choices = isset( $group['_options'] ) && is_array( $group['_options'] ) ? $group['_options'] : array();
				$selected = is_array( $val ) ? $val : array( $val );
				$enb_qty = isset( $group['_enb_qty'] ) && $group['_enb_qty'] === 'yes';

				foreach ( $selected as $key ) {
					if ( isset( $choices[$key] ) ) {
						$price = isset( $choices[$key]['price'] ) && is_numeric( $choices[$key]['price'] ) ? floatval( $choices[$key]['price'] ) : 0;
						$val_op = $choices[$key]['name'];
						$qty_op = 1;

						if ( $enb_qty ) {
							$qty_key = 'o100_addon_' . $id . '_' . $key . '_qty';
							if ( isset( $_POST[$qty_key] ) && is_numeric( $_POST[$qty_key] ) ) {
								$qty_op = intval( $_POST[$qty_key] );
								if ( $qty_op > 1 ) {
									$price = $price * $qty_op;
									$val_op = $val_op . ' x ' . $qty_op;
								}
							}
						}

						$type_price = isset( $choices[$key]['type'] ) && $choices[$key]['type'] !== '' ? $choices[$key]['type'] : '';

						$addon_data[] = array(
							'name'  => $group['_name'],
							'value' => $val_op,
							'price' => $price,
							'type'  => $type_price,
							'_type' => $type,
							'qty'   => $qty_op
						);
					}
				}
			} elseif ( in_array( $type, array( 'text', 'textarea', 'quantity' ) ) ) {
				if ( is_string( $val ) && trim( $val ) !== '' ) {
					$price = isset( $group['_price'] ) && is_numeric( $group['_price'] ) ? floatval( $group['_price'] ) : 0;
					$price_type = isset( $group['_price_type'] ) ? $group['_price_type'] : '';
					
					if ( 'quantity' === $type ) {
						$qty = floatval( $val );
						if ( $qty > 0 ) {
							$addon_data[] = array(
								'name'  => $group['_name'],
								'value' => $val,
								'price' => $price * $qty,
								'type'  => $price_type,
								'_type' => $type,
								'qty'   => $qty
							);
						}
					} else {
						$addon_data[] = array(
							'name'  => $group['_name'],
							'value' => wp_strip_all_tags( $val ),
							'price' => $price,
							'type'  => $price_type,
							'_type' => $type,
							'qty'   => 1
						);
					}
				}
			}
		}

		if ( ! empty( $addon_data ) ) {
			$cart_item_data['o100_addons'] = $addon_data;
		}

		return $cart_item_data;
	}

	/**
	 * 4. Adjust Cart Price
	 */
	public function adjust_cart_item_price( $cart_item ) {
		if ( isset( $cart_item['o100_addons'] ) && is_array( $cart_item['o100_addons'] ) ) {
			$extra_price = 0;
			$qty = $cart_item['quantity'];

			foreach ( $cart_item['o100_addons'] as $addon ) {
				if ( isset( $addon['price'] ) && $addon['price'] > 0 ) {
					if ( isset( $addon['type'] ) && $addon['type'] === 'fixed' ) {
						// fixed price across total quantity = price / qty
						$extra_price += $addon['price'] / $qty;
					} else {
						// qty based = price applies per item
						$extra_price += $addon['price'];
					}
				}
			}

			if ( $extra_price > 0 ) {
				$base_price = (float) $cart_item['data']->get_price( 'edit' );
				$cart_item['data']->set_price( $base_price + $extra_price );
			}
		}
		return $cart_item;
	}

	public function adjust_cart_item_price_session( $cart_item, $values ) {
		if ( isset( $values['o100_addons'] ) ) {
			$cart_item['o100_addons'] = $values['o100_addons'];
			$cart_item = $this->adjust_cart_item_price( $cart_item );
		}
		return $cart_item;
	}

	/**
	 * 5. Display in cart
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['o100_addons'] ) && is_array( $cart_item['o100_addons'] ) ) {
			foreach ( $cart_item['o100_addons'] as $addon ) {
				$price_suffix = '';
				if ( isset( $addon['price'] ) && $addon['price'] > 0 ) {
					$price_to_show = $addon['price'];
					if ( isset( $addon['_type'] ) && $addon['_type'] === 'quantity' && isset($addon['qty']) && $addon['qty'] > 0 ) {
						$price_to_show = $addon['price'] / $addon['qty'];
					}
					$price_suffix = ' (+' . $this->format_addon_price_html( $price_to_show ) . ')';
				}

				$item_data[] = array(
					'name'  => $addon['name'],
					'value' => $addon['value'] . $price_suffix,
				);
			}
		}
		return $item_data;
	}

	/**
	 * 6. Save to Order
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['o100_addons'] ) && is_array( $values['o100_addons'] ) ) {
			foreach ( $values['o100_addons'] as $addon ) {
				$price_suffix = '';
				if ( isset( $addon['price'] ) && $addon['price'] > 0 ) {
					$price_to_show = $addon['price'];
					if ( isset( $addon['_type'] ) && $addon['_type'] === 'quantity' && isset($addon['qty']) && $addon['qty'] > 0 ) {
						$price_to_show = $addon['price'] / $addon['qty'];
					}
					$price_suffix = ' (+' . $this->format_addon_price_html( $price_to_show ) . ')';
				}
				$item->add_meta_data( $addon['name'], $addon['value'] . $price_suffix );
			}
			// Save raw data for future editing/reordering
			$item->add_meta_data( '_o100_addons', $values['o100_addons'] );
		}
	}

	public function enqueue_assets() {
		if ( ! is_admin() ) {
			$css_path = O100_ADDONS_PATH . 'assets/css/frontend.css';
			$js_path = O100_ADDONS_PATH . 'assets/js/frontend.js';
			
			$css_ver = file_exists( $css_path ) ? filemtime( $css_path ) : O100_VERSION;
			$js_ver = file_exists( $js_path ) ? filemtime( $js_path ) : O100_VERSION;

			wp_enqueue_style(
				'o100-product-addons-front',
				O100_ADDONS_URL . 'assets/css/frontend.css',
				array(),
				$css_ver
			);
			wp_enqueue_script(
				'o100-product-addons-front',
				O100_ADDONS_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				$js_ver,
				true
			);
		}
	}

	public function format_addon_price_html( $price_val ) {
		$price_html = wc_price( $price_val );
		$currency = get_woocommerce_currency();
		$doordash_symbols = array(
			'CAD' => 'CA$',
			'USD' => 'US$',
			'AUD' => 'AU$',
			'NZD' => 'NZ$',
		);
		
		if ( isset( $doordash_symbols[ $currency ] ) ) {
			$symbol = get_woocommerce_currency_symbol( $currency );
			$search = '<span class="woocommerce-Price-currencySymbol">' . $symbol . '</span>';
			$replace = '<span class="woocommerce-Price-currencySymbol">' . $doordash_symbols[ $currency ] . '</span>';
			$price_html = str_replace( $search, $replace, $price_html );
			
			if ( strpos( $price_html, $replace ) === false ) {
				$pos = strpos( $price_html, $symbol );
				if ( $pos !== false ) {
					$price_html = substr_replace( $price_html, $doordash_symbols[ $currency ], $pos, strlen( $symbol ) );
				}
			}
		}
		return $price_html;
	}

}



