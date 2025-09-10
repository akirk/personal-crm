<?php
/**
 * Enhanced Importer script for person data
 * Usage: 
 *   php import-person.php file1.yaml file2.yaml ...
 *   php import-person.php --interactive file1.yaml file2.yaml ...
 */

// Create a minimal CLI environment to avoid web dependencies
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';

/**
 * Create backup of existing configuration file (max one per minute)
 */
function create_backup( $file_path ) {
	if ( ! file_exists( $file_path ) ) {
		return true; // No file to backup
	}
	// Create backups directory if it doesn't exist
	$backups_dir = dirname( $file_path ) . '/backups';
	if ( ! file_exists( $backups_dir ) ) {
		mkdir( $backups_dir, 0755, true );
	}
	// Generate backup filename in backups directory (minute precision only)
	$filename = basename( $file_path );
	$backup_timestamp = date( '-Y-m-d-H-i' ); // No seconds - only minute precision
	$backup_filename = substr( $filename, 0, -4 ) . 'bak' . $backup_timestamp . '.json';
	$backup_path = $backups_dir . '/' . $backup_filename;
	
	// Only create backup if one doesn't already exist for this minute
	if ( file_exists( $backup_path ) ) {
		return true; // Backup for this minute already exists
	}
	
	return copy( $file_path, $backup_path );
}

/**
 * Parse YAML-like data format
 */
function parse_person_data( $content ) {
    $lines = explode( "\n", $content );
    $data = array();
    $current_key = null;
    $is_multiline = false;
    $multiline_content = '';
    $in_companies = false;
    $in_usernames = false;
    
    foreach ( $lines as $line ) {
        $line = rtrim( $line );
        
        // Skip empty lines
        if ( empty( $line ) ) {
            continue;
        }
        
        // Check for main keys
        if ( preg_match( '/^(\w+):\s*(.*)$/', $line, $matches ) ) {
            $key = $matches[1];
            $value = $matches[2];
            
            // End any previous multiline
            if ( $is_multiline && $current_key ) {
                $data[$current_key] = trim( $multiline_content );
                $is_multiline = false;
                $multiline_content = '';
            }
            
            $current_key = $key;
            $in_companies = ( $key === 'companies' );
            $in_usernames = ( $key === 'usernames' );
            
            if ( $value === '|' ) {
                // Start multiline
                $is_multiline = true;
                $multiline_content = '';
            } elseif ( empty( $value ) && ( $in_companies || $in_usernames ) ) {
                // Initialize array for nested data
                $data[$key] = array();
            } else {
                $data[$key] = $value;
            }
        } elseif ( $is_multiline ) {
            // Add to multiline content
            $multiline_content .= ( empty( $multiline_content ) ? '' : "\n" ) . ltrim( $line );
        } elseif ( $in_usernames && preg_match( '/^\s+(\w+(?:\.\w+)?):\s*(.+)$/', $line, $matches ) ) {
            // Parse username entries
            if ( ! isset( $data['usernames'] ) ) {
                $data['usernames'] = array();
            }
            $data['usernames'][$matches[1]] = $matches[2];
        } elseif ( $in_companies && strpos( $line, '- company:' ) !== false ) {
            // Start of company entry
            if ( ! isset( $data['companies'] ) ) {
                $data['companies'] = array();
            }
            $data['companies'][] = array();
            $company_index = count( $data['companies'] ) - 1;
        } elseif ( $in_companies && preg_match( '/^\s+(\w+):\s*(.+)$/', $line, $matches ) ) {
            // Company fields
            $company_index = count( $data['companies'] ) - 1;
            if ( $company_index >= 0 ) {
                $data['companies'][$company_index][$matches[1]] = $matches[2];
            }
        }
    }
    
    // Handle final multiline
    if ( $is_multiline && $current_key ) {
        $data[$current_key] = trim( $multiline_content );
    }
    
    return $data;
}

/**
 * Get team slug from company name
 */
function get_team_slug_from_company( $company_name ) {
    $normalized = strtolower( trim( $company_name ) );
    
    // Map known company names to team slugs
    $company_mappings = array(
        'automattic' => 'global', // Default to global for Automattic employees
    );
    
    return $company_mappings[$normalized] ?? $normalized;
}

/**
 * Format address to "City, Country" or "City, State, Country" for US
 */
function format_location( $address, $interactive = false ) {
    $lines = array_map( 'trim', explode( "\n", $address ) );
    $lines = array_filter( $lines ); // Remove empty lines
    
    if ( empty( $lines ) ) {
        return '';
    }
    
    // Get the last line which typically contains city, region, postal code, country
    $last_line = end( $lines );
    
    // Try simple patterns first
    // Pattern: City, State ZIP, Country (US format)  
    if ( preg_match( '/^(.+?),\s*([A-Z]{2})\s+\d+.*,?\s*(?:US|United States)?$/i', $last_line, $matches ) ) {
        $city = trim( $matches[1] );
        $state = strtoupper( trim( $matches[2] ) );
        return "$city, $state, United States";
    }
    
    // Also handle format without country: City, State ZIP
    if ( preg_match( '/^(.+?),\s*([A-Z]{2})\s+\d+/', $last_line, $matches ) ) {
        $city = trim( $matches[1] );
        $state = strtoupper( trim( $matches[2] ) );
        return "$city, $state, United States";
    }
    
    // Pattern: City, Region PostalCode, CountryCode
    if ( preg_match( '/^(.+?),\s*(.+?)\s+[\d\-]+.*,\s*([A-Z]{2})$/i', $last_line, $matches ) ) {
        $city = trim( $matches[1] );
        $region = trim( $matches[2] );
        $country_code = strtoupper( trim( $matches[3] ) );
        $country = get_country_name( $country_code );
        
        // For US, include state
        if ( $country_code === 'US' ) {
            return "$city, $region, $country";
        } else {
            return "$city, $country";
        }
    }
    
    // Check if it's just a country code (2-3 letters) - this should prompt user
    if ( preg_match( '/^[A-Z]{2,3}$/i', trim( $last_line ) ) ) {
        $country_code = strtoupper( trim( $last_line ) );
        $country_name = get_country_name( $country_code );
        // Don't auto-use country names - let it fall through to prompt user
        $fallback = $country_name;
    }
    
    // Check for simple "City, Country" format
    elseif ( preg_match( '/^(.+?),\s*(.+)$/i', $last_line, $matches ) && count( explode( ',', $last_line ) ) == 2 ) {
        $city = trim( $matches[1] );
        $country = trim( $matches[2] );
        // If country looks like a full country name (not a code), keep the format
        if ( strlen( $country ) > 3 && ! preg_match( '/\d/', $country ) ) {
            return "$city, $country";
        }
    }
    
    // Check if it's just a country name (should prompt user for city)
    else {
        // If we can't parse it well, return a simple fallback
        $fallback = trim( str_replace( array( '  ', "\n" ), array( ' ', ', ' ), $last_line ) );
        
        // Check if fallback is likely just a country name
        $common_countries = array(
            'portugal', 'spain', 'france', 'germany', 'italy', 'netherlands', 'belgium', 
            'switzerland', 'austria', 'poland', 'czech republic', 'slovakia', 'hungary',
            'romania', 'bulgaria', 'greece', 'turkey', 'united kingdom', 'ireland',
            'denmark', 'sweden', 'norway', 'finland', 'united states', 'canada', 'mexico',
            'brazil', 'argentina', 'chile', 'colombia', 'australia', 'new zealand',
            'japan', 'china', 'india', 'singapore', 'south korea', 'russia', 'ukraine'
        );
        
        if ( in_array( strtolower( $fallback ), $common_countries ) ) {
            // This is likely just a country name - should prompt user
        } else {
            // Not a recognized country, might be a valid location already
            return $fallback;
        }
    }
    
    // If we reach here, we have a country code/name or unclear format that needs user input
    if ( ! isset( $fallback ) ) {
        $fallback = trim( str_replace( array( '  ', "\n" ), array( ' ', ', ' ), $last_line ) );
    }
    
    // If we can't parse it well, return a simple fallback
    $fallback = trim( str_replace( array( '  ', "\n" ), array( ' ', ', ' ), $last_line ) );
    
    // In batch mode, show the address and ask for clarification
    if ( ! $interactive ) {
        echo "  ⚠️  Could not automatically format location:\n";
        echo "  Original address: " . str_replace( "\n", " | ", $address ) . "\n";
        echo "  Suggested fallback: $fallback\n";
        echo "  Please enter properly formatted location (City, Country or City, State, Country for US)\n";
        echo "  or press Enter to use fallback: ";
        $user_input = trim( fgets( STDIN ) );
        if ( ! empty( $user_input ) ) {
            return $user_input;
        }
    } else {
        // In interactive mode, let user confirm/edit the location
        echo "  Could not automatically format location. Please review:\n";
        echo "  Original: " . str_replace( "\n", " | ", $address ) . "\n";
    }
    
    return $fallback;
}

/**
 * Get country name from country code
 */
function get_country_name( $country_code ) {
    $countries = array(
        'US' => 'United States',
        'PT' => 'Portugal',
        'NL' => 'Netherlands', 
        'DE' => 'Germany',
        'ES' => 'Spain',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'FR' => 'France',
        'IT' => 'Italy',
        'AU' => 'Australia',
        'BR' => 'Brazil',
        'RO' => 'Romania',
        'PL' => 'Poland',
        'IN' => 'India',
        'JP' => 'Japan',
        'CN' => 'China',
        'UA' => 'Ukraine',
        'SK' => 'Slovakia',
        'CZ' => 'Czech Republic',
        'HU' => 'Hungary',
        'AT' => 'Austria',
        'CH' => 'Switzerland',
        'BE' => 'Belgium',
        'DK' => 'Denmark',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'FI' => 'Finland',
        'GR' => 'Greece',
        'TR' => 'Turkey',
        'IE' => 'Ireland',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'PE' => 'Peru',
        'VE' => 'Venezuela',
        'ZA' => 'South Africa',
        'EG' => 'Egypt',
        'IL' => 'Israel',
        'SA' => 'Saudi Arabia',
        'AE' => 'UAE',
        'RU' => 'Russia',
        'KR' => 'South Korea',
        'TH' => 'Thailand',
        'VN' => 'Vietnam',
        'PH' => 'Philippines',
        'ID' => 'Indonesia',
        'MY' => 'Malaysia',
        'SG' => 'Singapore',
        'NZ' => 'New Zealand',
    );
    
    return $countries[$country_code] ?? $country_code;
}

/**
 * Normalize website URL to ensure it starts with https://
 */
function normalize_website_url( $url ) {
    if ( empty( $url ) ) {
        return '';
    }
    
    $url = trim( $url );
    
    // If it already starts with https://, keep it as is
    if ( strpos( $url, 'https://' ) === 0 ) {
        return $url;
    }
    
    // If it starts with http://, replace with https://
    if ( strpos( $url, 'http://' ) === 0 ) {
        return str_replace( 'http://', 'https://', $url );
    }
    
    // If it doesn't have a protocol, add https://
    if ( ! preg_match( '/^[a-z]+:\/\//', $url ) ) {
        return 'https://' . $url;
    }
    
    // For other protocols (ftp://, etc.), leave as is
    return $url;
}

/**
 * Infer timezone from location
 */
function infer_timezone_from_location( $location ) {
    $location_lower = strtolower( $location );
    
    // US State mappings
    $us_state_timezones = array(
        // Eastern Time
        'connecticut' => 'America/New_York',
        'delaware' => 'America/New_York', 
        'florida' => 'America/New_York',
        'georgia' => 'America/New_York',
        'maine' => 'America/New_York',
        'maryland' => 'America/New_York',
        'massachusetts' => 'America/New_York',
        'new hampshire' => 'America/New_York',
        'new jersey' => 'America/New_York',
        'new york' => 'America/New_York',
        'north carolina' => 'America/New_York',
        'ohio' => 'America/New_York',
        'pennsylvania' => 'America/New_York',
        'rhode island' => 'America/New_York',
        'south carolina' => 'America/New_York',
        'vermont' => 'America/New_York',
        'virginia' => 'America/New_York',
        'west virginia' => 'America/New_York',
        
        // Central Time
        'alabama' => 'America/Chicago',
        'arkansas' => 'America/Chicago',
        'illinois' => 'America/Chicago',
        'indiana' => 'America/Chicago', // Most of Indiana
        'iowa' => 'America/Chicago',
        'kansas' => 'America/Chicago',
        'kentucky' => 'America/Chicago',
        'louisiana' => 'America/Chicago',
        'minnesota' => 'America/Chicago',
        'mississippi' => 'America/Chicago',
        'missouri' => 'America/Chicago',
        'nebraska' => 'America/Chicago',
        'north dakota' => 'America/Chicago',
        'oklahoma' => 'America/Chicago',
        'south dakota' => 'America/Chicago',
        'tennessee' => 'America/Chicago',
        'texas' => 'America/Chicago',
        'wisconsin' => 'America/Chicago',
        
        // Mountain Time
        'colorado' => 'America/Denver',
        'montana' => 'America/Denver',
        'new mexico' => 'America/Denver',
        'utah' => 'America/Denver',
        'wyoming' => 'America/Denver',
        'arizona' => 'America/Phoenix', // Arizona doesn't observe DST
        'idaho' => 'America/Denver', // Southern Idaho
        
        // Pacific Time
        'california' => 'America/Los_Angeles',
        'nevada' => 'America/Los_Angeles',
        'oregon' => 'America/Los_Angeles',
        'washington' => 'America/Los_Angeles',
        
        // Alaska & Hawaii
        'alaska' => 'America/Anchorage',
        'hawaii' => 'Pacific/Honolulu',
    );
    
    // Country mappings (single timezone countries or dominant timezone)
    $country_timezones = array(
        'united states' => 'America/New_York', // Default to Eastern if no state specified
        'canada' => 'America/Toronto', // Eastern Canada as default
        'mexico' => 'America/Mexico_City',
        
        // Europe
        'portugal' => 'Europe/Lisbon',
        'spain' => 'Europe/Madrid',
        'france' => 'Europe/Paris',
        'germany' => 'Europe/Berlin',
        'italy' => 'Europe/Rome',
        'netherlands' => 'Europe/Amsterdam',
        'belgium' => 'Europe/Brussels',
        'switzerland' => 'Europe/Zurich',
        'austria' => 'Europe/Vienna',
        'poland' => 'Europe/Warsaw',
        'czech republic' => 'Europe/Prague',
        'slovakia' => 'Europe/Bratislava',
        'hungary' => 'Europe/Budapest',
        'romania' => 'Europe/Bucharest',
        'bulgaria' => 'Europe/Sofia',
        'greece' => 'Europe/Athens',
        'turkey' => 'Europe/Istanbul',
        'united kingdom' => 'Europe/London',
        'ireland' => 'Europe/Dublin',
        'denmark' => 'Europe/Copenhagen',
        'sweden' => 'Europe/Stockholm',
        'norway' => 'Europe/Oslo',
        'finland' => 'Europe/Helsinki',
        
        // Asia
        'japan' => 'Asia/Tokyo',
        'china' => 'Asia/Shanghai',
        'india' => 'Asia/Kolkata',
        'singapore' => 'Asia/Singapore',
        'thailand' => 'Asia/Bangkok',
        'vietnam' => 'Asia/Ho_Chi_Minh',
        'philippines' => 'Asia/Manila',
        'indonesia' => 'Asia/Jakarta',
        'malaysia' => 'Asia/Kuala_Lumpur',
        'south korea' => 'Asia/Seoul',
        
        // Oceania
        'australia' => 'Australia/Sydney', // Eastern Australia as default
        'new zealand' => 'Pacific/Auckland',
        
        // Americas
        'brazil' => 'America/Sao_Paulo',
        'argentina' => 'America/Argentina/Buenos_Aires',
        'chile' => 'America/Santiago',
        'colombia' => 'America/Bogota',
        'peru' => 'America/Lima',
        'venezuela' => 'America/Caracas',
        
        // Others
        'south africa' => 'Africa/Johannesburg',
        'egypt' => 'Africa/Cairo',
        'israel' => 'Asia/Jerusalem',
        'saudi arabia' => 'Asia/Riyadh',
        'uae' => 'Asia/Dubai',
        'russia' => 'Europe/Moscow', // Moscow as default
        'ukraine' => 'Europe/Kiev',
    );
    
    // First try US state mappings (more specific)
    foreach ( $us_state_timezones as $state => $timezone ) {
        if ( strpos( $location_lower, $state ) !== false ) {
            return $timezone;
        }
    }
    
    // Then try country mappings
    foreach ( $country_timezones as $country => $timezone ) {
        if ( strpos( $location_lower, $country ) !== false ) {
            return $timezone;
        }
    }
    
    // Default fallback
    return '';
}

/**
 * Create team if it doesn't exist
 */
function create_team_if_not_exists( $team_slug, $team_name ) {
    $team_file = __DIR__ . '/' . $team_slug . '.json';
    
    if ( file_exists( $team_file ) ) {
        return true; // Team already exists
    }
    
    // Create new team structure
    $team_config = array(
        'activity_url_prefix' => '',
        'team_name' => $team_name,
        'team_members' => array(),
        'leadership' => array(),
        'alumni' => array(),
        'events' => array(),
        'not_managing_team' => true, // Create as not managed as requested
    );
    
    $json_content = json_encode( $team_config, JSON_PRETTY_PRINT );
    return file_put_contents( $team_file, $json_content ) !== false;
}

/**
 * Interactive confirmation for field values
 */
function confirm_field( $field_name, $current_value, $description = '' ) {
    if ( empty( $current_value ) && $current_value !== '0' ) {
        echo "  $field_name: [empty]";
        if ( $description ) {
            echo " ($description)";
        }
        echo "\n";
        echo "  Enter new value (or press Enter to skip): ";
        $input = trim( fgets( STDIN ) );
        return $input === '' ? null : $input;
    }
    
    echo "  $field_name: $current_value";
    if ( $description ) {
        echo " ($description)";
    }
    echo "\n";
    echo "  [k]eep, [e]dit, [s]kip, [q]uit: ";
    
    $choice = strtolower( trim( fgets( STDIN ) ) );
    
    switch ( $choice ) {
        case 'k':
        case '':
            return $current_value;
        case 'e':
            echo "  Enter new value: ";
            $input = trim( fgets( STDIN ) );
            return $input === '' ? $current_value : $input;
        case 's':
            return null;
        case 'q':
            echo "Import cancelled.\n";
            exit( 0 );
        default:
            echo "Invalid choice. Keeping current value.\n";
            return $current_value;
    }
}

/**
 * Map imported data to team member format with optional interactive confirmation
 */
function map_person_data( $imported_data, $interactive = false ) {
    $member_data = array();
    
    if ( $interactive ) {
        echo "\n=== Confirming person data ===\n";
    }
    
    // Basic info
    if ( isset( $imported_data['name'] ) ) {
        if ( $interactive ) {
            $member_data['name'] = confirm_field( 'Name', $imported_data['name'] );
        } else {
            $member_data['name'] = $imported_data['name'];
        }
    }
    
    if ( isset( $imported_data['birthdate'] ) ) {
        if ( $interactive ) {
            $member_data['birthday'] = confirm_field( 'Birthday', $imported_data['birthdate'], 'YYYY-MM-DD format' );
        } else {
            $member_data['birthday'] = $imported_data['birthdate'];
        }
    }
    
    // Email field
    if ( isset( $imported_data['email'] ) ) {
        if ( $interactive ) {
            $member_data['email'] = confirm_field( 'Email', $imported_data['email'] );
        } else {
            $member_data['email'] = $imported_data['email'];
        }
    }
    
    // Website URL field with https:// validation
    if ( isset( $imported_data['website'] ) ) {
        $website_url = normalize_website_url( $imported_data['website'] );
        if ( $interactive ) {
            $member_data['website'] = confirm_field( 'Website', $website_url, 'must start with https://' );
        } else {
            $member_data['website'] = $website_url;
        }
    }
    
    // Address handling with smart formatting and timezone inference
    if ( isset( $imported_data['address'] ) ) {
        $formatted_location = format_location( $imported_data['address'], $interactive );
        $inferred_timezone = infer_timezone_from_location( $formatted_location );
        
        if ( $interactive ) {
            $member_data['location'] = confirm_field( 'Location', $formatted_location, 'City, Country or City, State, Country for US' );
            $member_data['timezone'] = confirm_field( 'Timezone', $inferred_timezone, 'inferred from location' );
        } else {
            $member_data['location'] = $formatted_location;
            $member_data['timezone'] = $inferred_timezone;
        }
    }
    
    // Store Twitter URL for potential use as fallback website
    $twitter_url = '';
    
    // Username mappings
    if ( isset( $imported_data['usernames'] ) ) {
        foreach ( $imported_data['usernames'] as $platform => $username ) {
            switch ( $platform ) {
                case 'wordpress.com':
                    // This is the key field - we'll use this for matching
                    break;
                case 'github':
                    if ( $interactive ) {
                        $github = confirm_field( 'GitHub username', $username );
                        if ( $github ) {
                            $member_data['github'] = $github;
                        }
                    } else {
                        $member_data['github'] = $username;
                    }
                    break;
                case 'twitter':
                    // Store Twitter URL for potential use as website fallback
                    $twitter_url = 'https://twitter.com/' . $username;
                    break;
                case 'wordpress.org':
                    if ( $interactive ) {
                        $wporg = confirm_field( 'WordPress.org username', $username );
                        if ( $wporg ) {
                            $member_data['wordpress'] = $wporg;
                        }
                    } else {
                        $member_data['wordpress'] = $username;
                    }
                    break;
            }
        }
    }
    
    // If no dedicated website was provided but we have Twitter, use Twitter as website
    if ( empty( $member_data['website'] ) && ! empty( $twitter_url ) ) {
        if ( $interactive ) {
            $use_twitter = confirm_field( 'Use Twitter as website (no dedicated website found)', $twitter_url );
            if ( $use_twitter ) {
                $member_data['website'] = $use_twitter;
            }
        } else {
            $member_data['website'] = $twitter_url;
        }
    }
    
    // Company/team info
    if ( isset( $imported_data['companies'] ) && ! empty( $imported_data['companies'] ) ) {
        $company = $imported_data['companies'][0]; // Take first company
        if ( isset( $company['start_date'] ) ) {
            if ( $interactive ) {
                $anniversary = confirm_field( 'Company anniversary', $company['start_date'], 'YYYY-MM-DD format' );
                if ( $anniversary ) {
                    $member_data['company_anniversary'] = $anniversary;
                }
            } else {
                $member_data['company_anniversary'] = $company['start_date'];
            }
        }
    }
    
    // Initialize empty values for required fields
    $member_data['nickname'] = $member_data['nickname'] ?? '';
    $member_data['role'] = $member_data['role'] ?? '';
    $member_data['timezone'] = $member_data['timezone'] ?? '';
    $member_data['partner'] = $member_data['partner'] ?? '';
    $member_data['kids'] = $member_data['kids'] ?? array();
    $member_data['github_repos'] = $member_data['github_repos'] ?? array();
    $member_data['linkedin'] = $member_data['linkedin'] ?? '';
    $member_data['personal_events'] = $member_data['personal_events'] ?? array();
    $member_data['links'] = $member_data['links'] ?? array();
    $member_data['notes'] = $member_data['notes'] ?? '';
    $member_data['location'] = $member_data['location'] ?? '';
    $member_data['birthday'] = $member_data['birthday'] ?? '';
    $member_data['company_anniversary'] = $member_data['company_anniversary'] ?? '';
    $member_data['email'] = $member_data['email'] ?? '';
    $member_data['website'] = $member_data['website'] ?? '';
    
    // Remove null values
    $member_data = array_filter( $member_data, function( $value ) {
        return $value !== null;
    } );
    
    return $member_data;
}

/**
 * Get WordPress.com username from imported data
 */
function get_wordpress_username( $imported_data ) {
    return $imported_data['usernames']['wordpress.com'] ?? null;
}

/**
 * Determine target team from company data
 */
function get_target_team( $imported_data, $interactive = false ) {
    if ( isset( $imported_data['companies'] ) && ! empty( $imported_data['companies'] ) ) {
        $company = $imported_data['companies'][0]; // Take first company
        
        // Check if team is explicitly specified
        if ( isset( $company['team'] ) ) {
            $team = strtolower( $company['team'] );
            if ( $interactive ) {
                $confirmed_team = confirm_field( 'Team', $team );
                return $confirmed_team ? strtolower( $confirmed_team ) : $team;
            }
            return $team;
        }
        
        // Otherwise derive from company name
        if ( isset( $company['name'] ) ) {
            $team = get_team_slug_from_company( $company['name'] );
            if ( $interactive ) {
                $confirmed_team = confirm_field( 'Team (derived from company)', $team );
                return $confirmed_team ? strtolower( $confirmed_team ) : $team;
            }
            return $team;
        }
    }
    
    $default_team = 'global';
    if ( $interactive ) {
        $confirmed_team = confirm_field( 'Team (default)', $default_team );
        return $confirmed_team ? strtolower( $confirmed_team ) : $default_team;
    }
    
    return $default_team;
}

/**
 * Import person data
 */
function import_person( $file_path, $interactive = false ) {
    if ( ! file_exists( $file_path ) ) {
        echo "Error: File not found: $file_path\n";
        return false;
    }
    
    echo "=== Processing: $file_path ===\n";
    
    $content = file_get_contents( $file_path );
    $imported_data = parse_person_data( $content );
    
    // Get WordPress.com username (this is our key field)
    $wordpress_username = get_wordpress_username( $imported_data );
    if ( empty( $wordpress_username ) ) {
        echo "Error: No WordPress.com username found in the data\n";
        return false;
    }
    
    // Show basic info
    echo "Name: {$imported_data['name']}\n";
    echo "WordPress.com username: $wordpress_username\n";
    
    if ( $interactive ) {
        echo "\nDo you want to import this person? [y]es, [s]kip, [q]uit: ";
        $choice = strtolower( trim( fgets( STDIN ) ) );
        
        switch ( $choice ) {
            case 's':
                echo "Skipped.\n";
                return true;
            case 'q':
                echo "Import cancelled.\n";
                exit( 0 );
            case 'y':
            case '':
                break;
            default:
                echo "Invalid choice. Skipping.\n";
                return true;
        }
    }
    
    // Determine target team
    $team_slug = get_target_team( $imported_data, $interactive );
    $team_name = ucfirst( $team_slug );
    
    echo "Target team: $team_slug\n";
    
    // Create team if it doesn't exist
    if ( ! create_team_if_not_exists( $team_slug, $team_name ) ) {
        echo "Error: Could not create team: $team_slug\n";
        return false;
    }
    
    // Load existing team data
    $team_file = __DIR__ . '/' . $team_slug . '.json';
    $team_config = json_decode( file_get_contents( $team_file ), true );
    
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        echo "Error: Invalid JSON in team file: " . json_last_error_msg() . "\n";
        return false;
    }
    
    // Map the imported data to our format
    $member_data = map_person_data( $imported_data, $interactive );
    
    // Check if user already exists and create backup
    $user_exists = isset( $team_config['team_members'][$wordpress_username] );
    if ( $user_exists ) {
        echo "User already exists - creating backup...\n";
        if ( ! create_backup( $team_file ) ) {
            echo "Warning: Could not create backup\n";
        } else {
            echo "Backup created successfully\n";
        }
        
        if ( $interactive ) {
            echo "User $wordpress_username already exists. [o]verwrite, [s]kip: ";
            $choice = strtolower( trim( fgets( STDIN ) ) );
            if ( $choice === 's' ) {
                echo "Skipped existing user.\n";
                return true;
            }
        }
    }
    
    // Final confirmation in interactive mode
    if ( $interactive ) {
        echo "\n=== Final confirmation ===\n";
        echo "About to import/update: $wordpress_username to team: $team_slug\n";
        echo "Continue? [y]es, [n]o: ";
        $choice = strtolower( trim( fgets( STDIN ) ) );
        if ( $choice !== 'y' && $choice !== '' ) {
            echo "Import cancelled.\n";
            return true;
        }
    }
    
    // Add/update the member
    $team_config['team_members'][$wordpress_username] = $member_data;
    
    // Save the updated team config
    $json_content = json_encode( $team_config, JSON_PRETTY_PRINT );
    if ( file_put_contents( $team_file, $json_content ) !== false ) {
        echo $user_exists ? "✅ Successfully updated user: $wordpress_username\n" : "✅ Successfully imported user: $wordpress_username\n";
        return true;
    } else {
        echo "❌ Error: Could not save team file\n";
        return false;
    }
}

/**
 * Show usage information
 */
function show_usage() {
    echo "Enhanced Person Data Importer\n";
    echo "Usage:\n";
    echo "  php import-person.php [--interactive] file1.yaml [file2.yaml ...]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --interactive    Enable interactive mode for field confirmation\n";
    echo "  --help          Show this help message\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php import-person.php person1.yaml person2.yaml\n";
    echo "  php import-person.php --interactive person1.yaml\n";
    echo "\n";
}

// Main execution
if ( $argc < 2 ) {
    show_usage();
    exit( 1 );
}

// Parse command line arguments
$interactive = false;
$files = array();

for ( $i = 1; $i < $argc; $i++ ) {
    $arg = $argv[$i];
    
    if ( $arg === '--interactive' ) {
        $interactive = true;
    } elseif ( $arg === '--help' ) {
        show_usage();
        exit( 0 );
    } else {
        $files[] = $arg;
    }
}

if ( empty( $files ) ) {
    echo "Error: No files specified.\n";
    show_usage();
    exit( 1 );
}

echo "Person Data Importer\n";
echo "Mode: " . ( $interactive ? "Interactive" : "Batch" ) . "\n";
echo "Files to process: " . count( $files ) . "\n";
echo str_repeat( "=", 50 ) . "\n";

$successful_imports = 0;
$failed_imports = 0;

foreach ( $files as $file_path ) {
    if ( import_person( $file_path, $interactive ) ) {
        $successful_imports++;
    } else {
        $failed_imports++;
    }
    
    echo "\n";
}

echo str_repeat( "=", 50 ) . "\n";
echo "Import Summary:\n";
echo "✅ Successful: $successful_imports\n";
echo "❌ Failed: $failed_imports\n";
echo "📁 Total files processed: " . count( $files ) . "\n";

exit( $failed_imports > 0 ? 1 : 0 );