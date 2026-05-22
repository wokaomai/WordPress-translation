<?php
/**
 * Frontend Language Switcher
 *
 * Supports four integration modes:
 *   - menu_item : add via Appearance → Menus (custom menu item)
 *   - auto      : auto-inject into primary nav menu
 *   - shortcode : [aitwc_language_switcher]
 *   - none      : disabled (use shortcode/PHP only)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('AITWC_Frontend_Switcher', false)) :

class AITWC_Frontend_Switcher {

    private static $instance = null;

    /**
     * Special CSS class used to flag a custom menu item as the language switcher.
     */
    const MENU_ITEM_CLASS = 'aitwc-language-switcher-menu-item';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Front-end assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Menu integration
        add_filter('wp_nav_menu_items', array($this, 'auto_inject_into_menu'), 10, 2);
        add_filter('walker_nav_menu_start_el', array($this, 'render_menu_item'), 10, 4);

        // Admin: add "Language Switcher" meta box on Appearance → Menus screen.
        // Use the screen-specific hook so add_meta_box() is guaranteed loaded.
        add_action('admin_head-nav-menus.php', array($this, 'register_nav_menu_meta_box'));

        // Shortcode: [aitwc_language_switcher]
        add_shortcode('aitwc_language_switcher', array($this, 'shortcode_render'));

        // Floating / top-bar position rendering
        add_action('wp_body_open', array($this, 'render_floating_switcher'));
        add_action('wp_footer', array($this, 'render_floating_switcher_fallback'));

        // SEO + URL handling
        add_action('wp_head', array($this, 'output_hreflang_tags'));
        add_action('init', array($this, 'register_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_language_redirect'));
    }

    /* -----------------------------------------------------------------
     * Asset loading
     * ----------------------------------------------------------------- */

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
        $source_language  = get_option('aitwc_source_language', 'en');
        $all_languages    = AI_Translator_WooCommerce::get_supported_languages();

        // Build active languages array
        $active_langs = array();
        if (isset($all_languages[$source_language])) {
            $active_langs[$source_language] = $all_languages[$source_language];
        }
        foreach ((array) $target_languages as $code) {
            if (isset($all_languages[$code])) {
                $active_langs[$code] = $all_languages[$code];
            }
        }

        wp_localize_script('aitwc-frontend', 'aitwc_front', array(
            'ajax_url'         => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('aitwc_frontend_nonce'),
            'current_lang'     => $this->get_current_language(),
            'source_lang'      => $source_language,
            'languages'        => $active_langs,
            'flags_url'        => AITWC_PLUGIN_URL . 'assets/flags/',
            'seo_urls'         => get_option('aitwc_seo_urls', 'yes'),
            'current_url'      => $this->get_clean_url(),
            // For JS-based menu-item replacement (works with themes that use
            // a custom Walker and don't fire walker_nav_menu_start_el).
            'integration_mode' => $this->get_integration_mode(),
            'menu_item_class'  => self::MENU_ITEM_CLASS,
            'menu_item_url'    => '#aitwc-language-switcher',
            'switcher_html'    => $this->get_switcher_html(),
        ));
    }

    /* -----------------------------------------------------------------
     * Menu integration mode
     * ----------------------------------------------------------------- */

    /**
     * Get the configured integration mode.
     *
     * Falls back to legacy `aitwc_switcher_position` when the new option is unset:
     *   - 'nav-menu'   => 'auto'
     *   - 'top-bar'    => 'top-bar'
     *   - 'float-right'=> 'float-right'
     *
     * @return string One of: menu_item|auto|shortcode|top-bar|float-right|none
     */
    public function get_integration_mode() {
        $mode = get_option('aitwc_menu_integration', '');
        if (!empty($mode)) {
            return $mode;
        }

        // Legacy fallback
        $legacy = get_option('aitwc_switcher_position', 'nav-menu');
        switch ($legacy) {
            case 'top-bar':
                return 'top-bar';
            case 'float-right':
                return 'float-right';
            case 'nav-menu':
            default:
                return 'auto';
        }
    }

    /* -----------------------------------------------------------------
     * Auto-injection into navigation menu
     * ----------------------------------------------------------------- */

    /**
     * Auto-inject switcher into the primary nav menu.
     *
     * Only injects in 'auto' mode, into a single (preferred) menu location, and
     * only ONCE per page-render to avoid the duplicate-injection bug that
     * caused the switcher to appear in unrelated top-bar/utility menus.
     */
    public function auto_inject_into_menu($items, $args) {
        if ($this->get_integration_mode() !== 'auto') {
            return $items;
        }

        static $injected = false;
        if ($injected) {
            return $items;
        }

        $location = isset($args->theme_location) ? $args->theme_location : '';

        // Preferred locations across common themes — STRICTLY primary nav only.
        // Locations like 'top' / 'header' / 'header-menu' are often used for
        // secondary utility bars (where the original screenshot bug occurred),
        // so they're intentionally NOT included here.
        $preferred = array(
            'primary',
            'main-menu',
            'main_menu',
            'main',
            'menu-1',
            'primary-menu',
        );

        /**
         * Filter: aitwc_auto_inject_locations
         * Allow themes to specify which menu locations should receive the
         * auto-injected language switcher.
         */
        $preferred = apply_filters('aitwc_auto_inject_locations', $preferred);

        if (!in_array($location, $preferred, true)) {
            // Not a preferred location — do not fall back to "any menu" anymore.
            // (Previous behaviour caused the switcher to render in top-bar / utility menus.)
            return $items;
        }

        $injected = true;
        $items   .= '<li class="menu-item aitwc-menu-item">' . $this->get_switcher_html() . '</li>';
        return $items;
    }

    /* -----------------------------------------------------------------
     * Custom menu item integration (Appearance → Menus)
     * ----------------------------------------------------------------- */

    /**
     * Register the meta box that appears on the Appearance → Menus screen,
     * letting site admins drag a "Language Switcher" item into any menu.
     */
    public function register_nav_menu_meta_box() {
        if (!function_exists('add_meta_box')) {
            return;
        }
        add_meta_box(
            'aitwc-nav-menu-meta-box',
            __('Language Switcher', 'ai-translator-wc'),
            array($this, 'render_nav_menu_meta_box'),
            'nav-menus',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box content.
     */
    public function render_nav_menu_meta_box() {
        global $_nav_menu_placeholder;
        $_nav_menu_placeholder = (0 > (int) $_nav_menu_placeholder) ? (int) $_nav_menu_placeholder - 1 : -1;
        ?>
        <div id="aitwc-language-switcher-nav-menu" class="posttypediv">
            <p style="margin:0 0 10px;">
                <?php esc_html_e('Add the language switcher anywhere in your menu. Drag it to reorder.', 'ai-translator-wc'); ?>
            </p>
            <div class="tabs-panel tabs-panel-active">
                <ul class="categorychecklist form-no-clear">
                    <li>
                        <label class="menu-item-title">
                            <input type="checkbox"
                                   class="menu-item-checkbox"
                                   name="menu-item[<?php echo esc_attr($_nav_menu_placeholder); ?>][menu-item-object-id]"
                                   value="<?php echo esc_attr($_nav_menu_placeholder); ?>" />
                            <?php esc_html_e('Language Switcher', 'ai-translator-wc'); ?>
                        </label>
                        <input type="hidden" class="menu-item-type"
                               name="menu-item[<?php echo esc_attr($_nav_menu_placeholder); ?>][menu-item-type]"
                               value="custom" />
                        <input type="hidden" class="menu-item-title"
                               name="menu-item[<?php echo esc_attr($_nav_menu_placeholder); ?>][menu-item-title]"
                               value="<?php esc_attr_e('Language Switcher', 'ai-translator-wc'); ?>" />
                        <input type="hidden" class="menu-item-url"
                               name="menu-item[<?php echo esc_attr($_nav_menu_placeholder); ?>][menu-item-url]"
                               value="#aitwc-language-switcher" />
                        <input type="hidden" class="menu-item-classes"
                               name="menu-item[<?php echo esc_attr($_nav_menu_placeholder); ?>][menu-item-classes]"
                               value="<?php echo esc_attr(self::MENU_ITEM_CLASS); ?>" />
                    </li>
                </ul>
            </div>
            <p class="button-controls">
                <span class="add-to-menu">
                    <input type="submit"
                           class="button submit-add-to-menu right"
                           value="<?php esc_attr_e('Add to Menu', 'ai-translator-wc'); ?>"
                           name="add-aitwc-language-switcher-menu-item"
                           id="submit-aitwc-language-switcher-nav-menu" />
                    <span class="spinner"></span>
                </span>
            </p>
            <p class="description" style="margin-top:8px;">
                <?php esc_html_e('Tip: After adding, you can edit the item to change its label or CSS class. Keep the class "aitwc-language-switcher-menu-item" so the plugin can recognize and replace it with the actual switcher.', 'ai-translator-wc'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Replace flagged menu items with the rendered switcher HTML.
     *
     * A menu item is recognized as a switcher placeholder when:
     *  - it has the CSS class `aitwc-language-switcher-menu-item`, OR
     *  - its URL is exactly `#aitwc-language-switcher`.
     */
    public function render_menu_item($item_output, $item, $depth, $args) {
        $classes = isset($item->classes) ? (array) $item->classes : array();
        $url     = isset($item->url) ? $item->url : '';

        $is_switcher = in_array(self::MENU_ITEM_CLASS, $classes, true)
            || $url === '#aitwc-language-switcher';

        if (!$is_switcher) {
            return $item_output;
        }

        return $this->get_switcher_html();
    }

    /* -----------------------------------------------------------------
     * Shortcode + floating positions
     * ----------------------------------------------------------------- */

    /**
     * [aitwc_language_switcher] shortcode.
     */
    public function shortcode_render($atts = array()) {
        return $this->get_switcher_html();
    }

    /**
     * Render switcher in body-open hook for top-bar / float positions.
     */
    public function render_floating_switcher() {
        $mode = $this->get_integration_mode();

        if ($mode === 'top-bar') {
            echo '<div class="aitwc-top-bar">' . $this->get_switcher_html() . '</div>';
        } elseif ($mode === 'float-right') {
            echo '<div class="aitwc-float-right">' . $this->get_switcher_html() . '</div>';
        }
    }

    /**
     * Fallback: if `wp_body_open` was not fired by the theme, render in footer.
     */
    public function render_floating_switcher_fallback() {
        if (did_action('wp_body_open')) {
            return;
        }
        $this->render_floating_switcher();
    }

    /* -----------------------------------------------------------------
     * Switcher HTML
     * ----------------------------------------------------------------- */

    public function get_switcher_html() {
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $source_language  = get_option('aitwc_source_language', 'en');
        $all_languages    = AI_Translator_WooCommerce::get_supported_languages();
        $current_lang     = $this->get_current_language();

        $current_info = isset($all_languages[$current_lang])
            ? $all_languages[$current_lang]
            : (isset($all_languages[$source_language]) ? $all_languages[$source_language] : $all_languages['en']);

        ob_start();
        ?>
        <div class="aitwc-switcher" data-current="<?php echo esc_attr($current_lang); ?>">
            <button type="button" class="aitwc-switcher-btn" aria-expanded="false" aria-haspopup="true">
                <img src="<?php echo esc_url(AITWC_PLUGIN_URL . 'assets/flags/' . $current_info['flag'] . '.svg'); ?>"
                     alt="<?php echo esc_attr($current_info['native']); ?>"
                     class="aitwc-flag-icon">
                <span class="aitwc-lang-name"><?php echo esc_html($current_info['native']); ?></span>
                <svg class="aitwc-arrow" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true">
                    <path d="M1 1l4 4 4-4" stroke="currentColor" fill="none" stroke-width="1.5"/>
                </svg>
            </button>
            <ul class="aitwc-dropdown" role="menu">
                <?php
                // Source language first
                if (isset($all_languages[$source_language])) {
                    $this->render_language_item($source_language, $all_languages[$source_language], $current_lang);
                }

                // Target languages
                foreach ((array) $target_languages as $code) {
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
        $url       = $this->get_language_url($code);
        ?>
        <li class="aitwc-dropdown-item<?php echo esc_attr($is_active); ?>" role="menuitem">
            <a href="<?php echo esc_url($url); ?>" data-lang="<?php echo esc_attr($code); ?>">
                <img src="<?php echo esc_url(AITWC_PLUGIN_URL . 'assets/flags/' . $lang_info['flag'] . '.svg'); ?>"
                     alt="<?php echo esc_attr($lang_info['name']); ?>"
                     class="aitwc-flag-icon">
                <span><?php echo esc_html($lang_info['native']); ?></span>
            </a>
        </li>
        <?php
    }

    /* -----------------------------------------------------------------
     * SEO / URL handling
     * ----------------------------------------------------------------- */

    public function register_rewrite_rules() {
        if (get_option('aitwc_seo_urls', 'yes') !== 'yes') {
            return;
        }

        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $lang_codes = implode('|', array_map(function ($code) {
            return preg_quote(strtolower($code), '/');
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

    public function handle_language_redirect() {
        $lang = get_query_var('aitwc_lang', '');
        if (!empty($lang)) {
            $lang = $this->normalize_lang_code($lang);
            if (!headers_sent()) {
                setcookie('aitwc_language', $lang, time() + (365 * DAY_IN_SECONDS), '/');
            }
            $_COOKIE['aitwc_language'] = $lang;
        }
    }

    public function output_hreflang_tags() {
        if (is_admin()) {
            return;
        }

        $source_language  = get_option('aitwc_source_language', 'en');
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));
        $current_url      = $this->get_clean_url();

        echo '<link rel="alternate" hreflang="' . esc_attr($source_language) . '" href="' . esc_url($current_url) . '" />' . "\n";

        foreach ((array) $target_languages as $code) {
            $lang_url = $this->build_seo_url($current_url, $code);
            $hreflang = strtolower(str_replace('_', '-', $code));
            echo '<link rel="alternate" hreflang="' . esc_attr($hreflang) . '" href="' . esc_url($lang_url) . '" />' . "\n";
        }

        echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($current_url) . '" />' . "\n";
    }

    /* -----------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------- */

    private function get_current_language() {
        $lang = get_query_var('aitwc_lang', '');
        if (!empty($lang)) {
            return $this->normalize_lang_code($lang);
        }
        if (isset($_GET['lang']) && !empty($_GET['lang'])) {
            return sanitize_text_field(wp_unslash($_GET['lang']));
        }
        if (isset($_COOKIE['aitwc_language']) && !empty($_COOKIE['aitwc_language'])) {
            return sanitize_text_field(wp_unslash($_COOKIE['aitwc_language']));
        }
        return get_option('aitwc_source_language', 'en');
    }

    private function get_language_url($lang_code) {
        $source_language = get_option('aitwc_source_language', 'en');
        $seo_urls        = get_option('aitwc_seo_urls', 'yes');
        $current_url     = $this->get_clean_url();

        if ($lang_code === $source_language) {
            return $current_url;
        }
        if ($seo_urls === 'yes') {
            return $this->build_seo_url($current_url, $lang_code);
        }
        return add_query_arg('lang', $lang_code, $current_url);
    }

    private function build_seo_url($url, $lang_code) {
        $home_url  = trailingslashit(home_url());
        $path      = str_replace($home_url, '', $url);
        $lang_slug = strtolower(str_replace('_', '-', $lang_code));
        return $home_url . $lang_slug . '/' . ltrim($path, '/');
    }

    private function get_clean_url() {
        $current_url      = home_url(add_query_arg(array()));
        $target_languages = get_option('aitwc_target_languages', array('zh-CN', 'fr', 'es', 'ko'));

        foreach ((array) $target_languages as $code) {
            $slug        = strtolower(str_replace('_', '-', $code));
            $current_url = preg_replace('#/' . preg_quote($slug, '#') . '/#', '/', $current_url, 1);
        }
        return $current_url;
    }

    private function normalize_lang_code($slug) {
        $map = array(
            'zh-cn' => 'zh-CN',
            'zh-tw' => 'zh-TW',
        );
        $lower = strtolower($slug);
        return isset($map[$lower]) ? $map[$lower] : $lower;
    }
}

endif; // class_exists
