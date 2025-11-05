# Intégration MCP avec AI Context

## Vue d'ensemble

Le module **MCP** (Model Context Protocol) et notre module **AI Context** sont **très complémentaires** mais fonctionnent différemment.

## Différences fondamentales

| Critère | **AI Context** (notre MVP) | **MCP** |
|---------|---------------------------|---------|
| **Type** | Enrichissement passif de prompts | Exposition active d'outils |
| **Approche** | Injecte du contexte dans chaque requête | LLM appelle des outils selon besoin |
| **Utilisation** | Automatique, transparent | À la demande de l'IA |
| **Scope** | CKEditor AI, AI Automators | Tous les clients MCP (Claude, Cursor) |
| **Architecture** | Event Subscriber | Plugin system + JSON-RPC |

## Ce que fait actuellement AI Context (MVP Phase 1)

### ✅ Fonctionnel

```
DRUPAL SITE CONTEXT:
Site: Votre Site
Content: Article en cours d'édition
Type: article
Tags: technologie, drupal

USER REQUEST:
Améliore le ton de ce texte...
```

**Avantages :**
- Automatique (pas besoin que l'IA demande)
- Léger (contexte de base seulement)
- Intégré à CKEditor AI

**Limitations actuelles :**
- ❌ Ne liste PAS les contenus pertinents du site
- ❌ Pas d'accès aux autres nodes
- ❌ Pas de recherche de liens internes
- ❌ Pas d'analyse SEO

→ **Ces fonctionnalités sont prévues en Phase 2**

## Ce que fait MCP

### Outils exposés par défaut

Le module MCP expose des **outils** que l'IA peut appeler :

1. **Content Tools**
   - `read_content` : Lire n'importe quel contenu
   - `create_content` : Créer du contenu
   - `update_content` : Mettre à jour du contenu
   - `search_content` : Chercher du contenu

2. **AI Tools**
   - Appeler les function calls du module AI
   - Utiliser les AI Agents

3. **JSON API Tools**
   - Accès complet à l'API Drupal

4. **Drush Tools** (optionnel)
   - Exécuter des commandes Drush

### Exemple d'interaction

```
IA : "Je vais chercher les articles similaires sur le site"
MCP Tool: search_content(query="bistrot paris", content_type="article")
→ Résultats: [Article 1, Article 2, Article 3]

IA : "Je vais lire l'article 2 pour voir le contexte"
MCP Tool: read_content(nid=2)
→ Contenu complet de l'article

IA : "Voici des suggestions de liens internes basés sur..."
```

## 🎯 Stratégie d'intégration recommandée

### Phase 1 : MVP AI Context (✅ FAIT)

**Ce qui fonctionne :**
- Contexte de base injecté dans CKEditor AI
- Site config + node metadata + taxonomies
- Cache performant
- Tests unitaires

**À compléter :**
- Documentation avec screenshots
- Tests en conditions réelles
- Ajuster le contexte selon feedback

### Phase 2 : AI Context Extended + MCP

**Option A : AI Context fait tout**
- Implémenter InternalLinksCollector
- Implémenter SeoMetadataCollector  
- Menu structure, etc.
- **Inconvénient** : Beaucoup de code à écrire

**Option B : AI Context + MCP (RECOMMANDÉ)**
- AI Context : contexte de base (léger, automatique)
- MCP : outils avancés (recherche, liens, etc.)
- **Avantage** : MCP fait déjà 80% du travail !

### Architecture hybride recommandée

```
┌─────────────────────────────────────────────┐
│         CKEditor AI Request                  │
└───────────────┬─────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────┐
│   AI Context Event Subscriber                │
│   → Injecte contexte de base :              │
│     - Site name/slogan                       │
│     - Current node metadata                  │
│     - Taxonomies                             │
│   → Prompt enrichi automatiquement           │
└───────────────┬─────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────┐
│         OpenAI / Anthropic LLM               │
│   + MCP Tools disponibles (si configuré)    │
│   → Peut appeler:                            │
│     - search_content()                       │
│     - read_content()                         │
│     - AI function calls                      │
└─────────────────────────────────────────────┘
```

## 💡 Proposition concrète

### Ce qu'on garde de AI Context

1. **Contexte de base automatique** (Phase 1 actuelle)
   - Nom du site
   - Node en cours
   - Taxonomies
   - **Léger et transparent**

2. **Hook system** pour extensions
   - `hook_ai_context_collect_alter()`
   - Permet aux autres modules d'ajouter du contexte

### Ce qu'on délègue à MCP

1. **Recherche de contenus pertinents**
   - Via `search_content` tool
   - L'IA appelle quand elle en a besoin

2. **Lecture de contenus connexes**
   - Via `read_content` tool
   - Selon le besoin de l'IA

3. **Analyse SEO avancée**
   - Via plugins MCP custom

### Avantages de cette approche

✅ **Pas de duplication** : MCP fait déjà le travail  
✅ **Complémentarité** : Contexte léger + outils puissants  
✅ **Flexibilité** : L'IA choisit ce dont elle a besoin  
✅ **Performance** : Pas de surcharge inutile  
✅ **Maintenance** : Moins de code à maintenir  

## 🔧 Modifications suggérées à la Roadmap

### Phase 2 : Simplifiée avec MCP

Au lieu de créer tous les collecteurs, on crée des **plugins MCP** :

#### 2.1 Plugin MCP : DrupalContext

```php
#[Mcp(
  id: 'drupal_context',
  label: 'Drupal Context Provider'
)]
class DrupalContextMcp extends McpPluginBase {
  
  public function getTools(): array {
    return [
      new Tool(
        name: 'get_related_content',
        description: 'Find content related to current node',
        inputSchema: [
          'type' => 'object',
          'properties' => [
            'node_id' => ['type' => 'integer'],
            'limit' => ['type' => 'integer', 'default' => 5],
          ],
        ],
        annotations: new ToolAnnotations(
          readOnlyHint: true,
          idempotentHint: true,
        ),
      ),
      
      new Tool(
        name: 'get_internal_links',
        description: 'Suggest relevant internal links',
        inputSchema: [...],
      ),
      
      new Tool(
        name: 'analyze_seo',
        description: 'Analyze SEO for current content',
        inputSchema: [...],
      ),
    ];
  }
  
  public function execute(string $toolName, array $arguments): mixed {
    return match($toolName) {
      'get_related_content' => $this->getRelatedContent($arguments),
      'get_internal_links' => $this->getInternalLinks($arguments),
      'analyze_seo' => $this->analyzeSeo($arguments),
    };
  }
}
```

#### 2.2 Intégration AI Context → MCP

Créer un event subscriber qui **expose le contexte AI Context via MCP** :

```php
// Quand MCP démarre, enregistrer le contexte actuel comme "resource"
$mcpServer->addResource(
  uri: 'drupal://context/current',
  name: 'Current Drupal Context',
  description: 'Context collected by AI Context module',
  mimeType: 'application/json',
);
```

## 📊 Roadmap révisée

### Phase 1 : MVP ✅ (COMPLÈTE)
- Service de contexte de base
- Collecteurs : Site, Node, Taxonomy
- Event subscriber CKEditor
- Tests unitaires

### Phase 2 : MCP Integration (NOUVEAU)
- ~~Créer InternalLinksCollector~~ → Plugin MCP
- ~~Créer SeoMetadataCollector~~ → Plugin MCP
- ~~Créer MenuStructureCollector~~ → Plugin MCP
- **Nouveau** : Plugin MCP DrupalContext
- **Nouveau** : Resource MCP pour contexte actuel
- **Nouveau** : Configuration MCP dans AI Context settings

### Phase 3 : Agents (ALIGNÉ)
- Les agents utilisent MCP tools automatiquement
- Pas besoin de ChatConsumer séparés
- Configuration via MCP

## 🎬 Prochaines étapes immédiates

1. ✅ **Valider Phase 1 MVP** 
   - Tests réels avec utilisateurs
   - Ajuster le contexte de base
   - Documenter

2. 🔄 **Étudier l'intégration MCP**
   - Créer POC plugin MCP
   - Tester avec Claude Desktop
   - Valider l'architecture

3. 📝 **Mettre à jour la roadmap**
   - Simplifier Phase 2
   - Focus sur plugins MCP
   - Documentation intégration

## ⚡ Quick Win immédiat

**Activer MCP Studio** pour voir ce qui est exposé :

```bash
ddev drush en mcp_studio -y
# Puis accéder à /admin/config/mcp/studio
```

Cela permettra de :
- Voir les outils MCP disponibles
- Tester les appels
- Comprendre comment créer nos plugins

## 🎯 Conclusion

**AI Context MVP** : ✅ Fonctionnel, fait son job (contexte de base léger)

**MCP** : 🚀 Ouvre des possibilités énormes sans réinventer la roue

**Stratégie recommandée** : 
- Garder AI Context pour le contexte automatique de base
- Utiliser MCP pour les fonctionnalités avancées (recherche, liens, SEO)
- Créer des plugins MCP custom qui utilisent les collecteurs de AI Context

**Bénéfices** :
- Moins de code à écrire et maintenir
- Meilleure intégration avec l'écosystème AI
- Flexibilité pour les LLMs
- Standards ouverts (MCP)

