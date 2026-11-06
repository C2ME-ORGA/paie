# Workflow de développement — batiPaie

## 1. Sources de vérité

- **Jira** : périmètre fonctionnel, critères d'acceptation, dépendances, statut et version cible.
- **GitHub** : code source, branches, commits, Pull Requests, revues et contrôles automatisés.
- **OpenCode** : analyse, conception, implémentation et revue assistées par les agents du dépôt.
- **Validation humaine** : arbitrages métier, conformité sociale, fusion et mise en production.

Un ticket Jira ne doit pas être considéré comme terminé uniquement parce que le code existe. Les critères d'acceptation et la revue doivent être satisfaits.

## 2. Convention de branches

Après initialisation du dépôt, convention recommandée :

- `main` : code stable et versions publiées.
- `develop` : intégration des évolutions validées.
- `feature/BP-123-description-courte` : nouvelle fonctionnalité.
- `fix/BP-123-description-courte` : correction fonctionnelle.
- `hotfix/BP-123-description-courte` : correction urgente d'une version publiée.
- `docs/BP-123-description-courte` : documentation seule.

Une branche de travail part de `develop` et cible `develop` par Pull Request. Les versions validées sont promues de `develop` vers `main` par une Pull Request dédiée. Si le dépôt adopte une autre stratégie, mettre ce document à jour avant de commencer les développements.

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
2. Fusionner uniquement lorsque les contrôles sont verts et la revue acceptable.
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

Ne pas utiliser automatiquement `Closes BP-XXX` pour fermer le ticket lors de la fusion vers `develop` : le ticket peut nécessiter une validation fonctionnelle ou une promotion ultérieure vers `main`. Préférer une mention `Relates to BP-XXX` ou `Refs BP-XXX` dans le corps de PR, puis clôturer Jira après validation convenue.

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

## 8. Releases

Les versions Jira prévues sont `0.1.0`, `0.2.0`, `0.3.0` et `1.0.0`.
La version cible `1.0.0` est attendue au 30 novembre 2026.

Une version ne doit être publiée que si :
- les tickets affectés à la version sont terminés ou formellement reportés ;
- les tests et contrôles de release passent ;
- les notes de version sont préparées ;
- les migrations et consignes de déploiement sont documentées ;
- la validation humaine de mise en production est obtenue.

La date cible ne justifie jamais de contourner les exigences de sécurité ou de conformité.
