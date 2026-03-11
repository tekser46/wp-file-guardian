<?php
/**
 * Plugin Name: WP File Guardian
 * Plugin URI:  https://example.com/wp-file-guardian
 * Description: Advanced file maintenance, repair, backup, malware scanning, and cleanup for WordPress.
 * Version:     1.0.0
 * Author:      WP File Guardian Team
 * Author URI:  https://example.com
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

define( 'WPFG_VERSION', '1.0.0' );
define( 'WPFG_DB_VERSION', '1.0.0' );
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
 * Initialize the plugin after all plugins are loaded.
 */
function wpfg_init() {
    load_plugin_textdomain( 'wp-file-guardian', false, dirname( WPFG_PLUGIN_BASENAME ) . '/languages' );

    // Load core includes.
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
    );
    foreach ( $includes as $inc ) {
        require_once WPFG_PLUGIN_DIR . 'includes/class-' . $inc . '.php';
    }

    if ( is_admin() ) {
        require_once WPFG_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPFG_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        WPFG_Admin::init();
        WPFG_Ajax_Handler::init();
    }

    WPFG_Cron::init();
}
add_action( 'plugins_loaded', 'wpfg_init' );
