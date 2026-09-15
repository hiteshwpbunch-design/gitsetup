<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

class Security {
    public function __construct() {}

    /**
     * Ensure the user has permission to perform GitHub sync actions.
     */
    public static function verify_user_permissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-github-sync-manager' ) );
        }
    }

    /**
     * Validate and normalize a local file path to ensure it is within the allowed sync root.
     */
    public static function validate_path( $path, $sync_root = null ) {
        if ( null === $sync_root ) {
            $settings = Settings::get_settings();
            $sync_root = isset($settings['sync']['sync_root']) ? $settings['sync']['sync_root'] : ABSPATH;
        }

        $normalized_path = wp_normalize_path( $path );
        $normalized_root = wp_normalize_path( $sync_root );

        // Basic directory traversal check
        if ( strpos( $normalized_path, '..' ) !== false ) {
            return false;
        }

        $real_path = realpath( $normalized_path );
        if ( ! $real_path ) {
            // Path doesn't exist yet, we still need to validate it
            $dir = dirname( $normalized_path );
            $real_dir = realpath( $dir );
            if ( ! $real_dir || strpos( wp_normalize_path( $real_dir ), $normalized_root ) !== 0 ) {
                return false;
            }
            return true;
        }

        $real_path = wp_normalize_path( $real_path );
        if ( strpos( $real_path, $normalized_root ) !== 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Hide specific sensitive keys/tokens from logs or outputs.
     */
    public static function sanitize_log_message( $message ) {
        $token = Settings::get_token();
        if ( ! empty( $token ) ) {
            $message = str_replace( $token, '[REDACTED]', $message );
        }
        return $message;
    }
}
