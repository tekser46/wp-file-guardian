<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX request handler for all async admin operations.
 * Every handler verifies nonce + capability before acting.
 */
class WPFG_Ajax_Handler {

    public static function init() {
        $actions = array(
            'wpfg_start_scan',
            'wpfg_scan_batch',
            'wpfg_cancel_scan',
            'wpfg_ignore_result',
            'wpfg_quarantine_file',
            'wpfg_restore_quarantine',
            'wpfg_delete_quarantine',
            'wpfg_delete_file',
            'wpfg_bulk_action',
            'wpfg_create_backup',
            'wpfg_delete_backup',
            'wpfg_restore_backup',
            'wpfg_download_backup',
            'wpfg_download_file',
            'wpfg_verify_core',
            'wpfg_repair_core',
            'wpfg_reinstall_plugin',
            'wpfg_reinstall_theme',
            'wpfg_export_logs',
            'wpfg_clear_logs',
            'wpfg_export_settings',
            'wpfg_file_info',
            // v2 actions.
            'wpfg_db_scan_start',
            'wpfg_db_scan_batch',
            'wpfg_revision_stats',
            'wpfg_cleanup_revisions',
            'wpfg_build_baseline',
            'wpfg_compare_files',
            'wpfg_test_remote',
            'wpfg_upload_remote',
            // v3 actions.
            'wpfg_apply_hardening',
            'wpfg_test_hardening',
            'wpfg_firewall_add_rule',
            'wpfg_firewall_delete_rule',
            'wpfg_firewall_toggle_rule',
            'wpfg_firewall_get_log',
            'wpfg_firewall_clear_log',
            'wpfg_firewall_get_stats',
            'wpfg_vuln_scan_start',
            'wpfg_vuln_update_item',
            'wpfg_vuln_ignore',
            'wpfg_get_security_score',
            'wpfg_send_test_summary',
            'wpfg_2fa_generate_secret',
            'wpfg_2fa_verify_setup',
            'wpfg_2fa_disable',
            'wpfg_2fa_regenerate_backup',
            'wpfg_2fa_get_users_status',
            'wpfg_check_permissions',
            'wpfg_fix_permission',
            'wpfg_fix_permissions_bulk',
            'wpfg_db_ignore_finding',
            'wpfg_db_clean_item',
            'wpfg_db_view_item',
        );

        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, array( __CLASS__, $action ) );
        }
    }

    /**
     * Verify nonce and capability. Dies on failure.
     */
    private static function verify() {
        if ( ! check_ajax_referer( 'wpfg_ajax', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wp-file-guardian' ) ), 403 );
        }
        if ( ! WPFG_Helpers::check_capability() ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-file-guardian' ) ), 403 );
        }
    }

    // ---- Scanner ----

    public static function wpfg_start_scan() {
        self::verify();
        $type = isset( $_POST['scan_type'] ) ? sanitize_text_field( $_POST['scan_type'] ) : 'full';
        $session_id = WPFG_Scanner::create_session( $type );
        $files = WPFG_Scanner::collect_files( $session_id );
        WPFG_Logger::log( 'scan_started', '', 'success', 'Type: ' . $type . ', Files: ' . count( $files ) );
        wp_send_json_success( array(
            'session_id' => $session_id,
            'total'      => count( $files ),
        ) );
    }

    public static function wpfg_scan_batch() {
        self::verify();
        $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
        $offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        if ( ! $session_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid session.', 'wp-file-guardian' ) ) );
        }
        $result = WPFG_Scanner::process_batch( $session_id, $offset );

        // Notify on completion if critical issues found.
        if ( $result['done'] ) {
            $stats = WPFG_Scanner::get_dashboard_stats();
            if ( $stats['critical_count'] > 0 ) {
                WPFG_Notifications::notify_critical_findings( $session_id, $stats['critical_count'] );
            }
        }

        wp_send_json_success( $result );
    }

    public static function wpfg_cancel_scan() {
        self::verify();
        $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
        WPFG_Scanner::cancel_session( $session_id );
        WPFG_Logger::log( 'scan_cancelled', '', 'success', 'Session: ' . $session_id );
        wp_send_json_success();
    }

    public static function wpfg_ignore_result() {
        self::verify();
        $result_id = isset( $_POST['result_id'] ) ? absint( $_POST['result_id'] ) : 0;
        $ignore    = isset( $_POST['ignore'] ) ? (bool) $_POST['ignore'] : true;
        WPFG_Scanner::ignore_result( $result_id, $ignore );
        wp_send_json_success();
    }

    // ---- Quarantine ----

    public static function wpfg_quarantine_file() {
        self::verify();
        $path   = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
        $reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
        if ( ! $path ) {
            wp_send_json_error( array( 'message' => __( 'No path specified.', 'wp-file-guardian' ) ) );
        }
        $result = WPFG_Quarantine::quarantine_file( $path, $reason );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'id' => $result ) );
    }

    public static function wpfg_restore_quarantine() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $result = WPFG_Quarantine::restore( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success();
    }

    public static function wpfg_delete_quarantine() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $result = WPFG_Quarantine::delete( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success();
    }

    // ---- File Management ----

    public static function wpfg_delete_file() {
        self::verify();
        $path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
        if ( ! $path ) {
            wp_send_json_error( array( 'message' => __( 'No path specified.', 'wp-file-guardian' ) ) );
        }

        // Quarantine-first logic.
        if ( WPFG_Settings::get( 'quarantine_first', true ) ) {
            $result = WPFG_Quarantine::quarantine_file( $path, __( 'Deleted via File Manager', 'wp-file-guardian' ) );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }
            wp_send_json_success( array( 'quarantined' => true ) );
        }

        // Backup before action.
        if ( WPFG_Settings::get( 'backup_before_action', true ) && file_exists( $path ) ) {
            WPFG_Backup::create_restore_point( array( $path ), 'Before delete: ' . basename( $path ) );
        }

        $result = WPFG_Filesystem::delete_file( $path );
        if ( is_wp_error( $result ) ) {
            WPFG_Logger::log( 'file_delete', $path, 'error', $result->get_error_message() );
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        WPFG_Logger::log( 'file_delete', $path, 'success' );
        wp_send_json_success();
    }

    public static function wpfg_bulk_action() {
        self::verify();
        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
        $paths  = isset( $_POST['paths'] ) ? array_map( 'sanitize_text_field', (array) $_POST['paths'] ) : array();

        if ( ! $action || empty( $paths ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-file-guardian' ) ) );
        }

        $results = array( 'success' => 0, 'failed' => 0 );

        foreach ( $paths as $path ) {
            switch ( $action ) {
                case 'quarantine':
                    $r = WPFG_Quarantine::quarantine_file( $path, __( 'Bulk quarantine', 'wp-file-guardian' ) );
                    break;
                case 'delete':
                    if ( WPFG_Settings::get( 'quarantine_first', true ) ) {
                        $r = WPFG_Quarantine::quarantine_file( $path, __( 'Bulk delete (quarantined)', 'wp-file-guardian' ) );
                    } else {
                        $r = WPFG_Filesystem::delete_file( $path );
                    }
                    break;
                case 'ignore':
                    // Expect result IDs for ignore.
                    WPFG_Scanner::ignore_result( absint( $path ), true );
                    $r = true;
                    break;
                default:
                    $r = new WP_Error( 'unknown', 'Unknown action' );
            }

            if ( is_wp_error( $r ) ) {
                $results['failed']++;
            } else {
                $results['success']++;
            }
        }

        WPFG_Logger::log( 'bulk_' . $action, '', 'success', sprintf( '%d success, %d failed', $results['success'], $results['failed'] ) );
        wp_send_json_success( $results );
    }

    public static function wpfg_file_info() {
        self::verify();
        $path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
        if ( ! $path || ! WPFG_Helpers::is_allowed_path( $path ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid path.', 'wp-file-guardian' ) ) );
        }
        $info = WPFG_Filesystem::file_info( $path );
        if ( ! $info ) {
            wp_send_json_error( array( 'message' => __( 'File not found.', 'wp-file-guardian' ) ) );
        }
        $info['modified_formatted'] = wp_date( 'Y-m-d H:i:s', $info['modified'] );
        $info['size_formatted']     = WPFG_Helpers::format_bytes( $info['size'] );
        wp_send_json_success( $info );
    }

    // ---- Backups ----

    public static function wpfg_create_backup() {
        self::verify();
        $type  = isset( $_POST['backup_type'] ) ? sanitize_text_field( $_POST['backup_type'] ) : 'full';
        $custom = isset( $_POST['custom_paths'] ) ? array_map( 'sanitize_text_field', (array) $_POST['custom_paths'] ) : array();
        $result = WPFG_Backup::create( $type, $custom );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'backup_id' => $result ) );
    }

    public static function wpfg_delete_backup() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $result = WPFG_Backup::delete( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success();
    }

    public static function wpfg_restore_backup() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $result = WPFG_Backup::restore( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        WPFG_Notifications::notify_restore_completed( 'backup' );
        wp_send_json_success();
    }

    public static function wpfg_download_backup() {
        // This is a GET request for file download.
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'wpfg_download_backup_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }
        if ( ! WPFG_Helpers::check_capability() ) {
            wp_die( 'Unauthorized.' );
        }

        global $wpdb;
        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_backups WHERE id = %d",
            $id
        ) );
        if ( ! $backup || ! file_exists( $backup->file_path ) ) {
            wp_die( 'Backup not found.' );
        }

        WPFG_Logger::log( 'backup_download', $backup->file_path, 'success' );

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . basename( $backup->file_path ) . '"' );
        header( 'Content-Length: ' . filesize( $backup->file_path ) );
        readfile( $backup->file_path ); // phpcs:ignore
        exit;
    }

    public static function wpfg_download_file() {
        self::verify();
        $path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
        if ( ! $path || ! WPFG_Helpers::is_allowed_path( $path ) || ! file_exists( $path ) ) {
            wp_send_json_error( array( 'message' => __( 'File not found or not allowed.', 'wp-file-guardian' ) ) );
        }
        // Return a temporary download URL via nonce-protected link.
        $token = wp_create_nonce( 'wpfg_dl_' . md5( $path ) );
        set_transient( 'wpfg_dl_' . $token, $path, 5 * MINUTE_IN_SECONDS );
        wp_send_json_success( array(
            'url' => add_query_arg( array(
                'action'   => 'wpfg_serve_file',
                'token'    => $token,
                '_wpnonce' => wp_create_nonce( 'wpfg_serve_file' ),
            ), admin_url( 'admin-ajax.php' ) ),
        ) );
    }

    // ---- Repair ----

    public static function wpfg_verify_core() {
        self::verify();
        $result = WPFG_Repair::verify_core();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    public static function wpfg_repair_core() {
        self::verify();
        $files   = isset( $_POST['files'] ) ? array_map( 'sanitize_text_field', (array) $_POST['files'] ) : array();
        $dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'];

        if ( empty( $files ) ) {
            wp_send_json_error( array( 'message' => __( 'No files specified.', 'wp-file-guardian' ) ) );
        }

        $result = WPFG_Repair::repair_core( $files, $dry_run );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        if ( ! $dry_run ) {
            WPFG_Notifications::notify_repair_completed(
                count( $result['repaired'] ),
                count( $result['errors'] )
            );
        }

        wp_send_json_success( $result );
    }

    public static function wpfg_reinstall_plugin() {
        self::verify();
        $slug    = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
        $dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'];
        $result  = WPFG_Repair::reinstall_plugin( $slug, $dry_run );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    public static function wpfg_reinstall_theme() {
        self::verify();
        $slug    = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
        $dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'];
        $result  = WPFG_Repair::reinstall_theme( $slug, $dry_run );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    // ---- Logs ----

    public static function wpfg_export_logs() {
        self::verify();
        $csv = WPFG_Logger::export_csv();
        wp_send_json_success( array( 'csv' => $csv ) );
    }

    public static function wpfg_clear_logs() {
        self::verify();
        WPFG_Logger::clear();
        WPFG_Logger::log( 'logs_cleared', '', 'success' );
        wp_send_json_success();
    }

    // ---- Settings ----

    public static function wpfg_export_settings() {
        self::verify();
        wp_send_json_success( array( 'json' => WPFG_Settings::export() ) );
    }

    // ---- v2: DB Scanner ----

    public static function wpfg_db_scan_start() {
        self::verify();
        // Get total counts for progress tracking.
        global $wpdb;
        $totals = array(
            'posts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
            'options'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" ),
            'comments' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments}" ),
        );
        // Create a new session ID.
        $session_id = time();
        // Clear old results for this session.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wpfg_db_scan_results WHERE session_id = %d",
            $session_id
        ) );
        WPFG_Logger::log( 'db_scan_started', '', 'success' );
        wp_send_json_success( array(
            'session_id' => $session_id,
            'totals'     => $totals,
        ) );
    }

    public static function wpfg_db_scan_batch() {
        self::verify();
        $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
        $source     = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
        $offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $batch_size = 200;

        if ( ! $session_id || ! $source ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-file-guardian' ) ) );
        }

        $result = array();
        switch ( $source ) {
            case 'posts':
                $result = WPFG_DB_Scanner::scan_posts( $batch_size, $offset );
                break;
            case 'options':
                $result = WPFG_DB_Scanner::scan_options( 500, $offset );
                break;
            case 'comments':
                $result = WPFG_DB_Scanner::scan_comments( 500, $offset );
                break;
            case 'users':
                $findings = WPFG_DB_Scanner::scan_users();
                WPFG_DB_Scanner::save_results( $session_id, $findings );
                wp_send_json_success( array(
                    'findings'  => count( $findings ),
                    'done'      => true,
                    'source'    => 'users',
                ) );
                return;
            case 'cron':
                $findings = WPFG_DB_Scanner::scan_cron();
                WPFG_DB_Scanner::save_results( $session_id, $findings );
                wp_send_json_success( array(
                    'findings'  => count( $findings ),
                    'done'      => true,
                    'source'    => 'cron',
                ) );
                return;
        }

        // Save findings.
        if ( ! empty( $result['findings'] ) ) {
            WPFG_DB_Scanner::save_results( $session_id, $result['findings'] );
        }

        wp_send_json_success( array(
            'findings'  => count( $result['findings'] ),
            'processed' => $result['processed'],
            'total'     => $result['total'],
            'done'      => $result['done'],
            'source'    => $source,
        ) );
    }

    // ---- v2: Revision Cleanup ----

    public static function wpfg_revision_stats() {
        self::verify();
        $stats = WPFG_DB_Scanner::get_revision_stats();
        wp_send_json_success( $stats );
    }

    public static function wpfg_cleanup_revisions() {
        self::verify();
        $keep = isset( $_POST['keep_per_post'] ) ? absint( $_POST['keep_per_post'] ) : 0;
        $result = WPFG_DB_Scanner::cleanup_revisions( $keep );
        wp_send_json_success( $result );
    }

    // ---- v2: File Monitor ----

    public static function wpfg_build_baseline() {
        self::verify();
        $count = WPFG_File_Monitor::build_baseline( true );
        wp_send_json_success( array( 'count' => $count ) );
    }

    public static function wpfg_compare_files() {
        self::verify();
        $result = WPFG_File_Monitor::compare();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    // ---- v2: Remote Backup ----

    public static function wpfg_test_remote() {
        self::verify();
        $result = WPFG_Remote_Backup::test_connection();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'message' => __( 'Connection successful!', 'wp-file-guardian' ) ) );
    }

    public static function wpfg_upload_remote() {
        self::verify();
        $backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
        if ( ! $backup_id ) {
            wp_send_json_error( array( 'message' => __( 'No backup specified.', 'wp-file-guardian' ) ) );
        }

        global $wpdb;
        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_backups WHERE id = %d",
            $backup_id
        ) );
        if ( ! $backup || ! file_exists( $backup->file_path ) ) {
            wp_send_json_error( array( 'message' => __( 'Backup file not found.', 'wp-file-guardian' ) ) );
        }

        $result = WPFG_Remote_Backup::upload( $backup->file_path );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'message' => __( 'Backup uploaded to remote storage.', 'wp-file-guardian' ) ) );
    }

    // ---- v3: Hardening ----

    public static function wpfg_apply_hardening() {
        self::verify();

        // Support single key/value toggle from inline script.
        $key   = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        $value = isset( $_POST['value'] ) ? absint( $_POST['value'] ) : 0;

        if ( $key ) {
            // Single toggle.
            WPFG_Settings::set( $key, $value ? true : false );
            WPFG_Hardening::apply_all();
            wp_send_json_success( array( 'key' => $key, 'value' => $value ) );
            return;
        }

        // Bulk settings (from admin.js bindHardening, if used).
        $settings = isset( $_POST['settings'] ) ? (array) $_POST['settings'] : array();
        $result = WPFG_Hardening::apply_settings( $settings );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    public static function wpfg_test_hardening() {
        self::verify();
        $result = WPFG_Hardening::test_status();
        wp_send_json_success( $result );
    }

    // ---- v3: Firewall ----

    public static function wpfg_firewall_add_rule() {
        self::verify();
        $rule_type = isset( $_POST['rule_type'] ) ? sanitize_text_field( $_POST['rule_type'] ) : '';
        $value     = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
        $notes     = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';
        $expires   = isset( $_POST['expires_at'] ) ? sanitize_text_field( $_POST['expires_at'] ) : null;
        if ( ! $rule_type || ! $value ) {
            wp_send_json_error( array( 'message' => __( 'Rule type and value are required.', 'wp-file-guardian' ) ) );
        }
        $result = WPFG_Firewall::add_rule( $rule_type, $value, $notes, $expires );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        WPFG_Logger::log( 'firewall_rule_added', $value, 'success', 'Type: ' . $rule_type );
        wp_send_json_success( array( 'id' => $result ) );
    }

    public static function wpfg_firewall_delete_rule() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        WPFG_Firewall::delete_rule( $id );
        wp_send_json_success();
    }

    public static function wpfg_firewall_toggle_rule() {
        self::verify();
        $id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $active = isset( $_POST['is_active'] ) ? (bool) $_POST['is_active'] : true;
        WPFG_Firewall::toggle_rule( $id, $active );
        wp_send_json_success();
    }

    public static function wpfg_firewall_get_log() {
        self::verify();
        $page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 50;
        $result   = WPFG_Firewall::get_log( $page, $per_page );
        wp_send_json_success( $result );
    }

    public static function wpfg_firewall_clear_log() {
        self::verify();
        WPFG_Firewall::clear_log();
        WPFG_Logger::log( 'firewall_log_cleared', '', 'success' );
        wp_send_json_success();
    }

    public static function wpfg_firewall_get_stats() {
        self::verify();
        $stats = WPFG_Firewall::get_stats();
        wp_send_json_success( $stats );
    }

    // ---- v3: Vulnerability Scanner ----

    public static function wpfg_vuln_scan_start() {
        self::verify();
        $result = WPFG_Vuln_Scanner::scan();
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    public static function wpfg_vuln_update_item() {
        self::verify();
        $type = isset( $_POST['item_type'] ) ? sanitize_text_field( $_POST['item_type'] ) : '';
        $slug = isset( $_POST['item_slug'] ) ? sanitize_text_field( $_POST['item_slug'] ) : '';
        if ( ! $type || ! $slug ) {
            wp_send_json_error( array( 'message' => __( 'Invalid item.', 'wp-file-guardian' ) ) );
        }
        $result = WPFG_Vuln_Scanner::update_item( $type, $slug );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( $result );
    }

    public static function wpfg_vuln_ignore() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        WPFG_Vuln_Scanner::ignore( $id );
        wp_send_json_success();
    }

    // ---- v3: Security Score ----

    public static function wpfg_get_security_score() {
        self::verify();
        $result = WPFG_Risk_Score::calculate();
        $result['recommendations'] = WPFG_Risk_Score::get_recommendations();
        $result['history'] = WPFG_Risk_Score::get_score_history();
        wp_send_json_success( $result );
    }

    public static function wpfg_send_test_summary() {
        self::verify();
        $result = WPFG_Notifications::send_weekly_summary();
        if ( $result ) {
            wp_send_json_success( array( 'message' => __( 'Test summary email sent.', 'wp-file-guardian' ) ) );
        }
        wp_send_json_error( array( 'message' => __( 'Failed to send summary email.', 'wp-file-guardian' ) ) );
    }

    // ---- v3: Two-Factor Auth ----

    public static function wpfg_2fa_generate_secret() {
        self::verify();
        $user_id = get_current_user_id();

        // Generate and store secret.
        $secret    = WPFG_Two_Factor::generate_secret();
        $encrypted = WPFG_Two_Factor::encrypt_secret( $secret );
        update_user_meta( $user_id, 'wpfg_2fa_secret', $encrypted );
        update_user_meta( $user_id, 'wpfg_2fa_enabled', '1' );

        // Generate backup codes.
        $backup_codes = WPFG_Two_Factor::generate_backup_codes( $user_id );

        // Build QR URI.
        $user  = wp_get_current_user();
        $qr_uri = WPFG_Two_Factor::generate_qr_uri( $secret, $user->user_email );

        WPFG_Logger::log( '2fa_enabled', '', 'success', sprintf( '2FA enabled for user %d', $user_id ) );

        wp_send_json_success( array(
            'secret'       => $secret,
            'qr_uri'       => $qr_uri,
            'backup_codes' => $backup_codes,
        ) );
    }

    public static function wpfg_2fa_verify_setup() {
        self::verify();
        $user_id = get_current_user_id();
        $code    = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';

        if ( empty( $code ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a verification code.', 'wp-file-guardian' ) ) );
        }

        $valid = WPFG_Two_Factor::verify_code( $user_id, $code );
        if ( $valid ) {
            wp_send_json_success( array( 'message' => __( 'Code verified successfully.', 'wp-file-guardian' ) ) );
        }
        wp_send_json_error( array( 'message' => __( 'Invalid code. Please check your authenticator app and try again.', 'wp-file-guardian' ) ) );
    }

    public static function wpfg_2fa_disable() {
        self::verify();
        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : get_current_user_id();
        // Only admins can disable other users' 2FA.
        if ( $user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-file-guardian' ) ) );
        }
        WPFG_Two_Factor::disable( $user_id );
        wp_send_json_success();
    }

    public static function wpfg_2fa_regenerate_backup() {
        self::verify();
        $user_id = get_current_user_id();
        $codes   = WPFG_Two_Factor::regenerate_backup_codes( $user_id );
        wp_send_json_success( array( 'codes' => $codes ) );
    }

    public static function wpfg_2fa_get_users_status() {
        self::verify();
        $users = WPFG_Two_Factor::get_users_status();
        wp_send_json_success( array( 'users' => $users ) );
    }

    // ---- v3: Permission Checker ----

    public static function wpfg_check_permissions() {
        self::verify();
        $result = WPFG_Permission_Checker::scan();
        wp_send_json_success( $result );
    }

    public static function wpfg_fix_permission() {
        self::verify();
        $path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
        $perm = isset( $_POST['permission'] ) ? sanitize_text_field( $_POST['permission'] ) : '';
        if ( ! $path || ! $perm ) {
            wp_send_json_error( array( 'message' => __( 'Path and permission are required.', 'wp-file-guardian' ) ) );
        }
        $result = WPFG_Permission_Checker::fix_permission( $path, $perm );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success();
    }

    public static function wpfg_fix_permissions_bulk() {
        self::verify();
        $items = isset( $_POST['items'] ) ? (array) $_POST['items'] : array();
        $result = WPFG_Permission_Checker::fix_bulk( $items );
        wp_send_json_success( $result );
    }

    // ------------------------------------------------------------------
    // DB Scanner Actions
    // ------------------------------------------------------------------

    public static function wpfg_db_ignore_finding() {
        self::verify();
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'wp-file-guardian' ) ) );
        }
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'wpfg_db_scan_results', array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success();
    }

    public static function wpfg_db_view_item() {
        self::verify();
        $source = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
        $row_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
        if ( ! $source || ! $row_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-file-guardian' ) ) );
        }

        global $wpdb;
        $content = '';

        switch ( $source ) {
            case 'wp_options':
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_id = %d", $row_id ) );
                if ( $row ) {
                    $content = sprintf( "Option: %s\n\nValue (first 2000 chars):\n%s", $row->option_name, mb_substr( $row->option_value, 0, 2000 ) );
                }
                break;
            case 'wp_posts':
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_type, post_content FROM {$wpdb->posts} WHERE ID = %d", $row_id ) );
                if ( $row ) {
                    $content = sprintf( "Title: %s\nType: %s\n\nContent (first 2000 chars):\n%s", $row->post_title, $row->post_type, mb_substr( $row->post_content, 0, 2000 ) );
                }
                break;
            case 'wp_comments':
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT comment_author, comment_author_url, comment_content FROM {$wpdb->comments} WHERE comment_ID = %d", $row_id ) );
                if ( $row ) {
                    $content = sprintf( "Author: %s\nURL: %s\n\nComment (first 2000 chars):\n%s", $row->comment_author, $row->comment_author_url, mb_substr( $row->comment_content, 0, 2000 ) );
                }
                break;
            case 'wp_users':
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT user_login, user_email, user_url FROM {$wpdb->users} WHERE ID = %d", $row_id ) );
                if ( $row ) {
                    $content = sprintf( "Login: %s\nEmail: %s\nURL: %s", $row->user_login, $row->user_email, $row->user_url );
                }
                break;
            case 'wp_cron':
                $cron = _get_cron_array();
                $content = __( 'Cron jobs are informational only and cannot be viewed individually.', 'wp-file-guardian' );
                break;
            default:
                $content = __( 'Unknown source table.', 'wp-file-guardian' );
        }

        wp_send_json_success( array( 'content' => $content ) );
    }

    public static function wpfg_db_clean_item() {
        self::verify();
        $source = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
        $row_id = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
        if ( ! $source || ! $row_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-file-guardian' ) ) );
        }

        global $wpdb;

        switch ( $source ) {
            case 'wp_options':
                $wpdb->delete( $wpdb->options, array( 'option_id' => $row_id ), array( '%d' ) );
                WPFG_Logger::log( 'db_clean', $source . '#' . $row_id, 'success', 'Option deleted' );
                break;
            case 'wp_comments':
                wp_delete_comment( $row_id, true );
                WPFG_Logger::log( 'db_clean', $source . '#' . $row_id, 'success', 'Comment deleted' );
                break;
            default:
                wp_send_json_error( array( 'message' => __( 'Cleaning is only available for options and comments. For posts and users, please use WordPress admin.', 'wp-file-guardian' ) ) );
                return;
        }

        wp_send_json_success( array( 'message' => __( 'Item cleaned successfully.', 'wp-file-guardian' ) ) );
    }
}
