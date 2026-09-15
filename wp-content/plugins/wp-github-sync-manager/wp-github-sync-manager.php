<?php
/**
 * Plugin Name: WP GitHub Sync Manager
 * Plugin URI:  https://github.com/
 * Description: Production-ready WordPress plugin to securely synchronize your WordPress project and database with GitHub without needing local Git binaries.
 * Version:     1.0.0
 * Author:      Hitesh
 * Author URI:  https://github.com/hitesh
 * Text Domain: wp-github-sync-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_GITHUB_SYNC_VERSION', '1.0.0' );
define( 'WP_GITHUB_SYNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_GITHUB_SYNC_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
require_once WP_GITHUB_SYNC_DIR . 'includes/class-autoloader.php';
WPGitHubSync\Autoloader::register();

// Activation hook
register_activation_hook( __FILE__, [ 'WPGitHubSync\Plugin', 'activate' ] );
// Deactivation hook
register_deactivation_hook( __FILE__, [ 'WPGitHubSync\Plugin', 'deactivate' ] );

// Initialize Plugin
add_action( 'plugins_loaded', function() {
    WPGitHubSync\Plugin::get_instance()->init();
} );
