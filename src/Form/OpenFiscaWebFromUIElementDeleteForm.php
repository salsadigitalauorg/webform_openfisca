<?php

namespace Drupal\webform_openfisca\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_ui\Form\WebformUiElementDeleteForm;

/**
 * Submit Handler to reset values after delete of Elements.
 */
class OpenFiscaWebFromUIElementDeleteForm extends WebformUiElementDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $key = $this->key;
    $field_mappings = json_decode($this->webform->getThirdPartySetting('webform_openfisca', 'fisca_field_mappings'), TRUE) ?? [];
    $field_mapping = $field_mappings[$key];
    // Field mappings are joined together in the format
    // variable_entity.entity_key.variable_name
    // eg. persons.personA.age.
    $keys = explode('.', $field_mapping);
    $variable_name = $keys[2] ?? '';
    unset($field_mappings[$key]);
    $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_field_mappings', json_encode($field_mappings));

    $fisca_variables = json_decode($this->webform->getThirdPartySetting('webform_openfisca', 'fisca_variables'), TRUE) ?? [];
    unset($fisca_variables[$variable_name]);
    $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_variables', json_encode($fisca_variables));

    $fisca_entity_roles = json_decode($this->webform->getThirdPartySetting('webform_openfisca', 'fisca_entity_roles'), TRUE) ?? [];
    unset($fisca_entity_roles[$key]);
    $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_entity_roles', json_encode($fisca_entity_roles));

    $this->webform->save();
    parent::submitForm($form, $form_state);
  }

}
