---
name: laravel-domain-architecture
description: Concevoir et faire évoluer l'architecture Laravel de batiPaie, ses domaines métier, services, modèles, migrations, API et frontières entre modules.
compatibility: opencode
metadata:
  project: batipaie
  stack: laravel
---

# Architecture Laravel par domaines

## Quand utiliser cette skill
Pour toute évolution touchant la structure du projet, les domaines métier, les modèles Eloquent, les services applicatifs, les événements, les jobs, les API ou les dépendances entre modules.

## Principes
- Commencer par lire le code existant, `composer.json`, les routes, les providers, les migrations et les tests. Ne jamais supposer que le dépôt est encore dans son état initial.
- Respecter les conventions déjà établies lorsqu'elles sont cohérentes. Proposer une refactorisation séparée plutôt que mélanger refonte et fonctionnalité.
- Organiser le métier par domaines cohérents : Company, Employees, Contracts, WorkingTime, Leave, Expenses, BusinessTrips, ConstructionSites, Payroll, PayrollRules, SocialDeclarations, Documents, Reporting et Integrations. Adapter cette liste à la structure réelle du dépôt.
- Éviter les dépendances circulaires. Les domaines communiquent par contrats explicites, services applicatifs, événements ou interfaces.
- Garder les contrôleurs et composants Livewire fins. Placer les règles métier dans des services/actions ou objets métier testables.
- Ne pas créer une abstraction, interface, repository ou couche supplémentaire sans besoin démontré.
- Utiliser les Form Requests / validation dédiée aux frontières HTTP et des policies pour l'autorisation.
- Toute modification de schéma doit être portée par une migration réversible autant que possible.
- Préserver les données historiques : ne pas écraser une donnée contractuelle ou de paie qui doit être historisée.

## Workflow obligatoire
1. Inspecter les fichiers et conventions proches de la fonctionnalité.
2. Identifier le domaine propriétaire, les dépendances et les impacts sur les autres domaines.
3. Présenter un plan bref si le changement touche plusieurs domaines ou le schéma.
4. Implémenter le plus petit changement cohérent.
5. Ajouter ou adapter tests unitaires, feature et architecture selon le risque.
6. Exécuter les vérifications ciblées puis la suite pertinente.
7. Résumer fichiers modifiés, choix, tests exécutés et limites restantes.

## À éviter
- Logique métier dans Blade, composants UI ou contrôleurs.
- Accès direct à une table d'un autre domaine sans contrat explicite.
- Modifications globales de conventions sans migration progressive.
- Supposer une structure de dossiers non vérifiée.
