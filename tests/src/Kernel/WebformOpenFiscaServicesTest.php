<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Logger\LoggerChannel;
use Drupal\KernelTests\KernelTestBase;
use Drupal\webform_openfisca\OpenFisca\ClientFactory;
use Drupal\webform_openfisca\RacContentHelper;
use Drupal\webform_openfisca\Routing\OpenFiscaRouteSubscriber;
use Drupal\webform_openfisca\WebformThirdPartySettingsFormAlter;
use Drupal\webform_openfisca\WebformUiElementFormAlter;

/**
 * Tests the declared services in services.yml.
 *
 * @group webform_openfisca
 */
class WebformOpenFiscaServicesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['webform_openfisca'];

  /**
   * Test for the services of Webform OpenFisca module.
   */
  public function testServices(): void {
    $this->assertInstanceOf(ClientFactory::class, $this->container->get('webform_openfisca.openfisca_client_factory'));
    $this->assertInstanceOf(OpenFiscaRouteSubscriber::class, $this->container->get('webform_openfisca.route_subscriber'));
    $this->assertInstanceOf(RacContentHelper::class, $this->container->get('webform_openfisca.rac_helper'));
    $this->assertInstanceOf(WebformThirdPartySettingsFormAlter::class, $this->container->get('webform_openfisca.webform_form_alter.third_party_settings'));
    $this->assertInstanceOf(WebformUiElementFormAlter::class, $this->container->get('webform_openfisca.webform_form_alter.ui_element'));
    $this->assertInstanceOf(LoggerChannel::class, $this->container->get('logger.channel.webform_openfisca'));
  }

}
