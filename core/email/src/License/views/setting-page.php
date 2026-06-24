<?php
use Order100\Notification\Engine\License\License;
use Order100\Notification\Engine\License\LicensingPlugin;
use Order100\Notification\Engine\License\CorePlugin;

defined( 'ABSPATH' ) || exit;

$licensing_plugins = $this->get_licensing_plugins();
?>
<div class="o100ne-license-wrap">
    <div id="o100ne-license-root">
        <div class="o100ne-license-layout">
            <div class="o100ne-license-layout-primary">
                <div class="o100ne-license-layout-main">
                    <div class="o100ne-license-settings">
                        <?php
                        foreach ( $licensing_plugins as $_plugin ) {
                            $licensing_plugin = new LicensingPlugin( $_plugin['slug'] );
                            $license          = $licensing_plugin->get_license();
                            if ( $license->is_active() ) {
                                require CorePlugin::get( 'path' ) . 'views/license/information-card.php';
                            } else {
                                require CorePlugin::get( 'path' ) . 'views/license/activate-card.php';
                            }
                        }
                        do_action( 'o100_extra_plugins' );
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


