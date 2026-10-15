<?php
/**
 * Cache Helper Class for Apify Instagram Extractor
 *
 * Manages transient caching of Instagram profile data to drastically reduce
 * API calls and prevent hitting Apify monthly rate limits.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Apify_Insta_Cache_Helper {

    private $prefix = 'apify_insta_p_';
    private $index_option = 'apify_insta_cached_keys';

    /**
     * Generate cache key for username
     */
    public function get_key( $username ) {
        $clean = strtolower( trim( $username ) );
        return $this->prefix . md5( $clean );
    }

    /**
     * Retrieve cached profile
     *
     * @param string $username
     * @return array|false
     */
    public function get( $username ) {
        $key  = $this->get_key( $username );
        $data = get_transient( $key );

        if ( ! empty( $data ) && is_array( $data ) ) {
            $data['_is_cached'] = true;
            return $data;
        }

        return false;
    }

    /**
     * Save profile data to cache
     *
     * @param string $username
     * @param array  $data
     * @param int    $expiration Expiration in seconds
     * @return bool
     */
    public function set( $username, $data, $expiration = 86400 ) {
        $key = $this->get_key( $username );

        // Attach cached timestamp
        $data['_cached_at'] = current_time( 'timestamp' );
        $data['_cached_human'] = current_time( 'mysql' );

        $saved = set_transient( $key, $data, $expiration );

        if ( $saved ) {
            $this->add_to_index( $key, $username );
        }

        return $saved;
    }

    /**
     * Delete cached profile
     *
     * @param string $username
     * @return bool
     */
    public function delete( $username ) {
        $key = $this->get_key( $username );
        $this->remove_from_index( $key );
        return delete_transient( $key );
    }

    /**
     * Clear all cached Instagram profiles
     *
     * @return int Number of cleared items
     */
    public function flush_all() {
        $keys = get_option( $this->index_option, array() );
        $count = 0;

        if ( is_array( $keys ) ) {
            foreach ( $keys as $key => $meta ) {
                if ( delete_transient( $key ) ) {
                    $count++;
                }
            }
        }

        update_option( $this->index_option, array() );
        return $count;
    }

    /**
     * Get count of currently cached profiles
     */
    public function get_cached_count() {
        $keys = get_option( $this->index_option, array() );
        return is_array( $keys ) ? count( $keys ) : 0;
    }

    /**
     * Record key in index
     */
    private function add_to_index( $key, $username ) {
        $keys = get_option( $this->index_option, array() );
        if ( ! is_array( $keys ) ) {
            $keys = array();
        }

        $keys[ $key ] = array(
            'username'   => sanitize_text_field( $username ),
            'created_at' => current_time( 'timestamp' ),
        );

        // Keep maximum 200 items in the index registry
        if ( count( $keys ) > 200 ) {
            $keys = array_slice( $keys, -200, 200, true );
        }

        update_option( $this->index_option, $keys, false );
    }

    /**
     * Remove key from index
     */
    private function remove_from_index( $key ) {
        $keys = get_option( $this->index_option, array() );
        if ( is_array( $keys ) && isset( $keys[ $key ] ) ) {
            unset( $keys[ $key ] );
            update_option( $this->index_option, $keys, false );
        }
    }
}
