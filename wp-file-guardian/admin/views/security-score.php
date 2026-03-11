<?php if ( ! defined( 'ABSPATH' ) ) exit;

$score = $risk['score'];
$grade = $risk['grade'];
$color = WPFG_Risk_Score::grade_color( $grade );
?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Security Score', 'wp-file-guardian' ); ?></h1>

    <!-- Score Gauge -->
    <div class="wpfg-card wpfg-card-wide" style="text-align: center;">
        <div style="display: inline-block; position: relative; width: 220px; height: 220px; margin: 20px auto;">
            <svg viewBox="0 0 220 220" width="220" height="220">
                <!-- Background circle -->
                <circle cx="110" cy="110" r="95" fill="none" stroke="#e5e7eb" stroke-width="12" />
                <!-- Score arc -->
                <circle cx="110" cy="110" r="95" fill="none"
                    stroke="<?php echo esc_attr( $color ); ?>"
                    stroke-width="12"
                    stroke-linecap="round"
                    stroke-dasharray="<?php echo esc_attr( 2 * M_PI * 95 ); ?>"
                    stroke-dashoffset="<?php echo esc_attr( 2 * M_PI * 95 * ( 1 - $score / 100 ) ); ?>"
                    transform="rotate(-90 110 110)"
                    style="transition: stroke-dashoffset 1s ease-out;" />
                <!-- Grade letter -->
                <text x="110" y="100" text-anchor="middle" fill="<?php echo esc_attr( $color ); ?>" font-size="56" font-weight="700" font-family="-apple-system, BlinkMacSystemFont, sans-serif">
                    <?php echo esc_html( $grade ); ?>
                </text>
                <!-- Score number -->
                <text x="110" y="135" text-anchor="middle" fill="#50575e" font-size="22" font-family="-apple-system, BlinkMacSystemFont, sans-serif">
                    <?php echo esc_html( $score ); ?>/100
                </text>
            </svg>
        </div>
        <p style="font-size: 16px; color: #50575e; margin-top: 8px;">
            <?php
            if ( $score >= 90 ) {
                esc_html_e( 'Excellent! Your site security is in great shape.', 'wp-file-guardian' );
            } elseif ( $score >= 80 ) {
                esc_html_e( 'Good security posture. A few improvements recommended.', 'wp-file-guardian' );
            } elseif ( $score >= 70 ) {
                esc_html_e( 'Fair security. Review the items below to improve.', 'wp-file-guardian' );
            } elseif ( $score >= 50 ) {
                esc_html_e( 'Below average. Several security issues need attention.', 'wp-file-guardian' );
            } else {
                esc_html_e( 'Critical! Immediate action is required to secure your site.', 'wp-file-guardian' );
            }
            ?>
        </p>
    </div>

    <!-- Score Breakdown -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Score Breakdown', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-table-container">
            <table class="wpfg-modern-table">
                <thead>
                    <tr>
                        <th style="width:40px;"><?php esc_html_e( 'Status', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Factor', 'wp-file-guardian' ); ?></th>
                        <th style="width:100px; text-align:right;"><?php esc_html_e( 'Deduction', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $risk['factors'] as $key => $factor ) : ?>
                    <tr>
                        <td>
                            <?php if ( 'good' === $factor['status'] ) : ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#00a32a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php elseif ( 'bad' === $factor['status'] ) : ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d63638" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <?php else : ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dba617" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $factor['label'] ); ?></td>
                        <td style="text-align:right;">
                            <?php if ( $factor['deduction'] > 0 ) : ?>
                                <span style="color:#d63638; font-weight:600;">-<?php echo esc_html( $factor['deduction'] ); ?></span>
                            <?php else : ?>
                                <span style="color:#00a32a;">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recommendations -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Recommendations', 'wp-file-guardian' ); ?></h2>
        <ul class="wpfg-recommendations">
            <?php
            $has_recommendations = false;
            foreach ( $risk['factors'] as $key => $factor ) :
                if ( 'good' === $factor['status'] ) {
                    continue;
                }
                $has_recommendations = true;
                $fix_url = '';

                switch ( $key ) {
                    case 'wp_version':
                        $fix_url = admin_url( 'update-core.php' );
                        break;
                    case 'plugin_updates':
                        $fix_url = admin_url( 'plugins.php?plugin_status=upgrade' );
                        break;
                    case 'theme_updates':
                        $fix_url = admin_url( 'update-core.php' );
                        break;
                    case 'ssl':
                        $fix_url = '';
                        break;
                    case 'debug':
                        $fix_url = '';
                        break;
                    case 'file_editor':
                        $fix_url = admin_url( 'admin.php?page=wpfg-settings' );
                        break;
                    case 'scan_findings':
                        $fix_url = admin_url( 'admin.php?page=wpfg-scanner' );
                        break;
                    case 'core_integrity':
                        $fix_url = admin_url( 'admin.php?page=wpfg-repair' );
                        break;
                    case 'login_failures':
                        $fix_url = admin_url( 'admin.php?page=wpfg-login-guard' );
                        break;
                    case 'backup':
                        $fix_url = admin_url( 'admin.php?page=wpfg-backups' );
                        break;
                    case 'db_prefix':
                    case 'admin_user':
                        $fix_url = '';
                        break;
                }
            ?>
                <li class="wpfg-rec-<?php echo 'bad' === $factor['status'] ? 'critical' : 'warning'; ?>">
                    <?php echo esc_html( $factor['label'] ); ?>
                    <?php if ( $fix_url ) : ?>
                        <a href="<?php echo esc_url( $fix_url ); ?>" class="wpfg-btn wpfg-btn-sm wpfg-btn-secondary" style="margin-left: 8px;">
                            <?php esc_html_e( 'Fix', 'wp-file-guardian' ); ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>

            <?php if ( ! $has_recommendations ) : ?>
                <li class="wpfg-rec-ok">
                    <?php esc_html_e( 'All checks passed. Your site is well secured!', 'wp-file-guardian' ); ?>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Score History Chart -->
    <div class="wpfg-card wpfg-card-wide">
        <h2><?php esc_html_e( 'Score History', 'wp-file-guardian' ); ?></h2>
        <?php if ( ! empty( $score_history['labels'] ) ) : ?>
        <canvas id="wpfg-score-chart" height="250"></canvas>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Chart === 'undefined') return;
                var ctx = document.getElementById('wpfg-score-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo wp_json_encode( $score_history['labels'] ); ?>,
                        datasets: [{
                            label: '<?php echo esc_js( __( 'Security Score', 'wp-file-guardian' ) ); ?>',
                            data: <?php echo wp_json_encode( $score_history['scores'] ); ?>,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#6366f1',
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: { precision: 0 },
                                title: {
                                    display: true,
                                    text: '<?php echo esc_js( __( 'Score', 'wp-file-guardian' ) ); ?>'
                                }
                            }
                        }
                    }
                });
            });
        </script>
        <?php else : ?>
            <p><?php esc_html_e( 'No score history available yet. Scores are recorded each time a scan is performed.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>
</div>
