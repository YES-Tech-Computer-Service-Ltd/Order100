<?php

namespace Order100\Notification\Engine\Emails;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Elements\ElementsLoader;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * GlobalHeaderFooter Class
 *
 * This is an O100ne element, not an email template. But its customizer page (for editing, saving, etc...) shares the same logic as email template customizer.
 *
 * @method static GlobalHeaderFooter get_instance()
 */
class GlobalHeaderFooter extends BaseEmail {
    use SingletonTrait;

    public $email_types = [ O100NE_GLOBAL_HEADER_FOOTER_ID ];

    protected function __construct() {
        $this->id        = 'o100_global_header_footer';
        $this->title     = __( 'Global header footer', 'order100' );
        $this->recipient = __( 'Global header footer recipient placeholder', 'order100' );
    }

    public function get_default_elements() {
        $default_elements = ElementsLoader::load_elements(
            [
                [
                    'type'       => 'Heading',
                    'attributes' => [
                        'rich_text' => __( 'Email Heading', 'order100' ),
                    ],
                ],
                [
                    'type' => 'SkeletonDivider',
                ],
                [
                    'type' => 'Footer',
                ],
            ]
        );

        return $default_elements;
    }

    public function get_all_elements() {
        return parent::get_elements();
    }

    public function get_template_file( $located, $template_name, $args ) {
    }

    public function get_template_path() {
    }
}


// TS: 20260112130652
