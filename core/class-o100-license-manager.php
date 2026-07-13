<?php
/**
 * O100 License Manager
 * Wraps Freemius SDK and provides methods for feature limitation checking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_License_Manager {
	private static $instance = null;
	public $fs = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_freemius();
		add_action( 'admin_footer', array( $this, 'render_upgrade_modal' ) );
		add_action( 'admin_head', array( $this, 'fix_freemius_pricing_css' ) );
	}

	public function fix_freemius_pricing_css() {
		global $plugin_page;
		if ( false !== strpos( (string)$plugin_page, '-pricing' ) ) {
			echo '<style>
				/* Force Freemius pricing boxes to be wider and title to wrap */
				.fs-pricing-page h1, .fs-pricing h1 {
					font-size: 24px !important;
					white-space: normal !important;
					line-height: 1.4 !important;
					text-align: center !important;
					margin-bottom: 30px !important;
					max-width: 800px;
					margin-left: auto;
					margin-right: auto;
				}
				.fs-plans {
					display: flex !important;
					justify-content: center !important;
					gap: 30px !important;
				}
				.fs-plan {
					min-width: 280px !important;
					width: 100% !important;
					max-width: 350px !important;
				}
				.fs-plan .fs-features {
					min-height: 100px; /* Ensure boxes are reasonably tall even with no features */
				}
			</style>';
		}
	}

	private function init_freemius() {
		if ( ! function_exists( 'order100_fs' ) ) {
			function order100_fs() {
				global $order100_fs;
				if ( ! isset( $order100_fs ) ) {
					// Load Freemius SDK
					require_once dirname( __FILE__, 2 ) . '/freemius/start.php';
					
					// Initialize Freemius
					$order100_fs = fs_dynamic_init( array(
						'id'                  => '33984',
						'slug'                => 'order100-all-in-one-food-ordering-loyalty-rewards-marketing-auto',
						'type'                => 'plugin',
						'public_key'          => 'pk_b703048bab7ee7a65056ca6d22167',
						'is_premium'          => true,
						'premium_suffix'      => 'Premium',
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'is_org_compliant'    => true,
						'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
						'menu'                => array(
							'slug'           => 'order100',
							'first-path'     => 'admin.php?page=order100',
							'account'        => true,
							'contact'        => true,
							'support'        => false,
						),
					) );
				}
				return $order100_fs;
			}
			$this->fs = order100_fs();
			
			// Optional: Hook into freemius to add custom logic on activation, etc.
			$this->fs->add_action('after_uninstall', array( $this, 'uninstall_cleanup' ) );
		}
	}

	public function uninstall_cleanup() {
		// Cleanup options when plugin is deleted and user chooses to delete data
	}

	/**
	 * Check if user is premium (paying or active free trial).
	 */
	public function is_premium() {
		if ( $this->fs && $this->fs->can_use_premium_code() ) {
			return true;
		}
		
		// For local testing without a license, we might mock it if O100_ENV is set to mock-pro
		if ( defined( 'O100_ENV' ) && O100_ENV === 'mock-pro' ) {
			return true;
		}

		return false;
	}

	/**
	 * Render a beautiful upgrade notice UI box.
	 */
	public function render_upgrade_notice( $feature_name, $message = '' ) {
		if ( empty( $message ) ) {
			$message = sprintf( __( 'To use %s, please upgrade to Order100 Pro.', 'order100' ), esc_html( $feature_name ) );
		}
		$upgrade_url = $this->fs ? $this->fs->get_upgrade_url() : '#';
		
		echo '<div class="o100-upgrade-notice" style="position:relative; overflow:hidden; padding:40px 30px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border:1px solid #e2e8f0; border-radius:16px; text-align:center; margin: 24px 0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">';
		echo '<div style="position:absolute; top:0; left:0; right:0; height:4px; background: linear-gradient(90deg, #F59322, #f59e0b, #fbbf24);"></div>';
		echo '<div style="display:inline-flex; align-items:center; justify-content:center; width:56px; height:56px; border-radius:50%; background:#fff7ed; color:#F59322; margin-bottom:20px;"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg></div>';
		echo '<h3 style="color:#0f172a; margin:0 0 12px 0; font-size:20px; font-weight:700;">' . esc_html( $feature_name ) . ' <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; background:linear-gradient(135deg, #F59322, #c2410c); color:#fff; padding:3px 8px; border-radius:12px; vertical-align:middle; margin-left:6px; box-shadow:0 2px 4px rgba(217,119,6,0.2);">PRO</span></h3>';
		echo '<p style="color:#475569; font-size:15px; margin:0 auto 28px auto; line-height:1.6; max-width:500px;">' . esc_html( $message ) . '</p>';
		echo '<a href="' . esc_url( $upgrade_url ) . '" style="display:inline-block; background: #0f172a; color: #fff; text-decoration: none; padding: 12px 28px; font-size: 15px; font-weight: 600; border-radius: 8px; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);" onmouseover="this.style.background=\'#1e293b\'" onmouseout="this.style.background=\'#0f172a\'">Upgrade to Unlock</a>';
		echo '</div>';
	}

	/**
	 * Return the HTML for a small inline [PRO] badge.
	 */
	public function get_pro_badge( $tooltip = '' ) {
		$tooltip_attr = ! empty( $tooltip ) ? ' title="' . esc_attr( $tooltip ) . '"' : '';
		return '<span class="o100-pro-badge"' . $tooltip_attr . ' style="display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; background:linear-gradient(135deg, #F59322, #c2410c); color:#fff; padding:2px 6px; border-radius:10px; vertical-align:middle; margin-left:6px; box-shadow:0 2px 4px rgba(217,119,6,0.2); cursor:pointer;">PRO</span>';
	}

	/**
	 * Render a hidden global upgrade modal that can be triggered via JS.
	 */
	public function render_upgrade_modal() {
		// Only render if not premium
		if ( $this->is_premium() ) return;
		
		$upgrade_url = $this->fs ? $this->fs->get_upgrade_url() : '#';
		
		// The Modal HTML
		echo '<div id="o100-pro-upgrade-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); z-index:999999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">';
		echo '<div class="o100-modal-content" style="position:relative; width:90%; max-width:480px; background:#fff; border-radius:16px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); overflow:hidden; animation:o100PopIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);">';
		// Top border
		echo '<div style="position:absolute; top:0; left:0; right:0; height:4px; background: linear-gradient(90deg, #F59322, #f59e0b, #fbbf24);"></div>';
		// Close button
		echo '<button onclick="document.getElementById(\'o100-pro-upgrade-modal\').style.display=\'none\';" style="position:absolute; top:12px; right:12px; background:none; border:none; font-size:24px; color:#94a3b8; cursor:pointer; line-height:1;">&times;</button>';
		
		echo '<div style="padding:40px 30px; text-align:center;">';
		
		// Crown Icon
		echo '<div style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:50%; background:#fffbeb; margin-bottom:24px; box-shadow: 0 4px 14px 0 rgba(251, 191, 36, 0.2);">';
		echo '<svg width="32" height="32" viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 20h20v2H2z"/><path d="M12 15l-4-3-4 5V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12l-4-5-4 3z"/></svg>';
		echo '</div>';
		
		// Title
		echo '<h3 id="o100-pro-modal-title" style="color:#0f172a; margin:0 0 16px 0; font-size:22px; font-weight:800; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">Upgrade to Access the Full Potential of Order100</h3>';
		
		// Description
		echo '<p id="o100-pro-modal-desc" style="color:#64748b; font-size:15px; margin:0 auto 32px auto; line-height:1.6; max-width: 400px;">Unlock limitless marketing possibilities. Upgrade now to exceed your limit and access valuable tools that fuel your business.</p>';
		
		// Button
		echo '<a href="' . esc_url( $upgrade_url ) . '" style="display:inline-block; background: #7c3aed; color: #fff; text-decoration: none; padding: 14px 36px; font-size: 16px; font-weight: 600; border-radius: 8px; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(124, 58, 237, 0.3);" onmouseover="this.style.background=\'#6d28d9\'" onmouseout="this.style.background=\'#7c3aed\'">Upgrade Now</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		
		// JS logic for showing modal and animation
		echo '<style>@keyframes o100PopIn { 0% { opacity: 0; transform: scale(0.95); } 100% { opacity: 1; transform: scale(1); } }</style>';
		echo '<script>
		function o100ShowProModal(featureName, featureDesc) {
			var modal = document.getElementById("o100-pro-upgrade-modal");
			if(!modal) return;
			// For the new design, we keep the static title and just use featureDesc
			if(featureDesc) {
				document.getElementById("o100-pro-modal-desc").innerText = featureDesc;
			}
			modal.style.display = "flex";
		}
		
		// Auto-bind to elements with data-pro-feature attribute
		document.addEventListener("DOMContentLoaded", function() {
			var proElements = document.querySelectorAll("[data-pro-feature]");
			for(var i=0; i<proElements.length; i++) {
				proElements[i].addEventListener("click", function(e) {
					if (!this.hasAttribute("data-allow-default")) {
						e.preventDefault();
						e.stopPropagation();
					}
					var name = this.getAttribute("data-pro-feature");
					var desc = this.getAttribute("data-pro-desc") || "Upgrade to Order100 Pro to unlock this feature and scale your business.";
					o100ShowProModal(name, desc);
				});
			}
		});
		</script>';
	}
}

/**
 * Helper function to quickly access License Manager
 */
function O100_License() {
	return O100_License_Manager::instance();
}
