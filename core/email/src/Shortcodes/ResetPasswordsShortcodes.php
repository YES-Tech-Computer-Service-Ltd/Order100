<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Abstracts\BaseShortcode;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * @method: static ResetPasswordsShortcodes get_instance()
 */
class ResetPasswordsShortcodes extends BaseShortcode {
    use SingletonTrait;

    public $available_email_ids = [
        'customer_reset_password',
    ];

    public function get_shortcodes() {
        $shortcodes[] = [
            'name'        => 'o100_password_reset_link',
            'description' => __( 'Password Reset Link', 'order100' ),
            'group'       => 'reset_passwords',
            'callback'    => [ $this, 'o100ne_password_reset_link' ],
        ];

        return $shortcodes;
    }

    public function o100ne_password_reset_link( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $link_text = esc_html__( 'Click here to reset your password', 'woocommerce' );

        if ( ! empty( $render_data['is_sample'] ) || ( empty( $render_data['reset_key'] ) && empty( $render_data['email'] ) ) ) {
            /**
             * Is sample order
             */

            $link_reset = get_home_url() . '/my-account/lost-password';

            return wp_kses_post( "<a href='$link_reset'> $link_text </a>" );
        }

        $user = new \WP_User( intval( $render_data['email']->user_id ) );

        $link_reset = add_query_arg(
            [
                'key' => $render_data['reset_key'],
                'id'  => $user->ID,
            ],
            wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
        );

        return wp_kses_post( "<a href='$link_reset'> $link_text </a>" );
    }
}
