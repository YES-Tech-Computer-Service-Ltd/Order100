<?php
/**
 * Order100 License & Quota Manager
 *
 * Handles Freemium limits, feature toggles, and upgrade prompts.
 * Designed to be dynamically updated via API in the future.
 *
 * @package Order100
 */

defined( 'ABSPATH' ) or die();

class O100_License_Manager {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Cached quotas array.
	 *
	 * @var array
	 */
	protected $quotas = array();

	/**
	 * Default fallback limits for the Freemium version.
	 *
	 * @var array
	 */
	protected $default_limits = array(
		'discount_rules_limit'  => 1,      // Max active complex discount rules
		'visible_crm_customers' => 100,    // Max fully visible customers in CRM
		'punch_card_ui'         => false,  // Is visual punch card enabled?
		'item_based_rewards'    => false,  // Can points be redeemed for products?
		'ai_features'           => false,  // Is AI Copywriter / Menu Generator enabled?
		'automation_triggers'   => false,  // Are advanced automation hooks enabled?
	);

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_quotas();
	}

	/**
	 * Initialize and load the quotas from the database.
	 */
	private function init_quotas() {
		$saved_quotas = get_option( 'o100_freemium_limits', array() );

		if ( empty( $saved_quotas ) || ! is_array( $saved_quotas ) ) {
			// Save default limits on first run
			update_option( 'o100_freemium_limits', $this->default_limits );
			$this->quotas = $this->default_limits;
		} else {
			// Merge saved with defaults to ensure all keys exist
			$this->quotas = wp_parse_args( $saved_quotas, $this->default_limits );
		}
	}

	/**
	 * Check if the current installation has an active PRO license.
	 * 
	 * @return bool
	 */
	public function is_pro() {
		// @todo: Implement real license verification logic later.
		// For now, we simulate Free mode unless a filter overrides it.
		return apply_filters( 'o100_is_pro_license_active', false );
	}

	/**
	 * Get the numeric or boolean limit for a specific feature.
	 *
	 * @param string $feature_key The key of the feature (e.g., 'discount_rules_limit').
	 * @return mixed The limit value, or null if key does not exist.
	 */
	public function get_quota( $feature_key ) {
		// If PRO, return infinite limits (or true for booleans)
		if ( $this->is_pro() ) {
			if ( is_bool( $this->default_limits[ $feature_key ] ) ) {
				return true;
			}
			return 999999999; 
		}

		return isset( $this->quotas[ $feature_key ] ) ? $this->quotas[ $feature_key ] : null;
	}

	/**
	 * Check if a specific usage amount is within the allowed limits.
	 *
	 * @param string $feature_key The key of the feature.
	 * @param int    $current_usage The current amount already used/active.
	 * @return bool True if within limits, False if limit exceeded.
	 */
	public function check_limit( $feature_key, $current_usage = 0 ) {
		if ( $this->is_pro() ) {
			return true;
		}

		$limit = $this->get_quota( $feature_key );

		if ( is_bool( $limit ) ) {
			return $limit; // e.g. ai_features is true or false
		}

		return $current_usage < $limit;
	}

	/**
	 * Helper function to render a unified "Upgrade to PRO" popup or inline banner.
	 *
	 * @param string $feature_name The human-readable name of the feature being restricted.
	 * @param string $display_type 'modal' or 'banner'.
	 */
	public function render_upgrade_prompt( $feature_name, $display_type = 'modal' ) {
		if ( 'banner' === $display_type ) {
			?>
			<div class="o100-upgrade-banner" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #bbf7d0; padding: 16px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
				<div>
					<h4 style="margin: 0 0 8px 0; color: #166534; font-size: 16px; display: flex; align-items: center; gap: 8px;">
						<span class="dashicons dashicons-lock" style="color: #22c55e;"></span>
						Unlock <?php echo esc_html( $feature_name ); ?>
					</h4>
					<p style="margin: 0; color: #15803d; font-size: 14px;">Upgrade to Order100 Pro to scale your restaurant marketing without limits.</p>
				</div>
				<a href="https://order100.io/pricing" target="_blank" class="button button-primary" style="background: #22c55e; border-color: #16a34a; color: white;">Upgrade Now</a>
			</div>
			<?php
		} else {
			// Render Modal (Hidden by default, triggered via JS)
			?>
			<div id="o100-upgrade-modal-<?php echo esc_attr( sanitize_title( $feature_name ) ); ?>" class="o100-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999; justify-content: center; align-items: center;">
				<div class="o100-modal-content" style="background: #fff; width: 400px; border-radius: 12px; padding: 32px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
					<span class="dashicons dashicons-lock" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 16px;"></span>
					<h2 style="margin: 0 0 12px; font-size: 22px;">Upgrade to Unlock</h2>
					<p style="color: #64748b; margin-bottom: 24px; line-height: 1.5;">You've reached the free limit. Upgrade to Order100 Pro to unlock <strong><?php echo esc_html( $feature_name ); ?></strong> and grow your sales.</p>
					<a href="https://order100.io/pricing" target="_blank" class="button button-primary button-large" style="width: 100%; padding: 8px 0; font-size: 16px;">Upgrade to Pro</a>
					<button type="button" class="button-link o100-modal-close" style="margin-top: 16px; color: #94a3b8; text-decoration: none;" onclick="jQuery(this).closest('.o100-modal-overlay').hide();">Maybe later</button>
				</div>
			</div>
			<?php
		}
	}
}

/**
 * Global accessor function for the License Manager.
 *
 * @return O100_License_Manager
 */
function O100_License() {
	return O100_License_Manager::get_instance();
}




// TS: 20260402232730
