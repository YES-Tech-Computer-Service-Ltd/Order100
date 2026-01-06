<?php

namespace Order100\Notification\Engine\PreviewEmail;

use Order100\Notification\Engine\Utils\SingletonTrait;

use Order100\Notification\Engine\PreviewEmail\Integration\WcSubscriptions;

/**
 *
 * @method static PreviewEmailsLoader get_instance()
 */
class PreviewEmailsLoader {
    use SingletonTrait;

    protected function __construct() {
        // TODO: inject hooks for addon
        if ( class_exists( 'WC_Subscriptions' ) ) {
            // WcSubscriptions::get_instance();
        }
    }
}



// TS: 20260106125033
