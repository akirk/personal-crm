<?php
/**
 * Storage Factory
 * 
 * Creates appropriate storage instances based on configuration
 */

require_once __DIR__ . '/storage-interface.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/json-storage.php';

class StorageFactory {
    /**
     * Create storage instance based on configuration
     * 
     * @param string $type 'json' or 'sqlite'
     * @param array $options Optional configuration options
     * @return StorageInterface
     */
    public static function create( $type = null, $options = array() ) {
        // Default to SQLite if not specified
        if ( $type === null ) {
            $type = defined( 'STORAGE_TYPE' ) ? STORAGE_TYPE : 'sqlite';
        }
        
        switch ( strtolower( $type ) ) {
            case 'json':
                $json_dir = isset( $options['json_dir'] ) ? $options['json_dir'] : null;
                return new JsonStorage( $json_dir );
                
            case 'sqlite':
            default:
                $db_file = isset( $options['db_file'] ) ? $options['db_file'] : null;
                return new Storage( $db_file );
        }
    }
    
    /**
     * Get available storage types
     */
    public static function get_available_types() {
        return array( 'json', 'sqlite' );
    }
}