<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * File and directory permission checker.
 * Scans for insecure permissions and provides remediation.
 */
class WPFG_Permission_Checker {

    /**
     * Recommended permissions for known paths.
     */
    private static $recommended = array(
        'wp-config.php' => 0600,
        '.htaccess'     => 0644,
        'index.php'     => 0644,
    );

    /**
     * Default permissions for files and directories.
     */
    const DEFAULT_FILE_PERMS = 0644;
    const DEFAULT_DIR_PERMS  = 0755;

    /**
     * Scan file and directory permissions.
     *
     * @param array|null $paths Paths to scan. Null uses defaults + critical files.
     * @return array List of permission issues.
     */
    public static function scan( $paths = null ) {
        // Windows detection.
        if ( PHP_OS_FAMILY === 'Windows' ) {
            return array(
                'issues'  => array(),
                'windows' => true,
                'message' => __( 'File permission checks are not applicable on Windows servers. Unix-style permissions are only available on Linux/Unix systems.', 'wp-file-guardian' ),
            );
        }

        if ( null === $paths ) {
            $paths = self::get_scan_paths();
        }

        $issues = array();

        foreach ( $paths as $path ) {
            $abs_path = self::resolve_path( $path );
            if ( ! file_exists( $abs_path ) ) {
                continue;
            }

            if ( is_dir( $abs_path ) ) {
                $issue = self::check_path( $abs_path );
                if ( $issue ) {
                    $issues[] = $issue;
                }
                // Also scan direct children of critical directories.
                if ( in_array( basename( $abs_path ), array( 'wp-content', 'wp-admin', 'wp-includes' ), true ) ) {
                    $children = @scandir( $abs_path );
                    if ( is_array( $children ) ) {
                        foreach ( $children as $child ) {
                            if ( '.' === $child || '..' === $child ) {
                                continue;
                            }
                            $child_path = $abs_path . '/' . $child;
                            $child_issue = self::check_path( $child_path );
                            if ( $child_issue ) {
                                $issues[] = $child_issue;
                            }
                        }
                    }
                }
            } else {
                $issue = self::check_path( $abs_path );
                if ( $issue ) {
                    $issues[] = $issue;
                }
            }
        }

        return array(
            'issues'  => $issues,
            'windows' => false,
            'message' => '',
        );
    }

    /**
     * Check permissions for a single path.
     *
     * @param string $path Absolute file or directory path.
     * @return array|null Issue array or null if permissions are correct.
     */
    public static function check_path( $path ) {
        if ( ! file_exists( $path ) ) {
            return null;
        }

        $current     = fileperms( $path ) & 0777;
        $recommended = self::get_recommended( $path );
        $is_dir      = is_dir( $path );
        $basename    = basename( $path );

        // Determine severity based on how far off permissions are.
        $severity = 'ok';
        $needs_fix = false;

        if ( $current !== $recommended ) {
            $needs_fix = true;

            // World-writable is critical.
            if ( $current & 0002 ) {
                $severity = 'critical';
            }
            // Group-writable on sensitive files is high.
            elseif ( ( $current & 0020 ) && in_array( $basename, array( 'wp-config.php', '.htaccess' ), true ) ) {
                $severity = 'high';
            }
            // Too permissive but not world-writable.
            elseif ( $current > $recommended ) {
                $severity = 'medium';
            }
            // Less permissive than recommended (usually fine).
            else {
                $severity  = 'low';
                $needs_fix = false;
            }
        }

        if ( ! $needs_fix ) {
            return null;
        }

        return array(
            'path'        => $path,
            'relative'    => self::relative_path( $path ),
            'current'     => $current,
            'current_str' => self::format_perms( $current ),
            'recommended' => $recommended,
            'rec_str'     => self::format_perms( $recommended ),
            'type'        => $is_dir ? 'directory' : 'file',
            'severity'    => $severity,
        );
    }

    /**
     * Get the recommended permission for a given path.
     *
     * @param string $path Absolute path.
     * @return int Octal permission value.
     */
    public static function get_recommended( $path ) {
        $basename = basename( $path );

        // Check specific file recommendations.
        if ( isset( self::$recommended[ $basename ] ) ) {
            return self::$recommended[ $basename ];
        }

        // Directories.
        if ( is_dir( $path ) ) {
            return self::DEFAULT_DIR_PERMS;
        }

        // Default for regular files.
        return self::DEFAULT_FILE_PERMS;
    }

    /**
     * Fix permissions on a single path.
     *
     * @param string $path Absolute path.
     * @param int    $mode Octal permission mode.
     * @return array { success: bool, message: string }
     */
    public static function fix_permission( $path, $mode = null ) {
        if ( PHP_OS_FAMILY === 'Windows' ) {
            return array(
                'success' => false,
                'message' => __( 'Permission changes are not supported on Windows.', 'wp-file-guardian' ),
            );
        }

        if ( ! file_exists( $path ) ) {
            return array(
                'success' => false,
                'message' => __( 'File or directory does not exist.', 'wp-file-guardian' ),
            );
        }

        if ( null === $mode ) {
            $mode = self::get_recommended( $path );
        }

        $result = @chmod( $path, $mode );

        if ( $result ) {
            WPFG_Logger::log( 'permission_fix', $path, 'success', sprintf(
                'Permissions changed to %s',
                self::format_perms( $mode )
            ) );
            return array(
                'success' => true,
                'message' => sprintf(
                    __( 'Permissions for %s changed to %s.', 'wp-file-guardian' ),
                    basename( $path ),
                    self::format_perms( $mode )
                ),
            );
        }

        WPFG_Logger::log( 'permission_fix', $path, 'error', 'Failed to change permissions' );
        return array(
            'success' => false,
            'message' => sprintf(
                __( 'Failed to change permissions for %s. The web server may not have sufficient privileges.', 'wp-file-guardian' ),
                basename( $path )
            ),
        );
    }

    /**
     * Fix all issues at once.
     *
     * @param array $issues Array of issue arrays from scan().
     * @return array { fixed: int, failed: int, results: array }
     */
    public static function fix_all( $issues ) {
        $fixed   = 0;
        $failed  = 0;
        $results = array();

        foreach ( $issues as $issue ) {
            $result = self::fix_permission( $issue['path'], $issue['recommended'] );
            $results[] = array_merge( $result, array( 'path' => $issue['path'] ) );

            if ( $result['success'] ) {
                $fixed++;
            } else {
                $failed++;
            }
        }

        return array(
            'fixed'   => $fixed,
            'failed'  => $failed,
            'results' => $results,
        );
    }

    /**
     * Get critical file/directory paths that should always be checked.
     *
     * @return array
     */
    public static function get_critical_files() {
        return array(
            ABSPATH . 'wp-config.php',
            ABSPATH . '.htaccess',
            ABSPATH . 'index.php',
            ABSPATH . 'wp-content',
            ABSPATH . 'wp-admin',
            ABSPATH . 'wp-includes',
        );
    }

    /**
     * Quick summary check of critical files only, for risk score.
     *
     * @return array { total_checked: int, issues_found: int, critical: int }
     */
    public static function get_summary() {
        if ( PHP_OS_FAMILY === 'Windows' ) {
            return array(
                'total_checked' => 0,
                'issues_found'  => 0,
                'critical'      => 0,
                'windows'       => true,
            );
        }

        $critical_files = self::get_critical_files();
        $issues_found   = 0;
        $critical_count = 0;

        foreach ( $critical_files as $path ) {
            if ( ! file_exists( $path ) ) {
                continue;
            }
            $issue = self::check_path( $path );
            if ( $issue ) {
                $issues_found++;
                if ( 'critical' === $issue['severity'] ) {
                    $critical_count++;
                }
            }
        }

        return array(
            'total_checked' => count( $critical_files ),
            'issues_found'  => $issues_found,
            'critical'      => $critical_count,
            'windows'       => false,
        );
    }

    /**
     * Get all paths to scan (settings + critical files).
     *
     * @return array
     */
    private static function get_scan_paths() {
        $paths = self::get_critical_files();

        $settings_paths = WPFG_Settings::get( 'scan_paths', array() );
        foreach ( (array) $settings_paths as $rel_path ) {
            $abs_path = ABSPATH . ltrim( $rel_path, '/' );
            if ( ! in_array( $abs_path, $paths, true ) ) {
                $paths[] = $abs_path;
            }
        }

        return $paths;
    }

    /**
     * Resolve a path to an absolute path.
     *
     * @param string $path
     * @return string
     */
    private static function resolve_path( $path ) {
        if ( 0 === strpos( $path, ABSPATH ) ) {
            return $path;
        }
        return ABSPATH . ltrim( $path, '/' );
    }

    /**
     * Format octal permissions as a string (e.g., "0755").
     *
     * @param int $perms
     * @return string
     */
    private static function format_perms( $perms ) {
        return sprintf( '%04o', $perms );
    }

    /**
     * Get relative path from ABSPATH.
     *
     * @param string $path
     * @return string
     */
    private static function relative_path( $path ) {
        $abspath = wp_normalize_path( ABSPATH );
        $path    = wp_normalize_path( $path );
        if ( 0 === strpos( $path, $abspath ) ) {
            return substr( $path, strlen( $abspath ) );
        }
        return $path;
    }
}
