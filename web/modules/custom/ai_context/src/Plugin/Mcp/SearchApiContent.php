<?php

declare(strict_types=1);

namespace Drupal\ai_context\Plugin\Mcp;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mcp\Attribute\Mcp;
use Drupal\mcp\Plugin\McpPluginBase;
use Drupal\mcp\ServerFeatures\Tool;
use Drupal\mcp\ServerFeatures\ToolAnnotations;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MCP plugin for Search API integration.
 */
#[Mcp(
  id: 'search_api_content',
  name: new TranslatableMarkup('Search API Content'),
  description: new TranslatableMarkup('Provides intelligent full-text search capabilities using Search API with scoring, stemming, and relevance ranking.'),
)]
class SearchApiContent extends McpPluginBase implements ContainerFactoryPluginInterface {

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

    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(): bool {
    return \Drupal::moduleHandler()->moduleExists('search_api')
      && \Drupal::moduleHandler()->moduleExists('node');
  }

  /**
   * {@inheritdoc}
   */
  public function getRequirementsDescription(): string {
    if (!$this->checkRequirements()) {
      return $this->t('The Search API and Node modules must be enabled to use this plugin.');
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return [
      new Tool(
        name: 'search_drupal_content',
        description: 'Search for Drupal content using full-text search with intelligent scoring, stemming, and relevance ranking. This is the recommended way to find content when you need to suggest internal links or find related articles. Returns results with titles, URLs, excerpts, and relevance scores.',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'query' => [
              'type' => 'string',
              'description' => 'The search query. Can be keywords, phrases, or full sentences. The search engine will apply stemming, stop words removal, and relevance scoring automatically.',
            ],
            'content_types' => [
              'type' => 'array',
              'description' => 'Optional array of content type machine names to filter results (e.g., ["article", "page"]). If not provided, searches all content types.',
              'items' => [
                'type' => 'string',
              ],
            ],
            'limit' => [
              'type' => 'integer',
              'description' => 'Maximum number of results to return',
              'default' => 10,
              'minimum' => 1,
              'maximum' => 50,
            ],
            'fields' => [
              'type' => 'array',
              'description' => 'Optional array of field names to include in results. If not provided, returns title, URL, and excerpt.',
              'items' => [
                'type' => 'string',
              ],
            ],
          ],
          'required' => ['query'],
        ],
        annotations: new ToolAnnotations(
          title: 'Search Drupal Content',
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
    return [];
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
    if ($toolId === 'search_drupal_content') {
      return $this->searchDrupalContent($arguments);
    }

    throw new \InvalidArgumentException("Unknown tool: {$toolId}");
  }

  /**
   * {@inheritdoc}
   */
  public function readResource(string $resourceId): array {
    throw new \InvalidArgumentException("Unknown resource: {$resourceId}");
  }

  /**
   * Search Drupal content using Search API.
   *
   * @param array $arguments
   *   Tool arguments.
   *
   * @return array
   *   Search results.
   */
  protected function searchDrupalContent(array $arguments): array {
    $query = $arguments['query'] ?? '';
    $content_types = $arguments['content_types'] ?? [];
    $limit = $arguments['limit'] ?? 10;
    $fields = $arguments['fields'] ?? ['title', 'url', 'excerpt'];

    if (empty($query)) {
      return [
        'content' => [
          ['type' => 'text', 'text' => 'Error: Query parameter is required'],
        ],
      ];
    }

    try {
      // Load the Search API index.
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = Index::load('content');

      if (!$index) {
        return [
          'content' => [
            ['type' => 'text', 'text' => 'Error: Search API index "content" not found. Please configure Search API.'],
          ],
        ];
      }

      // Create the search query.
      $search_query = $index->query();

      // Set the search keywords.
      $search_query->keys($query);

      // Add content type filter if provided.
      if (!empty($content_types)) {
        $search_query->addCondition('type', $content_types, 'IN');
      }

      // Only published content.
      $search_query->addCondition('status', 1);

      // Set limit.
      $search_query->range(0, $limit);

      // Execute the search.
      $results = $search_query->execute();

      $output = [
        'query' => $query,
        'total_results' => $results->getResultCount(),
        'results' => [],
        'performance' => [
          'search_time' => round($results->getQuery()->getOption('search api query time', 0) * 1000, 2) . 'ms',
        ],
      ];

      // Process results.
      foreach ($results->getResultItems() as $result) {
        $item = [
          'score' => round($result->getScore(), 2),
        ];

        // Get the original entity.
        $entity = $result->getOriginalObject()->getValue();

        if ($entity) {
          // Add requested fields.
          foreach ($fields as $field_name) {
            switch ($field_name) {
              case 'title':
                $item['title'] = $entity->label();
                break;

              case 'url':
                $item['url'] = $entity->toUrl()->toString();
                break;

              case 'excerpt':
                $item['excerpt'] = $result->getExcerpt() ?? $this->generateExcerpt($entity, $query);
                break;

              case 'type':
                $item['content_type'] = $entity->bundle();
                break;

              case 'created':
                $item['created'] = date('Y-m-d H:i:s', $entity->getCreatedTime());
                break;

              case 'changed':
                $item['changed'] = date('Y-m-d H:i:s', $entity->getChangedTime());
                break;

              default:
                // Custom field.
                if ($entity->hasField($field_name) && !$entity->get($field_name)->isEmpty()) {
                  $item[$field_name] = $entity->get($field_name)->getString();
                }
            }
          }
        }

        $output['results'][] = $item;
      }

      return [
        'content' => [
          [
            'type' => 'text',
            'text' => json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
   * Generate an excerpt from entity body.
   *
   * @param mixed $entity
   *   The entity.
   * @param string $query
   *   The search query.
   *
   * @return string
   *   The excerpt.
   */
  protected function generateExcerpt($entity, string $query): string {
    if (!$entity->hasField('body') || $entity->get('body')->isEmpty()) {
      return '';
    }

    $body = strip_tags($entity->get('body')->value);
    $query_words = explode(' ', strtolower($query));

    // Try to find the first occurrence of any query word.
    $position = FALSE;
    foreach ($query_words as $word) {
      if (strlen($word) > 2) {
        $position = stripos($body, $word);
        if ($position !== FALSE) {
          break;
        }
      }
    }

    // If found, get context around it.
    if ($position !== FALSE) {
      $start = max(0, $position - 100);
      $excerpt = substr($body, $start, 200);
      return ($start > 0 ? '...' : '') . $excerpt . '...';
    }

    // Otherwise, just return the beginning.
    return substr($body, 0, 200) . '...';
  }

}

