<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WordPress core file integrity checker.
 * Compares installed files against official checksums.
 */
class WPFG_Core_Integrity {

    /**
     * Fetch official WordPress checksums for a given version and locale.
     *
     * @param string $version WordPress version.
     * @param string $locale  Locale (default en_US).
     * @return array|WP_Error Associative array of file => md5 hash.
     */
    public static function get_checksums( $version = '', $locale = 'en_US' ) {
        if ( ! $version ) {
            global $wp_version;
            $version = $wp_version;
        }

        $url = sprintf(
            'https://api.wordpress.org/core/checksums/1.0/?version=%s&locale=%s',
            urlencode( $version ),
            urlencode( $locale )
        );

        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['checksums'] ) ) {
            return new WP_Error( 'no_checksums', __( 'Could not retrieve checksums for this WordPress version.', 'wp-file-guardian' ) );
        }

        return $body['checksums'];
    }

    /**
     * Verify core files against official checksums.
     *
     * @return array { modified: [], missing: [], extra: [], verified: int }
     */
    public static function verify( $checksums = null ) {
        if ( null === $checksums ) {
            $checksums = self::get_checksums();
        }
        if ( is_wp_error( $checksums ) ) {
            return $checksums;
        }

        $result = array(
            'modified' => array(),
            'missing'  => array(),
            'verified' => 0,
        );

        foreach ( $checksums as $file => $expected_hash ) {
            // Skip wp-content files in checksum list.
            if ( strpos( $file, 'wp-content/' ) === 0 ) {
                continue;
            }
            $full_path = ABSPATH . $file;

            if ( ! file_exists( $full_path ) ) {
                $result['missing'][] = $file;
                continue;
            }

            $actual_hash = md5_file( $full_path );
            if ( $actual_hash !== $expected_hash ) {
                $result['modified'][] = array(
                    'file'     => $file,
                    'expected' => $expected_hash,
                    'actual'   => $actual_hash,
                );
            } else {
                $result['verified']++;
            }
        }

        return $result;
    }

    /**
     * Get the download URL for a specific WordPress version.
     */
    public static function get_core_download_url( $version = '' ) {
        if ( ! $version ) {
            global $wp_version;
            $version = $wp_version;
        }
        return sprintf( 'https://downloads.wordpress.org/release/wordpress-%s.zip', $version );
    }

    /**
     * Download and extract specific core files for repair.
     *
     * @param array $files Array of relative file paths to restore.
     * @return array|WP_Error Results.
     */
    public static function repair_files( $files, $dry_run = false ) {
        global $wp_version;

        // Download the WordPress package.
        $url     = self::get_core_download_url( $wp_version );
        $tmp_dir = WPFG_Helpers::storage_dir() . '/temp/core-repair-' . time();
        wp_mkdir_p( $tmp_dir );

        $download = download_url( $url );
        if ( is_wp_error( $download ) ) {
            return $download;
        }

        // Extract.
        $result = WPFG_Filesystem::extract_zip( $download, $tmp_dir );
        @unlink( $download );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $core_dir = $tmp_dir . '/wordpress';
        $repaired = array();
        $errors   = array();

        foreach ( $files as $file ) {
            // Never overwrite wp-config.php or user content.
            if ( 'wp-config.php' === $file || strpos( $file, 'wp-content/' ) === 0 ) {
                continue;
            }

            $source = $core_dir . '/' . $file;
            $dest   = ABSPATH . $file;

            if ( ! file_exists( $source ) ) {
                $errors[] = sprintf( __( 'File not found in core package: %s', 'wp-file-guardian' ), $file );
                continue;
            }

            if ( $dry_run ) {
                $repaired[] = array(
                    'file'   => $file,
                    'action' => file_exists( $dest ) ? 'replace' : 'create',
                    'size'   => filesize( $source ),
                );
            } else {
                $dir = dirname( $dest );
                if ( ! is_dir( $dir ) ) {
                    wp_mkdir_p( $dir );
                }
                if ( copy( $source, $dest ) ) {
                    $repaired[] = $file;
                } else {
                    $errors[] = sprintf( __( 'Failed to restore: %s', 'wp-file-guardian' ), $file );
                }
            }
        }

        // Cleanup temp files.
        WPFG_Filesystem::delete_directory( $tmp_dir );

        return array(
            'repaired' => $repaired,
            'errors'   => $errors,
            'dry_run'  => $dry_run,
        );
    }
}
