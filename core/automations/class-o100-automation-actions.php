<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Automation_Actions {

	public static function init() {
		add_filter( 'o100_automation_action_send_email', array( __CLASS__, 'action_send_email' ), 10, 3 );
		add_filter( 'o100_automation_action_send_sms', array( __CLASS__, 'action_send_sms' ), 10, 3 );
		add_filter( 'o100_automation_action_give_coupon_existing', array( __CLASS__, 'action_give_coupon_existing' ), 10, 3 );
		add_filter( 'o100_automation_action_give_coupon_custom', array( __CLASS__, 'action_give_coupon_custom' ), 10, 3 );
		add_filter( 'o100_automation_action_add_tag', array( __CLASS__, 'action_add_tag' ), 10, 3 );
	}

	// =========================================================================
	// ACTION: SEND EMAIL (via O100ne Template system)
	// =========================================================================

	/**
	 * Send email using O100ne Template system, with wp_mail fallback
	 *
	 * @param string|int $template_id  Post ID of the O100ne template
	 * @param array      $context      Trigger context (email, order_id, etc.)
	 * @param array      $config       Additional action config
	 */
	public static function action_send_email( $context, $template_id, $config = array() ) {
		if ( empty( $context['email'] ) ) return $context;

		$to = $context['email'];
		$subject_fallback = ! empty( $config['subject'] ) ? $config['subject'] : 'Notification from Order100';

		$actual_template_id = ! empty( $template_id ) ? $template_id : ( ! empty( $config['visual_template_id'] ) ? $config['visual_template_id'] : 0 );

		// Try to use the O100 email template system
		if ( ! empty( $actual_template_id ) && class_exists( 'Order100\\Notification\\Engine\\Models\\TemplateModel' ) ) {
			$template_data = \Order100\Notification\Engine\Models\TemplateModel::find_by_id( intval( $actual_template_id ) );
			if ( $template_data && ! empty( $template_data['id'] ) ) {
				// Instantiate template by its registered name
				$template_name = ! empty( $template_data['name'] ) ? $template_data['name'] : '';
				if ( ! empty( $template_name ) ) {
					$template_class = new \Order100\Notification\Engine\O100neTemplate( $template_name );
					$render_data = array();
					if ( ! empty( $context['order_id'] ) ) {
						$render_data['order'] = wc_get_order( $context['order_id'] );
					}
					// Add customer context if available
					if ( ! empty( $context['customer_id'] ) ) {
					    $render_data['customer_id'] = $context['customer_id'];
					}
					if ( ! empty( $context['coupon_code'] ) ) {
						$render_data['coupon_code'] = $context['coupon_code'];
						$render_data['coupon_value'] = ! empty( $context['coupon_value'] ) ? $context['coupon_value'] : '';
					}

					$html = $template_class->get_content( $render_data );
					$subject = ! empty( $config['subject'] ) ? $config['subject'] : ucwords( str_replace( array( '_', '-' ), ' ', $template_name ) );
					$headers = array( 'Content-Type: text/html; charset=UTF-8' );
					wp_mail( $to, $subject, $html, $headers );
					return $context;
				}
			}
		}

		$message_fallback = ! empty( $config['message'] ) ? $config['message'] : 'You have a new notification.';
		$replacements = array(
			'[o100_customer_name]' => ! empty( $context['first_name'] ) ? $context['first_name'] : '',
			'[o100_order_id]'      => ! empty( $context['order_id'] ) ? $context['order_id'] : '',
			'[o100_store_name]'    => get_bloginfo( 'name' ),
			'[o100_coupon_code]'   => ! empty( $context['coupon_code'] ) ? $context['coupon_code'] : '',
			'[o100_coupon_value]'  => ! empty( $context['coupon_value'] ) ? $context['coupon_value'] : '',
		);
		
		$subject_fallback = str_replace( array_keys( $replacements ), array_values( $replacements ), $subject_fallback );
		$message_fallback = str_replace( array_keys( $replacements ), array_values( $replacements ), $message_fallback );

		// Fallback: basic wp_mail
		wp_mail( $to, $subject_fallback, $message_fallback );
		return $context;
	}

	// =========================================================================
	// ACTION: SEND SMS (via O100_SMS_Engine)
	// =========================================================================

	/**
	 * Send SMS with placeholder replacement
	 *
	 * @param string $message  SMS body text with optional placeholders
	 * @param array  $context  Trigger context
	 * @param array  $config   Additional action config
	 */
	public static function action_send_sms( $context, $message, $config = array() ) {
		$actual_message = ! empty( $message ) ? $message : ( ! empty( $config['message'] ) ? $config['message'] : '' );
		if ( empty( $actual_message ) ) return $context;
		if ( empty( $context['phone'] ) && empty( $context['user_id'] ) ) return $context;

		$phone = ! empty( $context['phone'] ) ? $context['phone'] : '';

		// Try to get phone from CRM customer record
		if ( empty( $phone ) && ! empty( $context['customer_id'] ) && class_exists( 'O100_Customers_DB' ) ) {
			global $wpdb;
			$table = O100_Customers_DB::get_table_customers();
			$phone = $wpdb->get_var( $wpdb->prepare(
				"SELECT phone FROM $table WHERE id = %d", intval( $context['customer_id'] )
			) );
		}

		// Fallback: try WP user meta
		if ( empty( $phone ) && ! empty( $context['user_id'] ) ) {
			$phone = get_user_meta( $context['user_id'], 'billing_phone', true );
		}

		if ( empty( $phone ) ) return $context;

		// Replace placeholders in message
		$replacements = array(
			'[o100_customer_name]' => ! empty( $context['first_name'] ) ? $context['first_name'] : '',
			'[o100_order_id]'      => ! empty( $context['order_id'] ) ? $context['order_id'] : '',
			'[o100_store_name]'    => get_bloginfo( 'name' ),
			'[o100_coupon_code]'   => ! empty( $context['coupon_code'] ) ? $context['coupon_code'] : '',
			'[o100_coupon_value]'  => ! empty( $context['coupon_value'] ) ? $context['coupon_value'] : '',
		);
		$actual_message = str_replace( array_keys( $replacements ), array_values( $replacements ), $actual_message );

		if ( class_exists( 'O100_SMS_Engine' ) ) {
			O100_SMS_Engine::send_custom_sms( $phone, $actual_message );
		}
		return $context;
	}

	// =========================================================================
	// ACTION: GIVE EXISTING COUPON (from Promotions DB)
	// =========================================================================

	/**
	 * Look up an existing promotion and email its coupon code to the customer
	 *
	 * @param string|int $promo_id  ID in the o100_promotions table
	 * @param array      $context   Trigger context
	 * @param array      $config    Additional action config
	 */
	public static function action_give_coupon_existing( $context, $promo_id, $config = array() ) {
		$actual_promo_id = ! empty( $promo_id ) ? $promo_id : ( ! empty( $config['promo_id'] ) ? $config['promo_id'] : 0 );
		if ( empty( $actual_promo_id ) ) return $context;

		if ( class_exists( 'O100_Promotions_DB' ) ) {
			$promo = O100_Promotions_DB::get( intval( $actual_promo_id ) );
			if ( $promo && ! empty( $promo['promo_code'] ) ) {
				$context['coupon_code']  = $promo['promo_code'];
				$context['coupon_value'] = ! empty( $promo['discount_amount'] ) ? floatval( $promo['discount_amount'] ) : 0;
			}
		}
		return $context;
	}

	// =========================================================================
	// ACTION: GIVE CUSTOM COUPON (via O100_Reward_Gateway)
	// =========================================================================

	/**
	 * Generate a one-time coupon via the Reward Gateway and store code in context
	 *
	 * @param string $val      Unused (config-driven action)
	 * @param array  $context  Trigger context
	 * @param array  $config   Action config: discount_type, discount_value, expiry_days, usage_limit
	 */
	public static function action_give_coupon_custom( $context, $val, $config = array() ) {
		if ( empty( $context['user_id'] ) ) return $context;

		$discount_type  = ! empty( $config['discount_type'] ) ? $config['discount_type'] : 'percent';
		$discount_value = ! empty( $config['discount_value'] ) ? floatval( $config['discount_value'] ) : 10;
		$expiry_days    = ! empty( $config['expiry_days'] ) ? intval( $config['expiry_days'] ) : 30;
		$usage_limit    = ! empty( $config['usage_limit'] ) ? intval( $config['usage_limit'] ) : 1;

		if ( class_exists( 'O100_Reward_Gateway' ) ) {
			$result = O100_Reward_Gateway::award_coupon( array(
				'user_id'        => $context['user_id'],
				'source'         => 'automation',
				'source_id'      => ! empty( $context['automation_id'] ) ? $context['automation_id'] : 0,
				'discount_type'  => $discount_type === 'percent' ? 'percent' : 'fixed_cart',
				'discount_value' => $discount_value,
				'expiry_days'    => $expiry_days,
				'usage_limit'    => $usage_limit,
				'note'           => 'Auto-generated by Automation Engine',
			) );

			// Store generated coupon in context for subsequent actions (e.g., send_email, send_sms)
			if ( ! empty( $result['coupon_code'] ) ) {
				$context['coupon_code']  = $result['coupon_code'];
				$context['coupon_value'] = $discount_value . ( $discount_type === 'percent' ? '%' : '$' );
			}
		}
		return $context;
	}

	// =========================================================================
	// ACTION: ADD TAG (via O100_Customers_DB)
	// =========================================================================

	/**
	 * Assign a CRM tag to the customer
	 *
	 * @param string|int $tag_id   Tag ID to assign
	 * @param array      $context  Trigger context (needs customer_id)
	 * @param array      $config   Additional action config
	 */
	public static function action_add_tag( $context, $tag_id, $config = array() ) {
		$actual_tag_id = ! empty( $tag_id ) ? $tag_id : ( ! empty( $config['tag_id'] ) ? $config['tag_id'] : 0 );
		$customer_id = ! empty( $context['customer_id'] ) ? intval( $context['customer_id'] ) : 0;
		if ( empty( $customer_id ) || empty( $actual_tag_id ) ) return $context;

		if ( class_exists( 'O100_Customers_DB' ) ) {
			O100_Customers_DB::assign_tag_to_customer( $customer_id, intval( $actual_tag_id ) );
		}
		return $context;
	}
}

O100_Automation_Actions::init();
