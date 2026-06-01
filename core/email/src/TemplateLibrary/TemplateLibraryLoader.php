<?php

namespace Order100\Notification\Engine\TemplateLibrary;

use Order100\Notification\Engine\Abstracts\BaseTemplate;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Loader that auto-discovers and registers Template Library templates
 * from the Templates directory. Add new template classes under
 * Templates/{EmailType}/{TemplateName}.php and they will be registered automatically.
 *
 * @method static TemplateLibraryLoader get_instance()
 */
class TemplateLibraryLoader {
    use SingletonTrait;

    private function __construct() {
        $this->load_templates_from_directory();
    }

    /**
     * Recursively scan a directory for PHP files and register template classes.
     *
     * @return void
     */
    protected function load_templates_from_directory() {
        $template_library_service = TemplateLibraryService::get_instance();
        $templates_base_path      = O100NE_PLUGIN_PATH . '/src/TemplateLibrary/Templates';
        $templates_base_namespace = 'Order100\Notification\Engine\\TemplateLibrary\\Templates';

        if ( ! is_dir( $templates_base_path ) ) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $templates_base_path, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $fileinfo ) {
            if ( ! $fileinfo->isFile() || $fileinfo->getExtension() !== 'php' ) {
                continue;
            }

            $relative_path = substr( $fileinfo->getPathname(), strlen( $templates_base_path ) + 1 );
            $relative_path = str_replace( '\\', '/', $relative_path );
            $class_name    = str_replace( '/', '\\', pathinfo( $relative_path, PATHINFO_DIRNAME ) . '/' . pathinfo( $relative_path, PATHINFO_FILENAME ) );
            $class_name    = trim( $class_name, '\\' );
            $full_class    = $templates_base_namespace . '\\' . $class_name;

            // Require file first — Composer classmap may not include newly added templates
            require_once $fileinfo->getPathname();

            if ( class_exists( $full_class ) && is_subclass_of( $full_class, BaseTemplate::class, true ) ) {
                $template_library_service->register( $full_class::get_instance() );
            }
        }

        do_action( 'o100_register_template_library', $template_library_service );
    }
}




// TS: 20260127154407

// TS: 20260220173750

// TS: 20260428035710

// TS: 20260601122705
