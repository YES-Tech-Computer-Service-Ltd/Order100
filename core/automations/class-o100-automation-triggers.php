<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class O100_Automation_Triggers {

	public static function init() {}

	/**
	 * Get trigger groups for <optgroup> rendering
	 */
	public static function get_groups() {
		return array(
			'orders'       => 'Orders',
			'customers'    => 'Customers',
			'reservations' => 'Reservations',
			'loyalty'      => 'Loyalty',
			'scheduled'    => 'Scheduled',
		);
	}

	/**
	 * Get all registered triggers
	 */
	public static function get_triggers() {
		return array(
			// === Orders ===
			'new_order'          => array( 'label' => 'New Order Placed', 'description' => 'Triggers when a new order is placed.', 'group' => 'orders' ),
			'order_processing'   => array( 'label' => 'Order is Being Prepared', 'description' => 'Triggers when order status changes to processing.', 'group' => 'orders' ),
			'order_completed'    => array( 'label' => 'Order Completed', 'description' => 'Triggers when an order is completed.', 'group' => 'orders' ),
			'order_failed'       => array( 'label' => 'Payment Failed', 'description' => 'Triggers when payment fails.', 'group' => 'orders' ),
			'order_cancelled'    => array( 'label' => 'Order Cancelled', 'description' => 'Triggers when an order is cancelled.', 'group' => 'orders' ),
			'order_refunded'     => array( 'label' => 'Order Refunded', 'description' => 'Triggers when an order is refunded.', 'group' => 'orders' ),
			'abandoned_cart'     => array( 'label' => 'Cart Abandoned', 'description' => 'Triggers when a cart is abandoned.', 'group' => 'orders' ),

			// === Customers ===
			'customer_created'        => array( 'label' => 'New Customer Added', 'description' => 'Triggers when a new customer enters the CRM.', 'group' => 'customers' ),
			'customer_tag_added'      => array( 'label' => 'Tag Added to Customer', 'description' => 'Triggers when a tag is assigned to a customer.', 'group' => 'customers' ),
			'customer_tag_removed'    => array( 'label' => 'Tag Removed from Customer', 'description' => 'Triggers when a tag is removed from a customer.', 'group' => 'customers' ),
			'customer_list_added'     => array( 'label' => 'Customer Joined a List', 'description' => 'Triggers when a customer is added to a mailing list.', 'group' => 'customers' ),
			'customer_list_removed'   => array( 'label' => 'Customer Left a List', 'description' => 'Triggers when a customer is removed from a mailing list.', 'group' => 'customers' ),
			'customer_status_changed' => array( 'label' => 'Subscription Status Changed', 'description' => 'Triggers when customer status changes (subscribed/unsubscribed/pending).', 'group' => 'customers' ),

			// === Reservations ===
			'new_reservation'         => array( 'label' => 'New Reservation', 'description' => 'Triggers when a new reservation is created.', 'group' => 'reservations' ),
			'reservation_confirmed'   => array( 'label' => 'Reservation Confirmed', 'description' => 'Triggers when a reservation is confirmed.', 'group' => 'reservations' ),
			'reservation_cancelled'   => array( 'label' => 'Reservation Cancelled', 'description' => 'Triggers when a reservation is cancelled.', 'group' => 'reservations' ),
			'reservation_no_show'     => array( 'label' => 'Customer No-Show', 'description' => 'Triggers when a customer does not show up for reservation.', 'group' => 'reservations' ),

			// === Loyalty ===
			'loyalty_level_changed'        => array( 'label' => 'VIP Level Changed', 'description' => 'Triggers when a customer loyalty level changes.', 'group' => 'loyalty' ),
			'loyalty_points_earned'        => array( 'label' => 'Points Earned', 'description' => 'Triggers when a customer earns loyalty points.', 'group' => 'loyalty' ),
			'loyalty_reward_expiring'      => array( 'label' => 'Reward Expiring Soon', 'description' => 'Triggers when a loyalty reward is about to expire.', 'group' => 'loyalty' ),
			'loyalty_punch_card_completed' => array( 'label' => 'Punch Card Completed', 'description' => 'Triggers when a punch card is fully redeemed.', 'group' => 'loyalty' ),

			// === Scheduled ===
			'customer_inactive'     => array( 'label' => 'Customer Inactive', 'description' => 'Triggers daily for customers who have been inactive for the specified number of days.', 'group' => 'scheduled' ),
			'customer_birthday'     => array( 'label' => 'Customer Birthday', 'description' => 'Triggers daily for customers whose birthday is coming up.', 'group' => 'scheduled' ),
			'customer_anniversary'  => array( 'label' => 'Account Anniversary', 'description' => 'Triggers daily for customers celebrating their registration anniversary.', 'group' => 'scheduled' ),
			'user_login'            => array( 'label' => 'Customer Logged In', 'description' => 'Triggers when a customer logs in.', 'group' => 'scheduled' ),
		);
	}
}

O100_Automation_Triggers::init();
