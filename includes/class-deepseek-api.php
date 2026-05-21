<?php
/**
 * DeepSeek API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AITWC_DeepSeek_API {

    private $api_key;
    private $api_url = 'https://api.deepseek.com/chat/completions';
    private $model;

    public function __construct() {
        $this->api_key = get_option('aitwc_deepseek_api_key', '');
        $this->model = get_option('aitwc_deepseek_model', 'deepseek-chat');
    }

    /**
     * Translate text using DeepSeek API
     *
     * @param string $text Text to translate
     * @param string $source_lang Source language code
     * @param string $target_lang Target language code
     * @return string|WP_Error Translated text or error
     */
    public function translate($text, $source_lang, $target_lang) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('DeepSeek API key is not configured.', 'ai-translator-wc'));
        }

        $languages = AI_Translator_WooCommerce::get_supported_languages();
        $source_name = isset($languages[$source_lang]) ? $languages[$source_lang]['name'] : $source_lang;
        $target_name = isset($languages[$target_lang]) ? $languages[$target_lang]['name'] : $target_lang;

        $system_prompt = "You are a professional translator. Translate text accurately while preserving HTML tags. Only output the translated text without any explanation.";

        $user_prompt = sprintf(
            "Translate the following text from %s to %s:\n\n%s",
            $source_name,
            $target_name,
            $text
        );

        $body = array(
            'model'    => $this->model,
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $user_prompt),
            ),
            'temperature' => 0.3,
            'max_tokens'  => 4096,
        );

        $response = wp_remote_post($this->api_url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
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
            return new WP_Error('api_error', sprintf(__('DeepSeek API error: %s', 'ai-translator-wc'), $error_msg));
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        return new WP_Error('parse_error', __('Failed to parse DeepSeek API response.', 'ai-translator-wc'));
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
        return 'DeepSeek';
    }
}
