<?php
namespace Order100\Notification\Engine\Migrations;

use Order100\Notification\Engine\Migrations\MigrationHelper;
use Order100\Notification\Engine\Utils\SingletonTrait;
use Order100\Notification\Engine\Utils\Logger;

/**
 * Database migration Main class
 */
class MainMigration {
    use SingletonTrait;

    private $logger;

    private $old_version;
    private $new_version;

    const CORE_MIGRATIONS = [
        '4.0.0' => '\Order100\Notification\Engine\Migrations\Versions\Ver_4_0_0',
        '4.0.7' => '\Order100\Notification\Engine\Migrations\Versions\Ver_4_0_7',
        '4.1.0' => '\Order100\Notification\Engine\Migrations\Versions\Ver_4_1_0',
    ];

    private function __construct() {
        if ( ! defined( 'O100NE_VERSION' ) ) {
            return;
        }
        $this->logger      = new Logger();
        $this->new_version = MigrationHelper::format_version_number( O100NE_VERSION );

        $old_version = get_option( 'o100_version' );
        // O100ne's version from db
        $this->old_version = MigrationHelper::format_version_number( $old_version ?? '3.9.9' );
    }

    public function migrate( $skip_check_migration = false ) {
        $args = [
            'post_type'      => 'o100_template',
            'post_status'    => 'any',
            'posts_per_page' => -1,
        ];

        $query = new \WP_Query( $args );

        $has_o100ne_template = $query->have_posts();
        if ( ! $skip_check_migration && ( empty( $this->old_version ) && ! $has_o100ne_template ) ) {
            $this->logger->log( 'O100ne is freshly installed, no migrations needed!' );
            return false;
        }

        $this->logger->log( '***** Start migration transaction *****' );
        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        try {
            $core_migrations = self::CORE_MIGRATIONS;
            $this->logger->log( 'Start core migrations' );

            if ( $skip_check_migration ) {
                $filtered_migrations = $core_migrations;
            } else {
                $filtered_migrations = MigrationHelper::filter_migrations( $core_migrations, $this->old_version, $this->new_version );
            }

            if ( ! empty( $filtered_migrations ) ) {
                MigrationHelper::perform_migrations( $filtered_migrations, $skip_check_migration );
                update_option( 'o100_version', $this->new_version );
                wp_cache_delete( 'o100_version', 'options' );
            }

            $this->logger->log( 'Finish core migrations' );

            do_action( 'o100_run_addon_migrations' );
            $wpdb->query( 'COMMIT' );

            return true;
        } catch ( \Exception $e ) {
            $wpdb->query( 'ROLLBACK' );
            $this->logger->log( "[Migration failed] {$e->getMessage()}" );
            return false;
        } finally {
            $this->logger->log( '***** End migration transaction *****' );
        }//end try
    }
}

