<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;

/**
 * Kernel test for WebformThirdPartySettingsFormAlter class.
 *
 * @group webform_openfisca
 * @group webform_alter
 * @group webform_alter_wip
 * @coversDefaultClass \Drupal\webform_openfisca\WebformThirdPartySettingsFormAlter
 */
class WebformThirdPartySettingsFormAlterKernelTest extends BaseKernelTestCase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();
  }

  /**
   * Test the WebformThirdPartySettingsFormAlter class.
   */
  public function testWebformThirdPartySettingsFormAlter(): void {
    $webform = Webform::load('test_dac');
    $this->assertInstanceOf(WebformInterface::class, $webform);

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');

    // Build and check the form.
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);

    $this->assertArrayHasKey('third_party_settings', $settings_form);
    $this->assertArrayHasKey('webform_openfisca', $settings_form['third_party_settings']);

    $openfisca_settings_form = $settings_form['third_party_settings']['webform_openfisca'];

    $this->assertArrayHasKey('fisca_enabled', $openfisca_settings_form);
    $this->assertEquals('checkbox', $openfisca_settings_form['fisca_enabled']['#type']);
    $this->assertTrue($openfisca_settings_form['fisca_enabled']['#default_value']);

    $this->assertArrayHasKey('fisca_debug_mode', $openfisca_settings_form);
    $this->assertEquals('checkbox', $openfisca_settings_form['fisca_debug_mode']['#type']);
    $this->assertFalse($openfisca_settings_form['fisca_debug_mode']['#default_value']);

    $this->assertArrayHasKey('fisca_logging_mode', $openfisca_settings_form);
    $this->assertEquals('checkbox', $openfisca_settings_form['fisca_logging_mode']['#type']);
    $this->assertTrue($openfisca_settings_form['fisca_logging_mode']['#default_value']);

    $this->assertArrayHasKey('fisca_api_endpoint', $openfisca_settings_form);
    $this->assertEquals('textfield', $openfisca_settings_form['fisca_api_endpoint']['#type']);
    $this->assertEquals('https://api.openfisca.test/', $openfisca_settings_form['fisca_api_endpoint']['#default_value']);

    $this->assertArrayHasKey('fisca_api_authorization_header', $openfisca_settings_form);
    $this->assertEquals('textfield', $openfisca_settings_form['fisca_api_authorization_header']['#type']);
    $this->assertEquals('Token WEBFORM-OPENFISCA-TEST-TOKEN', $openfisca_settings_form['fisca_api_authorization_header']['#default_value']);

    $this->assertArrayHasKey('fisca_return_key', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_return_key']['#type']);
    $this->assertEquals('text', $openfisca_settings_form['fisca_return_key']['#mode']);
    $this->assertEquals('persons.personA.disability_allowance_eligible,persons.personA.disability_allowance_benefit,persons.personA.monthly_income_exceeds_limit', $openfisca_settings_form['fisca_return_key']['#default_value']);

    $this->assertArrayHasKey('fisca_field_mappings', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_field_mappings']['#type']);
    $this->assertEquals('javascript', $openfisca_settings_form['fisca_field_mappings']['#mode']);
    $json = $openfisca_settings_form['fisca_field_mappings']['#default_value'];
    $mappings = Json::decode($json);
    $this->assertEquals([
      'what_is_your_monthly_income_',
      'has_disability',
      'requires_ongoing_support',
      'requires_ongoing_supervision_or_treatment',
      'disability_allowance_eligible',
      'aus_citizen_or_permanent_resident',
      'disability_allowance_benefit',
      'monthly_income_exceeds_limit',
    ], array_keys($mappings));

    $this->assertArrayHasKey('fisca_variables', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_variables']['#type']);
    $this->assertEquals('javascript', $openfisca_settings_form['fisca_variables']['#mode']);
    $json = $openfisca_settings_form['fisca_variables']['#default_value'];
    $mappings = Json::decode($json);
    $this->assertEquals([
      'salary',
      'has_disability',
      'requires_ongoing_support',
      'disability_allowance_eligible',
      'aus_citizen',
      'disability_allowance_benefit',
      'monthly_income_exceeds_limit',
      'monthly_income',
      'requires_ongoing_supervision_or_treatment',
    ], array_keys($mappings));

    $this->assertArrayHasKey('fisca_parameter_tokens', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_parameter_tokens']['#type']);
    $this->assertEquals('text', $openfisca_settings_form['fisca_parameter_tokens']['#mode']);
    $this->assertEquals('disability_allowance_max_income,income_tax_rate', $openfisca_settings_form['fisca_parameter_tokens']['#default_value']);

    $this->assertArrayHasKey('fisca_entity_roles', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_entity_roles']['#type']);
    $this->assertEquals('javascript', $openfisca_settings_form['fisca_entity_roles']['#mode']);
    $json = $openfisca_settings_form['fisca_entity_roles']['#default_value'];
    $mappings = Json::decode($json);
    $this->assertEmpty($mappings);

    $this->assertArrayHasKey('fisca_immediate_response_mapping', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_immediate_response_mapping']['#type']);
    $this->assertEquals('javascript', $openfisca_settings_form['fisca_immediate_response_mapping']['#mode']);
    $json = $openfisca_settings_form['fisca_immediate_response_mapping']['#default_value'];
    $mappings = Json::decode($json);
    $this->assertTrue($mappings['aus_citizen_or_permanent_resident']);
    $this->assertTrue($mappings['has_disability']);
    $this->assertArrayNotHasKey('requires_ongoing_support', $mappings);

    $this->assertArrayHasKey('fisca_immediate_exit_mapping', $openfisca_settings_form);
    $this->assertEquals('webform_codemirror', $openfisca_settings_form['fisca_immediate_exit_mapping']['#type']);
    $this->assertEquals('text', $openfisca_settings_form['fisca_immediate_exit_mapping']['#mode']);
    $this->assertEquals('persons.personA.exit', $openfisca_settings_form['fisca_immediate_exit_mapping']['#default_value']);

    $this->assertArrayHasKey('fisca_immediate_response_ajax_indicator', $openfisca_settings_form);
    $this->assertEquals('checkbox', $openfisca_settings_form['fisca_immediate_response_ajax_indicator']['#type']);
    $this->assertTrue($openfisca_settings_form['fisca_immediate_response_ajax_indicator']['#default_value']);

    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertTrue($openfisca_settings->isEnabled());
    $this->assertFalse($openfisca_settings->isDebugEnabled());
    $this->assertTrue($openfisca_settings->isLoggingEnabled());
    $this->assertNotEmpty($openfisca_settings->getParameterTokens());
    $this->assertNotEmpty($openfisca_settings->getImmediateExitKeys());
    $this->assertTrue($openfisca_settings->hasImmediateResponseAjaxIndicator());

    // Clear some settings and submit the form.
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_enabled', NULL);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_debug_mode', '1');
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_logging_mode', NULL);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_immediate_exit_mapping', '');
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_immediate_response_ajax_indicator', NULL);
    $form_builder->submitForm($form_object, $form_state);
    $form_object->save($settings_form, $form_state);

    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertFalse($openfisca_settings->isEnabled());
    $this->assertTrue($openfisca_settings->isDebugEnabled());
    $this->assertFalse($openfisca_settings->isLoggingEnabled());
    $this->assertNotEmpty($openfisca_settings->getParameterTokens());
    $this->assertEmpty($openfisca_settings->getImmediateExitKeys());
    $this->assertFalse($openfisca_settings->hasImmediateResponseAjaxIndicator());

    $this->assertFalse($openfisca_settings->getVariable('exit'));
    $this->assertFalse($openfisca_settings->getVariable('child_currently_at_school'));
    // Reload the form - set immediate exit keys.
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_immediate_exit_mapping', 'persons.personA.exit,persons.personA.child_currently_at_school');
    $form_builder->submitForm($form_object, $form_state);
    $form_object->save($settings_form, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertEquals(['persons.personA.exit', 'persons.personA.child_currently_at_school'], $openfisca_settings->getImmediateExitKeys());
    $this->assertIsArray($openfisca_settings->getVariable('child_currently_at_school'));
    $this->assertFalse($openfisca_settings->getVariable('exit'));

    $this->assertTrue($openfisca_settings->hasApiEndpoint());
    // Reload the form - clear API endpoint.
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_api_endpoint', '');
    $form_builder->submitForm($form_object, $form_state);
    $form_object->save($settings_form, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertFalse($openfisca_settings->hasApiEndpoint());

    // Reload the form - reset API endpoint and clear tokens.
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_api_endpoint', 'https://api.openfisca.test');
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_parameter_tokens', '');
    $form_builder->submitForm($form_object, $form_state);
    $form_object->save($settings_form, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertEquals('https://api.openfisca.test', $openfisca_settings->getApiEndpoint());
    $this->assertEmpty($openfisca_settings->getParameterTokens());
    $this->assertEmpty($openfisca_settings->getParameters());

    // Reload the form - set some tokens.
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_parameter_tokens', 'disability_allowance_max_income,income_tax_rate');
    $form_builder->submitForm($form_object, $form_state);
    $form_object->save($settings_form, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertEquals(['disability_allowance_max_income', 'income_tax_rate'], $openfisca_settings->getParameterTokens());
    $this->assertIsArray($openfisca_settings->getParameter('disability_allowance_max_income'));
    $this->assertIsArray($openfisca_settings->getParameter('income_tax_rate'));

    // Reload the form - add an invalid parameter token.
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_parameter_tokens', 'disability_allowance_max_income,income_tax_rate, invalid_parameter,wrong_parameter');
    $form_builder->submitForm($form_object, $form_state);
    $errors = $form_state->getErrors();
    $this->assertIsArray($errors);
    $this->assertArrayHasKey('[third_party_settings][webform_openfisca][fisca_parameter_tokens', $errors);
    $error = $errors['[third_party_settings][webform_openfisca][fisca_parameter_tokens'];
    $this->assertInstanceOf(TranslatableMarkup::class, $error);
    /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $error */
    $error_arguments = $error->getArguments();
    $this->assertArrayHasKey('%parameters', $error_arguments);
    $this->assertEquals('invalid_parameter, wrong_parameter', $error_arguments['%parameters']);
    $this->assertArrayHasKey(':link', $error_arguments);
    $this->assertEquals('https://api.openfisca.test/parameters', $error_arguments[':link']);
  }

  /**
   * Test the webform_openfisca_key_auth module.
   */
  public function testAuthTokenKey() : void {
    $this->enableModules(['key', 'webform_openfisca_key_auth']);
    $this->installConfig(['webform_openfisca_test']);

    $webform = Webform::load('test_dac');
    $this->assertInstanceOf(WebformInterface::class, $webform);

    // Build and check the form.
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $settings_form = $this->reloadSettingsForm($webform, $form_object, $form_state);

    $this->assertArrayHasKey('third_party_settings', $settings_form);
    $this->assertArrayHasKey('webform_openfisca', $settings_form['third_party_settings']);
    $openfisca_settings_form = $settings_form['third_party_settings']['webform_openfisca'];

    $this->assertArrayHasKey('fisca_api_authorization_header_token_key', $openfisca_settings_form);
    $this->assertEquals('key_select', $openfisca_settings_form['fisca_api_authorization_header_token_key']['#type']);
    $this->assertArrayHasKey('openfisca_authorization_key', $openfisca_settings_form['fisca_api_authorization_header_token_key']['#options']);

    // Select a key.
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $this->setWebformOpenFiscaFormStateValue($form_state, 'fisca_api_authorization_header_token_key', 'openfisca_authorization_key');
    $form_builder->submitForm($form_object, $form_state);
    $form_object->save($settings_form, $form_state);
    $webform = Webform::load('test_dac');
    $token_key = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_api_authorization_header_token_key', '');
    $this->assertEquals('openfisca_authorization_key', $token_key);

    putenv('OPENFISCA_AUTH_TOKEN=EXTRA-TEST-OPENFISCA-AUTH-TOKEN');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $client = $openfisca_settings->getOpenFiscaClient(\Drupal::service('webform_openfisca.openfisca_client_factory'));
    $http_client_options = $client->getHttpClientOptions();
    $this->assertArrayHasKey('headers', $http_client_options);
    $this->assertArrayHasKey('Authorization', $http_client_options['headers']);
    $auth_header = $http_client_options['headers']['Authorization'];
    $this->assertEquals('Token WEBFORM-OPENFISCA-TEST-TOKEN EXTRA-TEST-OPENFISCA-AUTH-TOKEN', $auth_header);

    // Tests with another webform.
    $webform = Webform::load('test_invalid_api');
    $token_key = (string) $webform->getThirdPartySetting('webform_openfisca', 'fisca_api_authorization_header_token_key', '');
    $this->assertEmpty($token_key);

    $webform->setThirdPartySetting('webform_openfisca', 'fisca_api_authorization_header_token_key', 'invalid_token_key');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $client = $openfisca_settings->getOpenFiscaClient(\Drupal::service('webform_openfisca.openfisca_client_factory'));
    $http_client_options = $client->getHttpClientOptions();
    $this->assertArrayNotHasKey('headers', $http_client_options);
  }

  /**
   * Get the form of Webform General Settings.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   * @param \Drupal\Core\Form\FormInterface|null $form_object
   *   The form object - will be overridden.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state -  will be overridden.
   *
   * @return array
   *   The form array.
   *
   * @throws \Exception
   */
  protected function reloadSettingsForm(WebformInterface $webform, ?FormInterface &$form_object = NULL, ?FormStateInterface &$form_state = NULL) : array {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');

    $form_object = $this->container->get('entity_type.manager')->getFormObject('webform', 'settings');
    $form_object->setEntity($webform);
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform]);
    return $form_builder->buildForm($form_object, $form_state);
  }

  /**
   * Set a Webform OpenFisca value in the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  protected function setWebformOpenFiscaFormStateValue(FormStateInterface $form_state, string $key, mixed $value): void {
    $form_state->setValue(['third_party_settings', 'webform_openfisca', $key], $value);
  }

}
