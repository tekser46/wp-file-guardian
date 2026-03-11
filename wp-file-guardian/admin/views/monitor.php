<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'File Monitor', 'wp-file-guardian' ); ?></h1>

    <!-- Baseline Status -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'File Baseline', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-stats-row wpfg-mb">
            <div class="wpfg-stat-sm">
                <strong><?php echo esc_html( number_format( $baseline_count ) ); ?></strong>
                <?php esc_html_e( 'Files Tracked', 'wp-file-guardian' ); ?>
            </div>
            <div class="wpfg-stat-sm">
                <strong><?php echo $last_check ? esc_html( wp_date( 'Y-m-d H:i:s', $last_check ) ) : esc_html__( 'Never', 'wp-file-guardian' ); ?></strong>
                <?php esc_html_e( 'Last Check', 'wp-file-guardian' ); ?>
            </div>
        </div>
        <div class="wpfg-scan-actions">
            <button type="button" class="button button-primary" id="wpfg-build-baseline">
                <?php echo $baseline_count > 0 ? esc_html__( 'Rebuild Baseline', 'wp-file-guardian' ) : esc_html__( 'Build Baseline', 'wp-file-guardian' ); ?>
            </button>
            <?php if ( $baseline_count > 0 ) : ?>
            <button type="button" class="button" id="wpfg-compare-files"><?php esc_html_e( 'Compare Now', 'wp-file-guardian' ); ?></button>
            <?php endif; ?>
            <span class="wpfg-inline-status" id="wpfg-monitor-status"></span>
        </div>
    </div>

    <!-- Compare Results (populated via AJAX) -->
    <div id="wpfg-compare-results" class="wpfg-card" style="display:none;">
        <h2><?php esc_html_e( 'Comparison Results', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-stats-row wpfg-mb">
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-critical" id="wpfg-mon-added">0</span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Added', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-warning" id="wpfg-mon-modified">0</span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Modified', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number" id="wpfg-mon-deleted">0</span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Deleted', 'wp-file-guardian' ); ?></span>
            </div>
        </div>
        <div id="wpfg-mon-details"></div>
    </div>

    <!-- Change History -->
    <?php if ( ! empty( $history ) ) : ?>
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Change History', 'wp-file-guardian' ); ?></h2>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Added', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Modified', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Deleted', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Details', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $history as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( $entry->created_at ); ?></td>
                    <td><span class="wpfg-text-critical"><?php echo esc_html( $entry->added_count ); ?></span></td>
                    <td><span class="wpfg-text-warning"><?php echo esc_html( $entry->modified_count ); ?></span></td>
                    <td><?php echo esc_html( $entry->deleted_count ); ?></td>
                    <td>
                        <button type="button" class="button button-small wpfg-view-changes" data-details="<?php echo esc_attr( $entry->details ); ?>">
                            <?php esc_html_e( 'View', 'wp-file-guardian' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ( $baseline_count > 0 ) : ?>
    <div class="wpfg-card">
        <p><?php esc_html_e( 'No file changes detected yet. The monitor checks hourly for modifications.', 'wp-file-guardian' ); ?></p>
    </div>
    <?php endif; ?>

    <!-- Change Details Modal -->
    <div id="wpfg-modal" class="wpfg-modal" style="display:none;">
        <div class="wpfg-modal-content">
            <span class="wpfg-modal-close">&times;</span>
            <h3><?php esc_html_e( 'Change Details', 'wp-file-guardian' ); ?></h3>
            <div id="wpfg-modal-body"></div>
        </div>
    </div>
</div>
