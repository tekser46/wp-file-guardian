<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Quarantine service.
 * Moves suspicious files to a protected directory instead of deleting them.
 */
class WPFG_Quarantine {

    /**
     * Get the quarantine directory path.
     */
    public static function dir() {
        return WPFG_Helpers::storage_dir() . '/quarantine';
    }

    /**
     * Quarantine a file.
     *
     * @param string $file_path Absolute path to file.
     * @param string $reason    Reason for quarantine.
     * @return int|WP_Error Quarantine record ID or error.
     */
    public static function quarantine_file( $file_path, $reason = '' ) {
        if ( ! file_exists( $file_path ) ) {
            return new WP_Error( 'not_found', __( 'File does not exist.', 'wp-file-guardian' ) );
        }
        if ( ! WPFG_Helpers::is_allowed_path( $file_path ) ) {
            return new WP_Error( 'invalid_path', __( 'Path is not allowed.', 'wp-file-guardian' ) );
        }

        $q_dir = self::dir();
        if ( ! is_dir( $q_dir ) ) {
            wp_mkdir_p( $q_dir );
        }

        $hash       = md5_file( $file_path );
        $size       = filesize( $file_path );
        $q_filename = WPFG_Helpers::unique_filename( 'q_', pathinfo( $file_path, PATHINFO_EXTENSION ) . '.quarantined' );
        $q_path     = $q_dir . '/' . $q_filename;

        // Move file to quarantine.
        if ( ! rename( $file_path, $q_path ) ) {
            return new WP_Error( 'move_failed', __( 'Could not move file to quarantine.', 'wp-file-guardian' ) );
        }

        // Save record.
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wpfg_quarantine',
            array(
                'original_path'   => wp_normalize_path( $file_path ),
                'quarantine_path' => wp_normalize_path( $q_path ),
                'file_hash'       => $hash,
                'file_size'       => $size,
                'reason'          => sanitize_textarea_field( $reason ),
                'user_id'         => get_current_user_id(),
                'status'          => 'quarantined',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
        );

        $record_id = $wpdb->insert_id;
        WPFG_Logger::log( 'quarantine', $file_path, 'success', $reason );

        return $record_id;
    }

    /**
     * Restore a quarantined file to its original location.
     */
    public static function restore( $record_id ) {
        global $wpdb;
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_quarantine WHERE id = %d AND status = 'quarantined'",
            $record_id
        ) );

        if ( ! $record ) {
            return new WP_Error( 'not_found', __( 'Quarantine record not found.', 'wp-file-guardian' ) );
        }

        if ( ! file_exists( $record->quarantine_path ) ) {
            return new WP_Error( 'file_missing', __( 'Quarantined file no longer exists.', 'wp-file-guardian' ) );
        }

        // Ensure destination directory exists.
        $dest_dir = dirname( $record->original_path );
        if ( ! is_dir( $dest_dir ) ) {
            wp_mkdir_p( $dest_dir );
        }

        if ( ! rename( $record->quarantine_path, $record->original_path ) ) {
            return new WP_Error( 'restore_failed', __( 'Could not restore file.', 'wp-file-guardian' ) );
        }

        $wpdb->update(
            $wpdb->prefix . 'wpfg_quarantine',
            array( 'status' => 'restored', 'restored_at' => current_time( 'mysql' ) ),
            array( 'id' => $record_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        WPFG_Logger::log( 'quarantine_restore', $record->original_path, 'success' );
        return true;
    }

    /**
     * Permanently delete a quarantined file.
     */
    public static function delete( $record_id ) {
        global $wpdb;
        $record = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_quarantine WHERE id = %d",
            $record_id
        ) );

        if ( ! $record ) {
            return new WP_Error( 'not_found', __( 'Quarantine record not found.', 'wp-file-guardian' ) );
        }

        if ( file_exists( $record->quarantine_path ) ) {
            @unlink( $record->quarantine_path );
        }

        $wpdb->update(
            $wpdb->prefix . 'wpfg_quarantine',
            array( 'status' => 'deleted', 'deleted_at' => current_time( 'mysql' ) ),
            array( 'id' => $record_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        WPFG_Logger::log( 'quarantine_delete', $record->original_path, 'success' );
        return true;
    }

    /**
     * List quarantined files.
     */
    public static function get_list( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_quarantine';

        $defaults = array(
            'status'   => 'quarantined',
            'per_page' => 20,
            'page'     => 1,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( $args['status'] ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
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
                $limit,
                $offset
            ) );
        }

        return array( 'total' => $total, 'items' => $items );
    }

    /**
     * Count quarantined files.
     */
    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wpfg_quarantine WHERE status = 'quarantined'"
        );
    }
}
