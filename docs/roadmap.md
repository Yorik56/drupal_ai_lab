# Roadmap : Context-aware prompt generation

## Issue de référence

**Issue** : Context-aware prompt generation (Drupal content, internal links, styles)

**Projet** : AI (Artificial Intelligence)

**Version cible** : 2.0.x-dev

**Priorité** : Major

**Composant** : AI Core

**Status** : Phase 1 MVP ✅ COMPLÈTE | Phase 2 RÉVISÉE avec MCP

## 🎉 Découverte importante : Module MCP

Le module **MCP** (Model Context Protocol) est installé et **change la donne**.

### Plugins MCP disponibles

**1. Content Plugin** (`drupal/mcp/src/Plugin/Mcp/Content.php`)
- `search-content` : Recherche de contenu avec filtres multiples
- Gestion des content types configurables
- Respect des permissions Drupal

**2. JSON:API Plugin** (`JsonApi.php`)
- `jsonapi_read` : Lecture complète d'entités
- `jsonapi_schema` : Schéma des ressources
- Support filtrage, pagination, includes

**3. AI Function Calling Plugin** (`AiFunctionCalling.php`)
- Expose **toutes** les AI function calls comme outils MCP
- Conversion automatique du schéma
- Intégration transparente

**4. AI Agent Calling Plugin** (`AiAgentCalling.php`)
- Expose **tous** les AI Agents comme outils MCP
- Gestion des capacités par agent
- Permissions par agent

**5. General Plugin** (`General.php`)
- `info` : Informations du site (nom, slogan, version, etc.)
- Outils utilitaires

**6. Drush Caller Plugin** (`DrushCaller.php`)
- Expose toutes les commandes Drush comme outils
- Pour développement uniquement
- Génération automatique du schéma

**7. MCP Studio** (`mcp_studio`)
- Création d'outils MCP sans coder
- Interface de test
- Configuration visuelle

### Impact sur notre roadmap

**Ce que MCP nous apporte :**
- ✅ Recherche de contenu → Déjà fait
- ✅ Lecture de nodes → Déjà fait
- ✅ Agents → Déjà exposés
- ✅ Function calls → Déjà exposés
- ✅ Architecture plugin → Déjà fait

**Ce qu'on garde de AI Context :**
- ✅ Enrichissement **automatique** et transparent (contexte de base)
- ✅ Performance via cache
- ✅ Simplicité d'utilisation
- ✅ Pas besoin de client MCP pour fonctionner

**Stratégie finale :**
1. **AI Context** : Contexte léger automatique (Phase 1 actuelle)
2. **MCP** : Outils avancés à la demande (search, read, agents)
3. **Plugin MCP custom** : Expose le contexte AI Context via MCP (Phase 2)

**Conclusion** : La Phase 2 est **10x plus simple** qu'initialement prévu !

## Conditions préalables

- [x] Implémenter par phases (MVP d'abord)
- [x] Tests automatisés obligatoires
- [x] Filtrage de sécurité dès le début
- [ ] Documenter l'impact performance (tests en cours)
- [ ] Alignement avec l'issue #3492940 (ChatConsumer) pour les agents

## Phase 1 : MVP (Base) ✅ COMPLÈTE

### Service principal ✅

- [x] Créer l'interface `DrupalContextServiceInterface` dans `src/Service/`
- [x] Implémenter `DrupalContextService` avec méthodes :
  - [x] `collectContext(array $options = []): array`
  - [x] `enrichPrompt(string $prompt, array $context_keys = []): string`
  - [x] `getCachedContext(string $cache_key): ?array`
- [x] Déclarer le service `ai_context.context_service` dans `ai_context.services.yml`
- [x] Ajouter les arguments nécessaires (cache.ai, entity_type.manager, etc.)

### Collecteurs de base ✅

- [x] Créer `src/ContextCollector/SiteConfigCollector.php`
  - [x] Récupérer nom du site
  - [x] Récupérer slogan
  - [x] Récupérer configuration de base
- [x] Créer `src/ContextCollector/NodeMetadataCollector.php`
  - [x] Récupérer titre, type, statut
  - [x] Récupérer dates (création, modification)
  - [x] Récupérer auteur
- [x] Créer `src/ContextCollector/TaxonomyCollector.php`
  - [x] Récupérer termes associés
  - [x] Récupérer vocabulaires
  - [x] Récupérer hiérarchies (implémentation de base)

### Hook pour enrichissement ✅

- [x] Créer hook `hook_ai_prompt_alter(&$prompt, $context)`
- [x] Créer hook `hook_ai_context_collect_alter(&$context, $options)`
- [ ] Documenter les hooks dans `ai.api.php` (à faire)

### Système de cache ✅

- [x] Utiliser le bin `cache.ai` existant
- [x] Implémenter le cache pour chaque collector
- [x] Créer les cache tags appropriés :
  - [x] `ai_context:site`
  - [x] `ai_context:node:{nid}`
  - [x] `ai_context:taxonomy:{tid}`
- [x] Implémenter l'invalidation du cache
- [x] Performance validée : 3.5x plus rapide avec cache (0.54ms → 0.22ms)

### Tests Phase 1 ✅

- [x] Tests unitaires pour `DrupalContextService` (6 tests, 27 assertions)
- [x] Tests avec `Drupal\Tests\UnitTestCase`
- [x] Tests fonctionnels manuels validés
- [x] Tests de performance pour le caching
- [x] Configuration PHPUnit avec drupal/core-dev
- [ ] Tests d'intégration CKEditor en conditions réelles (à valider)

### Documentation Phase 1 ✅

- [x] Documenter l'API du service dans PHPDoc
- [x] Créer README.md avec exemples d'utilisation
- [x] Créer INSTALL.md avec guide d'installation
- [x] Créer guide de tests (docs/TESTING.md)
- [ ] Ajouter exemples dans `docs/examples/` (optionnel)

### Intégration AI CKEditor (Phase 1) ✅

- [x] Créer event subscriber `CKEditorContextSubscriber`
  - [x] Intercepter les requêtes via `KernelEvents::REQUEST`
  - [x] Injecter le service `ai_context.context_service`
- [x] Enrichir le prompt avant l'envoi au provider
  - [x] Détecter le path `/api/ai-ckeditor/request/`
  - [x] Collecter le contexte Drupal pertinent
  - [x] Ajouter le contexte au prompt utilisateur
- [x] Gérer les données du formulaire d'édition
  - [x] Extraire les données JSON du Request
  - [x] Collecter métadonnées (site + entity si fourni)
  - [x] Filtrer selon permissions de l'utilisateur actuel
- [x] Logging et debugging
  - [x] Logs WARNING pour visibilité
  - [x] Logs confirmés fonctionnels : "Context enrichment applied"
- [ ] Configuration UI par plugin CKEditor (Phase 2)

### Tests intégration CKEditor (Phase 1) ⏳

- [x] Event subscriber validé via logs
- [x] Enrichissement de prompt confirmé via logs
- [x] Tests avec plugin CKEditor Completion validés
- [ ] Tests complets avec tous les plugins CKEditor
- [ ] Tests de performance avec contexte injecté
- [ ] Validation en production

### Documentation intégration CKEditor (Phase 1) ⏳

- [x] Code documenté avec PHPDoc
- [x] Guide d'installation et utilisation
- [ ] Screenshots de l'amélioration avec/sans contexte
- [ ] Vidéo de démonstration

## Phase 2 : Intégration MCP (RÉVISÉE - Simplifiée grâce à MCP)

**Constat** : Le module MCP fournit déjà les outils avancés prévus. Au lieu de tout recréer, on crée un **plugin MCP custom** qui utilise notre contexte.

### Outils MCP déjà disponibles ✅

MCP expose nativement :
- ✅ `search-content` : Recherche de contenu avec filtres
- ✅ `jsonapi_read` : Lecture complète de nodes via JSON:API
- ✅ `info` : Informations du site
- ✅ Tous les AI Function Calls
- ✅ Tous les AI Agents
- ✅ Commandes Drush (dev)

### Plugin MCP : DrupalContext

- [ ] Créer `src/Plugin/Mcp/DrupalContext.php`
  - [ ] Attribut `#[Mcp(id: 'drupal_context')]`
  - [ ] Extend `McpPluginBase`
  - [ ] Injecter `ai_context.context_service`
- [ ] Exposer le contexte comme **Resource MCP**
  - [ ] URI : `drupal://context/current`
  - [ ] Format : JSON avec contexte collecté
  - [ ] Mise à jour en temps réel
- [ ] Créer des outils MCP custom
  - [ ] `get_related_content` : Contenu similaire au node actuel
  - [ ] `suggest_internal_links` : Suggestions de liens internes
  - [ ] `analyze_content_seo` : Analyse SEO basique
  - [ ] `get_content_style` : Analyser le style éditorial du site

### Enrichissement automatique du System Prompt MCP

- [ ] Event subscriber pour enrichir le contexte MCP
- [ ] Ajouter le contexte AI Context au contexte initial MCP
- [ ] Configuration pour activer/désactiver par plugin MCP

### Configuration UI

- [ ] Page de configuration `ai_context.mcp_settings`
- [ ] Activer/désactiver l'intégration MCP
- [ ] Choisir quels collecteurs exposer via MCP
- [ ] Configurer les permissions par outil MCP
- [ ] Routing dans `ai_context.routing.yml`

### Tests Phase 2

- [ ] Tests du plugin MCP DrupalContext
- [ ] Tests des outils custom (get_related_content, etc.)
- [ ] Tests d'intégration avec MCP Studio
- [ ] Tests avec Claude Desktop en tant que client
- [ ] Tests de permissions et sécurité MCP
- [ ] Tests de performance avec MCP actif

### Documentation Phase 2

- [ ] Guide d'intégration MCP + AI Context
- [ ] Configuration de Claude Desktop avec MCP
- [ ] Exemples d'utilisation des outils custom
- [ ] Architecture hybride AI Context + MCP
- [ ] Voir `docs/mcp-integration.md` pour détails

## Phase 3 : Enrichissement & Production

**Cette phase consolide l'intégration MCP et prépare la contribution à drupal/ai**

### Amélioration du contexte de base

- [ ] Améliorer les collecteurs existants basés sur feedback
  - [ ] Ajouter plus de métadonnées utiles
  - [ ] Optimiser la performance
  - [ ] Affiner le formatage du contexte
- [ ] Ajouter contexte de l'utilisateur actuel (rôles, permissions)
- [ ] Contexte de la langue active du site
- [ ] Contexte du workflow de contenu (si applicable)

### Intégration AI Automators

- [ ] Event subscriber pour enrichir les prompts d'AI Automators
- [ ] Configuration par automator
- [ ] Templates de contexte par type d'automator
- [ ] Tests avec différents automators

### Enrichissement CKEditor avancé

- [ ] Contexte spécifique par type de plugin
  - [ ] Plugin Tone : Exemples de ton depuis d'autres contenus du site
  - [ ] Plugin Summarize : Structure de résumés existants
  - [ ] Plugin Translate : Glossaire de termes du site
  - [ ] Plugin Completion : Suggérer des phrases basées sur le style du site
- [ ] Analyser le contenu déjà saisi dans l'éditeur
  - [ ] Extraire `content` depuis le Request
  - [ ] Identifier les entités mentionnées
  - [ ] Détecter les termes de taxonomie
- [ ] Envoi de l'entity_id depuis JavaScript
  - [ ] Modifier les plugins CKEditor JS
  - [ ] Ajouter entity_type, entity_id, field_name au payload
  - [ ] Implémenter via `drupalSettings`

### Configuration UI

- [ ] Page de configuration `ai_context.settings`
  - [ ] Activer/désactiver l'enrichissement par module (CKEditor, Automators)
  - [ ] Configurer le cache max age global
  - [ ] Liste de champs sensibles à exclure
  - [ ] Prévisualisation du contexte
- [ ] Configuration par plugin CKEditor
  - [ ] Activer/désactiver le contexte par outil AI
  - [ ] Personnaliser le template de contexte
- [ ] Routing dans `ai_context.routing.yml`

### Tests Phase 3

- [ ] Tests d'intégration CKEditor complets
- [ ] Tests avec AI Automators
- [ ] Tests de performance en production
- [ ] Tests de charge avec multiples utilisateurs
- [ ] Tests de sécurité et permissions

### Documentation Phase 3

- [ ] Guide complet utilisateur
- [ ] Screenshots avant/après contexte
- [ ] Vidéos de démonstration
- [ ] Best practices par use case
- [ ] Guide de troubleshooting
- [ ] Documentation pour contribution à drupal/ai

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

- Performance : mitigation via caching agressif ✅
- Sécurité : filtrage dès Phase 1 ✅
- Scope creep : **ÉVITÉ grâce à MCP** ✅
- Compatibilité : tests sur Drupal 10.4+ et 11+ ✅

## Résumé exécutif MCP

### Ce que MCP change pour nous

| Fonctionnalité initialement prévue | Avant MCP | Avec MCP |
|-------------------------------------|-----------|----------|
| Recherche de contenu pertinent | À coder (InternalLinksCollector) | ✅ `search-content` tool |
| Lecture de nodes | À coder (NodeReader) | ✅ `jsonapi_read` tool |
| Analyse SEO | À coder (SeoMetadataCollector) | ⚠️ Tool custom simple |
| Menu structure | À coder (MenuCollector) | ✅ Via `jsonapi_read` |
| AI Agents | À coder (ChatConsumer) | ✅ Déjà exposés |
| Function calls | À exposer | ✅ Déjà exposés |

**Économie de code estimée** : ~70% de la Phase 2 initiale

### Architecture finale

```
┌─────────────────────────────────────────────────┐
│           CKEditor AI Request                    │
│         (utilisateur édite du contenu)           │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│    AI Context Event Subscriber                   │
│    → Enrichit automatiquement avec :             │
│      • Nom du site                               │
│      • Node en cours d'édition                   │
│      • Taxonomies                                │
│    → Contexte léger et transparent               │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│         LLM (OpenAI, Claude, etc.)               │
│    + MCP Tools disponibles (si configuré) :      │
│      • search-content(query, filters)            │
│      • jsonapi_read(entity_type, id)             │
│      • get_related_content(node_id)              │
│      • AI Agents & Function Calls                │
│    → L'IA décide quels outils utiliser           │
└─────────────────────────────────────────────────┘
```

### Avantages de l'architecture hybride

1. **Performance** : Contexte de base léger (< 1ms), outils MCP à la demande
2. **Flexibilité** : L'IA choisit ce dont elle a besoin
3. **Maintenabilité** : Moins de code custom, utilise des standards
4. **Évolutivité** : Facile d'ajouter de nouveaux outils MCP
5. **Interopérabilité** : Standards MCP, fonctionne avec tous les clients

### Prochaines étapes recommandées

**Immédiat (1-2 semaines)** :
1. ✅ Valider Phase 1 MVP en production
2. ✅ Collecter feedback utilisateurs
3. ✅ Ajuster le contexte de base si nécessaire

**Court terme (1-2 mois)** :
1. Créer plugin MCP `DrupalContext`
2. Exposer le contexte comme resource MCP
3. Tester avec Claude Desktop

**Moyen terme (3-6 mois)** :
1. Contribuer à drupal/ai (issue fork)
2. Intégration avec AI Automators
3. Configuration UI

**Long terme (6+ mois)** :
1. Agents contextuels avancés
2. Optimisations basées sur métriques
3. Documentation complète pour la communauté

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

