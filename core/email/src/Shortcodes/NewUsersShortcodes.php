<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Abstracts\BaseShortcode;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Utils\TemplateHelpers;

/**
 * @method: static NewUsersShortcodes get_instance()
 */
class NewUsersShortcodes extends BaseShortcode {
    use SingletonTrait;

    public $available_email_ids = [
        'customer_new_account',
    ];

    public function get_shortcodes() {
        $shortcodes   = [];
        $shortcodes[] = [
            'name'        => 'o100_user_new_password',
            'description' => __( 'User New Password', 'order100' ),
            'group'       => 'new_users',
            'callback'    => [ $this, 'o100ne_user_new_password' ],
        ];
        $shortcodes[] = [
            'name'        => 'o100_set_password_link',
            'description' => __( 'Set Password Link (For New Accounts)', 'order100' ),
            'attributes'  => [
                'text_link' => __( 'Click here to set your new password.', 'woocommerce' ),
            ],
            'group'       => 'new_users',
            'callback'    => [ $this, 'o100ne_set_password_link' ],
        ];

        return $shortcodes;
    }

    public function o100ne_user_new_password( $data ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            return __( 'G(UAM1(eIX#G', 'order100' );
        }

        return ! empty( $render_data['email']->user_pass ) ? $render_data['email']->user_pass : '';
    }

    public function o100ne_set_password_link( $data, $shortcode_atts = [] ) {

        $render_data = isset( $data['render_data'] ) ? $data['render_data'] : [];

        $is_placeholder = isset( $data['is_placeholder'] ) ? $data['is_placeholder'] : false;

        $text_link = isset( $shortcode_atts['text_link'] ) ? $shortcode_atts['text_link'] : TemplateHelpers::get_content_as_placeholder( 'text_link', __( 'Click here to set your new password.', 'order100' ), $is_placeholder );

        if ( ! empty( $render_data['is_sample'] ) ) {
            /**
             * Is sample order
             */
            $url = wc_customer_edit_account_url();

            return wp_kses_post( "<a href='$url'> $text_link </a>" );
        }

        if ( isset( $render_data['set_password_url'] ) && ! empty( $render_data['set_password_url'] ) ) {

            $url = $render_data['set_password_url'];

            return wp_kses_post( "<a href='$url'> $text_link </a>" );
        }

        return '';
    }
}
