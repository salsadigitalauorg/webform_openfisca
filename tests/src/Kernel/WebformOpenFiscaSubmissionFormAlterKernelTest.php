<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Kernel test for hook_webform_submission_form_alter().
 *
 * Exercises every guard in webform_openfisca_webform_submission_form_alter()
 * to ensure stale session data is cleared exactly when a fresh OpenFisca
 * journey starts.
 *
 * @group webform_openfisca
 * @group webform_openfisca_form_alter
 */
class WebformOpenFiscaSubmissionFormAlterKernelTest extends BaseKernelTestCase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();

    // Ensure a session is bound to the request so the store can write/clear.
    $request = \Drupal::request();
    if (!$request->hasSession()) {
      $request->setSession(new Session(new MockArraySessionStorage()));
    }
  }

  /**
   * Building the add form for an OpenFisca-enabled webform clears the bucket.
   */
  public function testAddFormClearsSessionBucketForOpenFiscaWebform(): void {
    $store = $this->seedSessionStore('test_dac');

    $this->buildSubmissionForm('test_dac', 'add');

    $this->assertFalse(
      $store->has('test_dac', 'sentinel'),
      'A new add-operation submission form should clear the session bucket.',
    );
  }

  /**
   * Building the edit form must not clear the session bucket.
   */
  public function testEditFormDoesNotClearSessionBucket(): void {
    $store = $this->seedSessionStore('test_dac');

    $this->buildSubmissionForm('test_dac', 'edit');

    $this->assertTrue(
      $store->has('test_dac', 'sentinel'),
      'An edit-operation submission form must not clear the session bucket.',
    );
  }

  /**
   * Disabling OpenFisca on the webform short-circuits the clear.
   */
  public function testDisabledOpenFiscaWebformDoesNotClearSessionBucket(): void {
    $webform = Webform::load('test_dac');
    $webform->setThirdPartySetting('webform_openfisca', 'fisca_enabled', FALSE);
    $webform->save();

    $store = $this->seedSessionStore('test_dac');

    $this->buildSubmissionForm('test_dac', 'add');

    $this->assertTrue(
      $store->has('test_dac', 'sentinel'),
      'When OpenFisca integration is disabled the bucket must remain intact.',
    );
  }

  /**
   * A non-WebformSubmissionForm form-state short-circuits the hook.
   *
   * Calls the hook directly with a form_state whose form object is not a
   * WebformSubmissionForm. The hook must return without touching the store.
   */
  public function testNonWebformSubmissionFormShortCircuits(): void {
    $store = $this->seedSessionStore('test_dac');

    $form = [];
    $form_state = new FormState();
    // Plant any non-WebformSubmissionForm callable object so the hook's
    // instanceof check fails cleanly. addBuildInfo avoids the
    // "Undefined array key 'callback_object'" warning under PHP 8.4 strict.
    $form_state->addBuildInfo('callback_object', new \stdClass());
    webform_openfisca_webform_submission_form_alter($form, $form_state);

    $this->assertTrue(
      $store->has('test_dac', 'sentinel'),
      'The hook must short-circuit when the form is not a WebformSubmissionForm.',
    );
  }

  /**
   * A rebuild (multi-page / AJAX) must not clear the session bucket.
   */
  public function testRebuildDoesNotClearSessionBucket(): void {
    $store = $this->seedSessionStore('test_dac');

    $webform = Webform::load('test_dac');
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $storage->create(['webform_id' => $webform->id()]);

    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = \Drupal::entityTypeManager()->getFormObject('webform_submission', 'add');
    $form_object->setEntity($submission);

    $form = [];
    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form_state->setRebuild(TRUE);

    webform_openfisca_webform_submission_form_alter($form, $form_state);

    $this->assertTrue(
      $store->has('test_dac', 'sentinel'),
      'A rebuild must not clear the session bucket (multi-page / AJAX flows).',
    );
  }

  /**
   * Editing an existing (non-new) submission must not clear the bucket.
   */
  public function testExistingSubmissionDoesNotClearSessionBucket(): void {
    $store = $this->seedSessionStore('test_dac');

    $webform = Webform::load('test_dac');
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $storage->create(['webform_id' => $webform->id()]);
    $submission->save();
    // The submission now has an id and isNew() returns FALSE.
    $this->assertFalse($submission->isNew());

    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = \Drupal::entityTypeManager()->getFormObject('webform_submission', 'add');
    $form_object->setEntity($submission);

    $form = [];
    $form_state = new FormState();
    $form_state->setFormObject($form_object);

    webform_openfisca_webform_submission_form_alter($form, $form_state);

    $this->assertTrue(
      $store->has('test_dac', 'sentinel'),
      'An already-saved submission must not be treated as a new journey.',
    );
  }

  /**
   * Seed the session store with a sentinel value for the given webform id.
   *
   * @param string $webform_id
   *   The webform id namespace.
   *
   * @return \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface
   *   The session store.
   */
  protected function seedSessionStore(string $webform_id): OpenFiscaSessionStoreInterface {
    /** @var \Drupal\webform_openfisca\SessionStore\OpenFiscaSessionStoreInterface $store */
    $store = \Drupal::service('webform_openfisca.session_store');
    $store->set($webform_id, 'sentinel', 'pre-existing', 3600);
    $this->assertTrue($store->has($webform_id, 'sentinel'));
    return $store;
  }

  /**
   * Build the submission form for a webform under a given operation.
   *
   * @param string $webform_id
   *   The webform id.
   * @param string $operation
   *   The entity form operation (e.g. 'add' or 'edit').
   */
  protected function buildSubmissionForm(string $webform_id, string $operation): void {
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = Webform::load($webform_id);
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $storage->create(['webform_id' => $webform->id()]);
    $this->assertInstanceOf(WebformSubmissionInterface::class, $submission);

    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = \Drupal::service('form_builder');
    $form_object = \Drupal::entityTypeManager()->getFormObject('webform_submission', $operation);
    $form_object->setEntity($submission);
    $form_state = new FormState();
    $form_builder->buildForm($form_object, $form_state);
  }

}
