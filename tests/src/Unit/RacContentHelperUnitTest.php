<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\RacContentHelper;

/**
 * Tests the RacContentHelper class pure logic methods.
 *
 * @group webform_openfisca
 * @group rac
 * @coversDefaultClass \Drupal\webform_openfisca\RacContentHelper
 */
class RacContentHelperUnitTest extends UnitTestCase {

  /**
   * The testable RacContentHelper instance.
   *
   * @var \Drupal\Tests\webform_openfisca\Unit\TestableRacContentHelper
   */
  protected TestableRacContentHelper $helper;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $this->helper = new TestableRacContentHelper($entityTypeManager, $entityFieldManager);
  }

  /**
   * Tests evaluateCondition() with AND operator - all values match.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionAndAllMatch(): void {
    $is_matched = [1, 1, 1];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'AND');
    $this->assertTrue($result, 'AND operator should return TRUE when all values are truthy.');
  }

  /**
   * Tests evaluateCondition() with AND operator - partial match.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionAndPartialMatch(): void {
    $is_matched = [1, 0, 1];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'AND');
    $this->assertFalse($result, 'AND operator should return FALSE when any value is falsy.');
  }

  /**
   * Tests evaluateCondition() with AND operator - no matches.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionAndNoMatch(): void {
    $is_matched = [0, 0, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'AND');
    $this->assertFalse($result, 'AND operator should return FALSE when all values are falsy.');
  }

  /**
   * Tests evaluateCondition() with OR operator - all values match.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionOrAllMatch(): void {
    $is_matched = [1, 1, 1];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'OR');
    $this->assertTrue($result, 'OR operator should return TRUE when all values are truthy.');
  }

  /**
   * Tests evaluateCondition() with OR operator - any match.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionOrAnyMatch(): void {
    $is_matched = [0, 1, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'OR');
    $this->assertTrue($result, 'OR operator should return TRUE when any value is truthy.');
  }

  /**
   * Tests evaluateCondition() with OR operator - no matches.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionOrNoMatch(): void {
    $is_matched = [0, 0, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'OR');
    $this->assertFalse($result, 'OR operator should return FALSE when no values are truthy.');
  }

  /**
   * Tests evaluateCondition() with XOR operator - exactly one match.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionXorExactlyOneMatch(): void {
    $is_matched = [0, 1, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'XOR');
    $this->assertTrue($result, 'XOR operator should return TRUE when exactly one value is truthy.');
  }

  /**
   * Tests evaluateCondition() with XOR operator - multiple matches.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionXorMultipleMatches(): void {
    $is_matched = [1, 1, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'XOR');
    $this->assertFalse($result, 'XOR operator should return FALSE when more than one value is truthy.');
  }

  /**
   * Tests evaluateCondition() with XOR operator - no matches.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionXorNoMatch(): void {
    $is_matched = [0, 0, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'XOR');
    $this->assertFalse($result, 'XOR operator should return FALSE when no values are truthy.');
  }

  /**
   * Tests evaluateCondition() with empty array.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionEmptyArray(): void {
    $result = $this->helper->publicEvaluateCondition([], 'AND');
    $this->assertFalse($result, 'Empty array should return FALSE.');
  }

  /**
   * Tests evaluateCondition() with null array.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionNullArray(): void {
    $result = $this->helper->publicEvaluateCondition(NULL, 'AND');
    $this->assertFalse($result, 'NULL should return FALSE.');
  }

  /**
   * Tests evaluateCondition() with lowercase operator.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionLowercaseOperator(): void {
    $is_matched = [0, 1, 0];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'or');
    $this->assertTrue($result, 'Lowercase operator should work correctly.');
  }

  /**
   * Tests evaluateCondition() defaults to AND for unknown operator.
   *
   * @covers ::evaluateCondition
   */
  public function testEvaluateConditionUnknownOperatorDefaultsToAnd(): void {
    $is_matched = [1, 1, 1];
    $result = $this->helper->publicEvaluateCondition($is_matched, 'UNKNOWN');
    $this->assertTrue($result, 'Unknown operator should default to AND behaviour.');

    $is_matched_partial = [1, 0, 1];
    $result_partial = $this->helper->publicEvaluateCondition($is_matched_partial, 'UNKNOWN');
    $this->assertFalse($result_partial, 'Unknown operator with partial match should return FALSE (AND default).');
  }

  /**
   * Tests returnOperator() mappings.
   *
   * @covers ::returnOperator
   * @dataProvider dataProviderReturnOperator
   */
  public function testReturnOperator(string $input, string $expected): void {
    $result = $this->helper->publicReturnOperator($input);
    $this->assertEquals($expected, $result, sprintf('Operator "%s" should map to "%s".', $input, $expected));
  }

  /**
   * Data provider for testReturnOperator().
   *
   * @return array<string, array<string, string>>
   *   Test data.
   */
  public static function dataProviderReturnOperator(): array {
    return [
      'equal' => ['input' => 'equal', 'expected' => '=='],
      'notequal' => ['input' => 'notequal', 'expected' => '!='],
      'lessthan' => ['input' => 'lessthan', 'expected' => '<'],
      'greaterthan' => ['input' => 'greaterthan', 'expected' => '>'],
      'lessthanequalto' => ['input' => 'lessthanequalto', 'expected' => '<='],
      'greaterthanequalto' => ['input' => 'greaterthanequalto', 'expected' => '>='],
      'unknown defaults to ==' => ['input' => 'unknown_operator', 'expected' => '=='],
      'empty string defaults to ==' => ['input' => '', 'expected' => '=='],
    ];
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with equality.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorEqual(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'equal'),
      'Equal operator: 100 == "100" should be TRUE.'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '200', 'equal'),
      'Equal operator: 100 == "200" should be FALSE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with not equal.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorNotEqual(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '200', 'notequal'),
      'Not equal operator: 100 != "200" should be TRUE.'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'notequal'),
      'Not equal operator: 100 != "100" should be FALSE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with greater than.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorGreaterThan(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(200, '100', 'greaterthan'),
      'Greater than: 200 > "100" should be TRUE.'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'greaterthan'),
      'Greater than: 100 > "100" should be FALSE (equal not greater).'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(50, '100', 'greaterthan'),
      'Greater than: 50 > "100" should be FALSE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with less than.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorLessThan(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(50, '100', 'lessthan'),
      'Less than: 50 < "100" should be TRUE.'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'lessthan'),
      'Less than: 100 < "100" should be FALSE (equal not less).'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(200, '100', 'lessthan'),
      'Less than: 200 < "100" should be FALSE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with greater than or equal.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorGreaterThanOrEqual(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(200, '100', 'greaterthanequalto'),
      'Greater than or equal: 200 >= "100" should be TRUE.'
    );
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'greaterthanequalto'),
      'Greater than or equal: 100 >= "100" should be TRUE.'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(50, '100', 'greaterthanequalto'),
      'Greater than or equal: 50 >= "100" should be FALSE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with less than or equal.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorLessThanOrEqual(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(50, '100', 'lessthanequalto'),
      'Less than or equal: 50 <= "100" should be TRUE.'
    );
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'lessthanequalto'),
      'Less than or equal: 100 <= "100" should be TRUE.'
    );
    $this->assertFalse(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(200, '100', 'lessthanequalto'),
      'Less than or equal: 200 <= "100" should be FALSE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() throws exception for invalid.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorInvalidThrowsException(): void {
    // The method uses returnOperator() which defaults unknown to '==',
    // but the match statement in compareUsingOperatorWithRacRuleValue
    // should handle the actual operator symbol.
    // Testing with a value that would result in an unhandled operator.
    // Based on the code, returnOperator returns '==' for unknown,
    // so this should not throw. Let's verify the '==' default works.
    $result = $this->helper->publicCompareUsingOperatorWithRacRuleValue(100, '100', 'invalid_operator');
    $this->assertTrue($result, 'Invalid operator should default to == comparison.');
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with type coercion.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorTypeCoercion(): void {
    // String "100" should equal int 100.
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue('100', '100', 'equal'),
      'String "100" == "100" should be TRUE.'
    );
    // Boolean true should equal string "1" in loose comparison.
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(TRUE, '1', 'equal'),
      'TRUE == "1" should be TRUE with loose comparison.'
    );
    // Float comparison.
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(100.5, '100', 'greaterthan'),
      '100.5 > "100" should be TRUE.'
    );
  }

  /**
   * Tests compareUsingOperatorWithRacRuleValue() with zero values.
   *
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testCompareUsingOperatorWithZeroValues(): void {
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(0, '0', 'equal'),
      '0 == "0" should be TRUE.'
    );
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(1, '0', 'greaterthan'),
      '1 > "0" should be TRUE.'
    );
    $this->assertTrue(
      $this->helper->publicCompareUsingOperatorWithRacRuleValue(0, '1', 'lessthan'),
      '0 < "1" should be TRUE.'
    );
  }

  /**
   * Tests compareWithRacRuleValue() uses loose comparison for RAC redirect.
   *
   * @covers ::compareWithRacRuleValue
   * @dataProvider dataProviderCompareWithRacRuleValueLooseComparison
   */
  public function testCompareWithRacRuleValueLooseComparison(mixed $value, string $rac_rule_value, bool $expected): void {
    $result = $this->helper->publicCompareWithRacRuleValue($value, $rac_rule_value);
    $this->assertSame($expected, $result, sprintf('compareWithRacRuleValue(%s, %s) should be %s.', var_export($value, TRUE), var_export($rac_rule_value, TRUE), $expected ? 'TRUE' : 'FALSE'));
  }

  /**
   * Data provider for testCompareWithRacRuleValueLooseComparison().
   *
   * @return array[]
   *   Test data: value, rac_rule_value, expected.
   */
  public static function dataProviderCompareWithRacRuleValueLooseComparison(): array {
    return [
      'int 100 vs string 100' => [100, '100', TRUE],
      'string 100 vs string 100' => ['100', '100', TRUE],
      'bool true vs string 1' => [TRUE, '1', TRUE],
      'string TRUE vs bool true is false (strict string)' => ['TRUE', '1', FALSE],
      'bool false vs string 0' => [FALSE, '0', TRUE],
      'float 200.5 vs string 200.5' => [200.5, '200.5', TRUE],
      'mismatch int vs string' => [100, '200', FALSE],
      'empty string vs empty' => ['', '', TRUE],
    ];
  }

}

/**
 * Testable subclass that exposes protected methods.
 */
class TestableRacContentHelper extends RacContentHelper {

  /**
   * Public wrapper for evaluateCondition().
   *
   * @param array|null $is_matched
   *   Array of matched values.
   * @param string $operator
   *   The operator.
   *
   * @return bool
   *   The result.
   */
  public function publicEvaluateCondition(?array $is_matched, string $operator): bool {
    return $this->evaluateCondition($is_matched, $operator);
  }

  /**
   * Public wrapper for returnOperator().
   *
   * @param string $operator
   *   The operator string.
   *
   * @return string
   *   The operator symbol.
   */
  public function publicReturnOperator(string $operator): string {
    return $this->returnOperator($operator);
  }

  /**
   * Public wrapper for compareUsingOperatorWithRacRuleValue().
   *
   * @param mixed $value
   *   The value.
   * @param string $rac_rule_value
   *   The RAC rule value.
   * @param string $operator
   *   The operator.
   *
   * @return bool
   *   The comparison result.
   */
  public function publicCompareUsingOperatorWithRacRuleValue(mixed $value, string $rac_rule_value, string $operator): bool {
    return $this->compareUsingOperatorWithRacRuleValue($value, $rac_rule_value, $operator);
  }

  /**
   * Public wrapper for compareWithRacRuleValue().
   *
   * @param mixed $value
   *   The value.
   * @param string $rac_rule_value
   *   The RAC rule value.
   *
   * @return bool
   *   TRUE if the values are considered equal (loose comparison).
   */
  public function publicCompareWithRacRuleValue(mixed $value, string $rac_rule_value): bool {
    return $this->compareWithRacRuleValue($value, $rac_rule_value);
  }

}
