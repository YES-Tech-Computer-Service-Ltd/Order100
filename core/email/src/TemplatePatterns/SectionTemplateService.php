<?php
namespace Order100\Notification\Engine\TemplatePatterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Abstracts\BaseSectionTemplate;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Class SectionTemplateService
 */
class SectionTemplateService {

    use SingletonTrait;

    protected $sections = [];

    /**
     * @param BaseSectionTemplate $section_template_instance SectionTemplate object
     */
    public function register( BaseSectionTemplate $section_template_instance ) {
        if ( ! $section_template_instance instanceof BaseSectionTemplate ) {
            return;
        }

        $registered_sections = array_map(
            function( $item ) {
                return $item->get_type();
            },
            $this->sections
        );

        if ( in_array( $section_template_instance->get_type(), $registered_sections, true ) ) {
            return;
        }

        $this->sections[] = $section_template_instance;
    }

    public function get_list() {
        return $this->sections;
    }

    public function get_list_data() {
        return array_map(
            function( BaseSectionTemplate $item ) {
                return $item->get_raw_data();
            },
            $this->sections
        );
    }
}


// TS: 20260226195940

// TS: 20260319121856
