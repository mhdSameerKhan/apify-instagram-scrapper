<?php
/**
 * Apify Token Manager Class
 *
 * Manages multiple Apify API tokens, automated round-robin / alternating rotation,
 * monthly request usage tracking (1,800 free limits), automatic failover, and token health checks.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Apify_Insta_Token_Manager {

    private $option_name = 'apify_insta_tokens';
    private $pointer_option = 'apify_insta_token_pointer';

    public function __construct() {
        // Auto-heal any tokens that had their usage falsely inflated to 1800 or contained full URLs
        $this->sanitize_token_usages();
    }

    /**
     * Clean token string, stripping any URL prefixes or query parameters if pasted by mistake
     */
    public function clean_token_string( $token_str ) {
        $token_str = trim( (string) $token_str );
        // Extract apify_api_... token if user pasted full URL or query string
        if ( preg_match( '/apify_api_[a-zA-Z0-9_]+/i', $token_str, $matches ) ) {
            return $matches[0];
        }
        return $token_str;
    }

    /**
     * Auto-heal falsely inflated usage counters and clean URLs from tokens
     */
    private function sanitize_token_usages() {
        $tokens = get_option( $this->option_name, array() );
        if ( ! is_array( $tokens ) || empty( $tokens ) ) {
            return;
        }

        $month   = $this->get_current_month();
        $updated = false;

        foreach ( $tokens as &$t ) {
            // Auto-clean token if a full URL was pasted
            if ( isset( $t['token'] ) ) {
                $cleaned = $this->clean_token_string( $t['token'] );
                if ( $cleaned !== $t['token'] ) {
                    $t['token']      = $cleaned;
                    $t['last_status']= 'ok';
                    $t['last_error'] = '';
                    $updated         = true;
                }
            }

            $month_usage = isset( $t['monthly_usage'][ $month ] ) ? intval( $t['monthly_usage'][ $month ] ) : 0;
            $total_usage = isset( $t['total_usage'] ) ? intval( $t['total_usage'] ) : 0;

            // If monthly usage was artificially set to 1800 due to an error, restore to true total usage
            if ( $month_usage >= 1800 && $total_usage < 1800 ) {
                $t['monthly_usage'][ $month ] = $total_usage;
                $t['last_status']             = 'ok';
                $t['last_error']              = '';
                $updated                      = true;
            }
        }

        if ( $updated ) {
            update_option( $this->option_name, $tokens );
        }
    }

    /**
     * Get all configured tokens
     *
     * @return array
     */
    public function get_tokens() {
        $tokens = get_option( $this->option_name, array() );
        return is_array( $tokens ) ? $tokens : array();
    }

    /**
     * Save all tokens
     *
     * @param array $tokens
     * @return bool
     */
    public function save_tokens( $tokens ) {
        return update_option( $this->option_name, array_values( $tokens ) );
    }

    /**
     * Get single token by ID
     *
     * @param string $id
     * @return array|null
     */
    public function get_token_by_id( $id ) {
        $tokens = $this->get_tokens();
        foreach ( $tokens as $item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Add a new API token
     *
     * @param string $token_str
     * @param string $label
     * @param int    $limit Default 1800
     * @return array|WP_Error
     */
    public function add_token( $token_str, $label = '', $limit = 1800 ) {
        $token_str = $this->clean_token_string( sanitize_text_field( $token_str ) );
        if ( empty( $token_str ) ) {
            return new WP_Error( 'empty_token', __( 'API Token cannot be empty.', 'apify-instagram-extractor' ) );
        }

        $tokens = $this->get_tokens();

        // Check if token already exists
        foreach ( $tokens as $existing ) {
            if ( isset( $existing['token'] ) && $existing['token'] === $token_str ) {
                return new WP_Error( 'duplicate_token', __( 'This Apify API Token is already added.', 'apify-instagram-extractor' ) );
            }
        }

        $id = 'tok_' . substr( md5( $token_str . microtime() ), 0, 10 );
        $count = count( $tokens ) + 1;
        $label = ! empty( $label ) ? sanitize_text_field( $label ) : sprintf( __( 'Apify Token #%d', 'apify-instagram-extractor' ), $count );

        $new_token = array(
            'id'            => $id,
            'token'         => $token_str,
            'label'         => $label,
            'limit'         => max( 1, intval( $limit ) ),
            'status'        => 'active', // active, paused
            'created_at'    => current_time( 'mysql' ),
            'monthly_usage' => array(),
            'total_usage'   => 0,
            'last_used'     => 0,
            'last_status'   => 'ok',
            'last_error'    => '',
            'account_name'  => '',
        );

        // Run verification test to auto-populate account name
        $test_result = $this->test_token( $token_str );
        if ( ! is_wp_error( $test_result ) && ! empty( $test_result['username'] ) ) {
            $new_token['account_name'] = $test_result['username'];
            if ( empty( $label ) || $label === sprintf( __( 'Apify Token #%d', 'apify-instagram-extractor' ), $count ) ) {
                $new_token['label'] = sprintf( __( '%s (%s)', 'apify-instagram-extractor' ), $test_result['name'], $test_result['username'] );
            }
        }

        $tokens[] = $new_token;
        $this->save_tokens( $tokens );

        return $new_token;
    }

    /**
     * Update an existing token
     *
     * @param string $id
     * @param array  $data
     * @return bool|WP_Error
     */
    public function update_token( $id, $data ) {
        $tokens = $this->get_tokens();
        $found  = false;

        foreach ( $tokens as &$item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                if ( isset( $data['label'] ) ) {
                    $item['label'] = sanitize_text_field( $data['label'] );
                }
                if ( isset( $data['limit'] ) ) {
                    $item['limit'] = max( 1, intval( $data['limit'] ) );
                }
                if ( isset( $data['status'] ) && in_array( $data['status'], array( 'active', 'paused' ), true ) ) {
                    $item['status'] = $data['status'];
                }
                if ( isset( $data['token'] ) && ! empty( $data['token'] ) ) {
                    $item['token'] = $this->clean_token_string( sanitize_text_field( $data['token'] ) );
                }
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error( 'not_found', __( 'Token not found.', 'apify-instagram-extractor' ) );
        }

        return $this->save_tokens( $tokens );
    }

    /**
     * Delete token
     *
     * @param string $id
     * @return bool
     */
    public function delete_token( $id ) {
        $tokens = $this->get_tokens();
        $new_tokens = array();
        $deleted = false;

        foreach ( $tokens as $item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                $deleted = true;
                continue;
            }
            $new_tokens[] = $item;
        }

        if ( $deleted ) {
            $this->save_tokens( $new_tokens );
        }

        return $deleted;
    }

    /**
     * Toggle active/paused status
     */
    public function toggle_status( $id ) {
        $tokens = $this->get_tokens();
        foreach ( $tokens as &$item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                $item['status'] = ( $item['status'] === 'active' ) ? 'paused' : 'active';
                $this->save_tokens( $tokens );
                return $item['status'];
            }
        }
        return false;
    }

    /**
     * Reset token usage counter
     */
    public function reset_usage( $id, $month = null ) {
        if ( is_null( $month ) ) {
            $month = $this->get_current_month();
        }

        $tokens = $this->get_tokens();
        foreach ( $tokens as &$item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                $item['monthly_usage'][ $month ] = 0;
                $item['last_status']             = 'ok';
                $item['last_error']              = '';
                $this->save_tokens( $tokens );
                return true;
            }
        }
        return false;
    }

    /**
     * Get current month string (e.g. "2026-10")
     */
    public function get_current_month() {
        return gmdate( 'Y-m' );
    }

    /**
     * Get token monthly usage
     */
    public function get_token_monthly_usage( $token_data, $month = null ) {
        if ( is_null( $month ) ) {
            $month = $this->get_current_month();
        }
        return isset( $token_data['monthly_usage'][ $month ] ) ? intval( $token_data['monthly_usage'][ $month ] ) : 0;
    }

    /**
     * Get eligible active tokens
     *
     * @param string|null $exclude_id Optional token ID to exclude (e.g. during failover)
     * @return array
     */
    public function get_available_tokens( $exclude_id = null ) {
        $tokens    = $this->get_tokens();
        $month     = $this->get_current_month();
        $available = array();
        $active_all= array();

        foreach ( $tokens as $t ) {
            if ( ! isset( $t['status'] ) || $t['status'] !== 'active' ) {
                continue;
            }

            $active_all[] = $t;

            if ( ! empty( $exclude_id ) && $t['id'] === $exclude_id ) {
                continue;
            }

            $limit = isset( $t['limit'] ) ? intval( $t['limit'] ) : 1800;
            $used  = $this->get_token_monthly_usage( $t, $month );

            if ( $used < $limit ) {
                $available[] = $t;
            }
        }

        // Safety fallback: If all available were excluded or exhausted, but active tokens exist,
        // use active tokens so we NEVER lock the user out with a hard failure.
        if ( empty( $available ) && ! empty( $active_all ) ) {
            return $active_all;
        }

        return $available;
    }

    /**
     * Get next token to use according to rotation strategy
     *
     * @param string|null $exclude_id Token ID to exclude if retrying on failover
     * @return array|WP_Error Token data array or WP_Error if none available
     */
    public function get_next_token( $exclude_id = null ) {
        $available = $this->get_available_tokens( $exclude_id );

        if ( empty( $available ) ) {
            $all_tokens = $this->get_tokens();
            if ( empty( $all_tokens ) ) {
                return new WP_Error( 'no_tokens', __( 'No Apify API tokens configured. Please add an API token in WordPress Admin > Instagram Extractor > API Keys & Rotation.', 'apify-instagram-extractor' ) );
            }

            // If tokens are paused, activate the first one automatically
            foreach ( $all_tokens as &$tok ) {
                if ( isset( $tok['status'] ) && $tok['status'] === 'paused' ) {
                    $tok['status'] = 'active';
                    $this->save_tokens( $all_tokens );
                    return $tok;
                }
            }

            return $all_tokens[0];
        }

        // If only 1 available, return it directly
        if ( count( $available ) === 1 ) {
            return $available[0];
        }

        $settings = get_option( 'apify_insta_settings', array() );
        $strategy = isset( $settings['rotation_strategy'] ) ? $settings['rotation_strategy'] : 'round_robin';

        switch ( $strategy ) {
            case 'least_used':
                $month = $this->get_current_month();
                usort( $available, function( $a, $b ) use ( $month ) {
                    $uA = $this->get_token_monthly_usage( $a, $month );
                    $uB = $this->get_token_monthly_usage( $b, $month );
                    return $uA <=> $uB;
                });
                return $available[0];

            case 'sequential':
                return $available[0];

            case 'round_robin':
            default:
                $pointer = intval( get_option( $this->pointer_option, 0 ) );
                $selected_index = $pointer % count( $available );
                $selected_token = $available[ $selected_index ];

                // Advance pointer for next request
                update_option( $this->pointer_option, ( $pointer + 1 ) % 1000000 );

                return $selected_token;
        }
    }

    /**
     * Record successful API request for a token
     *
     * @param string $id Token ID
     * @return bool
     */
    public function record_success( $id ) {
        $tokens = $this->get_tokens();
        $month  = $this->get_current_month();
        $found  = false;

        foreach ( $tokens as &$item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                if ( ! isset( $item['monthly_usage'] ) || ! is_array( $item['monthly_usage'] ) ) {
                    $item['monthly_usage'] = array();
                }

                $item['monthly_usage'][ $month ] = isset( $item['monthly_usage'][ $month ] ) ? intval( $item['monthly_usage'][ $month ] ) + 1 : 1;
                $item['total_usage']             = isset( $item['total_usage'] ) ? intval( $item['total_usage'] ) + 1 : 1;
                $item['last_used']               = current_time( 'timestamp' );
                $item['last_status']             = 'ok';
                $item['last_error']              = '';
                $found = true;
                break;
            }
        }

        if ( $found ) {
            return $this->save_tokens( $tokens );
        }

        return false;
    }

    /**
     * Mark a token error without artificially inflating request counts
     *
     * @param string $id
     * @param string $error_type
     * @param string $message
     */
    public function record_error( $id, $error_type, $message = '' ) {
        $tokens = $this->get_tokens();

        foreach ( $tokens as &$item ) {
            if ( isset( $item['id'] ) && $item['id'] === $id ) {
                $item['last_status'] = $error_type;
                $item['last_error']  = sanitize_text_field( $message );
                // NOTE: We NEVER artificially force monthly_usage to 1800!
                break;
            }
        }

        $this->save_tokens( $tokens );
    }

    /**
     * Test token connection against live Apify user endpoint
     *
     * @param string $token_str
     * @return array|WP_Error
     */
    public function test_token( $token_str ) {
        $token_str = $this->clean_token_string( $token_str );
        if ( empty( $token_str ) ) {
            return new WP_Error( 'empty_token', __( 'Token cannot be empty.', 'apify-instagram-extractor' ) );
        }

        $url = 'https://api.apify.com/v2/users/me?token=' . rawurlencode( $token_str );

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'http_error', sprintf( __( 'Connection failed: %s', 'apify-instagram-extractor' ), $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code === 401 || $code === 403 ) {
            $msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Invalid Apify API token.', 'apify-instagram-extractor' );
            return new WP_Error( 'invalid_token', $msg );
        }

        if ( $code !== 200 || ! isset( $data['data'] ) ) {
            return new WP_Error( 'api_error', sprintf( __( 'Apify responded with HTTP %d: %s', 'apify-instagram-extractor' ), $code, $body ) );
        }

        $user_data = $data['data'];
        $plan_info = isset( $user_data['plan'] ) ? $user_data['plan'] : array();

        return array(
            'id'       => isset( $user_data['id'] ) ? $user_data['id'] : '',
            'username' => isset( $user_data['username'] ) ? $user_data['username'] : '',
            'name'     => isset( $user_data['profile']['name'] ) ? $user_data['profile']['name'] : '',
            'email'    => isset( $user_data['email'] ) ? $user_data['email'] : '',
            'tier'     => isset( $plan_info['tier'] ) ? $plan_info['tier'] : 'FREE',
            'credits'  => isset( $plan_info['monthlyUsageCreditsUsd'] ) ? $plan_info['monthlyUsageCreditsUsd'] : 5,
        );
    }
}
