<?php
/**
 * AJAX Handler - Handles frontend translation requests
 * Implements hybrid mode: serve from DB cache, translate on-the-fly if missing
 */

if (!defined('ABSPATH')) {
    exit;
}

class AITWC_Ajax_Handler {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Frontend AJAX (for logged-in and non-logged-in users)
        add_action('wp_ajax_aitwc_translate_page', array($this, 'translate_page'));
        add_action('wp_ajax_nopriv_aitwc_translate_page', array($this, 'translate_page'));

        add_action('wp_ajax_aitwc_translate_text', array($this, 'translate_text'));
        add_action('wp_ajax_nopriv_aitwc_translate_text', array($this, 'translate_text'));

        add_action('wp_ajax_aitwc_set_language', array($this, 'set_language'));
        add_action('wp_ajax_nopriv_aitwc_set_language', array($this, 'set_language'));

        add_action('wp_ajax_aitwc_get_translation_status', array($this, 'get_translation_status'));
        add_action('wp_ajax_nopriv_aitwc_get_translation_status', array($this, 'get_translation_status'));
    }


    /**
     * Translate a full page/post - hybrid mode
     * 1. Check database cache
     * 2. If not cached, translate via AI and store
     * 3. Return translated content
     */
    public function translate_page() {
        check_ajax_referer('aitwc_frontend_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        if (empty($post_id) || empty($target_lang)) {
            wp_send_json_error(array('message' => __('Missing parameters.', 'ai-translator-wc')));
        }

        $translator = AITWC_Translator_Core::get_instance();
        $result = $translator->translate_post($post_id, $target_lang);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Translate a single text string - hybrid mode
     */
    public function translate_text() {
        check_ajax_referer('aitwc_frontend_nonce', 'nonce');

        $text = sanitize_textarea_field($_POST['text']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        if (empty($text) || empty($target_lang)) {
            wp_send_json_error(array('message' => __('Missing parameters.', 'ai-translator-wc')));
        }

        $translator = AITWC_Translator_Core::get_instance();
        $result = $translator->translate_text($text, $target_lang);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('translated_text' => $result));
    }

    /**
     * Set user language preference via cookie
     */
    public function set_language() {
        check_ajax_referer('aitwc_frontend_nonce', 'nonce');

        $lang = sanitize_text_field($_POST['language']);
        
        if (empty($lang)) {
            wp_send_json_error(array('message' => __('Invalid language.', 'ai-translator-wc')));
        }

        // Set cookie for 1 year
        if (!headers_sent()) {
            setcookie('aitwc_language', $lang, time() + (365 * DAY_IN_SECONDS), '/');
        }

        wp_send_json_success(array('language' => $lang));
    }

    /**
     * Check if a post has cached translation
     */
    public function get_translation_status() {
        check_ajax_referer('aitwc_frontend_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        $cache = new AITWC_Translation_Cache();
        $translation = $cache->get_post_translation($post_id, $target_lang);

        wp_send_json_success(array(
            'has_translation' => !empty($translation),
            'is_complete'     => !empty($translation) && $translation->is_complete,
        ));
    }
}
