---
name: testing-quality-gates
description: Définir et exécuter les tests Pest, contrôles qualité, non-régression et gates CI pour batiPaie.
compatibility: opencode
metadata:
  project: batipaie
  quality: testing
---

# Tests et qualité logicielle

## Quand utiliser cette skill
Pour toute nouvelle fonctionnalité, correction, refactorisation, migration, règle métier ou préparation de release.

## Règles
- Lire les scripts Composer, la configuration Pest, CI et conventions du dépôt avant d'exécuter une commande.
- Ajouter un test qui reproduit le bug avant de corriger lorsque c'est possible.
- Couvrir les comportements métier et les permissions, pas seulement les détails d'implémentation.
- Utiliser des factories et jeux de données explicites. Éviter les tests dépendants de données de production.
- Tester les migrations sur une base propre et vérifier leur rollback lorsque pertinent.
- Ne pas affaiblir un test, supprimer une assertion ou modifier un seuil pour faire passer artificiellement la CI.
- Les calculs de paie exigent des tests de référence, limites, arrondis, dates d'effet et non-régression.
- Ne pas annoncer une suite « verte » si elle n'a pas été exécutée.

## Gate de fin de tâche
1. Tests ciblés.
2. Suite Pest pertinente.
3. Laravel Pint (ou outil configuré dans le dépôt).
4. Analyse statique si configurée (Larastan/PHPStan).
5. Build frontend si les assets changent.
6. Vérification du diff, migrations et absence de secrets.
7. Résumer commandes et résultats exacts.

## Gestion des échecs
Distinguer clairement échec lié au changement, environnement indisponible, dépendance externe et test préexistant instable. Ne pas masquer un échec.
