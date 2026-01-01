<?php

namespace Order100\Notification\Engine\Integrations\DHL;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * DHL
 * * @method static DHLIntegration get_instance()
 */
class DHLIntegration {
    use SingletonTrait;

    protected function __construct() {
        if ( ! class_exists( 'PR_DHL_WC' ) ) {
            return;
        }

        add_action(
            'o100_register_shortcodes',
            function() {
                DHLShortcodes::get_instance();
            }
        );
    }
}

