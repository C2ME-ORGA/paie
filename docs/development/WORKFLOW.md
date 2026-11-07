# Workflow de développement — batiPaie

## 1. Sources de vérité

- **Jira** : périmètre fonctionnel, critères d'acceptation, dépendances, statut et version cible.
- **GitHub** : code source, branches, commits, Pull Requests, revues et contrôles automatisés.
- **OpenCode** : analyse, conception, implémentation et revue assistées par les agents du dépôt.
- **Validation humaine** : arbitrages métier, conformité sociale, fusion et mise en production.

Un ticket Jira ne doit pas être considéré comme terminé uniquement parce que le code existe. Les critères d'acceptation et la revue doivent être satisfaits.

## 2. Convention de branches

Stratégie officielle de promotion :

`feat/*` / `fix/*` → `develop` → `master` → `production` → tags / GitHub Releases

- `production` : état exact déployé en production. **Seule branche prévue pour publier un tag et une GitHub Release** ; cette exclusivité est une règle de processus appliquée par le workflow `Release`, et non une restriction technique globale de GitHub.
- `master` : code stable validé, candidat à la mise en production.
- `develop` : intégration des évolutions validées.
- `feat/BP-123-description-courte` : nouvelle fonctionnalité.
- `fix/BP-123-description-courte` : correction fonctionnelle.
- `hotfix/BP-123-description-courte` : correction urgente d'une version en production.
- `docs/BP-123-description-courte` : documentation seule.

Règles de promotion :

- une branche de travail (`feat/*`, `fix/*`, `docs/*`) part de `develop` et cible `develop` par Pull Request ;
- `develop` est promue vers `master` par une PR de promotion dédiée ;
- `master` est promue vers `production` par une PR de promotion dédiée ;
- un `hotfix/*` part de `production`, cible `production`, puis est rétro-fusé `production` → `master` → `develop` ;
- aucun commit direct n'est autorisé sur `develop`, `master` ou `production` : tout passe par Pull Request ;
- une release est publiée uniquement depuis `production` (voir §8).

La CI (`.github/workflows/tests.yml`) s'exécute sur les pushs vers `develop`, `master` et `production`, sur les PR ciblant ces trois branches, et manuellement via `workflow_dispatch`.

## 3. Convention de commits

Format :

`type(BP-123): description concise`

Types conseillés :
- `feat` : fonctionnalité
- `fix` : correction
- `refactor` : restructuration sans changement fonctionnel attendu
- `test` : tests
- `docs` : documentation
- `build` : dépendances/outillage
- `chore` : maintenance

Exemples :
- `feat(BP-42): add employee contract history`
- `fix(BP-57): prevent overlapping absence periods`
- `test(BP-57): cover absence boundary dates`

Chaque commit doit rester cohérent et ne pas mélanger plusieurs tickets indépendants.

## 4. Cycle d'un ticket

### Étape A — Préparation
1. Ouvrir le ticket Jira et vérifier description, critères d'acceptation, parent, dépendances et version cible.
2. Vérifier que le ticket est suffisamment précis et qu'aucune décision métier/réglementaire ne manque.
3. Demander à l'agent `architect` une analyse si le ticket touche plusieurs domaines, le modèle de données, une intégration ou une règle de paie.
4. Obtenir la validation humaine du plan lorsqu'une décision structurante ou métier est requise.

### Étape B — Développement
1. Créer une branche dédiée à partir de `develop`.
2. Confier au `developer` uniquement le ticket validé.
3. Faire des commits atomiques en incluant la clé Jira.
4. Ajouter les tests correspondant aux critères d'acceptation.
5. Ne jamais ajouter une règle de paie, un taux, un plafond ou une formule sans source, date d'effet et périmètre validés.

### Étape C — Pull Request
1. Ouvrir une PR de la branche de travail vers `develop`.
2. Inclure la clé Jira dans le titre et le corps de la PR.
3. Décrire les changements, les tests exécutés, les risques et les éventuelles migrations.
4. Laisser la PR en brouillon tant que le travail ou les tests ne sont pas terminés.
5. Demander une revue à `reviewer`.
6. Corriger les constats bloquants/majeurs et faire repasser les contrôles.

### Étape D — Validation et clôture
1. Un humain vérifie les critères d'acceptation et les éventuelles décisions métier.
2. Fusionner uniquement lorsque les contrôles sont verts et la revue acceptable ; la promotion vers `master` puis `production` fait l'objet de PR dédiées (§8).
3. Mettre à jour le ticket Jira avec le résultat réel, les liens PR/commit et les éventuelles réserves.
4. Passer le ticket à l'état correspondant au workflow Jira réel. Ne jamais supposer le nom ou l'identifiant d'une transition.
5. Supprimer la branche après fusion si elle n'est plus utile.

## 5. Liaison Jira / GitHub

Toujours faire apparaître la clé `BP-XXX` :
- dans le nom de branche ;
- dans le message de commit ;
- dans le titre de Pull Request ;
- dans le corps de Pull Request.

Exemple de titre : `[BP-42] Ajouter l'historique des contrats salariés`.

La clé permet à l'intégration Jira/GitHub de rattacher les activités si l'intégration de développement est activée et correctement configurée. Vérifier le rattachement réel dans Jira ; ne pas considérer qu'une simple mention garantit la synchronisation.

Ne pas utiliser automatiquement `Closes BP-XXX` pour fermer le ticket lors de la fusion vers `develop` : le ticket peut nécessiter une validation fonctionnelle ou une promotion ultérieure vers `master` puis `production`. Préférer une mention `Relates to BP-XXX` ou `Refs BP-XXX` dans le corps de PR, puis clôturer Jira après validation convenue.

## 6. Règles de sécurité et de conformité

- Aucun secret, jeton, mot de passe, certificat privé ou fichier `.env` ne doit être commité.
- Aucun dump de base de données réelle ni donnée personnelle de salarié dans les fixtures, tests ou artefacts.
- Les tests utilisent des données fictives.
- Les logs et captures joints aux PR ne doivent pas exposer de données personnelles ou de paie.
- Les migrations de données sensibles et les changements d'autorisations nécessitent une revue spécifique.
- Une revue de code ne remplace pas la validation de la règle sociale par une source officielle et une personne compétente.

## 7. Definition of Done

Un ticket est prêt à être clôturé lorsque :
- tous les critères d'acceptation sont satisfaits ;
- les tests pertinents sont ajoutés et passent ;
- les contrôles qualité disponibles passent ;
- les permissions et données personnelles ont été examinées ;
- les migrations et impacts de données sont documentés ;
- la PR a été revue et fusionnée ;
- Jira contient le lien vers la PR et le compte rendu ;
- les éventuelles réserves sont explicitement enregistrées.

## 8. Promotions et publication

### 8.1 Promotions entre branches

| Étape | Source | Cible | Contenu attendu de la PR |
|---|---|---|---|
| Intégration | `feat/*`, `fix/*`, `docs/*` | `develop` | Ticket(s) Jira, tests, risques |
| Stabilisation | `develop` | `master` | Tickets inclus, résultats des contrôles |
| Mise en production | `master` | `production` | Version visée, risques, migrations et consignes de déploiement |
| Correction urgente | `hotfix/*` | `production` | Incident, correctif, plan de rétro-fusion |

Après une mise en production ou un hotfix, rétro-fuser `production` → `master` → `develop` afin d'aligner les branches. Une PR de promotion ne doit contenir aucune modification de code hors des commits déjà validés sur la branche source.

### 8.2 Publication d'une release

Le workflow `.github/workflows/release.yml` :

- se déclenche manuellement (`workflow_dispatch`) avec une version `MAJOR.MINOR.PATCH` ;
- refuse toute exécution hors de la branche `production` ;
- rejoue les contrôles qualité (Composer, Pest, Pint, PHPStan, build frontend) ;
- refuse une version invalide ou un tag `vX.Y.Z` déjà existant ;
- crée le tag `vX.Y.Z` et la GitHub Release avec notes générées.

Ces contrôles ne portent que sur les opérations du workflow : ils garantissent qu'il ne publie ni une version mal formée ni un tag existant, mais ne peuvent pas empêcher un utilisateur autorisé de créer manuellement un tag.

Prérequis avant publication :
- les tickets affectés à la version sont terminés ou formellement reportés ;
- les tests et contrôles de release passent ;
- les notes de version sont préparées et relues ;
- les migrations et consignes de déploiement sont documentées ;
- la validation humaine de mise en production est obtenue.

Les versions Jira prévues sont `0.1.0`, `0.2.0`, `0.3.0` et `1.0.0`.
La version cible `1.0.0` est attendue au 30 novembre 2026.

La date cible ne justifie jamais de contourner les exigences de sécurité ou de conformité.

## 9. Protections de branches et environnements

Ces réglages se configurent manuellement dans GitHub (Settings → Rules / Branches / Environments) et ne sont pas versionnés dans le dépôt.

| Cible | Règles recommandées |
|---|---|
| `production` | PR obligatoire (depuis `master` ou `hotfix/*`), checks CI requis, revue approuvée, force push interdit, suppression interdite |
| `master` | PR obligatoire (depuis `develop` ou `hotfix/*`), checks CI requis, revue approuvée, force push et suppression interdits |
| `develop` | PR obligatoire (depuis `feat/*`, `fix/*`, `docs/*`), checks CI requis, force push et suppression interdits |
| Tags `v*` | Protection des tags : modification et suppression interdites ; création autorisée (manuelle ou via le workflow `Release`) |
| Environnement `production` | Réviseurs requis avant publication, branches de déploiement limitées à `production` |

Checks à rendre obligatoires (jobs du workflow `CI`) :

- `Composer`
- `Quality / Pest`
- `Quality / Pint`
- `Quality / PHPStan`
- `Frontend build`

Réglages complémentaires : activer « Require a pull request before merging », « Dismiss stale approvals » et « Require conversation resolution » sur les trois branches protégées ; laisser les permissions par défaut d'Actions en lecture seule et ne les élargir qu'au job de release (`contents: write`) ; ne jamais exposer de secret de production dans un workflow de PR.

Deux limites de GitHub sont à connaître :

- les protections de branches ne permettent pas de restreindre nativement la **branche source** d'une Pull Request ; les règles « depuis `master` ou `hotfix/*` » reposent sur la convention, la revue et, si nécessaire, un check dédié ;
- la protection des tags `v*` couvre la modification et la suppression des tags existants, pas leur création : un utilisateur disposant des droits nécessaires peut toujours créer un tag manuellement, et le workflow `Release` peut créer le sien via `gh release create`.
