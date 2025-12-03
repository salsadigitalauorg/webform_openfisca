(function (Drupal, drupalSettings) {
  Drupal.behaviors.openFiscaCheckboxPopup = {
    attach: function (context, settings) {
      // Ensure we only attach once per context.
      const checkboxes = context.querySelectorAll(
        'input[name="third_party_settings[webform_openfisca][fisca_enabled]"]'
      );

      checkboxes.forEach(function (checkbox) {
        if (!checkbox.dataset.popupAttached) {
          checkbox.dataset.popupAttached = true;

          checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
              Drupal.dialog(
                '<p>Please make sure that you have disabled saving of submissions for this form.</p>',
                {
                  title: 'Important Notice',
                  width: 400
                }
              ).showModal();
            }
          });
        }
      });
    }
  };
})(Drupal, drupalSettings);
