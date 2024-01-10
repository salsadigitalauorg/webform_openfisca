<?php

namespace Drupal\webform_openfisca\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class OpenFiscaRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('entity.webform_ui.element.delete_form')) {
      $route->setDefault('_form', '\Drupal\webform_openfisca\Form\OpenFiscaWebFromUIElementDeleteForm');
    }
  }

}
