<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\block\BlockInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Kernel test for webform_openfisca_block_access and RAC paragraph detection.
 *
 * @group webform_openfisca
 * @covers ::webform_openfisca_block_access
 * @covers ::_webform_openfisca_find_block_with_rac_element
 */
class WebformOpenFiscaBlockAccessKernelTest extends BaseKernelTestCase {

  /**
   * RequestStack variable.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->enableModules(['block', 'block_content']);
    $this->installEntitySchema('block_content');
    $this->setUpBlockContentModules();

    // Manually set up a mock session for the request.
    $request = $this->requestStack->getCurrentRequest();
    $session = new Session(new MockArraySessionStorage());
    $request->setSession($session);

    $this->setCurrentUser(new AnonymousUserSession());
  }

  /**
   * Tests _webform_openfisca_find_block_with_rac_element for RAC blocks.
   */
  public function testFindBlockWithRacElement(): void {
    $block_with_rac = $this->createRacBlockContent('test_webform', 'RAC Block', [], 'AND');
    $this->assertTrue(
      \_webform_openfisca_find_block_with_rac_element($block_with_rac),
      'Block with block_rac_elements paragraph must be detected.'
    );

    $block_without_rac = BlockContent::create([
      'type' => 'rac_block',
      'info' => 'Empty block',
    ]);
    $block_without_rac->save();
    $this->assertFalse(
      \_webform_openfisca_find_block_with_rac_element($block_without_rac),
      'Block without block_rac_elements paragraph must not be detected.'
    );
  }

  /**
   * Tests block_access forbidden when block has RAC and ID not in query.
   */
  public function testBlockAccessRacBlockNotInQueryReturnsForbidden(): void {
    $block_content = $this->createRacBlockContent('test_webform', 'RAC Block', [], 'AND');
    $block_plugin = $this->createBlockPluginMock('block_content:' . $block_content->uuid());

    $request = Request::create('/', 'GET', []);
    $this->container->get('request_stack')->push($request);

    $result = \webform_openfisca_block_access($block_plugin, 'view', new AnonymousUserSession());

    $this->assertTrue($result->isForbidden(), 'Block with RAC paragraph and ID not in ?blocks= must be forbidden.');
    $this->assertTrue($result->getCacheContexts() !== NULL && in_array('url.query_args:blocks', $result->getCacheContexts(), TRUE));
  }

  /**
   * Tests block_access returns allowed when block has RAC and ID is in query.
   */
  public function testBlockAccessRacBlockInQueryReturnsAllowed(): void {
    $block_content = $this->createRacBlockContent('test_webform', 'RAC Block', [], 'AND');
    $block_plugin = $this->createBlockPluginMock('block_content:' . $block_content->uuid());

    $request = Request::create('/', 'GET', ['blocks' => (string) $block_content->id()]);
    $this->container->get('request_stack')->push($request);

    $result = \webform_openfisca_block_access($block_plugin, 'view', new AnonymousUserSession());

    $this->assertTrue($result->isAllowed(), 'Block with RAC paragraph and ID in ?blocks= must be allowed.');
    $this->assertTrue($result->getCacheContexts() !== NULL && in_array('url.query_args:blocks', $result->getCacheContexts(), TRUE));
  }

  /**
   * Tests block_access returns neutral when block has no RAC paragraph.
   */
  public function testBlockAccessBlockWithoutRacReturnsNeutral(): void {
    $block_without_rac = BlockContent::create([
      'type' => 'rac_block',
      'info' => 'Empty block',
    ]);
    $block_without_rac->save();
    $block_plugin = $this->createBlockPluginMock('block_content:' . $block_without_rac->uuid());

    $request = Request::create('/', 'GET', []);
    $this->container->get('request_stack')->push($request);

    $result = \webform_openfisca_block_access($block_plugin, 'view', new AnonymousUserSession());

    $this->assertTrue($result->isNeutral(), 'Block without RAC paragraph must be neutral.');
  }

  /**
   * Tests block_access returns neutral when operation is not 'view'.
   */
  public function testBlockAccessNonViewOperationReturnsNeutral(): void {
    $block_content = $this->createRacBlockContent('test_webform', 'RAC Block', [], 'AND');
    $block_plugin = $this->createBlockPluginMock('block_content:' . $block_content->uuid());

    $request = Request::create('/', 'GET', []);
    $this->container->get('request_stack')->push($request);

    $result = \webform_openfisca_block_access($block_plugin, 'configure', new AnonymousUserSession());

    $this->assertTrue($result->isNeutral(), 'Non-view operation must be neutral.');
  }

  /**
   * Creates a mock BlockInterface with the given plugin ID.
   *
   * @param string $plugin_id
   *   The plugin ID (e.g. 'block_content:uuid').
   *
   * @return \Drupal\block\BlockInterface
   *   The mock block plugin.
   */
  protected function createBlockPluginMock(string $plugin_id): BlockInterface {
    $block = $this->createMock(BlockInterface::class);
    $block->method('getPluginId')->willReturn($plugin_id);
    return $block;
  }

}
