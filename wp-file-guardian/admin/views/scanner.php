<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'File Scanner', 'wp-file-guardian' ); ?></h1>

    <!-- Scan Controls -->
    <div class="wpfg-card wpfg-scan-card" id="wpfg-scan-controls">
        <div class="wpfg-scan-header">
            <div class="wpfg-scan-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 2a10 10 0 0 1 0 20" opacity="0.3"/>
                    <path d="M12 6v6l4 2"/>
                </svg>
            </div>
            <div>
                <h2><?php esc_html_e( 'Security Scanner', 'wp-file-guardian' ); ?></h2>
                <p class="wpfg-scan-desc"><?php esc_html_e( 'Scan your WordPress files for malware, backdoors, and suspicious code patterns.', 'wp-file-guardian' ); ?></p>
            </div>
        </div>
        <div class="wpfg-scan-actions">
            <button type="button" class="wpfg-btn wpfg-btn-primary wpfg-start-scan" data-scan-type="full">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <?php esc_html_e( 'Full Scan', 'wp-file-guardian' ); ?>
            </button>
            <button type="button" class="wpfg-btn wpfg-btn-secondary wpfg-start-scan" data-scan-type="quick">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                <?php esc_html_e( 'Quick Scan', 'wp-file-guardian' ); ?>
            </button>
            <button type="button" class="wpfg-btn wpfg-btn-ghost" id="wpfg-cancel-scan" style="display:none;">
                <?php esc_html_e( 'Cancel', 'wp-file-guardian' ); ?>
            </button>
        </div>

        <!-- Modern Progress -->
        <div id="wpfg-scan-progress" class="wpfg-scan-progress" style="display:none;">
            <div class="wpfg-progress-ring-container">
                <svg class="wpfg-progress-ring" width="100" height="100" viewBox="0 0 100 100">
                    <circle class="wpfg-progress-ring-bg" cx="50" cy="50" r="42" fill="none" stroke="#e5e7eb" stroke-width="6"/>
                    <circle class="wpfg-progress-ring-fill" id="wpfg-ring-fill" cx="50" cy="50" r="42" fill="none" stroke="url(#wpfg-gradient)" stroke-width="6" stroke-linecap="round" stroke-dasharray="263.89" stroke-dashoffset="263.89" transform="rotate(-90 50 50)"/>
                    <defs>
                        <linearGradient id="wpfg-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#6366f1"/>
                            <stop offset="100%" style="stop-color:#8b5cf6"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="wpfg-progress-ring-text">
                    <span id="wpfg-progress-pct">0</span><small>%</small>
                </div>
            </div>
            <div class="wpfg-progress-details">
                <p id="wpfg-scan-status" class="wpfg-progress-label"><?php esc_html_e( 'Preparing scan...', 'wp-file-guardian' ); ?></p>
                <div class="wpfg-progress-bar-slim">
                    <div class="wpfg-progress-bar-slim-fill" id="wpfg-progress-fill" style="width:0%"></div>
                </div>
                <div class="wpfg-progress-meta">
                    <span id="wpfg-scan-files-count">0</span> <?php esc_html_e( 'files scanned', 'wp-file-guardian' ); ?>
                    <span class="wpfg-dot-separator"></span>
                    <span id="wpfg-scan-issues-count">0</span> <?php esc_html_e( 'issues found', 'wp-file-guardian' ); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ( $session && $results ) : ?>
    <!-- Scan Results -->
    <div class="wpfg-card">
        <div class="wpfg-results-header">
            <h2>
                <?php
                printf(
                    esc_html__( 'Scan Results #%d', 'wp-file-guardian' ),
                    $session_id
                );
                ?>
                <span class="wpfg-status-chip wpfg-status-<?php echo esc_attr( $session->status ); ?>">
                    <?php echo esc_html( ucfirst( $session->status ) ); ?>
                </span>
            </h2>
        </div>

        <!-- Stats Cards -->
        <div class="wpfg-result-stats">
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number"><?php echo esc_html( number_format( $session->total_files ) ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Files Scanned', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number"><?php echo esc_html( WPFG_Helpers::format_bytes( $session->total_size ) ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Total Size', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat wpfg-result-stat-issues">
                <span class="wpfg-result-stat-number"><?php echo esc_html( $session->issues_found ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Issues Found', 'wp-file-guardian' ); ?></span>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="wpfg-filters-bar">
            <input type="hidden" name="page" value="wpfg-scanner" />
            <input type="hidden" name="session" value="<?php echo esc_attr( $session_id ); ?>" />
            <div class="wpfg-filter-group">
                <select name="severity" class="wpfg-select">
                    <option value=""><?php esc_html_e( 'All Severities', 'wp-file-guardian' ); ?></option>
                    <option value="critical" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'critical' ); ?>><?php esc_html_e( 'Critical', 'wp-file-guardian' ); ?></option>
                    <option value="warning" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'warning' ); ?>><?php esc_html_e( 'Warning', 'wp-file-guardian' ); ?></option>
                    <option value="info" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'info' ); ?>><?php esc_html_e( 'Info', 'wp-file-guardian' ); ?></option>
                    <option value="notice" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'notice' ); ?>><?php esc_html_e( 'Notice', 'wp-file-guardian' ); ?></option>
                </select>
                <div class="wpfg-search-input">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search files...', 'wp-file-guardian' ); ?>" />
                </div>
                <button type="submit" class="wpfg-btn wpfg-btn-secondary"><?php esc_html_e( 'Filter', 'wp-file-guardian' ); ?></button>
            </div>
        </form>

        <!-- Results Table -->
        <?php if ( ! empty( $results['items'] ) ) : ?>
        <form method="post" id="wpfg-results-form">
            <div class="wpfg-table-container">
                <table class="wpfg-modern-table">
                    <thead>
                        <tr>
                            <th class="wpfg-col-check"><input type="checkbox" id="wpfg-select-all" /></th>
                            <th class="wpfg-col-severity"><?php esc_html_e( 'Severity', 'wp-file-guardian' ); ?></th>
                            <th class="wpfg-col-file"><?php esc_html_e( 'File', 'wp-file-guardian' ); ?></th>
                            <th class="wpfg-col-size"><?php esc_html_e( 'Size', 'wp-file-guardian' ); ?></th>
                            <th class="wpfg-col-modified"><?php esc_html_e( 'Modified', 'wp-file-guardian' ); ?></th>
                            <th class="wpfg-col-issue"><?php esc_html_e( 'Issue', 'wp-file-guardian' ); ?></th>
                            <th class="wpfg-col-actions"><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $results['items'] as $item ) : ?>
                        <tr class="wpfg-result-row wpfg-severity-<?php echo esc_attr( $item->severity ); ?> <?php echo $item->is_ignored ? 'wpfg-ignored' : ''; ?>">
                            <td class="wpfg-col-check"><input type="checkbox" name="selected[]" value="<?php echo esc_attr( $item->file_path ); ?>" /></td>
                            <td class="wpfg-col-severity">
                                <?php
                                $severity_labels = array(
                                    'critical' => __( 'Critical', 'wp-file-guardian' ),
                                    'warning'  => __( 'Warning', 'wp-file-guardian' ),
                                    'info'     => __( 'Info', 'wp-file-guardian' ),
                                    'notice'   => __( 'Notice', 'wp-file-guardian' ),
                                );
                                $severity_icons = array(
                                    'critical' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
                                    'warning'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
                                    'info'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
                                    'notice'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>',
                                );
                                ?>
                                <span class="wpfg-severity-badge wpfg-sev-<?php echo esc_attr( $item->severity ); ?>">
                                    <?php echo isset( $severity_icons[ $item->severity ] ) ? $severity_icons[ $item->severity ] : ''; ?>
                                    <?php echo esc_html( isset( $severity_labels[ $item->severity ] ) ? $severity_labels[ $item->severity ] : $item->severity ); ?>
                                </span>
                            </td>
                            <td class="wpfg-col-file">
                                <code title="<?php echo esc_attr( $item->file_path ); ?>"><?php echo esc_html( WPFG_Helpers::relative_path( $item->file_path ) ); ?></code>
                            </td>
                            <td class="wpfg-col-size"><?php echo esc_html( WPFG_Helpers::format_bytes( $item->file_size ) ); ?></td>
                            <td class="wpfg-col-modified"><?php echo esc_html( $item->file_modified ); ?></td>
                            <td class="wpfg-col-issue">
                                <span class="wpfg-issue-text"><?php echo esc_html( $item->description ); ?></span>
                            </td>
                            <td class="wpfg-col-actions">
                                <div class="wpfg-action-group">
                                    <button type="button" class="wpfg-icon-btn wpfg-icon-btn-danger wpfg-action-btn" data-action="quarantine" data-path="<?php echo esc_attr( $item->file_path ); ?>" title="<?php esc_attr_e( 'Quarantine', 'wp-file-guardian' ); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    </button>
                                    <button type="button" class="wpfg-icon-btn wpfg-icon-btn-success wpfg-action-btn" data-action="ignore" data-id="<?php echo esc_attr( $item->id ); ?>" title="<?php esc_attr_e( 'Ignore', 'wp-file-guardian' ); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                    <button type="button" class="wpfg-icon-btn wpfg-action-btn" data-action="info" data-path="<?php echo esc_attr( $item->file_path ); ?>" title="<?php esc_attr_e( 'Details', 'wp-file-guardian' ); ?>">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions -->
            <div class="wpfg-bulk-bar">
                <select id="wpfg-bulk-action" class="wpfg-select">
                    <option value=""><?php esc_html_e( 'Bulk Actions', 'wp-file-guardian' ); ?></option>
                    <option value="quarantine"><?php esc_html_e( 'Quarantine Selected', 'wp-file-guardian' ); ?></option>
                    <option value="ignore"><?php esc_html_e( 'Ignore Selected', 'wp-file-guardian' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Delete Selected', 'wp-file-guardian' ); ?></option>
                </select>
                <button type="button" class="wpfg-btn wpfg-btn-secondary" id="wpfg-apply-bulk"><?php esc_html_e( 'Apply', 'wp-file-guardian' ); ?></button>
            </div>
        </form>

        <!-- Pagination -->
        <?php
        $total_pages = ceil( $results['total'] / 50 );
        $current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $total_pages > 1 ) :
            $range  = 2;
            $show   = array();
            for ( $i = 1; $i <= $total_pages; $i++ ) {
                if ( $i === 1 || $i === $total_pages || ( $i >= $current - $range && $i <= $current + $range ) ) {
                    $show[] = $i;
                }
            }
        ?>
        <div class="wpfg-pagination">
            <?php if ( $current > 1 ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current - 1 ) ); ?>" class="wpfg-page-btn">&laquo;</a>
            <?php endif; ?>
            <?php
            $prev = 0;
            foreach ( $show as $page ) :
                if ( $prev && $page - $prev > 1 ) :
            ?>
                    <span class="wpfg-page-ellipsis">&hellip;</span>
            <?php
                endif;
                if ( $page === $current ) :
            ?>
                    <span class="wpfg-page-btn wpfg-page-current"><?php echo esc_html( $page ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $page ) ); ?>" class="wpfg-page-btn"><?php echo esc_html( $page ); ?></a>
                <?php endif;
                $prev = $page;
            endforeach; ?>
            <?php if ( $current < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current + 1 ) ); ?>" class="wpfg-page-btn">&raquo;</a>
            <?php endif; ?>
            <span class="wpfg-pagination-info">
                <?php printf( esc_html__( 'Page %1$d of %2$d (%3$d results)', 'wp-file-guardian' ), $current, $total_pages, $results['total'] ); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <div class="wpfg-empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#a0aec0" stroke-width="1.5"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p><?php esc_html_e( 'No issues found matching your filters.', 'wp-file-guardian' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- File Info Modal -->
    <div id="wpfg-modal" class="wpfg-modal" style="display:none;">
        <div class="wpfg-modal-content">
            <span class="wpfg-modal-close">&times;</span>
            <h3><?php esc_html_e( 'File Details', 'wp-file-guardian' ); ?></h3>
            <div id="wpfg-modal-body"></div>
        </div>
    </div>
</div>
