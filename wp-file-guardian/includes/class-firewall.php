<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Firewall / WAF: IP blocking, rate limiting, country blocking, user-agent filtering.
 */
class WPFG_Firewall {

    const RULES_TABLE = 'wpfg_firewall_rules';
    const LOG_TABLE   = 'wpfg_firewall_log';

    /**
     * Initialize firewall hooks.
     */
    public static function init() {
        if ( ! WPFG_Settings::get( 'firewall_enabled', false ) ) {
            return;
        }

        add_action( 'init', array( __CLASS__, 'check_request' ), 1 );
    }

    /**
     * Get the real client IP address.
     */
    private static function get_client_ip() {
        // Cloudflare.
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
        }

        // Proxy / load balancer.
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip  = trim( $ips[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }

        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return '127.0.0.1';
    }

    /**
     * Run all firewall checks on the current request.
     */
    public static function check_request() {
        // Skip internal WordPress requests.
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $request_uri, 'admin-ajax.php' ) !== false || strpos( $request_uri, 'wp-cron.php' ) !== false ) {
            return;
        }

        // Whitelist logged-in administrators.
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return;
        }

        $ip = self::get_client_ip();
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

        // 1. Whitelisted IPs always pass.
        if ( self::is_whitelisted( $ip ) ) {
            return;
        }

        // 2. Blacklist check.
        if ( self::is_blacklisted( $ip ) ) {
            self::block_request( $ip, 'ip_blacklist', $ua );
        }

        // 3. Country check.
        if ( ! self::check_country( $ip ) ) {
            self::block_request( $ip, 'country_block', $ua );
        }

        // 4. Rate limit check.
        if ( ! self::check_rate_limit( $ip ) ) {
            self::block_request( $ip, 'rate_limit', $ua );
        }

        // 5. User agent check.
        if ( ! self::check_user_agent( $ua ) ) {
            self::block_request( $ip, 'ua_block', $ua );
        }
    }

    /**
     * Check if an IP is whitelisted.
     */
    private static function is_whitelisted( $ip ) {
        global $wpdb;
        $table = $wpdb->prefix . self::RULES_TABLE;

        $rules = $wpdb->get_col(
            "SELECT rule_value FROM {$table} WHERE rule_type = 'ip_whitelist' AND is_active = 1"
        );

        foreach ( $rules as $rule_value ) {
            if ( strpos( $rule_value, '/' ) !== false ) {
                if ( self::ip_in_cidr( $ip, $rule_value ) ) {
                    return true;
                }
            } elseif ( $ip === $rule_value ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is blacklisted.
     */
    private static function is_blacklisted( $ip ) {
        global $wpdb;
        $table = $wpdb->prefix . self::RULES_TABLE;
        $now   = current_time( 'mysql' );

        $rules = $wpdb->get_results(
            "SELECT rule_value, expires_at FROM {$table}
             WHERE rule_type = 'ip_blacklist' AND is_active = 1"
        );

        foreach ( $rules as $rule ) {
            // Skip expired rules.
            if ( ! empty( $rule->expires_at ) && $rule->expires_at < $now ) {
                continue;
            }

            if ( strpos( $rule->rule_value, '/' ) !== false ) {
                if ( self::ip_in_cidr( $ip, $rule->rule_value ) ) {
                    return true;
                }
            } elseif ( $ip === $rule->rule_value ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP country is allowed.
     *
     * @return bool True if allowed, false if blocked.
     */
    private static function check_country( $ip ) {
        if ( ! WPFG_Settings::get( 'firewall_geoip_enabled', false ) ) {
            return true;
        }

        $country = self::get_ip_country( $ip );
        if ( empty( $country ) ) {
            return true;
        }

        $blocked = WPFG_Settings::get( 'firewall_blocked_countries', array() );
        if ( ! is_array( $blocked ) || empty( $blocked ) ) {
            return true;
        }

        return ! in_array( strtoupper( $country ), array_map( 'strtoupper', $blocked ), true );
    }

    /**
     * Check rate limit for an IP.
     *
     * @return bool True if within limit, false if exceeded.
     */
    private static function check_rate_limit( $ip ) {
        $limit  = (int) WPFG_Settings::get( 'firewall_rate_limit', 60 );
        $window = (int) WPFG_Settings::get( 'firewall_rate_window', 60 );

        if ( $limit <= 0 ) {
            return true;
        }

        $key   = 'wpfg_rate_' . md5( $ip );
        $count = (int) get_transient( $key );

        if ( $count >= $limit ) {
            return false;
        }

        set_transient( $key, $count + 1, $window );

        return true;
    }

    /**
     * Check user agent against blocked patterns.
     *
     * @return bool True if allowed, false if blocked.
     */
    private static function check_user_agent( $ua ) {
        if ( empty( $ua ) ) {
            return true;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::RULES_TABLE;

        $patterns = $wpdb->get_col(
            "SELECT rule_value FROM {$table} WHERE rule_type = 'ua_block' AND is_active = 1"
        );

        foreach ( $patterns as $pattern ) {
            if ( stripos( $ua, $pattern ) !== false ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Block the request: log, maybe auto-ban, send 403.
     */
    private static function block_request( $ip, $rule, $ua = '' ) {
        global $wpdb;

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
        $country     = self::get_ip_country( $ip );

        // Log to firewall log table.
        $wpdb->insert( $wpdb->prefix . self::LOG_TABLE, array(
            'ip_address'  => $ip,
            'rule_matched' => sanitize_text_field( $rule ),
            'user_agent'  => mb_substr( $ua, 0, 255 ),
            'request_uri' => mb_substr( $request_uri, 0, 2048 ),
            'country'     => $country,
            'created_at'  => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );

        // Log to main audit log.
        WPFG_Logger::log( 'firewall_block', $ip, 'success',
            sprintf( 'Blocked by %s rule. UA: %s, URI: %s', $rule, mb_substr( $ua, 0, 100 ), $request_uri )
        );

        self::maybe_auto_ban( $ip );

        status_header( 403 );
        wp_die(
            __( 'Access denied. Your request has been blocked by the firewall.', 'wp-file-guardian' ),
            __( '403 Forbidden', 'wp-file-guardian' ),
            array( 'response' => 403 )
        );
    }

    /**
     * Auto-ban IP if it exceeds the threshold of recent blocks.
     */
    private static function maybe_auto_ban( $ip ) {
        if ( ! WPFG_Settings::get( 'firewall_auto_ban', false ) ) {
            return;
        }

        $threshold = (int) WPFG_Settings::get( 'firewall_auto_ban_threshold', 20 );
        $window    = (int) WPFG_Settings::get( 'firewall_auto_ban_window', 3600 );
        $duration  = (int) WPFG_Settings::get( 'firewall_auto_ban_duration', 86400 );

        global $wpdb;
        $table = $wpdb->prefix . self::LOG_TABLE;

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE ip_address = %s AND created_at > %s",
            $ip,
            gmdate( 'Y-m-d H:i:s', time() - $window )
        ) );

        if ( $count >= $threshold ) {
            // Check if already auto-banned.
            $rules_table = $wpdb->prefix . self::RULES_TABLE;
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$rules_table}
                 WHERE rule_type = 'ip_blacklist' AND rule_value = %s AND notes LIKE '%%auto-ban%%' AND is_active = 1",
                $ip
            ) );

            if ( ! $exists ) {
                $expires_at = gmdate( 'Y-m-d H:i:s', time() + $duration );
                self::add_rule( 'ip_blacklist', $ip, sprintf( 'auto-ban after %d blocks', $count ), $expires_at );

                WPFG_Logger::log( 'firewall_auto_ban', $ip, 'success',
                    sprintf( 'Auto-banned for %d seconds after %d blocks', $duration, $count )
                );
            }
        }
    }

    /**
     * Get the 2-letter country code for an IP address via ip-api.com.
     */
    public static function get_ip_country( $ip ) {
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return '';
        }

        $cache_key = 'wpfg_geo_' . md5( $ip );
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        $response = wp_remote_get( 'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=countryCode', array(
            'timeout' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $code = ! empty( $body['countryCode'] ) ? sanitize_text_field( $body['countryCode'] ) : '';

        set_transient( $cache_key, $code, DAY_IN_SECONDS );

        return $code;
    }

    // -------------------------------------------------------
    // Rule management
    // -------------------------------------------------------

    /**
     * Add a firewall rule.
     */
    public static function add_rule( $type, $value, $notes = '', $expires_at = null ) {
        global $wpdb;

        $data = array(
            'rule_type'  => sanitize_text_field( $type ),
            'rule_value' => sanitize_text_field( $value ),
            'notes'      => sanitize_text_field( $notes ),
            'is_active'  => 1,
            'created_at' => current_time( 'mysql' ),
        );
        $formats = array( '%s', '%s', '%s', '%d', '%s' );

        if ( $expires_at ) {
            $data['expires_at'] = sanitize_text_field( $expires_at );
            $formats[]          = '%s';
        }

        return $wpdb->insert( $wpdb->prefix . self::RULES_TABLE, $data, $formats );
    }

    /**
     * Delete a firewall rule by ID.
     */
    public static function delete_rule( $id ) {
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . self::RULES_TABLE,
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );
    }

    /**
     * Toggle a rule active/inactive.
     */
    public static function toggle_rule( $id, $active ) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . self::RULES_TABLE,
            array( 'is_active' => $active ? 1 : 0 ),
            array( 'id' => absint( $id ) ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Get rules with pagination and optional filters.
     *
     * @param array $args { type, search, per_page, page }
     * @return array { total: int, items: array }
     */
    public static function get_rules( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . self::RULES_TABLE;

        $defaults = array(
            'type'     => '',
            'search'   => '',
            'per_page' => 20,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( $args['type'] ) {
            $where[]  = 'rule_type = %s';
            $values[] = $args['type'];
        }
        if ( $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(rule_value LIKE %s OR notes LIKE %s)';
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

    // -------------------------------------------------------
    // Log management
    // -------------------------------------------------------

    /**
     * Get firewall log entries with pagination and filters.
     *
     * @param array $args { rule, ip, per_page, page }
     * @return array { total: int, items: array }
     */
    public static function get_log( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . self::LOG_TABLE;

        $defaults = array(
            'rule'     => '',
            'ip'       => '',
            'per_page' => 30,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( $args['rule'] ) {
            $where[]  = 'rule_matched = %s';
            $values[] = $args['rule'];
        }
        if ( $args['ip'] ) {
            $like     = '%' . $wpdb->esc_like( $args['ip'] ) . '%';
            $where[]  = 'ip_address LIKE %s';
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
     * Clear all firewall log entries.
     */
    public static function clear_log() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}" . self::LOG_TABLE );
    }

    /**
     * Delete log entries older than the retention period.
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        $days = (int) WPFG_Settings::get( 'firewall_log_retention_days', 30 );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE created_at < %s",
            gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
        ) );
    }

    // -------------------------------------------------------
    // Stats
    // -------------------------------------------------------

    /**
     * Get firewall statistics for dashboard display.
     */
    public static function get_stats() {
        global $wpdb;
        $log_table   = $wpdb->prefix . self::LOG_TABLE;
        $rules_table = $wpdb->prefix . self::RULES_TABLE;

        $day_ago  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
        $week_ago = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );

        $blocked_today = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$log_table} WHERE created_at > %s", $day_ago
        ) );

        $blocked_week = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$log_table} WHERE created_at > %s", $week_ago
        ) );

        $total_rules = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$rules_table} WHERE is_active = 1"
        );

        $auto_banned = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$rules_table}
             WHERE rule_type = 'ip_blacklist' AND is_active = 1 AND notes LIKE '%auto-ban%'"
        );

        $top_ips = $wpdb->get_results( $wpdb->prepare(
            "SELECT ip_address, COUNT(*) AS block_count FROM {$log_table}
             WHERE created_at > %s GROUP BY ip_address ORDER BY block_count DESC LIMIT 10",
            $week_ago
        ) );

        $top_rules = $wpdb->get_results( $wpdb->prepare(
            "SELECT rule_matched, COUNT(*) AS block_count FROM {$log_table}
             WHERE created_at > %s GROUP BY rule_matched ORDER BY block_count DESC",
            $week_ago
        ) );

        return array(
            'blocked_today' => $blocked_today,
            'blocked_week'  => $blocked_week,
            'total_rules'   => $total_rules,
            'auto_banned'   => $auto_banned,
            'top_ips'       => $top_ips,
            'top_rules'     => $top_rules,
        );
    }

    // -------------------------------------------------------
    // CIDR helper
    // -------------------------------------------------------

    /**
     * Check if an IP address falls within a CIDR range.
     */
    private static function ip_in_cidr( $ip, $cidr ) {
        if ( strpos( $cidr, '/' ) === false ) {
            return $ip === $cidr;
        }

        list( $subnet, $mask ) = explode( '/', $cidr, 2 );
        $mask = (int) $mask;

        // IPv4.
        if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $ip_long     = ip2long( $ip );
            $subnet_long = ip2long( $subnet );

            if ( $ip_long === false || $subnet_long === false || $mask < 0 || $mask > 32 ) {
                return false;
            }

            $mask_long = -1 << ( 32 - $mask );
            return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
        }

        // IPv6.
        if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            $ip_bin     = inet_pton( $ip );
            $subnet_bin = inet_pton( $subnet );

            if ( $ip_bin === false || $subnet_bin === false || $mask < 0 || $mask > 128 ) {
                return false;
            }

            $mask_bin = str_repeat( "\xff", (int) ( $mask / 8 ) );
            if ( $mask % 8 ) {
                $mask_bin .= chr( 256 - pow( 2, 8 - ( $mask % 8 ) ) );
            }
            $mask_bin = str_pad( $mask_bin, 16, "\x00" );

            return ( $ip_bin & $mask_bin ) === ( $subnet_bin & $mask_bin );
        }

        return false;
    }
}
