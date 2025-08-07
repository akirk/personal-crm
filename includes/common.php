<?php
/**
 * Common functions shared between admin.php and index.php
 */

/**
 * Build URL with team parameter
 */
function build_team_url( $base_url, $additional_params = array() ) {
	global $current_team;
	$params = array();
	if ( $current_team !== 'team' ) {
		$params['team'] = $current_team;
	}
	$params = array_merge( $params, $additional_params );
	
	if ( ! empty( $params ) ) {
		return $base_url . '?' . http_build_query( $params );
	}
	return $base_url;
}

/**
 * Get all available team files (excluding backup files)
 */
function get_available_teams() {
	$teams = array();
	$json_files = glob( __DIR__ . '/../*.json' );
	
	foreach ( $json_files as $file ) {
		$basename = basename( $file, '.json' );
		// Skip backup files
		if ( strpos( $basename, '.bak' ) === false && strpos( $basename, 'bak-' ) === false ) {
			$teams[] = $basename;
		}
	}
	
	sort( $teams );
	return $teams;
}

/**
 * Get team name from config file
 */
function get_team_name_from_file( $team_slug ) {
	$file_path = __DIR__ . '/../' . $team_slug . '.json';
	if ( file_exists( $file_path ) ) {
		$config = json_decode( file_get_contents( $file_path ), true );
		if ( json_last_error() === JSON_ERROR_NONE && isset( $config['team_name'] ) ) {
			return $config['team_name'];
		}
	}
	return ucfirst( str_replace( '_', ' ', $team_slug ) );
}

/**
 * Privacy mode helper functions
 */
function mask_name( $full_name, $privacy_mode ) {
	if ( ! $privacy_mode ) {
		return $full_name;
	}
	
	$parts = explode( ' ', trim( $full_name ) );
	if ( count( $parts ) <= 1 ) {
		return $full_name; // Only first name, no masking needed
	}
	
	// Return first name + masked last name
	$first_name = $parts[0];
	$last_name_initial = isset( $parts[ count( $parts ) - 1 ] ) ? substr( $parts[ count( $parts ) - 1 ], 0, 1 ) . '.' : '';
	
	return $first_name . ' ' . $last_name_initial;
}

function mask_username( $username, $privacy_mode ) {
	if ( ! $privacy_mode ) {
		return $username;
	}
	
	if ( strlen( $username ) <= 3 ) {
		return $username; // Too short to mask meaningfully
	}
	
	return substr( $username, 0, 3 ) . '...';
}