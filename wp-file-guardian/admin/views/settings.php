<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wpfg-wrap">
    <h1><?php esc_html_e( 'Settings', 'wp-file-guardian' ); ?></h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'wp-file-guardian' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $message ) : ?>
        <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'wpfg_settings_save' ); ?>

        <!-- Scanning -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Scanning', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="wpfg-scan-paths"><?php esc_html_e( 'Scan Paths', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <textarea name="wpfg[scan_paths]" id="wpfg-scan-paths" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", (array) ( $settings['scan_paths'] ?? array() ) ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One path per line, relative to ABSPATH.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-excluded-paths"><?php esc_html_e( 'Excluded Paths', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <textarea name="wpfg[excluded_paths]" id="wpfg-excluded-paths" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", (array) ( $settings['excluded_paths'] ?? array() ) ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One path per line, relative to ABSPATH.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-excluded-ext"><?php esc_html_e( 'Excluded Extensions', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="text" name="wpfg[excluded_extensions]" id="wpfg-excluded-ext" value="<?php echo esc_attr( implode( ', ', (array) ( $settings['excluded_extensions'] ?? array() ) ) ); ?>" class="large-text" />
                        <p class="description"><?php esc_html_e( 'Comma-separated list.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-sensitivity"><?php esc_html_e( 'Pattern Sensitivity', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <select name="wpfg[scan_sensitivity]" id="wpfg-sensitivity">
                            <option value="low" <?php selected( $settings['scan_sensitivity'] ?? '', 'low' ); ?>><?php esc_html_e( 'Low', 'wp-file-guardian' ); ?></option>
                            <option value="medium" <?php selected( $settings['scan_sensitivity'] ?? '', 'medium' ); ?>><?php esc_html_e( 'Medium', 'wp-file-guardian' ); ?></option>
                            <option value="high" <?php selected( $settings['scan_sensitivity'] ?? '', 'high' ); ?>><?php esc_html_e( 'High', 'wp-file-guardian' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-batch-size"><?php esc_html_e( 'Batch Size', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="number" name="wpfg[batch_size]" id="wpfg-batch-size" value="<?php echo esc_attr( $settings['batch_size'] ?? 500 ); ?>" min="50" max="5000" />
                        <p class="description"><?php esc_html_e( 'Files per batch during scan.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-max-file-size"><?php esc_html_e( 'Max File Size for Scan (bytes)', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="number" name="wpfg[max_file_size_scan]" id="wpfg-max-file-size" value="<?php echo esc_attr( $settings['max_file_size_scan'] ?? 10485760 ); ?>" min="1048576" />
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-scheduled-scan"><?php esc_html_e( 'Scheduled Scan', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <select name="wpfg[scheduled_scan]" id="wpfg-scheduled-scan">
                            <option value="off" <?php selected( $settings['scheduled_scan'] ?? '', 'off' ); ?>><?php esc_html_e( 'Off', 'wp-file-guardian' ); ?></option>
                            <option value="hourly" <?php selected( $settings['scheduled_scan'] ?? '', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'wp-file-guardian' ); ?></option>
                            <option value="twicedaily" <?php selected( $settings['scheduled_scan'] ?? '', 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'wp-file-guardian' ); ?></option>
                            <option value="daily" <?php selected( $settings['scheduled_scan'] ?? '', 'daily' ); ?>><?php esc_html_e( 'Daily', 'wp-file-guardian' ); ?></option>
                            <option value="weekly" <?php selected( $settings['scheduled_scan'] ?? '', 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'wp-file-guardian' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Safety & Quarantine -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Safety & Quarantine', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Quarantine First', 'wp-file-guardian' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="wpfg[quarantine_first]" value="1" <?php checked( $settings['quarantine_first'] ?? false ); ?> />
                        <?php esc_html_e( 'Move files to quarantine instead of immediate deletion.', 'wp-file-guardian' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Backup Before Action', 'wp-file-guardian' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="wpfg[backup_before_action]" value="1" <?php checked( $settings['backup_before_action'] ?? false ); ?> />
                        <?php esc_html_e( 'Create a restore point before destructive operations.', 'wp-file-guardian' ); ?></label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Backups -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Backups', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="wpfg-backup-location"><?php esc_html_e( 'Backup Storage Path', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="text" name="wpfg[backup_location]" id="wpfg-backup-location" value="<?php echo esc_attr( $settings['backup_location'] ?? '' ); ?>" class="large-text" />
                        <p class="description"><?php esc_html_e( 'Leave blank for default (wp-content/uploads/wpfg/backups).', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-scheduled-backup"><?php esc_html_e( 'Scheduled Backup', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <select name="wpfg[scheduled_backup]" id="wpfg-scheduled-backup">
                            <option value="off" <?php selected( $settings['scheduled_backup'] ?? '', 'off' ); ?>><?php esc_html_e( 'Off', 'wp-file-guardian' ); ?></option>
                            <option value="daily" <?php selected( $settings['scheduled_backup'] ?? '', 'daily' ); ?>><?php esc_html_e( 'Daily', 'wp-file-guardian' ); ?></option>
                            <option value="weekly" <?php selected( $settings['scheduled_backup'] ?? '', 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'wp-file-guardian' ); ?></option>
                            <option value="monthly" <?php selected( $settings['scheduled_backup'] ?? '', 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'wp-file-guardian' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-retention"><?php esc_html_e( 'Backup Retention', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="number" name="wpfg[backup_retention]" id="wpfg-retention" value="<?php echo esc_attr( $settings['backup_retention'] ?? 5 ); ?>" min="1" max="50" />
                        <p class="description"><?php esc_html_e( 'Number of backups to keep.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Login Protection -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Login Protection', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Login Guard', 'wp-file-guardian' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="wpfg[login_protection]" value="1" <?php checked( $settings['login_protection'] ?? true ); ?> />
                        <?php esc_html_e( 'Enable brute-force protection and login logging.', 'wp-file-guardian' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-max-attempts"><?php esc_html_e( 'Max Failed Attempts', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="number" name="wpfg[login_max_attempts]" id="wpfg-max-attempts" value="<?php echo esc_attr( $settings['login_max_attempts'] ?? 5 ); ?>" min="1" max="50" />
                        <p class="description"><?php esc_html_e( 'Lock out IP after this many failed attempts.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-lockout-mins"><?php esc_html_e( 'Lockout Duration (minutes)', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="number" name="wpfg[login_lockout_minutes]" id="wpfg-lockout-mins" value="<?php echo esc_attr( $settings['login_lockout_minutes'] ?? 30 ); ?>" min="1" max="1440" />
                    </td>
                </tr>
            </table>
        </div>

        <!-- Notifications -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Notifications', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Email Notifications', 'wp-file-guardian' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="wpfg[email_notifications]" value="1" <?php checked( $settings['email_notifications'] ?? false ); ?> />
                        <?php esc_html_e( 'Send email alerts for critical events.', 'wp-file-guardian' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-email"><?php esc_html_e( 'Notification Email', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="email" name="wpfg[notification_email]" id="wpfg-email" value="<?php echo esc_attr( $settings['notification_email'] ?? '' ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Leave blank to use admin email.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-slack-url"><?php esc_html_e( 'Slack Webhook URL', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="url" name="wpfg[slack_webhook_url]" id="wpfg-slack-url" value="<?php echo esc_attr( $settings['slack_webhook_url'] ?? '' ); ?>" class="large-text" />
                        <p class="description"><?php esc_html_e( 'Leave blank to disable Slack notifications.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-tg-token"><?php esc_html_e( 'Telegram Bot Token', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="text" name="wpfg[telegram_bot_token]" id="wpfg-tg-token" value="<?php echo esc_attr( $settings['telegram_bot_token'] ?? '' ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="wpfg-tg-chat"><?php esc_html_e( 'Telegram Chat ID', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="text" name="wpfg[telegram_chat_id]" id="wpfg-tg-chat" value="<?php echo esc_attr( $settings['telegram_chat_id'] ?? '' ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Both token and chat ID are needed for Telegram alerts.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Remote Backup -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Remote Backup', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="wpfg-remote-type"><?php esc_html_e( 'Remote Storage', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <select name="wpfg[remote_backup_type]" id="wpfg-remote-type">
                            <option value="none" <?php selected( $settings['remote_backup_type'] ?? 'none', 'none' ); ?>><?php esc_html_e( 'None (local only)', 'wp-file-guardian' ); ?></option>
                            <option value="ftp" <?php selected( $settings['remote_backup_type'] ?? '', 'ftp' ); ?>><?php esc_html_e( 'FTP / FTPS', 'wp-file-guardian' ); ?></option>
                            <option value="s3" <?php selected( $settings['remote_backup_type'] ?? '', 's3' ); ?>><?php esc_html_e( 'Amazon S3', 'wp-file-guardian' ); ?></option>
                            <option value="custom" <?php selected( $settings['remote_backup_type'] ?? '', 'custom' ); ?>><?php esc_html_e( 'Custom Directory', 'wp-file-guardian' ); ?></option>
                        </select>
                    </td>
                </tr>
                <!-- FTP Settings -->
                <tr class="wpfg-remote-ftp">
                    <th><label><?php esc_html_e( 'FTP Host', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[ftp_host]" value="<?php echo esc_attr( $settings['ftp_host'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-ftp">
                    <th><label><?php esc_html_e( 'FTP User', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[ftp_user]" value="<?php echo esc_attr( $settings['ftp_user'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-ftp">
                    <th><label><?php esc_html_e( 'FTP Password', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="password" name="wpfg[ftp_pass]" value="<?php echo esc_attr( $settings['ftp_pass'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-ftp">
                    <th><label><?php esc_html_e( 'FTP Port', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="number" name="wpfg[ftp_port]" value="<?php echo esc_attr( $settings['ftp_port'] ?? 21 ); ?>" min="1" max="65535" /></td>
                </tr>
                <tr class="wpfg-remote-ftp">
                    <th><label><?php esc_html_e( 'FTP Directory', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[ftp_dir]" value="<?php echo esc_attr( $settings['ftp_dir'] ?? '/' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-ftp">
                    <th><?php esc_html_e( 'Use FTPS (SSL)', 'wp-file-guardian' ); ?></th>
                    <td><label><input type="checkbox" name="wpfg[ftp_ssl]" value="1" <?php checked( $settings['ftp_ssl'] ?? false ); ?> /> <?php esc_html_e( 'Enable SSL encryption', 'wp-file-guardian' ); ?></label></td>
                </tr>
                <!-- S3 Settings -->
                <tr class="wpfg-remote-s3">
                    <th><label><?php esc_html_e( 'S3 Access Key', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[s3_access_key]" value="<?php echo esc_attr( $settings['s3_access_key'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-s3">
                    <th><label><?php esc_html_e( 'S3 Secret Key', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="password" name="wpfg[s3_secret_key]" value="<?php echo esc_attr( $settings['s3_secret_key'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-s3">
                    <th><label><?php esc_html_e( 'S3 Bucket', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[s3_bucket]" value="<?php echo esc_attr( $settings['s3_bucket'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-s3">
                    <th><label><?php esc_html_e( 'S3 Region', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[s3_region]" value="<?php echo esc_attr( $settings['s3_region'] ?? 'eu-central-1' ); ?>" class="regular-text" /></td>
                </tr>
                <tr class="wpfg-remote-s3">
                    <th><label><?php esc_html_e( 'S3 Prefix', 'wp-file-guardian' ); ?></label></th>
                    <td><input type="text" name="wpfg[s3_prefix]" value="<?php echo esc_attr( $settings['s3_prefix'] ?? 'wpfg-backups/' ); ?>" class="regular-text" /></td>
                </tr>
                <!-- Custom Directory -->
                <tr class="wpfg-remote-custom">
                    <th><label><?php esc_html_e( 'Custom Directory Path', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <input type="text" name="wpfg[remote_custom_dir]" value="<?php echo esc_attr( $settings['remote_custom_dir'] ?? '' ); ?>" class="large-text" />
                        <p class="description"><?php esc_html_e( 'Absolute server path for backup copies.', 'wp-file-guardian' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Access & Debug -->
        <div class="wpfg-card">
            <h2><?php esc_html_e( 'Access & Debug', 'wp-file-guardian' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="wpfg-capability"><?php esc_html_e( 'Required Capability', 'wp-file-guardian' ); ?></label></th>
                    <td>
                        <select name="wpfg[capability]" id="wpfg-capability">
                            <option value="manage_options" <?php selected( $settings['capability'] ?? '', 'manage_options' ); ?>>manage_options</option>
                            <option value="manage_network" <?php selected( $settings['capability'] ?? '', 'manage_network' ); ?>>manage_network</option>
                            <option value="edit_plugins" <?php selected( $settings['capability'] ?? '', 'edit_plugins' ); ?>>edit_plugins</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Debug Mode', 'wp-file-guardian' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="wpfg[debug_mode]" value="1" <?php checked( $settings['debug_mode'] ?? false ); ?> />
                        <?php esc_html_e( 'Enable verbose logging for troubleshooting.', 'wp-file-guardian' ); ?></label>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="wpfg_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'wp-file-guardian' ); ?>" />
            <input type="submit" name="wpfg_reset_settings" class="button" value="<?php esc_attr_e( 'Reset to Defaults', 'wp-file-guardian' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Reset all settings to defaults?', 'wp-file-guardian' ); ?>');" />
        </p>
    </form>

    <!-- Import / Export -->
    <div class="wpfg-card">
        <h2><?php esc_html_e( 'Import / Export Settings', 'wp-file-guardian' ); ?></h2>
        <div class="wpfg-import-export">
            <button type="button" class="button" id="wpfg-export-settings"><?php esc_html_e( 'Export Settings (JSON)', 'wp-file-guardian' ); ?></button>

            <form method="post" style="margin-top:10px;">
                <?php wp_nonce_field( 'wpfg_settings_save' ); ?>
                <textarea name="wpfg_import_json" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'Paste JSON here...', 'wp-file-guardian' ); ?>"></textarea>
                <input type="submit" name="wpfg_import_settings" class="button" value="<?php esc_attr_e( 'Import Settings', 'wp-file-guardian' ); ?>" />
            </form>
        </div>
    </div>
</div>
