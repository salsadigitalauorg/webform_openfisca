<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler;

/**
 * Tests the OpenFiscaJourneyHandler class.
 *
 * @group webform_openfisca
 * @group webform_openfisca_handler
 * @coversDefaultClass \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler
 */
class OpenFiscaJourneyHandlerKernelTest extends BaseKernelTestCase {

  const string PERIOD = '2025-01-01';

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();
    // Set the period query so that the request payload does not change.
    \Drupal::request()->query->set('period', static::PERIOD);
  }

  /**
   * Test the alterElement() method.
   */
  public function testAlterElement(): void {
    // Build and check the No API form.
    $webform = Webform::load('test_no_api');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');
    $this->assertArrayHasKey('elements', $webform_submission_form);
    $this->assertArrayHasKey('has_disability', $webform_submission_form['elements']);
    $has_disability = $webform_submission_form['elements']['has_disability'];
    $this->assertArrayNotHasKey('#ajax', $has_disability);
    $this->assertArrayHasKey('#attributes', $has_disability);
    $this->assertArrayNotHasKey('data-openfisca-immediate-response', $has_disability['#attributes']);
    $this->assertArrayNotHasKey('data-openfisca-webform-id', $has_disability['#attributes']);

    // Build and check the Test DAC form.
    $webform = Webform::load('test_dac');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());

    // Test Edit operation.
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'edit');
    $this->assertArrayHasKey('elements', $webform_submission_form);
    $this->assertArrayHasKey('has_disability', $webform_submission_form['elements']);
    $has_disability = $webform_submission_form['elements']['has_disability'];
    $this->assertArrayNotHasKey('#ajax', $has_disability);
    $this->assertArrayHasKey('#attributes', $has_disability);
    $this->assertArrayNotHasKey('data-openfisca-immediate-response', $has_disability['#attributes']);
    $this->assertArrayNotHasKey('data-openfisca-webform-id', $has_disability['#attributes']);

    // Test Add operation.
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');
    $this->assertArrayHasKey('elements', $webform_submission_form);
    $this->assertArrayHasKey('has_disability', $webform_submission_form['elements']);
    $has_disability = $webform_submission_form['elements']['has_disability'];
    $this->assertArrayHasKey('#attributes', $has_disability);
    $this->assertArrayHasKey('data-openfisca-immediate-response', $has_disability['#attributes']);
    $this->assertEquals('true', $has_disability['#attributes']['data-openfisca-immediate-response']);
    $this->assertArrayHasKey('data-openfisca-webform-id', $has_disability['#attributes']);
    $this->assertEquals($webform->id(), $has_disability['#attributes']['data-openfisca-webform-id']);
    $this->assertArrayHasKey('#ajax', $has_disability);
    $ajax = $has_disability['#ajax'];
    $this->assertInstanceOf(OpenFiscaJourneyHandler::class, $ajax['callback'][0]);
    $this->assertEquals('requestOpenFiscaImmediateResponse', $ajax['callback'][1]);
    $this->assertEquals('fiscaImmediateResponse:request', $ajax['event']);
    $this->assertEquals('throbber', $ajax['progress']['type']);
  }

  /**
   * Test the submitForm() method.
   */
  public function testSubmitForm(): void {
    $this->setUpRacContentModules();

    $webform = Webform::load('test_dac');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    // @see OpenFiscaTestClientMiddleware::loadFixture()
    // RequestPayload hash: 8fed8ce13457255b727b270e, expecting result from
    // calculate-8fed8ce13457255b727b270e-notes-submitForm-no-benefit.json.
    $values = [
      'aus_citizen_or_permanent_resident' => 'true',
      'what_is_your_monthly_income_' => '500',
      'has_disability' => 'true',
      'requires_ongoing_support' => 'true',
      'requires_ongoing_supervision_or_treatment' => 'true',
      'disability_allowance_eligible' => 'null',
      'disability_allowance_benefit' => '',
      'monthly_income_exceeds_limit' => '',
    ];
    $webform_submission = $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $handler->submitForm($webform_submission_form, $form_state, $webform_submission);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayHasKey('response', $recent_debug_data);
    $response = $recent_debug_data['response'];
    $this->assertNotNull($response);
    $this->assertEquals('https://api.openfisca.test/calculate', $response->getDebugData('openfisca_api_endpoint'), 'openfisca_api_endpoint is not https://api.openfisca.test/calculate.');
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertFalse($response->hasDebugData('rac_redirect'), 'rac_redirect exists.');
    $this->assertFalse($response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url exists.');
    $this->assertSame(0, $response->getDebugData('total_benefits'), 'total_benefits is not 0.');
    $this->assertEquals(['total_benefit' => 0, 'period' => static::PERIOD, 'change' => 1], $response->getDebugData('query_append'), 'query_append.total_benefit is not 0.');
    $this->assertEquals([
      'persons.personA.disability_allowance_eligible' => FALSE,
      'persons.personA.disability_allowance_benefit' => 0,
      'persons.personA.monthly_income_exceeds_limit' => TRUE,
    ], $response->getDebugData('result_values'), 'result_values array does not match expected values.');
    $this->assertNotEmpty($response->getDebugData('query'), 'query is empty');
    $query = [];
    parse_str($response->getDebugData('query'), $query);
    foreach (array_keys($values) as $key) {
      $this->assertArrayHasKey($key, $query, sprintf('Query does not contain key "%s".', $key));
    }
    $this->assertEquals('0', $query['total_benefit'], 'query.total_benefit is not 0.');
    $this->assertEquals('0', $query['disability_allowance_eligible'], 'query.disability_allowance is not 0.');
    $this->assertEquals('0', $query['disability_allowance_benefit'], 'query.disability_benefit is not 0.');
    $this->assertEquals('1', $query['monthly_income_exceeds_limit'], 'query.monthly_income_exceeds_limit is not 1.');

    // Prepare RAC content.
    $this->createTestPage('Page /node/1');
    $no_benefit = $this->createTestPage('No Benefit');
    $disability_benefit = $this->createTestPage('Disability Benefit');
    $this->createRacContent('test_dac', 'Test RAC', [
      [
        'redirect' => $no_benefit,
        'rules' => [
          'persons.personA.disability_allowance_benefit' => 0,
          'persons.personA.disability_allowance_eligible' => 0,
        ],
      ],
      [
        'redirect' => $disability_benefit,
        'rules' => [
          'persons.personA.disability_allowance_benefit' => 1,
          'persons.personA.disability_allowance_eligible' => 1,
        ],
      ],
    ]);

    // Test the same submission with RAC.
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');
    $webform_submission = $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $handler->submitForm($webform_submission_form, $form_state, $webform_submission);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayHasKey('response', $recent_debug_data);
    $response = $recent_debug_data['response'];
    $this->assertNotNull($response);
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertEquals($no_benefit->toUrl()->toString(), $response->getDebugData('rac_redirect'), sprintf('rac_redirect is not "%s".', $no_benefit->toUrl()->toString()));
    $this->assertEquals($no_benefit->toUrl()->toString() . '?what_is_your_monthly_income_=500&has_disability=1&requires_ongoing_support=1&requires_ongoing_supervision_or_treatment=1&disability_allowance_eligible=0&aus_citizen_or_permanent_resident=1&disability_allowance_benefit=0&monthly_income_exceeds_limit=1&total_benefit=0', $response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url is not expected.');

    // Reset the webform and test new submission with RAC.
    $webform->resetSettings();
    // RequestPayload hash: 114331932191b5eb572eb8ff.
    // Expecting result from calculate-114331932191b5eb572eb8ff-notes-submitForm-disability_benefits.json.
    $values = [
      'aus_citizen_or_permanent_resident' => 'true',
      'what_is_your_monthly_income_' => '100',
      'has_disability' => 'true',
      'requires_ongoing_support' => 'true',
      'requires_ongoing_supervision_or_treatment' => 'true',
      'disability_allowance_eligible' => 'null',
      'disability_allowance_benefit' => '',
      'monthly_income_exceeds_limit' => '',
    ];
    $webform_submission = $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $handler->submitForm($webform_submission_form, $form_state, $webform_submission);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayHasKey('response', $recent_debug_data);
    $response = $recent_debug_data['response'];
    $this->assertNotNull($response);
    $this->assertSame(1, $response->getDebugData('total_benefits'), 'total_benefits is not 1.');
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertEquals($disability_benefit->toUrl()->toString(), $response->getDebugData('rac_redirect'), sprintf('rac_redirect is not "%s".', $disability_benefit->toUrl()->toString()));
    $this->assertEquals($disability_benefit->toUrl()->toString() . '?what_is_your_monthly_income_=100&has_disability=1&requires_ongoing_support=1&requires_ongoing_supervision_or_treatment=1&disability_allowance_eligible=1&aus_citizen_or_permanent_resident=1&disability_allowance_benefit=1&monthly_income_exceeds_limit=0&total_benefit=1', $response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url is not expected.');
  }

  /**
   * Test the testImmediateResponse() method with RAC content.
   */
  public function testImmediateResponse(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_dac_immediate');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    // Prepare RAC content.
    $this->createTestPage('Page /node/1');
    $disability_benefit = $this->createTestPage('Disability Benefit');
    $non_aus_citizen = $this->createTestPage('Non-Aus citizen');
    $this->createRacContent('test_dac_immediate', 'Test RAC', [
      [
        'redirect' => $disability_benefit,
        'rules' => [
          'persons.personA.disability_allowance_benefit' => 1,
          'persons.personA.disability_allowance_eligible' => 1,
        ],
      ],
      [
        'redirect' => $non_aus_citizen,
        'rules' => [
          'persons.personA.aus_citizen' => 0,
        ],
      ],
    ]);

    // @see OpenFiscaTestClientMiddleware::loadFixture()
    // RequestPayload hash: 83732831beb93bbd70698b0e. Expecting result from
    // calculate-83732831beb93bbd70698b0e-notes-immediate-exit.json.
    $values = [
      'aus_citizen_or_permanent_resident' => 'false',
      'what_is_your_monthly_income_' => '',
      'has_disability' => '',
      'requires_ongoing_support' => '',
      'requires_ongoing_supervision_or_treatment' => '',
      'disability_allowance_eligible' => 'null',
      'disability_allowance_benefit' => '',
      'monthly_income_exceeds_limit' => '',
    ];
    $form_state->setTriggeringElement($webform_submission_form['elements']['aus_citizen_or_permanent_resident']);
    $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $ajax_response = $handler->requestOpenFiscaImmediateResponse($webform_submission_form, $form_state);

    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayHasKey('response', $recent_debug_data);
    $response = $recent_debug_data['response'];
    $this->assertNotNull($response);
    $this->assertEquals('https://api.openfisca.test/calculate', $response->getDebugData('openfisca_api_endpoint'), 'openfisca_api_endpoint is not https://api.openfisca.test/calculate.');
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertSame(-1, $response->getDebugData('total_benefits'), 'total_benefits is not -1.');
    $this->assertEquals(['total_benefit' => 0, 'period' => static::PERIOD, 'change' => 1, 'immediate_exit' => 1], $response->getDebugData('query_append'), 'query_append.total_benefit is not 0.');
    $this->assertEquals($non_aus_citizen->toUrl()->toString(), $response->getDebugData('rac_redirect'), sprintf('rac_redirect is not "%s".', $non_aus_citizen->toUrl()->toString()));
    $this->assertEquals($non_aus_citizen->toUrl()->toString() . '?disability_allowance_eligible=0&aus_citizen_or_permanent_resident=0&disability_allowance_benefit=0&period=2025-01-01&change=1&total_benefit=0&immediate_exit=1', $response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url is not expected.');

    $command = current($ajax_response->getCommands());
    $this->assertEquals('invoke', $command['command']);
    $this->assertEmpty($command['selector']);
    $this->assertEquals('webformOpenfiscaImmediateResponseRedirect', $command['method']);
    $this->assertEquals($non_aus_citizen->toUrl()->toString(), $command['args'][0]['confirmation_url']);
    $this->assertEquals('disability_allowance_eligible=0&aus_citizen_or_permanent_resident=0&disability_allowance_benefit=0&period=2025-01-01&change=1&total_benefit=0&immediate_exit=1', $command['args'][0]['query']);

    // Reset the webform and test with other submission values.
    $webform->resetSettings();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');
    // RequestPayload hash: d872fe59dfd11466b8232a76. Expecting result from
    // calculate-d872fe59dfd11466b8232a76-notes-immediate-response.json.
    $values = [
      'aus_citizen_or_permanent_resident' => 'true',
      'what_is_your_monthly_income_' => '',
      'has_disability' => 'true',
      'requires_ongoing_support' => '',
      'requires_ongoing_supervision_or_treatment' => '',
      'disability_allowance_eligible' => 'null',
      'disability_allowance_benefit' => '',
      'monthly_income_exceeds_limit' => '',
    ];
    $form_state->setTriggeringElement($webform_submission_form['elements']['has_disability']);
    $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $ajax_response = $handler->requestOpenFiscaImmediateResponse($webform_submission_form, $form_state);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayHasKey('response', $recent_debug_data);
    $response = $recent_debug_data['response'];
    $this->assertNotNull($response);
    $this->assertEquals('https://api.openfisca.test/calculate', $response->getDebugData('openfisca_api_endpoint'), 'openfisca_api_endpoint is not https://api.openfisca.test/calculate.');
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertSame(1, $response->getDebugData('total_benefits'), 'total_benefits is not 1.');
    $this->assertEquals(['total_benefit' => 1, 'period' => static::PERIOD, 'change' => 1], $response->getDebugData('query_append'), 'query_append.total_benefit is not 1.');
    $this->assertEquals($disability_benefit->toUrl()->toString(), $response->getDebugData('rac_redirect'), sprintf('rac_redirect is not "%s".', $disability_benefit->toUrl()->toString()));
    $this->assertEquals($disability_benefit->toUrl()->toString() . '?has_disability=1&disability_allowance_eligible=1&aus_citizen_or_permanent_resident=1&disability_allowance_benefit=1&period=2025-01-01&change=1&total_benefit=1', $response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url is not expected.');

    $command = current($ajax_response->getCommands());
    $this->assertEquals('invoke', $command['command']);
    $this->assertEmpty($command['selector']);
    $this->assertEquals('webformOpenfiscaImmediateResponseRedirect', $command['method']);
    $this->assertEquals($disability_benefit->toUrl()->toString(), $command['args'][0]['confirmation_url']);
    $this->assertEquals('has_disability=1&disability_allowance_eligible=1&aus_citizen_or_permanent_resident=1&disability_allowance_benefit=1&period=2025-01-01&change=1&total_benefit=1', $command['args'][0]['query']);

    // Reset the webform and test with other submission values.
    $webform->resetSettings();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');
    // RequestPayload hash: dd069e110305d536bcf9ee0c.
    // Expecting result from calculate-dd069e110305d536bcf9ee0c-notes-immediate-response-continue.json.
    $values = [
      'aus_citizen_or_permanent_resident' => 'true',
      'what_is_your_monthly_income_' => '100',
      'has_disability' => 'false',
      'requires_ongoing_support' => '',
      'requires_ongoing_supervision_or_treatment' => '',
      'disability_allowance_eligible' => 'null',
      'disability_allowance_benefit' => '',
      'monthly_income_exceeds_limit' => '',
    ];
    $form_state->setTriggeringElement($webform_submission_form['elements']['what_is_your_monthly_income_']);
    $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $ajax_response = $handler->requestOpenFiscaImmediateResponse($webform_submission_form, $form_state);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayHasKey('response', $recent_debug_data);
    $response = $recent_debug_data['response'];
    $this->assertNotNull($response);
    $this->assertEquals('https://api.openfisca.test/calculate', $response->getDebugData('openfisca_api_endpoint'), 'openfisca_api_endpoint is not https://api.openfisca.test/calculate.');
    $this->assertFalse($response->hasDebugData('webform_confirmation_url'), 'webform_confirmation_url exists.');
    $this->assertSame(0, $response->getDebugData('total_benefits'), 'total_benefits is not 0.');
    $this->assertEquals(['total_benefit' => 0, 'period' => static::PERIOD, 'change' => 1], $response->getDebugData('query_append'), 'query_append.total_benefit is not 0.');
    $this->assertFalse($response->hasDebugData('rac_redirect'), 'rac_redirect exists.');
    $this->assertFalse($response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url exists.');

    $command = current($ajax_response->getCommands());
    $this->assertEquals('invoke', $command['command']);
    $this->assertEmpty($command['selector']);
    $this->assertEquals('webformOpenfiscaImmediateResponseContinue', $command['method']);
    $this->assertEquals('what_is_your_monthly_income_', $command['args'][0]['name']);
    $this->assertEquals('test_dac_immediate', $command['args'][0]['webform']);
    $this->assertEquals('edit-what-is-your-monthly-income-', $command['args'][0]['selector']);
    $this->assertEquals('edit-what-is-your-monthly-income-3', $command['args'][0]['original_selector']);
  }

  /**
   * Test the submitForm() method with Invalid API form and no logging.
   */
  public function testSubmitFormWithInvalidApi(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_invalid_api');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    // Unset the period query.
    \Drupal::request()->query->set('period', NULL);

    $values = [
      'aus_citizen_or_permanent_resident' => 'false',
    ];
    $webform_submission = $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $handler->submitForm($webform_submission_form, $form_state, $webform_submission);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayNotHasKey('response', $recent_debug_data);
  }

  /**
   * Test the submitForm() method with Invalid API form and Period webform key.
   */
  public function testSubmitFormWithInvalidApiWithPeriodKey(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_invalid_api_period');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    // Unset the period query.
    \Drupal::request()->query->set('period', NULL);

    $values = [
      'aus_citizen_or_permanent_resident' => 'false',
      'period' => '2025-01-02',
      'hidden_field' => 'some values',
    ];
    $webform_submission = $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $handler->submitForm($webform_submission_form, $form_state, $webform_submission);
    $recent_debug_data = $handler->getRecentDebugData();
    $this->assertArrayNotHasKey('response', $recent_debug_data);
  }

  /**
   * Test the submitForm() method with Invalid API form and no logging.
   */
  public function testSubmitFormWithInvalidApiWithNoLog(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_invalid_api_nolog');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    $values = [
      'aus_citizen_or_permanent_resident' => 'false',
    ];
    $webform_submission = $this->prepareWebformSubmission($webform, $form_state, $values);
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $handler->submitForm($webform_submission_form, $form_state, $webform_submission);
    $this->assertEmpty($handler->getRecentDebugData());
  }

  /**
   * Retrieve a new webform submission for a webform.
   *
   * @param \Drupal\webform\WebformInterface|string $webform
   *   The webform.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state.
   * @param array|null $values
   *   The initial values for the submission.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   The empty webform submission.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function prepareWebformSubmission(WebformInterface|string $webform, ?FormStateInterface $form_state = NULL, ?array $values = []) : WebformSubmissionInterface {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $storage */
    $storage = $entity_type_manager->getStorage('webform_submission');
    $webform_id = ($webform instanceof WebformInterface) ? $webform->id() : (string) $webform;
    if ($form_state instanceof FormStateInterface && is_array($values)) {
      $form_state->setValues($values);
    }
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $storage->create([
      'webform_id' => $webform_id,
      'data' => ($form_state instanceof FormStateInterface) ? $form_state->getValues() : [],
    ]);
    return $webform_submission;
  }

  /**
   * Get the form for a webform submission operation.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param \Drupal\Core\Form\FormInterface|null $form_object
   *   The form object - will be overridden.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   The form state - will be overridden.
   * @param string $operation
   *   The entity form operation.
   *
   * @return array
   *   The form.
   *
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  protected function reloadWebformSubmissionForm(WebformSubmissionInterface $webform_submission, ?FormInterface &$form_object = NULL, ?FormStateInterface &$form_state = NULL, string $operation = 'add') : array {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');

    $form_object = $this->container->get('entity_type.manager')->getFormObject('webform_submission', $operation);
    $form_object->setEntity($webform_submission);
    $form_state = new FormState();
    return $form_builder->buildForm($form_object, $form_state);
  }

}
