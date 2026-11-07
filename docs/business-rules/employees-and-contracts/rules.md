# Règles métier — Salariés et contrats

**Version :** 0.2.0  
**Référence temporelle de la recherche juridique :** 7 novembre 2026  
**Statut global :** document de travail ; aucune règle ci-dessous n'est autorisée en production à ce stade.

> « Date d'effet vérifiée » désigne la date d'effet du texte telle qu'indiquée par la source officielle consultée. La date de situation d'un salarié devra être évaluée avec la version du texte applicable à cette date. La date de consultation ne remplace pas la date d'effet.

## SAL-CON-001 — Identification du salarié

- **Règle fonctionnelle :** créer une fiche individuelle avec un identifiant interne stable et uniquement les données nécessaires à la gestion de la relation de travail.
- **Données envisagées :** identité, date de naissance si nécessaire, coordonnées, informations administratives requises pour les formalités.
- **Nature :** obligations de formalités et protection des données ; la liste exhaustive des champs ne constitue pas une liste légale uniforme.
- **Sources :** Code du travail, L.1221-10 et suivants ; RGPD, articles 5(1)(c) et 6.
- **Date d'effet :** RGPD applicable depuis le 25/05/2018 ; L.1221-10 en vigueur depuis le 01/05/2008 (vérifier la version applicable à chaque situation).
- **Périmètre :** salariés de C2ME.
- **Exceptions/cas limites :** changement d'état civil, homonymie, salarié étranger, donnée manquante ou rectifiée, demande d'effacement.
- **Contrôle envisagé :** distinguer champs légalement requis, nécessaires à l'exécution du contrat et facultatifs ; documenter la finalité et les habilitations.
- **Statut :** À valider.

## SAL-CON-002 — Déclaration préalable à l'embauche (DPAE)

- **Règle fonctionnelle :** suivre la réalisation de la DPAE avant l'embauche effective ; la création d'une fiche salarié ne vaut pas déclaration.
- **Source :** Code du travail, L.1221-10 à L.1221-12-1.
- **Date d'effet de L.1221-10 :** 01/05/2008 ; version consultée le 07/11/2026.
- **Périmètre :** embauche d'un salarié.
- **Exceptions/cas limites :** déclaration corrigée, annulée ou rejetée ; preuve non encore archivée ; formalités spécifiques de certains régimes.
- **Contrôle envisagé :** enregistrer date/heure d'envoi, statut, organisme, référence et justificatif ; signaler une DPAE absente ou non confirmée.
- **Limite :** BatiPaie ne réalise pas automatiquement la déclaration dans ce périmètre documentaire.
- **Statut :** À valider.

## SAL-CON-003 — Registre unique du personnel

- **Règle fonctionnelle :** tenir le registre unique du personnel pour l'établissement C2ME et inscrire les personnes dans les conditions légales.
- **Source :** Code du travail, L.1221-13 à L.1221-15-1 et dispositions réglementaires associées.
- **Date d'effet de L.1221-13 :** 29/01/2017 ; version consultée le 07/11/2026.
- **Périmètre :** établissement employant des salariés.
- **Exceptions/cas limites :** stagiaires et volontaires en service civique inscrits dans une partie spécifique ; rectification ; salariés détachés accueillis.
- **Contrôle envisagé :** conserver l'ordre des embauches, les mentions réglementaires et un historique non destructif. Vérifier les modalités légales de tenue informatique.
- **Statut :** À valider.

## SAL-CON-004 — Nature du contrat et motif du CDD

- **Règle fonctionnelle :** distinguer CDI, CDD et contrat d'apprentissage. Le temps partiel est une modalité attachée au contrat.
- **Sources :** Code du travail, L.1221-2 (CDI forme normale et générale), L.1242-1 et L.1242-2 (recours au CDD), dispositions du livre II de la sixième partie pour l'apprentissage.
- **Date d'effet :** textes consultés le 07/11/2026 ; date d'effet propre à chaque disposition à contrôler sur Légifrance.
- **Périmètre :** nouveaux contrats et contrats successifs.
- **Exceptions/cas limites :** motif légal de recours au CDD, contrats particuliers, apprentissage, contrat à temps partiel.
- **Contrôle envisagé :** motif de recours obligatoire pour un CDD ; empêcher une validation sans motif et fondement documentés.
- **Statut :** À valider.

## SAL-CON-005 — Écrit, mentions et transmission du CDD

- **Règle fonctionnelle :** un CDD doit être établi par écrit, comporter un motif précis et les mentions légales adaptées à son cas ; suivre sa transmission au salarié.
- **Sources :** Code du travail, L.1242-12 et L.1242-13.
- **Date d'effet :** dispositions en vigueur depuis le 01/05/2008 ; version consultée le 07/11/2026.
- **Périmètre :** CDD relevant de ces dispositions.
- **Cas limites :** terme précis ou imprécis, remplacement, durée minimale, poste, convention collective, rémunération, période d'essai éventuelle.
- **Contrôle envisagé :** formulaire conditionnel selon motif et nature du terme ; preuve de transmission au plus tard dans les deux jours ouvrables suivant l'embauche.
- **Statut :** À valider.

## SAL-CON-006 — Contrat à temps partiel

- **Règle fonctionnelle :** tout contrat à temps partiel doit être écrit et contenir les mentions prévues par la loi.
- **Source :** Code du travail, L.3123-6.
- **Date d'effet :** 10/08/2016 ; version consultée le 07/11/2026.
- **Périmètre :** CDI ou CDD à temps partiel.
- **Cas limites :** durée hebdomadaire ou mensuelle, répartition, modalités de communication des horaires, modification de répartition, heures complémentaires et dérogations conventionnelles.
- **Contrôle envisagé :** vérifier les mentions requises et les règles dérogatoires applicables avant validation.
- **Statut :** À valider.

## SAL-CON-007 — Période d'essai du CDI

- **Règle fonctionnelle :** la période d'essai et la possibilité de son renouvellement ne se présument pas ; elles doivent être expressément stipulées. Contrôler les plafonds et conditions applicables.
- **Sources :** Code du travail, L.1221-19 à L.1221-26.
- **Dates d'effet :** L.1221-19 à L.1221-23 en vigueur depuis le 27/06/2008 ; versions consultées le 07/11/2026.
- **Périmètre :** CDI.
- **Cas limites :** catégorie professionnelle, accord de branche étendu autorisant le renouvellement, durée plus favorable, suspension, délai de prévenance, déduction d'un stage dans les conditions de L.1221-24.
- **Contrôle envisagé :** ne calculer qu'après identification de la catégorie et de la convention/accord applicable ; bloquer si les données ou sources manquent ; aucun renouvellement automatique.
- **Statut :** À vérifier (applicabilité conventionnelle indispensable).

## SAL-CON-008 — Période d'essai du CDD

- **Règle fonctionnelle :** appliquer le régime spécifique du CDD, distinct de celui du CDI.
- **Source :** Code du travail, L.1242-10 et L.1242-11.
- **Date d'effet de L.1242-10 :** 01/05/2008 ; version consultée le 07/11/2026.
- **Périmètre :** CDD.
- **Cas limites :** durée initiale inférieure ou égale à six mois, durée supérieure à six mois, contrat sans terme précis, usage ou stipulation conventionnelle plus courte.
- **Contrôle envisagé :** vérifier la clause et calculer selon la durée initialement prévue ou la durée minimale si le terme est imprécis ; appliquer les limites légales et toute règle plus favorable vérifiée.
- **Statut :** À vérifier.

## SAL-CON-009 — Période de rupture initiale de l'apprentissage

- **Règle fonctionnelle :** ne pas traiter les 45 premiers jours de formation pratique en entreprise comme une période d'essai CDI ou CDD.
- **Source :** Code du travail, L.6222-18.
- **Date d'effet :** 01/01/2019 ; version consultée le 07/11/2026.
- **Périmètre :** contrat d'apprentissage.
- **Cas limites :** jours de formation pratique non consécutifs, alternance CFA/entreprise, suspension, rupture après cette période.
- **Contrôle envisagé :** suivre les jours de formation pratique réellement effectués chez l'employeur ; ne pas calculer 45 jours calendaires à partir de la seule date de début.
- **Statut :** À vérifier.

## SAL-CON-010 — Dates et formalités du contrat d'apprentissage

- **Règle fonctionnelle :** enregistrer la date de début d'exécution, la période de formation pratique chez l'employeur et la période de formation en CFA.
- **Source :** Code du travail, L.6222-12 et dispositions réglementaires relatives au contrat et à son dépôt.
- **Date d'effet de L.6222-12 :** 01/01/2019 ; version consultée le 07/11/2026.
- **Périmètre :** contrats d'apprentissage.
- **Cas limites :** calendrier CFA différé, changement de CFA, suspension, rupture, dates incohérentes.
- **Contrôle envisagé :** contrôler les dates au regard des limites prévues par le texte et conserver contrat, preuve de dépôt et justificatifs.
- **Statut :** À vérifier.

## SAL-CON-011 — Renouvellement du CDD

- **Règle fonctionnelle :** suivre les renouvellements autorisés et leur formalisation avant le terme initial.
- **Sources :** Code du travail, L.1243-13 et L.1243-13-1, sous réserve des exceptions prévues par ces textes.
- **Date d'effet :** 24/09/2017 ; versions consultées le 07/11/2026.
- **Périmètre :** CDD pouvant être renouvelés.
- **Cas limites :** accord de branche étendu fixant un nombre maximal différent, durée maximale, CDD relevant de L.1242-3, renouvellement prévu au contrat ou par avenant.
- **Contrôle envisagé :** vérifier le texte de branche et le motif ; exiger une formalisation avant le terme initial ; ne pas coder un nombre maximal universel sans vérification.
- **Statut :** À vérifier.

## SAL-CON-012 — Poursuite de la relation après le terme du CDD

- **Règle fonctionnelle :** détecter une poursuite de la relation de travail après le terme et déclencher une alerte de régularisation.
- **Source :** Code du travail, L.1243-11.
- **Date d'effet :** texte en vigueur depuis le 01/05/2008 ; version applicable à la situation à vérifier.
- **Périmètre :** CDD arrivant à échéance.
- **Cas limites :** poursuite effective du travail, nouveau contrat, transformation en CDI, suspension ou erreur de date.
- **Contrôle envisagé :** alerte avant et au terme ; aucune clôture automatique du dossier salarié ; décision et justificatif obligatoires.
- **Statut :** À vérifier.

## SAL-CON-013 — Modification du contrat et avenants

- **Règle fonctionnelle :** distinguer la mise à jour administrative d'une modification du contrat ; conserver la proposition, l'accord du salarié lorsqu'il est requis, le document et la date d'effet.
- **Sources :** Code du travail, L.1222-1 ; L.1222-6 pour la procédure particulière de modification pour motif économique. La qualification d'une modification du contrat dépend aussi de la jurisprudence et de la situation.
- **Date d'effet :** textes consultés le 07/11/2026 ; qualification juridique à vérifier au cas par cas.
- **Périmètre :** changement de rémunération, durée du travail, qualification, lieu ou autres conditions contractuelles.
- **Cas limites :** changement des conditions de travail, modification essentielle, refus du salarié, effet rétroactif demandé, erreur de saisie.
- **Contrôle envisagé :** aucune règle générale automatisée de consentement sans qualification préalable ; historiser les valeurs avant/après et la preuve.
- **Statut :** À vérifier.

## SAL-CON-014 — Historique complet

- **Règle fonctionnelle C2ME :** conserver l'historique des fiches, contrats, avenants et événements, sans écrasement silencieux des versions antérieures.
- **Nature :** exigence interne C2ME ; ce n'est pas une affirmation d'une obligation légale unique de journaliser chaque champ de cette manière.
- **Sources :** décision fonctionnelle C2ME ; RGPD, notamment principes de limitation de conservation, exactitude et sécurité.
- **Date d'effet interne :** à fixer lors de la validation finale du référentiel.
- **Périmètre :** données administratives et contractuelles.
- **Cas limites :** rectification, annulation, changement rétroactif, demande d'effacement, restriction d'accès.
- **Contrôle envisagé :** auteur, horodatage, date d'effet, ancienne/nouvelle valeur, motif, justificatif et statut ; accès limité par habilitation ; conservation et effacement définis par catégorie.
- **Statut :** À valider par C2ME.

## SAL-CON-015 — Fin du contrat

- **Règle fonctionnelle :** enregistrer date, motif, mode de rupture, documents et événements de sortie sans assimiler les régimes juridiques.
- **Sources :** dispositions du Code du travail propres au contrat et au mode de rupture (notamment titres relatifs au CDD, à la rupture du CDI et au contrat d'apprentissage).
- **Date d'effet :** à déterminer selon le mode de rupture et la version du texte applicable.
- **Périmètre :** fin de CDI, CDD ou apprentissage.
- **Cas limites :** arrivée du terme, rupture anticipée du CDD, démission, licenciement, rupture conventionnelle, rupture d'apprentissage, décès.
- **Contrôle envisagé :** motif et date obligatoires ; parcours de sortie spécifique ; conservation des justificatifs et déclenchement des formalités de fin de contrat.
- **Statut :** À vérifier.

## Règle transversale — Pas de valeur juridique par défaut

Lorsqu'une convention collective, une catégorie professionnelle, un motif de CDD, une date d'effet ou une pièce justificative manque, BatiPaie doit signaler l'information manquante et empêcher la validation concernée. Il ne doit pas substituer une valeur supposée.
