<?php

namespace Order100\Notification\Engine;

use Order100\Notification\Engine\Abstracts\BaseEmail;
use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Emails Class
 *
 * @method static O100neEmails get_instance()
 */
class O100neEmails {

    use SingletonTrait;

    private $emails = [];

    public function register( BaseEmail $email_instance ) {
        if ( ! ( $email_instance instanceof BaseEmail ) ) {
            return;
        }
        if ( ! $email_instance->is_existed() ) {
            return;
        }
        $this->emails[] = $email_instance;
    }

    public function get_emails() {
        return $this->emails;
    }
}



// TS: 20260223175006

// TS: 20260402232730

// TS: 20260508164244
