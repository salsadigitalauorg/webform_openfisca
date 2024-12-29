<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\OpenFisca\ClientInterface;

/**
 * Tests the OpenFisca Client Factory class.
 *
 * @group webform_openfisca
 * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\ClientFactory
 */
class OpenFiscaClientFactoryUnitTest extends UnitTestCase {

  use UnitTestTrait;

  /**
   * Tests the create() method of OpenFisca Client Factory.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\ClientFactory::__construct
   * @covers \Drupal\webform_openfisca\OpenFisca\ClientFactory::create
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getBaseUri
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::sanitiseUri
   */
  public function testCreate(): void {
    $factory = $this->mockClientFactory();
    $client = $factory->create('https://openfisca.test/api', [], ['webform_openfisca_settings' => NULL]);
    $this->assertInstanceOf(ClientInterface::class, $client);
    $this->assertEquals('https://openfisca.test/api', $client->getBaseUri());
  }

}
