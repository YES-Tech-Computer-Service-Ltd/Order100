<?php

namespace Order100\Notification\Engine\Constants;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * O100ne Styles constants class
 */
class O100neStyles {
    use SingletonTrait;

    protected function __construct() {
        $this->define_constants();
    }

    protected function define_constants() {
        if ( ! defined( 'O100NE_COLOR_TEXT_DEFAULT' ) ) {
            define( 'O100NE_COLOR_TEXT_DEFAULT', '#636363' );
        }

        if ( ! defined( 'O100NE_COLOR_BACKGROUND_DEFAULT' ) ) {
            define( 'O100NE_COLOR_BACKGROUND_DEFAULT', '#f9f9f9' );
        }

        if ( ! defined( 'O100NE_COLOR_WC_DEFAULT' ) ) {
            define( 'O100NE_COLOR_WC_DEFAULT', '#873EFF' );
        }

        if ( ! defined( 'O100NE_COLOR_BORDER_DEFAULT' ) ) {
            define( 'O100NE_COLOR_BORDER_DEFAULT', '#e5e5e5' );
        }

        if ( ! defined( 'O100NE_DEFAULT_FAMILY' ) ) {
            define( 'O100NE_DEFAULT_FAMILY', 'Helvetica,Roboto,Arial,sans-serif' );
        }

        if ( ! defined( 'O100NE_COLOR_CONTENT_BACKGROUND_DEFAULT' ) ) {
            define( 'O100NE_COLOR_CONTENT_BACKGROUND_DEFAULT', '#ffffff' );
        }

        if ( ! defined( 'O100NE_COLOR_CONTENT_TEXT_DEFAULT' ) ) {
            define( 'O100NE_COLOR_CONTENT_TEXT_DEFAULT', '#000000' );
        }

        if ( ! defined( 'O100NE_COLOR_TITLE_DEFAULT' ) ) {
            define( 'O100NE_COLOR_TITLE_DEFAULT', '#873EFF' );
        }
    }
}

// TS: 20260106125033

// TS: 20260108164045

// TS: 20260120224658
