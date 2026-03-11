<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Database Scanner', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Scan Database for Malware', 'wp-file-guardian' ); ?></h2>
        <p><?php esc_html_e( 'Scan posts, options, comments, and users for injected scripts, suspicious code, and hidden admin accounts.', 'wp-file-guardian' ); ?></p>
        <div class="wpfg-scan-actions">
            <button type="button" class="button button-primary" id="wpfg-start-db-scan"><?php esc_html_e( 'Start DB Scan', 'wp-file-guardian' ); ?></button>
            <span class="wpfg-inline-status" id="wpfg-db-scan-status"></span>
        </div>
        <div id="wpfg-db-scan-progress" style="display:none; margin-top:15px;">
            <div class="wpfg-progress-bar">
                <div class="wpfg-progress-fill" id="wpfg-db-progress-fill" style="width:0%"></div>
            </div>
            <p id="wpfg-db-scan-msg"><?php esc_html_e( 'Scanning...', 'wp-file-guardian' ); ?></p>
        </div>
    </div>

    <!-- Revision Cleanup -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Revision Cleanup', 'wp-file-guardian' ); ?></h2>
        <p><?php esc_html_e( 'Delete unnecessary post revisions and old auto-drafts to optimize your database.', 'wp-file-guardian' ); ?></p>
        <div id="wpfg-revision-stats" style="margin:15px 0; padding:12px 16px; background:#f8f9fa; border-radius:6px; display:none;">
            <span id="wpfg-rev-total" style="margin-right:20px;"><strong><?php esc_html_e( 'Revisions:', 'wp-file-guardian' ); ?></strong> <span>-</span></span>
            <span id="wpfg-rev-size" style="margin-right:20px;"><strong><?php esc_html_e( 'Size:', 'wp-file-guardian' ); ?></strong> <span>-</span></span>
            <span id="wpfg-rev-drafts"><strong><?php esc_html_e( 'Old Auto-Drafts:', 'wp-file-guardian' ); ?></strong> <span>-</span></span>
        </div>
        <div class="wpfg-scan-actions" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <label for="wpfg-keep-revisions" style="font-weight:500;"><?php esc_html_e( 'Keep per post:', 'wp-file-guardian' ); ?></label>
            <select id="wpfg-keep-revisions" style="min-width:140px;">
                <option value="0"><?php esc_html_e( 'Delete All', 'wp-file-guardian' ); ?></option>
                <option value="2"><?php esc_html_e( 'Keep 2', 'wp-file-guardian' ); ?></option>
                <option value="5" selected><?php esc_html_e( 'Keep 5', 'wp-file-guardian' ); ?></option>
                <option value="10"><?php esc_html_e( 'Keep 10', 'wp-file-guardian' ); ?></option>
            </select>
            <button type="button" class="button" id="wpfg-check-revisions"><?php esc_html_e( 'Check Revisions', 'wp-file-guardian' ); ?></button>
            <button type="button" class="button button-primary" id="wpfg-cleanup-revisions" style="background:#dc3545; border-color:#dc3545;"><?php esc_html_e( 'Cleanup Revisions', 'wp-file-guardian' ); ?></button>
            <span class="wpfg-inline-status" id="wpfg-revision-status"></span>
        </div>
    </div>

    <?php if ( $results && ! empty( $results['items'] ) ) : ?>
    <div class="wpfg-card">
        <h2><?php printf( esc_html__( 'Results (%d findings)', 'wp-file-guardian' ), $results['total'] ); ?></h2>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Severity', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Source', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $results['items'] as $item ) : ?>
                <tr class="wpfg-row-<?php echo esc_attr( $item->severity ); ?>" id="wpfg-db-row-<?php echo esc_attr( $item->id ); ?>">
                    <td><?php echo WPFG_Helpers::severity_badge( $item->severity ); ?></td>
                    <td><?php echo esc_html( $item->source ); ?> #<?php echo esc_html( $item->row_id ); ?></td>
                    <td><?php echo esc_html( $item->description ); ?></td>
                    <td><?php echo esc_html( $item->created_at ); ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ( 'critical' === $item->severity || 'warning' === $item->severity ) : ?>
                            <button type="button" class="button button-small wpfg-db-view-item" data-source="<?php echo esc_attr( $item->source ); ?>" data-row-id="<?php echo esc_attr( $item->row_id ); ?>" title="<?php esc_attr_e( 'View Details', 'wp-file-guardian' ); ?>">
                                <?php esc_html_e( 'View', 'wp-file-guardian' ); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete wpfg-db-clean-item" data-source="<?php echo esc_attr( $item->source ); ?>" data-row-id="<?php echo esc_attr( $item->row_id ); ?>" title="<?php esc_attr_e( 'Clean this item', 'wp-file-guardian' ); ?>">
                                <?php esc_html_e( 'Clean', 'wp-file-guardian' ); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="button button-small wpfg-db-ignore-item" data-id="<?php echo esc_attr( $item->id ); ?>" title="<?php esc_attr_e( 'Dismiss this finding', 'wp-file-guardian' ); ?>">
                            <?php esc_html_e( 'Ignore', 'wp-file-guardian' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ( $session_id ) : ?>
    <div class="wpfg-card">
        <p><?php esc_html_e( 'No suspicious findings in the database. Everything looks clean!', 'wp-file-guardian' ); ?></p>
    </div>
    <?php endif; ?>
</div>
