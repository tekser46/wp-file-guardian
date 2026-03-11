<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Vulnerability Scanner', 'wp-file-guardian' ); ?></h1>

    <!-- Summary Cards -->
    <div class="wpfg-card wpfg-card-wide">
        <h2><?php esc_html_e( 'Vulnerability Overview', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-result-stats">
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number"><?php echo esc_html( $summary['total'] ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Total Issues', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number wpfg-text-critical"><?php echo esc_html( $summary['critical'] ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Critical', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number" style="color:#d63638;"><?php echo esc_html( $summary['high'] ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'High', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number wpfg-text-warning"><?php echo esc_html( $summary['medium'] ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Medium', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number wpfg-text-info"><?php echo esc_html( $summary['low'] ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Low', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-result-stat">
                <span class="wpfg-result-stat-number" style="color:#666;"><?php echo esc_html( $summary['outdated'] ); ?></span>
                <span class="wpfg-result-stat-label"><?php esc_html_e( 'Outdated', 'wp-file-guardian' ); ?></span>
            </div>
        </div>
        <?php if ( $summary['last_scan'] ) : ?>
            <p class="wpfg-mb">
                <strong><?php esc_html_e( 'Last Scan:', 'wp-file-guardian' ); ?></strong>
                <?php echo esc_html( $summary['last_scan'] ); ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Scan Controls -->
    <div class="wpfg-card">
        <div class="wpfg-scan-header">
            <div class="wpfg-scan-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div>
                <h2><?php esc_html_e( 'Check for Vulnerabilities', 'wp-file-guardian' ); ?></h2>
                <p class="wpfg-scan-desc"><?php esc_html_e( 'Scan all installed plugins and themes for known vulnerabilities and available updates.', 'wp-file-guardian' ); ?></p>
            </div>
        </div>
        <div class="wpfg-scan-actions">
            <button type="button" class="wpfg-btn wpfg-btn-primary" id="wpfg-start-vuln-scan">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                <?php esc_html_e( 'Scan Now', 'wp-file-guardian' ); ?>
            </button>
        </div>
        <div id="wpfg-vuln-progress" style="display:none;">
            <div class="wpfg-progress-bar-slim">
                <div class="wpfg-progress-bar-slim-fill" id="wpfg-vuln-progress-fill" style="width:0%"></div>
            </div>
            <p id="wpfg-vuln-status" class="wpfg-progress-label"><?php esc_html_e( 'Scanning plugins and themes...', 'wp-file-guardian' ); ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="wpfg-card">
        <form method="get" class="wpfg-filters-bar">
            <input type="hidden" name="page" value="wpfg-vulnerabilities" />
            <div class="wpfg-filter-group">
                <select name="severity" class="wpfg-select">
                    <option value=""><?php esc_html_e( 'All Severities', 'wp-file-guardian' ); ?></option>
                    <option value="critical" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'critical' ); ?>><?php esc_html_e( 'Critical', 'wp-file-guardian' ); ?></option>
                    <option value="high" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'high' ); ?>><?php esc_html_e( 'High', 'wp-file-guardian' ); ?></option>
                    <option value="medium" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'medium' ); ?>><?php esc_html_e( 'Medium', 'wp-file-guardian' ); ?></option>
                    <option value="low" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'low' ); ?>><?php esc_html_e( 'Low', 'wp-file-guardian' ); ?></option>
                </select>
                <select name="type" class="wpfg-select">
                    <option value=""><?php esc_html_e( 'All Types', 'wp-file-guardian' ); ?></option>
                    <option value="plugin" <?php selected( isset( $_GET['type'] ) && $_GET['type'] === 'plugin' ); ?>><?php esc_html_e( 'Plugins', 'wp-file-guardian' ); ?></option>
                    <option value="theme" <?php selected( isset( $_GET['type'] ) && $_GET['type'] === 'theme' ); ?>><?php esc_html_e( 'Themes', 'wp-file-guardian' ); ?></option>
                </select>
                <div class="wpfg-search-input">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search plugins/themes...', 'wp-file-guardian' ); ?>" />
                </div>
                <button type="submit" class="wpfg-btn wpfg-btn-secondary"><?php esc_html_e( 'Filter', 'wp-file-guardian' ); ?></button>
            </div>
        </form>

        <!-- Results Table -->
        <?php if ( ! empty( $results['items'] ) ) : ?>
        <div class="wpfg-table-container">
            <table class="wpfg-modern-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Type', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Installed', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Latest', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Vulnerability', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Severity', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $results['items'] as $item ) : ?>
                    <tr class="wpfg-result-row wpfg-severity-<?php echo esc_attr( $item->severity ); ?>">
                        <td>
                            <span class="wpfg-badge wpfg-badge-info"><?php echo esc_html( ucfirst( $item->type ) ); ?></span>
                        </td>
                        <td><strong><?php echo esc_html( $item->name ); ?></strong></td>
                        <td><code><?php echo esc_html( $item->installed_version ); ?></code></td>
                        <td>
                            <?php if ( $item->installed_version !== $item->latest_version ) : ?>
                                <code style="color:#00a32a;"><?php echo esc_html( $item->latest_version ); ?></code>
                            <?php else : ?>
                                <code><?php echo esc_html( $item->latest_version ); ?></code>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( ! empty( $item->vulnerabilities ) ) : ?>
                                <?php foreach ( $item->vulnerabilities as $vuln ) : ?>
                                    <div class="wpfg-vuln-entry">
                                        <?php echo esc_html( $vuln['title'] ); ?>
                                        <?php if ( ! empty( $vuln['cve'] ) ) : ?>
                                            <small>(<?php echo esc_html( $vuln['cve'] ); ?>)</small>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $vuln['fixed_in'] ) ) : ?>
                                            <br><small><?php printf( esc_html__( 'Fixed in: %s', 'wp-file-guardian' ), esc_html( $vuln['fixed_in'] ) ); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <span style="color:#666;"><?php esc_html_e( 'Version outdated', 'wp-file-guardian' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $sev_colors = array(
                                'critical' => '#8b0000',
                                'high'     => '#d63638',
                                'medium'   => '#dba617',
                                'low'      => '#2271b1',
                                'none'     => '#666',
                            );
                            $sev_color = isset( $sev_colors[ $item->severity ] ) ? $sev_colors[ $item->severity ] : '#666';
                            ?>
                            <span class="wpfg-severity-badge wpfg-sev-<?php echo esc_attr( $item->severity ); ?>">
                                <?php echo esc_html( ucfirst( $item->severity ) ); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $status_classes = array(
                                'vulnerable' => 'wpfg-badge-critical',
                                'outdated'   => 'wpfg-badge-warning',
                                'ignored'    => 'wpfg-badge-info',
                                'secure'     => 'wpfg-badge-info',
                            );
                            $status_class = isset( $status_classes[ $item->status ] ) ? $status_classes[ $item->status ] : 'wpfg-badge-info';
                            ?>
                            <span class="wpfg-badge <?php echo esc_attr( $status_class ); ?>">
                                <?php echo esc_html( ucfirst( $item->status ) ); ?>
                            </span>
                        </td>
                        <td>
                            <div class="wpfg-action-group">
                                <?php if ( 'ignored' !== $item->status ) : ?>
                                    <button type="button" class="wpfg-btn wpfg-btn-primary wpfg-btn-sm wpfg-vuln-action" data-action="update" data-slug="<?php echo esc_attr( $item->slug ); ?>" data-type="<?php echo esc_attr( $item->type ); ?>" title="<?php esc_attr_e( 'Update', 'wp-file-guardian' ); ?>">
                                        <?php esc_html_e( 'Update', 'wp-file-guardian' ); ?>
                                    </button>
                                    <button type="button" class="wpfg-btn wpfg-btn-ghost wpfg-btn-sm wpfg-vuln-action" data-action="ignore" data-id="<?php echo esc_attr( $item->id ); ?>" title="<?php esc_attr_e( 'Ignore', 'wp-file-guardian' ); ?>">
                                        <?php esc_html_e( 'Ignore', 'wp-file-guardian' ); ?>
                                    </button>
                                <?php else : ?>
                                    <span style="color:#666;"><?php esc_html_e( 'Ignored', 'wp-file-guardian' ); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        $total_pages = ceil( $results['total'] / 20 );
        $current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $total_pages > 1 ) :
            $range = 2;
            $show  = array();
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
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#a0aec0" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <p><?php esc_html_e( 'No vulnerability issues found. Run a scan to check your plugins and themes.', 'wp-file-guardian' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
