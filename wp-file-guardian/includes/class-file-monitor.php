<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * File change monitor.
 * Stores baseline hashes and detects modifications between scans.
 * Supports incremental scanning.
 */
class WPFG_File_Monitor {

    const OPTION_BASELINE = 'wpfg_file_baseline';
    const OPTION_LAST_CHECK = 'wpfg_monitor_last_check';

    /**
     * Initialize file monitor cron.
     */
    public static function init() {
        add_action( 'wpfg_file_monitor_check', array( __CLASS__, 'run_check' ) );

        // Schedule if not already scheduled.
        if ( ! wp_next_scheduled( 'wpfg_file_monitor_check' ) ) {
            wp_schedule_event( time() + 300, 'hourly', 'wpfg_file_monitor_check' );
        }
    }

    /**
     * Build or rebuild the baseline hash map.
     * Stores relative_path => md5 hash for all scanned files.
     *
     * @param bool $force Rebuild even if baseline exists.
     * @return int Number of files in baseline.
     */
    public static function build_baseline( $force = false ) {
        $existing = get_option( self::OPTION_BASELINE );
        if ( $existing && ! $force ) {
            return count( $existing );
        }

        $settings  = get_option( 'wpfg_settings', WPFG_Settings::defaults() );
        $paths     = ! empty( $settings['scan_paths'] ) ? $settings['scan_paths'] : array( 'wp-content', 'wp-includes', 'wp-admin' );
        $excluded  = ! empty( $settings['excluded_paths'] ) ? $settings['excluded_paths'] : array();
        $skip_ext  = ! empty( $settings['excluded_extensions'] ) ? $settings['excluded_extensions'] : array();

        $baseline = array();

        foreach ( (array) $paths as $rel_path ) {
            $abs_path = ABSPATH . ltrim( $rel_path, '/' );
            if ( ! is_dir( $abs_path ) ) {
                continue;
            }

            foreach ( WPFG_Filesystem::scan_directory( $abs_path, $excluded, $skip_ext ) as $file ) {
                if ( ! is_readable( $file ) ) {
                    continue;
                }
                $rel = WPFG_Helpers::relative_path( $file );
                $baseline[ $rel ] = array(
                    'hash'     => md5_file( $file ),
                    'size'     => filesize( $file ),
                    'modified' => filemtime( $file ),
                );
            }
        }

        update_option( self::OPTION_BASELINE, $baseline, false );
        update_option( self::OPTION_LAST_CHECK, time() );

        WPFG_Logger::log( 'monitor_baseline', '', 'success', sprintf( '%d files indexed', count( $baseline ) ) );
        return count( $baseline );
    }

    /**
     * Compare current files against baseline.
     * Returns arrays of added, modified, deleted files.
     */
    public static function compare() {
        $baseline = get_option( self::OPTION_BASELINE );
        if ( ! is_array( $baseline ) || empty( $baseline ) ) {
            return new WP_Error( 'no_baseline', __( 'No baseline exists. Build one first.', 'wp-file-guardian' ) );
        }

        $settings  = get_option( 'wpfg_settings', WPFG_Settings::defaults() );
        $paths     = ! empty( $settings['scan_paths'] ) ? $settings['scan_paths'] : array( 'wp-content', 'wp-includes', 'wp-admin' );
        $excluded  = ! empty( $settings['excluded_paths'] ) ? $settings['excluded_paths'] : array();
        $skip_ext  = ! empty( $settings['excluded_extensions'] ) ? $settings['excluded_extensions'] : array();

        $current_files = array();
        $added    = array();
        $modified = array();
        $deleted  = array();

        foreach ( (array) $paths as $rel_path ) {
            $abs_path = ABSPATH . ltrim( $rel_path, '/' );
            if ( ! is_dir( $abs_path ) ) {
                continue;
            }

            foreach ( WPFG_Filesystem::scan_directory( $abs_path, $excluded, $skip_ext ) as $file ) {
                if ( ! is_readable( $file ) ) {
                    continue;
                }

                $rel  = WPFG_Helpers::relative_path( $file );
                $hash = md5_file( $file );
                $size = filesize( $file );
                $mod  = filemtime( $file );

                $current_files[ $rel ] = true;

                if ( ! isset( $baseline[ $rel ] ) ) {
                    $added[] = array(
                        'path'     => $rel,
                        'size'     => $size,
                        'modified' => $mod,
                    );
                } elseif ( $baseline[ $rel ]['hash'] !== $hash ) {
                    $modified[] = array(
                        'path'         => $rel,
                        'old_hash'     => $baseline[ $rel ]['hash'],
                        'new_hash'     => $hash,
                        'old_size'     => $baseline[ $rel ]['size'],
                        'new_size'     => $size,
                        'old_modified' => $baseline[ $rel ]['modified'],
                        'new_modified' => $mod,
                    );
                }
            }
        }

        // Find deleted files.
        foreach ( $baseline as $rel => $info ) {
            if ( ! isset( $current_files[ $rel ] ) ) {
                $deleted[] = array(
                    'path'     => $rel,
                    'old_size' => $info['size'],
                );
            }
        }

        return array(
            'added'        => $added,
            'modified'     => $modified,
            'deleted'      => $deleted,
            'total_added'  => count( $added ),
            'total_modified' => count( $modified ),
            'total_deleted'  => count( $deleted ),
            'baseline_count' => count( $baseline ),
            'checked_at'   => current_time( 'mysql' ),
        );
    }

    /**
     * Cron callback: compare and notify if changes detected.
     */
    public static function run_check() {
        $result = self::compare();
        if ( is_wp_error( $result ) ) {
            return;
        }

        $changes = $result['total_added'] + $result['total_modified'] + $result['total_deleted'];

        if ( $changes > 0 ) {
            // Save changes record.
            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'wpfg_file_changes', array(
                'added_count'    => $result['total_added'],
                'modified_count' => $result['total_modified'],
                'deleted_count'  => $result['total_deleted'],
                'details'        => wp_json_encode( array(
                    'added'    => array_slice( $result['added'], 0, 50 ),
                    'modified' => array_slice( $result['modified'], 0, 50 ),
                    'deleted'  => array_slice( $result['deleted'], 0, 50 ),
                ) ),
                'created_at' => current_time( 'mysql' ),
            ), array( '%d', '%d', '%d', '%s', '%s' ) );

            WPFG_Logger::log( 'file_changes_detected', '', 'success',
                sprintf( 'Added: %d, Modified: %d, Deleted: %d', $result['total_added'], $result['total_modified'], $result['total_deleted'] )
            );

            // Email notification.
            if ( $result['total_modified'] > 0 || $result['total_added'] > 5 ) {
                $subject = sprintf(
                    __( 'File Changes Detected: %d modifications', 'wp-file-guardian' ),
                    $changes
                );

                $body = sprintf(
                    __( "File changes detected on your site:\n\nAdded: %d files\nModified: %d files\nDeleted: %d files\n\nReview changes: %s", 'wp-file-guardian' ),
                    $result['total_added'],
                    $result['total_modified'],
                    $result['total_deleted'],
                    admin_url( 'admin.php?page=wpfg-monitor' )
                );

                WPFG_Notifications::send_email( $subject, $body, 'file_changes' );
            }
        }

        update_option( self::OPTION_LAST_CHECK, time() );
    }

    /**
     * Get recent file change records.
     */
    public static function get_change_history( $limit = 20 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_file_changes ORDER BY created_at DESC LIMIT %d",
            $limit
        ) );
    }

    /**
     * Get the last check timestamp.
     */
    public static function last_check() {
        return get_option( self::OPTION_LAST_CHECK, 0 );
    }

    /**
     * Get baseline file count.
     */
    public static function baseline_count() {
        $baseline = get_option( self::OPTION_BASELINE );
        return is_array( $baseline ) ? count( $baseline ) : 0;
    }

    /**
     * Incremental scan: only scan files modified since the last check.
     */
    public static function scan_changed_only() {
        $last_check = self::last_check();
        if ( ! $last_check ) {
            return new WP_Error( 'no_baseline', __( 'Build a baseline first.', 'wp-file-guardian' ) );
        }

        $settings  = get_option( 'wpfg_settings', WPFG_Settings::defaults() );
        $paths     = ! empty( $settings['scan_paths'] ) ? $settings['scan_paths'] : array( 'wp-content', 'wp-includes', 'wp-admin' );
        $excluded  = ! empty( $settings['excluded_paths'] ) ? $settings['excluded_paths'] : array();
        $skip_ext  = ! empty( $settings['excluded_extensions'] ) ? $settings['excluded_extensions'] : array();

        $changed = array();
        foreach ( (array) $paths as $rel_path ) {
            $abs_path = ABSPATH . ltrim( $rel_path, '/' );
            if ( ! is_dir( $abs_path ) ) {
                continue;
            }

            foreach ( WPFG_Filesystem::scan_directory( $abs_path, $excluded, $skip_ext ) as $file ) {
                if ( filemtime( $file ) > $last_check ) {
                    $changed[] = $file;
                }
            }
        }

        return $changed;
    }
}
