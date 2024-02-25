<?php

namespace Drupal\Tests\webform_openfisca\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Test case for checking fields in content types and paragraph types.
 */
class WebformOpenFiscaFunctionalTest extends WebformBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'webform_openfisca', 
    'paragraphs', 
    'webform', 
    'token', 
    'node', 
    'path', 
    'menu_ui', 
    'text',
    'field_ui'
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An administrative user to configure the test environment.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Login root user.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests the webform (entity reference) field.
   */
  public function testWebformField() {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->rootUser);

    /* ********************************************************************** */

    // Check that webform select menu is visible.
    $this->drupalGet('/node/add/rac');
    $this->assertNoCssSelect('#edit-field-webform-0-target-id optgroup');
    $assert_session->optionExists('edit-field-webform-0-target-id', 'contact');

    // Add category to 'contact' webform.
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::load('contact');
    $webform->set('categories', ['{Some category}']);
    $webform->save();

    // Check that webform select menu included optgroup.
    $this->drupalGet('/node/add/rac');
    $this->assertCssSelect('#edit-field-webform-0-target-id optgroup[label="{Some category}"]');

    // Create a second webform.
    $webform_2 = $this->createWebform();

    // Check that webform 2 is included in the select menu.
    $this->drupalGet('/node/add/rac');
    $assert_session->optionExists('edit-field-webform-0-target-id', 'contact');
    $assert_session->optionExists('edit-field-webform-0-target-id', $webform_2->id());

    // Limit the webform select menu to only the contact form.
    $this->drupalGet('/admin/structure/types/manage/rac/form-display');
    $this->drupalGet('/admin/structure/types/manage/rac/form-display');
    $this->submitForm([], 'field_webform_settings_edit');
    $this->submitForm(['fields[field_webform][settings_edit_form][settings][webforms][]' => ['contact']], 'Save');

    // Check that webform 2 is NOT included in the select menu.
    $this->drupalGet('/node/add/rac');
    $assert_session->optionExists('edit-field-webform-0-target-id', 'contact');
    $assert_session->optionNotExists('edit-field-webform-0-target-id', $webform_2->id());
  }

}
