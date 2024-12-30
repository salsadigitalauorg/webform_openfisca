<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\webform_openfisca\WebformOpenFiscaSettings;

/**
 * Tests the WebformOpenFiscaSettings class.
 *
 * @group webform_openfisca
 * @group webform_openfisca_settings
 * @coversDefaultClass \Drupal\webform_openfisca\WebformOpenFiscaSettings
 */
class WebformOpenFiscaSettingsUnitTest extends OpenFiscaHelperUnitTest {

  /**
   * Tests the WebformOpenFiscaSettings class.
   */
  public function testWebformOpenFiscaSettings(): void {
    $webform = $this->mockWebform('../modules/webform_openfisca_test/config/install/webform.webform.test_dac.yml');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertEquals('test_dac', $openfisca_settings->getWebformId());
    $this->assertTrue($openfisca_settings->isEnabled());
    $this->assertFalse($openfisca_settings->isDebugEnabled());
    $this->assertTrue($openfisca_settings->isLoggingEnabled());
    $this->assertTrue($openfisca_settings->hasApiEndpoint());
    $this->assertEquals('https://api.openfisca.test/', $openfisca_settings->getApiEndpoint());
    $this->assertTrue($openfisca_settings->hasApiAuthorizationHeader());
    $this->assertEquals('Token WEBFORM-OPENFISCA-TEST-TOKEN', $openfisca_settings->getApiAuthorizationHeader());

    $json = Json::decode($openfisca_settings->getJsonVariables());
    $this->assertArrayHasKey('salary', $json);
    $this->assertArrayHasKey('has_disability', $json);
    $this->assertArrayHasKey('requires_ongoing_support', $json);
    $variables = $openfisca_settings->getVariables();
    $this->assertArrayHasKey('salary', $variables);
    $this->assertArrayHasKey('has_disability', $variables);
    $this->assertArrayHasKey('requires_ongoing_support', $variables);
    $variable = $openfisca_settings->getVariable('salary');
    $this->assertIsArray($variable);
    $this->assertArrayHasKey('defaultValue', $variable);
    $this->assertEquals(0, $variable['defaultValue']);
    $this->assertArrayHasKey('definitionPeriod', $variable);
    $this->assertEquals('DAY', $variable['definitionPeriod']);
    $this->assertFalse($openfisca_settings->getVariable('non-existent-variable'));

    $json = Json::decode($openfisca_settings->getJsonParameters());
    $this->assertArrayHasKey('disability_allowance_max_income', $json);
    $this->assertArrayHasKey('income_tax_rate', $json);
    $parameters = $openfisca_settings->getParameters();
    $this->assertArrayHasKey('disability_allowance_max_income', $parameters);
    $this->assertArrayHasKey('income_tax_rate', $parameters);
    $parameter = $openfisca_settings->getParameter('income_tax_rate');
    $this->assertIsArray($parameter);
    $this->assertArrayHasKey('id', $parameter);
    $this->assertEquals('income_tax_rate', $parameter['id']);
    $this->assertFalse($openfisca_settings->getParameter('non-existent-parameter'));

    $this->assertEquals('disability_allowance_max_income,income_tax_rate', $openfisca_settings->getPlainParameterTokens());
    $this->assertEquals(['disability_allowance_max_income', 'income_tax_rate'], $openfisca_settings->getParameterTokens());

    $json = Json::decode($openfisca_settings->getJsonFieldMappings());
    $this->assertArrayHasKey('has_disability', $json);
    $this->assertArrayHasKey('requires_ongoing_support', $json);
    $field_mappings = $openfisca_settings->getFieldMappings();
    $this->assertArrayHasKey('has_disability', $field_mappings);
    $this->assertArrayHasKey('requires_ongoing_support', $field_mappings);
    $this->assertEquals('persons.personA.has_disability', $openfisca_settings->getFieldMapping('has_disability'));
    $this->assertEquals('persons.personA.requires_ongoing_support', $openfisca_settings->getFieldMapping('requires_ongoing_support'));
    $this->assertFalse($openfisca_settings->getFieldMapping('non-existent-mapping'));

    $this->assertEquals('persons.personA.disability_allowance_eligible,persons.personA.disability_allowance_benefit,persons.personA.monthly_income_exceeds_limit', $openfisca_settings->getPlainReturnKeys());
    $this->assertEquals(['persons.personA.disability_allowance_eligible', 'persons.personA.disability_allowance_benefit', 'persons.personA.monthly_income_exceeds_limit'], $openfisca_settings->getReturnKeys());

    $json = Json::decode($openfisca_settings->getJsonEntityRoles());
    $this->assertEmpty($json);
    $this->assertEmpty($openfisca_settings->getEntityRoles());
    $this->assertFalse($openfisca_settings->getEntityRole('non-existent-role'));

    $json = Json::decode($openfisca_settings->getJsonImmediateResponseMapping());
    $this->assertArrayHasKey('has_disability', $json);
    $this->assertArrayHasKey('aus_citizen_or_permanent_resident', $json);
    $field_mappings = $openfisca_settings->getImmediateResponseMapping();
    $this->assertArrayHasKey('has_disability', $field_mappings);
    $this->assertArrayHasKey('aus_citizen_or_permanent_resident', $field_mappings);
    $this->assertTrue($openfisca_settings->fieldHasImmediateResponse('has_disability'));
    $this->assertTrue($openfisca_settings->fieldHasImmediateResponse('aus_citizen_or_permanent_resident'));
    $this->assertFalse($openfisca_settings->fieldHasImmediateResponse('non-existent-mapping'));

    $this->assertTrue($openfisca_settings->hasImmediateResponseAjaxIndicator());

    $this->assertEquals('persons.personA.exit', $openfisca_settings->getPlainImmediateExitKeys());
    $this->assertEquals(['persons.personA.exit'], $openfisca_settings->getImmediateExitKeys());

    $this->assertEquals('2024-12-31', $openfisca_settings->formatVariablePeriod('has_disability', '2024-12-31'));
    $this->assertEquals('', $openfisca_settings->formatVariablePeriod('non-existent-mapping', '2024-12-31'));

    $new_openfisca_settings = $openfisca_settings->removeWebformElementMappings('has_disability');
    $this->assertFalse($new_openfisca_settings->getFieldMapping('has_disability'));
    $this->assertFalse($new_openfisca_settings->getVariable('has_disability'));
    $this->assertFalse($new_openfisca_settings->fieldHasImmediateResponse('has_disability'));

    // Test the getOpenFiscaClient() method.
    $factory = $this->mockClientFactory();
    $client = $openfisca_settings->getOpenFiscaClient($factory, []);
    $this->assertEquals('https://api.openfisca.test', $client->getBaseUri());

    $new_openfisca_settings = $openfisca_settings->updateApiEndpoint('https://v2.openfisca.test/api');
    $this->assertEquals('https://v2.openfisca.test/api', $new_openfisca_settings->getApiEndpoint());
    $client = $new_openfisca_settings->getOpenFiscaClient($factory, []);
    $this->assertEquals('https://v2.openfisca.test/api', $client->getBaseUri());

    // Test the No API webform.
    $webform = $this->mockWebform('../modules/webform_openfisca_test/config/install/webform.webform.test_no_api.yml');
    $openfisca_settings = WebformOpenFiscaSettings::load($webform);
    $this->assertEquals('test_no_api', $openfisca_settings->getWebformId());
    $this->assertFalse($openfisca_settings->hasApiEndpoint());
    $this->assertFalse($openfisca_settings->hasApiAuthorizationHeader());
    $client = $openfisca_settings->getOpenFiscaClient($factory);
    $this->assertEmpty($client->getBaseUri());
  }

}
