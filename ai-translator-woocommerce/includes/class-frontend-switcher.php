<?php
/**
 * Frontend Language Switcher - Embedded in navigation bar with flags
 */

if (!defined('ABSPATH')) {
    exit;
}

class AITWC_Frontend_Switcher {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('wp_nav_menu_items', array($this, 'add_switcher_to_menu'), 10, 2);
        add_action('wp_head', array($this, 'output_hreflang_tags'));
        add_action('init', array($this, 'register_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_language_redirect'));
    }


    public function enqueue_assets() {
        wp_enqueue_style(
            'aitwc-frontend',
            AITWC_PLUGIN_URL . 'assets/css/frontend-switcher.css',
            array(),
            AITWC_VERSION
        );

        wp_enqueue_script(
            'aitwc-frontend',
            AITWC_PLUGIN_URL . 'assets/js/frontend-switcher.js',
            array('jquery'),
            AITWC_VERSION,
            true
        );

        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $source_language = get_option('aitwc_source_language', 'en');
        $all_languages = AI_Translator_WooCommerce::get_supported_languages();

        // Build active languages array
        $active_langs = array();
        $active_langs[$source_language] = $all_languages[$source_language];
        foreach ($target_languages as $code) {
            if (isset($all_languages[$code])) {
                $active_langs[$code] = $all_languages[$code];
            }
        }

        wp_localize_script('aitwc-frontend', 'aitwc_front', array(
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('aitwc_frontend_nonce'),
            'current_lang'    => $this->get_current_language(),
            'source_lang'     => $source_language,
            'languages'       => $active_langs,
            'flags_url'       => AITWC_PLUGIN_URL . 'assets/flags/',
            'seo_urls'        => get_option('aitwc_seo_urls', 'yes'),
            'current_url'     => $this->get_clean_url(),
        ));
    }


    /**
     * Add language switcher to navigation menu
     */
    public function add_switcher_to_menu($items, $args) {
        // Only add to primary menu
        if ($args->theme_location !== 'primary' && $args->theme_location !== 'main-menu') {
            // Fallback: add to any menu if primary not found
            static $added = false;
            if ($added) return $items;
            $added = true;
        }

        $switcher_html = $this->get_switcher_html();
        $items .= '<li class="menu-item aitwc-menu-item">' . $switcher_html . '</li>';

        return $items;
    }

    /**
     * Generate the language switcher dropdown HTML
     */
    public function get_switcher_html() {
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $source_language = get_option('aitwc_source_language', 'en');
        $all_languages = AI_Translator_WooCommerce::get_supported_languages();
        $current_lang = $this->get_current_language();

        $current_info = isset($all_languages[$current_lang]) ? $all_languages[$current_lang] : $all_languages['en'];

        ob_start();
        ?>
        <div class="aitwc-switcher" id="aitwc-language-switcher">
            <button class="aitwc-switcher-btn" aria-expanded="false" aria-haspopup="true">
                <img src="<?php echo esc_url(AITWC_PLUGIN_URL . 'assets/flags/' . $current_info['flag'] . '.svg'); ?>" 
                     alt="<?php echo esc_attr($current_info['native']); ?>" 
                     class="aitwc-flag-icon">
                <span class="aitwc-lang-name"><?php echo esc_html($current_info['native']); ?></span>
                <svg class="aitwc-arrow" width="10" height="6" viewBox="0 0 10 6"><path d="M1 1l4 4 4-4" stroke="currentColor" fill="none" stroke-width="1.5"/></svg>
            </button>
            <ul class="aitwc-dropdown" role="menu">
                <?php
                // Source language first
                $this->render_language_item($source_language, $all_languages[$source_language], $current_lang);
                
                // Target languages
                foreach ($target_languages as $code) {
                    if (isset($all_languages[$code]) && $code !== $source_language) {
                        $this->render_language_item($code, $all_languages[$code], $current_lang);
                    }
                }
                ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_language_item($code, $lang_info, $current_lang) {
        $is_active = ($code === $current_lang) ? ' aitwc-active' : '';
        $url = $this->get_language_url($code);
        ?>
        <li class="aitwc-dropdown-item<?php echo $is_active; ?>" role="menuitem">
            <a href="<?php echo esc_url($url); ?>" data-lang="<?php echo esc_attr($code); ?>">
                <img src="<?php echo esc_url(AITWC_PLUGIN_URL . 'assets/flags/' . $lang_info['flag'] . '.svg'); ?>" 
                     alt="<?php echo esc_attr($lang_info['name']); ?>" 
                     class="aitwc-flag-icon">
                <span><?php echo esc_html($lang_info['native']); ?></span>
            </a>
        </li>
        <?php
    }


    /**
     * Register SEO-friendly rewrite rules: /lang-code/original-path/
     */
    public function register_rewrite_rules() {
        if (get_option('aitwc_seo_urls', 'yes') !== 'yes') {
            return;
        }

        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $lang_codes = implode('|', array_map(function($code) {
            return preg_quote(strtolower(str_replace('-', '-', $code)), '/');
        }, $target_languages));

        if (!empty($lang_codes)) {
            add_rewrite_rule(
                '^(' . $lang_codes . ')/(.*)$',
                'index.php?aitwc_lang=$matches[1]&aitwc_path=$matches[2]',
                'top'
            );
            add_rewrite_rule(
                '^(' . $lang_codes . ')/?$',
                'index.php?aitwc_lang=$matches[1]',
                'top'
            );
        }
    }

    public function add_query_vars($vars) {
        $vars[] = 'aitwc_lang';
        $vars[] = 'aitwc_path';
        return $vars;
    }

    /**
     * Handle language from URL and set cookie
     */
    public function handle_language_redirect() {
        $lang = get_query_var('aitwc_lang', '');
        
        if (!empty($lang)) {
            // Normalize language code (url uses lowercase)
            $lang = $this->normalize_lang_code($lang);
            
            if (!headers_sent()) {
                setcookie('aitwc_language', $lang, time() + (365 * DAY_IN_SECONDS), '/');
            }
            $_COOKIE['aitwc_language'] = $lang;
        }
    }

    /**
     * Output hreflang tags for SEO
     */
    public function output_hreflang_tags() {
        if (is_admin()) return;

        $source_language = get_option('aitwc_source_language', 'en');
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $current_url = $this->get_clean_url();

        // Source language (default)
        echo '<link rel="alternate" hreflang="' . esc_attr($source_language) . '" href="' . esc_url($current_url) . '" />' . "\n";

        // Target languages
        foreach ($target_languages as $code) {
            $lang_url = $this->build_seo_url($current_url, $code);
            $hreflang = strtolower(str_replace('_', '-', $code));
            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($lang_url) . '" />' . "\n";
        }

        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($current_url) . '" />' . "\n";
    }


    /**
     * Get current active language
     */
    private function get_current_language() {
        // Check URL rewrite
        $lang = get_query_var('aitwc_lang', '');
        if (!empty($lang)) {
            return $this->normalize_lang_code($lang);
        }

        // Check GET parameter
        if (isset($_GET['lang']) && !empty($_GET['lang'])) {
            return sanitize_text_field($_GET['lang']);
        }

        // Check cookie
        if (isset($_COOKIE['aitwc_language']) && !empty($_COOKIE['aitwc_language'])) {
            return sanitize_text_field($_COOKIE['aitwc_language']);
        }

        return get_option('aitwc_source_language', 'en');
    }

    /**
     * Get language-specific URL
     */
    private function get_language_url($lang_code) {
        $source_language = get_option('aitwc_source_language', 'en');
        $seo_urls = get_option('aitwc_seo_urls', 'yes');
        $current_url = $this->get_clean_url();

        if ($lang_code === $source_language) {
            return $current_url;
        }

        if ($seo_urls === 'yes') {
            return $this->build_seo_url($current_url, $lang_code);
        }

        return add_query_arg('lang', $lang_code, $current_url);
    }

    /**
     * Build SEO-friendly URL with language prefix
     */
    private function build_seo_url($url, $lang_code) {
        $home_url = trailingslashit(home_url());
        $path = str_replace($home_url, '', $url);
        $lang_slug = strtolower(str_replace('_', '-', $lang_code));
        return $home_url . $lang_slug . '/' . ltrim($path, '/');
    }

    /**
     * Get clean current URL without language prefix
     */
    private function get_clean_url() {
        $current_url = home_url(add_query_arg(array()));
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));

        // Remove language prefix if present
        foreach ($target_languages as $code) {
            $slug = strtolower(str_replace('_', '-', $code));
            $pattern = '/' . preg_quote($slug, '/') . '\//';
            $current_url = preg_replace('#/' . preg_quote($slug, '#') . '/#', '/', $current_url, 1);
        }

        return $current_url;
    }

    /**
     * Normalize language code from URL slug to standard code
     */
    private function normalize_lang_code($slug) {
        $map = array(
            'zh-cn' => 'zh-CN',
            'zh-tw' => 'zh-TW',
        );
        $lower = strtolower($slug);
        return isset($map[$lower]) ? $map[$lower] : $lower;
    }
}
