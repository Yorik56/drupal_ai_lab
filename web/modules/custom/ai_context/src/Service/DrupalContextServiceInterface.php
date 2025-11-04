<?php

declare(strict_types=1);

namespace Drupal\ai_context\Service;

/**
 * Interface for Drupal context service.
 */
interface DrupalContextServiceInterface {

  /**
   * Collects Drupal context based on provided options.
   *
   * @param array $options
   *   Options for context collection. May include:
   *   - entity_type: The entity type (e.g., 'node', 'taxonomy_term').
   *   - entity_id: The entity ID.
   *   - plugin: The AI plugin ID requesting context.
   *   - collectors: Array of specific collector IDs to use.
   *
   * @return array
   *   Array of collected context data.
   */
  public function collectContext(array $options = []): array;

  /**
   * Enriches a prompt with Drupal context.
   *
   * @param string $prompt
   *   The original prompt.
   * @param array $context
   *   The context data to inject.
   * @param array $context_keys
   *   Optional array of specific context keys to include.
   *
   * @return string
   *   The enriched prompt.
   */
  public function enrichPrompt(string $prompt, array $context, array $context_keys = []): string;

  /**
   * Gets cached context data.
   *
   * @param string $cache_key
   *   The cache key.
   *
   * @return array|null
   *   The cached context data or NULL if not found.
   */
  public function getCachedContext(string $cache_key): ?array;

  /**
   * Sets context data in cache.
   *
   * @param string $cache_key
   *   The cache key.
   * @param array $context
   *   The context data to cache.
   * @param array $cache_tags
   *   Cache tags for invalidation.
   * @param int $max_age
   *   Maximum age in seconds.
   */
  public function setCachedContext(string $cache_key, array $context, array $cache_tags = [], int $max_age = 3600): void;

}

