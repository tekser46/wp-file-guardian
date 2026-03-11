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
