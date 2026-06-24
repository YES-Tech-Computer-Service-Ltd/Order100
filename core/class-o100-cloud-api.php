<?php
/**
 * O100 Cloud API Gateway
 * Handles requests to central server for premium features and asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class O100_Cloud_API {
	private static $instance = null;
	private $api_url = 'https://api.order100.com/v1'; // TODO: Update with real API URL

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Determines if we should fetch from local development sandbox or remote cloud.
	 */
	public function is_local_dev() {
		return defined( 'O100_ENV' ) && O100_ENV === 'development';
	}

	/**
	 * Fetch a premium component or algorithm.
	 * 
	 * @param string $endpoint The API endpoint (e.g., 'loyalty/calculate_points')
	 * @param array $payload The data payload
	 * @return mixed
	 */
	public function request( $endpoint, $payload = array() ) {
		if ( ! O100_License()->is_premium() ) {
			return new WP_Error( 'o100_not_premium', 'Premium license required for this API call.' );
		}

		if ( $this->is_local_dev() ) {
			return $this->mock_local_request( $endpoint, $payload );
		}

		// Real Cloud API request logic
		$url = $this->api_url . '/' . ltrim( $endpoint, '/' );
		
		$args = array(
			'body'    => wp_json_encode( $payload ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'X-Order100-Domain' => $_SERVER['HTTP_HOST'] ?? '',
				// 'X-Order100-Signature' => O100_License()->get_signature() // TODO: Implement signature
			),
			'timeout' => 15,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Get URL for remote JavaScript/CSS asset injection.
	 */
	public function get_asset_url( $asset_name ) {
		if ( $this->is_local_dev() ) {
			return O100_PLUGIN_URL . 'cloud-assets/' . $asset_name;
		}
		
		// Real cloud URL requiring a signed token
		// $token = O100_License()->generate_asset_token();
		return 'https://assets.order100.com/v1/' . $asset_name;
	}

	/**
	 * Mocks the API response by looking for a local file in /cloud-assets/mocks/ during development
	 */
	private function mock_local_request( $endpoint, $payload ) {
		$mock_file = O100_PLUGIN_DIR . 'cloud-assets/mocks/' . str_replace( '/', '_', $endpoint ) . '.php';
		if ( file_exists( $mock_file ) ) {
			return include $mock_file;
		}
		return array( 'success' => true, 'mocked' => true, 'endpoint' => $endpoint );
	}
}

/**
 * Helper function to quickly access Cloud API
 */
function O100_Cloud() {
	return O100_Cloud_API::instance();
}
