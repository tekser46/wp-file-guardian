<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'File Scanner', 'wp-file-guardian' ); ?></h1>

    <!-- Scan Controls -->
    <div class="wpfg-card" id="wpfg-scan-controls">
        <h2><?php esc_html_e( 'Start New Scan', 'wp-file-guardian' ); ?></h2>
        <p><?php esc_html_e( 'Scan your WordPress files for suspicious patterns, modifications, and integrity issues.', 'wp-file-guardian' ); ?></p>
        <div class="wpfg-scan-actions">
            <select id="wpfg-scan-type">
                <option value="full"><?php esc_html_e( 'Full Scan', 'wp-file-guardian' ); ?></option>
                <option value="quick"><?php esc_html_e( 'Quick Scan (wp-content only)', 'wp-file-guardian' ); ?></option>
            </select>
            <button type="button" class="button button-primary" id="wpfg-start-scan"><?php esc_html_e( 'Start Scan', 'wp-file-guardian' ); ?></button>
            <button type="button" class="button" id="wpfg-cancel-scan" style="display:none;"><?php esc_html_e( 'Cancel', 'wp-file-guardian' ); ?></button>
        </div>

        <!-- Progress -->
        <div id="wpfg-scan-progress" style="display:none; margin-top:15px;">
            <div class="wpfg-progress-bar">
                <div class="wpfg-progress-fill" id="wpfg-progress-fill" style="width:0%"></div>
            </div>
            <p id="wpfg-scan-status"><?php esc_html_e( 'Preparing...', 'wp-file-guardian' ); ?></p>
        </div>
    </div>

    <?php if ( $session && $results ) : ?>
    <!-- Scan Results -->
    <div class="wpfg-card">
        <h2>
            <?php
            printf(
                /* translators: %d: session ID */
                esc_html__( 'Scan Results (Session #%d)', 'wp-file-guardian' ),
                $session_id
            );
            ?>
            <span class="wpfg-badge wpfg-badge-<?php echo $session->status === 'completed' ? 'info' : 'warning'; ?>">
                <?php echo esc_html( ucfirst( $session->status ) ); ?>
            </span>
        </h2>

        <div class="wpfg-stats-row wpfg-mb">
            <div class="wpfg-stat-sm">
                <strong><?php echo esc_html( number_format( $session->total_files ) ); ?></strong>
                <?php esc_html_e( 'Files Scanned', 'wp-file-guardian' ); ?>
            </div>
            <div class="wpfg-stat-sm">
                <strong><?php echo esc_html( WPFG_Helpers::format_bytes( $session->total_size ) ); ?></strong>
                <?php esc_html_e( 'Total Size', 'wp-file-guardian' ); ?>
            </div>
            <div class="wpfg-stat-sm">
                <strong><?php echo esc_html( $session->issues_found ); ?></strong>
                <?php esc_html_e( 'Issues', 'wp-file-guardian' ); ?>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" class="wpfg-filters">
            <input type="hidden" name="page" value="wpfg-scanner" />
            <input type="hidden" name="session" value="<?php echo esc_attr( $session_id ); ?>" />
            <select name="severity">
                <option value=""><?php esc_html_e( 'All Severities', 'wp-file-guardian' ); ?></option>
                <option value="critical" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'critical' ); ?>><?php esc_html_e( 'Critical', 'wp-file-guardian' ); ?></option>
                <option value="warning" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'warning' ); ?>><?php esc_html_e( 'Warning', 'wp-file-guardian' ); ?></option>
                <option value="info" <?php selected( isset( $_GET['severity'] ) && $_GET['severity'] === 'info' ); ?>><?php esc_html_e( 'Info', 'wp-file-guardian' ); ?></option>
            </select>
            <input type="text" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search files...', 'wp-file-guardian' ); ?>" />
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-file-guardian' ); ?></button>
        </form>

        <!-- Results Table -->
        <?php if ( ! empty( $results['items'] ) ) : ?>
        <form method="post" id="wpfg-results-form">
            <table class="widefat striped wpfg-table">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="wpfg-select-all" /></th>
                        <th><?php esc_html_e( 'Severity', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'File', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Modified', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Issue', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $results['items'] as $item ) : ?>
                    <tr class="wpfg-row-<?php echo esc_attr( $item->severity ); ?> <?php echo $item->is_ignored ? 'wpfg-ignored' : ''; ?>">
                        <td><input type="checkbox" name="selected[]" value="<?php echo esc_attr( $item->file_path ); ?>" /></td>
                        <td><?php echo WPFG_Helpers::severity_badge( $item->severity ); ?></td>
                        <td class="wpfg-filepath" title="<?php echo esc_attr( $item->file_path ); ?>">
                            <?php echo esc_html( WPFG_Helpers::relative_path( $item->file_path ) ); ?>
                        </td>
                        <td><?php echo esc_html( WPFG_Helpers::format_bytes( $item->file_size ) ); ?></td>
                        <td><?php echo esc_html( $item->file_modified ); ?></td>
                        <td><?php echo esc_html( $item->description ); ?></td>
                        <td class="wpfg-actions">
                            <button type="button" class="button button-small wpfg-action-btn" data-action="quarantine" data-path="<?php echo esc_attr( $item->file_path ); ?>" title="<?php esc_attr_e( 'Quarantine', 'wp-file-guardian' ); ?>">&#128274;</button>
                            <button type="button" class="button button-small wpfg-action-btn" data-action="ignore" data-id="<?php echo esc_attr( $item->id ); ?>" title="<?php esc_attr_e( 'Ignore', 'wp-file-guardian' ); ?>">&#10003;</button>
                            <button type="button" class="button button-small wpfg-action-btn" data-action="info" data-path="<?php echo esc_attr( $item->file_path ); ?>" title="<?php esc_attr_e( 'Details', 'wp-file-guardian' ); ?>">&#8505;</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Bulk Actions -->
            <div class="wpfg-bulk-bar">
                <select id="wpfg-bulk-action">
                    <option value=""><?php esc_html_e( 'Bulk Actions', 'wp-file-guardian' ); ?></option>
                    <option value="quarantine"><?php esc_html_e( 'Quarantine Selected', 'wp-file-guardian' ); ?></option>
                    <option value="ignore"><?php esc_html_e( 'Ignore Selected', 'wp-file-guardian' ); ?></option>
                    <option value="delete"><?php esc_html_e( 'Delete Selected', 'wp-file-guardian' ); ?></option>
                </select>
                <button type="button" class="button" id="wpfg-apply-bulk"><?php esc_html_e( 'Apply', 'wp-file-guardian' ); ?></button>
            </div>
        </form>

        <!-- Pagination -->
        <?php
        $total_pages = ceil( $results['total'] / 50 );
        $current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $total_pages > 1 ) :
            // Smart pagination: show first, last, current ±2, with ellipsis.
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
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current - 1 ) ); ?>">&laquo;</a>
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
                    <span class="wpfg-page-current"><?php echo esc_html( $page ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $page ) ); ?>"><?php echo esc_html( $page ); ?></a>
                <?php endif;
                $prev = $page;
            endforeach; ?>
            <?php if ( $current < $total_pages ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $current + 1 ) ); ?>">&raquo;</a>
            <?php endif; ?>
            <span class="wpfg-pagination-info">
                <?php printf( esc_html__( 'Page %1$d of %2$d (%3$d results)', 'wp-file-guardian' ), $current, $total_pages, $results['total'] ); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No issues found matching your filters.', 'wp-file-guardian' ); ?></p>
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
