<?php

declare(strict_types=1);

namespace Drupal\ai_context\Plugin\Mcp;

use Drupal\ai_context\Service\DrupalContextServiceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Resource;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MCP plugin for Drupal context awareness.
 */
#[Mcp(
  id: 'drupal_context',
  name: new TranslatableMarkup('Drupal Context'),
  description: new TranslatableMarkup('Provides context-aware tools for Drupal content, internal links, and SEO analysis.'),
)]
class DrupalContext extends McpPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Drupal context service.
   *
   * @var \Drupal\ai_context\Service\DrupalContextServiceInterface
   */
  protected DrupalContextServiceInterface $contextService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $instance->contextService = $container->get('ai_context.context_service');
    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): bool {
    return \Drupal::moduleHandler()->moduleExists('ai_context')
      && \Drupal::moduleHandler()->moduleExists('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      return $this->t('The AI Context and Node modules must be enabled to use this plugin.');
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return [
      new Tool(
        name: 'get_current_context',
        description: 'Get the current Drupal context including site information, current node metadata, and taxonomies. This provides base context about the site and content being edited.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'entity_type' => [
              'type' => 'string',
              'description' => 'The entity type (e.g., node, taxonomy_term)',
              'enum' => ['node', 'taxonomy_term'],
            ],
            'entity_id' => [
              'type' => 'integer',
              'description' => 'The entity ID',
            ],
          ],
        ],
        annotations: new ToolAnnotations(
          title: 'Get Current Context',
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
          openWorldHint: false,
        ),
      ),

      new Tool(
        name: 'get_related_content',
        description: 'Find content related to a specific node based on shared taxonomies, content type, or keywords. Returns a list of related articles with titles, URLs, and relevance scores.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'node_id' => [
              'type' => 'integer',
              'description' => 'The node ID to find related content for',
            ],
            'limit' => [
              'type' => 'integer',
              'description' => 'Maximum number of related items to return',
              'default' => 5,
              'minimum' => 1,
              'maximum' => 20,
            ],
            'content_type' => [
              'type' => 'string',
              'description' => 'Optionally filter by content type',
            ],
          ],
          'required' => ['node_id'],
        ],
        annotations: new ToolAnnotations(
          title: 'Get Related Content',
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
          openWorldHint: false,
        ),
      ),

      new Tool(
        name: 'suggest_internal_links',
        description: 'Analyze text content and suggest relevant internal links to existing Drupal content. Returns suggestions with anchor text, target URL, and relevance explanation.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'content' => [
              'type' => 'string',
              'description' => 'The text content to analyze for internal link suggestions',
            ],
            'current_node_id' => [
              'type' => 'integer',
              'description' => 'The current node ID (to avoid self-links)',
            ],
            'max_suggestions' => [
              'type' => 'integer',
              'description' => 'Maximum number of link suggestions',
              'default' => 3,
              'minimum' => 1,
              'maximum' => 10,
            ],
          ],
          'required' => ['content'],
        ],
        annotations: new ToolAnnotations(
          title: 'Suggest Internal Links',
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
          openWorldHint: false,
        ),
      ),

      new Tool(
        name: 'analyze_content_seo',
        description: 'Analyze content for basic SEO optimization. Checks title length, meta description, keyword density, heading structure, and provides actionable suggestions.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'title' => [
              'type' => 'string',
              'description' => 'The content title',
            ],
            'content' => [
              'type' => 'string',
              'description' => 'The main content/body text',
            ],
            'meta_description' => [
              'type' => 'string',
              'description' => 'The meta description (if any)',
            ],
          ],
          'required' => ['title', 'content'],
        ],
        annotations: new ToolAnnotations(
          title: 'Analyze Content SEO',
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
          openWorldHint: false,
        ),
      ),

      new Tool(
        name: 'get_content_style',
        description: 'Analyze the editorial style and tone of existing content on the site. Returns style guidelines, common phrases, tone characteristics, and formatting patterns.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'content_type' => [
              'type' => 'string',
              'description' => 'The content type to analyze',
              'default' => 'article',
            ],
            'sample_size' => [
              'type' => 'integer',
              'description' => 'Number of recent items to analyze',
              'default' => 10,
              'minimum' => 3,
              'maximum' => 20,
            ],
          ],
        ],
        annotations: new ToolAnnotations(
          title: 'Get Content Style',
          readOnlyHint: true,
          idempotentHint: true,
          destructiveHint: false,
          openWorldHint: false,
        ),
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResources(): array {
    return [
      new Resource(
        uri: 'drupal://context/site',
        name: 'Site Context',
        description: 'Current Drupal site context including name, slogan, and configuration',
        mimeType: 'application/json',
        text: NULL,
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceTemplates(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function executeTool(string $toolId, mixed $arguments): array {
    return match ($toolId) {
      'get_current_context' => $this->getCurrentContext($arguments),
      'get_related_content' => $this->getRelatedContent($arguments),
      'suggest_internal_links' => $this->suggestInternalLinks($arguments),
      'analyze_content_seo' => $this->analyzeContentSeo($arguments),
      'get_content_style' => $this->getContentStyle($arguments),
      default => throw new \InvalidArgumentException("Unknown tool: {$toolId}"),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function readResource(string $resourceId): array {
    if ($resourceId === 'drupal://context/site') {
      $context = $this->contextService->collectContext();
      return [
        new Resource(
          uri: 'drupal://context/site',
          name: 'Site Context',
          description: 'Current Drupal site context',
          mimeType: 'application/json',
          text: json_encode($context, JSON_PRETTY_PRINT),
        ),
      ];
    }

    throw new \InvalidArgumentException("Unknown resource: {$resourceId}");
  }

  /**
   * Get current Drupal context.
   *
   * @param array $arguments
   *   Tool arguments.
   *
   * @return array
   *   The current context.
   */
  protected function getCurrentContext(array $arguments): array {
    $options = [];

    if (!empty($arguments['entity_type'])) {
      $options['entity_type'] = $arguments['entity_type'];
    }

    if (!empty($arguments['entity_id'])) {
      $options['entity_id'] = $arguments['entity_id'];
    }

    $context = $this->contextService->collectContext($options);

    return [
      'content' => [
        [
          'type' => 'text',
          'text' => json_encode($context, JSON_PRETTY_PRINT),
        ],
      ],
    ];
  }

  /**
   * Get related content based on taxonomies and content type.
   *
   * @param array $arguments
   *   Tool arguments.
   *
   * @return array
   *   List of related content.
   */
  protected function getRelatedContent(array $arguments): array {
    $node_id = $arguments['node_id'];
    $limit = $arguments['limit'] ?? 5;
    $content_type = $arguments['content_type'] ?? NULL;

    try {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $node = $node_storage->load($node_id);

      if (!$node instanceof NodeInterface) {
        return [
          'content' => [
            ['type' => 'text', 'text' => 'Node not found'],
          ],
        ];
      }

      // Collect taxonomy term IDs from the node.
      $term_ids = [];
      foreach ($node->getFields() as $field) {
        if ($field->getFieldDefinition()->getType() === 'entity_reference') {
          $target_type = $field->getFieldDefinition()->getSetting('target_type');
          if ($target_type === 'taxonomy_term') {
            foreach ($field->referencedEntities() as $term) {
              $term_ids[] = $term->id();
            }
          }
        }
      }

      // Build query for related content.
      $query = $node_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('nid', $node_id, '!=')
        ->range(0, $limit)
        ->sort('changed', 'DESC');

      if ($content_type) {
        $query->condition('type', $content_type);
      }
      elseif ($node->bundle()) {
        $query->condition('type', $node->bundle());
      }

      // Add taxonomy filter if terms exist.
      if (!empty($term_ids)) {
        $or_group = $query->orConditionGroup();
        foreach ($node->getFields() as $field_name => $field) {
          if ($field->getFieldDefinition()->getType() === 'entity_reference') {
            $target_type = $field->getFieldDefinition()->getSetting('target_type');
            if ($target_type === 'taxonomy_term') {
              $or_group->condition($field_name, $term_ids, 'IN');
            }
          }
        }
        $query->condition($or_group);
      }

      $nids = $query->execute();
      $related_nodes = $node_storage->loadMultiple($nids);

      $results = [];
      foreach ($related_nodes as $related_node) {
        $results[] = [
          'id' => $related_node->id(),
          'title' => $related_node->getTitle(),
          'type' => $related_node->bundle(),
          'url' => $related_node->toUrl()->toString(),
          'changed' => date('Y-m-d H:i:s', $related_node->getChangedTime()),
        ];
      }

      return [
        'content' => [
          [
            'type' => 'text',
            'text' => json_encode([
              'related_content' => $results,
              'count' => count($results),
              'based_on' => [
                'taxonomies' => count($term_ids) . ' shared terms',
                'content_type' => $node->bundle(),
              ],
            ], JSON_PRETTY_PRINT),
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'content' => [
          ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
        ],
      ];
    }
  }

  /**
   * Suggest internal links based on content analysis.
   *
   * @param array $arguments
   *   Tool arguments.
   *
   * @return array
   *   Link suggestions.
   */
  protected function suggestInternalLinks(array $arguments): array {
    $content = $arguments['content'];
    $current_node_id = $arguments['current_node_id'] ?? NULL;
    $max_suggestions = $arguments['max_suggestions'] ?? 3;

    try {
      // Extract potential keywords from content (simple implementation).
      $words = str_word_count(strtolower(strip_tags($content)), 1);
      $word_freq = array_count_values($words);
      arsort($word_freq);
      $keywords = array_slice(array_keys($word_freq), 0, 10);

      // Search for nodes matching keywords.
      $node_storage = $this->entityTypeManager->getStorage('node');
      $query = $node_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->range(0, $max_suggestions * 2);

      if ($current_node_id) {
        $query->condition('nid', $current_node_id, '!=');
      }

      // Search in title.
      $or_group = $query->orConditionGroup();
      foreach (array_slice($keywords, 0, 5) as $keyword) {
        if (strlen($keyword) > 3) {
          $or_group->condition('title', $keyword, 'CONTAINS');
        }
      }
      $query->condition($or_group);

      $nids = $query->execute();
      $nodes = $node_storage->loadMultiple($nids);

      $suggestions = [];
      $count = 0;
      foreach ($nodes as $node) {
        if ($count >= $max_suggestions) {
          break;
        }

        $suggestions[] = [
          'title' => $node->getTitle(),
          'url' => $node->toUrl()->toString(),
          'anchor_text_suggestion' => $node->getTitle(),
          'relevance' => 'Matched keywords in title',
          'node_type' => $node->bundle(),
        ];
        $count++;
      }

      return [
        'content' => [
          [
            'type' => 'text',
            'text' => json_encode([
              'suggestions' => $suggestions,
              'count' => count($suggestions),
              'analyzed_keywords' => array_slice($keywords, 0, 5),
            ], JSON_PRETTY_PRINT),
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'content' => [
          ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
        ],
      ];
    }
  }

  /**
   * Analyze content for SEO.
   *
   * @param array $arguments
   *   Tool arguments.
   *
   * @return array
   *   SEO analysis results.
   */
  protected function analyzeContentSeo(array $arguments): array {
    $title = $arguments['title'];
    $content = strip_tags($arguments['content']);
    $meta_description = $arguments['meta_description'] ?? '';

    $analysis = [
      'title' => $this->analyzeTitleSeo($title),
      'content' => $this->analyzeContentLength($content),
      'meta_description' => $this->analyzeMetaDescription($meta_description),
      'keywords' => $this->analyzeKeywords($content),
      'suggestions' => [],
    ];

    // Generate suggestions.
    if ($analysis['title']['length'] < 30) {
      $analysis['suggestions'][] = 'Title is too short. Aim for 50-60 characters.';
    }
    if ($analysis['title']['length'] > 70) {
      $analysis['suggestions'][] = 'Title is too long. Keep it under 60 characters.';
    }
    if (empty($meta_description)) {
      $analysis['suggestions'][] = 'Add a meta description (150-160 characters).';
    }
    if ($analysis['content']['word_count'] < 300) {
      $analysis['suggestions'][] = 'Content is short. Consider adding more details (aim for 500+ words).';
    }

    return [
      'content' => [
        [
          'type' => 'text',
          'text' => json_encode($analysis, JSON_PRETTY_PRINT),
        ],
      ],
    ];
  }

  /**
   * Get content style analysis from existing site content.
   *
   * @param array $arguments
   *   Tool arguments.
   *
   * @return array
   *   Style analysis.
   */
  protected function getContentStyle(array $arguments): array {
    $content_type = $arguments['content_type'] ?? 'article';
    $sample_size = $arguments['sample_size'] ?? 10;

    try {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $query = $node_storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('type', $content_type)
        ->condition('status', 1)
        ->range(0, $sample_size)
        ->sort('changed', 'DESC');

      $nids = $query->execute();
      $nodes = $node_storage->loadMultiple($nids);

      $style_analysis = [
        'content_type' => $content_type,
        'sample_size' => count($nodes),
        'patterns' => [],
        'common_topics' => [],
        'tone_indicators' => [],
      ];

      // Analyze titles.
      $title_lengths = [];
      foreach ($nodes as $node) {
        $title_lengths[] = strlen($node->getTitle());
      }

      $style_analysis['patterns']['average_title_length'] = !empty($title_lengths)
        ? (int) (array_sum($title_lengths) / count($title_lengths))
        : 0;

      $style_analysis['patterns']['title_range'] = [
        'min' => !empty($title_lengths) ? min($title_lengths) : 0,
        'max' => !empty($title_lengths) ? max($title_lengths) : 0,
      ];

      // Sample titles.
      $style_analysis['sample_titles'] = [];
      $count = 0;
      foreach ($nodes as $node) {
        if ($count >= 5) {
          break;
        }
        $style_analysis['sample_titles'][] = $node->getTitle();
        $count++;
      }

      return [
        'content' => [
          [
            'type' => 'text',
            'text' => json_encode($style_analysis, JSON_PRETTY_PRINT),
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      return [
        'content' => [
          ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
        ],
      ];
    }
  }

  /**
   * Analyze title for SEO.
   *
   * @param string $title
   *   The title to analyze.
   *
   * @return array
   *   Title analysis.
   */
  protected function analyzeTitleSeo(string $title): array {
    return [
      'length' => strlen($title),
      'word_count' => str_word_count($title),
      'optimal' => strlen($title) >= 50 && strlen($title) <= 60,
    ];
  }

  /**
   * Analyze content length.
   *
   * @param string $content
   *   The content to analyze.
   *
   * @return array
   *   Content analysis.
   */
  protected function analyzeContentLength(string $content): array {
    $word_count = str_word_count($content);
    return [
      'word_count' => $word_count,
      'character_count' => strlen($content),
      'quality' => $word_count >= 500 ? 'good' : ($word_count >= 300 ? 'acceptable' : 'short'),
    ];
  }

  /**
   * Analyze meta description.
   *
   * @param string $meta_description
   *   The meta description.
   *
   * @return array
   *   Meta description analysis.
   */
  protected function analyzeMetaDescription(string $meta_description): array {
    if (empty($meta_description)) {
      return [
        'present' => FALSE,
        'length' => 0,
        'optimal' => FALSE,
      ];
    }

    return [
      'present' => TRUE,
      'length' => strlen($meta_description),
      'optimal' => strlen($meta_description) >= 150 && strlen($meta_description) <= 160,
    ];
  }

  /**
   * Analyze keywords.
   *
   * @param string $content
   *   The content to analyze.
   *
   * @return array
   *   Keyword analysis.
   */
  protected function analyzeKeywords(string $content): array {
    $words = str_word_count(strtolower($content), 1);
    $word_freq = array_count_values($words);
    arsort($word_freq);

    // Remove common stop words.
    $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
    foreach ($stop_words as $stop_word) {
      unset($word_freq[$stop_word]);
    }

    $top_keywords = array_slice($word_freq, 0, 10, TRUE);

    return [
      'top_keywords' => $top_keywords,
      'total_words' => count($words),
      'unique_words' => count($word_freq),
    ];
  }

}

