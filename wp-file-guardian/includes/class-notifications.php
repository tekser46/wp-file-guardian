<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Email and dashboard notification service.
 */
class WPFG_Notifications {

    /**
     * Send an email notification.
     *
     * @param string $subject Email subject.
     * @param string $message Email body (plain text).
     * @param string $type    Notification type for filtering.
     */
    public static function send_email( $subject, $message, $type = 'general' ) {
        if ( ! WPFG_Settings::get( 'email_notifications', true ) ) {
            return false;
        }

        $to = WPFG_Settings::get( 'notification_email' );
        if ( ! $to ) {
            $to = get_option( 'admin_email' );
        }

        $site_name = get_bloginfo( 'name' );
        $subject   = sprintf( '[%s] WP File Guardian: %s', $site_name, $subject );
        $headers   = array( 'Content-Type: text/plain; charset=UTF-8' );

        $footer = "\n\n---\n" . sprintf(
            /* translators: %s: site URL */
            __( 'This notification was sent by WP File Guardian on %s', 'wp-file-guardian' ),
            home_url()
        );

        $email_sent = wp_mail( $to, $subject, $message . $footer, $headers );

        // Also send to Slack/Telegram if configured.
        self::send_slack( $subject . "\n" . $message );
        self::send_telegram( $subject . "\n" . $message );

        return $email_sent;
    }

    /**
     * Send a Slack webhook notification.
     */
    public static function send_slack( $text ) {
        $url = WPFG_Settings::get( 'slack_webhook_url' );
        if ( ! $url ) {
            return false;
        }

        $payload = wp_json_encode( array(
            'text'       => $text,
            'username'   => 'WP File Guardian',
            'icon_emoji' => ':shield:',
        ) );

        $response = wp_remote_post( $url, array(
            'body'    => $payload,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'timeout' => 10,
        ) );

        return ! is_wp_error( $response );
    }

    /**
     * Send a Telegram bot notification.
     */
    public static function send_telegram( $text ) {
        $token   = WPFG_Settings::get( 'telegram_bot_token' );
        $chat_id = WPFG_Settings::get( 'telegram_chat_id' );
        if ( ! $token || ! $chat_id ) {
            return false;
        }

        $url = sprintf( 'https://api.telegram.org/bot%s/sendMessage', $token );
        $response = wp_remote_post( $url, array(
            'body'    => array(
                'chat_id'    => $chat_id,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ),
            'timeout' => 10,
        ) );

        return ! is_wp_error( $response );
    }

    /**
     * Notify on critical scan findings.
     */
    public static function notify_critical_findings( $session_id, $critical_count ) {
        $subject = sprintf(
            /* translators: %d: number of critical issues */
            __( '%d Critical Issues Found During Scan', 'wp-file-guardian' ),
            $critical_count
        );
        $message = sprintf(
            __( "A file scan has completed and found %d critical issue(s).\n\nPlease review the findings in your WordPress dashboard:\n%s", 'wp-file-guardian' ),
            $critical_count,
            admin_url( 'admin.php?page=wpfg-scanner&session=' . $session_id )
        );
        self::send_email( $subject, $message, 'critical' );
    }

    /**
     * Notify on backup completion.
     */
    public static function notify_backup_completed( $type, $size ) {
        $subject = __( 'Backup Completed Successfully', 'wp-file-guardian' );
        $message = sprintf(
            __( "A %s backup has been created successfully.\nSize: %s\n\nManage backups: %s", 'wp-file-guardian' ),
            $type,
            WPFG_Helpers::format_bytes( $size ),
            admin_url( 'admin.php?page=wpfg-backups' )
        );
        self::send_email( $subject, $message, 'backup' );
    }

    /**
     * Notify on repair completion.
     */
    public static function notify_repair_completed( $repaired_count, $error_count ) {
        $subject = __( 'Core Repair Completed', 'wp-file-guardian' );
        $message = sprintf(
            __( "Core file repair has completed.\nFiles repaired: %d\nErrors: %d\n\nReview: %s", 'wp-file-guardian' ),
            $repaired_count,
            $error_count,
            admin_url( 'admin.php?page=wpfg-repair' )
        );
        self::send_email( $subject, $message, 'repair' );
    }

    /**
     * Notify on restore completion.
     */
    public static function notify_restore_completed( $backup_type ) {
        $subject = __( 'Backup Restored Successfully', 'wp-file-guardian' );
        $message = sprintf(
            __( "A %s backup has been restored successfully.\n\nPlease verify your site is working correctly.", 'wp-file-guardian' ),
            $backup_type
        );
        self::send_email( $subject, $message, 'restore' );
    }

    /**
     * Add a transient admin notice.
     */
    public static function add_admin_notice( $message, $type = 'info' ) {
        $notices   = get_transient( 'wpfg_admin_notices' );
        $notices   = is_array( $notices ) ? $notices : array();
        $notices[] = array( 'message' => $message, 'type' => $type );
        set_transient( 'wpfg_admin_notices', $notices, HOUR_IN_SECONDS );
    }

    /**
     * Display and clear admin notices.
     */
    public static function display_admin_notices() {
        $notices = get_transient( 'wpfg_admin_notices' );
        if ( ! is_array( $notices ) || empty( $notices ) ) {
            return;
        }
        foreach ( $notices as $notice ) {
            $class = 'notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible';
            printf( '<div class="%s"><p>%s</p></div>', $class, esc_html( $notice['message'] ) );
        }
        delete_transient( 'wpfg_admin_notices' );
    }
}
