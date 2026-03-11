<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Login Guard', 'wp-file-guardian' ); ?></h1>

    <!-- Stats Overview -->
    <div class="wpfg-card wpfg-card-wide">
        <h2><?php esc_html_e( 'Login Activity (Last 24 Hours)', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-stats-row">
            <div class="wpfg-stat">
                <span class="wpfg-stat-number" style="color:#00a32a;"><?php echo esc_html( $stats['total_today_success'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Successful Logins', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-critical"><?php echo esc_html( $stats['total_today_failed'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Failed Attempts', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-info"><?php echo esc_html( $stats['unique_ips_today'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Unique IPs', 'wp-file-guardian' ); ?></span>
            </div>
            <div class="wpfg-stat">
                <span class="wpfg-stat-number wpfg-text-warning"><?php echo esc_html( $stats['total_lockouts'] ); ?></span>
                <span class="wpfg-stat-label"><?php esc_html_e( 'Total Lockouts', 'wp-file-guardian' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Login Log -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Login Log', 'wp-file-guardian' ); ?></h2>

        <!-- Filters -->
        <form method="get" class="wpfg-filters">
            <input type="hidden" name="page" value="wpfg-login-guard" />
            <select name="status">
                <option value=""><?php esc_html_e( 'All Statuses', 'wp-file-guardian' ); ?></option>
                <option value="success" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'success' ); ?>><?php esc_html_e( 'Success', 'wp-file-guardian' ); ?></option>
                <option value="failed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'failed' ); ?>><?php esc_html_e( 'Failed', 'wp-file-guardian' ); ?></option>
            </select>
            <input type="text" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search username or IP...', 'wp-file-guardian' ); ?>" />
            <button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-file-guardian' ); ?></button>
        </form>

        <?php if ( ! empty( $log['items'] ) ) : ?>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Status', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Username', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'IP Address', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'User Agent', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $log['items'] as $entry ) : ?>
                <tr class="<?php echo $entry->status === 'failed' ? 'wpfg-row-critical' : ''; ?>">
                    <td>
                        <?php if ( $entry->status === 'success' ) : ?>
                            <span class="wpfg-badge wpfg-badge-info"><?php esc_html_e( 'Success', 'wp-file-guardian' ); ?></span>
                        <?php else : ?>
                            <span class="wpfg-badge wpfg-badge-critical"><?php esc_html_e( 'Failed', 'wp-file-guardian' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $entry->username ); ?></td>
                    <td><code><?php echo esc_html( $entry->ip_address ); ?></code></td>
                    <td class="wpfg-filepath" title="<?php echo esc_attr( $entry->user_agent ); ?>"><?php echo esc_html( mb_substr( $entry->user_agent, 0, 60 ) ); ?></td>
                    <td><?php echo esc_html( $entry->created_at ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php
        $total_pages = ceil( $log['total'] / 30 );
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
                <?php printf( esc_html__( 'Page %1$d of %2$d (%3$d entries)', 'wp-file-guardian' ), $current, $total_pages, $log['total'] ); ?>
            </span>
        </div>
        <?php endif; ?>

        <?php else : ?>
            <p><?php esc_html_e( 'No login attempts recorded yet.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>
</div>
