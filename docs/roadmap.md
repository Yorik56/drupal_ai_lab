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

