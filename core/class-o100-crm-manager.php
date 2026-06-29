<?php
/**
 * CRM Manager
 *
 * Handles the loading and initialization of CRM adapters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_CRM_Manager {

	/**
	 * available adapters
	 *
	 * @var array
	 */
	private $adapters = array();

	/**
	 * Active adapter
	 *
	 * @var O100_CRM_Adapter_Interface|null
	 */
	private $active_adapter = null;

	/**
	 * Settings options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->options = get_option( 'o100_options' );
		$this->load_adapters();
		$this->init_active_adapter();

		// Hook into settings save to trigger field check
		add_action( 'cmb2_save_options-page_fields_o100_options', array( $this, 'on_settings_save' ), 10, 2 );
	}

	/**
	 * Load available CRM adapters.
	 */
	private function load_adapters() {
		// Include interface and adapters
		require_once O100_PATH . 'includes/crm/interface-crm-adapter.php';
		require_once O100_PATH . 'includes/crm/class-fluent-crm-adapter.php';

		// Register adapters
		$this->register_adapter( new O100_Fluent_CRM_Adapter() );
		
		// Future adapters can be registered here
	}

	/**
	 * Register a CRM adapter.
	 *
	 * @param O100_CRM_Adapter_Interface $adapter
	 */
	public function register_adapter( O100_CRM_Adapter_Interface $adapter ) {
		$this->adapters[ $adapter->get_slug() ] = $adapter;
	}

	/**
	 * Initialize the active adapter based on settings.
	 */
	private function init_active_adapter() {
		if ( empty( $this->options['o100_enable_crm'] ) ) {
			return;
		}

		$selected_crm = isset( $this->options['o100_crm_provider'] ) ? $this->options['o100_crm_provider'] : 'fluent_crm';

		if ( isset( $this->adapters[ $selected_crm ] ) && $this->adapters[ $selected_crm ]->is_active() ) {
			$this->active_adapter = $this->adapters[ $selected_crm ];
			$this->active_adapter->init();
		}
	}

	/**
	 * Get list of available adapters for settings.
	 *
	 * @return array
	 */
	public function get_adapter_options() {
		$options = array();
		foreach ( $this->adapters as $slug => $adapter ) {
			$options[ $slug ] = $adapter->get_name();
		}
		return $options;
	}

	/**
	 * Triggered when settings are saved.
	 * Checks if CRM is enabled and triggers field check.
	 *
	 * @param int   $object_id
	 * @param array $updated_options
	 */
	public function on_settings_save( $object_id, $updated_options ) {
		// Reload options
		$this->options = $updated_options; // Passed directly or fetch again
        // Actually $updated_options might be the field args, let's just re-get option to be safe or use what's passed if it is the values
        // CMB2 action passes ( $object_id, $cmb_id ) usually, wait.
        // The action is 'cmb2_save_options-page_fields_{$option_key}'
        // Args: $object_id, $updated_args. 
        // NOTE: It seems CMB2 passes $object_id (which is the option key usually) and $updated_fields (array of field ids updated).
        // It does NOT pass the new values directly in a usable way easily without getting them.
        
        $options = get_option( 'o100_options' );

		if ( isset( $options['o100_enable_crm'] ) && $options['o100_enable_crm'] === 'on' ) {
			$selected_crm = isset( $options['o100_crm_provider'] ) ? $options['o100_crm_provider'] : 'fluent_crm';

			if ( isset( $this->adapters[ $selected_crm ] ) && $this->adapters[ $selected_crm ]->is_active() ) {
				$this->adapters[ $selected_crm ]->ensure_custom_fields();
			}
		}
	}
}

