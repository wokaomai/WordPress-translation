/**
 * AI Translator - Frontend Language Switcher JavaScript
 * Handles dropdown toggle, language switching, and cookie management
 */
(function($) {
    'use strict';

    var AITWC_Switcher = {

        init: function() {
            this.replaceMenuItemPlaceholders();
            this.bindEvents();
            this.setInitialState();
        },

        /**
         * Find any menu items that look like our switcher placeholder and
         * replace them with the real switcher HTML.
         *
         * Why JS: many themes use a custom Walker_Nav_Menu and skip the
         *   walker_nav_menu_start_el filter, so PHP-side replacement is not
         *   reliable across themes. This DOM-level replacement is theme
         *   agnostic and works even with Elementor/Bricks/etc. headers.
         */
        replaceMenuItemPlaceholders: function() {
            var url = aitwc_front.menu_item_url || '#aitwc-language-switcher';
            var cls = aitwc_front.menu_item_class || 'aitwc-language-switcher-menu-item';

            // Build candidate selectors:
            //   - <a href="#aitwc-language-switcher">
            //   - <li class="...aitwc-language-switcher-menu-item...">
            //   - <li class="...menu-item-aitwc-language-switcher-menu-item...">
            //     (WP often prefixes user CSS class with 'menu-item-')
            var $candidates = $('a[href="' + url + '"]').closest('li');
            $candidates = $candidates.add('li.' + cls);
            $candidates = $candidates.add('li.menu-item-' + cls);

            if (!$candidates.length) {
                return;
            }

            // Replace the inner content of each candidate <li> with the
            // pre-rendered switcher HTML. Keep the <li> wrapper so the
            // theme's nav layout (flex/grid) keeps working.
            var html = aitwc_front.switcher_html || '';
            if (!html) {
                return;
            }

            $candidates.each(function () {
                var $li = $(this);
                // Avoid double-replacement
                if ($li.find('.aitwc-switcher').length) {
                    return;
                }
                $li.html(html).addClass('aitwc-menu-item');
            });
        },

        bindEvents: function() {
            // Toggle dropdown
            $(document).on('click', '.aitwc-switcher-btn', this.toggleDropdown);

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.aitwc-switcher').length) {
                    $('.aitwc-switcher').removeClass('open');
                }
            });

            // Language selection
            $(document).on('click', '.aitwc-dropdown-item a', this.onLanguageSelect);

            // Keyboard navigation
            $(document).on('keydown', '.aitwc-switcher', this.handleKeyboard);
        },

        setInitialState: function() {
            // Set cookie if language is in URL but not in cookie
            var currentLang = aitwc_front.current_lang;
            if (currentLang && currentLang !== aitwc_front.source_lang) {
                this.setCookie('aitwc_language', currentLang, 365);
            }
        },

        toggleDropdown: function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $switcher = $(this).closest('.aitwc-switcher');
            $switcher.toggleClass('open');
            $(this).attr('aria-expanded', $switcher.hasClass('open'));
        },

        onLanguageSelect: function(e) {
            e.preventDefault();
            var $link = $(this);
            var lang = $link.data('lang');
            var url = $link.attr('href');

            // Set cookie
            AITWC_Switcher.setCookie('aitwc_language', lang, 365);

            // Set AJAX preference
            $.ajax({
                url: aitwc_front.ajax_url,
                type: 'POST',
                data: {
                    action: 'aitwc_set_language',
                    nonce: aitwc_front.nonce,
                    language: lang
                }
            });

            // Navigate to the translated URL
            if (url) {
                window.location.href = url;
            } else {
                window.location.reload();
            }
        },

        handleKeyboard: function(e) {
            var $switcher = $(this);
            var $items = $switcher.find('.aitwc-dropdown-item a');
            var $focused = $items.filter(':focus');
            var index = $items.index($focused);

            switch(e.keyCode) {
                case 27: // Escape
                    $switcher.removeClass('open');
                    $switcher.find('.aitwc-switcher-btn').focus();
                    break;
                case 40: // Down
                    e.preventDefault();
                    if (!$switcher.hasClass('open')) {
                        $switcher.addClass('open');
                    }
                    if (index < $items.length - 1) {
                        $items.eq(index + 1).focus();
                    } else {
                        $items.eq(0).focus();
                    }
                    break;
                case 38: // Up
                    e.preventDefault();
                    if (index > 0) {
                        $items.eq(index - 1).focus();
                    } else {
                        $items.eq($items.length - 1).focus();
                    }
                    break;
                case 13: // Enter
                    if ($focused.length) {
                        $focused.trigger('click');
                    }
                    break;
            }
        },

        setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + (value || '') + expires + '; path=/';
        },

        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
    };

    $(document).ready(function() {
        AITWC_Switcher.init();
    });

})(jQuery);
