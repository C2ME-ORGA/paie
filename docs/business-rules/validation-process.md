# Processus de validation des règles métier

## Statuts
| Statut | Signification | Production |
|---|---|---|
| Brouillon | Proposition initiale | Interdite |
| À vérifier | Source, date ou champ d'application à confirmer | Interdite |
| À valider | Documentation vérifiée, arbitrage métier attendu | Interdite |
| Validée | Source et comportement confirmés par les valideurs compétents, preuve de décision enregistrée | Autorisée dans le seul périmètre et la période validés |
| Bloquée | Contradiction ou donnée indispensable manquante | Interdite |
| Obsolète | Remplacée ou expirée | Interdite pour les nouvelles situations ; historique conservé |

## Circuit
1. Rédiger la règle avec un identifiant stable.
2. Qualifier la source : loi, règlement, convention collective, accord, procédure ou décision interne.
3. Identifier l'article exact, l'URL officielle, la date de consultation, la date d'entrée en vigueur et les dispositions transitoires.
4. Vérifier le champ d'application : salarié, contrat, catégorie, établissement et période.
5. Documenter exceptions, cas limites, décomptes et arrondis.
6. Faire confirmer le comportement opérationnel par C2ME.
7. Faire valider les points sociaux/juridiques par la personne compétente.
8. Enregistrer la preuve de validation avant de passer au statut « Validée ».
9. Le ticket de développement doit référencer l'identifiant **et la version exacte** de chaque règle appliquée.
10. Surveiller les sources et versionner toute évolution ; une nouvelle version ne modifie pas rétroactivement les situations antérieures sans décision documentée.

## Preuve de validation obligatoire
Pour chaque règle validée, conserver :
- identifiant et version de la règle ;
- décision explicite (validée, validée sous conditions, refusée) ;
- périmètre matériel, personnel et temporel de la décision ;
- nom ou identifiant du valideur et sa qualité/fonction ;
- date de validation ;
- références et versions des sources examinées ;
- réserves, conditions et date de réexamen éventuelle.

Une validation globale d'un document ne vaut pas validation de toutes les règles si les règles ou réserves ne sont pas explicitement identifiées.

## Métadonnées minimales
Identifiant, titre, description fonctionnelle, type de règle, source et URL, article, date de consultation, date d'entrée en vigueur, dates d'effet début/fin, périmètre, préconditions, exceptions, cas limites, données requises, contrôles envisagés, statut, preuve de validation, historique.

## Garde-fous
- Une règle non validée ne doit alimenter aucun calcul, contrôle bloquant ou décision automatique en production.
- En cas de doute ou de conflit, demander une vérification humaine.
- Ne pas présumer l'applicabilité d'une convention selon le seul secteur d'activité.
- Identifier les règles internes comme telles, sans les présenter comme des obligations légales.
