<?php
/**
 * Banner Profile Template
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme_class = 'apify-theme-' . esc_attr( isset( $theme ) ? $theme : 'light' );
$posts       = isset( $profile['latestPosts'] ) && is_array( $profile['latestPosts'] ) ? $profile['latestPosts'] : array();
?>

<div class="apify-insta-banner <?php echo esc_attr( $theme_class ); ?>">
    <div class="apify-banner-header">
        <div class="apify-banner-left">
            <?php
            $avatar_raw      = ! empty( $profile['profilePicUrl'] ) ? $profile['profilePicUrl'] : '';
            $avatar_fallback = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_avatar_fallback_data_uri( $profile['fullName'] ) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjY2NjIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjwvc3ZnPg==';
            $avatar_url      = ! empty( $avatar_raw ) ? ( class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_proxy_url( $avatar_raw ) : $avatar_raw ) : $avatar_fallback;
            $avatar_download = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_download_url( $avatar_raw, 'instagram_avatar_' . sanitize_title( $profile['username'] ) . '.jpg' ) : $avatar_url;
            ?>
            <a href="javascript:void(0);"
               class="apify-lightbox-trigger apify-avatar-trigger"
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
                     class="apify-banner-avatar"
                     loading="lazy"
                     referrerpolicy="no-referrer"
                     crossorigin="anonymous"
                     onerror="if(this.src!=='<?php echo esc_js( $avatar_fallback ); ?>'){this.onerror=null;this.src='<?php echo esc_js( $avatar_fallback ); ?>';}" />
                <span class="apify-avatar-zoom-badge" title="<?php esc_attr_e( 'View HD & Download DP', 'apify-instagram-extractor' ); ?>">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="white"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                </span>
            </a>
            <div class="apify-banner-meta">
                <div class="apify-banner-name-wrap">
                    <span class="apify-banner-fullname"><?php echo esc_html( $profile['fullName'] ); ?></span>
                    <?php if ( ! empty( $profile['verified'] ) ) : ?>
                        <span class="apify-badge-verified-sm">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="#0095f6"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1.9 14.7l-4.1-4.1 1.4-1.4 2.7 2.7 6.7-6.7 1.4 1.4-8.1 8.1z"/></svg>
                        </span>
                    <?php endif; ?>
                </div>
                <a href="<?php echo esc_url( $profile['profileUrl'] ); ?>" target="_blank" rel="noopener noreferrer" class="apify-banner-handle">
                    @<?php echo esc_html( $profile['username'] ); ?>
                </a>
                <?php if ( ! empty( $profile['businessCategoryName'] ) ) : ?>
                    <span class="apify-banner-cat"><?php echo esc_html( $profile['businessCategoryName'] ); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="apify-banner-center">
            <div class="apify-banner-stat-box">
                <span class="apify-stat-value"><?php echo esc_html( $profile['followersFormatted'] ); ?></span>
                <span class="apify-stat-title"><?php esc_html_e( 'Followers', 'apify-instagram-extractor' ); ?></span>
            </div>
            <div class="apify-banner-stat-box">
                <span class="apify-stat-value"><?php echo esc_html( $profile['followsFormatted'] ); ?></span>
                <span class="apify-stat-title"><?php esc_html_e( 'Following', 'apify-instagram-extractor' ); ?></span>
            </div>
            <div class="apify-banner-stat-box">
                <span class="apify-stat-value"><?php echo esc_html( $profile['postsFormatted'] ); ?></span>
                <span class="apify-stat-title"><?php esc_html_e( 'Posts', 'apify-instagram-extractor' ); ?></span>
            </div>
        </div>

        <div class="apify-banner-right">
            <!-- Dedicated Download Profile Picture Button -->
            <button type="button"
                    class="apify-btn-download-avatar-direct"
                    data-url="<?php echo esc_url( $avatar_download ); ?>"
                    data-name="<?php echo esc_attr( 'instagram_avatar_' . sanitize_title( $profile['username'] ) . '.jpg' ); ?>"
                    title="<?php esc_attr_e( 'Download Profile Picture (HD)', 'apify-instagram-extractor' ); ?>">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                <span class="apify-btn-dl-text"><?php esc_html_e( 'Download DP', 'apify-instagram-extractor' ); ?></span>
            </button>

            <a href="<?php echo esc_url( $profile['profileUrl'] ); ?>" target="_blank" rel="noopener noreferrer" class="apify-btn-follow">
                <?php esc_html_e( 'Follow on Instagram', 'apify-instagram-extractor' ); ?>
            </a>
        </div>
    </div>

    <?php if ( ! empty( $profile['biography'] ) ) : ?>
        <div class="apify-banner-bio">
            <?php echo wp_kses_post( $profile['biographyHtml'] ); ?>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $show_posts ) && ! empty( $posts ) ) : ?>
        <div class="apify-posts-grid apify-banner-grid">
            <?php foreach ( $posts as $post ) :
                $raw_post_img   = isset( $post['displayUrl'] ) ? $post['displayUrl'] : '';
                $post_fallback  = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_fallback_svg_data_uri( 'Instagram Post' ) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjY2NjIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjwvc3ZnPg==';
                $post_img_url   = ! empty( $raw_post_img ) ? ( class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_proxy_url( $raw_post_img ) : $raw_post_img ) : $post_fallback;
                $post_caption   = ! empty( $post['caption'] ) ? $post['caption'] : '';
                $post_short_cap = ! empty( $post['caption'] ) ? wp_trim_words( $post['caption'], 8, '...' ) : $profile['username'];
                $post_shortcode = ! empty( $post['shortcode'] ) ? $post['shortcode'] : ( ! empty( $post['id'] ) ? $post['id'] : wp_generate_password( 6, false ) );
                $video_url      = ! empty( $post['videoUrl'] ) ? $post['videoUrl'] : '';
                $is_video       = ! empty( $video_url ) || ( isset( $post['type'] ) && $post['type'] === 'Video' );
                $dl_ext         = ( $is_video && ! empty( $video_url ) ) ? '.mp4' : '.jpg';
                $dl_file_url    = ( $is_video && ! empty( $video_url ) ) ? $video_url : $raw_post_img;
                $post_download  = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_download_url( $dl_file_url, 'instagram_' . sanitize_title( $profile['username'] ) . '_' . $post_shortcode . $dl_ext ) : ( ! empty( $dl_file_url ) ? $dl_file_url : $post_img_url );
            ?>
                <div class="apify-post-thumb apify-lightbox-trigger"
                     data-type="post"
                     data-img="<?php echo esc_url( $post_img_url ); ?>"
                     data-download-url="<?php echo esc_url( $post_download ); ?>"
                     data-download-name="<?php echo esc_attr( 'instagram_' . sanitize_title( $profile['username'] ) . '_' . $post_shortcode . $dl_ext ); ?>"
                     data-caption="<?php echo esc_attr( $post_caption ); ?>"
                     data-likes="<?php echo esc_attr( $post['likesFormatted'] ); ?>"
                     data-comments="<?php echo esc_attr( $post['commentsFormatted'] ); ?>"
                     data-date="<?php echo esc_attr( ! empty( $post['dateHuman'] ) ? $post['dateHuman'] : '' ); ?>"
                     data-post-url="<?php echo esc_url( $post['url'] ); ?>"
                     data-username="<?php echo esc_attr( $profile['username'] ); ?>"
                     data-user-avatar="<?php echo esc_url( $avatar_url ); ?>"
                     data-video-url="<?php echo esc_url( $video_url ); ?>"
                     role="button"
                     tabindex="0"
                     aria-label="<?php esc_attr_e( 'View post in fullscreen modal and download', 'apify-instagram-extractor' ); ?>">
                    <a href="javascript:void(0);" class="apify-post-link" tabindex="-1" onclick="return false;">
                        <div class="apify-post-img-wrap">
                            <img src="<?php echo esc_url( $post_img_url ); ?>"
                                 alt="<?php echo esc_attr( $profile['username'] ); ?>"
                                 loading="lazy"
                                 referrerpolicy="no-referrer"
                                 crossorigin="anonymous"
                                 onerror="if(this.src!=='<?php echo esc_js( $post_fallback ); ?>'){this.onerror=null;this.src='<?php echo esc_js( $post_fallback ); ?>';}" />
                            
                            <?php if ( $is_video ) : ?>
                                <span class="apify-post-badge-video" title="<?php esc_attr_e( 'Video / Reel', 'apify-instagram-extractor' ); ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            <?php endif; ?>

                            <div class="apify-post-overlay">
                                <div class="apify-overlay-stats-row">
                                    <span class="apify-overlay-stat">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                        <?php echo esc_html( $post['likesFormatted'] ); ?>
                                    </span>
                                    <span class="apify-overlay-stat">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>
                                        <?php echo esc_html( $post['commentsFormatted'] ); ?>
                                    </span>
                                </div>
                                <div class="apify-overlay-cta">
                                    <span class="apify-overlay-badge-action">
                                        <svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                                        <?php esc_html_e( 'View & Download', 'apify-instagram-extractor' ); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
