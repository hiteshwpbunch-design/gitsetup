<?php
namespace WPGitHubSync\Services;

defined( 'ABSPATH' ) || exit;

class Database_Sync {
    private $db;
    private $settings;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->settings = \WPGitHubSync\Settings::get_settings();
    }

    public function get_tables() {
        return $this->db->get_col( "SHOW TABLES LIKE '{$this->db->prefix}%'" );
    }

    public function export_table_chunk( $table, $offset = 0, $limit = 1000 ) {
        // Protect sensitive data based on settings
        $excluded_columns = isset( $this->settings['database']['excluded_columns'] ) ? $this->settings['database']['excluded_columns'] : [];
        
        $columns = $this->db->get_results( "SHOW COLUMNS FROM `$table`", ARRAY_A );
        $primary_key = '';
        $select_cols = [];
        
        foreach ( $columns as $col ) {
            if ( $col['Key'] === 'PRI' ) {
                $primary_key = $col['Field'];
            }
            if ( ! in_array( $col['Field'], $excluded_columns ) ) {
                $select_cols[] = "`{$col['Field']}`";
            } else {
                $select_cols[] = "'' AS `{$col['Field']}`"; // Replace sensitive with empty string
            }
        }

        $select_sql = implode( ', ', $select_cols );
        
        // Determistic chunking using primary key if available, else offset
        if ( $primary_key && $offset > 0 ) {
            $query = $this->db->prepare( "SELECT $select_sql FROM `$table` WHERE `$primary_key` > %d ORDER BY `$primary_key` ASC LIMIT %d", $offset, $limit );
        } else {
            $query = $this->db->prepare( "SELECT $select_sql FROM `$table` LIMIT %d, %d", $offset, $limit );
        }

        $rows = $this->db->get_results( $query, ARRAY_N );
        
        if ( empty( $rows ) ) {
            return false;
        }

        $sql = '';
        foreach ( $rows as $row ) {
            $values = [];
            foreach ( $row as $val ) {
                if ( is_null( $val ) ) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . esc_sql( $val ) . "'";
                }
            }
            $sql .= "INSERT INTO `$table` VALUES (" . implode( ', ', $values ) . ");\n";
        }

        $last_id = 0;
        if ( $primary_key ) {
            // Find primary key index in original columns
            $pk_index = 0;
            foreach ( $columns as $idx => $col ) {
                if ( $col['Field'] === $primary_key ) {
                    $pk_index = $idx;
                    break;
                }
            }
            $last_row = end( $rows );
            $last_id = $last_row[$pk_index];
        } else {
            $last_id = $offset + count( $rows );
        }

        return [
            'sql' => $sql,
            'count' => count( $rows ),
            'last_id' => $last_id,
            'primary_key' => $primary_key
        ];
    }
}
