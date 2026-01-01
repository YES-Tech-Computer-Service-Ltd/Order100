<?php

namespace Order100\Notification\Engine\TemplatePatterns;

use Order100\Notification\Engine\TemplatePatterns\SectionTemplateService;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * @method static SectionTemplatesLoader get_instance()
 */
class SectionTemplatesLoader {

    use SingletonTrait;

    /**
     * @var SectionTemplateService
     */
    public $service;

    private function __construct() {

        $this->service = SectionTemplateService::get_instance();

        $dir = new \DirectoryIterator( O100NE_PLUGIN_PATH . '/src/TemplatePatterns/SectionTemplates' );
        foreach ( $dir as $fileinfo ) {
            if ( ! $fileinfo->isDot() ) {
                $file_name  = $fileinfo->getFilename();
                $class_name = basename( $file_name, '.php' );
                $class      = 'Order100\Notification\Engine\\TemplatePatterns\\SectionTemplates\\' . $class_name;
                if ( __CLASS__ === $class ) {
                    continue;
                }
                if ( class_exists( $class ) ) {
                    $this->service->register( $class::get_instance() );
                }
            }
        }

        do_action( 'o100_register_template_sections', $this->service );
    }
}

