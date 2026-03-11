<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vulnerability scanner for plugins and themes.
 * Checks versions against WordPress.org and WPScan APIs.
 */
class WPFG_Vuln_Scanner {

    /**
     * Initialize cron hook for scheduled vulnerability scans.
     */
    public static function init() {
        add_action( 'wpfg_scheduled_vuln_scan', array( __CLASS__, 'scan' ) );
    }

    /**
     * Run a full vulnerability scan on all plugins and themes.
     *
     * @return array { plugins: array, themes: array, summary: array }
     */
    public static function scan() {
        $session = array(
            'started_at' => current_time( 'mysql' ),
            'user_id'    => get_current_user_id(),
        );

        $plugin_findings = self::check_plugins();
        $theme_findings  = self::check_themes();

        $all_findings = array_merge( $plugin_findings, $theme_findings );

        self::save_results( $session, $all_findings );

        WPFG_Logger::log( 'vuln_scan', '', 'success', sprintf(
            'Vulnerability scan completed: %d plugins, %d themes checked, %d issues found.',
            count( get_plugins() ),
            count( wp_get_themes() ),
            count( $all_findings )
        ) );

        return array(
            'plugins' => $plugin_findings,
            'themes'  => $theme_findings,
            'summary' => self::get_summary(),
        );
    }

    /**
     * Check all installed plugins for vulnerabilities and outdated versions.
     *
     * @return array
     */
    public static function check_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins  = get_plugins();
        $findings = array();

        foreach ( $plugins as $plugin_file => $plugin_data ) {
            $slug = self::extract_slug( $plugin_file );
            if ( empty( $slug ) ) {
                continue;
            }

            $installed_version = $plugin_data['Version'];
            $item = array(
                'slug'              => $slug,
                'name'              => $plugin_data['Name'],
                'type'              => 'plugin',
                'installed_version' => $installed_version,
                'latest_version'    => $installed_version,
                'vulnerabilities'   => array(),
                'severity'          => 'none',
                'status'            => 'secure',
            );

            // Check WordPress.org API for latest version.
            $wp_api = self::query_wordpress_api( $slug, 'plugin' );
            if ( $wp_api && ! empty( $wp_api['version'] ) ) {
                $item['latest_version'] = $wp_api['version'];
                if ( version_compare( $installed_version, $wp_api['version'], '<' ) ) {
                    $item['status']   = 'outdated';
                    $item['severity'] = 'low';
                }
            }

            // Check WPScan API if token is configured.
            $wpscan_token = WPFG_Settings::get( 'wpscan_api_token', '' );
            if ( ! empty( $wpscan_token ) ) {
                $vulns = self::query_wpscan_api( $slug, 'plugin' );
                if ( ! empty( $vulns ) ) {
                    foreach ( $vulns as $vuln ) {
                        // Only include vulnerabilities affecting the installed version.
                        if ( ! empty( $vuln['fixed_in'] ) && version_compare( $installed_version, $vuln['fixed_in'], '>=' ) ) {
                            continue;
                        }
                        $item['vulnerabilities'][] = $vuln;
                    }

                    if ( ! empty( $item['vulnerabilities'] ) ) {
                        $item['status'] = 'vulnerable';
                        // Determine highest severity from vulnerabilities.
                        $item['severity'] = self::highest_severity( $item['vulnerabilities'] );
                    }
                }
            }

            if ( 'secure' !== $item['status'] ) {
                $findings[] = $item;
            }
        }

        return $findings;
    }

    /**
     * Check all installed themes for vulnerabilities and outdated versions.
     *
     * @return array
     */
    public static function check_themes() {
        $themes   = wp_get_themes();
        $findings = array();

        foreach ( $themes as $slug => $theme ) {
            $installed_version = $theme->get( 'Version' );
            $item = array(
                'slug'              => $slug,
                'name'              => $theme->get( 'Name' ),
                'type'              => 'theme',
                'installed_version' => $installed_version,
                'latest_version'    => $installed_version,
                'vulnerabilities'   => array(),
                'severity'          => 'none',
                'status'            => 'secure',
            );

            // Check WordPress.org API for latest version.
            $wp_api = self::query_wordpress_api( $slug, 'theme' );
            if ( $wp_api && ! empty( $wp_api['version'] ) ) {
                $item['latest_version'] = $wp_api['version'];
                if ( version_compare( $installed_version, $wp_api['version'], '<' ) ) {
                    $item['status']   = 'outdated';
                    $item['severity'] = 'low';
                }
            }

            // Check WPScan API if token is configured.
            $wpscan_token = WPFG_Settings::get( 'wpscan_api_token', '' );
            if ( ! empty( $wpscan_token ) ) {
                $vulns = self::query_wpscan_api( $slug, 'theme' );
                if ( ! empty( $vulns ) ) {
                    foreach ( $vulns as $vuln ) {
                        if ( ! empty( $vuln['fixed_in'] ) && version_compare( $installed_version, $vuln['fixed_in'], '>=' ) ) {
                            continue;
                        }
                        $item['vulnerabilities'][] = $vuln;
                    }

                    if ( ! empty( $item['vulnerabilities'] ) ) {
                        $item['status']   = 'vulnerable';
                        $item['severity'] = self::highest_severity( $item['vulnerabilities'] );
                    }
                }
            }

            if ( 'secure' !== $item['status'] ) {
                $findings[] = $item;
            }
        }

        return $findings;
    }

    /**
     * Query the WordPress.org API for plugin or theme information.
     *
     * @param string $slug Plugin or theme slug.
     * @param string $type 'plugin' or 'theme'.
     * @return array|null { version: string } or null on failure.
     */
    public static function query_wordpress_api( $slug, $type = 'plugin' ) {
        if ( 'theme' === $type ) {
            $url = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=' . urlencode( $slug );
        } else {
            $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . urlencode( $slug );
        }

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data ) || isset( $data['error'] ) ) {
            return null;
        }

        return array(
            'version' => isset( $data['version'] ) ? $data['version'] : '',
        );
    }

    /**
     * Query the WPScan API for known vulnerabilities.
     *
     * @param string $slug Plugin or theme slug.
     * @param string $type 'plugin' or 'theme'.
     * @return array List of vulnerability arrays.
     */
    public static function query_wpscan_api( $slug, $type = 'plugin' ) {
        $token = WPFG_Settings::get( 'wpscan_api_token', '' );
        if ( empty( $token ) ) {
            return array();
        }

        $endpoint = 'https://wpscan.com/api/v3/' . urlencode( $type ) . 's/' . urlencode( $slug );

        $response = wp_remote_get( $endpoint, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Token token=' . $token,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data ) || ! is_array( $data ) ) {
            return array();
        }

        // WPScan returns { slug: { vulnerabilities: [...] } }.
        $key = array_key_first( $data );
        if ( ! $key || empty( $data[ $key ]['vulnerabilities'] ) ) {
            return array();
        }

        $vulns = array();
        foreach ( $data[ $key ]['vulnerabilities'] as $v ) {
            $severity = 'medium';
            if ( ! empty( $v['cvss']['score'] ) ) {
                $score = (float) $v['cvss']['score'];
                if ( $score >= 9.0 ) {
                    $severity = 'critical';
                } elseif ( $score >= 7.0 ) {
                    $severity = 'high';
                } elseif ( $score >= 4.0 ) {
                    $severity = 'medium';
                } else {
                    $severity = 'low';
                }
            }

            $vulns[] = array(
                'title'    => isset( $v['title'] ) ? $v['title'] : __( 'Unknown vulnerability', 'wp-file-guardian' ),
                'fixed_in' => isset( $v['fixed_in'] ) ? $v['fixed_in'] : '',
                'severity' => $severity,
                'cve'      => ! empty( $v['references']['cve'] ) ? $v['references']['cve'][0] : '',
                'url'      => ! empty( $v['references']['url'] ) ? $v['references']['url'][0] : '',
            );
        }

        return $vulns;
    }

    /**
     * Save scan results to the database.
     *
     * @param array $session Session metadata.
     * @param array $items   Finding items.
     */
    public static function save_results( $session, $items ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_vulnerabilities';

        // Clear previous results.
        $wpdb->query( "TRUNCATE TABLE {$table}" );

        foreach ( $items as $item ) {
            $wpdb->insert( $table, array(
                'slug'              => sanitize_text_field( $item['slug'] ),
                'name'              => sanitize_text_field( $item['name'] ),
                'type'              => sanitize_text_field( $item['type'] ),
                'installed_version' => sanitize_text_field( $item['installed_version'] ),
                'latest_version'    => sanitize_text_field( $item['latest_version'] ),
                'vulnerabilities'   => wp_json_encode( $item['vulnerabilities'] ),
                'severity'          => sanitize_text_field( $item['severity'] ),
                'status'            => sanitize_text_field( $item['status'] ),
                'scanned_at'        => isset( $session['started_at'] ) ? $session['started_at'] : current_time( 'mysql' ),
            ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        }
    }

    /**
     * Get paginated vulnerability results.
     *
     * @param array $args Query arguments.
     * @return array { total: int, items: array }
     */
    public static function get_results( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_vulnerabilities';

        $defaults = array(
            'severity' => '',
            'type'     => '',
            'status'   => '',
            'search'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'severity',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( $args['severity'] ) {
            $where[]  = 'severity = %s';
            $values[] = $args['severity'];
        }
        if ( $args['type'] ) {
            $where[]  = 'type = %s';
            $values[] = $args['type'];
        }
        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }
        if ( $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(name LIKE %s OR slug LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        // Custom severity ordering.
        $allowed_order = array( 'id', 'name', 'slug', 'type', 'severity', 'status', 'scanned_at' );
        $orderby = in_array( $args['orderby'], $allowed_order, true ) ? $args['orderby'] : 'severity';

        if ( 'severity' === $orderby ) {
            $orderby = "FIELD(severity, 'critical', 'high', 'medium', 'low', 'none')";
            $order   = 'ASC';
        } else {
            $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        }

        $offset = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit  = absint( $args['per_page'] );

        if ( ! empty( $values ) ) {
            $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $values ) );
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                array_merge( $values, array( $limit, $offset ) )
            ) );
        } else {
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $limit, $offset
            ) );
        }

        // Decode vulnerabilities JSON for each item.
        foreach ( $items as &$item ) {
            $item->vulnerabilities = json_decode( $item->vulnerabilities, true );
            if ( ! is_array( $item->vulnerabilities ) ) {
                $item->vulnerabilities = array();
            }
        }

        return array( 'total' => $total, 'items' => $items );
    }

    /**
     * Get summary counts by severity.
     *
     * @return array
     */
    public static function get_summary() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_vulnerabilities';

        $total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $critical = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE severity = 'critical'" );
        $high     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE severity = 'high'" );
        $medium   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE severity = 'medium'" );
        $low      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE severity = 'low'" );
        $outdated = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'outdated'" );
        $last_scan = $wpdb->get_var( "SELECT scanned_at FROM {$table} ORDER BY scanned_at DESC LIMIT 1" );

        return array(
            'total'     => $total,
            'critical'  => $critical,
            'high'      => $high,
            'medium'    => $medium,
            'low'       => $low,
            'outdated'  => $outdated,
            'last_scan' => $last_scan,
        );
    }

    /**
     * Update a plugin or theme using WP core upgraders.
     *
     * @param string $slug Plugin file or theme slug.
     * @param string $type 'plugin' or 'theme'.
     * @return bool|WP_Error
     */
    public static function update_item( $slug, $type = 'plugin' ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $skin = new Automatic_Upgrader_Skin();

        if ( 'theme' === $type ) {
            $upgrader = new Theme_Upgrader( $skin );
            $result   = $upgrader->upgrade( $slug );
        } else {
            $upgrader = new Plugin_Upgrader( $skin );
            $result   = $upgrader->upgrade( $slug );
        }

        if ( is_wp_error( $result ) ) {
            WPFG_Logger::log( 'vuln_update', $slug, 'error', $result->get_error_message() );
            return $result;
        }

        if ( false === $result ) {
            $error_msg = __( 'Update failed. The item may not be hosted on WordPress.org.', 'wp-file-guardian' );
            WPFG_Logger::log( 'vuln_update', $slug, 'error', $error_msg );
            return new WP_Error( 'update_failed', $error_msg );
        }

        WPFG_Logger::log( 'vuln_update', $slug, 'success', sprintf( 'Updated %s: %s', $type, $slug ) );
        return true;
    }

    /**
     * Ignore a vulnerability by setting its status to 'ignored'.
     *
     * @param int $id Row ID in wpfg_vulnerabilities table.
     */
    public static function ignore_vuln( $id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'wpfg_vulnerabilities',
            array( 'status' => 'ignored' ),
            array( 'id' => absint( $id ) ),
            array( '%s' ),
            array( '%d' )
        );
        WPFG_Logger::log( 'vuln_ignore', (string) $id, 'success', 'Vulnerability marked as ignored' );
    }

    /**
     * Extract plugin slug from plugin file path (e.g., "akismet/akismet.php" -> "akismet").
     *
     * @param string $plugin_file
     * @return string
     */
    private static function extract_slug( $plugin_file ) {
        if ( strpos( $plugin_file, '/' ) !== false ) {
            return dirname( $plugin_file );
        }
        return basename( $plugin_file, '.php' );
    }

    /**
     * Determine the highest severity from a list of vulnerabilities.
     *
     * @param array $vulns
     * @return string
     */
    private static function highest_severity( $vulns ) {
        $priority = array( 'critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1 );
        $max      = 0;
        $result   = 'low';

        foreach ( $vulns as $v ) {
            $sev = isset( $v['severity'] ) ? $v['severity'] : 'medium';
            $val = isset( $priority[ $sev ] ) ? $priority[ $sev ] : 0;
            if ( $val > $max ) {
                $max    = $val;
                $result = $sev;
            }
        }

        return $result;
    }
}
