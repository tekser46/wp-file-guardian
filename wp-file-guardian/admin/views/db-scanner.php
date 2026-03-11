<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Database Scanner', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Scan Database for Malware', 'wp-file-guardian' ); ?></h2>
        <p><?php esc_html_e( 'Scan posts, options, comments, and users for injected scripts, suspicious code, and hidden admin accounts.', 'wp-file-guardian' ); ?></p>
        <div class="wpfg-scan-actions">
            <button type="button" class="button button-primary" id="wpfg-start-db-scan"><?php esc_html_e( 'Start DB Scan', 'wp-file-guardian' ); ?></button>
            <span class="wpfg-inline-status" id="wpfg-db-scan-status"></span>
        </div>
        <div id="wpfg-db-scan-progress" style="display:none; margin-top:15px;">
            <div class="wpfg-progress-bar">
                <div class="wpfg-progress-fill" id="wpfg-db-progress-fill" style="width:0%"></div>
            </div>
            <p id="wpfg-db-scan-msg"><?php esc_html_e( 'Scanning...', 'wp-file-guardian' ); ?></p>
        </div>
    </div>

    <?php if ( $results && ! empty( $results['items'] ) ) : ?>
    <div class="wpfg-card">
        <h2><?php printf( esc_html__( 'Results (%d findings)', 'wp-file-guardian' ), $results['total'] ); ?></h2>
        <table class="widefat striped wpfg-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Severity', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Source', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'wp-file-guardian' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wp-file-guardian' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $results['items'] as $item ) : ?>
                <tr class="wpfg-row-<?php echo esc_attr( $item->severity ); ?>">
                    <td><?php echo WPFG_Helpers::severity_badge( $item->severity ); ?></td>
                    <td><?php echo esc_html( $item->source ); ?> #<?php echo esc_html( $item->row_id ); ?></td>
                    <td><?php echo esc_html( $item->description ); ?></td>
                    <td><?php echo esc_html( $item->created_at ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif ( $session_id ) : ?>
    <div class="wpfg-card">
        <p><?php esc_html_e( 'No suspicious findings in the database. Everything looks clean!', 'wp-file-guardian' ); ?></p>
    </div>
    <?php endif; ?>
</div>
