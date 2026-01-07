<?php
/**
 * Export/Import functionality for Personal CRM
 *
 * Handles JSONL export and import of all CRM data including extension plugin tables.
 */

namespace PersonalCRM;

class ExportImport {
    private $crm;
    private $wpdb;

    public function __construct( $crm ) {
        $this->crm = $crm;
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Export all registered tables to JSONL format.
     *
     * @return string JSONL content
     */
    public function export_to_jsonl() {
        $tables = $this->crm->get_registered_export_tables();
        $lines = array();

        // Write metadata line
        $meta = array(
            '_meta'           => true,
            '_version'        => '1.0',
            '_exported_at'    => gmdate( 'c' ),
            '_plugin'         => 'personal-crm',
            '_plugin_version' => defined( 'PERSONAL_CRM_VERSION' ) ? PERSONAL_CRM_VERSION : '1.0.0',
        );
        $lines[] = wp_json_encode( $meta );

        // Build set of valid IDs for each table (for orphan detection)
        $valid_ids = array();
        foreach ( $tables as $table_name => $config ) {
            $valid_ids[ $table_name ] = $this->get_table_ids( $table_name );
        }

        // Export each table in order
        foreach ( $tables as $table_name => $config ) {
            $records = $this->export_table( $table_name, $config );
            foreach ( $records as $record ) {
                // Skip orphaned records (foreign key references non-existent record)
                if ( $this->is_orphaned_record( $record, $config, $valid_ids ) ) {
                    continue;
                }
                $record['_table'] = $table_name;
                $lines[] = wp_json_encode( $record );
            }
        }

        return implode( "\n", $lines ) . "\n";
    }

    /**
     * Get all IDs from a table.
     *
     * @param string $table_name Table name without prefix.
     * @return array Set of IDs (as keys for fast lookup).
     */
    private function get_table_ids( $table_name ) {
        $full_table_name = $this->wpdb->prefix . $table_name;
        $ids = $this->wpdb->get_col( "SELECT id FROM {$full_table_name}" );
        return array_flip( $ids ?: array() );
    }

    /**
     * Check if a record has orphaned foreign key references.
     *
     * @param array $record    The record data.
     * @param array $config    Table configuration.
     * @param array $valid_ids Map of table_name => [id => index].
     * @return bool True if orphaned.
     */
    private function is_orphaned_record( $record, $config, $valid_ids ) {
        if ( empty( $config['foreign_keys'] ) ) {
            return false;
        }

        foreach ( $config['foreign_keys'] as $column => $reference ) {
            if ( ! isset( $record[ $column ] ) || $record[ $column ] === '' || $record[ $column ] === null ) {
                continue;
            }

            list( $ref_table, $ref_column ) = explode( '.', $reference );
            $fk_value = (int) $record[ $column ];

            // Check if the referenced ID exists
            if ( ! isset( $valid_ids[ $ref_table ][ $fk_value ] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Export all records from a single table.
     *
     * @param string $table_name Table name without prefix.
     * @param array  $config     Table configuration.
     * @return array Array of records.
     */
    private function export_table( $table_name, $config ) {
        $full_table_name = $this->wpdb->prefix . $table_name;

        $order_by = '';
        if ( ! empty( $config['order_by'] ) ) {
            $order_by = ' ORDER BY ' . $config['order_by'];
        } else {
            $order_by = ' ORDER BY id ASC';
        }

        $results = $this->wpdb->get_results(
            "SELECT * FROM {$full_table_name}{$order_by}",
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * Get table record counts for display.
     *
     * @return array Table name => count mapping.
     */
    public function get_table_counts() {
        $tables = $this->crm->get_registered_export_tables();
        $counts = array();

        foreach ( $tables as $table_name => $config ) {
            $full_table_name = $this->wpdb->prefix . $table_name;
            $count = $this->wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" );
            $counts[ $table_name ] = (int) $count;
        }

        return $counts;
    }

    /**
     * Validate a JSONL file before import.
     *
     * @param string $file_path Path to the JSONL file.
     * @return array [ 'valid' => bool, 'error' => string|null, 'stats' => array ]
     */
    public function validate_jsonl( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return array( 'valid' => false, 'error' => 'File not found.' );
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return array( 'valid' => false, 'error' => 'Could not open file.' );
        }

        $line_number = 0;
        $has_meta = false;
        $table_counts = array();
        $registered_tables = $this->crm->get_registered_export_tables();

        while ( ( $line = fgets( $handle ) ) !== false ) {
            $line_number++;
            $line = trim( $line );

            if ( empty( $line ) ) {
                continue;
            }

            $record = json_decode( $line, true );

            if ( json_last_error() !== JSON_ERROR_NONE ) {
                fclose( $handle );
                return array(
                    'valid' => false,
                    'error' => sprintf( 'Invalid JSON on line %d: %s', $line_number, json_last_error_msg() ),
                );
            }

            // Check for metadata line
            if ( isset( $record['_meta'] ) && $record['_meta'] ) {
                $has_meta = true;
                continue;
            }

            // Validate table name
            if ( empty( $record['_table'] ) ) {
                fclose( $handle );
                return array(
                    'valid' => false,
                    'error' => sprintf( 'Missing _table field on line %d.', $line_number ),
                );
            }

            $table_name = $record['_table'];
            if ( ! isset( $registered_tables[ $table_name ] ) ) {
                // Unknown table - this could be from an extension that's not active
                // We'll skip it during import but note it here
                if ( ! isset( $table_counts[ $table_name ] ) ) {
                    $table_counts[ $table_name ] = array( 'count' => 0, 'known' => false );
                }
            } else {
                if ( ! isset( $table_counts[ $table_name ] ) ) {
                    $table_counts[ $table_name ] = array( 'count' => 0, 'known' => true );
                }
            }
            $table_counts[ $table_name ]['count']++;
        }

        fclose( $handle );

        return array(
            'valid' => true,
            'error' => null,
            'stats' => array(
                'has_meta'     => $has_meta,
                'line_count'   => $line_number,
                'table_counts' => $table_counts,
            ),
        );
    }

    /**
     * Import data from a JSONL file.
     *
     * @param string $file_path Path to the JSONL file.
     * @param string $mode      'replace' or 'merge'.
     * @return array|\WP_Error Result with counts or error.
     */
    public function import_from_jsonl( $file_path, $mode = 'replace' ) {
        // Validate first
        $validation = $this->validate_jsonl( $file_path );
        if ( ! $validation['valid'] ) {
            return new \WP_Error( 'invalid_jsonl', $validation['error'] );
        }

        $tables = $this->crm->get_registered_export_tables();

        // Clear existing data in replace mode
        if ( $mode === 'replace' ) {
            $this->clear_tables( array_keys( $tables ) );
        }

        // ID mapping: table_name => [ old_id => new_id ]
        $id_mapping = array();
        foreach ( $tables as $table_name => $config ) {
            $id_mapping[ $table_name ] = array();
        }

        // Read and import line by line
        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return new \WP_Error( 'import_failed', 'Could not open import file.' );
        }

        $imported_counts = array();
        $skipped_counts = array();

        while ( ( $line = fgets( $handle ) ) !== false ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }

            $record = json_decode( $line, true );

            // Skip metadata line
            if ( isset( $record['_meta'] ) && $record['_meta'] ) {
                continue;
            }

            $table_name = $record['_table'] ?? null;
            if ( ! $table_name || ! isset( $tables[ $table_name ] ) ) {
                continue; // Skip unknown tables
            }

            $config = $tables[ $table_name ];

            // Store old ID (cast to int for consistent mapping keys)
            $old_id = isset( $record['id'] ) ? (int) $record['id'] : null;

            // Remove metadata fields
            unset( $record['_table'], $record['id'] );

            // Remap foreign keys - skip record if mapping fails (orphaned references)
            $remap_failed = false;
            foreach ( $config['foreign_keys'] ?? array() as $column => $reference ) {
                if ( ! isset( $record[ $column ] ) || $record[ $column ] === '' || $record[ $column ] === null ) {
                    continue;
                }
                list( $ref_table, $ref_column ) = explode( '.', $reference );
                $old_value = (int) $record[ $column ];

                if ( isset( $id_mapping[ $ref_table ][ $old_value ] ) ) {
                    $record[ $column ] = $id_mapping[ $ref_table ][ $old_value ];
                } else {
                    // Orphaned reference - skip this record
                    $remap_failed = true;
                    break;
                }
            }

            if ( $remap_failed ) {
                $skipped_counts[ $table_name ] = ( $skipped_counts[ $table_name ] ?? 0 ) + 1;
                continue;
            }

            // Handle merge mode - check for existing record
            if ( $mode === 'merge' && ! empty( $config['unique_key'] ) ) {
                $unique_key = $config['unique_key'];
                if ( isset( $record[ $unique_key ] ) ) {
                    $existing_id = $this->find_existing_record( $table_name, $unique_key, $record[ $unique_key ] );
                    if ( $existing_id ) {
                        // Update existing record
                        $this->update_record( $table_name, $existing_id, $record );
                        if ( $old_id ) {
                            $id_mapping[ $table_name ][ $old_id ] = $existing_id;
                        }
                        $imported_counts[ $table_name ] = ( $imported_counts[ $table_name ] ?? 0 ) + 1;
                        continue;
                    }
                }
            }

            // Insert new record
            $new_id = $this->insert_record( $table_name, $record );

            // If insert fails (e.g., duplicate key), skip but continue
            if ( $new_id === false ) {
                $skipped_counts[ $table_name ] = ( $skipped_counts[ $table_name ] ?? 0 ) + 1;
                continue;
            }

            // Update ID mapping
            if ( $old_id && $new_id ) {
                $id_mapping[ $table_name ][ $old_id ] = $new_id;
            }

            $imported_counts[ $table_name ] = ( $imported_counts[ $table_name ] ?? 0 ) + 1;
        }

        fclose( $handle );

        return array(
            'success' => true,
            'counts'  => $imported_counts,
            'skipped' => $skipped_counts,
            'mode'    => $mode,
        );
    }

    /**
     * Clear all data from specified tables (in reverse order for foreign keys).
     *
     * @param array $table_names Array of table names.
     */
    private function clear_tables( $table_names ) {
        // Reverse order to respect foreign key constraints
        $table_names = array_reverse( $table_names );

        foreach ( $table_names as $table_name ) {
            $full_table_name = $this->wpdb->prefix . $table_name;
            $this->wpdb->query( "DELETE FROM {$full_table_name}" );
        }
    }

    /**
     * Remap foreign key values using the ID mapping.
     *
     * @param array $record     The record data.
     * @param array $config     Table configuration.
     * @param array $id_mapping Current ID mapping.
     * @return array Modified record.
     */
    private function remap_foreign_keys( $record, $config, $id_mapping, $table_name = '' ) {
        if ( empty( $config['foreign_keys'] ) ) {
            return $record;
        }

        foreach ( $config['foreign_keys'] as $column => $reference ) {
            if ( ! isset( $record[ $column ] ) || $record[ $column ] === '' || $record[ $column ] === null ) {
                continue;
            }

            // Parse reference: 'table_name.column'
            list( $ref_table, $ref_column ) = explode( '.', $reference );

            // Cast to int for consistent lookup (JSON values are strings)
            $old_value = (int) $record[ $column ];

            if ( isset( $id_mapping[ $ref_table ][ $old_value ] ) ) {
                $record[ $column ] = $id_mapping[ $ref_table ][ $old_value ];
            } else {
                throw new \Exception( sprintf(
                    'Cannot remap %s.%s: no mapping found for %s.id=%d (mapping has %d entries)',
                    $table_name,
                    $column,
                    $ref_table,
                    $old_value,
                    count( $id_mapping[ $ref_table ] ?? [] )
                ) );
            }
        }

        return $record;
    }

    /**
     * Find an existing record by unique key.
     *
     * @param string $table_name Table name.
     * @param string $key_column Unique key column.
     * @param mixed  $value      Value to search for.
     * @return int|null Existing record ID or null.
     */
    private function find_existing_record( $table_name, $key_column, $value ) {
        $full_table_name = $this->wpdb->prefix . $table_name;

        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$full_table_name} WHERE {$key_column} = %s LIMIT 1",
                $value
            )
        );
    }

    /**
     * Get column names for a table.
     *
     * @param string $table_name Table name without prefix.
     * @return array Column names.
     */
    private function get_table_columns( $table_name ) {
        static $cache = array();

        if ( isset( $cache[ $table_name ] ) ) {
            return $cache[ $table_name ];
        }

        $full_table_name = $this->wpdb->prefix . $table_name;
        $columns = $this->wpdb->get_col( "DESCRIBE {$full_table_name}", 0 );
        $cache[ $table_name ] = $columns ?: array();

        return $cache[ $table_name ];
    }

    /**
     * Filter record to only include columns that exist in the table.
     *
     * @param string $table_name Table name.
     * @param array  $record     Record data.
     * @return array Filtered record.
     */
    private function filter_record_columns( $table_name, $record ) {
        $valid_columns = $this->get_table_columns( $table_name );

        if ( empty( $valid_columns ) ) {
            return $record;
        }

        return array_intersect_key( $record, array_flip( $valid_columns ) );
    }

    /**
     * Insert a new record.
     *
     * @param string $table_name Table name.
     * @param array  $record     Record data.
     * @return int|false New ID or false on failure.
     */
    private function insert_record( $table_name, $record ) {
        $full_table_name = $this->wpdb->prefix . $table_name;

        // Filter to only include columns that exist in the target table
        // This preserves created_at for tables where it's meaningful (e.g., join dates)
        $record = $this->filter_record_columns( $table_name, $record );

        $result = $this->wpdb->insert( $full_table_name, $record );

        if ( $result === false ) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update an existing record.
     *
     * @param string $table_name Table name.
     * @param int    $id         Record ID.
     * @param array  $record     Record data.
     * @return bool Success.
     */
    private function update_record( $table_name, $id, $record ) {
        $full_table_name = $this->wpdb->prefix . $table_name;

        // Filter to only include columns that exist in the target table
        // This preserves created_at for tables where it's meaningful (e.g., join dates)
        $record = $this->filter_record_columns( $table_name, $record );

        $result = $this->wpdb->update(
            $full_table_name,
            $record,
            array( 'id' => $id )
        );

        return $result !== false;
    }
}
