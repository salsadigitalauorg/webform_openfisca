<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\OpenFisca\Helper;

/**
 * Tests the OpenFisca Helper class.
 *
 * @group webform_openfisca
 */
class OpenFiscaHelperUnitTest extends UnitTestCase {

  /**
   * Tests the expandCsvString() method of OpenFisca Helper.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Helper::expandCsvString
   * @dataProvider dataProviderExpandCsvString
   */
  public function testExpandCsvString(string $input, array $expected, bool $remove_empty): void {
    $this->assertEquals($expected, Helper::expandCsvString($input, $remove_empty));
  }

  /**
   * Provides data for testing the expandCsvString() method.
   */
  public static function dataProviderExpandCsvString(): array {
    return [
      ['', [], TRUE],
      ['a', ['a'], TRUE],
      ['a,b', ['a', 'b'], TRUE],
      ['a, b, ', ['a', 'b'], TRUE],
      [', b, ,c', ['b', 'c'], TRUE],
      [',, ,', [], TRUE],
      ['a, b, ', ['a', 'b', ''], FALSE],
      [',, ,', ['', '', '', ''], FALSE],
    ];
  }

  /**
   * Tests the combineOpenFiscaFieldMapping() method of OpenFisca Helper.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Helper::combineOpenFiscaFieldMapping
   */
  public function testCombineOpenFiscaFieldMapping(): void {
    $this->assertEquals('person.PersonA.age', Helper::combineOpenFiscaFieldMapping('person', 'PersonA', 'age'));
  }

  /**
   * Tests the parseOpenFiscaFieldMapping() method of OpenFisca Helper.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Helper::parseOpenFiscaFieldMapping
   * @dataProvider dataProviderParseOpenFiscaFieldMapping
   */
  public function testParseOpenFiscaFieldMapping(string $field_mapping, string $variable, array $parse) {
    $group_entity = NULL;
    $entity = NULL;
    $parents = [];
    $path = [];
    $this->assertEquals($variable, Helper::parseOpenFiscaFieldMapping($field_mapping, $group_entity, $entity, $path, $parents));
    $this->assertEquals($group_entity, $parse['group_entity']);
    $this->assertEquals($entity, $parse['entity']);
    $this->assertEquals($parents, $parse['parents']);
    $this->assertEquals($path, $parse['path']);
  }

  /**
   * Provides data for testing the parseOpenFiscaFieldMapping() method.
   */
  public static function dataProviderParseOpenFiscaFieldMapping(): array {
    return [
      ['persons.PersonA.salary', 'salary',
        [
          'group_entity' => 'persons',
          'entity' => 'PersonA',
          'parents' => ['persons', 'PersonA'],
          'path' => ['persons', 'PersonA', 'salary'],
        ],
      ],
      ['persons.PersonA', 'PersonA',
        [
          'group_entity' => 'persons',
          'entity' => NULL,
          'parents' => ['persons'],
          'path' => ['persons', 'PersonA'],
        ],
      ],
      ['persons', 'persons',
        [
          'group_entity' => NULL,
          'entity' => NULL,
          'parents' => [],
          'path' => ['persons'],
        ],
      ],
      ['', '',
        [
          'group_entity' => NULL,
          'entity' => NULL,
          'parents' => [],
          'path' => [''],
        ],
      ],
    ];
  }

}
