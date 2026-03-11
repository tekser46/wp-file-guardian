<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Audit Log', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-card">
        <!-- Filters -->
        <form method="get" class="wpfg-filters">
            <input type="hidden" name="page" value="wpfg-logs" />
            <select name="action_filter">
                <option value=""><?php esc_html_e( 'All Actions', 'wp-file-guardian' ); ?></option>
                <?php
                $actions = array(
                    'scan_started', 'scan_completed', 'scan_cancelled',
                    'quarantine', 'quarantine_restore', 'quarantine_delete',
                    'file_delete', 'bulk_quarantine', 'bulk_delete', 'bulk_ignore',
                    'backup_create', 'backup_delete', 'backup_restore', 'backup_download',
                    'restore_point', 'repair_core', 'repair_plugin', 'repair_theme',
                    'settings_change', 'settings_reset', 'logs_cleared',
                );
                foreach ( $actions as $a ) :
                ?>
                    <option value="<?php echo esc_attr( $a ); ?>" <?php selected( isset( $_GET['action_filter'] ) && $_GET['action_filter'] === $a ); ?>>
                        <?php echo esc_html( $a ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="result">
                <option value=""><?php esc_html_e( 'All Results', 'wp-file-guardian' ); ?></option>
                <option value="success" <?php selected( isset( $_GET['result'] ) && $_GET['result'] === 'success' ); ?>><?php esc_html_e( 'Success', 'wp-file-guardian' ); ?></option>
                <option value="error" <?php selected( isset( $_GET['result'] ) && $_GET['result'] === 'error' ); ?>><?php esc_html_e( 'Error', 'wp-file-guardian' ); ?></option>
            </select>
            <input type="text" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'wp-file-guardian' ); ?>" />
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-file-guardian' ); ?></button>
        </form>

        <!-- Log Actions -->
        <div class="wpfg-log-actions" style="margin-bottom:10px;">
            <button type="button" class="button" id="wpfg-export-logs"><?php esc_html_e( 'Export CSV', 'wp-file-guardian' ); ?></button>
            <button type="button" class="button wpfg-btn-danger" id="wpfg-clear-logs"><?php esc_html_e( 'Clear All Logs', 'wp-file-guardian' ); ?></button>
        </div>

        <?php if ( ! empty( $logs['items'] ) ) : ?>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'User', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Target', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Result', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Details', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'IP', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs['items'] as $log ) : ?>
                <tr>
                    <td><?php echo esc_html( $log->created_at ); ?></td>
                    <td>
                        <?php
                        $user = get_userdata( $log->user_id );
                        echo esc_html( $user ? $user->display_name : ( $log->user_id ? '#' . $log->user_id : __( 'System', 'wp-file-guardian' ) ) );
                        ?>
                    </td>
                    <td><code><?php echo esc_html( $log->action ); ?></code></td>
                    <td class="wpfg-filepath" title="<?php echo esc_attr( $log->target_path ); ?>">
                        <?php echo esc_html( $log->target_path ? WPFG_Helpers::relative_path( $log->target_path ) : '—' ); ?>
                    </td>
                    <td>
                        <span class="wpfg-badge wpfg-badge-<?php echo $log->result === 'success' ? 'info' : 'critical'; ?>">
                            <?php echo esc_html( ucfirst( $log->result ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( wp_trim_words( $log->details, 15 ) ); ?></td>
                    <td><?php echo esc_html( $log->ip_address ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $total_pages = ceil( $logs['total'] / 30 );
        $current     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $total_pages > 1 ) :
        ?>
        <div class="wpfg-pagination">
            <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                <?php if ( $i === $current ) : ?>
                    <span class="wpfg-page-current"><?php echo esc_html( $i ); ?></span>
                <?php else : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No log entries found.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>
</div>
