<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'WP File Guardian — Dashboard', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-dashboard-grid">

        <!-- Risk Score Gauge -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Security Score', 'wp-file-guardian' ); ?></h2>
            <div class="wpfg-risk-gauge">
                <div class="wpfg-gauge-circle" style="--gauge-color: <?php echo esc_attr( WPFG_Risk_Score::grade_color( $risk['grade'] ) ); ?>; --gauge-pct: <?php echo esc_attr( $risk['score'] ); ?>;">
                    <span class="wpfg-gauge-score"><?php echo esc_html( $risk['score'] ); ?></span>
                    <span class="wpfg-gauge-grade"><?php echo esc_html( $risk['grade'] ); ?></span>
                </div>
            </div>
            <ul class="wpfg-risk-factors">
                <?php foreach ( $risk['factors'] as $factor ) : ?>
                <li class="wpfg-factor-<?php echo esc_attr( $factor['status'] ); ?>">
                    <?php echo esc_html( $factor['label'] ); ?>
                    <?php if ( $factor['deduction'] > 0 ) : ?>
                        <span class="wpfg-factor-pts">-<?php echo esc_html( $factor['deduction'] ); ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Health Overview -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Site Health Overview', 'wp-file-guardian' ); ?></h2>
            <div class="wpfg-stats-row">
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo esc_html( number_format( $stats['total_files'] ) ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Files Scanned', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-critical"><?php echo esc_html( $stats['critical_count'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Critical', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-warning"><?php echo esc_html( $stats['warning_count'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Warnings', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-info"><?php echo esc_html( $stats['info_count'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Info', 'wp-file-guardian' ); ?></span>
                </div>
            </div>
            <?php if ( $stats['last_scan'] ) : ?>
                <p class="wpfg-mb"><strong><?php esc_html_e( 'Last Scan:', 'wp-file-guardian' ); ?></strong> <?php echo esc_html( $stats['last_scan'] ); ?>
                    <span class="wpfg-badge wpfg-badge-<?php echo esc_attr( $stats['scan_status'] === 'completed' ? 'info' : 'warning' ); ?>">
                        <?php echo esc_html( ucfirst( $stats['scan_status'] ) ); ?>
                    </span>
                </p>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-scanner' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Run New Scan', 'wp-file-guardian' ); ?></a>
            <?php if ( ! empty( $stats['session_id'] ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-scanner&session=' . $stats['session_id'] ) ); ?>" class="button"><?php esc_html_e( 'View Results', 'wp-file-guardian' ); ?></a>
            <?php endif; ?>
        </div>

        <!-- Login Activity -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Login Activity (24h)', 'wp-file-guardian' ); ?></h2>
            <div class="wpfg-stats-row">
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number" style="color:#00a32a;"><?php echo esc_html( $login_stats['total_today_success'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Successful', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-critical"><?php echo esc_html( $login_stats['total_today_failed'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Failed', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number wpfg-text-warning"><?php echo esc_html( $login_stats['total_lockouts'] ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Lockouts', 'wp-file-guardian' ); ?></span>
                </div>
            </div>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-login-guard' ) ); ?>" class="button"><?php esc_html_e( 'View Login Log', 'wp-file-guardian' ); ?></a></p>
        </div>

        <!-- File Monitor -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'File Monitor', 'wp-file-guardian' ); ?></h2>
            <div class="wpfg-stats-row">
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo esc_html( number_format( $monitor_count ) ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Files Tracked', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo $monitor_last ? esc_html( human_time_diff( $monitor_last ) . ' ago' ) : esc_html__( 'Never', 'wp-file-guardian' ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Last Check', 'wp-file-guardian' ); ?></span>
                </div>
            </div>
            <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-monitor' ) ); ?>" class="button"><?php esc_html_e( 'Manage Monitor', 'wp-file-guardian' ); ?></a></p>
        </div>

        <!-- Quarantine & Backup -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Quarantine & Backups', 'wp-file-guardian' ); ?></h2>
            <div class="wpfg-stats-row">
                <div class="wpfg-stat">
                    <span class="wpfg-stat-number"><?php echo esc_html( $quarantine_count ); ?></span>
                    <span class="wpfg-stat-label"><?php esc_html_e( 'Quarantined', 'wp-file-guardian' ); ?></span>
                </div>
                <div class="wpfg-stat">
                    <?php if ( $latest_backup ) : ?>
                        <span class="wpfg-stat-number"><?php echo esc_html( WPFG_Helpers::format_bytes( $latest_backup->file_size ) ); ?></span>
                        <span class="wpfg-stat-label"><?php echo esc_html( $latest_backup->created_at ); ?></span>
                    <?php else : ?>
                        <span class="wpfg-stat-number">—</span>
                        <span class="wpfg-stat-label"><?php esc_html_e( 'No Backups', 'wp-file-guardian' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-quarantine' ) ); ?>" class="button"><?php esc_html_e( 'Quarantine', 'wp-file-guardian' ); ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-backups' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Backup', 'wp-file-guardian' ); ?></a>
            </p>
        </div>

        <!-- Scan History Chart -->
        <div class="wpfg-card wpfg-card-wide">
            <h2><?php esc_html_e( 'Scan History (Last 30 Days)', 'wp-file-guardian' ); ?></h2>
            <?php if ( ! empty( $scan_history['labels'] ) ) : ?>
            <canvas id="wpfg-scan-chart" height="250"></canvas>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof Chart === 'undefined') return;
                    var ctx = document.getElementById('wpfg-scan-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo wp_json_encode( $scan_history['labels'] ); ?>,
                            datasets: [
                                {
                                    label: '<?php echo esc_js( __( 'Critical', 'wp-file-guardian' ) ); ?>',
                                    data: <?php echo wp_json_encode( $scan_history['critical'] ); ?>,
                                    borderColor: '#d63638',
                                    backgroundColor: 'rgba(214,54,56,0.1)',
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: '<?php echo esc_js( __( 'Warning', 'wp-file-guardian' ) ); ?>',
                                    data: <?php echo wp_json_encode( $scan_history['warning'] ); ?>,
                                    borderColor: '#dba617',
                                    backgroundColor: 'rgba(219,166,23,0.1)',
                                    tension: 0.3,
                                    fill: true
                                },
                                {
                                    label: '<?php echo esc_js( __( 'Info', 'wp-file-guardian' ) ); ?>',
                                    data: <?php echo wp_json_encode( $scan_history['info'] ); ?>,
                                    borderColor: '#2271b1',
                                    backgroundColor: 'rgba(34,113,177,0.1)',
                                    tension: 0.3,
                                    fill: true
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } }
                            }
                        }
                    });
                });
            </script>
            <?php else : ?>
                <p><?php esc_html_e( 'No scan history available yet. Run scans regularly to see trends.', 'wp-file-guardian' ); ?></p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Quick Actions', 'wp-file-guardian' ); ?></h2>
            <ul class="wpfg-quick-actions">
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpfg-db-scanner' ) ); ?>"><?php esc_html_e( 'Scan Database', 'wp-file-guardian' ); ?></a></li>
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
                <?php if ( $risk['score'] < 70 ) : ?>
                    <li class="wpfg-rec-warning"><?php printf( esc_html__( 'Security score is %d — review the risk factors above.', 'wp-file-guardian' ), $risk['score'] ); ?></li>
                <?php endif; ?>
                <?php if ( 'never' === $stats['scan_status'] ) : ?>
                    <li class="wpfg-rec-warning"><?php esc_html_e( 'Run your first scan to check file integrity.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
                <?php if ( ! $latest_backup ) : ?>
                    <li class="wpfg-rec-warning"><?php esc_html_e( 'Create a backup before making changes.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
                <?php if ( 0 === $monitor_count ) : ?>
                    <li class="wpfg-rec-warning"><?php esc_html_e( 'Build a file baseline to enable change monitoring.', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
                <?php if ( $stats['critical_count'] === 0 && 'never' !== $stats['scan_status'] && $risk['score'] >= 70 ) : ?>
                    <li class="wpfg-rec-ok"><?php esc_html_e( 'Your site looks healthy. Keep scanning regularly!', 'wp-file-guardian' ); ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
