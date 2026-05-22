<?php
/**
 * Admin Settings Panel
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AITWC_Admin_Settings', false)) :

class AITWC_Admin_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_aitwc_batch_translate', array($this, 'ajax_batch_translate'));
        add_action('wp_ajax_aitwc_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_aitwc_test_api', array($this, 'ajax_test_api'));
    }


    public function add_menu_page() {
        add_menu_page(
            __('AI Translator', 'ai-translator-wc'),
            __('AI Translator', 'ai-translator-wc'),
            'manage_options',
            'aitwc-settings',
            array($this, 'render_settings_page'),
            'dashicons-translation',
            56
        );

        add_submenu_page(
            'aitwc-settings',
            __('Settings', 'ai-translator-wc'),
            __('Settings', 'ai-translator-wc'),
            'manage_options',
            'aitwc-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'aitwc-settings',
            __('Batch Translate', 'ai-translator-wc'),
            __('Batch Translate', 'ai-translator-wc'),
            'manage_options',
            'aitwc-batch',
            array($this, 'render_batch_page')
        );

        add_submenu_page(
            'aitwc-settings',
            __('Statistics', 'ai-translator-wc'),
            __('Statistics', 'ai-translator-wc'),
            'manage_options',
            'aitwc-stats',
            array($this, 'render_stats_page')
        );
    }


    public function register_settings() {
        register_setting('aitwc_settings', 'aitwc_ai_provider');
        register_setting('aitwc_settings', 'aitwc_gemini_api_key');
        register_setting('aitwc_settings', 'aitwc_gemini_model');
        register_setting('aitwc_settings', 'aitwc_deepseek_api_key');
        register_setting('aitwc_settings', 'aitwc_deepseek_model');
        register_setting('aitwc_settings', 'aitwc_openai_api_key');
        register_setting('aitwc_settings', 'aitwc_openai_model');
        register_setting('aitwc_settings', 'aitwc_claude_api_key');
        register_setting('aitwc_settings', 'aitwc_claude_model');
        register_setting('aitwc_settings', 'aitwc_source_language');
        register_setting('aitwc_settings', 'aitwc_target_languages');
        register_setting('aitwc_settings', 'aitwc_switcher_position');
        register_setting('aitwc_settings', 'aitwc_menu_integration');
        register_setting('aitwc_settings', 'aitwc_translate_products');
        register_setting('aitwc_settings', 'aitwc_translate_pages');
        register_setting('aitwc_settings', 'aitwc_translate_posts');
        register_setting('aitwc_settings', 'aitwc_seo_urls');
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'aitwc') === false) {
            return;
        }
        wp_enqueue_style('aitwc-admin', AITWC_PLUGIN_URL . 'assets/css/admin-settings.css', array(), AITWC_VERSION);
        wp_enqueue_script('aitwc-admin', AITWC_PLUGIN_URL . 'assets/js/admin-settings.js', array('jquery'), AITWC_VERSION, true);
        wp_localize_script('aitwc-admin', 'aitwc_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('aitwc_admin_nonce'),
        ));
    }


    public function render_settings_page() {
        $providers = array(
            'gemini'   => 'Google Gemini',
            'deepseek' => 'DeepSeek',
            'openai'   => 'OpenAI (GPT)',
            'claude'   => 'Anthropic Claude',
        );
        $languages = AI_Translator_WooCommerce::get_supported_languages();
        $current_provider = get_option('aitwc_ai_provider', 'gemini');
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $source_language = get_option('aitwc_source_language', 'en');
        ?>
        <div class="wrap aitwc-settings-wrap">
            <h1><?php _e('AI Translator Settings', 'ai-translator-wc'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('aitwc_settings'); ?>
                
                <div class="aitwc-settings-section">
                    <h2><?php _e('AI Provider Configuration', 'ai-translator-wc'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Active Provider', 'ai-translator-wc'); ?></th>
                            <td>
                                <select name="aitwc_ai_provider" id="aitwc_ai_provider">
                                    <?php foreach ($providers as $key => $name) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($current_provider, $key); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>


                    <!-- Gemini Settings -->
                    <div class="aitwc-provider-settings" data-provider="gemini">
                        <h3>Google Gemini</h3>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('API Key', 'ai-translator-wc'); ?></th>
                                <td>
                                    <input type="password" name="aitwc_gemini_api_key" class="regular-text"
                                        value="<?php echo esc_attr(get_option('aitwc_gemini_api_key', '')); ?>">
                                    <button type="button" class="button aitwc-test-api" data-provider="gemini">
                                        <?php _e('Test Connection', 'ai-translator-wc'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Model', 'ai-translator-wc'); ?></th>
                                <td>
                                    <select name="aitwc_gemini_model">
                                        <option value="gemini-pro" <?php selected(get_option('aitwc_gemini_model', 'gemini-pro'), 'gemini-pro'); ?>>Gemini Pro</option>
                                        <option value="gemini-1.5-pro" <?php selected(get_option('aitwc_gemini_model', ''), 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                                        <option value="gemini-1.5-flash" <?php selected(get_option('aitwc_gemini_model', ''), 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>


                    <!-- DeepSeek Settings -->
                    <div class="aitwc-provider-settings" data-provider="deepseek">
                        <h3>DeepSeek</h3>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('API Key', 'ai-translator-wc'); ?></th>
                                <td>
                                    <input type="password" name="aitwc_deepseek_api_key" class="regular-text"
                                        value="<?php echo esc_attr(get_option('aitwc_deepseek_api_key', '')); ?>">
                                    <button type="button" class="button aitwc-test-api" data-provider="deepseek">
                                        <?php _e('Test Connection', 'ai-translator-wc'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Model', 'ai-translator-wc'); ?></th>
                                <td>
                                    <select name="aitwc_deepseek_model">
                                        <option value="deepseek-chat" <?php selected(get_option('aitwc_deepseek_model', 'deepseek-chat'), 'deepseek-chat'); ?>>DeepSeek Chat</option>
                                        <option value="deepseek-reasoner" <?php selected(get_option('aitwc_deepseek_model', ''), 'deepseek-reasoner'); ?>>DeepSeek Reasoner</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- OpenAI Settings -->
                    <div class="aitwc-provider-settings" data-provider="openai">
                        <h3>OpenAI</h3>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('API Key', 'ai-translator-wc'); ?></th>
                                <td>
                                    <input type="password" name="aitwc_openai_api_key" class="regular-text"
                                        value="<?php echo esc_attr(get_option('aitwc_openai_api_key', '')); ?>">
                                    <button type="button" class="button aitwc-test-api" data-provider="openai">
                                        <?php _e('Test Connection', 'ai-translator-wc'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Model', 'ai-translator-wc'); ?></th>
                                <td>
                                    <select name="aitwc_openai_model">
                                        <option value="gpt-4o-mini" <?php selected(get_option('aitwc_openai_model', 'gpt-4o-mini'), 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
                                        <option value="gpt-4o" <?php selected(get_option('aitwc_openai_model', ''), 'gpt-4o'); ?>>GPT-4o</option>
                                        <option value="gpt-4-turbo" <?php selected(get_option('aitwc_openai_model', ''), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>


                    <!-- Claude Settings -->
                    <div class="aitwc-provider-settings" data-provider="claude">
                        <h3>Anthropic Claude</h3>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('API Key', 'ai-translator-wc'); ?></th>
                                <td>
                                    <input type="password" name="aitwc_claude_api_key" class="regular-text"
                                        value="<?php echo esc_attr(get_option('aitwc_claude_api_key', '')); ?>">
                                    <button type="button" class="button aitwc-test-api" data-provider="claude">
                                        <?php _e('Test Connection', 'ai-translator-wc'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <th><?php _e('Model', 'ai-translator-wc'); ?></th>
                                <td>
                                    <select name="aitwc_claude_model">
                                        <option value="claude-3-haiku-20240307" <?php selected(get_option('aitwc_claude_model', 'claude-3-haiku-20240307'), 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku</option>
                                        <option value="claude-3-sonnet-20240229" <?php selected(get_option('aitwc_claude_model', ''), 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet</option>
                                        <option value="claude-3-5-sonnet-20241022" <?php selected(get_option('aitwc_claude_model', ''), 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="aitwc-settings-section">
                    <h2><?php _e('Language Settings', 'ai-translator-wc'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Source Language', 'ai-translator-wc'); ?></th>
                            <td>
                                <select name="aitwc_source_language">
                                    <?php foreach ($languages as $code => $lang) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($source_language, $code); ?>>
                                            <?php echo esc_html($lang['native'] . ' (' . $lang['name'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Target Languages', 'ai-translator-wc'); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ($languages as $code => $lang) : 
                                        if ($code === $source_language) continue; ?>
                                        <label style="display:inline-block; margin-right:15px; margin-bottom:8px;">
                                            <input type="checkbox" name="aitwc_target_languages[]" 
                                                value="<?php echo esc_attr($code); ?>"
                                                <?php checked(in_array($code, (array)$target_languages)); ?>>
                                            <?php echo esc_html($lang['native']); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>


                <div class="aitwc-settings-section">
                    <h2><?php _e('Display & SEO Settings', 'ai-translator-wc'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Switcher Integration Mode', 'ai-translator-wc'); ?></th>
                            <td>
                                <?php
                                // Read mode with legacy fallback
                                $integration = get_option('aitwc_menu_integration', '');
                                if (empty($integration)) {
                                    $legacy = get_option('aitwc_switcher_position', 'nav-menu');
                                    $integration = ($legacy === 'top-bar') ? 'top-bar'
                                        : (($legacy === 'float-right') ? 'float-right' : 'auto');
                                }
                                ?>
                                <select name="aitwc_menu_integration" id="aitwc_menu_integration">
                                    <option value="menu_item" <?php selected($integration, 'menu_item'); ?>>
                                        <?php _e('Menu Item (recommended) — add via Appearance → Menus', 'ai-translator-wc'); ?>
                                    </option>
                                    <option value="auto" <?php selected($integration, 'auto'); ?>>
                                        <?php _e('Auto-inject into primary navigation', 'ai-translator-wc'); ?>
                                    </option>
                                    <option value="top-bar" <?php selected($integration, 'top-bar'); ?>>
                                        <?php _e('Top Bar (above page)', 'ai-translator-wc'); ?>
                                    </option>
                                    <option value="float-right" <?php selected($integration, 'float-right'); ?>>
                                        <?php _e('Floating (fixed right)', 'ai-translator-wc'); ?>
                                    </option>
                                    <option value="shortcode" <?php selected($integration, 'shortcode'); ?>>
                                        <?php _e('Shortcode / PHP only', 'ai-translator-wc'); ?>
                                    </option>
                                    <option value="none" <?php selected($integration, 'none'); ?>>
                                        <?php _e('Disabled', 'ai-translator-wc'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Recommended: choose <strong>Menu Item</strong>, then go to <em>Appearance → Menus</em> and drag the "Language Switcher" item into your menu where you want it.', 'ai-translator-wc'); ?><br>
                                    <?php _e('Or use the shortcode <code>[aitwc_language_switcher]</code> anywhere in your content / page-builder.', 'ai-translator-wc'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('SEO Friendly URLs', 'ai-translator-wc'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="aitwc_seo_urls" value="yes"
                                        <?php checked(get_option('aitwc_seo_urls', 'yes'), 'yes'); ?>>
                                    <?php _e('Enable SEO friendly URLs (e.g., /zh/product-name/)', 'ai-translator-wc'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="aitwc-settings-section">
                    <h2><?php _e('Content Settings', 'ai-translator-wc'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Translate Content Types', 'ai-translator-wc'); ?></th>
                            <td>
                                <label><input type="checkbox" name="aitwc_translate_products" value="yes"
                                    <?php checked(get_option('aitwc_translate_products', 'yes'), 'yes'); ?>>
                                    <?php _e('WooCommerce Products', 'ai-translator-wc'); ?></label><br>
                                <label><input type="checkbox" name="aitwc_translate_pages" value="yes"
                                    <?php checked(get_option('aitwc_translate_pages', 'yes'), 'yes'); ?>>
                                    <?php _e('Pages', 'ai-translator-wc'); ?></label><br>
                                <label><input type="checkbox" name="aitwc_translate_posts" value="yes"
                                    <?php checked(get_option('aitwc_translate_posts', 'yes'), 'yes'); ?>>
                                    <?php _e('Posts', 'ai-translator-wc'); ?></label>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }


    public function render_batch_page() {
        $languages = AI_Translator_WooCommerce::get_supported_languages();
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        ?>
        <div class="wrap aitwc-settings-wrap">
            <h1><?php _e('Batch Translate', 'ai-translator-wc'); ?></h1>
            <p><?php _e('Pre-translate all your content so visitors get instant page loads from the database cache.', 'ai-translator-wc'); ?></p>

            <div class="aitwc-batch-controls">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Target Language', 'ai-translator-wc'); ?></th>
                        <td>
                            <select id="aitwc-batch-lang">
                                <?php foreach ($target_languages as $code) :
                                    if (isset($languages[$code])) : ?>
                                    <option value="<?php echo esc_attr($code); ?>">
                                        <?php echo esc_html($languages[$code]['native']); ?>
                                    </option>
                                <?php endif; endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Content Type', 'ai-translator-wc'); ?></th>
                        <td>
                            <select id="aitwc-batch-type">
                                <option value="product"><?php _e('Products', 'ai-translator-wc'); ?></option>
                                <option value="page"><?php _e('Pages', 'ai-translator-wc'); ?></option>
                                <option value="post"><?php _e('Posts', 'ai-translator-wc'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Batch Size', 'ai-translator-wc'); ?></th>
                        <td>
                            <input type="number" id="aitwc-batch-size" value="10" min="1" max="50">
                            <p class="description"><?php _e('Number of items to translate per batch (lower = safer for API limits)', 'ai-translator-wc'); ?></p>
                        </td>
                    </tr>
                </table>

                <button type="button" class="button button-primary" id="aitwc-start-batch">
                    <?php _e('Start Batch Translation', 'ai-translator-wc'); ?>
                </button>
                <button type="button" class="button" id="aitwc-clear-cache">
                    <?php _e('Clear All Cache', 'ai-translator-wc'); ?>
                </button>
            </div>

            <div id="aitwc-batch-progress" style="display:none;">
                <h3><?php _e('Progress', 'ai-translator-wc'); ?></h3>
                <div class="aitwc-progress-bar"><div class="aitwc-progress-fill"></div></div>
                <p id="aitwc-batch-status"></p>
            </div>
        </div>
        <?php
    }


    public function render_stats_page() {
        $cache = new AITWC_Translation_Cache();
        $stats = $cache->get_stats();
        $languages = AI_Translator_WooCommerce::get_supported_languages();
        ?>
        <div class="wrap aitwc-settings-wrap">
            <h1><?php _e('Translation Statistics', 'ai-translator-wc'); ?></h1>

            <div class="aitwc-stats-grid">
                <div class="aitwc-stat-card">
                    <h3><?php echo esc_html($stats['total_translations']); ?></h3>
                    <p><?php _e('Total Translations', 'ai-translator-wc'); ?></p>
                </div>
                <div class="aitwc-stat-card">
                    <h3><?php echo esc_html($stats['total_posts']); ?></h3>
                    <p><?php _e('Translated Posts/Products', 'ai-translator-wc'); ?></p>
                </div>
            </div>

            <?php if (!empty($stats['by_language'])) : ?>
            <h2><?php _e('By Language', 'ai-translator-wc'); ?></h2>
            <table class="widefat">
                <thead><tr><th><?php _e('Language', 'ai-translator-wc'); ?></th><th><?php _e('Count', 'ai-translator-wc'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($stats['by_language'] as $row) :
                        $lang_name = isset($languages[$row['target_language']]) ? $languages[$row['target_language']]['native'] : $row['target_language'];
                    ?>
                    <tr><td><?php echo esc_html($lang_name); ?></td><td><?php echo esc_html($row['count']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($stats['by_provider'])) : ?>
            <h2><?php _e('By AI Provider', 'ai-translator-wc'); ?></h2>
            <table class="widefat">
                <thead><tr><th><?php _e('Provider', 'ai-translator-wc'); ?></th><th><?php _e('Count', 'ai-translator-wc'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($stats['by_provider'] as $row) : ?>
                    <tr><td><?php echo esc_html(ucfirst($row['ai_provider'])); ?></td><td><?php echo esc_html($row['count']); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }


    public function ajax_batch_translate() {
        check_ajax_referer('aitwc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $target_lang = sanitize_text_field($_POST['target_lang']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $batch_size = intval($_POST['batch_size']);

        $translator = AITWC_Translator_Core::get_instance();
        $results = $translator->batch_translate($target_lang, $post_type, $batch_size);

        wp_send_json_success($results);
    }

    public function ajax_clear_cache() {
        check_ajax_referer('aitwc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $cache = new AITWC_Translation_Cache();
        $cache->clear_all();

        wp_send_json_success(array('message' => __('Cache cleared successfully.', 'ai-translator-wc')));
    }

    public function ajax_test_api() {
        check_ajax_referer('aitwc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $provider_name = sanitize_text_field($_POST['provider']);
        
        // Temporarily set provider for testing
        $original = get_option('aitwc_ai_provider');
        update_option('aitwc_ai_provider', $provider_name);

        $translator = AITWC_Translator_Core::get_instance();
        $result = $translator->translate_text('Hello, this is a test.', 'zh-CN');

        update_option('aitwc_ai_provider', $original);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Connection successful!', 'ai-translator-wc'),
            'translation' => $result,
        ));
    }
}

endif; // class_exists
