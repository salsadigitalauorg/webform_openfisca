<?php

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function webform_openfisca_token_info() : array {
  $info['tokens']['webform_openfisca']['wo_rounded'] = [
    'name' => t('Numbers - rounded and beautified.'),
    'description' => t('Numbers, rounded and commas added. Example usage: [current-page:wo_rounded:53789.23232] OR [current-page:wo_rounded:current-page:query:abc]'),
    'dynamic' => TRUE,
  ];

  $info['types']['webform_openfisca'] = [
    'name' => t('Webform openfisca'),
    'description' => t('Tokens related to webform openfisca.'),
    'needs-data' => 'webform_openfisca',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function webform_openfisca_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) : array {
  $replacements = [];

  // [wo_rounded] dynamic tokens.
  if ($wo_tokens = \Drupal::token()->findWithPrefix($tokens, 'wo_rounded')) {
    $token = Drupal::token();
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

  return $replacements;
}
