# BP-192 — Lot 2 — Cartographie du périmètre métier et préparation de l'instrumentation d'audit

- **Ticket** : BP-192 (journal d'audit métier), phase « Lot 2 — cartographie ».
- **Nature** : analyse en lecture seule. Aucun code applicatif, migration, configuration, permission ou interface n'est modifié par ce lot.
- **Date d'analyse** : 2026-11-08.
- **Branche** : `develop`.
- **HEAD** : `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` — `[BP-191] Sécuriser le RBAC et l'initialisation administrateur (#9)`.
- **Périmètre documentaire consulté** : Lots 0 à 1E.3 (`docs/business-rules/bp-192-audit-*.md`, `docs/security/RBAC.md`, `docs/security/ADMIN-BOOTSTRAP.md`, `docs/development/*`).

Convention de lecture utilisée dans tout le rapport :

- **Fait** : constaté directement dans un fichier, une migration ou une route, avec référence.
- **Déduction** : conséquence logique d'un ou plusieurs faits, sans preuve d'exécution.
- **Recommandation** : proposition, non implémentée dans ce lot.

---

## 1. État initial du dépôt

### 1.1 Références Git

| Élément | Valeur |
|---|---|
| Branche active | `develop` |
| HEAD | `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` |
| Message | `[BP-191] Sécuriser le RBAC et l'initialisation administrateur (#9)` |
| Auteur / date | Vortech Studio — 2026-11-08 15:39:49 +0100 |

### 1.2 Modifications préexistantes

**Fait** : `git status --porcelain` retourne 56 entrées, toutes liées aux lots 0 → 1E.3 et déjà présentes avant cette analyse. Elles se répartissent en :

- ajouts indexés (`A`) : contrats et enums d'audit, services `App\Services\Audit\*`, listener d'authentification, configurations `activitylog` / `filament-activity-log`, migrations `activity_log`, tests d'audit ;
- modifications indexées puis retravaillées (`AM`) : `app/Enums/AuditEventType.php`, `app/Services/Audit/AuditSanitizer.php`, `app/Support/Audit/AuditIntegrityConfiguration.php`, `config/audit.php`, migrations d'audit, documentation d'audit, plusieurs tests ;
- modifications non indexées (` M`) : `.env.example`, `.env.production.example`, `.gitignore`, actions d'administration, `app/Providers/AppServiceProvider.php`, `app/Providers/Filament/AdminPanelProvider.php`, `composer.json`, `composer.lock`, `app/Support/Rbac/*`, `docs/security/RBAC.md`, tests RBAC.

**Fait** : aucune entrée non suivie (`git ls-files --others --exclude-standard` vide) au moment de l'analyse. L'artefact `lot-1-complete.diff` signalé au Lot 1E.3 n'est plus présent dans le worktree (`Test-Path` = `False`).

**Fait** : ce lot n'a modifié, indexé ou supprimé aucun de ces éléments. Le seul fichier produit est le présent rapport.

### 1.3 Fiabilité de l'affichage console

**Fait** : la console PowerShell de l'environnement restitue certains caractères UTF-8 sous forme de caractères de remplacement, rendant illisible à l'écran un libellé correctement enregistré. Une vérification octet par octet des sources donne :

| Chaîne | Octets constatés | Verdict |
|---|---|---|
| `Système` | `53797374 c3a8 6d65` | correct |
| `Automatisé` | `4175746f6d61746973 c3a9` | correct |
| `Auteur supprimé` | `417574657572207375707072696d c3a9` | correct |
| `Création d’un utilisateur` | `4372 c3a9 6174696f6e2064 e28099 756e…` | correct |

**Déduction** : les séquences visibles dans la console sont un artefact d'affichage, pas une corruption des fichiers. `app/Providers/Filament/AdminPanelProvider.php` est correct sur disque.

---

## 2. Synthèse de l'architecture métier

### 2.1 Constat central

**Fait** : le dépôt ne contient **aucun domaine métier RH/paie implémenté**. Les seuls modèles applicatifs sont `App\Models\User` et `App\Models\AdministratorInvitation`. Il n'existe ni salarié, ni contrat, ni établissement, ni chantier, ni temps de travail, ni congé, ni élément variable, ni bulletin, ni déclaration, ni document.

**Fait** : les seuls domaines réellement opérationnels sont transverses et techniques :

1. identité et comptes utilisateurs (création uniquement) ;
2. amorçage de l'administrateur par invitation ;
3. référentiel RBAC (rôles et permissions) ;
4. journal d'audit en lecture seule.

**Déduction** : le « périmètre métier » demandé au Lot 2 se limite aujourd'hui à l'administration de la plateforme. Les domaines RH/paie n'existent qu'à l'état de documentation (`docs/business-rules/employees-and-contracts/`) et de permissions différées (`docs/security/RBAC.md`, section « Permissions différées »).

### 2.2 Inventaire des composants réellement présents

| Catégorie | Contenu réel | Preuve |
|---|---|---|
| Modèles applicatifs | `User`, `AdministratorInvitation` | `app/Models/` |
| Modèles packages | `Spatie\Permission\Models\Role`, `Permission`, `Spatie\Activitylog\Models\Activity` | `config/permission.php`, `config/activitylog.php` |
| Enums | `AuditEventType`, `AuditEventCategory`, `AuditOrigin`, `RbacRole`, `PermissionName` | `app/Enums/` |
| Value objects | `AuditContext` (`final readonly`), `AuditResult` (`final readonly`) | `app/Services/Audit/` |
| Services | `AuditManager`, `AuditSanitizer`, `SpatieAuditLogger`, `RbacSynchronizer` | `app/Services/Audit/`, `app/Support/Rbac/` |
| Actions | `AcceptAdministratorInvitation`, `InviteAdministrator` | `app/Actions/Administration/` |
| Contrôleurs | `AcceptAdministratorInvitationController` uniquement | `app/Http/Controllers/Auth/` |
| Form Requests | `AcceptAdministratorInvitationRequest` uniquement | `app/Http/Requests/Auth/` |
| Middleware applicatif | `AdministratorInvitationSecurityHeaders` uniquement | `app/Http/Middleware/` |
| Notifications | `AdministratorInvitationNotification` (mail) | `app/Notifications/` |
| Listeners | `AuditAuthenticationEvents` (login, logout, failed) | `app/Listeners/` |
| Commandes Artisan applicatives | `batipaie:admin-invite` uniquement | `app/Console/Commands/` |
| Composants Livewire applicatifs | **aucun** (pas de répertoire `app/Livewire`) | arborescence `app/` |
| Ressources / pages / widgets Filament applicatifs | **aucun** (pas de répertoire `app/Filament`) | arborescence `app/`, `AdminPanelProvider::panel()` |
| Jobs, imports, exports, observateurs, policies applicatives | **aucun** | arborescence `app/` |
| Vues | `welcome`, `auth.administrator-invitation`, `auth.administrator-invitation-invalid` | `resources/views/` |

**Fait** : `AdminPanelProvider::panel()` déclare `discoverResources(in: app_path('Filament/Resources'))`, `discoverPages(...)` et `discoverWidgets(...)`, mais ces répertoires n'existent pas. Le panneau ne sert donc que le tableau de bord Filament et la ressource `ActivityLogResource` fournie par `alizharb/filament-activity-log`.

**Fait** : `ActivityLogResource` est enregistrée par le plugin `ActivityLogPlugin` (`app/Providers/Filament/AdminPanelProvider.php`, lignes 33-79). Elle est protégée par les permissions `audit.view` (`config/filament-activity-log.php`, clé `permissions`). Les actions `delete`, `revert`, `restore`, `export`, `prune` et `retention_hold` sont désactivées.

---

## 3. Inventaire des domaines

Statuts utilisés : **opérationnel**, **partiel**, **structure technique seule**, **absent**.

| Domaine | Rôle fonctionnel | Statut | Preuve |
|---|---|---|---|
| Identité et comptes utilisateurs | Créer et authentifier un compte | **Partiel** — création via acceptation d'invitation ; aucune modification, désactivation ou suppression | `app/Actions/Administration/AcceptAdministratorInvitation.php`, `app/Models/User.php`, `routes/web.php` |
| Amorçage administrateur | Inviter le premier administrateur, consommer ou révoquer l'invitation | **Opérationnel** | `InviteAdministrator`, `AcceptAdministratorInvitation`, `batipaie:admin-invite` |
| RBAC (rôles et permissions) | Synchroniser le référentiel, protéger le panneau et l'audit | **Opérationnel** pour la synchronisation ; **partiel** pour l'attribution (aucune interface de gestion des rôles) | `app/Support/Rbac/RbacMatrix.php`, `RbacSynchronizer.php`, `app/Enums/PermissionName.php` |
| Journal d'audit | Persister et consulter les événements | **Opérationnel** en écriture applicative ; **lecture seule** côté interface | `app/Services/Audit/*`, `config/audit.php`, `config/filament-activity-log.php` |
| Authentification et session | Connexion, déconnexion, échec | **Opérationnel** (Filament + Sanctum pour `/api/user`) | `app/Listeners/AuditAuthenticationEvents.php`, `routes/api.php` |
| Salariés et contrats | Dossiers salariés, contrats, avenants | **Absent** — documentation uniquement | `docs/business-rules/employees-and-contracts/rules.md` (SAL-CON-001 à 015) |
| Convention collective et classifications | IDCC, classifications, minima | **Absent** | `docs/business-rules/README.md` (« À créer ») |
| Temps de travail, absences, congés | Saisie et validation du temps | **Absent** | idem |
| Éléments variables de paie | Primes, heures, variables | **Absent** | idem |
| Calcul de paie et cotisations | Brut, net, PAS, coût employeur | **Absent** | idem |
| Indemnités et frais professionnels BTP | Déplacements, paniers, trajets | **Absent** | idem |
| Chantiers | Affectation et suivi de chantier | **Absent** | idem |
| Bulletins, déclarations, CIBTP | Production et archivage | **Absent** | idem |
| Documents RH et paie | Stockage privé et téléchargement contrôlé | **Absent** | aucun modèle, aucune migration, aucun disque applicatif dédié |
| Reporting et exports | Exports et tableaux de bord métier | **Absent** — l'export d'audit est explicitement désactivé | `config/filament-activity-log.php`, clé `resource.actions.export` = `false` |
| Portail salarié | Accès salarié | **Absent** | aucune route, aucun composant |
| Intégrations et synchronisations externes | Imports, API tierces | **Absent** | aucun job, aucune commande d'import, aucun client HTTP applicatif |

**Fait** : `app/Enums/PermissionName.php` ne contient que deux permissions actives (`administration.panel.access`, `audit.view`) et documente explicitement que les permissions métier ne doivent être ajoutées que lorsque la fonctionnalité existe.

**Déduction** : aucun domaine métier ne peut être instrumenté au Lot 2. Toute liste d'événements `employee.*`, `contract.*`, `leave_request.*` ou `payroll_period.*` serait une invention au regard de l'état du dépôt.

---

## 4. Inventaire des modèles et relations

### 4.1 Modèles applicatifs

| Modèle | Table | Attributs notables | Relations |
|---|---|---|---|
| `App\Models\User` | `users` | `name`, `email` (unique), `email_verified_at`, `password` (`hashed`), `remember_token`, timestamps | `HasRoles` (Spatie, pivot morph `model_has_roles`), `HasApiTokens` (`personal_access_tokens`), `Notifiable` |
| `App\Models\AdministratorInvitation` | `administrator_invitations` | `name`, `email`, `token_hash` (unique, `hidden`), `expires_at`, `consumed_at`, `revoked_at`, timestamps | aucune relation Eloquent |

**Fait** : `User` utilise les attributs PHP `#[Fillable(['name', 'email', 'password'])]` et `#[Hidden(['password', 'remember_token'])]`. `canAccessPanel()` vérifie `administration.panel.access` sur le guard `web` (`app/Models/User.php`).

**Fait** : `AdministratorInvitation::hashToken()` applique `hash('sha256', $token)` ; seul le hash est persisté. `isUsableAt()` vérifie `consumed_at === null && revoked_at === null && expires_at > now()`.

**Fait** : `Notifiable` est déclaré mais aucune migration `notifications` n'existe ; seules les notifications mail sont utilisées (`Notification::route('mail', ...)`).

### 4.2 Modèles packages

| Modèle | Table | Usage réel |
|---|---|---|
| `Spatie\Permission\Models\Role` | `roles` | créé par `RbacSynchronizer::synchronize()` |
| `Spatie\Permission\Models\Permission` | `permissions` | créé par `RbacSynchronizer::ensurePermissions()` |
| pivots Spatie | `role_has_permissions`, `model_has_roles`, `model_has_permissions` | écrits par `syncPermissions()` et `assignRole()` |
| `Spatie\Activitylog\Models\Activity` | `activity_log` | écrit uniquement par `SpatieAuditLogger` |

### 4.3 Tables de la base

**Fait** (`database/migrations/`) :

| Migration | Tables |
|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_11_06_204616_create_permission_tables.php` | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` |
| `2026_11_06_225734_create_personal_access_tokens_table.php` | `personal_access_tokens` |
| `2026_11_08_000000_create_administrator_invitations_table.php` | `administrator_invitations` |
| `2026_11_08_160743_create_activity_log_table.php` | `activity_log` |
| `2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php` | colonnes `risk_score`, `risk_level`, `retention_hold`, `integrity_hash`, `request_id`, `ip_address` + table interne `activity_log_audit_control_objects` |

**Déduction** : les tables `jobs`, `job_batches`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens` et `personal_access_tokens` sont des structures de framework. Aucune n'a de producteur applicatif métier, à l'exception de `sessions` (Filament) et `personal_access_tokens` (Sanctum installé, aucun endpoint de création de token).

### 4.4 Enums et value objects

| Élément | Rôle | Contrainte notable |
|---|---|---|
| `AuditEventType` | catalogue fermé des 16 événements | `fromValue()` refuse toute chaîne hors regex `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$` et tout cas inconnu |
| `AuditEventCategory` | `business`, `security`, `system` | détermine le `log_name` |
| `AuditOrigin` | `user`, `system`, `automated` | un acteur humain est interdit pour `system` et `automated` |
| `RbacRole` | 7 rôles métier cibles | libellés français |
| `PermissionName` | 2 permissions actives | les permissions métier sont différées |
| `AuditContext` | contexte immuable | validation stricte à la construction |
| `AuditResult` | résultat d'écriture | n'expose pas le modèle Spatie |

**Fait** : `AuditEventType::category()` classe `security.*` et `rbac.*` en `Security`, `system.*` en `System`, tout le reste en `Business`.

---

## 5. Cartographie des opérations d'écriture

### 5.1 Méthode

**Fait** : recherche exhaustive dans `app/`, `database/`, `routes/`, `resources/` des motifs `::create(`, `->create(`, `->save(`, `->update(`, `->delete(`, `forceDelete`, `restore(`, `saveQuietly`, `updateQuietly`, `deleteQuietly`, `upsert`, `updateOrCreate`, `firstOrCreate`, `insert(`, `insertOrIgnore`, `DB::table`, `DB::statement`, `DB::update`, `DB::delete`, `attach(`, `detach(`, `sync(`, `syncWithoutDetaching`.

**Fait** : aucun `saveQuietly`, `updateQuietly`, `deleteQuietly`, `upsert`, `updateOrCreate`, `insert(`, `attach(`, `detach(`, `sync(`, `syncWithoutDetaching`, `DB::table`, `DB::statement`, `DB::update`, `DB::delete` dans `app/`. Les seules occurrences sont celles listées ci-dessous, plus `DB::transaction`, `DB::prohibitDestructiveCommands` et un `insertOrIgnore` limité à la migration d'audit.

### 5.2 Opérations réelles

| # | Domaine | Modèle / table | Opération | Point d'entrée | Fichier | Méthode | Couverture audit | Particularité |
|---|---|---|---|---|---|---|---|---|
| 1 | Identité | `User` / `users` | `User::query()->create()` | Acceptation d'invitation (HTTP) | `app/Actions/Administration/AcceptAdministratorInvitation.php:75` | `handle()` | **Couverte** par `user.created` (ligne 97) | dans `DB::transaction`, verrou `lockForUpdate` sur rôle puis invitation |
| 2 | Identité | `User` / `users` | `forceFill(['email_verified_at' => now()])->save()` | même | `...AcceptAdministratorInvitation.php:81` | `handle()` | **Partielle** : la valeur est reprise dans `user.created`, mais aucun `user.updated` n'est émis | déclenche l'événement Eloquent `updated`, sans observateur |
| 3 | Identité / RBAC | pivot `model_has_roles` | `assignRole($administratorRole)` | même | `...AcceptAdministratorInvitation.php:82` | `handle()` | **Couverte** par `rbac.role_assigned` explicite (ligne 104) | écriture pivot sans événement Eloquent de modèle métier |
| 4 | Administration | `AdministratorInvitation` | `AdministratorInvitation::query()->create()` | `batipaie:admin-invite` | `app/Actions/Administration/InviteAdministrator.php:109` | `handle()` | **Couverte** par `administrator_invitation.created` (ligne 117) | transaction, verrou sur le rôle administrateur |
| 5 | Administration | `AdministratorInvitation` | `forceFill(['revoked_at'])->save()` | `batipaie:admin-invite` (invitation en attente) | `...InviteAdministrator.php:77` | `handle()` | **Couverte** par `administrator_invitation.revoked` (ligne 80) | boucle sur invitations non consommées |
| 6 | Administration | `AdministratorInvitation` | `forceFill(['revoked_at'])->save()` | `batipaie:admin-invite` (échec d'envoi) | `...InviteAdministrator.php:141` | `handle()` | **Couverte** par `delivery_failed` + `revoked` (lignes 146, 152) | hors transaction initiale, après l'envoi du mail |
| 7 | Administration | `AdministratorInvitation` | `forceFill(['consumed_at'])->save()` | Acceptation d'invitation | `...AcceptAdministratorInvitation.php:84` | `handle()` | **Couverte** par `administrator_invitation.consumed` (ligne 113) | transaction |
| 8 | RBAC | `Role` / `roles` | `Role::query()->firstOrCreate()` | synchronisation RBAC | `app/Support/Rbac/RbacSynchronizer.php:37` | `synchronize()` | **Non couverte** : la création d'un rôle n'émet aucun événement | idempotent, dans `DB::transaction` |
| 9 | RBAC | `Permission` / `permissions` | `Permission::query()->firstOrCreate()` | synchronisation RBAC | `app/Support/Rbac/RbacSynchronizer.php:120` | `ensurePermissions()` | **Non couverte** : la création d'une permission n'émet aucun événement | idempotent |
| 10 | RBAC | pivot `role_has_permissions` | `$roleModel->syncPermissions(...)` | synchronisation RBAC | `app/Support/Rbac/RbacSynchronizer.php:51` | `synchronize()` | **Couverte indirectement** par le diff `rbac.permission_assigned` / `rbac.permission_revoked` (lignes 62, 70) | opération de masse ; le diff est calculé avant l'écriture |
| 11 | Audit | `Activity` / `activity_log` | `activity()->...->log()` | tous les points d'audit | `app/Services/Audit/SpatieAuditLogger.php:80` | `record()` | Sans objet (journal lui-même) | seul écrivain autorisé ; signature `integrity_hash` appliquée après persistance |
| 12 | Authentification | `sessions` | écriture framework | Filament / Sanctum | framework | — | **Non auditée** | hors périmètre BP-192 |
| 13 | Jetons | `personal_access_tokens` | aucune écriture applicative | — | — | — | Sans objet | Sanctum installé, aucun endpoint de création |

### 5.3 Chemins qui contournent les événements Eloquent

**Fait** : aucun modèle applicatif n'utilise `LogsActivity`, `SoftDeletes`, ni d'observateur. `app/Providers/AppServiceProvider.php::registerAuditListeners()` n'enregistre que `Login`, `Logout` et `Failed`.

**Déduction** : toutes les écritures métier actuelles sont instrumentées **explicitement** par appel à `AuditManager::record()` dans l'action porteuse. Aucune écriture n'est auditée « automatiquement ».

**Fait** : `config/activitylog.php` fixe `buffer.enabled = false` avec le commentaire explicite que les insertions bufferisées contourneraient les événements de modèle et empêcheraient l'application des métadonnées (risque, hash d'intégrité, contexte).

**Déduction** : c'est une garantie nécessaire au mécanisme HMAC. Toute activation du buffer casserait la signature et doit rester interdite.

### 5.4 Transactions métier

| Transaction | Fichier | Contenu |
|---|---|---|
| Acceptation d'invitation | `AcceptAdministratorInvitation.php:35` | verrou rôle, verrou invitation, contrôle administrateur existant, création `User`, vérification e-mail, `assignRole`, consommation invitation, **3 écritures d'audit** |
| Invitation | `InviteAdministrator.php:45` | verrou rôle, révocation des invitations expirées, contrôle administrateur, création invitation, **audit de création** |
| Synchronisation RBAC | `RbacSynchronizer.php:33` | création permissions, `firstOrCreate` rôles, `syncPermissions`, **audits de diff** |

**Déduction** : les écritures d'audit sont dans la même transaction que la donnée auditée. Un rollback supprime donc l'événement d'audit en même temps que la donnée. C'est le comportement attendu (pas d'événement pour une opération annulée) et il est couvert par `tests/Feature/Audit/AuditTransactionTest.php`.

---

## 6. Tableau des événements d'audit candidats

### 6.1 Règle de sélection

**Recommandation** : ne retenir un événement que si son point d'entrée existe ou est explicitement prévu dans une livraison planifiée. Un événement sans producteur est un contrat mort et un risque de faux sentiment de couverture.

### 6.2 Événements retenus (fonctionnalités réellement présentes)

| # | Identifiant | Catégorie | Domaine | Modèle | Action déclenchante | Point d'instrumentation | `old_values` autorisés | `new_values` autorisés | Exclusions impératives | Cas particuliers |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `user.created` | business | Identité | `User` | acceptation d'invitation | `AcceptAdministratorInvitation::handle()` après création | `[]` | `name`, `email`, `email_verified_at` | `password`, `remember_token`, hash | contexte `system`, acteur humain interdit |
| 2 | `user.updated` | business | Identité | `User` | **aucun point d'entrée** | à créer lors d'un futur écran/service de modification | `name`, `email`, `email_verified_at` | idem | `password`, `remember_token` | ne doit jamais transporter un mot de passe, même haché |
| 3 | `user.deleted` | business | Identité | `User` | **aucun point d'entrée** | à créer | `name`, `email` | `[]` | tout identifiant de connexion ou secret | doit rester possible après suppression : `subject_id` conservé |
| 4 | `administrator_invitation.created` | business | Amorçage admin | `AdministratorInvitation` | `batipaie:admin-invite` | `InviteAdministrator::handle()` | `[]` | `name`, `email`, `expires_at` | `token_hash`, jeton brut | jeton brut uniquement en mémoire et dans l'e-mail |
| 5 | `administrator_invitation.consumed` | business | Amorçage admin | `AdministratorInvitation` | acceptation | `AcceptAdministratorInvitation::handle()` | `consumed_at` = `null` | `consumed_at` | jeton, mot de passe | transaction |
| 6 | `administrator_invitation.revoked` | business | Amorçage admin | `AdministratorInvitation` | nouvelle invitation ou échec d'envoi | `InviteAdministrator::handle()` | `revoked_at` | `revoked_at` | jeton | deux points d'appel distincts, même événement |
| 7 | `administrator_invitation.delivery_failed` | business | Amorçage admin | `AdministratorInvitation` | échec SMTP | `InviteAdministrator::handle()` (catch `Throwable`) | `[]` | `[]` | corps de l'e-mail, jeton, adresse SMTP | ne jamais journaliser le message d'exception brut |
| 8 | `rbac.role_assigned` | security | RBAC | `User` | acceptation d'invitation | `AcceptAdministratorInvitation::handle()` | `[]` | `name`, `email` | secrets | métadonnées `role`, `guard` |
| 9 | `rbac.role_removed` | security | RBAC | `User` | **aucun point d'entrée** | à créer avec la gestion des rôles | `name`, `email` | `name`, `email` | secrets | idem |
| 10 | `rbac.permission_assigned` | security | RBAC | `Role` | synchronisation | `RbacSynchronizer::synchronize()` | `[]` | `[]` | — | métadonnées `role`, `permission`, `guard` |
| 11 | `rbac.permission_revoked` | security | RBAC | `Role` | synchronisation | `RbacSynchronizer::synchronize()` | `[]` | `[]` | — | idem |
| 12 | `security.login_succeeded` | security | Authentification | `User` | événement `Login` | `AuditAuthenticationEvents::login()` | `[]` | `[]` | identifiants, payload | acteur = utilisateur connecté |
| 13 | `security.logout` | security | Authentification | `User` | événement `Logout` | `AuditAuthenticationEvents::logout()` | `[]` | `[]` | session, cookie | acteur = utilisateur |
| 14 | `security.login_failed` | security | Authentification | `User` | événement `Failed` | `AuditAuthenticationEvents::failed()` | `[]` | `[]` | e-mail saisi, mot de passe | compte inconnu volontairement non journalisé (anti-énumération) |

### 6.3 Événements définis mais sans producteur

| Identifiant | Constat | Recommandation |
|---|---|---|
| `user.updated` | défini, filtré, testé au niveau API ; aucun écran ni service de modification d'utilisateur | ne pas le déclarer « couvert » ; l'instrumenter lors du premier point d'entrée réel |
| `user.deleted` | idem | idem |
| `rbac.role_removed` | défini et testé ; aucun retrait de rôle hors tests | idem |
| `security.access_denied` | défini ; aucun émetteur. `strictAuthorization()` de Filament refuse l'accès sans passer par `AuditManager` | **hors périmètre Lot 2** ; à traiter dans un lot sécurité dédié si le besoin est confirmé |
| `system.command_completed` | défini ; aucune commande ne l'émet | **hors périmètre Lot 2** ; ne pas activer pour `batipaie:admin-invite` sans arbitrage (risque de doublon avec les événements métier déjà émis) |

### 6.4 Événements explicitement non retenus

**Fait** : aucune des fonctionnalités suivantes n'existe dans le dépôt. Les événements correspondants ne sont donc **pas** retenus au Lot 2 :

- `employee.created`, `employee.updated`, `employee.deleted` ;
- `contract.created`, `contract.updated`, `contract.terminated` ;
- `leave_request.created`, `leave_request.approved`, `leave_request.rejected` ;
- `payroll_period.created`, `payroll_period.closed`, `payslip.generated` ;
- tout événement `site.*`, `expense.*`, `document.*`, `cibtp.*`, `declaration.*`.

**Déduction** : les propositions indicatives du brief ne peuvent pas être transformées en spécification tant que les modèles, tables et points d'entrée n'existent pas. Les inclure produirait un catalogue d'événements sans contrepartie technique et fausserait le suivi de couverture.

### 6.5 Convention de nommage

**Fait** : `AuditEventType::fromValue()` impose `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$`.

**Recommandation** :

- `<domaine_au_pluriel>.<action_au_participes_passé>` : `employees.created`, `contracts.terminated` ;
- une action métier distincte (validation, refus, clôture) doit avoir son propre identifiant, pas un `updated` générique ;
- tout nouvel identifiant doit être ajouté simultanément à `AuditEventType`, à `config/audit.php` (`fields` et, si nécessaire, `reasons`) et documenté.

---

## 7. Politique de protection des données

### 7.1 Données sensibles réellement présentes

| Donnée | Emplacement | Statut actuel |
|---|---|---|
| Mot de passe (haché) | `users.password` | `Hidden`, `hashed`, exclu des allowlists et `sensitive_keys` |
| `remember_token` | `users.remember_token` | `Hidden`, exclu |
| Jeton d'invitation brut | mémoire, URL `GET/POST /admin/invitation/{token}`, e-mail | jamais persisté ; seul le SHA-256 l'est |
| Hash de jeton | `administrator_invitations.token_hash` | `Hidden`, exclu des allowlists |
| Adresse e-mail | `users.email`, `administrator_invitations.email` | journalisée (`name`, `email`) |
| Session | `sessions.payload` | non journalisée |
| Jetons API | `personal_access_tokens.token` | non journalisé |
| Métadonnées d'audit | `activity_log.properties` | allowlist + blacklist + motifs |
| Adresse IP / user-agent | colonnes `ip_address` présentes ; collecte désactivée (`auto_context.enabled = false`) | non renseignées aujourd'hui |

### 7.2 Politique à trois niveaux

| Niveau | Données | Règle |
|---|---|---|
| **Journalisable en clair** | nom, adresse e-mail professionnelle, dates techniques (`created_at`, `expires_at`, `consumed_at`, `revoked_at`), noms de rôles et de permissions, identifiants techniques de corrélation | allowlist explicite par couple modèle/événement |
| **Journalisable sous forme masquée ou dérivée** | adresse e-mail dans les sorties console (déjà masquée par `InviteAdministratorCommand::maskEmail()`), adresse IP si un jour collectée (anonymisation prévue par la configuration), identifiant de document | masquage **avant** persistance, jamais uniquement à l'affichage |
| **Exclu totalement** | mot de passe (même haché), `remember_token`, jeton brut, `token_hash`, cookies et sessions, IBAN/BIC/RIB, NIR/numéro de sécurité sociale, données médicales, contenu de document, corps d'e-mail | blacklist `config/audit.php` + motifs + `default_except_attributes` + redaction Filament |

### 7.3 Mécanismes constatés

**Fait** : `AuditSanitizer` applique, dans cet ordre :

1. l'allowlist `audit.fields[subject][event]` ; un couple absent ne journalise **aucune** valeur ;
2. la blacklist `audit.sensitive_keys` et les motifs `audit.sensitive_patterns`, même sur un champ autorisé ;
3. le filtrage récursif des tableaux et objets `JsonSerializable`, avec `audit.max_depth` ;
4. le rejet des modèles Eloquent passés comme valeur ;
5. la validation des métadonnées par catégorie (valeurs contrôlées, identifiants opaques, allowlist de `reason`).

**Fait** : la protection intervient avant la persistance (`SpatieAuditLogger` appelle `sanitizeChanges()` et `sanitizeMetadata()` avant `->log()`), pas au moment de l'affichage.

**Fait** : `config/filament-activity-log.php` ajoute une couche de redaction UI (`privacy.redaction`) et un mode `immutable_mode = true`. **Déduction** : cette couche est un filet de sécurité d'affichage, pas le contrôle principal.

### 7.4 Recommandations pour les futurs domaines

- **Recommandation** : conserver l'allowlist par couple modèle/événement, sans exception « tout sauf ».
- **Recommandation** : pour les rémunérations, journaliser le montant et la date d'effet dans l'événement métier, mais jamais l'IBAN, le NIR ou le bulletin complet.
- **Recommandation** : pour les documents, journaliser uniquement l'identifiant, le type, le nom logique et l'empreinte, jamais le contenu binaire ni le chemin de stockage.
- **Recommandation** : pour les données médicales, exclure totalement ; journaliser seulement l'existence d'un arrêt et ses dates administratives après arbitrage métier/RGPD.
- **Recommandation** : ne jamais réutiliser `reason` comme champ libre ; toute nouvelle justification doit être ajoutée à `config/audit.php`.

---

## 8. Analyse des origines et des acteurs

### 8.1 Mécanisme existant

**Fait** : `AuditManager::defaultContext()` construit le contexte par défaut :

- `source = console` si `app()->runningInConsole()`, sinon `http` ;
- acteur = `Auth::user()` si un utilisateur est authentifié ;
- sinon contexte `system` sans acteur ;
- identifiant de corrélation UUID généré systématiquement.

**Fait** : `AuditContext` refuse un acteur humain pour `system` et `automated`, et refuse un contexte `user` sans acteur. `AuditManager::withContext()` restaure le contexte précédent dans un `finally`.

**Fait** : `AuditManager` est lié en `scoped` (`AppServiceProvider::register()`), donc réinitialisé entre requêtes et entre jobs. `resetContext()` couvre les processus longs.

**Fait** : `SpatieAuditLogger::record()` refuse un acteur non persisté (`AuditLoggingException::invalidActor`) et écrit `causedByAnonymous()` lorsqu'il n'y a pas d'acteur.

### 8.2 Correspondance réelle point d'entrée → origine

| Point d'entrée | Origine | Acteur | Métadonnées | Justesse |
|---|---|---|---|---|
| `POST /admin/invitation/{token}` | `system` | aucun | `source=http`, `channel=administrator_invitation`, `initiated_by=invitation_recipient` | correct : le destinataire n'est pas authentifié |
| `batipaie:admin-invite` | `system` | aucun | `source=console`, `command=batipaie:admin-invite`, `channel=administrator_invitation` | correct |
| `RbacSynchronizer` via seeder/console | `system` (contexte par défaut) | aucun | `channel=rbac_synchronization`, `reason`, `source=console` | correct |
| `RbacSynchronizer` via HTTP (aucun point d'entrée aujourd'hui) | `user` ou `system` selon `Auth::user()` | selon contexte | idem | à surveiller si un écran est ajouté |
| `Login` | `user` | utilisateur connecté | `source=auth`, `channel=session`, `guard` | correct |
| `Logout` | `user` | utilisateur | idem | correct |
| `Failed` (compte connu) | `system` | aucun | idem | correct : ne pas attribuer l'échec à la victime |
| `Failed` (compte inconnu) | aucune écriture | — | — | correct : anti-énumération |

### 8.3 Valeurs autorisées et non utilisées

**Fait** : `config/audit.php` autorise `source` ∈ `{http, console, queue, scheduler, test, auth}`, mais `queue` et `scheduler` n'ont aucun producteur. `AuditOrigin::Automated` est défini mais aucun code applicatif ne l'utilise.

**Recommandation** : lors de l'ajout de jobs ou d'imports, imposer :

- `AuditContext::automated()` avec `source=queue` ou `source=scheduler` ;
- `metadata.job` ou `metadata.command` renseigné ;
- `metadata.initiated_by` pour identifier l'utilisateur ou le système demandeur ;
- un `correlation_id` commun à toutes les lignes d'un même lot.

**Recommandation** : ne jamais déduire l'acteur d'une variable globale dans un job. Le contexte doit être passé explicitement par le dispatch, sinon un job exécuté après une requête authentifiée pourrait attribuer à tort l'action à l'utilisateur.

### 8.4 Cas où acteur métier et origine technique diffèrent

**Fait** : l'acceptation d'invitation est le cas réel : l'origine est `system`, l'acteur métier est le destinataire non authentifié, matérialisé par `initiated_by=invitation_recipient`.

**Recommandation** : pour les futurs imports et synchronisations externes, distinguer `origin=automated` et `metadata.initiated_by`. Ne pas créer d'acteur fictif ; `AuditContext` interdit déjà un acteur humain en `automated`.

---

## 9. Cas particuliers et limites techniques

| Situation | Support actuel | Stratégie nécessaire |
|---|---|---|
| Transaction multi-modèles | **Pris en charge** : audits dans la même transaction (`AcceptAdministratorInvitation`, `InviteAdministrator`, `RbacSynchronizer`) | conserver ce modèle ; ne jamais écrire l'audit hors transaction pour une opération atomique |
| Rollback / échec | **Pris en charge** : la ligne d'audit est annulée avec la donnée ; testé par `AuditTransactionTest` | vérifier qu'aucun audit n'est écrit après le `commit` pour une opération déjà validée |
| Échec d'écriture d'audit | **Fail-closed** : `AuditLoggingException` remonte et annule la transaction | conserver ; ne jamais envelopper l'audit dans un `try/catch` silencieux |
| Suppression logique | **Non applicable** : aucun modèle n'utilise `SoftDeletes` | lors de l'introduction, distinguer `deleted` (logique) et `force_deleted` via métadonnée |
| Suppression définitive / restauration | **Absente** | prévoir `*.deleted` avec `metadata.hard_delete=true` ; ne pas réutiliser `restored` sans arbitrage |
| Relation many-to-many / pivot | **Partiellement pris en charge** : `assignRole` et `syncPermissions` n'émettent aucun événement de modèle | auditer explicitement le diff avant/après, comme le fait `RbacSynchronizer` |
| Opérations de masse | **Non supportées** : aucun `upsert`, `updateOrCreate` ou `DB::table` applicatif | si introduites, calculer le diff et émettre un événement par enregistrement ou un événement de lot corrélé |
| Imports | **Absents** | un événement par enregistrement + `correlation_id` de lot ; jamais un événement unique opaque |
| Synchronisations externes | **Absentes** | contexte `automated`, `source` dédié, `initiated_by`, idempotence |
| Jobs relancés | **Absents** ; `AuditManager` n'a pas de clé d'idempotence | accepter le doublon et le corréler, ou ajouter une clé d'idempotence dans les métadonnées (hors Lot 2) |
| `saveQuietly()` / `updateQuietly()` / `deleteQuietly()` | **Aucun usage** | interdire par revue de code : ces méthodes empêchent tout observateur futur |
| Écritures sans événement Eloquent | **Présentes** : `firstOrCreate` (rôles, permissions), `syncPermissions`, `assignRole` | instrumentation explicite obligatoire |
| Journalisation du journal | **Volontairement absente** | ne pas activer `LogsActivity` sur `Activity` (récursion, bruit) |
| Buffer Activitylog | **Désactivé** (`config/activitylog.php`) | maintenir désactivé : le buffer contourne les événements de modèle et donc la signature |
| Signature HMAC | **Appliquée après persistance** par la couche Filament (`integrity.key` → `ACTIVITY_LOG_INTEGRITY_KEY`) | hors périmètre ; ne pas modifier |
| Rotation de clé HMAC | **Non gérée** : mono-clé | hors périmètre ; documenter la perte de vérifiabilité des anciennes lignes |
| Suppression de lignes | **Non détectable** par HMAC | hors périmètre ; nécessite un ancrage externe |
| Isolation multi-entreprise / multi-établissement | **Absente** : aucune colonne `company_id` / `establishment_id` | **point bloquant pour les domaines à venir** : le journal devra être cloisonné, sinon un `audit.view` global exposera toutes les entités |
| Politique de conservation | **Non arbitrée** : `clean_after_days = null`, `retention.enabled = false` | à arbitrer avant la mise en production des domaines métier |
| Couverture du test d'encodage | **Partielle** : `auditSourceFiles()` ne couvre ni `AdminPanelProvider.php` ni l'ensemble de `app/` | étendre la garde à tous les fichiers porteurs de libellés français (lot de qualité séparé) |

**Fait** : `tests/MySQL/` contient deux scripts de concurrence (bootstrap administrateur) exécutés hors suite Pest standard. **Déduction** : les scénarios de concurrence sur les futures écritures métier ne seront pas couverts par la suite SQLite par défaut.

---

## 10. Proposition de découpage du Lot 2

### 10.1 Principe

**Déduction** : seuls deux sous-lots sont réalisables immédiatement, car ils portent sur des fonctionnalités existantes ou sur des points d'entrée manquants du socle d'administration. Les sous-lots métier ne peuvent être planifiés qu'après livraison du domaine correspondant.

### 10.2 Lot 2A — Cycle de vie utilisateur

| Rubrique | Contenu |
|---|---|
| Domaines | Identité et comptes |
| Modèles | `User` |
| Événements | `user.updated`, `user.deleted` |
| Fichiers susceptibles d'être modifiés | nouvelle action `app/Actions/Administration/UpdateUser.php`, `DeleteUser.php` ; éventuel écran Filament ; `config/audit.php` si les allowlists évoluent ; tests |
| Dépendances | décision métier sur la modification/suppression des comptes et sur la suppression définitive |
| Risques de régression | mot de passe modifié journalisé par erreur ; suppression d'un administrateur laissant le système sans administrateur ; policy manquante |
| Tests | création/modification/suppression, old/new, acteur, origine, mot de passe exclu, rollback, opération refusée |
| Critères d'acceptation | aucun secret dans `activity_log` ; `user.updated` contient uniquement `name`, `email`, `email_verified_at` ; `user.deleted` conserve le `subject_id` ; permissions vérifiées côté serveur |
| Complexité | moyenne |

### 10.3 Lot 2B — Administration RBAC

| Rubrique | Contenu |
|---|---|
| Domaines | RBAC |
| Modèles | `User`, `Role`, `Permission`, pivots |
| Événements | `rbac.role_assigned`, `rbac.role_removed` (+ éventuellement audit de création de rôle) |
| Fichiers susceptibles d'être modifiés | nouvel écran/action de gestion des rôles, `config/audit.php`, tests |
| Dépendances | décision sur les permissions différées (`users.assign_roles`, `roles.manage`) ; ne pas créer de permission sans fonctionnalité |
| Risques de régression | duplication avec les événements Spatie Permission ; élévation de privilèges non tracée ; suppression du dernier administrateur |
| Tests | attribution/retrait, acteur administrateur, refus pour un rôle non autorisé, absence de doublon, non-régression du synchroniseur |
| Critères d'acceptation | chaque attribution/retrait produit exactement une ligne ; `rbac.permission_*` reste produit par le synchroniseur uniquement ; aucun événement Spatie Permission activé sans suppression du diff |
| Complexité | moyenne |

### 10.4 Lots métier conditionnels

Les sous-lots suivants sont **indicatifs**. Ils ne peuvent pas être spécifiés ni estimés de façon fiable avant que le domaine correspondant existe (modèles, tables, actions, écrans). Les identifiants d'événements listés sont des **gabarits non retenus** au sens de la section 6.4.

| Sous-lot | Domaine | Prérequis | Gabarit d'événements (non validé) | Complexité prévisible |
|---|---|---|---|---|
| 2C | Salariés et contrats | modèles `Employee`, `Contract`, `ContractAmendment` ; documentation SAL-CON validée | `employees.*`, `contracts.*` | élevée |
| 2D | Temps, absences, congés | modèles de saisie et de validation | `worktime.*`, `leave_requests.*` | élevée |
| 2E | Éléments variables, frais BTP, chantiers | modèles de variables, frais, chantiers | `payroll_variables.*`, `expenses.*`, `sites.*` | élevée |
| 2F | Paie, bulletins, déclarations, CIBTP | moteur de paie, périodes, bulletins | `payroll_periods.*`, `payslips.*`, `declarations.*` | très élevée |
| 2G | Documents, exports, reporting | stockage privé, policies de téléchargement | `documents.*` (+ audit d'export distinct) | moyenne à élevée |

**Recommandation** : ne pas ouvrir 2C avant que les règles SAL-CON soient validées et que les modèles soient livrés. Instrumenter un domaine naissant coûte moins cher que rétro-instrumenter un domaine déjà en production.

---

## 11. Plan de tests

### 11.1 Matrice commune (à décliner par sous-lot)

| # | Test | Assertion attendue |
|---|---|---|
| 1 | Création | une ligne, `event` exact, `subject_type`/`subject_id` corrects, `new_values` limités à l'allowlist |
| 2 | Modification | `old_values` et `new_values` contiennent uniquement les champs autorisés et les valeurs réellement modifiées |
| 3 | Suppression | une ligne, sujet conservé par identifiant, aucune valeur sensible |
| 4 | Acteur | `causer_id` = utilisateur réel ; jamais un acteur inventé |
| 5 | Origine | `properties.origin` = `user` / `system` / `automated` conformément au point d'entrée |
| 6 | Traitement automatisé | `source=queue|scheduler`, `job` ou `command` renseigné, aucun causer humain |
| 7 | Données sensibles | injection de `password`, `token`, `iban`, `nir` : aucune valeur persistée |
| 8 | Rollback | aucune ligne d'audit après annulation de la transaction |
| 9 | Opération échouée | aucune ligne pour une validation refusée ou une exception avant écriture |
| 10 | Opération de masse | une ligne par enregistrement ou événement de lot corrélé, jamais un événement unique opaque |
| 11 | Relations et pivots | attribution/retrait produisent exactement une ligne, sans doublon |
| 12 | Non-régression | les tests des lots 1A → 1E.3 restent verts |
| 13 | Immutabilité | aucune API ne permet de modifier ou supprimer une ligne d'audit |
| 14 | Encodage | les nouveaux libellés français passent la garde d'encodage et les descriptions exactes |

### 11.2 Tests par sous-lot

| Sous-lot | Fichiers de test à créer/étendre | Points spécifiques |
|---|---|---|
| 2A | `tests/Feature/Audit/AuditUserInstrumentationTest.php` (étendre), nouveaux tests d'action | mot de passe jamais journalisé, dernier administrateur protégé, `user.deleted` après suppression |
| 2B | `tests/Feature/Audit/AuditRbacInstrumentationTest.php` (étendre), tests d'autorisation | un seul événement par attribution, refus sans permission, non-régression du synchroniseur |
| 2C+ | tests du domaine + tests d'audit dédiés | à définir avec le domaine ; réutiliser la matrice commune |

**Recommandation** : ne pas modifier les tests existants pour les faire passer ; ajouter des cas.

---

## 12. Risques et points à arbitrer

| # | Risque / point | Nature | Impact | Recommandation |
|---|---|---|---|---|
| 1 | Aucun domaine métier n'existe | Fait | Le Lot 2 métier est impossible aujourd'hui | livrer 2A/2B, puis suivre les livraisons de domaines |
| 2 | Trois événements sans producteur (`user.updated`, `user.deleted`, `rbac.role_removed`) | Fait | faux sentiment de couverture | ne pas les compter comme couverts |
| 3 | Création de rôles et de permissions non auditée | Fait | ajout silencieux d'un rôle | arbitrer : événement dédié ou documentation explicite |
| 4 | `email_verified_at` modifié sans `user.updated` | Fait | modification non tracée isolément | arbitrer : accepter l'agrégation dans `user.created` ou émettre un événement |
| 5 | Adresse e-mail journalisée en clair | Fait | donnée personnelle dans le journal | arbitrer la conservation et l'accès ; le masquage console existe déjà |
| 6 | Absence d'isolation entreprise/établissement | Fait | fuite inter-entités dès les premiers domaines | **bloquant** : définir le cloisonnement avant 2C |
| 7 | Politique de conservation non arbitrée | Fait | conservation illimitée ou purge non conforme | arbitrer avant mise en production |
| 8 | Buffer Activitylog désactivable par configuration | Fait | perte de signature HMAC | verrouiller par test de configuration |
| 9 | Observateurs ou `LogsActivity` ajoutés plus tard | Risque | doublons d'événements | maintenir la règle « instrumentation explicite uniquement » |
| 10 | Événements Spatie Permission activés | Risque | doublons avec le diff du synchroniseur | interdire sans suppression du diff |
| 11 | Volume d'audit du synchroniseur RBAC | Risque | bruit si la synchronisation est fréquente | conserver le diff (pas d'événement si aucun changement) |
| 12 | Couverture partielle de la garde d'encodage | Fait | libellés corrompus non détectés | étendre `auditSourceFiles()` à tous les fichiers porteurs de textes français |
| 13 | Concurrence non couverte hors MySQL | Fait | conditions de course sur les futurs domaines | intégrer les scénarios MySQL au plan de tests des lots métier |
| 14 | Absence de clé d'idempotence d'audit | Fait | doublons en cas de rejeu de job | accepter et corréler, ou ajouter une clé en métadonnée |
| 15 | Suppressions de lignes non détectables par HMAC | Fait (limite connue) | altération non prouvable | hors périmètre ; documenter |

**Points à arbitrer explicitement avant tout code** :

1. faut-il auditer la création des rôles et permissions, ou seulement le diff d'attribution ?
2. faut-il un événement distinct pour la vérification d'e-mail (`user.created` vs `user.updated`) ?
3. quelle durée de conservation et quel niveau d'accès pour les adresses e-mail journalisées ?
4. quel modèle d'isolation entreprise/établissement pour le journal ?
5. quelles données de paie sont journalisables (montants, dates d'effet) et lesquelles sont exclues ?

---

## 13. Conclusion et ordre d'implémentation

### 13.1 Ce que la cartographie établit

**Fait** : le dépôt ne contient aujourd'hui aucun domaine RH/paie. Le socle existant couvre l'identité, l'amorçage administrateur, le RBAC et l'audit, avec une instrumentation explicite et transactionnelle de sept écritures réelles.

**Fait** : le mécanisme d'audit est correctement structuré pour accueillir de nouveaux domaines : catalogue fermé, contextes typés, allowlist par couple modèle/événement, blacklist de secrets, écriture unique, signature après persistance.

**Déduction** : la valeur du Lot 2 réside moins dans l'instrumentation immédiate que dans la fixation des conventions et dans la réduction du risque de rétro-instrumentation.

### 13.2 Ordre recommandé

1. **Lot 2A** — cycle de vie utilisateur (modification, suppression) : ferme les trois événements sans producteur du socle.
2. **Lot 2B** — administration RBAC : ferme `rbac.role_removed` et sécurise l'attribution des rôles.
3. **Arbitrages transverses** — isolation entreprise/établissement, conservation, données de paie journalisables.
4. **Lot 2C** — salariés et contrats, dès que le domaine et les règles SAL-CON sont livrés et validés.
5. **Lots 2D à 2G** — par ordre de livraison réelle des domaines, en réutilisant la matrice de tests commune.

### 13.3 Verdict

**CARTOGRAPHIE VALIDÉE AVEC RÉSERVES.**

Réserves :

- aucune cartographie d'événements métier RH/paie n'est possible, faute de domaine implémenté ; la section 6.4 l'explicite et refuse d'inventer des événements ;
- trois événements du socle restent sans point d'entrée réel ;
- l'isolation multi-entreprise et la politique de conservation ne sont pas arbitrées ;
- la garde d'encodage ne couvre pas encore tous les fichiers porteurs de libellés français ;
- le périmètre du Lot 2 ne peut pas être planifié au-delà des sous-lots 2A et 2B.

Aucune implémentation du Lot 2 n'a été commencée. Aucun code applicatif, migration, configuration, permission ou interface n'a été modifié par ce lot.
