<?php

namespace Drupal\Tests\webform_openfisca\Functional;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;
use Drupal\webform\Entity\Webform;

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
    'field_ui',
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

  /**
   * Tests the presence of fields in nodes of type 'rac'.
   */
  public function testNodeFields() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/node/add/rac');
    $assert_session->elementExists('css', "#edit-title-0-value");
    $assert_session->elementExists('css', "#edit-field-rules-0-subform-field-rac-element-0-subform-field-variable-0-value");
    $assert_session->elementExists('css', "#edit-field-rules-0-subform-field-rac-element-0-subform-field-value--wrapper");
    $assert_session->elementExists('css', "#edit-field-rules-0-subform-field-redirect-to-0-target-id");
    $assert_session->elementExists('css', "#edit-field-webform-0-target-id");
  }

  /**
   * Tests the webfrom.
   */
  public function testWebfrom() {
    $assert_session = $this->assertSession();
    $this->drupalLogin($this->rootUser);

    // Create a node of type 'page'.
    $node_page = $this->createNode([
      'type' => 'page',
      'title' => 'Test Page Node',
    ]);

    $webform = $this->createWebform([
      'title' => 'Test Webfrom',
      'id' => 'test_webfrom_fisca',
    ]);

    $paragraph_rac_element = Paragraph::create([
      'type' => 'rac_element',
      'field_value' => TRUE,
      'field_variable' => 'aacc_impn',
    ]);
    $paragraph_rac_element->save();

    $paragraph_rac = Paragraph::create([
      'type' => 'rac',
      'field_rac_element' => [
        [
          'target_id' => $paragraph_rac_element->id(),
          'target_revision_id' => $paragraph_rac_element->getRevisionId(),
        ],
      ],
      'field_redirect_to' => [
        'target_id' => $node_page->id(),
      ],
    ]);
    $paragraph_rac->save();

    // Create a node of type 'rac'.
    $node_rac = $this->createNode([
      'type' => 'rac',
      'title' => 'Test Rac Node',
      'field_rules' => [
        [
          'target_id' => $paragraph_rac->id(),
          'target_revision_id' => $paragraph_rac->getRevisionId(),
        ],
      ],
      'field_webform' => [
        'target_id' => $webform->id(),
      ],
    ]);

    $this->drupalGet($node_rac->toUrl());
    $assert_session->elementExists('css', sprintf('h1:contains("%s")', 'Test Rac Node'));

    $webform = Webform::load('test_webfrom_fisca');

    $this->drupalGet('/admin/structure/webform/manage/test_webfrom_fisca/settings');
    $assert_session->responseContains('Third party settings');

    $edit = [
      'third_party_settings[webform_openfisca][fisca_enabled]' => TRUE,
      'third_party_settings[webform_openfisca][fisca_api_endpoint]' => 'https://api.fr.openfisca.org/latest',
      'third_party_settings[webform_openfisca][fisca_return_key]' => 'individus.Xyz.aacc_impn,individus.Abc.aacc_impn',
    ];
    $this->submitForm($edit, 'Save');

    $webform = $this->reloadWebform('test_webfrom_fisca');
    $this->assertEquals(
      TRUE,
      $this->config('webform.webform.test_webfrom_fisca')->get('third_party_settings.webform_openfisca.fisca_enabled')
    );
    $this->assertEquals(
      'https://api.fr.openfisca.org/latest',
      $this->config('webform.webform.test_webfrom_fisca')->get('third_party_settings.webform_openfisca.fisca_api_endpoint')
    );
    $this->assertEquals(
      'individus.Xyz.aacc_impn,individus.Abc.aacc_impn',
      $this->config('webform.webform.test_webfrom_fisca')->get('third_party_settings.webform_openfisca.fisca_return_key')
    );

    $this->drupalGet('/admin/structure/webform/manage/test_webfrom_fisca/handlers/add/openfisca_journey');
    $edit = [
      'handler_id' => 'openfisca_journey_test_handler',
      'label' => 'Openfisca Journey Test Handler',
    ];
    $this->submitForm($edit, 'Save');

    $this->drupalGet('/admin/structure/webform/manage/test_webfrom_fisca/handlers');
    $assert_session->linkByHrefExistsExact('/admin/structure/webform/manage/test_webfrom_fisca/handlers/openfisca_journey_test_handler/edit');

    $this->drupalGet('/admin/structure/webform/manage/test_webfrom_fisca/element/add/textfield');
    $edit = [
      'key' => 'test_field_1',
      'properties[title]' => 'Test Field 1',
      'fisca_entity_key' => 'Abc',
      'fisca_entity_role' => 'families.family.partners.Abc',
      'fisca_entity_role_array' => TRUE,
      'fisca_machine_name' => 'aacc_impn',
    ];
    $this->submitForm($edit, 'Save');

    $this->drupalGet('/admin/structure/webform/manage/test_webfrom_fisca/element/add/textfield');
    $edit = [
      'key' => 'test_field_2',
      'properties[title]' => 'Test Field 2',
      'fisca_entity_key' => 'Xyz',
      'fisca_entity_role' => 'families.family.partners.Xyz',
      'fisca_entity_role_array' => TRUE,
      'fisca_machine_name' => 'aacc_impn',
    ];
    $this->submitForm($edit, 'Save');

    $webform = $this->reloadWebform('test_webfrom_fisca');

  }

}
