<?php

namespace Drupal\webform_openfisca;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

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
   * Constructor for MymoduleServiceExample.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   A Guzzle client object.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $configFactory) {
    $this->httpClient = $http_client;
    $this->configFactory = $configFactory;
  }

  /**
   * Post to openfisca.
   */
  public function post($api_endpoint, $payload) {

    $response = $this->httpClient->post($api_endpoint, $payload);

    return $response;
  }

  /**
   * Get OpenFisca Variables.
   */
  public function openFiscaGetVariables($api_endpoint) {

    $response = $this->httpClient->request('GET', $api_endpoint . '/variables');

    return json_decode($response->getBody(), TRUE);
  }

  /**
   * Get OpenFisca Parameters.
   */
  public function openFiscaGetParameters($api_endpoint) {

    $response = $this->httpClient->request('GET', $api_endpoint . '/parameters');

    return json_decode($response->getBody(), TRUE);
  }

  /**
   * Get OpenFisca Variable.
   */
  public function openFiscaGetVariable($api_endpoint, $variable) {

    $response = $this->httpClient->request('GET', $api_endpoint . '/variable/' . $variable);

    return json_decode($response->getBody(), TRUE);
  }

  /**
   * Get OpenFisca parameter.
   */
  public function openFiscaGetParameter($api_endpoint, $parameter) {

    $response = $this->httpClient->request('GET', $api_endpoint . '/parameter/' . $parameter);

    return json_decode($response->getBody(), TRUE);
  }

  /**
   * Get Open Fisca attribute details.
   */
  public function openFiscaGetAttributeDetails($href) {

    if (isset($href)) {
      $response = $this->httpClient->request('GET', $href);

      return json_decode($response->getBody(), TRUE);
    }
  }

}
