<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Abstracts\BaseShortcode;
use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\TemplateHelpers;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * @method: static CommonShortcodes get_instance()
 */
class CommonShortcodes extends BaseShortcode {

    use SingletonTrait;

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public function get_shortcodes() {
        $shortcodes = [
            [
                'name'        => 'o100_site_name',
                'description' => __( 'Site Name', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_site_name' ],
            ],
            [
                'name'        => 'o100_site_link',
                'description' => __( 'Site Link', 'order100' ),
                'attributes'  => [
                    'text_link' => __( 'Home URL', 'order100' ),
                ],
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_site_link' ],
            ],

            [
                'name'        => 'o100_domain',
                'description' => __( 'Domain', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_domain' ],
            ],
            [
                'name'        => 'o100_user_account_link',
                'description' => __( 'User Account Link', 'order100' ),
                'attributes'  => [
                    'text_link' => __( 'My Account', 'order100' ),
                ],
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_user_account_link' ],
            ],

            [
                'name'        => 'o100_user_email',
                'description' => __( 'User Email', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_user_email' ],
            ],
            [
                'name'        => 'o100_user_id',
                'description' => __( 'User ID', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_user_id' ],
            ],
            [
                'name'        => 'o100_customer_username',
                'description' => __( 'Username', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_username' ],
            ],
            [
                'name'        => 'o100_customer_name',
                'description' => __( 'Name', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_name' ],
            ],
            [
                'name'        => 'o100_customer_first_name',
                'description' => __( 'First Name', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_first_name' ],
            ],
            [
                'name'        => 'o100_customer_last_name',
                'description' => __( 'Last Name', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_last_name' ],
            ],
            [
                'name'        => 'o100_get_heading',
                'description' => __( 'Email heading', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_get_heading' ],
            ],
            [
                'name'        => 'o100_additional_content',
                'description' => __( 'Additional Content', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_additional_content' ],
            ],
            // New Shortcodes for Order100 Modules
            [
                'name'        => 'o100_store_name',
                'description' => __( 'Restaurant Name', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_store_name' ],
            ],
            [
                'name'        => 'o100_store_logo_url',
                'description' => __( 'Restaurant Logo URL', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_store_logo_url' ],
            ],
            [
                'name'        => 'o100_store_phone',
                'description' => __( 'Restaurant Phone', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_store_phone' ],
            ],
            [
                'name'        => 'o100_store_address',
                'description' => __( 'Restaurant Address', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_store_address' ],
            ],
            [
                'name'        => 'o100_branch_name',
                'description' => __( 'Branch Name', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_branch_name' ],
            ],
            [
                'name'        => 'o100_branch_phone',
                'description' => __( 'Branch Phone', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_branch_phone' ],
            ],
            [
                'name'        => 'o100_branch_address',
                'description' => __( 'Branch Address', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_branch_address' ],
            ],
            [
                'name'        => 'o100_customer_birthday',
                'description' => __( 'Customer Birthday', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_birthday' ],
            ],
            [
                'name'        => 'o100_loyalty_points',
                'description' => __( 'Loyalty Points (Legacy)', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_loyalty_balance' ],
            ],
            [
                'name'        => 'o100_loyalty_points_earned',
                'description' => __( 'Points Earned This Order', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_loyalty_points_earned' ],
            ],
            [
                'name'        => 'o100_loyalty_balance',
                'description' => __( 'Total Points Balance', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_loyalty_balance' ],
            ],
            [
                'name'        => 'o100_customer_total_orders',
                'description' => __( 'Customer Total Orders', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_total_orders' ],
            ],
            [
                'name'        => 'o100_customer_phone',
                'description' => __( 'Customer Phone', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_customer_phone' ],
            ],
            [
                'name'        => 'o100_order_type',
                'description' => __( 'Order Method', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_order_type' ],
            ],
            [
                'name'        => 'o100_order_method',
                'description' => __( 'Order Method', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_order_type' ],
            ],
            [
                'name'        => 'o100_prep_time',
                'description' => __( 'Prep Time', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_prep_time' ],
            ],
            [
                'name'        => 'o100_selected_time',
                'description' => __( 'Selected Time (ASAP/Timeslot)', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_selected_time' ],
            ],
            [
                'name'        => 'o100_selected_date',
                'description' => __( 'Selected Date', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_selected_date' ],
            ],
            [
                'name'        => 'o100_selected_timeslot',
                'description' => __( 'Selected Timeslot', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_selected_timeslot' ],
            ],
            [
                'name'        => 'o100_date_deli',
                'description' => __( 'Delivery/Pickup Date', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_date_deli' ],
            ],
            [
                'name'        => 'o100_time_deli',
                'description' => __( 'Delivery/Pickup Time', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_time_deli' ],
            ],
            [
                'name'        => 'o100_delivery_instruction',
                'description' => __( 'Delivery Instructions', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_delivery_instruction' ],
            ],

            [
                'name'        => 'o100_discount_amount',
                'description' => __( 'Discount Amount', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_discount_amount' ],
            ],
            [
                'name'        => 'o100_unsubscribe_url',
                'description' => __( 'Unsubscribe URL', 'order100' ),
                'group'       => 'general',
                'callback'    => [ $this, 'o100ne_unsubscribe_url' ],
            ],
        ];

        return apply_filters( 'o100_common_shortcodes', $shortcodes );
    }

    public function o100ne_site_name() {
        return esc_html( get_bloginfo( 'name' ) );
    }

    public function o100ne_site_link( $data, $shortcode_atts = [] ) {
        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Home URL', 'order100' ), $is_placeholder );

        return '<a href="' . esc_url( get_home_url() ) . '"> ' . $text_link . ' </a>';
    }



    public function o100ne_domain() {
        if ( ! empty( wp_parse_url( get_site_url() )['host'] ) ) {
            return wp_parse_url( get_site_url() )['host'];
        } return '';
    }

    public function o100ne_user_account_link( $data, $shortcode_atts = [] ) {
        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'My Account', 'order100' ), $is_placeholder );

        return '<a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '"> ' . $text_link . ' </a>';
    }



    public function o100ne_user_email( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'johndoe@gmail.com';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                return $render_data['email']->user_email;
            }
            $user = wp_get_current_user();
            return ! empty( $user ) ? $user->data->user_email : '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) && ! empty( $user->user_email ) ) {
                return $user->user_email;
            } else {
                return $order->get_billing_email();
            }
        }
    }

    public function o100ne_user_id( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return '0';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->object ) ) {
                if ( $render_data['email']->object instanceof \WP_User ) {
                    return $render_data['email']->object->ID;
                }
            }
            $user = wp_get_current_user();
            return ! empty( $user ) ? $user->ID : '0';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) && ! empty( $user->ID ) ) {
                return $user->ID;
            } else {
                $user = get_user_by( 'email', $order->get_billing_email() );
                if ( ! empty( $user ) && ! empty( $user->ID ) ) {
                    return $user->ID;
                }
                return '';
            }
        }
    }

    private function get_crm_customer_from_order( $order ) {
        if ( empty( $order ) ) return null;
        $email = $order->get_billing_email();
        if ( empty( $email ) || ! class_exists( 'O100_Customers_DB' ) ) return null;
        global $wpdb;
        $tbl_customers = \O100_Customers_DB::get_table_customers();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_customers} WHERE email = %s", $email ) );
    }

    public function o100ne_customer_username( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'johndoe';
        }

        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_login ) ) {
                return $render_data['email']->user_login;
            }
            $user = wp_get_current_user();
            return ! empty( $user ) ? $user->user_login : '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) && ! empty( $user->user_login ) ) {
                return $user->user_login;
            } else {
                $user = get_user_by( 'email', $order->get_billing_email() );
                if ( ! empty( $user ) && ! empty( $user->user_login ) ) {
                    return $user->user_login;
                }
                return '';
            }
        }
    }

    public function o100ne_customer_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'John Doe';
        }
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                $user = get_user_by( 'email', $render_data['email']->user_email );
            } else {
                $user = wp_get_current_user();
            }
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
                return ' ' !== $name ? $name : $user->user_nicename;
            }
            return '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true );
                if ( ' ' === $name ) {
                    $name = $user->user_nicename;
                }
            }
            if ( empty( trim( $name ) ) ) {
                $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            }
            if ( empty( trim( $name ) ) ) {
                $crm = $this->get_crm_customer_from_order( $order );
                if ( $crm ) {
                    $name = trim( $crm->first_name . ' ' . $crm->last_name );
                }
            }
            return ! empty( $name ) ? trim( $name ) : '';
        }//end if
    }

    public function o100ne_customer_first_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'John';
        }
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                $user = get_user_by( 'email', $render_data['email']->user_email );
            } else {
                $user = wp_get_current_user();
            }
            if ( ! empty( $user ) ) {
                return get_user_meta( $user->ID, 'first_name', true );
            }
            return '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'first_name', true );
            }
            if ( empty( $name ) ) {
                $name = $order->get_billing_first_name();
            }
            return $name;
        }
    }

    public function o100ne_customer_last_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( ! empty( $render_data['is_sample'] ) ) {
            return 'Doe';
        }
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( empty( $order ) ) {
            if ( isset( $render_data['email'] ) && isset( $render_data['email']->user_email ) ) {
                $user = get_user_by( 'email', $render_data['email']->user_email );
            } else {
                $user = wp_get_current_user();
            }
            if ( ! empty( $user ) ) {
                return get_user_meta( $user->ID, 'last_name', true );
            }
            return '';
        } else {
            $user = $order->get_user();
            if ( ! empty( $user ) ) {
                $name = get_user_meta( $user->ID, 'last_name', true );
            }
            if ( empty( $name ) ) {
                $name = $order->get_billing_last_name();
            }
            return $name;
        }
    }

    public function o100ne_get_heading( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( isset( $render_data['email_heading'] ) ) {
            return $render_data['email_heading'];
        } else {
            $order = Helpers::get_order_from_shortcode_data( $render_data );
            if ( ! empty( $order ) && isset( $render_data['email'] ) && 'customer_refunded_order' === $render_data['email']->id ) {
                return 'Order Refunded: ' . ! empty( $order ) ? $order->get_id() : '1';
            }
        }
        return __( 'Email heading', 'order100' );
    }

    public function o100ne_additional_content( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        if ( isset( $render_data['additional_content'] ) ) {
            return wpautop( wptexturize( $render_data['additional_content'] ) );
        }
        return __( 'Additional content', 'order100' );
    }

    // --- New Callbacks for Order100 Modules ---

    public function o100ne_store_name( $data ) {
        $profile = get_option('o100_store_profile', []);
        return isset($profile['name']) ? esc_html($profile['name']) : esc_html(get_bloginfo('name'));
    }

    public function o100ne_store_logo_url( $data ) {
        $profile = get_option('o100_store_profile', []);
        $store_logo = $profile['o100_store_logo'] ?? '';
        if (empty($store_logo)) {
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            if ($custom_logo_id) {
                $image = wp_get_attachment_image_src( $custom_logo_id , 'full' );
                if ($image) $store_logo = $image[0];
            }
        }
        if (empty($store_logo)) {
            $store_logo = esc_url( O100NE_PLUGIN_URL . 'assets/images/woocommerce-logo.png' );
        }
        return esc_url( $store_logo );
    }

    public function o100ne_store_phone( $data ) {
        $profile = get_option('o100_store_profile', []);
        return isset($profile['phone']) ? esc_html($profile['phone']) : '';
    }

    public function o100ne_store_address( $data ) {
        $profile = get_option('o100_store_profile', []);
        return isset($profile['address']) ? esc_html($profile['address']) : '';
    }

    public function o100ne_branch_name( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $branch_id = $order->get_meta( 'o100_branch_id' );
            if ( $branch_id ) {
                return esc_html( get_the_title( $branch_id ) );
            }
        }
        return $this->o100ne_store_name($data);
    }

    public function o100ne_branch_phone( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $branch_id = $order->get_meta( 'o100_branch_id' );
            if ( $branch_id ) {
                $phone = get_post_meta( $branch_id, 'o100_branch_phone', true );
                return esc_html( $phone );
            }
        }
        return $this->o100ne_store_phone($data);
    }

    public function o100ne_branch_address( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $branch_id = $order->get_meta( 'o100_branch_id' );
            if ( $branch_id ) {
                $address = get_post_meta( $branch_id, 'o100_branch_address', true );
                return esc_html( $address );
            }
        }
        return $this->o100ne_store_address($data);
    }

    public function o100ne_customer_birthday( $data ) {
        global $wpdb;
        $email = $this->o100ne_user_email($data);
        if ( ! empty($email) ) {
            $loyalty_account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}o100_loyalty_accounts WHERE email = %s", $email ) );
            if ( $loyalty_account && !empty($loyalty_account->birthday) ) {
                return date_i18n( get_option('date_format'), strtotime($loyalty_account->birthday) );
            }
        }
        return '';
    }

    public function o100ne_loyalty_points_earned( $data ) {
        global $wpdb;
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $ledger = $wpdb->get_row( $wpdb->prepare( "SELECT points FROM {$wpdb->prefix}o100_loyalty_ledger WHERE order_id = %d AND action_type = 'order_earned'", $order->get_id() ) );
            if ( $ledger ) {
                return $ledger->points;
            }
        }
        return '0';
    }

    public function o100ne_loyalty_balance( $data ) {
        global $wpdb;
        $email = $this->o100ne_user_email($data);
        if ( ! empty($email) ) {
            $loyalty_account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}o100_loyalty_accounts WHERE email = %s", $email ) );
            if ( $loyalty_account ) {
                return $loyalty_account->points_balance;
            }
        }
        return '0';
    }

    public function o100ne_customer_total_orders( $data ) {
        $email = $this->o100ne_user_email($data);
        if ( ! empty($email) ) {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                $args = [
                    'customer_id' => $user->ID,
                    'status'      => array('wc-completed', 'wc-processing'),
                    'return'      => 'ids',
                ];
                $orders = wc_get_orders( $args );
                return count( $orders );
            }
        }
        return '0';
    }

    public function o100ne_customer_phone( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $phone = $order->get_billing_phone();
            if ( empty( trim( $phone ) ) ) {
                $crm = $this->get_crm_customer_from_order( $order );
                if ( $crm ) {
                    $phone = $crm->phone;
                }
            }
            return $phone;
        }
        return '';
    }

    public function o100ne_order_type( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $method = $order->get_meta( '_o100_order_method' );
            if ( empty($method) ) {
                $method = $order->get_meta( 'o100_order_method' );
            }
            if ( empty($method) ) {
                $method = $order->get_meta( '_o100_order_type' );
            }
            if ( empty($method) ) {
                $method = $order->get_meta( 'o100_order_type' );
            }
            if ( empty($method) ) {
                $method = $order->get_meta( 'exwfood_order_method' );
            }
            if ( empty($method) ) {
                $method = $order->get_meta( 'exwfood_order_type' );
            }
            
            $method_lower = strtolower( trim( $method ) );
            if ( $method_lower === 'delivery' ) return __('Delivery', 'order100');
            if ( $method_lower === 'pickup' || $method_lower === 'takeaway' ) return __('Pickup', 'order100');
            if ( $method_lower === 'reservation' || $method_lower === 'dinein' ) return __('Dine In', 'order100');
        }
        return __('Online Order', 'order100');
    }

    public function o100ne_prep_time( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $prep_time = $order->get_meta( '_wooauto_confirmed_prep_time' );
            if ( empty($prep_time) ) {
                $prep_time = $order->get_meta( 'o100_prep_time' );
            }
            if ( empty($prep_time) ) {
                $prep_time = $order->get_meta( 'exwfood_prep_time' );
            }
            if ( $prep_time ) {
                return $prep_time . ' ' . __('mins', 'order100');
            }
        }
        return '30 ' . __('mins', 'order100');
    }

    public function o100ne_selected_time( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $val = $order->get_meta( 'o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( '_o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( 'exwfood_time_deli' );
            
            // Format time if it's a timestamp or date format
            if ( !empty($val) && $val !== 'ASAP' ) {
                $time = strtotime($val);
                if ($time !== false) {
                    $date_format = get_option('date_format');
                    $time_format = get_option('time_format');
                    // Check if it's just a time or date+time
                    if ( strpos($val, '-') === false && strpos($val, '/') === false ) {
                        // just time
                        return date_i18n($time_format, $time);
                    } else {
                        // date and time
                        return date_i18n($date_format . ' ' . $time_format, $time);
                    }
                }
                return $val;
            } else if ($val === 'ASAP') {
                return __('ASAP', 'order100');
            }
            return $val;
        }
        return 'ASAP';
    }

    public function o100ne_selected_date( $data ) {
        if ( isset( $data['is_sample'] ) && $data['is_sample'] ) return date_i18n(get_option('date_format'));
        $order = isset($data['order']) ? $data['order'] : false;
        if ( !$order ) {
            $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
            $order = Helpers::get_order_from_shortcode_data( $render_data );
        }
        if ( ! empty( $order ) ) {
            $val = $order->get_meta( 'o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( '_o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( 'exwfood_time_deli' );
            
            if ( !empty($val) && $val !== 'ASAP' ) {
                $time = strtotime($val);
                if ($time !== false) {
                    if ( strpos($val, '-') === false && strpos($val, '/') === false ) {
                        // just time, no date
                        return '';
                    } else {
                        return date_i18n(get_option('date_format'), $time);
                    }
                }
                return $val;
            } else if ($val === 'ASAP') {
                return __('Today', 'order100');
            }
            return $val;
        }
        return '';
    }

    public function o100ne_selected_timeslot( $data ) {
        if ( isset( $data['is_sample'] ) && $data['is_sample'] ) return date_i18n(get_option('time_format'));
        $order = isset($data['order']) ? $data['order'] : false;
        if ( !$order ) {
            $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
            $order = Helpers::get_order_from_shortcode_data( $render_data );
        }
        if ( ! empty( $order ) ) {
            $val = $order->get_meta( 'o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( '_o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( 'exwfood_time_deli' );
            
            if ( !empty($val) && $val !== 'ASAP' ) {
                $time = strtotime($val);
                if ($time !== false) {
                    return date_i18n(get_option('time_format'), $time);
                }
                return $val;
            } else if ($val === 'ASAP') {
                return __('ASAP', 'order100');
            }
            return $val;
        }
        return 'ASAP';
    }

    public function o100ne_delivery_instruction( $data ) {
        if ( isset( $data['is_sample'] ) && $data['is_sample'] ) return 'Leave it at the front door.';
        $order = isset($data['order']) ? $data['order'] : false;
        if ( !$order ) {
            $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
            $order = Helpers::get_order_from_shortcode_data( $render_data );
        }
        if ( ! empty( $order ) ) {
            $instruction = $order->get_meta( 'o100_delivery_instruction' );
            return $instruction;
        }
        return '';
    }



    public function o100ne_discount_amount( $data ) {
        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
        $order = Helpers::get_order_from_shortcode_data( $render_data );
        if ( ! empty( $order ) ) {
            $discount = $order->get_total_discount();
            if ( $discount > 0 ) {
                return wc_price( $discount, [ 'currency' => $order->get_currency() ] );
            }
        }
        return wc_price(0);
    }

    public function o100ne_unsubscribe_url( $data ) {
        $email = $this->o100ne_user_email($data);
        if ( ! empty($email) ) {
            // Generate a secure unsubscribe link
            return add_query_arg([
                'o100_action' => 'unsubscribe',
                'token' => base64_encode($email . '|' . md5($email . NONCE_SALT))
            ], home_url());
        }
        return '#';
    }

    public function o100ne_date_deli( $data ) {
        if ( isset( $data['is_sample'] ) && $data['is_sample'] ) return date_i18n(get_option('date_format'));
        $order = isset($data['order']) ? $data['order'] : false;
        if ( !$order ) {
            $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
            $order = Helpers::get_order_from_shortcode_data( $render_data );
        }
        if ( ! empty( $order ) ) {
            $val = $order->get_meta( 'o100_date_deli' );
            if ( $val === '' ) $val = $order->get_meta( '_o100_date_deli' );
            if ( !empty($val) ) {
                if ( preg_match('/^1\d{9}$/', $val) ) {
                    // It's a UNIX timestamp
                    $time = (int) $val;
                } else if ( preg_match('/^\d{8}$/', $val) ) {
                    $val = substr($val, 0, 4) . '-' . substr($val, 4, 2) . '-' . substr($val, 6, 2);
                    $time = strtotime($val);
                } else {
                    $time = strtotime($val);
                }
                return $time !== false ? date_i18n(get_option('date_format'), $time) : $val;
            }
        }
        return '';
    }

    public function o100ne_time_deli( $data ) {
        if ( isset( $data['is_sample'] ) && $data['is_sample'] ) return '18:30';
        $order = isset($data['order']) ? $data['order'] : false;
        if ( !$order ) {
            $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];
            $order = Helpers::get_order_from_shortcode_data( $render_data );
        }
        if ( ! empty( $order ) ) {
            $val = $order->get_meta( 'o100_time_deli' );
            if ( $val === '' ) $val = $order->get_meta( '_o100_time_deli' );
            return $val ?: '';
        }
        return '';
    }

}