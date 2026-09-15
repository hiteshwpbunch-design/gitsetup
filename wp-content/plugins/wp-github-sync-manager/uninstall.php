<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Only delete plugin data if explicitly requested in settings
$settings = get_option( 'wp_github_sync_settings', [] );

if ( ! empty( $settings['security']['delete_on_uninstall'] ) ) {
    global $wpdb;

    // Drop tables
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}github_sync_jobs" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}github_sync_logs" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}github_sync_snapshots" );

    // Delete options
    delete_option( 'wp_github_sync_settings' );
    delete_option( 'wp_github_sync_token' );
    delete_option( 'wp_github_sync_version' );
}
