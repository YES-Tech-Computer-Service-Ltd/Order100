<?php
namespace Order100\Notification\Engine;

use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Emails\EmailsLoader;
use Order100\Notification\Engine\Engine\ActDeact;
use Order100\Notification\Engine\Engine\Backend\SettingsPage;
use Order100\Notification\Engine\Engine\RestAPI;
use Order100\Notification\Engine\PostTypes\TemplatePostType;
use Order100\Notification\Engine\Shortcodes\ShortcodesLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\TemplatePatterns\PatternsLoader;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplatesLoader;
use Order100\Notification\Engine\PreviewEmail\PreviewEmailsLoader;
use Order100\Notification\Engine\TemplateLibrary\TemplateLibraryLoader;

/**
 * O100ne Plugin Initializer
 *
 * @method static Initialize get_instance()
 */
class Initialize {

    use SingletonTrait;

    /**
     * The Constructor that load the engine classes
     */
    protected function __construct() {
        I18n::get_instance();

        /**
         * Handle init core
         * Emails, Templates, Elements, Shortcodes, Integrations
         * $hook_name => $priority
         */
        $initialization_core_hooks = [
            apply_filters( 'o100_temp_init_hook_name', 'init' ) => 10,
            // Integrate for Mastercard Gateway plugin
            'woocommerce_api_mastercard_gateway' => 10,
        ];
        foreach ( $initialization_core_hooks as $hook => $priority ) {
            add_action( $hook, [ $this, 'init_core' ], $priority ?? 10 );
        }

        add_action( 'init', [ $this, 'init_modules' ] );
    }

    public function init_core() {
        require_once O100NE_PLUGIN_PATH . 'src/Functions.php';
        do_action( 'o100_init_start' );

        /**
         * Core Integrations (disabled — no external integrations needed)
         */

        EmailsLoader::get_instance();
        ElementsLoader::get_instance();
        ShortcodesLoader::get_instance();
    }

    public function init_modules() {

        $version_current        = O100NE_VERSION;
        $version_old            = get_option( 'o100_version' );
        $version_current_backup = get_option( 'o100_version_backup' );

        if ( $version_current !== $version_old ) {
            if ( $version_current_backup !== $version_current ) {
                \Order100\Notification\Engine\Migrations\MainMigration::get_instance()->migrate();

                update_option( 'o100_version', O100NE_VERSION );
                update_option( 'o100_version_backup', O100NE_VERSION );
            }
        }

        ActDeact::get_instance();

        WooHandler::get_instance();
        /**
         * Preview Email loader
         */

        PreviewEmailsLoader::get_instance();

        /**
         * Supported templates
         */
        SupportedPlugins::get_instance();

        /**
         * Core core filters
         */

        SectionTemplatesLoader::get_instance();
        PatternsLoader::get_instance();

        /**
         * Template Library: auto-discover pre-built templates
         */
        TemplateLibraryLoader::get_instance();

        /**
         * Initialize rest api
         */
        RestAPI::get_instance();

        /**
         * Initialize pages
         */
        SettingsPage::get_instance();

        /**
         * WooCommerce email settings integration (admin only)
         * Adds "Custom Template" column to WooCommerce > Settings > Emails
         */
        if ( is_admin() ) {
            require_once O100NE_PLUGIN_PATH . 'src/Admin/WCEmailColumnIntegration.php';
            \Order100\Notification\Engine\Admin\WCEmailColumnIntegration::get_instance();
        }

        TemplatePostType::get_instance();
        Ajax::get_instance();

        // Notices disabled — we use addon's own notice system

        do_action( 'o100_loaded' );
    }
}


// TS: 20260207122908

// TS: 20260306164119

// TS: 20260420114203

// TS: 20260504164724
