# Processus de validation des règles métier

## Objectif

Garantir qu'une règle BatiPaie est sourcée, applicable à la population concernée, comprise fonctionnellement et explicitement validée avant toute implémentation.

## Statuts

| Statut | Signification | Utilisation en production |
|---|---|---|
| Brouillon | Proposition non vérifiée | Interdite |
| À vérifier | Source, date d'effet ou champ d'application à contrôler | Interdite |
| À valider | Vérification documentaire réalisée, arbitrage métier attendu | Interdite |
| Validée | Source et comportement confirmés par le valideur compétent | Autorisée dans le périmètre et les dates validés |
| Bloquée | Contradiction ou information indispensable manquante | Interdite |
| Obsolète | Règle remplacée ou arrivée à échéance | Interdite pour les nouvelles situations ; historique conservé |

## Étapes de validation

1. **Rédaction** — attribuer un identifiant stable et décrire le comportement attendu sans préjuger du choix technique.
2. **Qualification de la source** — identifier la nature de la source (loi, règlement, convention collective, accord, procédure interne), l'article exact, l'URL officielle et la date de consultation.
3. **Vérification temporelle** — préciser la date d'entrée en vigueur, la période concernée et, si nécessaire, les dispositions transitoires.
4. **Vérification du champ d'application** — déterminer les salariés, contrats, établissements et situations concernés. Une convention collective ne doit pas être supposée applicable à tous les salariés.
5. **Analyse des exceptions** — documenter les dérogations, cas limites, règles de décompte et éventuels arrondis. Si une règle dépend d'une donnée absente, la marquer bloquante.
6. **Validation métier** — faire confirmer par C2ME le comportement opérationnel et les paramètres internes.
7. **Validation sociale/juridique** — faire confirmer les points nécessitant une expertise paie ou juridique. La présence d'un lien Légifrance ne remplace pas cette validation.
8. **Autorisation d'implémentation** — la règle passe à « Validée » seulement après les validations requises. Le ticket de développement référence l'identifiant et la version de règle.
9. **Maintenance** — surveiller les évolutions des sources, créer une nouvelle version datée et préserver l'historique.

## Données minimales d'une règle

- Identifiant stable et titre.
- Description fonctionnelle et comportement attendu.
- Nature de la règle et source(s) officielles.
- URL, article/paragraphe, date de consultation.
- Date d'effet début et, si connue, date de fin.
- Population et périmètre.
- Préconditions, exceptions, cas limites et décompte/arrondis.
- Données requises et contrôles applicatifs envisagés.
- Statut, valideur(s), date et référence de validation.
- Historique des modifications.

## Règles de sécurité du référentiel

- Une règle non validée ne doit pas alimenter un calcul, un contrôle bloquant ou une décision automatique en production.
- En cas de doute ou de conflit de sources, le système doit demander une vérification humaine plutôt que choisir arbitrairement.
- Ne jamais inventer un taux, une durée, un plafond ou une règle conventionnelle.
- Ne pas déduire le champ d'application d'une convention à partir du seul secteur d'activité déclaré.
- Les règles internes C2ME sont identifiées comme telles et ne sont pas présentées comme des obligations légales.
