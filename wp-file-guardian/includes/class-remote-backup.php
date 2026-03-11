<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Remote backup handler.
 * Supports FTP, SFTP, and custom directory targets.
 * S3 and Google Drive require additional libraries, marked as stubs.
 */
class WPFG_Remote_Backup {

    /**
     * Upload a backup file to a remote destination.
     *
     * @param string $local_path  Local backup file path.
     * @param string $destination 'ftp', 's3', 'google_drive', 'custom_dir'
     * @return true|WP_Error
     */
    public static function upload( $local_path, $destination = '' ) {
        if ( ! $destination ) {
            $destination = WPFG_Settings::get( 'remote_backup_type', '' );
        }

        if ( ! file_exists( $local_path ) ) {
            return new WP_Error( 'file_missing', __( 'Backup file not found.', 'wp-file-guardian' ) );
        }

        switch ( $destination ) {
            case 'ftp':
                return self::upload_ftp( $local_path );
            case 'custom_dir':
                return self::upload_custom_dir( $local_path );
            case 's3':
                return self::upload_s3( $local_path );
            case 'google_drive':
                return self::upload_google_drive( $local_path );
            default:
                return new WP_Error( 'no_destination', __( 'No remote backup destination configured.', 'wp-file-guardian' ) );
        }
    }

    /**
     * Upload via FTP.
     */
    private static function upload_ftp( $local_path ) {
        $host = WPFG_Settings::get( 'ftp_host', '' );
        $user = WPFG_Settings::get( 'ftp_user', '' );
        $pass = WPFG_Settings::get( 'ftp_pass', '' );
        $port = (int) WPFG_Settings::get( 'ftp_port', 21 );
        $dir  = WPFG_Settings::get( 'ftp_dir', '/' );
        $ssl  = (bool) WPFG_Settings::get( 'ftp_ssl', false );

        if ( ! $host || ! $user ) {
            return new WP_Error( 'ftp_config', __( 'FTP credentials not configured.', 'wp-file-guardian' ) );
        }

        $conn = $ssl ? @ftp_ssl_connect( $host, $port, 30 ) : @ftp_connect( $host, $port, 30 );
        if ( ! $conn ) {
            return new WP_Error( 'ftp_connect', __( 'Could not connect to FTP server.', 'wp-file-guardian' ) );
        }

        if ( ! @ftp_login( $conn, $user, $pass ) ) {
            ftp_close( $conn );
            return new WP_Error( 'ftp_login', __( 'FTP authentication failed.', 'wp-file-guardian' ) );
        }

        ftp_pasv( $conn, true );

        $remote_file = rtrim( $dir, '/' ) . '/' . basename( $local_path );
        $result = @ftp_put( $conn, $remote_file, $local_path, FTP_BINARY );
        ftp_close( $conn );

        if ( ! $result ) {
            return new WP_Error( 'ftp_upload', __( 'FTP upload failed.', 'wp-file-guardian' ) );
        }

        WPFG_Logger::log( 'remote_backup_ftp', $local_path, 'success', 'Uploaded to ' . $host . $remote_file );
        return true;
    }

    /**
     * Copy to a custom directory (e.g., external mount, NAS).
     */
    private static function upload_custom_dir( $local_path ) {
        $dir = WPFG_Settings::get( 'remote_custom_dir', '' );
        if ( ! $dir || ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return new WP_Error( 'custom_dir', __( 'Custom backup directory is not writable.', 'wp-file-guardian' ) );
        }

        $dest = trailingslashit( $dir ) . basename( $local_path );
        if ( ! copy( $local_path, $dest ) ) {
            return new WP_Error( 'copy_failed', __( 'Could not copy backup to remote directory.', 'wp-file-guardian' ) );
        }

        WPFG_Logger::log( 'remote_backup_dir', $local_path, 'success', 'Copied to ' . $dest );
        return true;
    }

    /**
     * Upload to Amazon S3.
     * STUB: Requires AWS SDK. Install via Composer: composer require aws/aws-sdk-php
     */
    private static function upload_s3( $local_path ) {
        $key    = WPFG_Settings::get( 's3_access_key', '' );
        $secret = WPFG_Settings::get( 's3_secret_key', '' );
        $bucket = WPFG_Settings::get( 's3_bucket', '' );
        $region = WPFG_Settings::get( 's3_region', 'eu-central-1' );
        $prefix = WPFG_Settings::get( 's3_prefix', 'wpfg-backups/' );

        if ( ! $key || ! $secret || ! $bucket ) {
            return new WP_Error( 's3_config', __( 'S3 credentials not configured.', 'wp-file-guardian' ) );
        }

        // Check if AWS SDK is available.
        if ( ! class_exists( 'Aws\S3\S3Client' ) ) {
            // Try WordPress HTTP API multipart upload as fallback.
            return self::upload_s3_native( $local_path, $key, $secret, $bucket, $region, $prefix );
        }

        // @codeCoverageIgnoreStart
        try {
            $client = new \Aws\S3\S3Client( array(
                'version'     => 'latest',
                'region'      => $region,
                'credentials' => array( 'key' => $key, 'secret' => $secret ),
            ) );

            $client->putObject( array(
                'Bucket'     => $bucket,
                'Key'        => $prefix . basename( $local_path ),
                'SourceFile' => $local_path,
            ) );

            WPFG_Logger::log( 'remote_backup_s3', $local_path, 'success', 'Bucket: ' . $bucket );
            return true;
        } catch ( \Exception $e ) {
            return new WP_Error( 's3_error', $e->getMessage() );
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Native S3 upload using WordPress HTTP API (no AWS SDK required).
     * Supports files up to ~100MB.
     */
    private static function upload_s3_native( $local_path, $key, $secret, $bucket, $region, $prefix ) {
        $filename   = basename( $local_path );
        $object_key = $prefix . $filename;
        $date       = gmdate( 'Ymd\THis\Z' );
        $datestamp  = gmdate( 'Ymd' );
        $host       = "{$bucket}.s3.{$region}.amazonaws.com";
        $url        = "https://{$host}/{$object_key}";

        $content      = file_get_contents( $local_path );
        $content_hash = hash( 'sha256', $content );

        // Create canonical request for AWS Signature V4.
        $scope         = "{$datestamp}/{$region}/s3/aws4_request";
        $canonical     = "PUT\n/{$object_key}\n\ncontent-type:application/octet-stream\nhost:{$host}\nx-amz-content-sha256:{$content_hash}\nx-amz-date:{$date}\n\ncontent-type;host;x-amz-content-sha256;x-amz-date\n{$content_hash}";
        $string_to_sign = "AWS4-HMAC-SHA256\n{$date}\n{$scope}\n" . hash( 'sha256', $canonical );

        $signing_key = hash_hmac( 'sha256', 'aws4_request',
            hash_hmac( 'sha256', 's3',
                hash_hmac( 'sha256', $region,
                    hash_hmac( 'sha256', $datestamp, "AWS4{$secret}", true ), true ), true ), true );
        $signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );

        $auth = "AWS4-HMAC-SHA256 Credential={$key}/{$scope}, SignedHeaders=content-type;host;x-amz-content-sha256;x-amz-date, Signature={$signature}";

        $response = wp_remote_request( $url, array(
            'method'  => 'PUT',
            'timeout' => 300,
            'headers' => array(
                'Content-Type'           => 'application/octet-stream',
                'x-amz-content-sha256'   => $content_hash,
                'x-amz-date'             => $date,
                'Authorization'          => $auth,
            ),
            'body' => $content,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 200 && $code < 300 ) {
            WPFG_Logger::log( 'remote_backup_s3', $local_path, 'success', 'Bucket: ' . $bucket );
            return true;
        }

        return new WP_Error( 's3_error', sprintf( __( 'S3 upload failed with status %d', 'wp-file-guardian' ), $code ) );
    }

    /**
     * Upload to Google Drive.
     * STUB: Requires OAuth2 setup and Google API Client Library.
     */
    private static function upload_google_drive( $local_path ) {
        // Google Drive integration requires OAuth2 token exchange and Google API Client.
        // This is marked as a future premium feature.
        return new WP_Error( 'not_implemented',
            __( 'Google Drive backup requires additional setup. Please use FTP, S3, or custom directory for now.', 'wp-file-guardian' )
        );
    }

    /**
     * Test connection to the configured remote destination.
     */
    public static function test_connection( $type = '' ) {
        if ( ! $type ) {
            $type = WPFG_Settings::get( 'remote_backup_type', '' );
        }

        switch ( $type ) {
            case 'ftp':
                $host = WPFG_Settings::get( 'ftp_host', '' );
                $user = WPFG_Settings::get( 'ftp_user', '' );
                $pass = WPFG_Settings::get( 'ftp_pass', '' );
                $port = (int) WPFG_Settings::get( 'ftp_port', 21 );
                $ssl  = (bool) WPFG_Settings::get( 'ftp_ssl', false );

                $conn = $ssl ? @ftp_ssl_connect( $host, $port, 10 ) : @ftp_connect( $host, $port, 10 );
                if ( ! $conn ) {
                    return new WP_Error( 'ftp_connect', __( 'Connection failed.', 'wp-file-guardian' ) );
                }
                if ( ! @ftp_login( $conn, $user, $pass ) ) {
                    ftp_close( $conn );
                    return new WP_Error( 'ftp_login', __( 'Authentication failed.', 'wp-file-guardian' ) );
                }
                ftp_close( $conn );
                return true;

            case 'custom_dir':
                $dir = WPFG_Settings::get( 'remote_custom_dir', '' );
                if ( ! $dir || ! is_dir( $dir ) ) {
                    return new WP_Error( 'dir_missing', __( 'Directory does not exist.', 'wp-file-guardian' ) );
                }
                if ( ! is_writable( $dir ) ) {
                    return new WP_Error( 'dir_readonly', __( 'Directory is not writable.', 'wp-file-guardian' ) );
                }
                return true;

            case 's3':
                $key    = WPFG_Settings::get( 's3_access_key', '' );
                $bucket = WPFG_Settings::get( 's3_bucket', '' );
                if ( ! $key || ! $bucket ) {
                    return new WP_Error( 's3_config', __( 'S3 not configured.', 'wp-file-guardian' ) );
                }
                return true; // Full test would require an actual API call.

            default:
                return new WP_Error( 'no_type', __( 'No remote backup type selected.', 'wp-file-guardian' ) );
        }
    }
}
