<?php
/**
 * WP-CLI Commands for A8C HR Tool Migration
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class A8C_HR_Migrate_Command {

    /**
     * Migrate data between storage backends
     *
     * ## OPTIONS
     *
     * <from>
     * : Source storage type (json, sqlite, wpdb)
     *
     * <to>
     * : Target storage type (json, sqlite, wpdb)
     *
     * [--dry-run]
     * : Show what would be migrated without making changes
     *
     * ## EXAMPLES
     *
     *     wp hr migrate json wpdb
     *     wp hr migrate sqlite json --dry-run
     *     wp hr migrate wpdb sqlite
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( $args, $assoc_args ) {
        $from = $args[0] ?? '';
        $to = $args[1] ?? '';
        $dry_run = isset( $assoc_args['dry-run'] );

        $valid_types = [ 'json', 'sqlite', 'wpdb' ];

        if ( ! in_array( $from, $valid_types ) || ! in_array( $to, $valid_types ) ) {
            WP_CLI::error( 'Invalid storage type. Valid types: ' . implode( ', ', $valid_types ) );
        }

        if ( $from === $to ) {
            WP_CLI::error( 'Source and target storage types cannot be the same.' );
        }

        // Include migration functionality from the existing migrate.php
        $migrate_command = $from . '-to-' . $to;

        WP_CLI::line( "Starting migration: {$from} → {$to}" );
        if ( $dry_run ) {
            WP_CLI::line( "DRY RUN MODE - No changes will be made" );
        }

        try {
            // Use the existing migrate.php functions
            $this->run_migration( $migrate_command, $dry_run );
        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }

    /**
     * Run the actual migration using existing migrate.php functions
     */
    private function run_migration( $command, $dry_run ) {
        // Include the migrate.php functions without running the CLI part
        $migrate_file = A8C_HR_PLUGIN_DIR . 'migrate.php';

        // Read the migrate.php file and extract just the functions
        ob_start();

        switch ( $command ) {
            case 'json-to-sqlite':
                $this->migrate_json_to_sqlite_wp( $dry_run );
                break;
            case 'sqlite-to-json':
                $this->migrate_sqlite_to_json_wp( $dry_run );
                break;
            case 'json-to-wpdb':
                $this->migrate_json_to_wpdb_wp( $dry_run );
                break;
            case 'wpdb-to-json':
                $this->migrate_wpdb_to_json_wp( $dry_run );
                break;
            case 'sqlite-to-wpdb':
                $this->migrate_sqlite_to_wpdb_wp( $dry_run );
                break;
            case 'wpdb-to-sqlite':
                $this->migrate_wpdb_to_sqlite_wp( $dry_run );
                break;
            default:
                WP_CLI::error( "Unknown migration command: {$command}" );
        }

        $output = ob_get_clean();
        WP_CLI::line( $output );
    }

    private function migrate_json_to_wpdb_wp( $dry_run ) {
        WP_CLI::line( "Migration: JSON files → WordPress Database" );
        WP_CLI::line( "==========================================" );

        if ( $dry_run ) {
            WP_CLI::line( "This is a dry run. No changes will be made." );
            return;
        }

        try {
            require_once A8C_HR_PLUGIN_DIR . 'includes/wpdb-storage.php';
            $wpdb_storage = new WpdbStorage();

            $migrated_teams = $wpdb_storage->migrate_from_json();

            WP_CLI::success( "Migration completed! Migrated {$migrated_teams} teams to WordPress database." );

        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }

    private function migrate_wpdb_to_json_wp( $dry_run ) {
        WP_CLI::line( "Migration: WordPress Database → JSON files" );
        WP_CLI::line( "==========================================" );

        if ( $dry_run ) {
            WP_CLI::line( "This is a dry run. No changes will be made." );
            return;
        }

        try {
            require_once A8C_HR_PLUGIN_DIR . 'includes/wpdb-storage.php';
            require_once A8C_HR_PLUGIN_DIR . 'includes/json-storage.php';

            $wpdb_storage = new WpdbStorage();
            $json_storage = new JsonStorage();

            $teams = $wpdb_storage->get_available_teams();

            if ( empty( $teams ) ) {
                WP_CLI::line( "No teams found in WordPress database." );
                return;
            }

            $migrated_count = 0;

            foreach ( $teams as $team_slug ) {
                WP_CLI::line( "Migrating team: {$team_slug}..." );

                $config = $wpdb_storage->get_team_config( $team_slug );

                if ( $config ) {
                    if ( $json_storage->save_team_config( $team_slug, $config ) ) {
                        $migrated_count++;
                        WP_CLI::line( "  ✓ Migrated team: {$team_slug}" );
                    } else {
                        WP_CLI::line( "  ✗ Failed to migrate team: {$team_slug}" );
                    }
                } else {
                    WP_CLI::line( "  ⚠ No config found for team: {$team_slug}" );
                }
            }

            WP_CLI::success( "Migration completed! Migrated {$migrated_count} teams to JSON files." );

        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }

    private function migrate_json_to_sqlite_wp( $dry_run ) {
        WP_CLI::line( "Migration: JSON files → SQLite database" );
        WP_CLI::line( "=======================================" );

        if ( $dry_run ) {
            WP_CLI::line( "This is a dry run. No changes will be made." );
            return;
        }

        try {
            require_once A8C_HR_PLUGIN_DIR . 'includes/storage.php';
            $sqlite_storage = new Storage();

            $migrated_teams = $sqlite_storage->migrate_from_json();

            WP_CLI::success( "Migration completed! Migrated {$migrated_teams} teams to SQLite." );

        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }

    private function migrate_sqlite_to_json_wp( $dry_run ) {
        WP_CLI::line( "Migration: SQLite database → JSON files" );
        WP_CLI::line( "=======================================" );

        if ( $dry_run ) {
            WP_CLI::line( "This is a dry run. No changes will be made." );
            return;
        }

        try {
            require_once A8C_HR_PLUGIN_DIR . 'includes/storage.php';
            require_once A8C_HR_PLUGIN_DIR . 'includes/json-storage.php';

            $sqlite_storage = new Storage();
            $json_storage = new JsonStorage();

            $teams = $sqlite_storage->get_available_teams();

            if ( empty( $teams ) ) {
                WP_CLI::line( "No teams found in SQLite database." );
                return;
            }

            $migrated_count = 0;

            foreach ( $teams as $team_slug ) {
                WP_CLI::line( "Migrating team: {$team_slug}..." );

                $config = $sqlite_storage->get_team_config( $team_slug );

                if ( $config ) {
                    if ( $json_storage->save_team_config( $team_slug, $config ) ) {
                        $migrated_count++;
                        WP_CLI::line( "  ✓ Migrated team: {$team_slug}" );
                    } else {
                        WP_CLI::line( "  ✗ Failed to migrate team: {$team_slug}" );
                    }
                } else {
                    WP_CLI::line( "  ⚠ No config found for team: {$team_slug}" );
                }
            }

            WP_CLI::success( "Migration completed! Migrated {$migrated_count} teams to JSON files." );

        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }

    private function migrate_sqlite_to_wpdb_wp( $dry_run ) {
        WP_CLI::line( "Migration: SQLite database → WordPress Database" );
        WP_CLI::line( "===============================================" );

        if ( $dry_run ) {
            WP_CLI::line( "This is a dry run. No changes will be made." );
            return;
        }

        try {
            require_once A8C_HR_PLUGIN_DIR . 'includes/storage.php';
            require_once A8C_HR_PLUGIN_DIR . 'includes/wpdb-storage.php';

            $sqlite_storage = new Storage();
            $wpdb_storage = new WpdbStorage();

            $teams = $sqlite_storage->get_available_teams();

            if ( empty( $teams ) ) {
                WP_CLI::line( "No teams found in SQLite database." );
                return;
            }

            $migrated_count = 0;

            foreach ( $teams as $team_slug ) {
                WP_CLI::line( "Migrating team: {$team_slug}..." );

                $config = $sqlite_storage->get_team_config( $team_slug );

                if ( $config ) {
                    if ( $wpdb_storage->save_team_config( $team_slug, $config ) ) {
                        $migrated_count++;
                        WP_CLI::line( "  ✓ Migrated team: {$team_slug}" );
                    } else {
                        WP_CLI::line( "  ✗ Failed to migrate team: {$team_slug}" );
                    }
                } else {
                    WP_CLI::line( "  ⚠ No config found for team: {$team_slug}" );
                }
            }

            WP_CLI::success( "Migration completed! Migrated {$migrated_count} teams to WordPress database." );

        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }

    private function migrate_wpdb_to_sqlite_wp( $dry_run ) {
        WP_CLI::line( "Migration: WordPress Database → SQLite database" );
        WP_CLI::line( "===============================================" );

        if ( $dry_run ) {
            WP_CLI::line( "This is a dry run. No changes will be made." );
            return;
        }

        try {
            require_once A8C_HR_PLUGIN_DIR . 'includes/wpdb-storage.php';
            require_once A8C_HR_PLUGIN_DIR . 'includes/storage.php';

            $wpdb_storage = new WpdbStorage();
            $sqlite_storage = new Storage();

            $teams = $wpdb_storage->get_available_teams();

            if ( empty( $teams ) ) {
                WP_CLI::line( "No teams found in WordPress database." );
                return;
            }

            $migrated_count = 0;

            foreach ( $teams as $team_slug ) {
                WP_CLI::line( "Migrating team: {$team_slug}..." );

                $config = $wpdb_storage->get_team_config( $team_slug );

                if ( $config ) {
                    if ( $sqlite_storage->save_team_config( $team_slug, $config ) ) {
                        $migrated_count++;
                        WP_CLI::line( "  ✓ Migrated team: {$team_slug}" );
                    } else {
                        WP_CLI::line( "  ✗ Failed to migrate team: {$team_slug}" );
                    }
                } else {
                    WP_CLI::line( "  ⚠ No config found for team: {$team_slug}" );
                }
            }

            WP_CLI::success( "Migration completed! Migrated {$migrated_count} teams to SQLite database." );

        } catch ( Exception $e ) {
            WP_CLI::error( "Migration failed: " . $e->getMessage() );
        }
    }
}