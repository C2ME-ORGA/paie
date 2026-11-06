---
name: security-rgpd-payroll
description: Sécuriser les données RH et de paie de batiPaie, contrôler les accès, les secrets, les journaux, les exports et la conformité RGPD.
compatibility: opencode
metadata:
  project: batipaie
  domain: security
---

# Sécurité, confidentialité et RGPD

## Quand utiliser cette skill
Pour l'authentification, autorisations, données personnelles, bancaires, médicales, pièces jointes, exports, logs, sauvegardes et espace salarié.

## Principes
- Appliquer le moindre privilège et vérifier l'autorisation côté serveur à chaque lecture ou mutation sensible.
- Séparer les accès administrateur, RH, gestionnaire paie, manager, salarié et prestataire selon des permissions explicites.
- Vérifier systématiquement l'isolation par entreprise/établissement lorsque le modèle le requiert.
- Ne jamais journaliser mots de passe, tokens, IBAN complets, données médicales, montants individuels sensibles ou contenu de bulletins.
- Chiffrer les secrets et données hautement sensibles selon une stratégie documentée ; gérer rotation et sauvegarde des clés.
- Protéger les uploads : type réel, taille, nommage, stockage privé, antivirus si disponible, téléchargement autorisé et liens temporaires.
- Protéger contre IDOR, mass assignment, CSRF, XSS, injection SQL, SSRF, élévation de privilèges et fuite inter-tenant.
- Les exports et téléchargements doivent être autorisés et audités.
- Prévoir conservation, archivage, rectification et suppression/anonymisation selon les obligations applicables ; ne pas inventer les durées légales.
- Les données médicales et justificatifs d'absence nécessitent une protection renforcée et un accès strictement limité.

## Vérifications
- Tests d'accès autorisé/refusé pour chaque rôle.
- Tests d'isolation entre entreprises.
- Tests de téléchargement de documents privés.
- Vérification des logs, erreurs, exports et notifications.
- Audit des dépendances et configuration de production.
