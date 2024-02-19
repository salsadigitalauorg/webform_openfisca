<?php

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\Form\ConfigForm;

/**
 * Tests the ConfigForm class.
 *
 * @group webform_openfisca
 */
class ConfigFormTest extends UnitTestCase {

  /**
   * Tests the getEditableConfigNames() method.
   */
  public function testGetEditableConfigNames() {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configForm = new ConfigForm($configFactory);

    $configNames = $configForm->getEditableConfigNames();
    $this->assertEquals(['webform_openfisca.settings'], $configNames);
  }

  /**
   * Tests the buildForm() method.
   */
  public function testBuildForm() {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configForm = new ConfigForm($configFactory);

    $form = [];
    $formState = $this->createMock(FormStateInterface::class);

    // Call the buildForm() method.
    $form = $configForm->buildForm($form, $formState);

    // Ensure that the form elements are added correctly.
    $this->assertArrayHasKey('debug', $form);
    $this->assertArrayHasKey('#type', $form['debug']);
    $this->assertEquals('checkbox', $form['debug']['#type']);
    $this->assertArrayHasKey('#title', $form['debug']);
    $this->assertEquals('Debug?.', $form['debug']['#title']);
  }

  /**
   * Tests the submitForm() method.
   */
  public function testSubmitForm() {
    // Mock the ConfigFactoryInterface.
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->expects($this->once())
      ->method('getEditable')
      ->with('webform_openfisca.settings')
      ->willReturnSelf();
    $configFactory->expects($this->once())
      ->method('save');

    // Create an instance of ConfigForm.
    $configForm = new ConfigForm($configFactory);

    // Prepare form data.
    $form = [
      'debug' => [
        '#type' => 'checkbox',
        '#title' => 'Debug?.',
      ],
    ];
    $formState = $this->createMock(FormStateInterface::class);
    $formState->expects($this->once())
      ->method('getValue')
      ->with('debug')
      ->willReturn(TRUE);

    // Call the submitForm() method.
    $configForm->submitForm($form, $formState);
  }

}
