<?php
/**
 * Team Links Tab
 * Contains team links form and POST handler
 */
namespace PersonalCRM;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Handle POST request for team links
 */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'save_team_links' ) {
	$crm = PersonalCrm::get_instance();
	$group_obj = $crm->storage->get_group( $current_group );

	// Get existing links for comparison
	$existing_links = $crm->storage->get_group_links_by_id( $group_obj->id );

	// Process submitted links
	$submitted_links = array();
	if ( isset( $_POST['team_links'] ) && is_array( $_POST['team_links'] ) ) {
		foreach ( $_POST['team_links'] as $link_data ) {
			$link_text = trim( sanitize_text_field( $link_data['text'] ?? '' ) );
			$link_url = trim( sanitize_url( $link_data['url'] ?? '' ) );
			if ( ! empty( $link_text ) && ! empty( $link_url ) ) {
				$submitted_links[ $link_text ] = $link_url;
			}
		}
	}

	// Delete links that were removed
	foreach ( $existing_links as $link_name => $link_url ) {
		if ( ! isset( $submitted_links[ $link_name ] ) ) {
			$crm->storage->delete_group_link( $group_obj->id, $link_name );
		}
	}

	// Save or update links
	$success = true;
	foreach ( $submitted_links as $link_name => $link_url ) {
		if ( ! $crm->storage->save_group_link( $group_obj->id, $link_name, $link_url ) ) {
			$success = false;
		}
	}

	if ( $success ) {
		$message = 'Links saved successfully!';
	} else {
		$error = 'Failed to save links.';
	}
}
?>
<div id="team_links" class="tab-content <?php echo $active_tab === 'team_links' ? 'active' : ''; ?>">
    <h2>Links</h2>
    <p class="text-muted" style="margin-bottom: 20px;">These links will appear on the front page next to the headline.</p>

    <form method="post">
        <input type="hidden" name="action" value="save_team_links">
        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">

        <div class="form-group">
            <div id="team-links-container">
                <?php
                $team_links = $config->links ?? array();
                $link_index = 0;
                if ( ! empty( $team_links ) ) :
                    foreach ( $team_links as $link_text => $link_url ) : ?>
                        <div class="team-link-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                            <input type="text" name="team_links[<?php echo $link_index; ?>][text]" value="<?php echo htmlspecialchars( $link_text ); ?>" placeholder="Link text (e.g., Linear)" style="flex: 0 0 150px;">
                            <input type="url" name="team_links[<?php echo $link_index; ?>][url]" value="<?php echo htmlspecialchars( $link_url ); ?>" placeholder="https://..." style="flex: 1;">
                            <button type="button" class="remove-team-link btn-remove-personal">Remove</button>
                        </div>
                    <?php
                    $link_index++;
                    endforeach;
                else : ?>
                    <div class="team-link-row" style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                        <input type="text" name="team_links[0][text]" value="" placeholder="Link text (e.g., Linear)" style="flex: 0 0 150px;">
                        <input type="url" name="team_links[0][url]" value="" placeholder="https://..." style="flex: 1;">
                        <button type="button" class="remove-team-link btn-remove-personal">Remove</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-team-link" class="btn btn-add-personal">+ Add Link</button>
        </div>

        <button type="submit" class="btn">Save Links</button>
    </form>
</div>
