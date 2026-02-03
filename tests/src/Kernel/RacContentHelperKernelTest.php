<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Kernel test for the RacContentHelper service.
 *
 * @group webform_openfisca
 * @group rac
 * @coversDefaultClass \Drupal\webform_openfisca\RacContentHelper
 */
class RacContentHelperKernelTest extends BaseKernelTestCase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpRacContentModules();
  }

  /**
   * Test the RacContentHelper service.
   */
  public function testRacContentHelper(): void {
    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // No RAC node for webform 'test'.
    $redirect = $helper->findRacRedirectForWebform('test', []);
    $this->assertNull($redirect);

    $page1 = $this->createTestPage('Test page 1');
    $page2 = $this->createTestPage('Test page 2');

    // Page 3 is not a node.
    $page3 = EntityTest::create(['id' => 123]);
    $page3->save();

    $this->createRacContent('test', 'Test RAC - empty');
    $this->createRacContent('test', 'Test RAC', [
      [],
      [
        'redirect' => [],
        'rules' => [],
      ],
      [
        'redirect' => $page1,
        'rules' => [],
      ],
      [
        'redirect' => NULL,
        'rules' => ['page1_var1' => 'value1'],
      ],
      [
        'redirect' => $page1,
        'rules' => [
          'page1_var1' => 'value1',
          'page1_var2' => 100,
          'page1_var3' => TRUE,
        ],
      ],
      [
        'redirect' => $page2,
        'rules' => [
          'page2_var1' => 'value2',
          'page2_var2' => '200.50',
          'page2_var3' => '0',
          'page2_var4' => 1,
        ],
      ],
      [
        'redirect' => $page3,
        'rules' => [
          'page3_var1' => 'value3',
          'page3_var2' => 300,
          'page3_var3' => FALSE,
        ],
      ],
    ]);

    // All values must match a redirect rule.
    $redirect = $helper->findRacRedirectForWebform('test', [
      'page1_var1' => 'value1',
    ]);
    $this->assertNull($redirect, sprintf('Redirect %s found for [page1_var1=>value1]', $redirect));
    $redirect = $helper->findRacRedirectForWebform('test', [
      'page1_var1' => 'value1',
      'page1_var2' => 100,
    ]);
    $this->assertNull($redirect, sprintf('Redirect %s found for [page1_var1=>value1] and [page1_var2=>100]', $redirect));

    // Compare: (string) '100' and (int) 100 are equal.
    $redirect = $helper->findRacRedirectForWebform('test', [
      'page1_var1' => 'value1',
      'page1_var2' => 100,
      'page1_var3' => TRUE,
    ]);
    $this->assertEquals($page1->toUrl()->toString(), $redirect, 'Redirect not found for [page1_var1=>value1] and [page1_var2=>100] and [page1_var3=>TRUE]');

    // Compare: (string) 'TRUE' and (bool) TRUE are not equal.
    $redirect = $helper->findRacRedirectForWebform('test', [
      'page1_var1' => 'value1',
      'page1_var2' => '100',
      'page1_var3' => 'TRUE',
    ]);
    $this->assertNull($redirect, sprintf('Redirect %s found for [page1_var1=>value1] and [page1_var2=>100] and [page1_var3=>"TRUE"]', $redirect));

    // Compare: (string) '200.50' and (float) 200.50 are equal.
    // Compare: (string) '0' and (bool) FALSE are equal.
    // Compare: (string) '1' and (bool) TRUE are equal.
    $redirect = $helper->findRacRedirectForWebform('test', [
      'page2_var0' => 123,
      'page2_var1' => 'value2',
      'page2_var2' => 200.50,
      'page2_var3' => FALSE,
      'page2_var4' => TRUE,
      'page2_var5' => 'true',
      'page2_var6' => 'false',
    ]);
    $this->assertEquals($page2->toUrl()->toString(), $redirect, 'Redirect not found for [page2_var1=>value2] and [page2_var2=>200.50] and [page2_var3=>FALSE]');

    $redirect = $helper->findRacRedirectForWebform('test', [
      'page3_var1' => 'value3',
      'page3_var2' => '300',
      'page3_var3' => FALSE,
    ]);
    $this->assertNull($redirect, sprintf('Redirect %s found for [page3_var1=>value3] and [page3_var2=>300] and [page3_var3=>FALSE]', $redirect));
  }

  /**
   * Test findVisibleBlocksForWebform() with no blocks.
   *
   * @covers ::findVisibleBlocksForWebform
   */
  public function testFindVisibleBlocksForWebformNoBlocks(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    $visible_blocks = $helper->findVisibleBlocksForWebform('nonexistent_webform', []);
    $this->assertEquals([], $visible_blocks, 'Should return empty array when no blocks reference the webform.');
  }

  /**
   * Test findVisibleBlocksForWebform() with block having no rules.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::findRacBlockContentForWebform
   */
  public function testFindVisibleBlocksForWebformNoRulesAlwaysVisible(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with no rules.
    $block = $this->createRacBlockContent('test_webform', 'Block No Rules', []);

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', []);
    $this->assertContains($block->id(), $visible_blocks, 'Block without rules should always be visible.');
  }

  /**
   * Test findVisibleBlocksForWebform() with AND operator - all match.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   */
  public function testFindVisibleBlocksAndOperatorAllMatch(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with AND rules that should all match.
    $block = $this->createRacBlockContent('test_webform', 'Block AND Rules', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'var1', 'value' => '100', 'operator' => 'equal'],
          ['variable' => 'var2', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    $matching_values = [
      'var1' => 100,
      'var2' => 200,
    ];

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertContains($block->id(), $visible_blocks, 'Block should be visible when all AND conditions match.');
  }

  /**
   * Test findVisibleBlocksForWebform() with AND operator - partial match.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   */
  public function testFindVisibleBlocksAndOperatorPartialMatch(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with AND rules where only some match.
    $block = $this->createRacBlockContent('test_webform', 'Block AND Partial', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'var1', 'value' => '100', 'operator' => 'equal'],
          ['variable' => 'var2', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    $matching_values = [
      'var1' => 100,
      'var2' => 999,  // Does not match.
    ];

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertNotContains($block->id(), $visible_blocks, 'Block should NOT be visible when only some AND conditions match.');
  }

  /**
   * Test findVisibleBlocksForWebform() with OR operator.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   * @covers ::evaluateCondition
   */
  public function testFindVisibleBlocksOrOperator(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with OR rules where only one matches.
    $block = $this->createRacBlockContent('test_webform', 'Block OR Rules', [
      [
        'operator' => 'OR',
        'rules' => [
          ['variable' => 'var1', 'value' => '100', 'operator' => 'equal'],
          ['variable' => 'var2', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    $matching_values = [
      'var1' => 100,  // Matches.
      'var2' => 999,  // Does not match.
    ];

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertContains($block->id(), $visible_blocks, 'Block should be visible when any OR condition matches.');
  }

  /**
   * Test findVisibleBlocksForWebform() with XOR operator.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   * @covers ::evaluateCondition
   */
  public function testFindVisibleBlocksXorOperator(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with XOR rules - exactly one should match.
    $block = $this->createRacBlockContent('test_webform', 'Block XOR Rules', [
      [
        'operator' => 'XOR',
        'rules' => [
          ['variable' => 'var1', 'value' => '100', 'operator' => 'equal'],
          ['variable' => 'var2', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    // Only one matches - should be visible.
    $matching_values_one = [
      'var1' => 100,  // Matches.
      'var2' => 999,  // Does not match.
    ];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values_one);
    $this->assertContains($block->id(), $visible_blocks, 'Block should be visible when exactly one XOR condition matches.');

    // Both match - should NOT be visible.
    $matching_values_both = [
      'var1' => 100,  // Matches.
      'var2' => 200,  // Also matches.
    ];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values_both);
    $this->assertNotContains($block->id(), $visible_blocks, 'Block should NOT be visible when multiple XOR conditions match.');
  }

  /**
   * Test findVisibleBlocksForWebform() with comparison operators.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::compareUsingOperatorWithRacRuleValue
   */
  public function testFindVisibleBlocksComparisonOperators(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with greater than comparison.
    $block_gt = $this->createRacBlockContent('test_webform_gt', 'Block GT', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'income', 'value' => '50000', 'operator' => 'greaterthan'],
        ],
      ],
    ], 'AND');

    // Create block with less than comparison.
    $block_lt = $this->createRacBlockContent('test_webform_lt', 'Block LT', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'age', 'value' => '65', 'operator' => 'lessthan'],
        ],
      ],
    ], 'AND');

    // Test greater than.
    $matching_values_gt = ['income' => 60000];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform_gt', $matching_values_gt);
    $this->assertContains($block_gt->id(), $visible_blocks, 'Block should be visible when income > 50000.');

    $matching_values_not_gt = ['income' => 40000];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform_gt', $matching_values_not_gt);
    $this->assertNotContains($block_gt->id(), $visible_blocks, 'Block should NOT be visible when income <= 50000.');

    // Test less than.
    $matching_values_lt = ['age' => 30];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform_lt', $matching_values_lt);
    $this->assertContains($block_lt->id(), $visible_blocks, 'Block should be visible when age < 65.');
  }

  /**
   * Test findVisibleBlocksForWebform() with outer OR operator.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   */
  public function testFindVisibleBlocksOuterOrOperator(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with two rule groups, outer OR operator.
    // Block is visible if either group passes.
    $block = $this->createRacBlockContent('test_webform', 'Block Outer OR', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'group1_var', 'value' => '100', 'operator' => 'equal'],
        ],
      ],
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'group2_var', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'OR');

    // Only first group matches.
    $matching_values = [
      'group1_var' => 100,
      'group2_var' => 999,
    ];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertContains($block->id(), $visible_blocks, 'Block should be visible when first group matches with outer OR.');
  }

  /**
   * Test findVisibleBlocksForWebform() with outer XOR operator.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   */
  public function testFindVisibleBlocksOuterXorOperator(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with two rule groups, outer XOR operator.
    $block = $this->createRacBlockContent('test_webform', 'Block Outer XOR', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'group1_var', 'value' => '100', 'operator' => 'equal'],
        ],
      ],
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'group2_var', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'XOR');

    // Only first group matches - should be visible.
    $matching_values_one = [
      'group1_var' => 100,
      'group2_var' => 999,
    ];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values_one);
    $this->assertContains($block->id(), $visible_blocks, 'Block should be visible when exactly one group matches with outer XOR.');

    // Both groups match - should NOT be visible.
    $matching_values_both = [
      'group1_var' => 100,
      'group2_var' => 200,
    ];
    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values_both);
    $this->assertNotContains($block->id(), $visible_blocks, 'Block should NOT be visible when both groups match with outer XOR.');
  }

  /**
   * Test findVisibleBlocksForWebform() with missing variable.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   */
  public function testFindVisibleBlocksMissingVariable(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with rule for variable that won't exist in matching values.
    $block = $this->createRacBlockContent('test_webform', 'Block Missing Var', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'missing_var', 'value' => '100', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    $matching_values = [
      'other_var' => 100,  // Different variable.
    ];

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertNotContains($block->id(), $visible_blocks, 'Block should NOT be visible when required variable is missing.');
  }

  /**
   * Test findVisibleBlocksForWebform() with unpublished paragraph.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::findRacBlockContentForWebform
   */
  public function testFindVisibleBlocksUnpublishedParagraph(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create unpublished block.
    $block = $this->createRacBlockContent('test_webform', 'Block Unpublished', [], 'AND', FALSE);

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', []);
    $this->assertNotContains($block->id(), $visible_blocks, 'Block with unpublished paragraph should not be found.');
  }

  /**
   * Test findVisibleBlocksForWebform() with multiple blocks.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::findRacBlockContentForWebform
   */
  public function testFindVisibleBlocksMultipleBlocks(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create multiple blocks for the same webform.
    $block1 = $this->createRacBlockContent('test_webform', 'Block 1', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'var1', 'value' => '100', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    $block2 = $this->createRacBlockContent('test_webform', 'Block 2', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'var2', 'value' => '200', 'operator' => 'equal'],
        ],
      ],
    ], 'AND');

    $block3 = $this->createRacBlockContent('test_webform', 'Block 3 No Rules', []);

    $matching_values = [
      'var1' => 100,  // Matches block1.
      'var2' => 999,  // Does not match block2.
    ];

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertContains($block1->id(), $visible_blocks, 'Block 1 should be visible.');
    $this->assertNotContains($block2->id(), $visible_blocks, 'Block 2 should NOT be visible.');
    $this->assertContains($block3->id(), $visible_blocks, 'Block 3 (no rules) should be visible.');
  }

  /**
   * Test findVisibleBlocksForWebform() with nested inner AND, outer OR.
   *
   * @covers ::findVisibleBlocksForWebform
   * @covers ::processRules
   */
  public function testFindVisibleBlocksNestedOperators(): void {
    $this->setUpBlockContentModules();

    /** @var \Drupal\webform_openfisca\RacContentHelper $helper */
    $helper = $this->container->get('webform_openfisca.rac_helper');

    // Create block with nested structure:
    // Outer OR between two groups
    // Each group has inner AND with multiple rules.
    $block = $this->createRacBlockContent('test_webform', 'Block Nested', [
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'group1_a', 'value' => '10', 'operator' => 'equal'],
          ['variable' => 'group1_b', 'value' => '20', 'operator' => 'equal'],
        ],
      ],
      [
        'operator' => 'AND',
        'rules' => [
          ['variable' => 'group2_a', 'value' => '30', 'operator' => 'equal'],
          ['variable' => 'group2_b', 'value' => '40', 'operator' => 'equal'],
        ],
      ],
    ], 'OR');

    // First group fully matches (inner AND), second doesn't.
    $matching_values = [
      'group1_a' => 10,
      'group1_b' => 20,
      'group2_a' => 30,
      'group2_b' => 999,  // Breaks second group's AND.
    ];

    $visible_blocks = $helper->findVisibleBlocksForWebform('test_webform', $matching_values);
    $this->assertContains($block->id(), $visible_blocks, 'Block should be visible when first nested group fully matches.');
  }

}
