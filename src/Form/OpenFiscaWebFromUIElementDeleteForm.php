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
    unset($field_mappings[$key]);
    $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_field_mappings', json_encode($field_mappings));

    $fisca_variables = json_decode($this->webform->getThirdPartySetting('webform_openfisca', 'fisca_variables'), TRUE) ?? [];
    unset($fisca_variables[$key]);
    $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_variables', json_encode($fisca_variables));

    $this->webform->save();
    parent::submitForm($form, $form_state);
  }

}
