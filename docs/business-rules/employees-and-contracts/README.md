# Domaine — Salariés et contrats

**Version :** 0.2.0 — **Préparé le :** 7 novembre 2026  
**État :** documenté ; validation métier et juridique finale en attente.

## Décisions C2ME confirmées
- Un seul établissement dans le périmètre initial.
- CDI, CDD et apprentissage.
- Le temps partiel est une modalité attachée à un CDI ou un CDD, pas une nature autonome de contrat.
- Contrôle strict des périodes d'essai et régimes spécifiques : aucune validation automatique si les données ou sources manquent.
- Historique complet des fiches, contrats, avenants et événements.

## Inclus
Identification, formalités d'embauche, registre unique du personnel, contrats, temps partiel, périodes d'essai, apprentissage, renouvellement/terme du CDD, modifications et sortie.

## Exclus
Classifications conventionnelles détaillées, temps/absences/congés, éléments variables, calcul de paie/cotisations, indemnités et frais professionnels.

## Documents
- [rules.md](rules.md) — 15 règles métier.
- [legal-sources.md](legal-sources.md) — références officielles.
- [edge-cases.md](edge-cases.md) — cas limites et décisions ouvertes.
- [CHANGELOG.md](CHANGELOG.md) — historique.

## Principes
Une fiche salarié peut être liée à plusieurs contrats successifs. Les données d'identité sont séparées des données contractuelles. Dates de signature, de début, de fin, de saisie et d'effet sont distinctes. Un avenant ne remplace pas l'historique antérieur. Le modèle initial est mono-établissement. Les documents RH sont privés et soumis aux habilitations et à une matrice de conservation.

Le périmètre fonctionnel est confirmé ; les règles restent « À vérifier » ou « À valider » tant que sources conventionnelles et arbitrages C2ME ne sont pas confirmés.
