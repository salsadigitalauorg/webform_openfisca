<?php

namespace Drupal\webform_openfisca\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 *
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
    // Booking link.
    $form['booking_link'] = [
      '#type' => 'url',
      '#title' => $this->t('Vaccine booking link:'),
      '#default_value' => $config->get('webform_openfisca.booking_link'),
      '#description' => $this->t('Add the URL for the booking link.'),
    ];
    // Booking text.
    $form['booking_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vaccine booking text:'),
      '#default_value' => $config->get('webform_openfisca.booking_text'),
      '#description' => $this->t('Add the URL for the booking text.'),
    ];
    // VIC health website URL.
    $form['victoria_health_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL for VIC health website:'),
      '#default_value' => $config->get('webform_openfisca.victoria_health_url'),
      '#description' => $this->t('Add the URL for VIC health website.'),
    ];
    // NSW health website URL.
    $form['new_south_wales_health_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL for NSW health website:'),
      '#default_value' => $config->get('webform_openfisca.new_south_wales_health_url'),
      '#description' => $this->t('Add the URL for NSW health website.'),
    ];
    // WA health website URL.
    $form['western_australia_health_url'] = [
      '#type' => 'url',
      '#title' => $this->t('URL for WA health website:'),
      '#default_value' => $config->get('webform_openfisca.western_australia_health_url'),
      '#description' => $this->t('Add the URL for WA health website.'),
    ];
    $form['api_endpoint'] = [
      '#type' => 'url',
      '#title' => $this->t('Endpoint for the Open Fisca API'),
      '#default_value' => $config->get('webform_openfisca.api_endpoint'),
      '#description' => $this->t('Endpoint for the Open Fisca API.'),
    ];
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
    $config->set('webform_openfisca.booking_link', $form_state->getValue('booking_link'));
    $config->set('webform_openfisca.booking_text', $form_state->getValue('booking_text'));
    $config->set('webform_openfisca.victoria_health_url', $form_state->getValue('victoria_health_url'));
    $config->set('webform_openfisca.new_south_wales_health_url', $form_state->getValue('new_south_wales_health_url'));
    $config->set('webform_openfisca.western_australia_health_url', $form_state->getValue('western_australia_health_url'));
    $config->set('webform_openfisca.api_endpoint', $form_state->getValue('api_endpoint'));

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
