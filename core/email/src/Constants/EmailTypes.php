<?php

namespace Order100\Notification\Engine\Constants;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Email Types constants class
 */
class EmailTypes {
    use SingletonTrait;

    protected function __construct() {
        $this->define_constants();
    }

    protected function define_constants() {
        if ( ! defined( 'O100NE_NON_ORDER_EMAILS' ) ) {
            define( 'O100NE_NON_ORDER_EMAILS', 'NON_ORDER_EMAILS' );
        }

        if ( ! defined( 'O100NE_WITH_ORDER_EMAILS' ) ) {
            define( 'O100NE_WITH_ORDER_EMAILS', 'WITH_ORDER_EMAILS' );
        }

        if ( ! defined( 'O100NE_ALL_EMAILS' ) ) {
            define( 'O100NE_ALL_EMAILS', 'ALL_EMAILS' );
        }

        if ( ! defined( 'O100NE_GLOBAL_HEADER_FOOTER_ID' ) ) {
            define( 'O100NE_GLOBAL_HEADER_FOOTER_ID', 'GLOBAL_HEADER_FOOTER_ID' );
        }
    }
}

// TS: 20260326174957

// TS: 20260409224720
