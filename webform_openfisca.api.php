<?php

/**
 * @file
 * Webform OpenFisca hooks.
 */

declare(strict_types=1);

use Drupal\webform_openfisca\WebformOpenFiscaSettings;

/**
 * Alter the options to create the OpenFisca API Client.
 *
 * This alter hook can be invoked from both modules and themes.
 *
 * @param array $options
 *   The options.
 * @param array $context
 *   The context array with the following keys:
 *   - api_endpoint: the API endpoint of OpenFisca.
 * @param array $webform_openfisca_context
 *   The webform OpenFisca context array with the following keys:
 *   - webform_openfisca_settings: the WebformOpenFiscaSettings object.
 *
 * @see \Drupal\webform_openfisca\OpenFisca\ClientFactory::create()
 * @see \Drupal\webform_openfisca\WebformOpenFiscaSettings::getOpenFiscaClient()
 * @see https://docs.guzzlephp.org/en/latest/request-options.html
 */
function hook_webform_openfisca_client_options_alter(array &$options, array &$context, array &$webform_openfisca_context) : void {
  /** @var \Drupal\webform_openfisca\WebformOpenFiscaSettings $webform_openfisca_settings */
  $webform_openfisca_settings = $webform_openfisca_context['webform_openfisca_settings'];
  if ($webform_openfisca_settings instanceof WebformOpenFiscaSettings
    && $webform_openfisca_settings->getWebformId() === 'my_custom_webform'
  ) {
    $options['auth'] = ['username', 'password'];
  }

  if (($context['api_endpoint'] ?? '') === 'https://my-api.openfisca.org') {
    $options['query'] = ['foo' => 'bar'];
  }
}

