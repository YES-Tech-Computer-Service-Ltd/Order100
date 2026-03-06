<?php

namespace Order100\Notification\Engine\Controllers;

use Order100\Notification\Engine\Abstracts\BaseController;
use Order100\Notification\Engine\Models\AddonModel;
use Order100\Notification\Engine\Models\SettingModel;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Settings Controller
 * * @method static AddonController get_instance()
 */
class AddonController extends BaseController {
    use SingletonTrait;

    private $model = null;

    protected function __construct() {
        $this->model = SettingModel::get_instance();
        $this->init_hooks();
    }

    protected function permission_callback_admin_only() {
        return current_user_can( 'activate_plugins' );
    }

    protected function init_hooks() {
        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/addons',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_addons' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/addons/activate',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_activate_addon' ],
                    'permission_callback' => [ $this, 'permission_callback_admin_only' ],
                ],
            ]
        );
        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/addons/deactivate',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_deactivate_addon' ],
                    'permission_callback' => [ $this, 'permission_callback_admin_only' ],
                ],
            ]
        );
    }

    public function exec_get_addons( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_addons' ], $request );
    }

    public function exec_activate_addon( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'activate_addon' ], $request );
    }

    public function exec_deactivate_addon( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'deactivate_addon' ], $request );
    }

    public function get_addons() {
        return array_values( AddonModel::get_all() );
    }

    public function activate_addon( \WP_REST_Request $request ) {
        $addon = $request->get_param( 'addon' );

        if ( ! $addon ) {
            return [
                'success' => false,
                'message' => 'Addon not found',
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $plugin_status = \install_plugin_install_status(
            [
                'slug'    => $addon,
                'version' => '',
            ]
        );

        if ( $plugin_status['status'] === false || empty( $plugin_status['file'] ) ) {
            return [
                'success' => false,
                'message' => 'Addon not installed',
            ];
        }

        $result = activate_plugin( $plugin_status['file'] );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
                'addon'   => $addon,
            ];
        }
        return [
            'success' => true,
            'message' => 'Addon activated',
        ];
    }

    public function deactivate_addon( \WP_REST_Request $request ) {
        $addon = $request->get_param( 'addon' );

        if ( ! $addon ) {
            return [
                'success' => false,
                'message' => 'Addon not found',
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $plugin_status = \install_plugin_install_status(
            [
                'slug'    => $addon,
                'version' => '',
            ]
        );

        if ( $plugin_status['status'] === false || empty( $plugin_status['file'] ) ) {
            return [
                'success' => false,
                'message' => 'Addon not installed',
            ];
        }

        deactivate_plugins( $plugin_status['file'] );

        return [
            'success' => true,
            'message' => 'Addon deactivated',
        ];
    }
}

// TS: 20260305163713
