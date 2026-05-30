=== Custom Cookie CMP ===
Contributors: vanochumak
Tags: cookie, gdpr, consent, cookie-banner, google-consent-mode
Requires at least: 5.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight cookie consent banner for WordPress with Google Consent Mode v2 support.

== Description ==

Custom Cookie CMP is a lightweight and modern cookie consent plugin for WordPress. It helps you comply with GDPR and other privacy regulations while integrating seamlessly with Google Consent Mode v2.

It displays a customizable cookie banner and a preferences popup where visitors can manage consent by category. Consent choices are stored for a configurable period and can be updated at any time.

Built with performance in mind — no external dependencies, no unnecessary scripts.

**Features**

* Clean and customizable cookie consent banner
* Google Consent Mode v2 integration
* Granular cookie categories (Functional, Marketing, Analytics, Preferences)
* Configurable consent expiry period
* Modern preferences popup with toggle controls
* Lightweight and fast — no external libraries
* Translation-ready (WPML / Polylang compatible)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/custom-cookie-cmp/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. Go to **Settings → Cookie CMP** to configure the banner

== Frequently Asked Questions ==

= Does this plugin support Google Consent Mode v2? =
Yes. The plugin integrates with Google Consent Mode v2 and updates consent states based on user choices.

= Is this plugin GDPR compliant? =
The plugin provides the necessary UI and consent management tools. Final compliance depends on your website configuration and legal requirements.

= Can I customize the banner appearance? =
Yes. You can customize banner text, colors, buttons, layout, and consent expiry directly from the admin panel.

= Is it translation-ready? =
Yes. The plugin is fully translation-ready and compatible with WPML and Polylang.

== Screenshots ==

1. Powerful and clean admin dashboard with full control over banner behavior and consent settings.
2. Multilingual text management with structured tabs for Banner, Popup, Buttons, and Categories.
3. Elegant and responsive frontend cookie preferences modal with granular consent controls.
4. Clean and customizable cookie consent banner with Accept, Decline, and Manage options.

== Changelog ==

= 1.3.3 =
* Added "Mobile: buttons inline with text" option — when enabled, banner buttons stay on the right side in the same row as the text on mobile screens

= 1.3.2 =
* Fix: text fields in the Texts section were not saved on sites without Polylang or WPML — locale key case mismatch (en_US vs en_us) caused saved values to be unreadable

= 1.3.1 =
* CSS: fixed bottom-left and bottom-right banner positions on mobile (equal 15px margins on both sides)
* CSS: removed banner_inline_layout option; changed banner border radius default from 4 to 0
* Build: added npm-based minification for CSS and JS (clean-css-cli + terser + onchange)

= 1.3.0 =
* Added "center (floating)" banner position
* Added banner border radius setting
* Added banner bottom offset setting
* Added banner inline layout option (text and buttons in one row)
* Added banner padding, font size, inner width and inner side padding settings
* Added button min-width setting
* Added hide "Decline all" button option
* Added hide banner title option
* Added banner link color field
* CSS: switched banner inner layout from grid to flex for better responsiveness
* CSS: new CSS variables for all new styling options

= 1.2.3 =
* Performance: frontend banner CSS and JS are now skipped for visitors who already have a saved consent cookie
* Google Consent Mode: stored consent choices are now restored via a lightweight inline script on repeat visits, ensuring gtag("consent","update") fires correctly even without the full UI
* Added customcookiecmp_should_load_frontend_assets() helper for themes and plugins
* Version and asset cache-busting bumped to 1.2.3

= 1.2.0 =
* Added configurable consent expiry
* Added Reset to defaults functionality
* Improved admin UI structure
* Fixed options cache issue

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.3.3 =
New option: keep banner buttons inline with text on mobile. Enable in Settings → Cookie CMP → General Settings.

= 1.3.2 =
Bug fix: texts entered in the Banner, Popup, Buttons and Categories fields were not saved on plain WordPress installations (without Polylang or WPML). Update immediately if you use the Texts section.

= 1.3.1 =
Bug fix: corrected bottom-left and bottom-right banner alignment on mobile screens.

= 1.3.0 =
Significant banner customization update: new layout options, positioning controls, and visual settings for the cookie banner. Fully backward compatible — existing settings remain unchanged.

= 1.2.3 =
Performance improvement: banner CSS and JS are no longer loaded for returning visitors who have already made a consent choice. Google Consent Mode state is still correctly restored on every page load.

= 1.2.0 =
Improved stability and added new configuration options.