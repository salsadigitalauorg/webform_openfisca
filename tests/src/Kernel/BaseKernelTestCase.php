<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for kernel tests.
 */
abstract class BaseKernelTestCase extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected static $modules = [
    'datetime',
    'file',
    'field',
    'filter',
    'menu_ui',
    'options',
    'serialization',
    'language',
    'system',
    'text',
    'node',
    'user',
    'entity_reference_revisions',
    'paragraphs',
    'token',
    'webform',
    'webform_ui',
    'webform_openfisca',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installConfig(['filter']);
    $this->installEntitySchema('file');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['user']);
    $this->installEntitySchema('user');
    $this->installConfig(['webform_openfisca']);
  }

  /**
   * Set up the webform module.
   */
  protected function setUpRacContentModules() : void {
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installConfig(['paragraphs']);
    $this->installEntitySchema('paragraph');
    $this->enableModules(['entity_test']);
    $this->installEntitySchema('entity_test');
  }

  /**
   * Set up the webform module.
   */
  protected function setUpWebformModule() : void {
    $this->enableModules(['path', 'path_alias']);
    $this->installEntitySchema('path_alias');
    $this->installConfig(['language']);
    $this->installSchema('webform', ['webform']);
    $this->installConfig('webform');
    $this->installEntitySchema('webform_submission');
  }

  /**
   * Set up the webform_openfisca_test module.
   */
  protected function setupWebformOpenFiscaTest() : void {
    $this->installConfig(['token']);
    $this->setUpWebformModule();
    $this->enableModules(['webform_openfisca_test']);
    $this->installConfig(['webform_openfisca_test']);
    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Create a test Page node.
   *
   * @param string $title
   *   The node title.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createTestPage(string $title): NodeInterface {
    return $this->createNode([
      'type' => 'page',
      'title' => $title,
    ]);
  }

  /**
   * Create a test RAC node.
   *
   * @param string $webform_id
   *   The webform for the RAC content.
   * @param string $title
   *   Node title.
   * @param array[] $redirect_rules
   *   The redirect rules. Each redirect rule has these keys:
   *   - 'redirect': the node to redirect.
   *   - 'rules': the matching rules for the redirect. The key and value of each
   *   rule will be compared with variable => value.
   *
   * @return \Drupal\node\NodeInterface
   *   The RAC content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createRacContent(string $webform_id, string $title, array $redirect_rules = []) : NodeInterface {
    $rac_rules = [];
    foreach ($redirect_rules as $redirect_rule) {
      if (empty($redirect_rule)) {
        $rac_rules[] = Paragraph::create(['type' => 'rac']);
        continue;
      }

      $rac_elements = [];
      if (empty($redirect_rule['rules'])) {
        $rac_elements[] = Paragraph::create([
          'type' => 'rac_element',
        ]);
      }
      else {
        foreach ($redirect_rule['rules'] as $variable => $value) {
          $rac_elements[] = Paragraph::create([
            'type' => 'rac_element',
            'field_value' => $value,
            'field_variable' => $variable,
          ]);
        }
      }

      $redirect = $redirect_rule['redirect'] ?? [];
      $rac_rules[] = Paragraph::create([
        'type' => 'rac',
        'field_rac_element' => $rac_elements,
        'field_redirect_to' => $redirect,
      ]);
    }

    $rac = $this->createNode([
      'type' => 'rac',
      'title' => $title,
      'field_rules' => $rac_rules,
      'field_webform' => [
        'target_id' => $webform_id,
      ],
    ]);
    $rac->save();

    return $rac;
  }

  /**
   * Visit an internal path.
   *
   * @param string $path
   *   The path.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Exception
   */
  protected function visitInternalPath(string $path) : Response {
    /** @var \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel */
    $http_kernel = $this->container->get('http_kernel');
    $request = Request::create($path);
    return $http_kernel->handle($request);
  }

}
