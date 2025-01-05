<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;
use Drupal\webform_ui\Form\WebformUiElementEditForm;

/**
 * Kernel test for WebformUiElementFormAlter class.
 *
 * @group webform_openfisca
 * @group webform_alter
 * @coversDefaultClass \Drupal\webform_openfisca\WebformUiElementFormAlter
 */
class WebformUiElementFormAlterKernelTest extends BaseKernelTestCase {

  use UserCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();
  }

  /**
   * Test the WebformUiElementFormAlter class.
   */
  public function testWebformUiElementFormAlter(): void {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');

    // Test the No API webform.
    $webform = Webform::load('test_no_api');
    $this->assertInstanceOf(WebformInterface::class, $webform);
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $element_edit_form = $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->assertArrayNotHasKey('fisca_machine_name', $element_edit_form);
    $this->assertArrayNotHasKey('fisca_entity_key', $element_edit_form);
    $this->assertArrayNotHasKey('fisca_entity_role', $element_edit_form);
    $this->assertArrayNotHasKey('fisca_entity_role_array', $element_edit_form);
    $this->assertArrayNotHasKey('fisca_immediate_response', $element_edit_form);

    // Test the Invalid API webform.
    $webform = Webform::load('test_invalid_api');
    $this->assertInstanceOf(WebformInterface::class, $webform);
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $element_edit_form = $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->assertArrayHasKey('fisca_machine_name', $element_edit_form);
    $this->assertEquals('select', $element_edit_form['fisca_machine_name']['#type']);
    $this->assertEquals(['_nil', 'name_key', 'period'], array_keys($element_edit_form['fisca_machine_name']['#options']));

    $this->assertArrayHasKey('fisca_entity_key', $element_edit_form);
    $this->assertArrayHasKey('fisca_entity_role', $element_edit_form);
    $this->assertArrayHasKey('fisca_entity_role_array', $element_edit_form);
    $this->assertArrayHasKey('fisca_immediate_response', $element_edit_form);

    // Test the DAC form.
    $webform = Webform::load('test_dac');
    $this->assertInstanceOf(WebformInterface::class, $webform);

    // Build and submit the form.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $element_edit_form = $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);

    $this->assertArrayHasKey('fisca_machine_name', $element_edit_form);
    $this->assertEquals('select', $element_edit_form['fisca_machine_name']['#type']);
    $this->assertArrayHasKey('_nil', $element_edit_form['fisca_machine_name']['#options']);
    $this->assertArrayHasKey('name_key', $element_edit_form['fisca_machine_name']['#options']);
    $this->assertArrayHasKey('period', $element_edit_form['fisca_machine_name']['#options']);

    $this->assertArrayHasKey('fisca_entity_key', $element_edit_form);
    $this->assertEquals('textfield', $element_edit_form['fisca_entity_key']['#type']);

    $this->assertArrayHasKey('fisca_entity_role', $element_edit_form);
    $this->assertEquals('textfield', $element_edit_form['fisca_entity_role']['#type']);

    $this->assertArrayHasKey('fisca_entity_role_array', $element_edit_form);
    $this->assertEquals('checkbox', $element_edit_form['fisca_entity_role_array']['#type']);

    $this->assertArrayHasKey('fisca_immediate_response', $element_edit_form);
    $this->assertEquals('checkbox', $element_edit_form['fisca_immediate_response']['#type']);

    $this->simulateWebformStates($form_state);
    // Disable Immediate response - use NULL instead of 0 for checkbox.
    $form_state->setValue('fisca_immediate_response', NULL);
    $form_builder->submitForm(WebformUiElementEditForm::class, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertFalse($openfisca_settings->fieldHasImmediateResponse('has_disability'));

    // Re-enable the immediate response.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->simulateWebformStates($form_state);
    $form_state->setValue('fisca_immediate_response', '1');
    $form_builder->submitForm(WebformUiElementEditForm::class, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertTrue($openfisca_settings->fieldHasImmediateResponse('has_disability'));
    $this->assertEquals('persons.personA.has_disability', $openfisca_settings->getFieldMapping('has_disability'));
    $this->assertFalse($openfisca_settings->getVariable('income_tax'));

    // Map the 'has_disability' field to the OpenFisca variable 'income_tax'.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->simulateWebformStates($form_state);
    $form_state->setValue('fisca_machine_name', 'income_tax');
    $form_state->setValue('fisca_entity_role', 'families.family.children');
    $form_state->setValue('fisca_entity_role_array', '1');
    $form_builder->submitForm(WebformUiElementEditForm::class, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertEquals('persons.personA.income_tax', $openfisca_settings->getFieldMapping('has_disability'));
    $this->assertIsArray($openfisca_settings->getEntityRole('has_disability'));
    $this->assertEquals(['role' => 'families.family.children', 'is_array' => TRUE], $openfisca_settings->getEntityRole('has_disability'));
    $this->assertIsArray($openfisca_settings->getVariable('income_tax'));
    // @todo Remove the old variable when the field mapping is changed.
    $this->assertIsArray($openfisca_settings->getVariable('has_disability'));

    // Remove the entity role.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->simulateWebformStates($form_state);
    $form_state->setValue('fisca_entity_role', '');
    $form_builder->submitForm(WebformUiElementEditForm::class, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertFalse($openfisca_settings->getEntityRole('has_disability'));

    // Unmap the 'has_disability' field.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->simulateWebformStates($form_state);
    $form_state->setValue('fisca_machine_name', '_nil');
    $form_builder->submitForm(WebformUiElementEditForm::class, $form_state);
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertFalse($openfisca_settings->getFieldMapping('has_disability'));
    $this->assertFalse($openfisca_settings->getEntityRole('has_disability'));
    $this->assertFalse($openfisca_settings->getVariable('income_tax'));

    // Build and submit a different element without OpenFisca mapping.
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'requires_ongoing_support']);
    $form_builder->buildForm(WebformUiElementEditForm::class, $form_state);
    $this->simulateWebformStates($form_state);
    $form_builder->submitForm(WebformUiElementEditForm::class, $form_state);
  }

  /**
   * Simulate the Webform States prior to submitting the form.
   *
   * @param \Drupal\Core\Form\FormState $form_state
   *   The form state.
   */
  protected function simulateWebformStates(FormState $form_state): void {
    $prop_states = $form_state->getValue(['properties', 'states', 'states']);
    foreach ($prop_states as &$state) {
      if (array_key_exists('selector', $state)) {
        $state['trigger'] = 'filled';
        $state['value'] = '';
      }
    }
    unset($state);
    $form_state->setValue(['properties', 'states', 'states'], $prop_states);
  }

}
