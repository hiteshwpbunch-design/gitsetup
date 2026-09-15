<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

// Handle form submission
if ( isset( $_POST['wp_github_sync_save_settings'] ) && check_admin_referer( 'wp_github_sync_save_settings_nonce' ) ) {
    
    // Process Token
    if ( ! empty( $_POST['github_token'] ) ) {
        // Only save if it's not the dummy placeholder
        if ( $_POST['github_token'] !== '********' ) {
            Settings::set_token( sanitize_text_field( $_POST['github_token'] ) );
        }
    }

    $settings = Settings::get_settings();

    // Process Sync Settings
    $settings['sync']['sync_root'] = wp_normalize_path( sanitize_text_field( $_POST['sync_root'] ) );
    $settings['sync']['file_batch_size'] = absint( $_POST['file_batch_size'] );
    $settings['sync']['max_file_size'] = absint( $_POST['max_file_size'] ) * 1024 * 1024; // convert MB to bytes
    
    $exclusions = sanitize_textarea_field( $_POST['exclusions'] );
    $settings['sync']['exclusions'] = array_filter( array_map( 'trim', explode( "\n", $exclusions ) ) );

    // Process Backup Settings
    $settings['backup']['retention'] = absint( $_POST['retention'] );
    $settings['backup']['auto_backup_before_pull'] = isset( $_POST['auto_backup_before_pull'] ) ? true : false;
    $settings['backup']['auto_db_backup'] = isset( $_POST['auto_db_backup'] ) ? true : false;

    // Process Security Settings
    $settings['security']['environment'] = sanitize_text_field( $_POST['environment'] );
    $settings['security']['delete_on_uninstall'] = isset( $_POST['delete_on_uninstall'] ) ? true : false;

    update_option( Settings::OPTION_NAME, $settings );
    
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'wp-github-sync-manager' ) . '</p></div>';
}

$settings = Settings::get_settings();
$token = Settings::get_token();
$has_token = ! empty( $token );
?>

<div class="wrap wp-github-sync-settings">
    <div class="card" style="max-width: 800px; padding: 20px;">
        <form method="post" action="">
            <?php wp_nonce_field( 'wp_github_sync_save_settings_nonce' ); ?>
            <input type="hidden" name="wp_github_sync_save_settings" value="1">

            <h2 class="title"><?php esc_html_e( 'GitHub API Settings', 'wp-github-sync-manager' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="github_token"><?php esc_html_e( 'Personal Access Token', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="password" id="github_token" name="github_token" value="<?php echo $has_token ? '********' : ''; ?>" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Requires repo read/write access. The token is stored securely and never displayed.', 'wp-github-sync-manager' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2 class="title"><?php esc_html_e( 'Synchronization Settings', 'wp-github-sync-manager' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sync_root"><?php esc_html_e( 'Local Sync Directory', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="text" id="sync_root" name="sync_root" value="<?php echo esc_attr( $settings['sync']['sync_root'] ); ?>" class="large-text">
                        <p class="description"><?php esc_html_e( 'Absolute path to the WordPress directory you want to sync.', 'wp-github-sync-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="file_batch_size"><?php esc_html_e( 'File Batch Size', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="number" id="file_batch_size" name="file_batch_size" value="<?php echo esc_attr( $settings['sync']['file_batch_size'] ); ?>" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_file_size"><?php esc_html_e( 'Max File Size (MB)', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="number" id="max_file_size" name="max_file_size" value="<?php echo esc_attr( $settings['sync']['max_file_size'] / 1024 / 1024 ); ?>" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="exclusions"><?php esc_html_e( 'File Exclusions', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <textarea id="exclusions" name="exclusions" rows="8" class="large-text code"><?php echo esc_textarea( implode( "\n", $settings['sync']['exclusions'] ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One pattern per line. Use wildcard patterns (e.g., *.log, node_modules/).', 'wp-github-sync-manager' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr>

            <h2 class="title"><?php esc_html_e( 'Backup & Security', 'wp-github-sync-manager' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="environment"><?php esc_html_e( 'Environment', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <select id="environment" name="environment">
                            <option value="production" <?php selected( $settings['security']['environment'], 'production' ); ?>>Production</option>
                            <option value="staging" <?php selected( $settings['security']['environment'], 'staging' ); ?>>Staging</option>
                            <option value="development" <?php selected( $settings['security']['environment'], 'development' ); ?>>Development</option>
                            <option value="local" <?php selected( $settings['security']['environment'], 'local' ); ?>>Local</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Production environments require extra confirmation before dangerous operations.', 'wp-github-sync-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="retention"><?php esc_html_e( 'Backup Retention', 'wp-github-sync-manager' ); ?></label></th>
                    <td>
                        <input type="number" id="retention" name="retention" value="<?php echo esc_attr( $settings['backup']['retention'] ); ?>" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of file and database backups to keep.', 'wp-github-sync-manager' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Automation', 'wp-github-sync-manager' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_backup_before_pull" value="1" <?php checked( $settings['backup']['auto_backup_before_pull'] ); ?>>
                            <?php esc_html_e( 'Automatic backup before Pull', 'wp-github-sync-manager' ); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" name="auto_db_backup" value="1" <?php checked( $settings['backup']['auto_db_backup'] ); ?>>
                            <?php esc_html_e( 'Automatic backup before Database Import', 'wp-github-sync-manager' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Uninstall', 'wp-github-sync-manager' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $settings['security']['delete_on_uninstall'] ); ?>>
                            <?php esc_html_e( 'Delete all plugin data on uninstall (tables, settings, logs). Backups are preserved.', 'wp-github-sync-manager' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'wp-github-sync-manager' ); ?>">
            </p>
        </form>
    </div>
</div>
