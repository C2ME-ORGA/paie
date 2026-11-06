---
name: payroll-domain-model
description: Modéliser les données RH et de paie de batiPaie avec historisation, périodes, règles datées, traçabilité et séparation des responsabilités.
compatibility: opencode
metadata:
  project: batipaie
  domain: payroll
---

# Modèle métier RH et paie

## Quand utiliser cette skill
Pour les salariés, contrats, avenants, classifications, établissements, temps, absences, indemnités, variables, périodes et bulletins.

## Invariants métier
- Une donnée applicable à une période doit être datée avec une période de validité explicite (`valid_from`, `valid_to` ou équivalent) lorsque son historique a une incidence.
- Ne jamais modifier rétroactivement une règle ou une donnée déjà utilisée dans une paie validée sans mécanisme explicite de régularisation et de traçabilité.
- Distinguer date de saisie, date d'effet, période de paie et date de validation.
- Une paie validée doit conserver un instantané autonome des données nécessaires à sa justification et à sa reproduction.
- Distinguer les données contractuelles, les variables mensuelles, les résultats calculés et les écritures de correction.
- Conserver la provenance de chaque variable : saisie, import, calcul automatique, correction manuelle, justificatif.
- Utiliser des types décimaux adaptés aux montants ; ne jamais utiliser de flottants binaires pour les calculs monétaires.
- Gérer explicitement les entrées/sorties en cours de période, temps partiel, changements de contrat, absences et régularisations.
- Ne jamais supposer qu'une convention collective ou un IDCC s'applique à tous les salariés. L'accord applicable doit être déterminé et historisé par population/établissement/salarié selon les règles validées.

## Modélisation
- Préférer des statuts métier explicites et des transitions contrôlées.
- Prévoir contraintes d'intégrité en base et index sur les recherches fréquentes.
- Définir les règles de suppression : données de paie et pièces justificatives ne doivent pas être supprimées comme de simples données temporaires.
- Toute donnée sensible doit avoir une politique d'accès et de conservation définie.

## Questions à résoudre avant implémentation
- Quelle est la source de vérité : contrat, planning, pointage ou saisie de variable ?
- Quelle date détermine l'applicabilité de la règle ?
- Comment gérer les corrections après validation ?
- Quelle preuve doit être conservée pour audit ?
- Quels cas limites doivent être testés ?
