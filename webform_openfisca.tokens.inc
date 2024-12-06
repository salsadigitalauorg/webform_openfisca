<?php

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;

/**
 * Implements hook_token_info().
 */
function webform_openfisca_token_info() : array {
  $info['types']['webform_openfisca'] = [
    'name' => t('Webform OpenFisca'),
    'description' => t('Tokens related to Webform OpenFisca.'),
  ];

  $info['tokens']['webform_openfisca']['wo_rounded'] = [
    'name' => t('Webform OpenFisca: rounded and beautified numbers'),
    'description' => t('Numbers, rounded and commas added. Example usage: [current-page:wo_rounded:53789.23232] OR [current-page:wo_rounded:current-page:query:abc]'),
    'dynamic' => TRUE,
  ];

  $all_settings = _webform_openfisca_get_all_settings();
  foreach ($all_settings as $webform_id => $openfisca_settings) {
    if (empty($openfisca_settings->getParameters())) {
      continue;
    }
    $info['types']['wo_params:' . $webform_id] = [
      'name' => t('Webform OpenFisca: %id parameters', ['%id' => $webform_id]),
      'description' => t('OpenFisca parameter tokens for webform %id.', ['%id' => $webform_id]),
      'needs-data' => 'webform',
    ];
    $info['tokens']['webform_openfisca']['wo_params:' . $webform_id] = [
      'name' => t('Webform OpenFisca: %id parameters', ['%id' => $webform_id]),
      'description' => t('OpenFisca parameter tokens for webform %id.', ['%id' => $webform_id]),
      'type' => 'wo_params:' . $webform_id,
    ];
    $parameters = $openfisca_settings->getParameters();
    foreach ($parameters as $parameter_name => $parameter) {
      $info['tokens']['wo_params:' . $webform_id][$parameter_name] = [
        'name' => t('Parameter: %name', ['%name' => $parameter_name]),
        'description' => $parameter['description'],
      ];
    }
  }

  return $info;
}

/**
 * Implements hook_tokens().
 */
function webform_openfisca_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) : array {
  $replacements = [];
  $token = \Drupal::token();

  // [wo_rounded] dynamic tokens.
  if ($wo_tokens = $token->findWithPrefix($tokens, 'wo_rounded')) {
    foreach ($wo_tokens as $name => $original) {
      if (is_numeric($name)) {
        $value = $name;
      }
      elseif (is_string($name)) {
        $value = (float) $token->replace('[' . $name . ']');
      }
      $replacements[$original] = number_format($value ?? 0, 2, ".", ",");
    }
  }

  // [wo_params] tokens.
  $period = \Drupal::request()->query->get('period');
  $webform_storage = \Drupal::entityTypeManager()->getStorage('webform');
  /** @var array<string, \Drupal\webform_openfisca\WebformOpenFiscaSettings> $webform_openfisca_settings */
  $webform_openfisca_settings = [];
  foreach ($tokens as $name => $original) {
    if (str_starts_with($name, 'wo_params:')) {
      // Default replacement is empty string.
      $replacements[$original] = '';
      // Extract Webform ID and Parameter from the token.
      $token_params = explode(':', $name);
      if (!isset($token_params[0], $token_params[1], $token_params[2])) {
        continue;
      }
      [, $webform_id, $parameter_name] = $token_params;
      // Load the Webform OpenFisca settings for the webform.
      if (isset($webform_openfisca_settings[$webform_id])) {
        $openfisca_settings = $webform_openfisca_settings[$webform_id];
      }
      else {
        /** @var \Drupal\webform\WebformInterface $webform */
        $webform = $webform_storage->load($webform_id);
        if ($webform instanceof WebformInterface) {
          $openfisca_settings = WebformOpenFiscaSettings::load($webform);
          $webform_openfisca_settings[$webform_id] = $openfisca_settings;
        }
      }
      if (!isset($openfisca_settings) || !$openfisca_settings instanceof WebformOpenFiscaSettings) {
        continue;
      }
      // Get the parameter from OpenFisca.
      $parameter = $openfisca_settings->getParameter($parameter_name);
      if ($parameter === FALSE || !isset($parameter['values']) || !is_array($parameter['values'])) {
        continue;
      }
      // Use the period if set.
      if (!empty($period) && isset($parameter['values'][$period])) {
        $replacement = $parameter['values'][$period];
      }
      // Otherwise, use the first value.
      else {
        $replacement = reset($parameter['values']);
      }
      // Replace the token with a safe markup.
      $replacements[$original] = Markup::create($replacement);
    }
  }

  return $replacements;
}

/**
 * Get all Webform OpenFisca settings.
 *
 * @return \Drupal\webform_openfisca\WebformOpenFiscaSettings[]
 *   The settings.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function _webform_openfisca_get_all_settings() : array {
  $all_settings = [];
  $webform_storage = \Drupal::entityTypeManager()->getStorage('webform');
  /** @var \Drupal\webform\WebformInterface[] $webforms */
  $webforms = $webform_storage->loadMultiple();
  foreach ($webforms as $webform) {
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    if ($openfisca_settings->isEnabled() && $openfisca_settings->hasApiEndpoint()) {
      $all_settings[$webform->id()] = $openfisca_settings;
    }
  }
  return $all_settings;
}
