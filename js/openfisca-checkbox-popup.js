(function (Drupal, drupalSettings) {
  Drupal.behaviors.openFiscaCheckboxPopup = {
    attach (context, settings) {
      // Ensure we only attach once per context.
      const checkboxes = context.querySelectorAll(
        'input[name="third_party_settings[webform_openfisca][fisca_enabled]"]',
      );

      checkboxes.forEach(function (checkbox) {
        if (!checkbox.dataset.popupAttached) {
          checkbox.dataset.popupAttached = true;

          checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
              Drupal.dialog(
                "<p>Please be aware that submission data is automatically saved in Drupal unless this feature is disabled. Based on your use case, you'll need to consider the privacy implications of your webform and may need to disable saving of submissions.</p>",
                {
                  title: 'Important Notice',
                  width: 400,
                },
              ).showModal();
            }
          });
        }
      });
    },
  };
})(Drupal, drupalSettings);
