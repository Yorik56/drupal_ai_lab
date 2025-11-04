# Roadmap : Context-aware prompt generation

## Issue de référence

**Issue** : Context-aware prompt generation (Drupal content, internal links, styles)

**Projet** : AI (Artificial Intelligence)

**Version cible** : 2.0.x-dev

**Priorité** : Major

**Composant** : AI Core

**Status** : GO avec conditions

## Conditions préalables

- [ ] Implémenter par phases (MVP d'abord)
- [ ] Documenter l'impact performance
- [ ] Tests automatisés obligatoires
- [ ] Filtrage de sécurité dès le début
- [ ] Alignement avec l'issue #3492940 (ChatConsumer) pour les agents

## Phase 1 : MVP (Base)

### Service principal

- [ ] Créer l'interface `DrupalContextServiceInterface` dans `src/Service/`
- [ ] Implémenter `DrupalContextService` avec méthodes :
  - [ ] `collectContext(array $options = []): array`
  - [ ] `enrichPrompt(string $prompt, array $context_keys = []): string`
  - [ ] `getCachedContext(string $cache_key): ?array`
- [ ] Déclarer le service `ai.drupal_context` dans `ai.services.yml`
- [ ] Ajouter les arguments nécessaires (cache.ai, entity_type.manager, etc.)

### Collecteurs de base

- [ ] Créer `src/ContextCollector/SiteConfigCollector.php`
  - [ ] Récupérer nom du site
  - [ ] Récupérer slogan
  - [ ] Récupérer configuration de base
- [ ] Créer `src/ContextCollector/NodeMetadataCollector.php`
  - [ ] Récupérer titre, type, statut
  - [ ] Récupérer dates (création, modification)
  - [ ] Récupérer auteur
- [ ] Créer `src/ContextCollector/TaxonomyCollector.php`
  - [ ] Récupérer termes associés
  - [ ] Récupérer vocabulaires
  - [ ] Récupérer hiérarchies

### Hook pour enrichissement

- [ ] Créer hook `hook_ai_prompt_alter(&$prompt, $context)`
- [ ] Documenter le hook dans `ai.api.php`
- [ ] Implémenter l'intégration dans le prompt manager existant

### Système de cache

- [ ] Utiliser le bin `cache.ai` existant
- [ ] Implémenter le cache pour chaque collector
- [ ] Créer les cache tags appropriés :
  - [ ] `ai_context:site`
  - [ ] `ai_context:node:{nid}`
  - [ ] `ai_context:taxonomy:{tid}`
- [ ] Implémenter l'invalidation du cache

### Tests Phase 1

- [ ] Tests unitaires pour `DrupalContextService`
- [ ] Tests unitaires pour chaque collector
- [ ] Tests d'intégration pour l'enrichissement de prompts
- [ ] Tests de performance pour le caching
- [ ] Tests de cache invalidation

### Documentation Phase 1

- [ ] Documenter l'API du service dans PHPDoc
- [ ] Créer un exemple d'utilisation basique
- [ ] Documenter le hook dans le developer guide
- [ ] Ajouter des exemples dans `docs/examples/`

### Intégration AI CKEditor (Phase 1)

- [ ] Créer un event subscriber pour `ai_ckeditor.pre_request`
  - [ ] Intercepter les requêtes dans `AiRequest::doRequest()`
  - [ ] Injecter le service `ai.drupal_context`
- [ ] Enrichir le prompt avant l'envoi au provider (ligne 140-146)
  - [ ] Détecter le contexte d'édition (entity type, bundle, field)
  - [ ] Collecter le contexte Drupal pertinent
  - [ ] Ajouter le contexte au system prompt ou user prompt
- [ ] Gérer les données du formulaire d'édition
  - [ ] Extraire l'entity en cours d'édition depuis le `Request`
  - [ ] Collecter métadonnées de l'entity (titre, type, taxonomies)
  - [ ] Filtrer selon permissions de l'utilisateur actuel
- [ ] Configuration par plugin CKEditor
  - [ ] Permettre d'activer/désactiver le contexte par plugin
  - [ ] Configurer quels collecteurs utiliser par plugin
  - [ ] Ajouter options dans `AiCKEditorPluginBase`

### Tests intégration CKEditor (Phase 1)

- [ ] Tests du subscriber de pré-requête
- [ ] Tests de l'enrichissement de prompts dans CKEditor
- [ ] Tests avec différents plugins CKEditor (Tone, Summarize, etc.)
- [ ] Tests de permissions et filtrage de sécurité
- [ ] Tests de performance avec contexte injecté

### Documentation intégration CKEditor (Phase 1)

- [ ] Documenter l'event `ai_ckeditor.pre_request`
- [ ] Exemples de contexte injecté dans CKEditor
- [ ] Guide de configuration par plugin CKEditor
- [ ] Screenshots de l'amélioration avec/sans contexte

## Phase 2 : Extension (Plugin System)

### Architecture de plugins

- [ ] Créer l'attribut `#[ContextCollector]` dans `src/Attribute/`
  - [ ] Paramètres : id, label, description, weight
- [ ] Créer `ContextCollectorInterface` dans `src/Plugin/`
  - [ ] Méthode `collect(array $options): array`
  - [ ] Méthode `getCacheKey(): string`
  - [ ] Méthode `getCacheTags(): array`
  - [ ] Méthode `getCacheMaxAge(): int`
- [ ] Créer `ContextCollectorBase` dans `src/Base/`
  - [ ] Implémenter `ConfigurableInterface`
  - [ ] Implémenter `PluginFormInterface`
  - [ ] Fournir les méthodes communes
- [ ] Créer `ContextCollectorPluginManager` dans `src/PluginManager/`
- [ ] Déclarer le plugin manager dans `ai.services.yml`

### Migration des collecteurs existants

- [ ] Migrer `SiteConfigCollector` vers plugin system
- [ ] Migrer `NodeMetadataCollector` vers plugin system
- [ ] Migrer `TaxonomyCollector` vers plugin system

### Nouveaux collecteurs

- [ ] Créer `InternalLinksCollector`
  - [ ] Analyser les relations entre nodes
  - [ ] Récupérer les liens entrants/sortants
  - [ ] Calculer la pertinence des liens
  - [ ] Implémenter le caching agressif
- [ ] Créer `SeoMetadataCollector`
  - [ ] Récupérer meta descriptions
  - [ ] Récupérer mots-clés
  - [ ] Récupérer données schema.org si disponibles
  - [ ] Intégration avec module SEO si présent
- [ ] Créer `MenuStructureCollector`
  - [ ] Récupérer structure de menu
  - [ ] Identifier la position du node dans le menu
  - [ ] Récupérer les éléments frères/parents

### Filtrage de sécurité

- [ ] Créer `ContextSecurityFilter` dans `src/Security/`
- [ ] Implémenter vérification des permissions Drupal
  - [ ] Filtrer les nodes selon `node.view`
  - [ ] Filtrer les termes selon permissions vocabulaire
  - [ ] Filtrer les données utilisateurs sensibles
- [ ] Créer une liste de champs sensibles à exclure par défaut
- [ ] Permettre la configuration des filtres via settings
- [ ] Ajouter un événement `ContextSecurityEvent` pour altérer

### Index de relations (optionnel)

- [ ] Évaluer la pertinence d'un nouvel index
- [ ] Si pertinent : créer une table `ai_context_links`
  - [ ] Colonnes : source_nid, target_nid, link_type, weight
  - [ ] Index sur source_nid et target_nid
- [ ] Implémenter le populate de l'index
- [ ] Créer un hook_update pour créer la table
- [ ] Implémenter la mise à jour lors de la modification de nodes

### Configuration UI

- [ ] Créer un formulaire de configuration `ai.context_settings`
- [ ] Permettre d'activer/désactiver les collecteurs
- [ ] Configurer le cache max age par collector
- [ ] Configurer les filtres de sécurité
- [ ] Ajouter une page de routing dans `ai.routing.yml`

### Tests Phase 2

- [ ] Tests du plugin system
- [ ] Tests de chaque nouveau collector
- [ ] Tests du filtrage de sécurité
- [ ] Tests de performance avec multiples collecteurs
- [ ] Tests d'intégration avec modules SEO existants

### Documentation Phase 2

- [ ] Guide pour créer un custom context collector
- [ ] Documentation des considérations de sécurité
- [ ] Exemples d'utilisation avancée
- [ ] Documentation de la configuration UI

## Phase 3 : AI Agents (Alignement avec #3492940)

### Prérequis

- [ ] Attendre la résolution de l'issue #3492940 (ChatConsumer)
- [ ] Valider l'architecture ChatConsumer finalisée

### Agents contextuels

- [ ] Créer `InternalLinkAgent` dans `src/Plugin/ChatConsumer/`
  - [ ] Utiliser `InternalLinksCollector`
  - [ ] Suggérer des liens internes pertinents
  - [ ] Vérifier la pertinence sémantique
  - [ ] Formater les suggestions de liens
- [ ] Créer `SeoAgent` dans `src/Plugin/ChatConsumer/`
  - [ ] Utiliser `SeoMetadataCollector`
  - [ ] Analyser l'optimisation SEO du contenu
  - [ ] Suggérer des améliorations
  - [ ] Vérifier la densité de mots-clés
- [ ] Créer `StyleGuideAgent` dans `src/Plugin/ChatConsumer/`
  - [ ] Analyser le style du contenu existant
  - [ ] Suggérer des ajustements de ton
  - [ ] Vérifier la cohérence éditoriale

### Intégration avec AI Automators

- [ ] Créer des exemples d'utilisation dans `ai_automators`
- [ ] Permettre la sélection de collecteurs par automator
- [ ] Documenter les use cases
- [ ] Créer des templates de configuration

### Intégration AI CKEditor avancée (Phase 2)

- [ ] Enrichissement contextuel par type de plugin
  - [ ] Plugin Tone : injecter exemples de ton du site existant
  - [ ] Plugin Summarize : fournir structure de résumés du site
  - [ ] Plugin Translate : fournir glossaire de termes du site
  - [ ] Plugin Completion : suggérer liens internes pertinents
- [ ] Contexte de contenu existant
  - [ ] Analyser le contenu déjà saisi dans l'éditeur
  - [ ] Identifier les entités mentionnées
  - [ ] Suggérer des liens internes automatiquement
  - [ ] Détecter les termes de taxonomie pertinents
- [ ] Interface de sélection de contexte
  - [ ] Ajouter UI pour choisir les collecteurs actifs
  - [ ] Prévisualisation du contexte qui sera envoyé
  - [ ] Indicateur visuel du contexte actif
- [ ] Optimisation des prompts CKEditor
  - [ ] Templates de prompts context-aware par plugin
  - [ ] Variables de substitution pour le contexte
  - [ ] Raccourcis pour contexte fréquent

### Tests intégration CKEditor avancée (Phase 2)

- [ ] Tests par type de plugin (Tone, Summarize, etc.)
- [ ] Tests de détection d'entités dans le contenu
- [ ] Tests de suggestions de liens internes
- [ ] Tests de l'UI de sélection de contexte
- [ ] Tests de performance avec contexte étendu

### Documentation intégration CKEditor avancée (Phase 2)

- [ ] Guide d'utilisation par plugin
- [ ] Exemples de prompts optimisés
- [ ] Vidéos de démonstration
- [ ] Best practices pour chaque type de contenu

### Intégration MCP (optionnel)

- [ ] Évaluer la pertinence de MCP (Model Context Protocol)
- [ ] Si pertinent : créer plugin MCP dans `src/Plugin/Mcp/`
- [ ] Exposer les collecteurs comme ressources MCP
- [ ] Documenter l'utilisation avec outils externes

### Tests Phase 3

- [ ] Tests des agents avec ChatConsumer
- [ ] Tests d'intégration avec ai_automators
- [ ] Tests de bout en bout de génération contextuelle
- [ ] Tests de performance avec agents actifs

### Documentation Phase 3

- [ ] Guide d'utilisation des agents contextuels
- [ ] Exemples d'intégration avec ai_automators et seo_ai
- [ ] Best practices pour la génération contextuelle
- [ ] Tutoriels vidéo ou screenshots

## Documentation transversale

### Developer Guide

- [ ] Créer section "Context-Aware Generation" dans le guide
- [ ] Documenter l'architecture du système
- [ ] Fournir des exemples de code complets
- [ ] Documenter les hooks et événements
- [ ] Ajouter des diagrammes d'architecture

### User Guide

- [ ] Expliquer les bénéfices pour les utilisateurs finaux
- [ ] Guide de configuration
- [ ] FAQ sur la sécurité et la performance
- [ ] Troubleshooting

### API Documentation

- [ ] Générer la documentation PHPDoc complète
- [ ] Documenter tous les services publics
- [ ] Documenter toutes les interfaces
- [ ] Exemples d'implémentation de plugins

## Tests de performance

### Benchmarks

- [ ] Mesurer l'impact sur les temps de génération
- [ ] Mesurer l'utilisation mémoire
- [ ] Mesurer l'efficacité du cache
- [ ] Comparer avec/sans context enrichment

### Optimisations

- [ ] Profiler les collecteurs les plus lents
- [ ] Optimiser les requêtes de base de données
- [ ] Implémenter le lazy loading si nécessaire
- [ ] Ajouter des limites configurables

### Documentation performance

- [ ] Documenter l'impact mesuré
- [ ] Fournir des recommandations de configuration
- [ ] Documenter les stratégies de caching
- [ ] Créer un guide d'optimisation

## Validation et release

### Code review

- [ ] Revue par les mainteneurs du module AI
- [ ] Validation de l'architecture
- [ ] Validation des standards de code
- [ ] Validation de la couverture de tests

### Tests communautaires

- [ ] Beta release pour tests
- [ ] Collecte de feedback
- [ ] Ajustements basés sur le feedback
- [ ] Tests sur différentes configurations Drupal

### Documentation finale

- [ ] Mettre à jour le CHANGELOG
- [ ] Créer une release note complète
- [ ] Mettre à jour la documentation principale
- [ ] Créer un article de blog d'annonce

### Release

- [ ] Tag de version
- [ ] Publication sur drupal.org
- [ ] Annonce dans la communauté
- [ ] Support post-release

## Métriques de succès

### Objectifs quantitatifs

- [ ] Réduction du temps de génération de contenu pertinent : -30%
- [ ] Augmentation de la pertinence des liens internes : +50%
- [ ] Couverture de tests : >90%
- [ ] Impact performance : <100ms overhead par génération

### Objectifs qualitatifs

- [ ] Amélioration de la cohérence éditoriale perçue
- [ ] Meilleure optimisation SEO des contenus générés
- [ ] Feedback positif de la communauté
- [ ] Adoption par d'autres modules (ai_automators, seo_ai)
- [ ] Amélioration de la pertinence des suggestions CKEditor AI
- [ ] Génération automatique de liens internes contextuels
- [ ] Respect du ton et style éditorial du site dans CKEditor

## Notes importantes

### Dépendances entre phases

- Phase 2 nécessite Phase 1 complète
- Phase 3 nécessite Phase 2 ET l'issue #3492940

### Points de décision

- Index de relations : décision après benchmark Phase 1
- MCP integration : décision après validation Phase 2
- Nouveaux collecteurs : prioriser selon feedback communauté

### Risques identifiés

- Performance : mitigation via caching agressif
- Sécurité : filtrage dès Phase 1
- Scope creep : strict respect des phases
- Compatibilité : tests sur Drupal 10.4+ et 11+

## Notes techniques

### Point d'interception CKEditor

Le controller `Drupal\ai_ckeditor\Controller\AiRequest::doRequest()` est le point d'entrée pour toutes les requêtes CKEditor AI.

**Ligne 140-146** : Construction du `ChatInput` et du system prompt
```php
$messages = new ChatInput([
  new ChatMessage('user', $data->prompt),
]);
$messages->setStreamedOutput(TRUE);
$messages->setSystemPrompt('You are helpful website assistant...');
```

**Stratégies d'interception :**

1. **Event Subscriber** (Recommandé)
   - Créer un événement `ai_ckeditor.pre_request` avant la ligne 140
   - Permettre d'altérer `$data->prompt` et le system prompt
   - Injecter le contexte Drupal de manière propre

2. **Service Decorator**
   - Décorer `AiRequest` pour wrapper `doRequest()`
   - Plus invasif mais plus de contrôle

3. **Hook alter** (Legacy)
   - Utiliser `hook_ai_ckeditor_prompt_alter()`
   - Moins performant mais simple

**Contexte disponible dans le Request :**
- `$editor` : EditorInterface avec format et settings
- `$ai_ckeditor_plugin` : Plugin CKEditor actif (Tone, Summarize, etc.)
- `$request` : Request HTTP contenant potentiellement l'entity_id, field_name

**Extraction du contexte d'édition :**
```php
// À implémenter dans le subscriber
$content = $request->request->get('content'); // Contenu déjà saisi
$entity_type = $request->request->get('entity_type'); // Si fourni par JS
$entity_id = $request->request->get('entity_id'); // Si fourni par JS
$field_name = $request->request->get('field_name'); // Si fourni par JS
```

**Injection du contexte :**
```php
// Dans le subscriber
$context = $this->drupalContextService->collectContext([
  'entity_type' => $entity_type,
  'entity_id' => $entity_id,
  'plugin' => $ai_ckeditor_plugin->getPluginId(),
]);

$enriched_prompt = $this->drupalContextService->enrichPrompt(
  $data->prompt,
  $context
);
```

### Modifications JavaScript nécessaires

Pour améliorer le contexte, modifier les plugins CKEditor JS pour envoyer plus de données :

**Fichier** : `ai_ckeditor/js/ckeditor5_plugins/*/src/aicommand.js`

```javascript
// Ajouter au payload AJAX
fetch(url, {
  method: 'POST',
  body: JSON.stringify({
    prompt: prompt,
    content: editor.getData(), // Contenu actuel
    // Ajouter ces données si disponibles dans Drupal.settings :
    entity_type: drupalSettings.entity_type,
    entity_id: drupalSettings.entity_id,
    field_name: drupalSettings.field_name
  })
});
```

### Performance et caching

**Cache keys par contexte :**
- Site config : `ai_context:site`
- Node : `ai_context:node:{nid}`
- Taxonomy : `ai_context:taxonomy:{tid}`
- Links : `ai_context:links:{nid}`

**Cache max-age recommandé :**
- Site config : 24h (rarement change)
- Node metadata : 1h (change modérément)
- Internal links : 6h (change peu fréquemment)
- SEO metadata : 1h

**Invalidation :**
- Invalider `ai_context:node:{nid}` lors de la sauvegarde du node
- Invalider `ai_context:links:{nid}` lors de modifications de liens
- Utiliser `Cache::invalidateTags()` dans les hooks appropriés

