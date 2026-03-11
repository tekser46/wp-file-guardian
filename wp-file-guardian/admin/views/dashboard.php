<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'WP File Guardian — Dashboard', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-dashboard-grid">
        <!-- Health Overview -->
        <div class="wpfg-card wpfg-card-wide">
            <h2><?php esc_html_e( 'Site Health Overview', 'wp-file-guardian' ); ?></h2>
            <div class="wpfg-stats-row">
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo esc_html( number_format( $stats['total_files'] ) ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Total Files Scanned', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo esc_html( WPFG_Helpers::format_bytes( $stats['total_size'] ) ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Total Size', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-critical"><?php echo esc_html( $stats['critical_count'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Critical Issues', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-warning"><?php echo esc_html( $stats['warning_count'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Warnings', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo esc_html( $stats['info_count'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Info', 'wp-file-guardian' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Last Scan -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Last Scan', 'wp-file-guardian' ); ?></h2>
            <?php if ( $stats['last_scan'] ) : ?>
                <p><strong><?php esc_html_e( 'Date:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( $stats['last_scan'] ); ?></p>
                <p><strong><?php esc_html_e( 'Status:', 'wp-file-guardian' ); ?></strong>
                    <span class="wpfg-badge wpfg-badge-<?php echo esc_attr( $stats['scan_status'] === 'completed' ? 'info' : 'warning' ); ?>">
                        <?php echo esc_html( ucfirst( $stats['scan_status'] ) ); ?>
                    </span>
                </p>
                <p><strong><?php esc_html_e( 'Issues:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( $stats['issues_found'] ); ?></p>
                <?php if ( ! empty( $stats['session_id'] ) ) : ?>
                    <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-scanner&session=' . $stats['session_id'] ) ); ?>" class="button"><?php esc_html_e( 'View Results', 'wp-file-guardian' ); ?></a></p>
                <?php endif; ?>
            <?php else : ?>
                <p><?php esc_html_e( 'No scans have been run yet.', 'wp-file-guardian' ); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-scanner' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Run New Scan', 'wp-file-guardian' ); ?></a></p>
        </div>

        <!-- Quarantine -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Quarantine', 'wp-file-guardian' ); ?></h2>
            <p class="wpfg-stat-number"><?php echo esc_html( $quarantine_count ); ?></p>
            <p class="wpfg-stat-label"><?php esc_html_e( 'Files in Quarantine', 'wp-file-guardian' ); ?></p>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-quarantine' ) ); ?>" class="button"><?php esc_html_e( 'Manage Quarantine', 'wp-file-guardian' ); ?></a></p>
        </div>

        <!-- Latest Backup -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Latest Backup', 'wp-file-guardian' ); ?></h2>
            <?php if ( $latest_backup ) : ?>
                <p><strong><?php esc_html_e( 'Type:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( ucfirst( $latest_backup->backup_type ) ); ?></p>
                <p><strong><?php esc_html_e( 'Size:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( WPFG_Helpers::format_bytes( $latest_backup->file_size ) ); ?></p>
                <p><strong><?php esc_html_e( 'Date:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( $latest_backup->created_at ); ?></p>
                <p><strong><?php esc_html_e( 'Status:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( ucfirst( $latest_backup->status ) ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'No backups created yet.', 'wp-file-guardian' ); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-backups' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Backup', 'wp-file-guardian' ); ?></a></p>
        </div>

        <!-- Quick Actions -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Quick Actions', 'wp-file-guardian' ); ?></h2>
            <ul class="wpfg-quick-actions">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-repair' ) ); ?>"><?php esc_html_e( 'Verify Core Integrity', 'wp-file-guardian' ); ?></a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-files' ) ); ?>"><?php esc_html_e( 'Browse Files', 'wp-file-guardian' ); ?></a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-logs' ) ); ?>"><?php esc_html_e( 'View Audit Log', 'wp-file-guardian' ); ?></a></li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'wp-file-guardian' ); ?></a></li>
            </ul>
        </div>

        <!-- Recommendations -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Recommendations', 'wp-file-guardian' ); ?></h2>
            <ul class="wpfg-recommendations">
                <?php if ( $stats['critical_count'] > 0 ) : ?>
                    <li class="wpfg-rec-critical"><?php esc_html_e( 'Critical issues found — review scan results immediately.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
                <?php if ( 'never' === $stats['scan_status'] ) : ?>
                    <li class="wpfg-rec-warning"><?php esc_html_e( 'Run your first scan to check file integrity.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
                <?php if ( ! $latest_backup ) : ?>
                    <li class="wpfg-rec-warning"><?php esc_html_e( 'Create a backup before making changes.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
                <?php if ( $stats['critical_count'] === 0 && 'never' !== $stats['scan_status'] ) : ?>
                    <li class="wpfg-rec-ok"><?php esc_html_e( 'No critical issues detected.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
