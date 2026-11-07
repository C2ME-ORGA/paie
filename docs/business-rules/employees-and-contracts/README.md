# Domaine — Salariés et contrats

**Version :** 0.2.0  
**Date de préparation :** 7 novembre 2026  
**Statut global :** documenté — validation métier et juridique finale en attente.

## Décisions de périmètre validées par C2ME

- Un seul établissement dans le périmètre fonctionnel initial.
- Contrats pris en charge : CDI, CDD et apprentissage.
- Le temps partiel est une modalité de durée du travail applicable à un CDI ou un CDD, et non une nature de contrat autonome.
- Contrôle strict des périodes d'essai et périodes spécifiques : pas de validation automatique en l'absence des sources et données requises.
- Historique complet des fiches salariés, contrats, avenants et événements.

## Inclus

Identification du salarié, embauche et formalités, suivi du registre unique du personnel, contrats, temps partiel, périodes d'essai, apprentissage, renouvellement/terme du CDD, modifications contractuelles, événements et sortie.

## Hors périmètre

Classification conventionnelle détaillée, gestion du temps et des absences, éléments variables, calcul des bulletins/cotisations, indemnités et frais professionnels. Ces domaines sont documentés séparément.

## Fichiers

- [rules.md](rules.md) — règles fonctionnelles identifiées.
- [legal-sources.md](legal-sources.md) — sources juridiques et dates de consultation.
- [edge-cases.md](edge-cases.md) — cas limites et décisions à obtenir.
- [CHANGELOG.md](CHANGELOG.md) — historique des versions.

## Principes de modélisation fonctionnelle

- Une fiche salarié peut être liée à plusieurs contrats successifs.
- Les données d'identité sont séparées des données propres à chaque contrat.
- Les dates de signature, de début, de fin, de saisie et d'effet sont distinctes.
- Les avenants et changements contractuels ne remplacent pas l'historique antérieur.
- Le modèle initial est mono-établissement ; toute évolution multi-établissements nécessitera un arbitrage et une version du périmètre.
- Les documents RH sont privés et accessibles selon les habilitations ; les règles de conservation et d'effacement sont définies par catégorie de document.

## Validation

Le périmètre fonctionnel est confirmé par l'utilisateur. Les règles restent individuellement « À vérifier » ou « À valider » tant que les sources applicables, notamment conventionnelles, et les décisions C2ME ne sont pas formellement confirmées. Voir [le processus commun](../validation-process.md).
