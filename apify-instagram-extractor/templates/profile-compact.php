<?php
/**
 * Compact Profile Card Template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme_class = 'apify-theme-' . esc_attr( isset( $theme ) ? $theme : 'light' );
?>

<div class="apify-insta-compact <?php echo esc_attr( $theme_class ); ?>">
    <div class="apify-compact-inner">
        <?php
        $avatar_raw      = ! empty( $profile['profilePicUrl'] ) ? $profile['profilePicUrl'] : '';
        $avatar_fallback = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_avatar_fallback_data_uri( $profile['fullName'] ) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjY2NjIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjwvc3ZnPg==';
        $avatar_url      = ! empty( $avatar_raw ) ? ( class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_proxy_url( $avatar_raw ) : $avatar_raw ) : $avatar_fallback;
        $avatar_download = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_download_url( $avatar_raw, 'instagram_avatar_' . sanitize_title( $profile['username'] ) . '.jpg' ) : $avatar_url;
        ?>
        <a href="javascript:void(0);"
           class="apify-compact-avatar-link apify-lightbox-trigger apify-avatar-trigger"
           data-type="avatar"
           data-img="<?php echo esc_url( $avatar_url ); ?>"
           data-download-url="<?php echo esc_url( $avatar_download ); ?>"
           data-download-name="<?php echo esc_attr( 'instagram_avatar_' . sanitize_title( $profile['username'] ) . '.jpg' ); ?>"
           data-username="<?php echo esc_attr( $profile['username'] ); ?>"
           data-fullname="<?php echo esc_attr( $profile['fullName'] ); ?>"
           data-profile-url="<?php echo esc_url( $profile['profileUrl'] ); ?>"
           role="button"
           tabindex="0"
           title="<?php esc_attr_e( 'Click to view full screen & download profile picture', 'apify-instagram-extractor' ); ?>">
            <img src="<?php echo esc_url( $avatar_url ); ?>"
                 alt="<?php echo esc_attr( $profile['fullName'] ); ?>"
                 class="apify-compact-avatar"
                 loading="lazy"
                 referrerpolicy="no-referrer"
                 crossorigin="anonymous"
                 onerror="if(this.src!=='<?php echo esc_js( $avatar_fallback ); ?>'){this.onerror=null;this.src='<?php echo esc_js( $avatar_fallback ); ?>';}" />
        </a>

        <div class="apify-compact-info">
            <div class="apify-compact-name-row">
                <a href="<?php echo esc_url( $profile['profileUrl'] ); ?>" target="_blank" rel="noopener noreferrer" class="apify-compact-handle">
                    @<?php echo esc_html( $profile['username'] ); ?>
                </a>
                <?php if ( ! empty( $profile['verified'] ) ) : ?>
                    <span class="apify-badge-verified-sm">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="#0095f6"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1.9 14.7l-4.1-4.1 1.4-1.4 2.7 2.7 6.7-6.7 1.4 1.4-8.1 8.1z"/></svg>
                    </span>
                <?php endif; ?>
            </div>

            <div class="apify-compact-stats">
                <span><strong><?php echo esc_html( $profile['followersFormatted'] ); ?></strong> <?php esc_html_e( 'followers', 'apify-instagram-extractor' ); ?></span>
                <span>&bull;</span>
                <span><strong><?php echo esc_html( $profile['postsFormatted'] ); ?></strong> <?php esc_html_e( 'posts', 'apify-instagram-extractor' ); ?></span>
            </div>
        </div>

        <div class="apify-compact-actions">
            <!-- Dedicated Download Profile Picture Button -->
            <button type="button"
                    class="apify-btn-download-avatar-direct apify-compact-dl-btn"
                    data-url="<?php echo esc_url( $avatar_download ); ?>"
                    data-name="<?php echo esc_attr( 'instagram_avatar_' . sanitize_title( $profile['username'] ) . '.jpg' ); ?>"
                    title="<?php esc_attr_e( 'Download Profile Picture (HD)', 'apify-instagram-extractor' ); ?>">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                <span><?php esc_html_e( 'DP', 'apify-instagram-extractor' ); ?></span>
            </button>

            <a href="<?php echo esc_url( $profile['profileUrl'] ); ?>" target="_blank" rel="noopener noreferrer" class="apify-compact-btn">
                <?php esc_html_e( 'Follow', 'apify-instagram-extractor' ); ?>
            </a>
        </div>
    </div>
</div>
