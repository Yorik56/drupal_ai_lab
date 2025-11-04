<?php

declare(strict_types=1);

namespace Drupal\ai_context\ContextCollector;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Collects node metadata context.
 */
class NodeMetadataCollector implements ContextCollectorInterface {

  /**
   * Constructs a NodeMetadataCollector.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
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

      // Check access.
      if (!$node->access('view', $this->currentUser)) {
        return [];
      }

      return [
        'id' => $node->id(),
        'title' => $node->getTitle(),
        'type' => $node->bundle(),
        'status' => $node->isPublished() ? 'published' : 'unpublished',
        'created' => $node->getCreatedTime(),
        'changed' => $node->getChangedTime(),
        'author' => $node->getOwner()->getDisplayName(),
        'language' => $node->language()->getId(),
      ];
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
    return "ai_context:node:{$nid}";
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(array $options = []): array {
    $nid = $options['entity_id'] ?? NULL;
    $tags = ['ai_context:node'];

    if ($nid) {
      $tags[] = "node:{$nid}";
    }

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // Node metadata changes moderately, cache for 1 hour.
    return 3600;
  }

}

