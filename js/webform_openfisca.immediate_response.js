/**
 * @file
 * Attaches behaviors for the OpenFisca immediate response.
 */
(function($, Drupal, drupalSettings) {
  Drupal.behaviors.webform_openfisca_immediate_response = {
    attach(context, settings) {
      // Redirect to result page upon Openfisca immediate response.
      $.fn.webformOpenfiscaImmediateResponseRedirect = function(immediate_response) {
        console.log(immediate_response);
        if (typeof immediate_response.confirmation_url !== 'undefined' && typeof immediate_response.query !== 'undefined') {
          window.location = immediate_response.confirmation_url + '?' + immediate_response.query;
        }
      };

      // Trigger fiscaImmediateResponse event.
      once('webformOpenfiscaImmediateResponse', '*[data-openfisca-immediate-response=true]', context).forEach(function (element) {
        const triggerFiscaImmediateResponse = function (event) {
          if ($(this).val() !== "") {
            $(this).triggerHandler('fiscaImmediateResponse', event);
          }
        }

        if ($(element).is('input[type=text]') || $(element).is('textarea')) {
          $(element).bind('blur', element, triggerFiscaImmediateResponse);
        }
        else if ($(element).is('input') || $(element).is('select')) {
          $(element).bind('change', element, triggerFiscaImmediateResponse);
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
