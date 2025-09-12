<?php
/**
 * Team Selection Screen
 * 
 * Allows users to select which team they want to view when multiple teams exist
 */

// Include common functions
require_once __DIR__ . '/includes/common.php';

$available_teams = get_available_teams();

// Check for type filter parameter
$type_filter = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : null;
if ( $type_filter && !in_array( $type_filter, array( 'team', 'group' ) ) ) {
	$type_filter = null; // Invalid type, ignore filter
}

// Filter teams by type if specified
if ( $type_filter ) {
	$filtered_teams = array();
	foreach ( $available_teams as $team_slug ) {
		$team_type = get_team_type_from_file( $team_slug );
		if ( $team_type === $type_filter ) {
			$filtered_teams[] = $team_slug;
		}
	}
	$available_teams = $filtered_teams;
}

// If there's only one team or no teams, redirect appropriately
if ( empty( $available_teams ) ) {
	header( 'Location: admin.php?create_team=new' );
	exit;
} elseif ( count( $available_teams ) === 1 ) {
	header( 'Location: ./' );
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Team Selection - Orbit Team Management</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/cmd-k.css">
</head>
<body>
    <?php render_cmd_k_panel(); ?>
    <?php render_dark_mode_toggle(); ?>

    <div class="team-selection-container">
        <div class="team-selection-header">
            <h1>Select<?php if ( $type_filter ) echo ' (' . htmlspecialchars( ucfirst( $type_filter ) ) . 's)'; ?></h1>
            <p>Choose which one you'd like to view:</p>
            
            <div class="team-search-container">
                <input type="text" id="team-search" placeholder="Search teams or people..." autocomplete="off">
                <div id="search-clear" class="search-clear" style="display: none;">&times;</div>
            </div>
            
            <div class="type-filter-buttons">
                <a href="team-selection.php" class="filter-btn<?php echo !$type_filter ? ' active' : ''; ?>">All</a>
                <a href="team-selection.php?type=team" class="filter-btn<?php echo $type_filter === 'team' ? ' active' : ''; ?>">Teams</a>
                <a href="team-selection.php?type=group" class="filter-btn<?php echo $type_filter === 'group' ? ' active' : ''; ?>">Groups</a>
            </div>
        </div>

        <div class="team-grid" id="team-grid">
            <?php foreach ( $available_teams as $team_slug ) : ?>
                <?php 
                $team_name = get_team_name_from_file( $team_slug ); 
                $team_type = get_team_type_from_file( $team_slug );
                $people_count = get_team_people_count( $team_slug );
                $people_data = get_team_people_data( $team_slug );
                $param_name = ( $team_type === 'group' ) ? 'group' : 'team';
                ?>
                <a href="index.php<?php if ( get_default_team() !== $team_slug ) echo '?' . $param_name . '=' . urlencode( $team_slug ); ?>" 
                   class="team-card" 
                   data-team-name="<?php echo htmlspecialchars( $team_name ); ?>"
                   data-team-slug="<?php echo htmlspecialchars( $team_slug ); ?>"
                   data-team-type="<?php echo htmlspecialchars( $team_type ); ?>"
                   data-people-data="<?php echo htmlspecialchars( json_encode( $people_data ) ); ?>">
                    <h3><?php echo htmlspecialchars( $team_name ); ?></h3>
                    <div class="team-card-details">
                        <p class="team-file-name"><?php echo htmlspecialchars( $team_slug ); ?>.json</p>
                        <p class="team-people-count"><?php echo $people_count; ?> <?php echo $people_count === 1 ? 'person' : 'people'; ?></p>
                        <div class="team-matched-person" style="display: none;">
                            <span class="match-label">Found:</span>
                            <span class="match-name" data-team-slug="<?php echo htmlspecialchars( $team_slug ); ?>" style="cursor: pointer; text-decoration: underline; color: #007cba;"></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div id="no-results" class="no-results-message" style="display: none;">
            <p>No teams found matching your search.</p>
        </div>

        <div class="admin-link-section">
            <a href="admin.php?create_team=new">⚙️ Create New</a>
        </div>
    </div>
    
    <script src="assets/cmd-k.js"></script>
    <script src="assets/script.js"></script>
    <?php init_cmd_k_js(); ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('team-search');
        const clearButton = document.getElementById('search-clear');
        const teamCards = document.querySelectorAll('.team-card');
        const teamGrid = document.getElementById('team-grid');
        const noResults = document.getElementById('no-results');
	searchInput.focus();
        
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            
            if (searchTerm === '') {
                // Show all cards when search is empty
                teamCards.forEach(card => {
                    card.style.display = 'block';
                    const matchedPersonElement = card.querySelector('.team-matched-person');
                    if (matchedPersonElement) {
                        matchedPersonElement.style.display = 'none';
                    }
                });
                clearButton.style.display = 'none';
                noResults.style.display = 'none';
                visibleCount = teamCards.length;
            } else {
                clearButton.style.display = 'flex';
                
                teamCards.forEach(card => {
                    const teamName = card.getAttribute('data-team-name').toLowerCase();
                    const teamSlug = card.getAttribute('data-team-slug').toLowerCase();
                    const peopleData = JSON.parse(card.getAttribute('data-people-data') || '{}');
                    const matchedPersonElement = card.querySelector('.team-matched-person');
                    const matchNameElement = card.querySelector('.match-name');
                    
                    // Check if search term matches team name, slug, or any person name
                    const matchesTeam = teamName.includes(searchTerm) || teamSlug.includes(searchTerm);
                    let matchedPerson = null;
                    let matchedUsername = null;
                    
                    // Search through people data (username -> {name, ...})
                    for (const [username, personData] of Object.entries(peopleData)) {
                        if (personData.name && personData.name.toLowerCase().includes(searchTerm)) {
                            matchedPerson = personData.name;
                            matchedUsername = username;
                            break;
                        }
                    }
                    
                    if (matchesTeam || matchedPerson) {
                        card.style.display = 'block';
                        visibleCount++;
                        
                        // Show matched person if search matched a person name
                        if (matchedPerson && !matchesTeam && matchedPersonElement && matchNameElement) {
                            matchedPersonElement.style.display = 'block';
                            matchNameElement.textContent = matchedPerson;
                            const teamSlug = matchNameElement.getAttribute('data-team-slug');
                            matchNameElement.onclick = function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                window.location.href = `person.php?team=${encodeURIComponent(teamSlug)}&person=${encodeURIComponent(matchedUsername)}`;
                                return false;
                            };
                        } else if (matchedPersonElement) {
                            matchedPersonElement.style.display = 'none';
                        }
                    } else {
                        card.style.display = 'none';
                        if (matchedPersonElement) {
                            matchedPersonElement.style.display = 'none';
                        }
                    }
                });
            }
            
            // Show/hide no results message
            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }
        
        // Search as user types
        searchInput.addEventListener('input', performSearch);
        
        // Clear search
        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            performSearch();
            searchInput.focus();
        });
        
        // Focus search field when pressing '/' key
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && e.target !== searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    });
    </script>
</body>
</html>
