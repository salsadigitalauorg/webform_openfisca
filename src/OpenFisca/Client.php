<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\OpenFisca;

use Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload;
use Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Implementation of OpenFisca client.
 */
class Client implements ClientInterface {

  /**
   * Constructor.
   *
   * @param string $baseApiUri
   *   The base API endpoint.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP Client service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger service.
   */
  public function __construct(
    protected string $baseApiUri,
    protected GuzzleClientInterface $httpClient,
    protected LoggerInterface $logger,
  ) {
    $this->baseApiUri = static::sanitiseUri($this->baseApiUri);
  }

  /**
   * {@inheritDoc}
   */
  public function getEntities(array $options = []): ?array {
    return $this->get(static::ENDPOINT_ENTITIES, $options)?->getData();
  }

  /**
   * {@inheritDoc}
   */
  public function getVariables(array $options = []): ?array {
    return $this->get(static::ENDPOINT_VARIABLES, $options)?->getData();
  }

  /**
   * {@inheritDoc}
   */
  public function getVariable(string $variable, array $options = []): ?array {
    return $this->get(static::ENDPOINT_VARIABLE . '/' . $variable, $options)?->getData();
  }

  /**
   * {@inheritDoc}
   */
  public function getParameters(array $options = []): ?array {
    return $this->get(static::ENDPOINT_PARAMETERS, $options)?->getData();
  }

  /**
   * {@inheritDoc}
   */
  public function getParameter(string $parameter, array $options = []): ?array {
    return $this->get(static::ENDPOINT_PARAMETER . '/' . $parameter, $options)?->getData();
  }

  /**
   * {@inheritDoc}
   *
   * @see https://openfisca.org/doc/openfisca-web-api/config-openapi.html
   */
  public function getSpec(array $options = []): ?array {
    return $this->get(static::ENDPOINT_SPEC, $options)?->getData();
  }

  /**
   * {@inheritDoc}
   *
   * @see https://openfisca.org/doc/openfisca-web-api/input-output-data.html
   */
  public function calculate(array|RequestPayload $entities): ?ResponsePayload {
    return $this->post(static::ENDPOINT_CALCULATE, $entities);
  }

  /**
   * {@inheritDoc}
   *
   * @see https://openfisca.org/doc/openfisca-web-api/trace-simulation.html
   */
  public function trace(array|RequestPayload $entities): ?ResponsePayload {
    return $this->post(static::ENDPOINT_TRACE, $entities);
  }

  /**
   * {@inheritDoc}
   */
  public function get(string $endpoint, array $options = []) : ?ResponsePayload {
    $openfisca_endpoint = static::sanitiseUri($endpoint);
    try {
      $response = $this->httpClient->request('GET', $openfisca_endpoint, $options);
      return ResponsePayload::fromHttpResponse($response)
        ?->setDebugData('openfisca_api_endpoint', $this->baseApiUri . '/' . $openfisca_endpoint);
    }
    catch (GuzzleException|RequestException $guzzle_exception) {
      $this->logger->error('Error fetching data from OpenFisca API (@uri/@endpoint). Exception: @exception', [
        '@uri' => $this->baseApiUri,
        '@endpoint' => $openfisca_endpoint,
        '@exception' => $guzzle_exception->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function post(string $endpoint, array|RequestPayload $payload) : ?ResponsePayload {
    $openfisca_endpoint = static::sanitiseUri($endpoint);
    try {
      $response = $this->httpClient->request('POST', $openfisca_endpoint, [
        RequestOptions::JSON => ($payload instanceof RequestPayload) ? $payload->getData() : $payload,
      ]);
      return ResponsePayload::fromHttpResponse($response)
        ?->setDebugData('openfisca_api_endpoint', $this->baseApiUri . '/' . $openfisca_endpoint);
    }
    catch (GuzzleException|RequestException $guzzle_exception) {
      $this->logger->error('Error posting to OpenFisca API (@uri/@endpoint). Exception: @exception', [
        '@uri' => $this->baseApiUri,
        '@endpoint' => $openfisca_endpoint,
        '@exception' => $guzzle_exception->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * {@inheritDoc}
   */
  public static function sanitiseUri(string $uri): string {
    return trim($uri, '/');
  }

}
