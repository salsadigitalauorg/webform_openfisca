/**
 * @file
 * Attaches behaviors for the OpenFisca immediate response.
 */
(function($, Drupal, drupalSettings) {

  /**
   * Preserve the existing event handler of an element.
   *
   * The preserved handlers can be later triggered via the
   * fiscaImmediateResponse:continue event.
   *
   * @param {HTMLElement} element
   *   The element.
   * @param {string} event_type
   *   The event type.
   */
  function preserveEventHandler(element, event_type) {
    let events = $._data(element, 'events');
    if ((event_type in events) && events[event_type].length) {
      events[event_type].forEach((event) => {
        $(element).bind('fiscaImmediateResponse:continue', event.handler)
      });
    }
  }

  Drupal.behaviors.webform_openfisca_immediate_response = {
    attach(context, settings) {
      // Redirect to result page upon Openfisca immediate response.
      $.fn.webformOpenfiscaImmediateResponseRedirect = function(immediate_response) {
        if (typeof immediate_response.confirmation_url !== 'undefined' && typeof immediate_response.query !== 'undefined') {
          window.location = immediate_response.confirmation_url + '?' + immediate_response.query;
        }
      };
      // Trigger the preserved events as immediate response has no redirect.
      $.fn.webformOpenfiscaImmediateResponseContinue = function(triggering_element) {
        if (triggering_element !== null) {
          let element = $('[data-openfisca-webform-id=' + triggering_element.webform + '][data-openfisca-immediate-response=true][name=' + triggering_element.name + '][data-drupal-selector=' + triggering_element.selector + ']').get(0);
          if (!element) {
            // The selector may be changed but the element is not rebuilt.
            // Attempt to use webform and element name instead.
            element = $('[data-openfisca-webform-id=' + triggering_element.webform + '][data-openfisca-immediate-response=true][name=' + triggering_element.name + ']').get(0);
          }
          $(element).triggerHandler('fiscaImmediateResponse:continue');
        }
      };

      // Trigger fiscaImmediateResponse:request event.
      once('webformOpenfiscaImmediateResponse', '*[data-openfisca-immediate-response=true]', context).forEach(function (element) {
        const triggerFiscaImmediateResponse_request = function (event) {
          if ($(this).val() !== "") {
            $(this).triggerHandler('fiscaImmediateResponse:request', event);
          }
        }

        if ($(element).is('input[type=text]') || $(element).is('textarea')) {
          preserveEventHandler(element, 'blur');
          $(element).unbind('blur');
          $(element).bind('blur', element, triggerFiscaImmediateResponse_request);
        }
        else if ($(element).is('input') || $(element).is('select')) {
          preserveEventHandler(element, 'change');
          $(element).unbind('change');
          $(element).bind('change', element, triggerFiscaImmediateResponse_request);
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
