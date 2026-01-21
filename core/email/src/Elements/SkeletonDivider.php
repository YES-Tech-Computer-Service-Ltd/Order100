<?php
namespace Order100\Notification\Engine\Elements;

use Order100\Notification\Engine\Abstracts\BaseElement;
use Order100\Notification\Engine\Utils\SingletonTrait;
/**
 * SkeletonDivider Elements
 * This element is only used as a divider on email customizers, it should not be displayed on any test mail/ real mail.
 */
class SkeletonDivider extends BaseElement {

    use SingletonTrait;

    protected static $type = 'skeleton_divider';

    public $available_email_ids = [ O100NE_ALL_EMAILS ];

    public static function get_data( $attributes = [] ) {

        return [
            'id'        => uniqid(),
            'type'      => self::$type,
            'name'      => __( 'Skeleton Divider', 'order100' ),
            'group'     => 'hidden',
            'available' => true,
            'position'  => -1,
            'data'      => [],
        ];
    }
}


// TS: 20260121141931
