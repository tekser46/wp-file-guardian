<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP-Cron scheduled tasks.
 */
class WPFG_Cron {

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'add_schedules' ) );
        add_action( 'wpfg_scheduled_scan', array( __CLASS__, 'run_scheduled_scan' ) );
        add_action( 'wpfg_scheduled_backup', array( __CLASS__, 'run_scheduled_backup' ) );
        add_action( 'wpfg_cleanup_old_backups', array( __CLASS__, 'cleanup_old_backups' ) );

        // Re-schedule if settings changed.
        add_action( 'update_option_wpfg_settings', array( __CLASS__, 'reschedule' ), 10, 0 );

        // Ensure events are scheduled.
        self::ensure_scheduled();
    }

    /**
     * Add custom cron intervals.
     */
    public static function add_schedules( $schedules ) {
        $schedules['weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'wp-file-guardian' ),
        );
        $schedules['monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display'  => __( 'Once Monthly', 'wp-file-guardian' ),
        );
        return $schedules;
    }

    /**
     * Ensure cron events are scheduled based on settings.
     */
    public static function ensure_scheduled() {
        $scan_freq   = WPFG_Settings::get( 'scheduled_scan', 'daily' );
        $backup_freq = WPFG_Settings::get( 'scheduled_backup', 'weekly' );

        if ( 'off' !== $scan_freq && ! wp_next_scheduled( 'wpfg_scheduled_scan' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, $scan_freq, 'wpfg_scheduled_scan' );
        }
        if ( 'off' !== $backup_freq && ! wp_next_scheduled( 'wpfg_scheduled_backup' ) ) {
            wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, $backup_freq, 'wpfg_scheduled_backup' );
        }
        if ( ! wp_next_scheduled( 'wpfg_cleanup_old_backups' ) ) {
            wp_schedule_event( time() + 3 * HOUR_IN_SECONDS, 'daily', 'wpfg_cleanup_old_backups' );
        }
    }

    /**
     * Reschedule events when settings change.
     */
    public static function reschedule() {
        wp_clear_scheduled_hook( 'wpfg_scheduled_scan' );
        wp_clear_scheduled_hook( 'wpfg_scheduled_backup' );
        self::ensure_scheduled();
    }

    /**
     * Run a scheduled scan.
     */
    public static function run_scheduled_scan() {
        $session_id = WPFG_Scanner::create_session( 'scheduled' );
        $files      = WPFG_Scanner::collect_files( $session_id );
        $total      = count( $files );
        $batch_size = (int) WPFG_Settings::get( 'batch_size', 500 );
        $offset     = 0;

        while ( $offset < $total ) {
            $result = WPFG_Scanner::process_batch( $session_id, $offset, $batch_size );
            $offset = $result['processed'];
            if ( $result['done'] ) {
                break;
            }
        }

        // Send notification if critical issues found.
        $stats = WPFG_Scanner::get_dashboard_stats();
        if ( $stats['critical_count'] > 0 ) {
            WPFG_Notifications::notify_critical_findings( $session_id, $stats['critical_count'] );
        }
    }

    /**
     * Run a scheduled backup.
     */
    public static function run_scheduled_backup() {
        $backup_id = WPFG_Backup::create( 'full' );
        if ( ! is_wp_error( $backup_id ) ) {
            global $wpdb;
            $backup = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wpfg_backups WHERE id = %d",
                $backup_id
            ) );
            if ( $backup ) {
                WPFG_Notifications::notify_backup_completed( $backup->backup_type, $backup->file_size );
            }
        }
    }

    /**
     * Cleanup old backups based on retention setting.
     */
    public static function cleanup_old_backups() {
        WPFG_Backup::enforce_retention();
    }
}
