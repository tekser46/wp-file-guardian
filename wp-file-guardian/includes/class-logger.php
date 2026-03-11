<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Audit logging service.
 */
class WPFG_Logger {

    /**
     * Log an action.
     *
     * @param string $action      Action identifier.
     * @param string $target_path File or resource path.
     * @param string $result      'success' or 'error'.
     * @param string $details     Additional details or error message.
     */
    public static function log( $action, $target_path = '', $result = 'success', $details = '' ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wpfg_logs',
            array(
                'user_id'     => get_current_user_id(),
                'action'      => sanitize_text_field( $action ),
                'target_path' => sanitize_text_field( $target_path ),
                'details'     => sanitize_textarea_field( $details ),
                'result'      => in_array( $result, array( 'success', 'error' ), true ) ? $result : 'success',
                'ip_address'  => WPFG_Helpers::get_client_ip(),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Query logs with filters.
     *
     * @param array $args Query arguments.
     * @return array { total: int, items: array }
     */
    public static function query( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_logs';

        $defaults = array(
            'action'   => '',
            'result'   => '',
            'search'   => '',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $values = array();

        if ( $args['action'] ) {
            $where[]  = 'action = %s';
            $values[] = $args['action'];
        }
        if ( $args['result'] ) {
            $where[]  = 'result = %s';
            $values[] = $args['result'];
        }
        if ( $args['search'] ) {
            $like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[]  = '(target_path LIKE %s OR details LIKE %s)';
            $values[] = $like;
            $values[] = $like;
        }

        $where_sql = implode( ' AND ', $where );

        // Sanitize orderby.
        $allowed_orderby = array( 'id', 'action', 'result', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

        $offset = max( 0, ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] ) );
        $limit  = absint( $args['per_page'] );

        // Total count.
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
                $limit,
                $offset
            ) );
        }

        return array(
            'total' => $total,
            'items' => $items,
        );
    }

    /**
     * Export logs as CSV.
     */
    public static function export_csv() {
        global $wpdb;
        $table = $wpdb->prefix . 'wpfg_logs';
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

        $output = fopen( 'php://temp', 'r+' );
        if ( ! empty( $rows ) ) {
            fputcsv( $output, array_keys( $rows[0] ) );
            foreach ( $rows as $row ) {
                fputcsv( $output, $row );
            }
        }
        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );
        return $csv;
    }

    /**
     * Clear all logs.
     */
    public static function clear() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wpfg_logs" );
    }
}
