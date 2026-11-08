# BP-192 — Lot 1D : interface Filament sécurisée du journal d’audit

**Ticket Jira :** BP-192 — Implémenter le journal d’audit métier complet  
**Lot :** 1D — consultation Filament en lecture seule  
**Date :** 8 novembre 2026  
**Branche :** `develop`  
**Commit de départ :** `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`

---

## 1. Décision d’architecture

L’interface retenue est la ressource Filament fournie par
`alizharb/filament-activity-log` 2.0.0, enregistrée **une seule fois** dans le panel
`admin`.

Elle n’est pas remplacée par une page maison, car les protections suivantes ont été
vérifiées et limitent le plugin à un usage strictement en lecture seule :

- autorisation `viewAny` / `view` déléguée à la policy `Activity` du plugin ;
- mapping vers la permission BatiPaie `audit.view` ;
- widgets, dashboard, page « activités utilisateur », retention hold et mutations désactivés ;
- export, purge, suppression, restauration et revert explicitement désactivés ;
- aucune relation manager enregistrée ;
- aucune page de création, modification ou suppression.

La ressource reste protégée par `strictAuthorization()` du panel et par la policy de
ressource Filament aux niveaux HTTP et Livewire.

---

## 2. Corrections apportées aux événements du Lot 1C

### 2.1 Acceptation d’une invitation

L’acceptation est déclenchée par le destinataire avant toute authentification. L’origine
reste donc `system` : aucun utilisateur authentifié ne doit être inventé.

Une métadonnée explicite a été ajoutée aux événements `user.created`,
`rbac.role_assigned` et `administrator_invitation.consumed` :

```text
initiated_by = invitation_recipient
source       = http
channel      = administrator_invitation
```

Les tests vérifient cette métadonnée sur les trois événements.

### 2.2 Échec d’envoi d’une invitation

Le comportement réel reste une révocation : le code positionne `revoked_at` pour permettre
un retry propre. L’événement `administrator_invitation.revoked` reste donc justifié.

Un événement distinct a été ajouté :

```text
administrator_invitation.delivery_failed
```

Il est écrit avant la révocation, avec la même corrélation. La séquence est désormais :

1. `administrator_invitation.created` ;
2. `administrator_invitation.delivery_failed` ;
3. `administrator_invitation.revoked`.

Aucun token, hash de token ou contenu d’email n’est journalisé.

---

## 3. Panel et enregistrement

- Panel : `admin`.
- Guard : `web`.
- Provider : `app/Providers/Filament/AdminPanelProvider.php`.
- Plugin : `ActivityLogPlugin::make()`, enregistré une seule fois.
- Ressource : `ActivityLogResource`.
- Pages exposées : liste et détail uniquement.
- Routes : `GET /admin/activity-logs` et `GET /admin/activity-logs/{record}`.
- Aucune route de création, modification, suppression, export ou purge.

---

## 4. Permissions

### 4.1 Permission active

Seule la permission suivante est utilisée pour la consultation :

```text
audit.view
```

Elle est créée dans `PermissionName` et accordée **uniquement au rôle Administrateur**
dans `RbacMatrix`. Les autres rôles internes conservent uniquement
`administration.panel.access`.

Cette restriction est volontairement minimale. L’ouverture à Direction, RH ou Gestionnaire
de paie est une décision métier qui devra être explicitement arbitrée.

### 4.2 Export

`audit.export` reste défini dans la configuration du plugin mais **n’est pas créé** dans le
référentiel RBAC. L’action d’export est également désactivée dans la configuration.
Aucun export n’est disponible.

---

## 5. Protection des accès

| Niveau | Protection |
|---|---|
| Panel | Session `web`, `strictAuthorization()`, permission `administration.panel.access` |
| Navigation | `ActivityLogResource::canViewAny()` → `audit.view` |
| Liste HTTP | `GET /admin/activity-logs` → policy `viewAny` |
| Détail HTTP | `GET /admin/activity-logs/{record}` → policy `view` |
| Livewire | `mountCanAuthorizeResourceAccess` / `hydrateCanAuthorizeResourceAccess` → 403 |
| Requêtes de table | Policy de ressource avant chargement |
| Actions de ligne | Aucune action destructive, export ou purge visible |
| Actions groupées | Aucune action destructive ou retention |
| Widgets | Désactivés |
| Dashboard | Désactivé |
| Page activités utilisateur | Désactivée |
| Relation manager | Non enregistré |

Un utilisateur sans `audit.view` reçoit un refus, même par URL directe ou par test Livewire.
Un simple masquage de navigation n’est pas utilisé comme protection.

---

## 6. Interface de consultation

### 6.1 Liste

Colonnes disponibles :

- date et heure ;
- événement ;
- origine (`user`, `system`, `automated`) ;
- auteur ou « Système » / « Auteur supprimé » ;
- type de ressource ;
- identifiant de ressource ;
- description ;
- log name, risque, request ID selon configuration.

La colonne `causer.name` du plugin est masquée afin de ne pas exposer l’email du causer.
Une colonne `actor_display` affiche le nom ou l’indication système.

Filtres disponibles :

- type d’événement ;
- origine ;
- période (`created_from` / `created_until`) ;
- auteur (`causer_id`) ;
- type de ressource ;
- identifiant de ressource ;
- log name ;
- risque ;
- request ID.

La recherche globale est activée. La pagination est serveur, avec 25 lignes par défaut.

### 6.2 Détail

Le détail présente :

- les informations générales ;
- l’auteur ou l’indication système ;
- la date et l’heure ;
- la ressource ;
- les anciennes et nouvelles valeurs autorisées ;
- les métadonnées non sensibles via l’onglet de données filtrées ;
- la corrélation et l’origine dans les propriétés assainies.

Les onglets de modifications et de données brutes n’affichent que ce qui a été écrit par
`AuditLogger` après passage dans `AuditSanitizer`.

### 6.3 Lecture seule

Aucune action ne permet de créer, modifier, supprimer, restaurer, purger ou exporter une
trace depuis l’interface.

Les tests vérifient l’absence des actions de ligne et de masse ainsi que l’absence de
verbe HTTP de mutation.

---

## 7. Confidentialité

Le filtrage reste garanti **en amont de l’affichage** par la couche métier :

- `AuditSanitizer` filtre les attributs avant/après ;
- les métadonnées sont filtrées par liste blanche ;
- les secrets, tokens, mots de passe, cookies, IBAN, NIR et données médicales sont exclus ;
- `activity_log` ne contient que les valeurs autorisées.

L’interface applique en plus :

- la rédaction d’affichage du plugin ;
- le masquage de l’IP et du user-agent ;
- le masquage de l’email du causer dans la liste ;
- l’absence d’export.

Les tests d’interface vérifient qu’un mot de passe ou un header `Authorization` transmis
volontairement à `AuditLogger` n’apparaît ni en liste ni en détail.

---

## 8. Fonctionnalités volontairement désactivées

| Fonctionnalité | État |
|---|---|
| Création manuelle d’une trace | Désactivée |
| Modification d’une trace | Désactivée |
| Suppression | Désactivée |
| Restauration | Désactivée |
| Revert | Désactivé |
| Purge | Désactivée |
| Export | Désactivé |
| Retention hold | Désactivé |
| Widgets | Désactivés |
| Dashboard | Désactivé |
| Page activités utilisateur | Désactivée |
| Relation manager | Non enregistré |
| Filtre `company_id` | Non ajouté : aucun modèle multi-entreprise n’existe |

---

## 9. Tests

### 9.1 Tests ciblés Audit

```bash
php artisan test --filter=Audit
```

Résultat : **113 tests, 113 réussis, 387 assertions**.

Ils couvrent notamment :

- accès autorisé avec `audit.view` ;
- refus sans `audit.view` sur la liste et le détail ;
- redirection des invités ;
- navigation masquée ;
- refus Livewire ;
- pagination serveur ;
- recherche ;
- filtres événement, origine, auteur et période ;
- détail avec anciennes/nouvelles valeurs et métadonnées ;
- absence de mot de passe et de secret dans l’interface ;
- absence d’actions de mutation, d’export et de purge ;
- absence de verbe HTTP de mutation ;
- création de `audit.view` sans `audit.export` ;
- corrections `initiated_by` et `delivery_failed`.

### 9.2 Suite complète

```bash
php artisan test
```

Résultat : **194 tests, 194 réussis, 720 assertions**.

### 9.3 Qualité

| Commande | Résultat |
|---|---|
| `vendor/bin/pint --test` | Réussi |
| `vendor/bin/phpstan analyse --no-progress` | Réussi, 0 erreur |
| `composer test` | Réussi |
| `composer validate --no-check-publish --strict` | Réussi |
| `composer audit` | Aucun avis de sécurité ; 2 packages abandonnés préexistants |

---

## 10. Limites et risques

- Le plugin est un composant tiers récent et mono-mainteneur. La version 2.0.0 reste
  épinglée ; toute mise à jour doit être revue, notamment les policies, actions et widgets.
- La policy `Activity` du plugin remplace toute policy applicative sur ce modèle. Il ne faut
  pas créer de policy parallèle sans réévaluer ce point.
- Le HMAC du Lot 1A ne détecte pas la suppression d’une ligne.
- Le mode immutable et la policy ne rendent pas la base append-only ; les droits SQL et les
  sauvegardes restent nécessaires.
- Aucun cloisonnement multi-entreprise ou établissement n’existe dans le schéma actuel.
- Les consultations du journal ne sont pas elles-mêmes auditées dans ce lot.
- Les exports et la politique de conservation restent hors périmètre.
- L’origine `system` d’une acceptation d’invitation est volontaire ; la métadonnée
  `initiated_by=invitation_recipient` documente l’initiative humaine sans inventer un acteur
  authentifié.
- L’accès est limité au rôle Administrateur. Une ouverture à d’autres rôles nécessite un
  arbitrage métier et une mise à jour de `RbacMatrix`.

---

## 11. Écarts par rapport à BP-192

Aucun écart fonctionnel sur la consultation en lecture seule. Les éléments suivants restent
hors du périmètre initial et ne sont pas implémentés :

- exports ;
- journalisation des consultations ;
- conservation, purge et legal hold ;
- ancrage externe ;
- reporting avancé ;
- isolation multi-entreprise.

---

## 12. Verdict

**LOT 1D VALIDÉ.**

L’interface est strictement en lecture seule, protégée par `audit.view` au niveau navigation,
HTTP et Livewire, sans action de mutation, export ou purge. Les données affichées sont celles
filtrées par la couche métier. Les tests ciblés et la suite complète passent, ainsi que Pint
et PHPStan.
