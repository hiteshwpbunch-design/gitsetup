<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

$settings = Settings::get_settings();
$branch = $settings['repository']['default_branch'];

$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
$compare_result = null;

if ( $action === 'compare' && check_admin_referer( 'wp_github_sync_push_nonce' ) ) {
    $sync = new Services\File_Sync();
    $compare_result = $sync->compare( $branch );
    
    // Store compare result temporarily so AJAX can pick it up
    if ( ! is_wp_error( $compare_result ) ) {
        set_transient( 'wp_github_sync_push_compare_' . get_current_user_id(), $compare_result, HOUR_IN_SECONDS );
    }
}
?>

<div class="wrap wp-github-sync-push">
    <div class="card" style="max-width: 900px; padding: 20px;">
        <h2 class="title"><?php esc_html_e( 'Push to GitHub', 'wp-github-sync-manager' ); ?></h2>
        
        <p><?php printf( esc_html__( 'Target Branch: %s', 'wp-github-sync-manager' ), '<strong>' . esc_html( $branch ) . '</strong>' ); ?></p>

        <?php if ( is_wp_error( $compare_result ) ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $compare_result->get_error_message() ); ?></p></div>
        <?php endif; ?>

        <?php if ( ! $compare_result || is_wp_error( $compare_result ) ) : ?>
            <form method="post" action="">
                <?php wp_nonce_field( 'wp_github_sync_push_nonce' ); ?>
                <input type="hidden" name="action" value="compare">
                <p>
                    <button type="submit" class="button button-primary button-hero">
                        <?php esc_html_e( 'Scan Local Changes', 'wp-github-sync-manager' ); ?>
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
                <h3><?php esc_html_e( 'Changes to Push', 'wp-github-sync-manager' ); ?></h3>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><?php echo $added; ?> Added</li>
                    <li><?php echo $modified; ?> Modified</li>
                    <li><?php echo $deleted; ?> Deleted</li>
                </ul>
            </div>

            <?php if ( $total_changes === 0 ) : ?>
                <div class="notice notice-info"><p><?php esc_html_e( 'No local changes detected.', 'wp-github-sync-manager' ); ?></p></div>
                <a href="?page=wp-github-sync-push" class="button button-secondary"><?php esc_html_e( 'Back', 'wp-github-sync-manager' ); ?></a>
            <?php else : ?>
                <div id="push-ui-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="commit_message"><?php esc_html_e( 'Commit Message', 'wp-github-sync-manager' ); ?></label></th>
                            <td>
                                <input type="text" id="commit_message" class="large-text" placeholder="<?php esc_attr_e( 'e.g., Update Elementor custom widgets', 'wp-github-sync-manager' ); ?>">
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="button" id="start-push-btn" class="button button-primary button-hero">
                            <?php esc_html_e( 'Push Changes', 'wp-github-sync-manager' ); ?>
                        </button>
                        <a href="?page=wp-github-sync-push" class="button button-secondary button-hero" style="margin-left: 10px; line-height: 2.3;"><?php esc_html_e( 'Cancel', 'wp-github-sync-manager' ); ?></a>
                    </p>
                </div>

                <!-- Progress UI (Hidden initially) -->
                <div id="push-progress-ui" style="display: none; margin-top: 20px;">
                    <h3><?php esc_html_e( 'Pushing to GitHub...', 'wp-github-sync-manager' ); ?></h3>
                    <div style="background: #e1e1e1; border-radius: 4px; overflow: hidden; height: 20px; width: 100%; margin-bottom: 10px;">
                        <div id="push-progress-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s;"></div>
                    </div>
                    <p id="push-progress-text">0 / <?php echo $total_changes; ?> files processed.</p>
                    <div id="push-errors" style="color: #dc3232; margin-top: 10px;"></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
