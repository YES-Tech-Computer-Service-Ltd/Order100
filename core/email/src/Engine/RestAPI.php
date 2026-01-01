<?php
namespace Order100\Notification\Engine\Engine;

use Order100\Notification\Engine\Controllers\MigrationController;
use Order100\Notification\Engine\Controllers\RevisionController;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Controllers\SettingController;
use Order100\Notification\Engine\Controllers\TemplateController;
use Order100\Notification\Engine\Controllers\ProductController;
use Order100\Notification\Engine\Controllers\AddonController;
use Order100\Notification\Engine\Controllers\TemplateLibraryController;

/**
 * O100ne Rest API
 */
class RestAPI {
    use SingletonTrait;

    /**
     * Hooks Initialization
     *
     * @return void
     */
    protected function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_o100ne_endpoints' ] );
    }

    /**
     * Add O100ne Endpoints
     */
    public function add_o100ne_endpoints() {
        TemplateController::get_instance();
        SettingController::get_instance();
        RevisionController::get_instance();
        MigrationController::get_instance();
        ProductController::get_instance();
        AddonController::get_instance();
        TemplateLibraryController::get_instance();
        do_action( 'o100_init_rest_controllers' );
    }
}
