<?php

declare(strict_types=1);

namespace Drupal\ai_context\ContextCollector;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Collects site configuration context.
 */
class SiteConfigCollector implements ContextCollectorInterface {

  /**
   * Constructs a SiteConfigCollector.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function collect(array $options = []): array {
    $site_config = $this->configFactory->get('system.site');

    return [
      'name' => $site_config->get('name'),
      'slogan' => $site_config->get('slogan'),
      'mail' => $site_config->get('mail'),
      'front' => $site_config->get('page.front'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKey(array $options = []): string {
    return 'ai_context:site';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(array $options = []): array {
    return ['config:system.site'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // Site config rarely changes, cache for 24 hours.
    return 86400;
  }

}

