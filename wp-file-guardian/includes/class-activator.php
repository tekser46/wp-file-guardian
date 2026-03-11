<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles plugin activation: creates DB tables, default options, directories.
 */
class WPFG_Activator {

    public static function activate() {
        self::create_tables();
        self::create_directories();
        self::set_defaults();
        update_option( 'wpfg_db_version', WPFG_DB_VERSION );
        // Flush rewrite rules after activation.
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = array();

        // Scan sessions.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_scan_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            scan_type VARCHAR(50) NOT NULL DEFAULT 'full',
            total_files BIGINT UNSIGNED NOT NULL DEFAULT 0,
            scanned_files BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            issues_found INT UNSIGNED NOT NULL DEFAULT 0,
            options LONGTEXT,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        // Scan results / findings.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_scan_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            file_path VARCHAR(1024) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_modified DATETIME NULL,
            file_permissions VARCHAR(10) DEFAULT '',
            file_hash VARCHAR(64) DEFAULT '',
            file_type VARCHAR(50) DEFAULT '',
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            issue_type VARCHAR(100) NOT NULL DEFAULT '',
            description TEXT,
            is_ignored TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY severity (severity),
            KEY issue_type (issue_type),
            KEY is_ignored (is_ignored)
        ) {$charset};";

        // Quarantine records.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_quarantine (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            original_path VARCHAR(1024) NOT NULL,
            quarantine_path VARCHAR(1024) NOT NULL,
            file_hash VARCHAR(64) DEFAULT '',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reason TEXT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'quarantined',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            restored_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        // Backup records.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_backups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            backup_type VARCHAR(50) NOT NULL DEFAULT 'full',
            file_path VARCHAR(1024) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_count INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY backup_type (backup_type),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        // Audit log.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(100) NOT NULL,
            target_path VARCHAR(1024) DEFAULT '',
            details LONGTEXT,
            result VARCHAR(20) NOT NULL DEFAULT 'success',
            ip_address VARCHAR(45) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY action (action),
            KEY result (result),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }

    /**
     * Create required directories with protective .htaccess and index.php.
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $base       = trailingslashit( $upload_dir['basedir'] ) . 'wpfg';
        $dirs       = array(
            $base,
            $base . '/quarantine',
            $base . '/backups',
            $base . '/temp',
        );

        foreach ( $dirs as $dir ) {
            if ( ! is_dir( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            // Block direct access.
            $htaccess = $dir . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, "Deny from all\n" );
            }
            $index = $dir . '/index.php';
            if ( ! file_exists( $index ) ) {
                file_put_contents( $index, "<?php\n// Silence is golden.\n" );
            }
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_defaults() {
        $defaults = WPFG_Settings::defaults();
        if ( ! get_option( 'wpfg_settings' ) ) {
            update_option( 'wpfg_settings', $defaults );
        }
    }
}
