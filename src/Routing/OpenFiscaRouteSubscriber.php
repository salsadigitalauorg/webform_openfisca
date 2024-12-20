<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\webform_openfisca\Form\WebformUiElementDeleteForm;

/**
 * Route subscriber to replace the Webform UI Element delete form.
 */
class OpenFiscaRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) : void {
    $route = $collection->get('entity.webform_ui.element.delete_form');
    $route?->setDefault('_form', WebformUiElementDeleteForm::class);
  }

}
