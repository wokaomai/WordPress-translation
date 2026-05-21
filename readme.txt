=== AI Translator for WooCommerce ===
Contributors: wokaomai
Tags: translation, woocommerce, ai, multilingual, gemini, deepseek, openai, claude
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered translation plugin for WooCommerce supporting Google Gemini, DeepSeek, OpenAI, and Claude. Translations are cached in database for instant page loads.

== Description ==

AI Translator for WooCommerce uses cutting-edge AI models to automatically translate your entire store into multiple languages. Translations are stored permanently in your database, so visitors get instant page loads without waiting for API calls.

**Supported AI Providers:**
* Google Gemini (Gemini Pro, 1.5 Pro, 1.5 Flash)
* DeepSeek (DeepSeek Chat, DeepSeek Reasoner)
* OpenAI (GPT-4o Mini, GPT-4o, GPT-4 Turbo)
* Anthropic Claude (Claude 3 Haiku, Sonnet, 3.5 Sonnet)

**Key Features:**
* Hybrid translation mode: serves from DB cache, translates on-the-fly if not cached
* SEO-friendly URLs with language prefix (/zh-cn/product-name/)
* Flag-based language switcher embedded in navigation bar
* Batch translation from admin panel
* Automatic hreflang tags for SEO
* WooCommerce deep integration (products, categories, cart, checkout)
* Translation statistics dashboard
* Content invalidation when posts are updated

**Supported Languages:**
* English (source)
* Chinese Simplified
* French
* Spanish
* Korean
* And many more configurable

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through the WordPress Plugins menu
3. Go to AI Translator > Settings
4. Enter your API key for your preferred AI provider
5. Select target languages
6. Use Batch Translate to pre-translate existing content

== Frequently Asked Questions ==

= How does the hybrid mode work? =
When a visitor views a page in another language, the plugin first checks the database for a cached translation. If found, it serves instantly. If not, it calls the AI API in real-time, stores the result, and serves it. Subsequent visitors get instant cached responses.

= Will translations slow down my site? =
No. Once translated, content is served directly from your database — as fast as any normal WordPress page. Only the first visitor (or batch translate) triggers the AI API call.

= Can I use multiple AI providers? =
You can configure all providers, but only one is active at a time. You can switch providers anytime from the settings.

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Gemini, DeepSeek, OpenAI, Claude
* Database-cached translations
* SEO-friendly URLs
* Navigation bar language switcher with flags
* Batch translation feature
* Translation statistics
