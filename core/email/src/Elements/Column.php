<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Column Elements
 */
class Column extends BaseElement {

    use SingletonTrait;

    protected static $type = 'column';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $width = 5, $attributes = [] ) {
        return [
            'id'                    => uniqid(),
            'type'                  => self::$type,
            'group'                 => 'hidden',
            // only appears inside column_layout
                        'available' => true,
            'children'              => isset( $attributes['children'] ) ? $attributes['children'] : [],

            'data'                  => [
                'width' => isset( $attributes['width'] ) ? $attributes['width'] : $width,
            ],
        ];
    }
}



// TS: 20260219142230

// TS: 20260224142535
