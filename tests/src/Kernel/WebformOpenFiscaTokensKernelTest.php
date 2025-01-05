<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

/**
 * Kernel test for hook_tokens.
 *
 * @group webform_openfisca
 * @group webform_openfisca_tokens
 */
class WebformOpenFiscaTokensKernelTest extends BaseKernelTestCase {

  /**
   * {@inheritDoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->setupWebformOpenFiscaTest();
  }

  /**
   * Tests the Webform OpenFisca tokens.
   */
  public function testTokens() : void {
    $token_service = \Drupal::token();
    $text = 'wo_rounded: [webform_openfisca:wo_rounded:123.4567]';
    $this->assertEquals('wo_rounded: 123.46', $token_service->replace($text));

    $text = 'wo_rounded: [webform_openfisca:wo_rounded:some-text]';
    $this->assertEquals('wo_rounded: 0.00', $token_service->replace($text));

    \Drupal::request()->query->set('number', 12345.6789);
    $text = 'wo_rounded: [webform_openfisca:wo_rounded:current-page:query:number]';
    $this->assertEquals('wo_rounded: 12,345.68', $token_service->replace($text));

    $text = 'disability_allowance_max_income: [webform_openfisca:wo_params:test_dac:disability_allowance_max_income]';
    $this->assertEquals('disability_allowance_max_income: 500', $token_service->replace($text));

    $text = 'income_tax_rate: [webform_openfisca:wo_params:test_dac:income_tax_rate]';
    $this->assertEquals('income_tax_rate: 0.14', $token_service->replace($text));

    \Drupal::request()->query->set('period', '2024-01-01');
    $text = 'income_tax_rate: [webform_openfisca:wo_params:test_dac:income_tax_rate]';
    $this->assertEquals('income_tax_rate: 0.15', $token_service->replace($text));

    \Drupal::request()->query->set('period', '2025-01-01');
    $text = 'income_tax_rate: [webform_openfisca:wo_rounded:webform_openfisca:wo_params:test_dac:income_tax_rate]';
    $this->assertEquals('income_tax_rate: 0.16', $token_service->replace($text));

    $text = 'invalid_parameter: [webform_openfisca:wo_params:test_dac:invalid_parameter]';
    $this->assertEquals('invalid_parameter: ', $token_service->replace($text));

    \Drupal::request()->query->set('period', NULL);
    $text = 'mixed parameters: [webform_openfisca:wo_params:no_webform][webform_openfisca:wo_params:test_dac:disability_allowance_max_income] [webform_openfisca:wo_params:] [webform_openfisca:wo_params:test_dac:income_tax_rate] [webform_openfisca:wo_params:invalid_parameter][webform_openfisca:wo_params:no_webform:no_parameter]';
    $this->assertEquals('mixed parameters: 500  0.14 ', $token_service->replace($text));
  }

  /**
   * Tests the Help page.
   */
  public function testHelp() : void {
    $this->enableModules(['help']);
    $this->installConfig(['help']);
    $response = $this->visitInternalPath('/admin/help/webform_openfisca');
    $response_content = $response->getContent();
    $this->assertIsString($response_content);
    $this->assertStringContainsString('[webform_openfisca:wo_rounded:?]', $response_content);
    $this->assertStringContainsString('[webform_openfisca:wo_params:test_dac]', $response_content);
    $this->assertStringContainsString('[webform_openfisca:wo_params:test_dac:disability_allowance_max_income]', $response_content);
    $this->assertStringContainsString('[webform_openfisca:wo_params:test_dac:income_tax_rate]', $response_content);
  }

}
