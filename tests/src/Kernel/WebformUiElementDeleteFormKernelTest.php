<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Drupal\webform_openfisca\Form\WebformUiElementDeleteForm;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;

/**
 * Kernel test for WebformUiElementDeleteForm class.
 *
 * @group webform_openfisca
 * @group webform_alter
 * @coversDefaultClass \Drupal\webform_openfisca\Form\WebformUiElementDeleteForm
 */
class WebformUiElementDeleteFormKernelTest extends BaseKernelTestCase {

  use UserCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();
  }

  /**
   * Test the WebformUiElementDeleteForm::submitForm() method.
   */
  public function testWebformUiElementDeleteForm(): void {
    $webform = Webform::load('test_dac');
    $this->assertInstanceOf(WebformInterface::class, $webform);

    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertNotFalse($openfisca_settings->getFieldMapping('has_disability'));
    $this->assertNotFalse($openfisca_settings->getVariable('has_disability'));
    $this->assertTrue($openfisca_settings->fieldHasImmediateResponse('has_disability'));

    // Build and submit the form.
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = $this->container->get('form_builder');
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$webform, 'has_disability']);
    $form_builder->buildForm(WebformUiElementDeleteForm::class, $form_state);
    $form_builder->submitForm(WebformUiElementDeleteForm::class, $form_state);

    // Reload and check if the element is removed from OpenFisca settings.
    $webform = Webform::load('test_dac');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertFalse($openfisca_settings->getFieldMapping('has_disability'));
    $this->assertFalse($openfisca_settings->getVariable('has_disability'));
    $this->assertFalse($openfisca_settings->fieldHasImmediateResponse('has_disability'));
  }

}
