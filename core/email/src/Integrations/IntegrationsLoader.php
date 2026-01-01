<?php

namespace Order100\Notification\Engine\Integrations;

use Order100\Notification\Engine\Integrations\AdminAndSiteEnhancements\AdminAndSiteEnhancements;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Integrations\DHL\DHLIntegration;
use Order100\Notification\Engine\Integrations\F4ShippingPhoneAndEmailForWooCommerce\F4ShippingPhoneAndEmailForWooCommerce;

/**
 * IntegrationsLoader
 * * @method static IntegrationsLoader get_instance()
 */
class IntegrationsLoader {
    use SingletonTrait;

    protected function __construct() {
        RankMath::get_instance();
        F4ShippingPhoneAndEmailForWooCommerce::get_instance();
        AdminAndSiteEnhancements::get_instance();
        DHLIntegration::get_instance();
    }
}


