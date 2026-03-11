<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * File scanning engine.
 * Supports chunked / resumable scanning for large sites.
 */
class WPFG_Scanner {

    /**
     * Create a new scan session.
     *
     * @param string $scan_type 'full', 'quick', 'custom'
     * @param array  $options   Optional overrides.
     * @return int Session ID.
     */
    public static function create_session( $scan_type = 'full', $options = array() ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wpfg_scan_sessions',
            array(
                'user_id'    => get_current_user_id(),
                'status'     => 'pending',
                'scan_type'  => sanitize_text_field( $scan_type ),
                'options'    => wp_json_encode( $options ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );
        return $wpdb->insert_id;
    }

    /**
     * Get a scan session by ID.
     */
    public static function get_session( $session_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_scan_sessions WHERE id = %d",
            $session_id
        ) );
    }

    /**
     * Get the latest scan session.
     */
    public static function get_latest_session() {
        global $wpdb;
        return $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}wpfg_scan_sessions ORDER BY id DESC LIMIT 1"
        );
    }

    /**
     * Update session status.
     */
    public static function update_session( $session_id, $data ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'wpfg_scan_sessions',
            $data,
            array( 'id' => $session_id ),
            null,
            array( '%d' )
        );
    }

    /**
     * Collect all files to be scanned and store count.
     * Returns array of file paths.
     */
    public static function collect_files( $session_id ) {
        $settings  = get_option( 'wpfg_settings', WPFG_Settings::defaults() );
        $session   = self::get_session( $session_id );
        $sess_opts = $session && $session->options ? json_decode( $session->options, true ) : array();
        $scan_type = $session ? $session->scan_type : 'full';

        // Quick scan: only wp-content. Full scan: all configured paths.
        if ( 'quick' === $scan_type ) {
            $scan_paths = array( 'wp-content' );
        } else {
            $scan_paths = ! empty( $sess_opts['scan_paths'] ) ? $sess_opts['scan_paths'] : $settings['scan_paths'];
        }
        $excluded   = ! empty( $sess_opts['excluded_paths'] ) ? $sess_opts['excluded_paths'] : $settings['excluded_paths'];
        $skip_ext   = ! empty( $sess_opts['excluded_extensions'] ) ? $sess_opts['excluded_extensions'] : $settings['excluded_extensions'];

        // Always exclude the plugin's own directory to avoid false positives
        // (our malware pattern definitions contain the very strings we scan for).
        $self_dir = wp_normalize_path( WPFG_PLUGIN_DIR );

        $files = array();
        foreach ( (array) $scan_paths as $rel_path ) {
            $abs_path = ABSPATH . ltrim( $rel_path, '/' );
            if ( ! is_dir( $abs_path ) ) {
                if ( is_file( $abs_path ) ) {
                    $norm = wp_normalize_path( $abs_path );
                    if ( strpos( $norm, $self_dir ) !== 0 ) {
                        $files[] = $norm;
                    }
                }
                continue;
            }
            foreach ( WPFG_Filesystem::scan_directory( $abs_path, $excluded, $skip_ext ) as $file ) {
                $norm = wp_normalize_path( $file );
                if ( strpos( $norm, $self_dir ) !== 0 ) {
                    $files[] = $norm;
                }
            }
        }

        // Update session with total count.
        self::update_session( $session_id, array(
            'total_files' => count( $files ),
            'status'      => 'collecting',
            'started_at'  => current_time( 'mysql' ),
        ) );

        // Store file list in a transient for batch processing.
        set_transient( 'wpfg_scan_files_' . $session_id, $files, HOUR_IN_SECONDS );

        return $files;
    }

    /**
     * Process a batch of files for a given session.
     *
     * @param int $session_id
     * @param int $offset     Starting index.
     * @param int $batch_size Number of files per batch.
     * @return array { processed: int, total: int, done: bool }
     */
    public static function process_batch( $session_id, $offset = 0, $batch_size = 0 ) {
        if ( ! $batch_size ) {
            $batch_size = (int) WPFG_Settings::get( 'batch_size', 500 );
        }

        $files = get_transient( 'wpfg_scan_files_' . $session_id );
        if ( ! is_array( $files ) ) {
            return array( 'processed' => 0, 'total' => 0, 'done' => true, 'error' => 'no_files' );
        }

        $total       = count( $files );
        $batch       = array_slice( $files, $offset, $batch_size );
        $settings    = get_option( 'wpfg_settings', WPFG_Settings::defaults() );
        $sensitivity = ! empty( $settings['scan_sensitivity'] ) ? $settings['scan_sensitivity'] : 'medium';
        $max_size    = ! empty( $settings['max_file_size_scan'] ) ? (int) $settings['max_file_size_scan'] : 10485760;
        $large_threshold = ! empty( $settings['large_file_threshold'] ) ? (int) $settings['large_file_threshold'] : 5242880;
        $issues      = 0;
        $scanned_size = 0;

        // Build a set of verified plugin/theme slugs from wordpress.org
        // to avoid false positives on legitimate code.
        $verified_slugs = self::get_verified_slugs();
        $plugins_dir    = wp_normalize_path( WP_PLUGIN_DIR . '/' );
        $themes_dir     = wp_normalize_path( get_theme_root() . '/' );

        self::update_session( $session_id, array( 'status' => 'scanning' ) );

        foreach ( $batch as $file_path ) {
            if ( ! file_exists( $file_path ) ) {
                continue;
            }

            $info   = WPFG_Filesystem::file_info( $file_path );
            $size   = $info['size'];
            $scanned_size += $size;
            $findings    = array();
            $notices     = array(); // Non-threat observations (recently modified, large file, etc.)

            // Determine if file belongs to a verified wordpress.org plugin or theme.
            $is_verified = self::is_verified_path( $file_path, $plugins_dir, $themes_dir, $verified_slugs );

            // Check for hidden files.
            $basename = basename( $file_path );
            if ( strpos( $basename, '.' ) === 0 && '.htaccess' !== $basename ) {
                $notices[] = array( 'type' => 'hidden_file', 'severity' => 'info', 'desc' => __( 'Hidden file detected.', 'wp-file-guardian' ) );
            }

            // Check for unusually large files.
            if ( $size > $large_threshold ) {
                $notices[] = array( 'type' => 'large_file', 'severity' => 'info', 'desc' => sprintf( __( 'Large file: %s', 'wp-file-guardian' ), WPFG_Helpers::format_bytes( $size ) ) );
            }

            // Writable sensitive files.
            if ( in_array( $basename, WPFG_Helpers::sensitive_files(), true ) && $info['is_writable'] ) {
                $findings[] = array( 'type' => 'writable_sensitive', 'severity' => 'warning', 'desc' => __( 'Sensitive file is writable.', 'wp-file-guardian' ) );
            }

            // Recently modified (last 24h) — informational only, not a threat.
            if ( $info['modified'] > ( time() - DAY_IN_SECONDS ) ) {
                $notices[] = array( 'type' => 'recently_modified', 'severity' => 'info', 'desc' => __( 'File modified in the last 24 hours.', 'wp-file-guardian' ) );
            }

            // Duplicate backup / archive / temp files.
            $ext = $info['extension'];
            if ( in_array( $ext, array( 'bak', 'old', 'orig', 'tmp', 'temp', 'swp', 'log' ), true ) ) {
                $notices[] = array( 'type' => 'temp_file', 'severity' => 'info', 'desc' => __( 'Temporary or backup file.', 'wp-file-guardian' ) );
            }
            if ( in_array( $ext, array( 'zip', 'tar', 'gz', 'sql' ), true ) ) {
                $findings[] = array( 'type' => 'archive_file', 'severity' => 'warning', 'desc' => __( 'Archive or database dump found.', 'wp-file-guardian' ) );
            }

            // PHP files: scan for malware patterns.
            if ( WPFG_Helpers::is_php_file( $file_path ) && $size <= $max_size ) {
                $content = file_get_contents( $file_path );
                if ( false !== $content ) {
                    $mal_findings = WPFG_Malware_Patterns::scan_content( $content, $sensitivity );
                    foreach ( $mal_findings as $mf ) {
                        $severity = $mf['severity'];
                        // For verified wordpress.org plugins/themes, downgrade
                        // pattern matches from critical/warning to info — these
                        // are almost certainly false positives (e.g., Google Site Kit
                        // uses exec() in its Composer autoloader).
                        if ( $is_verified && in_array( $severity, array( 'critical', 'warning' ), true ) ) {
                            $severity = 'info';
                        }
                        $findings[] = array(
                            'type'     => 'malware_pattern',
                            'severity' => $severity,
                            'desc'     => $mf['label'] . ( $is_verified ? ' ' . __( '[verified plugin — likely false positive]', 'wp-file-guardian' ) : '' ),
                        );
                    }
                }
            }

            // Suspicious uploads (PHP in uploads directory).
            $uploads_dir = wp_normalize_path( wp_upload_dir()['basedir'] );
            if ( WPFG_Helpers::is_php_file( $file_path ) && strpos( wp_normalize_path( $file_path ), $uploads_dir ) === 0 ) {
                // Skip empty index.php files — these are standard WordPress
                // directory listing protection files, not threats.
                $is_empty_index = ( 'index.php' === $basename && $size <= 50 );

                // Skip known plugin directories that legitimately store PHP in uploads.
                $known_plugin_dirs = array(
                    '/sucuri/',
                    '/starter-templates/',
                    '/template-kits/',
                    '/elementor/',
                    '/woocommerce_uploads/',
                    '/wpforms/',
                    '/wp-file-guardian/',
                    '/wpfg/',
                );
                $is_known = false;
                $norm_path = wp_normalize_path( $file_path );
                foreach ( $known_plugin_dirs as $dir ) {
                    if ( strpos( $norm_path, $uploads_dir . $dir ) === 0 ) {
                        $is_known = true;
                        break;
                    }
                }
                if ( $is_empty_index ) {
                    // Empty index.php — standard WP directory protection, not a threat.
                } elseif ( ! $is_known ) {
                    $findings[] = array( 'type' => 'suspicious_upload', 'severity' => 'critical', 'desc' => __( 'PHP file in uploads directory.', 'wp-file-guardian' ) );
                } else {
                    $notices[] = array( 'type' => 'known_plugin_upload', 'severity' => 'info', 'desc' => __( 'PHP file in known plugin uploads directory.', 'wp-file-guardian' ) );
                }
            }

            // Store threat findings (malware patterns, suspicious files, etc.).
            if ( ! empty( $findings ) ) {
                $max_severity = 'info';
                $descs        = array();
                foreach ( $findings as $f ) {
                    $descs[] = $f['desc'];
                    if ( 'critical' === $f['severity'] ) {
                        $max_severity = 'critical';
                    } elseif ( 'warning' === $f['severity'] && 'critical' !== $max_severity ) {
                        $max_severity = 'warning';
                    }
                }
                // Append notice descriptions as context (but they don't affect severity).
                $notice_descs = array();
                foreach ( $notices as $n ) {
                    $notice_descs[] = $n['desc'];
                }
                $full_desc = implode( '; ', $descs );
                if ( ! empty( $notice_descs ) ) {
                    $full_desc .= ' [' . implode( '; ', $notice_descs ) . ']';
                }
                self::save_result( $session_id, $file_path, $info, $max_severity, $findings[0]['type'], $full_desc );
                $issues++;

                // Auto-quarantine critical files if enabled.
                if ( 'critical' === $max_severity && WPFG_Settings::get( 'auto_quarantine_critical', false ) ) {
                    $q_result = WPFG_Quarantine::quarantine_file( $file_path, implode( '; ', $descs ) );
                    if ( ! is_wp_error( $q_result ) ) {
                        WPFG_Logger::log( 'auto_quarantine', $file_path, 'success', implode( '; ', $descs ) );
                    }
                }

                // Note: Email notifications are sent once when the scan completes,
                // not per-file. See WPFG_Cron::run_scheduled_scan() and AJAX scan completion.
            }
            // Non-threat notices (recently modified, large file, temp file, etc.)
            // are NOT stored as scan results — they only appear as context when
            // attached to a real finding above. Storing them separately would
            // flood the results table with harmless informational entries and
            // mislead users into thinking their site has security issues.
        }

        $processed = $offset + count( $batch );
        $done      = $processed >= $total;

        // Update session progress.
        $update = array(
            'scanned_files' => $processed,
            'issues_found'  => self::count_session_issues( $session_id ),
        );

        if ( $done ) {
            $update['status']       = 'completed';
            $update['completed_at'] = current_time( 'mysql' );
            $update['total_size']   = self::get_session_total_size( $session_id );
            delete_transient( 'wpfg_scan_files_' . $session_id );
            WPFG_Logger::log( 'scan_completed', '', 'success', sprintf( 'Session %d: %d files scanned, %d issues.', $session_id, $total, $update['issues_found'] ) );
        }

        self::update_session( $session_id, $update );

        return array(
            'processed' => $processed,
            'total'     => $total,
            'done'      => $done,
            'issues'    => $issues,
        );
    }

    /**
     * Save a scan result to the database.
     */
    private static function save_result( $session_id, $file_path, $info, $severity, $issue_type, $description ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wpfg_scan_results',
            array(
                'session_id'       => $session_id,
                'file_path'        => wp_normalize_path( $file_path ),
                'file_size'        => $info['size'],
                'file_modified'    => gmdate( 'Y-m-d H:i:s', $info['modified'] ),
                'file_permissions' => $info['permissions'],
                'file_hash'        => $info['hash'],
                'file_type'        => $info['extension'],
                'severity'         => $severity,
                'issue_type'       => $issue_type,
                'description'      => $description,
                'created_at'       => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Count issues for a session.
     */
    private static function count_session_issues( $session_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpfg_scan_results WHERE session_id = %d",
            $session_id
        ) );
    }

    /**
     * Get total scanned size from results.
     */
    private static function get_session_total_size( $session_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(file_size) FROM {$wpdb->prefix}wpfg_scan_results WHERE session_id = %d",
            $session_id
        ) );
    }

    /**
     * Get scan results for a session with filters.
     */
    public static function get_results( $session_id, $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_scan_results';

        $defaults = array(
            'severity'  => '',
            'issue_type'=> '',
            'search'    => '',
            'ignored'   => '',
            'per_page'  => 20,
            'page'      => 1,
            'orderby'   => 'severity',
            'order'     => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( 'session_id = %d' );
        $values = array( $session_id );

        if ( $args['severity'] ) {
            $where[]  = 'severity = %s';
            $values[] = $args['severity'];
        }
        if ( $args['issue_type'] ) {
            $where[]  = 'issue_type = %s';
            $values[] = $args['issue_type'];
        }
        if ( '' !== $args['ignored'] ) {
            $where[]  = 'is_ignored = %d';
            $values[] = (int) $args['ignored'];
        }
        if ( $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(file_path LIKE %s OR description LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        $allowed_order = array( 'id', 'file_path', 'file_size', 'severity', 'issue_type', 'file_modified', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'severity';

        // Custom severity ordering.
        if ( 'severity' === $orderby ) {
            $orderby = "FIELD(severity, 'critical', 'warning', 'info', 'notice')";
            $order   = 'ASC'; // critical first.
        } else {
            $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        }

        $offset = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit  = absint( $args['per_page'] );

        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $values ) );
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            array_merge( $values, array( $limit, $offset ) )
        ) );

        return array( 'total' => $total, 'items' => $items );
    }

    /**
     * Mark a result as ignored/trusted.
     */
    public static function ignore_result( $result_id, $ignore = true ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'wpfg_scan_results',
            array( 'is_ignored' => $ignore ? 1 : 0 ),
            array( 'id' => $result_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    /**
     * Get dashboard statistics from the latest scan.
     */
    public static function get_dashboard_stats() {
        $session = self::get_latest_session();
        if ( ! $session ) {
            return array(
                'total_files'     => 0,
                'total_size'      => 0,
                'issues_found'    => 0,
                'critical_count'  => 0,
                'warning_count'   => 0,
                'info_count'      => 0,
                'last_scan'       => null,
                'scan_status'     => 'never',
            );
        }

        global $wpdb;
        $session_id = $session->id;
        $prefix     = $wpdb->prefix;

        $critical = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}wpfg_scan_results WHERE session_id = %d AND severity = 'critical' AND is_ignored = 0",
            $session_id
        ) );
        $warning = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}wpfg_scan_results WHERE session_id = %d AND severity = 'warning' AND is_ignored = 0",
            $session_id
        ) );
        $info = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}wpfg_scan_results WHERE session_id = %d AND severity = 'info' AND is_ignored = 0",
            $session_id
        ) );
        $notice = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}wpfg_scan_results WHERE session_id = %d AND severity = 'notice' AND is_ignored = 0",
            $session_id
        ) );

        return array(
            'total_files'    => (int) $session->total_files,
            'total_size'     => (int) $session->total_size,
            'issues_found'   => (int) $session->issues_found,
            'critical_count' => $critical,
            'warning_count'  => $warning,
            'info_count'     => $info,
            'notice_count'   => $notice,
            'last_scan'      => $session->completed_at ?? $session->started_at,
            'scan_status'    => $session->status,
            'session_id'     => $session_id,
        );
    }

    /**
     * Cancel a running scan.
     */
    public static function cancel_session( $session_id ) {
        self::update_session( $session_id, array( 'status' => 'cancelled' ) );
        delete_transient( 'wpfg_scan_files_' . $session_id );
    }

    /**
     * Get a set of verified plugin/theme slugs from wordpress.org.
     * Uses cached results to avoid repeated API calls during a scan.
     *
     * @return array Associative array of slug => true.
     */
    private static function get_verified_slugs() {
        $cached = get_transient( 'wpfg_verified_slugs' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $slugs = array();

        // Get all installed plugins and check which ones come from wordpress.org.
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $update_data = get_site_transient( 'update_plugins' );

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $slug = dirname( $plugin_file );
            if ( '.' === $slug ) {
                $slug = basename( $plugin_file, '.php' );
            }
            // A plugin is from wordpress.org if it appears in the update check data
            // (either as up-to-date, or with an available update).
            $in_repo = false;
            if ( $update_data ) {
                if ( isset( $update_data->response[ $plugin_file ] ) ||
                     isset( $update_data->no_update[ $plugin_file ] ) ) {
                    $in_repo = true;
                }
            }
            if ( $in_repo ) {
                $slugs[ $slug ] = true;
            }
        }

        // Get all installed themes and check which come from wordpress.org.
        $all_themes  = wp_get_themes();
        $theme_data  = get_site_transient( 'update_themes' );
        foreach ( $all_themes as $theme_slug => $theme_obj ) {
            $in_repo = false;
            if ( $theme_data ) {
                if ( isset( $theme_data->response[ $theme_slug ] ) ||
                     isset( $theme_data->no_update[ $theme_slug ] ) ) {
                    $in_repo = true;
                }
            }
            if ( $in_repo ) {
                $slugs[ $theme_slug ] = true;
            }
        }

        // Cache for 12 hours.
        set_transient( 'wpfg_verified_slugs', $slugs, 12 * HOUR_IN_SECONDS );

        return $slugs;
    }

    /**
     * Check if a file path belongs to a verified wordpress.org plugin or theme.
     *
     * @param string $file_path   Normalized absolute file path.
     * @param string $plugins_dir Normalized plugins directory with trailing slash.
     * @param string $themes_dir  Normalized themes directory with trailing slash.
     * @param array  $verified    Associative array of verified slugs.
     * @return bool
     */
    private static function is_verified_path( $file_path, $plugins_dir, $themes_dir, $verified ) {
        $norm = wp_normalize_path( $file_path );

        // Check plugins.
        if ( strpos( $norm, $plugins_dir ) === 0 ) {
            $relative = substr( $norm, strlen( $plugins_dir ) );
            $slug = strtok( $relative, '/' );
            if ( $slug && isset( $verified[ $slug ] ) ) {
                return true;
            }
        }

        // Check themes.
        if ( strpos( $norm, $themes_dir ) === 0 ) {
            $relative = substr( $norm, strlen( $themes_dir ) );
            $slug = strtok( $relative, '/' );
            if ( $slug && isset( $verified[ $slug ] ) ) {
                return true;
            }
        }

        // WordPress core files (wp-admin, wp-includes) are also trusted.
        $wp_admin    = wp_normalize_path( ABSPATH . 'wp-admin/' );
        $wp_includes = wp_normalize_path( ABSPATH . 'wp-includes/' );
        if ( strpos( $norm, $wp_admin ) === 0 || strpos( $norm, $wp_includes ) === 0 ) {
            return true;
        }

        return false;
    }
}
