<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Two-Factor Authentication (TOTP / RFC 6238).
 * Provides 2FA for WordPress login with backup codes and device remembering.
 */
class WPFG_Two_Factor {

    const META_ENABLED      = 'wpfg_2fa_enabled';
    const META_SECRET        = 'wpfg_2fa_secret';
    const META_BACKUP_CODES  = 'wpfg_2fa_backup_codes';
    const META_DEVICES       = 'wpfg_2fa_remembered_devices';
    const COOKIE_NAME        = 'wpfg_2fa_device';
    const TRANSIENT_PREFIX   = 'wpfg_2fa_pending_';
    const CODE_DIGITS        = 6;
    const TIME_STEP          = 30;

    /**
     * Base32 alphabet for TOTP secret encoding.
     */
    private static $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Initialize 2FA hooks.
     */
    public static function init() {
        add_filter( 'authenticate', array( __CLASS__, 'intercept_login' ), 99, 3 );
        add_action( 'init', array( __CLASS__, 'verify_2fa_login' ) );
        add_action( 'wp_login', array( __CLASS__, 'on_login_check_device' ), 5, 2 );
        add_action( 'show_user_profile', array( __CLASS__, 'render_profile_fields' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_fields' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_fields' ) );
    }

    /**
     * Intercept login if user has 2FA enabled and device is not remembered.
     *
     * @param WP_User|WP_Error|null $user
     * @param string $username
     * @param string $password
     * @return WP_User|WP_Error|null
     */
    public static function intercept_login( $user, $username, $password ) {
        if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
            return $user;
        }

        if ( ! self::is_enabled_for_user( $user->ID ) ) {
            return $user;
        }

        // Check if device is remembered.
        if ( self::is_device_remembered( $user->ID ) ) {
            return $user;
        }

        // Store pending login in transient.
        $token = wp_generate_password( 32, false );
        set_transient( self::TRANSIENT_PREFIX . $token, array(
            'user_id'    => $user->ID,
            'created_at' => time(),
        ), 5 * MINUTE_IN_SECONDS );

        // Get remember me preference.
        $remember = ! empty( $_POST['rememberme'] );

        // Output 2FA verification form.
        self::render_2fa_form( $token, $remember );

        // Prevent normal login flow from continuing.
        exit;
    }

    /**
     * Handle 2FA code verification POST.
     */
    public static function verify_2fa_login() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            return;
        }

        if ( empty( $_POST['wpfg_2fa_token'] ) || empty( $_POST['wpfg_2fa_code'] ) ) {
            return;
        }

        // Verify nonce.
        if ( ! wp_verify_nonce( $_POST['wpfg_2fa_nonce'], 'wpfg_2fa_verify' ) ) {
            return;
        }

        $token = sanitize_text_field( $_POST['wpfg_2fa_token'] );
        $code  = sanitize_text_field( $_POST['wpfg_2fa_code'] );

        $pending = get_transient( self::TRANSIENT_PREFIX . $token );
        if ( ! $pending || empty( $pending['user_id'] ) ) {
            // Token expired or invalid.
            wp_safe_redirect( wp_login_url() . '?wpfg_2fa_error=expired' );
            exit;
        }

        // Check token age (5 minutes max).
        if ( ( time() - $pending['created_at'] ) > 300 ) {
            delete_transient( self::TRANSIENT_PREFIX . $token );
            wp_safe_redirect( wp_login_url() . '?wpfg_2fa_error=expired' );
            exit;
        }

        $user_id = (int) $pending['user_id'];

        // Verify the code (TOTP or backup code).
        if ( ! self::verify_code( $user_id, $code ) ) {
            // Invalid code - show form again with error.
            $remember = ! empty( $_POST['wpfg_2fa_remember_login'] );
            self::render_2fa_form( $token, $remember, __( 'Invalid verification code. Please try again.', 'wp-file-guardian' ) );
            exit;
        }

        // Clean up transient.
        delete_transient( self::TRANSIENT_PREFIX . $token );

        // Remember device if requested.
        if ( ! empty( $_POST['wpfg_2fa_remember_device'] ) ) {
            self::remember_device( $user_id );
        }

        // Set auth cookie and redirect.
        $remember = ! empty( $_POST['wpfg_2fa_remember_login'] );
        wp_set_auth_cookie( $user_id, $remember );
        wp_set_current_user( $user_id );

        WPFG_Logger::log( '2fa_login', '', 'success', sprintf( 'User %d authenticated with 2FA', $user_id ) );

        $redirect_to = ! empty( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : admin_url();
        wp_safe_redirect( $redirect_to );
        exit;
    }

    /**
     * Check device on login (for logging purposes).
     *
     * @param string  $user_login
     * @param WP_User $user
     */
    public static function on_login_check_device( $user_login, $user ) {
        if ( self::is_enabled_for_user( $user->ID ) && self::is_device_remembered( $user->ID ) ) {
            WPFG_Logger::log( '2fa_remembered_device', '', 'success', sprintf( 'User %d bypassed 2FA with remembered device', $user->ID ) );
        }
    }

    /**
     * Render 2FA fields on user profile page.
     *
     * @param WP_User $user
     */
    public static function render_profile_fields( $user ) {
        $enabled = self::is_enabled_for_user( $user->ID );
        $secret  = self::get_user_secret( $user->ID );
        ?>
        <h2><?php esc_html_e( 'Two-Factor Authentication', 'wp-file-guardian' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( '2FA Status', 'wp-file-guardian' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="wpfg_2fa_enabled" value="1" <?php checked( $enabled ); ?> />
                        <?php esc_html_e( 'Enable two-factor authentication', 'wp-file-guardian' ); ?>
                    </label>
                </td>
            </tr>
            <?php if ( $enabled && $secret ) : ?>
            <tr>
                <th><?php esc_html_e( 'Secret Key', 'wp-file-guardian' ); ?></th>
                <td>
                    <code><?php echo esc_html( $secret ); ?></code>
                    <p class="description"><?php esc_html_e( 'Use this key in your authenticator app if you cannot scan the QR code.', 'wp-file-guardian' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Backup Codes', 'wp-file-guardian' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="wpfg_2fa_regenerate_backup" value="1" />
                        <?php esc_html_e( 'Generate new backup codes (this will invalidate existing codes)', 'wp-file-guardian' ); ?>
                    </label>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Save 2FA profile fields.
     *
     * @param int $user_id
     */
    public static function save_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        $was_enabled = self::is_enabled_for_user( $user_id );
        $now_enabled = ! empty( $_POST['wpfg_2fa_enabled'] );

        update_user_meta( $user_id, self::META_ENABLED, $now_enabled ? '1' : '0' );

        // Generate secret if enabling for the first time.
        if ( $now_enabled && ! $was_enabled ) {
            $secret = self::generate_secret();
            $encrypted = self::encrypt_secret( $secret );
            update_user_meta( $user_id, self::META_SECRET, $encrypted );
            self::generate_backup_codes( $user_id );
            WPFG_Logger::log( '2fa_enabled', '', 'success', sprintf( '2FA enabled for user %d', $user_id ) );
        }

        // Disable: clean up.
        if ( ! $now_enabled && $was_enabled ) {
            delete_user_meta( $user_id, self::META_SECRET );
            delete_user_meta( $user_id, self::META_BACKUP_CODES );
            delete_user_meta( $user_id, self::META_DEVICES );
            WPFG_Logger::log( '2fa_disabled', '', 'success', sprintf( '2FA disabled for user %d', $user_id ) );
        }

        // Regenerate backup codes if requested.
        if ( $now_enabled && ! empty( $_POST['wpfg_2fa_regenerate_backup'] ) ) {
            self::generate_backup_codes( $user_id );
        }
    }

    // ------------------------------------------------------------------
    // TOTP Core (RFC 6238)
    // ------------------------------------------------------------------

    /**
     * Generate a random 16-character base32 secret.
     *
     * @return string
     */
    public static function generate_secret() {
        $secret = '';
        for ( $i = 0; $i < 16; $i++ ) {
            $secret .= self::$base32_chars[ wp_rand( 0, 31 ) ];
        }
        return $secret;
    }

    /**
     * Generate a TOTP code for a given secret and time slice.
     *
     * @param string   $secret     Base32-encoded secret.
     * @param int|null $time_slice Time slice override (null = current).
     * @return string  6-digit code.
     */
    public static function get_totp_code( $secret, $time_slice = null ) {
        if ( null === $time_slice ) {
            $time_slice = floor( time() / self::TIME_STEP );
        }

        $secret_bytes = self::base32_decode( $secret );

        // Pack time as 8-byte big-endian.
        $time_bytes = pack( 'N*', 0, $time_slice );

        // HMAC-SHA1.
        $hash = hash_hmac( 'sha1', $time_bytes, $secret_bytes, true );

        // Dynamic truncation.
        $offset = ord( $hash[19] ) & 0x0f;
        $code   = (
            ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) |
            ( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
            ( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 ) |
            ( ord( $hash[ $offset + 3 ] ) & 0xff )
        ) % pow( 10, self::CODE_DIGITS );

        return str_pad( (string) $code, self::CODE_DIGITS, '0', STR_PAD_LEFT );
    }

    /**
     * Verify a code for a user (TOTP + backup codes).
     *
     * @param int    $user_id
     * @param string $code
     * @return bool
     */
    public static function verify_code( $user_id, $code ) {
        $code = trim( $code );

        // Try TOTP first: check current and adjacent time slices (+/- 1).
        $secret = self::get_user_secret( $user_id );
        if ( $secret ) {
            $current_slice = floor( time() / self::TIME_STEP );
            for ( $i = -1; $i <= 1; $i++ ) {
                $expected = self::get_totp_code( $secret, $current_slice + $i );
                if ( hash_equals( $expected, $code ) ) {
                    return true;
                }
            }
        }

        // Try backup codes.
        return self::verify_backup_code( $user_id, $code );
    }

    /**
     * Generate the otpauth:// URI for QR code generation.
     *
     * @param string $secret Base32 secret.
     * @param string $email  User email.
     * @return string
     */
    public static function generate_qr_uri( $secret, $email ) {
        return sprintf(
            'otpauth://totp/WPFileGuardian:%s?secret=%s&issuer=WPFileGuardian',
            rawurlencode( $email ),
            rawurlencode( $secret )
        );
    }

    // ------------------------------------------------------------------
    // Backup Codes
    // ------------------------------------------------------------------

    /**
     * Generate 10 backup codes for a user.
     *
     * @param int $user_id
     * @return array Plain-text backup codes (display once to user).
     */
    public static function generate_backup_codes( $user_id ) {
        $codes       = array();
        $hashed      = array();

        for ( $i = 0; $i < 10; $i++ ) {
            $code     = wp_generate_password( 8, false );
            $codes[]  = $code;
            $hashed[] = wp_hash_password( $code );
        }

        update_user_meta( $user_id, self::META_BACKUP_CODES, $hashed );

        return $codes;
    }

    /**
     * Verify a backup code and consume it on match.
     *
     * @param int    $user_id
     * @param string $code
     * @return bool
     */
    public static function verify_backup_code( $user_id, $code ) {
        $stored = get_user_meta( $user_id, self::META_BACKUP_CODES, true );
        if ( ! is_array( $stored ) || empty( $stored ) ) {
            return false;
        }

        foreach ( $stored as $index => $hash ) {
            if ( wp_check_password( $code, $hash ) ) {
                // Consume the code.
                unset( $stored[ $index ] );
                $stored = array_values( $stored );
                update_user_meta( $user_id, self::META_BACKUP_CODES, $stored );
                WPFG_Logger::log( '2fa_backup_code', '', 'success', sprintf( 'Backup code used for user %d. %d codes remaining.', $user_id, count( $stored ) ) );
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Device Remembering
    // ------------------------------------------------------------------

    /**
     * Remember the current device for a user.
     *
     * @param int $user_id
     */
    public static function remember_device( $user_id ) {
        $token = wp_generate_password( 64, false );
        $hash  = wp_hash( $token );

        $devices = get_user_meta( $user_id, self::META_DEVICES, true );
        if ( ! is_array( $devices ) ) {
            $devices = array();
        }

        $devices[] = array(
            'hash'       => $hash,
            'created_at' => time(),
            'ip'         => WPFG_Helpers::get_client_ip(),
        );

        // Keep only last 10 devices.
        $devices = array_slice( $devices, -10 );
        update_user_meta( $user_id, self::META_DEVICES, $devices );

        // Set cookie for 30 days.
        $expire = time() + ( 30 * DAY_IN_SECONDS );
        setcookie( self::COOKIE_NAME, $user_id . '|' . $token, $expire, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }

    /**
     * Check if the current device is remembered for a user.
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_device_remembered( $user_id ) {
        if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
            return false;
        }

        $parts = explode( '|', $_COOKIE[ self::COOKIE_NAME ], 2 );
        if ( count( $parts ) !== 2 ) {
            return false;
        }

        $cookie_user_id = (int) $parts[0];
        $cookie_token   = $parts[1];

        if ( $cookie_user_id !== $user_id ) {
            return false;
        }

        $hash    = wp_hash( $cookie_token );
        $devices = get_user_meta( $user_id, self::META_DEVICES, true );

        if ( ! is_array( $devices ) ) {
            return false;
        }

        foreach ( $devices as $device ) {
            if ( isset( $device['hash'] ) && hash_equals( $device['hash'], $hash ) ) {
                // Check if device is not older than 30 days.
                if ( ( time() - $device['created_at'] ) < ( 30 * DAY_IN_SECONDS ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if 2FA is enabled for a user.
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_enabled_for_user( $user_id ) {
        return '1' === get_user_meta( $user_id, self::META_ENABLED, true );
    }

    // ------------------------------------------------------------------
    // Encryption Helpers
    // ------------------------------------------------------------------

    /**
     * Encrypt a TOTP secret for storage.
     *
     * @param string $secret Plain-text secret.
     * @return string Base64-encoded encrypted string.
     */
    public static function encrypt_secret( $secret ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $secret );
        }

        $key    = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, 32 );
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $cipher ) {
            return base64_encode( $secret );
        }

        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt a stored TOTP secret.
     *
     * @param string $encrypted Base64-encoded encrypted string.
     * @return string Plain-text secret.
     */
    public static function decrypt_secret( $encrypted ) {
        $data = base64_decode( $encrypted );

        if ( ! function_exists( 'openssl_decrypt' ) || strlen( $data ) <= 16 ) {
            return $data;
        }

        $key = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, 32 );
        $iv  = substr( $data, 0, 16 );
        $raw = substr( $data, 16 );

        $decrypted = openssl_decrypt( $raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $decrypted ) {
            // Fallback: might be stored unencrypted.
            return $data;
        }

        return $decrypted;
    }

    // ------------------------------------------------------------------
    // Base32 Encode / Decode
    // ------------------------------------------------------------------

    /**
     * Decode a base32-encoded string to binary.
     *
     * @param string $input Base32 string.
     * @return string Binary data.
     */
    public static function base32_decode( $input ) {
        $input  = strtoupper( rtrim( $input, '=' ) );
        $length = strlen( $input );
        $buffer = 0;
        $bits   = 0;
        $output = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $val = strpos( self::$base32_chars, $input[ $i ] );
            if ( false === $val ) {
                continue;
            }
            $buffer = ( $buffer << 5 ) | $val;
            $bits  += 5;

            if ( $bits >= 8 ) {
                $bits   -= 8;
                $output .= chr( ( $buffer >> $bits ) & 0xff );
                $buffer &= ( 1 << $bits ) - 1;
            }
        }

        return $output;
    }

    /**
     * Encode binary data to base32.
     *
     * @param string $input Binary data.
     * @return string Base32 string.
     */
    public static function base32_encode( $input ) {
        $length = strlen( $input );
        $buffer = 0;
        $bits   = 0;
        $output = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $buffer = ( $buffer << 8 ) | ord( $input[ $i ] );
            $bits  += 8;

            while ( $bits >= 5 ) {
                $bits   -= 5;
                $output .= self::$base32_chars[ ( $buffer >> $bits ) & 0x1f ];
                $buffer &= ( 1 << $bits ) - 1;
            }
        }

        if ( $bits > 0 ) {
            $output .= self::$base32_chars[ ( $buffer << ( 5 - $bits ) ) & 0x1f ];
        }

        return $output;
    }

    // ------------------------------------------------------------------
    // Private Helpers
    // ------------------------------------------------------------------

    /**
     * Get the decrypted secret for a user.
     *
     * @param int $user_id
     * @return string|null
     */
    private static function get_user_secret( $user_id ) {
        $encrypted = get_user_meta( $user_id, self::META_SECRET, true );
        if ( empty( $encrypted ) ) {
            return null;
        }
        return self::decrypt_secret( $encrypted );
    }

    /**
     * Render the 2FA verification form (custom template, posts to wp-login.php).
     *
     * @param string $token     Pending login token.
     * @param bool   $remember  Remember me preference.
     * @param string $error     Optional error message.
     */
    private static function render_2fa_form( $token, $remember = false, $error = '' ) {
        $nonce = wp_create_nonce( 'wpfg_2fa_verify' );
        $login_url = wp_login_url();

        // Determine redirect URL.
        $redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( $_REQUEST['redirect_to'] ) : admin_url();

        // Clean output.
        if ( ! headers_sent() ) {
            nocache_headers();
            header( 'Content-Type: text/html; charset=utf-8' );
        }
        ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php printf( esc_html__( 'Two-Factor Authentication — %s', 'wp-file-guardian' ), get_bloginfo( 'name' ) ); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f0f1; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .wpfg-2fa-wrap { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 40px; max-width: 400px; width: 100%; }
        .wpfg-2fa-wrap h1 { font-size: 20px; margin-bottom: 8px; color: #1d2327; }
        .wpfg-2fa-wrap p { color: #50575e; font-size: 14px; margin-bottom: 20px; line-height: 1.5; }
        .wpfg-2fa-wrap label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; font-size: 14px; }
        .wpfg-2fa-wrap input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 18px; letter-spacing: 4px; text-align: center; }
        .wpfg-2fa-wrap input[type="text"]:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        .wpfg-2fa-wrap .wpfg-2fa-submit { width: 100%; padding: 10px; background: #2271b1; color: #fff; border: none; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 16px; }
        .wpfg-2fa-wrap .wpfg-2fa-submit:hover { background: #135e96; }
        .wpfg-2fa-error { background: #fcf0f1; border-left: 4px solid #d63638; padding: 12px; margin-bottom: 16px; color: #d63638; font-size: 13px; border-radius: 0 4px 4px 0; }
        .wpfg-2fa-remember { margin-top: 12px; }
        .wpfg-2fa-remember label { font-weight: normal; display: flex; align-items: center; gap: 6px; }
        .wpfg-2fa-back { display: block; text-align: center; margin-top: 16px; color: #2271b1; font-size: 13px; text-decoration: none; }
        .wpfg-2fa-back:hover { color: #135e96; text-decoration: underline; }
        .wpfg-2fa-icon { text-align: center; margin-bottom: 20px; color: #2271b1; }
    </style>
</head>
<body>
    <div class="wpfg-2fa-wrap">
        <div class="wpfg-2fa-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </div>
        <h1><?php esc_html_e( 'Two-Factor Authentication', 'wp-file-guardian' ); ?></h1>
        <p><?php esc_html_e( 'Enter the 6-digit code from your authenticator app, or use a backup code.', 'wp-file-guardian' ); ?></p>

        <?php if ( $error ) : ?>
            <div class="wpfg-2fa-error"><?php echo esc_html( $error ); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( $login_url ); ?>">
            <input type="hidden" name="wpfg_2fa_token" value="<?php echo esc_attr( $token ); ?>" />
            <input type="hidden" name="wpfg_2fa_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
            <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
            <?php if ( $remember ) : ?>
                <input type="hidden" name="wpfg_2fa_remember_login" value="1" />
            <?php endif; ?>

            <label for="wpfg-2fa-code"><?php esc_html_e( 'Verification Code', 'wp-file-guardian' ); ?></label>
            <input type="text" id="wpfg-2fa-code" name="wpfg_2fa_code" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9a-zA-Z]*" maxlength="20" autofocus required />

            <div class="wpfg-2fa-remember">
                <label>
                    <input type="checkbox" name="wpfg_2fa_remember_device" value="1" />
                    <?php esc_html_e( 'Remember this device for 30 days', 'wp-file-guardian' ); ?>
                </label>
            </div>

            <button type="submit" class="wpfg-2fa-submit"><?php esc_html_e( 'Verify', 'wp-file-guardian' ); ?></button>
        </form>

        <a href="<?php echo esc_url( $login_url ); ?>" class="wpfg-2fa-back"><?php esc_html_e( 'Back to login', 'wp-file-guardian' ); ?></a>
    </div>
</body>
</html>
        <?php
    }
}
