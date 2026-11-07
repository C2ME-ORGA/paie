# Processus de validation des règles métier

## Statuts
| Statut | Signification | Production |
|---|---|---|
| Brouillon | Proposition initiale | Interdite |
| À vérifier | Source, date ou champ d'application à confirmer | Interdite |
| À valider | Documentation vérifiée, arbitrage métier attendu | Interdite |
| Validée | Source et comportement confirmés par les valideurs compétents | Autorisée dans le seul périmètre validé |
| Bloquée | Contradiction ou donnée indispensable manquante | Interdite |
| Obsolète | Remplacée ou expirée | Interdite pour les nouvelles situations ; historique conservé |

## Circuit
1. Rédiger la règle avec un identifiant stable.
2. Qualifier la source : loi, règlement, convention collective, accord, procédure ou décision interne.
3. Identifier article exact, URL officielle, date de consultation, date d'effet et éventuelles dispositions transitoires.
4. Vérifier le champ d'application : salarié, contrat, catégorie, établissement et période.
5. Documenter exceptions, cas limites, décomptes et arrondis.
6. Faire confirmer le comportement opérationnel par C2ME.
7. Faire valider les points sociaux/juridiques par la personne compétente.
8. Passer à « Validée » uniquement après ces confirmations ; le ticket d'implémentation référence l'identifiant et la version.
9. Surveiller les sources et versionner toute évolution.

## Métadonnées minimales
Identifiant, titre, description fonctionnelle, type de règle, source et URL, article, date de consultation, dates d'effet début/fin, périmètre, préconditions, exceptions, cas limites, données requises, contrôles envisagés, statut, valideur/date, historique.

## Garde-fous
- Une règle non validée ne doit alimenter aucun calcul, contrôle bloquant ou décision automatique en production.
- En cas de doute ou de conflit, demander une vérification humaine.
- Ne pas présumer l'applicabilité d'une convention selon le seul secteur d'activité.
- Identifier les règles internes comme telles, sans les présenter comme des obligations légales.
