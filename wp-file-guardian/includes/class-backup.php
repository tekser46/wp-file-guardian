<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Backup service.
 * Creates, manages, and restores file backups.
 */
class WPFG_Backup {

    /**
     * Get the backup storage directory.
     */
    public static function dir() {
        $custom = WPFG_Settings::get( 'backup_location' );
        if ( $custom && is_dir( $custom ) && is_writable( $custom ) ) {
            return rtrim( $custom, '/\\' );
        }
        return WPFG_Helpers::storage_dir() . '/backups';
    }

    /**
     * Create a backup.
     *
     * @param string $type 'full', 'plugins', 'themes', 'uploads', 'custom'
     * @param array  $custom_paths Array of relative paths (for 'custom' type).
     * @return int|WP_Error Backup record ID or error.
     */
    public static function create( $type = 'full', $custom_paths = array() ) {
        $backup_dir = self::dir();
        if ( ! is_dir( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
        }

        // Determine which paths to back up.
        $paths = self::get_paths_for_type( $type, $custom_paths );
        if ( empty( $paths ) ) {
            return new WP_Error( 'no_paths', __( 'No valid paths to back up.', 'wp-file-guardian' ) );
        }

        // Collect files.
        $files = array();
        foreach ( $paths as $path ) {
            $abs = ABSPATH . ltrim( $path, '/' );
            if ( is_file( $abs ) ) {
                $files[] = $abs;
            } elseif ( is_dir( $abs ) ) {
                foreach ( WPFG_Filesystem::scan_directory( $abs ) as $f ) {
                    $files[] = $f;
                }
            }
        }

        if ( empty( $files ) ) {
            return new WP_Error( 'no_files', __( 'No files found to back up.', 'wp-file-guardian' ) );
        }

        // Create record first.
        global $wpdb;
        $filename = 'wpfg-backup-' . $type . '-' . gmdate( 'Ymd-His' ) . '.zip';
        $zip_path = $backup_dir . '/' . $filename;

        $wpdb->insert(
            $wpdb->prefix . 'wpfg_backups',
            array(
                'user_id'     => get_current_user_id(),
                'backup_type' => sanitize_text_field( $type ),
                'file_path'   => wp_normalize_path( $zip_path ),
                'file_count'  => count( $files ),
                'status'      => 'creating',
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );
        $backup_id = $wpdb->insert_id;

        // Create ZIP.
        $result = WPFG_Filesystem::create_zip( $zip_path, $files, ABSPATH );
        if ( is_wp_error( $result ) ) {
            $wpdb->update(
                $wpdb->prefix . 'wpfg_backups',
                array( 'status' => 'failed', 'notes' => $result->get_error_message() ),
                array( 'id' => $backup_id )
            );
            WPFG_Logger::log( 'backup_create', $zip_path, 'error', $result->get_error_message() );
            return $result;
        }

        $file_size = file_exists( $zip_path ) ? filesize( $zip_path ) : 0;
        $wpdb->update(
            $wpdb->prefix . 'wpfg_backups',
            array( 'status' => 'completed', 'file_size' => $file_size ),
            array( 'id' => $backup_id )
        );

        WPFG_Logger::log( 'backup_create', $zip_path, 'success', sprintf( '%s backup, %d files, %s', $type, count( $files ), WPFG_Helpers::format_bytes( $file_size ) ) );

        // Enforce retention.
        self::enforce_retention();

        return $backup_id;
    }

    /**
     * Get paths for a backup type.
     */
    private static function get_paths_for_type( $type, $custom = array() ) {
        switch ( $type ) {
            case 'plugins':
                return array( 'wp-content/plugins' );
            case 'themes':
                return array( 'wp-content/themes' );
            case 'uploads':
                return array( 'wp-content/uploads' );
            case 'custom':
                return array_map( 'sanitize_text_field', $custom );
            case 'full':
            default:
                return array( 'wp-content' );
        }
    }

    /**
     * Restore files from a backup.
     */
    public static function restore( $backup_id ) {
        global $wpdb;
        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_backups WHERE id = %d AND status = 'completed'",
            $backup_id
        ) );

        if ( ! $backup ) {
            return new WP_Error( 'not_found', __( 'Backup record not found.', 'wp-file-guardian' ) );
        }
        if ( ! file_exists( $backup->file_path ) ) {
            return new WP_Error( 'file_missing', __( 'Backup file no longer exists.', 'wp-file-guardian' ) );
        }

        $result = WPFG_Filesystem::extract_zip( $backup->file_path, ABSPATH );
        if ( is_wp_error( $result ) ) {
            WPFG_Logger::log( 'backup_restore', $backup->file_path, 'error', $result->get_error_message() );
            return $result;
        }

        WPFG_Logger::log( 'backup_restore', $backup->file_path, 'success' );
        return true;
    }

    /**
     * Delete a backup.
     */
    public static function delete( $backup_id ) {
        global $wpdb;
        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_backups WHERE id = %d",
            $backup_id
        ) );

        if ( ! $backup ) {
            return new WP_Error( 'not_found', __( 'Backup not found.', 'wp-file-guardian' ) );
        }

        if ( file_exists( $backup->file_path ) ) {
            @unlink( $backup->file_path );
        }

        $wpdb->delete( $wpdb->prefix . 'wpfg_backups', array( 'id' => $backup_id ), array( '%d' ) );
        WPFG_Logger::log( 'backup_delete', $backup->file_path, 'success' );
        return true;
    }

    /**
     * Get list of backups.
     */
    public static function get_list( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_backups';

        $defaults = array(
            'per_page' => 20,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $offset = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit  = absint( $args['per_page'] );

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ) );

        return array( 'total' => $total, 'items' => $items );
    }

    /**
     * Enforce backup retention limits.
     */
    public static function enforce_retention() {
        global $wpdb;
        $retention = (int) WPFG_Settings::get( 'backup_retention', 5 );
        $table     = $wpdb->prefix . 'wpfg_backups';

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" );
        if ( $count <= $retention ) {
            return;
        }

        $to_delete = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'completed' ORDER BY created_at ASC LIMIT %d",
            $count - $retention
        ) );

        foreach ( $to_delete as $backup ) {
            self::delete( $backup->id );
        }
    }

    /**
     * Create a restore point (quick backup before destructive action).
     */
    public static function create_restore_point( $files, $label = '' ) {
        $backup_dir = self::dir();
        if ( ! is_dir( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
        }

        $filename = 'wpfg-restore-point-' . gmdate( 'Ymd-His' ) . '.zip';
        $zip_path = $backup_dir . '/' . $filename;

        $result = WPFG_Filesystem::create_zip( $zip_path, (array) $files, ABSPATH );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wpfg_backups',
            array(
                'user_id'     => get_current_user_id(),
                'backup_type' => 'restore_point',
                'file_path'   => wp_normalize_path( $zip_path ),
                'file_size'   => filesize( $zip_path ),
                'file_count'  => count( (array) $files ),
                'status'      => 'completed',
                'notes'       => sanitize_text_field( $label ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
        );

        WPFG_Logger::log( 'restore_point', $zip_path, 'success', $label );
        return $wpdb->insert_id;
    }

    /**
     * Get the download URL for a backup (served through admin-ajax for security).
     */
    public static function get_download_url( $backup_id ) {
        return add_query_arg( array(
            'action'   => 'wpfg_download_backup',
            'id'       => $backup_id,
            '_wpnonce' => wp_create_nonce( 'wpfg_download_backup_' . $backup_id ),
        ), admin_url( 'admin-ajax.php' ) );
    }
}
