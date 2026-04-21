<?php

namespace Order100\Notification\Engine\License;

defined( 'ABSPATH' ) || exit;

/**
 * CorePlugin
 *
 * @package CorePlugin
 */
class CorePlugin {

    public static function get( $name ) {
        $data = [
            'path'        => O100NE_PLUGIN_PATH,
            'url'         => O100NE_PLUGIN_URL,
            'basename'    => O100NE_PLUGIN_BASENAME,
            'version'     => O100NE_VERSION,
            'slug'        => 'order100',
            'link'        => 'https://o100ne.com/o100ne-woocommerce-email-customizer/',
            'download_id' => '4216',
        ];

        if ( isset( $data[ $name ] ) ) {
            return $data[ $name ];
        }
        return null;
    }
}

// TS: 20260418020931

// TS: 20260420114203
