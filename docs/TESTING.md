# Guide de test - AI Context

## Tests unitaires PHPUnit

### Installation

```bash
ddev composer require --dev drupal/core-dev:^11.2
```

### Exécution

Utiliser `-c web/core` pour accéder aux classes de test Drupal :

```bash
# Tous les tests
ddev exec vendor/bin/phpunit -c web/core web/modules/custom/ai_context/tests/src/Unit/

# Test spécifique
ddev exec vendor/bin/phpunit -c web/core web/modules/custom/ai_context/tests/src/Unit/DrupalContextServiceTest.php

# Avec couverture (nécessite xdebug)
ddev xdebug on
ddev exec vendor/bin/phpunit -c web/core --coverage-html coverage web/modules/custom/ai_context/tests/src/Unit/
ddev xdebug off
```

Résultat attendu :
```
PHPUnit 11.5.43
......  6 / 6 (100%)
OK (6 tests, 27 assertions)
```

## Tests fonctionnels

### Vérifier le service

```bash
ddev drush eval "var_dump(\Drupal::hasService('ai_context.context_service'));"
```

### Tester la collecte de contexte

```bash
ddev drush eval "print_r(\Drupal::service('ai_context.context_service')->collectContext());"
```

### Tester l'enrichissement

```bash
ddev drush eval "\$s = \Drupal::service('ai_context.context_service'); \$c = ['site' => ['name' => 'Test']]; echo \$s->enrichPrompt('Write text', \$c);"
```

### Vérifier tous les services

```bash
ddev drush eval "foreach (['ai_context.context_service', 'ai_context.ckeditor_subscriber'] as \$srv) { echo \$srv . ': ' . (\Drupal::hasService(\$srv) ? 'OK' : 'FAIL') . PHP_EOL; }"
```

### Tester la performance du cache

```bash
ddev drush eval "\$s = \Drupal::service('ai_context.context_service'); \$t1 = microtime(true); \$s->collectContext(); echo 'Time: ' . round((microtime(true)-\$t1)*1000, 2) . 'ms';"
```

## Tests d'intégration CKEditor

### Monitoring en temps réel

```bash
ddev drush watchdog:tail
```

### Procédure de test

1. Créer ou éditer un article avec CKEditor
2. Utiliser un outil AI (Tone, Summarize, Completion)
3. Observer les logs

Log attendu :
```
AI Context enrichment applied to CKEditor request for plugin: [nom]
```

### Vérifier les logs

```bash
ddev drush watchdog:show --count=20
```

## Tests MCP

### Lister les plugins MCP

```bash
ddev drush eval "print_r(array_keys(\Drupal::service('plugin.manager.mcp')->getDefinitions()));"
```

### Tester les outils MCP DrupalContext

```bash
# get_current_context
ddev drush eval "\$p = \Drupal::service('plugin.manager.mcp')->createInstance('drupal_context'); \$r = \$p->executeTool('get_current_context', []); echo \$r['content'][0]['text'];"

# get_related_content
ddev drush eval "\$p = \Drupal::service('plugin.manager.mcp')->createInstance('drupal_context'); \$r = \$p->executeTool('get_related_content', ['node_id' => 1, 'limit' => 5]); echo \$r['content'][0]['text'];"

# analyze_content_seo
ddev drush eval "\$p = \Drupal::service('plugin.manager.mcp')->createInstance('drupal_context'); \$r = \$p->executeTool('analyze_content_seo', ['title' => 'Test', 'content' => 'Test content for SEO analysis.']); echo \$r['content'][0]['text'];"
```

### Tester la ressource MCP

```bash
ddev drush eval "\$p = \Drupal::service('plugin.manager.mcp')->createInstance('drupal_context'); \$r = \$p->readResource('drupal://context/site'); echo \$r[0]->text;"
```

## Tests Search API (Phase 3)

### Vérifier installation Search API

```bash
ddev drush pm:list | grep search_api
```

### Lister les index Search API

```bash
ddev drush search-api:list
```

### Reindexer le contenu

```bash
ddev drush search-api:index
```

### Tester l'outil MCP SearchApiContent

```bash
# Recherche simple
ddev drush eval "\$p = \Drupal::service('plugin.manager.mcp')->createInstance('search_api_content'); \$r = \$p->executeTool('search_drupal_content', ['query' => 'restaurant', 'limit' => 5]); echo \$r['content'][0]['text'];"

# Recherche avec filtres
ddev drush eval "\$p = \Drupal::service('plugin.manager.mcp')->createInstance('search_api_content'); \$r = \$p->executeTool('search_drupal_content', ['query' => 'gastronomie', 'content_types' => ['article'], 'limit' => 3]); echo \$r['content'][0]['text'];"
```

### Vérifier performance de recherche

```bash
# Mesurer le temps de recherche
ddev drush eval "\$start = microtime(true); \$index = \Drupal\search_api\Entity\Index::load('content'); \$query = \$index->query(); \$query->keys('test'); \$results = \$query->execute(); echo 'Time: ' . round((microtime(true) - \$start) * 1000, 2) . 'ms, Results: ' . \$results->getResultCount();"
```

## Tests Mode MCP Full (Phase 3.2)

### Activer le mode MCP Full

```bash
ddev drush config:set ai_context.settings mcp_mode full -y
ddev drush cr
```

### Simuler un appel CKEditor avec function calling

```bash
# Tester que les tools sont exposés
ddev drush eval "
\$input = new \Drupal\ai\OperationType\Chat\ChatInput([
  new \Drupal\ai\OperationType\Chat\ChatMessage('user', 'Write about Portuguese restaurants')
]);
// Vérifier que setChatTools() est appelé dans le subscriber
print_r(\$input->getChatTools());
"
```

### Vérifier les logs de tool calls

```bash
ddev drush watchdog:tail --filter=ai_context
```

Rechercher les logs :
```
✅ MCP Full mode: Tools exposed to OpenAI
✅ OpenAI tool_call received: search_drupal_content
✅ Executing MCP tool: search_drupal_content
✅ Tool results returned to OpenAI
```

## Tests Mode MCP Direct (Phase 3.2)

### Activer le mode MCP Direct

```bash
ddev drush config:set ai_context.settings mcp_mode direct -y
ddev drush cr
```

### Vérifier l'appel systématique

```bash
ddev drush watchdog:tail --filter=ai_context
```

Rechercher les logs :
```
✅ MCP Direct mode: Calling search_drupal_content with user prompt
✅ Search results added to context
✅ Single call to OpenAI with enriched context
```

## Benchmarks comparatifs

### Mesurer les tokens utilisés

```bash
# Mode Full
ddev drush eval "
\$config = \Drupal::configFactory()->getEditable('ai_context.settings');
\$config->set('mcp_mode', 'full')->save();
// Faire un appel CKEditor et mesurer
echo 'Tokens Mode Full: ' . \$tokens;
"

# Mode Direct
ddev drush eval "
\$config = \Drupal::configFactory()->getEditable('ai_context.settings');
\$config->set('mcp_mode', 'direct')->save();
// Faire un appel CKEditor et mesurer
echo 'Tokens Mode Direct: ' . \$tokens;
"
```

### Mesurer la latence

```bash
# Mode Full (2 requêtes)
time curl -X POST "http://drupalai.ddev.site/api/ai-ckeditor/request/..."

# Mode Direct (1 requête)
time curl -X POST "http://drupalai.ddev.site/api/ai-ckeditor/request/..."
```

## Standards de code

### PHPCS

```bash
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/ai_context/
```

### PHPStan

```bash
ddev exec vendor/bin/phpstan analyse web/modules/custom/ai_context/src/
```

## Debugging

### Activer Xdebug

```bash
ddev xdebug on
# Ajouter breakpoints dans l'IDE
# Utiliser CKEditor AI
ddev xdebug off
```

### Logs détaillés

```bash
ddev drush config:set system.logging error_level verbose -y
ddev drush cr
```

## Troubleshooting

### Service not found

```bash
ddev drush cr
ddev composer dump-autoload
```

### Tests qui échouent

```bash
ddev drush pmu ai_context -y
ddev drush en ai_context -y
ddev drush cr
```

### Contexte non appliqué

Vérifier que l'event subscriber est enregistré :

```bash
ddev drush eval "\$listeners = \Drupal::service('event_dispatcher')->getListeners('kernel.request'); foreach (\$listeners as \$l) { if (is_array(\$l) && isset(\$l[0])) { \$class = get_class(\$l[0]); if (strpos(\$class, 'CKEditor') !== false) { echo \$class . PHP_EOL; } } }"
```

Résultat attendu : `Drupal\ai_context\EventSubscriber\CKEditorContextSubscriber`
