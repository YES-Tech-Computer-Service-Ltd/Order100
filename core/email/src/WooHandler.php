<?php

namespace Order100\Notification\Engine;

use Order100\Notification\Engine\Utils\Helpers;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Handles WooCommerce email preview
 *
 * @method static WooHandler get_instance()
 */
class WooHandler {

    use SingletonTrait;

    protected function __construct() {
        add_filter( 'woocommerce_prepare_email_for_preview', [ $this, 'display_preview_notice' ] );
        add_filter( 'woocommerce_mail_content', [ $this, 'handle_default_preview_content' ] );
        // Add settings to WooCommerce email options section
        add_filter( 'woocommerce_get_settings_email', [ $this, 'add_settings' ], 10, 2 );
        add_filter(
            'woocommerce_get_settings_advanced',
            function( $settings ) {
                foreach ( $settings as $index => $setting ) {
                    if ( $setting['id'] === 'woocommerce_feature_block_email_editor_enabled' ) {
                        $introduction_text           = sprintf( __( 'You can customize WooCommerce emails with <a href="%s" target="_blank">O100ne - WooCommerce Email Customizer</a>', 'order100' ), esc_url( admin_url( 'admin.php?page=o100ne-settings#' ), 'order100' ) );
                        $settings[ $index ]['desc'] .= '<br/><br/>' . $introduction_text . '<br/>';
                    }
                }
                return $settings;
            }
        );
    }

    public function display_preview_notice( $email ) {

        if ( ! ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'preview-mail' ) ) ) {
            return $email;
        }

        if ( isset( $_GET['preview_woocommerce_mail'] ) && ! Helpers::is_true( sanitize_text_field( wp_unslash( $_GET['preview_woocommerce_mail'] ) ) ) ) {
            return $email;
        }

        if ( isset( $_GET['rest_route'] ) && $_GET['rest_route'] === '/wc-admin-email/settings/email/send-preview' ) {
            return $email;
        }
        if ( ! isset( $email->id ) ) {
            return $email;
        }
        $email_id = $email->id;

        $o100ne_template = new O100neTemplate( $email_id );

        if ( ! $o100ne_template->is_exists() ) {
            return $email;
        }

        if ( ! $o100ne_template->is_enabled() ) {
            return $email;
        }

        add_filter( 'o100_previewing_template_is_o100ne_template', '__return_true' );
        ob_start();
        ?>
            <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; text-align: center;">
                <div style="background-color: #f7f4fa; padding: 12px 12px 24px 12px; border-radius: 4px; margin-bottom: 20px;">
                    <h2 style="color: <?php echo esc_attr( O100NE_COLOR_WC_DEFAULT ); ?>; font-size: 24px; margin-bottom: 8px;"><?php esc_html_e( 'O100ne Template Preview', 'order100' ); ?></h2>
                    <p style="color:rgb(110, 110, 110); font-size: 14px; margin-bottom: 24px;"><?php esc_html_e( 'This is one of your WooCommerce email templates customized with O100ne. You can modify its colors, layout, and content in the O100ne editor.', 'order100' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=o100ne-settings#/customizer/?template=' . $email_id ) ); ?>" target="_blank" style="display: inline-block; background-color: <?php echo esc_attr( O100NE_COLOR_WC_DEFAULT ); ?>; color: #fff; font-size: 12px; padding: 8px 12px; border-radius: 3px; text-decoration: none;"><?php esc_html_e( 'Customized Template', 'order100' ); ?></a>
                </div>
            </div>
        <?php
        $content = ob_get_clean();
        o100ne_kses_post_e( $content );
        return $email;
    }

    public function handle_default_preview_content( $content ) {

        if ( ! ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'preview-mail' ) ) ) {
            return $content;
        }

        if ( isset( $_GET['preview_woocommerce_mail'] ) && ! Helpers::is_true( sanitize_text_field( wp_unslash( $_GET['preview_woocommerce_mail'] ) ) ) ) {
            return $content;
        }

        if ( isset( $_GET['rest_route'] ) && $_GET['rest_route'] === '/wc-admin-email/settings/email/send-preview' ) {
            return $content;
        }

        if ( apply_filters( 'o100_previewing_template_is_o100ne_template', false ) ) {
            return '';
        }
        return $content;
    }

    /**
     * Add O100ne settings to WooCommerce email options.
     *
     * @param array  $settings        WooCommerce email settings.
     * @param string $current_section Current settings section.
     * @return array Modified settings.
     */
    public function add_settings( $settings, $current_section ) {
        // Only add to the email options section (empty section)
        if ( $current_section !== '' ) {
            return $settings;
        }

        $o100ne_settings = [
            [
                'title' => __( 'WooCommerce Email Designer', 'order100' ),
                'type'  => 'title',
                'id'    => 'o100_email_designer',
            ],
            [
                'title'    => __( 'Customize WooCommerce Emails', 'order100' ),
                'desc'     => '',
                'id'       => 'woocommerce_customizer_emails',
                'type'     => 'o100_button',
                'desc_tip' => true,
            ],
            [
                'type' => 'sectionend',
                'id'   => 'o100_email_designer',
            ],
        ];

        // Add custom button HTML
        add_action( 'woocommerce_admin_field_o100ne_button', [ $this, 'output_button' ] );

        $settings = array_merge( $settings, $o100ne_settings );

        return $settings;
    }

    /**
     * Output the custom button HTML
     *
     * @param array $value Button field settings.
     */
    public function output_button( $value ) {
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
            </th>
            <td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
                <button type="button" 
                        class="button"
                        id="<?php echo esc_attr( $value['id'] ); ?>"
                        onclick="window.open('<?php echo esc_url( admin_url( 'admin.php?page=o100ne-settings' ) ); ?>', '_blank')"
                >
                    <?php esc_html_e( 'Open O100ne', 'order100' ); ?>
                </button>
                <p class="description"><?php esc_html_e( 'Make Woocommerce Emails match your brand. ', 'order100' ); ?><a href="https://o100ne.com/o100ne-woocommerce-email-customizer/" target="_blank"><?php esc_html_e( 'O100ne - WooCommerce Email Customizer', 'order100' ); ?></a> <?php esc_html_e( ' plugin by ', 'order100' ); ?> <a href="https://o100ne.com/" target="_blank"><?php esc_html_e( 'O100ne', 'order100' ); ?></a>.</p>
            </td>
        </tr>
        <?php
    }
}



