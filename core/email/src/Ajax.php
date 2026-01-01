<?php

namespace Order100\Notification\Engine;

use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Models\SettingModel;
use Order100\Notification\Engine\Models\TemplateModel;
use Order100\Notification\Engine\Models\RevisionModel;
use Order100\Notification\Engine\Migrations\MainMigration;
use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Migrations\AbstractMigration;

/**
 * I18n Logic
 *
 * @method static Ajax get_instance()
 */
class Ajax {
    use SingletonTrait;

    protected function __construct() {
        $this->init_hooks();
    }

    protected function init_hooks() {
        $actions = [
            'preview_mail'                     => 'preview_mail',
            'preview_mail_for_woo'             => 'preview_mail_for_woo',
            'send_test_mail'                   => 'send_test_mail',
            'install_yaysmtp'                  => 'install_yaysmtp',
            'get_custom_hook_html'             => 'get_custom_hook_html',
            'get_template_data_onload'         => 'get_template_data_onload',
            'export_templates'                 => 'export_templates',
            'import_templates'                 => 'import_templates',
            'review'                           => 'o100_review',
            'change_ghf_tour'                  => 'change_ghf_tour',
            'dismiss_multi_select_notice'      => 'dismiss_multi_select_notice',
            'export_state'                     => 'export_state',
            'import_state'                     => 'import_state',
            'dismiss_new_element_notification' => 'dismiss_new_element_notification',
            'get_template_library'             => 'get_template_library',
            'save_to_template_library'         => 'save_to_template_library',
            'delete_from_template_library'     => 'delete_from_template_library',
        ];

        foreach ( $actions as $action => $method ) {
            add_action( 'wp_ajax_o100ne_' . $action, [ $this, $method ] );
            add_action( 'wp_ajax_nopriv_o100ne_' . $action, [ $this, $method ] );
        }
    }

    public function import_state() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            $import_file = isset( $_FILES['import_file'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_FILES['import_file'] ) ) : null;
            if ( ! $import_file ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find import file', 'order100' ) ] );
            }

            if ( $import_file['type'] !== 'application/zip' ) {
                return wp_send_json_error( [ 'mess' => __( 'Invalid file type. Please upload a ZIP file.', 'order100' ) ] );
            }

            $zip = new \ZipArchive();
            if ( $zip->open( $import_file['tmp_name'] ) !== true ) {
                return wp_send_json_error( [ 'mess' => __( 'Cannot open ZIP file.', 'order100' ) ] );
            }

            $state_data = null;
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $filename = $zip->getNameIndex( $i );
                if ( $filename === 'o100_backup.json' ) {
                    $state_data = $zip->getFromIndex( $i );
                    break;
                }
            }

            $zip->close();

            if ( ! $state_data ) {
                return wp_send_json_error( [ 'mess' => __( 'Cannot find o100ne_backup.json in the ZIP file.', 'order100' ) ] );
            }

            $imported_data = json_decode( $state_data );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return wp_send_json_error( [ 'mess' => __( 'Invalid JSON data in the state file.', 'order100' ) ] );
            }

            if ( empty( $imported_data->posts ) || empty( $imported_data->postmeta ) || empty( $imported_data->options ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Invalid state file structure.', 'order100' ) ] );
            }

            $migration_model = \Order100\Notification\Engine\Models\MigrationModel::get_instance();

            $source_version = $imported_data->version;

            $imported_posts = array_values(
                array_filter(
                    $imported_data->posts,
                    function( $post ) {
                        return isset( $post->post_type ) && 'o100_template' === $post->post_type;
                    }
                )
            );

            $imported_postmeta = array_values(
                array_filter(
                    $imported_data->postmeta,
                    function( $postmeta ) use ( $imported_posts ) {
                        if ( empty( $imported_posts ) ) {
                            return false;
                        }
                        if ( ! isset( $postmeta->post_id ) || ! isset( $postmeta->meta_key ) ) {
                            return false;
                        }
                        if ( strpos( (string) $postmeta->meta_key, 'order100' ) === false ) {
                            return false;
                        }
                        foreach ( $imported_posts as $post ) {
                            if ( isset( $post->ID ) && (int) $post->ID === (int) $postmeta->post_id ) {
                                return true;
                            }
                        }
                        return false;
                    }
                )
            );

            $imported_options = array_values(
                array_filter(
                    $imported_data->options,
                    function( $option ) {
                        return isset( $option->option_name ) && strpos( (string) $option->option_name, 'order100' ) !== false;
                    }
                )
            );

            $backup_data = [
                'posts'        => $imported_posts,
                'postmeta'     => $imported_postmeta,
                'options'      => $imported_options,
                'created_date' => $imported_data->created_date ?? current_datetime()->format( 'Y-m-d H:i:s' ),
                'name'         => '_o100ne_import_backup_' . $source_version,
                'version'      => $source_version,
            ];

            $migration_model->reset( $backup_data );

            wp_send_json_success(
                [
                    'message' => __( 'Import state successfully', 'order100' ),
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
            wp_send_json_error( [ 'mess' => __( 'Import failed: ', 'order100' ) . $error->getMessage() ] );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
            wp_send_json_error( [ 'mess' => __( 'Import failed: ', 'order100' ) . $exception->getMessage() ] );
        }//end try
    }

    public function export_state() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }

        try {
            global $wpdb;

            /**
             * Backup posts and postmeta
             */
            $query_posts            = "
            SELECT *
            FROM {$wpdb->posts}
            WHERE post_type = 'o100_template'
            ";
            $o100ne_template_posts = $wpdb->get_results( $query_posts );// phpcs:ignore

            $query_postmeta            = "
                SELECT *
                FROM {$wpdb->postmeta}
                WHERE meta_key LIKE '%o100ne%'
            ";
            $o100ne_template_postmeta = $wpdb->get_results( $query_postmeta );// phpcs:ignore

            $backup_data = [
                'posts'    => $o100ne_template_posts,
                'postmeta' => $o100ne_template_postmeta,
            ];
            /** ****************************** */

            /**
             * Backup options
             */
            $query_options          = "
            SELECT *
            FROM {$wpdb->options}
            WHERE option_name LIKE '%o100ne%'
        ";
            $o100ne_options        = $wpdb->get_results( $query_options ); // phpcs:ignore
            $backup_data['options'] = $o100ne_options;

            $backup_data['created_date'] = current_datetime()->format( 'Y-m-d H:i:s' );
            $backup_data['version']      = get_option( 'o100_version_backup' );

            $backup_data = apply_filters( 'o100_backup_state_data', $backup_data );

            wp_send_json_success(
                [
                    'message'   => 'success',
                    'data'      => $backup_data,
                    'file_name' => 'o100_export_backup_' . gmdate( 'm-d-Y' ),
                ]
            );

        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    public function sanitize( $array ) {

        return wp_kses_post_deep( $array );
    }

    public function process_plugin_installer( $slug ) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

        $api = plugins_api(
            'plugin_information',
            [
                'slug'   => $slug,
                'fields' => [
                    'short_description' => false,
                    'sections'          => false,
                    'requires'          => false,
                    'rating'            => false,
                    'ratings'           => false,
                    'downloaded'        => false,
                    'last_updated'      => false,
                    'added'             => false,
                    'tags'              => false,
                    'compatibility'     => false,
                    'homepage'          => false,
                    'donate_link'       => false,
                ],
            ]
        );

        $skin = new \WP_Ajax_Upgrader_Skin();

        $plugin_upgrader = new \Plugin_Upgrader( $skin );

        try {
            $result = $plugin_upgrader->install( $api->download_link );

            if ( is_wp_error( $result ) ) {
                o100ne_get_logger( $result );
            }

            return true;
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }

        return false;
    }

    public function install_yaysmtp() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }

        if ( ! current_user_can( 'install_plugins' ) && ! current_user_can( 'activate_plugins' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'You do not have permission to install plugins', 'order100' ) ] );
        }

        try {
            $is_installed = $this->process_plugin_installer( 'yaysmtp' );

            if ( false === $is_installed ) {
                wp_send_json_error( [ 'message' => $is_installed ] );
            }

            $result = activate_plugin( 'yaysmtp/yay-smtp.php' );

            if ( is_wp_error( $result ) ) {
                return wp_send_json_error( [ 'mess' => esc_html( $result->get_error_message() ) ] );
            }

            wp_send_json_success(
                [
                    'installed' => null === $result,
                ]
            );

        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    public function send_test_mail() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            $template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
            $order_id      = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 'sample_order';
            $email         = isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : '';

            if ( empty( $template_name ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find template', 'order100' ) ] );
            }

            if ( empty( $order_id ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find order', 'order100' ) ] );
            }

            if ( empty( $email ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find email', 'order100' ) ] );
            }

            $template = new O100neTemplate( $template_name );

            $render_data = [];

            if ( empty( $order_id ) || ( 'sample_order' === $order_id ) ) {
                $render_data['is_sample'] = true;
            } else {
                $render_data['order'] = wc_get_order( $order_id );
            }

            $render_data['is_customized_preview'] = true;
            // check if email template on preview and send test mail

            update_option( 'o100_default_email_test', $email );

            $html = $template->get_content( $render_data );

            $headers        = "Content-Type: text/html\r\n";
            $class_wc_email = \WC_Emails::instance();
            $subject        = __( 'Email Test', 'order100' );
            $send_mail      = $class_wc_email->send( $email, $subject, $html, $headers, [] );

            if ( ! $send_mail ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t send email', 'order100' ) ] );
            }

            wp_send_json_success(
                [
                    'email'             => $email,
                    'send_mail_success' => $send_mail,
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    public function preview_mail() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            $order_id         = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : 'sample_order';
            $template_data    = isset( $_POST['template_data'] ) ? $this->sanitize( wp_unslash( $_POST['template_data'] ) ) : []; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $unsaved_settings = isset( $_POST['unsaved_settings'] ) ? $this->sanitize( wp_unslash( $_POST['unsaved_settings'] ) ) : []; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            if ( empty( $template_data ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find template', 'order100' ) ] );
            }

            if ( empty( $order_id ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Can\'t find order', 'order100' ) ] );
            }

            $template = new O100neTemplate( $template_data['name'] );

            if ( ! empty( $unsaved_settings ) ) {
                global $o100ne_unsaved_settings;
                $o100ne_unsaved_settings = $unsaved_settings;
            }

            $template->set_background_color( $template_data['background_color'] );
            $template->set_text_link_color( $template_data['text_link_color'] );
            $template->set_global_header_settings( $template_data['global_header_settings'] );
            $template->set_global_footer_settings( $template_data['global_footer_settings'] );
            $template->set_elements( $template_data['elements'] );

            $render_data = [];

            if ( empty( $order_id ) || ( 'sample_order' === $order_id ) ) {
                $render_data['is_sample'] = true;
            } else {
                $render_data['order'] = wc_get_order( $order_id );
            }

            $render_data['is_customized_preview'] = true;
            // check if email template on preview and send test mail

            $html = $template->get_content( $render_data );

            // TODO: render with passing settings
            $current_email = null;
            $subject       = 'Sample Subject';
            $emails        = wc()->mailer()->emails;
            foreach ( $emails as $email ) {
                if ( $email->id === $template_data['name'] ) {
                    $current_email = $email;
                    if ( method_exists( $current_email, 'set_object' ) ) {
                        if ( ! empty( $render_data['order'] ) && is_a( $render_data['order'], '\WC_Order' ) ) {
                            $current_email->set_object( $render_data['order'] );
                        } else {
                            $current_email->set_object( Helpers::get_dummy_order() );
                        }
                    }
                    break;
                }
            }

            if ( ! empty( $current_email ) ) {
                $subject = $current_email->get_subject();
            }
            $email_address = wp_get_current_user()->user_email ?? 'sample@example.com';

            wp_send_json_success(
                [
                    'html'          => $html,
                    'subject'       => $subject,
                    'email_address' => $email_address,
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    public function preview_mail_for_woo() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            $template_name   = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
            $search_order_id = isset( $_POST['search_order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['search_order_id'] ) ) : null;
            $email_address   = isset( $_POST['email_address'] ) ? sanitize_text_field( wp_unslash( $_POST['email_address'] ) ) : '';

            $email_preview_output = PreviewEmail\PreviewEmailWoo::email_preview_output( $search_order_id, $template_name, $email_address );

            $email_preview_output = apply_filters( 'o100_preview_email', $email_preview_output, $search_order_id, $template_name, $email_address );

            $send_mail = false;

            if ( ! empty( $email_address ) && ! empty( $email_preview_output['html'] ) ) {
                $headers        = "Content-Type: text/html\r\n";
                $class_wc_email = \WC_Emails::instance();
                $subject        = __( 'Email Preview', 'order100' );
                $send_mail      = $class_wc_email->send( $email_address, $subject, $email_preview_output['html'], $headers, [] );
                if ( ! $send_mail ) {
                    return wp_send_json_error( [ 'mess' => __( 'Can\'t send email', 'order100' ) ] );
                }
            }

            wp_send_json_success(
                [
                    'html'                  => ! empty( $email_preview_output['html'] ) ? $email_preview_output['html'] : __( 'No email content found', 'order100' ),
                    'subject'               => ! empty( $email_preview_output['subject'] ) ? $email_preview_output['subject'] : __( 'No subject found', 'order100' ),
                    'is_disabled_send_mail' => ! empty( $email_preview_output['is_disabled_send_mail'] ) ? $email_preview_output['is_disabled_send_mail'] : false,
                    'send_mail_success'     => $send_mail,
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    public function export_templates() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            $templates = isset( $_POST['templates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['templates'] ) ) : [];
            // TODO: sanitize
            $default     = [
                'post_type'      => 'o100_template',
                'post_status'    => [ 'publish', 'pending', 'future' ],
                'posts_per_page' => '-1',
                'meta_query'     => [
                    [
                        'key'     => O100neTemplate::META_KEYS['name'],
                        'value'   => $templates,
                        'compare' => 'IN',
                    ],
                ],
            ];
            $export_data = [];
            $query       = new \WP_Query( $default );
            if ( $query->have_posts() ) {
                $posts = $query->get_posts();
                foreach ( $posts as $post ) {
                    $template_name            = get_post_meta( $post->ID, O100neTemplate::META_KEYS['name'], true );
                    $elements                 = get_post_meta( $post->ID, O100neTemplate::META_KEYS['elements'], true );
                    $language                 = get_post_meta( $post->ID, O100neTemplate::META_KEYS['language'], true );
                    $text_link_color          = get_post_meta( $post->ID, O100neTemplate::META_KEYS['text_link_color'], true );
                    $background_color         = get_post_meta( $post->ID, O100neTemplate::META_KEYS['background_color'], true );
                    $content_background_color = get_post_meta( $post->ID, O100neTemplate::META_KEYS['content_background_color'], true );
                    $content_text_color       = get_post_meta( $post->ID, O100neTemplate::META_KEYS['content_text_color'], true );
                    $file_name                = "{$template_name}.json";
                    if ( empty( $language ) ) {
                        $export_data[] = [
                            'file_name'      => $file_name,
                            'templates_data' => [
                                'template'                 => $template_name,
                                'elements'                 => $elements,
                                'language'                 => '',
                                'text_link_color'          => $text_link_color,
                                'background_color'         => $background_color,
                                'content_background_color' => $content_background_color,
                                'content_text_color'       => $content_text_color,
                                'title_color'              => $title_color,
                            ],
                        ];
                    }
                }//end foreach
            }//end if
            wp_reset_postdata();
            wp_send_json_success(
                [
                    'message'   => __( 'Export successfully', 'order100' ),
                    'data'      => $export_data,
                    'file_name' => 'o100_customizer_templates_' . gmdate( 'm-d-Y' ),
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    public function import_templates() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            if ( ! empty( $_FILES ) ) {
                $result = $this->process_import( $_FILES );
                wp_send_json_success(
                    [
                        'imported_data' => $result,
                    ]
                );
            } else {
                wp_send_json_error( [ 'message' => __( 'Can\'t find import files.', 'order100' ) ] );
            }
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }

    public function process_import( $files ) {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $imported_templates = [];
        $is_legacy          = false;
        foreach ( $files as $file ) {
            if ( isset( $file['type'] ) ) {
                if ( 'application/json' === $file['type'] ) {
                    if ( ! empty( $file['tmp_name'] ) ) {
                        $file_tmp_name = sanitize_text_field( $file['tmp_name'] );
                        $file_content  = $wp_filesystem->get_contents( $file_tmp_name );
                        $file_content  = json_decode( $file_content, true );
                        if ( ! isset( $file_content['template'] ) ) {
                            if ( isset( $file_content['o100neTemplateExport'] ) ) {
                                $is_legacy          = true;
                                $imported_templates = array_merge( $imported_templates, $this->process_import_legacy( $file_content ) );
                            } else {
                                continue;
                            }
                        } else {
                            $update_result = $this->processing_import_update_data( $file_content );
                            if ( ! empty( $update_result ) ) {
                                $imported_templates[] = $update_result;
                            }
                        }
                    }//end if
                }//end if
            }//end if
        }//end foreach
        if ( $is_legacy ) {
            MainMigration::get_instance()->migrate( true );
        }
        return $imported_templates;
    }

    public function process_import_legacy( $file_content ) {
        $updated_templates = [];
        // Import templates
        foreach ( $file_content['o100neTemplateExport'] as $template ) {
            $updated_result = $this->processing_import_update_data( $template, true );
            if ( ! empty( $updated_result ) ) {
                $updated_templates[] = $updated_result;
            }
        }//end foreach
        // Import settings
        $import_settings = isset( $file_content['o100_settings'] ) ? $file_content['o100_settings'] : [];
        if ( ! empty( $import_settings ) ) {
            update_option( 'o100_settings', $import_settings );
        }
        return $updated_templates;
    }

    public function processing_import_update_data( $data, $is_legacy = false ) {
        $template_name    = $data['template'] ?? null;
        $elements         = $data['elements'] ?? null;
        $text_link_color  = $data['text_link_color'] ?? null;
        $background_color = $data['background_color'] ?? null;
        $title_color      = $data['title_color'] ?? null;

        if ( empty( $template_name ) ) {
            return null;
        }

        $template = new O100neTemplate( $template_name );
        if ( ! $template->is_exists() ) {
            return null;
        }

        $template->set_elements( $elements );
        $template->set_text_link_color( $text_link_color );
        $template->set_background_color( $background_color );
        $template->set_title_color( $title_color );
        $template->set_content_background_color( $content_background_color );
        $template->set_content_text_color( $content_text_color );
        if ( $is_legacy ) {
            $template->set_status( 'inactive' );
        }

        $template->save();

        wp_reset_postdata();

        return [
            'template_name' => $template_name,
        ];
    }

    /**
     * Process a custom hook request and generate HTML content.
     *
     * This function handles a custom hook request, generates HTML content based on the provided data and attributes.
     * It is designed to be used as an AJAX callback.
     *
     * @example $_POST['data'] =
     * [
     *     'template_data' => O100ne\O100neTemplate,
     *     'order_id' => 'sample_order',
     *     'attributes' => [
     *         [
     *             'name' => 'hook',
     *             'value' => 'your_hook'
     *         ],
     *         [
     *             'name' => 'background_color',
     *             'value' => '#ffffff'
     *         ]
     *     ]
     * ]
     *
     * @return void This function sends a JSON response with HTML content or error messages.
     */
    public function get_custom_hook_html() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            $attributes = isset( $_POST['data']['attributes'] ) ? $_POST['data']['attributes'] : []; // phpcs:ignore
            if ( empty( $attributes ) ) {
                return wp_send_json_error( [ 'mess' => __( 'Attributes empty', 'order100' ) ] );
            }

            /**
             * Build data for shortcode
             */
            $template_model = \Order100\Notification\Engine\Models\TemplateModel::get_instance();
            $data           = [];
            if ( ! empty( $_POST['data']['template_data'] ) ) {
                $data = \Order100\Notification\Engine\Models\TemplateModel::get_shortcode_executor_data( sanitize_text_field( wp_unslash( $_POST['data']['template_data'] ) ), sanitize_text_field( wp_unslash( $_POST['data']['order_id'] ) ) );

                $data['template']->set_props( sanitize_text_field( wp_unslash( $_POST['data']['template_data'] ) ) );
            }

            $hook_shortcodes = \Order100\Notification\Engine\Shortcodes\HookShortcodes::get_instance();
            $html            = $hook_shortcodes->o100ne_handle_custom_hook_shortcode( $data, $attributes );
            wp_send_json_success(
                [
                    'html' => $html,
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }//end try
    }

    /**
     * Get all needed data when load O100ne template to customizer.
     */
    public function get_template_data_onload() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        // BYPASS: if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
        // BYPASS:     return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        // BYPASS: }
        try {
            $setting_model = SettingModel::get_instance();
            $setting_model = \Order100\Notification\Engine\Models\SettingModel::get_instance();
            $settings_data = $setting_model->find_all();

            $template_name = isset( $_POST['data']['template_name'] ) ? sanitize_text_field( $_POST['data']['template_name'] ) : 'new_order';
            $order_id      = isset( $_POST['data']['order_id'] ) ? sanitize_text_field( $_POST['data']['order_id'] ) : 'sample_order';

            $template_model = \Order100\Notification\Engine\Models\TemplateModel::get_instance();

            $shortcodes_data = $template_model->get_shortcodes_by_template_name_and_order_id( $template_name, $order_id );

            $templates_data = apply_filters( 'o100_get_all_templates', $template_model->find_all() );

            $selected_template_data = $template_model->find_by_name( $template_name );

            $elements_data = \Order100\Notification\Engine\Models\TemplateModel::get_elements_for_template( $template_name );

            $revision_model = \Order100\Notification\Engine\Models\RevisionModel::get_instance();
            $revisions_data = $revision_model->get_by_template( $template_name );

            wp_send_json_success(
                [
                    'settings_data'          => $settings_data,
                    'templates_data'         => $templates_data,
                    'selected_template_data' => $selected_template_data,
                    'elements_data'          => $elements_data,
                    'revisions_data'         => $revisions_data,
                    'shortcodes_data'        => $shortcodes_data,
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
            wp_send_json_error( [ 'mess' => $error->getMessage() ] );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
            wp_send_json_error( [ 'mess' => $exception->getMessage() ] );
        } catch ( \Throwable $throwable ) {
            o100ne_get_logger( $throwable );
            wp_send_json_error( [ 'mess' => $throwable->getMessage() ] );
        }//end try
    }

    public function o100ne_review() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {

            $o100ne_review = update_option( 'o100_review', true );

            wp_send_json_success(
                [
                    'reviewed' => $o100ne_review,
                ]
            );

        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }

    public function change_ghf_tour() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }

        try {
            $next_move = isset( $_POST['next_move'] ) ? sanitize_text_field( wp_unslash( $_POST['next_move'] ) ) : 'initial';
            $ghf_tour  = update_option( 'o100_ghf_tour', $next_move );

            wp_send_json_success(
                [
                    'ghf_tour' => $ghf_tour,
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }

    public function dismiss_multi_select_notice() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }

        try {
            update_option( 'o100_show_multi_select_notice', 'no' );

            wp_send_json_success(
                [
                    'show_multi_select_notice' => 'no',
                ]
            );
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }

    public function dismiss_new_element_notification() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }

        $elements = isset( $_POST['elements'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['elements'] ) ) : [];

        $viewed_new_elements = get_option( 'o100_viewed_new_elements', [] );
        $viewed_new_elements = array_unique( array_merge( $viewed_new_elements, $elements ) );

        try {
            update_option( 'o100_viewed_new_elements', $viewed_new_elements );

            wp_send_json_success(
                [
                    'viewed_new_elements' => $viewed_new_elements,
                ]
            );
        } catch ( \Error $error ) {
            wp_send_json_error( [ 'mess' => $error->getMessage() ] );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }

    public function get_template_library() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        $templates = get_option('o100_template_library', []);
        if ( ! is_array( $templates ) ) {
            $templates = [];
        }
        wp_send_json_success(['templates' => $templates]);
    }

    public function save_to_template_library() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : 'Unnamed Template';
        $mjml = isset($_POST['mjml']) ? wp_unslash($_POST['mjml']) : '';
        
        $templates = get_option('o100_template_library', []);
        if ( ! is_array( $templates ) ) {
            $templates = [];
        }
        
        $id = uniqid('tpl_');
        array_unshift($templates, [
            'id'   => $id,
            'name' => $name,
            'mjml' => $mjml,
            'date' => current_time('mysql')
        ]);
        
        update_option('o100_template_library', $templates);
        wp_send_json_success(['id' => $id, 'templates' => $templates]);
    }

    public function delete_from_template_library() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_frontend_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        
        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        $templates = get_option('o100_template_library', []);
        if ( ! is_array( $templates ) ) {
            $templates = [];
        }
        
        $templates = array_values(array_filter($templates, function($tpl) use ($id) {
            return isset($tpl['id']) && $tpl['id'] !== $id;
        }));
        
        update_option('o100_template_library', $templates);
        wp_send_json_success(['templates' => $templates]);
    }
}

