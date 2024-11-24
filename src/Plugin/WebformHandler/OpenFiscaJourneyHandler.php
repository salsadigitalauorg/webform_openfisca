<?php

namespace Drupal\webform_openfisca\Plugin\WebformHandler;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_openfisca\OpenFisca\Helper as OpenFiscaHelper;
use Drupal\webform_openfisca\OpenFisca\ClientFactoryInterface as OpenFiscaClientFactoryInterface;
use Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload;
use Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload;
use Drupal\webform_openfisca\RacContentHelperInterface;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Webform submission debug handler.
 *
 * @WebformHandler(
 *   id = "openfisca_journey",
 *   label = @Translation("OpenFisca Journey handler"),
 *   category = @Translation("External"),
 *   description = @Translation("Maintain the journey server based on calculations from OpenFisca."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class OpenFiscaJourneyHandler extends WebformHandlerBase {

  /**
   * Current request.
   */
  protected Request $request;

  /**
   * OpenFisca Client factory.
   */
  protected OpenFiscaClientFactoryInterface $openfiscaClientFactory;

  /**
   * RAC content helper.
   */
  protected RacContentHelperInterface $racContentHelper;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->openfiscaClientFactory = $container->get('webform_openfisca.openfisca_client_factory');
    $instance->racContentHelper = $container->get('webform_openfisca.rac_helper');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) : void {
    $payload = $this->prepareOpenfiscaPayload($webform_submission);
    $openfisca_response = NULL;
    $this->determineBenefits($payload, response_payload: $openfisca_response);
    if ($openfisca_response instanceof ResponsePayload) {
      $this->overrideConfirmationUrl($openfisca_response);
    }

    // Debug.
    $this->logDebug($payload, $openfisca_response);
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
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    if (!$openfisca_settings->isEnabled() || !$openfisca_settings->hasApiEndpoint()) {
      return;
    }

    $element_key = $element['#webform_key'];
    // Add the immediate response handling to webform element.
    $fisca_immediate_response_ajax_indicator = $openfisca_settings->hasImmediateResponseAjaxIndicator();
    if ($openfisca_settings->fieldHasImmediateResponse($element_key)) {
      $element['#attributes']['data-openfisca-immediate-response'] = 'true';
      $element['#attributes']['data-openfisca-webform-id'] = $webform->id();
      $element['#attached']['library'][] = 'webform_openfisca/immediate_response';
      $element['#ajax'] = [
        'callback' => [$this, 'requestOpenFiscaImmediateResponse'],
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
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function requestOpenFiscaImmediateResponse(array $form, FormStateInterface $form_state) : AjaxResponse {
    $webform = $this->getWebform();
    $values = $form_state->getValues() ?: [];
    // Create a faux webform submission from current form values.
    $webform_submission = WebformSubmission::create([
      'data' => $values,
      'webform_id' => $this->getWebform()->id(),
    ]);

    $payload = $this->prepareOpenfiscaPayload($webform_submission);
    $openfisca_response = NULL;
    $total_benefits = $this->determineBenefits($payload, $openfisca_response);
    $query_append = $openfisca_response?->getDebugData('query_append') ?: [];

    $immediate_response = [];
    if (($total_benefits !== 0 || !empty($query_append['immediate_exit']))
      && $openfisca_response instanceof ResponsePayload
    ) {
      $confirmation_url = $this->overrideConfirmationUrl($openfisca_response);
      $immediate_response = [
        'confirmation_url' => $confirmation_url,
        'query' => $openfisca_response->getDebugData('query') ?: '',
      ];
    }

    $response = new AjaxResponse();
    if (!empty($immediate_response)) {
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

    $this->logDebug($payload, $openfisca_response);

    return $response;
  }

  /**
   * Prepare the payload for querying Openfisca.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   Webform submission.
   *
   * @return \Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload
   *   The payload.
   */
  protected function prepareOpenfiscaPayload(WebformSubmissionInterface $webform_submission) : RequestPayload {
    $data = $webform_submission->getData();

    $webform = $this->getWebform();
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $result_keys = $openfisca_settings->getReturnKeys();
    $fisca_field_mappings = $openfisca_settings->getFieldMappings();

    $fisca_entity_roles = $openfisca_settings->getEntityRoles();
    // Prepare the person payload.
    $openfisca_payload = new RequestPayload();

    // Period.
    $period_query = $this->request->query->get('period');
    $query_append = [];

    if (isset($period_query) || (isset($fisca_field_mappings['period'], $data[$fisca_field_mappings['period']]))) {
      $period = $period_query ?? $data[$fisca_field_mappings['period']];
      $query_append['period'] = $period;
      $query_append['change'] = 1;
    }
    else {
      $period = (new DrupalDateTime())->format('Y-m-d');
    }

    foreach ($fisca_field_mappings as $webform_key => $openfisca_key) {
      // Always ignore the period key.
      if ($webform_key === 'period') {
        continue;
      }
      // We don't want to use the keys which are not mapped.
      if ($openfisca_key === '_nil') {
        if (isset($data[$webform_key])) {
          $query_append[$webform_key] = $data[$webform_key];
        }
      }
      elseif (!empty($data[$webform_key])) {
        // The openfisca_key will be in the format
        // variable_entity.entity_key.variable_name
        // eg. persons.personA.age
        // We need to dynamically create a multidimensional array
        // from the list of keys and then set the value.
        $path = [];
        $variable = OpenFiscaHelper::parseOpenFiscaFieldMapping($openfisca_key, path: $path);
        $val = strtolower($data[$webform_key]) === 'true' || strtolower($data[$webform_key]) === 'false' ? strtolower($data[$webform_key]) === 'true' : $data[$webform_key];
        $formatted_period = $openfisca_settings->formatVariablePeriod($variable, $period);
        if (!empty($formatted_period)) {
          $openfisca_payload->setValue($path, [$formatted_period => $val]);
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
      $path = [];
      $variable = OpenFiscaHelper::parseOpenFiscaFieldMapping($result_key, path: $path);
      $formatted_period = $openfisca_settings->formatVariablePeriod($variable, $period);
      if (!empty($formatted_period)) {
        $openfisca_payload->setValue($path, [$formatted_period => NULL]);
      }
    }

    // Create group entities with roles
    // eg. { families.familyA.children: ["childA", "childB"] }.
    foreach ($fisca_entity_roles as $fisca_entity_role) {
      $role = $fisca_entity_role['role'] ?? NULL;
      $is_array = $fisca_entity_role['is_array'] ?? FALSE;
      if (empty($role)) {
        continue;
      }
      // The role will be in the format
      // group_entity.group_entity_key.role.entity_key
      // eg. families.familyA.principal.personA
      // eg. families.familyA.children.child1
      // We need to dynamically create an multidimensional array
      // from the list of keys and then set the value.
      $parents = [];
      $entity_key = OpenFiscaHelper::parseOpenFiscaFieldMapping($role, parents: $parents);
      if ($openfisca_payload->findKey($entity_key) !== NULL) {
        if ($is_array) {
          $payload_entity_role = $openfisca_payload->getValue($parents);
          $payload_entity_role[] = $entity_key;
          $openfisca_payload->setValue($parents, $payload_entity_role);
        }
        else {
          $openfisca_payload->setValue($parents, $entity_key);
        }
      }
    }

    // Add immediate exit mapping to the payload.
    $immediate_exit_mapping = $openfisca_settings->getImmediateExitKeys();
    foreach ($immediate_exit_mapping as $immediate_exit_key) {
      $path = [];
      $parents = [];
      $variable = OpenFiscaHelper::parseOpenFiscaFieldMapping($immediate_exit_key, path: $path, parents: $parents);
      if (!empty($variable) && $openfisca_payload->keyPathExists($parents) && !$openfisca_payload->keyPathExists($path)) {
        $formatted_period = $openfisca_settings->formatVariablePeriod($variable, $period);
        $openfisca_payload->setValue($path, [$formatted_period => NULL]);
      }
    }

    $openfisca_payload->setDebugData('query_append', $query_append);

    return $openfisca_payload;
  }

  /**
   * Determine the benefits from OpenFisca.
   *
   * @param \Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload $request_payload
   *   The request payload to sent to OpenFisca.
   * @param \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload|null $response_payload
   *   The response payload retrieved from OpenFisca. All data to calculate the
   * benefits will be stored in debug data of the payload.
   *
   * @return int
   *   Number of benefits. -1 for immediate exit.
   */
  protected function determineBenefits(RequestPayload $request_payload, ?ResponsePayload &$response_payload = NULL) : int {
    $openfisca_settings = WebformOpenFiscaSettings::load($this->getWebform());
    $openfisca_client = $openfisca_settings->getOpenFiscaClient($this->openfiscaClientFactory);
    $response_payload = $openfisca_client->calculate($request_payload);
    if (!$response_payload instanceof ResponsePayload) {
      return 0;
    }

    $fisca_fields = [];
    $result_values = [];

    // Get the values of the return keys.
    $result_keys = $openfisca_settings->getReturnKeys();
    foreach ($result_keys as $result_key) {
      // The result_keys will be in the format entity.entity_key.variable_name
      // e.g. persons.personA.age
      // We need to dynamically create a multidimensional array
      // from the list of keys and then set the value.
      $path = [];
      OpenFiscaHelper::parseOpenFiscaFieldMapping($result_key, path: $path);
      $value = $response_payload->getValue($path);
      // We will not know the period key will be, get the first items value
      // e.g. { variable_name: { period: variable_value }}
      // e.g. { age: { "2022-11-01": 20 }}.
      $result_values[$result_key] = is_array($value) ? reset($value) : $value;
    }

    // Get the pre-set query_append from the request payload.
    $query_append = $request_payload->getDebugData('query_append') ?: [];

    // To calculate the total benefit.
    $total_benefits = 0;

    // @todo Check the logic of benefit calculation below.
    // Get the values of fisca fields.
    $fisca_field_mappings = $openfisca_settings->getFieldMappings();
    foreach ($fisca_field_mappings as $webform_key => $openfisca_key) {
      // Always ignore period key.
      if ($webform_key === 'period') {
        continue;
      }
      if ($openfisca_key !== '_nil') {
        // The openfisca_key will be in the format
        // variable_entity.entity_key.variable_name
        // eg. persons.personA.age
        // We need to dynamically create an multidimensional array
        // from the list of keys and then set the value.
        $path = [];
        OpenFiscaHelper::parseOpenFiscaFieldMapping($openfisca_key, path: $path);
        if ($response_payload->keyPathExists($path)) {
          $value = $response_payload->getValue($path);
          // We will not know the period key will be, get the first items value
          // e.g. { variable_name: { period: variable_value }}
          // e.g. { age: { "2022-11-01": 20 }}.
          if (is_array($value)) {
            $value = reset($value);
            // @todo Remove this logic or use configurable regex.
            if (str_contains($webform_key, '_benefit')) {
              // This is a benefit. Add it to the total.
              // Cast all benefits into int type.
              $total_benefits += (int) $value;
            }
            $fisca_fields[$webform_key] = $value;
          }
        }
      }
    }
    $query_append['total_benefit'] = $total_benefits;

    // Attempt to determine the special immediate exit.
    $immediate_exit_mapping = $openfisca_settings->getImmediateExitKeys();
    foreach ($immediate_exit_mapping as $immediate_exit_key) {
      $path = [];
      OpenFiscaHelper::parseOpenFiscaFieldMapping($immediate_exit_key, path: $path);
      if ($response_payload->keyPathExists($path)) {
        $immediate_exit = $response_payload->getValue($path);
        if (is_array($immediate_exit) && !empty(array_filter($immediate_exit))) {
          $query_append['immediate_exit'] = TRUE;
          $total_benefits = -1;
          break;
        }
      }
    }

    // Set debug data to the response payload.
    $response_payload->setDebugData('query_append', $query_append);
    $response_payload->setDebugData('fisca_fields', $fisca_fields);
    $response_payload->setDebugData('result_values', $result_values);
    $response_payload->setDebugData('total_benefits', $total_benefits);

    return $total_benefits;
  }

  /**
   * Override webform confirmation URL.
   *
   * @param \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload $response_payload
   *   The response payload from OpenFisca.
   *
   * @return string|null
   *   The confirmation URL.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function overrideConfirmationUrl(ResponsePayload $response_payload) : ?string {
    $query_append = $response_payload->getDebugData('query_append') ?: [];
    $fisca_fields = $response_payload->getDebugData('fisca_fields') ?: [];
    $query_params = array_merge($fisca_fields, $query_append);

    $existing_confirmation_url = $this->getWebform()->getSetting('confirmation_url');
    if (!empty($existing_confirmation_url)) {
      $response_payload->setDebugData('webform_confirmation_url', $existing_confirmation_url);

      $parsed_url = UrlHelper::parse($existing_confirmation_url);
      if (isset($parsed_url['query'])  && is_array($parsed_url['query'])) {
        $query_params = array_merge($query_params, $parsed_url['query']);
      }
    }

    $query = http_build_query($query_params);
    $query = urldecode($query);
    $response_payload->setDebugData('query', $query);

    $result_values = $response_payload->getDebugData('result_values') ?: [];
    $confirmation_url = $this->racContentHelper->findRacRedirectForWebform($this->getWebform()->id(), $result_values);
    // Override webform confirmation URL.
    if (!empty($confirmation_url)) {
      $overridden_confirmation_url = $confirmation_url . '?' . $query;
      $this->getWebform()->setSettingOverride('confirmation_url', $overridden_confirmation_url);

      $response_payload->setDebugData('rac_redirect', $confirmation_url);
      $response_payload->setDebugData('overridden_confirmation_url', $overridden_confirmation_url);
    }

    return $confirmation_url;
  }

  /**
   * Log the debug data.
   *
   * @param \Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload $request_payload
   *   The request payload sent to OpenFisca.
   * @param \Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload|null $response_payload
   *   The response payload retrieved from OpenFisca.
   * @param bool $show_message
   *   Whether to display the debug information in system messages.
   */
  public function logDebug(RequestPayload $request_payload, ?ResponsePayload $response_payload, bool $show_message = TRUE) : void {
    $webform = $this->getWebform();
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    if (!$openfisca_settings->isDebugEnabled() && !$openfisca_settings->isLoggingEnabled()) {
      return;
    }

    $fisca_fields = $response_payload?->getDebugData('fisca_fields') ?: [];
    $result_values = $response_payload?->getDebugData('result_values') ?: [];

    $build = [
      'label' => [
        '#markup' => $this->t('<strong>Webform Open Fisca Debug</strong>'),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'api_endpoint' => [
        '#markup' => $this->t('<strong>OpenFisca API:</strong> @endpoint', [
          '@endpoint' => ($response_payload?->getDebugData('openfisca_api_endpoint') ?: 'NULL'),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'payload' => [
        '#markup' => $this->t('<div><strong>Request Payload:</strong> <br/> <pre>@json</pre></div>', [
          '@json' => $request_payload->toJson(TRUE),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'response' => [
        '#markup' => $this->t('<strong>Response Payload:</strong> <br/> <pre>@json</pre>', [
          '@json' => $response_payload?->toJson(TRUE) ?: 'NULL',
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'total_benefits' => [
        '#markup' => $this->t('<strong>Total benefits:</strong> %total_benefits', [
          '%total_benefits' => ($response_payload?->getDebugData('total_benefits') ?: 'NULL'),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'result_values' => [
        '#markup' => $this->t('<strong>Result values:</strong> <br/> <pre>@values</pre>', [
          '@values' => OpenFiscaHelper::jsonEncodePretty($result_values),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'fisca_fields' => [
        '#markup' => $this->t('<strong>Fisca fields:</strong> <br/> <pre>@values</pre>', [
          '@values' => OpenFiscaHelper::jsonEncodePretty($fisca_fields),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'query' => [
        '#markup' => $this->t('<strong>Query:</strong> <pre>@query</pre>', [
          '@query' => ($response_payload?->getDebugData('query') ?? 'NULL'),
        ]) ,
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'original_confirmation_url' => [
        '#markup' => $this->t('<strong>Original Confirmation URL:</strong> <pre>@url</pre>', [
          '@url' => ($response_payload?->getDebugData('webform_confirmation_url') ?? 'NULL'),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'rac_redirect' => [
        '#markup' => $this->t('<strong>RAC Redirect URL:</strong> <pre>@url</pre>', [
          '@url' => ($response_payload?->getDebugData('rac_redirect') ?? 'NULL'),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
      'overridden_confirmation_url' => [
        '#markup' => $this->t('<strong>Overridden Confirmation URL:</strong> <br/> <pre>@url</pre>', [
          '@url' => ($response_payload?->getDebugData('overridden_confirmation_url') ?? 'NULL'),
        ]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ],
    ];
    $message = $this->renderer->renderInIsolation($build);
    if ($show_message && $openfisca_settings->isDebugEnabled()) {
      $this->messenger()->addWarning($message);
    }
    if ($openfisca_settings->isLoggingEnabled()) {
      $this->getLogger('webform_openfisca')->debug($message);
    }
  }

}
