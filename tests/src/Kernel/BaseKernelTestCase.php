<?php

declare(strict_types=1);

namespace Drupal\Tests\webform_openfisca\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
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

  /**
   * Set up block content modules for RAC block testing.
   */
  protected function setUpBlockContentModules(): void {
    $this->enableModules(['block_content']);
    $this->installEntitySchema('block_content');

    // Create block content type.
    $block_type = BlockContentType::create([
      'id' => 'rac_block',
      'label' => 'RAC Block',
    ]);
    $block_type->save();

    // Create the paragraph types for block RAC elements.
    $this->createBlockRacParagraphTypes();

    // Create field for block content to reference paragraphs.
    $this->createBlockRacParagraphField();
  }

  /**
   * Create paragraph types for block RAC elements.
   */
  protected function createBlockRacParagraphTypes(): void {
    // Create 'block_rac_elements' paragraph type.
    $paragraph_type = ParagraphsType::create([
      'id' => 'block_rac_elements',
      'label' => 'Block RAC Elements',
    ]);
    $paragraph_type->save();

    // Create fields for block_rac_elements paragraph.
    // field_block_webform - string field for webform ID.
    FieldStorageConfig::create([
      'field_name' => 'field_block_webform',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_block_webform',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_elements',
      'label' => 'Block Webform',
    ])->save();

    // field_operator - string field for AND/OR/XOR.
    FieldStorageConfig::create([
      'field_name' => 'field_operator',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_operator',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_elements',
      'label' => 'Operator',
    ])->save();

    // field_block_rules - entity reference to rule group paragraphs.
    FieldStorageConfig::create([
      'field_name' => 'field_block_rules',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_block_rules',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_elements',
      'label' => 'Block Rules',
    ])->save();

    // Create 'block_rac_rule_group' paragraph type for rule groups.
    $rule_group_type = ParagraphsType::create([
      'id' => 'block_rac_rule_group',
      'label' => 'Block RAC Rule Group',
    ]);
    $rule_group_type->save();

    // field_rules_operator - string field for inner operator.
    FieldStorageConfig::create([
      'field_name' => 'field_rules_operator',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_rules_operator',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_rule_group',
      'label' => 'Rules Operator',
    ])->save();

    // Note: field_rac_paragraphs is created on block_rac_rule_group in
    // createBlockRacParagraphField() to match block content's field name.

    // Create 'block_rac_rule' paragraph type for individual rules.
    $rule_type = ParagraphsType::create([
      'id' => 'block_rac_rule',
      'label' => 'Block RAC Rule',
    ]);
    $rule_type->save();

    // field_block_variable - string field.
    FieldStorageConfig::create([
      'field_name' => 'field_block_variable',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_block_variable',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_rule',
      'label' => 'Block Variable',
    ])->save();

    // field_block_value - string field.
    FieldStorageConfig::create([
      'field_name' => 'field_block_value',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_block_value',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_rule',
      'label' => 'Block Value',
    ])->save();

    // field_rule_block_operator - string field.
    FieldStorageConfig::create([
      'field_name' => 'field_rule_block_operator',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_rule_block_operator',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_rule',
      'label' => 'Rule Block Operator',
    ])->save();
  }

  /**
   * Create paragraph reference field on block content.
   */
  protected function createBlockRacParagraphField(): void {
    // Create field_rac_paragraphs on block_content.
    FieldStorageConfig::create([
      'field_name' => 'field_rac_paragraphs',
      'entity_type' => 'block_content',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_rac_paragraphs',
      'entity_type' => 'block_content',
      'bundle' => 'rac_block',
      'label' => 'RAC Paragraphs',
    ])->save();

    // Create same field name on paragraph entity type for rule groups.
    // The RacContentHelper::findRulesForBlock() expects the rule group
    // paragraph to have a field with the same name as the block's field.
    FieldStorageConfig::create([
      'field_name' => 'field_rac_paragraphs',
      'entity_type' => 'paragraph',
      'type' => 'entity_reference_revisions',
      'settings' => [
        'target_type' => 'paragraph',
      ],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_rac_paragraphs',
      'entity_type' => 'paragraph',
      'bundle' => 'block_rac_rule_group',
      'label' => 'RAC Paragraphs',
    ])->save();
  }

  /**
   * Create a block content with RAC visibility rules.
   *
   * @param string $webform_id
   *   The webform ID to associate with.
   * @param string $label
   *   The block label.
   * @param array $rule_groups
   *   Array of rule groups. Each group has:
   *   - 'operator': The inner operator (AND/OR).
   *   - 'rules': Array of rules with 'variable', 'value', 'operator'.
   * @param string $parent_operator
   *   The outer operator (AND/OR/XOR).
   * @param bool $published
   *   Whether the paragraph should be published.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   The created block content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createRacBlockContent(string $webform_id, string $label, array $rule_groups = [], string $parent_operator = 'AND', bool $published = TRUE): BlockContent {
    $block_rules = [];

    foreach ($rule_groups as $group) {
      $rules = [];
      foreach ($group['rules'] ?? [] as $rule) {
        $rule_paragraph = Paragraph::create([
          'type' => 'block_rac_rule',
          'field_block_variable' => $rule['variable'] ?? '',
          'field_block_value' => $rule['value'] ?? '',
          'field_rule_block_operator' => $rule['operator'] ?? 'equal',
        ]);
        $rule_paragraph->save();
        $rules[] = $rule_paragraph;
      }

      // Rule group paragraph uses field_rac_paragraphs (same name as block's
      // paragraph field) to reference the individual rule paragraphs.
      $rule_group_paragraph = Paragraph::create([
        'type' => 'block_rac_rule_group',
        'field_rules_operator' => $group['operator'] ?? 'AND',
        'field_rac_paragraphs' => $rules,
      ]);
      $rule_group_paragraph->save();
      $block_rules[] = $rule_group_paragraph;
    }

    $rac_elements_paragraph = Paragraph::create([
      'type' => 'block_rac_elements',
      'status' => $published ? 1 : 0,
      'field_block_webform' => $webform_id,
      'field_operator' => [['value' => $parent_operator]],
      'field_block_rules' => $block_rules,
    ]);
    $rac_elements_paragraph->save();

    $block = BlockContent::create([
      'type' => 'rac_block',
      'info' => $label,
      'field_rac_paragraphs' => [$rac_elements_paragraph],
    ]);
    $block->save();

    return $block;
  }

}
