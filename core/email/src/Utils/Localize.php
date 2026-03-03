<?php

namespace Order100\Notification\Engine\Utils;

defined( 'ABSPATH' ) || exit;

use Order100\Notification\Engine\Models\TemplateModel;

/**
 * Localize Classes
 */
class Localize {

    public static function get_list_orders() {
        if ( o100ne_is_wc_installed() ) {
            $data_orders   [] = [
                'id'           => 'sample_order',
                'order_number' => 'sample_order',
                'email'        => '',
                'first_name'   => '',
                'last_name'    => '',
                'title'        => esc_html__( 'Sample order', 'order100' ),
            ];

            $wc_list_orders = wc_get_orders(
                [
                    'limit' => 50,
                ]
            );

            foreach ( $wc_list_orders as $order ) {
                if ( method_exists( $order, 'get_id' ) && method_exists( $order, 'get_order_number' ) ) {
                    $order_id     = strval( $order->get_id() );
                    $order_number = $order->get_order_number();
                    $email        = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : '';
                    $first_name   = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : '';
                    $last_name    = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : '';
                    $title        = $order_number . ' - ' . $first_name . $last_name . ' (' . ( $email ? $email : __( 'Unknown', 'order100' ) ) . ')';

                    $data_orders[] = [
                        'id'           => $order_id,
                        'order_number' => $order_number,
                        'email'        => $email,
                        'first_name'   => $first_name,
                        'last_name'    => $last_name,
                        'title'        => $title,
                    ];
                }
            }
        }//end if
        return $data_orders;
    }

    public static function get_social_icons_data() {

        $socials             = [
            'behance',
            'discord',
            'dribble',
            'facebook',
            'github',
            'google',
            'instagram',
            'linkedin',
            'medium',
            'messenger',
            'pinterest',
            'reddit',
            'skype',
            'snapchat',
            'spotify',
            'telegram',
            'tiktok',
            'twitch',
            'twitter',
            'viber',
            'vimeo',
            'website',
            'wechat',
            'whatsapp',
            'youtube',
            'zillow',
        ];
        $resource_prefix_url = O100NE_PLUGIN_URL . 'assets/images/social-icons/';
        $themes              = [ 'colorful', 'line-dark', 'line-light', 'solid-dark', 'solid-light' ];
        $themes_pascal       = array_map( [ self::class, 'kebab_to_pascal' ], $themes );
        $images              = [];
        foreach ( $socials as $social ) {
            $pngs = [];
            foreach ( $themes as $theme ) {
                $theme_pascal = self::kebab_to_pascal( $theme );
                $file_name    = $theme . '.png';
                $file_url     = $resource_prefix_url . $social . '/' . $file_name;
                $pngs[]       = [
                    'theme' => $theme_pascal,
                    'src'   => $file_url,
                ];
            }
            $images[] = [
                'name' => $social,
                'data' => $pngs,
            ];
        }

        return [
            'themes' => $themes_pascal,
            'images' => $images,
        ];
    }

    public static function get_global_headers_footers() {
        $template_model         = TemplateModel::get_instance();
        $global_headers_footers = $template_model->get_global_header_and_footer();
        return $global_headers_footers;
    }

    public static function get_activated_addons() {
        $result = apply_filters( 'o100_activated_addons', [] );

        return $result;
    }

    private static function kebab_to_pascal( $input ) {
        // Remove hyphens and split the string into words
        $words = explode( '-', $input );
        // Capitalize the first letter of each word
        $pascal_words = array_map( 'ucfirst', $words );
        // Join the words back together
        $pascal_case = implode( '', $pascal_words );

        return $pascal_case;
    }
}


// TS: 20260123124308

// TS: 20260125161943

// TS: 20260201151221

// TS: 20260302233443
