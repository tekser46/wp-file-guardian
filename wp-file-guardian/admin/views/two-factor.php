<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Two-Factor Authentication', 'wp-file-guardian' ); ?></h1>

    <!-- Info Card -->
    <div class="wpfg-card wpfg-card-wide">
        <div class="wpfg-scan-header">
            <div class="wpfg-scan-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div>
                <h2><?php esc_html_e( 'Protect Your Accounts', 'wp-file-guardian' ); ?></h2>
                <p class="wpfg-scan-desc"><?php esc_html_e( 'Two-factor authentication adds an extra layer of security by requiring a verification code from your authenticator app in addition to your password.', 'wp-file-guardian' ); ?></p>
            </div>
        </div>
    </div>

    <!-- Current User Setup -->
    <?php
    $current_user = wp_get_current_user();
    $is_enabled   = WPFG_Two_Factor::is_enabled_for_user( $current_user->ID );
    $secret       = $is_enabled ? get_user_meta( $current_user->ID, 'wpfg_2fa_secret', true ) : '';
    $decrypted    = $secret ? WPFG_Two_Factor::decrypt_secret( $secret ) : '';
    $qr_uri       = $decrypted ? WPFG_Two_Factor::generate_qr_uri( $decrypted, $current_user->user_email ) : '';
    ?>
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Your 2FA Setup', 'wp-file-guardian' ); ?></h2>

        <?php if ( $is_enabled ) : ?>
            <p>
                <span class="wpfg-badge wpfg-badge-info"><?php esc_html_e( 'Enabled', 'wp-file-guardian' ); ?></span>
                <?php esc_html_e( 'Two-factor authentication is active on your account.', 'wp-file-guardian' ); ?>
            </p>

            <!-- QR Code Section -->
            <div id="wpfg-2fa-qr-section" style="display:none; margin-top:16px;">
                <h3><?php esc_html_e( 'Scan QR Code', 'wp-file-guardian' ); ?></h3>
                <p><?php esc_html_e( 'Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):', 'wp-file-guardian' ); ?></p>
                <div id="wpfg-2fa-qr" style="margin: 16px 0; padding: 16px; background: #fff; display: inline-block; border: 1px solid #ddd; border-radius: 4px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo rawurlencode( $qr_uri ); ?>" alt="<?php esc_attr_e( '2FA QR Code', 'wp-file-guardian' ); ?>" width="200" height="200" />
                </div>
                <p><strong><?php esc_html_e( 'Manual entry key:', 'wp-file-guardian' ); ?></strong> <code><?php echo esc_html( $decrypted ); ?></code></p>
            </div>

            <div style="margin-top: 12px;">
                <button type="button" class="wpfg-btn wpfg-btn-secondary" id="wpfg-2fa-show-qr">
                    <?php esc_html_e( 'Show QR Code', 'wp-file-guardian' ); ?>
                </button>
            </div>

            <!-- Verify Code -->
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee;">
                <h3><?php esc_html_e( 'Test Your Code', 'wp-file-guardian' ); ?></h3>
                <p><?php esc_html_e( 'Enter a code from your authenticator app to verify it is configured correctly:', 'wp-file-guardian' ); ?></p>
                <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
                    <input type="text" id="wpfg-2fa-test-code" class="regular-text" placeholder="<?php esc_attr_e( '000000', 'wp-file-guardian' ); ?>" maxlength="8" style="max-width: 200px; letter-spacing: 2px;" />
                    <button type="button" class="wpfg-btn wpfg-btn-primary" id="wpfg-2fa-verify">
                        <?php esc_html_e( 'Verify', 'wp-file-guardian' ); ?>
                    </button>
                    <span id="wpfg-2fa-verify-result" style="display:none;"></span>
                </div>
            </div>

            <!-- Backup Codes -->
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee;">
                <h3><?php esc_html_e( 'Backup Codes', 'wp-file-guardian' ); ?></h3>
                <p><?php esc_html_e( 'Backup codes can be used if you lose access to your authenticator app. Each code can only be used once.', 'wp-file-guardian' ); ?></p>
                <div id="wpfg-2fa-backup-codes" style="display:none; margin: 16px 0; padding: 16px; background: #f6f7f7; border-radius: 4px; font-family: monospace;">
                    <!-- Backup codes will be displayed here via AJAX -->
                </div>
                <button type="button" class="wpfg-btn wpfg-btn-secondary" id="wpfg-2fa-regen-backup">
                    <?php esc_html_e( 'Generate New Backup Codes', 'wp-file-guardian' ); ?>
                </button>
                <p class="description" style="margin-top: 6px;"><?php esc_html_e( 'Warning: Generating new codes will invalidate all existing backup codes.', 'wp-file-guardian' ); ?></p>
            </div>

        <?php else : ?>
            <p>
                <span class="wpfg-badge wpfg-badge-warning"><?php esc_html_e( 'Disabled', 'wp-file-guardian' ); ?></span>
                <?php esc_html_e( 'Two-factor authentication is not enabled on your account.', 'wp-file-guardian' ); ?>
            </p>
            <div id="wpfg-2fa-setup-section" style="margin-top: 16px;">
                <button type="button" class="wpfg-btn wpfg-btn-primary" id="wpfg-2fa-enable">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <?php esc_html_e( 'Enable 2FA', 'wp-file-guardian' ); ?>
                </button>
                <span id="wpfg-2fa-enable-status" style="display:none; margin-left: 8px;"></span>
            </div>
            <!-- QR code will appear here after enabling -->
            <div id="wpfg-2fa-new-setup" style="display:none; margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee;">
                <h3><?php esc_html_e( 'Scan QR Code', 'wp-file-guardian' ); ?></h3>
                <p><?php esc_html_e( 'Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.):', 'wp-file-guardian' ); ?></p>
                <div id="wpfg-2fa-new-qr" style="margin: 16px 0; padding: 16px; background: #fff; display: inline-block; border: 1px solid #ddd; border-radius: 4px;"></div>
                <p><strong><?php esc_html_e( 'Manual entry key:', 'wp-file-guardian' ); ?></strong> <code id="wpfg-2fa-new-secret"></code></p>
                <div style="margin-top: 16px;">
                    <h3><?php esc_html_e( 'Backup Codes', 'wp-file-guardian' ); ?></h3>
                    <p><?php esc_html_e( 'Save these codes in a safe place. They can be used if you lose your authenticator app:', 'wp-file-guardian' ); ?></p>
                    <div id="wpfg-2fa-new-backup" style="margin: 8px 0; padding: 16px; background: #f6f7f7; border-radius: 4px; font-family: monospace;"></div>
                </div>
                <p class="description"><?php esc_html_e( 'Reload the page after setting up your authenticator app to verify your configuration.', 'wp-file-guardian' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Users Table -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'User 2FA Status', 'wp-file-guardian' ); ?></h2>

        <?php if ( ! empty( $users_status ) ) : ?>
        <div class="wpfg-table-container">
            <table class="wpfg-modern-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Username', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Role', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( '2FA Status', 'wp-file-guardian' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'wp-file-guardian' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $users_status as $user_info ) : ?>
                    <tr>
                        <td>
                            <?php echo get_avatar( $user_info['user']->ID, 24 ); ?>
                            <strong><?php echo esc_html( $user_info['user']->user_login ); ?></strong>
                        </td>
                        <td><?php echo esc_html( $user_info['user']->user_email ); ?></td>
                        <td>
                            <?php
                            $roles = $user_info['user']->roles;
                            echo esc_html( ! empty( $roles ) ? ucfirst( $roles[0] ) : __( 'None', 'wp-file-guardian' ) );
                            ?>
                        </td>
                        <td>
                            <?php if ( $user_info['enabled'] ) : ?>
                                <span class="wpfg-badge wpfg-badge-info"><?php esc_html_e( 'Enabled', 'wp-file-guardian' ); ?></span>
                            <?php else : ?>
                                <span class="wpfg-badge wpfg-badge-critical"><?php esc_html_e( 'Disabled', 'wp-file-guardian' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $user_info['enabled'] && current_user_can( 'edit_users' ) && $user_info['user']->ID !== $current_user->ID ) : ?>
                                <button type="button" class="wpfg-btn wpfg-btn-ghost wpfg-btn-sm wpfg-2fa-disable-user" data-user-id="<?php echo esc_attr( $user_info['user']->ID ); ?>">
                                    <?php esc_html_e( 'Disable 2FA', 'wp-file-guardian' ); ?>
                                </button>
                            <?php elseif ( ! $user_info['enabled'] ) : ?>
                                <span style="color:#666;"><?php esc_html_e( 'User must enable', 'wp-file-guardian' ); ?></span>
                            <?php else : ?>
                                <span style="color:#666;"><?php esc_html_e( 'Your account', 'wp-file-guardian' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else : ?>
            <p><?php esc_html_e( 'No users found.', 'wp-file-guardian' ); ?></p>
        <?php endif; ?>
    </div>

    <!-- Settings Info -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( '2FA Settings', 'wp-file-guardian' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Force for Administrators', 'wp-file-guardian' ); ?></th>
                <td>
                    <p class="description">
                        <?php esc_html_e( 'It is strongly recommended that all administrator accounts enable two-factor authentication. Consider requiring 2FA for all admin users to protect against unauthorized access.', 'wp-file-guardian' ); ?>
                    </p>
                    <?php
                    $admin_users = get_users( array( 'role' => 'administrator' ) );
                    $admins_without_2fa = 0;
                    foreach ( $admin_users as $admin ) {
                        if ( ! WPFG_Two_Factor::is_enabled_for_user( $admin->ID ) ) {
                            $admins_without_2fa++;
                        }
                    }
                    if ( $admins_without_2fa > 0 ) :
                    ?>
                        <p style="margin-top: 8px;">
                            <span class="wpfg-badge wpfg-badge-warning">
                                <?php printf( esc_html__( '%d admin(s) without 2FA', 'wp-file-guardian' ), $admins_without_2fa ); ?>
                            </span>
                        </p>
                    <?php else : ?>
                        <p style="margin-top: 8px;">
                            <span class="wpfg-badge wpfg-badge-info">
                                <?php esc_html_e( 'All administrators have 2FA enabled', 'wp-file-guardian' ); ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle QR code visibility.
    var showQrBtn = document.getElementById('wpfg-2fa-show-qr');
    if (showQrBtn) {
        showQrBtn.addEventListener('click', function() {
            var section = document.getElementById('wpfg-2fa-qr-section');
            if (section.style.display === 'none') {
                section.style.display = 'block';
                showQrBtn.textContent = '<?php echo esc_js( __( 'Hide QR Code', 'wp-file-guardian' ) ); ?>';
            } else {
                section.style.display = 'none';
                showQrBtn.textContent = '<?php echo esc_js( __( 'Show QR Code', 'wp-file-guardian' ) ); ?>';
            }
        });
    }

    // Enable 2FA button.
    var enableBtn = document.getElementById('wpfg-2fa-enable');
    if (enableBtn) {
        enableBtn.addEventListener('click', function() {
            enableBtn.disabled = true;
            enableBtn.textContent = '<?php echo esc_js( __( 'Setting up...', 'wp-file-guardian' ) ); ?>';

            jQuery.post(ajaxurl, {
                action: 'wpfg_2fa_generate_secret',
                nonce: wpfg.nonce
            }, function(resp) {
                if (resp.success) {
                    var d = resp.data;
                    document.getElementById('wpfg-2fa-new-secret').textContent = d.secret;
                    document.getElementById('wpfg-2fa-new-qr').innerHTML =
                        '<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' +
                        encodeURIComponent(d.qr_uri) + '" width="200" height="200" alt="QR Code" />';

                    if (d.backup_codes && d.backup_codes.length) {
                        document.getElementById('wpfg-2fa-new-backup').innerHTML =
                            d.backup_codes.map(function(c) { return '<div>' + c + '</div>'; }).join('');
                    }

                    document.getElementById('wpfg-2fa-setup-section').style.display = 'none';
                    document.getElementById('wpfg-2fa-new-setup').style.display = 'block';
                } else {
                    alert(resp.data ? resp.data.message : '<?php echo esc_js( __( 'An error occurred.', 'wp-file-guardian' ) ); ?>');
                    enableBtn.disabled = false;
                    enableBtn.textContent = '<?php echo esc_js( __( 'Enable 2FA', 'wp-file-guardian' ) ); ?>';
                }
            });
        });
    }
});
</script>
