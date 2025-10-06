#!/usr/bin/env php
<?php
namespace PersonalCRM;
/**
 * People Finder CLI
 * Search tool for teams and people
 */

class PeopleFinderCLI {
    private array $teams = [];
    private array $people = [];
    private array $allItems = [];
    private string $lastSearchQuery = '';

    public function __construct() {
        $this->loadData();
    }

    /**
     * Load all team and people data using the storage backend
     */
    private function loadData(): void {
        // Include common functions to get storage access
        require_once __DIR__ . '/personal-crm.php';

        $crm = PersonalCrm::get_instance();
        $storage = $crm->storage;
        $available_teams = $storage->get_available_groups();
        
        foreach ( $available_teams as $teamSlug ) {
            $teamName = $storage->get_group_name( $teamSlug );
            $data = $storage->get_group( $teamSlug );
            
            if ( !$data ) {
                continue;
            }

            // Process team stats
            $teamMembersCount = count( $data['team_members'] ?? [] );
            $leadershipCount = count( $data['leadership'] ?? [] );
            $consultantsCount = count( $data['consultants'] ?? [] );
            $alumniCount = count( $data['alumni'] ?? [] );
            $totalPeople = $teamMembersCount + $leadershipCount + $consultantsCount + $alumniCount;

            $team = [
                'slug' => $teamSlug,
                'name' => $teamName,
                'team_members' => $teamMembersCount,
                'leadership' => $leadershipCount,
                'consultants' => $consultantsCount,
                'alumni' => $alumniCount,
                'total_people' => $totalPeople,
                'is_default' => $data['default'] ?? false,
                'type' => $data['type'] ?? 'team',
                'itemType' => 'team'
            ];

            $this->teams[] = $team;

            // Process people
            foreach ( ['team_members', 'leadership', 'consultants', 'alumni'] as $category ) {
                if ( !isset( $data[$category] ) ) {
                    continue;
                }

                $categoryLabel = match( $category ) {
                    'team_members' => 'Member',
                    'leadership' => 'Leadership',
                    'consultants' => 'Consultant',
                    'alumni' => 'Alumni'
                };

                foreach ( $data[$category] as $username => $personData ) {
                    $person = $this->processPerson( $username, $personData, $categoryLabel, $teamName, $teamSlug );
                    $this->people[] = $person;
                }
            }
        }

        // Sort teams and people
        usort( $this->teams, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
        usort( $this->people, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

        // Combine into searchable items
        $this->allItems = array_merge( $this->teams, $this->people );
    }

    /**
     * Process a person's data
     */
    private function processPerson( string $username, array $personData, string $type, string $teamName, string $teamSlug ): array {
        $links = [];
        
        // Process links
        foreach ( $personData['links'] ?? [] as $text => $url ) {
            if ( $url ) {
                $links[] = ['text' => $text, 'url' => $url];
            }
        }

        // Add standard links
        if ( !empty( $personData['github'] ) ) {
            $links[] = ['text' => 'GitHub', 'url' => "https://github.com/{$personData['github']}"];
        }
        if ( !empty( $personData['linkedin'] ) ) {
            $links[] = ['text' => 'LinkedIn', 'url' => "https://linkedin.com/in/{$personData['linkedin']}"];
        }
        if ( !empty( $personData['website'] ) ) {
            $links[] = ['text' => 'Website', 'url' => $personData['website']];
        }
        if ( !empty( $personData['linear'] ) ) {
            $links[] = ['text' => 'Linear', 'url' => "https://linear.app/a8c/profiles/{$personData['linear']}"];
        }

        return [
            'username' => $username,
            'name' => $personData['name'] ?? '',
            'nickname' => $personData['nickname'] ?? '',
            'role' => $personData['role'] ?? '',
            'type' => $type,
            'team' => $teamName,
            'team_slug' => $teamSlug,
            'location' => $personData['location'] ?? '',
            'timezone' => $personData['timezone'] ?? '',
            'birthday' => $personData['birthday'] ?? '',
            'company_anniversary' => $personData['company_anniversary'] ?? '',
            'links' => $links,
            'itemType' => 'person'
        ];
    }

    /**
     * Calculate age from birthday
     */
    private function calculateAge( string $birthday ): ?int {
        if ( empty( $birthday ) ) {
            return null;
        }

        // Handle full date format (YYYY-MM-DD)
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birthday ) ) {
            $birthDate = new DateTime( $birthday );
            $currentDate = new DateTime();
            return $currentDate->diff( $birthDate )->y;
        }

        return null;
    }

    /**
     * Calculate years at company from anniversary
     */
    private function calculateYearsAtCompany( string $anniversary ): ?array {
        if ( empty( $anniversary ) ) {
            return null;
        }

        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $anniversary ) ) {
            $anniversaryDate = new DateTime( $anniversary );
            $currentDate = new DateTime();
            $diff = $currentDate->diff( $anniversaryDate );
            
            $years = $diff->y;
            $months = $diff->m;
            
            return [ 'years' => $years, 'months' => $months ];
        }

        return null;
    }

    /**
     * Get current time in a specific timezone with offset
     */
    private function getCurrentTimeInTimezone( string $timezone ): string {
        try {
            $personTz = new DateTimeZone( $timezone );
            $personTime = new DateTime( 'now', $personTz );
            
            // Check if PHP timezone is properly configured
            $defaultTz = date_default_timezone_get();
            if ( $defaultTz === 'UTC' && ini_get( 'date.timezone' ) === false ) {
                echo "\033[33mWarning: PHP timezone not configured. Please set 'date.timezone' in your php.ini\033[0m\n";
                echo "Current default: {$defaultTz} - offsets will be calculated from UTC\n\n";
            }
            
            $localTz = new DateTimeZone( $defaultTz );
            $localTime = new DateTime( 'now', $localTz );
            
            // Calculate offset in hours
            $offsetSeconds = $personTime->getOffset() - $localTime->getOffset();
            $offsetHours = $offsetSeconds / 3600;
            
            // Determine if it's after hours (before 8 AM or after 6 PM)
            $hour = (int)$personTime->format( 'H' );
            $isAfterHours = $hour < 8 || $hour >= 18;
            $timeEmoji = $isAfterHours ? '🌙' : '🕒';
            
            $offsetText = '';
            if ( $offsetHours > 0 ) {
                $offsetText = ' +' . (int)$offsetHours . ' hrs';
            } elseif ( $offsetHours < 0 ) {
                $offsetText = ' ' . (int)$offsetHours . ' hrs';
            }
            
            return $timeEmoji . ' ' . $personTime->format( 'H:i' ) . $offsetText;
        } catch ( Exception $e ) {
            return 'Unknown';
        }
    }

    /**
     * Get upcoming events for a person (birthday, company anniversary, personal events)
     */
    private function getPersonUpcomingEvents( array $personData, string $personType = '' ): array {
        $events = [];
        $currentDate = new DateTime();
        $cutoffDate = clone $currentDate;
        $cutoffDate->add( new DateInterval( 'P1Y' ) ); // 1 year from now
        
        // Debug
        // error_log("Current: " . $currentDate->format('Y-m-d') . ", Cutoff: " . $cutoffDate->format('Y-m-d'));

        // Birthday
        if ( !empty( $personData['birthday'] ) ) {
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $personData['birthday'] ) ) {
                // Full date - use this year and next year
                $birthDate = new DateTime( $personData['birthday'] );
                $thisYearBirthday = new DateTime( $currentDate->format( 'Y' ) . '-' . $birthDate->format( 'm-d' ) );
                $nextYearBirthday = new DateTime( ( $currentDate->format( 'Y' ) + 1 ) . '-' . $birthDate->format( 'm-d' ) );
                
                // Check if this year's birthday hasn't passed yet
                if ( $thisYearBirthday >= $currentDate && $thisYearBirthday <= $cutoffDate ) {
                    $age = $thisYearBirthday->diff( $birthDate )->y;
                    $events[] = ['date' => $thisYearBirthday, 'type' => 'birthday', 'age' => $age];
                }
                // Always check next year's birthday if it's within our window
                if ( $nextYearBirthday <= $cutoffDate ) {
                    $age = $nextYearBirthday->diff( $birthDate )->y;
                    $events[] = ['date' => $nextYearBirthday, 'type' => 'birthday', 'age' => $age];
                }
            } elseif ( preg_match( '/^\d{2}-\d{2}$/', $personData['birthday'] ) ) {
                // Month-day only
                $thisYear = $currentDate->format( 'Y' );
                $nextYear = $thisYear + 1;
                
                try {
                    $thisYearBirthday = new DateTime( $thisYear . '-' . $personData['birthday'] );
                    $nextYearBirthday = new DateTime( $nextYear . '-' . $personData['birthday'] );
                    
                    // Check if this year's birthday hasn't passed yet
                    if ( $thisYearBirthday >= $currentDate && $thisYearBirthday <= $cutoffDate ) {
                        $events[] = ['date' => $thisYearBirthday, 'type' => 'birthday']; // No age for MM-DD format
                    }
                    // Always check next year's birthday if it's within our window
                    if ( $nextYearBirthday <= $cutoffDate ) {
                        $events[] = ['date' => $nextYearBirthday, 'type' => 'birthday']; // No age for MM-DD format
                    }
                } catch ( Exception $e ) {
                    // Skip invalid dates
                }
            }
        }

        // Company anniversary (skip for alumni - they no longer work at the company)
        if ( !empty( $personData['company_anniversary'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $personData['company_anniversary'] ) && $personType !== 'Alumni' ) {
            $anniversaryDate = new DateTime( $personData['company_anniversary'] );
            $thisYearAnniversary = new DateTime( $currentDate->format( 'Y' ) . '-' . $anniversaryDate->format( 'm-d' ) );
            $nextYearAnniversary = new DateTime( ( $currentDate->format( 'Y' ) + 1 ) . '-' . $anniversaryDate->format( 'm-d' ) );
            
            // Check if this year's anniversary hasn't passed yet
            if ( $thisYearAnniversary >= $currentDate && $thisYearAnniversary <= $cutoffDate ) {
                $years = $thisYearAnniversary->diff( $anniversaryDate )->y;
                $events[] = ['date' => $thisYearAnniversary, 'type' => 'work anniversary', 'years' => $years];
            } 
            // Always check next year's anniversary if it's within our window
            if ( $nextYearAnniversary <= $cutoffDate ) {
                $years = $nextYearAnniversary->diff( $anniversaryDate )->y;
                $events[] = ['date' => $nextYearAnniversary, 'type' => 'work anniversary', 'years' => $years];
            }
        }

        // Children's birthdays
        if ( !empty( $personData['kids'] ) ) {
            foreach ( $personData['kids'] as $kid ) {
                if ( !empty( $kid['birthday'] ) ) {
                    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $kid['birthday'] ) ) {
                        // Full date - use this year and next year
                        $birthDate = new DateTime( $kid['birthday'] );
                        $thisYearBirthday = new DateTime( $currentDate->format( 'Y' ) . '-' . $birthDate->format( 'm-d' ) );
                        $nextYearBirthday = new DateTime( ( $currentDate->format( 'Y' ) + 1 ) . '-' . $birthDate->format( 'm-d' ) );
                        
                        // Check if this year's birthday hasn't passed yet
                        if ( $thisYearBirthday >= $currentDate && $thisYearBirthday <= $cutoffDate ) {
                            $age = $thisYearBirthday->diff( $birthDate )->y;
                            $events[] = ['date' => $thisYearBirthday, 'type' => "{$kid['name']}'s birthday", 'age' => $age];
                        }
                        // Always check next year's birthday if it's within our window
                        if ( $nextYearBirthday <= $cutoffDate ) {
                            $age = $nextYearBirthday->diff( $birthDate )->y;
                            $events[] = ['date' => $nextYearBirthday, 'type' => "{$kid['name']}'s birthday", 'age' => $age];
                        }
                    }
                }
            }
        }

        // Partner's birthday
        if ( !empty( $personData['partner_birthday'] ) ) {
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $personData['partner_birthday'] ) ) {
                // Full date - use this year and next year
                $birthDate = new DateTime( $personData['partner_birthday'] );
                $thisYearBirthday = new DateTime( $currentDate->format( 'Y' ) . '-' . $birthDate->format( 'm-d' ) );
                $nextYearBirthday = new DateTime( ( $currentDate->format( 'Y' ) + 1 ) . '-' . $birthDate->format( 'm-d' ) );
                
                // Check if this year's birthday hasn't passed yet
                if ( $thisYearBirthday >= $currentDate && $thisYearBirthday <= $cutoffDate ) {
                    $partnerName = $personData['partner'] ?? 'Partner';
                    $age = $thisYearBirthday->diff( $birthDate )->y;
                    $events[] = ['date' => $thisYearBirthday, 'type' => "{$partnerName}'s birthday", 'age' => $age];
                }
                // Always check next year's birthday if it's within our window
                if ( $nextYearBirthday <= $cutoffDate ) {
                    $partnerName = $personData['partner'] ?? 'Partner';
                    $age = $nextYearBirthday->diff( $birthDate )->y;
                    $events[] = ['date' => $nextYearBirthday, 'type' => "{$partnerName}'s birthday", 'age' => $age];
                }
            } elseif ( preg_match( '/^\d{2}-\d{2}$/', $personData['partner_birthday'] ) ) {
                // Month-day only
                $thisYear = $currentDate->format( 'Y' );
                $nextYear = $thisYear + 1;
                
                try {
                    $thisYearBirthday = new DateTime( $thisYear . '-' . $personData['partner_birthday'] );
                    $nextYearBirthday = new DateTime( $nextYear . '-' . $personData['partner_birthday'] );
                    
                    // Check if this year's birthday hasn't passed yet
                    if ( $thisYearBirthday >= $currentDate && $thisYearBirthday <= $cutoffDate ) {
                        $partnerName = $personData['partner'] ?? 'Partner';
                        $events[] = ['date' => $thisYearBirthday, 'type' => "{$partnerName}'s birthday"]; // No age for MM-DD format
                    }
                    // Always check next year's birthday if it's within our window
                    if ( $nextYearBirthday <= $cutoffDate ) {
                        $partnerName = $personData['partner'] ?? 'Partner';
                        $events[] = ['date' => $nextYearBirthday, 'type' => "{$partnerName}'s birthday"]; // No age for MM-DD format
                    }
                } catch ( Exception $e ) {
                    // Skip invalid dates
                }
            }
        }

        // Personal events
        if ( !empty( $personData['personal_events'] ) ) {
            foreach ( $personData['personal_events'] as $event ) {
                if ( !empty( $event['date'] ) ) {
                    try {
                        $eventDate = new DateTime( $event['date'] );
                        if ( $eventDate >= $currentDate && $eventDate <= $cutoffDate ) {
                            $events[] = [
                                'date' => $eventDate,
                                'type' => $event['type'] ?? 'event'
                            ];
                        }
                    } catch ( Exception $e ) {
                        // Skip invalid dates
                    }
                }
            }
        }

        // Sort by date
        usort( $events, fn( $a, $b ) => $a['date'] <=> $b['date'] );

        return $events;
    }

    /**
     * Get days until an event
     */
    private function getDaysUntilEvent( DateTime $eventDate ): int {
        $today = new DateTime();
        $today->setTime( 0, 0, 0 );
        $event = clone $eventDate;
        $event->setTime( 0, 0, 0 );
        
        return $today->diff( $event )->days;
    }

    /**
     * Get human-readable time until text
     */
    private function getTimeUntilText( int $daysUntil ): string {
        if ( $daysUntil === 0 ) {
            return '(today)';
        } elseif ( $daysUntil === 1 ) {
            return '(tomorrow)';
        } elseif ( $daysUntil <= 7 ) {
            return "(in {$daysUntil} days)";
        } elseif ( $daysUntil <= 30 ) {
            $weeks = round( $daysUntil / 7 );
            return "(in {$weeks} weeks)";
        } else {
            $months = round( $daysUntil / 30 );
            return "(in {$months} months)";
        }
    }

    /**
     * Search through all items
     */
    public function search( string $query ): array {
        $this->lastSearchQuery = $query; // Store for exact match checking
        
        if ( empty( trim( $query ) ) ) {
            // Show teams overview by default
            return array_map( fn( $team ) => array_merge( $team, [ 'itemType' => 'team' ] ), $this->teams );
        }

        $query = strtolower( trim( $query ) );
        $results = [];

        foreach ( $this->allItems as $item ) {
            $searchText = '';
            
            if ( $item['itemType'] === 'team' ) {
                $searchText = strtolower( $item['name'] );
            } else {
                $searchText = strtolower( implode( ' ', [
                    $item['name'],
                    $item['nickname'] ?? '',
                    $item['username'],
                    $item['role'],
                    $item['type'],
                    $item['team']
                ] ) );
            }

            if ( str_contains( $searchText, $query ) ) {
                $results[] = $item;
            }
        }

        return $results;
    }

    /**
     * Find exact team name match in results
     */
    private function findExactTeamMatch( array $results, string $query ): ?array {
        if ( empty( $query ) ) {
            return null;
        }
        
        $query = strtolower( trim( $query ) );
        
        foreach ( $results as $result ) {
            if ( $result['itemType'] === 'team' ) {
                if ( strtolower( $result['name'] ) === $query || strtolower( $result['slug'] ) === $query ) {
                    return $result;
                }
            }
        }
        
        return null;
    }

    /**
     * Display search results
     */
    public function displayResults( array $results ): void {
        if ( empty( $results ) ) {
            echo "No results found.\n";
            return;
        }

        // Add interactive options if base URL is configured
        $baseUrl = getenv( 'PEOPLE_FINDER_BASE_URL' );
        if ( $baseUrl && !empty( $results ) ) {
            // Auto-view details for single results or exact team matches
            $shouldAutoView = false;
            $itemToView = null;
            
            if ( count( $results ) === 1 ) {
                $shouldAutoView = true;
                $itemToView = $results[0];
            } else {
                // Check for exact team name match
                $exactTeamMatch = $this->findExactTeamMatch( $results, trim( $this->lastSearchQuery ?? '' ) );
                if ( $exactTeamMatch ) {
                    $shouldAutoView = true;
                    $itemToView = $exactTeamMatch;
                }
            }
            
            if ( $shouldAutoView && $itemToView ) {
                // Go directly to detail view without showing search results
                if ( $itemToView['itemType'] === 'person' ) {
                    $this->showPersonDetails( $itemToView );
                } elseif ( $itemToView['itemType'] === 'team' ) {
                    $this->showTeamView( $itemToView );
                }
                return;
            }
        }

        // Only display search results if not auto-viewing
        $teamCount = 0;
        $personCount = 0;

        foreach ( $results as $index => $item ) {
            if ( $item['itemType'] === 'team' ) {
                $teamCount++;
                $this->displayTeam( $item, $index + 1 );
            } else {
                $personCount++;
                $this->displayPerson( $item, $index + 1 );
            }
        }

        echo "\n";
        if ( $teamCount > 0 && $personCount > 0 ) {
            $teamText = $teamCount === 1 ? '1 team' : "{$teamCount} teams";
            $personText = $personCount === 1 ? '1 person' : "{$personCount} people";
            echo "Found {$teamText} and {$personText}\n";
        } elseif ( $teamCount > 0 ) {
            $teamText = $teamCount === 1 ? '1 team' : "{$teamCount} teams";
            echo "Found {$teamText}\n";
        } else {
            $personText = $personCount === 1 ? '1 person' : "{$personCount} people";
            echo "Found {$personText}\n";
        }

        if ( $baseUrl ) {
            $this->showInteractiveOptions( $results );
        }
    }

    /**
     * Show interactive options after search results
     */
    private function showInteractiveOptions( array $results ): void {
        echo "\n\033[90mPress number+'o' to open web page, number+'e' to edit, or just number to view details (e.g., '1o', '2e', '3')\033[0m\n";
        echo "\033[90mPress 'q' to quit, or Enter to search again:\033[0m ";
        
        $input = trim( fgets( STDIN ) );
        
        if ( $input === 'q' || $input === 'quit' ) {
            return;
        }
        
        if ( empty( $input ) ) {
            echo "\nEnter new search term: ";
            $newSearch = trim( fgets( STDIN ) );
            if ( !empty( $newSearch ) ) {
                $newResults = $this->search( $newSearch );
                $this->displayResults( $newResults );
            }
            return;
        }
        
        // Parse input like "1o", "2e", or just "3" for details
        if ( preg_match( '/^(\d+)([oe]?)$/', $input, $matches ) ) {
            $index = (int)$matches[1] - 1; // Convert to 0-based index
            $action = $matches[2] ?? ''; // Empty means view details
            
            if ( isset( $results[$index] ) ) {
                $item = $results[$index];
                if ( empty( $action ) ) {
                    // Just number pressed - show details/view
                    if ( $item['itemType'] === 'person' ) {
                        $this->showPersonDetails( $item );
                    } elseif ( $item['itemType'] === 'team' ) {
                        $this->showTeamView( $item );
                    }
                } else {
                    // Number + action (o or e)
                    $this->handleAction( $item, $action );
                }
            } else {
                echo "Invalid selection. Please try again.\n";
                $this->showInteractiveOptions( $results );
            }
        } else {
            echo "Invalid input. Use format like '1o', '2e', or just '3' for details. Try again.\n";
            $this->showInteractiveOptions( $results );
        }
    }

    /**
     * Handle user action (open or edit)
     */
    private function handleAction( array $item, string $action ): void {
        $baseUrl = rtrim( getenv( 'PEOPLE_FINDER_BASE_URL' ), '/' );
        
        if ( $item['itemType'] === 'team' ) {
            if ( $action === 'o' ) {
                // Open team page
                $url = $baseUrl . '/';
                if ( $item['slug'] !== 'team' ) {
                    $url .= '?team=' . urlencode( $item['slug'] );
                }
            } else { // 'e'
                // Edit team page
                $url = $baseUrl . '/admin/index.php?team=' . urlencode( $item['slug'] );
            }
        } else {
            // Person
            if ( $action === 'o' ) {
                // Open person page
                $url = $baseUrl . '/person.php?person=' . urlencode( $item['username'] );
                if ( $item['team_slug'] !== 'team' ) {
                    $url .= '&team=' . urlencode( $item['team_slug'] );
                }
            } else { // 'e'
                // Edit person page
                $url = $baseUrl . '/admin/index.php?person=' . urlencode( $item['username'] );
                if ( $item['team_slug'] !== 'team' ) {
                    $url .= '&team=' . urlencode( $item['team_slug'] );
                }
            }
        }
        
        echo "Opening: {$url}\n";
        $this->openUrl( $url );
    }

    /**
     * Open URL in default browser
     */
    private function openUrl( string $url ): void {
        $os = PHP_OS_FAMILY;
        
        switch ( strtolower( $os ) ) {
            case 'darwin': // macOS
                exec( 'open ' . escapeshellarg( $url ) );
                break;
            case 'windows':
                exec( 'start ' . escapeshellarg( $url ) );
                break;
            case 'linux':
                exec( 'xdg-open ' . escapeshellarg( $url ) );
                break;
            default:
                echo "Please open manually: {$url}\n";
                break;
        }
    }

    /**
     * Show team view with members and events
     */
    private function showTeamView( array $team ): void {
        // Load team data from JSON file
        $filePath = $team['slug'] . '.json';
        if ( !file_exists( $filePath ) ) {
            echo "Could not load team information.\n";
            return;
        }

        $teamData = json_decode( file_get_contents( $filePath ), true );
        if ( !$teamData ) {
            echo "Could not parse team data.\n";
            return;
        }

        echo "\n\033[1m═══════════════════════════════════════════════════════════════\033[0m\n";
        echo "\033[1m🏢 {$team['name']}\033[0m";
        if ( $team['is_default'] ) {
            echo " \033[32m[DEFAULT]\033[0m";
        }
        echo "\n";
        echo "\033[1m═══════════════════════════════════════════════════════════════\033[0m\n";

        // Show team members
        if ( !empty( $teamData['team_members'] ) ) {
            echo "\n\033[1m👥 Members (" . count( $teamData['team_members'] ) . ")\033[0m\n";
            $index = 1;
            foreach ( $teamData['team_members'] as $username => $memberData ) {
                $this->displayTeamMember( $index, $username, $memberData, $team['slug'] );
                $index++;
            }
        }

        // Show consultants
        if ( !empty( $teamData['consultants'] ) ) {
            echo "\n\033[1m🔧 Consultants (" . count( $teamData['consultants'] ) . ")\033[0m\n";
            foreach ( $teamData['consultants'] as $username => $consultantData ) {
                $this->displayTeamMember( $index, $username, $consultantData, $team['slug'] );
                $index++;
            }
        }

        // Show upcoming team events
        $this->showTeamUpcomingEvents( $teamData );

        // Create indexed person list for selection
        $allMembers = [];
        $memberIndex = 1;
        foreach ( $teamData['team_members'] as $username => $memberData ) {
            $allMembers[$memberIndex] = $this->processPerson( $username, $memberData, 'Team Member', $team['name'], $team['slug'] );
            $memberIndex++;
        }
        foreach ( $teamData['consultants'] ?? [] as $username => $consultantData ) {
            $allMembers[$memberIndex] = $this->processPerson( $username, $consultantData, 'Consultant', $team['name'], $team['slug'] );
            $memberIndex++;
        }

        // Interactive selection
        if ( !empty( $allMembers ) ) {
            echo "\n\033[90mPress a number to view person details, 'o' to open team page, 'e' to edit team:\033[0m ";
            $this->handleTeamInteraction( $allMembers, $team );
        }
    }

    /**
     * Display a team member in the team view (compact one-line format)
     */
    private function displayTeamMember( int $index, string $username, array $memberData, string $teamSlug ): void {
        $name = $memberData['name'] ?? '';
        $nickname = $memberData['nickname'] ?? '';
        $role = $memberData['role'] ?? '';
        $location = $memberData['location'] ?? '';
        $timezone = $memberData['timezone'] ?? '';

        // Build the one-line display
        $line = "\033[1m{$index}. 👤 {$name}\033[0m";
        
        if ( !empty( $nickname ) ) {
            $line .= " \"{$nickname}\"";
        }
        
        $line .= " (@{$username})";
        
        if ( !empty( $role ) ) {
            $line .= " • \033[36m{$role}\033[0m";
        }
        
        if ( !empty( $location ) ) {
            $line .= " • 📍 {$location}";
            if ( !empty( $timezone ) ) {
                $currentTime = $this->getCurrentTimeInTimezone( $timezone );
                $line .= " ({$currentTime})";
            }
        }
        
        echo "{$line}\n";
    }

    /**
     * Show upcoming events for the entire team
     */
    private function showTeamUpcomingEvents( array $teamData ): void {
        $allEvents = [];
        $currentDate = new DateTime();
        $cutoffDate = clone $currentDate;
        $cutoffDate->add( new DateInterval( 'P3M' ) ); // 3 months from now

        // Collect events from all team members, consultants, and alumni
        $peopleByCategory = [
            'team_members' => $teamData['team_members'] ?? [],
            'consultants' => $teamData['consultants'] ?? [],
            'leadership' => $teamData['leadership'] ?? [],
            'alumni' => $teamData['alumni'] ?? []
        ];
        
        foreach ( $peopleByCategory as $category => $people ) {
            $personType = match( $category ) {
                'team_members' => 'Member',
                'leadership' => 'Leadership',
                'consultants' => 'Consultant',
                'alumni' => 'Alumni'
            };
            
            foreach ( $people as $username => $personData ) {
                $personEvents = $this->getPersonUpcomingEvents( $personData, $personType );
                foreach ( $personEvents as $event ) {
                    if ( $event['date'] <= $cutoffDate ) {
                        $eventData = [
                            'date' => $event['date'],
                            'type' => $event['type'],
                            'person' => $personData['name'] ?? $username
                        ];
                        // Copy age/years if present
                        if ( isset( $event['age'] ) ) {
                            $eventData['age'] = $event['age'];
                        }
                        if ( isset( $event['years'] ) ) {
                            $eventData['years'] = $event['years'];
                        }
                        $allEvents[] = $eventData;
                    }
                }
            }
        }

        // Sort by date
        usort( $allEvents, fn( $a, $b ) => $a['date'] <=> $b['date'] );

        if ( !empty( $allEvents ) ) {
            echo "\n\033[1m📅 Upcoming Team Events\033[0m\n";
            foreach ( array_slice( $allEvents, 0, 10 ) as $event ) { // Show max 10 events
                $daysUntil = $this->getDaysUntilEvent( $event['date'] );
                $timeUntilText = $this->getTimeUntilText( $daysUntil );
                
                echo "   {$event['date']->format( 'M j' )} • {$event['person']}'s {$event['type']}";
                
                // Add age or years information
                if ( isset( $event['age'] ) ) {
                    $ageText = $event['age'] === 1 ? '1 year old' : "{$event['age']} years old";
                    echo " ({$ageText})";
                } elseif ( isset( $event['years'] ) ) {
                    $yearsText = $event['years'] === 1 ? '1 year' : "{$event['years']} years";
                    echo " ({$yearsText})";
                }
                
                echo " {$timeUntilText}\n";
            }
        }
    }

    /**
     * Handle team view interactions
     */
    private function handleTeamInteraction( array $members, array $team ): void {
        $input = trim( strtolower( fgets( STDIN ) ) );
        
        if ( empty( $input ) ) {
            return;
        }
        
        // Handle 'o' and 'e' for web page actions
        if ( $input === 'o' || $input === 'e' ) {
            $this->handleAction( $team, $input );
            echo "\033[90mPress Enter to continue...\033[0m ";
            fgets( STDIN );
            return;
        }
        
        // Handle numeric input for person selection
        if ( is_numeric( $input ) ) {
            $memberIndex = (int)$input;
            if ( isset( $members[$memberIndex] ) ) {
                $this->showPersonDetails( $members[$memberIndex] );
                return;
            } else {
                echo "Invalid selection.\n";
            }
        } else {
            echo "Invalid input.\n";
        }
        
        // Show prompt again
        echo "\033[90mPress a number to view person details, 'o' to open team page, 'e' to edit team:\033[0m ";
        $this->handleTeamInteraction( $members, $team );
    }

    /**
     * Show detailed view of a person
     */
    private function showPersonDetails( array $item ): void {
        if ( $item['itemType'] !== 'person' ) {
            echo "Details view is only available for people.\n";
            return;
        }

        // Load full person data from JSON
        $filePath = $item['team_slug'] . '.json';
        if ( !file_exists( $filePath ) ) {
            echo "Could not load detailed information.\n";
            return;
        }

        $data = json_decode( file_get_contents( $filePath ), true );
        if ( !$data ) {
            echo "Could not parse team data.\n";
            return;
        }

        // Find the person in the data
        $personData = null;
        $categories = [ 'team_members', 'leadership', 'consultants', 'alumni' ];
        foreach ( $categories as $category ) {
            if ( isset( $data[$category][$item['username']] ) ) {
                $personData = $data[$category][$item['username']];
                break;
            }
        }

        if ( !$personData ) {
            echo "Could not find person data.\n";
            return;
        }

        echo "\n\033[1m═══════════════════════════════════════════════════════════════\033[0m\n";
        echo "\033[1m👤 {$item['name']}\033[0m";
        if ( !empty( $item['nickname'] ) ) {
            echo " \"{$item['nickname']}\"";
        }
        echo " (@{$item['username']})\n";
        echo "\033[1m═══════════════════════════════════════════════════════════════\033[0m\n\n";

        // Basic info
        echo "\033[1m📋 Basic Information\033[0m\n";
        echo "   Role: {$item['type']}";
        if ( !empty( $item['role'] ) ) {
            echo " • {$item['role']}";
        }
        echo "\n";
        echo "   🏢 Team: {$item['team']}\n";
        if ( !empty( $item['location'] ) ) {
            echo "   📍 Location: {$item['location']}\n";
        }
        if ( !empty( $personData['timezone'] ) ) {
            $currentTime = $this->getCurrentTimeInTimezone( $personData['timezone'] );
            echo "   ⏰ Time: {$currentTime}\n";
        }

        // Age and tenure
        if ( !empty( $item['birthday'] ) ) {
            $age = $this->calculateAge( $item['birthday'] );
            if ( $age !== null ) {
                echo "   🎂 Age: {$age} years old\n";
            }
        }

        if ( !empty( $item['company_anniversary'] ) ) {
            $tenure = $this->calculateYearsAtCompany( $item['company_anniversary'] );
            if ( $tenure !== null ) {
                if ( $tenure['years'] > 0 ) {
                    $tenureText = "{$tenure['years']} years";
                    if ( $tenure['months'] > 0 ) {
                        $tenureText .= ", {$tenure['months']} months";
                    }
                } else {
                    $tenureText = "{$tenure['months']} months";
                }
                echo "   💼 Company tenure: {$tenureText}\n";
            }
        }

        // Family
        if ( !empty( $personData['partner'] ) || !empty( $personData['kids'] ) ) {
            echo "\n\033[1m👨‍👩‍👧‍👦 Family\033[0m\n";
            if ( !empty( $personData['partner'] ) ) {
                echo "   Partner: {$personData['partner']}\n";
            }
            if ( !empty( $personData['kids'] ) ) {
                echo "   Children:\n";
                foreach ( $personData['kids'] as $kid ) {
                    $kidAge = '';
                    if ( isset( $kid['birth_year'] ) ) {
                        $currentYear = (int)date( 'Y' );
                        $kidAge = ' (' . ( $currentYear - $kid['birth_year'] ) . ' years old)';
                    }
                    echo "     • {$kid['name']}{$kidAge}\n";
                }
            }
        }

        // Upcoming events
        $upcomingEvents = $this->getPersonUpcomingEvents( $personData, $item['type'] ?? '' );
        if ( !empty( $upcomingEvents ) ) {
            echo "\n\033[1m📅 Upcoming Events\033[0m\n";
            foreach ( $upcomingEvents as $event ) {
                $daysUntil = $this->getDaysUntilEvent( $event['date'] );
                $timeUntilText = $this->getTimeUntilText( $daysUntil );
                
                echo "   {$event['date']->format( 'M j' )} • {$event['type']}";
                
                // Add age or years information
                if ( isset( $event['age'] ) ) {
                    $ageText = $event['age'] === 1 ? '1 year old' : "{$event['age']} years old";
                    echo " ({$ageText})";
                } elseif ( isset( $event['years'] ) ) {
                    $yearsText = $event['years'] === 1 ? '1 year' : "{$event['years']} years";
                    echo " ({$yearsText})";
                }
                
                echo " {$timeUntilText}\n";
            }
        }

        // Links and accounts
        $allLinks = [];
        $usedKeys = [];
        
        // Add custom links
        foreach ( $personData['links'] ?? [] as $text => $url ) {
            if ( $url ) {
                $key = $this->assignKey( $text, $usedKeys );
                $allLinks[] = ['text' => $text, 'url' => $url, 'key' => $key, 'type' => 'custom'];
            }
        }

        // Add standard accounts
        if ( !empty( $personData['github'] ) ) {
            $key = $this->assignKey( 'GitHub', $usedKeys );
            $allLinks[] = ['text' => 'GitHub', 'url' => "https://github.com/{$personData['github']}", 'key' => $key, 'type' => 'account'];
        }
        if ( !empty( $personData['linkedin'] ) ) {
            $key = $this->assignKey( 'LinkedIn', $usedKeys );
            $allLinks[] = ['text' => 'LinkedIn', 'url' => "https://linkedin.com/in/{$personData['linkedin']}", 'key' => $key, 'type' => 'account'];
        }
        if ( !empty( $personData['website'] ) ) {
            $key = $this->assignKey( 'Website', $usedKeys );
            $allLinks[] = ['text' => 'Website', 'url' => $personData['website'], 'key' => $key, 'type' => 'account'];
        }
        if ( !empty( $personData['wordpress'] ) ) {
            $key = $this->assignKey( 'WordPress.org', $usedKeys );
            $allLinks[] = ['text' => 'WordPress.org', 'url' => "https://profiles.wordpress.org/{$personData['wordpress']}", 'key' => $key, 'type' => 'account'];
        }
        if ( !empty( $personData['linear'] ) ) {
            $key = $this->assignKey( 'Linear', $usedKeys );
            $allLinks[] = ['text' => 'Linear', 'url' => "https://linear.app/a8c/profiles/{$personData['linear']}", 'key' => $key, 'type' => 'account'];
        }
        if ( !empty( $personData['email'] ) ) {
            $key = $this->assignKey( 'Email', $usedKeys );
            $allLinks[] = ['text' => 'Email', 'url' => "mailto:{$personData['email']}", 'key' => $key, 'type' => 'contact'];
        }

        // Add GitHub repos
        if ( !empty( $personData['github_repos'] ) ) {
            foreach ( $personData['github_repos'] as $repo ) {
                $key = $this->assignKey( $repo, $usedKeys );
                $allLinks[] = ['text' => "📦 {$repo}", 'url' => "https://github.com/{$repo}", 'key' => $key, 'type' => 'repo'];
            }
        }

        if ( !empty( $allLinks ) ) {
            echo "\n\033[1m🔗 Links & Accounts\033[0m\n";
            foreach ( $allLinks as $link ) {
                $displayText = $this->highlightKey( $link['text'], $link['key'] );
                echo "   {$displayText}\n";
                echo "   \033[90m{$link['url']}\033[0m\n";
            }
            
            $keys = array_column( $allLinks, 'key' );
            echo "\n\033[90mPress " . implode( ', ', $keys ) . " to open links, '\033[4mo\033[0m\033[90m' to open web page, '\033[4me\033[0m\033[90m' to edit:\033[0m ";
            $this->handleDetailActions( $allLinks, $item );
        } else {
            echo "\n\033[90mPress '\033[4mo\033[0m\033[90m' to open web page, '\033[4me\033[0m\033[90m' to edit:\033[0m ";
            $this->handleDetailActions( [], $item );
        }
    }

    /**
     * Assign a keyboard shortcut key for a link
     */
    private function assignKey( string $text, array &$usedKeys ): string {
        // Try first letter
        $firstLetter = strtolower( substr( $text, 0, 1 ) );
        if ( !in_array( $firstLetter, $usedKeys ) && $firstLetter !== 'o' && $firstLetter !== 'e' ) {
            $usedKeys[] = $firstLetter;
            return $firstLetter;
        }
        
        // Try other letters in the word
        for ( $i = 1; $i < strlen( $text ); $i++ ) {
            $letter = strtolower( $text[$i] );
            if ( ctype_alpha( $letter ) && !in_array( $letter, $usedKeys ) && $letter !== 'o' && $letter !== 'e' ) {
                $usedKeys[] = $letter;
                return $letter;
            }
        }
        
        // Fallback to numbers if all letters are taken
        for ( $i = 1; $i <= 9; $i++ ) {
            if ( !in_array( (string)$i, $usedKeys ) ) {
                $usedKeys[] = (string)$i;
                return (string)$i;
            }
        }
        
        return 'x'; // Final fallback
    }

    /**
     * Highlight the keyboard shortcut letter in text using underline
     */
    private function highlightKey( string $text, string $key ): string {
        // Find the position of the key in the text (case insensitive)
        $pos = stripos( $text, $key );
        if ( $pos !== false ) {
            return substr( $text, 0, $pos ) . 
                   "\033[4m" . $text[$pos] . "\033[0m" . 
                   substr( $text, $pos + 1 );
        }
        return $text;
    }

    /**
     * Handle actions in person details view
     */
    private function handleDetailActions( array $links, array $item ): void {
        $input = trim( strtolower( fgets( STDIN ) ) );
        
        if ( empty( $input ) ) {
            return;
        }
        
        // Handle 'o' and 'e' for web page actions
        if ( $input === 'o' || $input === 'e' ) {
            $this->handleAction( $item, $input );
            echo "\033[90mPress Enter to continue...\033[0m ";
            fgets( STDIN );
            return;
        }
        
        // Handle keyboard shortcuts for links
        foreach ( $links as $link ) {
            if ( $input === $link['key'] ) {
                echo "Opening: {$link['url']}\n";
                $this->openUrl( $link['url'] );
                
                echo "\033[90mPress Enter to continue...\033[0m ";
                fgets( STDIN );
                return;
            }
        }
        
        echo "Invalid input.\n";
        
        // Show prompt again
        if ( !empty( $links ) ) {
            $keys = array_column( $links, 'key' );
            echo "\033[90mPress " . implode( ', ', $keys ) . " to open links, '\033[4mo\033[0m\033[90m' to open web page, '\033[4me\033[0m\033[90m' to edit:\033[0m ";
        } else {
            echo "\033[90mPress '\033[4mo\033[0m\033[90m' to open web page, '\033[4me\033[0m\033[90m' to edit:\033[0m ";
        }
        $this->handleDetailActions( $links, $item );
    }

    /**
     * Display team information
     */
    private function displayTeam( array $team, int $index ): void {
        echo "\n\033[1m{$index}. 🏢 {$team['name']}\033[0m";
        if ( $team['is_default'] ) {
            echo " \033[32m[DEFAULT]\033[0m";
        }
        echo "\n";
        
        $stats = [];
        if ( $team['team_members'] > 0 ) {
            $stats[] = "👥 {$team['team_members']} members";
        }
        if ( $team['leadership'] > 0 ) {
            $stats[] = "👑 {$team['leadership']} leaders";
        }
        if ( isset( $team['consultants'] ) && $team['consultants'] > 0 ) {
            $stats[] = "🔧 {$team['consultants']} consultants";
        }
        if ( isset( $team['alumni'] ) && $team['alumni'] > 0 ) {
            $stats[] = "🎓 {$team['alumni']} alumni";
        }
        
        if ( !empty( $stats ) ) {
            echo "   " . implode( ' • ', $stats ) . "\n";
        }
        echo "   Total: {$team['total_people']} people\n";
        
        // Add web link if base URL is configured
        $baseUrl = getenv( 'PEOPLE_FINDER_BASE_URL' );
        if ( $baseUrl ) {
            $teamUrl = rtrim( $baseUrl, '/' ) . '/';
            if ( $team['slug'] !== 'team' ) {
                $teamUrl .= '?team=' . urlencode( $team['slug'] );
            }
            echo "   \033[94m🌐 {$teamUrl}\033[0m\n";
        }
    }

    /**
     * Display person information
     */
    private function displayPerson( array $person, int $index ): void {
        echo "\n\033[1m{$index}. 👤 {$person['name']}\033[0m";
        if ( !empty( $person['nickname'] ) ) {
            echo " \"{$person['nickname']}\"";
        }
        echo " (@{$person['username']})\n";

        $roleInfo = [];
        $roleInfo[] = $person['type'];
        if ( !empty( $person['role'] ) ) {
            $roleInfo[] = $person['role'];
        }
        
        if ( !empty( $roleInfo ) ) {
            echo "   \033[36m" . implode( ' • ', $roleInfo ) . "\033[0m\n";
        }

        $info = [];
        if ( !empty( $person['team'] ) ) {
            $info[] = "🏢 {$person['team']}";
        }
        if ( !empty( $person['location'] ) ) {
            $locationText = "📍 {$person['location']}";
            // Add current time if timezone is available
            if ( !empty( $person['timezone'] ) ) {
                $currentTime = $this->getCurrentTimeInTimezone( $person['timezone'] );
                $locationText .= " ({$currentTime})";
            }
            $info[] = $locationText;
        }

        // Add age if birthday is available
        if ( !empty( $person['birthday'] ) ) {
            $age = $this->calculateAge( $person['birthday'] );
            if ( $age !== null ) {
                $info[] = "🎂 {$age} years old";
            } elseif ( preg_match( '/^\d{2}-\d{2}$/', $person['birthday'] ) ) {
                // Month-day format
                $info[] = "🎂 " . date( 'M j', strtotime( '2000-' . $person['birthday'] ) );
            }
        }

        // Add company anniversary if available
        if ( !empty( $person['company_anniversary'] ) ) {
            $tenure = $this->calculateYearsAtCompany( $person['company_anniversary'] );
            if ( $tenure !== null ) {
                if ( $tenure['years'] > 0 ) {
                    $tenureText = "{$tenure['years']} years";
                    if ( $tenure['months'] > 0 ) {
                        $tenureText .= ", {$tenure['months']} months";
                    }
                } else {
                    $tenureText = "{$tenure['months']} months";
                }
                $info[] = "💼 {$tenureText} at company";
            }
        }

        if ( !empty( $info ) ) {
            echo "   " . implode( ' • ', $info ) . "\n";
        }

        if ( !empty( $person['links'] ) ) {
            echo "   \033[90mLinks:\033[0m ";
            $linkTexts = array_map( fn( $link ) => $link['text'], $person['links'] );
            echo implode( ', ', $linkTexts ) . "\n";
        }
        
        // Add web link if base URL is configured
        $baseUrl = getenv( 'PEOPLE_FINDER_BASE_URL' );
        if ( $baseUrl ) {
            $personUrl = rtrim( $baseUrl, '/' ) . '/person.php?person=' . urlencode( $person['username'] );
            if ( $person['team_slug'] !== 'team' ) {
                $personUrl .= '&team=' . urlencode( $person['team_slug'] );
            }
            echo "   \033[94m🌐 {$personUrl}\033[0m\n";
        }
    }

    /**
     * Show overall statistics
     */
    public function showStats(): void {
        echo "\n\033[1mOverall Statistics:\033[0m\n";
        echo "Teams: " . count( $this->teams ) . "\n";
        echo "People: " . count( $this->people ) . "\n";
        
        $totalByType = [];
        foreach ( $this->people as $person ) {
            $type = $person['type'];
            $totalByType[$type] = ( $totalByType[$type] ?? 0 ) + 1;
        }
        
        foreach ( $totalByType as $type => $count ) {
            echo "  {$type}: {$count}\n";
        }
        echo "\n";
    }

    /**
     * Parse time period string into DateInterval
     */
    private function parseTimePeriod( string $timePeriod ): DateInterval {
        // Default fallback
        $defaultInterval = new DateInterval( 'P1M' ); // 1 month
        
        $timePeriod = strtolower( trim( $timePeriod ) );
        
        // Handle formats like: 3m, 2w, 7d, 1y
        if ( preg_match( '/^(\d+)([mwdy])$/', $timePeriod, $matches ) ) {
            $number = (int)$matches[1];
            $unit = $matches[2];
            
            try {
                switch ( $unit ) {
                    case 'd': // days
                        return new DateInterval( "P{$number}D" );
                    case 'w': // weeks
                        return new DateInterval( "P{$number}W" );
                    case 'm': // months
                        return new DateInterval( "P{$number}M" );
                    case 'y': // years
                        return new DateInterval( "P{$number}Y" );
                }
            } catch ( Exception $e ) {
                // Fall back to default on invalid interval
                return $defaultInterval;
            }
        }
        
        // Handle full words: month, months, week, weeks, day, days, year, years
        if ( preg_match( '/^(\d+)\s*(day|days|week|weeks|month|months|year|years)$/', $timePeriod, $matches ) ) {
            $number = (int)$matches[1];
            $unit = $matches[2];
            
            try {
                if ( in_array( $unit, ['day', 'days'] ) ) {
                    return new DateInterval( "P{$number}D" );
                } elseif ( in_array( $unit, ['week', 'weeks'] ) ) {
                    return new DateInterval( "P{$number}W" );
                } elseif ( in_array( $unit, ['month', 'months'] ) ) {
                    return new DateInterval( "P{$number}M" );
                } elseif ( in_array( $unit, ['year', 'years'] ) ) {
                    return new DateInterval( "P{$number}Y" );
                }
            } catch ( Exception $e ) {
                return $defaultInterval;
            }
        }
        
        return $defaultInterval;
    }

    /**
     * Get human-readable description of time period
     */
    private function getTimePeriodDescription( string $timePeriod ): string {
        $timePeriod = strtolower( trim( $timePeriod ) );
        
        if ( preg_match( '/^(\d+)([mwdy])$/', $timePeriod, $matches ) ) {
            $number = (int)$matches[1];
            $unit = $matches[2];
            
            $unitNames = [
                'd' => $number === 1 ? 'day' : 'days',
                'w' => $number === 1 ? 'week' : 'weeks', 
                'm' => $number === 1 ? 'month' : 'months',
                'y' => $number === 1 ? 'year' : 'years'
            ];
            
            return "{$number} {$unitNames[$unit]}";
        }
        
        if ( preg_match( '/^(\d+)\s*(day|days|week|weeks|month|months|year|years)$/', $timePeriod, $matches ) ) {
            return trim( $timePeriod );
        }
        
        return '1 month'; // default description
    }

    /**
     * Show upcoming events for all teams/groups
     */
    public function showAllUpcomingEvents( bool $teamsOnly = false, bool $groupsOnly = false, string $timePeriod = '1m' ): void {
        $allEvents = [];
        $currentDate = new DateTime();
        $cutoffDate = clone $currentDate;
        $cutoffDate->add( $this->parseTimePeriod( $timePeriod ) );

        $teamsToProcess = $this->teams;
        
        // Apply filtering
        if ( $teamsOnly && $groupsOnly ) {
            // Both flags set, show all (ignore contradiction)
        } elseif ( $teamsOnly ) {
            // Filter to only teams (exclude groups/projects)
            $teamsToProcess = array_filter( $this->teams, function( $team ) {
                // Exclude groups explicitly marked as such
                if ( isset( $team['type'] ) && $team['type'] === 'group' ) {
                    return false;
                }
                // Consider teams with default=true or traditional team structure as "teams"
                return $team['is_default'] || 
                       $team['team_members'] > 0 || 
                       $team['leadership'] > 0;
            });
        } elseif ( $groupsOnly ) {
            // Filter to only groups/projects (exclude main teams)
            $teamsToProcess = array_filter( $this->teams, function( $team ) {
                // Use type field from JSON or fallback to structure-based logic
                if ( isset( $team['type'] ) && $team['type'] === 'group' ) {
                    return true;
                }
                // Fallback: Consider non-default teams with mainly consultants as "groups"
                return !$team['is_default'] && 
                       $team['consultants'] > 0 && 
                       $team['team_members'] === 0 && 
                       $team['leadership'] === 0;
            });
        }

        // Collect events from all teams/groups
        foreach ( $teamsToProcess as $team ) {
            $filePath = $team['slug'] . '.json';
            if ( !file_exists( $filePath ) || !is_readable( $filePath ) ) {
                continue;
            }

            $teamData = json_decode( file_get_contents( $filePath ), true );
            if ( !$teamData ) {
                continue;
            }

            // Collect events from all people in this team
            $peopleByCategory = [
                'team_members' => $teamData['team_members'] ?? [],
                'leadership' => $teamData['leadership'] ?? [],
                'consultants' => $teamData['consultants'] ?? [],
                'alumni' => $teamData['alumni'] ?? []
            ];
            
            foreach ( $peopleByCategory as $category => $people ) {
                $personType = match( $category ) {
                    'team_members' => 'Member',
                    'leadership' => 'Leadership',
                    'consultants' => 'Consultant',
                    'alumni' => 'Alumni'
                };
                
                foreach ( $people as $username => $personData ) {
                    $personEvents = $this->getPersonUpcomingEvents( $personData, $personType );
                    foreach ( $personEvents as $event ) {
                        if ( $event['date'] <= $cutoffDate ) {
                            $eventData = [
                                'date' => $event['date'],
                                'type' => $event['type'],
                                'person' => $personData['name'] ?? $username,
                                'team' => $team['name'],
                                'team_slug' => $team['slug']
                            ];
                            // Copy age/years if present
                            if ( isset( $event['age'] ) ) {
                                $eventData['age'] = $event['age'];
                            }
                            if ( isset( $event['years'] ) ) {
                                $eventData['years'] = $event['years'];
                            }
                            $allEvents[] = $eventData;
                        }
                    }
                }
            }
        }

        // Sort by date
        usort( $allEvents, fn( $a, $b ) => $a['date'] <=> $b['date'] );

        // Display results
        $filterText = '';
        if ( $teamsOnly && !$groupsOnly ) {
            $filterText = ' (Teams Only)';
        } elseif ( $groupsOnly && !$teamsOnly ) {
            $filterText = ' (Groups Only)';
        }
        
        $timePeriodDesc = $this->getTimePeriodDescription( $timePeriod );

        echo "\n\033[1m📅 Upcoming Events - Next {$timePeriodDesc}{$filterText}\033[0m\n";
        echo "\033[1m" . str_repeat( '═', 60 ) . "\033[0m\n";

        if ( empty( $allEvents ) ) {
            echo "No upcoming events found in the next {$timePeriodDesc}.\n";
            return;
        }

        $currentMonth = '';
        $eventCount = 0;
        foreach ( $allEvents as $event ) {
            $eventMonth = $event['date']->format( 'F Y' );
            
            // Show month header
            if ( $eventMonth !== $currentMonth ) {
                if ( $currentMonth !== '' ) {
                    echo "\n"; // Add spacing between months
                }
                echo "\n\033[1m📅 {$eventMonth}\033[0m\n";
                $currentMonth = $eventMonth;
            }
            
            $daysUntil = $this->getDaysUntilEvent( $event['date'] );
            $timeUntilText = $this->getTimeUntilText( $daysUntil );
            
            // Color coding based on how soon the event is
            $dateColor = '';
            if ( $daysUntil === 0 ) {
                $dateColor = "\033[91m"; // Red for today
            } elseif ( $daysUntil <= 7 ) {
                $dateColor = "\033[93m"; // Yellow for this week
            } elseif ( $daysUntil <= 30 ) {
                $dateColor = "\033[92m"; // Green for this month
            } else {
                $dateColor = "\033[90m"; // Gray for later
            }
            
            echo "   {$dateColor}{$event['date']->format( 'M j' )}\033[0m • ";
            echo "\033[1m{$event['person']}\033[0m's {$event['type']}";
            
            // Add age or years information
            if ( isset( $event['age'] ) ) {
                $ageText = $event['age'] === 1 ? '1 year old' : "{$event['age']} years old";
                echo " ({$ageText})";
            } elseif ( isset( $event['years'] ) ) {
                $yearsText = $event['years'] === 1 ? '1 year' : "{$event['years']} years";
                echo " ({$yearsText})";
            }
            
            echo " {$timeUntilText} ";
            echo "• \033[36m{$event['team']}\033[0m\n";
            
            $eventCount++;
        }

        echo "\n\033[90mShowing {$eventCount} events over the next {$timePeriodDesc}\033[0m\n";
        
        // Show legend
        echo "\n\033[90mColor coding: \033[91mToday\033[0m\033[90m • \033[93mThis week\033[0m\033[90m • \033[92mThis month\033[0m\033[90m • \033[90mLater\033[0m\n";
    }
}

// Check command line arguments
$showHelp = in_array( '--help', $argv ) || in_array( '-h', $argv );
$showStats = in_array( '--stats', $argv ) || in_array( '-s', $argv );
$filterTeams = in_array( '--teams', $argv );
$filterGroups = in_array( '--groups', $argv );

// Parse events with optional time parameter
$showEvents = false;
$eventsTimePeriod = '1m'; // Default to 1 month
foreach ( $argv as $index => $arg ) {
    if ( $arg === '--events' || $arg === '-e' ) {
        $showEvents = true;
        // Check if next argument is a time period (not a flag)
        if ( isset( $argv[$index + 1] ) && !str_starts_with( $argv[$index + 1], '-' ) ) {
            $eventsTimePeriod = $argv[$index + 1];
        }
        break;
    }
}

if ( $showHelp ) {
    echo "People Finder CLI - Search for teams and people\n\n";
    echo "Usage: php people-finder.php [OPTIONS] [SEARCH_TERM]\n\n";
    echo "Options:\n";
    echo "  -h, --help       Show this help message\n";
    echo "  -s, --stats      Show overall statistics\n";
    echo "  -e, --events [TIME]  Show upcoming events (default: 1m)\n";
    echo "                   TIME format: 3d, 2w, 1m, 6m, 1y\n";
    echo "                   Or: 7 days, 2 weeks, 3 months, 1 year\n";
    echo "      --teams      Filter events to teams only (use with --events)\n";
    echo "      --groups     Filter events to groups only (use with --events)\n\n";
    echo "Environment Variables:\n";
    echo "  PEOPLE_FINDER_BASE_URL   Base URL for web links (optional)\n";
    echo "                           Example: http://localhost/wp/a8c\n\n";
    echo "Interactive Commands (when base URL is set):\n";
    echo "  1o, 2o, etc.     Open web page for result #1, #2, etc.\n";
    echo "  1e, 2e, etc.     Open edit page for result #1, #2, etc.\n";
    echo "  1, 2, etc.       View detailed info for person #1, #2, etc.\n";
    echo "  q                Quit\n";
    echo "  Enter            Search again\n\n";
    echo "Examples:\n";
    echo "  php people-finder.php paolo        # Search for 'paolo'\n";
    echo "  php people-finder.php jetpack      # Search for 'jetpack'\n";
    echo "  php people-finder.php leadership   # Search for 'leadership'\n";
    echo "  php people-finder.php --stats      # Show statistics\n";
    echo "  php people-finder.php --events     # Show events for next month\n";
    echo "  php people-finder.php --events 2w  # Show events for next 2 weeks\n";
    echo "  php people-finder.php --events 3m --teams    # Show team events for 3 months\n";
    echo "  php people-finder.php --events 7d --groups   # Show group events for 7 days\n\n";
    echo "  # With web links and interactivity:\n";
    echo "  export PEOPLE_FINDER_BASE_URL=http://localhost/wp/a8c\n";
    echo "  php people-finder.php paolo        # Shows web link + interactive options\n\n";
    exit( 0 );
}

// Initialize the CLI
$cli = new PeopleFinderCLI();

if ( $showStats ) {
    $cli->showStats();
    exit( 0 );
}

if ( $showEvents ) {
    $cli->showAllUpcomingEvents( $filterTeams, $filterGroups, $eventsTimePeriod );
    exit( 0 );
}

// Get search term from command line
$searchTerm = '';
foreach ( $argv as $index => $arg ) {
    if ( $index === 0 || str_starts_with( $arg, '-' ) ) {
        continue; // Skip script name and options
    }
    
    // Skip time parameter that follows --events
    if ( $index > 0 && ( $argv[$index - 1] === '--events' || $argv[$index - 1] === '-e' ) ) {
        continue; // Skip time parameter
    }
    
    $searchTerm .= ( $searchTerm ? ' ' : '' ) . $arg;
}

if ( empty( $searchTerm ) ) {
    echo "Please provide a search term or use --help for usage information.\n";
    echo "Example: php people-finder.php paolo\n";
    exit( 1 );
}

$results = $cli->search( $searchTerm );
$cli->displayResults( $results );