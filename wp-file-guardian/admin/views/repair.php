<?php if ( ! defined( 'ABSPATH' ) ) exit;
global $wp_version;
?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Repair & Integrity', 'wp-file-guardian' ); ?></h1>

    <!-- Core Integrity Check -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'WordPress Core Integrity', 'wp-file-guardian' ); ?></h2>
        <p>
            <?php
            printf(
                /* translators: %s: WordPress version */
                esc_html__( 'Check your core files against official WordPress %s checksums.', 'wp-file-guardian' ),
                esc_html( $wp_version )
            );
            ?>
        </p>
        <button type="button" class="button button-primary" id="wpfg-verify-core"><?php esc_html_e( 'Verify Core Files', 'wp-file-guardian' ); ?></button>
        <span id="wpfg-verify-status" class="wpfg-inline-status"></span>

        <div id="wpfg-core-results" style="display:none; margin-top:15px;">
            <h3><?php esc_html_e( 'Results', 'wp-file-guardian' ); ?></h3>
            <div id="wpfg-core-summary"></div>

            <!-- Modified Files -->
            <div id="wpfg-modified-section" style="display:none;">
                <h4><?php esc_html_e( 'Modified Files', 'wp-file-guardian' ); ?></h4>
                <table class="widefat striped wpfg-table" id="wpfg-modified-table">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="wpfg-repair-select-all" /></th>
                            <th><?php esc_html_e( 'File', 'wp-file-guardian' ); ?></th>
                            <th><?php esc_html_e( 'Expected Hash', 'wp-file-guardian' ); ?></th>
                            <th><?php esc_html_e( 'Actual Hash', 'wp-file-guardian' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpfg-modified-tbody"></tbody>
                </table>
            </div>

            <!-- Missing Files -->
            <div id="wpfg-missing-section" style="display:none;">
                <h4><?php esc_html_e( 'Missing Files', 'wp-file-guardian' ); ?></h4>
                <ul id="wpfg-missing-list"></ul>
            </div>

            <!-- Repair Actions -->
            <div id="wpfg-repair-actions" style="display:none; margin-top:15px;">
                <button type="button" class="button" id="wpfg-repair-dry-run"><?php esc_html_e( 'Preview Repair (Dry Run)', 'wp-file-guardian' ); ?></button>
                <button type="button" class="button button-primary" id="wpfg-repair-execute"><?php esc_html_e( 'Repair Selected Files', 'wp-file-guardian' ); ?></button>
                <span id="wpfg-repair-status" class="wpfg-inline-status"></span>
            </div>

            <div id="wpfg-repair-preview" style="display:none; margin-top:15px;"></div>
        </div>
    </div>

    <!-- Plugin/Theme Reinstall -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Reinstall Plugin or Theme', 'wp-file-guardian' ); ?></h2>
        <p><?php esc_html_e( 'Reinstall official WordPress.org packages. A restore point is created automatically.', 'wp-file-guardian' ); ?></p>

        <div class="wpfg-reinstall-form">
            <select id="wpfg-reinstall-type">
                <option value="plugin"><?php esc_html_e( 'Plugin', 'wp-file-guardian' ); ?></option>
                <option value="theme"><?php esc_html_e( 'Theme', 'wp-file-guardian' ); ?></option>
            </select>
            <input type="text" id="wpfg-reinstall-slug" placeholder="<?php esc_attr_e( 'Slug (e.g., akismet)', 'wp-file-guardian' ); ?>" />
            <button type="button" class="button" id="wpfg-reinstall-preview"><?php esc_html_e( 'Preview', 'wp-file-guardian' ); ?></button>
            <button type="button" class="button button-primary" id="wpfg-reinstall-execute"><?php esc_html_e( 'Reinstall', 'wp-file-guardian' ); ?></button>
            <span id="wpfg-reinstall-status" class="wpfg-inline-status"></span>
        </div>
    </div>
</div>
