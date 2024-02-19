<?php

namespace Drupal\Tests\webform_openfisca\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the usage and configuration of the Webform OpenFisca Integration module.
 *
 * @group webform_openfisca
 */
class WebformOpenFiscaFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['webform', 'webform_openfisca'];

  /**
   * Tests the usage and configuration of the module.
   */
  public function testUsageAndConfiguration() {
    // Create a webform with no fields.
    $webform = $this->createWebform('test_module', 'Test Module');

    // Navigate to the Webform settings page.
    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/settings');

    // Verify that the settings page loads successfully.
    $this->assertSession()->statusCodeEquals(200);

    // Verify that the OpenFisca configuration section exists.
    $this->assertSession()->elementExists('css', '#edit-third-party-settings-webform-openfisca');

    // Verify the existence of specific form elements within the OpenFisca configuration section.
    $this->assertSession()->fieldExists('third_party_settings[webform_openfisca][fisca_enabled]');
    $this->assertSession()->fieldExists('third_party_settings[webform_openfisca][fisca_api_endpoint]');
    $this->assertSession()->fieldExists('third_party_settings[webform_openfisca][fisca_return_key]');
    $this->assertSession()->fieldExists('third_party_settings[webform_openfisca][fisca_field_mappings]');
    $this->assertSession()->fieldExists('third_party_settings[webform_openfisca][fisca_variables]');
    $this->assertSession()->fieldExists('third_party_settings[webform_openfisca][fisca_entity_roles]');

    // Fill the "OpenFisca API endpoint" field with the correct value.
    $edit = [
        'third_party_settings[webform_openfisca][fisca_api_endpoint]' => 'https://api.fr.openfisca.org/latest',
        'third_party_settings[webform_openfisca][fisca_enabled]' => TRUE,
    ];

    $this->submitForm($edit, 'Save configuration');

    // Check if "Enable OpenFisca RaC integration" is checked after saving the settings.
    $this->assertSession()->checkboxChecked('third_party_settings[webform_openfisca][fisca_enabled]');

    // Navigate to the Webform handlers page.
    $this->drupalGet('/admin/structure/webform/manage/' . $webform->id() . '/handlers');

    // Verify that the handlers page loads successfully.
    $this->assertSession()->statusCodeEquals(200);

    // Add the "Openfisca Journey handler" to the webform handlers.
    $this->clickLink('Add handler');
    $this->assertSession()->pageTextContains('Openfisca Journey handler');
    $this->assertSession()->elementExists('css', 'input[name="handler_config[plugin][enabled]"][value="1"]');

    // Enable the "Openfisca Journey handler" handler.
    $edit = [
    'handler_config[plugin][enabled]' => TRUE,
    ];
    $this->submitForm($edit, 'Save');

    // TODO
  }

  /**
   * Helper function to create a webform.
   *
   * @param string $id
   *   The machine name of the webform.
   * @param string $label
   *   The label of the webform.
   *
   * @return \Drupal\webform\Entity\Webform
   *   The created webform entity.
   */
  protected function createWebform($id, $label) {
    $webform = $this->container->get('entity_type.manager')->getStorage('webform')->create([
      'id' => $id,
      'label' => $label,
    ]);
    $webform->save();
    return $webform;
  }

}
