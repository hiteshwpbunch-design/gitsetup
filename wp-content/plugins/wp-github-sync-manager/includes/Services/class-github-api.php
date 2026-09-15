<?php
namespace WPGitHubSync\Services;

use WPGitHubSync\Settings;

defined( 'ABSPATH' ) || exit;

class GitHub_API {
    private $base_url = 'https://api.github.com';
    private $api_version = '2022-11-28';

    public function __construct() {}

    private function get_headers() {
        $token = Settings::get_token();
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => $this->api_version,
            'User-Agent'    => 'WP-GitHub-Sync-Manager/' . WP_GITHUB_SYNC_VERSION
        ];
    }

    private function request( $endpoint, $method = 'GET', $body = null ) {
        $url = $this->base_url . $endpoint;
        $args = [
            'method'  => $method,
            'headers' => $this->get_headers(),
            'timeout' => 30,
        ];

        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Handle Rate Limits here
        // if $status_code == 429 ...

        if ( $status_code >= 400 ) {
            $message = isset( $data['message'] ) ? $data['message'] : 'Unknown GitHub API Error';
            return new \WP_Error( 'github_api_error', $message, [ 'status' => $status_code, 'data' => $data ] );
        }

        return $data;
    }

    public function test_connection() {
        return $this->request( '/user' );
    }

    // Repository Details
    public function get_repository( $owner, $repo ) {
        return $this->request( "/repos/{$owner}/{$repo}" );
    }

    // Git Database API - Trees
    public function get_tree( $owner, $repo, $tree_sha, $recursive = false ) {
        $endpoint = "/repos/{$owner}/{$repo}/git/trees/{$tree_sha}";
        if ( $recursive ) {
            $endpoint .= '?recursive=1';
        }
        return $this->request( $endpoint );
    }

    public function create_tree( $owner, $repo, $base_tree, $tree_data ) {
        return $this->request( "/repos/{$owner}/{$repo}/git/trees", 'POST', [
            'base_tree' => $base_tree,
            'tree'      => $tree_data
        ] );
    }

    // Git Database API - Blobs
    public function get_blob( $owner, $repo, $file_sha ) {
        // Need raw accept header for blob download
        $url = $this->base_url . "/repos/{$owner}/{$repo}/git/blobs/{$file_sha}";
        $headers = $this->get_headers();
        $headers['Accept'] = 'application/vnd.github.raw';
        
        $response = wp_remote_request( $url, [
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => 60
        ] );

        if ( is_wp_error( $response ) ) return $response;
        
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return new \WP_Error( 'github_api_error', 'Failed to retrieve blob.' );
        }
        
        return wp_remote_retrieve_body( $response );
    }

    public function create_blob( $owner, $repo, $content, $encoding = 'utf-8' ) {
        return $this->request( "/repos/{$owner}/{$repo}/git/blobs", 'POST', [
            'content'  => $content,
            'encoding' => $encoding
        ] );
    }

    // Git Database API - Commits
    public function get_commit( $owner, $repo, $commit_sha ) {
        return $this->request( "/repos/{$owner}/{$repo}/git/commits/{$commit_sha}" );
    }

    public function create_commit( $owner, $repo, $message, $tree_sha, $parents ) {
        return $this->request( "/repos/{$owner}/{$repo}/git/commits", 'POST', [
            'message' => $message,
            'tree'    => $tree_sha,
            'parents' => $parents
        ] );
    }

    // Git Database API - Refs
    public function get_ref( $owner, $repo, $branch ) {
        return $this->request( "/repos/{$owner}/{$repo}/git/ref/heads/{$branch}" );
    }

    public function update_ref( $owner, $repo, $branch, $commit_sha, $force = false ) {
        return $this->request( "/repos/{$owner}/{$repo}/git/refs/heads/{$branch}", 'PATCH', [
            'sha'   => $commit_sha,
            'force' => $force
        ] );
    }

    // Branches
    public function get_branches( $owner, $repo ) {
        return $this->request( "/repos/{$owner}/{$repo}/branches" );
    }
}
