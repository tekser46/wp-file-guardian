<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Security Hardening', 'wp-file-guardian' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Enable or disable hardening measures to strengthen your WordPress installation.', 'wp-file-guardian' ); ?></p>

    <?php
    $items = array(
        'hardening_disable_file_editor' => array(
            'title'       => __( 'Disable File Editor', 'wp-file-guardian' ),
            'description' => __( 'Disables the built-in WordPress theme and plugin file editor to prevent code changes from the admin area.', 'wp-file-guardian' ),
        ),
        'hardening_disable_xmlrpc' => array(
            'title'       => __( 'Disable XML-RPC', 'wp-file-guardian' ),
            'description' => __( 'Disables XML-RPC and removes the X-Pingback header. Prevents brute-force and DDoS attacks via XML-RPC.', 'wp-file-guardian' ),
        ),
        'hardening_security_headers' => array(
            'title'       => __( 'Security Headers', 'wp-file-guardian' ),
            'description' => __( 'Adds X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, and Permissions-Policy headers.', 'wp-file-guardian' ),
        ),
        'hardening_block_php_uploads' => array(
            'title'       => __( 'Block PHP in Uploads', 'wp-file-guardian' ),
            'description' => __( 'Adds an .htaccess rule to prevent PHP execution inside the uploads directory.', 'wp-file-guardian' ),
        ),
        'hardening_disable_rest_unauth' => array(
            'title'       => __( 'Restrict REST API', 'wp-file-guardian' ),
            'description' => __( 'Blocks REST API access for unauthenticated visitors. Logged-in users are unaffected.', 'wp-file-guardian' ),
        ),
        'hardening_hide_wp_version' => array(
            'title'       => __( 'Hide WordPress Version', 'wp-file-guardian' ),
            'description' => __( 'Removes the WordPress version from the page source, RSS feeds, and enqueued asset URLs.', 'wp-file-guardian' ),
        ),
    );

    foreach ( $items as $key => $item ) :
        $is_enabled = isset( $status[ $key ] ) && ! empty( $status[ $key ]['enabled'] );
    ?>
    <div class="wpfg-card wpfg-hardening-item" data-key="<?php echo esc_attr( $key ); ?>">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0 0 4px;"><?php echo esc_html( $item['title'] ); ?></h3>
                <p class="description" style="margin:0;"><?php echo esc_html( $item['description'] ); ?></p>
            </div>
            <div style="text-align:right; white-space:nowrap; margin-left:20px;">
                <?php if ( $is_enabled ) : ?>
                    <span class="wpfg-hardening-status wpfg-badge wpfg-badge-info" style="margin-right:8px;"><?php esc_html_e( 'Active', 'wp-file-guardian' ); ?></span>
                    <button class="button wpfg-hardening-toggle" data-key="<?php echo esc_attr( $key ); ?>" data-action="disable"><?php esc_html_e( 'Disable', 'wp-file-guardian' ); ?></button>
                <?php else : ?>
                    <span class="wpfg-hardening-status wpfg-badge" style="background:#ccc; color:#555; margin-right:8px;"><?php esc_html_e( 'Inactive', 'wp-file-guardian' ); ?></span>
                    <button class="button button-primary wpfg-hardening-toggle" data-key="<?php echo esc_attr( $key ); ?>" data-action="enable"><?php esc_html_e( 'Enable', 'wp-file-guardian' ); ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Test Status -->
    <div class="wpfg-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0 0 4px;"><?php esc_html_e( 'Test Server Status', 'wp-file-guardian' ); ?></h3>
                <p class="description" style="margin:0;"><?php esc_html_e( 'Verify that the hardening measures are actually active on the server, not just saved in settings.', 'wp-file-guardian' ); ?></p>
            </div>
            <div>
                <button class="button button-secondary" id="wpfg-hardening-test"><?php esc_html_e( 'Test Status', 'wp-file-guardian' ); ?></button>
            </div>
        </div>
        <div id="wpfg-hardening-test-results" style="display:none; margin-top:12px;">
            <table class="widefat striped wpfg-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Measure', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Setting', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Server State', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Match', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    // Toggle hardening setting.
    $('.wpfg-hardening-toggle').on('click', function() {
        var $btn  = $(this);
        var key   = $btn.data('key');
        var action = $btn.data('action'); // 'enable' or 'disable'
        var value = action === 'enable' ? 1 : 0;

        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Saving...', 'wp-file-guardian' ) ); ?>');

        $.post(ajaxurl, {
            action: 'wpfg_hardening_toggle',
            key: key,
            value: value,
            _wpnonce: '<?php echo wp_create_nonce( 'wpfg_hardening' ); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data || '<?php echo esc_js( __( 'An error occurred.', 'wp-file-guardian' ) ); ?>');
                $btn.prop('disabled', false).text(action === 'enable' ? '<?php echo esc_js( __( 'Enable', 'wp-file-guardian' ) ); ?>' : '<?php echo esc_js( __( 'Disable', 'wp-file-guardian' ) ); ?>');
            }
        }).fail(function() {
            alert('<?php echo esc_js( __( 'Request failed.', 'wp-file-guardian' ) ); ?>');
            $btn.prop('disabled', false);
        });
    });

    // Test status.
    $('#wpfg-hardening-test').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Testing...', 'wp-file-guardian' ) ); ?>');

        $.post(ajaxurl, {
            action: 'wpfg_hardening_test',
            _wpnonce: '<?php echo wp_create_nonce( 'wpfg_hardening' ); ?>'
        }, function(response) {
            if (response.success) {
                var $tbody = $('#wpfg-hardening-test-results tbody').empty();
                var labels = <?php echo wp_json_encode( wp_list_pluck( $items, 'title' ) ); ?>;

                $.each(response.data, function(key, info) {
                    var settingLabel = info.setting ? '<?php echo esc_js( __( 'Enabled', 'wp-file-guardian' ) ); ?>' : '<?php echo esc_js( __( 'Disabled', 'wp-file-guardian' ) ); ?>';
                    var actualLabel  = info.actual  ? '<?php echo esc_js( __( 'Active', 'wp-file-guardian' ) ); ?>'   : '<?php echo esc_js( __( 'Inactive', 'wp-file-guardian' ) ); ?>';
                    var matchIcon    = info.match   ? '<span style="color:#00a32a;">&#10003;</span>' : '<span style="color:#d63638;">&#10007;</span>';

                    $tbody.append(
                        '<tr>' +
                            '<td>' + (labels[key] || key) + '</td>' +
                            '<td>' + settingLabel + '</td>' +
                            '<td>' + actualLabel + '</td>' +
                            '<td>' + matchIcon + '</td>' +
                        '</tr>'
                    );
                });

                $('#wpfg-hardening-test-results').show();
            } else {
                alert(response.data || '<?php echo esc_js( __( 'Test failed.', 'wp-file-guardian' ) ); ?>');
            }
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Status', 'wp-file-guardian' ) ); ?>');
        }).fail(function() {
            alert('<?php echo esc_js( __( 'Request failed.', 'wp-file-guardian' ) ); ?>');
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Test Status', 'wp-file-guardian' ) ); ?>');
        });
    });
});
</script>
