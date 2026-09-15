<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

class Database {
    
    public function __construct() {}

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_jobs = $wpdb->prefix . 'github_sync_jobs';
        $sql_jobs = "CREATE TABLE $table_jobs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_uuid varchar(64) NOT NULL,
            operation varchar(32) NOT NULL,
            status varchar(32) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            repository varchar(255) DEFAULT NULL,
            branch varchar(255) DEFAULT NULL,
            source_commit_sha varchar(64) DEFAULT NULL,
            target_commit_sha varchar(64) DEFAULT NULL,
            total_items int(11) DEFAULT 0,
            processed_items int(11) DEFAULT 0,
            failed_items int(11) DEFAULT 0,
            skipped_items int(11) DEFAULT 0,
            current_item text,
            progress tinyint(3) DEFAULT 0,
            payload longtext,
            result longtext,
            error_message text,
            retry_count tinyint(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            started_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY job_uuid (job_uuid),
            KEY status (status),
            KEY operation (operation),
            KEY repository (repository(191)),
            KEY branch (branch(191)),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $sql_jobs );

        $table_logs = $wpdb->prefix . 'github_sync_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(64) NOT NULL,
            repository varchar(255) DEFAULT NULL,
            branch varchar(255) DEFAULT NULL,
            status varchar(32) NOT NULL,
            message text,
            commit_sha varchar(64) DEFAULT NULL,
            job_uuid varchar(64) DEFAULT NULL,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $sql_logs );

        $table_snapshots = $wpdb->prefix . 'github_sync_snapshots';
        $sql_snapshots = "CREATE TABLE $table_snapshots (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            snapshot_uuid varchar(64) NOT NULL,
            type varchar(64) NOT NULL,
            path text NOT NULL,
            size bigint(20) unsigned DEFAULT 0,
            hash varchar(128) DEFAULT NULL,
            environment varchar(32) DEFAULT 'production',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            metadata longtext,
            PRIMARY KEY (id),
            KEY snapshot_uuid (snapshot_uuid),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta( $sql_snapshots );
    }
}
