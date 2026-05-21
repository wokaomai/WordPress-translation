<?php
/**
 * Plugin Name: AI Translator for WooCommerce
 * Plugin URI: https://github.com/wokaomai/WordPress-translation
 * Description: AI-powered translation plugin for WooCommerce. Supports Google Gemini, DeepSeek, OpenAI, and Claude. Translations are cached in database for instant delivery.
 * Version: 1.0.0
 * Author: wokaomai
 * Author URI: https://github.com/wokaomai
 * Text Domain: ai-translator-wc
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AITWC_VERSION', '1.0.0');
define('AITWC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AITWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AITWC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class AI_Translator_WooCommerce {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     */
    private function includes() {
        require_once AITWC_PLUGIN_DIR . 'includes/class-gemini-api.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-deepseek-api.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-openai-api.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-claude-api.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-translation-cache.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-translator-core.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        require_once AITWC_PLUGIN_DIR . 'includes/class-frontend-switcher.php';

        if (is_admin()) {
            require_once AITWC_PLUGIN_DIR . 'includes/class-admin-settings.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));

        // Declare WooCommerce feature compatibility
        add_action('before_woocommerce_init', array($this, 'declare_wc_compatibility'));
    }

    /**
     * Declare compatibility with WooCommerce features (HPOS, Blocks, etc.)
     */
    public function declare_wc_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare HPOS (High-Performance Order Storage) compatibility
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );

            // Declare Cart & Checkout Blocks compatibility
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true
            );
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $cache = new AITWC_Translation_Cache();
        $cache->create_tables();

        // Set default options
        $defaults = array(
            'ai_provider'      => 'gemini',
            'gemini_api_key'   => '',
            'deepseek_api_key' => '',
            'openai_api_key'   => '',
            'claude_api_key'   => '',
            'source_language'  => 'en',
            'target_languages' => array('zh-CN', 'fr', 'es', 'ko'),
            'switcher_position' => 'nav-menu',
            'switcher_style'   => 'dropdown',
            'seo_urls'         => 'yes',
            'auto_translate'   => 'no',
            'translate_products' => 'yes',
            'translate_pages'  => 'yes',
            'translate_posts'  => 'yes',
            'translate_menus'  => 'yes',
        );

        foreach ($defaults as $key => $value) {
            if (false === get_option('aitwc_' . $key)) {
                update_option('aitwc_' . $key, $value);
            }
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Init
     */
    public function init() {
        load_plugin_textdomain('ai-translator-wc', false, dirname(AITWC_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * After all plugins loaded
     */
    public function on_plugins_loaded() {
        // Initialize components
        AITWC_Translator_Core::get_instance();
        AITWC_Ajax_Handler::get_instance();
        AITWC_Frontend_Switcher::get_instance();

        if (is_admin()) {
            AITWC_Admin_Settings::get_instance();
        }
    }

    /**
     * Get supported languages with flags and names
     */
    public static function get_supported_languages() {
        return array(
            'en'    => array('name' => 'English', 'native' => 'English', 'flag' => 'us'),
            'zh-CN' => array('name' => 'Chinese (Simplified)', 'native' => '简体中文', 'flag' => 'cn'),
            'zh-TW' => array('name' => 'Chinese (Traditional)', 'native' => '繁體中文', 'flag' => 'tw'),
            'ja'    => array('name' => 'Japanese', 'native' => '日本語', 'flag' => 'jp'),
            'ko'    => array('name' => 'Korean', 'native' => '한국어', 'flag' => 'kr'),
            'fr'    => array('name' => 'French', 'native' => 'Français', 'flag' => 'fr'),
            'de'    => array('name' => 'German', 'native' => 'Deutsch', 'flag' => 'de'),
            'es'    => array('name' => 'Spanish', 'native' => 'Español', 'flag' => 'es'),
            'pt'    => array('name' => 'Portuguese', 'native' => 'Português', 'flag' => 'pt'),
            'ru'    => array('name' => 'Russian', 'native' => 'Русский', 'flag' => 'ru'),
            'ar'    => array('name' => 'Arabic', 'native' => 'العربية', 'flag' => 'sa'),
            'it'    => array('name' => 'Italian', 'native' => 'Italiano', 'flag' => 'it'),
            'nl'    => array('name' => 'Dutch', 'native' => 'Nederlands', 'flag' => 'nl'),
            'th'    => array('name' => 'Thai', 'native' => 'ไทย', 'flag' => 'th'),
            'vi'    => array('name' => 'Vietnamese', 'native' => 'Tiếng Việt', 'flag' => 'vn'),
            'id'    => array('name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'flag' => 'id'),
            'tr'    => array('name' => 'Turkish', 'native' => 'Türkçe', 'flag' => 'tr'),
            'pl'    => array('name' => 'Polish', 'native' => 'Polski', 'flag' => 'pl'),
            'hi'    => array('name' => 'Hindi', 'native' => 'हिन्दी', 'flag' => 'in'),
            'he'    => array('name' => 'Hebrew', 'native' => 'עברית', 'flag' => 'il'),
        );
    }
}

// Initialize plugin
AI_Translator_WooCommerce::get_instance();
