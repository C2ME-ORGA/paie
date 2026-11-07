# Règles métier — Salariés et contrats

**Version 0.2.0 — Référence documentaire : 7 novembre 2026**  
**État :** travail préparatoire ; aucune règle ci-dessous n'est autorisée en production.

> La date de consultation d'une source n'est pas sa date d'effet. Pour chaque situation, appliquer la version du texte en vigueur à sa date et dans son champ d'application. Les références conventionnelles C2ME restent à confirmer.

## SAL-CON-001 — Identification du salarié
- **Règle :** fiche individuelle et identifiant interne stable ; ne collecter que les données nécessaires à la relation de travail et aux formalités.
- **Sources :** Code du travail L.1221-10 et suivants ; RGPD art. 5(1)(c) et 6.
- **Date :** RGPD applicable depuis le 25/05/2018 ; L.1221-10 depuis le 01/05/2008.
- **Périmètre :** salariés C2ME.
- **Cas limites :** homonymie, changement d'état civil, salarié étranger, donnée rectifiée/effacée.
- **Contrôle :** distinguer données obligatoires, nécessaires et facultatives ; documenter finalité et habilitations.
- **Statut :** À valider.

## SAL-CON-002 — DPAE
- **Règle :** suivre la DPAE avant l'embauche effective ; création de fiche ≠ déclaration.
- **Source :** Code du travail L.1221-10 à L.1221-12-1 ; L.1221-10 depuis le 01/05/2008.
- **Périmètre :** embauche d'un salarié.
- **Cas limites :** déclaration corrigée, annulée, rejetée ou justificatif absent.
- **Contrôle :** enregistrer date/heure, statut, organisme, référence et justificatif ; signaler toute absence/non-confirmation.
- **Statut :** À valider.

## SAL-CON-003 — Registre unique du personnel
- **Règle :** tenir le registre pour l'établissement C2ME et inscrire les personnes selon les exigences légales.
- **Source :** Code du travail L.1221-13 à L.1221-15-1 ; L.1221-13 en vigueur depuis le 29/01/2017.
- **Périmètre :** établissement employant des salariés.
- **Cas limites :** stagiaires/volontaires en partie spécifique, rectification, salariés détachés accueillis.
- **Contrôle :** ordre des embauches, mentions réglementaires, historique non destructif ; vérifier modalités de tenue informatique.
- **Statut :** À valider.

## SAL-CON-004 — Nature du contrat et motif du CDD
- **Règle :** distinguer CDI, CDD et apprentissage. Le temps partiel est une modalité complémentaire.
- **Sources :** Code du travail L.1221-2, L.1242-1 et L.1242-2 ; livre II de la sixième partie pour l'apprentissage.
- **Périmètre :** nouveaux contrats et contrats successifs.
- **Cas limites :** motif légal de recours, contrat particulier, apprentissage, temps partiel.
- **Contrôle :** motif et fondement obligatoires pour un CDD ; empêcher validation sans justification.
- **Statut :** À valider.

## SAL-CON-005 — Écrit, mentions et transmission du CDD
- **Règle :** CDD écrit avec motif précis et mentions applicables ; suivre la transmission au salarié.
- **Source :** Code du travail L.1242-12 et L.1242-13, en vigueur depuis le 01/05/2008.
- **Périmètre :** CDD concernés.
- **Cas limites :** terme précis/imprécis, remplacement, durée minimale, poste, convention, rémunération, période d'essai.
- **Contrôle :** champs conditionnels selon le motif ; preuve de transmission au plus tard dans les deux jours ouvrables suivant l'embauche.
- **Statut :** À valider.

## SAL-CON-006 — Contrat à temps partiel
- **Règle :** contrat écrit avec les mentions légales.
- **Source :** Code du travail L.3123-6, en vigueur depuis le 10/08/2016.
- **Périmètre :** CDI ou CDD à temps partiel.
- **Cas limites :** durée hebdomadaire/mensuelle, répartition, communication des horaires, modifications, heures complémentaires et dérogations.
- **Contrôle :** vérifier les mentions et dispositions dérogatoires applicables.
- **Statut :** À valider.

## SAL-CON-007 — Période d'essai du CDI
- **Règle :** période d'essai et possibilité de renouvellement expressément stipulées ; contrôler plafonds et conditions.
- **Source :** Code du travail L.1221-19 à L.1221-26 ; L.1221-19 à L.1221-23 en vigueur depuis le 27/06/2008.
- **Périmètre :** CDI.
- **Cas limites :** catégorie, accord de branche étendu, durée plus favorable, suspension, délai de prévenance, déduction d'un stage (L.1221-24).
- **Contrôle :** ne calculer qu'après identification de la catégorie et de la convention applicable ; aucun renouvellement automatique.
- **Statut :** À vérifier — convention collective indispensable.

## SAL-CON-008 — Période d'essai du CDD
- **Règle :** régime CDD distinct du CDI.
- **Source :** Code du travail L.1242-10 et L.1242-11 ; L.1242-10 depuis le 01/05/2008.
- **Périmètre :** CDD.
- **Cas limites :** durée initiale ≤ 6 mois ou > 6 mois, terme imprécis, usage ou stipulation conventionnelle plus courte.
- **Contrôle :** clause prévue ; calcul selon durée initiale (ou durée minimale si terme imprécis), plafonds légaux et règle plus favorable vérifiée.
- **Statut :** À vérifier.

## SAL-CON-009 — Période initiale de rupture de l'apprentissage
- **Règle :** ne pas assimiler les 45 premiers jours de formation pratique en entreprise à une période d'essai CDI/CDD.
- **Source :** Code du travail L.6222-18, en vigueur depuis le 01/01/2019.
- **Périmètre :** apprentissage.
- **Cas limites :** jours non consécutifs, alternance CFA/entreprise, suspension, rupture après cette période.
- **Contrôle :** décompter les jours de formation pratique réellement effectués chez l'employeur, pas 45 jours calendaires.
- **Statut :** À vérifier.

## SAL-CON-010 — Dates et formalités du contrat d'apprentissage
- **Règle :** enregistrer début d'exécution, période de formation pratique chez l'employeur et période CFA.
- **Source :** Code du travail L.6222-12, en vigueur depuis le 01/01/2019, et dispositions réglementaires de dépôt.
- **Périmètre :** apprentissage.
- **Cas limites :** calendrier CFA différé, changement de CFA, suspension, rupture, dates incohérentes.
- **Contrôle :** contrôler les dates et conserver contrat, preuve de dépôt et justificatifs.
- **Statut :** À vérifier.

## SAL-CON-011 — Renouvellement du CDD
- **Règle :** suivre les renouvellements autorisés et leur formalisation avant le terme initial.
- **Sources :** Code du travail L.1243-13 et L.1243-13-1, en vigueur depuis le 24/09/2017.
- **Périmètre :** CDD renouvelables.
- **Cas limites :** accord de branche étendu, durée maximale, exception L.1242-3, clause initiale ou avenant.
- **Contrôle :** vérifier branche, motif et plafond ; formalisation avant terme ; aucun nombre maximal universel codé sans vérification.
- **Statut :** À vérifier.

## SAL-CON-012 — Poursuite après le terme du CDD
- **Règle :** détecter une poursuite de la relation après terme et déclencher une alerte.
- **Source :** Code du travail L.1243-11.
- **Périmètre :** CDD arrivant à échéance.
- **Cas limites :** poursuite effective, nouveau contrat, transformation en CDI, suspension ou erreur de date.
- **Contrôle :** alerte avant et au terme ; décision et justificatif obligatoires ; pas de clôture automatique du salarié.
- **Statut :** À vérifier.

## SAL-CON-013 — Modification du contrat et avenants
- **Règle :** distinguer mise à jour administrative et modification contractuelle ; conserver proposition, accord lorsqu'il est requis, document et date d'effet.
- **Sources :** Code du travail L.1222-1 ; L.1222-6 pour la procédure particulière de modification pour motif économique ; jurisprudence à examiner selon le cas.
- **Périmètre :** rémunération, durée, qualification, lieu et autres conditions contractuelles.
- **Cas limites :** changement des conditions de travail, refus, effet rétroactif, erreur de saisie.
- **Contrôle :** ne pas automatiser une règle générale de consentement sans qualification ; historiser valeurs et preuve.
- **Statut :** À vérifier.

## SAL-CON-014 — Historique complet
- **Règle C2ME :** conserver l'historique des fiches, contrats, avenants et événements, sans écrasement silencieux.
- **Nature :** exigence interne C2ME, pas une obligation légale unique de journaliser chaque champ sous cette forme.
- **Sources :** décision fonctionnelle C2ME ; RGPD, principes de limitation de conservation, exactitude et sécurité.
- **Date d'effet interne :** à fixer à la validation finale.
- **Périmètre :** données administratives et contractuelles.
- **Cas limites :** rectification, annulation, rétroactivité, demande d'effacement, restriction d'accès.
- **Contrôle :** auteur, horodatage, date d'effet, valeurs avant/après, motif, justificatif et statut ; conservation par catégorie.
- **Statut :** À valider.

## SAL-CON-015 — Fin du contrat
- **Règle :** enregistrer date, motif, mode de rupture, documents et événements de sortie sans confondre les régimes.
- **Sources :** dispositions du Code du travail propres au contrat et au mode de rupture (CDD, CDI, apprentissage).
- **Date d'effet :** à déterminer selon le mode de rupture et la version applicable.
- **Périmètre :** fin CDI, CDD ou apprentissage.
- **Cas limites :** terme, rupture anticipée CDD, démission, licenciement, rupture conventionnelle, rupture apprentissage, décès.
- **Contrôle :** motif/date obligatoires ; parcours spécifique ; justificatifs et formalités de sortie.
- **Statut :** À vérifier.

## Garde-fou transversal
Si convention, catégorie, motif, date d'effet ou justificatif manque, demander une vérification humaine et empêcher la validation concernée. Ne jamais substituer une valeur supposée.
