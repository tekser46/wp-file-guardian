<?php
/**
 * WP File Guardian Uninstall.
 * Runs when the plugin is deleted from WP admin.
 * Removes plugin data but preserves backups unless user confirms.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove database tables.
$tables = array(
    $wpdb->prefix . 'wpfg_scan_sessions',
    $wpdb->prefix . 'wpfg_scan_results',
    $wpdb->prefix . 'wpfg_quarantine',
    $wpdb->prefix . 'wpfg_backups',
    $wpdb->prefix . 'wpfg_logs',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
}

// Remove options.
delete_option( 'wpfg_settings' );
delete_option( 'wpfg_db_version' );

// Remove transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpfg_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpfg_%'" );

// Clear cron events.
wp_clear_scheduled_hook( 'wpfg_scheduled_scan' );
wp_clear_scheduled_hook( 'wpfg_scheduled_backup' );
wp_clear_scheduled_hook( 'wpfg_cleanup_old_backups' );

// Remove quarantine and temp directories (but NOT backups — preserve them).
$upload_dir = wp_upload_dir();
$base       = trailingslashit( $upload_dir['basedir'] ) . 'wpfg';

// Helper to delete a directory recursively.
function wpfg_uninstall_rmdir( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $items as $item ) {
        if ( $item->isDir() ) {
            rmdir( $item->getRealPath() );
        } else {
            unlink( $item->getRealPath() );
        }
    }
    rmdir( $dir );
}

// Remove quarantine and temp (backups are preserved for safety).
wpfg_uninstall_rmdir( $base . '/quarantine' );
wpfg_uninstall_rmdir( $base . '/temp' );

// Remove .htaccess and index.php in base dir, but leave backups folder.
$files_to_remove = array( $base . '/.htaccess', $base . '/index.php' );
foreach ( $files_to_remove as $file ) {
    if ( file_exists( $file ) ) {
        unlink( $file );
    }
}
