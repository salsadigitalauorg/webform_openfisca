<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;
use Drupal\webform_ui\Form\WebformUiElementDeleteForm as WebformUiElementDeleteFormBase;

/**
 * Update OpenFisca settings upon deleting a Webform element.
 */
class WebformUiElementDeleteForm extends WebformUiElementDeleteFormBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    $element_key = $this->key;
    try {
      $openfisca_settings = WebformOpenFiscaSettings::load($this->webform);
      $updated_settings = $openfisca_settings->removeWebformElementMappings($element_key);
      $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_field_mappings', $updated_settings->getJsonFieldMappings());
      $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_variables', $updated_settings->getJsonVariables());
      $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_entity_roles', $updated_settings->getJsonEntityRoles());
      $this->webform->setThirdPartySetting('webform_openfisca', 'fisca_immediate_response_mapping', $updated_settings->getJsonImmediateResponseMapping());

      $this->webform->save();
    }
    catch (EntityStorageException $entity_storage_exception) {
      $this->messenger()->addError($entity_storage_exception->getMessage());
    }

    parent::submitForm($form, $form_state);
  }

}
