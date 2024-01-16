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
   * Get Open Fisca Variable details.
   */
  public function openFiscaGetvariableDetails(array $variable) {

    if (isset($variable['href'])) {
      $response = $this->httpClient->request('GET', $variable['href']);

      return json_decode($response->getBody(), TRUE);
    }
  }

  /**
   * Get Open Fisca API values.
   */
  public function openFiscaGetApiValues($variable) {

    $config = $this->configFactory->get('webform_openfisca.settings');
    $api_url = $config->get('webform_openfisca.api_endpoint');

    // $period = '2022-07';
    $period_query = \Drupal::request()->query->get('period');
    if (isset($period_query)) {
      $period = $period_query;
      $query_append['period'] = $period;
    }
    else {
      $period_default = $period = '2022-08-01';
    }
    $period_default = '2022-08-01';

    $request = $this->httpClient->get($api_url . '/parameter/' . $variable);
    $response = json_decode($request->getBody(), TRUE);

    if (isset($response->values->$period)) {
      // TBC - add error handling.
      return $response->values->$period;
    }
    else {
      // TBC - add error handling.
      return $response->values->$period_default;
    }
  }

}
