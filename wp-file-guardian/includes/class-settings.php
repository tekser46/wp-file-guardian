<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugin settings management.
 */
class WPFG_Settings {

    /**
     * Return default settings.
     */
    public static function defaults() {
        return array(
            'capability'             => 'manage_options',
            'scan_paths'             => array( 'wp-content', 'wp-includes', 'wp-admin' ),
            'excluded_paths'         => array( 'wp-content/uploads/wpfg' ),
            'excluded_extensions'    => array( 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'mp4', 'mp3', 'avi', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'woff', 'woff2', 'ttf', 'eot' ),
            'scan_sensitivity'       => 'medium', // low, medium, high
            'quarantine_first'       => true,
            'backup_before_action'   => true,
            'backup_location'        => '', // empty = default
            'scheduled_scan'         => 'daily', // off, hourly, twicedaily, daily, weekly
            'scheduled_backup'       => 'weekly', // off, daily, weekly, monthly
            'backup_retention'       => 5, // number of backups to keep
            'email_notifications'    => true,
            'notification_email'     => '', // empty = admin email
            'debug_mode'             => false,
            'max_file_size_scan'     => 10485760, // 10 MB
            'batch_size'             => 500,
            'large_file_threshold'   => 5242880, // 5 MB
            // v2 settings.
            'login_protection'       => true,
            'login_max_attempts'     => 5,
            'login_lockout_minutes'  => 30,
            'remote_backup_type'     => 'none', // none, ftp, s3, gdrive, custom
            'ftp_host'               => '',
            'ftp_user'               => '',
            'ftp_pass'               => '',
            'ftp_port'               => 21,
            'ftp_dir'                => '/',
            'ftp_ssl'                => false,
            's3_access_key'          => '',
            's3_secret_key'          => '',
            's3_bucket'              => '',
            's3_region'              => 'eu-central-1',
            's3_prefix'              => 'wpfg-backups/',
            'remote_custom_dir'      => '',
            'slack_webhook_url'      => '',
            'telegram_bot_token'     => '',
            'telegram_chat_id'       => '',
            'auto_quarantine_critical' => false,
            'realtime_email_critical'  => true,
            // v3: Hardening.
            'hardening_disable_file_editor' => false,
            'hardening_disable_xmlrpc'      => false,
            'hardening_security_headers'    => true,
            'hardening_block_php_uploads'   => false,
            'hardening_disable_rest_unauth' => false,
            'hardening_hide_wp_version'     => false,
            'hardening_x_frame_options'     => 'SAMEORIGIN',
            'hardening_referrer_policy'     => 'strict-origin-when-cross-origin',
            // v3: Firewall.
            'firewall_enabled'              => false,
            'firewall_rate_limit'           => 60,
            'firewall_rate_window'          => 60,
            'firewall_auto_ban'             => true,
            'firewall_auto_ban_threshold'   => 10,
            'firewall_auto_ban_duration'    => 1440,
            'firewall_log_retention_days'   => 30,
            'firewall_geoip_enabled'        => false,
            'firewall_blocked_countries'    => array(),
            // v3: Vulnerability Scanner.
            'vuln_scan_schedule'            => 'daily',
            'wpscan_api_token'              => '',
            // v3: Security Score.
            'weekly_summary_enabled'        => true,
            'weekly_summary_day'            => 1,
            // v3: Two-Factor Auth.
            'two_factor_enabled'            => false,
            'two_factor_force_admins'       => false,
            'two_factor_remember_days'      => 30,
        );
    }

    /**
     * Get a specific setting value.
     */
    public static function get( $key, $default = null ) {
        $settings = get_option( 'wpfg_settings', self::defaults() );
        $defaults = self::defaults();
        if ( null === $default && isset( $defaults[ $key ] ) ) {
            $default = $defaults[ $key ];
        }
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update a specific setting.
     */
    public static function set( $key, $value ) {
        $settings         = get_option( 'wpfg_settings', self::defaults() );
        $settings[ $key ] = $value;
        update_option( 'wpfg_settings', $settings );
    }

    /**
     * Save all settings at once.
     */
    public static function save( $new_settings ) {
        $defaults = self::defaults();
        $clean    = array();
        foreach ( $defaults as $key => $default ) {
            if ( isset( $new_settings[ $key ] ) ) {
                $clean[ $key ] = self::sanitize_setting( $key, $new_settings[ $key ] );
            } else {
                // Checkboxes may not be present if unchecked.
                if ( is_bool( $default ) ) {
                    $clean[ $key ] = false;
                } else {
                    $clean[ $key ] = $default;
                }
            }
        }
        update_option( 'wpfg_settings', $clean );
        return $clean;
    }

    /**
     * Sanitize individual settings by key.
     */
    private static function sanitize_setting( $key, $value ) {
        switch ( $key ) {
            case 'capability':
                $allowed = array( 'manage_options', 'manage_network', 'edit_plugins' );
                return in_array( $value, $allowed, true ) ? $value : 'manage_options';

            case 'scan_paths':
            case 'excluded_paths':
                if ( is_string( $value ) ) {
                    $value = array_filter( array_map( 'trim', explode( "\n", $value ) ) );
                }
                return array_map( 'sanitize_text_field', (array) $value );

            case 'excluded_extensions':
                if ( is_string( $value ) ) {
                    $value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
                }
                return array_map( 'sanitize_text_field', (array) $value );

            case 'scan_sensitivity':
                return in_array( $value, array( 'low', 'medium', 'high' ), true ) ? $value : 'medium';

            case 'quarantine_first':
            case 'backup_before_action':
            case 'email_notifications':
            case 'debug_mode':
            case 'login_protection':
            case 'ftp_ssl':
            case 'auto_quarantine_critical':
            case 'realtime_email_critical':
            case 'hardening_disable_file_editor':
            case 'hardening_disable_xmlrpc':
            case 'hardening_security_headers':
            case 'hardening_block_php_uploads':
            case 'hardening_disable_rest_unauth':
            case 'hardening_hide_wp_version':
            case 'firewall_enabled':
            case 'firewall_auto_ban':
            case 'firewall_geoip_enabled':
            case 'weekly_summary_enabled':
            case 'two_factor_enabled':
            case 'two_factor_force_admins':
                return (bool) $value;

            case 'scheduled_scan':
                return in_array( $value, array( 'off', 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ? $value : 'daily';

            case 'scheduled_backup':
                return in_array( $value, array( 'off', 'daily', 'weekly', 'monthly' ), true ) ? $value : 'weekly';

            case 'backup_retention':
                return max( 1, min( 50, absint( $value ) ) );

            case 'notification_email':
                return sanitize_email( $value );

            case 'backup_location':
                return sanitize_text_field( $value );

            case 'max_file_size_scan':
            case 'batch_size':
            case 'large_file_threshold':
            case 'login_max_attempts':
            case 'login_lockout_minutes':
            case 'ftp_port':
            case 'firewall_rate_limit':
            case 'firewall_rate_window':
            case 'firewall_auto_ban_threshold':
            case 'firewall_auto_ban_duration':
            case 'firewall_log_retention_days':
            case 'weekly_summary_day':
            case 'two_factor_remember_days':
                return absint( $value );

            case 'remote_backup_type':
                return in_array( $value, array( 'none', 'ftp', 's3', 'gdrive', 'custom' ), true ) ? $value : 'none';

            case 'slack_webhook_url':
                return esc_url_raw( $value );

            case 'ftp_host':
            case 'ftp_user':
            case 'ftp_dir':
            case 's3_access_key':
            case 's3_bucket':
            case 's3_region':
            case 's3_prefix':
            case 'remote_custom_dir':
            case 'telegram_bot_token':
            case 'telegram_chat_id':
                return sanitize_text_field( $value );

            case 'ftp_pass':
            case 's3_secret_key':
                // Don't sanitize passwords too aggressively.
                return wp_unslash( $value );

            case 'hardening_x_frame_options':
                return in_array( $value, array( 'DENY', 'SAMEORIGIN', 'ALLOW-FROM' ), true ) ? $value : 'SAMEORIGIN';

            case 'hardening_referrer_policy':
                return in_array( $value, array( 'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url' ), true ) ? $value : 'strict-origin-when-cross-origin';

            case 'firewall_blocked_countries':
                if ( is_string( $value ) ) {
                    $value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
                }
                return array_map( 'sanitize_text_field', (array) $value );

            case 'vuln_scan_schedule':
                return in_array( $value, array( 'off', 'hourly', 'daily', 'weekly' ), true ) ? $value : 'daily';

            case 'wpscan_api_token':
                return sanitize_text_field( $value );

            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Reset settings to defaults.
     */
    public static function reset() {
        update_option( 'wpfg_settings', self::defaults() );
    }

    /**
     * Export settings as JSON.
     */
    public static function export() {
        return wp_json_encode( get_option( 'wpfg_settings', self::defaults() ), JSON_PRETTY_PRINT );
    }

    /**
     * Import settings from JSON string.
     */
    public static function import( $json ) {
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return false;
        }
        return self::save( $data );
    }
}
