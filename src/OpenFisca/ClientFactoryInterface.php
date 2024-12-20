<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\OpenFisca;

/**
 * Interface for OpenFisca Client factory.
 */
interface ClientFactoryInterface {

  /**
   * Create an OpenFisca client.
   *
   * @param string $api_endpoint
   *   The API endpoint of the OpenFisca instance.
   * @param array $options
   *   Addition options for the HTTP Client.
   * @param array $webform_openfisca_context
   *   The optional context from Webform OpenFisca.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\ClientInterface
   *   The OpenFisca client.
   *
   * @see https://docs.guzzlephp.org/en/latest/request-options.html
   */
  public function create(string $api_endpoint, array $options = [], array $webform_openfisca_context = []): ClientInterface;

}
