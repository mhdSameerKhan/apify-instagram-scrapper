<?php
/**
 * Apify Instagram Scraper Client
 *
 * Handles HTTP requests to the Apify Instagram Profile Scraper actor,
 * payload formatting, auto-retry across alternating API keys,
 * data normalization, and history logging.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Apify_Insta_Scraper {

    private $token_manager;
    private $cache;
    private $actor_id = 'apify~instagram-profile-scraper';
    private $history_option = 'apify_insta_history_logs';

    public function __construct( Apify_Insta_Token_Manager $token_manager, Apify_Insta_Cache_Helper $cache ) {
        $this->token_manager = $token_manager;
        $this->cache         = $cache;
    }

    /**
     * Clean and extract raw Instagram username from handles, @mentions, or URLs
     *
     * @param string $input
     * @return string
     */
    public function clean_username( $input ) {
        $input = trim( $input );

        // Strip leading @
        $input = ltrim( $input, '@' );

        // Check if full URL is passed
        if ( preg_match( '#instagram\.com/([a-zA-Z0-9_\.]+)/?#i', $input, $matches ) ) {
            $input = $matches[1];
        }

        // Clean query strings or slashes
        $input = strtok( $input, '?' );
        $input = trim( $input, '/' );

        // Final sanitation (Instagram usernames can only contain letters, numbers, periods, and underscores)
        $input = preg_replace( '/[^a-zA-Z0-9_\.]/', '', $input );

        return strtolower( $input );
    }

    /**
     * Get Instagram Profile Data
     *
     * @param string $username Instagram username or profile URL
     * @param array  $options Optional overrides: force_refresh, include_posts, posts_count, timeout
     * @return array|WP_Error
     */
    public function get_profile( $username, $options = array() ) {
        $start_time = microtime( true );
        $clean_user = $this->clean_username( $username );

        if ( empty( $clean_user ) ) {
            return new WP_Error( 'invalid_username', __( 'Please provide a valid Instagram username or profile URL.', 'apify-instagram-extractor' ) );
        }

        $force_refresh = ! empty( $options['force_refresh'] );

        // 1. Check transient cache
        if ( ! $force_refresh ) {
            $cached = $this->cache->get( $clean_user );
            if ( $cached ) {
                $elapsed = round( ( microtime( true ) - $start_time ) * 1000 );
                $this->log_request( $clean_user, 'Cached Memory', 'success', $elapsed, true );
                return $cached;
            }
        }

        // 2. Fetch plugin settings
        $settings      = get_option( 'apify_insta_settings', array() );
        $timeout       = isset( $options['timeout'] ) ? intval( $options['timeout'] ) : ( isset( $settings['request_timeout'] ) ? intval( $settings['request_timeout'] ) : 75 );
        $posts_count   = isset( $options['posts_count'] ) ? intval( $options['posts_count'] ) : ( isset( $settings['default_posts'] ) ? intval( $settings['default_posts'] ) : 6 );
        $include_posts = isset( $options['include_posts'] ) ? (bool) $options['include_posts'] : true;
        $include_about = isset( $options['include_about'] ) ? (bool) $options['include_about'] : ( ! empty( $settings['include_about'] ) );
        $cache_seconds = isset( $settings['cache_duration'] ) ? intval( $settings['cache_duration'] ) : 86400;

        // 3. Request Apify with auto-rotation and failover support
        $max_attempts = 3;
        $attempt      = 0;
        $excluded_ids = array();
        $last_error   = null;

        while ( $attempt < $max_attempts ) {
            $attempt++;

            // Get next token according to rotation strategy
            $token_data = $this->token_manager->get_next_token( ! empty( $excluded_ids ) ? end( $excluded_ids ) : null );

            if ( is_wp_error( $token_data ) ) {
                $last_error = $token_data;
                break;
            }

            $token_id  = $token_data['id'];
            $token_str = $token_data['token'];

            $result = $this->execute_sync_request( $clean_user, $token_str, array(
                'include_posts' => $include_posts,
                'posts_count'   => $posts_count,
                'include_about' => $include_about,
                'timeout'       => $timeout,
            ) );

            // If successful
            if ( ! is_wp_error( $result ) ) {
                // Record successful request on token counter
                $this->token_manager->record_success( $token_id );

                // Normalize profile data
                $normalized = $this->normalize_profile_data( $result, $token_data );

                // Store in transient cache
                if ( $cache_seconds > 0 ) {
                    $this->cache->set( $clean_user, $normalized, $cache_seconds );
                }

                $elapsed = round( ( microtime( true ) - $start_time ) * 1000 );
                $this->log_request( $clean_user, $token_data['label'], 'success', $elapsed, false );

                return $normalized;
            }

            // If error occurred, inspect error code for rate limit or quota
            $err_code = $result->get_error_code();
            $err_msg  = $result->get_error_message();
            $last_error = $result;

            if ( in_array( $err_code, array( 'rate_limited', 'quota_exceeded', 'auth_error' ), true ) ) {
                // Mark token with error so next token will be used
                $this->token_manager->record_error( $token_id, $err_code, $err_msg );
                $excluded_ids[] = $token_id;
                // Loop continues to attempt next token
                continue;
            } else {
                // Non-quota error (e.g. timeout or profile not found)
                break;
            }
        }

        $elapsed = round( ( microtime( true ) - $start_time ) * 1000 );
        $this->log_request( $clean_user, 'Unknown', 'failed', $elapsed, false, is_wp_error( $last_error ) ? $last_error->get_error_message() : 'Extraction failed' );

        return is_wp_error( $last_error ) ? $last_error : new WP_Error( 'extraction_failed', __( 'Could not extract Instagram profile. Please check username and API credentials.', 'apify-instagram-extractor' ) );
    }

    /**
     * Execute Synchronous Run on Apify API
     *
     * @param string $username
     * @param string $token
     * @param array  $args
     * @return array|WP_Error
     */
    private function execute_sync_request( $username, $token, $args = array() ) {
        $token         = $this->token_manager->clean_token_string( $token );
        $timeout       = isset( $args['timeout'] ) ? $args['timeout'] : 75;
        $include_posts = isset( $args['include_posts'] ) ? (bool) $args['include_posts'] : true;
        $posts_count   = isset( $args['posts_count'] ) ? intval( $args['posts_count'] ) : 6;
        $include_about = isset( $args['include_about'] ) ? (bool) $args['include_about'] : false;

        $url = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s&timeout=%d',
            rawurlencode( $this->actor_id ),
            rawurlencode( $token ),
            $timeout
        );

        $body_params = array(
            'usernames'           => array( $username ),
            'includeRecentPosts'  => $include_posts,
            'maxRecentPosts'      => $posts_count,
            'includeAboutSection' => $include_about,
        );

        $response = wp_remote_post( $url, array(
            'timeout' => $timeout + 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'    => wp_json_encode( $body_params ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'network_error', sprintf( __( 'HTTP request failed: %s', 'apify-instagram-extractor' ), $response->get_error_message() ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        // Handle specific HTTP Status Codes
        if ( $status_code === 401 ) {
            return new WP_Error( 'auth_error', __( 'Apify API token is invalid or expired (HTTP 401).', 'apify-instagram-extractor' ) );
        }

        if ( $status_code === 403 ) {
            return new WP_Error( 'quota_exceeded', __( 'Apify API account quota exceeded or permission denied (HTTP 403).', 'apify-instagram-extractor' ) );
        }

        if ( $status_code === 429 ) {
            return new WP_Error( 'rate_limited', __( 'Apify rate limit hit (HTTP 429). Rotating to next API token.', 'apify-instagram-extractor' ) );
        }

        // If Apify returned an error object
        if ( isset( $data['error'] ) ) {
            $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Apify API error.', 'apify-instagram-extractor' );
            return new WP_Error( 'apify_error', $msg );
        }

        // In run-sync-get-dataset-items, a successful response is a JSON array of items
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'invalid_format', __( 'Unexpected response format from Apify.', 'apify-instagram-extractor' ) );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'profile_not_found', sprintf( __( 'No profile found for Instagram handle "%s". The account may be banned, changed, or private.', 'apify-instagram-extractor' ), $username ) );
        }

        // First item in dataset array is the profile
        $profile_item = $data[0];

        // Check if scraper returned an error inside the item
        if ( isset( $profile_item['error'] ) && ! empty( $profile_item['error'] ) ) {
            return new WP_Error( 'scraper_item_error', $profile_item['error'] );
        }

        return $profile_item;
    }

    /**
     * Normalize raw Apify Instagram item into a clean, rich data structure
     *
     * @param array $raw
     * @param array $token_data
     * @return array
     */
    public function normalize_profile_data( $raw, $token_data = array() ) {
        $username      = isset( $raw['username'] ) ? sanitize_text_field( $raw['username'] ) : '';
        $full_name     = isset( $raw['fullName'] ) ? sanitize_text_field( $raw['fullName'] ) : '';
        $biography     = isset( $raw['biography'] ) ? sanitize_textarea_field( $raw['biography'] ) : '';
        $external_url  = isset( $raw['externalUrl'] ) ? esc_url_raw( $raw['externalUrl'] ) : '';
        $followers     = isset( $raw['followersCount'] ) ? intval( $raw['followersCount'] ) : 0;
        $follows       = isset( $raw['followsCount'] ) ? intval( $raw['followsCount'] ) : 0;
        $posts_count       = isset( $raw['postsCount'] ) ? intval( $raw['postsCount'] ) : 0;
        $profile_pic       = isset( $raw['profilePicUrlHD'] ) && ! empty( $raw['profilePicUrlHD'] ) ? esc_url_raw( $raw['profilePicUrlHD'] ) : ( isset( $raw['profilePicUrl'] ) ? esc_url_raw( $raw['profilePicUrl'] ) : '' );
        $raw_profile_pic   = $profile_pic;
        $proxy_profile_pic = ( ! empty( $profile_pic ) && class_exists( 'Apify_Insta_Image_Proxy' ) ) ? Apify_Insta_Image_Proxy::get_proxy_url( $profile_pic ) : $profile_pic;
        $is_verified       = ! empty( $raw['verified'] );
        $is_business       = ! empty( $raw['isBusinessAccount'] );
        $is_private        = ! empty( $raw['private'] );
        $category_name     = isset( $raw['businessCategoryName'] ) ? sanitize_text_field( $raw['businessCategoryName'] ) : '';
        $user_id           = isset( $raw['id'] ) ? sanitize_text_field( $raw['id'] ) : ( isset( $raw['instagramUserId'] ) ? sanitize_text_field( $raw['instagramUserId'] ) : '' );

        // Format Biography with interactive HTML links (@mentions, #hashtags, links)
        $biography_html = $this->format_biography_html( $biography );

        // Format latest posts
        $formatted_posts = array();
        $raw_posts = isset( $raw['latestPosts'] ) && is_array( $raw['latestPosts'] ) ? $raw['latestPosts'] : array();

        foreach ( $raw_posts as $post ) {
            $post_id     = isset( $post['id'] ) ? sanitize_text_field( $post['id'] ) : '';
            $shortcode   = isset( $post['shortCode'] ) ? sanitize_text_field( $post['shortCode'] ) : '';
            $post_url    = isset( $post['url'] ) ? esc_url_raw( $post['url'] ) : ( ! empty( $shortcode ) ? 'https://www.instagram.com/p/' . $shortcode . '/' : '' );
            $raw_display = isset( $post['displayUrl'] ) ? esc_url_raw( $post['displayUrl'] ) : '';
            $proxy_disp  = ( ! empty( $raw_display ) && class_exists( 'Apify_Insta_Image_Proxy' ) ) ? Apify_Insta_Image_Proxy::get_proxy_url( $raw_display ) : $raw_display;
            $likes       = isset( $post['likesCount'] ) ? intval( $post['likesCount'] ) : 0;
            $comments    = isset( $post['commentsCount'] ) ? intval( $post['commentsCount'] ) : 0;
            $caption     = isset( $post['caption'] ) ? sanitize_textarea_field( $post['caption'] ) : '';
            $post_type   = isset( $post['type'] ) ? sanitize_text_field( $post['type'] ) : 'Image';
            $video_url   = isset( $post['videoUrl'] ) ? esc_url_raw( $post['videoUrl'] ) : '';
            $timestamp   = isset( $post['timestamp'] ) ? sanitize_text_field( $post['timestamp'] ) : '';

            $formatted_posts[] = array(
                'id'                => $post_id,
                'shortcode'         => $shortcode,
                'url'               => $post_url,
                'displayUrl'        => $proxy_disp,
                'rawDisplayUrl'     => $raw_display,
                'videoUrl'          => $video_url,
                'caption'           => $caption,
                'likes'             => $likes,
                'likesFormatted'    => $this->format_metric_number( $likes ),
                'comments'          => $comments,
                'commentsFormatted' => $this->format_metric_number( $comments ),
                'type'              => $post_type,
                'timestamp'         => $timestamp,
                'dateHuman'         => ! empty( $timestamp ) ? date_i18n( get_option( 'date_format' ), strtotime( $timestamp ) ) : '',
            );
        }

        return array(
            'id'                   => $user_id,
            'username'             => $username,
            'fullName'             => ! empty( $full_name ) ? $full_name : $username,
            'biography'            => $biography,
            'biographyHtml'        => $biography_html,
            'externalUrl'          => $external_url,
            'profileUrl'           => 'https://www.instagram.com/' . $username . '/',
            'followersCount'       => $followers,
            'followersFormatted'   => $this->format_metric_number( $followers ),
            'followsCount'         => $follows,
            'followsFormatted'     => $this->format_metric_number( $follows ),
            'postsCount'           => $posts_count,
            'postsFormatted'       => $this->format_metric_number( $posts_count ),
            'profilePicUrl'        => $proxy_profile_pic,
            'rawProfilePicUrl'     => $raw_profile_pic,
            'verified'             => $is_verified,
            'isBusinessAccount'    => $is_business,
            'isPrivate'            => $is_private,
            'businessCategoryName' => $category_name,
            'latestPosts'          => $formatted_posts,
            'postsCountFetched'    => count( $formatted_posts ),
            '_token_label'         => isset( $token_data['label'] ) ? $token_data['label'] : 'Apify',
            '_token_id'            => isset( $token_data['id'] ) ? $token_data['id'] : '',
            '_extracted_at'        => current_time( 'timestamp' ),
            '_extracted_human'     => current_time( 'mysql' ),
            '_is_cached'           => false,
            'raw'                  => $raw, // Preserves complete raw data for advanced developer hooks
        );
    }

    /**
     * Format numbers into compact human-readable strings (1.2K, 3.4M, 1.1B)
     *
     * @param int|float $number
     * @return string
     */
    public function format_metric_number( $number ) {
        $number = floatval( $number );

        if ( $number >= 1000000000 ) {
            return round( $number / 1000000000, 1 ) . 'B';
        }
        if ( $number >= 1000000 ) {
            return round( $number / 1000000, 1 ) . 'M';
        }
        if ( $number >= 10000 ) {
            return round( $number / 1000, 1 ) . 'K';
        }
        if ( $number >= 1000 ) {
            return number_format( $number );
        }

        return (string) intval( $number );
    }

    /**
     * Convert @mentions and #hashtags into clickable Instagram links
     *
     * @param string $bio
     * @return string
     */
    private function format_biography_html( $bio ) {
        if ( empty( $bio ) ) {
            return '';
        }

        $bio = esc_html( $bio );

        // Convert URLs to hyperlinks
        $bio = preg_replace(
            '#(https?://[^\s]+)#i',
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" class="apify-insta-bio-link">$1</a>',
            $bio
        );

        // Convert @mentions
        $bio = preg_replace(
            '/@([a-zA-Z0-9_\.]+)/',
            '<a href="https://www.instagram.com/$1/" target="_blank" rel="noopener noreferrer nofollow" class="apify-insta-bio-mention">@$1</a>',
            $bio
        );

        // Convert #hashtags
        $bio = preg_replace(
            '/#([a-zA-Z0-9_]+)/',
            '<a href="https://www.instagram.com/explore/tags/$1/" target="_blank" rel="noopener noreferrer nofollow" class="apify-insta-bio-tag">#$1</a>',
            $bio
        );

        return nl2br( $bio );
    }

    /**
     * Log extraction request to history registry
     */
    private function log_request( $username, $token_label, $status, $time_ms, $is_cached = false, $error_msg = '' ) {
        $logs = get_option( $this->history_option, array() );
        if ( ! is_array( $logs ) ) {
            $logs = array();
        }

        $entry = array(
            'id'          => uniqid( 'log_', true ),
            'username'    => sanitize_text_field( $username ),
            'token_label' => sanitize_text_field( $token_label ),
            'status'      => sanitize_text_field( $status ),
            'time_ms'     => intval( $time_ms ),
            'is_cached'   => (bool) $is_cached,
            'timestamp'   => current_time( 'timestamp' ),
            'date_human'  => current_time( 'mysql' ),
            'error'       => sanitize_text_field( $error_msg ),
        );

        array_unshift( $logs, $entry );

        // Keep last 100 entries
        if ( count( $logs ) > 100 ) {
            $logs = array_slice( $logs, 0, 100 );
        }

        update_option( $this->history_option, $logs, false );
    }

    /**
     * Get extraction history logs
     *
     * @return array
     */
    public function get_logs() {
        $logs = get_option( $this->history_option, array() );
        return is_array( $logs ) ? $logs : array();
    }

    /**
     * Clear history logs
     */
    public function clear_logs() {
        return update_option( $this->history_option, array(), false );
    }
}
