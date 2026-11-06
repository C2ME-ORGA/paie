---
name: french-btp-social-compliance
description: Vérifier et implémenter les règles françaises de paie et les spécificités BTP, conventions collectives, déplacements, CIBTP et DSN avec sources datées.
compatibility: opencode
metadata:
  project: batipaie
  jurisdiction: france
  sector: btp
---

# Conformité sociale française et BTP

## Quand utiliser cette skill
Pour les conventions collectives, classifications, minima, temps de travail, heures supplémentaires, congés, indemnités de déplacement, grands déplacements, CIBTP, DSN, cotisations et prélèvement à la source.

## Règle absolue de vérification
- Ne jamais inventer un taux, plafond, minimum conventionnel, code DSN, formule ou seuil.
- Vérifier les textes et barèmes à la date d'effet concernée, en privilégiant les sources officielles : Légifrance, BOSS, URSSAF, Net-entreprises, service-public.fr, CIBTP et organismes officiels compétents.
- Noter l'URL, le texte/référence, la date de consultation, la date d'effet et le périmètre d'application dans la documentation/référentiel.
- Distinguer clairement obligation légale, disposition conventionnelle, accord d'entreprise, pratique interne et hypothèse de travail.
- En cas de contradiction, d'incertitude ou de cas individuel complexe, suspendre l'implémentation de la règle et demander validation à un professionnel compétent.

## BTP
- Ne pas présumer qu'un seul IDCC s'applique à tous les salariés. Vérifier le champ d'application et la catégorie concernée (ouvriers, ETAM, cadres, etc.).
- Traiter séparément les règles propres aux déplacements, petits/grands déplacements, repas, transport, trajet, hébergement et justificatifs.
- Ne pas assimiler automatiquement temps de trajet, temps de travail effectif et indemnité de déplacement.
- Prévoir la gestion des caisses de congés payés BTP/CIBTP selon le périmètre et les procédures applicables.
- Conserver les justificatifs et la méthode de calcul utilisée.

## Livrable de chaque règle
1. Description métier.
2. Source officielle et référence précise.
3. Date d'effet et date de fin éventuelle.
4. Population et conditions d'application.
5. Formule et exemples validés.
6. Cas limites et tests.
7. Responsable/validation métier.
