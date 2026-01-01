<?php

namespace Order100\Notification\Engine\Integrations\AdminAndSiteEnhancements;

use Order100\Notification\Engine\Utils\SingletonTrait;

/**
 * Admin and Site Enhancements (ASE)
 * Link: https://wordpress.org/plugins/admin-site-enhancements/
 *
 * AdminAndSiteEnhancements
 * * @method static AdminAndSiteEnhancements get_instance()
 */
class AdminAndSiteEnhancements {
    use SingletonTrait;

    private function __construct() {
        if ( self::is_3rd_party_installed() ) {
            $this->initialize_hooks();
        }
    }

    public static function is_3rd_party_installed() {
        return class_exists( 'ASENHA\Classes\Activation' );
    }

    private function initialize_hooks() {
        add_action( 'admin_head', [ $this, 'o100_custom_wp_content_width' ], 99 );
    }

    public function o100ne_custom_wp_content_width() {
        $current_screen = get_current_screen();
        $ase_options    = get_option( ASENHA_SLUG_U, [] );

        if ( $current_screen->id !== 'o100_page_o100ne-settings' || ! isset( $ase_options['wider_admin_menu'] ) || ! $ase_options['wider_admin_menu'] ) {
            return;
        }

        $admin_menu_width = $ase_options['admin_menu_width'] ?? '160px';

        ?>
        <style>
            #wpcontent {
                margin-left: <?php echo esc_attr( $admin_menu_width ); ?> !important;
            }

            .o100ne-header__navbar, .o100ne-footer{
                width: calc(100% - <?php echo esc_attr( $admin_menu_width ); ?>) !important;
            }
        </style>
        <?php
    }
}



