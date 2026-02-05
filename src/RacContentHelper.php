<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Implementation of RAC content helper service.
 */
class RacContentHelper implements RacContentHelperInterface {

  /**
   * Constructs a new \Drupal\webform_openfisca\RacContentHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public function findRacRedirectForWebform(string $webform_id, array $matching_values): ?string {
    // Extract the rules.
    $rules = $this->findRacRulesForWebform($webform_id);
    if (!is_array($rules)) {
      return NULL;
    }

    foreach ($rules as $visibility_rule) {
      foreach ($visibility_rule['rules'] as $rule) {
        // All rules of a redirect rule are evaluated with the AND logic.
        if (!isset($matching_values[$rule['variable']]) || !$this->compareWithRacRuleValue($matching_values[$rule['variable']], $rule['value'])) {
          // One mismatch, skip the entire redirect rule.
          continue 2;
        }
      }
      return $visibility_rule['redirect'];
    }
    return NULL;
  }

  /**
   * Find a valid RAC node referencing a webform.
   *
   * @param string $webform_id
   *   The webform ID.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node.
   */
  protected function findRacContentForWebform(string $webform_id): ?NodeInterface {
    try {
      /** @var \Drupal\node\NodeStorageInterface $node_storage */
      $node_storage = $this->entityTypeManager->getStorage('node');
      $nodes = $node_storage->getQuery()
        ->condition('type', 'rac')
        ->condition('field_webform', $webform_id)
        ->accessCheck(FALSE)
        ->execute();
      // If there are no nodes found exit early.
      if (empty($nodes)) {
        return NULL;
      }

      foreach ($nodes as $nid) {
        /** @var \Drupal\node\NodeInterface $node */
        $node = $node_storage->load($nid);
        // Ignore this node if it does not have the right fields.
        if (!$node instanceof NodeInterface
          || !$node->hasField('field_rules')
          || !($node->get('field_rules') instanceof EntityReferenceFieldItemListInterface)
          || $node->get('field_rules')->isEmpty()
        ) {
          continue;
        }
        // Return the found RAC node.
        return $node;
      }
    }
    // @codeCoverageIgnoreStart
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return NULL;
    }

    return NULL;
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find RAC block content IDs referencing a webform.
   *
   * @param string $webform_id
   *   The webform ID.
   *
   * @return array
   *   An array of block content IDs.
   */
  protected function findRacBlockContentForWebform(string $webform_id): array {
    try {
      $paragraphs = $this->entityTypeManager->getStorage('paragraph')->getQuery()
        ->condition('type', 'block_rac_elements')
        ->condition('status', 1)
        ->condition('field_block_webform', $webform_id)
        ->accessCheck(FALSE)
        ->execute();

      $blocks = [];

      if ($paragraphs) {
        $paragraph_entities = $this->entityTypeManager->getStorage('paragraph')->loadMultiple($paragraphs);
        foreach ($paragraph_entities as $paragraph) {
          if (!$paragraph instanceof ParagraphInterface) {
            continue;
          }
          $parent = $paragraph->getParentEntity();
          // Parent can be NULL when paragraphs are loaded via loadMultiple() in
          // some environments; skip so we do not trigger invalid ID.
          // @codeCoverageIgnoreStart - branch depends on Paragraphs runtime.
          if ($parent !== NULL) {
            $blocks[] = $parent->id();
          }
          // @codeCoverageIgnoreEnd
        }
      }

      return array_values($blocks);
    }
    // @codeCoverageIgnoreStart
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return [];
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find the block field name.
   *
   * @param \Drupal\block_content\BlockContentInterface $block
   *   Block object.
   *
   * @return int|string|null
   *   Return block field name.
   */
  protected function findBlockFieldName(BlockContentInterface $block): int|string|null {
    $block_type = $block->bundle();

    $fields = $this->entityFieldManager->getFieldDefinitions('block_content', $block_type);

    $field_found = NULL;

    foreach ($fields as $field_name => $field_definition) {
      // Only check paragraph reference fields.
      if ($field_definition->getType() === 'entity_reference_revisions') {
        $settings = $field_definition->getSettings();
        if (isset($settings['target_type']) && $settings['target_type'] === 'paragraph') {
          // Check the referenced paragraphs.
          $paragraphs = $block->get($field_name)->referencedEntities();
          foreach ($paragraphs as $paragraph) {
            if ($paragraph->bundle() === 'block_rac_elements') {
              $field_found = $field_name;
              break 2;
            }
          }
        }
      }
    }

    return $field_found;
  }

  /**
   * Find the rules for a block content entity.
   *
   * @param string|int $block_id
   *   The block content ID.
   *
   * @return array|null
   *   The rules as an array of ['variable' => string, 'value' => string], or
   *   NULL if not found.
   */
  protected function findRulesForBlock(string|int $block_id): ?array {
    try {
      /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $block_content_storage */
      $block_content_storage = $this->entityTypeManager->getStorage('block_content');
      /** @var \Drupal\block_content\BlockContentInterface|null $block */
      $block = $block_content_storage->load($block_id);

      if (!$block instanceof BlockContentInterface) {
        return NULL;
      }

      // Find the block field name with paragraph type block_rac_elements.
      $field_name = $this->findBlockFieldName($block);

      if ($field_name !== NULL && $field_name !== '') {
        $field_name_str = (string) $field_name;
        if (!$block->hasField($field_name_str)
          || !($block->get($field_name_str) instanceof EntityReferenceFieldItemListInterface)
          || $block->get($field_name_str)->isEmpty()
        ) {
          return NULL;
        }

        $paragraph = $block->get($field_name_str)->entity;

        $rac_element_paragraphs = [];
        if ($paragraph instanceof ParagraphInterface && $paragraph->hasField('field_block_rules')) {
          $rac_element_paragraphs = $paragraph->get('field_block_rules');
          $operator = $paragraph->get('field_operator')->getValue();
        }

        // Extract the rules.
        // Initialize redirect_rule for this rules_index.
        $visibility_rule = [
          'rules' => [],
        ];

        foreach ($rac_element_paragraphs as $rac_element_paragraph) {
          /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem<\Drupal\paragraphs\ParagraphInterface> $rac_element_paragraph */
          $paragraph_entity = $rac_element_paragraph->entity;
          if (!$paragraph_entity instanceof ParagraphInterface) {
            continue;
          }
          // Get the field that contains multiple paragraph references.
          $block_rac_elements_field = $paragraph_entity->get($field_name_str);
          $rule_operator = $paragraph_entity->get('field_rules_operator')->value;

          if (!$block_rac_elements_field instanceof EntityReferenceFieldItemListInterface || $block_rac_elements_field->isEmpty()) {
            continue;
          }
          /** @var \Drupal\paragraphs\ParagraphInterface[] $block_rules_paragraphs */
          $block_rules_paragraphs = $block_rac_elements_field->referencedEntities();

          $visibility_rule_single = [];

          foreach ($block_rules_paragraphs as $block_rac_element) {
            if (!$block_rac_element instanceof ParagraphInterface
              || !$block_rac_element->hasField('field_block_variable')
              || !$block_rac_element->hasField('field_block_value')
              || $block_rac_element->get('field_block_variable')->isEmpty()
              || $block_rac_element->get('field_block_value')->isEmpty()
            ) {
              continue;
            }
            $field_block_variable = $block_rac_element->get('field_block_variable')->getString();
            $field_block_value = $block_rac_element->get('field_block_value')->getString();
            $field_rule_block_operator = $block_rac_element->get('field_rule_block_operator')->getString();
            $visibility_rule_single[] = [
              'variable' => $field_block_variable,
              'value' => $field_block_value,
              'rule_block_operator' => $field_rule_block_operator,
            ];
          }

          if (!empty($visibility_rule_single)) {
            $visibility_rule_single['operator'] = $rule_operator;
            $visibility_rule['rules'][] = $visibility_rule_single;
          }
        }
        $visibility_rule['parent_operator'] = $operator ?? 'AND';
        return $visibility_rule;
      }

      return NULL;
    }
    // @codeCoverageIgnoreStart
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return NULL;
    }
    // @codeCoverageIgnoreEnd
  }

  /**
   * Find the RAC rules for a webform.
   *
   * @param string $webform_id
   *   The webform ID.
   *
   * @return array|null
   *   The rules.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function findRacRulesForWebform(string $webform_id): ?array {
    // Find the RAC node for this webform ID.
    $node = $this->findRacContentForWebform($webform_id);
    if (!$node instanceof NodeInterface) {
      return NULL;
    }
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\paragraphs\ParagraphInterface> $rac_element_paragraphs */
    $rac_element_paragraphs = $node->get('field_rules');
    /** @var \Drupal\paragraphs\ParagraphInterface[] $rules_paragraphs */
    $rules_paragraphs = $rac_element_paragraphs->referencedEntities();

    // Extract the rules.
    $rules = [];
    foreach ($rules_paragraphs as $paragraph) {
      // Ignore the invalid paragraphs.
      if (
        !$paragraph instanceof ParagraphInterface
        || !$paragraph->hasField('field_redirect_to')
        || !$paragraph->hasField('field_rac_element')
        || $paragraph->get('field_redirect_to')->isEmpty()
        || $paragraph->get('field_rac_element')->isEmpty()
      ) {
        continue;
      }

      $rac_elements = $paragraph->get('field_rac_element');
      if (!$rac_elements instanceof EntityReferenceFieldItemListInterface
        || $rac_elements->isEmpty()
      ) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }

      $redirect_to = $paragraph->get('field_redirect_to');
      if (!$redirect_to instanceof EntityReferenceFieldItemListInterface
        || $redirect_to->isEmpty()
      ) {
        // @codeCoverageIgnoreStart
        continue;
        // @codeCoverageIgnoreEnd
      }
      /** @var \Drupal\node\NodeInterface[] $redirect_nodes */
      $redirect_nodes = $redirect_to->referencedEntities();
      $redirect_node = reset($redirect_nodes);
      if (!$redirect_node instanceof NodeInterface) {
        // Skip this rule as the redirect is not a node.
        continue;
      }

      $visibility_rule = [
        'rules' => [],
        'redirect' => $redirect_node->toUrl()->toString(),
      ];

      /** @var \Drupal\paragraphs\ParagraphInterface $rac_element */
      foreach ($rac_elements->referencedEntities() as $rac_element) {
        if (!$rac_element->hasField('field_variable')
          || !$rac_element->hasField('field_value')
          || $rac_element->get('field_variable')->isEmpty()
          || $rac_element->get('field_value')->isEmpty()
        ) {
          continue;
        }
        $field_variable = $rac_element->get('field_variable')->getString();
        $field_value = $rac_element->get('field_value')->getString();
        $visibility_rule['rules'][] = [
          'variable' => $field_variable,
          'value' => $field_value,
        ];
      }
      if (!empty($visibility_rule['rules'])) {
        $rules[] = $visibility_rule;
      }
    }

    return $rules;
  }

  /**
   * Perform a string comparison between a value and a RAC rule value.
   *
   * @param mixed $value
   *   The value.
   * @param string $rac_rule_value
   *   The RAC rule value.
   *
   * @return bool
   *   TRUE if the 2 values are considered as equal.
   */
  protected function compareWithRacRuleValue(mixed $value, string $rac_rule_value): bool {
    // Do not apply explicit type-casting and strict comparison here as RAC
    // rules are always string but OpenFisca response can be in any type.
    // @todo Find a better way to perform strict comparison instead of relying
    // on hidden type-casting from PHP.
    return $value == $rac_rule_value;
  }

  /**
   * Compare a value with a RAC rule value using the chosen operator.
   *
   * @param mixed $value
   *   The value.
   * @param string $rac_rule_value
   *   The RAC rule value.
   * @param string $operator
   *   The operator.
   *
   * @return bool
   *   compare values and return TRUE or FALSE.
   */
  protected function compareUsingOperatorWithRacRuleValue(mixed $value, string $rac_rule_value, string $operator): bool {
    $operator_value = $this->returnOperator($operator);

    return match ($operator_value) {
      '=='  => $value == $rac_rule_value,
      '!='  => $value != $rac_rule_value,
      '>'   => $value > $rac_rule_value,
      '<'   => $value < $rac_rule_value,
      '>='  => $value >= $rac_rule_value,
      '<='  => $value <= $rac_rule_value,
      default => throw new \InvalidArgumentException("Unsupported operator: {$operator_value}")
    };
  }

  /**
   * Return operator based on value.
   *
   * @param string $operator
   *   Operator string.
   *
   * @return string
   *   Returns operator.
   */
  protected function returnOperator(string $operator): string {
    $operators = [
      'equal' => '==',
      'notequal' => '!=',
      'lessthan' => '<',
      'greaterthan' => '>',
      'lessthanequalto' => '<=',
      'greaterthanequalto' => '>=',
    ];

    return $operators[$operator] ?? '==';
  }

  /**
   * Find visible blocks for a webform based on matching values.
   *
   * @param string $webform_id
   *   The webform ID.
   * @param array $matching_values
   *   The values to match against block rules.
   *
   * @return array
   *   An array of block IDs that match the rules.
   */
  public function findVisibleBlocksForWebform(string $webform_id, array $matching_values): array {
    // Find all the blocks associated with this webform.
    $block_ids = $this->findRacBlockContentForWebform($webform_id);

    if (empty($block_ids)) {
      return [];
    }
    $visible_blocks = [];

    foreach ($block_ids as $block_id) {
      // Get the rules for this block.
      $rules = $this->findRulesForBlock($block_id);
      // No rules (or empty rules array) mean the block is always visible.
      if (!is_array($rules) || empty($rules['rules'] ?? [])) {
        $visible_blocks[] = $block_id;
        continue;
      }

      $this->processRules($block_id, $rules, $visible_blocks, $matching_values);
    }

    return $visible_blocks;
  }

  /**
   * Process rules for a block to determine visibility.
   *
   * @param string|int $block_id
   *   The block ID.
   * @param array $rules
   *   The rules array.
   * @param array &$visible_blocks
   *   Array of visible block IDs (passed by reference).
   * @param array $matching_values
   *   The matching values to evaluate against rules.
   *
   * @return void
   *   No return value.
   */
  protected function processRules(string|int $block_id, array $rules, array &$visible_blocks, array $matching_values): void {
    // Outer operator (AND, OR, XOR).
    $block_rules_operator = $rules['parent_operator'][0]['value'] ?? 'AND';
    $parent_is_matched = [];

    foreach ($rules['rules'] as $rules_data) {
      // Get rules for this block.
      $block_rules = $rules_data ?? [];
      unset($block_rules['operator']);
      $block_rule_operator = $rules_data['operator'] ?? 'AND';
      $total_inner_rules = count($block_rules);

      // Skip empty rule blocks early.
      if ($total_inner_rules === 0) {
        continue;
      }

      $is_matched = NULL;

      foreach ($block_rules as $rule) {
        $variable = $rule['variable'] ?? NULL;

        if (!isset($matching_values[$variable])) {
          $is_matched[] = 0;
          continue;
        }

        $result = $this->compareUsingOperatorWithRacRuleValue(
          (empty($matching_values[$variable]) ? 0 : $matching_values[$variable]),
          $rule['value'],
          $rule['rule_block_operator'] ?? 'AND'
        );

        $is_matched[] = !empty($result) ? 1 : 0;
      }

      if ($is_matched) {
        // At inner level.
        $parent_result = $this->evaluateCondition($is_matched, $block_rule_operator);
        $parent_is_matched[] = !empty($parent_result) ? 1 : 0;
      }
    }

    // At outer level.
    $finalResult = $this->evaluateCondition($parent_is_matched, $block_rules_operator);

    // Optional: keep visible blocks only if final result is TRUE.
    if ($finalResult) {
      $visible_blocks[] = $block_id;
    }
  }

  /**
   * Evaluate condition based on operator and matched values.
   *
   * @param array|null $is_matched
   *   Array of matched values (1 for match, 0 for no match).
   * @param string $operator
   *   The operator (AND, OR, XOR).
   *
   * @return bool
   *   TRUE if condition is met, FALSE otherwise.
   */
  protected function evaluateCondition(?array $is_matched, string $operator): bool {
    if ($is_matched) {
      switch (strtolower($operator)) {
        case 'or':
          return in_array(1, $is_matched);

        case 'xor':
          // True if **exactly one** condition is matched.
          $truthy_count = count(array_filter($is_matched, function ($v) {
            return $v !== 0 && $v !== '' && $v !== NULL;
          }));
          return ($truthy_count === 1);

        case 'and':
        default:
          // True if **all** conditions are matched (no 0, '', null, false).
          return count(array_filter($is_matched, function ($v) {
            return $v !== 0 && $v !== '' && $v !== NULL;
          })) === count($is_matched);
      }
    }

    return FALSE;
  }

}
