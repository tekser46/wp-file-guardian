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
            'wpfg-dashboard' => __( 'Dashboard', 'wp-file-guardian' ),
            'wpfg-scanner'   => __( 'Scanner', 'wp-file-guardian' ),
            'wpfg-files'     => __( 'File Manager', 'wp-file-guardian' ),
            'wpfg-quarantine'=> __( 'Quarantine', 'wp-file-guardian' ),
            'wpfg-backups'   => __( 'Backups', 'wp-file-guardian' ),
            'wpfg-repair'    => __( 'Repair', 'wp-file-guardian' ),
            'wpfg-logs'      => __( 'Logs', 'wp-file-guardian' ),
            'wpfg-settings'  => __( 'Settings', 'wp-file-guardian' ),
            'wpfg-sysinfo'   => __( 'System Info', 'wp-file-guardian' ),
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
        WPFG_Helpers::render_view( 'dashboard', compact( 'stats', 'quarantine_count', 'latest_backup' ) );
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
}
