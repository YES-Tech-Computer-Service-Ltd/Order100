<?php

namespace Order100\Notification\Engine\Notices;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 *
 * @method static Ajax get_instance()
 */
class Ajax {
    use SingletonTrait;

    protected function __construct() {
        $this->init_hooks();
    }

    protected function init_hooks() {
        add_action( 'wp_ajax_o100ne_dismiss_suggest_addons_notice', [ $this, 'o100_dismiss_suggest_addons_notice' ] );
        add_action( 'wp_ajax_o100ne_dismiss_upgrade_notice', [ $this, 'o100_dismiss_upgrade_notice' ] );
    }

    public function o100ne_dismiss_suggest_addons_notice() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            // The Notice should comeback after 60 days
            update_option( 'o100_next_recommendation_suggest_addons_notice_time', time() + 60 * 60 * 24 * 60 );
            wp_send_json_success();
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }

    public function o100ne_dismiss_upgrade_notice() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'o100_nonce' ) ) {
            return wp_send_json_error( [ 'mess' => __( 'Verify nonce failed', 'order100' ) ] );
        }
        try {
            // The Notice should comeback after 60 days
            update_option( 'o100_next_recommendation_upgrade_notice_time', time() + 60 * 60 * 24 * 60 );
            wp_send_json_success();
        } catch ( \Error $error ) {
            o100ne_get_logger( $error );
        } catch ( \Exception $exception ) {
            o100ne_get_logger( $exception );
        }
    }
}



