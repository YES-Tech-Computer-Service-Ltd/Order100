<?php
/**
 * CRM Adapter Interface
 *
 * Defines the contract for CRM integrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface O100_CRM_Adapter_Interface {

	/**
	 * Get the unique slug for this CRM adapter.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Get the display name for this CRM adapter.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Check if the CRM plugin is active and available.
	 *
	 * @return bool
	 */
	public function is_active();

	/**
	 * Initialize the adapter (e.g., hook into actions).
	 */
	public function init();

	/**
	 * Sync order data to the CRM.
	 *
	 * @param int $order_id The WooCommerce Order ID.
	 * @return void
	 */
	public function sync_order( $order_id );

	/**
	 * Ensure required custom fields exist in the CRM.
	 * Should check and create fields if they are missing.
	 *
	 * @return void
	 */
	public function ensure_custom_fields();
}

