<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles plugin activation: creates DB tables, default options, directories.
 */
class WPFG_Activator {

    public static function activate( $is_upgrade = false ) {
        self::create_tables();
        self::create_directories();
        self::set_defaults();
        update_option( 'wpfg_db_version', WPFG_DB_VERSION );
        // Only flush rewrite rules on first activation, not upgrades.
        if ( ! $is_upgrade ) {
            flush_rewrite_rules();
        }
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

        // v2: DB scan results.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_db_scan_results (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source VARCHAR(50) NOT NULL,
            row_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            description TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY severity (severity)
        ) {$charset};";

        // v2: File change monitor history.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_file_changes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            added_count INT UNSIGNED NOT NULL DEFAULT 0,
            modified_count INT UNSIGNED NOT NULL DEFAULT 0,
            deleted_count INT UNSIGNED NOT NULL DEFAULT 0,
            details LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) {$charset};";

        // v2: Login log.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_login_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'failed',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY username (username),
            KEY ip_address (ip_address),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        // v2: Scan history for charts.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_scan_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scan_date DATE NOT NULL,
            total_files INT UNSIGNED NOT NULL DEFAULT 0,
            critical_count INT UNSIGNED NOT NULL DEFAULT 0,
            warning_count INT UNSIGNED NOT NULL DEFAULT 0,
            info_count INT UNSIGNED NOT NULL DEFAULT 0,
            risk_score INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY scan_date (scan_date)
        ) {$charset};";

        // v3: Firewall log.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_firewall_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            rule_matched VARCHAR(100) NOT NULL DEFAULT '',
            user_agent VARCHAR(500) DEFAULT '',
            request_uri VARCHAR(1024) DEFAULT '',
            country_code VARCHAR(5) DEFAULT '',
            action_taken VARCHAR(20) NOT NULL DEFAULT 'blocked',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ip (ip),
            KEY rule_matched (rule_matched),
            KEY created_at (created_at)
        ) {$charset};";

        // v3: Firewall rules.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_firewall_rules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rule_type VARCHAR(50) NOT NULL DEFAULT '',
            value VARCHAR(500) NOT NULL DEFAULT '',
            notes VARCHAR(255) DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rule_type (rule_type),
            KEY is_active (is_active),
            KEY expires_at (expires_at)
        ) {$charset};";

        // v3: Vulnerabilities.
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wpfg_vulnerabilities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_type VARCHAR(20) NOT NULL DEFAULT '',
            item_slug VARCHAR(200) NOT NULL DEFAULT '',
            item_version VARCHAR(50) DEFAULT '',
            latest_version VARCHAR(50) DEFAULT '',
            vuln_id VARCHAR(100) DEFAULT '',
            vuln_title VARCHAR(500) DEFAULT '',
            severity VARCHAR(20) NOT NULL DEFAULT 'info',
            cvss_score DECIMAL(3,1) DEFAULT NULL,
            fixed_in VARCHAR(50) DEFAULT '',
            reference_url VARCHAR(1024) DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_type_slug (item_type, item_slug),
            KEY severity (severity),
            KEY status (status)
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
