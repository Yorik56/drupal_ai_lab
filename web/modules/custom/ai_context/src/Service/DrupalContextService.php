<?php

declare(strict_types=1);

namespace Drupal\ai_context\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Service for collecting and managing Drupal context for AI prompts.
 */
class DrupalContextService implements DrupalContextServiceInterface {

  /**
   * Constructs a DrupalContextService.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected CacheBackendInterface $cache,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function collectContext(array $options = []): array {
    $cache_key = $this->buildCacheKey($options);
    
    // Try to get from cache first.
    $cached = $this->getCachedContext($cache_key);
    if ($cached !== NULL) {
      return $cached;
    }

    $context = [];

    // Collect site configuration.
    $context['site'] = $this->collectSiteConfig();

    // Collect entity-specific context if provided.
    if (!empty($options['entity_type']) && !empty($options['entity_id'])) {
      $context['entity'] = $this->collectEntityContext(
        $options['entity_type'],
        $options['entity_id']
      );
    }

    // Allow other modules to alter the context.
    $this->moduleHandler->alter('ai_context_collect', $context, $options);

    // Cache the context.
    $cache_tags = $this->buildCacheTags($options);
    $this->setCachedContext($cache_key, $context, $cache_tags);

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function enrichPrompt(string $prompt, array $context, array $context_keys = []): string {
    if (empty($context)) {
      return $prompt;
    }

    $context_string = $this->formatContextAsString($context, $context_keys);
    
    if (empty($context_string)) {
      return $prompt;
    }

    // Prepend context to the prompt.
    $enriched = "DRUPAL SITE CONTEXT:\n";
    $enriched .= $context_string;
    $enriched .= "\n\nUSER REQUEST:\n";
    $enriched .= $prompt;

    return $enriched;
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedContext(string $cache_key): ?array {
    $cached = $this->cache->get($cache_key);
    return $cached ? $cached->data : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCachedContext(string $cache_key, array $context, array $cache_tags = [], int $max_age = 3600): void {
    $this->cache->set(
      $cache_key,
      $context,
      time() + $max_age,
      $cache_tags
    );
  }

  /**
   * Collects site configuration context.
   *
   * @return array
   *   Site configuration context.
   */
  protected function collectSiteConfig(): array {
    $site_config = $this->configFactory->get('system.site');
    
    return [
      'name' => $site_config->get('name'),
      'slogan' => $site_config->get('slogan'),
      'mail' => $site_config->get('mail'),
    ];
  }

  /**
   * Collects entity-specific context.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int|string $entity_id
   *   The entity ID.
   *
   * @return array
   *   Entity context data.
   */
  protected function collectEntityContext(string $entity_type, int|string $entity_id): array {
    try {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $entity = $storage->load($entity_id);

      if (!$entity) {
        return [];
      }

      // Check access.
      if (!$entity->access('view', $this->currentUser)) {
        return [];
      }

      $context = [
        'type' => $entity_type,
        'id' => $entity_id,
        'label' => $entity->label(),
      ];

      // Add node-specific data.
      if ($entity_type === 'node') {
        $context['bundle'] = $entity->bundle();
        $context['status'] = $entity->isPublished() ? 'published' : 'unpublished';
        $context['created'] = $entity->getCreatedTime();
        $context['changed'] = $entity->getChangedTime();

        // Collect taxonomy terms.
        $context['taxonomies'] = $this->collectNodeTaxonomies($entity);
      }

      return $context;
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Collects taxonomy terms from a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Array of taxonomy term names grouped by vocabulary.
   */
  protected function collectNodeTaxonomies($node): array {
    $taxonomies = [];

    foreach ($node->getFields() as $field_name => $field) {
      if ($field->getFieldDefinition()->getType() === 'entity_reference') {
        $target_type = $field->getFieldDefinition()->getSetting('target_type');
        
        if ($target_type === 'taxonomy_term') {
          foreach ($field->referencedEntities() as $term) {
            $vocabulary = $term->bundle();
            if (!isset($taxonomies[$vocabulary])) {
              $taxonomies[$vocabulary] = [];
            }
            $taxonomies[$vocabulary][] = $term->label();
          }
        }
      }
    }

    return $taxonomies;
  }

  /**
   * Formats context as a readable string.
   *
   * @param array $context
   *   The context data.
   * @param array $context_keys
   *   Optional specific keys to include.
   *
   * @return string
   *   Formatted context string.
   */
  protected function formatContextAsString(array $context, array $context_keys = []): string {
    $lines = [];

    // Filter context if specific keys are requested.
    if (!empty($context_keys)) {
      $context = array_intersect_key($context, array_flip($context_keys));
    }

    // Format site context.
    if (!empty($context['site'])) {
      $lines[] = "Site: {$context['site']['name']}";
      if (!empty($context['site']['slogan'])) {
        $lines[] = "Slogan: {$context['site']['slogan']}";
      }
    }

    // Format entity context.
    if (!empty($context['entity'])) {
      $entity = $context['entity'];
      $lines[] = "Content: {$entity['label']} ({$entity['type']})";
      
      if (!empty($entity['bundle'])) {
        $lines[] = "Type: {$entity['bundle']}";
      }

      if (!empty($entity['taxonomies'])) {
        foreach ($entity['taxonomies'] as $vocabulary => $terms) {
          $lines[] = ucfirst($vocabulary) . ": " . implode(', ', $terms);
        }
      }
    }

    return implode("\n", $lines);
  }

  /**
   * Builds a cache key from options.
   *
   * @param array $options
   *   The options array.
   *
   * @return string
   *   The cache key.
   */
  protected function buildCacheKey(array $options): string {
    $key_parts = ['ai_context'];

    if (!empty($options['entity_type'])) {
      $key_parts[] = $options['entity_type'];
    }
    if (!empty($options['entity_id'])) {
      $key_parts[] = $options['entity_id'];
    }
    if (!empty($options['plugin'])) {
      $key_parts[] = $options['plugin'];
    }

    return implode(':', $key_parts);
  }

  /**
   * Builds cache tags from options.
   *
   * @param array $options
   *   The options array.
   *
   * @return array
   *   Array of cache tags.
   */
  protected function buildCacheTags(array $options): array {
    $tags = ['ai_context'];

    if (!empty($options['entity_type']) && !empty($options['entity_id'])) {
      $tags[] = "ai_context:{$options['entity_type']}:{$options['entity_id']}";
      $tags[] = "{$options['entity_type']}:{$options['entity_id']}";
    }

    return $tags;
  }

}

