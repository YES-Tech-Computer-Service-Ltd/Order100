<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Automation_Engine {

	public static function init() {
		// === Orders ===
		add_action( 'woocommerce_new_order', array( __CLASS__, 'handle_new_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_order_processing' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'handle_order_completed' ), 20, 1 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'handle_order_failed' ), 20, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_order_cancelled' ), 20, 1 );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_order_refunded' ), 20, 1 );
		add_action( 'o100_crm_cart_abandoned', array( __CLASS__, 'handle_abandoned_cart' ), 20, 1 );

		// === Customers ===
		add_action( 'o100_crm_customer_created', array( __CLASS__, 'handle_customer_created' ), 20, 2 );
		add_action( 'o100_crm_tag_added', array( __CLASS__, 'handle_tag_added' ), 20, 2 );
		add_action( 'o100_crm_tag_removed', array( __CLASS__, 'handle_tag_removed' ), 20, 2 );
		add_action( 'o100_crm_list_added', array( __CLASS__, 'handle_list_added' ), 20, 2 );
		add_action( 'o100_crm_list_removed', array( __CLASS__, 'handle_list_removed' ), 20, 2 );
		add_action( 'o100_crm_status_changed', array( __CLASS__, 'handle_status_changed' ), 20, 3 );

		// === Reservations ===
		add_action( 'o100_new_reservation', array( __CLASS__, 'handle_new_reservation' ), 20, 2 );
		add_action( 'o100_reservation_status_changed', array( __CLASS__, 'handle_reservation_status' ), 20, 3 );

		// === Loyalty ===
		add_action( 'o100_loyalty_level_changed', array( __CLASS__, 'handle_loyalty_level' ), 20, 3 );
		add_action( 'o100_loyalty_points_earned', array( __CLASS__, 'handle_loyalty_points' ), 20, 3 );
		add_action( 'o100_loyalty_auto_reward_expiring', array( __CLASS__, 'handle_reward_expiring' ), 20, 5 );
		add_action( 'o100_loyalty_punch_card_redeemed', array( __CLASS__, 'handle_punch_card' ), 20, 4 );

		// === Account ===
		add_action( 'wp_login', array( __CLASS__, 'handle_user_login' ), 20, 2 );

		// === Cron ===
		if ( ! wp_next_scheduled( 'o100_automation_daily_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'o100_automation_daily_cron' );
		}
		add_action( 'o100_automation_daily_cron', array( __CLASS__, 'handle_daily_cron' ) );

		// === Action Scheduler callback for delayed actions ===
		add_action( 'o100_execute_delayed_automation', array( __CLASS__, 'execute_delayed_actions' ), 10, 2 );
	}

	// =========================================================================
	// ORDER HANDLERS
	// =========================================================================

	public static function handle_new_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'new_order', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	public static function handle_order_processing( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'order_processing', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	public static function handle_order_completed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'order_completed', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	public static function handle_order_failed( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'order_failed', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	public static function handle_order_cancelled( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'order_cancelled', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	public static function handle_order_refunded( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'order_refunded', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	public static function handle_abandoned_cart( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		self::trigger_event( 'abandoned_cart', array(
			'order_id'    => $order_id,
			'user_id'     => $order->get_user_id(),
			'email'       => $order->get_billing_email(),
			'customer_id' => self::get_crm_customer_id( $order->get_user_id(), $order->get_billing_email() ),
			'first_name'  => $order->get_billing_first_name(),
		) );
	}

	// =========================================================================
	// CUSTOMER (CRM) HANDLERS
	// =========================================================================

	public static function handle_customer_created( $customer_id, $data = array() ) {
		$customer = self::get_crm_customer( $customer_id );
		if ( ! $customer ) return;
		self::trigger_event( 'customer_created', array(
			'customer_id' => $customer_id,
			'user_id'     => $customer->wp_user_id,
			'email'       => $customer->email,
			'first_name'  => $customer->first_name,
		) );
	}

	public static function handle_tag_added( $customer_id, $tag_id ) {
		$customer = self::get_crm_customer( $customer_id );
		if ( ! $customer ) return;
		self::trigger_event( 'customer_tag_added', array(
			'customer_id' => $customer_id,
			'user_id'     => $customer->wp_user_id,
			'email'       => $customer->email,
			'first_name'  => $customer->first_name,
			'tag_id'      => $tag_id,
		) );
	}

	public static function handle_tag_removed( $customer_id, $tag_id ) {
		$customer = self::get_crm_customer( $customer_id );
		if ( ! $customer ) return;
		self::trigger_event( 'customer_tag_removed', array(
			'customer_id' => $customer_id,
			'user_id'     => $customer->wp_user_id,
			'email'       => $customer->email,
			'first_name'  => $customer->first_name,
			'tag_id'      => $tag_id,
		) );
	}

	public static function handle_list_added( $customer_id, $list_id ) {
		$customer = self::get_crm_customer( $customer_id );
		if ( ! $customer ) return;
		self::trigger_event( 'customer_list_added', array(
			'customer_id' => $customer_id,
			'user_id'     => $customer->wp_user_id,
			'email'       => $customer->email,
			'first_name'  => $customer->first_name,
			'list_id'     => $list_id,
		) );
	}

	public static function handle_list_removed( $customer_id, $list_id ) {
		$customer = self::get_crm_customer( $customer_id );
		if ( ! $customer ) return;
		self::trigger_event( 'customer_list_removed', array(
			'customer_id' => $customer_id,
			'user_id'     => $customer->wp_user_id,
			'email'       => $customer->email,
			'first_name'  => $customer->first_name,
			'list_id'     => $list_id,
		) );
	}

	public static function handle_status_changed( $customer_id, $new_status, $old_status ) {
		$customer = self::get_crm_customer( $customer_id );
		if ( ! $customer ) return;
		self::trigger_event( 'customer_status_changed', array(
			'customer_id' => $customer_id,
			'user_id'     => $customer->wp_user_id,
			'email'       => $customer->email,
			'first_name'  => $customer->first_name,
			'new_status'  => $new_status,
			'old_status'  => $old_status,
		) );
	}

	// =========================================================================
	// RESERVATION HANDLERS
	// =========================================================================

	public static function handle_new_reservation( $reservation_id, $data = array() ) {
		$user_id  = isset( $data['user_id'] ) ? intval( $data['user_id'] ) : 0;
		$email    = isset( $data['email'] ) ? $data['email'] : '';
		$name     = isset( $data['name'] ) ? $data['name'] : '';

		if ( empty( $email ) && $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$email = $user->user_email;
				if ( empty( $name ) ) {
					$name = $user->first_name;
				}
			}
		}

		self::trigger_event( 'new_reservation', array(
			'reservation_id' => $reservation_id,
			'user_id'        => $user_id,
			'email'          => $email,
			'first_name'     => $name,
			'customer_id'    => self::get_crm_customer_id( $user_id, $email ),
		) );
	}

	public static function handle_reservation_status( $reservation_id, $new_status, $old_status ) {
		// Dispatch to the appropriate trigger based on new status
		$status_trigger_map = array(
			'confirmed' => 'reservation_confirmed',
			'cancelled' => 'reservation_cancelled',
			'no_show'   => 'reservation_no_show',
		);

		if ( ! isset( $status_trigger_map[ $new_status ] ) ) return;

		$trigger_type = $status_trigger_map[ $new_status ];

		// Try to get reservation context
		$context = array(
			'reservation_id' => $reservation_id,
			'new_status'     => $new_status,
			'old_status'     => $old_status,
			'user_id'        => 0,
			'email'          => '',
			'first_name'     => '',
			'customer_id'    => 0,
		);

		// Attempt to load reservation data from the reservations table
		global $wpdb;
		$res_table = $wpdb->prefix . 'o100_reservations';
		$reservation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $res_table WHERE id = %d LIMIT 1", $reservation_id ) );
		if ( $reservation ) {
			$context['user_id']     = isset( $reservation->user_id ) ? intval( $reservation->user_id ) : 0;
			$context['email']       = isset( $reservation->email ) ? $reservation->email : '';
			$context['first_name']  = isset( $reservation->name ) ? $reservation->name : '';
			$context['customer_id'] = self::get_crm_customer_id( $context['user_id'], $context['email'] );
		}

		self::trigger_event( $trigger_type, $context );
	}

	// =========================================================================
	// LOYALTY HANDLERS
	// =========================================================================

	public static function handle_loyalty_level( $user_id, $new_level, $old_level ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;
		self::trigger_event( 'loyalty_level_changed', array(
			'user_id'     => $user_id,
			'email'       => $user->user_email,
			'first_name'  => $user->first_name,
			'customer_id' => self::get_crm_customer_id( $user_id, $user->user_email ),
			'new_level'   => $new_level,
			'old_level'   => $old_level,
		) );
	}

	public static function handle_loyalty_points( $user_id, $points_earned, $new_balance ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;
		self::trigger_event( 'loyalty_points_earned', array(
			'user_id'       => $user_id,
			'email'         => $user->user_email,
			'first_name'    => $user->first_name,
			'customer_id'   => self::get_crm_customer_id( $user_id, $user->user_email ),
			'points_earned' => $points_earned,
			'new_balance'   => $new_balance,
		) );
	}

	public static function handle_reward_expiring( $user_id, $reward_type, $reward_value, $expiry_date, $coupon_code ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;
		self::trigger_event( 'loyalty_reward_expiring', array(
			'user_id'      => $user_id,
			'email'        => $user->user_email,
			'first_name'   => $user->first_name,
			'customer_id'  => self::get_crm_customer_id( $user_id, $user->user_email ),
			'reward_type'  => $reward_type,
			'reward_value' => $reward_value,
			'expiry_date'  => $expiry_date,
			'coupon_code'  => $coupon_code,
		) );
	}

	public static function handle_punch_card( $user_id, $card_id, $card_name, $reward_data ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;
		self::trigger_event( 'loyalty_punch_card_completed', array(
			'user_id'     => $user_id,
			'email'       => $user->user_email,
			'first_name'  => $user->first_name,
			'customer_id' => self::get_crm_customer_id( $user_id, $user->user_email ),
			'card_id'     => $card_id,
			'card_name'   => $card_name,
			'reward_data' => $reward_data,
		) );
	}

	// =========================================================================
	// ACCOUNT HANDLERS
	// =========================================================================

	public static function handle_user_login( $user_login, $user ) {
		if ( ! is_object( $user ) ) {
			$user = get_user_by( 'login', $user_login );
		}
		if ( ! $user ) return;
		self::trigger_event( 'user_login', array(
			'user_id'     => $user->ID,
			'email'       => $user->user_email,
			'first_name'  => $user->first_name,
			'customer_id' => self::get_crm_customer_id( $user->ID, $user->user_email ),
		) );
	}

	// =========================================================================
	// CRON HANDLER
	// =========================================================================

	public static function handle_daily_cron() {
		if ( ! class_exists( 'O100_Customers_DB' ) ) return;
		if ( ! class_exists( 'O100_Automation_DB' ) ) return;

		global $wpdb;
		$table = O100_Customers_DB::get_table_customers();

		$automations = O100_Automation_DB::get_automations( array( 'status' => 'active' ) );
		if ( empty( $automations ) ) return;

		foreach ( $automations as $auto ) {
			if ( $auto->trigger_type === 'customer_inactive' ) {
				$config = json_decode( $auto->trigger_config, true );
				if ( ! is_array( $config ) ) $config = array();
				$days = isset( $config['days'] ) ? intval( $config['days'] ) : 30;
				$target_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

				$customers = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, wp_user_id, email, first_name FROM $table WHERE DATE(last_order_date) <= %s",
					$target_date
				) );

				if ( ! empty( $customers ) ) {
					foreach ( $customers as $c ) {
						$context = array(
							'user_id'       => $c->wp_user_id,
							'email'         => $c->email,
							'first_name'    => $c->first_name,
							'customer_id'   => $c->id,
							'automation_id' => $auto->id
						);
						if ( self::evaluate_conditions( $auto->conditions, $context ) ) {
							self::execute_actions( $auto->actions, $context );
						}
					}
				}
			} elseif ( $auto->trigger_type === 'customer_birthday' ) {
				$config = json_decode( $auto->trigger_config, true );
				if ( ! is_array( $config ) ) $config = array();
				$days_before = isset( $config['days_before'] ) ? intval( $config['days_before'] ) : 7;
				$target_date = date( 'm-d', strtotime( "+{$days_before} days" ) );

				$customers = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, wp_user_id, email, first_name FROM $table WHERE DATE_FORMAT(birthday, '%%m-%%d') = %s",
					$target_date
				) );

				if ( ! empty( $customers ) ) {
					foreach ( $customers as $c ) {
						$context = array(
							'user_id'       => $c->wp_user_id,
							'email'         => $c->email,
							'first_name'    => $c->first_name,
							'customer_id'   => $c->id,
							'automation_id' => $auto->id
						);
						if ( self::evaluate_conditions( $auto->conditions, $context ) ) {
							self::execute_actions( $auto->actions, $context );
						}
					}
				}
			} elseif ( $auto->trigger_type === 'customer_anniversary' ) {
				$config = json_decode( $auto->trigger_config, true );
				if ( ! is_array( $config ) ) $config = array();
				$days_before = isset( $config['days_before'] ) ? intval( $config['days_before'] ) : 0;
				$target_date = date( 'm-d', strtotime( "+{$days_before} days" ) );

				$customers = $wpdb->get_results( $wpdb->prepare(
					"SELECT id, wp_user_id, email, first_name FROM $table WHERE DATE_FORMAT(created_at, '%%m-%%d') = %s AND DATE(created_at) < %s",
					$target_date,
					date( 'Y-m-d' )
				) );

				if ( ! empty( $customers ) ) {
					foreach ( $customers as $c ) {
						$context = array(
							'user_id'       => $c->wp_user_id,
							'email'         => $c->email,
							'first_name'    => $c->first_name,
							'customer_id'   => $c->id,
							'automation_id' => $auto->id
						);
						if ( self::evaluate_conditions( $auto->conditions, $context ) ) {
							self::execute_actions( $auto->actions, $context );
						}
					}
				}
			}
		}
	}

	// =========================================================================
	// CORE ENGINE
	// =========================================================================

	/**
	 * Fire automation trigger — find matching automations and execute them
	 */
	public static function trigger_event( $trigger_type, $context = array() ) {
		// 1. Process goal completions first before any triggers
		self::check_goal_completions( $trigger_type, $context );

		$automations = O100_Automation_DB::get_automations( array(
			'status'       => 'active',
			'trigger_type' => $trigger_type,
		) );

		if ( empty( $automations ) ) return;

		foreach ( $automations as $auto ) {
			$context['automation_id'] = $auto->id;
			
			// 2. Check Trigger Config Details (e.g. specific tag_id or list_id)
			if ( ! empty( $auto->trigger_config ) ) {
				$config = json_decode( $auto->trigger_config, true );
				if ( is_array( $config ) ) {
					// tag_id matching
					if ( ( $trigger_type === 'customer_tag_added' || $trigger_type === 'customer_tag_removed' ) && ! empty( $config['tag_id'] ) ) {
						if ( ! isset( $context['tag_id'] ) ) continue;
						$allowed = is_array( $config['tag_id'] ) ? $config['tag_id'] : array( $config['tag_id'] );
						if ( ! in_array( (string) $context['tag_id'], $allowed, true ) && ! in_array( (int) $context['tag_id'], $allowed, true ) ) continue;
					}
					// list_id matching
					if ( ( $trigger_type === 'customer_list_added' || $trigger_type === 'customer_list_removed' ) && ! empty( $config['list_id'] ) ) {
						if ( ! isset( $context['list_id'] ) ) continue;
						$allowed = is_array( $config['list_id'] ) ? $config['list_id'] : array( $config['list_id'] );
						if ( ! in_array( (string) $context['list_id'], $allowed, true ) && ! in_array( (int) $context['list_id'], $allowed, true ) ) continue;
					}
					// loyalty level matching
					if ( $trigger_type === 'loyalty_level_changed' && ! empty( $config['level_id'] ) ) {
						if ( ! isset( $context['new_level'] ) ) continue;
						$allowed = is_array( $config['level_id'] ) ? $config['level_id'] : array( $config['level_id'] );
						if ( ! in_array( (string) $context['new_level'], $allowed, true ) && ! in_array( (int) $context['new_level'], $allowed, true ) ) continue;
					}
				}
			}

			// 3. Check Execution Limits (Run Once vs Re-entry)
			if ( ! self::check_execution_limits( $auto, $context ) ) {
				continue;
			}

			if ( self::evaluate_conditions( $auto->conditions, $context ) ) {
				self::execute_actions( $auto->actions, $context );
			}
		}
	}

	/**
	 * Check execution limits based on rules_and_goals and logs
	 */
	private static function check_execution_limits( $auto, $context ) {
		if ( empty( $context['customer_id'] ) ) return true; // Without a customer, we can't limit

		$rules = json_decode( $auto->rules_and_goals, true );
		if ( ! is_array( $rules ) ) {
			$rules = array( 'allow_reentry' => false, 'wait_days' => 0 ); // Default: Run once
		}

		global $wpdb;
		$logs_table = O100_Automation_DB::get_table_logs();
		
		$last_run = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $logs_table WHERE automation_id = %d AND customer_id = %d ORDER BY id DESC LIMIT 1",
			$auto->id, $context['customer_id']
		) );

		if ( ! $last_run ) return true; // Never run before

		if ( empty( $rules['allow_reentry'] ) ) {
			return false; // Run once, and it already ran
		}

		// Re-entry is allowed, check wait_days
		$wait_days = isset( $rules['wait_days'] ) ? intval( $rules['wait_days'] ) : 0;
		if ( $wait_days > 0 ) {
			$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$wait_days} days" ) );
			if ( $last_run->run_at > $cutoff_date ) {
				return false; // Too soon to re-enter
			}
		}

		return true; // Allowed to re-enter
	}

	/**
	 * Check if any running automations reached their exit goal
	 */
	private static function check_goal_completions( $trigger_type, $context ) {
		if ( empty( $context['customer_id'] ) ) return;

		// Map trigger events to Goal IDs
		$goal_achieved = null;
		if ( $trigger_type === 'new_order' || $trigger_type === 'order_completed' ) {
			$goal_achieved = 'placed_order';
		}
		
		if ( ! $goal_achieved ) return;

		global $wpdb;
		$logs_table = O100_Automation_DB::get_table_logs();
		$auto_table = O100_Automation_DB::get_table_name();

		// Find all active logs for this customer
		$active_logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.id, a.rules_and_goals FROM $logs_table l JOIN $auto_table a ON l.automation_id = a.id WHERE l.customer_id = %d AND l.status = 'active'",
			$context['customer_id']
		) );

		foreach ( $active_logs as $log ) {
			$rules = json_decode( $log->rules_and_goals, true );
			if ( ! empty( $rules['exit_goal'] ) && $rules['exit_goal'] === $goal_achieved ) {
				// Mark as exited by goal!
				$wpdb->update( $logs_table, array( 'status' => 'exited_by_goal' ), array( 'id' => $log->id ) );
			}
		}
	}

	// =========================================================================
	// CONDITIONS EVALUATION (10 fields)
	// =========================================================================

	/**
	 * Evaluate conditions JSON — AND logic (all must pass)
	 */
	private static function evaluate_conditions( $conditions_json, $context ) {
		$conditions = json_decode( $conditions_json, true );
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true; // No conditions = always run
		}

		foreach ( $conditions as $cond ) {
			$field = isset( $cond['field'] ) ? $cond['field'] : '';
			$op    = isset( $cond['op'] ) ? $cond['op'] : '==';
			$val   = isset( $cond['val'] ) ? $cond['val'] : '';

			$actual = self::get_context_value( $field, $context );

			// Special operators for tag/list/status/product checks
			if ( in_array( $field, array( 'customer_has_tag', 'customer_in_list', 'customer_status', 'purchased_product' ), true ) ) {
				if ( ! self::compare_special( $field, $actual, $op, $val, $context ) ) {
					return false;
				}
			} else {
				if ( ! self::compare( $actual, $op, $val ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Extract value for a condition field from context or database
	 */
	private static function get_context_value( $field, $context ) {
		global $wpdb;

		switch ( $field ) {
			case 'order_total':
				if ( ! empty( $context['order_id'] ) ) {
					$order = wc_get_order( $context['order_id'] );
					return $order ? floatval( $order->get_total() ) : 0;
				}
				return 0;

			case 'customer_total_spent':
				if ( ! empty( $context['customer_id'] ) && class_exists( 'O100_Customers_DB' ) ) {
					$table = O100_Customers_DB::get_table_customers();
					return floatval( $wpdb->get_var( $wpdb->prepare(
						"SELECT total_spent FROM $table WHERE id = %d", $context['customer_id']
					) ) );
				}
				return 0;

			case 'customer_total_orders':
				if ( ! empty( $context['customer_id'] ) && class_exists( 'O100_Customers_DB' ) ) {
					$table = O100_Customers_DB::get_table_customers();
					return intval( $wpdb->get_var( $wpdb->prepare(
						"SELECT total_orders FROM $table WHERE id = %d", $context['customer_id']
					) ) );
				}
				return 0;

			case 'customer_aov':
				if ( ! empty( $context['customer_id'] ) && class_exists( 'O100_Customers_DB' ) ) {
					$table = O100_Customers_DB::get_table_customers();
					$row   = $wpdb->get_row( $wpdb->prepare(
						"SELECT total_spent, total_orders FROM $table WHERE id = %d", $context['customer_id']
					) );
					if ( $row && $row->total_orders > 0 ) {
						return round( floatval( $row->total_spent ) / intval( $row->total_orders ), 2 );
					}
				}
				return 0;

			case 'customer_account_age':
				if ( ! empty( $context['user_id'] ) ) {
					$user = get_userdata( $context['user_id'] );
					if ( $user && ! empty( $user->user_registered ) ) {
						$registered = strtotime( $user->user_registered );
						$now        = current_time( 'timestamp' );
						return intval( floor( ( $now - $registered ) / DAY_IN_SECONDS ) );
					}
				}
				return 0;

			case 'customer_last_order_days':
				if ( ! empty( $context['customer_id'] ) && class_exists( 'O100_Customers_DB' ) ) {
					$table = O100_Customers_DB::get_table_customers();
					$last  = $wpdb->get_var( $wpdb->prepare(
						"SELECT last_order_date FROM $table WHERE id = %d", $context['customer_id']
					) );
					if ( $last ) {
						$last_ts = strtotime( $last );
						$now     = current_time( 'timestamp' );
						return intval( floor( ( $now - $last_ts ) / DAY_IN_SECONDS ) );
					}
				}
				return 9999; // No order ever = very large number

			// These return raw data for compare_special()
			case 'customer_has_tag':
			case 'customer_in_list':
			case 'customer_status':
			case 'purchased_product':
				return null; // Handled in compare_special

			default:
				return isset( $context[ $field ] ) ? $context[ $field ] : null;
		}
	}

	/**
	 * Standard numeric/string comparison
	 */
	private static function compare( $actual, $op, $expected ) {
		switch ( $op ) {
			case '==':
			case 'is':
				return $actual == $expected;
			case '!=':
			case 'is_not':
				return $actual != $expected;
			case '>':
				return floatval( $actual ) > floatval( $expected );
			case '<':
				return floatval( $actual ) < floatval( $expected );
			case '>=':
				return floatval( $actual ) >= floatval( $expected );
			case '<=':
				return floatval( $actual ) <= floatval( $expected );
			case 'contains':
				return strpos( (string) $actual, (string) $expected ) !== false;
		}
		return false;
	}

	/**
	 * Special comparison for tag/list/status/product fields
	 */
	private static function compare_special( $field, $actual, $op, $val, $context ) {
		global $wpdb;

		switch ( $field ) {
			case 'customer_has_tag':
				if ( empty( $context['customer_id'] ) || ! class_exists( 'O100_Customers_DB' ) ) return false;
				$rel_table = O100_Customers_DB::get_table_relationships();
				$target_ids = is_array( $val ) ? array_map( 'intval', $val ) : array( intval( $val ) );
				if ( empty( $target_ids ) ) return false;
				$in_clause = implode( ',', $target_ids );
				$exists    = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $rel_table WHERE customer_id = %d AND object_id IN ($in_clause) AND object_type = 'tag'",
					$context['customer_id']
				) );
				return ( $op === 'is' || $op === '==' || $op === 'includes' ) ? ( $exists > 0 ) : ( $exists == 0 );

			case 'customer_in_list':
				if ( empty( $context['customer_id'] ) || ! class_exists( 'O100_Customers_DB' ) ) return false;
				$rel_table = O100_Customers_DB::get_table_relationships();
				$target_ids = is_array( $val ) ? array_map( 'intval', $val ) : array( intval( $val ) );
				if ( empty( $target_ids ) ) return false;
				$in_clause = implode( ',', $target_ids );
				$exists    = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM $rel_table WHERE customer_id = %d AND object_id IN ($in_clause) AND object_type = 'list'",
					$context['customer_id']
				) );
				return ( $op === 'is' || $op === '==' || $op === 'includes' ) ? ( $exists > 0 ) : ( $exists == 0 );

			case 'customer_status':
				if ( empty( $context['customer_id'] ) || ! class_exists( 'O100_Customers_DB' ) ) return false;
				$table  = O100_Customers_DB::get_table_customers();
				$status = $wpdb->get_var( $wpdb->prepare(
					"SELECT status FROM $table WHERE id = %d", $context['customer_id']
				) );
				return ( $op === 'is' || $op === '==' ) ? ( $status === $val ) : ( $status !== $val );

			case 'purchased_product':
				if ( empty( $context['order_id'] ) ) return false;
				$order = wc_get_order( $context['order_id'] );
				if ( ! $order ) return false;
				$product_ids = array();
				foreach ( $order->get_items() as $item ) {
					$product_ids[] = $item->get_product_id();
					$variation_id  = $item->get_variation_id();
					if ( $variation_id ) {
						$product_ids[] = $variation_id;
					}
				}
				$target_ids = is_array( $val ) ? array_map( 'intval', $val ) : array( intval( $val ) );
				$found     = count( array_intersect( $target_ids, $product_ids ) ) > 0;
				return ( $op === 'includes' || $op === 'is' ) ? $found : ! $found;
		}

		return false;
	}

	// =========================================================================
	// ACTIONS EXECUTION (with Action Scheduler delay queue)
	// =========================================================================

	/**
	 * Execute actions chain — supports 'wait' action for delayed scheduling
	 */
	public static function execute_actions( $actions, &$context, $start_index = 0 ) {
		$actions = is_string( $actions ) ? json_decode( $actions, true ) : $actions;
		if ( empty( $actions ) || ! is_array( $actions ) ) return;

		global $wpdb;
		$logs_table = O100_Automation_DB::get_table_logs();

		// Insert log entry on first run if not already set
		if ( empty( $context['log_id'] ) && ! empty( $context['automation_id'] ) && ! empty( $context['customer_id'] ) ) {
			// Determine if there's any 'wait' action
			$has_wait = false;
			foreach ( $actions as $act ) {
				if ( isset( $act['type'] ) && $act['type'] === 'wait' ) {
					$has_wait = true;
					break;
				}
			}

			$wpdb->insert( $logs_table, array(
				'automation_id' => $context['automation_id'],
				'customer_id'   => $context['customer_id'],
				'status'        => $has_wait ? 'active' : 'completed',
				'run_at'        => current_time( 'mysql' ),
			) );
			$context['log_id'] = $wpdb->insert_id;
		}

		// Initialize action details array if not present
		if ( ! isset( $context['action_details'] ) ) {
			$context['action_details'] = array();
		}

		for ( $i = $start_index; $i < count( $actions ); $i++ ) {
			$act  = $actions[ $i ];
			$type = isset( $act['type'] ) ? $act['type'] : '';

			if ( $type === 'wait' ) {
				$minutes = isset( $act['val'] ) ? intval( $act['val'] ) : 0;
				$context['action_details'][] = array( 'type' => 'wait', 'val' => $minutes, 'status' => 'waiting' );

				if ( $minutes > 0 && function_exists( 'as_schedule_single_action' ) ) {
					// Schedule remaining actions after delay
					$remaining = array_slice( $actions, $i + 1 );
					if ( ! empty( $remaining ) ) {
						as_schedule_single_action(
							time() + ( $minutes * 60 ),
							'o100_execute_delayed_automation',
							array( json_encode( $remaining ), $context ),
							'o100_automations'
						);
					}
					// Update DB details before returning
					if ( ! empty( $context['log_id'] ) ) {
						$wpdb->update( $logs_table, array( 'details' => json_encode( $context['action_details'] ) ), array( 'id' => $context['log_id'] ) );
					}
					return; // Stop executing current chain
				}
				// If AS not available, skip wait and mark it completed
				$context['action_details'][count($context['action_details'])-1]['status'] = 'completed';
				continue; 
			}

			$val    = isset( $act['val'] ) ? $act['val'] : '';
			$config = isset( $act['config'] ) ? $act['config'] : array();
			
			$context = apply_filters( "o100_automation_action_{$type}", $context, $val, $config );
			
			// Assume success unless action filter sets otherwise
			$action_status = isset( $context['current_action_status'] ) ? $context['current_action_status'] : 'completed';
			$context['action_details'][] = array( 'type' => $type, 'val' => $val, 'status' => $action_status );
			// Reset for next action
			unset( $context['current_action_status'] );
		}

		// If we reach here, and log_id exists, mark it completed and save final details
		if ( ! empty( $context['log_id'] ) ) {
			$wpdb->update( $logs_table, array( 'status' => 'completed', 'details' => json_encode( $context['action_details'] ) ), array( 'id' => $context['log_id'] ) );
		}
	}

	/**
	 * Callback for Action Scheduler delayed execution
	 */
	public static function execute_delayed_actions( $actions_json, $context ) {
		// 1. Check if the log was marked as exited_by_goal
		if ( ! empty( $context['log_id'] ) ) {
			global $wpdb;
			$logs_table = O100_Automation_DB::get_table_logs();
			$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $logs_table WHERE id = %d", $context['log_id'] ) );
			
			if ( $status === 'exited_by_goal' ) {
				return; // Goal was achieved! Abort delayed actions.
			}
		}

		$actions = json_decode( $actions_json, true );
		if ( ! empty( $actions ) && is_array( $actions ) ) {
			self::execute_actions( $actions, $context, 0 );
		}
	}

	// =========================================================================
	// CRM HELPERS
	// =========================================================================

	/**
	 * Convert WP user_id → CRM customer_id
	 */
	private static function get_crm_customer_id( $wp_user_id, $email = '' ) {
		if ( ! class_exists( 'O100_Customers_DB' ) ) return 0;
		global $wpdb;
		$table = O100_Customers_DB::get_table_customers();
		
		if ( ! empty( $wp_user_id ) ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table WHERE wp_user_id = %d LIMIT 1", $wp_user_id
			) );
			if ( $id ) return $id;
		}

		if ( ! empty( $email ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table WHERE email = %s LIMIT 1", $email
			) );
		}

		return 0;
	}

	/**
	 * Get full CRM customer row by CRM customer_id
	 */
	private static function get_crm_customer( $customer_id ) {
		if ( empty( $customer_id ) || ! class_exists( 'O100_Customers_DB' ) ) return null;
		global $wpdb;
		$table = O100_Customers_DB::get_table_customers();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d LIMIT 1", $customer_id
		) );
	}
}

O100_Automation_Engine::init();
