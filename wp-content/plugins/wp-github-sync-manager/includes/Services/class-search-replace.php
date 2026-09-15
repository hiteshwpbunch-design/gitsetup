<?php
namespace WPGitHubSync\Services;

defined( 'ABSPATH' ) || exit;

class Search_Replace {
    
    public function __construct() {}

    /**
     * Safely search and replace in serialized data and strings.
     * Modified from standard WordPress DB search/replace logic.
     */
    public function recursive_replace( $search, $replace, $data ) {
        if ( is_string( $data ) ) {
            $unserialized = @unserialize( $data );
            if ( $unserialized !== false || $data === 'b:0;' ) {
                $replaced = $this->recursive_replace( $search, $replace, $unserialized );
                return serialize( $replaced );
            }
            return str_replace( $search, $replace, $data );
        } elseif ( is_array( $data ) ) {
            $new_array = [];
            foreach ( $data as $key => $value ) {
                $new_key = is_string( $key ) ? str_replace( $search, $replace, $key ) : $key;
                $new_array[ $new_key ] = $this->recursive_replace( $search, $replace, $value );
            }
            return $new_array;
        } elseif ( is_object( $data ) ) {
            $new_object = clone $data;
            foreach ( get_object_vars( $data ) as $key => $value ) {
                $new_object->$key = $this->recursive_replace( $search, $replace, $value );
            }
            return $new_object;
        }
        return $data;
    }
}
