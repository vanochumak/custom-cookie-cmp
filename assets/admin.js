jQuery(document).ready(function ($) {
   $(".ccc-color-field > input[type='text']").wpColorPicker();

   // Sync all TinyMCE editors to their textareas before form submit
   $('#customcookiecmp-settings-form').on('submit', function () {
      if (typeof tinyMCE !== 'undefined') {
         tinyMCE.triggerSave();
      }
   });

   // Donation notice dismiss
   $(document).on('click', '.customcookiecmp-donation-dismiss', function () {
      var nonce  = $(this).data('nonce');
      var $notice = $('#customcookiecmp-donation-notice');
      $.post(ajaxurl, { action: 'customcookiecmp_dismiss_donation', nonce: nonce }, function () {
         $notice.fadeOut(300, function () { $notice.remove(); });
      });
   });

   // Horizontal tab switching
   $(document).on('click', '.ccc-tab-btn', function () {
      var $btn    = $(this);
      var target  = $btn.data('target');
      var $locale = $btn.closest('.ccc-locale-body');

      $locale.find('.ccc-tab-btn').removeClass('is-active');
      $locale.find('.ccc-tab-panel').removeClass('is-active');

      $btn.addClass('is-active');
      $('#' + target).addClass('is-active');
   });
});
