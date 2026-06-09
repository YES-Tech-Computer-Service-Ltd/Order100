<?php

namespace Order100\Notification\Engine\Controllers;

use Order100\Notification\Engine\Abstracts\BaseController;
use Order100\Notification\Engine\TemplateLibrary\TemplateLibraryService;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Template Library Controller
 *
 * @method static TemplateLibraryController get_instance()
 */
class TemplateLibraryController extends BaseController {
    use SingletonTrait;

    protected function __construct() {
        $this->init_hooks();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    protected function init_hooks() {
        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/template-library',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_templates' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'exec_save_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'exec_delete_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    /**
     * Exec wrapper for getting list of templates.
     *
     * @param \WP_REST_Request $request Request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function exec_get_templates( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_templates' ], $request );
    }

    public function get_templates( \WP_REST_Request $request ) {
        $email_type = sanitize_text_field( $request->get_param( 'email_type' ) ?? '' );

        // User-saved templates from database
        $user_templates = get_option( 'o100_template_library', [] );
        if ( ! is_array( $user_templates ) ) {
            $user_templates = [];
        }

        // Code-defined (preset) templates from TemplateLibraryService
        $service = TemplateLibraryService::get_instance();
        $presets = [];
        if ( ! empty( $email_type ) ) {
            $presets = $service->get_list( $email_type );
        } else {
            // Return all presets when no email_type filter
            $presets = $service->get_all_templates();
        }

        // Mark preset templates so frontend can distinguish them
        foreach ( $presets as &$preset ) {
            $preset['is_preset'] = true;
        }
        foreach ( $user_templates as &$ut ) {
            $ut['is_preset'] = false;
        }

        return [
            'success'   => true,
            'templates' => array_merge( $presets, $user_templates ),
        ];
    }

    public function exec_save_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'save_template' ], $request );
    }

    public function save_template( \WP_REST_Request $request ) {
        $name = sanitize_text_field( $request->get_param( 'name' ) );
        $mjml = wp_unslash( $request->get_param( 'mjml' ) );

        if ( empty( $name ) ) {
            $name = 'Unnamed Template';
        }

        $templates = get_option( 'o100_template_library', [] );
        if ( ! is_array( $templates ) ) {
            $templates = [];
        }

        $id = uniqid( 'tpl_' );
        array_unshift( $templates, [
            'id'   => $id,
            'name' => $name,
            'mjml' => $mjml,
            'date' => current_time( 'mysql' ),
        ] );

        update_option( 'o100_template_library', $templates );

        return [
            'success'   => true,
            'id'        => $id,
            'templates' => $templates,
        ];
    }

    public function exec_delete_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'delete_template' ], $request );
    }

    public function delete_template( \WP_REST_Request $request ) {
        $id = sanitize_text_field( $request->get_param( 'id' ) );

        $templates = get_option( 'o100_template_library', [] );
        if ( ! is_array( $templates ) ) {
            $templates = [];
        }

        $templates = array_values( array_filter( $templates, function ( $tpl ) use ( $id ) {
            return isset( $tpl['id'] ) && $tpl['id'] !== $id;
        } ) );

        update_option( 'o100_template_library', $templates );

        return [
            'success'   => true,
            'templates' => $templates,
        ];
    }
}


// TS: 20260105164831

// TS: 20260220173750

// TS: 20260405170620

// Update TS: 20260609165000
