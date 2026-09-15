<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wp-github-sync-dashboard">
    <div class="card">
        <h2 class="title"><?php esc_html_e( 'Sync Dashboard', 'wp-github-sync-manager' ); ?></h2>
        
        <div class="sync-status-box">
            <?php 
            $settings = WPGitHubSync\Settings::get_settings();
            $repo = $settings['repository']['owner'] . '/' . $settings['repository']['name'];
            ?>
            <h3><?php esc_html_e( 'Repository:', 'wp-github-sync-manager' ); ?> <strong><?php echo esc_html( $repo ); ?></strong></h3>
            <p><?php esc_html_e( 'Branch:', 'wp-github-sync-manager' ); ?> <strong><?php echo esc_html( $settings['repository']['default_branch'] ); ?></strong></p>
            
            <p><?php esc_html_e( 'Connection Status:', 'wp-github-sync-manager' ); ?> 
                <span id="gh-connection-status" class="badge badge-warning"><?php esc_html_e( 'Checking...', 'wp-github-sync-manager' ); ?></span>
            </p>
        </div>

        <div class="sync-actions">
            <a href="?page=wp-github-sync-pull" class="button button-primary button-hero"><?php esc_html_e( 'Pull from GitHub', 'wp-github-sync-manager' ); ?></a>
            <a href="?page=wp-github-sync-push" class="button button-secondary button-hero"><?php esc_html_e( 'Push to GitHub', 'wp-github-sync-manager' ); ?></a>
        </div>
    </div>
</div>
