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
   * @param {string} eventType
   *   The event type.
   */
  function preserveEventHandler(element, eventType) {
    const events = $._data(element, 'events');
    if (
      typeof events !== 'undefined' &&
      eventType in events &&
      events[eventType].length
    ) {
      events[eventType].forEach((event) => {
        $(element).on('fiscaImmediateResponse:continue', event.handler);
      });
    }
  }

  Drupal.behaviors.webform_openfisca_immediate_response = {
    attach(context, settings) {
      // Redirect to result page upon Openfisca immediate response.
      $.fn.webformOpenfiscaImmediateResponseRedirect = function (
        immediateResponse,
      ) {
        if (
          typeof immediateResponse.confirmation_url !== 'undefined' &&
          typeof immediateResponse.query !== 'undefined'
        ) {
          window.location = `${immediateResponse.confirmation_url}?${immediateResponse.query}`;
        }
      };

      // Trigger the preserved events as immediate response has no result.
      $.fn.webformOpenfiscaImmediateResponseContinue = function (
        triggeringElement,
      ) {
        if (triggeringElement !== null) {
          let element = $(
            `[data-openfisca-webform-id=${
              triggeringElement.webform
            }][data-openfisca-immediate-response=true][name=${
              triggeringElement.name
            }][data-drupal-selector=${triggeringElement.selector}]`,
          ).get(0);
          if (!element && triggeringElement.original_selector !== '') {
            // The selector may be changed but the element is not rebuilt.
            // Attempt to use the original_selector.
            element = $(
              `[data-openfisca-webform-id=${
                triggeringElement.webform
              }][data-openfisca-immediate-response=true][name=${
                triggeringElement.name
              }][data-drupal-selector=${triggeringElement.original_selector}]`,
            ).get(0);
          }
          if (!element) {
            // Attempt to use webform and element name instead.
            element = $(
              `[data-openfisca-webform-id=${
                triggeringElement.webform
              }][data-openfisca-immediate-response=true][name=${
                triggeringElement.name
              }]`,
            ).get(0);
          }
          $(element).triggerHandler('fiscaImmediateResponse:continue');
        }
      };

      once(
        'webformOpenfiscaImmediateResponse',
        '*[data-openfisca-immediate-response=true]',
        context,
      ).forEach(function (element) {
        // Trigger fiscaImmediateResponse:request event.
        const triggerFiscaImmediateResponseRequest = function (event) {
          // Only trigger on selected radio/checkbox.
          if (
            this.matches('input[type=radio]') ||
            this.matches('input[checkbox]')
          ) {
            if (this.matches(':checked')) {
              $(this).triggerHandler('fiscaImmediateResponse:request', event);
            }
          }
          // Or on non-empty form elements.
          else if (this.value !== '') {
            $(this).triggerHandler('fiscaImmediateResponse:request', event);
          }
        };

        // Trigger the fiscaImmediateResponse:request event when the blur event
        // is triggered for text inputs and textareas.
        if (
          element.matches('input[type=text]') ||
          element.matches('textarea')
        ) {
          // Postpone the default blur event handler.
          preserveEventHandler(element, 'blur');
          $(element)
            .off('blur')
            .on('blur', element, triggerFiscaImmediateResponseRequest);
        }
        // Trigger the fiscaImmediateResponse:request event when the change
        // event is triggered for other inputs and selects.
        else if (element.matches('input') || element.matches('select')) {
          // Postpone the default change event handler.
          preserveEventHandler(element, 'change');
          $(element)
            .off('change')
            .on('change', element, triggerFiscaImmediateResponseRequest);
        }
      });
    },
  };
})(jQuery, Drupal, drupalSettings);
