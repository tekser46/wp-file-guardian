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
        add_action( 'wpfg_firewall_cleanup', array( __CLASS__, 'run_firewall_cleanup' ) );
        add_action( 'wpfg_scheduled_vuln_scan', array( __CLASS__, 'run_vuln_scan' ) );
        add_action( 'wpfg_weekly_summary', array( __CLASS__, 'run_weekly_summary' ) );

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
        // v3: Firewall log cleanup.
        if ( WPFG_Settings::get( 'firewall_enabled', false ) && ! wp_next_scheduled( 'wpfg_firewall_cleanup' ) ) {
            wp_schedule_event( time() + 4 * HOUR_IN_SECONDS, 'daily', 'wpfg_firewall_cleanup' );
        }
        // v3: Vulnerability scan.
        $vuln_freq = WPFG_Settings::get( 'vuln_scan_schedule', 'daily' );
        if ( 'off' !== $vuln_freq && ! wp_next_scheduled( 'wpfg_scheduled_vuln_scan' ) ) {
            wp_schedule_event( time() + 5 * HOUR_IN_SECONDS, $vuln_freq, 'wpfg_scheduled_vuln_scan' );
        }
        // v3: Weekly summary.
        if ( WPFG_Settings::get( 'weekly_summary_enabled', true ) && ! wp_next_scheduled( 'wpfg_weekly_summary' ) ) {
            wp_schedule_event( time() + 6 * HOUR_IN_SECONDS, 'weekly', 'wpfg_weekly_summary' );
        }
    }

    /**
     * Reschedule events when settings change.
     */
    public static function reschedule() {
        wp_clear_scheduled_hook( 'wpfg_scheduled_scan' );
        wp_clear_scheduled_hook( 'wpfg_scheduled_backup' );
        wp_clear_scheduled_hook( 'wpfg_firewall_cleanup' );
        wp_clear_scheduled_hook( 'wpfg_scheduled_vuln_scan' );
        wp_clear_scheduled_hook( 'wpfg_weekly_summary' );
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

        // Save scan history for Chart.js dashboard graphs.
        self::save_scan_history( $stats );
    }

    /**
     * Save scan history record for trend tracking.
     */
    private static function save_scan_history( $stats ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_scan_history';

        // Check table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        $risk  = WPFG_Risk_Score::calculate();

        // Upsert: update if today's record exists, otherwise insert.
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE scan_date = %s", $today
        ) );

        $data = array(
            'scan_date'      => $today,
            'critical_count' => $stats['critical_count'],
            'warning_count'  => $stats['warning_count'],
            'info_count'     => $stats['info_count'],
            'total_files'    => $stats['total_files'],
            'risk_score'     => $risk['score'],
        );

        if ( $existing ) {
            $wpdb->update( $table, $data, array( 'id' => $existing ) );
        } else {
            $wpdb->insert( $table, $data );
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

    /**
     * Clean up old firewall logs.
     */
    public static function run_firewall_cleanup() {
        if ( ! class_exists( 'WPFG_Firewall' ) ) {
            return;
        }
        WPFG_Firewall::cleanup_logs();
    }

    /**
     * Run scheduled vulnerability scan.
     */
    public static function run_vuln_scan() {
        if ( ! class_exists( 'WPFG_Vuln_Scanner' ) ) {
            return;
        }
        $result = WPFG_Vuln_Scanner::scan();
        if ( ! is_wp_error( $result ) && ! empty( $result['critical'] ) ) {
            WPFG_Notifications::notify_vulnerabilities_found( $result['critical'] );
        }
    }

    /**
     * Send weekly security summary.
     */
    public static function run_weekly_summary() {
        WPFG_Notifications::send_weekly_summary();
    }
}
