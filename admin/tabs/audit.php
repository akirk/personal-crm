<?php
/**
 * Audit Tab
 */
namespace PersonalCRM;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}
?>
            <h2>📊 Data Completeness Audit</h2>
            <p class="text-muted" style="margin-bottom: 20px;">Identify missing data points and improve team profiles</p>

            <?php
            // Get audit data for all people
            $audit_data = array();

            // Process all person types using unified approach
            $person_type_mappings = array(
                'team_members' => array('type_name' => ucfirst( $group ) . ' Member', 'audit_type' => 'member'),
                'leadership' => array('type_name' => 'Leadership', 'audit_type' => 'leader'),
                'consultants' => array('type_name' => 'Consultant', 'audit_type' => 'consultants'),
                'alumni' => array('type_name' => 'Alumni', 'audit_type' => 'alumni'),
            );
            
            foreach ( $person_type_mappings as $section_key => $type_info ) {
                foreach ( $config[ $section_key ] ?? array() as $username => $person ) {
                    $missing = get_missing_data_points( $person, $type_info['audit_type'], $current_group );
                    $score = get_completeness_score( $missing, $type_info['audit_type'], $current_group );
                    $audit_data[] = array(
                        'type' => $type_info['type_name'],
                        'name' => $person['name'],
                        'username' => $username,
                        'missing' => $missing,
                        'score' => $score,
                        'person' => $person
                    );
                }
            }

            // Sort by completeness score (lowest first to prioritize fixes)
            usort( $audit_data, function( $a, $b ) {
                if ( $a['score'] === $b['score'] ) {
                    return strcasecmp( $a['name'], $b['name'] );
                }
                return $a['score'] <=> $b['score'];
            } );

            // Calculate statistics
            $total_people = count( $audit_data );
            $complete_profiles = count( array_filter( $audit_data, function( $item ) { return $item['score'] >= 90; } ) );
            $needs_attention = count( array_filter( $audit_data, function( $item ) { return $item['score'] < 70; } ) );
            $avg_score = $total_people > 0 ? round( array_sum( array_column( $audit_data, 'score' ) ) / $total_people ) : 0;
            ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="admin-section">
                    <div class="stat-number"><?php echo $total_people; ?></div>
                    <div class="stat-label">Total People</div>
                </div>
                <div class="admin-section">
                    <div class="stat-number"><?php echo $avg_score; ?>%</div>
                    <div class="stat-label">Average Completeness</div>
                </div>
                <div class="admin-section">
                    <div class="stat-number"><?php echo $complete_profiles; ?></div>
                    <div class="stat-label">Complete Profiles (90%+)</div>
                </div>
                <div class="admin-section">
                    <div class="stat-number"><?php echo $needs_attention; ?></div>
                    <div class="stat-label">Needs Attention (&lt;70%)</div>
                </div>
            </div>

            <div class="events-tab-section">
                <span style="margin-right: 15px; font-weight: 600;">Filter by:</span>
                <select class="form-select-small" style="margin-right: 15px;" id="type-filter" onchange="filterAuditTable()">
                    <option value="">All Types</option>
                    <option value="Team Member"><?php echo ucfirst( $group ); ?> Members</option>
                    <option value="Leadership">Leadership</option>
                    <option value="Consultant">Consultants</option>
                    <option value="Alumni">Alumni</option>
                </select>
                <select class="form-select-small" id="score-filter" onchange="filterAuditTable()">
                    <option value="">All Scores</option>
                    <option value="poor">Poor (&lt;50%)</option>
                    <option value="fair">Fair (50-79%)</option>
                    <option value="good">Good (80-89%)</option>
                    <option value="excellent">Excellent (90%+)</option>
                </select>
            </div>

            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;" id="audit-table">
                <thead>
                    <tr class="table-header-row">
                        <th class="table-cell">Person</th>
                        <th class="table-cell">Type</th>
                        <th class="table-cell">Completeness</th>
                        <th class="table-cell">Missing Data Points</th>
                        <th class="table-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $audit_data as $item ) : ?>
                        <tr class="table-row" data-type="<?php echo htmlspecialchars( $item['type'] ); ?>" data-score="<?php echo $item['score']; ?>">
                            <td class="table-cell" style="font-weight: normal;">
                                <div style="font-weight: 600;">
                                    <?php echo htmlspecialchars( $crm->mask_name( $item['name'], $privacy_mode ) ); ?>
                                </div>
                                <div class="text-small-muted">
                                    @<?php echo htmlspecialchars( $crm->mask_username( $item['username'], $privacy_mode ) ); ?>
                                </div>
                            </td>
                            <td class="table-cell" style="font-weight: normal; white-space: nowrap;"><?php echo htmlspecialchars( $item['type'] ); ?></td>
                            <td class="table-cell" style="font-weight: normal;">
                                <span class="<?php
                                    if ( $item['score'] >= 90 ) echo 'score-excellent';
                                    elseif ( $item['score'] >= 80 ) echo 'score-good';
                                    elseif ( $item['score'] >= 50 ) echo 'score-fair';
                                    else echo 'score-poor';
                                ?>" style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-weight: 600; font-size: 12px; min-width: 40px; text-align: center;"><?php echo $item['score']; ?>%</span>
                            </td>
                            <td class="table-cell" style="font-weight: normal; font-size: 13px;">
                                <?php if ( empty( $item['missing'] ) ) : ?>
                                    <span class="link-success">✅ Complete</span>
                                <?php else : ?>
                                    <?php 
                                    $required_fields = array();
                                    $recommended_fields = array();
                                    $optional_fields = array();
                                    
                                    foreach ( $item['missing'] as $missing_item ) {
                                        if ( is_array( $missing_item ) ) {
                                            switch ( $missing_item['priority'] ) {
                                                case 'required':
                                                    $required_fields[] = $missing_item['field'];
                                                    break;
                                                case 'recommended':
                                                    $recommended_fields[] = $missing_item['field'];
                                                    break;
                                                case 'optional':
                                                    $optional_fields[] = $missing_item['field'];
                                                    break;
                                            }
                                        } else {
                                            // Backwards compatibility
                                            if ( strpos( $missing_item, 'optional' ) !== false ) {
                                                $recommended_fields[] = str_replace( ' (optional)', '', $missing_item );
                                            } else {
                                                $required_fields[] = $missing_item;
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ( ! empty( $required_fields ) ) : ?>
                                        <?php foreach ( $required_fields as $field ) : ?>
                                            <span class="link-danger" title="Required field"><?php echo htmlspecialchars( $field ); ?></span><?php echo ( $field !== end( $required_fields ) || ! empty( $recommended_fields ) || ! empty( $optional_fields ) ) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $recommended_fields ) ) : ?>
                                        <?php foreach ( $recommended_fields as $field ) : ?>
                                            <span class="link-warning" title="Recommended field - likely to be filled out"><?php echo htmlspecialchars( $field ); ?></span><?php echo ( $field !== end( $recommended_fields ) || ! empty( $optional_fields ) ) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $optional_fields ) ) : ?>
                                        <?php foreach ( $optional_fields as $field ) : ?>
                                            <span class="link-secondary" title="Optional field - may rightfully stay empty"><?php echo htmlspecialchars( $field ); ?></span><?php echo $field !== end( $optional_fields ) ? ', ' : ''; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="table-cell" style="font-weight: normal; white-space: nowrap;">
                                <a href="/crm/admin/<?php echo $current_group; ?>/person/<?php echo $item['username']; ?>/" class="link-primary text-small" style="margin-right: 8px;">✏️ Edit</a>
                                <a href="<?php echo $crm->build_url( 'index.php', array( 'person' => $item['username'] ) ); ?>" class="link-primary text-small" target="_blank">👁️ View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
