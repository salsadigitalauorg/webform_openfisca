<?php

namespace Drupal\webform_openfisca\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure webform OpenFisca integration settings for this site.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return ['webform_openfisca.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Form constructor.
    $form = parent::buildForm($form, $form_state);
    // Default settings.
    $config = $this->config('webform_openfisca.settings');

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => t('Debug?.'),
      '#default_value' => $config->get('webform_openfisca.debug'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('webform_openfisca.settings');
    $config->set('webform_openfisca.debug', $form_state->getValue('debug'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'webform_openfisca_form';
  }

}
