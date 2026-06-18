<?php

namespace Order100\Notification\Engine\Controllers;

use Order100\Notification\Engine\Abstracts\BaseController;
use Order100\Notification\Engine\Models\SettingModel;
use Order100\Notification\Engine\Models\TemplateModel;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\O100neTemplate;

/**
 * Template Controller
 *
 * @method static TemplateController get_instance()
 */
class TemplateController extends BaseController {
    use SingletonTrait;

    private $model = null;

    protected function __construct() {
        $this->model = TemplateModel::get_instance();
        $this->init_hooks();
    }

    protected function init_hooks() {
        $template_id_args = [
            'template_id' => [
                'type'     => 'string',
                'required' => true,
            ],
        ];
        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/icons',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_icons' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/promotions/active',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_active_promotions' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_templates' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/(?P<template_id>\d+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_template_by_id' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $template_id_args,
                ],
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_update_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $template_id_args,
                ],
                [
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'exec_delete_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $template_id_args,
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/get-template-by-name',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_template_by_name' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/change-status',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_change_status' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/reset',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_reset_templates' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/copy-template',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_copy_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/(?P<template_name>[a-zA-Z0-9_-]+)/all-elements',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_elements_by_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'template_name' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/(?P<template_name>[a-zA-Z0-9_-]+)/all-shortcodes/(?P<order>[a-zA-Z0-9_-]+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_all_shortcodes_by_template' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'template_name' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                        'order'         => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/global-header-footer/change-status',
            [
                [
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => [ $this, 'exec_change_global_header_footer_status' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/order-preview/(?P<order_id>\d+)',
            [
                [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'exec_get_order_preview' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/templates/send-test-email',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'exec_send_test_email' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'email' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                        'html' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ]
        );

        register_rest_route(
            O100NE_REST_NAMESPACE,
            '/preview-render-html',
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'exec_preview_render_html' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => [
                        'html' => [
                            'type'     => 'string',
                            'required' => true,
                        ],
                        'order_id' => [
                            'type'    => 'string',
                            'default' => '',
                        ],
                    ],
                ],
            ]
        );
    }

    public function exec_get_all_templates( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_templates' ], $request );
    }

    public function get_all_templates( \WP_REST_Request $request ) {
        $templates = $this->model->find_all();
        return apply_filters( 'o100_get_all_templates', $templates );
    }

    public function exec_get_template_by_id( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_template_by_id' ], $request );
    }

    public function get_template_by_id( \WP_REST_Request $request ) {
        $id            = sanitize_text_field( $request->get_param( 'template_id' ) );
        $template_data = $this->model::find_by_id( $id );
        return $template_data;
    }

    public function exec_update_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'update_template' ], $request );
    }

    public function update_template( \WP_REST_Request $request ) {
        $raw_data = $request->get_param( 'data' );
        // Handle both JSON string (form-encoded) and already-decoded array (application/json)
        $data = is_string( $raw_data ) ? json_decode( $raw_data, true ) : $raw_data;

        error_log( '[O100NE Save] update_template called. raw_data type: ' . gettype( $raw_data ) . ', data type: ' . gettype( $data ) );

        if ( empty( $data ) || ! is_array( $data ) ) {
            error_log( '[O100NE Save] Invalid data - returning error' );
            return new \WP_Error( 'invalid_data', 'Invalid template data', [ 'status' => 400 ] );
        }

        $id       = sanitize_text_field( $data['template_id'] ?? '' );
        // MJML builder sends 'template_elements', standard builder sends 'elements'
        $elements = $data['template_elements'] ?? $data['elements'] ?? [];
        // TODO: later
        // $elements                   = Helpers::elements_remove_settings_empty( $request->get_param( 'template_elements' ) );
        $background_color         = sanitize_text_field( $data['background_color'] ?? '' );
        $text_link_color          = sanitize_text_field( $data['text_link_color'] ?? '' );
        $content_background_color = sanitize_text_field( $data['content_background_color'] ?? '' );
        $content_text_color       = sanitize_text_field( $data['content_text_color'] ?? '' );
        $title_color              = sanitize_text_field( $data['title_color'] ?? '' );
        $global_header_settings   = $data['global_header_settings'] ?? O100neTemplate::DEFAULT_DATA['global_header_settings'];
        $global_footer_settings   = $data['global_footer_settings'] ?? O100neTemplate::DEFAULT_DATA['global_footer_settings'];

        $template_elements_type   = isset( $data['template_elements_type'] ) ? sanitize_text_field( $data['template_elements_type'] ) : '';
        // Do not sanitize template_html with wp_kses_post because it strips critical <style> tags and Outlook mso comments generated by MJML.
        $template_html            = isset( $data['template_html'] ) ? $data['template_html'] : '';

        error_log( '[O100NE Save] ID: ' . $id . ', elements_type: ' . $template_elements_type . ', html_len: ' . strlen( $template_html ) . ', elements_type(data): ' . gettype( $elements ) . ', elements_len: ' . ( is_string( $elements ) ? strlen( $elements ) : 'array' ) );
        error_log( '[O100NE Save] cond_o100_ in html: ' . preg_match_all( '/cond_o100_/', $template_html ) );

        $update_data              = [
            'elements'                 => $elements,
            'background_color'         => $background_color,
            'text_link_color'          => $text_link_color,
            'content_background_color' => $content_background_color,
            'content_text_color'       => $content_text_color,
            'title_color'              => $title_color,
            'global_header_settings'   => $global_header_settings,
            'global_footer_settings'   => $global_footer_settings,
        ];

        if ( ! empty( $template_html ) ) {
            $update_data['template_html'] = $template_html;
        }

        // Store template_elements_type if provided (e.g. 'mjml')
        if ( ! empty( $template_elements_type ) ) {
            $update_data['template_elements_type'] = $template_elements_type;
        }

        error_log( '[O100NE Save] update_data keys: ' . implode( ', ', array_keys( $update_data ) ) );

        $updated_data             = $this->model::update( $id, $update_data, true );
        return $updated_data;
    }

    public function exec_delete_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'delete_template' ], $request );
    }

    public function delete_template( \WP_REST_Request $request ) {
        $id = sanitize_text_field( $request->get_param( 'template_id' ) );
        $this->model::delete( $id );
        return [ 'success' => true ];
    }

    public function exec_get_template_by_name( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_template_by_name' ], $request );
    }

    public function get_template_by_name( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
        $template_data = $this->model::find_by_name( $template_name );

        if ( null === $template_data ) {
            $all_emails = o100ne_get_emails();
            if ( in_array(
                $template_name,
                array_map(
                    function ( $email ) {
                        return $email->get_id();
                    },
                    $all_emails
                )
            ) ) {

                $template_data = $this->model::insert(
                    [
                        'name'     => $template_name,
                        'elements' => o100ne_get_default_elements( $template_name ),
                    ]
                );
            }
        }//end if

        return $template_data;
    }

    public function exec_change_status( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'change_status' ], $request );
    }

    public function change_status( \WP_REST_Request $request ) {
        $list_id = is_array( $request->get_param( 'list_id' ) ) ? array_map( 'sanitize_text_field', wp_unslash( $request->get_param( 'list_id' ) ) ) : [];
        $status  = sanitize_text_field( $request->get_param( 'status' ) );
        foreach ( $list_id as $id ) {
            $this->model::update(
                $id,
                [
                    'status' => $status,
                ]
            );
        }
        return [ 'success' => true ];
    }

    public function exec_reset_templates( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'reset_templates' ], $request );
    }

    public function reset_templates( \WP_REST_Request $request ) {
        $list_id            = is_array( $request->get_param( 'list_id' ) ) ? array_map( 'sanitize_text_field', wp_unslash( $request->get_param( 'list_id' ) ) ) : [];
        $list_template_data = [];

        foreach ( $list_id as $id ) {
            $template_data                             = $this->model::find_by_id( $id );
            $default_elements                          = o100ne_get_default_elements( $template_data['name'] );
            $update_data                               = [
                'elements'                 => $default_elements,
                'background_color'         => O100neTemplate::DEFAULT_DATA['background_color'],
                'text_link_color'          => O100neTemplate::DEFAULT_DATA['text_link_color'],
                'content_background_color' => O100neTemplate::DEFAULT_DATA['content_background_color'],
                'content_text_color'       => O100neTemplate::DEFAULT_DATA['content_text_color'],
                'title_color'              => O100neTemplate::DEFAULT_DATA['title_color'],
                'global_header_settings'   => wp_parse_args(
                    [
                        'hidden' => true,
                    ],
                    $template_data['global_header_settings'] ?? O100neTemplate::DEFAULT_DATA['global_header_settings'],
                ),
                'global_footer_settings'   => wp_parse_args(
                    [
                        'hidden' => true,
                    ],
                    $template_data['global_footer_settings'] ?? O100neTemplate::DEFAULT_DATA['global_footer_settings'],
                ),
            ];
            $template_data['elements']                 = $update_data['elements'];
            $template_data['background_color']         = $update_data['background_color'];
            $template_data['text_link_color']          = $update_data['text_link_color'];
            $template_data['content_background_color'] = $update_data['content_background_color'];
            $template_data['content_text_color']       = $update_data['content_text_color'];
            $template_data['title_color']              = $update_data['title_color'] ?? '#000000';
            $template_data['global_header_settings']   = $update_data['global_header_settings'];
            $template_data['global_footer_settings']   = $update_data['global_footer_settings'];

            $list_template_data[] = $template_data;
            $this->model::update( $id, $update_data, true );
        }//end foreach

        return [
            'success'            => true,
            'list_template_data' => $list_template_data,
        ];
    }


    public function exec_copy_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'copy_template' ], $request );
    }

    public function copy_template( \WP_REST_Request $request ) {
        $template_id        = sanitize_text_field( $request->get_param( 'template_id' ) );
        $from_template      = sanitize_text_field( $request->get_param( 'from_template' ) );
        $copy_template_data = $this->model::find_by_name( $from_template );

        if ( empty( $copy_template_data ) || empty( $copy_template_data['id'] ) ) {
            return [
                'success' => false,
                'message' => 'Template not found',
            ];
        }

        $update_data = [
            'elements'                 => ! empty( $copy_template_data['elements'] ) ? $copy_template_data['elements'] : o100ne_get_default_elements( $from_template ),
            'background_color'         => $copy_template_data['background_color'] ?? O100NE_COLOR_BACKGROUND_DEFAULT,
            'content_background_color' => $copy_template_data['content_background_color'] ?? '#ffffff',
            'content_text_color'       => $copy_template_data['content_text_color'] ?? '#000000',
            'text_link_color'          => $copy_template_data['text_link_color'] ?? O100NE_COLOR_WC_DEFAULT,
            'global_header_settings'   => ! empty( $copy_template_data['global_header_settings'] ) ? $copy_template_data['global_header_settings'] : O100neTemplate::DEFAULT_DATA['global_header_settings'],
            'global_footer_settings'   => ! empty( $copy_template_data['global_footer_settings'] ) ? $copy_template_data['global_footer_settings'] : O100neTemplate::DEFAULT_DATA['global_footer_settings'],
            'title_color'              => $copy_template_data['title_color'] ?? '#000000',
        ];

        $this->model::update( $template_id, $update_data, true );
        return [
            'success' => true,
        ];
    }

    public function exec_get_all_elements_by_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_elements' ], $request );
    }

    public function get_all_elements( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
        $elements      = $this->model::get_elements_for_template( $template_name );
        return $elements;
    }

    public function exec_get_all_shortcodes_by_template( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_all_shortcodes' ], $request );
    }

    public function get_all_shortcodes( \WP_REST_Request $request ) {
        $template_name = sanitize_text_field( $request->get_param( 'template_name' ) );
        $order_id      = sanitize_text_field( $request->get_param( 'order' ) );
        return $this->model->get_shortcodes_by_template_name_and_order_id( $template_name, $order_id );
    }

    public function exec_change_global_header_footer_status( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'change_global_header_footer_status' ], $request );
    }

    public function change_global_header_footer_status( \WP_REST_Request $request ) {
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        return SettingModel::update( [ 'global_header_footer_enabled' => $status ] );
    }

    public function exec_get_order_preview( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_order_preview' ], $request );
    }

    public function get_order_preview( \WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return [ 'success' => false, 'message' => 'Order not found' ];
        }

        $items = [];
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $sku = $product ? $product->get_sku() : '';
            $image_id = $product ? $product->get_image_id() : 0;
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
            
            // Get formatted meta data (e.g. variations)
            $meta_data = '';
            if ( $product ) {
                $formatted_meta = $item->get_formatted_meta_data( '_', true );
                if ( ! empty( $formatted_meta ) ) {
                    $meta_html = [];
                    foreach ( $formatted_meta as $meta ) {
                        $meta_html[] = '<br/><small style="color:#666;">' . wp_kses_post( $meta->display_key ) . ': ' . wp_kses_post( $meta->display_value ) . '</small>';
                    }
                    $meta_data = implode( '', $meta_html );
                }
            }

            $items[] = [
                'name'  => $item->get_name() . $meta_data,
                'qty'   => $item->get_quantity(),
                'price' => wp_strip_all_tags( wc_price( $order->get_line_total( $item, true, true ) ) ),
                'sku'   => $sku,
                'image' => $image_url,
                'refunded_qty' => $order->get_qty_refunded_for_item( $item_id ),
                'purchase_note' => $product ? $product->get_purchase_note() : ''
            ];
        }

        $billing_address = $order->get_formatted_billing_address();
        $shipping_address = $order->get_formatted_shipping_address();
        
        // 1. CRM Lookup for Guest Customers
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name  = $order->get_billing_last_name();
        $billing_phone      = $order->get_billing_phone();
        $billing_email      = $order->get_billing_email();

        if ( class_exists( 'O100_Customers_DB' ) && ! empty( $billing_email ) ) {
            global $wpdb;
            $tbl_customers = \O100_Customers_DB::get_table_customers();
            $customer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_customers} WHERE email = %s", $billing_email ) );
            if ( $customer ) {
                $billing_first_name = $customer->first_name ?: $billing_first_name;
                $billing_last_name  = $customer->last_name ?: $billing_last_name;
                $billing_phone      = $customer->phone ?: $billing_phone;
            }
        }
        
        $item_totals = [];
        foreach ( $order->get_order_item_totals() as $key => $total ) {
            $item_totals[$key] = [
                'label' => wp_strip_all_tags( $total['label'] ),
                'value' => wp_strip_all_tags( $total['value'] )
            ];
        }

        return [
            'success' => true,
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'items' => $items,
            'item_totals' => $item_totals,
            'subtotal' => wp_strip_all_tags( wc_price( $order->get_subtotal() ) ),
            'total' => wp_strip_all_tags( wc_price( $order->get_total() ) ),
            'shipping_method' => $order->get_shipping_method(),
            'payment_method' => $order->get_payment_method_title(),
            'customer_note' => $order->get_customer_note(),
            'billing_address' => wp_kses_post( $billing_address ),
            'billing_first_name' => $billing_first_name,
            'billing_last_name' => $billing_last_name,
            'billing_phone' => $billing_phone,
            'billing_email' => $billing_email,
            'shipping_address' => wp_kses_post( $shipping_address ? $shipping_address : $billing_address ),
            'shipping_phone' => $order->get_shipping_phone(),
            'total_html' => wp_strip_all_tags( wc_price( $order->get_total() ) ),
            'order_date' => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
            'o100_time_deli' => $order->get_meta( 'o100_time_deli', true ),
            'order_type' => $order->get_meta( '_o100_order_method', true ) ?: $order->get_meta( 'o100_order_method', true ) ?: $order->get_meta( '_o100_order_type', true ) ?: $order->get_meta( 'o100_order_type', true ),
            'meta' => [
                'o100_delivery_instruction' => $order->get_meta( 'o100_delivery_instruction', true ) ?: $order->get_meta( '_o100_delivery_instruction', true ),
                'o100_date_deli' => $order->get_meta( 'o100_date_deli', true ) ?: $order->get_meta( '_o100_date_deli', true ),
                'o100_time_deli' => $order->get_meta( 'o100_time_deli', true ) ?: $order->get_meta( '_o100_time_deli', true ),
            ]
        ];
    }

    public function exec_send_test_email( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'send_test_email' ], $request );
    }

    /**
     * Send a test email with the current template HTML.
     */
    public function send_test_email( \WP_REST_Request $request ) {
        $to   = sanitize_email( $request->get_param( 'email' ) );
        $html = $request->get_param( 'html' );

        if ( ! is_email( $to ) ) {
            return [ 'success' => false, 'message' => 'Invalid email address.' ];
        }

        if ( empty( $html ) ) {
            return [ 'success' => false, 'message' => 'Email HTML content is empty.' ];
        }

        $site_name = get_bloginfo( 'name' );
        $subject   = sprintf( '[%s] Test Email - Email Template Preview', $site_name );

        // Set content type to HTML.
        add_filter( 'wp_mail_content_type', [ $this, 'set_html_content_type' ] );

        $sent = wp_mail( $to, $subject, $html );

        // Remove the filter immediately after sending.
        remove_filter( 'wp_mail_content_type', [ $this, 'set_html_content_type' ] );

        if ( $sent ) {
            return [ 'success' => true, 'message' => 'Test email sent to ' . $to ];
        }

        return [ 'success' => false, 'message' => 'Failed to send email. Check your server mail configuration.' ];
    }

    /**
     * Filter callback for wp_mail HTML content type.
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    public function exec_get_active_promotions( \WP_REST_Request $request ) {
        return $this->exec( [ $this, 'get_active_promotions' ], $request );
    }

    public function get_active_promotions( \WP_REST_Request $request ) {
        if ( ! class_exists( 'O100_Promotions_DB' ) ) {
            $promotions_db_path = O100_PATH . 'core/promotions/engine/class-o100-promotions-db.php';
            if ( file_exists( $promotions_db_path ) ) {
                require_once $promotions_db_path;
            } else {
                return [ 'success' => false, 'message' => 'Promotions module not found.' ];
            }
        }

        $promotions = \O100_Promotions_DB::query( [
            'status' => 'active',
            'limit'  => 500, // get all active promos
        ] );

        $formatted = [];
        foreach ( $promotions as $promo ) {
            $formatted[] = [
                'id'         => $promo['id'],
                'title'      => $promo['title'],
                'promo_code' => $promo['promo_code'],
            ];
        }

        return [
            'success' => true,
            'data'    => $formatted,
        ];
    }

    /**
     * Preview Render HTML — server-side shortcode processing
     *
     * Receives raw HTML with shortcode placeholders from the editor,
     * processes them using the notification engine's shortcode executor,
     * and returns the resolved HTML.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function exec_preview_render_html( \WP_REST_Request $request ) {
        $html     = $request->get_param( 'html' );
        $order_id = $request->get_param( 'order_id' );

        if ( empty( $html ) ) {
            return [ 'success' => false, 'html' => '' ];
        }

        // Build render data context
        $render_data = [
            'is_preview'            => true,
            'is_customized_preview' => true,
        ];

        // If a real order ID is provided, attach the order object
        $order = null;
        if ( ! empty( $order_id ) && $order_id !== 'sample_order' ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $render_data['order'] = $order;
            }
        }

        // Collect all registered shortcode definitions
        $all_shortcodes = [];
        $loader = \Order100\Notification\Engine\Shortcodes\ShortcodesLoader::get_instance();
        $shortcode_classes = [
            \Order100\Notification\Engine\Shortcodes\CommonShortcodes::get_instance(),
            \Order100\Notification\Engine\Shortcodes\ShippingShortcodes::get_instance(),
            \Order100\Notification\Engine\Shortcodes\BillingShortcodes::get_instance(),
            \Order100\Notification\Engine\Shortcodes\PaymentsShortcodes::get_instance(),
        ];

        foreach ( $shortcode_classes as $sc_instance ) {
            if ( method_exists( $sc_instance, 'get_shortcodes' ) ) {
                $sc_defs = $sc_instance->get_shortcodes();
                if ( is_array( $sc_defs ) ) {
                    $all_shortcodes = array_merge( $all_shortcodes, $sc_defs );
                }
            }
        }

        // Register shortcodes for this context via ShortcodesExecutor
        if ( ! empty( $all_shortcodes ) ) {
            new \Order100\Notification\Engine\Shortcodes\ShortcodesExecutor(
                $all_shortcodes,
                array_merge( $render_data, [ 'render_data' => $render_data ] )
            );
        }

        // Process all shortcodes
        $html = do_shortcode( $html );

        return [
            'success' => true,
            'html'    => $html,
        ];
    }
}

