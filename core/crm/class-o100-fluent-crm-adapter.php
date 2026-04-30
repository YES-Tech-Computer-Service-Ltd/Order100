<?php
/**
 * FluentCRM Adapter
 *
 * Implements the CRM interface for FluentCRM.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-crm-adapter.php';

class O100_Fluent_CRM_Adapter implements O100_CRM_Adapter_Interface {

	/**
	 * Get the unique slug for this CRM adapter.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'fluent_crm';
	}

	/**
	 * Get the display name for this CRM adapter.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'FluentCRM';
	}

	/**
	 * Check if FluentCRM is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'FLUENTCRM' ) && function_exists( 'FluentCrmApi' );
	}

	/**
	 * Initialize the adapter.
	 */
	public function init() {
		if ( ! $this->is_active() ) {
			return;
		}

		// Hook into order status change to processing or completed to sync data
		add_action( 'woocommerce_order_status_processing', array( $this, 'sync_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'sync_order' ), 10, 1 );
	}

	/**
	 * Get the list of custom fields required by this addon.
	 *
	 * @return array
	 */
	/**
	 * Get the list of custom fields required by this addon.
	 *
	 * @return array
	 */
	private function get_required_custom_fields() {
		return array(
			'current_order_type' => array(
				'label' => 'Current Order Type',
				'type'  => 'text',
			),
			'delivery_count' => array(
				'label' => 'Delivery Count',
				'type'  => 'number',
			),
			'takeaway_count' => array(
				'label' => 'Takeaway Count',
				'type'  => 'number',
			),
			'preferred_order_type' => array(
				'label' => 'Preferred Order Type',
				'type'  => 'text',
			),
			'scheduled_order_date' => array(
				'label' => 'Scheduled Order Date',
				'type'  => 'text',
			),
			'scheduled_order_time' => array(
				'label' => 'Scheduled Order Time',
				'type'  => 'text',
			),
			'scheduled_order_datetime' => array(
				'label' => 'Scheduled Order DateTime',
				'type'  => 'text',
			),
		);
	}

	/**
	 * Ensure required custom fields exist in FluentCRM.
	 * 
     * @deprecated Manual creation required now.
	 * @return void
	 */
	public function ensure_custom_fields() {
        // Disabled - Manual creation required
		return;
	}

	/**
	 * Sync order data to FluentCRM.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return void
	 */
	/**
	 * Sync order data to FluentCRM.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return void
	 */
	/**
	 * Sync order data to FluentCRM.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return void
	 */
	public function sync_order( $order_id ) {
		if ( ! $this->is_active() ) {
			return;
		}

        // Wrap in try-catch to prevent checkout crash
        try {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                return;
            }

            // Prevent double counting/syncing if hook fires multiple times
            if ( $order->get_meta( '_o100_behavior_counted' ) === 'yes' ) {
                return;
            }

            $email = $order->get_billing_email();
            if ( empty( $email ) ) {
                return;
            }

            $first_name = $order->get_billing_first_name();
            $last_name  = $order->get_billing_last_name();

            // 1. Data Extraction & Normalization
            
            // Order Type — try o100_ first, then legacy keys
            $raw_method = $order->get_meta( 'o100_order_method' );
            if ( ! $raw_method ) {
                $raw_method = $order->get_meta( '_o100_order_type' );
            }
            if ( ! $raw_method ) {
                $raw_method = $order->get_meta( 'exwfood_order_method' );
            }

            $method_norm = '';
            $current_type = '';
            
            $method_slug = strtolower( trim( (string) $raw_method ) );

            if ( $method_slug === 'delivery' ) {
                $method_norm = 'delivery';
                $current_type = 'Delivery';
            } elseif ( in_array( $method_slug, array( 'takeaway', 'pickup' ), true ) ) {
                $method_norm = 'takeaway';
                $current_type = 'Takeaway';
            } else {
                 // Fallback or unknown
                 $method_norm = $method_slug;
                 $current_type = ucfirst( $method_slug );
            }

            // Date & Time — try o100_ first, then legacy keys
            $scheduled_date = trim( (string) $order->get_meta( 'o100_date_deli', true ) );
            if ( $scheduled_date === '' ) $scheduled_date = trim( (string) $order->get_meta( 'exwfood_date_deli', true ) );
            $scheduled_time = trim( (string) $order->get_meta( 'o100_time_deli', true ) );
            if ( $scheduled_time === '' ) $scheduled_time = trim( (string) $order->get_meta( 'exwfood_time_deli', true ) );
            $timeslot_raw   = trim( (string) $order->get_meta( 'o100_timeslot', true ) );
            if ( $timeslot_raw === '' ) $timeslot_raw = trim( (string) $order->get_meta( 'exwfood_timeslot', true ) );

            $scheduled_datetime = '';
            if ( $scheduled_date !== '' ) {
                $start_time = '';
                if ( $timeslot_raw !== '' && strpos( $timeslot_raw, '|' ) !== false ) {
                    $parts = explode( '|', $timeslot_raw );
                    $start_time = trim( $parts[0] );
                }
                
                if ( $start_time !== '' ) {
                    $scheduled_datetime = $scheduled_date . ' ' . $start_time;
                } elseif ( $scheduled_time !== '' ) {
                    $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time;
                } else {
                    $scheduled_datetime = $scheduled_date;
                }
            }

            // 2. Counts Calculation
            // We use dynamic calculation from orders to ensure accuracy without maintaining user meta counters manually
            // This achieves the same result as the user snippet but is stateless and robust
            $delivery_count = $this->get_orders_count_by_method( $email, 'delivery' );
            $takeaway_count = $this->get_orders_count_by_method( $email, 'takeaway' );
            
            $preferred_order_type = 'Hybrid';
            if ( $delivery_count > $takeaway_count ) {
                $preferred_order_type = 'Delivery';
            } elseif ( $takeaway_count > $delivery_count ) {
                $preferred_order_type = 'Takeaway';
            }

            // 3. Prepare Data for FluentCRM
            $custom_values = array(
                'current_order_type'       => $current_type,
                'delivery_count'           => $delivery_count,
                'takeaway_count'           => $takeaway_count,
                'preferred_order_type'     => $preferred_order_type,
                'scheduled_order_date'     => $scheduled_date,
                'scheduled_order_time'     => $scheduled_time,
                'scheduled_order_datetime' => $scheduled_datetime,
            );

            // 4. Sync to FluentCRM
            if ( function_exists( 'FluentCrmApi' ) ) {
                $api = FluentCrmApi( 'contacts' );
                
                // Use createOrUpdate with custom_values directly included
                // This matches the user's working snippet structure
                $data = array(
                    'email'         => $email,
                    'first_name'    => $first_name,
                    'last_name'     => $last_name,
                    'status'        => 'subscribed',
                    'custom_values' => $custom_values
                );

                $api->createOrUpdate( $data );
            }

            // 5. Mark as counted
            $order->update_meta_data( '_o100_behavior_counted', 'yes' );
            $order->save();

        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Order100 CRM Sync Error: ' . $e->getMessage() );
            }
        }
	}

    /**
     * Sync initial confirmation data to FluentCRM.
     *
     * @param WC_Order $order The WooCommerce Order object.
     * @param int $prep_time  The preparation time in minutes.
     * @return void
     */
    public function sync_initial_confirmation( $order, $prep_time ) {
        if ( ! $this->is_active() || ! function_exists( 'FluentCrmApi' ) ) {
            return;
        }

        $email = $order->get_billing_email();
        if ( empty( $email ) ) {
            return;
        }

        try {
            $api = FluentCrmApi( 'contacts' );
            
            $data = array(
                'email'         => $email,
                'first_name'    => $order->get_billing_first_name(),
                'last_name'     => $order->get_billing_last_name(),
                'status'        => 'subscribed',
                'custom_values' => array(
                    'confirmed_prep_time' => $prep_time,
                ),
                'tags'          => array( 'order_merchant_confirmed' )
            );

            $api->createOrUpdate( $data );

        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Order100 CRM Sync Confirmation Error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Sync delay modification data to FluentCRM.
     *
     * @param WC_Order $order         The WooCommerce Order object.
     * @param int      $new_prep_time The new preparation time in minutes.
     * @param string   $reason        The reason for delay.
     * @return void
     */
    public function sync_delay_modification( $order, $new_prep_time, $reason ) {
        if ( ! $this->is_active() || ! function_exists( 'FluentCrmApi' ) ) {
            return;
        }

        $email = $order->get_billing_email();
        if ( empty( $email ) ) {
            return;
        }

        try {
            $api = FluentCrmApi( 'contacts' );
            
            $data = array(
                'email'         => $email,
                'status'        => 'subscribed',
                'custom_values' => array(
                    'confirmed_prep_time' => $new_prep_time,
                ),
                'tags'          => array( 'order_prep_time_delayed' )
            );

            $api->createOrUpdate( $data );

        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Order100 CRM Sync Delay Error: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Get count of orders by method for a customer email.
     *
     * @param string $email Customer email.
     * @param string $method Order method (delivery, takeaway).
     * @return int
     */
    private function get_orders_count_by_method( $email, $method ) {
        $cache_key = 'o100_crm_cnt_' . md5( $email . $method );
        $cached_count = get_transient( $cache_key );
        
        if ( false !== $cached_count ) {
            return (int) $cached_count;
        }

        $args = array(
            'limit'      => -1,
            'email'      => $email,
            'status'     => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'o100_order_method',
                    'value'   => $method,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'exwfood_order_method',
                    'value'   => $method,
                    'compare' => '=',
                ),
                array(
                    'key'     => '_o100_order_type',
                    'value'   => $method,
                    'compare' => '=',
                ),
            ),
            'return' => 'ids',
        );

        $orders = wc_get_orders( $args );
        $count = count( $orders );

        set_transient( $cache_key, $count, HOUR_IN_SECONDS );

        return $count;
    }
}



// TS: 20260429003914
