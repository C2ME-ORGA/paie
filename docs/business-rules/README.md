# Référentiel des règles métier — BatiPaie

Référentiel documentaire versionné, organisé par domaine fonctionnel.

| Domaine | Répertoire | État |
|---|---|---|
| Salariés et contrats | [employees-and-contracts](employees-and-contracts/README.md) | Documenté, validation finale en attente |
| Convention collective et classifications | À créer | Non commencé |
| Temps de travail, absences et congés | À créer | Non commencé |
| Éléments variables de paie | À créer | Non commencé |
| Calcul de paie et cotisations | À créer | Non commencé |
| Indemnités et frais professionnels | À créer | Non commencé |

## Gouvernance
- Chaque règle possède un identifiant stable, une description, un périmètre, une source, une date d'effet, des exceptions/cas limites et un statut.
- Distinguer loi/règlement, convention collective, accord d'entreprise et choix interne C2ME.
- Une règle documentée n'est pas automatiquement validée.
- **Une règle non validée ne peut pas être utilisée comme règle applicable en production.**
- Conserver les versions antérieures et les périodes d'effet ; ne jamais réécrire silencieusement l'historique.
- Aucune durée, valeur, taux ou règle conventionnelle ne doit être inventé.

Voir [validation-process.md](validation-process.md). Identifiants stables par domaine : `SAL-CON-001`, etc. Un identifiant retiré n'est jamais réattribué.
