<?php
namespace Order100\Notification\Engine;

use Order100\Notification\Engine\Utils\SingletonTrait;

defined( 'ABSPATH' ) || exit;
/**
 * I18n Logic
 *
 * @method static I18n get_instance()
 */
class I18n {

    use SingletonTrait;

    private function __construct() {
        add_action( 'init', [ $this, 'load_plugin_text_domain' ] );
        add_filter( 'o100_translations', [ $this, 'get_translations' ] );
    }

    public static function load_plugin_text_domain() {
        if ( function_exists( 'determine_locale' ) ) {
            $locale = determine_locale();
        } else {
            $locale = is_admin() ? get_user_locale() : get_locale();
        }

        unload_textdomain( 'order100' );
        load_textdomain( 'order100', O100NE_PLUGIN_PATH . 'i18n/languages/o100ne-' . $locale . '.mo' );

        load_plugin_textdomain( 'order100', false, O100NE_PLUGIN_PATH . 'i18n/languages/' );
    }

    public function get_translations() {
        $translations = get_translations_for_domain( 'order100' );
        $messages     = [];

        $entries = $translations->entries;
        foreach ( $entries as $key => $entry ) {
            $messages[ $entry->singular ] = $entry->translations;
        }

        return [
            'locale_data' => [
                'messages' => $messages,
            ],
        ];
    }
}



// TS: 20260104141940
