<?php

namespace Drupal\webform_openfisca;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Class OpenFiscaConnector.
 */
class OpenFiscaConnectorService {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor for MymoduleServiceExample.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $configFactory, LoggerInterface $logger) {
    $this->httpClient = $http_client;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
  }

  /**
   * Post to openfisca.
   */
  public function post($api_endpoint, $payload) {
    try {
      $response = $this->httpClient->post($api_endpoint, $payload);
      return $response;
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error posting to OpenFisca API: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Get OpenFisca Variables.
   */
  public function openFiscaGetVariables($api_endpoint) {
    try {
      $response = $this->httpClient->request('GET', $api_endpoint . '/variables');
      return json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error fetching OpenFisca variables: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Get OpenFisca Parameters.
   */
  public function openFiscaGetParameters($api_endpoint) {
    try {
      $response = $this->httpClient->request('GET', $api_endpoint . '/parameters');
      return json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error fetching OpenFisca parameters: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Get OpenFisca Variable.
   */
  public function openFiscaGetVariable($api_endpoint, $variable) {
    try {
      $response = $this->httpClient->request('GET', $api_endpoint . '/variable/' . $variable);
      return json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error fetching OpenFisca variable: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Get OpenFisca parameter.
   */
  public function openFiscaGetParameter($api_endpoint, $parameter) {
    try {
      $response = $this->httpClient->request('GET', $api_endpoint . '/parameter/' . $parameter);
      return json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error fetching OpenFisca parameter: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Get Open Fisca attribute details.
   */
  public function openFiscaGetAttributeDetails($href) {
    try {
      $response = $this->httpClient->request('GET', $href);
      return json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error fetching OpenFisca attribute details: ' . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Get OpenFisca Entities.
   */
  public function openFiscaGetEntities($api_endpoint) {
    try {
      $response = $this->httpClient->request('GET', $api_endpoint . '/entities');
      return json_decode($response->getBody(), TRUE);
    }
    catch (RequestException $e) {
      // Handle exception.
      $this->logger->error('Error fetching OpenFisca entities: ' . $e->getMessage());
      return NULL;
    }
  }

}
