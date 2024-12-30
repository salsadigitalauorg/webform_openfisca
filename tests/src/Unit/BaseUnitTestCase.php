<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\OpenFisca\ClientFactory;
use Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface;
use Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\MockObject\MockObject;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Base class for unit tests.
 */
abstract class BaseUnitTestCase extends UnitTestCase {

  /**
   * Mock an OpenFisca Client Factory service.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface
   *   The client factory.
   */
  protected function mockClientFactory() : ClientFactoryInterface {
    $guzzle_client = $this->createMock(GuzzleClientInterface::class);
    $http_client_factory = $this->createMock(HttpClientFactory::class);
    $http_client_factory->method('fromOptions')->willReturn($guzzle_client);
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('alter')->willReturn(NULL);
    $theme_manager = $this->createMock(ThemeManagerInterface::class);
    $theme_manager->method('alter')->willReturn(NULL);
    $logger = $this->mockLogger();

    $factory = new ClientFactory($http_client_factory, $logger, $module_handler, $theme_manager);
    return $factory;
  }

  /**
   * Mock a webform.
   *
   * @param string $yaml_file
   *   The YAML fixture of the webform config.
   *
   * @return \Drupal\webform\WebformInterface
   *   The webform.
   */
  protected function mockWebform(string $yaml_file): WebformInterface {
    $yaml = $this->loadFixture($yaml_file);
    // @phpstan-ignore-next-line
    $webform_yaml = Yaml::decode($yaml);

    $webform = $this->prophesize(WebformInterface::class);
    $webform->id()->willReturn($webform_yaml['id']);
    $webform->getThirdPartySetting('webform_openfisca', Argument::type('string'), Argument::any())
      ->will(static function ($args) use ($webform_yaml) {
        [, $openfisca_setting, $openfisca_setting_value] = $args;
        return $webform_yaml['third_party_settings']['webform_openfisca'][$openfisca_setting] ?? $openfisca_setting_value;
      });

    return $webform->reveal();
  }

  /**
   * Mock the logger channel.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked logger.
   */
  protected function mockLogger() : LoggerChannelInterface|MockObject {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->method('error');
    return $logger;
  }

  /**
   * Mock a Guzzle HTTP Client.
   *
   * @param string $response_json_file
   *   The JSON fixture for the response of the HTTP Client.
   *
   * @return \GuzzleHttp\ClientInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked client.
   */
  protected function mockHttpClient(string $response_json_file) : GuzzleClientInterface|MockObject {
    $json = $this->loadFixture($response_json_file);

    $body = $this->prophesize(StreamInterface::class);
    $body->getContents()->willReturn($json);
    $response = $this->prophesize(ResponseInterface::class);
    $response->getBody()->willReturn($body->reveal());

    $guzzle_client = $this->createMock(GuzzleClientInterface::class);
    $guzzle_client->method('request')->withAnyParameters()->willReturn($response->reveal());

    return $guzzle_client;
  }

  /**
   * Mock a Guzzle HTTP Client throwing an exception.
   *
   * @return \GuzzleHttp\ClientInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked client.
   */
  protected function mockHttpClientWithException() : GuzzleClientInterface|MockObject {
    $guzzle_client = $this->createMock(GuzzleClientInterface::class);
    $mocked_request = $this->createMock(RequestInterface::class);
    $guzzle_client->method('request')->willThrowException(new RequestException('Mocked Guzzle Request Exception.', request: $mocked_request));

    return $guzzle_client;
  }

  /**
   * Mock a Request Payload.
   *
   * @param string $json_file
   *   The JSON fixture for the request.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload
   *   The mocked payload.
   */
  protected function mockRequestPayload(string $json_file) : RequestPayload {
    $json = $this->loadFixture($json_file);
    return RequestPayload::fromJson($json);
  }

  /**
   * Load a fixture file.
   *
   * @param string $fixture_file
   *   The fixture file in the fixtures/ directory.
   *
   * @return string
   *   The content of the fixture file.
   */
  protected function loadFixture(string $fixture_file) : string {
    $fixture = dirname(__DIR__, 2) . '/fixtures/' . $fixture_file;
    $content = file_get_contents($fixture);
    $this->assertIsString($content, sprintf('The content of fixture file "%s" is not a string.', $fixture_file));
    return $content;
  }

}
