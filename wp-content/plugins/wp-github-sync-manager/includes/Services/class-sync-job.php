<?php
namespace WPGitHubSync\Services;

defined( 'ABSPATH' ) || exit;

class Sync_Job {
    private $job_uuid;
    private $table;
    
    public function __construct( $job_uuid = null ) {
        global $wpdb;
        $this->table = $wpdb->prefix . 'github_sync_jobs';
        
        if ( $job_uuid ) {
            $this->job_uuid = $job_uuid;
        }
    }

    public function create_job( $operation, $repository, $branch, $total_items, $payload = [] ) {
        global $wpdb;
        $this->job_uuid = wp_generate_uuid4();
        
        $wpdb->insert(
            $this->table,
            [
                'job_uuid'    => $this->job_uuid,
                'operation'   => $operation,
                'status'      => 'pending',
                'user_id'     => get_current_user_id(),
                'repository'  => $repository,
                'branch'      => $branch,
                'total_items' => $total_items,
                'payload'     => wp_json_encode( $payload ),
                'started_at'  => current_time( 'mysql' )
            ]
        );
        
        return $this->job_uuid;
    }

    public function get_job() {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE job_uuid = %s", $this->job_uuid ), ARRAY_A );
    }

    public function update_progress( $processed, $failed = 0, $skipped = 0, $current_item = '' ) {
        global $wpdb;
        $job = $this->get_job();
        if ( ! $job ) return false;
        
        $new_processed = $job['processed_items'] + $processed;
        $new_failed = $job['failed_items'] + $failed;
        $new_skipped = $job['skipped_items'] + $skipped;
        
        $progress = 0;
        if ( $job['total_items'] > 0 ) {
            $progress = floor( ( ( $new_processed + $new_failed + $new_skipped ) / $job['total_items'] ) * 100 );
        }
        
        $wpdb->update(
            $this->table,
            [
                'status' => 'processing',
                'processed_items' => $new_processed,
                'failed_items'    => $new_failed,
                'skipped_items'   => $new_skipped,
                'current_item'    => $current_item,
                'progress'        => $progress
            ],
            [ 'job_uuid' => $this->job_uuid ]
        );
    }

    public function complete_job( $result = [] ) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            [
                'status'       => 'completed',
                'progress'     => 100,
                'completed_at' => current_time( 'mysql' ),
                'result'       => wp_json_encode( $result )
            ],
            [ 'job_uuid' => $this->job_uuid ]
        );
    }

    public function fail_job( $error_message ) {
        global $wpdb;
        $wpdb->update(
            $this->table,
            [
                'status'        => 'failed',
                'error_message' => $error_message,
                'completed_at'  => current_time( 'mysql' )
            ],
            [ 'job_uuid' => $this->job_uuid ]
        );
    }
}
