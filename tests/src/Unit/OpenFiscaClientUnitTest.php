<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Component\Utility\NestedArray;
use Drupal\webform_openfisca\OpenFisca\Client;
use Drupal\webform_openfisca\OpenFisca\Helper;

/**
 * Tests the OpenFisca Client class.
 *
 * @group webform_openfisca
 * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\Client
 */
class OpenFiscaClientUnitTest extends BaseUnitTestCase {

  /**
   * Tests the get() method of OpenFisca Client.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::get
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getEntities
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::__construct
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getBaseUri
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getHttpClientOptions
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::sanitiseUri
   */
  public function testGet(): void {
    $logger = $this->mockLogger();
    $http_client = $this->mockHttpClient('payload/api/api.json');
    $client = new Client('https://openfisca.test/api/', $http_client, $logger, ['headers' => ['Authorization' => 'Bearer TOKEN']]);
    $this->assertSame('https://openfisca.test/api', $client->getBaseUri());
    $http_client_options = $client->getHttpClientOptions();
    $this->assertArrayHasKey('headers', $http_client_options);
    $this->assertArrayHasKey('Authorization', $http_client_options['headers']);
    $this->assertEquals('Bearer TOKEN', $http_client_options['headers']['Authorization']);

    // Tests the generic get() method.
    $response_payload = $client->get('/');
    $this->assertTrue($response_payload->keyPathExists('welcome'));
    $this->assertStringContainsString('This is the root of an OpenFisca Web API', $response_payload->getValue('welcome'));
    $this->assertTrue($response_payload->hasDebugData('openfisca_api_endpoint'));
    $this->assertEquals('https://openfisca.test/api/', $response_payload->getDebugData('openfisca_api_endpoint'));

    // Tests when an error occurs.
    $http_client = $this->mockHttpClientWithException();
    $client = new Client('https://openfisca.test/api', $http_client, $logger);
    $response_payload = $client->get('/');
    $this->assertNull($response_payload);
  }

  /**
   * Tests the post() method of OpenFisca Client.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::post
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::calculate
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::trace
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::__construct
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::sanitiseUri
   */
  public function testPost(): void {
    $logger = $this->mockLogger();
    $http_client = $this->mockHttpClient('payload/response.json');
    $client = new Client('https://openfisca.test/api', $http_client, $logger);

    // Tests the generic post() method.
    $request_payload = $this->mockRequestPayload('payload/request.json');
    $response_payload = $client->post('/test-post', $request_payload);
    $this->assertTrue($response_payload->keyPathExists('persons.Person.australian_citizen_or_permanent_resident'));
    $this->assertTrue($response_payload->hasDebugData('openfisca_api_endpoint'));
    $this->assertEquals('https://openfisca.test/api/test-post', $response_payload->getDebugData('openfisca_api_endpoint'));

    // Tests the calculate() method.
    $response_payload = $client->calculate($request_payload);
    $this->assertTrue($response_payload->keyPathExists('persons.Person.australian_citizen_or_permanent_resident'));
    $this->assertTrue($response_payload->hasDebugData('openfisca_api_endpoint'));
    $this->assertEquals('https://openfisca.test/api/calculate', $response_payload->getDebugData('openfisca_api_endpoint'));

    // Tests the trace() method.
    $response_payload = $client->trace($request_payload);
    $this->assertTrue($response_payload->keyPathExists('persons.Person.australian_citizen_or_permanent_resident'));
    $this->assertTrue($response_payload->hasDebugData('openfisca_api_endpoint'));
    $this->assertEquals('https://openfisca.test/api/trace', $response_payload->getDebugData('openfisca_api_endpoint'));

    // Tests when an error occurs.
    $http_client = $this->mockHttpClientWithException();
    $client = new Client('https://openfisca.test/api', $http_client, $logger);
    $response_payload = $client->post('/test-post', $request_payload->getData());
    $this->assertNull($response_payload);
  }

  /**
   * Tests the get*() methods of OpenFisca Client.
   *
   * @dataProvider dataProviderTestGetMethods
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getEntities
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getParameters
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getParameter
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getVariables
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getVariable
   * @covers \Drupal\webform_openfisca\OpenFisca\Client::getSpec
   */
  public function testGetMethods(string $json_file, string $method, array $expected_keys, array $expected_values): void {
    $http_client = $this->mockHttpClient($json_file);
    $client = new Client('https://openfisca.test/api', $http_client, $this->mockLogger());
    $method_array = explode('::', $method);
    $method_name = $method_array[0];
    $method_parameter = $method_array[1] ?? NULL;
    $response = $method_parameter ? $client->$method_name($method_parameter) : $client->$method_name();
    $this->assertNotNull($response, 'The response is NULL.');
    $this->assertIsArray($response, 'The response is not an array.');
    foreach ($expected_keys as $key) {
      $path = [];
      Helper::parseOpenFiscaFieldMapping($key, path: $path);
      $this->assertTrue(NestedArray::keyExists($response, $path), sprintf('[%s] The key "%s" does not exist in response.', $method, $key));
    }
    foreach ($expected_values as $key => $value) {
      $path = [];
      Helper::parseOpenFiscaFieldMapping($key, path: $path);
      $this->assertEquals($value, NestedArray::getValue($response, $path), sprintf('[%s] The value of key "%s" is not "%s".', $method, $key, var_export($value, TRUE)));
    }
  }

  /**
   * Provides test data for testGetMethods().
   *
   * @return array[]
   *   The test data.
   */
  public static function dataProviderTestGetMethods(): array {
    return [
      [
        'payload/api/entities.json',
        'getEntities',
        ['person', 'person.plural'],
        ['person.plural' => 'persons'],
      ],
      [
        'payload/api/parameters.json',
        'getParameters',
        ['austudy_age_threshold', 'austudy_age_threshold.description'],
        ['austudy_age_threshold.description' => 'Minimum age at which a person is eligible for AU Study.'],
      ],
      [
        'payload/api/parameter/austudy_age_threshold.json',
        'getParameter::austudy_age_threshold',
        ['id', 'values.2024-05-01'],
        ['id' => 'austudy_age_threshold', 'values.2024-05-01' => 25],
      ],
      [
        'payload/api/spec.json',
        'getSpec',
        ['components', 'components.headers.Country-Package.schema.type'],
        ['components.headers.Country-Package.schema.type' => 'string'],
      ],
      [
        'payload/api/variables.json',
        'getVariables',
        ['act_child_work_compliant', 'act_child_work_compliant.description'],
        ['act_child_work_compliant.description' => 'ACT child work compliant'],
      ],
      [
        'payload/api/variable/aboriginal_torres_strait_islander.json',
        'getVariable::aboriginal_torres_strait_islander',
        ['id', 'valueType'],
        ['id' => 'aboriginal_torres_strait_islander', 'valueType' => 'Boolean'],
      ],
    ];
  }

}
