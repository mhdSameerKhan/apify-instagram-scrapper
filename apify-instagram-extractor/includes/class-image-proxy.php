<?php
/**
 * Image Proxy & Cache Handler
 *
 * Resolves Instagram CDN 403 Forbidden hotlinking blocks and expired token issues
 * by proxying and caching images locally on the WordPress server.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Apify_Insta_Image_Proxy {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_apify_insta_image_proxy', array( $this, 'handle_proxy_request' ) );
        add_action( 'wp_ajax_nopriv_apify_insta_image_proxy', array( $this, 'handle_proxy_request' ) );
    }

    /**
     * URL-safe Base64 encode
     *
     * @param string $data
     * @return string
     */
    public static function encode_url( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * URL-safe Base64 decode
     *
     * @param string $data
     * @return string
     */
    public static function decode_url( $data ) {
        $str = strtr( $data, '-_', '+/' );
        $pad = strlen( $str ) % 4;
        if ( $pad ) {
            $str .= str_repeat( '=', 4 - $pad );
        }
        $decoded = base64_decode( $str, true );
        return ( false !== $decoded ) ? $decoded : '';
    }

    /**
     * Check if a URL belongs to verified Instagram/Meta CDN or Apify domains
     *
     * @param string $url
     * @return bool
     */
    public static function is_valid_instagram_cdn_url( $url ) {
        if ( empty( $url ) || ! is_string( $url ) ) {
            return false;
        }

        if ( ! preg_match( '#^https?://#i', $url ) ) {
            return false;
        }

        $host = parse_url( $url, PHP_URL_HOST );
        if ( empty( $host ) ) {
            return false;
        }

        $host = strtolower( $host );
        $allowed_domains = array(
            'cdninstagram.com',
            'fbcdn.net',
            'instagram.com',
            'meta.com',
            'facebook.com',
            'fbsbx.com',
            'akamaihd.net',
            'apify.com',
            'apifycontent.com',
            'cloudfront.net',
            'amazonaws.com',
        );

        foreach ( $allowed_domains as $domain ) {
            if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
                return true;
            }
        }

        return (bool) apply_filters( 'apify_insta_is_valid_cdn_url', false, $url, $host );
    }

    /**
     * Get cache filesystem path and public URL info for a given media URL
     *
     * @param string $url
     * @return array
     */
    public static function get_cache_info( $url ) {
        $cache_key  = 'apify_img_' . md5( $url );
        $upload_dir = wp_upload_dir();
        $cache_dir  = $upload_dir['basedir'] . '/apify-insta-cache';
        $cache_base = $upload_dir['baseurl'] . '/apify-insta-cache';
        $cache_file = $cache_dir . '/' . $cache_key . '.jpg';
        $file_url   = $cache_base . '/' . $cache_key . '.jpg';

        $exists = file_exists( $cache_file ) && ( filemtime( $cache_file ) > ( time() - 14 * DAY_IN_SECONDS ) );

        return array(
            'key'    => $cache_key,
            'dir'    => $cache_dir,
            'file'   => $cache_file,
            'url'    => $file_url,
            'exists' => $exists,
        );
    }

    /**
     * Get the proxy or direct cached URL for a given image
     *
     * @param string $url
     * @return string
     */
    public static function get_proxy_url( $url ) {
        if ( empty( $url ) || ! is_string( $url ) ) {
            return '';
        }

        // Return untouched if already a data URI or proxy URL
        if ( strpos( $url, 'data:image' ) === 0 || strpos( $url, 'admin-ajax.php' ) !== false ) {
            return $url;
        }

        // Check if settings disable image proxying
        $settings      = get_option( 'apify_insta_settings', array() );
        $proxy_enabled = ! isset( $settings['proxy_images'] ) || ! empty( $settings['proxy_images'] );
        if ( ! $proxy_enabled ) {
            return $url;
        }

        // If already cached locally on disk, serve the static file URL directly for maximum speed
        $info = self::get_cache_info( $url );
        if ( $info['exists'] ) {
            return $info['url'];
        }

        // Otherwise route through WordPress AJAX image proxy
        return add_query_arg(
            array(
                'action' => 'apify_insta_image_proxy',
                'img'    => self::encode_url( $url ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Get direct download attachment URL for a given image
     *
     * @param string $url
     * @param string $filename
     * @return string
     */
    public static function get_download_url( $url, $filename = 'instagram-media.jpg' ) {
        if ( empty( $url ) || ! is_string( $url ) ) {
            return '';
        }

        return add_query_arg(
            array(
                'action'   => 'apify_insta_image_proxy',
                'img'      => self::encode_url( $url ),
                'download' => '1',
                'filename' => rawurlencode( $filename ),
            ),
            admin_url( 'admin-ajax.php' )
        );
    }

    /**
     * Pre-cache an image locally to disk
     *
     * @param string $url
     * @return string Cached local file URL or proxy URL
     */
    public static function cache_image_locally( $url ) {
        if ( empty( $url ) || ! self::is_valid_instagram_cdn_url( $url ) ) {
            return $url;
        }

        $info = self::get_cache_info( $url );
        if ( $info['exists'] ) {
            return $info['url'];
        }

        if ( ! wp_mkdir_p( $info['dir'] ) || ! is_writable( $info['dir'] ) ) {
            return self::get_proxy_url( $url );
        }

        $args = array(
            'timeout'   => 15,
            'sslverify' => apply_filters( 'https_ssl_verify', true ),
            'headers'   => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Referer'    => 'https://www.instagram.com/',
                'Accept'     => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            ),
        );

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            $args['sslverify'] = false;
            $response = wp_remote_get( $url, $args );
        }

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            if ( ! empty( $body ) ) {
                @file_put_contents( $info['file'], $body );
                return $info['url'];
            }
        }

        return self::get_proxy_url( $url );
    }

    /**
     * Handle incoming image proxy request
     */
    public function handle_proxy_request() {
        $url = '';

        if ( isset( $_GET['img'] ) && ! empty( $_GET['img'] ) ) {
            $url = self::decode_url( sanitize_text_field( wp_unslash( $_GET['img'] ) ) );
        } elseif ( isset( $_GET['url'] ) && ! empty( $_GET['url'] ) ) {
            $url = rawurldecode( wp_unslash( $_GET['url'] ) );
        }

        if ( empty( $url ) || ! self::is_valid_instagram_cdn_url( $url ) ) {
            $this->serve_fallback_svg( 200, 'Invalid image URL' );
            exit;
        }

        $cache_info = self::get_cache_info( $url );
        $cache_key  = $cache_info['key'];
        $cache_dir  = $cache_info['dir'];
        $cache_file = $cache_info['file'];

        // 1. Check local filesystem cache first
        if ( file_exists( $cache_file ) && ( filemtime( $cache_file ) > ( time() - 14 * DAY_IN_SECONDS ) ) ) {
            $this->serve_file( $cache_file, 'image/jpeg' );
            exit;
        }

        // 2. Check transient cache
        $cached_transient = get_transient( $cache_key );
        if ( ! empty( $cached_transient ) && isset( $cached_transient['data'], $cached_transient['mime'] ) ) {
            $this->serve_binary( base64_decode( $cached_transient['data'] ), $cached_transient['mime'] );
            exit;
        }

        // 3. Fetch image server-side with Instagram Referer header to bypass 403 hotlinking block
        $args = array(
            'timeout'    => 20,
            'sslverify'  => apply_filters( 'https_ssl_verify', true ),
            'headers'    => array(
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept'          => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Referer'         => 'https://www.instagram.com/',
            ),
        );

        $response = wp_remote_get( $url, $args );

        // If SSL certificate verification fails on local/shared setups, retry once with sslverify=false
        if ( is_wp_error( $response ) ) {
            $args['sslverify'] = false;
            $response = wp_remote_get( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            $this->serve_fallback_svg( 200, 'Image Unavailable' );
            exit;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            $this->serve_fallback_svg( 200, 'Image Unavailable' );
            exit;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            $this->serve_fallback_svg( 200, 'Empty Image' );
            exit;
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( empty( $content_type ) || ( false === strpos( $content_type, 'image/' ) && false === strpos( $content_type, 'video/' ) ) ) {
            $content_type = 'image/jpeg';
        }

        // 4. Save to filesystem cache
        if ( wp_mkdir_p( $cache_dir ) && is_writable( $cache_dir ) ) {
            @file_put_contents( $cache_file, $body );
        } else {
            // Store in transient if filesystem is not writable
            set_transient( $cache_key, array(
                'mime' => $content_type,
                'data' => base64_encode( $body ),
            ), 14 * DAY_IN_SECONDS );
        }

        $this->serve_binary( $body, $content_type );
        exit;
    }

    /**
     * Serve a file from disk with conditional caching headers
     *
     * @param string $file_path
     * @param string $content_type
     */
    private function serve_file( $file_path, $content_type = 'image/jpeg' ) {
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        $last_modified = filemtime( $file_path );
        $etag          = md5( $file_path . $last_modified );

        if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) {
            status_header( 304 );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Length: ' . filesize( $file_path ) );
        header( 'Cache-Control: public, max-age=1209600, stale-while-revalidate=86400' );
        header( 'ETag: ' . $etag );
        header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Cross-Origin-Resource-Policy: cross-origin' );

        if ( ! empty( $_GET['download'] ) ) {
            $dl_name = ! empty( $_GET['filename'] ) ? sanitize_file_name( wp_unslash( $_GET['filename'] ) ) : 'instagram-image.jpg';
            if ( ! preg_match( '/\.(jpg|jpeg|png|webp|mp4)$/i', $dl_name ) ) {
                $dl_name .= '.jpg';
            }
            header( 'Content-Disposition: attachment; filename="' . $dl_name . '"' );
        }

        readfile( $file_path );
        exit;
    }

    /**
     * Serve binary image data
     *
     * @param string $body
     * @param string $content_type
     */
    private function serve_binary( $body, $content_type = 'image/jpeg' ) {
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        $etag = md5( $body );

        if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $etag ) {
            status_header( 304 );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Length: ' . strlen( $body ) );
        header( 'Cache-Control: public, max-age=1209600, stale-while-revalidate=86400' );
        header( 'ETag: ' . $etag );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Cross-Origin-Resource-Policy: cross-origin' );

        if ( ! empty( $_GET['download'] ) ) {
            $dl_name = ! empty( $_GET['filename'] ) ? sanitize_file_name( wp_unslash( $_GET['filename'] ) ) : 'instagram-image.jpg';
            if ( ! preg_match( '/\.(jpg|jpeg|png|webp|mp4)$/i', $dl_name ) ) {
                $dl_name .= '.jpg';
            }
            header( 'Content-Disposition: attachment; filename="' . $dl_name . '"' );
        }

        echo $body;
        exit;
    }

    /**
     * Serve clean SVG fallback placeholder with HTTP 200 so layout never displays broken image box
     *
     * @param int    $status_code
     * @param string $message
     */
    public function serve_fallback_svg( $status_code = 200, $message = 'Instagram Media' ) {
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }

        status_header( $status_code );
        header( 'Content-Type: image/svg+xml' );
        header( 'Cache-Control: public, max-age=86400' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Cross-Origin-Resource-Policy: cross-origin' );

        $clean_msg = esc_html( $message );
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400">
            <defs>
                <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#18181b"/>
                    <stop offset="100%" stop-color="#09090b"/>
                </linearGradient>
            </defs>
            <rect width="100%" height="100%" fill="url(#bg)"/>
            <g transform="translate(152, 135)" fill="#71717a">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" transform="scale(4)"/>
            </g>
            <text x="200" y="270" text-anchor="middle" fill="#a1a1aa" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="14" font-weight="500">' . $clean_msg . '</text>
        </svg>';

        echo $svg;
        exit;
    }

    /**
     * Generate fallback SVG Data URI for profile avatars with initial letter
     *
     * @param string $name
     * @return string
     */
    public static function get_avatar_fallback_data_uri( $name = '' ) {
        $letter = ! empty( $name ) ? strtoupper( mb_substr( trim( $name ), 0, 1 ) ) : 'IG';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100"><defs><linearGradient id="avbg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#f09433"/><stop offset="50%" stop-color="#dc2743"/><stop offset="100%" stop-color="#bc1888"/></linearGradient></defs><rect width="100" height="100" fill="url(#avbg)"/><text x="50" y="63" text-anchor="middle" fill="#ffffff" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="38" font-weight="700">' . esc_html( $letter ) . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    /**
     * Generate fallback SVG Data URI for post photos
     *
     * @param string $label
     * @return string
     */
    public static function get_fallback_svg_data_uri( $label = 'Instagram Media' ) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400"><defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#1e293b"/><stop offset="100%" stop-color="#0f172a"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/><g transform="translate(152, 135)" fill="#64748b"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" transform="scale(4)"/></g><text x="200" y="270" text-anchor="middle" fill="#94a3b8" font-family="-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif" font-size="14" font-weight="500">' . esc_html( $label ) . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    /**
     * Flush cached images directory
     *
     * @return int Number of deleted cache files
     */
    public static function flush_image_cache() {
        $upload_dir = wp_upload_dir();
        $cache_dir  = $upload_dir['basedir'] . '/apify-insta-cache';

        if ( ! is_dir( $cache_dir ) ) {
            return 0;
        }

        $files = glob( $cache_dir . '/*' );
        $count = 0;
        if ( is_array( $files ) ) {
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    @unlink( $file );
                    $count++;
                }
            }
        }

        return $count;
    }
}
