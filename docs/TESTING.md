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

### Tester les outils MCP

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
