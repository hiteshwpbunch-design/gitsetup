<?php
namespace WPGitHubSync\Services;

defined( 'ABSPATH' ) || exit;

class File_Sync {
    private $api;
    private $scanner;
    private $owner;
    private $repo;
    
    public function __construct() {
        $this->api = new GitHub_API();
        $this->scanner = new File_Scanner();
        $settings = \WPGitHubSync\Settings::get_settings();
        $this->owner = $settings['repository']['owner'];
        $this->repo = $settings['repository']['name'];
    }

    /**
     * Compare local files with remote GitHub tree to generate a diff set.
     */
    public function compare( $branch ) {
        // 1. Get latest commit on branch
        $ref_res = $this->api->get_ref( $this->owner, $this->repo, $branch );
        if ( is_wp_error( $ref_res ) ) return $ref_res;
        
        $commit_sha = $ref_res['object']['sha'];

        // 2. Get commit details to find tree SHA
        $commit_res = $this->api->get_commit( $this->owner, $this->repo, $commit_sha );
        if ( is_wp_error( $commit_res ) ) return $commit_res;

        $tree_sha = $commit_res['tree']['sha'];

        // 3. Get remote tree recursively
        $tree_res = $this->api->get_tree( $this->owner, $this->repo, $tree_sha, true );
        if ( is_wp_error( $tree_res ) ) return $tree_res;

        if ( isset( $tree_res['truncated'] ) && $tree_res['truncated'] ) {
            return new \WP_Error( 'tree_truncated', __( 'Repository tree is too large to fetch in one request.', 'wp-github-sync-manager' ) );
        }

        $remote_files = [];
        foreach ( $tree_res['tree'] as $item ) {
            if ( $item['type'] === 'blob' ) {
                $remote_files[$item['path']] = $item;
            }
        }

        // 4. Scan local files
        $local_files = $this->scanner->scan_directory();

        $changes = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
            'unchanged' => []
        ];

        // 5. Compare Local -> Remote
        foreach ( $local_files as $path => $local ) {
            if ( isset( $remote_files[$path] ) ) {
                $local_sha = File_Scanner::calculate_git_blob_sha( $local['full_path'] );
                if ( $local_sha === $remote_files[$path]['sha'] ) {
                    $changes['unchanged'][] = $path;
                } else {
                    $changes['modified'][] = [
                        'path' => $path,
                        'local_sha' => $local_sha,
                        'remote_sha' => $remote_files[$path]['sha']
                    ];
                }
                unset( $remote_files[$path] ); // Processed
            } else {
                $changes['added'][] = $path;
            }
        }

        // Remaining remote files are deleted locally
        foreach ( $remote_files as $path => $remote ) {
            if ( ! $this->scanner->is_excluded( $path ) ) {
                $changes['deleted'][] = [
                    'path' => $path,
                    'remote_sha' => $remote['sha']
                ];
            }
        }

        return [
            'base_commit' => $commit_sha,
            'base_tree' => $tree_sha,
            'changes' => $changes
        ];
    }
}
