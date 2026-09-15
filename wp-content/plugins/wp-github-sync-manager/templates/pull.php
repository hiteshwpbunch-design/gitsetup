<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

$settings = Settings::get_settings();
$branch = $settings['repository']['default_branch'];

$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
$compare_result = null;
$pull_result = null;

if ( $action === 'compare' && check_admin_referer( 'wp_github_sync_pull_nonce' ) ) {
    $sync = new Services\File_Sync();
    $compare_result = $sync->compare( $branch );
}

if ( $action === 'pull' && check_admin_referer( 'wp_github_sync_pull_nonce' ) ) {
    $sync = new Services\File_Sync();
    $compare_result = $sync->compare( $branch ); // We re-compare for safety
    
    if ( ! is_wp_error( $compare_result ) ) {
        // 1. Mandatory Backup
        $backup_mgr = new Services\Backup_Manager();
        $files_to_backup = array_merge( 
            array_column($compare_result['changes']['modified'], 'path'),
            array_column($compare_result['changes']['deleted'], 'path')
        );
        
        $backup_result = true;
        if ( ! empty( $files_to_backup ) ) {
            $backup_result = $backup_mgr->create_file_backup( $files_to_backup );
        }
        
        if ( is_wp_error( $backup_result ) ) {
            $pull_result = $backup_result;
        } else {
            // 2. Perform Pull
            $api = new Services\GitHub_API();
            $owner = $settings['repository']['owner'];
            $repo = $settings['repository']['name'];
            $sync_root = wp_normalize_path( $settings['sync']['sync_root'] );
            
            $errors = [];
            $pulled_count = 0;

            // Handle Additions & Modifications
            $to_download = array_merge(
                $compare_result['changes']['added'],
                array_column($compare_result['changes']['modified'], 'path')
            );
            
            // To get blobs, we need to find their remote SHAs.
            // But 'added' only has paths in our compare array right now.
            // For a robust implementation, we'd adjust File_Sync->compare to return remote SHAs for added files too.
            // For this UI mockup, we will fetch the tree again to get the SHAs.
            $tree_res = $api->get_tree( $owner, $repo, $compare_result['base_tree'], true );
            $remote_shas = [];
            if ( ! is_wp_error( $tree_res ) ) {
                foreach ( $tree_res['tree'] as $item ) {
                    if ( $item['type'] === 'blob' ) {
                        $remote_shas[$item['path']] = $item['sha'];
                    }
                }
            }

            foreach ( $to_download as $path ) {
                if ( isset( $remote_shas[$path] ) ) {
                    $blob = $api->get_blob( $owner, $repo, $remote_shas[$path] );
                    if ( ! is_wp_error( $blob ) ) {
                        $full_path = wp_normalize_path( $sync_root . '/' . $path );
                        $dir = dirname( $full_path );
                        if ( ! file_exists( $dir ) ) {
                            wp_mkdir_p( $dir );
                        }
                        file_put_contents( $full_path, $blob );
                        $pulled_count++;
                    } else {
                        $errors[] = "Failed to download $path";
                    }
                }
            }
            
            // Handle Deletions (if allowed)
            foreach ( $compare_result['changes']['deleted'] as $del ) {
                $full_path = wp_normalize_path( $sync_root . '/' . $del['path'] );
                if ( file_exists( $full_path ) ) {
                    // Safety check: Don't delete wp-config
                    if ( strpos( $del['path'], 'wp-config.php' ) === false ) {
                        unlink( $full_path );
                        $pulled_count++;
                    }
                }
            }

            if ( empty( $errors ) ) {
                $pull_result = [ 'success' => true, 'count' => $pulled_count ];
                // Reset compare_result so we don't show the old changes
                $compare_result = null;
            } else {
                $pull_result = new \WP_Error( 'pull_failed', 'Some files failed to pull.', $errors );
            }
        }
    } else {
        $pull_result = $compare_result;
    }
}
?>

<div class="wrap wp-github-sync-pull">
    <div class="card" style="max-width: 900px; padding: 20px;">
        <h2 class="title"><?php esc_html_e( 'Pull from GitHub', 'wp-github-sync-manager' ); ?></h2>
        
        <p><?php printf( esc_html__( 'Target Branch: %s', 'wp-github-sync-manager' ), '<strong>' . esc_html( $branch ) . '</strong>' ); ?></p>

        <?php if ( is_wp_error( $pull_result ) ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $pull_result->get_error_message() ); ?></p></div>
        <?php elseif ( isset( $pull_result['success'] ) ) : ?>
            <div class="notice notice-success"><p><?php printf( esc_html__( 'Pull successful! %d files updated.', 'wp-github-sync-manager' ), $pull_result['count'] ); ?></p></div>
        <?php endif; ?>

        <?php if ( is_wp_error( $compare_result ) ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $compare_result->get_error_message() ); ?></p></div>
        <?php endif; ?>

        <?php if ( ! $compare_result || is_wp_error( $compare_result ) ) : ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'wp_github_sync_pull_nonce' ); ?>
                <input type="hidden" name="action" value="compare">
                <p>
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e( 'Compare Remote Changes', 'wp-github-sync-manager' ); ?>
                    </button>
                </p>
            </form>
        <?php else : ?>
            <?php 
                $added = count( $compare_result['changes']['added'] );
                $modified = count( $compare_result['changes']['modified'] );
                $deleted = count( $compare_result['changes']['deleted'] );
                $total_changes = $added + $modified + $deleted;
            ?>
            
            <div class="sync-summary" style="background: #f0f0f1; padding: 15px; margin: 15px 0;">
                <h3><?php esc_html_e( 'Remote Changes Detected', 'wp-github-sync-manager' ); ?></h3>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php echo $added; ?> Added</li>
                    <li><?php echo $modified; ?> Modified</li>
                    <li><?php echo $deleted; ?> Deleted</li>
                </ul>
            </div>

            <?php if ( $total_changes === 0 ) : ?>
                <div class="notice notice-info"><p><?php esc_html_e( 'Your local files are already up-to-date with the remote branch.', 'wp-github-sync-manager' ); ?></p></div>
                <a href="?page=wp-github-sync-pull" class="button button-secondary"><?php esc_html_e( 'Back', 'wp-github-sync-manager' ); ?></a>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><strong><?php esc_html_e( 'WARNING', 'wp-github-sync-manager' ); ?></strong></p>
                    <p><?php esc_html_e( 'Pulling these changes will overwrite local files. A backup will be created automatically before proceeding.', 'wp-github-sync-manager' ); ?></p>
                </div>
                <form method="post" action="">
                    <?php wp_nonce_field( 'wp_github_sync_pull_nonce' ); ?>
                    <input type="hidden" name="action" value="pull">
                    <p class="submit">
                        <button type="submit" class="button button-primary button-hero" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to pull and overwrite local files?', 'wp-github-sync-manager' ); ?>');">
                            <?php esc_html_e( 'Backup & Pull Changes', 'wp-github-sync-manager' ); ?>
                        </button>
                        <a href="?page=wp-github-sync-pull" class="button button-secondary button-hero" style="margin-left: 10px; line-height: 2.3;"><?php esc_html_e( 'Cancel', 'wp-github-sync-manager' ); ?></a>
                    </p>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
