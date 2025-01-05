<?php

declare(strict_types=1);

namespace Drupal\webform_openfisca;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Implementation of RAC content helper service.
 */
class RacContentHelper implements RacContentHelperInterface {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
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

    foreach ($rules as $redirect_rule) {
      foreach ($redirect_rule['rules'] as $rule) {
        // All rules of a redirect rule are evaluated with the AND logic.
        if (!isset($matching_values[$rule['variable']]) || !$this->compareWithRacRuleValue($matching_values[$rule['variable']], $rule['value'])) {
          // One mismatch, skip the entire redirect rule.
          continue 2;
        }
      }
      return $redirect_rule['redirect'];
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
   * Find the RAC rules for a webform.
   *
   * @param string $webform_id
   *   The webform ID.
   *
   * @return array|null
   *   The rules.
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

      $redirect_rule = [
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
        $redirect_rule['rules'][] = [
          'variable' => $field_variable,
          'value' => $field_value,
        ];
      }
      if (!empty($redirect_rule['rules'])) {
        $rules[] = $redirect_rule;
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

}
