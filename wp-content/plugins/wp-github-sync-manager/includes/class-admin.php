<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

class Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function register_menus() {
        add_menu_page(
            __( 'GitHub Sync', 'wp-github-sync-manager' ),
            __( 'GitHub Sync', 'wp-github-sync-manager' ),
            'manage_options',
            'wp-github-sync',
            [ $this, 'render_dashboard' ],
            'dashicons-cloud-saved',
            80
        );

        $submenus = [
            'dashboard'  => [ 'title' => __( 'Dashboard', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync' ],
            'repository' => [ 'title' => __( 'Repository', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-repository' ],
            'branches'   => [ 'title' => __( 'Branches', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-branches' ],
            'pull'       => [ 'title' => __( 'Pull', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-pull' ],
            'push'       => [ 'title' => __( 'Push', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-push' ],
            'database'   => [ 'title' => __( 'Database', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-database' ],
            'history'    => [ 'title' => __( 'History', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-history' ],
            'backups'    => [ 'title' => __( 'Backups', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-backups' ],
            'logs'       => [ 'title' => __( 'Logs', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-logs' ],
            'settings'   => [ 'title' => __( 'Settings', 'wp-github-sync-manager' ), 'slug' => 'wp-github-sync-settings' ],
        ];

        foreach ( $submenus as $key => $menu ) {
            $parent_slug = 'wp-github-sync';
            $page_title = $menu['title'];
            $menu_title = $menu['title'];
            $capability = 'manage_options';
            $menu_slug = $menu['slug'];
            $callback = [ $this, 'render_' . $key ];
            
            // Dashboard is already added as main menu
            if ( $key === 'dashboard' ) {
                add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $parent_slug, $callback );
                continue;
            }

            add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback );
        }
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'wp-github-sync' ) === false ) {
            return;
        }

        wp_enqueue_style( 'wp-github-sync-admin', WP_GITHUB_SYNC_URL . 'assets/css/admin.css', [], WP_GITHUB_SYNC_VERSION );
        wp_enqueue_script( 'wp-github-sync-admin', WP_GITHUB_SYNC_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util' ], WP_GITHUB_SYNC_VERSION, true );
        
        wp_localize_script( 'wp-github-sync-admin', 'wpGitHubSync', [
            'restUrl' => esc_url_raw( rest_url( 'wp-github-sync/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'i18n'    => [
                'confirm' => __( 'Are you sure?', 'wp-github-sync-manager' ),
            ]
        ] );
    }

    // Dynamic renderer for templates
    public function __call( $name, $arguments ) {
        if ( strpos( $name, 'render_' ) === 0 ) {
            $template = str_replace( 'render_', '', $name );
            $file = WP_GITHUB_SYNC_DIR . 'templates/' . $template . '.php';
            
            echo '<div class="wrap wp-github-sync-wrap">';
            echo '<h1>' . esc_html( ucfirst( $template ) ) . '</h1>';
            
            if ( file_exists( $file ) ) {
                include $file;
            } else {
                echo '<p>' . esc_html__( 'Template not found:', 'wp-github-sync-manager' ) . ' ' . esc_html( $template ) . '</p>';
            }
            
            echo '</div>';
        }
    }
}
