<?php
/**
 * Plugin Name: Apify Instagram Profile Extractor
 * Plugin URI: https://github.com/mhdSameerKhan/apify-instagram-scrapper
 * Description: High-performance Instagram profile and recent posts extractor powered by Apify API. Features intelligent multi-API key rotation, 1,800 monthly request limit protection, transient caching, front-end shortcodes, live extraction playground, and developer APIs.
 * Version: 1.0.0
 * Author: Muhammad Sameer
 * Text Domain: apify-instagram-extractor
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define( 'APIFY_INSTA_VERSION', '1.0.0' );
define( 'APIFY_INSTA_FILE', __FILE__ );
define( 'APIFY_INSTA_PATH', plugin_dir_path( __FILE__ ) );
define( 'APIFY_INSTA_URL', plugin_dir_url( __FILE__ ) );
define( 'APIFY_INSTA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload / Include Core Plugin Classes
 */
require_once APIFY_INSTA_PATH . 'includes/class-cache-helper.php';
require_once APIFY_INSTA_PATH . 'includes/class-image-proxy.php';
require_once APIFY_INSTA_PATH . 'includes/class-apify-token-manager.php';
require_once APIFY_INSTA_PATH . 'includes/class-apify-scraper.php';
require_once APIFY_INSTA_PATH . 'includes/class-admin-dashboard.php';
require_once APIFY_INSTA_PATH . 'includes/class-shortcodes.php';

/**
 * Main Plugin Class (Singleton)
 */
final class Apify_Instagram_Extractor {

    private static $instance = null;

    public $token_manager;
    public $scraper;
    public $cache;
    public $image_proxy;
    public $admin;
    public $shortcodes;
    public $rest;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_components();
        $this->init_hooks();
    }

    private function init_components() {
        $this->cache         = new Apify_Insta_Cache_Helper();
        $this->image_proxy   = Apify_Insta_Image_Proxy::instance();
        $this->token_manager = new Apify_Insta_Token_Manager();
        $this->scraper       = new Apify_Insta_Scraper( $this->token_manager, $this->cache );
        $this->admin         = new Apify_Insta_Admin_Dashboard( $this->scraper, $this->token_manager, $this->cache );
        $this->shortcodes    = new Apify_Insta_Shortcodes( $this->scraper );
    }

    private function init_hooks() {
        add_filter( 'plugin_action_links_' . APIFY_INSTA_BASENAME, array( $this, 'action_links' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Safely register REST API routes when WordPress REST API initializes
     */
    public function register_rest_routes() {
        if ( ! class_exists( 'WP_REST_Controller' ) && defined( 'WPINC' ) && file_exists( ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php' ) ) {
            require_once ABSPATH . WPINC . '/rest-api/endpoints/class-wp-rest-controller.php';
        }

        if ( file_exists( APIFY_INSTA_PATH . 'includes/class-rest-controller.php' ) ) {
            require_once APIFY_INSTA_PATH . 'includes/class-rest-controller.php';
            if ( class_exists( 'Apify_Insta_REST_Controller' ) ) {
                $this->rest = new Apify_Insta_REST_Controller( $this->scraper );
                $this->rest->register_routes();
            }
        }
    }

    public static function activate_plugin() {
        self::instance()->activate();
    }

    public static function deactivate_plugin() {
        self::instance()->deactivate();
    }

    public function activate() {
        // Initialize default settings
        $default_settings = array(
            'rotation_strategy' => 'round_robin', // 'round_robin', 'sequential', 'least_used'
            'cache_duration'    => 86400,         // 24 hours in seconds
            'default_posts'     => 6,             // Number of posts to fetch
            'request_timeout'   => 75,            // Request timeout seconds
            'include_about'     => false,
            'public_search'     => 'public',      // 'public', 'logged_in', 'admin_only'
            'log_history'       => true,
            'proxy_images'      => true,
        );

        if ( false === get_option( 'apify_insta_settings' ) ) {
            update_option( 'apify_insta_settings', $default_settings );
        }

        // Tokens are managed via WP Admin > Instagram Extractor > API Keys
        if ( false === get_option( 'apify_insta_tokens' ) ) {
            update_option( 'apify_insta_tokens', array() );
        }
    }

    public function deactivate() {
        // Cleanup if needed (transients expire naturally)
    }

    public function action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=apify-instagram' ) . '">' . esc_html__( 'Settings & Extractor', 'apify-instagram-extractor' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}

// Global Activation / Deactivation Hooks
register_activation_hook( __FILE__, array( 'Apify_Instagram_Extractor', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Apify_Instagram_Extractor', 'deactivate_plugin' ) );

/**
 * Initialize Plugin Instance
 */
function apify_instagram_extractor() {
    return Apify_Instagram_Extractor::instance();
}

if ( did_action( 'plugins_loaded' ) ) {
    apify_instagram_extractor();
} else {
    add_action( 'plugins_loaded', 'apify_instagram_extractor' );
}

/**
 * Global Developer Helper: Extract Instagram Profile Data
 *
 * @param string $username Instagram username or URL
 * @param array  $options Optional arguments (force_refresh, include_posts, posts_count)
 * @return array|WP_Error Normalized profile data array or WP_Error on failure
 */
function apify_insta_get_profile( $username, $options = array() ) {
    return apify_instagram_extractor()->scraper->get_profile( $username, $options );
}

/**
 * Global Developer Helper: Render Profile Card HTML
 *
 * @param string $username Instagram username or URL
 * @param array  $args Display arguments (layout, theme, show_posts, posts_count)
 * @return string HTML markup
 */
function apify_insta_render_profile( $username, $args = array() ) {
    return apify_instagram_extractor()->shortcodes->render_profile_card( $username, $args );
}
