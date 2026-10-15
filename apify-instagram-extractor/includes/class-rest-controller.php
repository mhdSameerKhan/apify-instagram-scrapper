<?php
/**
 * REST API Controller for Apify Instagram Extractor
 *
 * Exposes endpoints for extracting Instagram profiles programmatically.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure WP_REST_Controller is loaded if called before rest_api_init
if ( ! class_exists( 'WP_REST_Controller' ) ) {
    if ( defined( 'WPINC' ) && file_exists( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php' ) ) {
        require_once ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php';
    }
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
    return;
}

class Apify_Insta_REST_Controller extends WP_REST_Controller {

    private $scraper;
    protected $namespace = 'apify-insta/v1';
    protected $rest_base = 'profile';

    public function __construct( Apify_Insta_Scraper $scraper ) {
        $this->scraper = $scraper;
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<username>[a-zA-Z0-9_\.]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'permissions_check' ),
                    'args'                => array(
                        'username' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'force_refresh' => array(
                            'required' => false,
                            'type'     => 'boolean',
                            'default'  => false,
                        ),
                        'posts_count' => array(
                            'required' => false,
                            'type'     => 'integer',
                            'default'  => 6,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Check request permissions
     */
    public function permissions_check( $request ) {
        $settings = get_option( 'apify_insta_settings', array() );
        $perm     = isset( $settings['public_search'] ) ? $settings['public_search'] : 'admin_only';

        if ( 'public' === $perm ) {
            return true;
        }

        if ( 'logged_in' === $perm ) {
            return is_user_logged_in();
        }

        return current_user_can( 'manage_options' );
    }

    /**
     * Handle GET request for profile
     */
    public function get_item( $request ) {
        $username      = $request->get_param( 'username' );
        $force_refresh = (bool) $request->get_param( 'force_refresh' );
        $posts_count   = intval( $request->get_param( 'posts_count' ) );

        $data = $this->scraper->get_profile( $username, array(
            'force_refresh' => $force_refresh,
            'posts_count'   => $posts_count ? $posts_count : 6,
        ) );

        if ( is_wp_error( $data ) ) {
            return new WP_Error(
                $data->get_error_code(),
                $data->get_error_message(),
                array( 'status' => 400 )
            );
        }

        return rest_ensure_response( array(
            'success' => true,
            'data'    => $data,
        ) );
    }
}
