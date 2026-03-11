<?php
/**
 * Plugin Name: WP File Guardian
 * Plugin URI:  https://github.com/tekser46/wp-file-guardian
 * Description: Advanced file maintenance, repair, backup, malware scanning, security monitoring, and cleanup for WordPress.
 * Version:     2.0.0
 * Author:      WP File Guardian Team
 * Author URI:  https://github.com/tekser46
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-file-guardian
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package WPFileGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPFG_VERSION', '2.0.0' );
define( 'WPFG_DB_VERSION', '2.0.0' );
define( 'WPFG_PLUGIN_FILE', __FILE__ );
define( 'WPFG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPFG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Default capability required for plugin access.
if ( ! defined( 'WPFG_CAPABILITY' ) ) {
    define( 'WPFG_CAPABILITY', 'manage_options' );
}

/**
 * Autoload plugin classes.
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'WPFG_';
    if ( strpos( $class, $prefix ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = WPFG_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Plugin activation.
 */
function wpfg_activate() {
    require_once WPFG_PLUGIN_DIR . 'includes/class-helpers.php';
    require_once WPFG_PLUGIN_DIR . 'includes/class-settings.php';
    require_once WPFG_PLUGIN_DIR . 'includes/class-activator.php';
    WPFG_Activator::activate();
}
register_activation_hook( __FILE__, 'wpfg_activate' );

/**
 * Plugin deactivation.
 */
function wpfg_deactivate() {
    require_once WPFG_PLUGIN_DIR . 'includes/class-deactivator.php';
    WPFG_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'wpfg_deactivate' );

/**
 * Check if DB schema needs upgrade on admin_init.
 */
function wpfg_maybe_upgrade() {
    $installed_version = get_option( 'wpfg_db_version', '0' );
    if ( version_compare( $installed_version, WPFG_DB_VERSION, '<' ) ) {
        require_once WPFG_PLUGIN_DIR . 'includes/class-helpers.php';
        require_once WPFG_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WPFG_PLUGIN_DIR . 'includes/class-activator.php';
        WPFG_Activator::activate();
    }
}
add_action( 'admin_init', 'wpfg_maybe_upgrade' );

/**
 * Initialize the plugin after all plugins are loaded.
 */
function wpfg_init() {
    load_plugin_textdomain( 'wp-file-guardian', false, dirname( WPFG_PLUGIN_BASENAME ) . '/languages' );

    // Load core v1 includes.
    $includes = array(
        'helpers',
        'filesystem',
        'logger',
        'settings',
        'malware-patterns',
        'core-integrity',
        'scanner',
        'quarantine',
        'backup',
        'repair',
        'notifications',
        'cron',
        'system-info',
        // v2 includes.
        'db-scanner',
        'file-monitor',
        'login-guard',
        'risk-score',
        'remote-backup',
    );
    foreach ( $includes as $inc ) {
        $file = WPFG_PLUGIN_DIR . 'includes/class-' . $inc . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    if ( is_admin() ) {
        require_once WPFG_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPFG_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        WPFG_Admin::init();
        WPFG_Ajax_Handler::init();
    }

    WPFG_Cron::init();
    WPFG_Login_Guard::init();
    WPFG_File_Monitor::init();

    // WP-CLI commands.
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        require_once WPFG_PLUGIN_DIR . 'includes/class-wp-cli.php';
        WPFG_WP_CLI::init();
    }
}
add_action( 'plugins_loaded', 'wpfg_init' );
