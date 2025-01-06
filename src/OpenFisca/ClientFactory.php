<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\OpenFisca;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Http\ClientFactory as HttpClientFactory;
use Drupal\Core\Theme\ThemeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Implementation of OpenFisca Client factory.
 */
class ClientFactory implements ClientFactoryInterface {

  /**
   * Constructs a new \Drupal\webform_openfisca\OpenFisca\ClientFactory object.
   *
   * @param \Drupal\Core\Http\ClientFactory $httpClientFactory
   *   HTTP Client Factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   Theme manager.
   */
  public function __construct(
    protected HttpClientFactory $httpClientFactory,
    protected LoggerInterface $logger,
    protected ModuleHandlerInterface $moduleHandler,
    protected ThemeManagerInterface $themeManager,
  ) {}

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\webform_openfisca\WebformOpenFiscaSettings::getOpenFiscaClient()
   * @see https://docs.guzzlephp.org/en/latest/request-options.html
   */
  public function create(string $api_endpoint, array $options = [], array $webform_openfisca_context = []): ClientInterface {
    $options['base_uri'] = Client::sanitiseUri($api_endpoint) . '/';

    // Allow other modules and the active theme to modify the options.
    $context = ['api_endpoint' => $options['base_uri']];
    $this->moduleHandler->alter('webform_openfisca_client_options', $options, $context, $webform_openfisca_context);
    $this->themeManager->alter('webform_openfisca_client_options', $options, $context, $webform_openfisca_context);

    $http_client = $this->httpClientFactory->fromOptions($options);
    return new Client($options['base_uri'], $http_client, $this->logger, $options);
  }

}
