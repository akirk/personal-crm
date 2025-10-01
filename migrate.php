#!/usr/bin/env php
<?php
/**
 * Enhanced Bidirectional Migration Tool
 * 
 * Migrate data between JSON and SQLite storage backends with dry-run support
 * 
 * Usage:
 *   php migrate.php json-to-sqlite [--dry-run]    # Migrate from JSON files to SQLite
 *   php migrate.php sqlite-to-json [--dry-run]    # Migrate from SQLite to JSON files
 *   php migrate.php --help                        # Show help
 */

// Include required files
require_once __DIR__ . '/includes/storage-factory.php';

function show_help() {
    echo "Enhanced Bidirectional Migration Tool\n";
    echo "====================================\n\n";
    echo "Usage:\n";
    echo "  php migrate.php json-to-sqlite [--dry-run]    # Migrate from JSON files to SQLite\n";
    echo "  php migrate.php sqlite-to-json [--dry-run]    # Migrate from SQLite to JSON files\n";
    echo "  php migrate.php --help                        # Show this help\n\n";
    echo "Options:\n";
    echo "  --dry-run    Show what would be migrated without making changes\n\n";
    echo "Examples:\n";
    echo "  # Preview migration from JSON to SQLite:\n";
    echo "  php migrate.php json-to-sqlite --dry-run\n\n";
    echo "  # Actually perform the migration:\n";
    echo "  php migrate.php json-to-sqlite\n\n";
    echo "  # Export SQLite database back to JSON files:\n";
    echo "  php migrate.php sqlite-to-json\n\n";
    echo "Features:\n";
    echo "  • Pre-migration statistics showing existing data in both formats\n";
    echo "  • Dry-run mode to preview changes before migrating\n";
    echo "  • Detection of what would be added, updated, or removed\n";
    echo "  • Comprehensive error handling and progress reporting\n\n";
    echo "Note: Always backup your data before running migrations!\n";
}

/**
 * Get statistics about existing data in both storage formats
 */
function get_storage_statistics() {
    $stats = [
        'json' => ['teams' => 0, 'people' => 0, 'files' => []],
        'sqlite' => ['teams' => 0, 'people' => 0, 'database_exists' => false]
    ];
    
    try {
        // Check JSON files
        require_once __DIR__ . '/includes/json-storage.php';
        $json_storage = new JsonStorage();
        $json_teams = $json_storage->get_available_teams();
        $stats['json']['teams'] = count($json_teams);
        
        foreach ($json_teams as $team_slug) {
            $stats['json']['files'][] = $team_slug . '.json';
            $config = $json_storage->get_team_config($team_slug);
            if ($config) {
                foreach (['team_members', 'leadership', 'consultants', 'alumni'] as $category) {
                    if (isset($config[$category]) && is_array($config[$category])) {
                        $stats['json']['people'] += count($config[$category]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // JSON storage not available or no files found
    }
    
    try {
        // Check SQLite database
        require_once __DIR__ . '/includes/storage.php';
        $sqlite_storage = new Storage();
        $sqlite_teams = $sqlite_storage->get_available_teams();
        $stats['sqlite']['teams'] = count($sqlite_teams);
        $stats['sqlite']['database_exists'] = true;
        
        foreach ($sqlite_teams as $team_slug) {
            $config = $sqlite_storage->get_team_config($team_slug);
            if ($config) {
                foreach (['team_members', 'leadership', 'consultants', 'alumni'] as $category) {
                    if (isset($config[$category]) && is_array($config[$category])) {
                        $stats['sqlite']['people'] += count($config[$category]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // SQLite storage not available or empty
    }
    
    return $stats;
}

/**
 * Show pre-migration statistics
 */
function show_statistics() {
    echo "Pre-Migration Statistics\n";
    echo "========================\n\n";
    
    $stats = get_storage_statistics();
    
    echo "JSON Storage:\n";
    echo "  Teams: {$stats['json']['teams']}\n";
    echo "  People: {$stats['json']['people']}\n";
    echo "  Files: " . implode(', ', array_slice($stats['json']['files'], 0, 5));
    if (count($stats['json']['files']) > 5) {
        echo " (+" . (count($stats['json']['files']) - 5) . " more)";
    }
    echo "\n\n";
    
    echo "SQLite Storage:\n";
    if ($stats['sqlite']['database_exists']) {
        echo "  Database: EXISTS\n";
        echo "  Teams: {$stats['sqlite']['teams']}\n";
        echo "  People: {$stats['sqlite']['people']}\n";
    } else {
        echo "  Database: NOT EXISTS\n";
        echo "  Teams: 0\n";
        echo "  People: 0\n";
    }
    echo "\n";
}

/**
 * Compare data between storage formats and show what would change
 */
function show_migration_preview($from_format, $to_format, $dry_run = true) {
    echo ($dry_run ? "Dry Run Preview" : "Migration Plan") . "\n";
    echo str_repeat("=", ($dry_run ? 15 : 14)) . "\n\n";
    
    try {
        require_once __DIR__ . '/includes/storage.php';
        require_once __DIR__ . '/includes/json-storage.php';
        
        if ($from_format === 'json') {
            $source = new JsonStorage();
            $target = new Storage();
            $source_teams = $source->get_available_teams();
            $target_teams = $target->get_available_teams();
        } else {
            $source = new Storage();
            $target = new JsonStorage();
            $source_teams = $source->get_available_teams();
            $target_teams = $target->get_available_teams();
        }
        
        $to_add = array_diff($source_teams, $target_teams);
        $to_update = array_intersect($source_teams, $target_teams);
        $to_remove = array_diff($target_teams, $source_teams);
        
        echo "Teams to be ADDED ({$to_format}): " . count($to_add) . "\n";
        foreach ($to_add as $team) {
            echo "  + {$team}\n";
        }
        echo "\n";
        
        echo "Teams to be UPDATED ({$to_format}): " . count($to_update) . "\n";
        foreach ($to_update as $team) {
            echo "  ~ {$team}\n";
        }
        echo "\n";
        
        if (!empty($to_remove)) {
            echo "Teams that exist in {$to_format} but not in {$from_format}: " . count($to_remove) . "\n";
            foreach ($to_remove as $team) {
                echo "  - {$team} (will remain unchanged)\n";
            }
            echo "\n";
        }
        
        echo "Summary:\n";
        echo "  • " . count($to_add) . " teams will be added\n";
        echo "  • " . count($to_update) . " teams will be updated\n";
        if (!empty($to_remove)) {
            echo "  • " . count($to_remove) . " teams in {$to_format} will remain unchanged\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "Error during preview: " . $e->getMessage() . "\n";
    }
}

function migrate_json_to_sqlite($dry_run = false) {
    echo "Migration: JSON files → SQLite database\n";
    echo "=======================================\n\n";
    
    show_statistics();
    show_migration_preview('json', 'SQLite', $dry_run);
    
    if ($dry_run) {
        echo "This was a dry run. No changes were made.\n";
        echo "Run without --dry-run to perform the actual migration.\n";
        return;
    }
    
    // Confirm before proceeding
    echo "Ready to perform migration. Continue? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'y' && trim($line) !== 'Y') {
        echo "Migration cancelled.\n";
        return;
    }
    
    echo "\nStarting actual migration from JSON files to SQLite...\n";
    
    try {
        // Create SQLite storage instance directly
        require_once __DIR__ . '/includes/storage.php';
        $sqlite_storage = new Storage();
        
        // Use built-in migration method
        $migrated_teams = $sqlite_storage->migrate_from_json();
        
        echo "Migration completed successfully!\n";
        echo "Migrated {$migrated_teams} teams to SQLite.\n";
        
        // Show final statistics
        echo "\nPost-Migration Statistics:\n";
        show_statistics();
        
    } catch ( Exception $e ) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        exit( 1 );
    }
    
    echo "\nMigration complete! Update config.php to use SQLite storage:\n";
    echo "define( 'STORAGE_TYPE', 'sqlite' );\n";
}

function migrate_sqlite_to_json($dry_run = false) {
    echo "Migration: SQLite database → JSON files\n";
    echo "=======================================\n\n";
    
    show_statistics();
    show_migration_preview('sqlite', 'JSON', $dry_run);
    
    if ($dry_run) {
        echo "This was a dry run. No changes were made.\n";
        echo "Run without --dry-run to perform the actual migration.\n";
        return;
    }
    
    // Confirm before proceeding
    echo "Ready to perform migration. Continue? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'y' && trim($line) !== 'Y') {
        echo "Migration cancelled.\n";
        return;
    }
    
    echo "\nStarting actual migration from SQLite to JSON files...\n";
    
    try {
        // Create both storage instances
        require_once __DIR__ . '/includes/storage.php';
        require_once __DIR__ . '/includes/json-storage.php';
        
        $sqlite_storage = new Storage();
        $json_storage = new JsonStorage();
        
        // Get all teams from SQLite
        $teams = $sqlite_storage->get_available_teams();
        
        if ( empty( $teams ) ) {
            echo "No teams found in SQLite database.\n";
            return;
        }
        
        $migrated_count = 0;
        
        foreach ( $teams as $team_slug ) {
            echo "Migrating team: {$team_slug}...\n";
            
            // Get team config from SQLite
            $config = $sqlite_storage->get_team_config( $team_slug );
            
            if ( $config ) {
                // Save to JSON
                if ( $json_storage->save_team_config( $team_slug, $config ) ) {
                    $migrated_count++;
                    echo "  ✓ Migrated team: {$team_slug}\n";
                } else {
                    echo "  ✗ Failed to migrate team: {$team_slug}\n";
                }
            } else {
                echo "  ⚠ No config found for team: {$team_slug}\n";
            }
        }
        
        echo "\nMigration completed successfully!\n";
        echo "Migrated {$migrated_count} teams to JSON files.\n";
        
        // Show final statistics
        echo "\nPost-Migration Statistics:\n";
        show_statistics();
        
    } catch ( Exception $e ) {
        echo "Migration failed: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        exit( 1 );
    }
    
    echo "\nMigration complete! Update config.php to use JSON storage:\n";
    echo "define( 'STORAGE_TYPE', 'json' );\n";
}

// Parse command line arguments
if ( $argc < 2 ) {
    show_help();
    exit( 1 );
}

$command = $argv[1];
$dry_run = in_array('--dry-run', $argv);

switch ( $command ) {
    case 'json-to-sqlite':
        migrate_json_to_sqlite($dry_run);
        break;
        
    case 'sqlite-to-json':
        migrate_sqlite_to_json($dry_run);
        break;
        
    case '--help':
    case '-h':
    case 'help':
        show_help();
        break;
        
    default:
        echo "Unknown command: {$command}\n\n";
        show_help();
        exit( 1 );
}

echo "\nDone!\n";
?>