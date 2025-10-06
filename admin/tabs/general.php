<?php
/**
 * General Settings Tab
 * Contains general settings form and POST handler
 */
namespace PersonalCRM;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Handle POST request for general settings
 */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['action'] ) && $_POST['action'] === 'save_general' ) {
	$crm = PersonalCrm::get_instance();
	$config = $crm->storage->get_group( $current_group );

	$config['group_name'] = sanitize_text_field( $_POST['team_name'] ?? '' );
	$config['activity_url_prefix'] = sanitize_url( $_POST['activity_url_prefix'] ?? '' );
	$config['type'] = sanitize_text_field( $_POST['team_type'] ?? 'team' );

	$is_default = isset( $_POST['is_default'] ) && $_POST['is_default'] === '1';
	if ( $is_default ) {
		$available_groups = $crm->storage->get_available_groups();
		foreach ( $available_groups as $group_slug ) {
			if ( $group_slug !== $current_group ) {
				$other_config = $crm->storage->get_group( $group_slug );
				if ( $other_config && isset( $other_config['default'] ) ) {
					unset( $other_config['default'] );
					$crm->storage->save_group( $group_slug, $other_config );
				}
			}
		}
		$config['default'] = true;
	} else {
		if ( isset( $config['default'] ) ) {
			unset( $config['default'] );
		}
	}

	if ( $crm->storage->save_group( $current_group, $config ) ) {
		$message = 'General settings saved successfully!';
	} else {
		$error = 'Failed to save configuration.';
	}
}
?>
<div id="general" class="tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
    <h2>General Settings</h2>
    <form method="post">
        <input type="hidden" name="action" value="save_general">
        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">

        <div class="form-group">
            <label for="team_name">Name</label>
            <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars( $config['group_name'] ); ?>" required autofocus>
        </div>

        <?php
        // Allow plugins to add fields after the name field
        do_action( 'personal_crm_admin_team_general_fields', $config, $group, $current_group );
        ?>

        <div class="form-group">
            <label for="team_type">Type</label>
            <select id="team_type" name="team_type">
                <option value="team" <?php echo ( ! isset( $config['type'] ) || $config['type'] === 'team' ) ? 'selected' : ''; ?>>Team (work/business context)</option>
                <option value="group" <?php echo ( isset( $config['type'] ) && $config['type'] === 'group' ) ? 'selected' : ''; ?>>Group (personal/social context)</option>
            </select>
            <small class="text-small-muted">Choose "Group" for personal friends/acquaintances, or "Team" for work/business contexts.</small>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 600;">
                <input type="checkbox" id="is_default" name="is_default" value="1" <?php echo isset( $config['default'] ) && $config['default'] ? 'checked' : ''; ?> style="width: auto;">
                <span>Set as default team</span>
            </label>
            <small class="text-small-muted" style="margin-left: 20px;">
                When users visit the site without specifying a team, they'll be redirected to this team automatically.
            </small>
        </div>

        <?php
        // Allow plugins to add team management options
        do_action( 'personal_crm_admin_team_management_options', $config, $group, $current_group );
        ?>


        <button type="submit" class="btn">Save General Settings</button>
    </form>
</div>
