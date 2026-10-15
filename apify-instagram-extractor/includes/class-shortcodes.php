<?php
/**
 * Shortcodes & Front-end Controller
 *
 * Handles [instagram_profile] and [instagram_profile_search] shortcodes,
 * template rendering, script enqueues, and front-end AJAX search.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Apify_Insta_Shortcodes {

    private $scraper;

    public function __construct( Apify_Insta_Scraper $scraper ) {
        $this->scraper = $scraper;

        add_shortcode( 'instagram_profile', array( $this, 'shortcode_profile' ) );
        add_shortcode( 'instagram_profile_search', array( $this, 'shortcode_profile' ) ); // Alias

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // AJAX handlers for front-end live search widget
        add_action( 'wp_ajax_apify_insta_frontend_search', array( $this, 'ajax_frontend_search' ) );
        add_action( 'wp_ajax_nopriv_apify_insta_frontend_search', array( $this, 'ajax_frontend_search' ) );
    }

    /**
     * Enqueue front-end styling and interactive scripts
     */
    public function enqueue_frontend_assets() {
        wp_register_style(
            'apify-insta-frontend',
            APIFY_INSTA_URL . 'assets/css/frontend.css',
            array(),
            APIFY_INSTA_VERSION
        );

        wp_register_script(
            'apify-insta-frontend',
            APIFY_INSTA_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            APIFY_INSTA_VERSION,
            true
        );

        wp_localize_script( 'apify-insta-frontend', 'apifyInstaFrontend', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'apify_insta_frontend_nonce' ),
            'strings'  => array(
                'loading'     => __( 'Connecting to Instagram & extracting profile...', 'apify-instagram-extractor' ),
                'enterHandle' => __( 'Please enter an Instagram handle (e.g. cristiano or nasa).', 'apify-instagram-extractor' ),
                'error'       => __( 'Failed to extract profile. Please check the handle or API key.', 'apify-instagram-extractor' ),
            ),
        ) );
    }

    /**
     * Shortcode: [instagram_profile]
     *
     * Provides an interactive search UI by default.
     * If username attribute is provided (e.g. [instagram_profile username="cristiano"]),
     * pre-extracts and displays that profile with the search bar above it.
     */
    public function shortcode_profile( $atts ) {
        $atts = shortcode_atts( array(
            'username'      => '',
            'layout'        => 'card',     // card, compact, banner
            'show_posts'    => 'yes',      // yes, no
            'posts_count'   => 6,
            'theme'         => 'light',    // light, dark, gradient
            'show_search'   => 'yes',      // yes, no
            'force_refresh' => 'no',
            'placeholder'   => __( 'Enter Instagram username or URL...', 'apify-instagram-extractor' ),
            'button_text'   => __( 'Extract Profile', 'apify-instagram-extractor' ),
        ), $atts, 'instagram_profile' );

        wp_enqueue_style( 'apify-insta-frontend' );
        wp_enqueue_script( 'apify-insta-frontend' );

        $preloaded_profile = null;

        // If a specific username was given in the shortcode, pre-fetch it on load
        if ( ! empty( $atts['username'] ) ) {
            $preloaded_profile = $this->scraper->get_profile( $atts['username'], array(
                'force_refresh' => ( $atts['force_refresh'] === 'yes' ),
                'include_posts' => ( $atts['show_posts'] === 'yes' ),
                'posts_count'   => intval( $atts['posts_count'] ),
            ) );
        }

        ob_start();
        include APIFY_INSTA_PATH . 'templates/search-widget.php';
        return ob_get_clean();
    }

    /**
     * Render Profile Card HTML template
     *
     * @param array|string $profile Profile data array or username string
     * @param array        $args Layout parameters
     * @return string
     */
    public function render_profile_card( $profile, $args = array() ) {
        wp_enqueue_style( 'apify-insta-frontend' );
        wp_enqueue_script( 'apify-insta-frontend' );

        if ( is_string( $profile ) ) {
            $profile = $this->scraper->get_profile( $profile, $args );
            if ( is_wp_error( $profile ) ) {
                return '<div class="apify-insta-notice apify-insta-notice-error">' . esc_html( $profile->get_error_message() ) . '</div>';
            }
        }

        $layout     = isset( $args['layout'] ) ? $args['layout'] : 'card';
        $theme      = isset( $args['theme'] ) ? $args['theme'] : 'light';
        $show_posts = ! isset( $args['show_posts'] ) || ( $args['show_posts'] === 'yes' || $args['show_posts'] === true );

        $template_map = array(
            'compact' => APIFY_INSTA_PATH . 'templates/profile-compact.php',
            'banner'  => APIFY_INSTA_PATH . 'templates/profile-banner.php',
            'card'    => APIFY_INSTA_PATH . 'templates/profile-card.php',
        );

        $template = isset( $template_map[ $layout ] ) && file_exists( $template_map[ $layout ] )
            ? $template_map[ $layout ]
            : $template_map['card'];

        ob_start();
        include $template;
        return ob_get_clean();
    }

    /**
     * AJAX handler for front-end live extraction
     */
    public function ajax_frontend_search() {
        check_ajax_referer( 'apify_insta_frontend_nonce', 'nonce' );

        $username      = isset( $_POST['username'] ) ? sanitize_text_field( $_POST['username'] ) : '';
        $theme         = isset( $_POST['theme'] ) ? sanitize_text_field( $_POST['theme'] ) : 'light';
        $layout        = isset( $_POST['layout'] ) ? sanitize_text_field( $_POST['layout'] ) : 'card';
        $posts_count   = isset( $_POST['posts_count'] ) ? intval( $_POST['posts_count'] ) : 6;
        $show_posts    = isset( $_POST['show_posts'] ) ? sanitize_text_field( $_POST['show_posts'] ) : 'yes';
        $force_refresh = ! empty( $_POST['force_refresh'] );

        if ( empty( $username ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter an Instagram handle.', 'apify-instagram-extractor' ) ) );
        }

        $profile = $this->scraper->get_profile( $username, array(
            'posts_count'   => $posts_count,
            'include_posts' => ( $show_posts === 'yes' ),
            'force_refresh' => $force_refresh,
        ) );

        if ( is_wp_error( $profile ) ) {
            $msg = $profile->get_error_message();
            if ( $profile->get_error_code() === 'no_tokens' ) {
                if ( current_user_can( 'manage_options' ) ) {
                    $msg = __( 'No Apify API key configured yet. Please go to WordPress Admin > Instagram Extractor > API Keys & Rotation to add your API key.', 'apify-instagram-extractor' );
                } else {
                    $msg = __( 'Instagram profile extraction is temporarily unavailable. Please contact the site administrator.', 'apify-instagram-extractor' );
                }
            }
            wp_send_json_error( array( 'message' => $msg ) );
        }

        $html = $this->render_profile_card( $profile, array(
            'layout'     => $layout,
            'theme'      => $theme,
            'show_posts' => ( $show_posts === 'yes' ),
        ) );

        wp_send_json_success( array(
            'html'    => $html,
            'profile' => $profile,
        ) );
    }
}
