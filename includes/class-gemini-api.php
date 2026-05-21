<?php
/**
 * Google Gemini API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AITWC_Gemini_API {

    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    private $model;

    public function __construct() {
        $this->api_key = get_option('aitwc_gemini_api_key', '');
        $this->model = get_option('aitwc_gemini_model', 'gemini-pro');
    }

    /**
     * Translate text using Gemini API
     *
     * @param string $text Text to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @return string|WP_Error Translated text or error
     */
    public function translate($text, $source_lang, $target_lang) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key is not configured.', 'ai-translator-wc'));
        }

        $languages = AI_Translator_WooCommerce::get_supported_languages();
        $source_name = isset($languages[$source_lang]) ? $languages[$source_lang]['name'] : $source_lang;
        $target_name = isset($languages[$target_lang]) ? $languages[$target_lang]['name'] : $target_lang;

        $prompt = sprintf(
            "You are a professional translator. Translate the following text from %s to %s. " .
            "Keep HTML tags intact if present. Only return the translated text, no explanations.\n\n" .
            "Text to translate:\n%s",
            $source_name,
            $target_name,
            $text
        );

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $this->model,
            $this->api_key
        );

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.3,
                'maxOutputTokens' => 4096,
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($status_code !== 200) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown API error';
            return new WP_Error('api_error', sprintf(__('Gemini API error: %s', 'ai-translator-wc'), $error_msg));
        }

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }

        return new WP_Error('parse_error', __('Failed to parse Gemini API response.', 'ai-translator-wc'));
    }

    /**
     * Check if API is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Get provider name
     */
    public function get_name() {
        return 'Google Gemini';
    }
}
