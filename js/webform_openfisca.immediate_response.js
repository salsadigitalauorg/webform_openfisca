/**
 * @file
 * Attaches behaviors for the OpenFisca immediate response.
 */
(function ($, Drupal, drupalSettings) {
  /**
   * Preserve the existing event handlers of an element.
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
    if (typeof events !== 'undefined' && (event_type in events) && events[event_type].length) {
      events[event_type].forEach((event) => {
        $(element).bind('fiscaImmediateResponse:continue', event.handler)
      });
    }
  }

  Drupal.behaviors.webform_openfisca_immediate_response = {
    attach(context, settings) {
      // Redirect to result page upon Openfisca immediate response.
      $.fn.webformOpenfiscaImmediateResponseRedirect = function (immediate_response) {
        if (typeof immediate_response.confirmation_url !== 'undefined' && typeof immediate_response.query !== 'undefined') {
          window.location = immediate_response.confirmation_url + '?' + immediate_response.query;
        }
      };

      // Trigger the preserved events as immediate response has no result.
      $.fn.webformOpenfiscaImmediateResponseContinue = function (triggering_element) {
        if (triggering_element !== NULL) {
          let element = $('[data-openfisca-webform-id=' + triggering_element.webform + '][data-openfisca-immediate-response=true][name=' + triggering_element.name + '][data-drupal-selector=' + triggering_element.selector + ']').get(0);
          if (!element && triggering_element.original_selector !== '') {
            // The selector may be changed but the element is not rebuilt.
            // Attempt to use the original_selector.
            element = $('[data-openfisca-webform-id=' + triggering_element.webform + '][data-openfisca-immediate-response=true][name=' + triggering_element.name + '][data-drupal-selector=' + triggering_element.original_selector + ']').get(0);
          }
          if (!element) {
            // Attempt to use webform and element name instead.
            element = $('[data-openfisca-webform-id=' + triggering_element.webform + '][data-openfisca-immediate-response=true][name=' + triggering_element.name + ']').get(0);
          }
          $(element).triggerHandler('fiscaImmediateResponse:continue');
        }
      };

      once('webformOpenfiscaImmediateResponse', '*[data-openfisca-immediate-response=true]', context).forEach(function (element) {
        // Trigger fiscaImmediateResponse:request event.
        const triggerFiscaImmediateResponse_request = function (event) {
          // Only trigger on selected radio/checkbox.
          if ($(this).is('input[type=radio]') || $(this).is('input[checkbox]')) {
            if ($(this).is(':checked')) {
              $(this).triggerHandler('fiscaImmediateResponse:request', event);
            }
          }
          // Or on non-empty form elements.
          else if ($(this).val() !== "") {
            $(this).triggerHandler('fiscaImmediateResponse:request', event);
          }
        }

        // Trigger the fiscaImmediateResponse:request event when the blur event
        // is triggered for text inputs and textareas.
        if ($(element).is('input[type=text]') || $(element).is('textarea')) {
          // Postpone the default blur event handler.
          preserveEventHandler(element, 'blur');
          $(element).unbind('blur')
            .bind('blur', element, triggerFiscaImmediateResponse_request);
        }
        // Trigger the fiscaImmediateResponse:request event when the change
        // event is triggered for other inputs and selects.
        else if ($(element).is('input') || $(element).is('select')) {
          // Postpone the default change event handler.
          preserveEventHandler(element, 'change');
          $(element).unbind('change')
            .bind('change', element, triggerFiscaImmediateResponse_request);
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
