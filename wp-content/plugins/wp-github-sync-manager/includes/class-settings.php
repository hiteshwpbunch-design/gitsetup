<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

class Settings {
    const OPTION_NAME = 'wp_github_sync_settings';
    const TOKEN_OPTION = 'wp_github_sync_token';

    public function __construct() {}

    public static function create_defaults() {
        $defaults = [
            'repository' => [
                'owner' => '',
                'name' => '',
                'default_branch' => 'main',
            ],
            'sync' => [
                'sync_root' => ABSPATH,
                'file_batch_size' => 50,
                'max_file_size' => 10 * 1024 * 1024, // 10 MB
                'exclusions' => self::get_default_exclusions(),
            ],
            'database' => [
                'batch_size' => 1000,
                'excluded_tables' => [],
                'excluded_columns' => [
                    'user_pass',
                    'user_activation_key',
                    'session_tokens',
                ],
            ],
            'backup' => [
                'retention' => 5,
                'auto_backup_before_pull' => true,
                'auto_db_backup' => true,
            ],
            'security' => [
                'environment' => 'production',
                'require_confirmation' => true,
                'delete_on_uninstall' => false,
            ],
            'performance' => [
                'job_timeout' => 300,
                'retry_count' => 3,
            ]
        ];

        if ( false === get_option( self::OPTION_NAME ) ) {
            add_option( self::OPTION_NAME, $defaults );
        }
    }

    public static function get_default_exclusions() {
        return [
            '.git/',
            '.env',
            '.env.*',
            'wp-config.php',
            '*.log',
            'node_modules/',
            'vendor/',
            'wp-content/cache/',
            'wp-content/upgrade/',
            'wp-content/uploads/cache/'
        ];
    }

    public static function get_settings() {
        $settings = get_option( self::OPTION_NAME, [] );
        // Merge with defaults in case of missing keys
        return $settings; // For full implementation, recursive merge would be here
    }

    public static function set_token( $token ) {
        // Simple base64 encoding (in a real-world scenario, proper encryption is advised)
        update_option( self::TOKEN_OPTION, base64_encode( $token ) );
    }

    public static function get_token() {
        $token = get_option( self::TOKEN_OPTION );
        return $token ? base64_decode( $token ) : '';
    }
}
