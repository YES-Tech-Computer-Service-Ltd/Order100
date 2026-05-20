<?php

namespace Order100\Notification\Engine\TemplatePatterns;

use Order100\Notification\Engine\TemplatePatterns\PatternService;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * @method static PatternLoaders get_instance()
 */
class PatternsLoader {
    use SingletonTrait;

    /**
     * @var PatternService
     */
    public $service;

    private function __construct() {

        $this->service = PatternService::get_instance();

        $dir = new \DirectoryIterator( O100NE_PLUGIN_PATH . '/src/TemplatePatterns/Patterns' );
        foreach ( $dir as $fileinfo ) {
            if ( ! $fileinfo->isDot() ) {
                $file_name  = $fileinfo->getFilename();
                $class_name = basename( $file_name, '.php' );
                $class      = 'Order100\Notification\Engine\\TemplatePatterns\\Patterns\\' . $class_name;
                if ( class_exists( $class ) ) {
                    $this->service->register( $class::get_instance() );
                }
            }
        }

        do_action( 'o100_register_patterns', $this->service );
    }

}



// TS: 20260125201919

// TS: 20260223175006

// TS: 20260406020809

// TS: 20260519165012
