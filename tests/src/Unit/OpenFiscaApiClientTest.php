<?php

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\OpenFiscaApiClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the OpenFiscaApiClient class.
 *
 * @group webform_openfisca
 */
class OpenFiscaApiClientTest extends UnitTestCase {

  /**
   * Tests the getResponse() method with successful response.
   */
  public function testGetResponseSuccess() {
    // Mock the Guzzle ClientInterface.
    $httpClient = $this->createMock(ClientInterface::class);

    // Prepare the response body.
    $responseData = [
      "persons" => [
        "Mum" => [
          "date_of_birth" => [
            "2024-01-25" => "2013-01-01"
          ],
          "family_scheme__caregiver_age_qualifies" => [
            "2024-01" => false
          ]
        ],
        "Dad" => [
          "date_of_birth" => [
            "2024-01-25" => "1996-01-01"
          ],
          "family_scheme__caregiver_age_qualifies" => [
            "2024-01" => true
          ]
        ]
      ],
      "families" => [
        "family" => [
          "partners" => [
            "Mum",
            "Dad"
          ]
        ]
      ]
    ];

    $responseBody = json_encode($responseData);

    // Prepare a successful response.
    $response = new Response(200, [], $responseBody);

    // Set up the expectations.
    $httpClient->expects($this->once())
      ->method('request')
      ->willReturn($response);

    // Create an instance of the OpenFiscaApiClient class.
    $openFiscaApiClient = new OpenFiscaApiClient($httpClient);

    // Call the getResponse() method.
    $result = $openFiscaApiClient->getResponse('GET', '/example/endpoint');

    // Ensure that the response data matches the expected data.
    $this->assertEquals($responseData, $result);
  }

  /**
   * Tests the getResponse() method with failed response.
   */
  public function testGetResponseFailure() {
    // Mock the Guzzle ClientInterface.
    $httpClient = $this->createMock(ClientInterface::class);

    // Prepare a failed response.
    $response = new Response(404);

    // Set up the expectations.
    $httpClient->expects($this->once())
      ->method('request')
      ->willThrowException(new RequestException('Error', $this->createMock(\Psr\Http\Message\RequestInterface::class), $response));

    // Create an instance of the OpenFiscaApiClient class.
    $openFiscaApiClient = new OpenFiscaApiClient($httpClient);

    // Call the getResponse() method.
    $result = $openFiscaApiClient->getResponse('GET', '/example/endpoint');

    // Ensure that the result is null in case of failure.
    $this->assertNull($result);
  }

}
