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

}
