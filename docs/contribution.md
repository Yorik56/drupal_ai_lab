# Guide de contribution au projet AI

## Introduction

Ce guide explique comment contribuer au projet AI pour Drupal, hébergé sur https://www.drupal.org/project/ai. Le projet utilise le système de suivi d'issues de Drupal.org et suit les conventions de contribution de la communauté Drupal.

## Avant de commencer

### Prérequis

- Compte sur Drupal.org
- Connaissance de base de Drupal et de son système de contribution
- Git configuré localement
- Environnement de développement Drupal fonctionnel

### Lectures recommandées

- https://www.drupal.org/contribute/development
- https://www.drupal.org/docs/develop/git
- https://www.drupal.org/project/issues

## Créer une issue

### Structure d'une issue

Chaque issue doit contenir les éléments suivants :

#### Champs obligatoires

**Project** : AI (Artificial Intelligence)

**Version** : Version cible (ex: 2.0.x-dev, 1.2.x-dev)

**Component** : Sous-module ou composant concerné
- AI Core
- AI Chatbot
- AI Search
- AI Assistant API
- AI Automators
- AI CKEditor
- AI Translate
- AI Logging
- Autres sous-modules

**Priority** : Niveau de priorité
- Critical : Problème bloquant majeur
- Major : Fonctionnalité importante ou bug significatif
- Normal : Amélioration standard ou bug mineur
- Minor : Petite amélioration

**Category** : Type d'issue
- Bug report : Rapport de bug
- Feature request : Demande de fonctionnalité
- Support request : Demande d'aide
- Plan : Proposition de plan ou d'architecture
- Task : Tâche de maintenance

#### Sections du résumé

##### AI Tracker Metadata (optionnel)

Pour les issues suivies par l'équipe de développement :

```
--- AI TRACKER METADATA ---
Update Summary: [Résumé court de la mise à jour]
Check-in Date: MM/DD/YYYY (US format) [Date de point d'étape]
Due Date: MM/DD/YYYY (US format) [Date limite]
Blocked by: [#XXXXXX] (Nouvelles issues sur nouvelles lignes)
Additional Collaborators: @username1, @username2
AI Tracker found here: https://www.drupalstarforge.ai/
--- END METADATA ---
```

##### Problem/Motivation

Section obligatoire décrivant :
- Le problème actuel ou le besoin
- Le contexte et les cas d'usage
- Les limitations de l'implémentation actuelle
- Références aux discussions pertinentes (ex: DrupalCon)

Exemple :

```
Currently the AI Assistant API is coupled with the AI Chatbot, but we should 
be able to decouple this by adding a plugin interface for the Chatbot that 
anyone can extend. One of the questions we get a lot is to setup a naive RAG 
functionality with the chatbot, so that should be possible.

We discussed this during DrupalCon Vienna and the general conclusion is that 
we open it up completely for anyone wanting to create some kind of integration.
```

##### Proposed resolution

Section obligatoire proposant une solution :
- Liste numérotée ou à puces des étapes d'implémentation
- Description des interfaces et classes à créer
- Spécification des méthodes et leurs signatures
- Considérations techniques importantes

Exemple :

```
1. Create an attribute called ChatConsumer that has label, description.
2. Create a pluginmanager that looks for ChatConsumers under src/Plugin/ChatConsumer
3. Create an interface that has setInput, getInput, setOutput, getOutput, doExecute
4. Interface should use ConfigurableInterface and PluginFormInterface.
5. Create a base class that has execute() that runs doExecute()
```

##### Remaining tasks (optionnel)

Tâches restantes pour compléter l'issue :
- Code à écrire
- Tests à ajouter
- Documentation à créer
- Revues de code nécessaires

##### User interface changes (optionnel)

Description des changements dans l'interface utilisateur :
- Captures d'écran avant/après
- Nouveaux formulaires ou pages
- Modifications de l'expérience utilisateur

##### API changes (optionnel)

Documentation des changements d'API :
- Nouvelles interfaces
- Méthodes ajoutées ou modifiées
- Classes dépréciées
- Impact sur les modules contribués

### Issue tags

Ajouter des tags pertinents pour faciliter le tri :
- `priority` : Issue prioritaire
- `Needs tests` : Tests manquants
- `Needs documentation` : Documentation manquante
- `Needs issue summary update` : Résumé à mettre à jour
- `Needs review` : Prêt pour revue
- `Needs work` : Travail supplémentaire nécessaire
- `Reviewed & tested by the community` : RTBC

## Travailler sur une issue

### 1. Créer un fork de l'issue

Sur Drupal.org, chaque issue peut avoir un fork Git associé :

1. Sur la page de l'issue, cliquer sur "Create issue fork"
2. Le système crée automatiquement un fork nommé `ai-ISSUE_NUMBER`
3. Exemple : `ai-3492940` pour l'issue #3492940

### 2. Cloner le fork localement

```bash
git clone --branch ISSUE_NUMBER https://git.drupalcode.org/issue/ai-ISSUE_NUMBER.git
cd ai-ISSUE_NUMBER
```

### 3. Créer une branche

Convention de nommage : `ISSUE_NUMBER-description`

```bash
git checkout -b 3492940-add-chatconsumer
```

### 4. Développer la fonctionnalité

- Suivre les standards de code Drupal
- Écrire des tests unitaires et fonctionnels
- Documenter le code avec PHPDoc
- Respecter les interfaces définies dans l'issue

### 5. Commits

Utiliser des messages de commit clairs et descriptifs :

```bash
git commit -m "Issue #3492940: Add ChatConsumer plugin interface"
git commit -m "Issue #3492940: Implement base ChatConsumer class"
git commit -m "Issue #3492940: Add tests for ChatConsumer"
```

### 6. Pousser les modifications

```bash
git push origin 3492940-add-chatconsumer
```

## Standards de code

### PHP Code Sniffer

Le projet utilise PHPCS pour vérifier les standards :

```bash
./vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/contrib/ai
```

### PHPStan

Analyse statique du code :

```bash
./vendor/bin/phpstan analyse
```

### Tests

Exécuter les tests :

```bash
./vendor/bin/phpunit -c web/modules/contrib/ai/phpunit.ai.xml.dist
```

## Participer aux discussions

### Structure des commentaires

Les commentaires sur les issues suivent généralement ce format :

1. **Status update** : Mise à jour du statut si nécessaire
2. **Summary** : Résumé des modifications apportées
3. **Technical details** : Détails techniques si pertinent
4. **Questions** : Questions pour l'équipe ou le mainteneur
5. **Next steps** : Étapes suivantes proposées

### Exemple de commentaire

```
I've implemented the ChatConsumer plugin interface as discussed.

Changes made:
- Added ChatConsumerInterface with all required methods
- Created ChatConsumerBase class
- Implemented ChatConsumerPluginManager
- Added configuration form handling

The implementation follows the same pattern as FunctionCall plugins. 
I've added PHPDoc for all methods.

Question: Should we add a separate event for ChatConsumer execution, 
or use the existing PreRequestEvent?

Next: Will add tests and update documentation.
```

### Mise à jour du statut

Utiliser les bons statuts lors des commentaires :

- **Active** : Issue en cours de traitement
- **Needs review** : Code prêt pour revue
- **Needs work** : Modifications nécessaires
- **Reviewed & tested by the community** : RTBC, prêt pour commit
- **Fixed** : Committée et résolue
- **Postponed** : En attente d'une autre issue
- **Closed (duplicate)** : Doublon
- **Closed (won't fix)** : Ne sera pas implémenté

### Attribution de l'issue

Pour s'assigner une issue :

1. Commenter "I'll work on this"
2. Mettre à jour le champ "Assigned" avec votre nom d'utilisateur
3. Changer le statut à "Active"

Exemple : `Assigned: Unassigned » username`

### Créer le premier commit

Lorsque vous faites votre premier commit sur un fork d'issue, ajouter un commentaire :

```
username made their first commit to this issue's fork.
```

## Revue de code

### En tant que revieweur

1. Vérifier que le code suit les standards Drupal
2. Tester localement la fonctionnalité
3. Vérifier les tests et leur couverture
4. Valider la documentation
5. Commenter de manière constructive :
   - Pointer les problèmes spécifiques
   - Proposer des solutions
   - Reconnaître les bons aspects

### En tant que contributeur

1. Répondre aux commentaires de manière professionnelle
2. Appliquer les corrections demandées
3. Expliquer les choix techniques si nécessaire
4. Mettre à jour le statut à "Needs review" après corrections

## Patcher vs Fork

### Utiliser les forks (recommandé)

Les forks d'issues sont la méthode recommandée :
- Meilleure traçabilité
- Facilite la collaboration
- Intégration native avec Drupal.org

### Créer un patch (legacy)

Si nécessaire, créer un patch :

```bash
git diff > ISSUE_NUMBER-description-COMMENT_NUMBER.patch
```

Exemple : `3492940-add-chatconsumer-12.patch`

## Meilleures pratiques

### Communication

- Être clair et concis
- Utiliser un langage professionnel
- Documenter les décisions techniques
- Poser des questions plutôt que supposer

### Code

- Suivre les patterns existants du module
- Écrire des tests pour tout nouveau code
- Documenter les API publiques
- Considérer la rétrocompatibilité

### Tests

- Tests unitaires pour la logique métier
- Tests fonctionnels pour les workflows
- Tests de régression pour les bugs corrigés
- Couverture de code significative

### Documentation

- Mettre à jour le résumé de l'issue
- Documenter les changements d'API
- Ajouter des exemples d'utilisation
- Mettre à jour CHANGELOG si pertinent

## Ressources

### Documentation officielle

- Projet AI : https://www.drupal.org/project/ai
- Documentation AI : https://project.pages.drupalcode.org/ai/
- Contributing to Drupal : https://www.drupal.org/contribute

### Support

- Issue queue : https://www.drupal.org/project/issues/ai
- Slack : #ai channel sur Drupal Slack
- AI Tracker : https://www.drupalstarforge.ai/

### Outils

- Git : https://git-scm.com/
- Composer : https://getcomposer.org/
- PHPStan : https://phpstan.org/
- PHPCS : https://github.com/squizlabs/PHP_CodeSniffer

## Exemple complet : Issue #3492940

### Contexte

Ajout d'un système de plugins ChatConsumer pour découpler l'API AI Assistant du Chatbot.

### Métadonnées

```
--- AI TRACKER METADATA ---
Update Summary: Add Chat Consumer
Check-in Date: MM/DD/YYYY
Due Date: MM/DD/YYYY
Blocked by: [#XXXXXX]
Additional Collaborators: @username1, @username2
--- END METADATA ---
```

### Résumé de l'issue

**Problem/Motivation** : Actuellement l'API AI Assistant est couplée avec le Chatbot. Un système de plugins permettrait plus de flexibilité et d'étendre facilement les fonctionnalités.

**Proposed resolution** :
1. Créer un attribut ChatConsumer avec label et description
2. Créer un plugin manager cherchant les plugins dans src/Plugin/ChatConsumer
3. Créer une interface avec setInput, getInput, setOutput, getOutput, doExecute
4. Créer une classe de base implémentant les méthodes communes

### Workflow de contribution

1. Créer le fork : `ai-3492940`
2. Cloner localement
3. Créer la branche : `3492940-add-chatconsumer`
4. Implémenter l'interface ChatConsumerInterface
5. Implémenter la classe ChatConsumerBase
6. Créer le ChatConsumerPluginManager
7. Écrire les tests
8. Mettre à jour la documentation
9. Pousser et commenter l'issue
10. Statut : "Needs review"
11. Itérer selon les retours
12. RTBC puis commit par un mainteneur

### Commentaires types

**Premier commit** :
```
unqunq made their first commit to this issue's fork.
```

**Mise à jour** :
```
I've implemented the ChatConsumer plugin system as specified. The implementation 
includes:

- ChatConsumerInterface with all required methods
- ChatConsumerBase providing default implementations
- ChatConsumerPluginManager for plugin discovery
- Configuration form integration
- Tests covering the main use cases

This follows the same architecture pattern as the FunctionCall plugins. 
I've added comprehensive PHPDoc comments for all public APIs.

Status: Needs review
```

**Revue** :
```
Looks good overall! Few minor issues:

1. Line 45: Missing return type declaration
2. The doExecute() method should be abstract in the base class
3. Tests are missing coverage for edge cases with empty input

Otherwise the architecture looks solid and matches the requirements. 
Will mark as "Needs work" for these fixes.
```

**Correction** :
```
Thanks for the review! I've addressed all the points:

1. Added return type declarations throughout
2. Made doExecute() abstract in ChatConsumerBase
3. Added additional test cases for edge cases

Status: Needs review
```

## Conclusion

Contribuer au projet AI suit les standards de la communauté Drupal. La clé est une communication claire, du code de qualité et des tests robustes. N'hésitez pas à poser des questions dans l'issue queue ou sur Slack.

Bienvenue dans la communauté des contributeurs AI pour Drupal !



