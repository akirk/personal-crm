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
     * @param array $filter Optional filter with 'group_ids' and 'person_ids'.
     * @return string JSONL content
     */
    public function export_to_jsonl( $filter = array() ) {
        if ( ! empty( $filter['group_ids'] ) || ! empty( $filter['person_ids'] ) || ! empty( $filter['exclude_personal'] ) || ! empty( $filter['options'] ) ) {
            return $this->export_to_jsonl_filtered( $filter );
        }
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
     * Export filtered data based on selected groups and people.
     *
     * @param array $filter Filter with 'group_ids' and/or 'person_ids'.
     * @return string JSONL content
     */
    private function export_to_jsonl_filtered( $filter ) {
        $lines = array();

        // Write metadata line
        $meta = array(
            '_meta'           => true,
            '_version'        => '1.0',
            '_exported_at'    => gmdate( 'c' ),
            '_plugin'         => 'personal-crm',
            '_plugin_version' => defined( 'PERSONAL_CRM_VERSION' ) ? PERSONAL_CRM_VERSION : '1.0.0',
            '_filtered'       => true,
        );
        $lines[] = wp_json_encode( $meta );

        $selected_group_ids = array_map( 'intval', $filter['group_ids'] ?? array() );
        $selected_person_ids = array_map( 'intval', $filter['person_ids'] ?? array() );
        $exclude_personal = ! empty( $filter['exclude_personal'] );
        $plugin_options = $filter['options'] ?? array();

        // Determine which people to export
        // If people are explicitly selected, use those (UI has already filtered based on group selection)
        // If no people selected but groups are, get all people from those groups
        if ( ! empty( $selected_person_ids ) ) {
            $all_person_ids = $selected_person_ids;
        } elseif ( ! empty( $selected_group_ids ) ) {
            $all_person_ids = $this->get_people_in_groups( $selected_group_ids );
        } else {
            $all_person_ids = array();
        }

        // Get groups: selected groups + groups that selected people belong to (for data integrity)
        $groups_from_people = $this->get_groups_for_people( $all_person_ids );
        $all_group_ids = array_unique( array_merge( $selected_group_ids, $groups_from_people ) );

        // Export groups (selected + their parents for hierarchy)
        $groups_to_export = $this->get_groups_with_parents( $all_group_ids );
        foreach ( $groups_to_export as $group ) {
            $group['_table'] = 'personal_crm_groups';
            $lines[] = wp_json_encode( $group );
        }

        // Export people
        $people = $this->get_records_by_ids( 'personal_crm_people', $all_person_ids );
        foreach ( $people as $person ) {
            if ( $exclude_personal ) {
                unset( $person['partner'], $person['partner_birthday'], $person['kids'] );
            }
            $person['_table'] = 'personal_crm_people';
            $lines[] = wp_json_encode( $person );
        }

        // Export people_groups (junction table)
        $people_groups = $this->get_people_groups_filtered( $all_person_ids, $all_group_ids );
        foreach ( $people_groups as $pg ) {
            $pg['_table'] = 'personal_crm_people_groups';
            $lines[] = wp_json_encode( $pg );
        }

        // Export people_groups_history
        $history = $this->get_people_groups_history_filtered( $all_person_ids, $all_group_ids );
        foreach ( $history as $h ) {
            $h['_table'] = 'personal_crm_people_groups_history';
            $lines[] = wp_json_encode( $h );
        }

        // Export notes for selected people (unless excluding personal data)
        if ( ! $exclude_personal ) {
            $notes = $this->get_records_by_foreign_key( 'personal_crm_notes', 'person_id', $all_person_ids );
            foreach ( $notes as $note ) {
                $note['_table'] = 'personal_crm_notes';
                $lines[] = wp_json_encode( $note );
            }
        }

        // Export events for selected groups
        $events = $this->get_records_by_foreign_key( 'personal_crm_events', 'group_id', $all_group_ids );
        $event_ids = array_column( $events, 'id' );
        foreach ( $events as $event ) {
            $event['_table'] = 'personal_crm_events';
            $lines[] = wp_json_encode( $event );
        }

        // Export group_links
        $group_links = $this->get_records_by_foreign_key( 'personal_crm_group_links', 'group_id', $all_group_ids );
        foreach ( $group_links as $link ) {
            $link['_table'] = 'personal_crm_group_links';
            $lines[] = wp_json_encode( $link );
        }

        // Export people_links
        $people_links = $this->get_records_by_foreign_key( 'personal_crm_people_links', 'person_id', $all_person_ids );
        foreach ( $people_links as $link ) {
            $link['_table'] = 'personal_crm_people_links';
            $lines[] = wp_json_encode( $link );
        }

        // Export event_links
        $event_links = $this->get_records_by_foreign_key( 'personal_crm_event_links', 'event_id', $event_ids );
        foreach ( $event_links as $link ) {
            $link['_table'] = 'personal_crm_event_links';
            $lines[] = wp_json_encode( $link );
        }

        // Get usernames for selected people (for tables that use username instead of person_id)
        $all_usernames = $this->get_usernames_by_ids( $all_person_ids );

        // Export extension plugin tables
        $extension_tables = $this->get_extension_tables();
        foreach ( $extension_tables as $table_name => $config ) {
            // Allow plugins to skip tables based on options
            if ( apply_filters( 'personal_crm_export_skip_table', false, $table_name, $plugin_options, $exclude_personal ) ) {
                continue;
            }

            // Filter records based on foreign keys
            $records = $this->export_extension_table_filtered(
                $table_name,
                $config,
                $all_person_ids,
                $all_group_ids,
                $all_usernames
            );

            foreach ( $records as $record ) {
                // Allow plugins to filter/modify records based on options
                $record = apply_filters( 'personal_crm_export_filter_record', $record, $table_name, $plugin_options, $exclude_personal );
                if ( $record === null ) {
                    continue;
                }
                $record['_table'] = $table_name;
                $lines[] = wp_json_encode( $record );
            }
        }

        return implode( "\n", $lines ) . "\n";
    }

    /**
     * Get tables registered by extension plugins (not core tables).
     */
    private function get_extension_tables() {
        $all_tables = $this->crm->get_registered_export_tables();
        $core_tables = array(
            'personal_crm_groups',
            'personal_crm_people',
            'personal_crm_people_groups',
            'personal_crm_people_groups_history',
            'personal_crm_notes',
            'personal_crm_events',
            'personal_crm_group_links',
            'personal_crm_people_links',
            'personal_crm_event_links',
        );

        return array_diff_key( $all_tables, array_flip( $core_tables ) );
    }

    /**
     * Get usernames for the given person IDs.
     */
    private function get_usernames_by_ids( $person_ids ) {
        if ( empty( $person_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $person_ids ), '%d' ) );
        return $this->wpdb->get_col( $this->wpdb->prepare(
            "SELECT username FROM {$this->wpdb->prefix}personal_crm_people WHERE id IN ($placeholders)",
            ...$person_ids
        ) );
    }

    /**
     * Export extension table records filtered by selected people/groups.
     */
    private function export_extension_table_filtered( $table_name, $config, $person_ids, $group_ids, $usernames ) {
        $foreign_keys = $config['foreign_keys'] ?? array();

        // Check for person_id foreign key
        if ( isset( $foreign_keys['person_id'] ) && $foreign_keys['person_id'] === 'personal_crm_people.id' ) {
            return $this->get_records_by_foreign_key( $table_name, 'person_id', $person_ids );
        }

        // Check for group_id foreign key
        if ( isset( $foreign_keys['group_id'] ) && $foreign_keys['group_id'] === 'personal_crm_groups.id' ) {
            return $this->get_records_by_foreign_key( $table_name, 'group_id', $group_ids );
        }

        // Check for username column (common pattern in extension plugins)
        if ( $this->table_has_column( $table_name, 'username' ) && ! empty( $usernames ) ) {
            return $this->get_records_by_username( $table_name, $usernames );
        }

        // Allow plugins to provide custom filtered records
        $records = apply_filters( 'personal_crm_export_extension_records', null, $table_name, $config, $person_ids, $group_ids, $usernames );
        if ( $records !== null ) {
            return $records;
        }

        // Fall back to exporting all records if no filtering applies
        return $this->export_table( $table_name, $config );
    }

    /**
     * Check if a table has a specific column.
     */
    private function table_has_column( $table_name, $column_name ) {
        static $cache = array();

        if ( ! isset( $cache[ $table_name ] ) ) {
            $full_table_name = $this->wpdb->prefix . $table_name;
            $columns = $this->wpdb->get_col( "DESCRIBE {$full_table_name}", 0 );
            $cache[ $table_name ] = $columns ?: array();
        }

        return in_array( $column_name, $cache[ $table_name ], true );
    }

    /**
     * Get records by username.
     */
    private function get_records_by_username( $table_name, $usernames ) {
        if ( empty( $usernames ) ) {
            return array();
        }

        $full_table_name = $this->wpdb->prefix . $table_name;
        $placeholders = implode( ',', array_fill( 0, count( $usernames ), '%s' ) );

        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$full_table_name} WHERE username IN ($placeholders) ORDER BY id",
            ...$usernames
        ), ARRAY_A );
    }

    /**
     * Get all descendant group IDs for a group.
     */
    private function get_descendant_group_ids( $group_id ) {
        $descendants = array();
        $children = $this->wpdb->get_col( $this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}personal_crm_groups WHERE parent_id = %d",
            $group_id
        ) );

        foreach ( $children as $child_id ) {
            $descendants[] = (int) $child_id;
            $descendants = array_merge( $descendants, $this->get_descendant_group_ids( $child_id ) );
        }

        return $descendants;
    }

    /**
     * Get group IDs that the given people belong to.
     */
    private function get_groups_for_people( $person_ids ) {
        if ( empty( $person_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $person_ids ), '%d' ) );
        $query = $this->wpdb->prepare(
            "SELECT DISTINCT group_id FROM {$this->wpdb->prefix}personal_crm_people_groups WHERE person_id IN ($placeholders)",
            ...$person_ids
        );

        return array_map( 'intval', $this->wpdb->get_col( $query ) );
    }

    /**
     * Get person IDs in the given groups.
     */
    private function get_people_in_groups( $group_ids ) {
        if ( empty( $group_ids ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
        $query = $this->wpdb->prepare(
            "SELECT DISTINCT person_id FROM {$this->wpdb->prefix}personal_crm_people_groups WHERE group_id IN ($placeholders)",
            ...$group_ids
        );

        return array_map( 'intval', $this->wpdb->get_col( $query ) );
    }

    /**
     * Get groups with their parent groups (for hierarchy preservation).
     */
    private function get_groups_with_parents( $group_ids ) {
        if ( empty( $group_ids ) ) {
            return array();
        }

        $all_ids = $group_ids;
        $parents_to_check = $group_ids;

        while ( ! empty( $parents_to_check ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $parents_to_check ), '%d' ) );
            $parent_ids = $this->wpdb->get_col( $this->wpdb->prepare(
                "SELECT DISTINCT parent_id FROM {$this->wpdb->prefix}personal_crm_groups
                 WHERE id IN ($placeholders) AND parent_id IS NOT NULL",
                ...$parents_to_check
            ) );

            $new_parents = array();
            foreach ( $parent_ids as $parent_id ) {
                $parent_id = (int) $parent_id;
                if ( ! in_array( $parent_id, $all_ids, true ) ) {
                    $all_ids[] = $parent_id;
                    $new_parents[] = $parent_id;
                }
            }
            $parents_to_check = $new_parents;
        }

        $placeholders = implode( ',', array_fill( 0, count( $all_ids ), '%d' ) );
        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_groups
             WHERE id IN ($placeholders) ORDER BY COALESCE(parent_id, 0), sort_order",
            ...$all_ids
        ), ARRAY_A );
    }

    /**
     * Get records by their IDs.
     */
    private function get_records_by_ids( $table_name, $ids ) {
        if ( empty( $ids ) ) {
            return array();
        }

        $full_table_name = $this->wpdb->prefix . $table_name;
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$full_table_name} WHERE id IN ($placeholders) ORDER BY id",
            ...$ids
        ), ARRAY_A );
    }

    /**
     * Get records by a foreign key column.
     */
    private function get_records_by_foreign_key( $table_name, $fk_column, $fk_values ) {
        if ( empty( $fk_values ) ) {
            return array();
        }

        $full_table_name = $this->wpdb->prefix . $table_name;
        $placeholders = implode( ',', array_fill( 0, count( $fk_values ), '%d' ) );

        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$full_table_name} WHERE {$fk_column} IN ($placeholders) ORDER BY id",
            ...$fk_values
        ), ARRAY_A );
    }

    /**
     * Get people_groups junction records filtered by person and group.
     */
    private function get_people_groups_filtered( $person_ids, $group_ids ) {
        if ( empty( $person_ids ) || empty( $group_ids ) ) {
            return array();
        }

        $person_placeholders = implode( ',', array_fill( 0, count( $person_ids ), '%d' ) );
        $group_placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people_groups
             WHERE person_id IN ($person_placeholders) AND group_id IN ($group_placeholders) ORDER BY id",
            ...array_merge( $person_ids, $group_ids )
        ), ARRAY_A );
    }

    /**
     * Get people_groups_history records filtered by person and group.
     */
    private function get_people_groups_history_filtered( $person_ids, $group_ids ) {
        if ( empty( $person_ids ) || empty( $group_ids ) ) {
            return array();
        }

        $person_placeholders = implode( ',', array_fill( 0, count( $person_ids ), '%d' ) );
        $group_placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

        return $this->wpdb->get_results( $this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}personal_crm_people_groups_history
             WHERE person_id IN ($person_placeholders) AND group_id IN ($group_placeholders) ORDER BY id",
            ...array_merge( $person_ids, $group_ids )
        ), ARRAY_A );
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
     * Get hierarchical groups with member counts for export selection UI.
     *
     * @return array Groups organized hierarchically with member info.
     */
    public function get_groups_for_export_selection() {
        $groups = $this->wpdb->get_results(
            "SELECT g.id, g.slug, g.group_name, g.display_icon, g.parent_id, g.sort_order,
                    (SELECT COUNT(*) FROM {$this->wpdb->prefix}personal_crm_people_groups pg WHERE pg.group_id = g.id) as member_count
             FROM {$this->wpdb->prefix}personal_crm_groups g
             ORDER BY COALESCE(g.parent_id, 0), g.sort_order, g.group_name",
            ARRAY_A
        );

        $groups_by_id = array();
        $children_by_parent = array();
        $top_level = array();

        foreach ( $groups as $group ) {
            $group['id'] = (int) $group['id'];
            $group['parent_id'] = $group['parent_id'] ? (int) $group['parent_id'] : null;
            $group['member_count'] = (int) $group['member_count'];
            $groups_by_id[ $group['id'] ] = $group;

            if ( $group['parent_id'] ) {
                $children_by_parent[ $group['parent_id'] ][] = $group;
            } else {
                $top_level[] = $group;
            }
        }

        $result = array();
        foreach ( $top_level as $parent ) {
            $parent['children'] = $children_by_parent[ $parent['id'] ] ?? array();
            $result[] = $parent;
        }

        return $result;
    }

    /**
     * Get people grouped by their memberships for export selection UI.
     *
     * @return array People with their group IDs.
     */
    public function get_people_for_export_selection() {
        $people = $this->wpdb->get_results(
            "SELECT p.id, p.username, p.name
             FROM {$this->wpdb->prefix}personal_crm_people p
             ORDER BY p.name",
            ARRAY_A
        );

        $memberships = $this->wpdb->get_results(
            "SELECT person_id, group_id FROM {$this->wpdb->prefix}personal_crm_people_groups",
            ARRAY_A
        );

        $groups_by_person = array();
        foreach ( $memberships as $m ) {
            $groups_by_person[ (int) $m['person_id'] ][] = (int) $m['group_id'];
        }

        foreach ( $people as &$person ) {
            $person['id'] = (int) $person['id'];
            $person['group_ids'] = $groups_by_person[ $person['id'] ] ?? array();
        }

        return $people;
    }

    /**
     * Get people without any group membership.
     *
     * @return array People with no groups.
     */
    public function get_ungrouped_people() {
        return $this->wpdb->get_results(
            "SELECT p.id, p.username, p.name
             FROM {$this->wpdb->prefix}personal_crm_people p
             LEFT JOIN {$this->wpdb->prefix}personal_crm_people_groups pg ON p.id = pg.person_id
             WHERE pg.person_id IS NULL
             ORDER BY p.name",
            ARRAY_A
        );
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
