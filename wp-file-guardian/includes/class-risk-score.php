<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Site-wide security risk score calculator.
 * Produces a 0-100 score (100 = most secure).
 */
class WPFG_Risk_Score {

    /**
     * Calculate the overall risk score.
     *
     * @return array { score: int, grade: string, factors: array }
     */
    public static function calculate() {
        $factors = array();
        $score   = 100;

        // 1. WordPress version up to date? (-15 max)
        $factors['wp_version'] = self::check_wp_version();
        $score -= $factors['wp_version']['deduction'];

        // 2. Plugin updates available? (-10 max)
        $factors['plugin_updates'] = self::check_plugin_updates();
        $score -= $factors['plugin_updates']['deduction'];

        // 3. Theme updates available? (-5 max)
        $factors['theme_updates'] = self::check_theme_updates();
        $score -= $factors['theme_updates']['deduction'];

        // 4. SSL enabled? (-10)
        $factors['ssl'] = self::check_ssl();
        $score -= $factors['ssl']['deduction'];

        // 5. Debug mode off? (-5)
        $factors['debug'] = self::check_debug_mode();
        $score -= $factors['debug']['deduction'];

        // 6. File editor disabled? (-5)
        $factors['file_editor'] = self::check_file_editor();
        $score -= $factors['file_editor']['deduction'];

        // 7. Critical scan findings? (-20 max)
        $factors['scan_findings'] = self::check_scan_findings();
        $score -= $factors['scan_findings']['deduction'];

        // 8. Core integrity? (-15 max)
        $factors['core_integrity'] = self::check_core_integrity();
        $score -= $factors['core_integrity']['deduction'];

        // 9. Login failures (brute force)? (-10 max)
        $factors['login_failures'] = self::check_login_failures();
        $score -= $factors['login_failures']['deduction'];

        // 10. Backup freshness? (-5 max)
        $factors['backup'] = self::check_backup_freshness();
        $score -= $factors['backup']['deduction'];

        // 11. Database prefix default? (-5)
        $factors['db_prefix'] = self::check_db_prefix();
        $score -= $factors['db_prefix']['deduction'];

        // 12. Admin username? (-5)
        $factors['admin_user'] = self::check_admin_username();
        $score -= $factors['admin_user']['deduction'];

        $score = max( 0, min( 100, $score ) );

        return array(
            'score'   => $score,
            'grade'   => self::score_to_grade( $score ),
            'factors' => $factors,
        );
    }

    private static function check_wp_version() {
        global $wp_version;
        $update = get_preferred_from_update_core();
        $latest = isset( $update->current ) ? $update->current : $wp_version;

        if ( version_compare( $wp_version, $latest, '>=' ) ) {
            return array( 'label' => __( 'WordPress is up to date', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
        }
        return array( 'label' => sprintf( __( 'WordPress update available: %s → %s', 'wp-file-guardian' ), $wp_version, $latest ), 'status' => 'bad', 'deduction' => 15 );
    }

    private static function check_plugin_updates() {
        $update_plugins = get_site_transient( 'update_plugins' );
        $count = isset( $update_plugins->response ) ? count( $update_plugins->response ) : 0;
        if ( 0 === $count ) {
            return array( 'label' => __( 'All plugins up to date', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
        }
        $deduction = min( 10, $count * 3 );
        return array( 'label' => sprintf( __( '%d plugin updates available', 'wp-file-guardian' ), $count ), 'status' => 'bad', 'deduction' => $deduction );
    }

    private static function check_theme_updates() {
        $update_themes = get_site_transient( 'update_themes' );
        $count = isset( $update_themes->response ) ? count( $update_themes->response ) : 0;
        if ( 0 === $count ) {
            return array( 'label' => __( 'All themes up to date', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
        }
        return array( 'label' => sprintf( __( '%d theme updates available', 'wp-file-guardian' ), $count ), 'status' => 'bad', 'deduction' => 5 );
    }

    private static function check_ssl() {
        if ( is_ssl() ) {
            return array( 'label' => __( 'SSL/HTTPS is active', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
        }
        return array( 'label' => __( 'SSL/HTTPS is not active', 'wp-file-guardian' ), 'status' => 'bad', 'deduction' => 10 );
    }

    private static function check_debug_mode() {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return array( 'label' => __( 'Debug mode is off', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
        }
        if ( defined( 'WP_DEBUG_DISPLAY' ) && ! WP_DEBUG_DISPLAY ) {
            return array( 'label' => __( 'Debug mode on but display off', 'wp-file-guardian' ), 'status' => 'ok', 'deduction' => 2 );
        }
        return array( 'label' => __( 'Debug mode with display is on (security risk)', 'wp-file-guardian' ), 'status' => 'bad', 'deduction' => 5 );
    }

    private static function check_file_editor() {
        if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
            return array( 'label' => __( 'File editor is disabled', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
        }
        return array( 'label' => __( 'Built-in file editor is enabled', 'wp-file-guardian' ), 'status' => 'bad', 'deduction' => 5 );
    }

    private static function check_scan_findings() {
        $stats = WPFG_Scanner::get_dashboard_stats();
        if ( $stats['critical_count'] > 0 ) {
            $deduction = min( 20, $stats['critical_count'] * 5 );
            return array( 'label' => sprintf( __( '%d critical scan findings', 'wp-file-guardian' ), $stats['critical_count'] ), 'status' => 'bad', 'deduction' => $deduction );
        }
        if ( $stats['warning_count'] > 5 ) {
            return array( 'label' => sprintf( __( '%d warning scan findings', 'wp-file-guardian' ), $stats['warning_count'] ), 'status' => 'ok', 'deduction' => 5 );
        }
        if ( 'never' === $stats['scan_status'] ) {
            return array( 'label' => __( 'No scan has been performed yet', 'wp-file-guardian' ), 'status' => 'bad', 'deduction' => 10 );
        }
        return array( 'label' => __( 'No critical scan findings', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
    }

    private static function check_core_integrity() {
        // Use cached result if recent.
        $cached = get_transient( 'wpfg_core_integrity_result' );
        if ( $cached !== false ) {
            return $cached;
        }

        $result = array( 'label' => __( 'Core integrity not checked yet', 'wp-file-guardian' ), 'status' => 'ok', 'deduction' => 5 );
        set_transient( 'wpfg_core_integrity_result', $result, 6 * HOUR_IN_SECONDS );
        return $result;
    }

    private static function check_login_failures() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_login_log';

        // Check if table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return array( 'label' => __( 'Login monitoring not active', 'wp-file-guardian' ), 'status' => 'ok', 'deduction' => 0 );
        }

        $day_ago = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
        $failures = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND created_at > %s", $day_ago
        ) );

        if ( $failures > 50 ) {
            return array( 'label' => sprintf( __( '%d failed logins in 24h (possible attack)', 'wp-file-guardian' ), $failures ), 'status' => 'bad', 'deduction' => 10 );
        }
        if ( $failures > 10 ) {
            return array( 'label' => sprintf( __( '%d failed logins in 24h', 'wp-file-guardian' ), $failures ), 'status' => 'ok', 'deduction' => 3 );
        }
        return array( 'label' => __( 'Login activity normal', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
    }

    private static function check_backup_freshness() {
        global $wpdb;
        $latest = $wpdb->get_var(
            "SELECT created_at FROM {$wpdb->prefix}wpfg_backups WHERE status = 'completed' ORDER BY created_at DESC LIMIT 1"
        );
        if ( ! $latest ) {
            return array( 'label' => __( 'No backup has been created', 'wp-file-guardian' ), 'status' => 'bad', 'deduction' => 5 );
        }
        $age_days = ( time() - strtotime( $latest ) ) / DAY_IN_SECONDS;
        if ( $age_days > 14 ) {
            return array( 'label' => sprintf( __( 'Last backup is %d days old', 'wp-file-guardian' ), (int) $age_days ), 'status' => 'bad', 'deduction' => 5 );
        }
        return array( 'label' => __( 'Recent backup exists', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
    }

    private static function check_db_prefix() {
        global $wpdb;
        if ( 'wp_' === $wpdb->prefix ) {
            return array( 'label' => __( 'Default database prefix (wp_) in use', 'wp-file-guardian' ), 'status' => 'ok', 'deduction' => 5 );
        }
        return array( 'label' => __( 'Custom database prefix', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
    }

    private static function check_admin_username() {
        $admin = get_user_by( 'login', 'admin' );
        if ( $admin ) {
            return array( 'label' => __( '"admin" username exists (easy target)', 'wp-file-guardian' ), 'status' => 'bad', 'deduction' => 5 );
        }
        return array( 'label' => __( 'No default "admin" username', 'wp-file-guardian' ), 'status' => 'good', 'deduction' => 0 );
    }

    /**
     * Convert score to letter grade.
     */
    public static function score_to_grade( $score ) {
        if ( $score >= 90 ) return 'A';
        if ( $score >= 80 ) return 'B';
        if ( $score >= 70 ) return 'C';
        if ( $score >= 50 ) return 'D';
        return 'F';
    }

    /**
     * Get grade color.
     */
    public static function grade_color( $grade ) {
        $colors = array( 'A' => '#00a32a', 'B' => '#2271b1', 'C' => '#dba617', 'D' => '#d63638', 'F' => '#8b0000' );
        return isset( $colors[ $grade ] ) ? $colors[ $grade ] : '#666';
    }
}
