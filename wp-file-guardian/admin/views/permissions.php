<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'File Permissions', 'wp-file-guardian' ); ?></h1>

    <!-- Windows Notice -->
    <?php if ( ! empty( $results['windows'] ) ) : ?>
    <div class="wpfg-card wpfg-card-wide">
        <div class="wpfg-empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dba617" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <h2><?php esc_html_e( 'Windows Server Detected', 'wp-file-guardian' ); ?></h2>
            <p><?php echo esc_html( $results['message'] ); ?></p>
            <p><?php esc_html_e( 'File permissions on Windows are managed through NTFS access control lists (ACLs) and cannot be checked or modified using Unix-style permission values.', 'wp-file-guardian' ); ?></p>
        </div>
    </div>
    <?php else : ?>

    <!-- Scan Controls -->
    <div class="wpfg-card wpfg-card-wide">
        <div class="wpfg-scan-header">
            <div class="wpfg-scan-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <div>
                <h2><?php esc_html_e( 'Permission Scanner', 'wp-file-guardian' ); ?></h2>
                <p class="wpfg-scan-desc"><?php esc_html_e( 'Check file and directory permissions against recommended security values.', 'wp-file-guardian' ); ?></p>
            </div>
        </div>
        <div class="wpfg-scan-actions">
            <button type="button" class="wpfg-btn wpfg-btn-primary" id="wpfg-perm-scan-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <?php esc_html_e( 'Scan Permissions', 'wp-file-guardian' ); ?>
            </button>
            <?php if ( ! empty( $results['issues'] ) ) : ?>
                <button type="button" class="wpfg-btn wpfg-btn-secondary" id="wpfg-perm-fix-all">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    <?php esc_html_e( 'Fix All', 'wp-file-guardian' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Table -->
    <?php if ( ! empty( $results['issues'] ) ) : ?>
    <div class="wpfg-card">
        <h2>
            <?php esc_html_e( 'Permission Issues', 'wp-file-guardian' ); ?>
            <span class="wpfg-badge wpfg-badge-warning"><?php echo esc_html( count( $results['issues'] ) ); ?></span>
        </h2>
        <div class="wpfg-table-container">
            <table class="wpfg-modern-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Path', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Current', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Recommended', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Severity', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $results['issues'] as $issue ) : ?>
                    <tr class="wpfg-result-row wpfg-severity-<?php echo esc_attr( $issue['severity'] ); ?>">
                        <td>
                            <code title="<?php echo esc_attr( $issue['path'] ); ?>"><?php echo esc_html( $issue['relative'] ); ?></code>
                        </td>
                        <td>
                            <code style="color:#d63638;"><?php echo esc_html( $issue['current_str'] ); ?></code>
                        </td>
                        <td>
                            <code style="color:#00a32a;"><?php echo esc_html( $issue['rec_str'] ); ?></code>
                        </td>
                        <td>
                            <?php if ( 'directory' === $issue['type'] ) : ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <?php esc_html_e( 'Directory', 'wp-file-guardian' ); ?>
                            <?php else : ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <?php esc_html_e( 'File', 'wp-file-guardian' ); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $sev_labels = array(
                                'critical' => __( 'Critical', 'wp-file-guardian' ),
                                'high'     => __( 'High', 'wp-file-guardian' ),
                                'medium'   => __( 'Medium', 'wp-file-guardian' ),
                                'low'      => __( 'Low', 'wp-file-guardian' ),
                            );
                            ?>
                            <span class="wpfg-severity-badge wpfg-sev-<?php echo esc_attr( $issue['severity'] ); ?>">
                                <?php echo esc_html( isset( $sev_labels[ $issue['severity'] ] ) ? $sev_labels[ $issue['severity'] ] : ucfirst( $issue['severity'] ) ); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="wpfg-btn wpfg-btn-primary wpfg-btn-sm wpfg-perm-fix" data-path="<?php echo esc_attr( $issue['path'] ); ?>" data-mode="<?php echo esc_attr( $issue['recommended'] ); ?>">
                                <?php esc_html_e( 'Fix', 'wp-file-guardian' ); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ( isset( $results['issues'] ) ) : ?>
    <div class="wpfg-card">
        <div class="wpfg-empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#00a32a" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p><?php esc_html_e( 'All file permissions are correctly configured. No issues found.', 'wp-file-guardian' ); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
