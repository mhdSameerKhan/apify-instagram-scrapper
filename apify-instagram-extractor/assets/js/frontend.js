/**
 * Apify Instagram Extractor - Frontend JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initFrontendExtractors();
        initImageErrorHandling();
        initLightbox();
    });

    function initFrontendExtractors() {
        $('.apify-frontend-wrapper').each(function() {
            var $wrapper  = $(this);
            var $form     = $wrapper.find('.apify-frontend-search-form');
            var $input    = $wrapper.find('.apify-search-input');
            var $btn      = $wrapper.find('.apify-search-btn');
            var $btnText  = $wrapper.find('.apify-btn-text');
            var $btnSpin  = $wrapper.find('.apify-btn-spinner');
            var $loader   = $wrapper.find('.apify-frontend-loader');
            var $error    = $wrapper.find('.apify-frontend-error');
            var $result   = $wrapper.find('.apify-frontend-result');
            var $chips    = $wrapper.find('.apify-chip');

            var theme     = $wrapper.data('theme') || 'light';
            var layout    = $wrapper.data('layout') || 'card';
            var posts     = $wrapper.data('posts') || 6;
            var showPosts = $wrapper.data('show-posts') || 'yes';

            // Sample quick chips click
            $chips.on('click', function(e) {
                e.preventDefault();
                var handle = $(this).data('handle');
                $input.val(handle);
                $form.trigger('submit');
            });

            // Form Submit / Extract Action
            $form.on('submit', function(e) {
                e.preventDefault();

                var username = $.trim($input.val());

                if (!username) {
                    $error.text(apifyInstaFrontend.strings.enterHandle).slideDown(200);
                    return;
                }

                $error.hide().empty();
                $result.hide();
                $loader.slideDown(200);
                $btn.prop('disabled', true);
                $btnText.text('Extracting...');
                $btnSpin.show();

                $.ajax({
                    url: apifyInstaFrontend.ajax_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'apify_insta_frontend_search',
                        nonce: apifyInstaFrontend.nonce,
                        username: username,
                        theme: theme,
                        layout: layout,
                        posts_count: posts,
                        show_posts: showPosts
                    },
                    success: function(res) {
                        $loader.hide();
                        $btn.prop('disabled', false);
                        $btnText.text('Extract Profile');
                        $btnSpin.hide();

                        if (res.success && res.data) {
                            $result.html(res.data.html).fadeIn(300);

                            // Smooth scroll if needed
                            $('html, body').animate({
                                scrollTop: $result.offset().top - 80
                            }, 400);
                        } else {
                            var msg = res.data && res.data.message ? res.data.message : apifyInstaFrontend.strings.error;
                            $error.html('<strong>Notice:</strong> ' + msg).slideDown(200);
                        }
                    },
                    error: function(xhr, status, err) {
                        $loader.hide();
                        $btn.prop('disabled', false);
                        $btnText.text('Extract Profile');
                        $btnSpin.hide();
                        $error.html('<strong>Connection Error:</strong> ' + (err || 'Request timed out. Please try again.')).slideDown(200);
                    }
                });
            });
        });
    }

    function initImageErrorHandling() {
        // Fallback for avatar images if they fail to load
        $(document).on('error', '.apify-avatar, .apify-banner-avatar, .apify-compact-avatar', function() {
            var $img = $(this);
            if ($img.data('has-fallback')) return;
            $img.data('has-fallback', true);

            var alt = $img.attr('alt') || 'IG';
            var letter = alt.trim().charAt(0).toUpperCase() || 'I';
            var $badge = $('<div class="apify-avatar-fallback-badge">' + letter + '</div>');
            $img.replaceWith($badge);
        });

        // Fallback for post thumbnails if they fail to load
        $(document).on('error', '.apify-post-img-wrap img', function() {
            var $img = $(this);
            if ($img.data('has-fallback')) return;
            $img.data('has-fallback', true);

            var postSvg = 'data:image/svg+xml;base64,' + btoa('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400"><rect width="100%" height="100%" fill="#18181b"/><g transform="translate(152, 135)" fill="#64748b"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" transform="scale(4)"/></g><text x="200" y="270" text-anchor="middle" fill="#94a3b8" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="14" font-weight="500">Instagram Media</text></svg>');
            $img.attr('src', postSvg);
        });
    }

    /**
     * Fullscreen Post & Profile Picture Lightbox Modal with Instant Media Download
     */
    function initLightbox() {
        var activeThumbs = [];
        var currentIndex = -1;

        function getModal() {
            var $m = $('#apify-lightbox-modal');
            if ($m.length === 0) {
                var html = '<div id="apify-lightbox-modal" class="apify-fullscreen-modal" style="display:none;" role="dialog" aria-modal="true" tabindex="-1">' +
                    '<div class="apify-fs-backdrop"></div>' +
                    '<div class="apify-fs-container">' +
                        '<!-- Top Fullscreen Bar -->' +
                        '<div class="apify-fs-topbar">' +
                            '<div class="apify-fs-user">' +
                                '<img class="apify-lightbox-user-avatar" src="" alt="" />' +
                                '<div class="apify-fs-user-meta">' +
                                    '<span class="apify-lightbox-username"></span>' +
                                    '<span class="apify-lightbox-date"></span>' +
                                '</div>' +
                            '</div>' +
                            '<div class="apify-fs-center">' +
                                '<span class="apify-fs-counter"></span>' +
                            '</div>' +
                            '<div class="apify-fs-actions">' +
                                '<button type="button" class="apify-btn-download-media apify-fs-top-dl" data-url="" data-name="" title="Download Media">' +
                                    '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>' +
                                    '<span class="apify-dl-btn-text">Download</span>' +
                                '</button>' +
                                '<button type="button" class="apify-lightbox-close" aria-label="Close fullscreen modal" title="Close (Esc)">' +
                                    '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' +
                                '</button>' +
                            '</div>' +
                        '</div>' +

                        '<!-- Fullscreen Stage -->' +
                        '<div class="apify-fs-stage">' +
                            '<div class="apify-fs-media-wrap">' +
                                '<button type="button" class="apify-lightbox-nav apify-nav-prev" aria-label="Previous Post" title="Previous (Left Arrow)">' +
                                    '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>' +
                                '</button>' +
                                '<img class="apify-lightbox-img" src="" alt="Instagram Post Fullscreen" />' +
                                '<video class="apify-lightbox-video" controls playsinline loop style="display:none;"></video>' +
                                '<button type="button" class="apify-lightbox-nav apify-nav-next" aria-label="Next Post" title="Next (Right Arrow)">' +
                                    '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>' +
                                '</button>' +
                            '</div>' +

                            '<!-- Info / Caption Drawer -->' +
                            '<div class="apify-fs-drawer">' +
                                '<div class="apify-fs-drawer-header">' +
                                    '<div class="apify-fs-drawer-user">' +
                                        '<img class="apify-lightbox-user-avatar" src="" alt="" />' +
                                        '<div class="apify-fs-drawer-user-meta">' +
                                            '<h4 class="apify-lightbox-username"></h4>' +
                                            '<span class="apify-lightbox-date"></span>' +
                                        '</div>' +
                                    '</div>' +
                                    '<div class="apify-lightbox-stats">' +
                                        '<span class="apify-lb-stat apify-lb-likes" title="Likes">' +
                                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="#ef4444"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' +
                                            '<span class="apify-lb-likes-count"></span>' +
                                        '</span>' +
                                        '<span class="apify-lb-stat apify-lb-comments" title="Comments">' +
                                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="#38bdf8"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/></svg>' +
                                            '<span class="apify-lb-comments-count"></span>' +
                                        '</span>' +
                                    '</div>' +
                                '</div>' +

                                '<div class="apify-fs-caption-box">' +
                                    '<p class="apify-lightbox-caption"></p>' +
                                '</div>' +

                                '<div class="apify-fs-drawer-actions">' +
                                    '<button type="button" class="apify-btn-download-media apify-fs-drawer-dl" data-url="" data-name="">' +
                                        '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>' +
                                        '<span class="apify-dl-btn-text">Download Post</span>' +
                                    '</button>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
                $('body').append(html);
                $m = $('#apify-lightbox-modal');
            }
            return $m;
        }

        function triggerDownload(url, name, $btn, defaultText) {
            if (!url) return;
            $btn = $btn || $();
            var $txt = $btn.find('.apify-dl-btn-text, .apify-btn-dl-text');
            var originalText = $txt.length ? $txt.text() : (defaultText || 'Download');
            $btn.prop('disabled', true);
            if ($txt.length) $txt.text('Downloading...');

            function revertSuccess() {
                if ($txt.length) $txt.text('✓ Downloaded!');
                setTimeout(function() {
                    $btn.prop('disabled', false);
                    if ($txt.length) $txt.text(originalText);
                }, 1400);
            }

            function fallbackDirect() {
                var link = document.createElement('a');
                link.style.display = 'none';
                link.href = url;
                link.download = name;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                setTimeout(function() {
                    if (document.body.contains(link)) {
                        document.body.removeChild(link);
                    }
                    revertSuccess();
                }, 400);
            }

            // Attempt direct fetch blob download
            fetch(url)
                .then(function(res) {
                    if (!res.ok) throw new Error('Fetch failed');
                    return res.blob();
                })
                .then(function(blob) {
                    var blobUrl = window.URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.style.display = 'none';
                    link.href = blobUrl;
                    link.download = name;
                    document.body.appendChild(link);
                    link.click();
                    setTimeout(function() {
                        if (document.body.contains(link)) {
                            document.body.removeChild(link);
                        }
                        window.URL.revokeObjectURL(blobUrl);
                        revertSuccess();
                    }, 400);
                })
                .catch(function() {
                    // Fallback to proxy direct attachment URL
                    fallbackDirect();
                });
        }

        function showPostAtIndex(idx) {
            if (!activeThumbs.length || idx < 0 || idx >= activeThumbs.length) return;
            currentIndex = idx;
            var $thumb = $(activeThumbs[idx]);
            var $m = getModal();

            var imgUrl       = $thumb.data('img') || '';
            var downloadUrl  = $thumb.data('download-url') || imgUrl;
            var downloadName = $thumb.data('download-name') || 'instagram-post.jpg';
            var caption      = $thumb.data('caption') || '';
            var likes        = $thumb.data('likes') || '0';
            var comments     = $thumb.data('comments') || '0';
            var date         = $thumb.data('date') || '';
            var username     = $thumb.data('username') || '';
            var userAvatar   = $thumb.data('user-avatar') || '';
            var videoUrl     = $thumb.data('video-url') || '';

            $m.removeClass('apify-mode-avatar').addClass('apify-mode-post');

            var $img = $m.find('.apify-lightbox-img');
            var $vid = $m.find('.apify-lightbox-video');

            if (videoUrl) {
                $vid.attr('src', videoUrl).show();
                try { $vid[0].play(); } catch(e){}
                $img.hide();
                $m.find('.apify-dl-btn-text').text('Download Video');
            } else {
                $vid.hide().attr('src', '');
                $img.attr('src', imgUrl).show();
                $m.find('.apify-dl-btn-text').text('Download Post');
            }

            $m.find('.apify-lightbox-user-avatar').attr('src', userAvatar);
            $m.find('.apify-lightbox-username').text('@' + username);
            $m.find('.apify-lightbox-date').text(date);

            // Counter: Post X of Y
            $m.find('.apify-fs-counter').text((idx + 1) + ' / ' + activeThumbs.length);

            // Highlight hashtags in caption
            var formattedCap = caption ? caption.replace(/(#\w+)/g, '<span class="apify-hashtag">$1</span>') : 'No caption';
            $m.find('.apify-lightbox-caption').html(formattedCap);

            $m.find('.apify-lb-likes-count').text(likes);
            $m.find('.apify-lb-comments-count').text(comments);
            $m.find('.apify-lightbox-stats').show();

            $m.find('.apify-btn-download-media').data('url', downloadUrl).data('name', downloadName);

            if (activeThumbs.length > 1) {
                $m.find('.apify-lightbox-nav').show();
            } else {
                $m.find('.apify-lightbox-nav').hide();
            }

            $m.fadeIn(200);
            $('body').addClass('apify-modal-open');
        }

        function showAvatar(data) {
            var $m = getModal();
            $m.removeClass('apify-mode-post').addClass('apify-mode-avatar');

            var $img = $m.find('.apify-lightbox-img');
            var $vid = $m.find('.apify-lightbox-video');
            $vid.hide().attr('src', '');
            $img.attr('src', data.img).show();

            $m.find('.apify-lightbox-user-avatar').attr('src', data.img);
            $m.find('.apify-lightbox-username').text('@' + data.username);
            $m.find('.apify-lightbox-date').text(data.fullname || 'High-Resolution Profile Picture');

            $m.find('.apify-fs-counter').text('Profile Picture');

            $m.find('.apify-lightbox-caption').html('<strong>' + $('<div>').text(data.fullname).html() + '</strong><br><span style="color:#a1a1aa;margin-top:6px;display:inline-block;">High-Resolution Profile Picture</span>');
            $m.find('.apify-lightbox-stats').hide();
            $m.find('.apify-lightbox-nav').hide();

            $m.find('.apify-dl-btn-text').text('Download Profile Picture');
            $m.find('.apify-btn-download-media').data('url', data.downloadUrl).data('name', data.downloadName);

            $m.fadeIn(200);
            $('body').addClass('apify-modal-open');
        }

        function closeModal() {
            var $m = $('#apify-lightbox-modal');
            if ($m.length) {
                $m.fadeOut(200, function() {
                    $m.find('.apify-lightbox-video').attr('src', '');
                });
                $('body').removeClass('apify-modal-open');
            }
        }

        // Intercept any click on post thumbs, post links, or avatar triggers
        // PREVENTS OPENING INSTAGRAM ON ANY POST CLICK
        $(document).on('click', '.apify-post-thumb, .apify-post-link, .apify-avatar-trigger, .apify-lightbox-trigger', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $el = $(this);
            var isAvatar = $el.hasClass('apify-avatar-trigger') || $el.data('type') === 'avatar' || $el.closest('[data-type="avatar"]').length > 0;

            if (isAvatar) {
                var $av = $el.hasClass('apify-avatar-trigger') ? $el : $el.closest('.apify-avatar-trigger');
                showAvatar({
                    img: $av.data('img'),
                    downloadUrl: $av.data('download-url') || $av.data('img'),
                    downloadName: $av.data('download-name') || 'instagram_avatar_' + ($av.data('username') || 'profile') + '.jpg',
                    username: $av.data('username'),
                    fullname: $av.data('fullname'),
                    profileUrl: $av.data('profile-url')
                });
                return;
            }

            var $thumb = $el.hasClass('apify-post-thumb') ? $el : $el.closest('.apify-post-thumb');
            if (!$thumb.length) return;
            var $grid = $thumb.closest('.apify-posts-grid');
            activeThumbs = $grid.find('.apify-post-thumb').toArray();
            var idx = activeThumbs.indexOf($thumb[0]);
            showPostAtIndex(idx >= 0 ? idx : 0);
        });

        // Dedicated Download Avatar Button on Profile Cards & Banners
        $(document).on('click', '.apify-btn-download-avatar-direct', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var url = $btn.data('url');
            var name = $btn.data('name') || 'instagram_avatar.jpg';
            triggerDownload(url, name, $btn, 'Download DP');
        });

        // Close Handlers
        $(document).on('click', '.apify-lightbox-close', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModal();
        });

        $(document).on('click', '.apify-fs-backdrop', function(e) {
            closeModal();
        });

        // Navigation Controls
        $(document).on('click', '.apify-nav-prev', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (activeThumbs.length > 1) {
                var newIdx = (currentIndex - 1 + activeThumbs.length) % activeThumbs.length;
                showPostAtIndex(newIdx);
            }
        });

        $(document).on('click', '.apify-nav-next', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (activeThumbs.length > 1) {
                var newIdx = (currentIndex + 1) % activeThumbs.length;
                showPostAtIndex(newIdx);
            }
        });

        // Keyboard Controls
        $(document).on('keydown', function(e) {
            var $m = $('#apify-lightbox-modal');
            if ($m.is(':visible')) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    closeModal();
                } else if (e.key === 'ArrowLeft' || e.keyCode === 37) {
                    $('.apify-nav-prev:visible').trigger('click');
                } else if (e.key === 'ArrowRight' || e.keyCode === 39) {
                    $('.apify-nav-next:visible').trigger('click');
                }
            }
        });

        // Download Action from Modal Buttons
        $(document).on('click', '.apify-btn-download-media', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var url = $btn.data('url');
            var name = $btn.data('name') || 'instagram-download.jpg';
            triggerDownload(url, name, $btn, 'Download');
        });
    }

})(jQuery);
