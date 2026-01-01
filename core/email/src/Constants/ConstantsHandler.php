<?php

namespace Order100\Notification\Engine\Constants;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * ConstantsHandler
 */
class ConstantsHandler {
    use SingletonTrait;

    protected function __construct() {
        O100neStyles::get_instance();
        EmailTypes::get_instance();
        Sources::get_instance();
    }
}
