<?php
namespace WPGitHubSync\Services;

defined( 'ABSPATH' ) || exit;

class Backup_Manager {
    private $backup_dir;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->backup_dir = wp_normalize_path( $upload_dir['basedir'] . '/wp-github-sync/backups' );
    }

    public function create_file_backup( $files_to_backup ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'missing_zip', __( 'ZipArchive extension is missing.', 'wp-github-sync-manager' ) );
        }

        $filename = 'backup-files-' . current_time( 'Y-m-d-H-i-s' ) . '-' . wp_generate_password( 6, false ) . '.zip';
        $filepath = $this->backup_dir . '/' . $filename;
        
        $zip = new \ZipArchive();
        if ( $zip->open( $filepath, \ZipArchive::CREATE ) !== true ) {
            return new \WP_Error( 'zip_failed', __( 'Could not create zip archive.', 'wp-github-sync-manager' ) );
        }

        $settings = \WPGitHubSync\Settings::get_settings();
        $sync_root = isset($settings['sync']['sync_root']) ? wp_normalize_path($settings['sync']['sync_root']) : wp_normalize_path(ABSPATH);

        foreach ( $files_to_backup as $relative_path ) {
            $full_path = wp_normalize_path( $sync_root . '/' . $relative_path );
            if ( file_exists( $full_path ) ) {
                $zip->addFile( $full_path, $relative_path );
            }
        }
        
        $zip->close();
        
        // Record Snapshot
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'github_sync_snapshots',
            [
                'snapshot_uuid' => wp_generate_uuid4(),
                'type' => 'file_backup',
                'path' => $filepath,
                'size' => filesize( $filepath ),
                'created_by' => get_current_user_id()
            ]
        );

        return $filepath;
    }

    public function enforce_retention() {
        $settings = \WPGitHubSync\Settings::get_settings();
        $retention = intval( $settings['backup']['retention'] ?? 5 );
        
        global $wpdb;
        $table = $wpdb->prefix . 'github_sync_snapshots';
        
        // Simple retention by number of records
        $snapshots = $wpdb->get_results( $wpdb->prepare( "SELECT id, path FROM $table WHERE type = %s ORDER BY created_at DESC", 'file_backup' ) );
        
        if ( count( $snapshots ) > $retention ) {
            for ( $i = $retention; $i < count( $snapshots ); $i++ ) {
                $snapshot = $snapshots[$i];
                if ( file_exists( $snapshot->path ) ) {
                    unlink( $snapshot->path );
                }
                $wpdb->delete( $table, [ 'id' => $snapshot->id ] );
            }
        }
    }
}
