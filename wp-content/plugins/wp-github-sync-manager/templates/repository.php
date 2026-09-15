<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

// Handle form submission
$connection_result = null;
if ( isset( $_POST['wp_github_sync_repo_settings'] ) && check_admin_referer( 'wp_github_sync_repo_settings_nonce' ) ) {
    
    $settings = Settings::get_settings();
    $settings['repository']['owner'] = sanitize_text_field( $_POST['repo_owner'] );
    $settings['repository']['name'] = sanitize_text_field( $_POST['repo_name'] );
    $settings['repository']['default_branch'] = sanitize_text_field( $_POST['default_branch'] );

    update_option( Settings::OPTION_NAME, $settings );
    
    if ( isset( $_POST['test_connection'] ) ) {
        $api = new Services\GitHub_API();
        $connection_result = $api->test_connection();
    } else {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Repository settings saved.', 'wp-github-sync-manager' ) . '</p></div>';
    }
}

$settings = Settings::get_settings();
$owner = $settings['repository']['owner'];
$repo = $settings['repository']['name'];
$branch = $settings['repository']['default_branch'];

?>

<div class="wrap wp-github-sync-repository">
    <div class="card" style="max-width: 800px; padding: 20px;">
        <h2 class="title"><?php esc_html_e( 'Repository Connection', 'wp-github-sync-manager' ); ?></h2>

        <?php if ( $connection_result !== null ) : ?>
            <?php if ( is_wp_error( $connection_result ) ) : ?>
                <div class="notice notice-error"><p><strong>Connection Failed:</strong> <?php echo esc_html( $connection_result->get_error_message() ); ?></p></div>
            <?php else : ?>
                <div class="notice notice-success"><p><strong>Connection Successful!</strong> Authenticated as: <?php echo esc_html( $connection_result['login'] ?? 'Unknown' ); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'wp_github_sync_repo_settings_nonce' ); ?>
            <input type="hidden" name="wp_github_sync_repo_settings" value="1">

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="repo_owner"><?php esc_html_e( 'Repository Owner', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="text" id="repo_owner" name="repo_owner" value="<?php echo esc_attr( $owner ); ?>" class="regular-text" placeholder="e.g. hitesh">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="repo_name"><?php esc_html_e( 'Repository Name', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="text" id="repo_name" name="repo_name" value="<?php echo esc_attr( $repo ); ?>" class="regular-text" placeholder="e.g. my-wordpress-project">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_branch"><?php esc_html_e( 'Default Branch', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="text" id="default_branch" name="default_branch" value="<?php echo esc_attr( $branch ); ?>" class="regular-text" placeholder="e.g. main">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Repository', 'wp-github-sync-manager' ); ?>">
                <input type="submit" name="test_connection" class="button button-secondary" value="<?php esc_attr_e( 'Test Connection', 'wp-github-sync-manager' ); ?>">
            </p>
        </form>
    </div>
</div>
