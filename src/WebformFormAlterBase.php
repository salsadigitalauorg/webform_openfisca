<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform_ui\Form\WebformUiElementFormInterface;
use Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface as OpenFiscaClientFactoryInterface;

/**
 * Base class to handle webform alter for OpenFisca integration.
 */
abstract class WebformFormAlterBase {

  use StringTranslationTrait;

  /**
   * Constructs a new \Drupal\webform_openfisca\WebformFormAlterBase object.
   *
   * @param \Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface $openFiscaClientFactory
   *   OpenFisca connector.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   String translation.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   Cache tags invalidator.
   */
  public function __construct(
    protected OpenFiscaClientFactoryInterface $openFiscaClientFactory,
    TranslationInterface $string_translation,
    protected MessengerInterface $messenger,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
    $this->setStringTranslation($string_translation);
  }

  /**
   * Retrieve the webform from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\webform\WebformInterface|null
   *   The webform.
   */
  protected function getWebformFromFormState(FormStateInterface $form_state): ?WebformInterface {
    $webform = NULL;
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityFormInterface) {
      $webform = $form_object->getEntity();
    }
    elseif ($form_object instanceof WebformUiElementFormInterface) {
      $webform = $form_object->getWebform();
    }

    return ($webform instanceof WebformInterface) ? $webform : NULL;
  }

  /**
   * Alter the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  abstract public function alterForm(array &$form, FormStateInterface $form_state): void;

}
