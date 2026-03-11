<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Filesystem abstraction layer.
 * Provides safe file operations with path validation.
 */
class WPFG_Filesystem {

    /**
     * Read file contents safely.
     */
    public static function read_file( $path ) {
        if ( ! self::validate_path( $path ) ) {
            return new WP_Error( 'invalid_path', __( 'Path is not allowed.', 'wp-file-guardian' ) );
        }
        if ( ! is_readable( $path ) ) {
            return new WP_Error( 'not_readable', __( 'File is not readable.', 'wp-file-guardian' ) );
        }
        return file_get_contents( $path ); // phpcs:ignore
    }

    /**
     * Write content to a file safely.
     */
    public static function write_file( $path, $content ) {
        if ( ! self::validate_path( $path ) ) {
            return new WP_Error( 'invalid_path', __( 'Path is not allowed.', 'wp-file-guardian' ) );
        }
        $dir = dirname( $path );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $result = file_put_contents( $path, $content ); // phpcs:ignore
        return false !== $result;
    }

    /**
     * Delete a file safely.
     */
    public static function delete_file( $path ) {
        if ( ! self::validate_path( $path ) ) {
            return new WP_Error( 'invalid_path', __( 'Path is not allowed.', 'wp-file-guardian' ) );
        }
        if ( ! file_exists( $path ) ) {
            return new WP_Error( 'not_found', __( 'File does not exist.', 'wp-file-guardian' ) );
        }
        wp_delete_file( $path );
        if ( file_exists( $path ) ) {
            @unlink( $path );
        }
        return ! file_exists( $path );
    }

    /**
     * Copy a file safely.
     */
    public static function copy_file( $source, $dest ) {
        if ( ! self::validate_path( $source ) || ! self::validate_path( $dest ) ) {
            return new WP_Error( 'invalid_path', __( 'Path is not allowed.', 'wp-file-guardian' ) );
        }
        if ( ! file_exists( $source ) ) {
            return new WP_Error( 'not_found', __( 'Source file does not exist.', 'wp-file-guardian' ) );
        }
        $dir = dirname( $dest );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        return copy( $source, $dest );
    }

    /**
     * Move / rename a file safely.
     */
    public static function move_file( $source, $dest ) {
        if ( ! self::validate_path( $source ) || ! self::validate_path( $dest ) ) {
            return new WP_Error( 'invalid_path', __( 'Path is not allowed.', 'wp-file-guardian' ) );
        }
        if ( ! file_exists( $source ) ) {
            return new WP_Error( 'not_found', __( 'Source file does not exist.', 'wp-file-guardian' ) );
        }
        $dir = dirname( $dest );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        return rename( $source, $dest );
    }

    /**
     * Recursively scan a directory and return file paths.
     * Uses a generator to handle large directories.
     *
     * @param string $dir       Directory path.
     * @param array  $excluded  Paths to exclude (relative to ABSPATH).
     * @param array  $skip_ext  Extensions to skip.
     * @param int    $max_depth Maximum recursion depth.
     * @return Generator<string>
     */
    public static function scan_directory( $dir, $excluded = array(), $skip_ext = array(), $max_depth = 20 ) {
        return self::_scan_recursive( $dir, $excluded, $skip_ext, $max_depth, 0 );
    }

    private static function _scan_recursive( $dir, $excluded, $skip_ext, $max_depth, $depth ) {
        if ( $depth > $max_depth ) {
            return;
        }
        $dir = rtrim( wp_normalize_path( $dir ), '/' );
        if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
            return;
        }

        $handle = opendir( $dir );
        if ( ! $handle ) {
            return;
        }

        while ( false !== ( $entry = readdir( $handle ) ) ) {
            if ( '.' === $entry || '..' === $entry ) {
                continue;
            }
            $full_path = $dir . '/' . $entry;
            $rel_path  = WPFG_Helpers::relative_path( $full_path );

            // Check exclusions.
            $skip = false;
            foreach ( $excluded as $ex ) {
                $ex = trim( $ex, '/' );
                if ( strpos( $rel_path, $ex ) === 0 ) {
                    $skip = true;
                    break;
                }
            }
            if ( $skip ) {
                continue;
            }

            if ( is_dir( $full_path ) ) {
                yield from self::_scan_recursive( $full_path, $excluded, $skip_ext, $max_depth, $depth + 1 );
            } else {
                // Skip excluded extensions.
                $ext = WPFG_Helpers::file_ext( $full_path );
                if ( ! empty( $skip_ext ) && in_array( $ext, $skip_ext, true ) ) {
                    continue;
                }
                yield $full_path;
            }
        }
        closedir( $handle );
    }

    /**
     * Validate that a path is within allowed boundaries.
     */
    public static function validate_path( $path ) {
        return WPFG_Helpers::is_allowed_path( $path );
    }

    /**
     * Get file metadata.
     */
    public static function file_info( $path ) {
        if ( ! file_exists( $path ) ) {
            return null;
        }
        return array(
            'path'        => $path,
            'relative'    => WPFG_Helpers::relative_path( $path ),
            'size'        => filesize( $path ),
            'modified'    => filemtime( $path ),
            'permissions' => WPFG_Helpers::file_permissions( $path ),
            'extension'   => WPFG_Helpers::file_ext( $path ),
            'hash'        => md5_file( $path ),
            'is_writable' => is_writable( $path ),
        );
    }

    /**
     * Create a ZIP archive from given file paths.
     *
     * @param string $zip_path Output ZIP file path.
     * @param array  $files    Array of absolute file paths.
     * @param string $base     Base directory to make paths relative inside the ZIP.
     * @return bool|WP_Error
     */
    public static function create_zip( $zip_path, $files, $base = '' ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'no_zip', __( 'ZipArchive extension is not available.', 'wp-file-guardian' ) );
        }

        $zip = new ZipArchive();
        $res = $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        if ( true !== $res ) {
            return new WP_Error( 'zip_error', __( 'Could not create ZIP archive.', 'wp-file-guardian' ) );
        }

        $base = $base ? rtrim( wp_normalize_path( $base ), '/' ) . '/' : '';

        foreach ( $files as $file ) {
            if ( ! is_file( $file ) || ! is_readable( $file ) ) {
                continue;
            }
            $local = $file;
            if ( $base && strpos( wp_normalize_path( $file ), $base ) === 0 ) {
                $local = substr( wp_normalize_path( $file ), strlen( $base ) );
            }
            $zip->addFile( $file, $local );
        }

        $zip->close();
        return true;
    }

    /**
     * Extract a ZIP archive to a directory.
     */
    public static function extract_zip( $zip_path, $dest ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'no_zip', __( 'ZipArchive extension is not available.', 'wp-file-guardian' ) );
        }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $zip_path ) ) {
            return new WP_Error( 'zip_error', __( 'Could not open ZIP archive.', 'wp-file-guardian' ) );
        }
        $zip->extractTo( $dest );
        $zip->close();
        return true;
    }

    /**
     * Delete a directory and all its contents recursively.
     */
    public static function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
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
        return rmdir( $dir );
    }
}
