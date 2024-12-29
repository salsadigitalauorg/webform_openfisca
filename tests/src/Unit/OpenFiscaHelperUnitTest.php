<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\webform_openfisca\OpenFisca\Helper;

/**
 * Tests the OpenFisca Helper class.
 *
 * @group webform_openfisca
 * @coversDefaultClass \Drupal\webform_openfisca\OpenFisca\Helper
 */
class OpenFiscaHelperUnitTest extends UnitTestCase {

  /**
   * {@inheritDoc}
   */
  public function setUp() : void {
    parent::setUp();

    \Drupal::unsetContainer();
    $container = new ContainerBuilder();

    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn('en');
    $language_manager = $this->prophesize(LanguageManagerInterface::class);
    $language_manager->getCurrentLanguage()->willReturn($language);

    $container->set('language_manager', $language_manager->reveal());
    \Drupal::setContainer($container);
  }

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
  public function testParseOpenFiscaFieldMapping(string $field_mapping, string $variable, array $parse) : void {
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
      ['persons.PersonA.salary.now', 'now',
       [
         'group_entity' => 'persons',
         'entity' => 'PersonA',
         'parents' => ['persons', 'PersonA', 'salary'],
         'path' => ['persons', 'PersonA', 'salary', 'now'],
       ],
      ],
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

  /**
   * Tests the formatPeriod() method of OpenFisca Helper.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Helper::formatPeriod
   * @dataProvider dataProviderFormatPeriod
   */
  public function testFormatPeriod(string $period_format, string $period, string $expected): void {
    $this->assertEquals($expected, Helper::formatPeriod($period_format, $period));
  }

  /**
   * Provides data for testing the formatPeriod() method.
   */
  public static function dataProviderFormatPeriod(): array {
    return [
      ['DAY', '2022-11-02', '2022-11-02'],
      ['WEEK', '2022-11-02', '2022-W44'],
      ['WEEKDAY', '2022-11-02', '2022-W44-3'],
      ['MONTH', '2022-11-02', '2022-11'],
      ['YEAR', '2022-11-02', '2022'],
      ['ETERNITY', '2022-11-02', 'ETERNITY'],
      ['', '2022-11-02', ''],
      ['CENTURY', '2022-11-02', ''],
    ];
  }

  /**
   * Tests the jsonEncodePretty() method of OpenFisca Helper.
   *
   * @covers \Drupal\webform_openfisca\OpenFisca\Helper::jsonEncodePretty
   */
  public function testJsonEncodePretty(): void {
    $this->assertEquals('[]', Helper::jsonEncodePretty([]));
    $this->assertEquals('123', Helper::jsonEncodePretty(123));

    $json = <<<JSON
{
    "data": "ABC"
}
JSON;
    $this->assertEquals($json, Helper::jsonEncodePretty(['data' => 'ABC']));

    $json = <<<JSON
{
    "data": "ABC",
    "id": 1
}
JSON;
    $this->assertEquals($json, Helper::jsonEncodePretty([
      'data' => 'ABC',
      'id' => 1,
    ]));

    $this->assertEquals('', Helper::jsonEncodePretty(fopen('php://memory', 'rb+')));
  }

}
