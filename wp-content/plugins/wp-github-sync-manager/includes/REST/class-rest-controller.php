<?php
namespace WPGitHubSync\REST;

defined( 'ABSPATH' ) || exit;

class REST_Controller {
    private $namespace = 'wp-github-sync/v1';

    public function __construct() {}

    public function register_routes() {
        register_rest_route( $this->namespace, '/status', [
            'methods'  => \WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_status' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( $this->namespace, '/repository/connect', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'connect_repository' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
        
        register_rest_route( $this->namespace, '/push/init', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'push_init' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
        
        register_rest_route( $this->namespace, '/push/blob', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'push_blob' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( $this->namespace, '/push/commit', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'push_commit' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // DB Export
        register_rest_route( $this->namespace, '/db/export/init', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'db_export_init' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
        
        register_rest_route( $this->namespace, '/db/export/chunk', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'db_export_chunk' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        register_rest_route( $this->namespace, '/db/export/finalize', [
            'methods'  => \WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'db_export_finalize' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );
    }

    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    public function get_status( \WP_REST_Request $request ) {
        return rest_ensure_response( [
            'success' => true,
            'status' => 'ok',
            'version' => WP_GITHUB_SYNC_VERSION
        ] );
    }

    public function connect_repository( \WP_REST_Request $request ) {
        $token = $request->get_param( 'token' );
        $owner = $request->get_param( 'owner' );
        $repo  = $request->get_param( 'repo' );

        if ( empty( $token ) || empty( $owner ) || empty( $repo ) ) {
            return new \WP_Error( 'missing_params', 'Missing required parameters.', [ 'status' => 400 ] );
        }

        \WPGitHubSync\Settings::set_token( $token );
        
        $settings = \WPGitHubSync\Settings::get_settings();
        $settings['repository']['owner'] = sanitize_text_field( $owner );
        $settings['repository']['name'] = sanitize_text_field( $repo );
        update_option( \WPGitHubSync\Settings::OPTION_NAME, $settings );

        // Test connection
        $api = new \WPGitHubSync\Services\GitHub_API();
        $result = $api->test_connection();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Repository connected successfully.', 'wp-github-sync-manager' ),
            'repo' => $result
        ] );
    }

    public function push_init( \WP_REST_Request $request ) {
        $compare_result = get_transient( 'wp_github_sync_push_compare_' . get_current_user_id() );
        if ( ! $compare_result ) {
            return new \WP_Error( 'expired', 'Compare data expired. Please rescan.' );
        }

        $files_to_push = array_merge( 
            $compare_result['changes']['added'], 
            array_column($compare_result['changes']['modified'], 'path') 
        );

        $job = new \WPGitHubSync\Services\Sync_Job();
        $job_uuid = $job->create_job( 'push', '', '', count( $files_to_push ), [
            'tree_data' => [],
            'deletions' => $compare_result['changes']['deleted'],
            'base_tree' => $compare_result['base_tree'],
            'base_commit' => $compare_result['base_commit']
        ]);

        return rest_ensure_response( [
            'success' => true,
            'job_uuid' => $job_uuid,
            'files' => $files_to_push
        ] );
    }

    public function push_blob( \WP_REST_Request $request ) {
        $job_uuid = $request->get_param( 'job_uuid' );
        $path = $request->get_param( 'path' );
        
        $job_obj = new \WPGitHubSync\Services\Sync_Job( $job_uuid );
        $job = $job_obj->get_job();
        if ( ! $job ) return new \WP_Error( 'invalid_job', 'Job not found.' );

        $settings = \WPGitHubSync\Settings::get_settings();
        $sync_root = wp_normalize_path( $settings['sync']['sync_root'] );
        $full_path = wp_normalize_path( $sync_root . '/' . $path );

        if ( ! file_exists( $full_path ) ) {
            return new \WP_Error( 'file_not_found', "File not found locally: $path" );
        }

        $api = new \WPGitHubSync\Services\GitHub_API();
        $owner = $settings['repository']['owner'];
        $repo = $settings['repository']['name'];

        $content = file_get_contents( $full_path );
        $blob_res = $api->create_blob( $owner, $repo, base64_encode( $content ), 'base64' );

        if ( is_wp_error( $blob_res ) ) {
            $job_obj->update_progress( 0, 1, 0, $path );
            return $blob_res;
        }

        // Save to payload
        $payload = json_decode( $job['payload'], true );
        $payload['tree_data'][] = [
            'path' => $path,
            'mode' => '100644',
            'type' => 'blob',
            'sha'  => $blob_res['sha']
        ];
        
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'github_sync_jobs',
            [ 'payload' => wp_json_encode( $payload ) ],
            [ 'job_uuid' => $job_uuid ]
        );

        $job_obj->update_progress( 1, 0, 0, $path );

        return rest_ensure_response( [ 'success' => true, 'sha' => $blob_res['sha'] ] );
    }

    public function push_commit( \WP_REST_Request $request ) {
        $job_uuid = $request->get_param( 'job_uuid' );
        $commit_message = $request->get_param( 'commit_message' ) ?: 'Automated commit';

        $job_obj = new \WPGitHubSync\Services\Sync_Job( $job_uuid );
        $job = $job_obj->get_job();
        if ( ! $job ) return new \WP_Error( 'invalid_job', 'Job not found.' );

        $payload = json_decode( $job['payload'], true );
        $tree_data = $payload['tree_data'];

        foreach ( $payload['deletions'] as $del ) {
            $tree_data[] = [
                'path' => $del['path'],
                'mode' => '100644',
                'type' => 'blob',
                'sha'  => null
            ];
        }

        if ( empty( $tree_data ) ) {
            return new \WP_Error( 'no_changes', 'No valid changes found to commit.' );
        }

        $settings = \WPGitHubSync\Settings::get_settings();
        $api = new \WPGitHubSync\Services\GitHub_API();
        $owner = $settings['repository']['owner'];
        $repo = $settings['repository']['name'];
        $branch = $settings['repository']['default_branch'];

        $tree_res = $api->create_tree( $owner, $repo, $payload['base_tree'], $tree_data );
        if ( is_wp_error( $tree_res ) ) return $tree_res;
        
        $commit_res = $api->create_commit( $owner, $repo, $commit_message, $tree_res['sha'], [ $payload['base_commit'] ] );
        if ( is_wp_error( $commit_res ) ) return $commit_res;

        $ref_res = $api->update_ref( $owner, $repo, $branch, $commit_res['sha'] );
        if ( is_wp_error( $ref_res ) ) return $ref_res;

        $job_obj->complete_job( [ 'commit_sha' => $commit_res['sha'] ] );
        delete_transient( 'wp_github_sync_push_compare_' . get_current_user_id() );

        return rest_ensure_response( [ 'success' => true, 'commit_sha' => $commit_res['sha'] ] );
    }

    public function db_export_init( \WP_REST_Request $request ) {
        $tables = $request->get_param( 'tables' );
        if ( empty( $tables ) || ! is_array( $tables ) ) {
            return new \WP_Error( 'missing_tables', 'No tables selected.' );
        }

        // Validate tables exist
        global $wpdb;
        $valid_tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}%'" );
        $tables_to_export = array_intersect( $tables, $valid_tables );

        if ( empty( $tables_to_export ) ) {
            return new \WP_Error( 'invalid_tables', 'Invalid tables selected.' );
        }

        // Create export directory
        $upload_dir = wp_upload_dir();
        $export_dir = wp_normalize_path( $upload_dir['basedir'] . '/wp-github-sync/exports/' . date('Y-m-d-H-i-s') . '-' . wp_generate_password( 6, false ) );
        wp_mkdir_p( $export_dir );

        $job = new \WPGitHubSync\Services\Sync_Job();
        $job_uuid = $job->create_job( 'db_export', '', '', count( $tables_to_export ), [
            'export_dir' => $export_dir,
            'tables' => $tables_to_export,
            'table_status' => array_fill_keys( $tables_to_export, [ 'offset' => 0, 'completed' => false ] )
        ]);

        return rest_ensure_response( [
            'success' => true,
            'job_uuid' => $job_uuid,
            'tables' => $tables_to_export
        ] );
    }

    public function db_export_chunk( \WP_REST_Request $request ) {
        $job_uuid = $request->get_param( 'job_uuid' );
        $table = $request->get_param( 'table' );
        
        $job_obj = new \WPGitHubSync\Services\Sync_Job( $job_uuid );
        $job = $job_obj->get_job();
        if ( ! $job ) return new \WP_Error( 'invalid_job', 'Job not found.' );

        $payload = json_decode( $job['payload'], true );
        if ( ! isset( $payload['table_status'][$table] ) ) {
            return new \WP_Error( 'invalid_table', 'Table not in job.' );
        }
        if ( $payload['table_status'][$table]['completed'] ) {
            return rest_ensure_response( [ 'success' => true, 'completed' => true ] );
        }

        $settings = \WPGitHubSync\Settings::get_settings();
        $batch_size = isset( $settings['database']['batch_size'] ) ? intval( $settings['database']['batch_size'] ) : 1000;
        $offset = intval( $payload['table_status'][$table]['offset'] );

        $db_sync = new \WPGitHubSync\Services\Database_Sync();
        
        // Disable foreign key checks for the export file header if offset is 0
        $sql_header = "";
        if ( $offset === 0 ) {
            $sql_header .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
            $sql_header .= "DROP TABLE IF EXISTS `$table`;\n";
            global $wpdb;
            $create_table = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
            if ( $create_table ) {
                $sql_header .= $create_table[1] . ";\n";
            }
        }

        $result = $db_sync->export_table_chunk( $table, $offset, $batch_size );
        
        $export_file = $payload['export_dir'] . '/' . $table . '.sql';
        
        if ( $result === false || $result['count'] === 0 ) {
            // Table export finished
            $payload['table_status'][$table]['completed'] = true;
            
            // Re-enable FK checks
            file_put_contents( $export_file, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n", FILE_APPEND );
            
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'github_sync_jobs',
                [ 'payload' => wp_json_encode( $payload ) ],
                [ 'job_uuid' => $job_uuid ]
            );
            $job_obj->update_progress( 1, 0, 0, $table );
            
            return rest_ensure_response( [ 'success' => true, 'completed' => true ] );
        }

        // Append to file
        if ( $offset === 0 && ! empty( $sql_header ) ) {
            file_put_contents( $export_file, $sql_header . $result['sql'] );
        } else {
            file_put_contents( $export_file, $result['sql'], FILE_APPEND );
        }

        $payload['table_status'][$table]['offset'] = $result['last_id'];
        
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'github_sync_jobs',
            [ 'payload' => wp_json_encode( $payload ) ],
            [ 'job_uuid' => $job_uuid ]
        );

        return rest_ensure_response( [ 'success' => true, 'completed' => false, 'rows' => $result['count'] ] );
    }

    public function db_export_finalize( \WP_REST_Request $request ) {
        $job_uuid = $request->get_param( 'job_uuid' );
        
        $job_obj = new \WPGitHubSync\Services\Sync_Job( $job_uuid );
        $job = $job_obj->get_job();
        if ( ! $job ) return new \WP_Error( 'invalid_job', 'Job not found.' );

        $payload = json_decode( $job['payload'], true );
        $export_dir = $payload['export_dir'];
        $zip_file = $export_dir . '.zip';

        // Create Zip
        $zip = new \ZipArchive();
        if ( $zip->open( $zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
            return new \WP_Error( 'zip_failed', 'Failed to create zip file.' );
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $export_dir ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $files as $name => $file ) {
            if ( ! $file->isDir() ) {
                $file_path = $file->getRealPath();
                $relative_path = substr( $file_path, strlen( $export_dir ) + 1 );
                $zip->addFile( $file_path, $relative_path );
            }
        }
        $zip->close();

        // Record snapshot
        global $wpdb;
        $snapshot_uuid = wp_generate_uuid4();
        $wpdb->insert(
            $wpdb->prefix . 'github_sync_snapshots',
            [
                'snapshot_uuid' => $snapshot_uuid,
                'type'          => 'database_snapshot',
                'path'          => $zip_file,
                'created_at'    => current_time( 'mysql' )
            ]
        );

        $job_obj->complete_job( [ 'snapshot_uuid' => $snapshot_uuid, 'path' => $zip_file ] );

        // Clean up raw sql directory (optional, but good for space)
        array_map('unlink', glob("$export_dir/*.*"));
        rmdir($export_dir);

        return rest_ensure_response( [ 'success' => true, 'snapshot_uuid' => $snapshot_uuid ] );
    }
}
