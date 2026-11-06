---
name: jira-delivery-workflow
description: Exécuter le backlog batiPaie dans Jira BP, sélectionner les tickets, maintenir leur traçabilité et préparer les versions sans modifier le périmètre arbitrairement.
compatibility: opencode
metadata:
  project: batipaie
  project-management: jira
---

# Workflow de livraison Jira batiPaie

## Références projet
- Projet Jira : `BP` (Batipaie).
- Backlog initial : Epics → Tâches → Sous-tâches.
- Dépôt : `C2ME-ORGA/paie`.
- Versions planifiées : `0.1.0`, `0.2.0`, `0.3.0`, `1.0.0`.
- Date cible de la 1.0.0 : 30 novembre 2026.

## Quand utiliser cette skill
Lorsqu'une demande fait référence à un ticket BP-*, à une tâche du backlog, à une livraison ou à une version.

## Règles de travail
- Lire le ticket Jira et ses critères d'acceptation avant de coder.
- Vérifier ses parents, sous-tâches, dépendances et statut.
- Ne pas supposer qu'un ticket est terminé parce que le code semble présent.
- Ne pas créer de doublon Jira sans rechercher les tickets existants.
- Ne pas modifier échéance, version, priorité, périmètre ou statut sans instruction explicite.
- Une demande technique doit rester traçable vers un ticket ; ajouter la clé Jira dans les commits/PR lorsque la convention du dépôt le permet.
- Signaler les tickets bloqués par une règle métier, une source réglementaire ou une décision d'architecture non validée.
- Avant de clôturer : vérifier les critères d'acceptation, tests, documentation et revue.
- La date cible du 30/11/2026 est une contrainte de planification, pas une garantie de conformité ou d'exhaustivité fonctionnelle.

## Compte rendu attendu
- Ticket traité.
- Fonctionnalité livrée.
- Fichiers/zone concernés.
- Critères d'acceptation vérifiés.
- Tests exécutés et résultats.
- Risques, dette ou tickets liés à créer.
