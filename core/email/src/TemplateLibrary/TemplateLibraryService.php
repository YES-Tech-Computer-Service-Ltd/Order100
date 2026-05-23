<?php

namespace Order100\Notification\Engine\TemplateLibrary;

use Order100\Notification\Engine\Abstracts\BaseTemplate;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Service responsible for registering and exposing code-defined email templates
 * for the Template Library feature.
 */
class TemplateLibraryService {
    use SingletonTrait;

    /**
     * @var BaseTemplate[]
     */
    protected $templates = [];

    /**
     * Constructor.
     *
     * @return void
     */
    protected function __construct() {
    }

    /**
     * Programmatically register a template class.
     *
     * @param BaseTemplate $template Template instance.
     *
     * @return void
     */
    public function register( BaseTemplate $template ) {
        if ( ! $template instanceof BaseTemplate ) {
            return;
        }

        if ( in_array( $template, $this->templates, true ) ) {
            return;
        }

        $template_key = $template->get_id();

        $this->templates[ $template_key ] = $template;
    }

    /**
     * Get list of template summaries for given email type
     *
     * @param string $email_type O100ne email/template name. Example: 'new_order'.
     *
     * @return array[]
     */
    public function get_list( $email_type ) {
        $results = [];

        foreach ( $this->templates as $template ) {
            if ( $template->get_email_type() !== $email_type ) {
                continue;
            }

            $results[] = $template->get_template_data();
        }

        usort(
            $results,
            function( $a, $b ) {
                $pos_a = isset( $a['position'] ) ? (int) $a['position'] : 10;
                $pos_b = isset( $b['position'] ) ? (int) $b['position'] : 10;

                if ( $pos_a === $pos_b ) {
                    return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
                }

                return $pos_a - $pos_b;
            }
        );

        return $results;
    }

    /**
     * Get all registered template summaries (all email types).
     *
     * @return array[]
     */
    public function get_all_templates() {
        $results = [];
        foreach ( $this->templates as $template ) {
            $results[] = $template->get_template_data();
        }
        return $results;
    }
}


// TS: 20260416234013

// TS: 20260522231234
