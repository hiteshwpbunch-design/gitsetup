<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

/**
 * PSR-4 Autoloader
 */
class Autoloader {
    public static function register() {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    public static function autoload( $class ) {
        $prefix = 'WPGitHubSync\\';
        $base_dir = WP_GITHUB_SYNC_DIR . 'includes/';

        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }

        $relative_class = substr( $class, $len );

        $parts = explode( '\\', $relative_class );
        $file_name = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';

        if ( ! empty( $parts ) ) {
            $path = implode( '/', $parts ) . '/' . $file_name;
        } else {
            $path = $file_name;
        }

        $file = $base_dir . $path;

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
}
