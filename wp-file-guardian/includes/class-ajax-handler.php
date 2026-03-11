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
}
