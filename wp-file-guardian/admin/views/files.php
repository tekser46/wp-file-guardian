<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'File Manager', 'wp-file-guardian' ); ?></h1>

    <div class="wpfg-card">
        <p><?php esc_html_e( 'Browse and manage files within your WordPress installation. Use the scan results page for detailed analysis.', 'wp-file-guardian' ); ?></p>

        <!-- Directory Browser -->
        <div class="wpfg-file-browser">
            <div class="wpfg-breadcrumb" id="wpfg-breadcrumb">
                <span class="wpfg-crumb" data-path="<?php echo esc_attr( ABSPATH ); ?>">ABSPATH</span>
            </div>

            <table class="widefat striped wpfg-table" id="wpfg-file-list">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="wpfg-file-select-all" /></th>
                        <th><?php esc_html_e( 'Name', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Modified', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Permissions', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody id="wpfg-file-tbody">
                    <tr><td colspan="6"><?php esc_html_e( 'Loading...', 'wp-file-guardian' ); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="wpfg-bulk-bar">
            <select id="wpfg-file-bulk-action">
                <option value=""><?php esc_html_e( 'Bulk Actions', 'wp-file-guardian' ); ?></option>
                <option value="quarantine"><?php esc_html_e( 'Quarantine Selected', 'wp-file-guardian' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete Selected', 'wp-file-guardian' ); ?></option>
            </select>
            <button type="button" class="button" id="wpfg-file-apply-bulk"><?php esc_html_e( 'Apply', 'wp-file-guardian' ); ?></button>
        </div>
    </div>

    <!-- File Info Modal -->
    <div id="wpfg-modal" class="wpfg-modal" style="display:none;">
        <div class="wpfg-modal-content">
            <span class="wpfg-modal-close">&times;</span>
            <h3><?php esc_html_e( 'File Details', 'wp-file-guardian' ); ?></h3>
            <div id="wpfg-modal-body"></div>
        </div>
    </div>
</div>
