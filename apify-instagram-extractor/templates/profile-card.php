<?php
/**
 * Profile Card Template (Standard Card)
 *
 * Variables available:
 * - $profile (array)
 * - $theme (string: 'light', 'dark', 'gradient')
 * - $show_posts (bool)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$theme_class = 'apify-theme-' . esc_attr( isset( $theme ) ? $theme : 'light' );
$posts       = isset( $profile['latestPosts'] ) && is_array( $profile['latestPosts'] ) ? $profile['latestPosts'] : array();
?>

<div class="apify-insta-card <?php echo esc_attr( $theme_class ); ?>">
    <!-- Card Header / Profile Info -->
    <div class="apify-card-inner">
        <div class="apify-profile-top">
            <div class="apify-avatar-wrap">
                <?php
                $avatar_raw       = ! empty( $profile['profilePicUrl'] ) ? $profile['profilePicUrl'] : '';
                $avatar_fallback  = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_avatar_fallback_data_uri( $profile['fullName'] ) : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjY2NjIj48Y2lyY2xlIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjwvc3ZnPg==';
                $avatar_url       = ! empty( $avatar_raw ) ? ( class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_proxy_url( $avatar_raw ) : $avatar_raw ) : $avatar_fallback;
                $avatar_download  = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::get_download_url( $avatar_raw, 'instagram_avatar_' . sanitize_title( $profile['username'] ) . '.jpg' ) : $avatar_url;
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
                         class="apify-avatar"
                         loading="lazy"
                         referrerpolicy="no-referrer"
                         crossorigin="anonymous"
                         onerror="if(this.src!=='<?php echo esc_js( $avatar_fallback ); ?>'){this.onerror=null;this.src='<?php echo esc_js( $avatar_fallback ); ?>';}" />
                    <span class="apify-avatar-zoom-badge" title="<?php esc_attr_e( 'View HD & Download DP', 'apify-instagram-extractor' ); ?>">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="white"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    </span>
                </a>
            </div>

            <div class="apify-profile-identity">
                <div class="apify-handle-row">
                    <div class="apify-handle-wrapper">
                        <h2 class="apify-username">
                            <a href="<?php echo esc_url( $profile['profileUrl'] ); ?>" target="_blank" rel="noopener noreferrer">
                                @<?php echo esc_html( $profile['username'] ); ?>
                            </a>
                        </h2>

                        <?php if ( ! empty( $profile['verified'] ) ) : ?>
                            <span class="apify-badge-verified" title="<?php esc_attr_e( 'Verified Account', 'apify-instagram-extractor' ); ?>">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="#0095f6"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1.9 14.7l-4.1-4.1 1.4-1.4 2.7 2.7 6.7-6.7 1.4 1.4-8.1 8.1z"/></svg>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="apify-profile-btn-group">
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
                            <?php esc_html_e( 'View on Instagram', 'apify-instagram-extractor' ); ?>
                        </a>
                    </div>
                </div>

                <div class="apify-fullname"><?php echo esc_html( $profile['fullName'] ); ?></div>

                <?php if ( ! empty( $profile['businessCategoryName'] ) ) : ?>
                    <div class="apify-category-tag"><?php echo esc_html( $profile['businessCategoryName'] ); ?></div>
                <?php endif; ?>

                <!-- Stats Bar -->
                <div class="apify-stats-bar">
                    <div class="apify-stat-col">
                        <span class="apify-stat-value"><?php echo esc_html( $profile['postsFormatted'] ); ?></span>
                        <span class="apify-stat-title"><?php esc_html_e( 'posts', 'apify-instagram-extractor' ); ?></span>
                    </div>
                    <div class="apify-stat-col">
                        <span class="apify-stat-value"><?php echo esc_html( $profile['followersFormatted'] ); ?></span>
                        <span class="apify-stat-title"><?php esc_html_e( 'followers', 'apify-instagram-extractor' ); ?></span>
                    </div>
                    <div class="apify-stat-col">
                        <span class="apify-stat-value"><?php echo esc_html( $profile['followsFormatted'] ); ?></span>
                        <span class="apify-stat-title"><?php esc_html_e( 'following', 'apify-instagram-extractor' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biography -->
        <?php if ( ! empty( $profile['biography'] ) ) : ?>
            <div class="apify-bio-box">
                <?php echo wp_kses_post( $profile['biographyHtml'] ); ?>
            </div>
        <?php endif; ?>

        <!-- External Website Link -->
        <?php if ( ! empty( $profile['externalUrl'] ) ) : ?>
            <div class="apify-link-box">
                <a href="<?php echo esc_url( $profile['externalUrl'] ); ?>" target="_blank" rel="noopener noreferrer nofollow" class="apify-website-link">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
                    <?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', $profile['externalUrl'] ) ); ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- Recent Posts Grid -->
        <?php if ( ! empty( $show_posts ) && ! empty( $posts ) ) : ?>
            <div class="apify-posts-section">
                <div class="apify-posts-header">
                    <span class="apify-posts-title"><?php esc_html_e( 'Recent Posts', 'apify-instagram-extractor' ); ?></span>
                </div>

                <div class="apify-posts-grid">
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
                                         alt="<?php echo esc_attr( $post_short_cap ); ?>"
                                         loading="lazy"
                                         referrerpolicy="no-referrer"
                                         crossorigin="anonymous"
                                         onerror="if(this.src!=='<?php echo esc_js( $post_fallback ); ?>'){this.onerror=null;this.src='<?php echo esc_js( $post_fallback ); ?>';}" />
                                    
                                    <?php if ( $is_video ) : ?>
                                        <span class="apify-post-badge-video" title="<?php esc_attr_e( 'Video / Reel', 'apify-instagram-extractor' ); ?>">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="white"><path d="M8 5v14l11-7z"/></svg>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Hover Overlay with Likes, Comments & Fullscreen prompt -->
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
            </div>
        <?php endif; ?>
    </div>
</div>
