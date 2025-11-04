# Installation Guide - AI Context Module

## Quick Start

### 1. Enable the module

```bash
cd /home/wsl/workspace/drupalai
ddev drush en ai_context -y
```

### 2. Clear cache

```bash
ddev drush cr
```

### 3. Verify installation

```bash
ddev drush pml | grep ai_context
```

You should see:
```
AI Context (ai_context)     Enabled
```

## Testing the Module

### Test with CKEditor

1. Go to any content edit form with CKEditor enabled
2. Use any AI CKEditor tool (Tone, Summarize, Completion, etc.)
3. The AI will now receive Drupal context automatically

### View logs

```bash
ddev drush watchdog:show --type=ai_context
```

## Manual Testing

Create a test script in `web/test_ai_context.php`:

```php
<?php

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once 'autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();

// Get the context service.
$context_service = $container->get('ai_context.context_service');

// Test 1: Collect site context.
echo "Test 1: Site Context\n";
$context = $context_service->collectContext();
print_r($context);

// Test 2: Enrich a prompt.
echo "\nTest 2: Enriched Prompt\n";
$prompt = "Write a blog post about Drupal.";
$enriched = $context_service->enrichPrompt($prompt, $context);
echo $enriched . "\n";

// Test 3: Collect node context (replace 1 with an actual node ID).
echo "\nTest 3: Node Context\n";
$node_context = $context_service->collectContext([
  'entity_type' => 'node',
  'entity_id' => 1,
]);
print_r($node_context);
```

Run the test:
```bash
ddev exec php web/test_ai_context.php
```

## Unit Tests

Run the unit tests:

```bash
ddev exec ../vendor/bin/phpunit web/modules/custom/ai_context/tests/src/Unit
```

Expected output:
```
PHPUnit 9.x.x

.....                                                       5 / 5 (100%)

Time: 00:00.123, Memory: 10.00 MB

OK (5 tests, 10 assertions)
```

## Troubleshooting

### Module not found

If you get "Module ai_context could not be found":

```bash
ddev drush cr
ddev composer dump-autoload
```

### Service not found

If you get "Service ai_context.context_service not found":

1. Check that the module is enabled:
```bash
ddev drush pml | grep ai_context
```

2. Rebuild cache:
```bash
ddev drush cr
```

3. Check service definition:
```bash
ddev drush debug:container ai_context.context_service
```

### No context in CKEditor

If context is not being applied to CKEditor requests:

1. Check logs:
```bash
ddev drush watchdog:show --type=ai_context
```

2. Verify the event subscriber is registered:
```bash
ddev drush debug:event
```

Look for `CKEditorContextSubscriber`.

3. Test manually:
```bash
ddev drush eval "print_r(\Drupal::service('ai_context.context_service')->collectContext());"
```

## Verify Installation

### Check Services

```bash
# Check if the main service exists
ddev drush debug:container | grep ai_context

# Should show:
# ai_context.context_service
# ai_context.ckeditor_subscriber
# ai_context.node_metadata_collector
# ai_context.site_config_collector
# ai_context.taxonomy_collector
```

### Check Event Subscribers

```bash
ddev drush debug:event kernel.request
```

Should include `CKEditorContextSubscriber`.

### Check Module Status

```bash
ddev drush pm:list --type=module --status=enabled | grep ai_context
```

## Performance

The module uses the `cache.ai` bin for caching. Monitor cache performance:

```bash
# View cache statistics
ddev drush eval "print_r(\Drupal::cache('ai')->getMultiple(['ai_context:site']));"
```

## Next Steps

1. Test with different content types
2. Test with taxonomy terms
3. Monitor logs for any issues
4. Configure cache settings if needed
5. Read the [README.md](README.md) for usage examples
6. Check the [roadmap](../../../docs/roadmap.md) for upcoming features

## Uninstallation

To remove the module:

```bash
# Disable the module
ddev drush pmu ai_context -y

# Clear cache
ddev drush cr
```

Note: Uninstalling will clear all cached context data from the `cache.ai` bin.

## Support

- Check logs: `ddev drush watchdog:show --type=ai_context`
- View recent errors: `ddev drush watchdog:show --severity=Error`
- Debug mode: Add breakpoints in `src/EventSubscriber/CKEditorContextSubscriber.php`

