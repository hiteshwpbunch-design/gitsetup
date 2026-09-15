<?php
namespace WPGitHubSync;

defined( 'ABSPATH' ) || exit;

class Plugin {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        // Core components
        $this->database = new Database();
        $this->settings = new Settings();
        $this->security = new Security();
        
        // Services
        $this->github_api = new Services\GitHub_API();
        
        if ( is_admin() ) {
            $this->admin = new Admin();
        }

        // REST API
        $this->rest_controller = new REST\REST_Controller();
    }

    private function init_hooks() {
        add_action( 'rest_api_init', [ $this->rest_controller, 'register_routes' ] );
    }

    public static function activate() {
        Database::create_tables();
        Settings::create_defaults();
        
        // Setup initial directories
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/wp-github-sync/backups';
        if ( ! file_exists( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
            file_put_contents( $backup_dir . '/.htaccess', 'deny from all' );
            file_put_contents( $backup_dir . '/index.php', '<?php // Silence is golden' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'wp_github_sync_cron' );
    }
}
