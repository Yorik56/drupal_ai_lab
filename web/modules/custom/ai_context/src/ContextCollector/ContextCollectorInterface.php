<?php

declare(strict_types=1);

namespace Drupal\ai_context\ContextCollector;

/**
 * Interface for context collector plugins.
 */
interface ContextCollectorInterface {

  /**
   * Collects context data.
   *
   * @param array $options
   *   Options for context collection.
   *
   * @return array
   *   Collected context data.
   */
  public function collect(array $options = []): array;

  /**
   * Gets the cache key for this collector.
   *
   * @param array $options
   *   Options for context collection.
   *
   * @return string
   *   The cache key.
   */
  public function getCacheKey(array $options = []): string;

  /**
   * Gets cache tags for this collector.
   *
   * @param array $options
   *   Options for context collection.
   *
   * @return array
   *   Array of cache tags.
   */
  public function getCacheTags(array $options = []): array;

  /**
   * Gets the maximum cache age in seconds.
   *
   * @return int
   *   Maximum cache age in seconds.
   */
  public function getCacheMaxAge(): int;

}

