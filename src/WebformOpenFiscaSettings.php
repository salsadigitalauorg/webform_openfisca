<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

use Drupal\Component\Serialization\Json;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\OpenFisca\Helper as OpenFiscaHelper;
use Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface as OpenFiscaClientFactoryInterface;
use Drupal\webform_openfisca\OpenFisca\ClientInterface as OpenFiscaClientInterface;

/**
 * Abstraction of the OpenFisca settings of a webform.
 */
class WebformOpenFiscaSettings {

  /**
   * The ID of the host webform.
   */
  protected string $webform_id = '';

  /**
   * Whether OpenFisca integration is enabled.
   */
  protected bool $enabled = FALSE;

  /**
   * Whether debug mode is enabled.
   */
  protected bool $debug = FALSE;

  /**
   * Whether to log OpenFisca API calls.
   */
  protected bool $apiLogging = FALSE;

  /**
   * The API Endpoint of OpenFisca.
   */
  protected string $apiEndpoint = '';

  /**
   * The HTTP Authorization header to connect to the API.
   */
  protected string $apiAuthorizationHeader = '';

  /**
   * JSON-encoded OpenFisca variables.
   *
   * @var string
   */
  protected string $jsonVariables = '[]';

  /**
   * OpenFisca variables.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $variables = [];

  /**
   * JSON-encoded OpenFisca parameters.
   *
   * @var string
   */
  protected string $jsonParameters = '[]';

  /**
   * OpenFisca parameters.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $parameters = [];

  /**
   * OpenFisca parameters used as tokens.
   *
   * @var string
   */
  protected string $parameterTokens = '';

  /**
   * The JSON-encoded field mapping.
   *
   * @var string
   */
  protected string $jsonFieldMappings = '[]';

  /**
   * The field mapping.
   *
   * @var array<string, string>
   */
  protected array $fieldMappings = [];

  /**
   * The keys for return values.
   */
  protected string $returnKeys = '';

  /**
   * JSON-encoded OpenFisca Entity roles.
   *
   * @var string
   */
  protected string $jsonEntityRoles = '[]';

  /**
   * OpenFisca Entity roles.
   *
   * @var array<string, array<string, mixed>>
   */
  protected array $entityRoles = [];

  /**
   * JSON-encoded immediate response field mapping.
   *
   * @var string
   */
  protected string $jsonImmediateResponseMapping = '[]';

  /**
   * Immediate response field mapping.
   *
   * @var array<string, bool>
   */
  protected array $immediateResponseMapping = [];

  /**
   * Whether to display the Ajax indicator for immediate response.
   */
  protected bool $immediateResponseAjaxIndicator = FALSE;

  /**
   * Immediate exit keys.
   */
  protected string $immediateExitKeys = '';

  /**
   * Constructor.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   */
  protected function __construct(WebformInterface $webform) {
    $this->webform_id = $webform->id();
    $this->enabled = (bool) $webform->getThirdPartySetting('webform_openfisca', 'fisca_enabled', FALSE);
    $this->debug = (bool) $webform->getThirdPartySetting('webform_openfisca', 'fisca_debug_mode', FALSE);
    $this->apiLogging = (bool) $webform->getThirdPartySetting('webform_openfisca', 'fisca_logging_mode', FALSE);
    $this->apiEndpoint = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_api_endpoint', '');
    $this->apiAuthorizationHeader = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_api_authorization_header', '');
    $this->returnKeys = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_return_key', '');

    $this->jsonVariables = $webform->getThirdPartySetting('webform_openfisca', 'fisca_variables', '[]');
    $variables = Json::decode($this->jsonVariables);
    $this->variables = is_array($variables) ? $variables : [];

    $this->jsonParameters = $webform->getThirdPartySetting('webform_openfisca', 'fisca_parameters', '[]');
    $parameters = Json::decode($this->jsonParameters);
    $this->parameters = is_array($parameters) ? $parameters : [];

    $this->parameterTokens = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_parameter_tokens', '');

    $this->jsonFieldMappings = $webform->getThirdPartySetting('webform_openfisca', 'fisca_field_mappings', '[]');
    $field_mappings = Json::decode($this->jsonFieldMappings);
    $this->fieldMappings = is_array($field_mappings) ? $field_mappings : [];

    $this->jsonEntityRoles = $webform->getThirdPartySetting('webform_openfisca', 'fisca_entity_roles', '[]');
    $entity_roles = Json::decode($this->jsonEntityRoles);
    $this->entityRoles = is_array($entity_roles) ? $entity_roles : [];

    $this->jsonImmediateResponseMapping = $webform->getThirdPartySetting('webform_openfisca', 'fisca_immediate_response_mapping', '[]');
    $immediate_response_mapping = Json::decode($this->jsonImmediateResponseMapping);
    $this->immediateResponseMapping = is_array($immediate_response_mapping) ? $immediate_response_mapping : [];

    $this->immediateResponseAjaxIndicator = (bool) $webform->getThirdPartySetting('webform_openfisca', 'fisca_immediate_response_ajax_indicator', FALSE);
    $this->immediateExitKeys = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_immediate_exit_mapping', '');
  }

  /**
   * Load OpenFisca settings from a webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   *
   * @return static
   *   The OpenFisca settings.
   */
  public static function load(WebformInterface $webform): static {
    return new static($webform);
  }

  /**
   * Get the Webform ID.
   *
   * @return string
   *   The ID.
   */
  public function getWebformId(): string {
    return $this->webform_id;
  }

  /**
   * Check if OpenFisca integration is enabled.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function isEnabled() : bool {
    return $this->enabled;
  }

  /**
   * Check if debug mode is enabled.
   *
   * @return bool
   *   TRUE if debug mode is enabled.
   */
  public function isDebugEnabled() : bool {
    return $this->debug;
  }

  /**
   * Check if API logging mode is enabled.
   *
   * @return bool
   *   TRUE if logging mode is enabled.
   */
  public function isLoggingEnabled() : bool {
    return $this->apiLogging;
  }

  /**
   * Get the API Endpoint of OpenFisca.
   *
   * @return string
   *   The endpoint.
   */
  public function getApiEndpoint() : string {
    return $this->apiEndpoint;
  }

  /**
   * Check if the API endpoint is set.
   *
   * @return bool
   *   TRUE if set.
   */
  public function hasApiEndpoint() : bool {
    return !empty($this->apiEndpoint);
  }

  /**
   * Get the Authorization header to connect to OpenFisca API.
   *
   * @return string
   *   The header.
   */
  public function getApiAuthorizationHeader() : string {
    return $this->apiAuthorizationHeader;
  }

  /**
   * Check if the Authorization header to connect to OpenFisca API is set.
   *
   * @return bool
   *   TRUE if set.
   */
  public function hasApiAuthorizationHeader() : bool {
    return !empty($this->apiAuthorizationHeader);
  }

  /**
   * Get the JSON-encoded OpenFisca variables.
   *
   * @return string
   *   The JSON string.
   */
  public function getJsonVariables() : string {
    return $this->jsonVariables;
  }

  /**
   * Get OpenFisca variables.
   *
   * @return array<string, array<string, mixed>>
   *   The variables.
   */
  public function getVariables() : array {
    return $this->variables;
  }

  /**
   * Get an OpenFisca variable.
   *
   * @param string $variable_name
   *   The variable name.
   *
   * @return array|false
   *   The variable, or FALSE if not exist.
   */
  public function getVariable(string $variable_name) : array|false {
    $variable = $this->variables[$variable_name] ?? FALSE;
    return is_array($variable) ? $variable : FALSE;
  }

  /**
   * Get the JSON-encoded OpenFisca parameters.
   *
   * @return string
   *   The JSON string.
   */
  public function getJsonParameters() : string {
    return $this->jsonParameters;
  }

  /**
   * Get OpenFisca parameters.
   *
   * @return array<string, array<string, mixed>>
   *   The parameters.
   */
  public function getParameters() : array {
    return $this->parameters;
  }

  /**
   * Get an OpenFisca parameter.
   *
   * @param string $parameter_name
   *   The parameter name.
   *
   * @return array|false
   *   The parameter, or FALSE if not exist.
   */
  public function getParameter(string $parameter_name) : array|false {
    $parameter = $this->parameters[$parameter_name] ?? FALSE;
    return is_array($parameter) ? $parameter : FALSE;
  }

  /**
   * Get OpenFisca parameters used as tokens.
   *
   * @return string
   *   The parameter tokens.
   */
  public function getPlainParameterTokens() : string {
    return $this->parameterTokens;
  }

  /**
   * Get OpenFisca parameters used as tokens as an array.
   *
   * @return string[]
   *   The parameter tokens.
   */
  public function getParameterTokens() : array {
    return OpenFiscaHelper::expandCsvString($this->parameterTokens);
  }

  /**
   * Get the JSON-encoded field mappings.
   *
   * @return string
   *   The JSON string.
   */
  public function getJsonFieldMappings() : string {
    return $this->jsonFieldMappings;
  }

  /**
   * Get the field mappings.
   *
   * @return array<string, string>
   *   The mapping.
   */
  public function getFieldMappings() : array {
    return $this->fieldMappings;
  }

  /**
   * Get a field mapping.
   *
   * @param string $field_name
   *   The webform field name.
   *
   * @return string|false
   *   The field mapping, or FALSE if not exist.
   *
   */
  public function getFieldMapping(string $field_name) : string|false {
    return $this->fieldMappings[$field_name] ?? FALSE;
  }

  /**
   * Get the keys for return values.
   *
   * @return string
   *   The keys (comma-separated)
   */
  public function getPlainReturnKeys() : string {
    return $this->returnKeys;
  }

  /**
   * Get the keys for return values as an array.
   *
   * @return string[]
   *   The keys.
   */
  public function getReturnKeys() : array {
    return OpenFiscaHelper::expandCsvString($this->returnKeys);
  }

  /**
   * Get the JSON-encoded entity roles.
   *
   * @return string
   *   The JSON string.
   */
  public function getJsonEntityRoles() : string {
    return $this->jsonEntityRoles;
  }

  /**
   * Get the entity roles.
   *
   * @return array<string, array<string, mixed>>
   *   The entity roles.
   */
  public function getEntityRoles() : array {
    return $this->entityRoles;
  }

  /**
   * Get an entity role.
   *
   * @param string $field_name
   *   The webform field name.
   *
   * @return array<string, array<string, mixed>>|false
   *   The entity role, or FALSE if not exist.
   */
  public function getEntityRole(string $field_name) : array|false {
    $entity_role = $this->entityRoles[$field_name] ?? FALSE;
    return is_array($entity_role) ? $entity_role : FALSE;
  }

  /**
   * Get the JSON-encoded immediate response mapping.
   *
   * @return string
   *   The JSON string.
   */
  public function getJsonImmediateResponseMapping() : string {
    return $this->jsonImmediateResponseMapping;
  }

  /**
   * Get the immediate response mapping.
   *
   * @return array<string, bool>
   *   The mapping.
   */
  public function getImmediateResponseMapping() : array {
    return $this->immediateResponseMapping;
  }

  /**
   * Check if a webform field has immediate response enabled.
   *
   * @param string $field_name
   *   The webform field name.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function fieldHasImmediateResponse(string $field_name) : bool {
    return !empty($this->immediateResponseMapping[$field_name]);
  }

  /**
   * Check if the Ajax indicator for immediate response should be displayed.
   *
   * @return bool
   *   TRUE if enabled.
   */
  public function hasImmediateResponseAjaxIndicator() : bool {
    return $this->immediateResponseAjaxIndicator;
  }

  /**
   * Get the immediate exit keys.
   *
   * @return string
   *   The keys (comma-separated).
   */
  public function getPlainImmediateExitKeys() : string {
    return $this->immediateExitKeys;
  }

  /**
   * Get the immediate exit keys as an array.
   *
   * @return string[]
   *   The keys.
   */
  public function getImmediateExitKeys() : array {
    return OpenFiscaHelper::expandCsvString($this->immediateExitKeys);
  }

  /**
   * Remove all mappings of a webform element from OpenFisca settings.
   *
   * @param string $element_key
   *   The key of the element.
   *
   * @return self
   *   A new object with the updated settings.
   */
  public function removeWebformElementMappings(string $element_key) : self {
    $updated_settings = clone $this;
    // Remove the field mapping of the element.
    $field_mapping = $updated_settings->getFieldMapping($element_key);
    if ($field_mapping !== FALSE) {
      unset($updated_settings->fieldMappings[$element_key]);
      $updated_settings->jsonFieldMappings = OpenFiscaHelper::jsonEncodePretty($updated_settings->fieldMappings);

      // Remove the OpenFisca variable.
      $variable_name = OpenFiscaHelper::parseOpenFiscaFieldMapping($field_mapping);
      if ($updated_settings->getVariable($variable_name) !== FALSE) {
        unset($updated_settings->variables[$variable_name]);
        $updated_settings->jsonVariables = OpenFiscaHelper::jsonEncodePretty($updated_settings->variables);
      }
    }

    // Remove the entity role.
    unset($updated_settings->entityRoles[$element_key]);
    $updated_settings->jsonEntityRoles = OpenFiscaHelper::jsonEncodePretty($updated_settings->entityRoles);

    // Remove the immediate response mapping.
    unset($updated_settings->immediateResponseMapping[$element_key]);
    $updated_settings->jsonImmediateResponseMapping = OpenFiscaHelper::jsonEncodePretty($updated_settings->immediateResponseMapping);

    return $updated_settings;
  }

  /**
   * Change the OpenFisca API Endpoint.
   *
   * @param string $api_endpoint
   *   The new API Endpoint.
   *
   * @return self
   *   A new object with the updated settings.
   */
  public function updateApiEndpoint(string $api_endpoint) : self {
    $updated_settings = clone $this;
    $updated_settings->apiEndpoint = $api_endpoint;
    return $updated_settings;
  }

  /**
   * Format a period value of a variable.
   *
   * @param string $variable_name
   *   The variable name.
   * @param string $period
   *   The period.
   *
   * @return string
   *   The formatted period.
   *
   * @see https://openfisca.org/doc/coding-the-legislation/35_periods.html
   */
  public function formatVariablePeriod(string $variable_name, string $period = 'now') : string {
    $fisca_variable = $this->getVariable($variable_name);
    if ($fisca_variable === FALSE) {
      return '';
    }
    $period_format = $fisca_variable['definitionPeriod'] ?? '';
    return OpenFiscaHelper::formatPeriod($period_format, $period);
  }

  /**
   * Get an OpenFisca API client for this Webform OpenFisca settings.
   *
   * @param \Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface $client_factory
   *   The OpenFisca client factory service.
   * @param array $options
   *   Additional options passed to client.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\ClientInterface
   *   The client.
   *
   * @see https://docs.guzzlephp.org/en/latest/request-options.html
   * @see https://docs.guzzlephp.org/en/latest/request-options.html#headers
   */
  public function getOpenFiscaClient(OpenFiscaClientFactoryInterface $client_factory, array $options = []) : OpenFiscaClientInterface {
    if ($this->hasApiAuthorizationHeader()) {
      $options['headers']['Authorization'] = $this->getApiAuthorizationHeader();
    }
    $context = ['webform_openfisca_settings' => clone $this];
    return $client_factory->create($this->getApiEndpoint(), $options, $context);
  }

}
