# Référentiel des règles métier — BatiPaie

Ce répertoire est la source documentaire des règles fonctionnelles de BatiPaie. Il est organisé par domaine et évolue de manière versionnée.

## Domaines

| Domaine | Répertoire | État |
|---|---|---|
| Salariés et contrats | [employees-and-contracts/](employees-and-contracts/README.md) | Première version documentée — validation finale en attente |
| Convention collective et classifications | À créer | Non commencé |
| Temps de travail, absences et congés | À créer | Non commencé |
| Éléments variables de paie | À créer | Non commencé |
| Calcul de paie et cotisations | À créer | Non commencé |
| Indemnités et frais professionnels | À créer | Non commencé |

## Règles de gouvernance

- Chaque règle possède un identifiant stable, une description, un périmètre, des sources, une date d'effet, des exceptions/cas limites et un statut.
- Les sources distinguent le droit légal/réglementaire, la convention collective, l'accord d'entreprise et les choix internes C2ME.
- Une règle documentée n'est pas automatiquement validée.
- **Aucune règle au statut « À vérifier », « À valider » ou « Bloquée » ne peut être utilisée comme règle applicable en production.**
- Une évolution crée une nouvelle version ou une entrée d'historique ; elle ne réécrit pas silencieusement le passé.
- Les règles sociales sont vérifiées à partir de sources officielles et pour une population et une période d'effet identifiées.
- Les règles internes ne doivent jamais être présentées comme des obligations légales.

## Cycle de vie

Voir [validation-process.md](validation-process.md) pour le circuit de rédaction, vérification, validation métier et autorisation d'implémentation.

## Convention d'identifiants

Les identifiants de règles sont stables et propres au domaine : `SAL-CON-001`, `SAL-CON-002`, etc. Un identifiant retiré n'est jamais réattribué à une autre règle.
