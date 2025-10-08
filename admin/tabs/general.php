<?php
/**
 * General Settings Tab
 * Contains general settings form and POST handler
 */
namespace PersonalCRM;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

$group_obj = $crm->storage->get_group( $current_group );
?>
<div id="general" class="tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
    <h2>General Settings</h2>
    <form method="post">
        <input type="hidden" name="action" value="save_general">
        <input type="hidden" name="group" value="<?php echo htmlspecialchars( $current_group ); ?>">

        <div class="form-group">
            <label for="team_name">Name</label>
            <input type="text" id="team_name" name="team_name" value="<?php echo htmlspecialchars( $group_obj->group_name ); ?>" required autofocus>
        </div>

        <div class="form-group">
            <label for="parent_group">Parent Group</label>
            <select id="parent_group" name="parent_group">
                <option value="none">None (Top-level group)</option>
                <?php
                $available_groups = $crm->storage->get_available_groups();
                $current_parent_slug = null;
                if ( ! empty( $group_obj->parent_id ) ) {
                    $parent = $crm->storage->get_parent_group( $group_obj->id );
                    $current_parent_slug = $parent['slug'] ?? null;
                }
                foreach ( $available_groups as $group_slug ) {
                    if ( $group_slug === $current_group ) continue; // Can't be parent of itself
                    $group_name = $crm->storage->get_group_name( $group_slug );
                    $selected = ( $group_slug === $current_parent_slug ) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars( $group_slug ) . '" ' . $selected . '>' . htmlspecialchars( $group_name ) . '</option>';
                }
                ?>
            </select>
            <small class="text-small-muted">Select a parent group to create a subgroup (e.g., "Engineering Leadership" under "Engineering")</small>
        </div>

        <div class="form-group">
            <label for="display_icon">Display Icon</label>
            <input type="text" id="display_icon" name="display_icon" value="<?php echo htmlspecialchars( $group_obj->display_icon ?? '' ); ?>" placeholder="e.g., 👥, 👑, 🤝">
            <small class="text-small-muted">Emoji to display in navigation</small>
        </div>

        <div class="form-group">
            <label for="sort_order">Sort Order</label>
            <input type="number" id="sort_order" name="sort_order" value="<?php echo intval( $group_obj->sort_order ?? 0 ); ?>" min="0">
            <small class="text-small-muted">Lower numbers appear first in navigation</small>
        </div>

        <?php
        // Allow plugins to add fields after the name field
        do_action( 'personal_crm_admin_team_general_fields', $group_obj, $group, $current_group );
        ?>

        <div class="form-group">
            <label for="team_type">Type</label>
            <select id="team_type" name="team_type">
                <option value="team" <?php echo ( $group_obj->type === 'team' ) ? 'selected' : ''; ?>>Team (work/business context)</option>
                <option value="group" <?php echo ( $group_obj->type === 'group' ) ? 'selected' : ''; ?>>Group (personal/social context)</option>
            </select>
            <small class="text-small-muted">Choose "Group" for personal friends/acquaintances, or "Team" for work/business contexts.</small>
        </div>

        <div class="form-group" style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-weight: 600;">
                <input type="checkbox" id="is_default" name="is_default" value="1" <?php echo $group_obj->is_default ? 'checked' : ''; ?> style="width: auto;">
                <span>Set as default team</span>
            </label>
            <small class="text-small-muted" style="margin-left: 20px;">
                When users visit the site without specifying a team, they'll be redirected to this team automatically.
            </small>
        </div>

        <?php
        // Allow plugins to add team management options
        do_action( 'personal_crm_admin_team_management_options', $group_obj, $group, $current_group );
        ?>


        <button type="submit" class="btn">Save General Settings</button>
    </form>
</div>
