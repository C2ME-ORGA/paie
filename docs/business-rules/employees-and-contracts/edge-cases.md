# Cas limites et décisions ouvertes — Salariés et contrats

Ce document recense les situations qui doivent être traitées explicitement. Une situation non couverte ne doit pas déclencher une règle automatique supposée.

## Identification et formalités

| Cas | Comportement attendu | Décision / source à vérifier |
|---|---|---|
| Homonymie | Ne pas fusionner deux salariés sur le seul nom/prénom | Définir les contrôles d'identité et l'identifiant interne |
| Changement d'état civil/adresse | Mettre à jour la donnée courante et conserver l'événement si nécessaire | Définir les champs historisés et la base légale de traitement |
| Salarié étranger | Suivre les justificatifs et autorisations requis selon la situation | Vérifier le régime applicable et les échéances |
| DPAE rejetée ou corrigée | Conserver les tentatives et le statut final | Définir le processus de correction et le justificatif attendu |
| Registre unique du personnel | Inscription non destructive et chronologique | Vérifier les mentions réglementaires et modalités informatiques |

## Contrats

| Cas | Comportement attendu | Décision / source à vérifier |
|---|---|---|
| Plusieurs contrats successifs | Conserver chaque contrat et ses propres dates | Définir les règles de continuité et ancienneté par cas |
| CDD sans motif ou motif incohérent | Empêcher la validation | Vérifier la liste légale des motifs et les justificatifs requis |
| CDD à terme imprécis | Exiger durée minimale et événement de fin selon le régime | Vérifier les mentions spécifiques |
| Renouvellement CDD tardif | Alerter et bloquer la validation rétroactive automatique | Vérifier la date de signature/transmission et les conséquences juridiques |
| CDD poursuivi après son terme | Alerte prioritaire et examen humain | Code du travail, L.1243-11 |
| CDI à temps partiel | Exiger l'écrit et les mentions applicables | L.3123-6 et accords/dérogations applicables |
| Temps partiel avec horaire variable | Conserver la répartition, les modalités de communication et leurs modifications | Vérifier les accords et délais de prévenance applicables |
| Contrat d'apprentissage | Stocker les périodes employeur/CFA séparément | L.6222-12 et règles de dépôt |
| Rupture apprentissage durant les 45 jours | Décompter les jours de formation pratique en entreprise, non les jours calendaires | L.6222-18 et preuve du calendrier réellement exécuté |

## Périodes d'essai

| Cas | Comportement attendu | Décision / source à vérifier |
|---|---|---|
| Clause absente | Ne pas créer de période d'essai | L.1221-23 pour le CDI ; régime CDD spécifique |
| Catégorie professionnelle inconnue | Ne pas calculer automatiquement | Obtenir catégorie et convention applicable |
| Renouvellement CDI | Vérifier accord de branche étendu, clause initiale et accord du salarié requis | L.1221-21 à L.1221-23 et convention applicable |
| Suspension pendant l'essai | Recalcul uniquement après qualification de l'événement | Vérifier la nature de la suspension et son effet sur le terme |
| CDD | Appliquer le calcul propre à L.1242-10 | Vérifier durée initiale, terme et dispositions plus favorables |
| Apprentissage | Ne pas utiliser le moteur d'essai CDI/CDD | L.6222-18 |

## Modifications et historique

| Cas | Comportement attendu | Décision / source à vérifier |
|---|---|---|
| Correction d'une erreur de saisie | Conserver la trace de correction et la valeur antérieure | Définir les rôles autorisés |
| Modification avec effet rétroactif | Alerte et justification obligatoires | Vérification juridique et paie préalable |
| Refus d'un avenant | Conserver proposition, réponse et date | Qualifier si l'accord est juridiquement nécessaire |
| Annulation d'un avenant signé | Ne pas supprimer le document ; créer un événement d'annulation | Validation humaine obligatoire |
| Demande d'effacement RGPD | Appliquer la matrice de conservation et les obligations légales | Arbitrage DPO/conseil ; ne pas supprimer l'audit obligatoire par défaut |
| Fin de contrat | Distinguer motif, procédure et documents selon le type de rupture | Documenter chaque mode de rupture dans un périmètre ultérieur |

## Points bloquants avant statut « Validée »

- Convention collective et accords applicables aux catégories de salariés C2ME.
- Catégories professionnelles utilisées pour calculer les périodes d'essai CDI.
- Paramètres conventionnels de renouvellement des périodes d'essai.
- Règles conventionnelles applicables aux CDD et à leur renouvellement.
- Formalités et contrôles du contrat d'apprentissage.
- Matrice de conservation des documents et données RH.
- Liste des motifs de sortie et workflows associés.
