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
}
