<?php

namespace Drupal\webform_openfisca\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  const FORMAT_YAML = 'yaml';

  /**
   * Format JSON.
   */
  const FORMAT_JSON = 'json';

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'format' => 'yaml',
      'submission' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
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
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @todo Fisca handler configuration.
    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $form_id = $form['#webform_id'];
    $settings = $this->getSettings();

    $data = ($settings['submission']) ? $webform_submission->toArray(TRUE) : $webform_submission->getData();

    // Extract all submission values as variables.
    $data_keys = array_keys($data);
    foreach ($data_keys as $key) {
      $$key = $data[$key];
    }

    // Very hard coded API body to send data to Openfisca
    // we will need to abstract this out to a service rather
    // than relying on http client.
    //
    // Difficulties:
    //   - need to define fields as 'null' for fisca to respond
    //   - need to consume a "period" to apply rules.
    $fisca_field_mappings = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_field_mappings');
    $fisca_field_mappings = json_decode($fisca_field_mappings, TRUE);
    $fisca_field_mappings = array_flip($fisca_field_mappings);

    $result_key = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_return_key');
    $result_keys = explode(", ", $result_key);
    // Echo "<pre>"; print_r($result_keys); die;.
    $fisca_variables = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_variables');
    $fisca_variables = json_decode($fisca_variables, TRUE) ?? [];

    $payload = ['persons' => []];

    // Prep the person payload. Hardcoded person.
    $person = "personA";

    // Period.
    // Suchi - Period can be a date/ a month or even the text "eternity" - so we need to change this code appropriately to capture these somehow.
    $period_query = \Drupal::request()->query->get('period');
    if (isset($period_query)) {
      $period = $period_query;
      $query_append['period'] = $period;
      $query_append['change'] = 1;
      unset($fisca_field_mappings['period']);
    }
    elseif ((isset($fisca_field_mappings['period'])) && (isset($data[$fisca_field_mappings['period']]))) {
      $period = $data[$fisca_field_mappings['period']];
      $query_append['period'] = $period;
      $query_append['change'] = 1;
      unset($fisca_field_mappings['period']);
    }
    else {
      $period = date("Y-m-d");
      unset($fisca_field_mappings['period']);
    }

    // Suchi: We had hardcoded the payload to be 'persons' - should be configurable.
    // Suchi: We have also hardcoded a lot of things here - which need to be generalised.
    $payload['persons'][$person] = [];
    // Echo "<pre>"; print_r($fisca_field_mappings); print_r($data); die;.
    foreach ($fisca_field_mappings as $openfisca_key => $webform_key) {
      // We dont wat to use the keys which are not mapped.
      if ($openfisca_key == '_nil') {
        if (isset($data[$webform_key])) {
          $query_append[$webform_key] = $data[$webform_key];
        }
      }
      else {
        if (empty($data[$webform_key])) {
          $val = NULL;
        }
        else {
          $val = $data[$webform_key] == 'True' || $data[$webform_key] == 'False' ? $data[$webform_key] == 'True' : $data[$webform_key];
        }
        $formatted_period = $this->format_variable_period($fisca_variables, $openfisca_key, $period);
        $payload['persons'][$person][$openfisca_key] = [$formatted_period => $val];
      }
    }

    $open_fisca_client = \Drupal::service('webform_openfisca.open_fisca_connector_service');
    $entity = $form_state->getFormObject()->getWebform();
    $request = $open_fisca_client->post($entity->getThirdPartySetting('webform_openfisca', 'fisca_api_endpoint') . '/calculate', ['json' => $payload]);
    $response = json_decode($request->getBody());

    $results = $response->persons->$person;

    // Check the value of the return key.
    foreach ($result_keys as $result_key) {
      $result_values[$result_key] = $results->$result_key->$period;
    }

    foreach ($results as $k => $result) {
      if (isset($result->ETERNITY)) {
        $fisca_fields[$k] = $result->ETERNITY;
      }
      elseif (isset($result->$period)) {
        $fisca_fields[$k] = $result->$period;
      }
      if (($k == 'last_vaccine_dose') && ($result->$period == 'Thu, 01 Jan 1970 00:00:00 GMT')) {
        // Mark this as null.
        $fisca_fields[$k] = NULL;
      }
    }

    if (isset($query_append) && is_array($query_append)) {
      $fisca_fields = array_merge($fisca_fields, $query_append);
    }

    $query = http_build_query($fisca_fields);
    $confirmation_url = $this->find_redirect_rules($form_id, $result_values);
    $this->getWebform()->setSettingOverride('confirmation_url', $confirmation_url . '?' . $query);

    // Debug.
    $config = \Drupal::configFactory()->get('webform_openfisca.settings');
    $debug = $config->get("webform_openfisca.debug");

    if ($debug) {
      $build = [
        'label' => ['#markup' => 'Debug:'],
        'payload' => [
          '#markup' => 'Openfisca Calculate Payload:<br>' . json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'response' => [
          '#markup' => 'Openfisca Calculate Response<br>' . json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'result_values' => [
          '#markup' => 'result_values<br>' . json_encode($result_values, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'fisca_fields' => [
          '#markup' => 'fisca_fields<br>' . json_encode($fisca_fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'query' => [
          '#markup' => 'query<br>' . json_encode($query, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_PRETTY_PRINT),
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ],
        'confirmation_url' => [
          '#markup' => 'confirmation_url<br>' . $confirmation_url,
          '#prefix' => '<pre>',
          '#suffix' => '</pre>',
        ]
      ];
      $message = $this->renderer->renderPlain($build);

      $this->messenger()->addWarning($message);
    }

  }

  /**
   * Helper function to find redirects from the rules defined for form id.
   *
   * @param $form_id
   */
  public function find_redirect_rules($form_id, $results) {

    // Find the rule node for this form id.
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'rac',
        'field_webform' => $form_id,
      ]);
    // If there are no nodes found exit early
    if (empty($nodes)) {
      return;
    }
    $entity = reset($nodes);

    // Extract the rules.
    $i = 0;
    foreach ($entity->field_rules as $paragraph) {
      /** @var Entity (i.e. Node, Paragraph, Term) $referenced_product **/
      $referenced_para = $paragraph->entity->toArray();
      $array_rules[$i]['redirect'] = $referenced_para['field_redirect_to'];
      $rules = $referenced_para['field_rac_element'];
      foreach ($rules as $rule) {
        $target_id = $rule['target_id'];
        $rule_array = \Drupal::entityTypeManager()->getStorage('paragraph')->load($target_id)->toArray();
        $field_variable = $rule_array['field_variable'][0]['value'];
        $field_value = $rule_array['field_value'][0]['value'];
        $array_rules[$i]['rules'][] = [
          'variable' => $field_variable,
          'value' => $field_value,
        ];
      }
      $i++;
    }

    // Now we have the rule as an array. Apply it to the results.
    foreach ($array_rules as $array_rule) {
      $evaluation = [];
      $redirect_node = $array_rule['redirect'][0]['target_id'];
      foreach ($array_rule['rules'] as $rule) {
        if ($results[$rule['variable']] == $rule['value']) {
          $evaluation[] = TRUE;
        }
        else {
          $evaluation[] = FALSE;
        }
      }
      if (!in_array(FALSE, $evaluation)) {
        return("/node/" . $redirect_node);
      }
    }
  }

  /**
   * Helper method to get variable period and return a date in the correct format.
   *
   * @param array $fisca_variables
   * @param string $variable
   * @param string $period_date
   *
   * @return string
   */
  private function format_variable_period($fisca_variables, $variable, $period_date) {
    // suchi - do we need this?
    $fisca_variable = $fisca_variables[$variable];
    $definition_period = $fisca_variable['definitionPeriod'];
    $date = date_create($period_date);

    switch ($definition_period) {
      case "DAY":
        // Date format yyyy-mm-dd eg. 2022-11-02.
        $formatted_period = date_format($date, "Y-m-d");
        break;

      case "WEEK":
        // Date format yyyy-Wxx eg. 2022-W44.
        $week = date_format($date, "W");
        $year = date_format($date, "Y");
        $formatted_period = $year . '-W' . $week;
        break;

      case "WEEKDAY":
        // Date format yyyy-Wxx-x eg. 2022-W44-2 (2=Tuesday the weekday number).
        $week = date_format($date, "W");
        $year = date_format($date, "Y");
        $weekday = date_format($date, "N");
        $formatted_period = $year . '-W' . $week . '-' . $weekday;
        break;

      case "MONTH":
        // Date format yyyy-mm eg. 2022-11.
        $formatted_period = date_format($date, "Y-m");
        break;

      case "YEAR":
        // Date format yyyy eg. 2022.
        $formatted_period = date_format($date, "Y");
        break;

      case "ETERNITY":
        // No date format, return string value ETERNITY.
        $formatted_period = 'ETERNITY';
        break;

      default:
        // @todo Log a error message. OpenFisca API will most likely return an error with no period_date set
        $formatted_period = '';
    }

    return $formatted_period;
  }

}


