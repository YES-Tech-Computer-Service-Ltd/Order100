<?php

namespace Order100\Notification\Engine\Shortcodes;

use Order100\Notification\Engine\Shortcodes\OrderDetails\OrderDetailsShortcodes;
use Order100\Notification\Engine\Shortcodes\ShippingShortcodes;
use Order100\Notification\Engine\Shortcodes\BillingShortcodes;
use Order100\Notification\Engine\Shortcodes\PaymentsShortcodes;
use Order100\Notification\Engine\Shortcodes\NewUsersShortcodes;
use Order100\Notification\Engine\Shortcodes\ResetPasswordsShortcodes;
use Order100\Notification\Engine\Shortcodes\LegacyCustomShortcodes;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * @method: static ShortcodesLoader get_instance()
 */
class ShortcodesLoader {

    use SingletonTrait;

    private $shortcode_intances = [];

    protected function __construct() {

        $this->shortcode_intances = [
            CommonShortcodes::get_instance(),
            OrderDetailsShortcodes::get_instance(),
            HookShortcodes::get_instance(),
            ShippingShortcodes::get_instance(),
            BillingShortcodes::get_instance(),
            PaymentsShortcodes::get_instance(),
            NewUsersShortcodes::get_instance(),
            ResetPasswordsShortcodes::get_instance(),
            OrderMetaShortcodes::get_instance(),
            LegacyCustomShortcodes::get_instance(),

            /**
             * @since 4.0.6
             */
            RefundShortcodes::get_instance(),
        ];

        do_action( 'o100_register_shortcodes', $this );

        foreach ( o100ne_get_emails() as $email ) {

            do_action( 'o100_' . O100NE_ALL_EMAILS . '_register_shortcodes', $email );
            do_action( 'o100_' . $email->get_id() . '_register_shortcodes', $email );

            if ( in_array( O100NE_NON_ORDER_EMAILS, $email->email_types, true ) ) {
                do_action( 'o100_' . O100NE_NON_ORDER_EMAILS . '_register_shortcodes', $email );
                continue;
            }
            if ( in_array( O100NE_WITH_ORDER_EMAILS, $email->email_types, true ) ) {
                do_action( 'o100_' . O100NE_WITH_ORDER_EMAILS . '_register_shortcodes', $email );
                continue;
            }

            if ( in_array( O100NE_GLOBAL_HEADER_FOOTER_ID, $email->email_types, true ) ) {
                do_action( 'o100_' . O100NE_GLOBAL_HEADER_FOOTER_ID . '_register_shortcodes', $email );
                continue;
            }
        }
    }
}

