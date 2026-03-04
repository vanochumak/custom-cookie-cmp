<?php

/**
 * Plugin Name: Custom Cookie CMP
 * Description: Lightweight Cookie Consent Management Platform with Google Consent Mode v2 support, customizable banner and popup, multilingual texts via Polylang and WPML.
 * Version: 1.2.0
 * Author: Ivan Chumak
 * Text Domain: custom-cookie-cmp
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
   exit;
}

// ---- Donation notice config ----
// Comment out the line below to disable the donation notice on your own sites
define('CCC_DONATION_NOTICE', true);
define('CCC_DONATION_URL', 'https://ko-fi.com/vanochumak');

class Custom_Cookie_CMP
{
   const OPTION_KEY = 'custom_cookie_cmp_options';
   const VERSION    = '1.2.0';


   private static $instance = null;
   private $options_cache   = null;

   public static function instance()
   {
      if (null === self::$instance) {
         self::$instance = new self();
      }
      return self::$instance;
   }

   private function __construct()
   {
      add_action('plugins_loaded', array($this, 'load_textdomain'));
      add_action('admin_menu', array($this, 'register_settings_page'));
      add_action('admin_init', array($this, 'register_settings'));
      add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

      add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
      add_action('wp_footer', array($this, 'render_banner'));

      add_action('wp_head', array($this, 'print_initial_consent_mode'), 5);

      add_action('admin_post_ccc_reset_defaults', array($this, 'handle_reset_defaults'));

      add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));

      add_action('admin_notices', array($this, 'render_donation_notice'));
      add_action('wp_ajax_ccc_dismiss_donation', array($this, 'handle_dismiss_donation'));
   }

   public function add_settings_link($links)
   {
      $url = admin_url('options-general.php?page=custom-cookie-cmp');
      array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'custom-cookie-cmp') . '</a>');
      return $links;
   }

   public function render_donation_notice()
   {
      // To disable: comment out define('CCC_DONATION_NOTICE', true) at the top of the file
      if (! defined('CCC_DONATION_NOTICE') || ! CCC_DONATION_NOTICE) {
         return;
      }

      $screen = get_current_screen();
      if (! $screen || $screen->id !== 'settings_page_custom-cookie-cmp') {
         return;
      }

      $user_id      = get_current_user_id();
      $dismissed_at = (int) get_user_meta($user_id, 'ccc_donation_dismissed', true);

      if ($dismissed_at && (time() - $dismissed_at) < (15 * DAY_IN_SECONDS)) {
         return;
      }
?>
      <div class="notice notice-info ccc-donation-notice" id="ccc-donation-notice" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:12px 16px;">
         <p style="margin:0;">
            <?php esc_html_e('Custom Cookie CMP is free and actively maintained. If it saves you time or helps your clients, consider supporting its development ☕', 'custom-cookie-cmp'); ?>
         </p>
         <span style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
            <a href="<?php echo esc_url(CCC_DONATION_URL); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
               <?php esc_html_e('Support Development on Ko-fi', 'custom-cookie-cmp'); ?>
            </a>
            <button type="button" class="button ccc-donation-dismiss" data-nonce="<?php echo esc_attr(wp_create_nonce('ccc_dismiss_donation')); ?>">
               <?php esc_html_e('Dismiss', 'custom-cookie-cmp'); ?>
            </button>
         </span>
      </div>
   <?php
   }

   public function handle_dismiss_donation()
   {
      check_ajax_referer('ccc_dismiss_donation', 'nonce');
      update_user_meta(get_current_user_id(), 'ccc_donation_dismissed', time());
      wp_send_json_success();
   }

   public function load_textdomain()
   {
   }

   public function get_locale_code()
   {
      if (function_exists('pll_current_language')) {
         return pll_current_language('slug');
      }
      if (defined('ICL_LANGUAGE_CODE')) {
         return ICL_LANGUAGE_CODE;
      }
      return determine_locale();
   }

   private function get_supported_locales()
   {
      $locales = array();

      // 1. Polylang (if active)
      if (function_exists('pll_languages_list')) {
         $locales = pll_languages_list(array('fields' => 'slug')); // We take slangs (en, uk)
      }
      // 2. WPML (if active)
      elseif (defined('ICL_SITEPRESS_VERSION') && function_exists('icl_get_languages')) {
         $langs = icl_get_languages('skip_missing=0');
         if (! empty($langs)) {
            $locales = array_keys($langs);
         }
      }

      // 3. If there is no language plugin — use the current site locale
      if (empty($locales)) {
         $locales = array(get_locale());
      }

      return array_unique($locales);
   }


   public function get_options()
   {
      $defaults = array(
         'enabled'           => 1,
         'hide_manage_btn'   => 0,
         'banner_width'      => '',
         'consent_expiry'    => 365,
         'active_cats'       => array( // Which categories to show
            'marketing'   => 1,
            'performance' => 1,
            'preferences' => 1,
         ),
         'position'          => 'bottom-full',
         'colors'            => array(
            'banner_bg'   => '#222222',
            'banner_text' => '#ffffff',
            'btn_primary' => '#ffffff',
            'btn_primary_text' => '#000000',
            'btn_secondary' => '#444444',
            'btn_secondary_text' => '#ffffff',
            // NEW COLORS FOR POP
            'popup_btn_save_bg'   => '#2271b1', // Blue for Save
            'popup_btn_save_text' => '#ffffff',
            'popup_btn_decline_bg' => '#f0f0f1', // Light for Decline
            'popup_btn_decline_text' => '#2c3338',
            'popup_btn_accept_bg' => '#ffffff', // Outline for Accept all
            'popup_btn_accept_text' => '#2271b1',
         ),
      );

      if (null !== $this->options_cache) {
         return $this->options_cache;
      }

      $options = get_option(self::OPTION_KEY, array());
      $options = wp_parse_args($options, $defaults);

      // Merge nested arrays if they are not in the saved ones
      $options['active_cats'] = wp_parse_args($options['active_cats'] ?? [], $defaults['active_cats']);
      $options['colors']      = wp_parse_args($options['colors'] ?? [], $defaults['colors']);

      $this->options_cache = $options;
      return $options;
   }

   public function admin_assets($hook)
   {
      $screen = get_current_screen();
      if (! $screen || $screen->id !== 'settings_page_custom-cookie-cmp') {
         return;
      }

      wp_enqueue_style(
         'ccc-admin',
         plugins_url('assets/admin.css', __FILE__),
         array(),
         self::VERSION
      );

      wp_enqueue_script(
         'ccc-admin',
         plugins_url('assets/admin.js', __FILE__),
         array('jquery', 'wp-color-picker'),
         self::VERSION,
         true
      );

      wp_enqueue_style('wp-color-picker');
   }

   public function get_texts($locale = '')
   {
      if (! $locale) {
         $locale = $this->get_locale_code();
      }

      $options = $this->get_options();
      $texts   = isset($options['texts_' . $locale]) ? $options['texts_' . $locale] : array();

      $defaults = array(
         'banner_title'       => __('Cookie Preferences', 'custom-cookie-cmp'),
         'banner_description' => __('We use cookies to optimize our website and service. You can manage your preferences.', 'custom-cookie-cmp'),
         'btn_accept_all'     => __('Accept all', 'custom-cookie-cmp'),
         'btn_decline_all'    => __('Decline all', 'custom-cookie-cmp'),
         'btn_manage'         => __('Manage cookies', 'custom-cookie-cmp'),
         'popup_title'        => __('Cookie Preferences', 'custom-cookie-cmp'),
         'popup_description'  => __('Select which types of cookies you want to allow. You can change these settings at any time.', 'custom-cookie-cmp'),
         'btn_save'           => __('Save my choices', 'custom-cookie-cmp'),

         'cat_functional_title'       => __('Functional', 'custom-cookie-cmp'),
         'cat_functional_description' => __('Required for the website to function properly and cannot be disabled.', 'custom-cookie-cmp'),

         'cat_marketing_title'       => __('Marketing, Advertising and Social Media', 'custom-cookie-cmp'),
         'cat_marketing_description' => __('Used to show you relevant ads and allow interaction with social media platforms.', 'custom-cookie-cmp'),

         'cat_performance_title'       => __('Performance and Analytics', 'custom-cookie-cmp'),
         'cat_performance_description' => __('Help us understand how visitors interact with the website.', 'custom-cookie-cmp'),

         'cat_preferences_title'       => __('Preferences', 'custom-cookie-cmp'),
         'cat_preferences_description' => __('Remember your choices and personalize your experience.', 'custom-cookie-cmp'),
      );

      $texts = wp_parse_args($texts, $defaults);

      return $texts;
   }

   private function get_consent_defaults()
   {
      return array(
         'ad_storage'              => 'denied',
         'analytics_storage'       => 'denied',
         'ad_user_data'            => 'denied',
         'ad_personalization'      => 'denied',
         'functionality_storage'   => 'granted',
         'personalization_storage' => 'denied',
      );
   }

   public function handle_reset_defaults()
   {
      if (! current_user_can('manage_options')) {
         wp_die(esc_html__('You do not have sufficient permissions.', 'custom-cookie-cmp'));
      }
      check_admin_referer('ccc_reset_defaults');

      delete_option(self::OPTION_KEY);
      $this->options_cache = null;

      wp_safe_redirect(add_query_arg(
         array('page' => 'custom-cookie-cmp', 'ccc_reset' => '1', '_wpnonce' => wp_create_nonce('ccc_reset_notice')),
         admin_url('options-general.php')
      ));
      exit;
   }

   /* ----------Admin settings ---------- */

   public function register_settings_page()
   {
      add_options_page(
         __('Cookie CMP', 'custom-cookie-cmp'),
         __('Cookie CMP', 'custom-cookie-cmp'),
         'manage_options',
         'custom-cookie-cmp',
         array($this, 'render_settings_page')
      );
   }

   public function register_settings()
   {
      register_setting(
         'custom_cookie_cmp_group',
         self::OPTION_KEY,
         array($this, 'sanitize_options')
      );

      add_settings_section(
         'custom_cookie_cmp_main',
         __('General Settings', 'custom-cookie-cmp'),
         '__return_false',
         'custom-cookie-cmp'
      );

      add_settings_field(
         'enabled',
         __('Enable banner', 'custom-cookie-cmp'),
         array($this, 'field_enabled'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_field(
         'banner_width',
         __('Banner max-width', 'custom-cookie-cmp'),
         array($this, 'field_banner_width'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_field(
         'consent_expiry',
         __('Consent expiry (days)', 'custom-cookie-cmp'),
         array($this, 'field_consent_expiry'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_field(
         'hide_manage_btn',
         __('Hide "Manage cookies" button', 'custom-cookie-cmp'),
         array($this, 'field_hide_manage'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_field(
         'active_cats',
         __('Active Categories', 'custom-cookie-cmp'),
         array($this, 'field_active_cats'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_field(
         'position',
         __('Banner position', 'custom-cookie-cmp'),
         array($this, 'field_position'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_field(
         'colors',
         __('Colors', 'custom-cookie-cmp'),
         array($this, 'field_colors'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_main'
      );

      add_settings_section(
         'custom_cookie_cmp_texts',
         __('Texts per language', 'custom-cookie-cmp'),
         array($this, 'section_texts_desc'),
         'custom-cookie-cmp'
      );

      add_settings_field(
         'texts',
         __('Texts', 'custom-cookie-cmp'),
         array($this, 'field_texts'),
         'custom-cookie-cmp',
         'custom_cookie_cmp_texts'
      );
   }

   public function field_banner_width()
   {
      $options = $this->get_options();
   ?>
      <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[banner_width]" value="<?php echo esc_attr($options['banner_width'] ?? ''); ?>" placeholder="e.g. 1200px or 80%">
      <p class="description"><?php esc_html_e('Set max-width for the banner content. Leave empty for full width.', 'custom-cookie-cmp'); ?></p>
   <?php
   }

   public function field_consent_expiry()
   {
      $options = $this->get_options();
   ?>
      <input type="number" class="small-text" min="1" max="730"
         name="<?php echo esc_attr(self::OPTION_KEY); ?>[consent_expiry]"
         value="<?php echo esc_attr($options['consent_expiry']); ?>">
      <p class="description"><?php esc_html_e('How many days to store the consent cookie (1–730). Default: 365.', 'custom-cookie-cmp'); ?></p>
   <?php
   }

   public function sanitize_options($input)
   {
      $output = $this->get_options();

      $output['enabled']         = empty($input['enabled']) ? 0 : 1;
      $output['hide_manage_btn'] = empty($input['hide_manage_btn']) ? 0 : 1;
      $output['banner_width']    = sanitize_text_field($input['banner_width'] ?? '');
      $output['consent_expiry']  = max(1, min(730, (int) ($input['consent_expiry'] ?? 365)));

      // Categories
      $output['active_cats']['marketing']   = !empty($input['active_cats']['marketing']) ? 1 : 0;
      $output['active_cats']['performance'] = !empty($input['active_cats']['performance']) ? 1 : 0;
      $output['active_cats']['preferences'] = !empty($input['active_cats']['preferences']) ? 1 : 0;

      $allowed_positions = array('bottom-full', 'bottom-left', 'bottom-right');
      $output['position'] = in_array($input['position'] ?? '', $allowed_positions, true)
         ? $input['position']
         : 'bottom-full';

      if (isset($input['colors']) && is_array($input['colors'])) {
         foreach ($output['colors'] as $key => $default) {
            if (isset($input['colors'][$key])) {
               $output['colors'][$key] = sanitize_hex_color($input['colors'][$key]) ?: $default;
            }
         }
      }

      if (isset($input['texts']) && is_array($input['texts'])) {
         $sanitized_texts = array();
         foreach ($input['texts'] as $locale => $texts) {
            $locale = sanitize_key($locale);
            if (empty($locale)) {
               continue;
            }
            foreach ($texts as $k => $v) {
               $sanitized_texts[$locale][$k] = wp_kses_post($v);
            }
         }
         foreach ($sanitized_texts as $locale => $texts) {
            $output['texts_' . $locale] = $texts;
         }
      }

      $this->options_cache = null;

      return $output;
   }

   public function field_enabled()
   {
      $options = $this->get_options();
   ?>
      <label>
         <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($options['enabled'], 1); ?> />
         <?php esc_html_e('Show cookie banner on the site', 'custom-cookie-cmp'); ?>
      </label>
   <?php
   }

   public function field_hide_manage()
   {
      $options = $this->get_options();
   ?>
      <label>
         <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[hide_manage_btn]" value="1" <?php checked($options['hide_manage_btn'], 1); ?> />
         <?php esc_html_e('Hide "Manage cookies" button on the banner', 'custom-cookie-cmp'); ?>
      </label>
   <?php
   }

   public function field_active_cats()
   {
      $options = $this->get_options();
      $cats    = $options['active_cats'];
   ?>
      <fieldset>
         <label style="display:block; margin-bottom:5px;">
            <input type="checkbox" checked disabled>
            Functional (<?php esc_html_e('Always active', 'custom-cookie-cmp'); ?>)
         </label>
         <label style="display:block; margin-bottom:5px;">
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[active_cats][marketing]" value="1" <?php checked($cats['marketing'], 1); ?> />
            Marketing, Advertising
         </label>
         <label style="display:block; margin-bottom:5px;">
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[active_cats][performance]" value="1" <?php checked($cats['performance'], 1); ?> />
            Performance, Analytics
         </label>
         <label style="display:block; margin-bottom:5px;">
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[active_cats][preferences]" value="1" <?php checked($cats['preferences'], 1); ?> />
            Preferences
         </label>
      </fieldset>
   <?php
   }

   public function field_position()
   {
      $options = $this->get_options();
   ?>
      <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[position]">
         <option value="bottom-full" <?php selected($options['position'], 'bottom-full'); ?>>
            <?php esc_html_e('Bottom full width', 'custom-cookie-cmp'); ?>
         </option>
         <option value="bottom-left" <?php selected($options['position'], 'bottom-left'); ?>>
            <?php esc_html_e('Bottom left', 'custom-cookie-cmp'); ?>
         </option>
         <option value="bottom-right" <?php selected($options['position'], 'bottom-right'); ?>>
            <?php esc_html_e('Bottom right', 'custom-cookie-cmp'); ?>
         </option>
      </select>
   <?php
   }

   public function field_colors()
   {
      $options = $this->get_options();
      $colors  = $options['colors'];
   ?>
      <div class="ccc-colors-grid">

         <!-- BANNER COLORS -->
         <div class="ccc-colors-col">
            <div class="ccc-color-section"><?php esc_html_e('Banner Colors', 'custom-cookie-cmp'); ?></div>

            <div class="ccc-color-field">
               <label><?php esc_html_e('Banner background', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][banner_bg]" value="<?php echo esc_attr($colors['banner_bg']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Banner text', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][banner_text]" value="<?php echo esc_attr($colors['banner_text']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Primary Button (Accept)', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][btn_primary]" value="<?php echo esc_attr($colors['btn_primary']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Primary Button Text', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][btn_primary_text]" value="<?php echo esc_attr($colors['btn_primary_text']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Secondary Button (Decline/Manage)', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][btn_secondary]" value="<?php echo esc_attr($colors['btn_secondary']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Secondary Button Text', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][btn_secondary_text]" value="<?php echo esc_attr($colors['btn_secondary_text']); ?>">
            </div>
         </div>

         <!-- POPUP BUTTON COLORS -->
         <div class="ccc-colors-col">
            <div class="ccc-color-section"><?php esc_html_e('Popup Button Colors', 'custom-cookie-cmp'); ?></div>

            <div class="ccc-color-field">
               <label><?php esc_html_e('Save my choices Background', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][popup_btn_save_bg]" value="<?php echo esc_attr($colors['popup_btn_save_bg']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Save my choices Text', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][popup_btn_save_text]" value="<?php echo esc_attr($colors['popup_btn_save_text']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Decline all Background', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][popup_btn_decline_bg]" value="<?php echo esc_attr($colors['popup_btn_decline_bg']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Decline all Text', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][popup_btn_decline_text]" value="<?php echo esc_attr($colors['popup_btn_decline_text']); ?>">
            </div>
            <div class="ccc-color-field">
               <label><?php esc_html_e('Accept all Border/Text Color', 'custom-cookie-cmp'); ?></label>
               <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[colors][popup_btn_accept_text]" value="<?php echo esc_attr($colors['popup_btn_accept_text']); ?>">
            </div>
         </div>

      </div>
      <?php
   }



   public function section_texts_desc()
   {
      echo '<p class="description-info">' . esc_html__('Define banner and popup texts per language.', 'custom-cookie-cmp') . '</p>';
   }

   public function field_texts()
   {
      $options = $this->get_options();
      $locales = $this->get_supported_locales();

      foreach ($locales as $i => $locale) :
         $texts        = isset($options['texts_' . $locale]) ? $options['texts_' . $locale] : $this->get_texts($locale);
         $eid          = 'ccc_' . preg_replace('/[^a-z0-9]/', '_', strtolower($locale));
         $field_prefix = self::OPTION_KEY . '[texts][' . $locale . ']';
         $tabs         = array(
            'banner'  => __('Banner', 'custom-cookie-cmp'),
            'popup'   => __('Popup', 'custom-cookie-cmp'),
            'buttons' => __('Buttons', 'custom-cookie-cmp'),
            'cats'    => __('Categories', 'custom-cookie-cmp'),
         );
      ?>
         <details class="ccc-locale-block" <?php echo $i === 0 ? 'open' : ''; ?>>
            <summary class="ccc-locale-summary">
               <span><?php echo esc_html(strtoupper($locale)); ?></span>
            </summary>
            <div class="ccc-locale-body">

               <nav class="ccc-tabs-nav">
                  <?php foreach ($tabs as $tab_key => $tab_label) : ?>
                     <button type="button" class="ccc-tab-btn <?php echo $tab_key === 'banner' ? 'is-active' : ''; ?>"
                        data-target="<?php echo esc_attr($eid . '-' . $tab_key); ?>">
                        <?php echo esc_html($tab_label); ?>
                     </button>
                  <?php endforeach; ?>
               </nav>

               <div class="ccc-tab-panel is-active" id="<?php echo esc_attr($eid); ?>-banner">
                  <p class="ccc-field-row">
                     <label class="ccc-field-label"><?php esc_html_e('Title', 'custom-cookie-cmp'); ?></label>
                     <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[banner_title]" value="<?php echo esc_attr($texts['banner_title']); ?>">
                  </p>
                  <div class="ccc-field-row">
                     <label class="ccc-field-label"><?php esc_html_e('Description', 'custom-cookie-cmp'); ?></label>
                     <?php wp_editor(wp_kses_post($texts['banner_description']), $eid . '_banner', array(
                        'textarea_name' => $field_prefix . '[banner_description]',
                        'media_buttons' => false,
                        'textarea_rows' => 4,
                        'quicktags'     => true,
                        'tinymce'       => array('toolbar1' => 'bold,italic,link,unlink,|,undo,redo', 'toolbar2' => ''),
                     )); ?>
                  </div>
               </div>

               <div class="ccc-tab-panel" id="<?php echo esc_attr($eid); ?>-popup">
                  <p class="ccc-field-row">
                     <label class="ccc-field-label"><?php esc_html_e('Title', 'custom-cookie-cmp'); ?></label>
                     <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[popup_title]" value="<?php echo esc_attr($texts['popup_title']); ?>">
                  </p>
                  <div class="ccc-field-row">
                     <label class="ccc-field-label"><?php esc_html_e('Description', 'custom-cookie-cmp'); ?></label>
                     <?php wp_editor(wp_kses_post($texts['popup_description']), $eid . '_popup', array(
                        'textarea_name' => $field_prefix . '[popup_description]',
                        'media_buttons' => false,
                        'textarea_rows' => 3,
                        'quicktags'     => true,
                        'tinymce'       => array('toolbar1' => 'bold,italic,link,unlink,|,undo,redo', 'toolbar2' => ''),
                     )); ?>
                  </div>
               </div>

               <div class="ccc-tab-panel" id="<?php echo esc_attr($eid); ?>-buttons">
                  <div class="ccc-texts-buttons-grid">
                     <p class="ccc-field-row">
                        <label class="ccc-field-label"><?php esc_html_e('Accept all', 'custom-cookie-cmp'); ?></label>
                        <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[btn_accept_all]" value="<?php echo esc_attr($texts['btn_accept_all']); ?>">
                     </p>
                     <p class="ccc-field-row">
                        <label class="ccc-field-label"><?php esc_html_e('Decline all', 'custom-cookie-cmp'); ?></label>
                        <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[btn_decline_all]" value="<?php echo esc_attr($texts['btn_decline_all']); ?>">
                     </p>
                     <p class="ccc-field-row">
                        <label class="ccc-field-label"><?php esc_html_e('Manage cookies', 'custom-cookie-cmp'); ?></label>
                        <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[btn_manage]" value="<?php echo esc_attr($texts['btn_manage']); ?>">
                     </p>
                     <p class="ccc-field-row">
                        <label class="ccc-field-label"><?php esc_html_e('Save my choices', 'custom-cookie-cmp'); ?></label>
                        <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[btn_save]" value="<?php echo esc_attr($texts['btn_save']); ?>">
                     </p>
                  </div>
               </div>

               <div class="ccc-tab-panel" id="<?php echo esc_attr($eid); ?>-cats">
                  <div class="ccc-categories-grid">
                     <?php
                     $categories = array(
                        'functional'  => __('Functional', 'custom-cookie-cmp'),
                        'marketing'   => __('Marketing', 'custom-cookie-cmp'),
                        'performance' => __('Performance', 'custom-cookie-cmp'),
                        'preferences' => __('Preferences', 'custom-cookie-cmp'),
                     );
                     foreach ($categories as $cat_key => $cat_label) : ?>
                        <div class="ccc-category-card">
                           <span class="ccc-category-badge"><?php echo esc_html($cat_label); ?></span>
                           <p class="ccc-field-row">
                              <label class="ccc-field-label"><?php esc_html_e('Title', 'custom-cookie-cmp'); ?></label>
                              <input type="text" class="large-text" name="<?php echo esc_attr($field_prefix); ?>[cat_<?php echo esc_attr($cat_key); ?>_title]" value="<?php echo esc_attr($texts['cat_' . $cat_key . '_title']); ?>">
                           </p>
                           <p class="ccc-field-row">
                              <label class="ccc-field-label"><?php esc_html_e('Description', 'custom-cookie-cmp'); ?></label>
                              <textarea class="large-text" name="<?php echo esc_attr($field_prefix); ?>[cat_<?php echo esc_attr($cat_key); ?>_description]" rows="2"><?php echo esc_textarea($texts['cat_' . $cat_key . '_description']); ?></textarea>
                           </p>
                        </div>
                     <?php endforeach; ?>
                  </div>
               </div>

            </div>
         </details>
      <?php
      endforeach;
   }

   public function render_settings_page()
   {
      ?>
      <div class="wrap ccc-admin-wrap">
         <h1><?php esc_html_e('Custom Cookie CMP', 'custom-cookie-cmp'); ?></h1>

         <?php if (isset($_GET['ccc_reset']) && $_GET['ccc_reset'] === '1' && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'ccc_reset_notice')) : ?>
            <div class="notice notice-success is-dismissible">
               <p><?php esc_html_e('Settings have been reset to defaults.', 'custom-cookie-cmp'); ?></p>
            </div>
         <?php endif; ?>

         <div class="ccc-admin-layout">
            <div class="ccc-admin-main">
               <form id="ccc-settings-form" method="post" action="options.php">
                  <?php settings_fields('custom_cookie_cmp_group'); ?>
                  <?php do_settings_sections('custom-cookie-cmp'); ?>
               </form>
            </div>
            <div class="ccc-admin-sidebar">

               <div class="ccc-admin-sidebar-box">
                  <div class="ccc-sidebar-title"><?php esc_html_e('Custom Cookie CMP', 'custom-cookie-cmp'); ?></div>
                  <div class="ccc-sidebar-body">
                     <button type="submit" form="ccc-settings-form" class="button button-primary button-large" style="width:100%;justify-content:center;">
                        <?php esc_html_e('Save Changes', 'custom-cookie-cmp'); ?>
                     </button>
                  </div>
               </div>

               <div class="ccc-admin-sidebar-box ccc-sidebar-box-reset">
                  <div class="ccc-sidebar-title"><?php esc_html_e('Reset', 'custom-cookie-cmp'); ?></div>
                  <div class="ccc-sidebar-body">
                     <p class="description" style="margin-bottom:10px;">
                        <?php esc_html_e('Restore all settings to their default values.', 'custom-cookie-cmp'); ?>
                     </p>
                     <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="ccc_reset_defaults">
                        <?php wp_nonce_field('ccc_reset_defaults'); ?>
                        <button type="submit" class="button button-link-delete"
                           onclick="return confirm('<?php echo esc_js(__('Reset all settings to defaults? This cannot be undone.', 'custom-cookie-cmp')); ?>')">
                           <?php esc_html_e('Reset to defaults', 'custom-cookie-cmp'); ?>
                        </button>
                     </form>
                  </div>
               </div>

            </div>
         </div>
      </div>
   <?php
   }


   /* ----------Frontend ---------- */

   public function enqueue_assets()
   {
      $options = $this->get_options();

      if (empty($options['enabled'])) {
         return;
      }

      wp_enqueue_style(
         'custom-cookie-cmp',
         plugins_url('assets/css/cookie-cmp.css', __FILE__),
         array(),
         self::VERSION
      );

      wp_enqueue_script(
         'custom-cookie-cmp',
         plugins_url('assets/js/cookie-cmp.js', __FILE__),
         array(),
         self::VERSION,
         true
      );

      $locale = $this->get_locale_code();
      $texts  = $this->get_texts($locale);

      $data = array(
         'position'      => $options['position'],
         'colors'        => $options['colors'],
         'hide_manage'   => !empty($options['hide_manage_btn']), // For JS
         'active_cats'   => $options['active_cats'],              // For JS
         'texts'         => $texts,
         'locale'        => $locale,
         'banner_width'  => $options['banner_width'] ?? '',
         'cookieName'      => 'ccc_consent_v2',
         'cookieExpiry'    => (int) ($options['consent_expiry'] ?? 365),
         'consentDefaults' => $this->get_consent_defaults(),
      );

      wp_localize_script('custom-cookie-cmp', 'CCC_DATA', $data);
   }

   public function render_banner()
   {
      $options = $this->get_options();
      $cats    = $options['active_cats'];

      if (empty($options['enabled'])) {
         return;
      }
   ?>
      <div id="ccc-banner" class="ccc-banner ccc-position-<?php echo esc_attr($options['position']); ?>" aria-hidden="true">
         <div class="ccc-banner-inner">
            <div class="ccc-banner-text">
               <h3 class="ccc-banner-title"></h3>
               <div class="ccc-banner-desc"></div>
            </div>
            <div class="ccc-banner-actions">
               <button type="button" class="ccc-btn ccc-btn-secondary ccc-btn-decline-all"></button>
               <!-- Manage button, if enabled -->
               <?php if (empty($options['hide_manage_btn'])): ?>
                  <button type="button" class="ccc-btn ccc-btn-secondary ccc-btn-manage"></button>
               <?php endif; ?>
               <button type="button" class="ccc-btn ccc-btn-primary ccc-btn-accept-all"></button>
            </div>
         </div>
      </div>

      <div id="ccc-popup-backdrop" class="ccc-popup-backdrop" aria-hidden="true">
         <div class="ccc-popup" role="dialog" aria-modal="true" aria-labelledby="ccc-popup-title">
            <div class="ccc-popup-header">
               <h3 id="ccc-popup-title"></h3>
               <button type="button" class="ccc-popup-close" aria-label="<?php esc_attr_e('Close', 'custom-cookie-cmp'); ?>">×</button>
            </div>
            <div class="ccc-popup-body">
               <p class="ccc-popup-description"></p>

               <div class="ccc-category ccc-category-functional">
                  <div class="ccc-category-text">
                     <h4 class="ccc-category-title"></h4>
                     <p class="ccc-category-description"></p>
                  </div>
                  <div class="ccc-category-toggle">
                     <span class="ccc-toggle-label"><?php esc_html_e('Always active', 'custom-cookie-cmp'); ?></span>
                  </div>
               </div>

               <?php if (!empty($cats['marketing'])): ?>
                  <div class="ccc-category ccc-category-marketing">
                     <div class="ccc-category-text">
                        <h4 class="ccc-category-title"></h4>
                        <p class="ccc-category-description"></p>
                     </div>
                     <div class="ccc-category-toggle">
                        <label class="ccc-switch">
                           <input type="checkbox" data-category="marketing">
                           <span class="ccc-slider"></span>
                        </label>
                     </div>
                  </div>
               <?php endif; ?>

               <?php if (!empty($cats['performance'])): ?>
                  <div class="ccc-category ccc-category-performance">
                     <div class="ccc-category-text">
                        <h4 class="ccc-category-title"></h4>
                        <p class="ccc-category-description"></p>
                     </div>
                     <div class="ccc-category-toggle">
                        <label class="ccc-switch">
                           <input type="checkbox" data-category="performance">
                           <span class="ccc-slider"></span>
                        </label>
                     </div>
                  </div>
               <?php endif; ?>

               <?php if (!empty($cats['preferences'])): ?>
                  <div class="ccc-category ccc-category-preferences">
                     <div class="ccc-category-text">
                        <h4 class="ccc-category-title"></h4>
                        <p class="ccc-category-description"></p>
                     </div>
                     <div class="ccc-category-toggle">
                        <label class="ccc-switch">
                           <input type="checkbox" data-category="preferences">
                           <span class="ccc-slider"></span>
                        </label>
                     </div>
                  </div>
               <?php endif; ?>

            </div>
            <div class="ccc-popup-footer">
               <button type="button" class="ccc-btn ccc-btn-secondary ccc-btn-decline-all"></button>
               <button type="button" class="ccc-btn ccc-btn-primary ccc-btn-save"></button>
               <button type="button" class="ccc-btn ccc-btn-primary-outline ccc-btn-accept-all"></button>
            </div>
         </div>
      </div>
   <?php
   }

   public function print_initial_consent_mode()
   {
      $options = $this->get_options();
      if (empty($options['enabled'])) {
         return;
      }
      $defaults = $this->get_consent_defaults();
   ?>
      <script>
         window.dataLayer = window.dataLayer || [];

         function gtag() {
            dataLayer.push(arguments);
         }
         gtag('consent', 'default', <?php echo wp_json_encode($defaults); ?>);
      </script>
<?php
   }
}

Custom_Cookie_CMP::instance();
