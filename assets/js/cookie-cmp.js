(function () {
   if (typeof window.CCC_DATA === 'undefined') {
      return;
   }

   var config = window.CCC_DATA;

   /* -------- Cookie helpers -------- */

   function setCookie(name, value, days) {
      var expires = "";
      if (days) {
         var date = new Date();
         date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
         expires = "; expires=" + date.toUTCString();
      }
      document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/; SameSite=Lax";
   }

   function getCookie(name) {
      var nameEQ = name + "=";
      var ca = document.cookie.split(';');
      for (var i = 0; i < ca.length; i++) {
         var c = ca[i];
         while (c.charAt(0) == ' ') c = c.substring(1, c.length);
         if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
      }
      return null;
   }

   /* -------- Consent read/write -------- */

   function readConsent() {
      try {
         var stored = getCookie(config.cookieName);
         if (!stored) return null;
         return JSON.parse(stored);
      } catch (e) {
         return null;
      }
   }

   function saveConsent(consent, source) {
      try {
         setCookie(config.cookieName, JSON.stringify(consent), config.cookieExpiry || 365);
         try {
            localStorage.setItem(config.cookieName, JSON.stringify(consent));
         } catch (e) { }
      } catch (e) { }

      if (typeof window.dataLayer !== 'undefined' && typeof window.gtag === 'function') {
         window.gtag('consent', 'update', consent);
      }

      window.CustomCookieCMP = window.CustomCookieCMP || {};
      window.CustomCookieCMP.consent = consent;
      window.CustomCookieCMP.source = source || 'unknown';
   }

   function getInitialConsent() {
      var existing = readConsent();
      if (existing) {
         return existing;
      }
      return config.consentDefaults;
   }

   function qs(id) {
      return document.getElementById(id);
   }

   function qsa(selector, root) {
      return (root || document).querySelectorAll(selector);
   }

   /* -------- UI: texts & styles -------- */

   function applyTexts() {
      var t = config.texts;

      var banner = qs('ccc-banner');
      if (!banner) return;

      banner.querySelector('.ccc-banner-title').textContent = t.banner_title;
      banner.querySelector('.ccc-banner-desc').innerHTML = t.banner_description;

      var btnAcceptBanner = banner.querySelector('.ccc-btn-accept-all');
      var btnDeclineBanner = banner.querySelector('.ccc-btn-decline-all');
      var btnManageBanner = banner.querySelector('.ccc-btn-manage');

      if (btnAcceptBanner) btnAcceptBanner.textContent = t.btn_accept_all;
      if (btnDeclineBanner) btnDeclineBanner.textContent = t.btn_decline_all;
      if (btnManageBanner) btnManageBanner.textContent = t.btn_manage;

      var popup = qs('ccc-popup-backdrop');
      if (!popup) return;

      popup.querySelector('#ccc-popup-title').textContent = t.popup_title;
      popup.querySelector('.ccc-popup-description').innerHTML = t.popup_description;

      function setCatText(selector, title, desc) {
         var el = popup.querySelector(selector);
         if (el) {
            el.querySelector('.ccc-category-title').textContent = title;
            el.querySelector('.ccc-category-description').innerHTML = desc;
         }
      }

      setCatText('.ccc-category-functional', t.cat_functional_title, t.cat_functional_description);
      setCatText('.ccc-category-marketing', t.cat_marketing_title, t.cat_marketing_description);
      setCatText('.ccc-category-performance', t.cat_performance_title, t.cat_performance_description);
      setCatText('.ccc-category-preferences', t.cat_preferences_title, t.cat_preferences_description);

      popup.querySelector('.ccc-btn-save').textContent = t.btn_save;
      popup.querySelector('.ccc-btn-accept-all').textContent = t.btn_accept_all;
      popup.querySelector('.ccc-btn-decline-all').textContent = t.btn_decline_all;
   }

   function applyStyles() {
      var colors = config.colors || {};
      var width = config.banner_width ? config.banner_width : 'none';
      var root = document.documentElement;

      root.style.setProperty('--ccc-banner-bg', colors.banner_bg || '#222');
      root.style.setProperty('--ccc-banner-text', colors.banner_text || '#fff');
      root.style.setProperty('--ccc-btn-primary-bg', colors.btn_primary || '#fff');
      root.style.setProperty('--ccc-btn-primary-text', colors.btn_primary_text || '#000');
      root.style.setProperty('--ccc-btn-secondary-bg', colors.btn_secondary || '#444');
      root.style.setProperty('--ccc-btn-secondary-text', colors.btn_secondary_text || '#fff');
      root.style.setProperty('--ccc-popup-save-bg', colors.popup_btn_save_bg || '#2271b1');
      root.style.setProperty('--ccc-popup-save-text', colors.popup_btn_save_text || '#ffffff');
      root.style.setProperty('--ccc-popup-decline-bg', colors.popup_btn_decline_bg || '#f0f0f1');
      root.style.setProperty('--ccc-popup-decline-text', colors.popup_btn_decline_text || '#2c3338');
      root.style.setProperty('--ccc-popup-accept-color', colors.popup_btn_accept_text || '#2271b1');
      root.style.setProperty('--ccc-banner-width', width);
      root.style.setProperty('--ccc-radius', (config.btn_border_radius !== undefined ? config.btn_border_radius : 4) + 'px');
      root.style.setProperty('--ccc-popup-radius', (config.popup_border_radius !== undefined ? config.popup_border_radius : 4) + 'px');
   }

   /* -------- Banner / Popup visibility -------- */

   function showBanner() {
      var banner = qs('ccc-banner');
      if (banner) {
         banner.setAttribute('aria-hidden', 'false');
      }
   }

   function hideBanner() {
      var banner = qs('ccc-banner');
      if (banner) {
         banner.setAttribute('aria-hidden', 'true');
      }
   }

   function showPopup() {
      var popup = qs('ccc-popup-backdrop');
      if (popup) {
         popup.inert = false;
         popup.style.display = '';
         popup.setAttribute('aria-hidden', 'false');
         popup.classList.add('ccc-visible');
         document.body.classList.add('cookie-popup-open');
      }
   }

   function hidePopup() {
      var popup = qs('ccc-popup-backdrop');
      if (popup) {
         popup.inert = true;
         popup.style.display = 'none';
         popup.setAttribute('aria-hidden', 'true');
         popup.classList.remove('ccc-visible');
         if (document.activeElement) {
            document.activeElement.blur();
         }
         document.body.classList.remove('cookie-popup-open');
      }
   }

   /* -------- Consent logic -------- */

   function mapConsentFromCategories(state) {
      var consent = {
         ad_storage: 'denied',
         analytics_storage: 'denied',
         ad_user_data: 'denied',
         ad_personalization: 'denied',
         functionality_storage: 'granted',
         personalization_storage: 'denied'
      };

      if (state.marketing) {
         consent.ad_storage = 'granted';
         consent.ad_user_data = 'granted';
         consent.ad_personalization = 'granted';
      }
      if (state.performance) {
         consent.analytics_storage = 'granted';
      }
      if (state.preferences) {
         consent.personalization_storage = 'granted';
      }

      return consent;
   }

   function getStateFromConsent(consent) {
      consent = consent || getInitialConsent();
      return {
         marketing: consent.ad_storage === 'granted',
         performance: consent.analytics_storage === 'granted',
         preferences: consent.personalization_storage === 'granted',
         functional: true
      };
   }

   function syncTogglesFromState(state) {
      qsa('#ccc-popup-backdrop input[data-category]').forEach(function (input) {
         var cat = input.getAttribute('data-category');
         if (state.hasOwnProperty(cat)) {
            input.checked = !!state[cat];
         }
      });
   }

   function getStateFromToggles() {
      var state = {
         marketing: false,
         performance: false,
         preferences: false,
         functional: true
      };

      var marketingInput = document.querySelector('.ccc-category-marketing input[type="checkbox"]');
      var performanceInput = document.querySelector('.ccc-category-performance input[type="checkbox"]');
      var preferencesInput = document.querySelector('.ccc-category-preferences input[type="checkbox"]');

      if (marketingInput) state.marketing = marketingInput.checked;
      if (performanceInput) state.performance = performanceInput.checked;
      if (preferencesInput) state.preferences = preferencesInput.checked;

      return state;
   }

   /* -------- Actions -------- */

   function acceptAll() {
      var state = { marketing: true, performance: true, preferences: true, functional: true };
      saveConsent(mapConsentFromCategories(state), 'accept_all');
      hideBanner();
      hidePopup();
      syncTogglesFromState(state);
   }

   function declineAll() {
      var state = { marketing: false, performance: false, preferences: false, functional: true };
      if (document.activeElement) {
         document.activeElement.blur();
      }
      saveConsent(mapConsentFromCategories(state), 'decline_all');
      hideBanner();
      hidePopup();
      syncTogglesFromState(state);
   }

   function saveChoices() {
      var state = getStateFromToggles();
      saveConsent(mapConsentFromCategories(state), 'save_choices');
      hideBanner();
      hidePopup();
   }

   /* -------- Global API & events -------- */

   function initGlobalApi() {
      window.CustomCookieCMP = window.CustomCookieCMP || {};
      window.CustomCookieCMP.getConsent = function () {
         return readConsent() || getInitialConsent();
      };
      window.CustomCookieCMP.openSettings = function () {
         showPopup();
      };
   }

   function bindEvents() {
      var banner = qs('ccc-banner');
      var popup = qs('ccc-popup-backdrop');
      if (!banner || !popup) return;

      banner.querySelector('.ccc-btn-accept-all').addEventListener('click', acceptAll);
      banner.querySelector('.ccc-btn-decline-all').addEventListener('click', declineAll);

      var btnManage = banner.querySelector('.ccc-btn-manage');
      if (btnManage) {
         btnManage.addEventListener('click', function () {
            var consent = readConsent() || getInitialConsent();
            syncTogglesFromState(getStateFromConsent(consent));
            showPopup();
         });
      }

      popup.querySelector('.ccc-btn-accept-all').addEventListener('click', acceptAll);
      popup.querySelector('.ccc-btn-decline-all').addEventListener('click', declineAll);
      popup.querySelector('.ccc-btn-save').addEventListener('click', saveChoices);

      popup.querySelector('.ccc-popup-close').addEventListener('click', hidePopup);
      popup.addEventListener('click', function (e) {
         if (e.target === popup) {
            hidePopup();
         }
      });

      qsa('.js-open-cookie-settings').forEach(function (el) {
         el.addEventListener('click', function (e) {
            e.preventDefault();
            var consent = readConsent() || getInitialConsent();
            syncTogglesFromState(getStateFromConsent(consent));
            showPopup();
         });
      });
   }

   function init() {
      applyTexts();
      applyStyles();
      initGlobalApi();
      bindEvents();

      var existing = readConsent();
      if (!existing) {
         showBanner();
      } else {
         window.CustomCookieCMP.consent = existing;
      }
   }

   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', init);
   } else {
      init();
   }
})();
