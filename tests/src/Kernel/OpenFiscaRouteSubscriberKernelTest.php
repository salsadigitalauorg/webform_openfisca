<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\webform_openfisca\Form\WebformUiElementDeleteForm;
use Symfony\Component\Routing\Route;

/**
 * Kernel test for OpenFiscaRouteSubscriber.
 *
 * @group webform_openfisca
 * @coversDefaultClass \Drupal\webform_openfisca\Routing\OpenFiscaRouteSubscriber
 */
class OpenFiscaRouteSubscriberKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'webform',
    'webform_ui',
    'webform_openfisca',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('webform', ['webform']);
  }

  /**
   * Tests delete form route is altered to use WebformUiElementDeleteForm.
   */
  public function testDeleteFormRouteUsesWebformUiElementDeleteForm(): void {
    /** @var \Symfony\Component\Routing\RouterInterface $router */
    $router = $this->container->get('router');
    $route_collection = $router->getRouteCollection();
    $route = $route_collection->get('entity.webform_ui.element.delete_form');

    $this->assertInstanceOf(Route::class, $route, 'Route entity.webform_ui.element.delete_form must exist.');
    $defaults = $route->getDefaults();
    $this->assertArrayHasKey('_form', $defaults, 'Route must have _form default.');
    $this->assertSame(
      WebformUiElementDeleteForm::class,
      $defaults['_form'],
      'Route _form default must be WebformUiElementDeleteForm.'
    );
  }

}
