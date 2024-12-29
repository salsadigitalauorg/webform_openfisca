<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca\OpenFisca;

use Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload;
use Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload;

/**
 * Interface for OpenFisca client.
 */
interface ClientInterface {

  /**
   * OpenFisca endpoints.
   *
   * @see https://openfisca.org/doc/openfisca-web-api/endpoints.html
   */
  public const string ENDPOINT_CALCULATE = 'calculate';
  public const string ENDPOINT_ENTITIES = 'entities';
  public const string ENDPOINT_PARAMETER = 'parameter';
  public const string ENDPOINT_PARAMETERS = 'parameters';
  public const string ENDPOINT_SPEC = 'spec';
  public const string ENDPOINT_TRACE = 'trace';
  public const string ENDPOINT_VARIABLE = 'variable';
  public const string ENDPOINT_VARIABLES = 'variables';

  /**
   * Get the base URI of the API Client.
   *
   * @return string
   *   The base URI.
   */
  public function getBaseUri(): string;

  /**
   * Get data from an OpenFisca endpoint.
   *
   * @param string $endpoint
   *   The endpoint.
   * @param array $options
   *   The request options.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload|null
   *   The returned data, or NULL upon error.
   */
  public function get(string $endpoint, array $options = []) : ?ResponsePayload;

  /**
   * Get all entities from OpenFisca.
   *
   * @param array $options
   *   The request options.
   *
   * @return array|null
   *   The entities, or NULL upon error.
   */
  public function getEntities(array $options = []): ?array;

  /**
   * Get a parameter from OpenFisca.
   *
   * @param string $parameter
   *   The parameter name.
   * @param array $options
   *   The request options.
   *
   * @return array|null
   *   The parameter data, or NULL upon error.
   */
  public function getParameter(string $parameter, array $options = []): ?array;

  /**
   * Get all parameters from OpenFisca.
   *
   * @param array $options
   *   The request options.
   *
   * @return array|null
   *   The parameters, or NULL upon error.
   */
  public function getParameters(array $options = []): ?array;

  /**
   * Get a variable from OpenFisca.
   *
   * @param string $variable
   *   The variable name.
   * @param array $options
   *   The request options.
   *
   * @return array|null
   *   The variable data, or NULL upon error.
   */
  public function getVariable(string $variable, array $options = []): ?array;

  /**
   * Get all variables from OpenFisca.
   *
   * @param array $options
   *   The request options.
   *
   * @return array<string, mixed>|null
   *   The variables, or NULL upon error.
   */
  public function getVariables(array $options = []): ?array;

  /**
   * Get the spec from OpenFisca.
   *
   * @param array $options
   *   The request options.
   *
   * @return array|null
   *   The spec, or NULL upon error.
   */
  public function getSpec(array $options = []): ?array;

  /**
   * Request OpenFisca to run a calculation.
   *
   * @param array|\Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload $entities
   *   The situation of the entities.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload|null
   *   The response, or NULL upon error.
   */
  public function calculate(array|RequestPayload $entities) : ?ResponsePayload;

  /**
   * Request OpenFisca to analyse a calculation.
   *
   * @param array|\Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload $entities
   *   The situation if the entities.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload|null
   *   The response, or NULL upon error.
   */
  public function trace(array|RequestPayload $entities) : ?ResponsePayload;

  /**
   * Post to an OpenFisca endpoint.
   *
   * @param string $endpoint
   *   The endpoint.
   * @param array|\Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload $payload
   *   The request payload.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload|null
   *   The response, or NULL upon error.
   */
  public function post(string $endpoint, array|RequestPayload $payload) : ?ResponsePayload;

  /**
   * Sanitise a URI.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The sanitised URI.
   */
  public static function sanitiseUri(string $uri): string;

}
