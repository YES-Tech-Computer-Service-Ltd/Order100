<?php
namespace Order100\Notification\Engine\TemplatePatterns\Patterns;

use Order100\Notification\Engine\Abstracts\BasePattern;
use Order100\Notification\Engine\Elements\Logo;
use Order100\Notification\Engine\TemplatePatterns\SectionTemplates\Header;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Header1 Elements
 */
class Header1 extends BasePattern {

    use SingletonTrait;

    public const TYPE = 'header_1';

    private function __construct() {
        $this->id       = uniqid();
        $this->section  = Header::TYPE;
        $this->position = 10;
        $this->name     = __( 'Header 1', 'order100' );
        $this->elements = [
            Logo::get_object_data(
                [
                    'background_color' => '#ffffff',
                    'align'            => 'center',
                    'src'              => 'https://images.wpbrandy.com/uploads/o100ne-footer-img-1.png',
                    'width'            => '195',
                    'url'              => '#',
                    'padding'          => [
                        'top'    => 30,
                        'right'  => 40,
                        'bottom' => 30,
                        'left'   => 40,
                    ],
                ]
            ),
        ];
    }
}

