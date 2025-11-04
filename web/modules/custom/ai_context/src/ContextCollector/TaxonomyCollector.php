<?php

declare(strict_types=1);

namespace Drupal\ai_context\ContextCollector;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Collects taxonomy terms context from nodes.
 */
class TaxonomyCollector implements ContextCollectorInterface {

  /**
   * Constructs a TaxonomyCollector.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function collect(array $options = []): array {
    if (empty($options['entity_type']) || $options['entity_type'] !== 'node') {
      return [];
    }

    if (empty($options['entity_id'])) {
      return [];
    }

    try {
      $storage = $this->entityTypeManager->getStorage('node');
      $node = $storage->load($options['entity_id']);

      if (!$node instanceof NodeInterface) {
        return [];
      }

      $taxonomies = [];

      foreach ($node->getFields() as $field) {
        if ($field->getFieldDefinition()->getType() === 'entity_reference') {
          $target_type = $field->getFieldDefinition()->getSetting('target_type');

          if ($target_type === 'taxonomy_term') {
            foreach ($field->referencedEntities() as $term) {
              $vocabulary = $term->bundle();
              if (!isset($taxonomies[$vocabulary])) {
                $taxonomies[$vocabulary] = [];
              }
              $taxonomies[$vocabulary][] = [
                'id' => $term->id(),
                'name' => $term->label(),
              ];
            }
          }
        }
      }

      return $taxonomies;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKey(array $options = []): string {
    $nid = $options['entity_id'] ?? 'unknown';
    return "ai_context:taxonomy:{$nid}";
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(array $options = []): array {
    $nid = $options['entity_id'] ?? NULL;
    $tags = ['ai_context:taxonomy'];

    if ($nid) {
      $tags[] = "node:{$nid}";
      $tags[] = 'taxonomy_term_list';
    }

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // Taxonomy associations don't change very often, cache for 6 hours.
    return 21600;
  }

}

