<?php
/**
 * O100ne licenses menu
 *
 * @package O100ne\Admin
 */

namespace Order100\Notification\Engine\O100neMenu;

defined( 'ABSPATH' ) || exit;

/**
 * Declare class
 */
class LicensesMenu {

    public static function render() {?>
        <script>
            document.querySelector("#wpbody-content").innerHTML = "";
        </script>
            <?php
            include plugin_dir_path( __FILE__ ) . 'views/licenses.php';
    }

    public static function load_data() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function enqueue_scripts() {
        wp_enqueue_style( 'o100ne-licenses', plugin_dir_url( __FILE__ ) . 'assets/css/licenses.css', [], '1.0' );
    }
}


// TS: 20260223122044

// TS: 20260504164724
