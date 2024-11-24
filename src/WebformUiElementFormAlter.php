<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\OpenFisca\Helper as OpenFiscaHelper;
use Drupal\webform_ui\Form\WebformUiElementFormInterface;

/**
 * Alter webform_ui_element_form.
 */
class WebformUiElementFormAlter extends WebformFormAlterBase {

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state) : void {
    $webform = $this->getWebformFromFormState($form_state);
    if (!$webform instanceof WebformInterface) {
      return;
    }

    $element_key = $this->getWebformElementKey($form_state);
    if (empty($element_key)) {
      return;
    }

    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    if (!$openfisca_settings->isEnabled() || !$openfisca_settings->hasApiEndpoint()) {
      return;
    }

    $field_mapping = $openfisca_settings->getFieldMapping($element_key);
    $field_mapping = ($field_mapping === FALSE) ? '' : $field_mapping;

    $entity_key = '';
    $form['fisca_machine_name'] = [
      '#type' => 'select',
      '#options' => $this->getOpenFiscaVariables($openfisca_settings),
      '#title' => $this->t('Linked Fisca variable'),
      '#prefix' => '<h2>Fisca enabled!</h2><p>Please provide Fisca variable name to correctly link.</p>',
      '#default_value' => OpenFiscaHelper::parseOpenFiscaFieldMapping($field_mapping, entity: $entity_key),
    ];

    $form['fisca_entity_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fisca entity key'),
      '#description' => [
        '#markup' => $this->t('Please provide Fisca entity key to group Fisca variables eg. personA.age, personB.birth_day'),
      ],
      '#default_value' => $entity_key ?? '',
    ];

    $role = $openfisca_settings->getEntityRole($element_key);
    $form['fisca_entity_role'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fisca entity role'),
      '#description' => [
        '#markup' => $this->t('Please provide Fisca entity role for complex group entities and roles eg. families.family.children.'),
      ],
      '#default_value' => $role['role'] ?? '',
    ];
    $form['fisca_entity_role_array'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fisca entity role array'),
      '#description' => [
        '#markup' => $this->t('Is the Fisca entity role an array eg. children should be an array whereas principal_caregiver is not'),
      ],
      '#default_value' => (bool) ($role['is_array'] ?? FALSE),
    ];

    $form['fisca_immediate_response'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Requires immediate response'),
      '#description' => [
        '#markup' => $this->t('Will this element require immediate response from OpenFisca?'),
      ],
      '#default_value' => $openfisca_settings->fieldHasImmediateResponse($element_key),
    ];

    $form['#submit'][] = [$this, 'submitForm'];
  }

  /**
   * Submit callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    $webform = $this->getWebformFromFormState($form_state);
    if (!$webform instanceof WebformInterface) {
      return;
    }

    $element_key = $this->getWebformElementKey($form_state);
    if (empty($element_key)) {
      return;
    }

    if ($form_state->getValue('key') !== $element_key) {
      return;
    }

    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    if (!$openfisca_settings->isEnabled() || !$openfisca_settings->hasApiEndpoint()) {
      return;
    }
    $fisca_field_mappings = $openfisca_settings->getFieldMappings();
    $fisca_variables = $openfisca_settings->getVariables();
    $fisca_entity_roles = $openfisca_settings->getEntityRoles();

    $openfisca_client = $openfisca_settings->getOpenFiscaClient($this->openFiscaClientFactory);

    $fisca_machine_name = $form_state->getValue('fisca_machine_name');
    $fisca_entity_key = $form_state->getValue('fisca_entity_key');
    $fisca_entity_role = $form_state->getValue('fisca_entity_role');
    $fisca_entity_role_array = (bool) $form_state->getValue('fisca_entity_role_array');
    // Update OpenFisca variables and field mappings.
    if (!array_key_exists($fisca_machine_name, $this->getGenericOpenFiscaVariables())) {
      // Get OpenFisca entities.
      $fisca_entities = $openfisca_client->getEntities();
      // Get OpenFisca variable.
      $fisca_variable = $openfisca_client->getVariable($fisca_machine_name);

      // Early return when OpenFisca responses do not have all expected data.
      if (!isset(
        $fisca_variable['entity'],
        $fisca_entities[$fisca_variable['entity']]['plural'])
      ) {
        return;
      }

      // Get OpenFisca Entity from variable.
      $fisca_entity = $fisca_entities[$fisca_variable['entity']];

      // Create field mapping in format 'entity_plural.entity_key.variable_name'
      // e.g. 'persons.personA.age'.
      $field_mapping = OpenFiscaHelper::combineOpenFiscaFieldMapping($fisca_entity['plural'], $fisca_entity_key, $fisca_machine_name);

      // Add/Update OpenFisca field mappings.
      $fisca_field_mappings[$element_key] = $field_mapping;
      // Add/Update OpenFisca variables.
      $fisca_variables[$fisca_machine_name] = $fisca_variable;

      if (!empty($fisca_entity_role)) {
        // Add/Update OpenFisca entity roles.
        // Get OpenFisca entity role and save as
        // object in format {"role": "children", "is_array": true}.
        $fisca_entity_roles[$element_key]['role'] = $fisca_entity_role;
        $fisca_entity_roles[$element_key]['is_array'] = $fisca_entity_role_array;
      }
      elseif (isset($fisca_entity_roles[$element_key])) {
        // Remove any entity role.
        unset($fisca_entity_roles[$element_key]);
      }
    }
    // Remove OpenFisca variable and field mapping for this element key.
    else {
      $field_mapping = $openfisca_settings->getFieldMapping($element_key);
      if ($field_mapping !== FALSE) {
        $fisca_variable = OpenFiscaHelper::parseOpenFiscaFieldMapping($field_mapping);
        unset($fisca_field_mappings[$field_mapping], $fisca_variables[$fisca_variable]);
      }
      // Remove the entity role too.
      unset($fisca_entity_roles[$element_key]);
    }

    $fisca_immediate_response_mapping = $openfisca_settings->getImmediateResponseMapping();
    $fisca_immediate_response = (bool) $form_state->getValue('fisca_immediate_response');
    if ($fisca_immediate_response) {
      $fisca_immediate_response_mapping[$element_key] = TRUE;
    }
    else {
      unset($fisca_immediate_response_mapping[$element_key]);
    }

    // Save OpenFisca settings.
    try {
      $webform->setThirdPartySetting('webform_openfisca', 'fisca_field_mappings', OpenFiscaHelper::jsonEncodePretty($fisca_field_mappings));
      $webform->setThirdPartySetting('webform_openfisca', 'fisca_variables', OpenFiscaHelper::jsonEncodePretty($fisca_variables));
      $webform->setThirdPartySetting('webform_openfisca', 'fisca_entity_roles', OpenFiscaHelper::jsonEncodePretty($fisca_entity_roles));
      $webform->setThirdPartySetting('webform_openfisca', 'fisca_immediate_response_mapping', OpenFiscaHelper::jsonEncodePretty($fisca_immediate_response_mapping));
      $webform->save();
    }
    catch (EntityStorageException $entity_storage_exception ) {
      $this->messenger->addError($entity_storage_exception->getMessage());
    }
  }


  /**
   * Get the key of the webform element.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The key.
   */
  protected function getWebformElementKey(FormStateInterface $form_state) : ?string {
    $form_object = $form_state->getFormObject();
    return ($form_object instanceof WebformUiElementFormInterface) ? $form_object->getKey() : NULL;
  }

  /**
   * Get OpenFisca variables as options.
   *
   * @param \Drupal\webform_openfisca\WebformOpenFiscaSettings $settings
   *   The Webform OpenFisca settings.
   *
   * @return array<string, string>
   *   The variables.
   */
  protected function getOpenFiscaVariables(WebformOpenFiscaSettings $settings) : array {
    if (!$settings->hasApiEndpoint()) {
      return [];
    }

    $openfisca_client = $settings->getOpenFiscaClient($this->openFiscaClientFactory);

    // Allow a generic key to be allocated to identify a person.
    $fisca_variables = $this->getGenericOpenFiscaVariables();

    $variables = $openfisca_client->getVariables();
    ksort($variables);
    foreach ($variables as $key => $variable) {
      $fisca_variables[$key] = $key;
      if (!empty($variable['description'])) {
        $fisca_variables[$key] .= ': ' . $variable['description'];
      }
    }
    return $fisca_variables;
  }

  /**
   * Get the generic OpenFisca variables.
   *
   * @return array<string, \Drupal\Component\Render\MarkupInterface|string>
   *   The variables.
   */
  protected function getGenericOpenFiscaVariables() : array {
    return [
      '_nil' => $this->t('- Exclude from mapping -'),
      'name_key' => $this->t('Name'),
      'period' => $this->t('Period'),
    ];
  }

}
