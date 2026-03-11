<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WP-CLI commands for WP File Guardian.
 *
 * Usage:
 *   wp file-guardian scan [--type=full]
 *   wp file-guardian verify-core
 *   wp file-guardian backup [--type=full]
 *   wp file-guardian risk-score
 *   wp file-guardian monitor build-baseline
 *   wp file-guardian monitor check
 *   wp file-guardian db-scan [--table=posts]
 */
class WPFG_WP_CLI {

    /**
     * Register commands if WP-CLI is active.
     */
    public static function init() {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        WP_CLI::add_command( 'file-guardian scan', array( __CLASS__, 'cmd_scan' ) );
        WP_CLI::add_command( 'file-guardian verify-core', array( __CLASS__, 'cmd_verify_core' ) );
        WP_CLI::add_command( 'file-guardian backup', array( __CLASS__, 'cmd_backup' ) );
        WP_CLI::add_command( 'file-guardian risk-score', array( __CLASS__, 'cmd_risk_score' ) );
        WP_CLI::add_command( 'file-guardian monitor', array( __CLASS__, 'cmd_monitor' ) );
        WP_CLI::add_command( 'file-guardian db-scan', array( __CLASS__, 'cmd_db_scan' ) );
    }

    /**
     * Run a file scan.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Scan type: full, quick
     * ---
     * default: full
     * ---
     *
     * ## EXAMPLES
     *   wp file-guardian scan
     *   wp file-guardian scan --type=quick
     *
     * @param array $args
     * @param array $assoc_args
     */
    public static function cmd_scan( $args, $assoc_args ) {
        $type = isset( $assoc_args['type'] ) ? $assoc_args['type'] : 'full';

        WP_CLI::log( "Starting {$type} scan..." );

        $session_id = WPFG_Scanner::create_session( $type );
        $files      = WPFG_Scanner::collect_files( $session_id );
        $total      = count( $files );

        WP_CLI::log( sprintf( 'Collected %d files.', $total ) );

        $batch_size = (int) WPFG_Settings::get( 'batch_size', 500 );
        $offset     = 0;
        $progress   = \WP_CLI\Utils\make_progress_bar( 'Scanning', $total );

        while ( $offset < $total ) {
            $result = WPFG_Scanner::process_batch( $session_id, $offset, $batch_size );
            $processed = min( $result['processed'], $total );
            for ( $i = 0; $i < ( $processed - $offset ); $i++ ) {
                $progress->tick();
            }
            $offset = $processed;
            if ( $result['done'] ) {
                break;
            }
        }

        $progress->finish();

        $stats = WPFG_Scanner::get_dashboard_stats();
        WP_CLI::success( sprintf(
            'Scan complete. Files: %d | Critical: %d | Warnings: %d | Info: %d',
            $stats['total_files'],
            $stats['critical_count'],
            $stats['warning_count'],
            $stats['info_count']
        ) );

        if ( $stats['critical_count'] > 0 ) {
            WP_CLI::warning( sprintf( '%d critical issues found! Review them in the admin panel.', $stats['critical_count'] ) );
        }
    }

    /**
     * Verify WordPress core file integrity.
     *
     * ## EXAMPLES
     *   wp file-guardian verify-core
     */
    public static function cmd_verify_core( $args, $assoc_args ) {
        WP_CLI::log( 'Verifying WordPress core integrity...' );

        $result = WPFG_Core_Integrity::verify();
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
            return;
        }

        WP_CLI::log( sprintf( 'Verified: %d files', $result['verified'] ) );

        if ( ! empty( $result['modified'] ) ) {
            WP_CLI::warning( sprintf( '%d modified files:', count( $result['modified'] ) ) );
            foreach ( $result['modified'] as $m ) {
                WP_CLI::log( '  ✗ ' . $m['file'] );
            }
        }

        if ( ! empty( $result['missing'] ) ) {
            WP_CLI::warning( sprintf( '%d missing files:', count( $result['missing'] ) ) );
            foreach ( $result['missing'] as $f ) {
                WP_CLI::log( '  ✗ ' . $f );
            }
        }

        if ( empty( $result['modified'] ) && empty( $result['missing'] ) ) {
            WP_CLI::success( 'All core files verified. No modifications detected.' );
        }
    }

    /**
     * Create a backup.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Backup type: full, plugins, themes, uploads
     * ---
     * default: full
     * ---
     *
     * [--remote]
     * : Upload to configured remote destination after creation.
     *
     * ## EXAMPLES
     *   wp file-guardian backup
     *   wp file-guardian backup --type=plugins --remote
     */
    public static function cmd_backup( $args, $assoc_args ) {
        $type = isset( $assoc_args['type'] ) ? $assoc_args['type'] : 'full';

        WP_CLI::log( "Creating {$type} backup..." );

        $result = WPFG_Backup::create( $type );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
            return;
        }

        global $wpdb;
        $backup = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wpfg_backups WHERE id = %d", $result
        ) );

        WP_CLI::success( sprintf(
            'Backup created: %s (%s, %d files)',
            basename( $backup->file_path ),
            WPFG_Helpers::format_bytes( $backup->file_size ),
            $backup->file_count
        ) );

        // Remote upload if requested.
        if ( isset( $assoc_args['remote'] ) ) {
            WP_CLI::log( 'Uploading to remote...' );
            $upload_result = WPFG_Remote_Backup::upload( $backup->file_path );
            if ( is_wp_error( $upload_result ) ) {
                WP_CLI::warning( 'Remote upload failed: ' . $upload_result->get_error_message() );
            } else {
                WP_CLI::success( 'Remote upload completed.' );
            }
        }
    }

    /**
     * Show the site security risk score.
     *
     * ## EXAMPLES
     *   wp file-guardian risk-score
     */
    public static function cmd_risk_score( $args, $assoc_args ) {
        $result = WPFG_Risk_Score::calculate();

        WP_CLI::log( '' );
        WP_CLI::log( sprintf( '  Security Score: %d/100 (Grade: %s)', $result['score'], $result['grade'] ) );
        WP_CLI::log( str_repeat( '─', 50 ) );

        foreach ( $result['factors'] as $key => $factor ) {
            $icon = 'good' === $factor['status'] ? '✓' : ( 'bad' === $factor['status'] ? '✗' : '~' );
            $ded  = $factor['deduction'] > 0 ? sprintf( ' (-%d)', $factor['deduction'] ) : '';
            WP_CLI::log( sprintf( '  %s %s%s', $icon, $factor['label'], $ded ) );
        }

        WP_CLI::log( '' );
    }

    /**
     * File monitor commands.
     *
     * ## OPTIONS
     *
     * <subcommand>
     * : build-baseline or check
     *
     * ## EXAMPLES
     *   wp file-guardian monitor build-baseline
     *   wp file-guardian monitor check
     */
    public static function cmd_monitor( $args, $assoc_args ) {
        $sub = isset( $args[0] ) ? $args[0] : '';

        switch ( $sub ) {
            case 'build-baseline':
                WP_CLI::log( 'Building file baseline...' );
                $count = WPFG_File_Monitor::build_baseline( true );
                WP_CLI::success( sprintf( 'Baseline built with %d files.', $count ) );
                break;

            case 'check':
                WP_CLI::log( 'Comparing files against baseline...' );
                $result = WPFG_File_Monitor::compare();
                if ( is_wp_error( $result ) ) {
                    WP_CLI::error( $result->get_error_message() );
                    return;
                }
                WP_CLI::log( sprintf( 'Added: %d | Modified: %d | Deleted: %d',
                    $result['total_added'], $result['total_modified'], $result['total_deleted']
                ) );
                if ( $result['total_modified'] > 0 ) {
                    foreach ( array_slice( $result['modified'], 0, 20 ) as $m ) {
                        WP_CLI::log( '  ✗ Modified: ' . $m['path'] );
                    }
                }
                break;

            default:
                WP_CLI::error( 'Usage: wp file-guardian monitor <build-baseline|check>' );
        }
    }

    /**
     * Run a database scan.
     *
     * ## OPTIONS
     *
     * [--table=<table>]
     * : Table to scan: posts, options, comments, users, cron, all
     * ---
     * default: all
     * ---
     *
     * ## EXAMPLES
     *   wp file-guardian db-scan
     *   wp file-guardian db-scan --table=posts
     */
    public static function cmd_db_scan( $args, $assoc_args ) {
        $table = isset( $assoc_args['table'] ) ? $assoc_args['table'] : 'all';

        $all_findings = array();

        $targets = ( 'all' === $table )
            ? array( 'posts', 'options', 'comments', 'users', 'cron' )
            : array( $table );

        foreach ( $targets as $t ) {
            WP_CLI::log( "Scanning {$t}..." );

            switch ( $t ) {
                case 'posts':
                    $offset = 0;
                    do {
                        $result = WPFG_DB_Scanner::scan_posts( 500, $offset );
                        $all_findings = array_merge( $all_findings, $result['findings'] );
                        $offset = $result['processed'];
                    } while ( ! $result['done'] );
                    break;
                case 'options':
                    $offset = 0;
                    do {
                        $result = WPFG_DB_Scanner::scan_options( 500, $offset );
                        $all_findings = array_merge( $all_findings, $result['findings'] );
                        $offset = $result['processed'];
                    } while ( ! $result['done'] );
                    break;
                case 'comments':
                    $offset = 0;
                    do {
                        $result = WPFG_DB_Scanner::scan_comments( 500, $offset );
                        $all_findings = array_merge( $all_findings, $result['findings'] );
                        $offset = $result['processed'];
                    } while ( ! $result['done'] );
                    break;
                case 'users':
                    $all_findings = array_merge( $all_findings, WPFG_DB_Scanner::scan_users() );
                    break;
                case 'cron':
                    $all_findings = array_merge( $all_findings, WPFG_DB_Scanner::scan_cron() );
                    break;
            }
        }

        if ( empty( $all_findings ) ) {
            WP_CLI::success( 'No suspicious content found in database.' );
            return;
        }

        WP_CLI::warning( sprintf( '%d findings:', count( $all_findings ) ) );
        foreach ( $all_findings as $f ) {
            $icon = 'critical' === $f['severity'] ? '✗' : ( 'warning' === $f['severity'] ? '!' : 'i' );
            WP_CLI::log( sprintf( '  [%s] %s', strtoupper( $f['severity'] ), $f['label'] ) );
        }
    }
}
