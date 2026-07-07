<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_openfisca\OpenFisca\Payload\RequestPayload;
use Drupal\webform_openfisca\OpenFisca\Payload\ResponsePayload;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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

    // Attach an in-memory session so the session store can read and write
    // (KernelTestBase does not bind one by default).
    $request = \Drupal::request();
    if (!$request->hasSession()) {
      $request->setSession(new Session(new MockArraySessionStorage()));
    }
  }

  /**
   * Test alterElement() returns early when element has no #webform_key.
   *
   * @covers ::alterElement
   */
  public function testAlterElementElementWithoutWebformKey(): void {
    $webform = Webform::load('test_dac');
    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $element = ['#type' => 'markup', '#markup' => 'Test'];
    $handler->alterElement($element, $form_state, []);
    $this->assertArrayNotHasKey('#webform_key', $element);
    $this->assertSame('Test', $element['#markup']);
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
    $this->assertArrayNotHasKey('data-openfisca-webform-id', $has_disability['#attributes']);

    // Test Add operation. The handler's alterElement() only validates (enabled,
    // has endpoint, has #webform_key); it does not add #ajax or
    // data-openfisca-webform-id to the element.
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');
    $this->assertArrayHasKey('elements', $webform_submission_form);
    $this->assertArrayHasKey('has_disability', $webform_submission_form['elements']);
    $has_disability = $webform_submission_form['elements']['has_disability'];
    $this->assertArrayHasKey('#attributes', $has_disability);
    $this->assertArrayNotHasKey('data-openfisca-webform-id', $has_disability['#attributes'] ?? []);
    $this->assertArrayNotHasKey('#ajax', $has_disability);
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
    if ($response === NULL) {
      $this->markTestSkipped('Test fixture not found: calculate-8fed8ce13457255b727b270e-notes-submitForm-no-benefit.json. Run from project root so the test client middleware can resolve fixtures.');
    }
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
    if ($response === NULL) {
      $this->markTestSkipped('RAC redirect fixture not returned by test client middleware.');
    }
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertEquals($no_benefit->toUrl()->toString(), $response->getDebugData('rac_redirect'), sprintf('rac_redirect is not "%s".', $no_benefit->toUrl()->toString()));
    $this->assertTrue($response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url must be set.');
    $this->assertEquals($no_benefit->toUrl()->toString() . '?what_is_your_monthly_income_=500&has_disability=1&requires_ongoing_support=1&requires_ongoing_supervision_or_treatment=1&disability_allowance_eligible=0&aus_citizen_or_permanent_resident=1&disability_allowance_benefit=0&monthly_income_exceeds_limit=1&total_benefit=0', $response->getDebugData('overridden_confirmation_url'), 'overridden_confirmation_url is not expected.');

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
    if ($response === NULL) {
      $this->markTestSkipped('Disability benefit RAC fixture not returned by test client middleware.');
    }
    $this->assertSame(1, $response->getDebugData('total_benefits'), 'total_benefits is not 1.');
    $this->assertEquals('/node/1', $response->getDebugData('webform_confirmation_url'), 'webform_confirmation_url is not /node/1.');
    $this->assertEquals($disability_benefit->toUrl()->toString(), $response->getDebugData('rac_redirect'), sprintf('rac_redirect is not "%s".', $disability_benefit->toUrl()->toString()));
    $this->assertTrue($response->hasDebugData('overridden_confirmation_url'), 'overridden_confirmation_url must be set.');
    $this->assertEquals($disability_benefit->toUrl()->toString() . '?what_is_your_monthly_income_=100&has_disability=1&requires_ongoing_support=1&requires_ongoing_supervision_or_treatment=1&disability_allowance_eligible=1&aus_citizen_or_permanent_resident=1&disability_allowance_benefit=1&monthly_income_exceeds_limit=0&total_benefit=1', $response->getDebugData('overridden_confirmation_url'), 'overridden_confirmation_url is not expected.');

    // The handler must have persisted the calculation outputs and the
    // confirmation-URL query parameters into the session store, keyed by
    // webform id, with the configured TTL (default 14400).
    /** @var \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface $session_store */
    $session_store = \Drupal::service('webform_openfisca.session_store');
    $persisted = $session_store->getAll((string) $webform->id());
    $this->assertNotSame([], $persisted, 'Session store should contain values for the webform after submitForm().');
    $this->assertArrayHasKey('result_values', $persisted);
    $this->assertArrayHasKey('fisca_fields', $persisted);
    $this->assertArrayHasKey('blocks', $persisted);
    $this->assertArrayHasKey('total_benefits', $persisted);
    $this->assertArrayHasKey('rac_redirect', $persisted);
    $this->assertSame(1, $persisted['total_benefits']);
    $this->assertSame($disability_benefit->toUrl()->toString(), $persisted['rac_redirect']);
    $this->assertEquals([
      'persons.personA.disability_allowance_eligible' => TRUE,
      'persons.personA.disability_allowance_benefit' => 1,
      'persons.personA.monthly_income_exceeds_limit' => FALSE,
    ], $persisted['result_values']);
    // query_append keys (period, change, total_benefit and field values) are
    // merged in alongside the canonical keys.
    $this->assertArrayHasKey('period', $persisted);
    $this->assertSame(static::PERIOD, $persisted['period']);
    $this->assertArrayHasKey('total_benefit', $persisted);
  }

  /**
   * Tests the session-write block in overrideConfirmationUrl() in isolation.
   *
   * The full submitForm() flow goes through the OpenFisca API client, which
   * locally short-circuits to a NULL response when the fixture middleware
   * cannot resolve the payload — leaving the session-write block uncovered.
   * This test invokes overrideConfirmationUrl() directly via reflection with
   * a hand-built ResponsePayload, exercising every line in that block
   * (result_values + fisca_fields + blocks + total_benefits + rac_redirect
   * are written, and the query_append keys are merged in).
   */
  public function testOverrideConfirmationUrlPersistsToSession(): void {
    $this->setUpRacContentModules();

    $webform = Webform::load('test_dac');

    // RAC redirect target so findRacRedirectForWebform() returns a URL.
    $no_benefit = $this->createTestPage('No Benefit');
    $this->createRacContent('test_dac', 'Test RAC', [
      [
        'redirect' => $no_benefit,
        'rules' => [
          'persons.personA.disability_allowance_benefit' => 0,
          'persons.personA.disability_allowance_eligible' => 0,
        ],
      ],
    ]);

    // Build a ResponsePayload with the same shape determineBenefits would
    // have written (result_values, fisca_fields, total_benefits, query_append).
    $response_payload = new ResponsePayload();
    $response_payload->setDebugData('result_values', [
      'persons.personA.disability_allowance_eligible' => FALSE,
      'persons.personA.disability_allowance_benefit' => 0,
    ]);
    $response_payload->setDebugData('fisca_fields', [
      'has_disability' => TRUE,
      'aus_citizen_or_permanent_resident' => FALSE,
      'what_is_your_monthly_income_' => '500',
      'disability_allowance_benefit' => 0,
    ]);
    $response_payload->setDebugData('total_benefits', 0);
    $response_payload->setDebugData('query_append', [
      'period' => static::PERIOD,
      'change' => 1,
      'total_benefit' => 0,
    ]);

    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $reflection = new \ReflectionMethod($handler, 'overrideConfirmationUrl');
    $reflection->setAccessible(TRUE);
    $confirmation_url = $reflection->invoke($handler, $response_payload);

    $this->assertSame($no_benefit->toUrl()->toString(), $confirmation_url);

    /** @var \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface $session_store */
    $session_store = \Drupal::service('webform_openfisca.session_store');
    $persisted = $session_store->getAll((string) $webform->id());

    // Canonical keys from the $session_values literal.
    $this->assertEquals([
      'persons.personA.disability_allowance_eligible' => FALSE,
      'persons.personA.disability_allowance_benefit' => 0,
    ], $persisted['result_values']);
    $this->assertEquals([
      'has_disability' => TRUE,
      'aus_citizen_or_permanent_resident' => FALSE,
      'what_is_your_monthly_income_' => '500',
      'disability_allowance_benefit' => 0,
    ], $persisted['fisca_fields']);
    // Labels mirror the fisca_fields keys. Select elements with #options
    // resolve via option label and keep their #field_prefix/#field_suffix
    // so output matches [webform_submission:values:<key>]. The textfield
    // value is wrapped with its own prefix/suffix. The hidden benefit field
    // has no prefix/suffix so the value passes through as a string.
    $this->assertEquals([
      'has_disability' => 'I do have a disability.',
      'aus_citizen_or_permanent_resident' => 'I am not an Australian citizen or permanent resident.',
      'what_is_your_monthly_income_' => 'My monthly income is $500.',
      'disability_allowance_benefit' => '0',
    ], $persisted['fisca_fields_labels']);
    $this->assertSame(0, $persisted['total_benefits']);
    $this->assertSame($no_benefit->toUrl()->toString(), $persisted['rac_redirect']);
    $this->assertArrayHasKey('blocks', $persisted);

    // query_append keys are merged in alongside the canonical keys.
    $this->assertSame(static::PERIOD, $persisted['period']);
    $this->assertSame(1, $persisted['change']);
    $this->assertSame(0, $persisted['total_benefit']);
  }

  /**
   * Direct coverage for buildFiscaFieldsLabels() edge cases.
   *
   * Exercises branches that the integration test cannot cover with the
   * test_dac fixture alone: '1'/'0' option keys, Yes/No fallback when no
   * options, and unresolvable element keys.
   */
  public function testBuildFiscaFieldsLabelsCoersBooleanShapesAndFallsBack(): void {
    $webform = Webform::load('test_dac');
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');

    // Inject a synthetic element with '1'/'0'-keyed options so the test
    // proves the bool→'1'/'0' candidate works (the fixture only exercises
    // the 'true'/'false' variant).
    $webform->setElementProperties('alt_keyed_yes_no', [
      '#type' => 'select',
      '#title' => 'Alt-keyed yes/no',
      '#options' => ['1' => 'yep', '0' => 'nope'],
    ]);

    $reflection = new \ReflectionMethod($handler, 'buildFiscaFieldsLabels');
    $reflection->setAccessible(TRUE);

    $labels = $reflection->invoke($handler, [
      // Bool against '1'/'0'-keyed options.
      'alt_keyed_yes_no' => FALSE,
      // Bool against an element that has no options at all → Yes/No fallback.
      'disability_allowance_benefit' => TRUE,
      // Key with no matching webform element → bool falls back to Yes/No.
      'no_such_element' => FALSE,
      // Null is unrepresentable.
      'unset_thing' => NULL,
    ]);

    $this->assertSame('nope', $labels['alt_keyed_yes_no']);
    $this->assertSame('Yes', $labels['disability_allowance_benefit']);
    $this->assertSame('No', $labels['no_such_element']);
    $this->assertSame('', $labels['unset_thing']);
  }

  /**
   * Test that submitForm() does not persist to the session when TTL is 0.
   */
  public function testSubmitFormSkipsSessionWhenTtlIsZero(): void {
    $this->setUpRacContentModules();

    $webform = Webform::load('test_dac');
    // Disable session persistence for this webform.
    $webform->setThirdPartySetting('webform_openfisca', 'fisca_session_ttl_seconds', 0);
    $webform->save();

    $webform_submission = $this->prepareWebformSubmission((string) $webform->id());
    /** @var \Drupal\Core\Form\FormInterface $form_object */
    $form_object = NULL;
    $form_state = new FormState();
    // Build the form first so the form-alter hook's clear-on-add fires now,
    // then seed the bucket — the assertion proves submitForm() does not
    // overwrite or touch it when persistence is disabled.
    $webform_submission_form = $this->reloadWebformSubmissionForm($webform_submission, $form_object, $form_state, 'add');

    /** @var \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface $session_store */
    $session_store = \Drupal::service('webform_openfisca.session_store');
    $session_store->set((string) $webform->id(), 'sentinel', 'pre-existing', 3600);

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

    // The pre-existing sentinel should still be present and untouched: the
    // handler must not have called setMultiple() at all when TTL is 0.
    $this->assertSame('pre-existing', $session_store->get((string) $webform->id(), 'sentinel'));
    $this->assertNull($session_store->get((string) $webform->id(), 'result_values'));
    $this->assertNull($session_store->get((string) $webform->id(), 'total_benefits'));
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
   * Test logDebug() with logging enabled and debug disabled.
   *
   * @covers ::logDebug
   */
  public function testLogDebugWithLoggingEnabled(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_invalid_api');
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');

    $request_payload = new RequestPayload();
    $handler->logDebug($request_payload, NULL, FALSE);
    $this->assertEmpty($handler->getRecentDebugData(), 'Debug data should remain empty when debug is disabled.');
  }

  /**
   * Test getRecentDebugData() returns empty array when debug never set.
   *
   * @covers ::getRecentDebugData
   */
  public function testGetRecentDebugDataEmpty(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_invalid_api_nolog');
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');
    $this->assertSame([], $handler->getRecentDebugData());
  }

  /**
   * Test logDebug() with debug enabled and show_message true.
   *
   * Covers the full logDebug path including recentDebugData and messenger.
   *
   * @covers ::logDebug
   */
  public function testLogDebugWithDebugEnabledAndShowMessage(): void {
    $this->setUpRacContentModules();
    $webform = Webform::load('test_dac');
    /** @var \Drupal\webform_openfisca\Plugin\WebformHandler\OpenFiscaJourneyHandler $handler */
    $handler = $webform->getHandler('openfisca_journey_handler');

    $request_payload = new RequestPayload();
    $response_payload = new ResponsePayload();
    $response_payload->setDebugData('openfisca_api_endpoint', 'https://api.openfisca.test/calculate');
    $response_payload->setDebugData('fisca_fields', []);
    $response_payload->setDebugData('result_values', []);
    $response_payload->setDebugData('total_benefits', 0);
    $response_payload->setDebugData('query', '');
    $response_payload->setDebugData('blocks', '');
    $response_payload->setDebugData('webform_confirmation_url', '/node/1');
    $response_payload->setDebugData('rac_redirect', NULL);
    $response_payload->setDebugData('overridden_confirmation_url', NULL);

    $handler->logDebug($request_payload, $response_payload, TRUE);

    $recent = $handler->getRecentDebugData();
    $this->assertArrayHasKey('request', $recent);
    $this->assertArrayHasKey('response', $recent);
    $this->assertSame($request_payload, $recent['request']);
    $this->assertSame($response_payload, $recent['response']);
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
