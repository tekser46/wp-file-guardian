<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Database malware scanner.
 * Scans wp_posts, wp_options, wp_comments for injected code.
 */
class WPFG_DB_Scanner {

    /**
     * Dangerous patterns to look for in database content.
     */
    private static function get_patterns() {
        return array(
            array(
                'pattern'  => '/<script[^>]*>.*?(eval|document\.write|window\.location|\.src\s*=)/si',
                'label'    => __( 'Suspicious JavaScript injection', 'wp-file-guardian' ),
                'severity' => 'critical',
            ),
            array(
                'pattern'  => '/<iframe[^>]*src\s*=\s*["\']https?:\/\/(?!(' . preg_quote( parse_url( home_url(), PHP_URL_HOST ), '/' ) . '))/i',
                'label'    => __( 'External iframe injection', 'wp-file-guardian' ),
                'severity' => 'critical',
            ),
            array(
                'pattern'  => '/\b(eval|assert)\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)/i',
                'label'    => __( 'PHP code execution in database', 'wp-file-guardian' ),
                'severity' => 'critical',
            ),
            array(
                'pattern'  => '/<script[^>]*src\s*=\s*["\']https?:\/\/(?!(' . preg_quote( parse_url( home_url(), PHP_URL_HOST ), '/' ) . '))/i',
                'label'    => __( 'External script injection', 'wp-file-guardian' ),
                'severity' => 'critical',
            ),
            array(
                'pattern'  => '/on(load|error|click|mouseover)\s*=\s*["\'].*?(eval|alert|document\.cookie|window\.location)/i',
                'label'    => __( 'Inline event handler with suspicious code', 'wp-file-guardian' ),
                'severity' => 'warning',
            ),
            array(
                'pattern'  => '/(document\.cookie|document\.write\s*\(|window\.location\s*=|\.innerHTML\s*=)/i',
                'label'    => __( 'DOM manipulation / cookie theft pattern', 'wp-file-guardian' ),
                'severity' => 'warning',
            ),
            array(
                'pattern'  => '/style\s*=\s*["\'][^"\']*position\s*:\s*absolute[^"\']*(?:left|top)\s*:\s*-\d{3,}/i',
                'label'    => __( 'Hidden content via CSS positioning', 'wp-file-guardian' ),
                'severity' => 'info',
            ),
            array(
                'pattern'  => '/<\?php/i',
                'label'    => __( 'PHP opening tag in database content', 'wp-file-guardian' ),
                'severity' => 'critical',
            ),
            array(
                'pattern'  => '/base64,[A-Za-z0-9+\/=]{100,}/',
                'label'    => __( 'Large base64 data blob (possible encoded payload)', 'wp-file-guardian' ),
                'severity' => 'warning',
            ),
        );
    }

    /**
     * Scan wp_posts table.
     *
     * @param int $batch_size
     * @param int $offset
     * @return array
     */
    public static function scan_posts( $batch_size = 200, $offset = 0 ) {
        global $wpdb;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title, post_content, post_excerpt, post_type, post_status, post_date
             FROM {$wpdb->posts} ORDER BY ID ASC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ) );

        $findings = array();
        $patterns = self::get_patterns();

        foreach ( $rows as $row ) {
            $content_to_scan = $row->post_title . ' ' . $row->post_content . ' ' . $row->post_excerpt;

            foreach ( $patterns as $pat ) {
                if ( preg_match( $pat['pattern'], $content_to_scan ) ) {
                    $findings[] = array(
                        'source'   => 'wp_posts',
                        'row_id'   => $row->ID,
                        'label'    => sprintf(
                            '%s (Post #%d: "%s", type: %s)',
                            $pat['label'],
                            $row->ID,
                            mb_substr( $row->post_title, 0, 50 ),
                            $row->post_type
                        ),
                        'severity' => $pat['severity'],
                        'date'     => $row->post_date,
                        'edit_url' => get_edit_post_link( $row->ID, 'raw' ),
                    );
                    break; // One finding per row is enough.
                }
            }
        }

        return array(
            'findings'  => $findings,
            'total'     => $total,
            'processed' => $offset + count( $rows ),
            'done'      => ( $offset + count( $rows ) ) >= $total,
        );
    }

    /**
     * Scan wp_options table.
     */
    public static function scan_options( $batch_size = 500, $offset = 0 ) {
        global $wpdb;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT option_id, option_name, option_value FROM {$wpdb->options}
             ORDER BY option_id ASC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ) );

        $findings = array();
        $patterns = self::get_patterns();

        // Skip known safe large options.
        $skip_options = array( 'cron', 'rewrite_rules', 'active_plugins', 'uninstall_plugins' );

        foreach ( $rows as $row ) {
            if ( in_array( $row->option_name, $skip_options, true ) ) {
                continue;
            }

            // Only scan string values > 20 chars.
            if ( strlen( $row->option_value ) < 20 ) {
                continue;
            }

            foreach ( $patterns as $pat ) {
                if ( preg_match( $pat['pattern'], $row->option_value ) ) {
                    $findings[] = array(
                        'source'      => 'wp_options',
                        'row_id'      => $row->option_id,
                        'option_name' => $row->option_name,
                        'label'       => sprintf(
                            '%s (Option: %s)',
                            $pat['label'],
                            $row->option_name
                        ),
                        'severity'    => $pat['severity'],
                        'preview'     => mb_substr( $row->option_value, 0, 200 ),
                    );
                    break;
                }
            }
        }

        return array(
            'findings'  => $findings,
            'total'     => $total,
            'processed' => $offset + count( $rows ),
            'done'      => ( $offset + count( $rows ) ) >= $total,
        );
    }

    /**
     * Scan wp_comments table for spam/injected links.
     */
    public static function scan_comments( $batch_size = 500, $offset = 0 ) {
        global $wpdb;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT comment_ID, comment_author, comment_author_url, comment_content,
                    comment_date, comment_approved
             FROM {$wpdb->comments} ORDER BY comment_ID ASC LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ) );

        $findings = array();
        $patterns = self::get_patterns();

        foreach ( $rows as $row ) {
            $content = $row->comment_author . ' ' . $row->comment_author_url . ' ' . $row->comment_content;

            foreach ( $patterns as $pat ) {
                if ( preg_match( $pat['pattern'], $content ) ) {
                    $findings[] = array(
                        'source'     => 'wp_comments',
                        'row_id'     => $row->comment_ID,
                        'label'      => sprintf(
                            '%s (Comment #%d by %s)',
                            $pat['label'],
                            $row->comment_ID,
                            mb_substr( $row->comment_author, 0, 30 )
                        ),
                        'severity'   => $pat['severity'],
                        'date'       => $row->comment_date,
                        'status'     => $row->comment_approved,
                    );
                    break;
                }
            }
        }

        return array(
            'findings'  => $findings,
            'total'     => $total,
            'processed' => $offset + count( $rows ),
            'done'      => ( $offset + count( $rows ) ) >= $total,
        );
    }

    /**
     * Scan wp_users for suspicious accounts.
     */
    public static function scan_users() {
        global $wpdb;

        $findings = array();

        // Find admin accounts created recently (last 30 days).
        $admins = get_users( array(
            'role'       => 'administrator',
            'date_query' => array(
                array( 'after' => '30 days ago' ),
            ),
        ) );

        foreach ( $admins as $admin ) {
            $findings[] = array(
                'source'   => 'wp_users',
                'row_id'   => $admin->ID,
                'label'    => sprintf(
                    __( 'Admin account created recently: %s (%s) on %s', 'wp-file-guardian' ),
                    $admin->user_login,
                    $admin->user_email,
                    $admin->user_registered
                ),
                'severity' => 'warning',
            );
        }

        // Find users with suspicious usernames.
        $suspicious_names = array( 'admin1', 'test', 'administrator', 'backdoor', 'hacker', 'root', 'shell' );
        $all_users = get_users( array( 'role' => 'administrator' ) );
        foreach ( $all_users as $user ) {
            if ( in_array( strtolower( $user->user_login ), $suspicious_names, true ) ) {
                $findings[] = array(
                    'source'   => 'wp_users',
                    'row_id'   => $user->ID,
                    'label'    => sprintf(
                        __( 'Admin with suspicious username: %s (%s)', 'wp-file-guardian' ),
                        $user->user_login,
                        $user->user_email
                    ),
                    'severity' => 'warning',
                );
            }
        }

        // Count total admins.
        $admin_count = count( $all_users );
        if ( $admin_count > 3 ) {
            $findings[] = array(
                'source'   => 'wp_users',
                'row_id'   => 0,
                'label'    => sprintf(
                    __( 'High number of administrator accounts: %d', 'wp-file-guardian' ),
                    $admin_count
                ),
                'severity' => 'info',
            );
        }

        return $findings;
    }

    /**
     * Scan cron jobs for suspicious entries.
     */
    public static function scan_cron() {
        $findings = array();
        $crons    = _get_cron_array();
        if ( ! is_array( $crons ) ) {
            return $findings;
        }

        // Known safe WP core hooks.
        $known_hooks = array(
            'wp_version_check', 'wp_update_plugins', 'wp_update_themes',
            'wp_scheduled_delete', 'wp_scheduled_auto_draft_delete',
            'delete_expired_transients', 'wp_privacy_delete_old_export_files',
            'wp_cron_delete_expired_db_locks', 'recovery_mode_clean_expired_keys',
            'wp_site_health_scheduled_check',
            // Our plugin hooks.
            'wpfg_scheduled_scan', 'wpfg_scheduled_backup', 'wpfg_cleanup_old_backups',
            'wpfg_file_monitor_check',
        );

        foreach ( $crons as $timestamp => $hooks ) {
            foreach ( $hooks as $hook => $events ) {
                // Skip known hooks.
                if ( in_array( $hook, $known_hooks, true ) ) {
                    continue;
                }

                // Flag hooks that look suspicious.
                $is_suspicious = false;
                $severity      = 'info';

                // Random-looking hook names.
                if ( preg_match( '/^[a-z0-9]{10,}$/i', $hook ) && ! preg_match( '/_/', $hook ) ) {
                    $is_suspicious = true;
                    $severity      = 'warning';
                }

                // Hooks with base64 or eval references.
                if ( preg_match( '/(base64|eval|exec|shell|backdoor)/i', $hook ) ) {
                    $is_suspicious = true;
                    $severity      = 'critical';
                }

                if ( $is_suspicious ) {
                    $findings[] = array(
                        'source'    => 'wp_cron',
                        'row_id'    => 0,
                        'label'     => sprintf(
                            __( 'Suspicious cron job: %s (scheduled: %s)', 'wp-file-guardian' ),
                            $hook,
                            wp_date( 'Y-m-d H:i:s', $timestamp )
                        ),
                        'severity'  => $severity,
                        'hook'      => $hook,
                        'timestamp' => $timestamp,
                    );
                }

                // Also report unknown third-party hooks as info.
                if ( ! $is_suspicious ) {
                    $findings[] = array(
                        'source'    => 'wp_cron',
                        'row_id'    => 0,
                        'label'     => sprintf(
                            __( 'Third-party cron job: %s', 'wp-file-guardian' ),
                            $hook
                        ),
                        'severity'  => 'info',
                        'hook'      => $hook,
                        'timestamp' => $timestamp,
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * Save DB scan results to custom table.
     */
    public static function save_results( $session_id, $findings ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_db_scan_results';

        foreach ( $findings as $f ) {
            $wpdb->insert( $table, array(
                'session_id'  => $session_id,
                'source'      => sanitize_text_field( $f['source'] ),
                'row_id'      => absint( $f['row_id'] ),
                'severity'    => sanitize_text_field( $f['severity'] ),
                'description' => sanitize_textarea_field( $f['label'] ),
                'created_at'  => current_time( 'mysql' ),
            ), array( '%d', '%s', '%d', '%s', '%s', '%s' ) );
        }
    }

    /**
     * Get saved DB scan results.
     */
    public static function get_results( $session_id, $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_db_scan_results';

        $defaults = array( 'per_page' => 50, 'page' => 1, 'severity' => '' );
        $args     = wp_parse_args( $args, $defaults );

        $where  = array( 'session_id = %d' );
        $values = array( $session_id );

        if ( $args['severity'] ) {
            $where[]  = 'severity = %s';
            $values[] = $args['severity'];
        }

        $where_sql = implode( ' AND ', $where );
        $offset    = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit     = absint( $args['per_page'] );

        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $values ) );
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY FIELD(severity,'critical','warning','info') ASC LIMIT %d OFFSET %d",
            array_merge( $values, array( $limit, $offset ) )
        ) );

        return array( 'total' => $total, 'items' => $items );
    }

    /**
     * Get latest DB scan session ID.
     */
    public static function get_latest_session() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT MAX(session_id) FROM {$wpdb->prefix}wpfg_db_scan_results"
        );
    }
}
