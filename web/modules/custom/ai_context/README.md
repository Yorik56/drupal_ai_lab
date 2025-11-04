# AI Context

Provides context-aware prompt generation for the AI module with Drupal content, internal links, and site metadata.

## Features

- Collects Drupal site context (name, slogan, configuration)
- Collects entity metadata (nodes, taxonomy terms)
- Automatically enriches AI prompts with relevant context
- Integrates seamlessly with AI CKEditor
- Cached for performance
- Permission-aware (respects Drupal access control)

## Installation

1. Enable the module:
```bash
drush en ai_context -y
```

2. Clear cache:
```bash
drush cr
```

## Usage

### Automatic Integration with CKEditor

Once enabled, the module automatically enriches all AI CKEditor requests with Drupal context.

When editing content with CKEditor AI tools (Tone, Summarize, etc.), the AI will receive:
- Site name and slogan
- Current content type and metadata
- Associated taxonomy terms
- Publication status

### Manual Usage in Custom Code

```php
// Get the context service.
$context_service = \Drupal::service('ai_context.context_service');

// Collect context for a specific node.
$context = $context_service->collectContext([
  'entity_type' => 'node',
  'entity_id' => 123,
]);

// Enrich a prompt with the collected context.
$original_prompt = "Write a summary of this content.";
$enriched_prompt = $context_service->enrichPrompt($original_prompt, $context);

// Use the enriched prompt with AI provider.
```

### Implementing hook_ai_context_collect_alter()

Other modules can alter the collected context:

```php
/**
 * Implements hook_ai_context_collect_alter().
 */
function mymodule_ai_context_collect_alter(array &$context, array $options) {
  // Add custom context data.
  $context['custom'] = [
    'key' => 'value',
  ];
  
  // Modify existing context.
  if (isset($context['site'])) {
    $context['site']['custom_field'] = 'custom_value';
  }
}
```

## Architecture

### Services

- **ai_context.context_service**: Main service for collecting and managing context
- **ai_context.ckeditor_subscriber**: Event subscriber for CKEditor integration

### Context Collectors

- **SiteConfigCollector**: Collects site configuration (name, slogan, mail)
- **NodeMetadataCollector**: Collects node metadata (title, type, status, dates)
- **TaxonomyCollector**: Collects taxonomy terms associated with nodes

### Caching

Context data is cached using the `cache.ai` bin with appropriate cache tags for invalidation:

- Site config: 24 hours (`ai_context:site`)
- Node metadata: 1 hour (`ai_context:node:{nid}`, `node:{nid}`)
- Taxonomy: 6 hours (`ai_context:taxonomy:{nid}`, `node:{nid}`, `taxonomy_term_list`)

## Configuration

Currently, the module works automatically without configuration. Future versions will include:

- Admin UI to enable/disable specific collectors
- Configuration for cache max-age per collector
- Security filters configuration
- Per-plugin context settings

## Requirements

- Drupal 10.4+ or Drupal 11+
- AI module (drupal/ai)
- AI CKEditor module (ai_ckeditor)

## Development

### Adding Custom Collectors

Create a new collector class implementing `ContextCollectorInterface`:

```php
namespace Drupal\mymodule\ContextCollector;

use Drupal\ai_context\ContextCollector\ContextCollectorInterface;

class MyCustomCollector implements ContextCollectorInterface {

  public function collect(array $options = []): array {
    // Collect your custom context.
    return ['my_data' => 'value'];
  }

  public function getCacheKey(array $options = []): string {
    return 'ai_context:my_custom';
  }

  public function getCacheTags(array $options = []): array {
    return ['ai_context:my_custom'];
  }

  public function getCacheMaxAge(): int {
    return 3600; // 1 hour
  }
}
```

Register it as a service:

```yaml
services:
  mymodule.my_custom_collector:
    class: Drupal\mymodule\ContextCollector\MyCustomCollector
    tags:
      - { name: ai_context_collector }
```

### Running Tests

```bash
# Unit tests
../../../vendor/bin/phpunit web/modules/custom/ai_context/tests/src/Unit

# Kernel tests
../../../vendor/bin/phpunit web/modules/custom/ai_context/tests/src/Kernel
```

## Roadmap

See `docs/roadmap.md` at the project root for the complete development roadmap.

### Phase 1 (Current)
- ✅ Core service implementation
- ✅ Basic collectors (Site, Node, Taxonomy)
- ✅ CKEditor integration
- ⏳ Unit and kernel tests
- ⏳ Documentation

### Phase 2 (Planned)
- Plugin system for collectors
- Internal links collector
- SEO metadata collector
- Menu structure collector
- Admin UI for configuration

### Phase 3 (Planned)
- AI Agents (InternalLinkAgent, SeoAgent, StyleGuideAgent)
- Advanced CKEditor integration
- MCP (Model Context Protocol) support

## Contributing

This module is currently in active development. Contributions are welcome!

1. Test the module in your environment
2. Report issues and feedback
3. Submit patches or pull requests

See `docs/contribution.md` for contribution guidelines.

## License

GPL-2.0-or-later

## Maintainers

Current maintainer: [Your Name]

## Support

- Issue queue: [Link to issue queue]
- Documentation: See `docs/` directory
- Related project: https://www.drupal.org/project/ai

