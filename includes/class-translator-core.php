<?php
/**
 * Translator Core - Main translation orchestration logic
 * Handles routing between AI providers and manages the translation pipeline
 */

if (!defined('ABSPATH')) {
    exit;
}

class AITWC_Translator_Core {

    private static $instance = null;
    private $cache;
    private $provider;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->cache = new AITWC_Translation_Cache();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for content filtering
     */
    private function init_hooks() {
        // Filter content on the frontend when language is set
        add_filter('the_title', array($this, 'filter_title'), 10, 2);
        add_filter('the_content', array($this, 'filter_content'), 10);
        add_filter('the_excerpt', array($this, 'filter_excerpt'), 10);
        add_filter('woocommerce_product_get_name', array($this, 'filter_product_name'), 10, 2);
        add_filter('woocommerce_product_get_short_description', array($this, 'filter_product_short_description'), 10, 2);
        add_filter('woocommerce_product_get_description', array($this, 'filter_product_description'), 10, 2);

        // Hook into post save to invalidate cache
        add_action('save_post', array($this, 'on_post_save'), 10, 3);
    }

    /**
     * Get the current target language from user request
     */
    public function get_current_language() {
        // Check URL parameter first
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
     * Check if we need to translate (not source language)
     */
    public function needs_translation() {
        $current = $this->get_current_language();
        $source = get_option('aitwc_source_language', 'en');
        return $current !== $source;
    }

    /**
     * Filter post title - serve from database cache
     */
    public function filter_title($title, $post_id = null) {
        if (is_admin() || !$this->needs_translation() || empty($post_id)) {
            return $title;
        }

        $target_lang = $this->get_current_language();
        $cached = $this->cache->get_post_translation($post_id, $target_lang);

        if ($cached && !empty($cached->translated_title)) {
            return $cached->translated_title;
        }

        return $title;
    }

    /**
     * Filter post content - serve from database cache
     */
    public function filter_content($content) {
        if (is_admin() || !$this->needs_translation()) {
            return $content;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        $target_lang = $this->get_current_language();
        $cached = $this->cache->get_post_translation($post_id, $target_lang);

        if ($cached && !empty($cached->translated_content)) {
            return $cached->translated_content;
        }

        return $content;
    }

    /**
     * Filter post excerpt - serve from database cache
     */
    public function filter_excerpt($excerpt) {
        if (is_admin() || !$this->needs_translation()) {
            return $excerpt;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return $excerpt;
        }

        $target_lang = $this->get_current_language();
        $cached = $this->cache->get_post_translation($post_id, $target_lang);

        if ($cached && !empty($cached->translated_excerpt)) {
            return $cached->translated_excerpt;
        }

        return $excerpt;
    }

    /**
     * Filter WooCommerce product name
     */
    public function filter_product_name($name, $product) {
        if (is_admin() || !$this->needs_translation()) {
            return $name;
        }

        $post_id = $product->get_id();
        $target_lang = $this->get_current_language();
        $cached = $this->cache->get_post_translation($post_id, $target_lang);

        if ($cached && !empty($cached->translated_title)) {
            return $cached->translated_title;
        }

        return $name;
    }

    /**
     * Filter WooCommerce product short description
     */
    public function filter_product_short_description($description, $product) {
        if (is_admin() || !$this->needs_translation()) {
            return $description;
        }

        $post_id = $product->get_id();
        $target_lang = $this->get_current_language();
        $cached = $this->cache->get_post_translation($post_id, $target_lang);

        if ($cached && !empty($cached->translated_excerpt)) {
            return $cached->translated_excerpt;
        }

        return $description;
    }

    /**
     * Filter WooCommerce product description
     */
    public function filter_product_description($description, $product) {
        if (is_admin() || !$this->needs_translation()) {
            return $description;
        }

        $post_id = $product->get_id();
        $target_lang = $this->get_current_language();
        $cached = $this->cache->get_post_translation($post_id, $target_lang);

        if ($cached && !empty($cached->translated_content)) {
            return $cached->translated_content;
        }

        return $description;
    }

    /**
     * Translate a full post/product and store in database
     *
     * @param int    $post_id Post ID
     * @param string $target_lang Target language
     * @param bool   $force Force re-translation even if cached
     * @return array|WP_Error Translation result or error
     */
    public function translate_post($post_id, $target_lang, $force = false) {
        // Check if already translated (unless forced)
        if (!$force) {
            $existing = $this->cache->get_post_translation($post_id, $target_lang);
            if ($existing && $existing->is_complete) {
                return array(
                    'title'   => $existing->translated_title,
                    'content' => $existing->translated_content,
                    'excerpt' => $existing->translated_excerpt,
                    'cached'  => true,
                );
            }
        }

        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Post not found.', 'ai-translator-wc'));
        }

        $source_lang = get_option('aitwc_source_language', 'en');
        $provider = $this->get_ai_provider();
        $provider_name = get_option('aitwc_ai_provider', 'gemini');

        $translation_data = array();

        // Translate title
        if (!empty($post->post_title)) {
            $translated_title = $provider->translate($post->post_title, $source_lang, $target_lang);
            if (is_wp_error($translated_title)) {
                return $translated_title;
            }
            $translation_data['title'] = $translated_title;
        }

        // Translate content
        if (!empty($post->post_content)) {
            $translated_content = $this->translate_long_text($post->post_content, $source_lang, $target_lang, $provider);
            if (is_wp_error($translated_content)) {
                return $translated_content;
            }
            $translation_data['content'] = $translated_content;
        }

        // Translate excerpt
        if (!empty($post->post_excerpt)) {
            $translated_excerpt = $provider->translate($post->post_excerpt, $source_lang, $target_lang);
            if (is_wp_error($translated_excerpt)) {
                return $translated_excerpt;
            }
            $translation_data['excerpt'] = $translated_excerpt;
        }

        // Store in database permanently
        $this->cache->store_post_translation($post_id, $target_lang, $translation_data, $provider_name);

        $translation_data['cached'] = false;
        return $translation_data;
    }

    /**
     * Translate long text by splitting into chunks
     *
     * @param string $text Long text to translate
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @param object $provider AI provider instance
     * @return string|WP_Error Translated text or error
     */
    private function translate_long_text($text, $source_lang, $target_lang, $provider) {
        $max_chunk_size = 3000; // characters per chunk

        if (mb_strlen($text) <= $max_chunk_size) {
            return $provider->translate($text, $source_lang, $target_lang);
        }

        // Split by paragraphs or HTML blocks
        $chunks = $this->split_text_into_chunks($text, $max_chunk_size);
        $translated_chunks = array();

        foreach ($chunks as $chunk) {
            $translated = $provider->translate($chunk, $source_lang, $target_lang);
            if (is_wp_error($translated)) {
                return $translated;
            }
            $translated_chunks[] = $translated;
        }

        return implode("\n\n", $translated_chunks);
    }

    /**
     * Split text into manageable chunks preserving HTML structure
     */
    private function split_text_into_chunks($text, $max_size) {
        $chunks = array();
        
        // Split by double newlines (paragraphs) or common block elements
        $blocks = preg_split('/(\n\s*\n|<\/(?:p|div|h[1-6]|ul|ol|table|section|article)>)/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $current_chunk = '';
        foreach ($blocks as $block) {
            if (mb_strlen($current_chunk . $block) > $max_size && !empty($current_chunk)) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $block;
            } else {
                $current_chunk .= $block;
            }
        }

        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }

        return $chunks;
    }

    /**
     * Translate a single text string (with caching)
     *
     * @param string $text Text to translate
     * @param string $target_lang Target language
     * @return string|WP_Error Translated text or error
     */
    public function translate_text($text, $target_lang) {
        if (empty($text)) {
            return $text;
        }

        $source_lang = get_option('aitwc_source_language', 'en');

        // Check cache first
        $cached = $this->cache->get_translation($text, $target_lang);
        if ($cached !== false) {
            return $cached;
        }

        // Call AI provider
        $provider = $this->get_ai_provider();
        $provider_name = get_option('aitwc_ai_provider', 'gemini');

        $translated = $provider->translate($text, $source_lang, $target_lang);
        if (is_wp_error($translated)) {
            return $translated;
        }

        // Store in database
        $this->cache->store_translation($text, $translated, $source_lang, $target_lang, $provider_name);

        return $translated;
    }

    /**
     * Batch translate multiple posts
     *
     * @param string $target_lang Target language
     * @param string $post_type Post type
     * @param int    $batch_size Batch size
     * @return array Results
     */
    public function batch_translate($target_lang, $post_type = 'product', $batch_size = 10) {
        $untranslated = $this->cache->get_untranslated_posts($target_lang, $post_type, $batch_size);
        
        $results = array(
            'total'     => count($untranslated),
            'success'   => 0,
            'failed'    => 0,
            'errors'    => array(),
        );

        foreach ($untranslated as $post_id) {
            $result = $this->translate_post($post_id, $target_lang);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['errors'][] = array(
                    'post_id' => $post_id,
                    'error'   => $result->get_error_message(),
                );
            } else {
                $results['success']++;
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }

        return $results;
    }

    /**
     * Get the configured AI provider instance
     *
     * @return object AI provider instance
     */
    public function get_ai_provider() {
        if ($this->provider) {
            return $this->provider;
        }

        $provider_name = get_option('aitwc_ai_provider', 'gemini');

        switch ($provider_name) {
            case 'deepseek':
                $this->provider = new AITWC_DeepSeek_API();
                break;
            case 'openai':
                $this->provider = new AITWC_OpenAI_API();
                break;
            case 'claude':
                $this->provider = new AITWC_Claude_API();
                break;
            case 'gemini':
            default:
                $this->provider = new AITWC_Gemini_API();
                break;
        }

        return $this->provider;
    }

    /**
     * Invalidate cache when post is updated
     */
    public function on_post_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!in_array($post->post_type, array('post', 'page', 'product'))) {
            return;
        }

        // Delete cached translations when content changes
        $this->cache->delete_post_translations($post_id);
    }
}
