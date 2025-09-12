#!/usr/bin/env php
<?php
/**
 * People Finder CLI
 * Search tool for teams and people
 */

class PeopleFinderCLI {
    private array $teams = [];
    private array $people = [];
    private array $allItems = [];

    public function __construct() {
        $this->loadData();
    }

    /**
     * Load all team and people data from JSON files
     */
    private function loadData(): void {
        $jsonFiles = glob( '*.json' );
        
        foreach ( $jsonFiles as $file ) {
            if ( !is_readable( $file ) ) {
                continue;
            }

            $data = json_decode( file_get_contents( $file ), true );
            if ( !$data ) {
                continue;
            }

            $teamSlug = basename( $file, '.json' );
            $teamName = $data['team_name'] ?? ucfirst( $teamSlug );

            // Skip hr-feedback file
            if ( $teamSlug === 'hr-feedback' ) {
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
                'itemType' => 'team'
            ];

            $this->teams[] = $team;

            // Process people
            foreach ( ['team_members', 'leadership', 'consultants', 'alumni'] as $category ) {
                if ( !isset( $data[$category] ) ) {
                    continue;
                }

                $categoryLabel = match( $category ) {
                    'team_members' => 'Team Member',
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
     * Get current time in a specific timezone
     */
    private function getCurrentTimeInTimezone( string $timezone ): string {
        try {
            $tz = new DateTimeZone( $timezone );
            $now = new DateTime( 'now', $tz );
            return $now->format( 'H:i' );
        } catch ( Exception $e ) {
            return 'Unknown';
        }
    }

    /**
     * Get upcoming events for a person (birthday, company anniversary, personal events)
     */
    private function getPersonUpcomingEvents( array $personData ): array {
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
                    $events[] = ['date' => $thisYearBirthday, 'type' => 'birthday'];
                }
                // Always check next year's birthday if it's within our window
                if ( $nextYearBirthday <= $cutoffDate ) {
                    $events[] = ['date' => $nextYearBirthday, 'type' => 'birthday'];
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
                        $events[] = ['date' => $thisYearBirthday, 'type' => 'birthday'];
                    }
                    // Always check next year's birthday if it's within our window
                    if ( $nextYearBirthday <= $cutoffDate ) {
                        $events[] = ['date' => $nextYearBirthday, 'type' => 'birthday'];
                    }
                } catch ( Exception $e ) {
                    // Skip invalid dates
                }
            }
        }

        // Company anniversary
        if ( !empty( $personData['company_anniversary'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $personData['company_anniversary'] ) ) {
            $anniversaryDate = new DateTime( $personData['company_anniversary'] );
            $thisYearAnniversary = new DateTime( $currentDate->format( 'Y' ) . '-' . $anniversaryDate->format( 'm-d' ) );
            $nextYearAnniversary = new DateTime( ( $currentDate->format( 'Y' ) + 1 ) . '-' . $anniversaryDate->format( 'm-d' ) );
            
            // Check if this year's anniversary hasn't passed yet
            if ( $thisYearAnniversary >= $currentDate && $thisYearAnniversary <= $cutoffDate ) {
                $events[] = ['date' => $thisYearAnniversary, 'type' => 'work anniversary'];
            } 
            // Always check next year's anniversary if it's within our window
            if ( $nextYearAnniversary <= $cutoffDate ) {
                $events[] = ['date' => $nextYearAnniversary, 'type' => 'work anniversary'];
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
     * Display search results
     */
    public function displayResults( array $results ): void {
        if ( empty( $results ) ) {
            echo "No results found.\n";
            return;
        }

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

        // Add interactive options if base URL is configured
        $baseUrl = getenv( 'PEOPLE_FINDER_BASE_URL' );
        if ( $baseUrl && !empty( $results ) ) {
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
                    // Just number pressed - show details
                    $this->showPersonDetails( $item );
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
                $url = $baseUrl . '/admin.php?team=' . urlencode( $item['slug'] );
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
                $url = $baseUrl . '/admin.php?person=' . urlencode( $item['username'] );
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
        echo "   Team: {$item['team']}\n";
        if ( !empty( $item['location'] ) ) {
            echo "   Location: {$item['location']}\n";
        }
        if ( !empty( $personData['timezone'] ) ) {
            $currentTime = $this->getCurrentTimeInTimezone( $personData['timezone'] );
            echo "   Timezone: {$personData['timezone']} ({$currentTime})\n";
        }

        // Age and tenure
        if ( !empty( $item['birthday'] ) ) {
            $age = $this->calculateAge( $item['birthday'] );
            if ( $age !== null ) {
                echo "   Age: {$age} years old\n";
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
                echo "   Company tenure: {$tenureText}\n";
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
        $upcomingEvents = $this->getPersonUpcomingEvents( $personData );
        if ( !empty( $upcomingEvents ) ) {
            echo "\n\033[1m📅 Upcoming Events\033[0m\n";
            foreach ( $upcomingEvents as $event ) {
                $daysUntil = $this->getDaysUntilEvent( $event['date'] );
                $timeUntilText = $this->getTimeUntilText( $daysUntil );
                echo "   {$event['date']->format( 'M j' )} • {$event['type']} {$timeUntilText}\n";
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
}

// Check command line arguments
$showHelp = in_array( '--help', $argv ) || in_array( '-h', $argv );
$showStats = in_array( '--stats', $argv ) || in_array( '-s', $argv );

if ( $showHelp ) {
    echo "People Finder CLI - Search for teams and people\n\n";
    echo "Usage: php people-finder.php [OPTIONS] [SEARCH_TERM]\n\n";
    echo "Options:\n";
    echo "  -h, --help       Show this help message\n";
    echo "  -s, --stats      Show overall statistics\n\n";
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
    echo "  php people-finder.php --stats      # Show statistics\n\n";
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

// Get search term from command line
$searchTerm = '';
foreach ( $argv as $index => $arg ) {
    if ( $index === 0 || str_starts_with( $arg, '-' ) ) {
        continue; // Skip script name and options
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