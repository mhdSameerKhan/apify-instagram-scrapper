<?php
/**
 * Frontend Search & Profile Interactive UI Template
 *
 * Variables available:
 * - $atts (array of shortcode attributes)
 * - $preloaded_profile (array|WP_Error|null)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme_class  = 'apify-theme-' . esc_attr( isset( $atts['theme'] ) ? $atts['theme'] : 'light' );
$placeholder  = isset( $atts['placeholder'] ) ? $atts['placeholder'] : __( 'Enter Instagram username or URL...', 'apify-instagram-extractor' );
$button_text  = isset( $atts['button_text'] ) ? $atts['button_text'] : __( 'Extract Profile', 'apify-instagram-extractor' );
$posts_count  = isset( $atts['posts_count'] ) ? intval( $atts['posts_count'] ) : 6;
$show_posts   = isset( $atts['show_posts'] ) ? $atts['show_posts'] : 'yes';
$layout       = isset( $atts['layout'] ) ? $atts['layout'] : 'card';
$show_search  = ! isset( $atts['show_search'] ) || $atts['show_search'] !== 'no';
$default_user = isset( $atts['username'] ) ? $atts['username'] : '';
?>

<div class="apify-frontend-wrapper <?php echo esc_attr( $theme_class ); ?>" data-theme="<?php echo esc_attr( isset( $atts['theme'] ) ? $atts['theme'] : 'light' ); ?>" data-layout="<?php echo esc_attr( $layout ); ?>" data-posts="<?php echo esc_attr( $posts_count ); ?>" data-show-posts="<?php echo esc_attr( $show_posts ); ?>">

    <?php
    $configured_tokens = get_option( 'apify_insta_tokens', array() );
    if ( empty( $configured_tokens ) && current_user_can( 'manage_options' ) ) :
    ?>
        <div class="apify-admin-notice-pill">
            <span class="dashicons dashicons-admin-generic"></span>
            <span><?php printf( __( 'Admin Notice: No Apify API token configured yet. <a href="%s">Add your API Key in Settings</a> to enable live profile extraction.', 'apify-instagram-extractor' ), esc_url( admin_url( 'admin.php?page=apify-instagram' ) ) ); ?></span>
        </div>
    <?php endif; ?>

    <?php if ( $show_search ) : ?>
        <!-- Search UI Card -->
        <div class="apify-search-card">
            <div class="apify-search-card-header">
                <div class="apify-search-icon-badge">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="white"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </div>
                <div>
                    <h3 class="apify-search-title"><?php esc_html_e( 'Instagram Profile Extractor', 'apify-instagram-extractor' ); ?></h3>
                    <p class="apify-search-desc"><?php esc_html_e( 'Search any public Instagram profile to view bio, followers, stats, and recent posts.', 'apify-instagram-extractor' ); ?></p>
                </div>
            </div>

            <form class="apify-frontend-search-form">
                <div class="apify-search-bar">
                    <span class="apify-search-prefix">@</span>
                    <input type="text" class="apify-search-input" value="<?php echo esc_attr( $default_user ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" required />
                    <button type="submit" class="apify-search-btn">
                        <span class="apify-btn-text"><?php echo esc_html( $button_text ); ?></span>
                        <span class="apify-btn-spinner" style="display: none;"></span>
                    </button>
                </div>

                <!-- Quick Sample Chips -->
                <div class="apify-sample-chips">
                    <span class="apify-chip-label"><?php esc_html_e( 'Try:', 'apify-instagram-extractor' ); ?></span>
                    <button type="button" class="apify-chip" data-handle="cristiano">@cristiano</button>
                    <button type="button" class="apify-chip" data-handle="nasa">@nasa</button>
                    <button type="button" class="apify-chip" data-handle="instagram">@instagram</button>
                    <button type="button" class="apify-chip" data-handle="leomessi">@leomessi</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Loading Skeleton Animation -->
    <div class="apify-frontend-loader" style="display: none;">
        <div class="apify-skeleton-card">
            <div class="apify-skeleton-header">
                <div class="apify-skeleton-avatar"></div>
                <div class="apify-skeleton-lines">
                    <div class="apify-skeleton-line apify-w-60"></div>
                    <div class="apify-skeleton-line apify-w-40"></div>
                    <div class="apify-skeleton-line apify-w-80"></div>
                </div>
            </div>
            <div class="apify-skeleton-posts">
                <div class="apify-skeleton-thumb"></div>
                <div class="apify-skeleton-thumb"></div>
                <div class="apify-skeleton-thumb"></div>
            </div>
            <div class="apify-loader-caption">
                <div class="apify-spinner-dots">
                    <span></span><span></span><span></span>
                </div>
                <span><?php esc_html_e( 'Connecting to Apify & extracting profile data...', 'apify-instagram-extractor' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Error Box -->
    <div class="apify-frontend-error" style="display: none;"></div>

    <!-- Extracted Profile Result -->
    <div class="apify-frontend-result">
        <?php
        if ( ! empty( $preloaded_profile ) ) {
            if ( is_wp_error( $preloaded_profile ) ) {
                echo '<div class="apify-frontend-error" style="display: block;">' . esc_html( $preloaded_profile->get_error_message() ) . '</div>';
            } else {
                echo $this->render_profile_card( $preloaded_profile, array(
                    'layout'     => $layout,
                    'theme'      => isset( $atts['theme'] ) ? $atts['theme'] : 'light',
                    'show_posts' => ( $show_posts === 'yes' ),
                ) );
            }
        }
        ?>
    </div>

</div>
