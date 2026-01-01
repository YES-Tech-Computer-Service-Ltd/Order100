<?php

namespace Order100\Notification\Engine\Emails;

use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\O100neEmails;

/**
 * EmailsLoader Class
 *
 * @method static EmailsLoader get_instance()
 */
class EmailsLoader {

    use SingletonTrait;

    private function __construct() {
        $this->init_hooks();
        $this->load_emails();
    }

    private function init_hooks() {
        add_action( 'o100_before_email_content', [ $this, 'before_email_content' ], 10, 1 );
        add_action( 'o100_after_email_content', [ $this, 'after_email_content' ], 10, 1 );

        /**
         * Email references hooks
         */
        add_filter( 'safe_style_css', [ $this, 'filter_safe_style_css' ], 10, 1 );
        add_filter( 'woocommerce_email_styles', [ $this, 'inject_custom_css' ] );
    }

    private function load_emails() {

        // Force WooCommerce to initialize its mailer so that wc()->mailer()->emails is populated.
        // This is crucial for REST API requests where WooCommerce might defer mailer initialization.
        if ( function_exists( 'wc' ) && ! empty( wc()->mailer() ) ) {
            wc()->mailer();
        }

        $o100ne_emails = O100neEmails::get_instance();

        $o100ne_emails->register( \Order100\Notification\Engine\Emails\NewOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CancelledOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerCancelledOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\FailedOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerFailedOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerOnHoldOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerProcessingOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerCompletedOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerRefundedOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerInvoice::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerNote::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerResetPassword::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerNewAccount::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\GlobalHeaderFooter::get_instance() );

        /**
         * POS emails, WC 9.9.3
         *
         * @since 4.0.6
         */
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerPOSCompletedOrder::get_instance() );
        $o100ne_emails->register( \Order100\Notification\Engine\Emails\CustomerPOSRefundedOrder::get_instance() );

        do_action( 'o100_register_emails', $o100ne_emails );
    }

    public function before_email_content( $template ) {
        include O100NE_PLUGIN_PATH . 'templates/emails/before-email-content.php';
    }

    public function after_email_content( $template ) {
        include O100NE_PLUGIN_PATH . 'templates/emails/after-email-content.php';
    }

    public function filter_safe_style_css( $default_array ) {
        $additional_allowed_css_attributes = [ 'display', 'background-repeat', 'word-wrap' ];
        return array_merge( $default_array, $additional_allowed_css_attributes );
    }

    public function inject_custom_css( $css = '' ) {
        $css             .= '.o100ne-element table { border-spacing: 0; }';
        $o100ne_settings = o100ne_settings();
        if ( ! boolval( $o100ne_settings['enable_custom_css'] ?? false ) ) {
            return $css;
        }
        $custom_css = isset( $o100ne_settings['custom_css'] ) ? $o100ne_settings['custom_css'] : '';
        $css       .= $custom_css;
        $css        = apply_filters( 'o100_email_styles', $css );
        return $css;
    }
}

