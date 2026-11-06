---
name: payroll-calculation-engine
description: Concevoir, implémenter et tester le moteur de calcul de paie, ses règles versionnées, arrondis, régularisations et simulations déterministes.
compatibility: opencode
metadata:
  project: batipaie
  domain: payroll-engine
---

# Moteur de calcul de paie

## Quand utiliser cette skill
Pour tout calcul de brut, cotisations, net, prélèvement à la source, coût employeur, avantages en nature, heures supplémentaires, absences, indemnités et régularisations.

## Principes non négociables
- Le moteur doit être indépendant de Livewire, Filament, contrôleurs et vues.
- Un calcul doit être déterministe : mêmes entrées, même version de règles et même contexte donnent le même résultat.
- Chaque résultat conserve la version des règles, les paramètres, les entrées, les étapes de calcul et les arrondis appliqués.
- Utiliser des décimaux ou une bibliothèque monétaire adaptée. Définir précisément la précision intermédiaire et les règles d'arrondi.
- Les règles réglementaires sont versionnées avec dates d'effet. Un calcul passé ne doit pas changer silencieusement après une mise à jour des barèmes.
- Séparer simulation, calcul, validation, clôture et archivage.
- Rendre les opérations idempotentes pour éviter les doubles calculs, doublons de variables ou doubles écritures.
- Toute correction d'une paie clôturée passe par un mécanisme de régularisation explicite.

## Architecture attendue
- Entrée de calcul validée et normalisée.
- Contexte de paie immuable (salarié, contrat applicable, période, établissement, convention/règles).
- Résolution des règles applicables à la date.
- Calculs ordonnés et traçables.
- Résultat détaillé avec bases, taux, montants, plafonds, arrondis et explications.
- Snapshot de calcul et identifiant de version du moteur/référentiel.

## Tests indispensables
- Tests unitaires par règle.
- Tests de non-régression avec jeux d'exemples validés.
- Cas de limites de tranches, plafonds, proratisations et arrondis.
- Entrée/sortie en cours de mois, temps partiel, heures supplémentaires, absences, maintien, avantages en nature, PAS et régularisations.
- Comparaison avec des résultats de référence validés par un professionnel de la paie.

## Sécurité métier
Ne jamais présenter un calcul comme juridiquement ou socialement conforme tant que ses règles et résultats n'ont pas été validés par une source officielle à jour et par un expert paie compétent.
