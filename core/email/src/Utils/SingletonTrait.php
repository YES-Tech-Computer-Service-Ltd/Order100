<?php
namespace Order100\Notification\Engine\Utils;

trait SingletonTrait {
    private static $instance;

    public static function get_instance( ...$args ) {
        $class = get_called_class();
        if ( ! $class::$instance ) {
            $class::$instance = new $class( ...$args );
        }

        return $class::$instance;
    }

    /** Singletons should not be cloneable. */
    protected function __clone() { }

    /** Singletons should not be restorable from strings. */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize a singleton.' );
    }
}




// TS: 20260123215546

// TS: 20260318022011

// TS: 20260406171431
