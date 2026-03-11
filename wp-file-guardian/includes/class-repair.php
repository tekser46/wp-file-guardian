<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Repair service.
 * Handles core file restoration, plugin/theme reinstallation.
 */
class WPFG_Repair {

    /**
     * Verify WordPress core integrity and return report.
     */
    public static function verify_core() {
        return WPFG_Core_Integrity::verify();
    }

    /**
     * Repair modified/missing core files.
     *
     * @param array $files   Relative file paths to repair.
     * @param bool  $dry_run Preview changes only.
     * @return array|WP_Error
     */
    public static function repair_core( $files, $dry_run = false ) {
        // Create restore point before repair.
        if ( ! $dry_run && WPFG_Settings::get( 'backup_before_action', true ) ) {
            $existing = array_filter( array_map( function ( $f ) {
                $full = ABSPATH . $f;
                return file_exists( $full ) ? $full : null;
            }, $files ) );
            if ( ! empty( $existing ) ) {
                WPFG_Backup::create_restore_point( $existing, __( 'Before core repair', 'wp-file-guardian' ) );
            }
        }

        $result = WPFG_Core_Integrity::repair_files( $files, $dry_run );

        if ( ! is_wp_error( $result ) && ! $dry_run ) {
            WPFG_Logger::log( 'repair_core', implode( ', ', $files ), 'success',
                sprintf( '%d repaired, %d errors', count( $result['repaired'] ), count( $result['errors'] ) )
            );
        }

        return $result;
    }

    /**
     * Reinstall a plugin from WordPress.org.
     *
     * @param string $slug    Plugin slug.
     * @param bool   $dry_run Preview only.
     * @return array|WP_Error
     */
    public static function reinstall_plugin( $slug, $dry_run = false ) {
        if ( ! function_exists( 'plugins_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        if ( ! function_exists( 'install_plugin_install_status' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $api = plugins_api( 'plugin_information', array(
            'slug'   => sanitize_text_field( $slug ),
            'fields' => array( 'download_link' => true ),
        ) );

        if ( is_wp_error( $api ) ) {
            return $api;
        }

        if ( $dry_run ) {
            return array(
                'slug'    => $slug,
                'version' => $api->version,
                'action'  => 'reinstall',
            );
        }

        // Backup current plugin directory first.
        $plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
        if ( is_dir( $plugin_dir ) && WPFG_Settings::get( 'backup_before_action', true ) ) {
            $files = array();
            foreach ( WPFG_Filesystem::scan_directory( $plugin_dir ) as $f ) {
                $files[] = $f;
            }
            WPFG_Backup::create_restore_point( $files, sprintf( 'Before reinstall: %s', $slug ) );
        }

        // Use WP upgrader.
        if ( ! class_exists( 'Plugin_Upgrader' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
        $result   = $upgrader->install( $api->download_link, array( 'overwrite_package' => true ) );

        if ( is_wp_error( $result ) ) {
            WPFG_Logger::log( 'repair_plugin', $slug, 'error', $result->get_error_message() );
            return $result;
        }

        WPFG_Logger::log( 'repair_plugin', $slug, 'success', 'Reinstalled v' . $api->version );
        return array( 'slug' => $slug, 'version' => $api->version, 'status' => 'reinstalled' );
    }

    /**
     * Reinstall a theme from WordPress.org.
     */
    public static function reinstall_theme( $slug, $dry_run = false ) {
        if ( ! function_exists( 'themes_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }

        $api = themes_api( 'theme_information', array(
            'slug'   => sanitize_text_field( $slug ),
            'fields' => array( 'download_link' => true ),
        ) );

        if ( is_wp_error( $api ) ) {
            return $api;
        }

        if ( $dry_run ) {
            return array(
                'slug'    => $slug,
                'version' => $api->version,
                'action'  => 'reinstall',
            );
        }

        $theme_dir = get_theme_root() . '/' . $slug;
        if ( is_dir( $theme_dir ) && WPFG_Settings::get( 'backup_before_action', true ) ) {
            $files = array();
            foreach ( WPFG_Filesystem::scan_directory( $theme_dir ) as $f ) {
                $files[] = $f;
            }
            WPFG_Backup::create_restore_point( $files, sprintf( 'Before reinstall: %s', $slug ) );
        }

        if ( ! class_exists( 'Theme_Upgrader' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
        $result   = $upgrader->install( $api->download_link, array( 'overwrite_package' => true ) );

        if ( is_wp_error( $result ) ) {
            WPFG_Logger::log( 'repair_theme', $slug, 'error', $result->get_error_message() );
            return $result;
        }

        WPFG_Logger::log( 'repair_theme', $slug, 'success', 'Reinstalled v' . $api->version );
        return array( 'slug' => $slug, 'version' => $api->version, 'status' => 'reinstalled' );
    }
}
