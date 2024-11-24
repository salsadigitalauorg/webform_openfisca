<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\OpenFisca\Helper as OpenFiscaHelper;

/**
 * Alter the webform 3rd-party settings form.
 */
class WebformThirdPartySettingsFormAlter extends WebformFormAlterBase {

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    $webform = $this->getWebformFromFormState($form_state);
    if (!$webform instanceof WebformInterface) {
      return;
    }

    $openfisca_settings = WebformOpenFiscaSettings::load($webform);

    $form['third_party_settings']['webform_openfisca'] = [
      '#type' => 'details',
      '#title' => $this->t('OpenFisca'),
      '#open' => TRUE,
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable OpenFisca RaC integration'),
      '#description' => '',
      '#default_value' => $openfisca_settings->isEnabled(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug mode'),
      '#description' => '',
      '#default_value' => $openfisca_settings->isDebugEnabled(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_logging_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log OpenFisca calculation'),
      '#description' => '',
      '#default_value' => $openfisca_settings->isLoggingEnabled(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenFisca API endpoint'),
      '#description' => $this->t('Specify the API endpoint to the Fisca Rule'),
      '#default_value' => $openfisca_settings->getApiEndpoint(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_api_authorization_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Authorization header to connect to OpenFisca API'),
      '#description' => $this->t('Specify the Authorization header to connect to a private OpenFisca API, e.g. a Basic auth or a Bearer token.'),
      '#default_value' => $openfisca_settings->getApiAuthorizationHeader(),
      '#field_prefix' => $this->t('Authorization:'),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_return_key'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'text',
      '#rows' => 3,
      '#title' => $this->t('The keys for the return value'),
      '#description' => $this->t('Specify the keys for the return value that needs to be checked. Comma separated.'),
      '#default_value' => $openfisca_settings->getPlainReturnKeys(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_field_mappings'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'javascript',
      '#rows' => 3,
      '#wrap' => FALSE,
      '#attributes' => [
        'style' => 'max-height: 300px;',
      ],
      '#title' => $this->t('OpenFisca Field mappings'),
      '#description' => $this->t('Specify the field mappings'),
      '#default_value' => $openfisca_settings->getJsonFieldMappings(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_variables'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'javascript',
      '#rows' => 3,
      '#wrap' => FALSE,
      '#attributes' => [
        'style' => 'max-height: 300px;',
      ],
      '#title' => $this->t('OpenFisca Variables'),
      '#description' => $this->t('Specify the variables from OpenFisca API'),
      '#default_value' => $openfisca_settings->getJsonVariables(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_entity_roles'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'javascript',
      '#rows' => 3,
      '#wrap' => FALSE,
      '#attributes' => [
        'style' => 'max-height: 300px;',
      ],
      '#title' => $this->t('OpenFisca Field Entity Roles'),
      '#description' => $this->t('Specify the field entity roles for OpenFisca API'),
      '#default_value' => $openfisca_settings->getJsonEntityRoles(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_immediate_response_mapping'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'javascript',
      '#rows' => 3,
      '#wrap' => FALSE,
      '#attributes' => [
        'style' => 'max-height: 300px;',
      ],
      '#title' => $this->t('OpenFisca immediate response mapping'),
      '#description' => $this->t('Specify the field immediate response mapping'),
      '#default_value' => $openfisca_settings->getJsonImmediateResponseMapping(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_immediate_exit_mapping'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'text',
      '#rows' => 3,
      '#title' => $this->t('OpenFisca immediate exit mapping'),
      '#description' => $this->t('Specify the return keys of OpenFisca response to map to immediate exit. Comma separated.'),
      '#default_value' => $openfisca_settings->getPlainImmediateExitKeys(),
    ];
    $form['third_party_settings']['webform_openfisca']['fisca_immediate_response_ajax_indicator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Ajax indicator'),
      '#description' => $this->t('Whether to display an Ajax indicator when an immediate response is required.'),
      '#default_value' => $openfisca_settings->hasImmediateResponseAjaxIndicator(),
    ];
    $form['#validate'][] = [$this, 'validateForm'];
  }

  /**
   * Validate callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function validateForm(array $form, FormStateInterface $form_state): void {
    $webform = $this->getWebformFromFormState($form_state);
    if (!$webform instanceof WebformInterface) {
      return;
    }

    // Add the immediate exit key to OpenFisca variables.
    $fisca_endpoint = $form_state->getValue(
      ['third_party_settings', 'webform_openfisca', 'fisca_api_endpoint']
    ) ?: '';
    if (empty(trim($fisca_endpoint ?? ''))) {
      return;
    }

    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $openfisca_client = $openfisca_settings->getOpenFiscaClient($this->openFiscaClientFactory);

    $fisca_variables = $form_state->getValue(
      ['third_party_settings', 'webform_openfisca', 'fisca_variables']
    ) ?: '';
    $fisca_variables = Json::decode($fisca_variables);

    $fisca_immediate_exit_mapping = $form_state->getValue(
      ['third_party_settings', 'webform_openfisca', 'fisca_immediate_exit_mapping']
    ) ?: '';
    $immediate_exit_keys = [];
    foreach (OpenFiscaHelper::expandCsvString($fisca_immediate_exit_mapping) as $immediate_exit_key) {
      $immediate_exit_key = OpenFiscaHelper::parseOpenFiscaFieldMapping($immediate_exit_key);
      $immediate_exit_keys[$immediate_exit_key] = $immediate_exit_key;
    }

    foreach ($immediate_exit_keys as $key) {
      if (!isset($fisca_variables[$key])) {
        $fisca_variable = $openfisca_client->getVariable($key);
        if ($fisca_variable !== NULL) {
          $fisca_variables[$key] = $fisca_variable;
        }
      }
    }
    $fisca_variables = OpenFiscaHelper::jsonEncodePretty($fisca_variables);
    $form_state->setValue(
      ['third_party_settings', 'webform_openfisca', 'fisca_variables'],
      $fisca_variables
    );
  }

}
