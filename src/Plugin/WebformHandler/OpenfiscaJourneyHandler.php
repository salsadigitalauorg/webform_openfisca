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
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $form_id = $form['#webform_id'];
    $settings = $this->getSettings();

    $data = ($settings['submission']) ? $webform_submission->toArray(TRUE) : $webform_submission->getData();

    // Extract all submission values as variables.
    $data_keys = array_keys($data);
    foreach ($data_keys as $key) {
      $$key = $data[$key];
    }

    $fisca_field_mappings = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_field_mappings');
    $fisca_field_mappings = json_decode($fisca_field_mappings, TRUE);

    $result_key = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_return_key');
    $result_keys = explode(",", $result_key);

    $fisca_variables = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_variables');
    $fisca_variables = json_decode($fisca_variables, TRUE) ?? [];

    $fisca_entity_roles = $webform_submission->getWebform()->getThirdPartySetting('webform_openfisca', 'fisca_entity_roles');
    $fisca_entity_roles = json_decode($fisca_entity_roles, TRUE) ?? [];
    // Prep the person payload.
    $payload = [];

    // Period.
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

    foreach ($fisca_field_mappings as $webform_key => $openfisca_key) {
      // We don't what to use the keys which are not mapped.
      if ($openfisca_key == '_nil') {
        if (isset($data[$webform_key])) {
          $query_append[$webform_key] = $data[$webform_key];
        }
      }
      else {
        if (!empty($data[$webform_key])) {
          // The openfisca_key will be in the format
          // variable_entity.entity_key.variable_name
          // eg. persons.personA.age
          // We need to dynamically create an multidimensional array
          // from the list of keys and then set the value.
          $keys = explode(".", $openfisca_key);
          $variable = array_pop($keys);
          $ref = &$payload;
          while ($key = array_shift($keys)) {
            $ref = &$ref[$key];
          }
          $val = strtolower($data[$webform_key]) == 'true' || strtolower($data[$webform_key]) == 'false' ? strtolower($data[$webform_key]) == 'true' : $data[$webform_key];
          $formatted_period = $this->formatVariablePeriod($fisca_variables, $variable, $period);
          $ref[$variable] = [$formatted_period => $val];
        }
      }
    }

    // Create result keys entities with null values to tell OpenFisca
    // to calculate these variables eg. { persons.personA.variable_name: null }.
    foreach ($result_keys as $result_key) {
      // The result_key will be in the format
      // variable_entity.entity_key.variable_name
      // eg. persons.personA.age
      // We need to dynamically create an multidimensional array
      // from the list of keys and then set the value.
      $keys = explode(".", $result_key);
      $variable = array_pop($keys);
      $ref = &$payload;
      while ($key = array_shift($keys)) {
        $ref = &$ref[$key];
      }
      $formatted_period = $this->formatVariablePeriod($fisca_variables, $variable, $period);
      $ref[$variable] = [$formatted_period => NULL];
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
        $keys = explode(".", $role);
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

    $open_fisca_client = \Drupal::service('webform_openfisca.open_fisca_connector_service');
    $entity = $form_state->getFormObject()->getWebform();
    $request = $open_fisca_client->post($entity->getThirdPartySetting('webform_openfisca', 'fisca_api_endpoint') . '/calculate', ['json' => $payload]);
    $response = json_decode($request->getBody(), TRUE);

    // Get the values of the return keys.
    foreach ($result_keys as $result_key) {
      // The result_keys will be in the format entity.entity_key.variable_name
      // eg. persons.personA.age
      // We need to dynamically create an multidimensional array
      // from the list of keys and then set the value.
      $keys = explode(".", $result_key);
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

    // To calculate the total benefeit.
    $total_benefit = 0;

    // Get the values of fisca fields.
    foreach ($fisca_field_mappings as $webform_key => $openfisca_key) {
      if ($openfisca_key != '_nil') {
        // The openfisca_key will be in the format
        // variable_entity.entity_key.variable_name
        // eg. persons.personA.age
        // We need to dynamically create an multidimensional array
        // from the list of keys and then set the value.
        $keys = explode(".", $openfisca_key);
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
            if (strpos($webform_key, "_benefit")) {
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

    if (isset($query_append) && is_array($query_append)) {
      $fisca_fields = array_merge($fisca_fields, $query_append);
    }

    $query = http_build_query($fisca_fields);
    $confirmation_url = $this->findRedirectRules($form_id, $result_values);
    if (!empty($confirmation_url)) {
      $this->getWebform()->setSettingOverride('confirmation_url', $confirmation_url . '?' . $query);
    }

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
        ],
      ];
      $message = $this->renderer->renderPlain($build);

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
   */
  public function findRedirectRules(string $form_id, array $results) {

    // Find the rule node for this form id.
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'rac',
        'field_webform' => $form_id,
      ]);
    // If there are no nodes found exit early.
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
   * Helper method to get variable period & return a date in the correct format.
   *
   * @param array $fisca_variables
   *   The list of avalibale fisca variables.
   * @param string $variable
   *   The variable to be accessed.
   * @param string $period_date
   *   The period date.
   *
   * @return string
   *   The formatted value.
   */
  private function formatVariablePeriod($fisca_variables, $variable, $period_date) {
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
