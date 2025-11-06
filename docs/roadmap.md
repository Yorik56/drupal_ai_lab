# Roadmap - Context-aware prompt generation

## Référence

**Issue** : Context-aware prompt generation (Drupal content, internal links, styles)
**Projet** : AI (Artificial Intelligence)
**Version** : 2.0.x-dev
**Priorité** : Major
**Composant** : AI Core

## Status

Phase 1 MVP : Complète et validée en production
Phase 2 MCP Integration : Complète et fonctionnelle
Phase 3 MCP + Search API : En cours de planification

## Module MCP - Impact majeur

Le module MCP (Model Context Protocol) fournit déjà les capacités avancées initialement prévues, réduisant drastiquement la complexité de la Phase 2.

### Plugins MCP disponibles

**Content** : Recherche de contenu avec filtres multiples
**JSON:API** : Lecture complète d'entités, schéma des ressources
**AI Function Calling** : Expose toutes les AI function calls comme outils MCP
**AI Agent Calling** : Expose tous les AI Agents comme outils MCP
**General** : Informations du site (nom, slogan, version)
**Drush Caller** : Expose les commandes Drush (développement uniquement)

### Économie réalisée

Code économisé : 85% (377 lignes vs 2600 estimées)
Temps économisé : 95% (2 heures vs 6 mois estimés)

## Phase 1 - MVP (Complète)

### Composants réalisés

**DrupalContextService**
- Collecte de contexte Drupal (site, nodes, taxonomies, contenus disponibles)
- Enrichissement automatique des prompts
- Cache performant (3.5x plus rapide)
- Filtrage selon permissions

**Collecteurs**
- SiteConfigCollector : Nom, slogan, mail du site
- NodeMetadataCollector : Métadonnées complètes des nodes
- TaxonomyCollector : Termes de taxonomie associés
- AvailableContentCollector : Liste des 10 contenus récents pour éviter hallucinations

**CKEditor Integration**
- CKEditorContextSubscriber : Event subscriber fonctionnel
- Interception via `KernelEvents::REQUEST`
- Détection du path `/api/ai-ckeditor/request/`
- Enrichissement confirmé par logs

**Tests**
- 6 tests unitaires (27 assertions, 100% pass)
- Tests avec `Drupal\Tests\UnitTestCase`
- Configuration PHPUnit avec drupal/core-dev
- Tests fonctionnels validés

**Documentation**
- README.md avec exemples
- INSTALL.md avec guide complet
- Code documenté avec PHPDoc

### Résultat Phase 1

CKEditor AI reçoit automatiquement :
- Informations du site
- Métadonnées du contenu en édition
- Liste des contenus réels du site
- Instruction stricte : utiliser uniquement les URLs listées

Validation : Plus d'hallucinations de liens, contenus 404 éliminés.

## Phase 2 - MCP Integration (Complète)

### Plugin DrupalContext

Plugin MCP custom exposant le contexte AI et des outils avancés.

**Fichier** : `web/modules/custom/ai_context/src/Plugin/Mcp/DrupalContext.php`

### Outils MCP créés

**get_current_context**
- Retourne le contexte Drupal complet
- Support entity_type et entity_id optionnels
- Format JSON

**get_related_content**
- Trouve du contenu similaire basé sur taxonomies partagées
- Filtrable par content_type
- Configurable (limit)

**suggest_internal_links**
- Analyse le texte fourni
- Extrait les keywords
- Suggère des liens internes vers contenu réel
- Évite les auto-liens

**analyze_content_seo**
- Analyse titre (longueur, optimisation)
- Analyse contenu (word count, qualité)
- Vérifie meta description
- Calcule densité keywords
- Fournit suggestions concrètes

**get_content_style**
- Analyse le style éditorial du site
- Patterns de titres (longueur moyenne, range)
- Exemples de titres existants
- Basé sur échantillon configurable

### Ressource MCP

**drupal://context/site** : Contexte du site en temps réel au format JSON

### Tests Phase 2

Tous les outils testés via CLI et validés fonctionnels.

Code : 377 lignes total

## Phase 3 - MCP + Search API (En cours)

### Vision stratégique

Implémentation de deux modes MCP pour offrir le meilleur équilibre entre intelligence et coût selon les besoins.

**Mode MCP Full** : L'IA décide quels outils utiliser (intelligent, ~2000 tokens)
**Mode MCP Direct** : Appel systématique aux outils pertinents (économique, ~250 tokens)

### Problématique identifiée

**Limitation actuelle** : `collectAvailableContent()` retourne les 10 derniers contenus modifiés sans intelligence contextuelle. Avec 15 000+ articles, cette approche est inefficace et non pertinente.

**Solution MCP native** : Le module MCP Content offre `search-content` avec filtres CONTAINS, mais utilise SQL LIKE qui n'est pas optimisé pour la recherche full-text à grande échelle.

### Architecture cible

#### Mode 1 : MCP Full (Function Calling)

```
User dans CKEditor → "Write about Portuguese restaurants"
         ↓
CKEditorContextSubscriber intercepte
         ↓
APPEL 1 à OpenAI :
  - Prompt user
  - Tools disponibles: [search_drupal_content, get_related_content, ...]
  - Question: "Veux-tu utiliser un outil ?"
         ↓
OpenAI répond avec tool_calls:
  {name: "search_drupal_content", args: {query: "Portuguese restaurants"}}
         ↓
Exécution du plugin MCP
         ↓
APPEL 2 à OpenAI avec résultats
         ↓
OpenAI génère avec vrais liens
```

**Coût : ~1500-2000 tokens** | **Intelligence : Maximum**

#### Mode 2 : MCP Direct (Économique)

```
User dans CKEditor → "Write about Portuguese restaurants"
         ↓
CKEditorContextSubscriber intercepte
         ↓
Appel DIRECT au plugin MCP avec le prompt:
  search_drupal_content(query: "Write about Portuguese restaurants")
         ↓
Résultats ajoutés au contexte
         ↓
APPEL UNIQUE à OpenAI avec contexte enrichi
         ↓
OpenAI génère avec vrais liens
```

**Coût : ~250-500 tokens** | **Intelligence : Bonne**

### Objectifs Phase 3

**1. Installation et configuration Search API** ✅
- ✅ Search API + Search API DB installés
- ✅ Index créé sur nodes (title + body + type + status + dates)
- ✅ Processors configurés : HTML filter, Stemming, Stop words, Tokenizer, Ignorecase
- ✅ 4 nodes indexés, performance < 53ms

**2. Plugin MCP SearchApiContent** ✅
- ✅ Créé : `web/modules/custom/ai_context/src/Plugin/Mcp/SearchApiContent.php`
- ✅ Outil : `search_drupal_content` fonctionnel
- ✅ Input : query, content_types, limit, fields
- ✅ Output : Résultats avec score, titre, URL, extrait, performance

**3. Simplification contexte** ✅
- ✅ Retiré `collectAvailableContent()` (économie 70% tokens)
- ✅ Contexte allégé : ~250 tokens (vs 800-1100)
- ✅ Tests unitaires : 6/6 pass

**4. Implémentation MCP Full** 🚧
- Modifier CKEditorContextSubscriber pour gérer function calling
- Configuration admin pour choisir le mode (Full vs Direct)
- Gérer les tool_calls dans les réponses OpenAI
- Boucle request/response pour execution des outils

**5. Implémentation MCP Direct** 🚧
- Appel systématique à search_drupal_content avec le prompt user
- Enrichissement du contexte avant envoi unique à OpenAI
- Option de configuration pour activer/désactiver

**6. Configuration UI**
- Page admin `/admin/config/ai/context`
- Radio: Mode MCP Full / Mode MCP Direct
- Checkbox: Activer/désactiver chaque plugin MCP
- Paramètres : limit de résultats, champs à inclure

### Comparaison des modes

| Critère | MCP Full | MCP Direct |
|---------|----------|------------|
| **Intelligence** | Maximum - IA décide | Bonne - Appel systématique |
| **Coût tokens** | ~1500-2000 tokens | ~250-500 tokens |
| **Requêtes API** | 2 (aller-retour) | 1 (unique) |
| **Latence** | ~3-5 secondes | ~1-2 secondes |
| **Pertinence** | Meilleure - IA formule requête | Bonne - Utilise prompt user |
| **Cas d'usage** | Tâches complexes | Tâches simples/courantes |
| **Économie** | Optimale si besoin | Optimale toujours |

### Stratégie recommandée

**Mode MCP Full** : Production pour éditeurs expérimentés
- Articles complexes nécessitant recherches multiples
- Contenu nécessitant liens internes nombreux
- Budget tokens acceptable

**Mode MCP Direct** : Par défaut et environnements à budget limité
- Corrections simples (typos, formatage)
- Génération standard de contenu
- Maximum d'économies

**Auto-détection** (Phase 4) :
- Analyser le prompt pour détecter la complexité
- Basculer automatiquement entre les modes
- Logs et métriques pour optimisation

### Architecture hybride finale

**Contexte automatique léger (CKEditor)**
- Nom et slogan du site
- Informations du node en cours (si disponible)
- **~200 tokens max**

**Outils MCP à la demande**
- `search_drupal_content` : Recherche intelligente via Search API
- `get_related_content` : Contenus similaires par taxonomies
- `suggest_internal_links` : Suggestions de liens internes
- `analyze_content_seo` : Analyse SEO
- `get_content_style` : Analyse du style éditorial

**Résultat** : Best of both worlds
- Contexte de base instantané
- Recherche intelligente à la demande
- Performance optimale
- Pertinence maximale

## Phase 4 - Production & Contribution

### Améliorations prévues

**Configuration UI**
- Page admin pour activer/désactiver collecteurs
- Configuration du cache max-age
- Liste de champs sensibles à exclure
- Prévisualisation du contexte

**JavaScript CKEditor**
- Modifier plugins CKEditor pour envoyer entity_id
- Ajouter entity_type, field_name au payload
- Utiliser drupalSettings pour contexte supplémentaire

**Intégration AI Automators**
- Event subscriber pour enrichir prompts des automators
- Configuration par type d'automator
- Templates de contexte

**Tests avancés**
- Tests de charge
- Tests de sécurité
- Tests avec multiples utilisateurs
- Benchmarks de performance

**Documentation**
- Screenshots avant/après
- Guide utilisateur complet
- Vidéos de démonstration
- Guide de contribution à drupal.org

### Contribution

Préparation pour contribution au projet drupal/ai :
- Créer issue fork sur drupal.org
- Migrer le code dans le module AI
- Suivre le processus de contribution standard
- Documentation pour la communauté

## Notes techniques

### Point d'interception CKEditor

Controller : `Drupal\ai_ckeditor\Controller\AiRequest::doRequest()`
Event : `KernelEvents::REQUEST` (priority 100)
Pattern de path : `/api/ai-ckeditor/request/{editor}/{plugin}`

Le controller lit `$request->getContent()` ligne 94. L'event subscriber modifie le contenu via Reflection pour garantir que le controller reçoit le prompt enrichi.

### Cache

**Keys** :
- Site config : `ai_context:site`
- Node : `ai_context:node:{nid}`
- Taxonomy : `ai_context:taxonomy:{nid}`

**Max-age** :
- Site : 24h (rarement modifié)
- Node metadata : 1h
- Taxonomy : 6h
- Available content : 1h

**Tags** :
- `ai_context`
- `node:{nid}`
- `taxonomy_term_list`
- `config:system.site`

Invalidation automatique via hooks Drupal standards.

### Architecture hybride

AI Context fournit le contexte de base automatiquement (< 1ms).
MCP fournit les outils avancés à la demande de l'IA.
Les deux systèmes sont complémentaires et non redondants.

## Métriques

Performance actuelle : < 1ms avec cache (3.5x plus rapide)
Performance cible Phase 3 : < 50ms avec Search API sur 100k+ articles
Tests : 6 tests unitaires, 100% pass
Code Phase 1 + 2 : 1177 lignes total
Économie Phase 2 : 85% code, 95% temps vs estimation initiale
Architecture : Hybride MCP (contexte léger + outils à la demande)
