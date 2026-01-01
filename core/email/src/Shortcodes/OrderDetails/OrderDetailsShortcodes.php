<?php

namespace Order100\Notification\Engine\Shortcodes\OrderDetails;

use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\TemplateHelpers;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Abstracts\BaseShortcode;

/**
 * @method: static OrderDetailsShortcodes get_instance()
 */
class OrderDetailsShortcodes extends BaseShortcode {
    use SingletonTrait;

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'o100_order_id',
            'description' => __( 'Order ID', 'order100' ),
            'attributes'  => [
                'is_plain'   => false,
                'forced_url' => '',
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_id' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_number',
            'description' => __( 'Order Number', 'order100' ),
            'attributes'  => [
                'is_plain' => false,
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_number' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_date',
            'description' => __( 'Order Date', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_date' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_link',
            'description' => __( 'Order URL', 'order100' ),
            'attributes'  => [
                'text_link' => __( 'Order', 'order100' ),
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_url',
            'description' => __( 'Order URL (String)', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_view_order_link',
            'description' => __( 'View Order Link', 'order100' ),
            'attributes'  => [
                'text_link' => __( 'Your Order', 'order100' ),
            ],
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_view_order_link' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_view_order_url',
            'description' => __( 'View Order URL (String)', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_view_order_url' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_status',
            'description' => __( 'Order Status', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_status' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_fee',
            'description' => __( 'Order Fee', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_fee' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_refund',
            'description' => __( 'Order Refund', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_refund' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_subtotal',
            'description' => __( 'Order Subtotal', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_subtotal' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_total',
            'description' => __( 'Order Total', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_total' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_total_value',
            'description' => __( 'Order Total Value', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_total_value' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_coupon_codes',
            'description' => __( 'Order Coupon Codes', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_coupon_codes' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_product_line_item_count',
            'description' => __( 'Number of line items in the order', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_product_line_item_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_product_line_item_count_double',
            'description' => __( 'Number of line items in the order (double)', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_product_line_item_count_double' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_product_item_count',
            'description' => __( 'Total quantity of all items in the order', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_product_item_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_product_count',
            'description' => __( 'Number of base products in the order', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_product_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_product_variation_count',
            'description' => __( 'Number of product variations in the order', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_order_product_variation_count' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_customer_roles',
            'description' => __( 'Customer Roles', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_customer_roles' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_customer_note',
            'description' => __( 'Customer Last Note', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_customer_note' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_customer_notes',
            'description' => __( 'All Customer Note', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_customer_notes' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_customer_provided_note',
            'description' => __( 'Customer Provided Note', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'o100_customer_provided_note' ],
        ];
        $shortcodes[] = [
            'name'        => 'woocommerce_email_order_meta',
            'description' => __( 'Order Meta Content', 'order100' ),
            'group'       => 'order_details',
            'callback'    => [ $this, 'woocommerce_email_order_meta' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_details',
            'description' => __( 'Order Details', 'order100' ),
            'group'       => 'order_details',
            'attributes'  => [
                'product_title'          => 'Product',
                'quantity_title'         => 'Quantity',
                'price_title'            => 'Price',
                'total_title'            => 'Total',
                'cart_subtotal_title'    => 'Subtotal',
                'shipping_title'         => 'Shipping',
                'payment_method_title'   => 'Payment method',
                'order_total_title'      => 'Total',
                'show_product_item_cost' => 'false',
            ],
            'callback'    => [ $this, 'o100ne_order_details' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_order_details_download_product',
            'description' => __( 'Order Details Download Product', 'order100' ),
            'group'       => '',
            'attributes'  => [
                'product_title'          => 'Product',
                'quantity_title'         => 'Quantity',
                'price_title'            => 'Price',
                'total_title'            => 'Total',
                'cart_subtotal_title'    => 'Subtotal',
                'shipping_title'         => 'Shipping',
                'payment_method_title'   => 'Payment method',
                'order_total_title'      => 'Total',
                'show_product_item_cost' => 'false',
            ],
            'callback'    => [ $this, 'o100ne_order_details_download_product' ],
        ];

        $shortcodes[] = [
            'name'        => 'o100_products',
            'description' => __( 'Products', 'order100' ),
            'group'       => 'order_details',
            'attributes'  => [
                'product_type'           => 'order_items',
                'linked_products_type'   => 'cross_sells',
                'max_products_displayed' => 5,
                'show_image'             => 'true',
                'show_sku'               => 'true',
                'show_price'             => 'true',
                'remove_link'            => 'false',
                'image_size'             => '80',
            ],
            'callback'    => [ $this, 'o100ne_products' ],
        ];

        return $shortcodes;
    }

    /**
     * Render products shortcode
     */
    public static function o100ne_products( $args, $shortcode_atts = [] ) {
        $render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];
        $order = \Order100\Notification\Engine\Utils\Helpers::get_order_from_shortcode_data( $render_data );
        $product_type = isset( $shortcode_atts['product_type'] ) ? $shortcode_atts['product_type'] : 'order_items';
        $show_image = isset( $shortcode_atts['show_image'] ) ? $shortcode_atts['show_image'] !== 'false' : true;
        $show_sku = isset( $shortcode_atts['show_sku'] ) ? $shortcode_atts['show_sku'] !== 'false' : true;
        $show_price = isset( $shortcode_atts['show_price'] ) ? $shortcode_atts['show_price'] !== 'false' : true;
        $remove_link = isset( $shortcode_atts['remove_link'] ) ? $shortcode_atts['remove_link'] === 'true' : false;
        $image_size = isset( $shortcode_atts['image_size'] ) ? intval( $shortcode_atts['image_size'] ) : 80;

        $items = [];
        $model = \Order100\Notification\Engine\Models\ProductModel::get_instance();

        if ( 'order_items' === $product_type ) {
            if ( $order && ! empty( $render_data['is_sample'] ) === false ) {
                foreach ( $order->get_items() as $item ) {
                    $product = $item->get_product();
                    if ( ! $product ) continue;
                    $items[] = array_merge( 
                        (array) $model->get_product_response( $product ),
                        [ 'qty' => $item->get_quantity() ]
                    );
                }
            } else {
                // sample item
                $items[] = [
                    'name' => 'Premium Item',
                    'qty' => 1,
                    'price' => wc_price( 25.00 ),
                    'sku' => 'PREM-01',
                    'thumbnail_src' => 'https://via.placeholder.com/80x80?text=Item',
                    'permalink' => '#'
                ];
            }
        } elseif ( in_array( $product_type, [ 'cross_sells', 'up_sells' ] ) ) {
            $params = [
                'order_id' => $order ? $order->get_id() : 'sample_order',
                'linked_products_type' => $product_type,
                'max_products_displayed' => isset( $shortcode_atts['max_products_displayed'] ) ? intval( $shortcode_atts['max_products_displayed'] ) : 5
            ];
            $products = $model->get_cross_up_sells_products( $params );
            foreach( $products as $p ) {
                $p['qty'] = 1;
                $items[] = $p;
            }
        } else {
            $mapped_product_type = $product_type === 'specific' ? 'product_selections' : $product_type;
            $params = [
                'product_type' => $mapped_product_type,
                'number_of_products' => isset( $shortcode_atts['max_products_displayed'] ) ? intval( $shortcode_atts['max_products_displayed'] ) : 5,
                'sorted_by' => 'none'
            ];
            if ( $product_type === 'specific' && ! empty( $shortcode_atts['specific_ids'] ) ) {
                $params['product_ids'] = array_map('intval', explode(',', $shortcode_atts['specific_ids']));
            }
            if ( ! empty( $shortcode_atts['exclude_ids'] ) ) {
                $params['exclude_ids'] = array_map('intval', explode(',', $shortcode_atts['exclude_ids']));
            }
            $products = $model->get_featured_products( $params );
            foreach( $products as $p ) {
                $p['qty'] = 1;
                $items[] = $p;
            }
        }

        if ( empty( $items ) ) {
            return '';
        }

        $columns = isset( $shortcode_atts['columns'] ) ? max(1, intval( $shortcode_atts['columns'] )) : 2;
        $max_rows = isset( $shortcode_atts['max_rows'] ) ? max(1, intval( $shortcode_atts['max_rows'] )) : 2;
        $max_items = $columns * $max_rows;

        $items = array_slice( $items, 0, $max_items );

        $title_color = isset( $shortcode_atts['title_color'] ) ? $shortcode_atts['title_color'] : '#3c434a';
        $title_size = isset( $shortcode_atts['title_size'] ) ? $shortcode_atts['title_size'] : '15';
        $price_color = isset( $shortcode_atts['price_color'] ) ? $shortcode_atts['price_color'] : '#3c434a';
        $price_size = isset( $shortcode_atts['price_size'] ) ? $shortcode_atts['price_size'] : '15';
        $vertical_distance = isset( $shortcode_atts['distance'] ) ? $shortcode_atts['distance'] : '10';
        $show_cart_btn = isset( $shortcode_atts['show_cart_btn'] ) ? $shortcode_atts['show_cart_btn'] === 'true' : false;
        $add_to_cart_url = isset( $shortcode_atts['add_to_cart_url'] ) ? $shortcode_atts['add_to_cart_url'] === 'true' : false;

        $html = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; table-layout: fixed;">';
        
        $total_items = count($items);
        for ($i = 0; $i < $total_items; $i += $columns) {
            $html .= '<tr>';
            for ($j = 0; $j < $columns; $j++) {
                if ($i + $j < $total_items) {
                    $item = $items[$i + $j];
                    
                    $image_src = ! empty( $item['thumbnail_src'] ) ? $item['thumbnail_src'] : 'https://via.placeholder.com/80x80?text=Img';
                    $productName = $remove_link ? esc_html( $item['name'] ) : '<a href="' . esc_url( $item['permalink'] ) . '" style="color:' . esc_attr($title_color) . '; text-decoration:none;">' . esc_html( $item['name'] ) . '</a>';
                    
                    $imageHtml = $show_image ? '<div style="margin-bottom:10px;"><img src="' . esc_url($image_src) . '" width="100%" style="background:#e2e8f0; border-radius:4px; max-width:100%; height:auto; display:block;" /></div>' : '';
                    
                    $titleHtml = '<div style="font-size:' . esc_attr($title_size) . 'px; color:' . esc_attr($title_color) . '; margin-bottom:5px;">' . $productName . '</div>';
                    
                    $qtyHtml = '<div style="font-size:13px; color:#666; margin-bottom:5px;">Quantity: ' . esc_html( $item['qty'] ) . '</div>';

                    $skuHtml = ( $show_sku && ! empty( $item['sku'] ) ) ? '<div style="font-size:12px; color:#64748b; margin-top:2px;">SKU: ' . esc_html( $item['sku'] ) . '</div>' : '';
                    
                    $item_price = ! empty( $item['sale_price_html'] ) ? $item['sale_price_html'] : ( ! empty( $item['price'] ) ? wc_price( $item['price'] ) : '' );
                    if ( 'order_items' === $product_type && isset( $item['price'] ) && is_string( $item['price'] ) ) {
                        $item_price = $item['price'];
                    }
                    $priceHtml = $show_price ? '<div style="font-size:' . esc_attr($price_size) . 'px; color:' . esc_attr($price_color) . '; margin-bottom:10px;">' . wp_kses_post( $item_price ) . '</div>' : '';
                    
                    $cartBtnHtml = '';
                    if ($show_cart_btn) {
                        $cart_url = $add_to_cart_url ? esc_url( add_query_arg('add-to-cart', $item['id'], $item['permalink']) ) : esc_url( $item['permalink'] );
                        $cartBtnHtml = '<div><a href="' . $cart_url . '" style="display:inline-block; padding:8px 16px; background:#6A4BFF; color:#fff; text-decoration:none; border-radius:4px; font-size:13px; font-weight:bold;">Add to cart</a></div>';
                    }

                    $html .= '<td style="vertical-align:top; width:' . (100 / $columns) . '%; padding: ' . esc_attr($vertical_distance) . 'px 10px; text-align:center;">';
                    $html .= $imageHtml . $titleHtml . $qtyHtml . $skuHtml . $priceHtml . $cartBtnHtml;
                    $html .= '</td>';
                } else {
                    $html .= '<td style="width:' . (100 / $columns) . '%; padding: ' . esc_attr($vertical_distance) . 'px 10px;"></td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Render order details shortcode
     *
     * @param array $args
     * $render_data
     * $element
     * $settings
     * $is_placeholder
     */
    public static function o100ne_order_details( $args, $shortcode_atts = [] ) {

        $render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];

        if ( ! empty( $shortcode_atts ) && is_array( $shortcode_atts ) ) {
            if ( ! isset( $args['element'] ) ) {
                $args['element'] = [ 'data' => [] ];
            }
            if ( ! isset( $args['element']['data'] ) ) {
                $args['element']['data'] = [];
            }
            $args['element']['data'] = array_merge( $args['element']['data'], $shortcode_atts );
        }

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = o100ne_get_content( 'templates/shortcodes/order-details/sample.php', $args );
            return $html;
        }

        if ( empty( $render_data['order'] ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $html = o100ne_get_content( 'templates/shortcodes/order-details/main.php', $args );
        return $html;
    }

    public static function o100ne_order_details_download_product( $args, $shortcode_atts = [] ) {

        $render_data = isset( $args['render_data'] ) ? $args['render_data'] : [];

        if ( ! empty( $shortcode_atts ) && is_array( $shortcode_atts ) ) {
            if ( ! isset( $args['element'] ) ) {
                $args['element'] = [ 'data' => [] ];
            }
            if ( ! isset( $args['element']['data'] ) ) {
                $args['element']['data'] = [];
            }
            $args['element']['data'] = array_merge( $args['element']['data'], $shortcode_atts );
        }

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $html = o100ne_get_content( 'templates/shortcodes/order-details-download-product/sample.php', $args );
            return $html;
        }

        if ( empty( $render_data['order'] ) ) {
            /**
             * Not having order/order_id
             */
            return '';
        }

        $html = o100ne_get_content( 'templates/shortcodes/order-details-download-product/main.php', $args );
        return $html;
    }

    public function o100ne_order_id( $data, $shortcode_atts ) {

        $render_data           = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $is_placeholder        = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;
        $is_customized_preview = isset( $render_data['is_customized_preview'] ) ? $render_data['is_customized_preview'] : false;
        $is_plain              = isset( $shortcode_atts['is_plain'] ) ? Helpers::is_true( $shortcode_atts['is_plain'] ) : false;

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */

            if ( ! $is_plain ) {
                return '<a href="#">1</a>';
            }

            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;

        $template = ! empty( $data['template'] ) ? $data['template'] : null;

        $text_link_color = ! empty( $template ) ? $template->get_text_link_color() : O100NE_COLOR_WC_DEFAULT;

        $element_type = isset( $data['element']['type'] ) ? $data['element']['type'] : '';

        $link_style = TemplateHelpers::get_style(
            [
                'color'           => 'heading' === $element_type ? 'inherit' : $text_link_color,
                'text-decoration' => 'heading' !== $element_type ? 'underline' : 'none',
            ]
        );

        $forced_url = '';

        if ( isset( $shortcode_atts['forced_url'] ) ) {
            $forced_url = filter_var( $shortcode_atts['forced_url'], FILTER_VALIDATE_URL ) === false ? '' : $shortcode_atts['forced_url'];
        }

        $url = ! empty( $forced_url ) ? do_shortcode( $forced_url ) : $order->get_edit_order_url();

        // If not plain text and (placeholder or customized preview or sent to admin), show as link
        if ( ! $is_plain && ( $is_placeholder || $is_customized_preview || $sent_to_admin ) ) {
            return wp_kses_post( "<a style='$link_style' href='{$url}'>{$order->get_id()}</a>" );
        }

        // If is_plain is true, return just the order ID without link
        return $order->get_id();
    }

    public function o100ne_order_number( $data, $shortcode_atts = [] ) {

        $render_data           = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $is_placeholder        = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;
        $is_customized_preview = isset( $render_data['is_customized_preview'] ) ? $render_data['is_customized_preview'] : false;
        $is_plain              = isset( $shortcode_atts['is_plain'] ) ? Helpers::is_true( $shortcode_atts['is_plain'] ) : false;

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */

            if ( ! $is_plain ) {
                return '<a href="#">1</a>';
            }

            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $template = ! empty( $data['template'] ) ? $data['template'] : null;

        $text_link_color = ! empty( $template ) ? $template->get_text_link_color() : O100NE_COLOR_WC_DEFAULT;

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;

        if ( ! $is_plain && ( $is_placeholder || $is_customized_preview || $sent_to_admin ) ) {
            // $sent_to_admin === true
            return wp_kses_post( "<a style='$text_link_color' href='{$order->get_edit_order_url()}'>{$order->get_order_number()}</a>" );
        }

        return $order->get_order_number();
    }

    public function o100ne_order_link( $data, $shortcode_atts = [] ) {
        $order_url = $this->o100ne_order_url( $data );

        if ( empty( $order_url ) ) {
            return '';
        }

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Order', 'order100' ), $is_placeholder );

        return wp_kses_post( "<a href='{$order_url}'>" . $text_link . '</a>' );
    }

    public function o100ne_order_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( get_home_url() );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;

        $order_url = $sent_to_admin ? $order->get_edit_order_url() : $order->get_view_order_url();

        if ( empty( $order_url ) ) {
            return '';
        }

        return esc_url( $order_url );
    }

    public function o100ne_view_order_link( $data, $shortcode_atts = [] ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Your Order', 'order100' ), $is_placeholder );

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '<a href="' . esc_url( get_home_url() ) . '">' . $text_link . '</a>';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $view_order_url = $order->get_view_order_url();

        if ( empty( $view_order_url ) ) {
            return '';
        }

        return wp_kses_post( "<a href='{$view_order_url}'>" . $text_link . '</a>' );
    }

    public function o100ne_view_order_url( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return esc_url( get_home_url() );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $view_order_url = $order->get_view_order_url();

        if ( empty( $view_order_url ) ) {
            return '';
        }

        return esc_url( $view_order_url );
    }

    public function o100ne_order_date( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return date_i18n( wc_date_format() );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_created_date = $order->get_date_created();

        if ( empty( $order_created_date ) ) {
            return '';
        }

        return $order_created_date->date_i18n( wc_date_format() );
    }

    public function o100ne_order_status( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'sample status', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $status = $order->get_status();

        if ( empty( $status ) ) {
            return '';
        }

        return strtolower( wc_get_order_status_name( $status ) );
    }

    public function o100ne_order_fee( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return 0;
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $fee = 0;

        $order_totals = $order->get_order_item_totals();

        if ( ! empty( $order_totals ) ) {
            foreach ( $order_totals as $index => $value ) {
                if ( strpos( $index, 'fee' ) !== false ) {
                    $fees = $order->get_fees();
                    foreach ( $fees as $fee_val ) {
                        if ( method_exists( $fee_val, 'get_amount' ) ) {
                            $fee += (float) $fee_val->get_amount();
                        }
                    }
                }
            }
        }

        return $fee;
    }

    public function o100ne_order_refund( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( 0 );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $refund = 0;

        $order_totals = $order->get_order_item_totals();

        if ( ! empty( $order_totals ) ) {
            foreach ( $order_totals as $index => $value ) {
                if ( strpos( $index, 'refund' ) !== false ) {
                    $refund = $order->get_total_refunded();
                }
            }
        }

        return wc_price( $refund, [ 'currency' => $order->get_currency() ] );
    }

    public function o100ne_order_subtotal( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( '18.00' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_totals = $order->get_order_item_totals();

        if ( empty( $order_totals ) ) {
            return '';
        }

        if ( ! empty( $order_totals['cart_subtotal'] ) && isset( $order_totals['cart_subtotal']['value'] ) ) {
            return $order_totals['cart_subtotal']['value'];
        } else {
            return '';
        }
    }

    public function o100ne_order_total( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return wc_price( '18.00' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] );
    }

    public function o100ne_order_total_value( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '18.00';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_total();
    }

    public function o100ne_order_coupon_codes( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return 'sample_code';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_coupon_codes = '';

        if ( method_exists( $order, 'get_coupon_codes' ) && ! empty( $order->get_coupon_codes() ) ) {
            $coupon_codes = $order->get_coupon_codes();
            foreach ( $coupon_codes as $coupon_code ) {
                $order_coupon_codes .= wp_kses_post( $coupon_code );
            }
        }
        return $order_coupon_codes;
    }

    public function o100ne_order_product_line_item_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return count( $order->get_items() );
    }

    public function o100ne_order_product_line_item_count_double( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '2';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return count( $order->get_items() ) * 2;
    }

    public function o100ne_order_product_item_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        return $order->get_item_count();
    }

    public function o100ne_order_product_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $products_in_order = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product->is_type( 'variation' ) ) {
                $products_in_order[] = $product->get_parent_id();
            } else {
                $products_in_order[] = $product->get_id();
            }
        }

        return count( array_unique( $products_in_order ) );
    }

    public function o100ne_order_product_variation_count( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '1';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $products_in_order = [];
        foreach ( $order->get_items() as $item ) {
            $product             = $item->get_product();
            $products_in_order[] = $product->get_id();
        }

        return count( array_unique( $products_in_order ) );
    }

    public function o100ne_customer_roles( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $current_user = wp_get_current_user();
            if ( ! empty( $current_user ) && isset( $current_user->roles ) && ! empty( $current_user->roles ) ) {
                return implode( ', ', $current_user->roles );
            }
            return '';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $user = $order->get_user();
        if ( ! empty( $user ) && isset( $user->roles ) && ! empty( $user->roles ) ) {
            return implode( ', ', $user->roles );
        }
        return '';
    }

    public function o100ne_customer_note( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'customer note', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_notes = $order->get_customer_order_notes();
        if ( ! empty( $order_notes ) && count( $order_notes ) > 0 ) {
            return wp_kses_post( wpautop( wptexturize( make_clickable( $order_notes[0]->comment_content ) ) ) );
        }
        return '';
    }

    public function o100ne_customer_notes( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'customer notes', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $order_notes = $order->get_customer_order_notes();
        $list_notes  = '';
        if ( ! empty( $order_notes ) && count( $order_notes ) > 0 ) {
            foreach ( $order_notes as $note ) {
                $list_notes .= wp_kses_post( wpautop( wptexturize( make_clickable( $note->comment_content ) ) ) );
            }
        }
        return $list_notes;
    }

    public function o100ne_customer_provided_note( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'customer provided notes', 'order100' );
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        if ( empty( $order ) ) {
            /**
             * Not having order_id
             */
            return '';
        }
        $customer_note = $order->get_customer_note();
        if ( ! empty( $customer_note ) ) {
            return $customer_note;
        }
        return '';
    }

    public function woocommerce_email_order_meta( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return '[woocommerce_email_order_meta]';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );

        $email = $render_data['email'] ?? [];

        if ( empty( $order ) || empty( $email ) ) {
            /**
             * Not having order_id
             */
            return '';
        }

        $sent_to_admin = isset( $render_data['sent_to_admin'] ) ? $render_data['sent_to_admin'] : false;
        $plain_text    = isset( $render_data['plain_text'] ) ? $render_data['plain_text'] : false;

        ob_start();

        do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

        $return = ob_get_clean();

        return $return;
    }
}


