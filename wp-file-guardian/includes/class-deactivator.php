<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles plugin deactivation.
 */
class WPFG_Deactivator {

    public static function deactivate() {
        // Clear scheduled cron events.
        wp_clear_scheduled_hook( 'wpfg_scheduled_scan' );
        wp_clear_scheduled_hook( 'wpfg_scheduled_backup' );
        wp_clear_scheduled_hook( 'wpfg_cleanup_old_backups' );
    }
}
