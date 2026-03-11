<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin controller — registers menus, enqueues assets, renders pages.
 */
class WPFG_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( 'WPFG_Notifications', 'display_admin_notices' ) );
    }

    /**
     * Register admin menu and submenus.
     */
    public static function register_menus() {
        $cap = WPFG_Helpers::get_capability();

        add_menu_page(
            __( 'File Guardian', 'wp-file-guardian' ),
            __( 'File Guardian', 'wp-file-guardian' ),
            $cap,
            'wpfg-dashboard',
            array( __CLASS__, 'page_dashboard' ),
            'dashicons-shield-alt',
            80
        );

        $subpages = array(
            'wpfg-dashboard'    => __( 'Dashboard', 'wp-file-guardian' ),
            'wpfg-scanner'      => __( 'File Scanner', 'wp-file-guardian' ),
            'wpfg-db-scanner'   => __( 'DB Scanner', 'wp-file-guardian' ),
            'wpfg-monitor'      => __( 'File Monitor', 'wp-file-guardian' ),
            'wpfg-files'        => __( 'File Manager', 'wp-file-guardian' ),
            'wpfg-quarantine'   => __( 'Quarantine', 'wp-file-guardian' ),
            'wpfg-backups'      => __( 'Backups', 'wp-file-guardian' ),
            'wpfg-repair'       => __( 'Repair', 'wp-file-guardian' ),
            'wpfg-login-guard'     => __( 'Login Guard', 'wp-file-guardian' ),
            'wpfg-hardening'       => __( 'Hardening', 'wp-file-guardian' ),
            'wpfg-firewall'        => __( 'Firewall', 'wp-file-guardian' ),
            'wpfg-vulnerabilities' => __( 'Vulnerabilities', 'wp-file-guardian' ),
            'wpfg-security-score'  => __( 'Security Score', 'wp-file-guardian' ),
            'wpfg-two-factor'      => __( 'Two-Factor Auth', 'wp-file-guardian' ),
            'wpfg-permissions'     => __( 'Permissions', 'wp-file-guardian' ),
            'wpfg-logs'            => __( 'Logs', 'wp-file-guardian' ),
            'wpfg-settings'        => __( 'Settings', 'wp-file-guardian' ),
            'wpfg-sysinfo'         => __( 'System Info', 'wp-file-guardian' ),
        );

        foreach ( $subpages as $slug => $title ) {
            add_submenu_page(
                'wpfg-dashboard',
                $title . ' — ' . __( 'File Guardian', 'wp-file-guardian' ),
                $title,
                $cap,
                $slug,
                array( __CLASS__, 'page_' . str_replace( '-', '_', str_replace( 'wpfg-', '', $slug ) ) )
            );
        }
    }

    /**
     * Enqueue admin CSS and JS on our pages only.
     */
    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wpfg-' ) === false && strpos( $hook, 'wpfg_' ) === false ) {
            // Check if our page slug appears.
            $screen = get_current_screen();
            if ( ! $screen || strpos( $screen->id, 'wpfg' ) === false ) {
                return;
            }
        }

        wp_enqueue_style(
            'wpfg-admin',
            WPFG_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            WPFG_VERSION
        );

        // Chart.js from CDN for dashboard and security score pages.
        $screen = get_current_screen();
        if ( $screen && ( strpos( $screen->id, 'wpfg-dashboard' ) !== false || strpos( $screen->id, 'wpfg-security-score' ) !== false ) ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', array(), '4.4.7', true );
        }

        // QR code library for 2FA setup.
        if ( $screen && strpos( $screen->id, 'wpfg-two-factor' ) !== false ) {
            wp_enqueue_script( 'qrcodejs', 'https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js', array(), '1.5.4', true );
        }

        wp_enqueue_script(
            'wpfg-admin',
            WPFG_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            WPFG_VERSION,
            true
        );

        wp_localize_script( 'wpfg-admin', 'wpfg', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpfg_ajax' ),
            'i18n'     => array(
                'confirm_delete'     => __( 'Are you sure you want to permanently delete this?', 'wp-file-guardian' ),
                'confirm_quarantine' => __( 'Move this file to quarantine?', 'wp-file-guardian' ),
                'confirm_restore'    => __( 'Restore this file to its original location?', 'wp-file-guardian' ),
                'confirm_repair'     => __( 'Proceed with repair? A restore point will be created first.', 'wp-file-guardian' ),
                'scanning'           => __( 'Scanning...', 'wp-file-guardian' ),
                'scan_complete'      => __( 'Scan complete.', 'wp-file-guardian' ),
                'error'              => __( 'An error occurred.', 'wp-file-guardian' ),
                'confirm_bulk'       => __( 'Apply this action to all selected items?', 'wp-file-guardian' ),
            ),
        ) );
    }

    // ---- Page renderers ----

    public static function page_dashboard() {
        if ( ! WPFG_Helpers::check_capability() ) {
            wp_die( esc_html__( 'Unauthorized.', 'wp-file-guardian' ) );
        }
        $stats = WPFG_Scanner::get_dashboard_stats();
        $quarantine_count = WPFG_Quarantine::count();
        $backup_list = WPFG_Backup::get_list( array( 'per_page' => 1 ) );
        $latest_backup = ! empty( $backup_list['items'] ) ? $backup_list['items'][0] : null;
        $risk = WPFG_Risk_Score::calculate();
        $login_stats = WPFG_Login_Guard::get_stats();
        $monitor_last = WPFG_File_Monitor::last_check();
        $monitor_count = WPFG_File_Monitor::baseline_count();
        $scan_history = self::get_scan_history_data();
        WPFG_Helpers::render_view( 'dashboard', compact(
            'stats', 'quarantine_count', 'latest_backup', 'risk',
            'login_stats', 'monitor_last', 'monitor_count', 'scan_history'
        ) );
    }

    /**
     * Get scan history for Chart.js (last 30 days).
     */
    private static function get_scan_history_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_scan_history';

        // Check table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return array( 'labels' => array(), 'critical' => array(), 'warning' => array(), 'info' => array(), 'score' => array() );
        }

        $rows = $wpdb->get_results(
            "SELECT scan_date, critical_count, warning_count, info_count, risk_score
             FROM {$table} ORDER BY scan_date DESC LIMIT 30"
        );

        $rows = array_reverse( $rows );
        $data = array( 'labels' => array(), 'critical' => array(), 'warning' => array(), 'info' => array(), 'score' => array() );
        foreach ( $rows as $r ) {
            $data['labels'][]   = $r->scan_date;
            $data['critical'][] = (int) $r->critical_count;
            $data['warning'][]  = (int) $r->warning_count;
            $data['info'][]     = (int) $r->info_count;
            $data['score'][]    = (int) $r->risk_score;
        }
        return $data;
    }

    public static function page_scanner() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $session_id = isset( $_GET['session'] ) ? absint( $_GET['session'] ) : 0;
        $session    = $session_id ? WPFG_Scanner::get_session( $session_id ) : null;
        $results    = null;
        if ( $session ) {
            $results = WPFG_Scanner::get_results( $session_id, array(
                'severity' => isset( $_GET['severity'] ) ? sanitize_text_field( $_GET['severity'] ) : '',
                'search'   => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
                'per_page' => 50,
                'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
            ) );
        }
        WPFG_Helpers::render_view( 'scanner', compact( 'session', 'session_id', 'results' ) );
    }

    public static function page_files() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        WPFG_Helpers::render_view( 'files' );
    }

    public static function page_quarantine() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $list = WPFG_Quarantine::get_list( array(
            'per_page' => 20,
            'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
        ) );
        WPFG_Helpers::render_view( 'quarantine', compact( 'list' ) );
    }

    public static function page_backups() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $list = WPFG_Backup::get_list( array(
            'per_page' => 20,
            'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
        ) );
        WPFG_Helpers::render_view( 'backups', compact( 'list' ) );
    }

    public static function page_repair() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        WPFG_Helpers::render_view( 'repair' );
    }

    public static function page_logs() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $logs = WPFG_Logger::query( array(
            'action'   => isset( $_GET['action_filter'] ) ? sanitize_text_field( $_GET['action_filter'] ) : '',
            'result'   => isset( $_GET['result'] ) ? sanitize_text_field( $_GET['result'] ) : '',
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
            'per_page' => 30,
            'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
        ) );
        WPFG_Helpers::render_view( 'logs', compact( 'logs' ) );
    }

    public static function page_settings() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );

        $saved   = false;
        $message = '';

        // Handle form submissions.
        if ( isset( $_POST['wpfg_save_settings'] ) && check_admin_referer( 'wpfg_settings_save' ) ) {
            WPFG_Settings::save( $_POST['wpfg'] ?? array() );
            WPFG_Logger::log( 'settings_change', '', 'success' );
            $saved = true;
        }
        if ( isset( $_POST['wpfg_reset_settings'] ) && check_admin_referer( 'wpfg_settings_save' ) ) {
            WPFG_Settings::reset();
            WPFG_Logger::log( 'settings_reset', '', 'success' );
            $message = __( 'Settings reset to defaults.', 'wp-file-guardian' );
        }
        if ( isset( $_POST['wpfg_import_settings'] ) && check_admin_referer( 'wpfg_settings_save' ) ) {
            $json = isset( $_POST['wpfg_import_json'] ) ? wp_unslash( $_POST['wpfg_import_json'] ) : '';
            $result = WPFG_Settings::import( $json );
            $message = $result ? __( 'Settings imported.', 'wp-file-guardian' ) : __( 'Invalid JSON.', 'wp-file-guardian' );
        }

        $settings = get_option( 'wpfg_settings', WPFG_Settings::defaults() );
        WPFG_Helpers::render_view( 'settings', compact( 'settings', 'saved', 'message' ) );
    }

    public static function page_sysinfo() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $info = WPFG_System_Info::get_info();
        WPFG_Helpers::render_view( 'system-info', compact( 'info' ) );
    }

    // ---- v2 page renderers ----

    public static function page_db_scanner() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $session_id = WPFG_DB_Scanner::get_latest_session();
        $results    = $session_id ? WPFG_DB_Scanner::get_results( $session_id, array(
            'severity' => isset( $_GET['severity'] ) ? sanitize_text_field( $_GET['severity'] ) : '',
            'per_page' => 50,
            'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
        ) ) : null;
        WPFG_Helpers::render_view( 'db-scanner', compact( 'session_id', 'results' ) );
    }

    public static function page_monitor() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $baseline_count = WPFG_File_Monitor::baseline_count();
        $last_check     = WPFG_File_Monitor::last_check();
        $history        = WPFG_File_Monitor::get_change_history( 20 );
        WPFG_Helpers::render_view( 'monitor', compact( 'baseline_count', 'last_check', 'history' ) );
    }

    public static function page_login_guard() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $stats = WPFG_Login_Guard::get_stats();
        $log   = WPFG_Login_Guard::get_log( array(
            'status'   => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
            'per_page' => 30,
            'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
        ) );
        WPFG_Helpers::render_view( 'login-guard', compact( 'stats', 'log' ) );
    }

    // ---- v3 page renderers ----

    public static function page_hardening() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $status = WPFG_Hardening::get_status();
        WPFG_Helpers::render_view( 'hardening', compact( 'status' ) );
    }

    public static function page_firewall() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );

        // Handle form POST actions.
        if ( isset( $_POST['wpfg_action'] ) ) {
            $action = sanitize_text_field( $_POST['wpfg_action'] );

            if ( 'add_firewall_rule' === $action && check_admin_referer( 'wpfg_firewall_add_rule', 'wpfg_fw_nonce' ) ) {
                WPFG_Firewall::add_rule(
                    sanitize_text_field( $_POST['rule_type'] ),
                    sanitize_text_field( $_POST['rule_value'] ),
                    sanitize_text_field( $_POST['rule_notes'] )
                );
                wp_safe_redirect( admin_url( 'admin.php?page=wpfg-firewall&msg=added' ) );
                exit;
            }

            if ( 'delete_firewall_rule' === $action && check_admin_referer( 'wpfg_firewall_delete_rule', 'wpfg_fw_nonce' ) ) {
                WPFG_Firewall::delete_rule( absint( $_POST['rule_id'] ) );
                wp_safe_redirect( admin_url( 'admin.php?page=wpfg-firewall&msg=deleted' ) );
                exit;
            }

            if ( 'toggle_firewall_rule' === $action && check_admin_referer( 'wpfg_firewall_toggle_rule', 'wpfg_fw_nonce' ) ) {
                WPFG_Firewall::toggle_rule( absint( $_POST['rule_id'] ), absint( $_POST['rule_active'] ) );
                wp_safe_redirect( admin_url( 'admin.php?page=wpfg-firewall&msg=updated' ) );
                exit;
            }
        }

        $stats = WPFG_Firewall::get_stats();
        $rules = WPFG_Firewall::get_rules( array(
            'per_page' => 20,
            'page'     => isset( $_GET['rules_paged'] ) ? absint( $_GET['rules_paged'] ) : 1,
        ) );
        $log = WPFG_Firewall::get_log( array(
            'per_page' => 30,
            'page'     => isset( $_GET['log_paged'] ) ? absint( $_GET['log_paged'] ) : 1,
        ) );
        WPFG_Helpers::render_view( 'firewall', compact( 'stats', 'rules', 'log' ) );
    }

    public static function page_vulnerabilities() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $session_id = 0;
        $results = WPFG_Vuln_Scanner::get_results( array(
            'per_page' => 50,
            'page'     => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
            'severity' => isset( $_GET['severity'] ) ? sanitize_text_field( $_GET['severity'] ) : '',
        ) );
        $summary = WPFG_Vuln_Scanner::get_summary();
        WPFG_Helpers::render_view( 'vulnerabilities', compact( 'results', 'summary' ) );
    }

    public static function page_security_score() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $risk = WPFG_Risk_Score::calculate();
        $score_history = self::get_scan_history_data();
        WPFG_Helpers::render_view( 'security-score', compact( 'risk', 'score_history' ) );
    }

    public static function page_two_factor() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $users_status = array();
        $admins = get_users( array( 'role' => 'administrator' ) );
        foreach ( $admins as $user ) {
            $users_status[] = array(
                'id'       => $user->ID,
                'login'    => $user->user_login,
                'email'    => $user->user_email,
                'role'     => 'administrator',
                'enabled'  => WPFG_Two_Factor::is_enabled_for_user( $user->ID ),
            );
        }
        WPFG_Helpers::render_view( 'two-factor', compact( 'users_status' ) );
    }

    public static function page_permissions() {
        if ( ! WPFG_Helpers::check_capability() ) wp_die( 'Unauthorized.' );
        $results = null;
        WPFG_Helpers::render_view( 'permissions', compact( 'results' ) );
    }
}
