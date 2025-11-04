# Guide de test - AI Context Module

## Méthode 1 : Tests unitaires avec PHPUnit (Recommandé)

### Installation de PHPUnit et drupal/core-dev

Si drupal/core-dev n'est pas installé :

```bash
cd /home/wsl/workspace/drupalai
ddev composer require --dev drupal/core-dev:^11.2
```

### Lancer les tests unitaires

**IMPORTANT** : Toujours utiliser `-c web/core` pour avoir accès aux classes de test Drupal.

```bash
# Depuis la racine du projet
cd /home/wsl/workspace/drupalai

# Lancer tous les tests du module (RECOMMANDÉ)
ddev exec vendor/bin/phpunit -c web/core web/modules/custom/ai_context/tests/src/Unit/

# Lancer un test spécifique
ddev exec vendor/bin/phpunit -c web/core web/modules/custom/ai_context/tests/src/Unit/DrupalContextServiceTest.php

# Avec verbosité
ddev exec vendor/bin/phpunit -c web/core -v web/modules/custom/ai_context/tests/src/Unit/

# Avec couverture de code (nécessite xdebug)
ddev xdebug on
ddev exec vendor/bin/phpunit -c web/core --coverage-html coverage web/modules/custom/ai_context/tests/src/Unit/
ddev xdebug off

# ❌ NE PAS FAIRE (ne fonctionnera pas avec Drupal\Tests\UnitTestCase)
# ddev exec vendor/bin/phpunit web/modules/custom/ai_context/tests/src/Unit
```

**Résultat attendu :**
```
PHPUnit 11.5.43 by Sebastian Bergmann and contributors.
......                                                              6 / 6 (100%)
Time: 00:00.026, Memory: 6.00 MB
OK (6 tests, 27 assertions)
```

## Méthode 2 : Test runner Drupal (Alternatif)

### Utiliser le test runner de Drupal core

```bash
# Depuis le conteneur DDEV
ddev ssh

# Lister tous les groupes de tests
php web/core/scripts/run-tests.sh --list

# Lancer les tests par groupe (quand configuré)
php web/core/scripts/run-tests.sh --verbose --class 'Drupal\Tests\ai_context\Unit\DrupalContextServiceTest'
```

## Méthode 3 : Tests manuels (Rapide)

### Test 1 : Vérifier que le service existe

```bash
ddev drush eval "var_dump(\Drupal::hasService('ai_context.context_service'));"
```

**Résultat attendu :** `bool(true)`

### Test 2 : Tester la collecte de contexte

```bash
ddev drush eval "print_r(\Drupal::service('ai_context.context_service')->collectContext());"
```

**Résultat attendu :**
```
Array
(
    [site] => Array
        (
            [name] => Votre site
            [slogan] => Votre slogan
            [mail] => admin@example.com
        )
)
```

### Test 3 : Tester l'enrichissement de prompt

```bash
ddev drush eval "
\$service = \Drupal::service('ai_context.context_service');
\$context = ['site' => ['name' => 'Test Site', 'slogan' => 'Test Slogan']];
\$prompt = 'Write an article';
echo \$service->enrichPrompt(\$prompt, \$context);
"
```

**Résultat attendu :**
```
DRUPAL SITE CONTEXT:
Site: Test Site
Slogan: Test Slogan

USER REQUEST:
Write an article
```

### Test 4 : Tester avec un node

```bash
# Remplacer 1 par un vrai node ID
ddev drush eval "
\$service = \Drupal::service('ai_context.context_service');
\$context = \$service->collectContext([
  'entity_type' => 'node',
  'entity_id' => 1,
]);
print_r(\$context);
"
```

### Test 5 : Vérifier l'event subscriber

```bash
ddev drush eval "
\$event_dispatcher = \Drupal::service('event_dispatcher');
\$listeners = \$event_dispatcher->getListeners('kernel.request');
foreach (\$listeners as \$listener) {
  if (is_array(\$listener) && isset(\$listener[0])) {
    echo get_class(\$listener[0]) . PHP_EOL;
  }
}
" | grep CKEditor
```

**Résultat attendu :** `Drupal\ai_context\EventSubscriber\CKEditorContextSubscriber`

### Test 6 : Tester le cache

```bash
# Test 1 : Collecter (va créer le cache)
ddev drush eval "
\$service = \Drupal::service('ai_context.context_service');
\$start = microtime(true);
\$context1 = \$service->collectContext();
\$time1 = microtime(true) - \$start;
echo 'First call: ' . \$time1 . ' seconds' . PHP_EOL;
"

# Test 2 : Collecter à nouveau (devrait être plus rapide avec le cache)
ddev drush eval "
\$service = \Drupal::service('ai_context.context_service');
\$start = microtime(true);
\$context2 = \$service->collectContext();
\$time2 = microtime(true) - \$start;
echo 'Cached call: ' . \$time2 . ' seconds' . PHP_EOL;
"
```

**Résultat attendu :** Le deuxième appel devrait être significativement plus rapide.

## Test d'intégration avec CKEditor

### Test en conditions réelles

1. **Activer le module** (si pas déjà fait) :
```bash
ddev drush en ai_context -y
ddev drush cr
```

2. **Aller sur une page d'édition** avec CKEditor AI

3. **Activer le logging** :
```bash
ddev drush config:set system.logging error_level verbose -y
```

4. **Utiliser un outil AI CKEditor** (Tone, Summarize, etc.)

5. **Vérifier les logs** :
```bash
ddev drush watchdog:show --type=ai_context --count=10
```

**Résultat attendu :**
```
Context enrichment applied to CKEditor AI request for plugin: tone
```

### Test avec curl (Simulation de requête CKEditor)

```bash
ddev exec curl -X POST http://drupalai.ddev.site/api/ai-ckeditor/request/basic_html/tone \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Improve this text", "entity_type": "node", "entity_id": 1}' \
  -b "SESS..."  # Ajouter votre cookie de session
```

## Vérification de la qualité du code

### PHPCS (Drupal Coding Standards)

```bash
# Vérifier les standards de code
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/ai_context/

# Corriger automatiquement
ddev exec vendor/bin/phpcbf --standard=Drupal web/modules/custom/ai_context/
```

### PHPStan (Analyse statique)

```bash
# Si PHPStan est installé
ddev exec vendor/bin/phpstan analyse web/modules/custom/ai_context/src/
```

## Debugging

### Activer le mode debug

Dans `settings.local.php` (ou `settings.php`) :

```php
$config['system.logging']['error_level'] = 'verbose';
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
```

### Ajouter des points d'arrêt

Utilisez Xdebug :

```bash
# Activer Xdebug
ddev xdebug on

# Ajouter un breakpoint dans votre IDE
# Par exemple dans CKEditorContextSubscriber.php ligne 70

# Utiliser CKEditor AI et le debugger s'arrêtera
```

### Logs détaillés

Ajouter du logging temporaire dans le code :

```php
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

// Dans votre service
$this->logger->debug('Context collected: @context', [
  '@context' => print_r($context, TRUE),
]);
```

Puis vérifier :
```bash
ddev drush watchdog:show --type=ai_context --severity=Debug
```

## Résultats attendus

### Tests unitaires

```
PHPUnit 11.5.43 by Sebastian Bergmann and contributors.

......                                                      6 / 6 (100%)

Time: 00:00.026, Memory: 6.00 MB

OK (6 tests, 27 assertions)
```

### Tests manuels

Tous les services doivent être disponibles et les contextes collectés correctement.

### Test CKEditor

Les prompts doivent être enrichis avec le contexte Drupal automatiquement.

## Troubleshooting

### Erreur : Class not found

```bash
ddev composer dump-autoload
ddev drush cr
```

### Erreur : Service not found

```bash
ddev drush cr
ddev drush debug:container ai_context.context_service
```

### Tests qui échouent

1. Vérifier les dépendances :
```bash
ddev composer install
```

2. Vérifier la base de données :
```bash
ddev drush status
```

3. Réinstaller le module :
```bash
ddev drush pmu ai_context -y
ddev drush en ai_context -y
ddev drush cr
```

## Performance Testing

### Benchmark du cache

```bash
ddev drush eval "
\$service = \Drupal::service('ai_context.context_service');

// Warm up
\$service->collectContext();

// Benchmark sans cache
\Drupal::cache('ai')->deleteAll();
\$start = microtime(true);
for (\$i = 0; \$i < 100; \$i++) {
  \$service->collectContext();
}
\$uncached = microtime(true) - \$start;

// Benchmark avec cache
\$start = microtime(true);
for (\$i = 0; \$i < 100; \$i++) {
  \$service->collectContext();
}
\$cached = microtime(true) - \$start;

echo 'Uncached: ' . \$uncached . ' seconds' . PHP_EOL;
echo 'Cached: ' . \$cached . ' seconds' . PHP_EOL;
echo 'Speedup: ' . round(\$uncached / \$cached, 2) . 'x' . PHP_EOL;
"
```

## CI/CD Integration

Pour intégrer dans un pipeline CI/CD :

```yaml
# .github/workflows/tests.yml
- name: Run PHPUnit tests
  run: |
    ddev exec vendor/bin/phpunit -c web/modules/custom/ai_context/phpunit.xml
```

## Next Steps

Une fois les tests passés :
1. ✅ Tester en conditions réelles
2. ✅ Monitorer les performances
3. ✅ Collecter le feedback utilisateur
4. ✅ Passer à la Phase 2 du roadmap

