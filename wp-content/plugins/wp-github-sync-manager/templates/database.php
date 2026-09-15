<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

$settings = Settings::get_settings();
$db_sync = new Services\Database_Sync();
$tables = $db_sync->get_tables();
$excluded_default = isset( $settings['database']['excluded_tables'] ) ? $settings['database']['excluded_tables'] : [];
?>

<div class="wrap wp-github-sync-database">
    <div class="card" style="max-width: 900px; padding: 20px;">
        <h2 class="title"><?php esc_html_e( 'Database Synchronization', 'wp-github-sync-manager' ); ?></h2>
        
        <p><?php esc_html_e( 'Export and import database snapshots securely.', 'wp-github-sync-manager' ); ?></p>
        
        <div class="notice notice-warning">
            <p><strong><?php esc_html_e( 'WARNING:', 'wp-github-sync-manager' ); ?></strong></p>
            <p><?php esc_html_e( 'Database tables may contain passwords, emails, personal information and private customer data. Never push sensitive production data to a public repository.', 'wp-github-sync-manager' ); ?></p>
        </div>

        <!-- Export Form -->
        <div id="db-export-form" style="border-top: 1px solid #ccc; padding-top: 20px; margin-top: 20px;">
            <h3><?php esc_html_e( 'Export Database', 'wp-github-sync-manager' ); ?></h3>
            <p><?php esc_html_e( 'Select the tables you want to export. Sensitive columns (like passwords) are automatically filtered based on your Settings.', 'wp-github-sync-manager' ); ?></p>
            
            <form id="form-export-db">
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; background: #fafafa;">
                    <?php foreach ( $tables as $table ) : ?>
                        <?php $is_excluded = in_array( $table, $excluded_default ); ?>
                        <label style="display: block; margin-bottom: 5px;">
                            <input type="checkbox" name="tables[]" value="<?php echo esc_attr( $table ); ?>" <?php checked( ! $is_excluded ); ?>>
                            <?php echo esc_html( $table ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p>
                    <button type="button" id="start-export-btn" class="button button-primary button-hero"><?php esc_html_e( 'Start Export', 'wp-github-sync-manager' ); ?></button>
                </p>
            </form>
        </div>

        <!-- Import Form -->
        <div id="db-import-form" style="border-top: 1px solid #ccc; padding-top: 20px; margin-top: 40px;">
            <h3><?php esc_html_e( 'Import Database', 'wp-github-sync-manager' ); ?></h3>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e( 'DANGER:', 'wp-github-sync-manager' ); ?></strong> <?php esc_html_e( 'This operation will modify the WordPress database. A backup will be taken automatically before import.', 'wp-github-sync-manager' ); ?></p>
            </div>
            
            <form id="form-import-db">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label><?php esc_html_e( 'Snapshot Source', 'wp-github-sync-manager' ); ?></label></th>
                        <td>
                            <label><input type="radio" name="import_source" value="existing" checked> <?php esc_html_e( 'Existing Snapshot on Server', 'wp-github-sync-manager' ); ?></label><br>
                            <label><input type="radio" name="import_source" value="upload"> <?php esc_html_e( 'Upload Snapshot File (.zip)', 'wp-github-sync-manager' ); ?></label>
                        </td>
                    </tr>
                    <tr id="row_existing_snapshot">
                        <th scope="row"><label for="import_snapshot"><?php esc_html_e( 'Select Snapshot', 'wp-github-sync-manager' ); ?></label></th>
                        <td>
                            <select id="import_snapshot" name="snapshot_uuid" class="regular-text">
                                <option value=""><?php esc_html_e( '-- Select a database snapshot --', 'wp-github-sync-manager' ); ?></option>
                                <?php
                                global $wpdb;
                                $table_snapshots = $wpdb->prefix . 'github_sync_snapshots';
                                $snapshots = $wpdb->get_results( "SELECT * FROM $table_snapshots WHERE type = 'database_backup' OR type = 'database_snapshot' ORDER BY created_at DESC" );
                                foreach ( $snapshots as $snap ) {
                                    echo '<option value="' . esc_attr( $snap->snapshot_uuid ) . '">' . esc_html( basename( $snap->path ) . ' (' . $snap->created_at . ')' ) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="row_upload_snapshot" style="display: none;">
                        <th scope="row"><label for="upload_snapshot_file"><?php esc_html_e( 'Select File', 'wp-github-sync-manager' ); ?></label></th>
                        <td>
                            <input type="file" id="upload_snapshot_file" name="snapshot_file" accept=".zip" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Upload a database snapshot ZIP file generated by this plugin.', 'wp-github-sync-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="search_replace_old"><?php esc_html_e( 'Search (Old URL)', 'wp-github-sync-manager' ); ?></label></th>
                        <td>
                            <input type="text" id="search_replace_old" name="search_replace_old" class="regular-text" placeholder="e.g. http://localhost">
                            <p class="description"><?php esc_html_e( 'Optional. Useful for migrating environments.', 'wp-github-sync-manager' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="search_replace_new"><?php esc_html_e( 'Replace (New URL)', 'wp-github-sync-manager' ); ?></label></th>
                        <td>
                            <input type="text" id="search_replace_new" name="search_replace_new" class="regular-text" placeholder="e.g. https://example.com">
                        </td>
                    </tr>
                    
                    <?php if ( $settings['security']['environment'] === 'production' ) : ?>
                    <tr>
                        <th scope="row"><label for="confirm_import"><?php esc_html_e( 'Confirm Import', 'wp-github-sync-manager' ); ?></label></th>
                        <td>
                            <input type="text" id="confirm_import" name="confirm_import" class="regular-text" placeholder="Type IMPORT to confirm" required>
                            <p class="description" style="color: #dc3232;"><?php esc_html_e( 'Required for Production environments.', 'wp-github-sync-manager' ); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <p>
                    <button type="button" id="start-import-btn" class="button button-primary button-hero"><?php esc_html_e( 'Start Import', 'wp-github-sync-manager' ); ?></button>
                </p>
            </form>
        </div>

        <!-- Progress UI -->
        <div id="db-progress-ui" style="display: none; margin-top: 20px;">
            <h3 id="db-progress-title"><?php esc_html_e( 'Processing...', 'wp-github-sync-manager' ); ?></h3>
            <div style="background: #e1e1e1; border-radius: 4px; overflow: hidden; height: 20px; width: 100%; margin-bottom: 10px;">
                <div id="db-progress-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s;"></div>
            </div>
            <p id="db-progress-text"></p>
            <div id="db-errors" style="color: #dc3232; margin-top: 10px;"></div>
            <div id="db-success" style="display: none; margin-top: 20px;">
                <a href="?page=wp-github-sync-database" class="button button-primary"><?php esc_html_e( 'Done', 'wp-github-sync-manager' ); ?></a>
            </div>
        </div>
        
    </div>
</div>
