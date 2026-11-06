---
name: livewire-filament-ui
description: Développer les interfaces batiPaie avec Livewire et Filament en respectant les conventions UI, l'accessibilité, la validation et les permissions.
compatibility: opencode
metadata:
  project: batipaie
  frontend: livewire-filament
---

# Interfaces Livewire et Filament

## Quand utiliser cette skill
Pour les écrans RH, formulaires, tables, actions, widgets, espaces salariés, ressources Filament et composants Livewire.

## Règles
- Inspecter les versions installées et les APIs réellement disponibles avant d'utiliser une classe ou une méthode. Ne pas transposer des exemples Filament 3/4 dans Filament 5 sans vérification.
- Réutiliser les composants, layouts, styles et patterns déjà présents dans le dépôt.
- Garder l'interface cohérente : libellés français, formats français pour dates et montants, états explicites, messages de validation compréhensibles.
- Toute action sensible doit être protégée côté serveur par policy/permission. Masquer un bouton ne constitue jamais une autorisation.
- Valider les données côté serveur, même si une validation Livewire existe côté client.
- Prévoir états loading, empty, error, success et confirmation pour les actions destructrices.
- Utiliser pagination, recherche et filtres serveur pour les listes volumineuses.
- Ne pas exposer de données personnelles ou bancaires dans les notifications, URLs, logs ou messages d'erreur.
- Respecter clavier, focus, labels, contrastes et erreurs accessibles.
- Ne pas introduire de JavaScript personnalisé si Livewire/Filament couvre correctement le besoin ; si nécessaire, suivre le mécanisme JS existant.

## Workflow
1. Identifier les composants comparables déjà présents.
2. Confirmer les APIs des versions installées via code, vendor ou documentation officielle.
3. Implémenter UI et autorisations ensemble.
4. Tester les cas nominal, validation, permission refusée et données vides.
5. Vérifier le rendu responsive et les interactions.
