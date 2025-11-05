# AI Context MVP - Module de contexte intelligent pour Drupal

## Description

Module Drupal qui enrichit automatiquement les requêtes vers les fournisseurs d'IA avec du contexte Drupal pertinent, éliminant les hallucinations de liens et améliorant la pertinence des générations de contenu.

## Problème résolu

Les LLMs génèrent du contenu sans connaissance du site Drupal :
- Invention de liens vers des contenus inexistants (erreurs 404)
- Suggestions non alignées avec le style éditorial du site
- Absence de cohérence avec la taxonomie existante
- Méconnaissance des contenus déjà publiés

## Solution implémentée

### Enrichissement automatique transparent

Interception des requêtes AI CKEditor via event subscriber et injection de contexte Drupal avant envoi au fournisseur.

### Contexte injecté

```
DRUPAL SITE CONTEXT:
Site: [Nom du site]
Slogan: [Slogan]

Content: [Node en cours] (node)
Type: [Content type]
Tags: [Taxonomies]

Existing content on the site (USE ONLY THESE URLS):
- [Titre 1] → [URL réelle]
- [Titre 2] → [URL réelle]
...

IMPORTANT: Only create links to URLs listed above.

USER REQUEST:
[Prompt original de l'utilisateur]
```

## Architecture

### Services

**ai_context.context_service** : Service principal de collecte et enrichissement du contexte
- `collectContext(array $options)` : Collecte le contexte selon options
- `enrichPrompt(string $prompt, array $context)` : Enrichit un prompt
- Cache via `cache.ai` pour performance optimale

### Collecteurs

**SiteConfigCollector** : Informations du site (nom, slogan, mail)

**NodeMetadataCollector** : Métadonnées des nodes (titre, type, statut, dates, auteur)

**TaxonomyCollector** : Termes de taxonomie associés aux contenus

### Event Subscriber

**CKEditorContextSubscriber** : Intercepte les requêtes `/api/ai-ckeditor/request/*` et enrichit automatiquement les prompts avant envoi au provider.

## Intégration MCP

### Plugin MCP DrupalContext

Expose le contexte et des outils avancés via le protocole Model Context Protocol pour utilisation avec Claude Desktop, Cursor, etc.

### Outils MCP disponibles

**get_current_context** : Retourne le contexte Drupal complet

**get_related_content** : Trouve du contenu similaire basé sur taxonomies

**suggest_internal_links** : Analyse le texte et suggère des liens internes réels

**analyze_content_seo** : Analyse SEO avec suggestions (titre, meta, keywords)

**get_content_style** : Analyse le style éditorial du site

### Ressource MCP

**drupal://context/site** : Ressource JSON temps réel du contexte du site

## Installation

```bash
cd /home/wsl/workspace/drupalai
ddev drush en ai_context -y
ddev drush cr
```

## Configuration

Aucune configuration requise. Le module fonctionne automatiquement dès activation.

Configuration optionnelle via `ai_context.services.yml` pour personnaliser les collecteurs.

## Utilisation

### Automatique avec CKEditor AI

Le contexte est automatiquement injecté dans toutes les requêtes CKEditor AI sans action requise.

### Via MCP

Les clients MCP peuvent appeler les outils exposés :

```javascript
// Depuis Claude Desktop ou Cursor
get_related_content({ node_id: 123, limit: 5 })
suggest_internal_links({ content: "...", max_suggestions: 3 })
analyze_content_seo({ title: "...", content: "..." })
```

### Programmatique

```php
$context_service = \Drupal::service('ai_context.context_service');
$context = $context_service->collectContext(['entity_type' => 'node', 'entity_id' => 123]);
$enriched = $context_service->enrichPrompt($prompt, $context);
```

## Performance

Cache intelligent avec tags d'invalidation :
- Site config : 24h
- Node metadata : 1h  
- Taxonomy : 6h
- Available content : 1h

Performance mesurée : < 1ms avec cache (3.5x plus rapide)

## Tests

```bash
# Tests unitaires
ddev exec vendor/bin/phpunit -c web/core web/modules/custom/ai_context/tests/src/Unit/

# Tests fonctionnels
ddev drush eval "print_r(\Drupal::service('ai_context.context_service')->collectContext());"

# Monitoring logs
ddev drush watchdog:tail
```

Résultats : 6 tests, 27 assertions, 100% pass

## Sécurité

Respect des permissions Drupal : seuls les contenus accessibles à l'utilisateur sont inclus dans le contexte.

Filtrage automatique des données sensibles via vérification d'accès.

## Extension

### Hook disponible

```php
/**
 * Implements hook_ai_context_collect_alter().
 */
function mymodule_ai_context_collect_alter(array &$context, array $options) {
  $context['custom'] = ['data' => 'value'];
}
```

### Créer un collecteur custom

Implémenter `ContextCollectorInterface` et enregistrer comme service avec tag `ai_context_collector`.

## Résultats

### Avant AI Context

- Liens inventés vers `/guide-des-bars`, `/meilleures-tapas` (404)
- Contenu générique sans lien avec le site
- Aucune suggestion de contenu existant

### Après AI Context

- Liens uniquement vers contenus réels (`/node/1`, `/node/2`, etc.)
- Suggestions basées sur taxonomies existantes
- Respect du style éditorial du site
- Analyse SEO contextuelle

## Contribution

Module développé comme POC pour démonstration de faisabilité.

Prévu pour contribution future au projet drupal/ai via issue fork sur drupal.org.

## Licence

GPL-2.0-or-later

## Références

- Module AI : https://www.drupal.org/project/ai
- Module MCP : https://www.drupal.org/project/mcp
- Documentation AI : https://project.pages.drupalcode.org/ai/

