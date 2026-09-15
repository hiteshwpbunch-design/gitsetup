<?php
namespace WPGitHubSync\Services;

defined( 'ABSPATH' ) || exit;

class Diff {
    public function __construct() {}

    /**
     * Generate HTML diff using WP native wp_text_diff.
     */
    public static function generate_diff( $local_file_path, $remote_content, $max_size = 2097152 ) { // 2MB max
        if ( file_exists( $local_file_path ) ) {
            $local_size = filesize( $local_file_path );
            if ( $local_size > $max_size ) {
                return '<div class="notice notice-warning"><p>' . __( 'Visual diff unavailable for large file.', 'wp-github-sync-manager' ) . '</p></div>';
            }
            $local_content = file_get_contents( $local_file_path );
        } else {
            $local_content = '';
        }

        if ( strlen( $remote_content ) > $max_size ) {
             return '<div class="notice notice-warning"><p>' . __( 'Visual diff unavailable for large file.', 'wp-github-sync-manager' ) . '</p></div>';
        }

        // Simple binary check
        if ( self::is_binary( $local_content ) || self::is_binary( $remote_content ) ) {
            return '<div class="notice notice-info"><p>' . __( 'Binary file changed. Visual diff not available.', 'wp-github-sync-manager' ) . '</p></div>';
        }

        // Must include WP diff libs
        if ( ! class_exists( 'WP_Text_Diff_Renderer_Table' ) ) {
            require_once ABSPATH . WPINC . '/wp-diff.php';
        }

        $local_lines  = explode( "\n", $local_content );
        $remote_lines = explode( "\n", $remote_content );

        $diff = wp_text_diff( $remote_lines, $local_lines, [
            'title_left'  => __( 'Remote (GitHub)', 'wp-github-sync-manager' ),
            'title_right' => __( 'Local (WordPress)', 'wp-github-sync-manager' ),
        ] );

        if ( empty( $diff ) ) {
            return '<p>' . __( 'No text differences found.', 'wp-github-sync-manager' ) . '</p>';
        }

        return '<div class="wp-github-sync-diff-wrapper">' . $diff . '</div>';
    }

    private static function is_binary( $content ) {
        // Fast binary detection: check first 512 bytes for null byte
        return strpos( substr( $content, 0, 512 ), "\x00" ) !== false;
    }
}
