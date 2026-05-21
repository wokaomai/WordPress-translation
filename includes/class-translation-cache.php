<?php
/**
 * Translation Cache - Database storage for translated content
 * Translations are stored permanently so visitors get instant page loads
 */

if (!defined('ABSPATH')) {
    exit;
}

class AITWC_Translation_Cache {

    private $table_name;
    private $meta_table;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aitwc_translations';
        $this->meta_table = $wpdb->prefix . 'aitwc_translation_meta';
    }

    /**
     * Create database tables on plugin activation
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            content_hash varchar(64) NOT NULL,
            source_language varchar(10) NOT NULL,
            target_language varchar(10) NOT NULL,
            original_text longtext NOT NULL,
            translated_text longtext NOT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'text',
            post_id bigint(20) unsigned DEFAULT NULL,
            field_name varchar(100) DEFAULT NULL,
            ai_provider varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'completed',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY content_hash_lang (content_hash, target_language),
            KEY post_id (post_id),
            KEY target_language (target_language),
            KEY status (status),
            KEY content_type (content_type)
        ) {$charset_collate};";

        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->meta_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            target_language varchar(10) NOT NULL,
            translated_title text DEFAULT NULL,
            translated_content longtext DEFAULT NULL,
            translated_excerpt text DEFAULT NULL,
            translated_slug varchar(200) DEFAULT NULL,
            ai_provider varchar(50) NOT NULL,
            is_complete tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_lang (post_id, target_language),
            KEY target_language (target_language),
            KEY is_complete (is_complete)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        dbDelta($sql2);
    }

    /**
     * Get cached translation by content hash and target language
     *
     * @param string $text Original text
     * @param string $target_lang Target language
     * @return string|false Translated text or false if not cached
     */
    public function get_translation($text, $target_lang) {
        global $wpdb;

        $hash = $this->generate_hash($text);

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT translated_text FROM {$this->table_name} WHERE content_hash = %s AND target_language = %s AND status = 'completed' LIMIT 1",
            $hash,
            $target_lang
        ));

        return $result !== null ? $result : false;
    }

    /**
     * Store translation in database
     *
     * @param string $original_text Original text
     * @param string $translated_text Translated text
     * @param string $source_lang Source language
     * @param string $target_lang Target language
     * @param string $ai_provider AI provider used
     * @param array  $extra Extra data (post_id, field_name, content_type)
     * @return bool Success
     */
    public function store_translation($original_text, $translated_text, $source_lang, $target_lang, $ai_provider, $extra = array()) {
        global $wpdb;

        $hash = $this->generate_hash($original_text);

        $data = array(
            'content_hash'    => $hash,
            'source_language' => $source_lang,
            'target_language' => $target_lang,
            'original_text'   => $original_text,
            'translated_text' => $translated_text,
            'ai_provider'     => $ai_provider,
            'content_type'    => isset($extra['content_type']) ? $extra['content_type'] : 'text',
            'post_id'         => isset($extra['post_id']) ? $extra['post_id'] : null,
            'field_name'      => isset($extra['field_name']) ? $extra['field_name'] : null,
            'status'          => 'completed',
        );

        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');

        // Try to update existing, insert if new
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE content_hash = %s AND target_language = %s LIMIT 1",
            $hash,
            $target_lang
        ));

        if ($existing) {
            return $wpdb->update(
                $this->table_name,
                array(
                    'translated_text' => $translated_text,
                    'ai_provider'     => $ai_provider,
                    'status'          => 'completed',
                ),
                array('id' => $existing),
                array('%s', '%s', '%s'),
                array('%d')
            ) !== false;
        }

        return $wpdb->insert($this->table_name, $data, $format) !== false;
    }

    /**
     * Get full page/post translation from meta table
     *
     * @param int    $post_id Post ID
     * @param string $target_lang Target language
     * @return object|false Translation data or false
     */
    public function get_post_translation($post_id, $target_lang) {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->meta_table} WHERE post_id = %d AND target_language = %s AND is_complete = 1 LIMIT 1",
            $post_id,
            $target_lang
        ));

        return $result ? $result : false;
    }

    /**
     * Store full post translation
     *
     * @param int    $post_id Post ID
     * @param string $target_lang Target language
     * @param array  $translation_data Array with title, content, excerpt, slug
     * @param string $ai_provider Provider used
     * @return bool Success
     */
    public function store_post_translation($post_id, $target_lang, $translation_data, $ai_provider) {
        global $wpdb;

        $data = array(
            'post_id'            => $post_id,
            'target_language'    => $target_lang,
            'translated_title'   => isset($translation_data['title']) ? $translation_data['title'] : null,
            'translated_content' => isset($translation_data['content']) ? $translation_data['content'] : null,
            'translated_excerpt' => isset($translation_data['excerpt']) ? $translation_data['excerpt'] : null,
            'translated_slug'    => isset($translation_data['slug']) ? $translation_data['slug'] : null,
            'ai_provider'        => $ai_provider,
            'is_complete'        => 1,
        );

        // Check if exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->meta_table} WHERE post_id = %d AND target_language = %s LIMIT 1",
            $post_id,
            $target_lang
        ));

        if ($existing) {
            unset($data['post_id'], $data['target_language']);
            return $wpdb->update($this->meta_table, $data, array('id' => $existing)) !== false;
        }

        return $wpdb->insert($this->meta_table, $data) !== false;
    }

    /**
     * Delete translations for a specific post
     *
     * @param int    $post_id Post ID
     * @param string $target_lang Optional target language, delete all if empty
     */
    public function delete_post_translations($post_id, $target_lang = '') {
        global $wpdb;

        if (!empty($target_lang)) {
            $wpdb->delete($this->meta_table, array('post_id' => $post_id, 'target_language' => $target_lang));
            $wpdb->delete($this->table_name, array('post_id' => $post_id, 'target_language' => $target_lang));
        } else {
            $wpdb->delete($this->meta_table, array('post_id' => $post_id));
            $wpdb->delete($this->table_name, array('post_id' => $post_id));
        }
    }

    /**
     * Clear all translations cache
     */
    public function clear_all() {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        $wpdb->query("TRUNCATE TABLE {$this->meta_table}");
    }

    /**
     * Get translation statistics
     */
    public function get_stats() {
        global $wpdb;

        return array(
            'total_translations' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"),
            'total_posts'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->meta_table} WHERE is_complete = 1"),
            'by_language'        => $wpdb->get_results(
                "SELECT target_language, COUNT(*) as count FROM {$this->meta_table} WHERE is_complete = 1 GROUP BY target_language",
                ARRAY_A
            ),
            'by_provider'        => $wpdb->get_results(
                "SELECT ai_provider, COUNT(*) as count FROM {$this->table_name} GROUP BY ai_provider",
                ARRAY_A
            ),
        );
    }

    /**
     * Get untranslated posts for a target language
     *
     * @param string $target_lang Target language
     * @param string $post_type Post type
     * @param int    $limit Limit
     * @return array Post IDs
     */
    public function get_untranslated_posts($target_lang, $post_type = 'product', $limit = 50) {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p 
            LEFT JOIN {$this->meta_table} t ON p.ID = t.post_id AND t.target_language = %s AND t.is_complete = 1
            WHERE p.post_type = %s AND p.post_status = 'publish' AND t.id IS NULL
            LIMIT %d",
            $target_lang,
            $post_type,
            $limit
        ));
    }

    /**
     * Generate content hash
     */
    private function generate_hash($text) {
        return hash('sha256', trim($text));
    }
}
