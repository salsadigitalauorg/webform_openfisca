<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\webform_openfisca\OpenFisca\ClientFactory;
use Drupal\webform_openfisca\OpenFisca\ClientInterface;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;

/**
 * Tests the OpenFisca Client Factory class.
 *
 * @group webform_openfisca
 * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\ClientFactory
 */
class OpenFiscaClientFactoryUnitTest extends BaseUnitTestCase {

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

  /**
   * Tests create() invokes alter hooks; client reflects altered options.
   *
   * Ensures key_auth and other implementations can alter client options and
   * that the created client receives the altered options (e.g. headers).
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\ClientFactory::create
   */
  public function testCreateInvokesAlterWithContext(): void {
    $endpoint = 'https://openfisca.test/api';
    $webform_context = ['webform_openfisca_settings' => new \stdClass()];

    $alter_invoked = [];
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->once())
      ->method('alter')
      ->willReturnCallback(function ($hook, &$options, $context, $woc) use (&$alter_invoked, $webform_context) {
        $this->assertSame('webform_openfisca_client_options', $hook);
        $this->assertSame($webform_context, $woc);
        $alter_invoked['options_has_base_uri'] = isset($options['base_uri']);
        $alter_invoked['base_uri'] = $options['base_uri'] ?? NULL;
        $alter_invoked['context_has_api_endpoint'] = isset($context['api_endpoint']);
        $alter_invoked['context_api_endpoint'] = $context['api_endpoint'] ?? NULL;
        $options['headers']['X-Custom-Alter'] = 'module-altered';
      });

    $theme_manager = $this->createMock(ThemeManagerInterface::class);
    $theme_manager->expects($this->once())
      ->method('alter')
      ->willReturnCallback(function ($hook, &$options) {
        $this->assertSame('webform_openfisca_client_options', $hook);
        $options['headers']['X-Theme-Alter'] = 'theme-altered';
      });

    $guzzle_client = $this->createMock(GuzzleClientInterface::class);
    $http_client_factory = $this->createMock(HttpClientFactory::class);
    $http_client_factory->method('fromOptions')->willReturn($guzzle_client);

    $factory = new ClientFactory($http_client_factory, $this->mockLogger(), $module_handler, $theme_manager);
    $client = $factory->create($endpoint, [], $webform_context);

    $this->assertInstanceOf(ClientInterface::class, $client);
    $this->assertTrue($alter_invoked['options_has_base_uri'] ?? FALSE, 'Alter received options with base_uri.');
    $this->assertStringContainsString('openfisca.test', $alter_invoked['base_uri'] ?? '', 'Alter received correct base_uri.');
    $this->assertTrue($alter_invoked['context_has_api_endpoint'] ?? FALSE, 'Alter received context with api_endpoint.');
    $this->assertStringContainsString('openfisca.test', $alter_invoked['context_api_endpoint'] ?? '', 'Context api_endpoint is correct.');

    $options = $client->getHttpClientOptions();
    $this->assertArrayHasKey('headers', $options, 'Client options reflect altered headers.');
    $this->assertArrayHasKey('X-Custom-Alter', $options['headers'], 'Module alter option is present on client.');
    $this->assertEquals('module-altered', $options['headers']['X-Custom-Alter']);
    $this->assertArrayHasKey('X-Theme-Alter', $options['headers'], 'Theme alter option is present on client.');
    $this->assertEquals('theme-altered', $options['headers']['X-Theme-Alter']);
  }

}
