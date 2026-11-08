# BP-192 — Lot 2A — Instrumentation du cycle de vie des utilisateurs

- **Ticket** : BP-192, Lot 2A.
- **Date** : 2026-11-08.
- **Branche** : `develop`.
- **HEAD** : `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`.
- **Périmètre** : `user.created`, `user.updated`, `user.deleted`.
- **Documents amont** : `bp-192-audit-business-mapping.md`, `bp-192-audit-architecture-decisions.md`, `bp-192-audit-instrumentation.md`.

Conventions : **Fait** = constaté dans le code ; **Déduction** = conséquence technique ; **Recommandation** = proposition non implémentée.

---

## 1. Objet et résultat

**Fait** : le dépôt ne contient qu'un seul point d'entrée applicatif qui crée un utilisateur : l'acceptation d'une invitation administrateur. Il n'existe **aucun** point d'entrée de modification ou de suppression d'utilisateur : ni contrôleur, ni action, ni composant Livewire, ni ressource Filament, ni commande Artisan.

**Résultat du lot** :

| Événement | Producteur réel | Statut |
|---|---|---|
| `user.created` | `AcceptAdministratorInvitation::handle()` | **instrumenté**, couverture renforcée et testée |
| `user.updated` | aucun | **non instrumentable** — documenté |
| `user.deleted` | aucun | **non instrumentable** — documenté |

**Décision appliquée** : conformément au brief, aucun CRUD utilisateur, aucun écran d'administration et aucun observateur global n'ont été créés pour produire artificiellement des événements. Aucun événement fictif n'est émis.

---

## 2. État vérifié du dépôt

| Élément | Valeur |
|---|---|
| Branche | `develop` |
| HEAD | `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` |
| Modifications préexistantes | 56 entrées des lots 0 → 1E.3, intactes |
| Entrées supplémentaires antérieures | `AGENTS.md` (règle de tests ciblés), `docs/business-rules/bp-192-audit-business-mapping.md`, `docs/business-rules/bp-192-audit-architecture-decisions.md` |
| Répertoires applicatifs | `Actions`, `Console`, `Contracts`, `Enums`, `Exceptions`, `Http`, `Listeners`, `Models`, `Notifications`, `Providers`, `Services`, `Support` |
| `app/Filament` | absent |
| `app/Livewire` | absent |
| `app/Jobs`, `app/Imports`, `app/Observers` | absents |

**Fait** : la situation décrite par la cartographie est toujours exacte au moment de ce lot.

---

## 3. Points d'entrée réels

| Opération | Point d'entrée | Fichier | Méthode |
|---|---|---|---|
| Création | `POST /admin/invitation/{token}` | `app/Http/Controllers/Auth/AcceptAdministratorInvitationController.php` | `store()` |
| Création (métier) | action applicative | `app/Actions/Administration/AcceptAdministratorInvitation.php` | `handle()` |
| Modification | **aucun** | — | — |
| Suppression | **aucun** | — | — |
| Désactivation | **aucune colonne d'état** | `database/migrations/0001_01_01_000000_create_users_table.php` | — |

**Fait** : recherche exhaustive dans `app/`, `routes/` et `database/` des motifs `->update(`, `->delete(`, `forceDelete`, `destroy(`, `SoftDeletes`, `UserUpdated`, `UserDeleted`. Seules occurrences :

- `User::query()->create()` dans `AcceptAdministratorInvitation::handle()` ;
- les cas `AuditEventType::UserUpdated` et `AuditEventType::UserDeleted` dans l'enum et la configuration ;
- les tests d'API `tests/Feature/Audit/AuditUserInstrumentationTest.php`.

**Déduction** : `user.updated` et `user.deleted` restent définis, filtrés et testés au niveau de l'API d'audit, mais n'ont aucun producteur en production.

---

## 4. `user.created` — instrumentation constatée

### 4.1 Implémentation

**Fait** (`app/Actions/Administration/AcceptAdministratorInvitation.php::handle()`) :

1. `User::query()->create([...])` — ligne 75 ;
2. `forceFill(['email_verified_at' => now()])->save()` — ligne 81 ;
3. `assignRole($administratorRole)` — ligne 82 ;
4. `forceFill(['consumed_at' => now()])->save()` sur l'invitation — ligne 84 ;
5. `AuditManager::record(UserCreated, ...)` — ligne 97 ;
6. `AuditManager::record(RbacRoleAssigned, ...)` — ligne 104 ;
7. `AuditManager::record(AdministratorInvitationConsumed, ...)` — ligne 113.

Le tout est exécuté dans un unique `DB::transaction()` (ligne 35), avec `lockForUpdate()` sur le rôle, l'invitation et l'administrateur existant.

### 4.2 Champs journalisés

| Champ | Présent | Source |
|---|---|---|
| `user_id` | oui, via `subject_id` | morph `subject` |
| `name` | oui, nouvelle valeur | `$user->only(['name', 'email', 'email_verified_at'])` |
| `email` | oui, nouvelle valeur | idem |
| `email_verified_at` | oui | idem, justifie la vérification du compte |
| `old_values` | vide | création |

**Fait** : l'allowlist `config/audit.php` → `AuditEventType::UserCreated->value => ['name', 'email', 'email_verified_at']` correspond exactement aux valeurs transmises.

**Déduction** : `email_verified_at` est un champ supplémentaire par rapport au tableau minimal du brief (nom, e-mail). Il reste non sensible, déjà allowlisté et documenté ; il a été conservé pour ne pas modifier un comportement validé.

### 4.3 Exclusions

**Fait** : `password` n'est jamais transmis à `AuditManager`. Le modèle `User` le caste en `hashed` et le déclare `Hidden`. `remember_token` est `Hidden`. Aucun jeton, cookie, session ou credential n'est transmis.

**Fait** : même si un champ interdit était transmis par erreur, `AuditSanitizer::sanitizeValues()` le retirerait via `isSensitiveKey()` avant la persistance (`app/Services/Audit/AuditSanitizer.php`).

**Déduction** : la protection repose sur deux barrières indépendantes : l'appelant ne transmet pas le secret, et le sanitizer le retirerait. Aucun masquage d'affichage n'est utilisé comme contrôle principal.

### 4.4 Origine et acteur

**Fait** : le contexte est construit par `AuditContext::system(correlationId, reason: 'Acceptation d'une invitation administrateur', metadata: [source => 'http', channel => 'administrator_invitation', initiated_by => 'invitation_recipient'])`.

Conséquences vérifiées :

- `properties.origin = system` ;
- `properties.source = http` ;
- `causer_id = null` et `causer_type = null` ;
- aucun acteur humain fictif n'est attribué ;
- les trois événements partagent le même `correlation_id`.

**Déduction** : la distinction entre acteur métier (destinataire de l'invitation, non authentifié) et origine technique (`system`) est correctement portée par `initiated_by = invitation_recipient`, conformément à `AuditContext`.

### 4.5 Unicité

**Fait** : `user.created` n'est produit qu'une fois par acceptation. Une seconde soumission du même jeton retourne 404 et n'ajoute aucune ligne (test existant, conservé).

---

## 5. `user.updated` — non instrumentable

### 5.1 Constat

**Fait** : aucun point d'entrée ne modifie un utilisateur. Aucun écran, aucune action, aucune route, aucune commande. Le modèle `User` ne contient ni méthode de mise à jour métier ni accesseur d'administration.

**Déduction** : instrumenter `user.updated` aujourd'hui imposerait soit de créer un CRUD hors périmètre, soit d'ajouter un observateur global. Les deux options sont explicitement interdites par le brief et par les règles des lots précédents (« instrumentation explicite uniquement »).

### 5.2 Règles à appliquer lors du futur point d'entrée

Ces règles sont documentées ici pour être appliquées sans réinterprétation :

| Règle | Détail |
|---|---|
| Comparaison avant/après | calculer le diff réel, ne transmettre que les champs modifiés |
| Champs autorisés | `name`, `email` (et `email_verified_at` si l'état de vérification change) |
| Un seul événement | une opération métier cohérente = une ligne, même si nom et e-mail changent ensemble |
| Aucun événement | si aucun champ autorisé ne change (par exemple modification du seul mot de passe) |
| Auteur | `AuditContext::user(actor: Auth::user())` |
| Sujet | l'utilisateur modifié, distinct du causer |
| Mot de passe | jamais la valeur ni le hash ; éventuellement `metadata.password_changed = true` |
| Transaction | écriture métier et audit dans la même transaction |
| Interdits | observateur global, `LogsActivity`, appel direct à `activity()` |

### 5.3 Changement de mot de passe

**Déduction** : un changement de mot de passe est un événement de sécurité. Il ne doit pas transporter de secret. Un événement distinct (`user.password_changed` ou `security.password_changed`) pourra être décidé ultérieurement ; il n'est pas créé dans ce lot.

---

## 6. `user.deleted` — non instrumentable

### 6.1 Constat

**Fait** :

- aucun point d'entrée de suppression ;
- `App\Models\User` n'utilise pas `SoftDeletes` ;
- la table `users` ne possède pas de colonne `deleted_at` ;
- aucune route, commande ou action ne supprime un utilisateur ;
- `SpatieAuditLogger` accepte néanmoins un sujet supprimé tant que sa clé existe, et `tests/Feature/Audit/AuditUserInstrumentationTest.php` vérifie ce comportement au niveau de l'API.

**Déduction** : une suppression serait aujourd'hui **physique**. Aucune suppression logique n'existe.

### 6.2 Comportement attendu lors d'un futur point d'entrée

| Point | Règle |
|---|---|
| Moment | après la suppression effective, dans la même transaction |
| Identifiant | `subject_id` conservé (la ligne d'audit survit au sujet) |
| Valeurs | `old_values = ['name', 'email']`, `new_values = []` |
| Suppression logique | si `SoftDeletes` est introduit, `user.deleted` doit distinguer le type via `metadata` |
| Restauration | `restored` ne doit pas être réutilisé sans arbitrage ; créer un événement dédié si nécessaire |
| Jetons | révoquer les jetons Sanctum de l'utilisateur dans la même opération |
| Dernier administrateur | suppression interdite et non auditée comme réussie |
| Rollback | aucune ligne `user.deleted` si la transaction est annulée |

**Recommandation** : ne jamais supprimer les lignes d'audit liées à l'utilisateur supprimé.

---

## 7. Données autorisées et exclues

| Donnée | Création | Modification | Suppression |
|---|---|---|---|
| `user_id` (`subject_id`) | autorisé | autorisé | autorisé |
| `name` | autorisé | avant/après si modifié | autorisé |
| `email` | autorisé | avant/après si modifié | autorisé |
| `email_verified_at` | autorisé | si modifié | non |
| `password` / hash | **exclu** | **exclu** | **exclu** |
| `remember_token` | **exclu** | **exclu** | **exclu** |
| Jetons, cookies, session, credentials | **exclus** | **exclus** | **exclus** |

**Fait** : `config/audit.php` contient déjà une blacklist de clés et quatre motifs larges couvrant `password`, `token`, `secret`, `iban`, `nir`, `ssn`, `medical`, `cookies`, `session`.

---

## 8. Transactions et rollback

**Fait** : l'acceptation d'invitation exécute la création utilisateur, la vérification d'e-mail, l'attribution du rôle, la consommation de l'invitation et les trois écritures d'audit dans un seul `DB::transaction()`.

**Fait** : `tests/Feature/Audit/AuditTransactionTest.php` vérifie qu'un rollback annule la ligne d'audit, et `tests/Feature/Audit/AuditInvitationInstrumentationTest.php` vérifie qu'une commande d'invitation en échec n'écrit aucun événement.

**Déduction** : une opération échouée ne peut pas produire un événement de réussite ; un même succès ne produit pas deux fois `user.created`.

---

## 9. Tests

### 9.1 Test renforcé

**Fichier** : `tests/Feature/Audit/AuditInvitationInstrumentationTest.php`
**Test** : `records user creation, role assignment and invitation consumption once`

Assertions ajoutées :

- `log_name = audit` et `event = user.created` ;
- `subject_id` = identifiant de l'utilisateur créé ;
- `causer_type` et `causer_id` nuls (aucun acteur humain fictif) ;
- `old_values` vide ;
- `name = Alice Admin` et `email = admin@example.com` exactement ;
- `password` absent des changements ;
- `properties.origin = system`, `source = http`, `channel = administrator_invitation`, `initiated_by = invitation_recipient` ;
- absence de `password`, `password_confirmation`, `remember_token`, `token`, `token_hash`, `secret` dans `properties` ;
- une seule ligne `user.created`.

### 9.2 Résultats

| Commande | Résultat |
|---|---|
| `php artisan test tests/Feature/Audit/AuditInvitationInstrumentationTest.php` | **6 tests, 6 réussis, 85 assertions** |

### 9.3 Tests non exécutés

- `user.updated` et `user.deleted` : déjà couverts au niveau API par `AuditUserInstrumentationTest`, non modifiés et non exécutés (aucun code concerné).
- Suite globale : volontairement non exécutée (règle permanente).

---

## 10. Limites et points ouverts

| # | Limite | Nature | Suite |
|---|---|---|---|
| 1 | `user.updated` sans producteur | Fait | à instrumenter au premier point d'entrée de modification |
| 2 | `user.deleted` sans producteur | Fait | à instrumenter au premier point d'entrée de suppression |
| 3 | Aucune colonne d'état utilisateur | Fait | une désactivation nécessiterait un modèle dédié |
| 4 | Aucun événement de changement de mot de passe | Déduction | décision ultérieure, hors 2A |
| 5 | `email_verified_at` journalisé à la création | Fait | conservé pour ne pas modifier un comportement validé |
| 6 | L'utilisateur ne peut pas encore être modifié par un administrateur | Fait | dépend d'un futur écran et des permissions `users.update` / `users.delete` |

---

## 11. Références

- `app/Actions/Administration/AcceptAdministratorInvitation.php`
- `app/Http/Controllers/Auth/AcceptAdministratorInvitationController.php`
- `app/Models/User.php`
- `app/Services/Audit/AuditManager.php`
- `app/Services/Audit/AuditSanitizer.php`
- `app/Services/Audit/AuditContext.php`
- `app/Enums/AuditEventType.php`
- `config/audit.php`
- `tests/Feature/Audit/AuditInvitationInstrumentationTest.php`
- `tests/Feature/Audit/AuditUserInstrumentationTest.php`
- `tests/Feature/Audit/AuditTransactionTest.php`
- `docs/business-rules/bp-192-audit-business-mapping.md`
- `docs/business-rules/bp-192-audit-architecture-decisions.md`
