# BP-192 — Lot 2B — Instrumentation du RBAC

- **Ticket** : BP-192, Lot 2B.
- **Date** : 2026-11-08.
- **Branche** : `develop`.
- **HEAD** : `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`.
- **Périmètre** : `rbac.role_assigned`, `rbac.role_removed`, `rbac.permission_assigned`, `rbac.permission_revoked`.
- **Documents amont** : `bp-192-audit-business-mapping.md`, `bp-192-audit-architecture-decisions.md`, `bp-192-audit-user-lifecycle.md`, `bp-192-audit-instrumentation.md`.

Conventions : **Fait** = constaté dans le code ; **Déduction** = conséquence technique ; **Recommandation** = proposition non implémentée.

---

## 1. Objet et résultat

**Fait** : le dépôt contient deux points d'entrée RBAC réels :

1. l'acceptation d'une invitation administrateur, qui attribue le rôle `administrator` à un utilisateur ;
2. la synchronisation du référentiel RBAC, qui aligne les permissions de chaque rôle géré sur `RbacMatrix`.

Il n'existe **aucun** point d'entrée applicatif de retrait de rôle, de synchronisation des rôles d'un utilisateur, ni d'attribution ou de révocation manuelle de permission à un rôle.

**Résultat** :

| Événement | Producteur réel | Statut |
|---|---|---|
| `rbac.role_assigned` | `AcceptAdministratorInvitation::handle()` | **instrumenté**, testé |
| `rbac.role_removed` | aucun | **non instrumentable** — documenté |
| `rbac.permission_assigned` | `RbacSynchronizer::synchronize()` | **instrumenté**, testé |
| `rbac.permission_revoked` | `RbacSynchronizer::synchronize()` | **instrumenté**, testé |

**Décision appliquée** : aucun CRUD de rôles ou de permissions n'a été créé. Les créations de rôles et de permissions ne sont pas journalisées, conformément au brief. Aucun observateur global, aucun événement Spatie automatique, aucun appel direct à `activity()`.

---

## 2. État initial du dépôt

| Élément | Valeur |
|---|---|
| Branche | `develop` |
| HEAD | `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` |
| Modifications préexistantes | 56 entrées des lots 0 → 1E.3, intactes |
| Entrées antérieures supplémentaires | `AGENTS.md`, `bp-192-audit-business-mapping.md`, `bp-192-audit-architecture-decisions.md`, `bp-192-audit-user-lifecycle.md`, `AuditInvitationInstrumentationTest.php` (Lot 2A) |
| `permission.events_enabled` | `false` |
| `app/Filament`, `app/Livewire`, `app/Observers`, `app/Jobs` | absents |

---

## 3. Cartographie réelle des points d'entrée

| Point d'entrée | Fichier et méthode | Opération | Instrumentation actuelle | Événement attendu | Risque de doublon | Transaction |
|---|---|---|---|---|---|---|
| Acceptation d'invitation | `app/Actions/Administration/AcceptAdministratorInvitation.php::handle()` | `assignRole($administratorRole)` (ligne 82) | explicite, ligne 104 | `rbac.role_assigned` | nul : Spatie n'émet rien (`events_enabled = false`), aucun observateur | dans `DB::transaction` (ligne 35) |
| Synchronisation RBAC | `app/Support/Rbac/RbacSynchronizer.php::synchronize()` | `syncPermissions()` (ligne 51) | diff explicite, lignes 62 et 70 | `rbac.permission_assigned` / `rbac.permission_revoked` | nul : diff calculé avant écriture, Spatie n'émet rien | dans `DB::transaction` (ligne 33) |
| Synchronisation RBAC | `...RbacSynchronizer::ensurePermissions()` | `Permission::firstOrCreate()` | aucune | — | — | idem |
| Synchronisation RBAC | `...RbacSynchronizer::synchronize()` | `Role::firstOrCreate()` (ligne 37) | aucune | — | — | idem |
| Retrait de rôle | **aucun** | — | — | `rbac.role_removed` | — | — |
| Synchronisation des rôles d'un utilisateur | **aucun** | — | — | — | — | — |
| Attribution manuelle de permission | **aucun** | — | — | — | — | — |
| Révocation manuelle de permission | **aucun** | — | — | — | — | — |

**Fait** : recherche exhaustive dans `app/` des appels `assignRole`, `removeRole`, `syncRoles`, `givePermissionTo`, `revokePermissionTo`, `syncPermissions`, `attach`, `detach`, `sync`, `syncWithoutDetaching`, `DB::table`.

Résultat :

- `assignRole` : `AcceptAdministratorInvitation::handle()` uniquement ;
- `syncPermissions` : `RbacSynchronizer::synchronize()` uniquement ;
- les autres méthodes : **aucune occurrence** dans le code applicatif ;
- aucune écriture directe sur les tables pivots.

**Déduction** : les seules opérations RBAC à instrumenter sont celles listées. Toute autre instrumentation serait fictive.

---

## 4. Matrice des événements

| Point d'entrée | Opération | Événement | Condition de déclenchement |
|---|---|---|---|
| `AcceptAdministratorInvitation::handle()` | Attribution du rôle `administrator` | `rbac.role_assigned` | toujours, car l'invitation ne peut être consommée qu'une fois et l'acceptation refuse un administrateur déjà existant |
| `RbacSynchronizer::synchronize()` | Permission ajoutée à un rôle géré | `rbac.permission_assigned` | seulement si la permission est absente de l'état initial |
| `RbacSynchronizer::synchronize()` | Permission retirée d'un rôle géré | `rbac.permission_revoked` | seulement si la permission est présente dans l'état initial mais absente de la matrice |
| — | — | `rbac.role_removed` | **non applicable** : aucun point d'entrée |

### 4.1 Calcul du diff

**Fait** (`RbacSynchronizer::synchronize()`) :

```
$currentPermissionNames = $roleModel->permissions()->pluck('name')->all();
$addedPermissionNames   = array_diff($desiredPermissionNames, $currentPermissionNames);
$removedPermissionNames = array_diff($currentPermissionNames, $desiredPermissionNames);
```

Le diff est calculé **avant** `syncPermissions()`, puis chaque ajout et chaque retrait produit exactement un événement.

**Déduction** : une permission conservée ne produit aucun événement. Une synchronisation sans changement produit zéro ligne, ce qui est vérifié par test.

### 4.2 Aucun doublon

**Fait** : `config/permission.php` → `events_enabled = false`. `syncPermissions()` de Spatie se contente alors de détacher puis rattacher les pivots sans émettre d'événement. `assignRole()` n'émet `RoleAttachedEvent` que si `events_enabled` est vrai.

**Déduction** : avec la configuration actuelle, aucun doublon n'est possible. Activer `events_enabled` produirait, pour `syncPermissions`, une révocation de **toutes** les permissions courantes suivie d'une réattribution, donc des événements parasites même sans changement.

**Recommandation** : maintenir `events_enabled = false`. Le test existant le vérifie et échouerait si la valeur changeait.

---

## 5. Opérations non instrumentées et raisons

| Opération | Raison | Suite |
|---|---|---|
| Retrait de rôle | aucun point d'entrée applicatif | instrumenter `rbac.role_removed` au premier écran ou service de retrait |
| `syncRoles()` | aucune occurrence | idem, avec diff |
| Attribution/révocation manuelle de permission à un rôle | aucune occurrence | instrumenter avec diff |
| Création de rôle | décision de code (`RbacMatrix`, `RbacRole`), non retenue par le brief | traçabilité Git et revue de code |
| Création de permission | décision de code (`PermissionName`), non retenue | idem |
| Mise à jour ou suppression de rôle | aucune opération | aucune |
| Attribution d'un rôle déjà présent | impossible via le seul point d'entrée : l'acceptation refuse un administrateur existant et un jeton consommé | aucun événement attendu ; vérifié par rejeu |
| Retrait d'un rôle absent | impossible : aucun point d'entrée | — |

**Recommandation (non implémentée)** : si un besoin de preuve d'exécution de la synchronisation apparaît, journaliser un unique `system.rbac_synchronized` avec des compteurs, plutôt qu'un événement par rôle ou permission créés.

---

## 6. Champs conservés et confidentialité

### 6.1 Allowlist modifiée

**Fait** : `config/audit.php` autorisait `['name', 'email']` pour `rbac.role_assigned` et `rbac.role_removed`. Elle est désormais limitée à `['name']`.

**Justification** : le brief impose de ne pas enregistrer l'adresse e-mail de l'utilisateur dans le contexte RBAC ; le `subject_id` suffit à identifier l'utilisateur. `name` est conservé pour lisibilité de la trace.

**Déduction** : même si un appelant transmettait `email` demain, le sanitizer le retirerait avant persistance.

### 6.2 Contenu réel des événements

| Événement | `old_values` | `new_values` | Métadonnées |
|---|---|---|---|
| `rbac.role_assigned` | vide | vide | `role`, `guard`, `origin`, `source`, `channel`, `initiated_by`, `correlation_id` |
| `rbac.permission_assigned` | vide | vide | `role`, `permission`, `guard`, `channel`, `reason`, `correlation_id` |
| `rbac.permission_revoked` | vide | vide | idem |

**Fait** : les rôles et permissions sont identifiés par leur **nom technique** (`administrator`, `administration.panel.access`, `audit.view`) et par le `subject_id` du rôle pour les événements de permission.

### 6.3 Données exclues

**Fait** : aucun mot de passe, jeton, secret, cookie, session ou credential n'est transmis. L'allowlist RBAC ne contient aucun champ de ce type, et `AuditSanitizer` applique en plus sa blacklist et ses motifs.

**Fait** : les tests vérifient l'absence d'e-mail, de `password`, de `token_hash` et de secret dans les lignes produites.

---

## 7. Transactions et rollback

**Fait** : les deux producteurs écrivent dans la transaction qui porte l'opération métier.

- Acceptation d'invitation : `DB::transaction()` englobe la création utilisateur, l'attribution du rôle et les trois écritures d'audit.
- Synchronisation : `DB::transaction()` englobe les créations de permissions et de rôles, `syncPermissions()` et les audits de diff.

**Fait** : un test dédié enveloppe `synchronize()` dans une transaction externe, force une exception, et vérifie qu'aucun événement RBAC ne subsiste, et qu'aucun rôle ni permission n'a été créé.

**Déduction** : une opération annulée ne laisse aucune trace de réussite.

---

## 8. Tests

### 8.1 Fichier modifié

`tests/Feature/Audit/AuditRbacInstrumentationTest.php`

Tests ajoutés ou renforcés :

| # | Test | Vérifie |
|---|---|---|
| 1 | `records effective permission assignments during the first RBAC synchronization` (renforcé) | 6 attributions, canal `security`, `origin = system`, aucune paire rôle/permission dupliquée, corrélation unique, absence d'e-mail et de secret |
| 2 | `does not record anything when a new synchronization has no effect` (existant) | synchronisation idempotente sans événement |
| 3 | `records the removal of an extra permission from a managed role` (existant) | révocation effective |
| 4 | `records a role removal through the audit API` (existant) | contrat `rbac.role_removed`, **niveau API uniquement** |
| 5 | `preserves an automated context and its source during RBAC synchronization` (existant) | origine `automated`, `source = queue`, `job` |
| 6 | `does not rely on Spatie permission events and therefore cannot duplicate them` (existant) | `permission.events_enabled = false` |
| 7 | `records exactly one role assignment through the invitation acceptance entry point` (nouveau) | attribution réelle, `subject_type`/`subject_id`, `causer` nul, `origin = system`, `source = http`, `role`/`guard`, valeurs vides, absence d'e-mail et de mot de passe, rejeu sans doublon |
| 8 | `records a real permission assignment when a managed role regains it` (nouveau) | attribution effective après retrait, une seule nouvelle ligne |
| 9 | `rolls back RBAC audit events with the synchronization transaction` (nouveau) | rollback : aucune ligne, aucun rôle, aucune permission |

### 8.2 Couverture des cas demandés

| Cas demandé | Couvert | Remarque |
|---|---|---|
| 1. Attribution effective d'un rôle | oui | test 7 |
| 2. Attribution d'un rôle déjà présent | **non applicable** | aucun point d'entrée ne peut réattribuer un rôle déjà présent ; le rejeu est couvert par le test 7 |
| 3. Révocation effective d'un rôle | **non applicable** | aucun point d'entrée |
| 4. Révocation d'un rôle absent | **non applicable** | aucun point d'entrée |
| 5. Attribution effective d'une permission | oui | test 8 |
| 6. Révocation effective d'une permission | oui | test 3 |
| 7. Synchronisation sans changement | oui | test 2 |
| 8. Synchronisation avec ajout | oui | test 8 |
| 9. Synchronisation avec révocation | oui | test 3 |
| 10. Absence de doublons | oui | tests 1 et 7 |
| 11. Absence d'e-mail | oui | tests 1 et 7 |
| 12. Absence de secrets | oui | tests 1 et 7 |
| 13. Rollback | oui | test 9 |
| 14. Absence d'événements Spatie | oui | test 6 |

### 8.3 Résultats

| Commande | Résultat |
|---|---|
| `php artisan test tests/Feature/Audit/AuditRbacInstrumentationTest.php` | **9 tests, 9 réussis, 46 assertions** |
| `php artisan test tests/Unit/Audit/AuditSanitizerTest.php` | **33 tests, 33 réussis, 49 assertions** |
| `php artisan test tests/Feature/Audit/AuditLoggerTest.php` | **10 tests, 10 réussis, 49 assertions** |

Les deux derniers fichiers sont exécutés parce que la modification de `config/audit.php` touche directement le sanitizer et la configuration d'audit.

### 8.4 Tests non exécutés

- Suite globale et `composer test` : interdits par la règle permanente.
- `AuditInvitationInstrumentationTest`, `AuditTransactionTest` : non modifiés, non exécutés ; leur couverture reste valable (aucun code correspondant n'a changé).
- Tests RBAC d'autorisation (`tests/Feature/Rbac/*`) : non concernés par ce lot.

---

## 9. Limites connues

| # | Limite | Nature |
|---|---|---|
| 1 | `rbac.role_removed` sans producteur | Fait |
| 2 | Aucun écran de gestion des rôles ou permissions | Fait |
| 3 | Créations de rôles et permissions non journalisées | Décision validée |
| 4 | `rbac.role_assigned` limité au bootstrap administrateur | Fait |
| 5 | Pas de clé d'idempotence d'audit | Fait (socle) |
| 6 | Les événements RBAC ne portent pas de valeurs avant/après | Choix : les métadonnées `role`/`permission` suffisent |
| 7 | Le test de retrait de rôle reste au niveau API | Fait, signalé explicitement |

---

## 10. Références

- `app/Actions/Administration/AcceptAdministratorInvitation.php`
- `app/Support/Rbac/RbacSynchronizer.php`
- `app/Support/Rbac/RbacMatrix.php`
- `app/Enums/AuditEventType.php`
- `app/Enums/PermissionName.php`
- `app/Services/Audit/AuditManager.php`
- `app/Services/Audit/AuditSanitizer.php`
- `config/audit.php`
- `config/permission.php`
- `tests/Feature/Audit/AuditRbacInstrumentationTest.php`
- `docs/business-rules/bp-192-audit-architecture-decisions.md`
