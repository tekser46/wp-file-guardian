<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * System information page for troubleshooting.
 */
class WPFG_System_Info {

    /**
     * Gather system information.
     */
    public static function get_info() {
        global $wpdb, $wp_version;

        $upload_dir = wp_upload_dir();
        $storage    = WPFG_Helpers::storage_dir();

        return array(
            __( 'WordPress', 'wp-file-guardian' ) => array(
                __( 'Version', 'wp-file-guardian' )        => $wp_version,
                __( 'Site URL', 'wp-file-guardian' )       => get_site_url(),
                __( 'Home URL', 'wp-file-guardian' )       => get_home_url(),
                __( 'Multisite', 'wp-file-guardian' )      => is_multisite() ? __( 'Yes', 'wp-file-guardian' ) : __( 'No', 'wp-file-guardian' ),
                __( 'ABSPATH', 'wp-file-guardian' )        => ABSPATH,
                __( 'WP Debug', 'wp-file-guardian' )       => defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'wp-file-guardian' ) : __( 'Disabled', 'wp-file-guardian' ),
                __( 'WP Cron', 'wp-file-guardian' )        => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Disabled', 'wp-file-guardian' ) : __( 'Enabled', 'wp-file-guardian' ),
                __( 'Active Theme', 'wp-file-guardian' )   => wp_get_theme()->get( 'Name' ),
                __( 'Active Plugins', 'wp-file-guardian' ) => count( get_option( 'active_plugins', array() ) ),
            ),
            __( 'Server', 'wp-file-guardian' ) => array(
                __( 'PHP Version', 'wp-file-guardian' )     => PHP_VERSION,
                __( 'Server Software', 'wp-file-guardian' ) => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( $_SERVER['SERVER_SOFTWARE'] ) : __( 'Unknown', 'wp-file-guardian' ),
                __( 'MySQL Version', 'wp-file-guardian' )   => $wpdb->db_version(),
                __( 'Max Execution Time', 'wp-file-guardian' ) => ini_get( 'max_execution_time' ) . 's',
                __( 'Memory Limit', 'wp-file-guardian' )    => ini_get( 'memory_limit' ),
                __( 'Upload Max Size', 'wp-file-guardian' ) => ini_get( 'upload_max_filesize' ),
                __( 'Post Max Size', 'wp-file-guardian' )   => ini_get( 'post_max_size' ),
                __( 'ZipArchive', 'wp-file-guardian' )      => class_exists( 'ZipArchive' ) ? __( 'Available', 'wp-file-guardian' ) : __( 'Not Available', 'wp-file-guardian' ),
                __( 'cURL', 'wp-file-guardian' )            => function_exists( 'curl_version' ) ? __( 'Available', 'wp-file-guardian' ) : __( 'Not Available', 'wp-file-guardian' ),
            ),
            __( 'Plugin Storage', 'wp-file-guardian' ) => array(
                __( 'Storage Dir', 'wp-file-guardian' )      => $storage,
                __( 'Storage Writable', 'wp-file-guardian' ) => is_writable( $storage ) ? __( 'Yes', 'wp-file-guardian' ) : __( 'No', 'wp-file-guardian' ),
                __( 'Quarantine Dir', 'wp-file-guardian' )   => $storage . '/quarantine',
                __( 'Backups Dir', 'wp-file-guardian' )      => $storage . '/backups',
                __( 'Uploads Dir', 'wp-file-guardian' )      => $upload_dir['basedir'],
                __( 'Uploads Writable', 'wp-file-guardian' ) => is_writable( $upload_dir['basedir'] ) ? __( 'Yes', 'wp-file-guardian' ) : __( 'No', 'wp-file-guardian' ),
            ),
            __( 'Plugin', 'wp-file-guardian' ) => array(
                __( 'Version', 'wp-file-guardian' )       => WPFG_VERSION,
                __( 'DB Version', 'wp-file-guardian' )    => get_option( 'wpfg_db_version', 'N/A' ),
                __( 'Debug Mode', 'wp-file-guardian' )    => WPFG_Settings::get( 'debug_mode' ) ? __( 'Enabled', 'wp-file-guardian' ) : __( 'Disabled', 'wp-file-guardian' ),
                __( 'Quarantined Files', 'wp-file-guardian' ) => WPFG_Quarantine::count(),
                __( 'Next Scan', 'wp-file-guardian' )     => self::next_scheduled( 'wpfg_scheduled_scan' ),
                __( 'Next Backup', 'wp-file-guardian' )   => self::next_scheduled( 'wpfg_scheduled_backup' ),
            ),
        );
    }

    /**
     * Get next scheduled time for an event.
     */
    private static function next_scheduled( $hook ) {
        $next = wp_next_scheduled( $hook );
        if ( ! $next ) {
            return __( 'Not Scheduled', 'wp-file-guardian' );
        }
        return wp_date( 'Y-m-d H:i:s', $next );
    }
}
