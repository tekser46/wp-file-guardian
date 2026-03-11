<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utility helpers used across the plugin.
 */
class WPFG_Helpers {

    /**
     * Get the plugin storage base directory.
     */
    public static function storage_dir() {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . 'wpfg';
    }

    /**
     * Format bytes into a human-readable string.
     */
    public static function format_bytes( $bytes, $precision = 2 ) {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        $bytes = max( $bytes, 0 );
        $pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow   = min( $pow, count( $units ) - 1 );
        $bytes /= pow( 1024, $pow );
        return round( $bytes, $precision ) . ' ' . $units[ $pow ];
    }

    /**
     * Get file extension (lowercase).
     */
    public static function file_ext( $path ) {
        return strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    }

    /**
     * Return a relative path from ABSPATH.
     */
    public static function relative_path( $path ) {
        $abs = wp_normalize_path( ABSPATH );
        $path = wp_normalize_path( $path );
        if ( strpos( $path, $abs ) === 0 ) {
            return substr( $path, strlen( $abs ) );
        }
        return $path;
    }

    /**
     * Check if a path is within allowed boundaries.
     */
    public static function is_allowed_path( $path ) {
        $real = realpath( $path );
        if ( false === $real ) {
            // File may not exist yet; normalize manually.
            $real = wp_normalize_path( $path );
        } else {
            $real = wp_normalize_path( $real );
        }
        $abspath = wp_normalize_path( ABSPATH );

        // Must be under ABSPATH.
        if ( strpos( $real, $abspath ) !== 0 ) {
            return false;
        }

        // Deny access to wp-config.php for write/delete operations.
        $basename = basename( $real );
        if ( 'wp-config.php' === $basename ) {
            return false;
        }

        return true;
    }

    /**
     * Verify the current user has the required capability.
     */
    public static function check_capability() {
        $settings   = get_option( 'wpfg_settings', array() );
        $capability = ! empty( $settings['capability'] ) ? $settings['capability'] : WPFG_CAPABILITY;
        return current_user_can( $capability );
    }

    /**
     * Get the configured capability string.
     */
    public static function get_capability() {
        $settings = get_option( 'wpfg_settings', array() );
        return ! empty( $settings['capability'] ) ? $settings['capability'] : WPFG_CAPABILITY;
    }

    /**
     * Generate a unique filename for storage.
     */
    public static function unique_filename( $prefix = '', $ext = '' ) {
        return $prefix . wp_generate_password( 12, false ) . '_' . time() . ( $ext ? '.' . ltrim( $ext, '.' ) : '' );
    }

    /**
     * Safely get the client IP.
     */
    public static function get_client_ip() {
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        return '127.0.0.1';
    }

    /**
     * Get the octal permission string for a file.
     */
    public static function file_permissions( $path ) {
        if ( ! file_exists( $path ) ) {
            return '';
        }
        return substr( sprintf( '%o', fileperms( $path ) ), -4 );
    }

    /**
     * Check if a file is a PHP file.
     */
    public static function is_php_file( $path ) {
        $ext = self::file_ext( $path );
        return in_array( $ext, array( 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar' ), true );
    }

    /**
     * Get an array of sensitive files that should be protected.
     */
    public static function sensitive_files() {
        return array(
            'wp-config.php',
            '.htaccess',
            'wp-config-sample.php',
        );
    }

    /**
     * Render an admin view template.
     */
    public static function render_view( $view, $data = array() ) {
        $file = WPFG_PLUGIN_DIR . 'admin/views/' . $view . '.php';
        if ( file_exists( $file ) ) {
            // Extract data for use inside the template.
            extract( $data, EXTR_SKIP ); // phpcs:ignore
            include $file;
        }
    }

    /**
     * Return severity badge HTML.
     */
    public static function severity_badge( $severity ) {
        $classes = array(
            'info'     => 'wpfg-badge wpfg-badge-info',
            'warning'  => 'wpfg-badge wpfg-badge-warning',
            'critical' => 'wpfg-badge wpfg-badge-critical',
        );
        $class = isset( $classes[ $severity ] ) ? $classes[ $severity ] : 'wpfg-badge';
        return '<span class="' . esc_attr( $class ) . '">' . esc_html( ucfirst( $severity ) ) . '</span>';
    }
}
