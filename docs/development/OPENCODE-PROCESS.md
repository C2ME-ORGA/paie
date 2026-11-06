# Processus OpenCode — batiPaie

## Rôles des agents

| Agent | Responsabilité | Peut modifier le code |
|---|---|---|
| `architect` | Analyse et conception | Non |
| `developer` | Implémentation et tests | Oui |
| `reviewer` | Revue indépendante | Non |

## Prompt de démarrage d'un ticket

```text
Traite le ticket Jira BP-XXX.

Commence par lire AGENTS.md et les skills pertinents.
Vérifie le ticket Jira, ses critères d'acceptation, ses dépendances et sa version cible.
Inspecte l'état réel du dépôt et les versions installées.
Ne modifie rien avant d'avoir résumé le périmètre et signalé les ambiguïtés.

Si une conception est nécessaire, délègue d'abord à architect.
Après validation du plan, implémente uniquement le ticket.
Ajoute les tests associés et exécute les contrôles disponibles.
Ne commit, ne push, ne fusionne et ne déploie rien sans demande explicite.

Termine par un compte rendu avec fichiers modifiés, tests, résultats,
risques et éventuelles validations encore nécessaires.
```

## Prompt de revue

```text
Effectue une revue indépendante de la Pull Request liée à BP-XXX.
Lis AGENTS.md, les skills pertinents, le ticket et ses critères d'acceptation.
Examine le diff et les tests. Recherche les régressions, problèmes de sécurité,
fuites de données personnelles, défauts de modèle de données et règles de paie
non sourcées. Ne modifie aucun fichier.

Classe chaque constat par gravité et donne le fichier, la ligne,
la conséquence et la correction attendue.
Conclue par APPROUVÉ, APPROUVÉ AVEC RÉSERVES ou À CORRIGER.
```

## Actions nécessitant une autorisation humaine

- arbitrage d'une règle de paie ou d'une interprétation conventionnelle ;
- changement de périmètre ou de critères d'acceptation ;
- modification des dépendances structurantes ;
- commit/push si non explicitement délégué ;
- fusion de Pull Request ;
- publication d'une release ;
- déploiement et migration en production.
