<?php

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\RedirectRule;

/**
 * Tests the RedirectRule class.
 *
 * @group webform_openfisca
 */
class RedirectRuleTest extends UnitTestCase {

  /**
   * Tests creating a RedirectRule object.
   */
  public function testCreateRedirectRule() {
    // Prepare the redirect rule data.
    $sourceElement = 'field_webform';
    $targetElement = 'field_redirect_to';
    $sourceValue = 'Some Value';
    $targetValue = '/redirect/path';

    // Create a RedirectRule object.
    $redirectRule = new RedirectRule($sourceElement, $targetElement, $sourceValue, $targetValue);

    // Ensure that the RedirectRule object is created successfully.
    $this->assertInstanceOf(RedirectRule::class, $redirectRule);
    // Ensure that the RedirectRule object has the correct properties.
    $this->assertEquals($sourceElement, $redirectRule->getSourceElement());
    $this->assertEquals($targetElement, $redirectRule->getTargetElement());
    $this->assertEquals($sourceValue, $redirectRule->getSourceValue());
    $this->assertEquals($targetValue, $redirectRule->getTargetValue());
  }

}
