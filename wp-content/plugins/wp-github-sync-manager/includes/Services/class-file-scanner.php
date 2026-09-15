<?php
namespace WPGitHubSync\Services;

use WPGitHubSync\Settings;

defined( 'ABSPATH' ) || exit;

class File_Scanner {
    private $sync_root;
    private $exclusions;

    public function __construct() {
        $settings = Settings::get_settings();
        $this->sync_root = isset( $settings['sync']['sync_root'] ) ? wp_normalize_path( $settings['sync']['sync_root'] ) : wp_normalize_path( ABSPATH );
        $this->exclusions = isset( $settings['sync']['exclusions'] ) ? $settings['sync']['exclusions'] : Settings::get_default_exclusions();
    }

    /**
     * Calculates the Git blob SHA-1 of a local file.
     * Formula: SHA-1("blob " + filesize + "\0" + file_contents)
     */
    public static function calculate_git_blob_sha( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        $filesize = filesize( $file_path );
        $header = "blob " . $filesize . "\0";
        
        // Use hash_init for memory efficiency on large files
        $ctx = hash_init( 'sha1' );
        hash_update( $ctx, $header );
        hash_update_file( $ctx, $file_path );
        
        return hash_final( $ctx );
    }

    public function is_excluded( $path ) {
        $relative_path = ltrim( str_replace( rtrim( $this->sync_root, '/' ), '', $path ), '/' );
        
        foreach ( $this->exclusions as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) continue;

            // Simple wildcard to regex conversion
            $regex = str_replace( '\*', '.*', preg_quote( $pattern, '/' ) );
            $regex = '/^' . $regex . '$/i'; // match exactly
            
            // if pattern ends with / it's a directory
            if ( substr( $pattern, -1 ) === '/' ) {
                $dir_pattern = substr( $pattern, 0, -1 );
                // check if the path starts with this directory or is exactly this directory
                if ( strpos( $relative_path . '/', $pattern ) === 0 ) {
                    return true;
                }
            } else {
                if ( preg_match( $regex, $relative_path ) ) {
                    return true;
                }
                // Also check basename for simple patterns like *.log
                if ( preg_match( $regex, basename( $relative_path ) ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    public function scan_directory( $dir = '' ) {
        $full_dir = wp_normalize_path( $this->sync_root . ( $dir ? '/' . $dir : '' ) );
        $files = [];

        if ( ! is_dir( $full_dir ) ) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $full_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ( $iterator as $fileinfo ) {
            $path = wp_normalize_path( $fileinfo->getPathname() );
            // Safely remove the sync root from the beginning of the path to get the relative path
            $relative_path = ltrim( str_replace( rtrim( $this->sync_root, '/' ), '', $path ), '/' );

            if ( $this->is_excluded( $path ) ) {
                if ( $fileinfo->isDir() ) {
                    // Skip descending into excluded directories
                    // Note: RecursiveDirectoryIterator doesn't easily skip dynamically without extending FilterIterator.
                    // A proper implementation would use FilterIterator. For brevity, we handle it here by just ignoring contents.
                }
                continue;
            }

            if ( $fileinfo->isFile() ) {
                // Ensure we don't include files that are inside excluded directories
                $is_in_excluded_dir = false;
                foreach ( $this->exclusions as $pattern ) {
                    if ( substr( $pattern, -1 ) === '/' && strpos( $relative_path . '/', $pattern ) === 0 ) {
                        $is_in_excluded_dir = true;
                        break;
                    }
                }

                if ( ! $is_in_excluded_dir ) {
                    $files[$relative_path] = [
                        'path' => $relative_path,
                        'full_path' => $path,
                        'size' => $fileinfo->getSize(),
                        'mtime' => $fileinfo->getMTime(),
                    ];
                }
            }
        }

        return $files;
    }
}
