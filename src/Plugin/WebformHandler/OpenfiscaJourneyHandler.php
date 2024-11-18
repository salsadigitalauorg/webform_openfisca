<?php

namespace Drupal\webform_openfisca\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_openfisca\OpenFiscaConnectorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webform submission debug handler.
 *
 * @WebformHandler(
 *   id = "openfisca_journey",
 *   label = @Translation("Openfisca Journey handler"),
 *   category = @Translation("External"),
 *   description = @Translation("Maintain the journey server based on calculations from Openfisca."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class OpenfiscaJourneyHandler extends WebformHandlerBase {

  /**
   * Format YAML.
   */
  const string FORMAT_YAML = 'yaml';

  /**
   * Format JSON.
   */
  const string FORMAT_JSON = 'json';

  /**
   * Current request.
   */
  protected Request $request;

  /**
   * OpenFisca Connector service.
   */
  protected OpenFiscaConnectorService $openfiscaConnector;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->openfiscaConnector = $container->get('webform_openfisca.open_fisca_connector_service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() : array {
    return [
      'format' => 'yaml',
      'submission' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() : array {
    $settings = $this->getSettings();
    switch ($settings['format']) {
      case static::FORMAT_JSON:
        $settings['format'] = $this->t('JSON');
        break;

      case static::FORMAT_YAML:
      default:
        $settings['format'] = $this->t('YAML');
        break;
    }
    return [
      '#settings' => $settings,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    // @todo Fisca handler configuration.
    return $this->setSettingsParents($form);
  }

  /**
   * Get OpenFisca setting of a webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   * @param string $key
   *   The setting.
   * @param mixed|NULL $default_value
   *   The default value.
   * @param bool $json_decode
   *   Whether to JSON decode the setting.
   *
   * @return mixed
   *   The value of setting.
   */
  protected function getWebformOpenFiscaSetting(WebformInterface $webform, string $key, mixed $default_value = NULL, bool $json_decode = TRUE) : mixed {
    $setting = $webform->getThirdPartySetting('webform_openfisca', $key);
    return ($json_decode ? Json::decode($setting) : $setting) ?? $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) : void {
    $webform = $webform_submission->getWebform();
    $query_append = [];
    $fisca_field_mappings = $this->getWebformOpenFiscaSetting($webform, 'fisca_field_mappings', []);
    $fisca_return_key = $this->getWebformOpenFiscaSetting($webform, 'fisca_return_key', json_decode: FALSE);
    $result_keys = explode(',', $fisca_return_key);
    $payload = $this->prepareOpenfiscaPayload($webform_submission, $query_append, $fisca_field_mappings, $result_keys);

    $fisca_fields = [];
    $result_values = [];
    $response = $this->calculateBenefits($payload, $result_keys, $query_append, $fisca_field_mappings, $fisca_fields, $result_values);

    $query = '';
    $confirmation_url = $this->overrideConfirmationUrl($query_append, $fisca_fields, $result_values, $query);

    // Debug.
    $this->displayDebug($payload, $response, $result_values, $fisca_fields, $query, $confirmation_url);
  }

  /**
   * Prepare the payload for querying Openfisca.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission.
   * @param array $query_append
   *   Query append.
   * @param array $fisca_field_mappings
   *   Openfisca field mappings.
   * @param array $result_keys
   *   Openfisca result keys.
   *
   * @return array
   *   The payload.
   */
  protected function prepareOpenfiscaPayload(WebformSubmissionInterface $webform_submission, array &$query_append, array &$fisca_field_mappings, array &$result_keys) : array {
    $settings = $this->getSettings();
    $data = ($settings['submission']) ? $webform_submission->toArray(TRUE) : $webform_submission->getData();

    // Extract all submission values as variables.
    extract($data, EXTR_OVERWRITE);

    $webform = $webform_submission->getWebform();

    $fisca_variables = $this->getWebformOpenFiscaSetting($webform, 'fisca_variables', []);

    $fisca_entity_roles = $this->getWebformOpenFiscaSetting($webform, 'fisca_entity_roles', []);
    // Prepare the person payload.
    $payload = [];

    // Period.
    $period_query = $this->request->query->get('period');
    $query_append = [];

    if (isset($period_query) || (isset($fisca_field_mappings['period'], $data[$fisca_field_mappings['period']]))) {
      $period = $period_query ?? $data[$fisca_field_mappings['period']];
      $query_append['period'] = $period;
      $query_append['change'] = 1;
    }
    else {
      $period = date('Y-m-d');
    }
    unset($fisca_field_mappings['period']);

    foreach ($fisca_field_mappings as $webform_key => $openfisca_key) {
      // We don't what to use the keys which are not mapped.
      if ($openfisca_key === '_nil') {
        if (isset($data[$webform_key])) {
          $query_append[$webform_key] = $data[$webform_key];
        }
      }
      else {
        if (!empty($data[$webform_key])) {
          // The openfisca_key will be in the format
          // variable_entity.entity_key.variable_name
          // eg. persons.personA.age
          // We need to dynamically create a multidimensional array
          // from the list of keys and then set the value.
          $keys = explode('.', $openfisca_key);
          $variable = array_pop($keys);
          $ref = &$payload;
          while ($key = array_shift($keys)) {
            $ref = &$ref[$key];
          }
          $val = strtolower($data[$webform_key]) === 'true' || strtolower($data[$webform_key]) === 'false' ? strtolower($data[$webform_key]) === 'true' : $data[$webform_key];
          $formatted_period = $this->formatVariablePeriod($fisca_variables, $variable, $period);
          if (!empty($formatted_period)) {
            $ref[$variable] = [$formatted_period => $val];
          }
        }
      }
    }

    // Create result keys entities with null values to tell OpenFisca
    // to calculate these variables eg. { persons.personA.variable_name: null }.
    foreach ($result_keys as $result_key) {
      // The result_key will be in the format
      // variable_entity.entity_key.variable_name
      // eg. persons.personA.age
      // We need to dynamically create a multidimensional array
      // from the list of keys and then set the value.
      $keys = explode('.', $result_key);
      $variable = array_pop($keys);
      $ref = &$payload;
      while ($key = array_shift($keys)) {
        $ref = &$ref[$key];
      }
      $formatted_period = $this->formatVariablePeriod($fisca_variables, $variable, $period);
      if (!empty($formatted_period)) {
        $ref[$variable] = [$formatted_period => NULL];
      }
    }

    // Create group entities with roles
    // eg. { families.familyA.children: ["childA", "childB"] }.
    foreach ($fisca_entity_roles as $fisca_entity_role) {
      $role = $fisca_entity_role['role'] ?? NULL;
      $is_array = $fisca_entity_role['is_array'] ?? FALSE;
      if (isset($role)) {
        // The role will be in the format
        // group_entity.group_entity_key.role.entity_key
        // eg. families.familyA.principal.personA
        // eg. families.familyA.children.child1
        // We need to dynamically create an multidimensional array
        // from the list of keys and then set the value.
        $keys = explode('.', $role);
        $entity_key = array_pop($keys);
        // Only add the role to the payload if the payload entity_key exists.
        if (str_contains(json_encode($payload), $entity_key)) {
          $ref = &$payload;
          while ($key = array_shift($keys)) {
            $ref = &$ref[$key];
          }
          if ($is_array) {
            $ref[] = $entity_key;
          }
          else {
            $ref = $entity_key;
          }
        }
      }
    }

    // Add immediate exit mapping to the payload.
    $immediate_exit_mapping = $this->getWebformOpenFiscaSetting($this->getWebform(), 'fisca_immediate_exit_mapping', default_value: '', json_decode: FALSE);
    foreach (explode(',', $immediate_exit_mapping) as $immediate_exit_key) {
      $immediate_exit_key = trim($immediate_exit_key);
      $keys = explode('.', $immediate_exit_key);
      $variable = array_pop($keys);
      if (NestedArray::keyExists($payload, $keys) && !NestedArray::keyExists($payload, array_merge($keys, [$variable]))) {
        $formatted_period = $this->formatVariablePeriod($fisca_variables, $variable, $period);
        NestedArray::setValue($payload, array_merge($keys, [$variable]), [$formatted_period => NULL]);
      }
    }

    return $payload;
  }

  /**
   * Calculate benefits from Openfisca.
   *
   * @param array $payload
   *   The payload.
   * @param array $result_keys
   *   The result keys.
   * @param array $fisca_field_mappings
   *   Openfisca field mappings.
   *
   * @return mixed
   *   Raw response from Openfisca.
   */
  protected function calculateBenefits(array $payload, array &$result_keys, array &$query_append, array &$fisca_field_mappings, array &$fisca_fields, array &$result_values) : mixed {
    $openfisca_endpoint = $this->getWebformOpenFiscaSetting($this->getWebform(), 'fisca_api_endpoint', json_decode: FALSE);
    $request = $this->openfiscaConnector->post($openfisca_endpoint  . '/calculate', ['json' => $payload]);
    $response = Json::decode($request?->getBody());

    // Get the values of the return keys.
    foreach ($result_keys as $result_key) {
      // The result_keys will be in the format entity.entity_key.variable_name
      // eg. persons.personA.age
      // We need to dynamically create an multidimensional array
      // from the list of keys and then set the value.
      $keys = explode('.', $result_key);
      // @todo Refactor this ambiguous section. Ideally use NestedArray.
      $ref = &$response;
      while ($key = array_shift($keys)) {
        $ref = &$ref[$key] ?? NULL;
      }
      // We will not know the period key will be, get the first items value
      // eg. { variable_name: { period: variable_value }}
      // eg. { age: { "2022-11-01": 20 }}.
      if (isset($ref)) {
        $objIterator = new \ArrayIterator($ref);
        $key = $objIterator->key();
        if (isset($ref[$key])) {
          $result_values[$result_key] = $ref[$key];
        }
      }
    }

    // To calculate the total benefit.
    $total_benefit = 0;

    // Get the values of fisca fields.
    foreach ($fisca_field_mappings as $webform_key => $openfisca_key) {
      if ($openfisca_key !== '_nil') {
        // The openfisca_key will be in the format
        // variable_entity.entity_key.variable_name
        // eg. persons.personA.age
        // We need to dynamically create an multidimensional array
        // from the list of keys and then set the value.
        $keys = explode('.', $openfisca_key);
        $variable = array_pop($keys);
        $ref = &$response;
        while ($key = array_shift($keys)) {
          $ref = &$ref[$key] ?? NULL;
        }
        // We will not know the period key will be, get the first items value
        // eg { variable_name: { period: variable_value }}
        // eg { age: { "2022-11-01": 20 }}.
        if (isset($ref) && isset($ref[$variable])) {
          $objIterator = new \ArrayIterator($ref[$variable]);
          $key = $objIterator->key();
          if (isset($ref[$variable][$key])) {
            if (strpos($webform_key, '_benefit')) {
              // This is a benefit. Add it to the total.
              // Cast all benefits into int type.
              $ref[$variable][$key] = (int) $ref[$variable][$key];
              $total_benefit += $ref[$variable][$key];
            }
            $fisca_fields[$webform_key] = $ref[$variable][$key];
          }
        }
      }
    }
    $query_append['total_benefit'] = $total_benefit;

    // Attempt to determine the special immediate exit.
    $immediate_exit_mapping = $this->getWebformOpenFiscaSetting($this->getWebform(), 'fisca_immediate_exit_mapping', default_value: '', json_decode: FALSE);
    foreach (explode(',', $immediate_exit_mapping) as $immediate_exit_key) {
      $immediate_exit_key = trim($immediate_exit_key);
      $keys = explode('.', $immediate_exit_key);
      if (NestedArray::keyExists($response, $keys)) {
        $immediate_exit = NestedArray::getValue($response, $keys);
        if (is_array($immediate_exit) && !empty(array_filter($immediate_exit))) {
          $query_append['immediate_exit'] = TRUE;
        }
      }

    }

    return $response;
  }

  /**
   * Override webform confirmation URL.
   *
   * @param array $query_append
   *   Query append.
   * @param array $fisca_fields
   *   Openfisca fields.
   * @param array $result_values
   *   Result values.
   * @param string $query
   *   Query string for the confirmation URL.
   *
   * @return string|null
   *   The confirmation URL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function overrideConfirmationUrl(array $query_append, array &$fisca_fields, array $result_values, string &$query) : ?string {
    if (!empty($query_append)) {
      $fisca_fields = array_merge($fisca_fields, $query_append);
    }

    $existing_confirmation_url = $this->getWebform()->getSetting('confirmation_url');
    if ($existing_confirmation_url) {
      $parsed_url = UrlHelper::parse($existing_confirmation_url);
      if (isset($parsed_url['query'])  && is_array($parsed_url['query'])) {
        $fisca_fields = array_merge($fisca_fields, $parsed_url['query']);
      }
    }

    $form_id = $this->getWebform()->id();
    $query = http_build_query($fisca_fields);
    $query = urldecode($query);
    $confirmation_url = $this->findRedirectRules($form_id, $result_values);
    if (!empty($confirmation_url)) {
      $this->getWebform()->setSettingOverride('confirmation_url', $confirmation_url . '?' . $query);
    }

    return $confirmation_url;
  }

  /**
   * Encode and pretty print JSON.
   *
   * @param mixed $data
   *   Data to encode.
   *
   * @return string|false
   *   The JSON.
   */
  protected function jsonPrettyEncode(mixed $data) : string|false {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT);
  }

  /**
   * Display Openfisca debug.
   *
   * @param array $payload
   *   The payload.
   * @param mixed $response
   *   Raw response from Openfisca.
   * @param array $result_values
   *   Result values.
   * @param array $fisca_fields
   *   Openfisca fields.
   * @param string $query
   *   Confirmation URL query string.
   * @param string|null $confirmation_url
   *   Confirmation URL.
   */
  protected function displayDebug(array $payload, mixed $response, array $result_values, array $fisca_fields, string $query, ?string $confirmation_url = NULL) : void {
    $config = $this->configFactory->get('webform_openfisca.settings');
    $debug = $config->get('webform_openfisca.debug');

    if ($debug) {
      $build = [
        'label' => ['#markup' => 'Debug:'],
        'payload' => [
          '#markup' => 'Openfisca Calculate Payload:<br>' . $this->jsonPrettyEncode($payload),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'response' => [
          '#markup' => 'Openfisca Calculate Response<br>' . $this->jsonPrettyEncode($response),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'result_values' => [
          '#markup' => 'result_values<br>' . $this->jsonPrettyEncode($result_values),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'fisca_fields' => [
          '#markup' => 'fisca_fields<br>' . $this->jsonPrettyEncode($fisca_fields),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'query' => [
          '#markup' => 'query<br>' . $query,
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'confirmation_url' => [
          '#markup' => 'confirmation_url<br>' . $confirmation_url,
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
      ];
      $message = $this->renderer->renderInIsolation($build);
      $this->messenger()->addWarning($message);
    }
  }

  /**
   * Helper function to find redirects from the rules defined for form id.
   *
   * @param string $form_id
   *   The form_id to be checked.
   * @param array $results
   *   The list of response values.
   *
   * @return string|null
   *   The redirect.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function findRedirectRules(string $form_id, array $results) : ?string {
    // Find the rule node for this form id.
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'rac',
        'field_webform' => $form_id,
      ]);
    // If there are no nodes found exit early.
    if (empty($nodes)) {
      return NULL;
    }
    $entity = reset($nodes);

    // Extract the rules.
    $array_rules = [];
    foreach ($entity->field_rules as $paragraph) {
      $array_rule = ['rules' => []];
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_para **/
      $referenced_para = $paragraph->entity->toArray();
      $array_rule['redirect'] = $referenced_para['field_redirect_to'];
      $rules = $referenced_para['field_rac_element'];
      foreach ($rules as $rule) {
        $target_id = $rule['target_id'];
        $rule_array = $this->entityTypeManager->getStorage('paragraph')->load($target_id)?->toArray();
        if (!is_array($rule_array)) {
          continue;
        }
        $field_variable = $rule_array['field_variable'][0]['value'];
        $field_value = $rule_array['field_value'][0]['value'];
        $array_rule['rules'][] = [
          'variable' => $field_variable,
          'value' => $field_value,
        ];
      }
      $array_rules[] = $array_rule;
    }

    // Now we have the rule as an array. Apply it to the results.
    foreach ($array_rules as $array_rule) {
      $evaluation = [];
      $redirect_node = $array_rule['redirect'][0]['target_id'];
      foreach ($array_rule['rules'] as $rule) {
        if (($results[$rule['variable']] ?? NULL) == $rule['value']) {
          $evaluation[] = TRUE;
        }
        else {
          $evaluation[] = FALSE;
        }
      }
      if (!in_array(FALSE, $evaluation, TRUE)) {
        return('/node/' . $redirect_node);
      }
    }
    return NULL;
  }

  /**
   * Helper method to get variable period & return a date in the correct format.
   *
   * @param array $fisca_variables
   *   The list of available fisca variables.
   * @param string $variable
   *   The variable to be accessed.
   * @param string $period_date
   *   The period date.
   *
   * @return string
   *   The formatted value.
   */
  protected function formatVariablePeriod(array $fisca_variables, string $variable, string $period_date) : string {
    if (!isset($fisca_variables[$variable])) {
      return '';
    }
    $fisca_variable = $fisca_variables[$variable];
    $definition_period = $fisca_variable['definitionPeriod'];
    $date = date_create($period_date);

    switch ($definition_period) {
      case 'DAY':
        // Date format yyyy-mm-dd eg. 2022-11-02.
        $formatted_period = date_format($date, 'Y-m-d');
        break;

      case 'WEEK':
        // Date format yyyy-Wxx eg. 2022-W44.
        $week = date_format($date, 'W');
        $year = date_format($date, 'Y');
        $formatted_period = $year . '-W' . $week;
        break;

      case 'WEEKDAY':
        // Date format yyyy-Wxx-x eg. 2022-W44-2 (2=Tuesday the weekday number).
        $week = date_format($date, 'W');
        $year = date_format($date, 'Y');
        $weekday = date_format($date, 'N');
        $formatted_period = $year . '-W' . $week . '-' . $weekday;
        break;

      case 'MONTH':
        // Date format yyyy-mm eg. 2022-11.
        $formatted_period = date_format($date, 'Y-m');
        break;

      case 'YEAR':
        // Date format yyyy eg. 2022.
        $formatted_period = date_format($date, 'Y');
        break;

      case 'ETERNITY':
        // No date format, return string value ETERNITY.
        $formatted_period = 'ETERNITY';
        break;

      default:
        // @todo Log a error message. OpenFisca API will most likely return an error with no period_date set
        $formatted_period = '';
    }

    return $formatted_period;
  }

  /**
   * {@inheritdoc}
   */
  public function alterElement(array &$element, FormStateInterface $form_state, array $context) : void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof WebformSubmissionForm) {
      return;
    }
    if ($form_object->getOperation() !== 'add') {
      return;
    }

    $webform = $form_object->getWebform();
    $fisca_enabled = $this->getWebformOpenFiscaSetting($webform, 'fisca_enabled', FALSE);
    if (!$fisca_enabled) {
      return;
    }

    $fisca_immediate_response_mapping = $this->getWebformOpenFiscaSetting($webform, 'fisca_immediate_response_mapping', []);
    $fisca_immediate_response_ajax_indicator = $this->getWebformOpenFiscaSetting($webform, 'fisca_immediate_response_ajax_indicator', TRUE, json_decode: FALSE);
    $key = $element['#webform_key'];
    if (!empty($fisca_immediate_response_mapping[$key])) {
      $element['#attributes']['data-openfisca-immediate-response'] = 'true';
      $element['#attributes']['data-openfisca-webform-id'] = $webform->id();
      $element['#attached']['library'][] = 'webform_openfisca/immediate_response';
      $element['#ajax'] = [
        'callback' => [$this, 'requestOpenfiscaImmediateResponse'],
        'disable-refocus' => TRUE,
        'event' => 'fiscaImmediateResponse:request',
        'progress' => [
          'type' => $fisca_immediate_response_ajax_indicator ? 'throbber' : 'none',
        ],
      ];
    }
  }

  /**
   * Ajax callback to request immediate response from Openfisca.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function requestOpenfiscaImmediateResponse(array $form, FormStateInterface $form_state) : AjaxResponse {
    $webform = $this->getWebform();
    $values = $form_state->getValues() ?: [];
    $webform_submission = WebformSubmission::create([
      'data' => $values,
      'webform_id' => $this->getWebform()->id(),
    ]);

    $query_append = [];
    $fisca_field_mappings = $this->getWebformOpenFiscaSetting($webform, 'fisca_field_mappings', []);
    $fisca_return_key = $this->getWebformOpenFiscaSetting($webform, 'fisca_return_key', json_decode: FALSE);
    $result_keys = explode(',', $fisca_return_key);

    $payload = $this->prepareOpenfiscaPayload($webform_submission, $query_append, $fisca_field_mappings, $result_keys);

    $fisca_fields = [];
    $result_values = [];
    $this->calculateBenefits($payload, $result_keys, $query_append, $fisca_field_mappings, $fisca_fields, $result_values);

    $immediate_response = [];
    if (!empty($query_append['total_benefit']) || !empty($query_append['immediate_exit'])) {
      $query = '';
      $confirmation_url = $this->overrideConfirmationUrl($query_append, $fisca_fields, $result_values, $query);
      $immediate_response = [
        'confirmation_url' => $confirmation_url,
        'query' => $query,
      ];
    }

    $response = new AjaxResponse();
    if (!empty($immediate_response)) {
      /** InvokeCommand($selector, $method, array $arguments = []) */
      $response->addCommand(new InvokeCommand(NULL, 'webformOpenfiscaImmediateResponseRedirect', [$immediate_response]));
    }
    else {
      $triggering_element = $form_state->getTriggeringElement();
      if (isset($values[$triggering_element['#name']])) {
        $data = [
          'name' => $triggering_element['#name'],
          'webform' => $triggering_element['#webform'] ?? $webform->id(),
          'selector' => $triggering_element['#attributes']['data-drupal-selector'] ?? '',
          'original_selector' => '',
        ];
        if (!empty($triggering_element['#id'])) {
          $original_id = preg_replace('/--([a-zA-Z0-9]{11})$/', '', $triggering_element['#id'], 1);
          $data['original_selector'] = Html::getId($original_id);
        }
        $response->addCommand(new InvokeCommand(NULL, 'webformOpenfiscaImmediateResponseContinue', [$data]));
      }
    }
    return $response;
  }

}
