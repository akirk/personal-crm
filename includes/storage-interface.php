<?php
/**
 * Storage Interface
 * 
 * Defines the contract for storage backends (JSON, SQLite, etc.)
 */

interface StorageInterface {
    /**
     * Get team configuration data
     */
    public function get_team_config( $team_slug );
    
    /**
     * Save team configuration data
     */
    public function save_team_config( $team_slug, $config );
    
    /**
     * Get all available team slugs
     */
    public function get_available_teams();
    
    /**
     * Get team name by slug
     */
    public function get_team_name( $team_slug );
    
    /**
     * Get team type by slug
     */
    public function get_team_type( $team_slug );
    
    /**
     * Get default team slug
     */
    public function get_default_team();
    
    /**
     * Check if team exists
     */
    public function team_exists( $team_slug );
    
    /**
     * Delete a team and all its data
     */
    public function delete_team( $team_slug );
    
    /**
     * Get HR feedback for a person
     */
    public function get_hr_feedback( $username, $month = null );
    
    /**
     * Save HR feedback for a person
     */
    public function save_hr_feedback( $username, $month, $data );
    
    /**
     * Get people count from team config
     */
    public function get_team_people_count( $team_slug );
    
    /**
     * Get all people names from team config for search purposes
     */
    public function get_team_people_names( $team_slug );
    
    /**
     * Get all people data from team config for search purposes
     */
    public function get_team_people_data( $team_slug );
}