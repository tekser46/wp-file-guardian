<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Login protection: brute-force limiting, login logging, suspicious login detection.
 */
class WPFG_Login_Guard {

    const TABLE = 'wpfg_login_log';

    /**
     * Initialize login guard hooks.
     */
    public static function init() {
        if ( ! WPFG_Settings::get( 'login_protection', true ) ) {
            return;
        }

        add_action( 'wp_login', array( __CLASS__, 'on_login_success' ), 10, 2 );
        add_action( 'wp_login_failed', array( __CLASS__, 'on_login_failed' ), 10, 2 );
        add_filter( 'authenticate', array( __CLASS__, 'check_lockout' ), 30, 3 );
        add_action( 'wp_login', array( __CLASS__, 'check_suspicious_login' ), 20, 2 );
    }

    /**
     * Log a successful login.
     */
    public static function on_login_success( $user_login, $user ) {
        self::record_attempt( $user_login, 'success', $user->ID );

        // Clear failed attempts for this IP.
        self::clear_failed_attempts( WPFG_Helpers::get_client_ip() );
    }

    /**
     * Log a failed login attempt.
     */
    public static function on_login_failed( $username, $error = null ) {
        self::record_attempt( $username, 'failed', 0 );

        $ip          = WPFG_Helpers::get_client_ip();
        $max_attempts = (int) WPFG_Settings::get( 'login_max_attempts', 5 );
        $lockout_time = (int) WPFG_Settings::get( 'login_lockout_minutes', 30 );
        $window       = $lockout_time * MINUTE_IN_SECONDS;

        $failed_count = self::count_recent_failures( $ip, $window );

        if ( $failed_count >= $max_attempts ) {
            self::lockout_ip( $ip, $lockout_time );
            WPFG_Logger::log( 'login_lockout', $ip, 'success',
                sprintf( 'IP locked out after %d failed attempts for user: %s', $failed_count, $username )
            );

            // Notify admin.
            WPFG_Notifications::send_email(
                sprintf( __( 'Login Lockout: %s', 'wp-file-guardian' ), $ip ),
                sprintf(
                    __( "IP address %s has been locked out after %d failed login attempts.\nUsername tried: %s\nLockout duration: %d minutes", 'wp-file-guardian' ),
                    $ip, $failed_count, $username, $lockout_time
                ),
                'security'
            );
        }
    }

    /**
     * Check if IP is locked out before authentication.
     */
    public static function check_lockout( $user, $username, $password ) {
        if ( empty( $username ) ) {
            return $user;
        }

        $ip       = WPFG_Helpers::get_client_ip();
        $lockout  = get_transient( 'wpfg_lockout_' . md5( $ip ) );

        if ( $lockout ) {
            $remaining = $lockout - time();
            if ( $remaining > 0 ) {
                return new WP_Error( 'wpfg_locked_out', sprintf(
                    '<strong>' . __( 'Error:', 'wp-file-guardian' ) . '</strong> ' .
                    __( 'Too many failed login attempts. Please try again in %d minutes.', 'wp-file-guardian' ),
                    ceil( $remaining / 60 )
                ) );
            }
        }

        return $user;
    }

    /**
     * Detect suspicious logins (new location/device).
     */
    public static function check_suspicious_login( $user_login, $user ) {
        if ( ! in_array( 'administrator', $user->roles, true ) ) {
            return;
        }

        $ip = WPFG_Helpers::get_client_ip();
        $known_ips = get_user_meta( $user->ID, 'wpfg_known_ips', true );

        if ( ! is_array( $known_ips ) ) {
            $known_ips = array();
        }

        if ( ! in_array( $ip, $known_ips, true ) ) {
            // New IP for this admin user.
            $known_ips[] = $ip;
            // Keep only last 20 IPs.
            $known_ips = array_slice( $known_ips, -20 );
            update_user_meta( $user->ID, 'wpfg_known_ips', $known_ips );

            if ( count( $known_ips ) > 1 ) {
                WPFG_Notifications::send_email(
                    __( 'Admin Login from New IP', 'wp-file-guardian' ),
                    sprintf(
                        __( "Admin user '%s' logged in from a new IP address: %s\n\nIf this was not you, please change your password immediately and review recent activity.\n\nDashboard: %s", 'wp-file-guardian' ),
                        $user_login,
                        $ip,
                        admin_url( 'admin.php?page=wpfg-login-guard' )
                    ),
                    'security'
                );
            }
        }
    }

    /**
     * Record a login attempt.
     */
    private static function record_attempt( $username, $status, $user_id = 0 ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . self::TABLE, array(
            'username'   => sanitize_user( $username ),
            'user_id'    => absint( $user_id ),
            'ip_address' => WPFG_Helpers::get_client_ip(),
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) ) : '',
            'status'     => sanitize_text_field( $status ),
            'created_at' => current_time( 'mysql' ),
        ), array( '%s', '%d', '%s', '%s', '%s', '%s' ) );
    }

    /**
     * Count recent failures from an IP.
     */
    private static function count_recent_failures( $ip, $window ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::TABLE . "
             WHERE ip_address = %s AND status = 'failed' AND created_at > %s",
            $ip,
            gmdate( 'Y-m-d H:i:s', time() - $window )
        ) );
    }

    /**
     * Clear failed attempts for IP after success.
     */
    private static function clear_failed_attempts( $ip ) {
        delete_transient( 'wpfg_lockout_' . md5( $ip ) );
    }

    /**
     * Lock out an IP address.
     */
    private static function lockout_ip( $ip, $minutes ) {
        $expiry = time() + ( $minutes * 60 );
        set_transient( 'wpfg_lockout_' . md5( $ip ), $expiry, $minutes * 60 );
    }

    /**
     * Get login log entries.
     */
    public static function get_log( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $defaults = array(
            'status'   => '',
            'search'   => '',
            'per_page' => 30,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(username LIKE %s OR ip_address LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit     = absint( $args['per_page'] );

        if ( ! empty( $values ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $values ) );
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge( $values, array( $limit, $offset ) )
            ) );
        } else {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit, $offset
            ) );
        }

        return array( 'total' => $total, 'items' => $items );
    }

    /**
     * Get statistics for dashboard.
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $day_ago = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

        return array(
            'total_today_success' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'success' AND created_at > %s", $day_ago
            ) ),
            'total_today_failed' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND created_at > %s", $day_ago
            ) ),
            'unique_ips_today' => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_address) FROM {$table} WHERE created_at > %s", $day_ago
            ) ),
            'total_lockouts' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wpfg_logs WHERE action = 'login_lockout'"
            ),
        );
    }

    /**
     * Cleanup old login records (keep last 90 days).
     */
    public static function cleanup() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . self::TABLE . " WHERE created_at < %s",
            gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS )
        ) );
    }
}
