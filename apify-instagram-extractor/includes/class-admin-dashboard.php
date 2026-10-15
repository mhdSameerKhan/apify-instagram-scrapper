<?php
/**
 * Admin Dashboard & Settings Class
 *
 * Provides a clean WordPress Admin experience focused on multi-token management,
 * 1,800-limit meters, alternating strategy controls, transient cache controls,
 * extraction audit logs, and frontend shortcode documentation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Apify_Insta_Admin_Dashboard {

    private $scraper;
    private $token_manager;
    private $cache;
    private $page_slug = 'apify-instagram';

    public function __construct( Apify_Insta_Scraper $scraper, Apify_Insta_Token_Manager $token_manager, Apify_Insta_Cache_Helper $cache ) {
        $this->scraper       = $scraper;
        $this->token_manager = $token_manager;
        $this->cache         = $cache;

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_apify_insta_test_token', array( $this, 'ajax_test_token' ) );
        add_action( 'wp_ajax_apify_insta_save_token', array( $this, 'ajax_save_token' ) );
        add_action( 'wp_ajax_apify_insta_delete_token', array( $this, 'ajax_delete_token' ) );
        add_action( 'wp_ajax_apify_insta_toggle_token', array( $this, 'ajax_toggle_token' ) );
        add_action( 'wp_ajax_apify_insta_reset_usage', array( $this, 'ajax_reset_usage' ) );
        add_action( 'wp_ajax_apify_insta_save_settings', array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_apify_insta_flush_cache', array( $this, 'ajax_flush_cache' ) );
        add_action( 'wp_ajax_apify_insta_clear_logs', array( $this, 'ajax_clear_logs' ) );
    }

    /**
     * Register Admin Menu Page
     */
    public function register_menu() {
        add_menu_page(
            __( 'Apify Instagram Extractor', 'apify-instagram-extractor' ),
            __( 'Instagram Extractor', 'apify-instagram-extractor' ),
            'manage_options',
            $this->page_slug,
            array( $this, 'render_admin_page' ),
            'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="#f09433"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>' ),
            28
        );
    }

    /**
     * Enqueue Admin Styles and Scripts
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, $this->page_slug ) === false ) {
            return;
        }

        wp_enqueue_style(
            'apify-insta-admin-css',
            APIFY_INSTA_URL . 'assets/css/admin.css',
            array(),
            APIFY_INSTA_VERSION
        );

        wp_enqueue_script(
            'apify-insta-admin-js',
            APIFY_INSTA_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            APIFY_INSTA_VERSION,
            true
        );

        wp_localize_script( 'apify-insta-admin-js', 'apifyInstaAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'apify_insta_admin_nonce' ),
            'strings'  => array(
                'testing'        => __( 'Testing Apify token...', 'apify-instagram-extractor' ),
                'saving'         => __( 'Saving...', 'apify-instagram-extractor' ),
                'confirmDelete'  => __( 'Are you sure you want to delete this API token?', 'apify-instagram-extractor' ),
                'confirmReset'   => __( 'Reset monthly request count for this token?', 'apify-instagram-extractor' ),
                'confirmFlush'   => __( 'Are you sure you want to flush all cached Instagram profiles?', 'apify-instagram-extractor' ),
                'confirmClearLog'=> __( 'Are you sure you want to clear all extraction logs?', 'apify-instagram-extractor' ),
                'copied'         => __( 'Copied to clipboard!', 'apify-instagram-extractor' ),
            ),
        ) );
    }

    /**
     * Render Full Admin Page with Tabs
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access.', 'apify-instagram-extractor' ) );
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'tokens';
        $tokens     = $this->token_manager->get_tokens();
        $settings   = get_option( 'apify_insta_settings', array() );
        $month      = $this->token_manager->get_current_month();
        $cached_cnt = $this->cache->get_cached_count();
        $logs       = $this->scraper->get_logs();

        ?>
        <div class="wrap apify-insta-admin-wrap">
            <header class="apify-insta-header">
                <div class="apify-insta-header-title">
                    <div class="apify-insta-logo-badge">
                        <svg viewBox="0 0 24 24" width="28" height="28" fill="white"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </div>
                    <div>
                        <h1><?php esc_html_e( 'Apify Instagram Extractor', 'apify-instagram-extractor' ); ?> <span class="apify-badge-ver">v<?php echo esc_html( APIFY_INSTA_VERSION ); ?></span></h1>
                        <p class="apify-subtitle"><?php esc_html_e( 'Enterprise-grade Instagram data extraction with multi-token rotation and free tier limit protection.', 'apify-instagram-extractor' ); ?></p>
                    </div>
                </div>

                <div class="apify-header-quick-stats">
                    <div class="apify-stat-pill">
                        <span class="apify-pill-label"><?php esc_html_e( 'Active API Keys', 'apify-instagram-extractor' ); ?>:</span>
                        <span class="apify-pill-val"><?php echo count( $this->token_manager->get_available_tokens() ); ?> / <?php echo count( $tokens ); ?></span>
                    </div>
                    <div class="apify-stat-pill">
                        <span class="apify-pill-label"><?php esc_html_e( 'Cached Profiles', 'apify-instagram-extractor' ); ?>:</span>
                        <span class="apify-pill-val"><?php echo intval( $cached_cnt ); ?></span>
                    </div>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <nav class="nav-tab-wrapper apify-tab-nav">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug . '&tab=tokens' ) ); ?>" class="nav-tab <?php echo $active_tab === 'tokens' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-network"></span> <?php esc_html_e( 'API Keys & Rotation', 'apify-instagram-extractor' ); ?>
                    <span class="apify-tab-counter"><?php echo count( $tokens ); ?></span>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug . '&tab=settings' ) ); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Settings & Cache', 'apify-instagram-extractor' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug . '&tab=logs' ) ); ?>" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Extraction Logs', 'apify-instagram-extractor' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->page_slug . '&tab=docs' ) ); ?>" class="nav-tab <?php echo $active_tab === 'docs' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Website Shortcode Guide', 'apify-instagram-extractor' ); ?>
                </a>
            </nav>

            <main class="apify-insta-tab-content">
                <?php
                switch ( $active_tab ) {
                    case 'settings':
                        $this->render_settings_tab( $settings, $cached_cnt );
                        break;
                    case 'logs':
                        $this->render_logs_tab( $logs );
                        break;
                    case 'docs':
                        $this->render_docs_tab();
                        break;
                    case 'tokens':
                    default:
                        $this->render_tokens_tab( $tokens, $month, $settings );
                        break;
                }
                ?>
            </main>
        </div>
        <?php
    }

    /**
     * Tab 1: API Keys & Rotation
     */
    private function render_tokens_tab( $tokens, $month, $settings ) {
        $strategy = isset( $settings['rotation_strategy'] ) ? $settings['rotation_strategy'] : 'round_robin';
        ?>
        <div class="apify-grid-2col">
            <!-- Token List & Meters -->
            <div class="apify-card">
                <div class="apify-card-header flex-between">
                    <div>
                        <h2><?php esc_html_e( 'Configured Apify API Keys', 'apify-instagram-extractor' ); ?></h2>
                        <p><?php esc_html_e( 'Each free Apify account has 1,800 free requests per month. Add multiple API tokens to rotate alternately and prevent exhausting your monthly limit.', 'apify-instagram-extractor' ); ?></p>
                    </div>
                    <button type="button" class="button button-primary" id="apify-btn-add-token-modal">
                        <span class="dashicons dashicons-plus-alt2"></span> <?php echo empty( $tokens ) ? esc_html__( 'Add Primary API Key', 'apify-instagram-extractor' ) : esc_html__( 'Add Another API Token', 'apify-instagram-extractor' ); ?>
                    </button>
                </div>

                <div class="apify-tokens-list">
                    <?php if ( empty( $tokens ) ) : ?>
                        <div class="apify-empty-state apify-empty-state-actionable">
                            <div class="apify-empty-icon-wrap">
                                <span class="dashicons dashicons-admin-network"></span>
                            </div>
                            <h3><?php esc_html_e( 'Connect Your Primary Apify API Key', 'apify-instagram-extractor' ); ?></h3>
                            <p><?php esc_html_e( 'Add your Apify API token below to start extracting Instagram profiles and tracking your 1,800 monthly requests.', 'apify-instagram-extractor' ); ?></p>
                            
                            <form id="apify-quick-add-token-form" class="apify-quick-add-box">
                                <div class="apify-quick-input-wrap">
                                    <input type="text" name="token" id="apify-quick-token-input" class="regular-text" placeholder="apify_api_..." required />
                                    <button type="submit" class="button button-primary">
                                        <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save & Connect Primary Key', 'apify-instagram-extractor' ); ?>
                                    </button>
                                </div>
                                <small class="description"><?php esc_html_e( 'Find your token at: Apify Console > Settings > Integrations > API tokens.', 'apify-instagram-extractor' ); ?></small>
                            </form>
                        </div>
                    <?php else : ?>
                        <?php foreach ( $tokens as $t ) :
                            $limit      = isset( $t['limit'] ) ? intval( $t['limit'] ) : 1800;
                            $used_month = $this->token_manager->get_token_monthly_usage( $t, $month );
                            $percent    = min( 100, round( ( $used_month / $limit ) * 100, 1 ) );
                            $is_active  = isset( $t['status'] ) && $t['status'] === 'active';
                            $masked_tok = substr( $t['token'], 0, 14 ) . '...' . substr( $t['token'], -6 );

                            $meter_class = 'meter-green';
                            if ( $percent >= 90 ) {
                                $meter_class = 'meter-red';
                            } elseif ( $percent >= 70 ) {
                                $meter_class = 'meter-orange';
                            }
                        ?>
                            <div class="apify-token-card <?php echo ! $is_active ? 'apify-token-paused' : ''; ?>" data-id="<?php echo esc_attr( $t['id'] ); ?>">
                                <div class="apify-token-header">
                                    <div class="apify-token-info">
                                        <h3 class="apify-token-title">
                                            <?php echo esc_html( $t['label'] ); ?>
                                            <?php if ( ! empty( $t['account_name'] ) ) : ?>
                                                <span class="apify-account-badge">@<?php echo esc_html( $t['account_name'] ); ?></span>
                                            <?php endif; ?>
                                        </h3>
                                        <div class="apify-token-key-display">
                                            <code><?php echo esc_html( $masked_tok ); ?></code>
                                            <button type="button" class="apify-btn-reveal-token" data-token="<?php echo esc_attr( $t['token'] ); ?>" title="<?php esc_attr_e( 'Copy full token', 'apify-instagram-extractor' ); ?>">
                                                <span class="dashicons dashicons-admin-page"></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="apify-token-status">
                                        <span class="apify-status-badge <?php echo $is_active ? 'status-active' : 'status-paused'; ?>">
                                            <?php echo $is_active ? esc_html__( 'Active', 'apify-instagram-extractor' ) : esc_html__( 'Paused', 'apify-instagram-extractor' ); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Usage Meter -->
                                <div class="apify-usage-meter-box">
                                    <div class="apify-usage-label-row">
                                        <span><?php printf( esc_html__( 'Usage for %s: %s / %s requests', 'apify-instagram-extractor' ), '<strong>' . esc_html( $month ) . '</strong>', '<strong>' . number_format( $used_month ) . '</strong>', number_format( $limit ) ); ?></span>
                                        <span class="apify-usage-percent <?php echo esc_attr( $meter_class ); ?>"><?php echo esc_html( $percent ); ?>%</span>
                                    </div>
                                    <div class="apify-progress-track">
                                        <div class="apify-progress-fill <?php echo esc_attr( $meter_class ); ?>" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
                                    </div>
                                    <div class="apify-usage-footer">
                                        <small><?php printf( esc_html__( 'Remaining: %s free requests this month (Total all-time: %s)', 'apify-instagram-extractor' ), '<strong>' . number_format( max( 0, $limit - $used_month ) ) . '</strong>', number_format( isset( $t['total_usage'] ) ? $t['total_usage'] : 0 ) ); ?></small>
                                    </div>
                                </div>

                                <!-- Token Actions Toolbar -->
                                <div class="apify-token-actions">
                                    <button type="button" class="button apify-btn-test-token" data-token="<?php echo esc_attr( $t['token'] ); ?>">
                                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Test Connection', 'apify-instagram-extractor' ); ?>
                                    </button>
                                    <button type="button" class="button apify-btn-toggle-token" data-id="<?php echo esc_attr( $t['id'] ); ?>">
                                        <span class="dashicons dashicons-controls-pause"></span> <?php echo $is_active ? esc_html__( 'Pause', 'apify-instagram-extractor' ) : esc_html__( 'Resume', 'apify-instagram-extractor' ); ?>
                                    </button>
                                    <button type="button" class="button apify-btn-reset-usage" data-id="<?php echo esc_attr( $t['id'] ); ?>">
                                        <span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Reset Month Count', 'apify-instagram-extractor' ); ?>
                                    </button>
                                    <button type="button" class="button button-link-delete apify-btn-delete-token" data-id="<?php echo esc_attr( $t['id'] ); ?>">
                                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'apify-instagram-extractor' ); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rotation Strategy & Info -->
            <div class="apify-card">
                <div class="apify-card-header">
                    <h2><?php esc_html_e( 'Smart Rotation Strategy', 'apify-instagram-extractor' ); ?></h2>
                    <p><?php esc_html_e( 'Select how the plugin balances extraction calls between your configured Apify accounts.', 'apify-instagram-extractor' ); ?></p>
                </div>

                <form id="apify-strategy-form">
                    <div class="apify-radio-card-group">
                        <label class="apify-radio-card <?php echo $strategy === 'round_robin' ? 'selected' : ''; ?>">
                            <input type="radio" name="rotation_strategy" value="round_robin" <?php checked( $strategy, 'round_robin' ); ?> />
                            <div class="apify-radio-card-body">
                                <strong><?php esc_html_e( 'Alternating / Round-Robin (Recommended)', 'apify-instagram-extractor' ); ?></strong>
                                <p><?php esc_html_e( 'Evenly rotates to the next API key on every request (Key 1 → Key 2 → Key 1 → Key 2). This prevents either key from reaching the 1,800 monthly limit quickly.', 'apify-instagram-extractor' ); ?></p>
                            </div>
                        </label>

                        <label class="apify-radio-card <?php echo $strategy === 'least_used' ? 'selected' : ''; ?>">
                            <input type="radio" name="rotation_strategy" value="least_used" <?php checked( $strategy, 'least_used' ); ?> />
                            <div class="apify-radio-card-body">
                                <strong><?php esc_html_e( 'Least Used This Month', 'apify-instagram-extractor' ); ?></strong>
                                <p><?php esc_html_e( 'Dynamically routes each request to the token with the lowest monthly usage count so keys balance automatically.', 'apify-instagram-extractor' ); ?></p>
                            </div>
                        </label>

                        <label class="apify-radio-card <?php echo $strategy === 'sequential' ? 'selected' : ''; ?>">
                            <input type="radio" name="rotation_strategy" value="sequential" <?php checked( $strategy, 'sequential' ); ?> />
                            <div class="apify-radio-card-body">
                                <strong><?php esc_html_e( 'Sequential & Failover', 'apify-instagram-extractor' ); ?></strong>
                                <p><?php esc_html_e( 'Uses Key 1 until limit reached or rate limited, then automatically switches to Key 2.', 'apify-instagram-extractor' ); ?></p>
                            </div>
                        </label>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Save Strategy', 'apify-instagram-extractor' ); ?>
                        </button>
                    </div>
                </form>

                <div class="apify-info-box" style="margin-top: 30px;">
                    <h4><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Automatic Failover Protection', 'apify-instagram-extractor' ); ?></h4>
                    <p><?php esc_html_e( 'If an API token encounters HTTP 429 (Rate Limit) or HTTP 403, the plugin automatically retries the request using your other token immediately so your visitors never experience an error.', 'apify-instagram-extractor' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Add Token Modal -->
        <div id="apify-modal-add-token" class="apify-modal" style="display: none;">
            <div class="apify-modal-content">
                <div class="apify-modal-header">
                    <h3><?php esc_html_e( 'Add Apify API Token', 'apify-instagram-extractor' ); ?></h3>
                    <button type="button" class="apify-modal-close">&times;</button>
                </div>
                <form id="apify-form-add-token">
                    <div class="apify-modal-body">
                        <div class="apify-form-group">
                            <label for="apify-new-token-str"><?php esc_html_e( 'Apify API Token *', 'apify-instagram-extractor' ); ?></label>
                            <input type="text" id="apify-new-token-str" name="token" class="regular-text" placeholder="apify_api_..." required />
                            <small class="description"><?php esc_html_e( 'Found in Apify Console > Settings > Integrations > API tokens.', 'apify-instagram-extractor' ); ?></small>
                        </div>

                        <div class="apify-form-group">
                            <label for="apify-new-token-label"><?php esc_html_e( 'Account Label (Optional)', 'apify-instagram-extractor' ); ?></label>
                            <input type="text" id="apify-new-token-label" name="label" class="regular-text" placeholder="e.g. Backup Apify Account 2" />
                        </div>

                        <div class="apify-form-group">
                            <label for="apify-new-token-limit"><?php esc_html_e( 'Monthly Free Request Limit', 'apify-instagram-extractor' ); ?></label>
                            <input type="number" id="apify-new-token-limit" name="limit" value="1800" min="1" max="100000" class="small-text" />
                            <small class="description"><?php esc_html_e( 'Standard free Apify accounts offer 1,800 requests per month.', 'apify-instagram-extractor' ); ?></small>
                        </div>
                    </div>
                    <div class="apify-modal-footer">
                        <button type="button" class="button apify-modal-close"><?php esc_html_e( 'Cancel', 'apify-instagram-extractor' ); ?></button>
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save & Verify Token', 'apify-instagram-extractor' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Tab 2: Settings & Cache
     */
    private function render_settings_tab( $settings, $cached_cnt ) {
        $cache_duration = isset( $settings['cache_duration'] ) ? intval( $settings['cache_duration'] ) : 86400;
        $default_posts  = isset( $settings['default_posts'] ) ? intval( $settings['default_posts'] ) : 6;
        $timeout        = isset( $settings['request_timeout'] ) ? intval( $settings['request_timeout'] ) : 75;
        $include_about  = ! empty( $settings['include_about'] );
        $public_search  = isset( $settings['public_search'] ) ? $settings['public_search'] : 'public';
        $proxy_images   = ! isset( $settings['proxy_images'] ) || ! empty( $settings['proxy_images'] );
        ?>
        <div class="apify-grid-2col">
            <div class="apify-card">
                <div class="apify-card-header">
                    <h2><?php esc_html_e( 'Scraping & General Configuration', 'apify-instagram-extractor' ); ?></h2>
                </div>

                <form id="apify-general-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="apify-cache-duration"><?php esc_html_e( 'Profile Cache Expiration', 'apify-instagram-extractor' ); ?></label></th>
                            <td>
                                <select id="apify-cache-duration" name="cache_duration">
                                    <option value="0" <?php selected( $cache_duration, 0 ); ?>><?php esc_html_e( 'Disabled (Always Fetch Live - Burns Quota)', 'apify-instagram-extractor' ); ?></option>
                                    <option value="3600" <?php selected( $cache_duration, 3600 ); ?>><?php esc_html_e( '1 Hour', 'apify-instagram-extractor' ); ?></option>
                                    <option value="21600" <?php selected( $cache_duration, 21600 ); ?>><?php esc_html_e( '6 Hours', 'apify-instagram-extractor' ); ?></option>
                                    <option value="43200" <?php selected( $cache_duration, 43200 ); ?>><?php esc_html_e( '12 Hours', 'apify-instagram-extractor' ); ?></option>
                                    <option value="86400" <?php selected( $cache_duration, 86400 ); ?>><?php esc_html_e( '24 Hours (Recommended)', 'apify-instagram-extractor' ); ?></option>
                                    <option value="604800" <?php selected( $cache_duration, 604800 ); ?>><?php esc_html_e( '7 Days', 'apify-instagram-extractor' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Caching extracted profiles via WordPress transients saves your free 1,800 monthly requests from repeat views.', 'apify-instagram-extractor' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="apify-default-posts"><?php esc_html_e( 'Default Posts to Fetch', 'apify-instagram-extractor' ); ?></label></th>
                            <td>
                                <input type="number" id="apify-default-posts" name="default_posts" value="<?php echo esc_attr( $default_posts ); ?>" min="0" max="24" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Number of recent posts to include with each profile extraction (0 to 24).', 'apify-instagram-extractor' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="apify-request-timeout"><?php esc_html_e( 'Request Timeout (seconds)', 'apify-instagram-extractor' ); ?></label></th>
                            <td>
                                <input type="number" id="apify-request-timeout" name="request_timeout" value="<?php echo esc_attr( $timeout ); ?>" min="30" max="180" class="small-text" />
                                <p class="description"><?php esc_html_e( 'Maximum wait time for Apify synchronous actor execution (recommended: 60-90s).', 'apify-instagram-extractor' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e( 'Extra Information', 'apify-instagram-extractor' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="include_about" value="1" <?php checked( $include_about, true ); ?> />
                                    <?php esc_html_e( 'Include "About Account" metadata (Account join date, former usernames if accessible)', 'apify-instagram-extractor' ); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e( 'Instagram Image Proxy & CDN Protection', 'apify-instagram-extractor' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="proxy_images" value="1" <?php checked( $proxy_images, true ); ?> />
                                    <?php esc_html_e( 'Proxy and cache Instagram photos locally on your server (Bypasses 403 Forbidden hotlinking blocks and expired image links)', 'apify-instagram-extractor' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Recommended: ON. Resolves broken image icons caused by Meta/Instagram cross-origin hotlinking blocks and browser privacy extensions.', 'apify-instagram-extractor' ); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="apify-public-search"><?php esc_html_e( 'Frontend Search Widget Access', 'apify-instagram-extractor' ); ?></label></th>
                            <td>
                                <select id="apify-public-search" name="public_search">
                                    <option value="public" <?php selected( $public_search, 'public' ); ?>><?php esc_html_e( 'Public (All Site Visitors)', 'apify-instagram-extractor' ); ?></option>
                                    <option value="logged_in" <?php selected( $public_search, 'logged_in' ); ?>><?php esc_html_e( 'Logged-In Users Only', 'apify-instagram-extractor' ); ?></option>
                                    <option value="admin_only" <?php selected( $public_search, 'admin_only' ); ?>><?php esc_html_e( 'Administrators Only', 'apify-instagram-extractor' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Controls who can trigger live extractions from the frontend [instagram_profile] shortcode.', 'apify-instagram-extractor' ); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'apify-instagram-extractor' ); ?></button>
                    </p>
                </form>
            </div>

            <!-- Cache Controls Card -->
            <div class="apify-card">
                <div class="apify-card-header">
                    <h2><?php esc_html_e( 'Transient Cache Management', 'apify-instagram-extractor' ); ?></h2>
                </div>

                <div class="apify-cache-status-box">
                    <div class="apify-stat-big">
                        <span class="apify-stat-number" id="apify-cached-count"><?php echo intval( $cached_cnt ); ?></span>
                        <span class="apify-stat-label"><?php esc_html_e( 'Profiles Cached in Memory', 'apify-instagram-extractor' ); ?></span>
                    </div>

                    <p><?php esc_html_e( 'Cached profiles respond instantly in milliseconds and consume 0 Apify API credits.', 'apify-instagram-extractor' ); ?></p>

                    <button type="button" class="button button-secondary" id="apify-btn-flush-cache">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Flush All Cached Profiles', 'apify-instagram-extractor' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Tab 3: Extraction Logs & History
     */
    private function render_logs_tab( $logs ) {
        ?>
        <div class="apify-card">
            <div class="apify-card-header flex-between">
                <div>
                    <h2><?php esc_html_e( 'Recent Extraction Logs', 'apify-instagram-extractor' ); ?></h2>
                    <p><?php esc_html_e( 'Audit trail showing the last 100 extractions, which API key was used, response time, and cache status.', 'apify-instagram-extractor' ); ?></p>
                </div>
                <?php if ( ! empty( $logs ) ) : ?>
                    <button type="button" class="button" id="apify-btn-clear-logs">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear History', 'apify-instagram-extractor' ); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="widefat striped apify-table-logs">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Time', 'apify-instagram-extractor' ); ?></th>
                            <th><?php esc_html_e( 'Instagram Handle', 'apify-instagram-extractor' ); ?></th>
                            <th><?php esc_html_e( 'API Key / Source', 'apify-instagram-extractor' ); ?></th>
                            <th><?php esc_html_e( 'Execution Time', 'apify-instagram-extractor' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'apify-instagram-extractor' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $logs ) ) : ?>
                            <tr>
                                <td colspan="5" class="text-center"><?php esc_html_e( 'No extraction requests logged yet.', 'apify-instagram-extractor' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $logs as $l ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $l['date_human'] ); ?></td>
                                    <td>
                                        <strong><a href="https://www.instagram.com/<?php echo esc_attr( $l['username'] ); ?>/" target="_blank" rel="noopener noreferrer">@<?php echo esc_html( $l['username'] ); ?></a></strong>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $l['is_cached'] ) ) : ?>
                                            <span class="apify-tag apify-tag-green"><?php esc_html_e( 'Cached Memory', 'apify-instagram-extractor' ); ?></span>
                                        <?php else : ?>
                                            <span class="apify-tag apify-tag-purple"><?php echo esc_html( $l['token_label'] ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $l['time_ms'] ); ?> ms</td>
                                    <td>
                                        <?php if ( $l['status'] === 'success' ) : ?>
                                            <span class="apify-badge-success"><?php esc_html_e( 'Success', 'apify-instagram-extractor' ); ?></span>
                                        <?php else : ?>
                                            <span class="apify-badge-error" title="<?php echo esc_attr( isset( $l['error'] ) ? $l['error'] : '' ); ?>"><?php esc_html_e( 'Failed', 'apify-instagram-extractor' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Tab 4: Frontend Shortcode Guide & Documentation
     */
    private function render_docs_tab() {
        ?>
        <div class="apify-grid-2col">
            <!-- Shortcodes Guide -->
            <div class="apify-card">
                <div class="apify-card-header">
                    <h2><?php esc_html_e( 'How to Add to Your Website Page', 'apify-instagram-extractor' ); ?></h2>
                    <p><?php esc_html_e( 'Simply add this shortcode to any WordPress page, post, or widget area to display the full search UI and profile results.', 'apify-instagram-extractor' ); ?></p>
                </div>

                <div class="apify-doc-item">
                    <h4>1. Live Search & Profile Extractor (Recommended)</h4>
                    <p><?php esc_html_e( 'Displays the full interactive search bar where visitors can enter any username and see all profile details, stats, bio, and recent posts live on your website:', 'apify-instagram-extractor' ); ?></p>
                    <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 8px; font-size: 15px; font-weight: 700; color: #0f172a;">
                        <code>[instagram_profile]</code>
                    </div>

                    <h5 style="margin-top: 20px;"><?php esc_html_e( 'Optional: Pre-load a Specific Profile', 'apify-instagram-extractor' ); ?></h5>
                    <p><?php esc_html_e( 'If you want a profile to already be loaded when the page opens, while still allowing visitors to search others:', 'apify-instagram-extractor' ); ?></p>
                    <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; color: #0f172a;">
                        <code>[instagram_profile username="cristiano" posts_count="6"]</code>
                    </div>

                    <h5 style="margin-top: 20px;"><?php esc_html_e( 'Available Shortcode Attributes:', 'apify-instagram-extractor' ); ?></h5>
                    <ul class="apify-doc-list">
                        <li><code>posts_count</code>: Number of recent posts to display (e.g. <code>posts_count="6"</code> or <code>12</code>)</li>
                        <li><code>theme</code>: <code>light</code> (default), <code>dark</code>, or <code>gradient</code></li>
                        <li><code>show_posts</code>: <code>yes</code> (default) or <code>no</code></li>
                        <li><code>placeholder</code>: Custom search input text</li>
                    </ul>
                </div>
            </div>

            <!-- PHP & REST API Guide -->
            <div class="apify-card">
                <div class="apify-card-header">
                    <h2><?php esc_html_e( 'Developer PHP & REST APIs', 'apify-instagram-extractor' ); ?></h2>
                </div>

                <div class="apify-doc-item">
                    <h4>PHP Template Integration</h4>
                    <p><?php esc_html_e( 'Use directly inside your WordPress theme files (e.g. page.php or single.php):', 'apify-instagram-extractor' ); ?></p>
                    <pre class="apify-code-box">&lt;?php echo do_shortcode( '[instagram_profile]' ); ?&gt;</pre>

                    <h4 style="margin-top: 20px;">Direct PHP Function Call</h4>
                    <pre class="apify-code-box">$profile = apify_insta_get_profile( 'cristiano' );

if ( ! is_wp_error( $profile ) ) {
    echo $profile['fullName'];
    echo $profile['followersFormatted']; // "640M"
    echo $profile['biographyHtml'];
}</pre>
                </div>

                <div class="apify-doc-item" style="margin-top: 20px;">
                    <h4>REST API Endpoint</h4>
                    <code>GET <?php echo esc_url( rest_url( 'apify-insta/v1/profile/cristiano' ) ); ?></code>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Test Token Connection
     */
    public function ajax_test_token() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

        $test = $this->token_manager->test_token( $token );

        if ( is_wp_error( $test ) ) {
            wp_send_json_error( array( 'message' => $test->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Connection Successful! Account: %s (%s). Tier: %s. Credits: $%s', 'apify-instagram-extractor' ),
                $test['name'],
                $test['username'],
                $test['tier'],
                $test['credits']
            ),
            'data'    => $test,
        ) );
    }

    /**
     * AJAX: Save / Add New Token
     */
    public function ajax_save_token() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $label = isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '';
        $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 1800;

        $result = $this->token_manager->add_token( $token, $label, $limit );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => __( 'API Token successfully added and verified!', 'apify-instagram-extractor' ),
            'token'   => $result,
        ) );
    }

    /**
     * AJAX: Delete Token
     */
    public function ajax_delete_token() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
        $deleted = $this->token_manager->delete_token( $id );

        if ( $deleted ) {
            wp_send_json_success( array( 'message' => __( 'Token deleted successfully.', 'apify-instagram-extractor' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to delete token.', 'apify-instagram-extractor' ) ) );
        }
    }

    /**
     * AJAX: Toggle Active / Paused Status
     */
    public function ajax_toggle_token() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
        $status = $this->token_manager->toggle_status( $id );

        if ( $status ) {
            wp_send_json_success( array( 'status' => $status ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Could not update token status.', 'apify-instagram-extractor' ) ) );
        }
    }

    /**
     * AJAX: Reset Monthly Usage
     */
    public function ajax_reset_usage() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
        $this->token_manager->reset_usage( $id );

        wp_send_json_success( array( 'message' => __( 'Usage count reset for current month.', 'apify-instagram-extractor' ) ) );
    }

    /**
     * AJAX: Save General Settings & Strategy
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $settings = get_option( 'apify_insta_settings', array() );

        if ( isset( $_POST['rotation_strategy'] ) ) {
            $settings['rotation_strategy'] = sanitize_text_field( $_POST['rotation_strategy'] );
        }
        if ( isset( $_POST['cache_duration'] ) ) {
            $settings['cache_duration'] = intval( $_POST['cache_duration'] );
        }
        if ( isset( $_POST['default_posts'] ) ) {
            $settings['default_posts'] = intval( $_POST['default_posts'] );
        }
        if ( isset( $_POST['request_timeout'] ) ) {
            $settings['request_timeout'] = intval( $_POST['request_timeout'] );
        }
        if ( isset( $_POST['public_search'] ) ) {
            $settings['public_search'] = sanitize_text_field( $_POST['public_search'] );
        }

        $settings['include_about'] = ! empty( $_POST['include_about'] );
        $settings['proxy_images']  = ! empty( $_POST['proxy_images'] );

        update_option( 'apify_insta_settings', $settings );

        wp_send_json_success( array( 'message' => __( 'Settings updated successfully.', 'apify-instagram-extractor' ) ) );
    }

    /**
     * AJAX: Flush Transient Cache
     */
    public function ajax_flush_cache() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $cleared = $this->cache->flush_all();
        $images_cleared = class_exists( 'Apify_Insta_Image_Proxy' ) ? Apify_Insta_Image_Proxy::flush_image_cache() : 0;

        wp_send_json_success( array(
            'cleared' => $cleared,
            'message' => sprintf( __( 'Flushed %1$d cached Instagram profiles and %2$d cached local images.', 'apify-instagram-extractor' ), $cleared, $images_cleared ),
        ) );
    }

    /**
     * AJAX: Clear History Logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer( 'apify_insta_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'apify-instagram-extractor' ) ) );
        }

        $this->scraper->clear_logs();

        wp_send_json_success( array( 'message' => __( 'Extraction logs cleared.', 'apify-instagram-extractor' ) ) );
    }
}
