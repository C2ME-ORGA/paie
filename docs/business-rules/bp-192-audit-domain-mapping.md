# BP-192 — Lot 2C — Cartographie des domaines métier et préparation de l'instrumentation

- **Ticket** : BP-192, Lot 2C.
- **Nature** : analyse documentaire. Aucun code, migration, configuration, permission, test ou interface modifié.
- **Date** : 2026-11-08.
- **Branche** : `develop`.
- **HEAD** : `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` — `[BP-191] Sécuriser le RBAC et l’initialisation administrateur (#9)`.
- **Documents amont** : `bp-192-audit-business-mapping.md`, `bp-192-audit-architecture-decisions.md`, `bp-192-audit-user-lifecycle.md`, `bp-192-audit-rbac.md`, `bp-192-audit-foundation.md`, `bp-192-audit-implementation.md`, `bp-192-audit-instrumentation.md`, `bp-192-audit-filament.md`, `bp-192-audit-consolidation.md`, `bp-192-audit-package-assessment.md`.

Conventions : **Fait** = constaté dans le code ; **Déduction** = conséquence technique ; **Recommandation** = proposition non implémentée.

---

## 1. État initial du dépôt

| Élément | Valeur |
|---|---|
| Branche | `develop` |
| HEAD | `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` |
| Modifications préexistantes | 56 entrées des lots 0 → 1E.3 |
| Entrées supplémentaires antérieures | `AGENTS.md`, 4 rapports BP-192 non suivis, 2 tests d'audit modifiés (2A, 2B) |
| Total `git status` avant ce lot | 61 entrées |
| Fichiers non suivis antérieurs | `bp-192-audit-architecture-decisions.md`, `bp-192-audit-business-mapping.md`, `bp-192-audit-rbac.md`, `bp-192-audit-user-lifecycle.md` |
| Tests applicatifs | 4 fichiers d'audit modifiés ou ajoutés depuis le HEAD |

**Fait** : les modifications des lots précédents sont intactes. Ce lot n'en modifie aucune.

---

## 2. Synthèse des domaines métier présents

**Fait** : l'application ne contient **aucun domaine RH, paie ou BTP**. Les seuls domaines implémentés sont transverses et administratifs.

| Domaine | État réel | Preuves |
|---|---|---|
| Administration et utilisateurs | **partiel** : création uniquement | `AcceptAdministratorInvitation::handle()` |
| Amorçage administrateur (invitations) | **opérationnel** | `InviteAdministrator`, `AcceptAdministratorInvitation`, `batipaie:admin-invite` |
| RBAC (rôles et permissions) | **opérationnel** en synchronisation ; **partiel** en attribution (bootstrap uniquement) | `RbacSynchronizer`, `RbacMatrix`, `PermissionName` |
| Authentification et session | **opérationnel** | `AuditAuthenticationEvents`, Filament, Sanctum |
| Journal d'audit | **opérationnel** en écriture ; lecture seule en interface | `AuditManager`, `audit.view` |
| Gestion documentaire | **absent** | aucun modèle, migration, disque applicatif dédié |
| Importations et exports | **absent** | aucun job, commande d'import, action d'export active |
| Paramétrage général | **structure technique seule** | `config/administration.php` ne contient que la durée d'expiration d'invitation |
| Traitements automatisés | **structure technique seule** | tables `jobs`, `job_batches`, `failed_jobs` sans producteur applicatif |

**Déduction** : le « périmètre métier » reste limité à l'administration de la plateforme. Aucun événement de type `employee.*`, `contract.*`, `leave.*` ou `payroll.*` ne peut être fondé sur du code existant.

---

## 3. Domaines absents ou incomplets

### 3.1 Absents du code

| Domaine | Documentation existante | Code |
|---|---|---|
| Salariés | `docs/business-rules/employees-and-contracts/rules.md` (SAL-CON-001 à 015) | absent |
| Contrats et avenants | idem | absent |
| Établissements et affectations | non documenté | absent |
| Convention collective et classifications | `docs/business-rules/README.md` (« À créer ») | absent |
| Temps de travail et présences | idem | absent |
| Congés et absences | idem | absent |
| Éléments variables de paie | idem | absent |
| Paramètres de paie | idem | absent |
| Bulletins de paie | idem | absent |
| Déclarations sociales et CIBTP | idem | absent |
| Frais professionnels et déplacements BTP | idem | absent |
| Chantiers | idem | absent |
| Portail salarié | non documenté | absent |
| Reporting et exports métier | non documenté | absent |

### 3.2 Incomplets

| Domaine | Ce qui existe | Ce qui manque |
|---|---|---|
| Utilisateurs | création, authentification, rôles | modification, suppression, désactivation, liste |
| RBAC | synchronisation par matrice, attribution du rôle administrateur | écran d'attribution/retrait, permission `users.assign_roles`, `roles.manage` |
| Audit | socle complet, 6 événements réellement produits | aucun événement métier |

### 3.3 Structure technique sans fonctionnalité

**Fait** :

- `jobs`, `job_batches`, `failed_jobs`, `cache`, `sessions`, `password_reset_tokens`, `personal_access_tokens` existent sans producteur applicatif ;
- `AdminPanelProvider` découvre des répertoires `app/Filament/Resources`, `Pages`, `Widgets` qui n'existent pas ;
- `filament-activity-log` fournit des widgets, pages et actions désactivés (`widgets.enabled = false`, `pages.user_activities.enabled = false`, actions de mutation `false`).

**Déduction** : leur présence ne doit pas être interprétée comme un domaine fonctionnel.

---

## 4. Cartographie technique détaillée

### 4.1 Modèles et relations

| Modèle | Table | Relations | Écriture applicative |
|---|---|---|---|
| `App\Models\User` | `users` | `roles()` via Spatie (pivot `model_has_roles`), `tokens()` Sanctum, `notifications()` | création, `assignRole` |
| `App\Models\AdministratorInvitation` | `administrator_invitations` | aucune | création, consommation, révocation |
| `Spatie\Permission\Models\Role` | `roles` | `permissions()` (pivot `role_has_permissions`), `users()` | `firstOrCreate`, `syncPermissions` |
| `Spatie\Permission\Models\Permission` | `permissions` | `roles()` | `firstOrCreate` |
| `Spatie\Activitylog\Models\Activity` | `activity_log` | morph `subject`, `causer` | via `SpatieAuditLogger` uniquement |

**Fait** : aucune relation métier (salarié → contrat, contrat → établissement, etc.) n'existe.

### 4.2 Points d'écriture réels

| # | Domaine | Modèle | Opération | Fichier / méthode | Couverture audit |
|---|---|---|---|---|---|
| 1 | Identité | `User` | `create()` | `AcceptAdministratorInvitation::handle()` l. 75 | `user.created` |
| 2 | Identité | `User` | `save()` (`email_verified_at`) | idem l. 81 | agrégé dans `user.created` |
| 3 | Identité / RBAC | pivot `model_has_roles` | `assignRole()` | idem l. 82 | `rbac.role_assigned` |
| 4 | Administration | `AdministratorInvitation` | `create()` | `InviteAdministrator::handle()` l. 109 | `administrator_invitation.created` |
| 5 | Administration | `AdministratorInvitation` | `save()` (`revoked_at`) | idem l. 77 | `…revoked` |
| 6 | Administration | `AdministratorInvitation` | `save()` (`revoked_at`) | idem l. 141 | `…delivery_failed` + `…revoked` |
| 7 | Administration | `AdministratorInvitation` | `save()` (`consumed_at`) | `AcceptAdministratorInvitation::handle()` l. 84 | `…consumed` |
| 8 | RBAC | `Role` | `firstOrCreate()` | `RbacSynchronizer::synchronize()` l. 37 | non couverte (décision) |
| 9 | RBAC | `Permission` | `firstOrCreate()` | `RbacSynchronizer::ensurePermissions()` l. 120 | non couverte (décision) |
| 10 | RBAC | pivot `role_has_permissions` | `syncPermissions()` | `RbacSynchronizer::synchronize()` l. 51 | diff `rbac.permission_*` |
| 11 | Audit | `Activity` | `log()` | `SpatieAuditLogger::record()` l. 80 | journal lui-même |
| 12 | Authentification | `sessions` | écriture framework | Filament / Sanctum | non auditée (hors périmètre) |

**Fait** : aucun `increment`, `decrement`, `upsert`, `updateOrCreate`, `forceDelete`, `restore`, `attach`, `detach`, `syncWithoutDetaching`, `DB::table`, `DB::statement`, `DB::update` ou `DB::delete` dans `app/`.

**Fait** : aucun `SoftDeletes` sur les modèles applicatifs.

### 4.3 Points d'entrée techniques

| Type | Contenu réel |
|---|---|
| Routes web | `GET /`, `GET|POST /admin/invitation/{token}` |
| Routes Filament | `GET /admin`, `GET /admin/activity-logs`, `GET /admin/activity-logs/{record}`, login/logout |
| Routes API | `GET /api/user` (Sanctum) |
| Contrôleurs applicatifs | `AcceptAdministratorInvitationController` |
| Form Requests | `AcceptAdministratorInvitationRequest` |
| Middleware applicatif | `AdministratorInvitationSecurityHeaders` |
| Composants Livewire applicatifs | aucun |
| Ressources / pages / widgets Filament applicatifs | aucun |
| Commandes Artisan applicatives | `batipaie:admin-invite` |
| Jobs | aucun |
| Listeners | `AuditAuthenticationEvents` (`Login`, `Logout`, `Failed`) |
| Seeders | `DatabaseSeeder` → `RbacSeeder` |
| Factories | `UserFactory`, `AdministratorInvitationFactory` |
| Notifications | `AdministratorInvitationNotification` (mail) |
| Événements Laravel applicatifs | aucun événement métier |

### 4.4 Schéma de dépendances

```text
[Identité / User] ──< model_has_roles >── [RBAC Role]
       │                                     │
       │                                     └──< role_has_permissions >── [RBAC Permission]
       │
       └── créé par [Amorçage administrateur / Invitation]

[Audit] observe : User, Invitation, Role, Permission, Activity
[Authentification] alimente : Audit (login, logout, failed)
```

Domaines futurs et dépendances prévisibles (aucun n'existe dans le code) :

```text
[Établissement] → [Salarié] → [Contrat] → [Affectation chantier]
                        │           │
                        │           └→ [Temps / Absences / Congés]
                        │
                        └→ [Éléments variables] → [Période de paie] → [Bulletin]
                                                          │
                                                          └→ [Déclarations / CIBTP]
[Dossier documentaire] attaché à Salarié, Contrat, Bulletin
```

**Déduction** : l'ordre d'instrumentation devra suivre cet ordre de dépendances, sinon les événements porteront sur des entités dont les parents ne sont pas encore auditables.

---

## 5. Matrice des opérations et événements potentiels

### 5.1 Opérations réelles (retenues)

| Domaine | Opération | Point d'entrée réel | Événement | Avant/après | Sensibilité | Transaction | Priorité |
|---|---|---|---|---|---|---|---|
| Identité | création utilisateur | `POST /admin/invitation/{token}` | `user.created` | `name`, `email`, `email_verified_at` | moyenne | oui | **déjà fait** |
| Identité | modification utilisateur | **aucun** | `user.updated` | `name`, `email` si modifiés | moyenne | à définir | P1 (conditionnelle) |
| Identité | suppression utilisateur | **aucun** | `user.deleted` | `name`, `email` | moyenne | à définir | P1 (conditionnelle) |
| Administration | création d'invitation | `batipaie:admin-invite` | `administrator_invitation.created` | `name`, `email`, `expires_at` | moyenne | oui | déjà fait |
| Administration | consommation | `POST /admin/invitation/{token}` | `…consumed` | `consumed_at` | faible | oui | déjà fait |
| Administration | révocation | commande / échec mail | `…revoked` | `revoked_at` | faible | oui | déjà fait |
| Administration | échec de livraison | commande | `…delivery_failed` | aucune valeur | faible | partielle | déjà fait |
| RBAC | attribution de rôle | acceptation d'invitation | `rbac.role_assigned` | aucune valeur, métadonnées | moyenne | oui | **déjà fait** |
| RBAC | retrait de rôle | **aucun** | `rbac.role_removed` | aucune valeur, métadonnées | moyenne | à définir | P1 (conditionnelle) |
| RBAC | permission ajoutée | `RbacSynchronizer` | `rbac.permission_assigned` | aucune valeur, métadonnées | faible | oui | **déjà fait** |
| RBAC | permission retirée | `RbacSynchronizer` | `rbac.permission_revoked` | aucune valeur, métadonnées | faible | oui | **déjà fait** |
| Authentification | connexion, déconnexion, échec | listeners Laravel | `security.*` | aucune valeur | moyenne | non applicable | déjà fait |

### 5.2 Opérations inexistantes — aucun événement retenu

| Domaine | Exemples d'événements | Statut |
|---|---|---|
| Salariés | `employee.created`, `employee.updated`, `employee.deleted` | **non retenus** : aucun modèle, aucune table, aucun point d'entrée |
| Contrats | `contract.created`, `contract.updated`, `contract.terminated` | idem |
| Congés | `leave.requested`, `leave.approved`, `leave.rejected` | idem |
| Paie | `payroll.period_opened`, `payroll.period_closed` | idem |
| Bulletins | `payslip.generated`, `payslip.published` | idem |
| Établissements, chantiers, frais, documents, CIBTP | `establishment.*`, `site.*`, `expense.*`, `document.*`, `cibtp.*` | idem |

**Déduction** : les inscrire au catalogue `AuditEventType` créerait des contrats sans producteur, un faux sentiment de couverture et un risque de divergence avec les modèles futurs.

### 5.3 Gabarits conditionnels (non retenus)

Ces gabarits ne sont pas des décisions ; ils indiquent seulement la convention à appliquer **après** livraison du domaine.

| Domaine futur | Convention | Champs probables (à arbitrer) |
|---|---|---|
| Salarié | `employee.created/updated/deleted` | matricule, nom, prénom, statut, dates d'entrée/sortie |
| Contrat | `contract.created/updated/terminated` | type, dates, classification, motif de rupture |
| Temps / congés | `leave.requested/approved/rejected` | type, période, statut |
| Variables de paie | `payroll_variable.created/updated/deleted` | code, période, quantité, montant (à arbitrer) |
| Période de paie | `payroll_period.opened/closed` | période, statut, date de clôture |
| Bulletin | `payslip.generated/published` | période, identifiant, statut |
| Document | `document.uploaded/deleted` | type, identifiant, empreinte (jamais le contenu) |

---

## 6. Analyse de confidentialité

### 6.1 Données réellement présentes

| Donnée | Emplacement actuel | Classe |
|---|---|---|
| Nom, e-mail | `users`, `administrator_invitations` | autorisée sous réserve |
| Identifiants techniques | `id`, `subject_id`, `correlation_id` | autorisée |
| Rôles, permissions, garde | `roles`, `permissions` | autorisée |
| Dates techniques | `created_at`, `expires_at`, `consumed_at`, `revoked_at` | autorisée |
| Mot de passe (hash) | `users.password` | **strictement interdite** |
| `remember_token` | `users.remember_token` | **strictement interdite** |
| Jeton d'invitation brut | mémoire, URL, e-mail | **strictement interdite** |
| `token_hash` | `administrator_invitations.token_hash` | **strictement interdite** |
| Jeton Sanctum | `personal_access_tokens.token` | **strictement interdite** |
| Session | `sessions.payload` | **strictement interdite** |
| Cookies | requête / réponse | **strictement interdite** |

### 6.2 Données futures — politique à appliquer

| Catégorie | Exemples | Règle |
|---|---|---|
| **Autorisée sous réserve de nécessité métier** | matricule, identifiant de contrat, type de contrat, statut, date d'effet, code de variable, identifiant de période, identifiant de bulletin | justifier chaque champ dans `config/audit.php` ; ne jamais transmettre un champ « parce qu'il est disponible » |
| **Protection renforcée ou exclusion** | rémunération, montants, coordonnées bancaires, IBAN, BIC, NIR, données d'absence, données de santé, contenu de contrat | montants et identifiants bancaires : **exclus par défaut** ; toute exception doit être arbitrée et documentée |
| **Strictement interdite** | mots de passe, hash, tokens, cookies, sessions, credentials, secrets, clés API | jamais, y compris dans `properties` ou `metadata` |

**Déduction** : la blacklist actuelle de `config/audit.php` couvre déjà `password`, `token`, `secret`, `iban`, `bic`, `nir`, `ssn`, `medical`, `health`, `diagnosis`, `sick_leave`, `card_number`, `cvv`. Elle devra être complétée pour les domaines futurs (par exemple `salary`, `gross`, `net`, `bank`, `at_iban`, `social_security`).

**Fait** : l'adresse e-mail a été retirée de l'allowlist RBAC au Lot 2B. **Recommandation** : conserver cette règle pour tous les événements dont le `subject_id` suffit.

---

## 7. Risques et limites techniques

### 7.1 Limites du socle face aux domaines futurs

| # | Limite | Détail | Impact |
|---|---|---|---|
| 1 | Sujet obligatoirement un modèle Eloquent | `AuditLogger::record()` exige un `Illuminate\Database\Eloquent\Model` avec clé | un événement de lot d'import ou de traitement global sans modèle ne peut pas être journalisé |
| 2 | Catalogue fermé | `AuditEventType` est un enum : chaque événement exige un déploiement | pas d'événements dynamiques par configuration |
| 3 | Allowlist par couple classe/événement | `config/audit.php` doit être complété pour chaque domaine | oubli = aucune valeur journalisée (silencieux) |
| 4 | `reason` en allowlist | toute nouvelle justification doit être ajoutée | sinon la raison est silencieusement supprimée |
| 5 | Pas de clé d'idempotence | `AuditManager` ne déduplique pas | un job rejoué peut dupliquer un événement |
| 6 | Pas de `batch_uuid` | colonne absente | corrélation de lot uniquement via `properties.correlation_id` |
| 7 | Signature HMAC re-signable | `filament-activity-log:backfill` recalcule `integrity_hash` | un opérateur avec accès console peut blanchir une modification |
| 8 | Pas de détection de suppression | HMAC individuel | une purge reste indétectable sans ancrage externe |
| 9 | Conservation non arbitrée | `clean_after_days = null` | conservation illimitée ou purge non conforme |
| 10 | Garde HMAC limitée à `production` | `staging` peut utiliser `APP_KEY` | signatures non séparées hors production |
| 11 | Pas d'isolation par établissement | décision non tranchée | accès potentiellement trop large aux données de paie |
| 12 | Garde d'encodage partielle | `auditSourceFiles()` limité | libellés corrompus non détectés |
| 13 | Volumétrie | 1 ligne par changement | à surveiller pour les imports et la paie |
| 14 | `activity_log` sans clé étrangère | sujets supprimés conservés par identifiant | comportement voulu, à ne pas « corriger » |

### 7.2 Risques de conception

| Risque | Description | Prévention |
|---|---|---|
| Journaliser chaque champ | bruit, données personnelles inutiles | allowlist métier par événement |
| Observer global | duplication, couplage | instrumentation explicite uniquement |
| Événements Spatie Permission | doublons sur `syncPermissions` | `events_enabled = false` |
| Audit hors transaction | trace d'une opération annulée | écriture dans la même transaction |
| Événement unique pour un lot | perte de granularité | une ligne par entité + corrélation |
| Événement par ligne pour un import massif | volume | seuil et stratégie de lot à arbitrer |
| Montants de paie en clair | donnée personnelle sensible | exclusion par défaut, exception arbitrée |
| Suppression d'un sujet | lien mort | conserver `subject_id`, jamais supprimer l'audit |

---

## 8. Priorisation des prochains lots

**Fait** : aucun domaine métier n'étant livré, la priorisation porte sur l'achèvement du socle administratif, puis sur les domaines au fur et à mesure de leur disponibilité.

| Rang | Lot proposé | Objectif | Dépendance | Complexité |
|---|---|---|---|---|
| 1 | **2D — Achèvement du cycle de vie utilisateur** | instrumenter `user.updated` et `user.deleted` lorsque l'écran d'administration existe | écran + permissions `users.update` / `users.delete` (hors audit) | moyenne |
| 2 | **2E — Administration RBAC** | instrumenter `rbac.role_removed` et toute attribution réelle | écran d'attribution + permissions `users.assign_roles` / `roles.manage` | moyenne |
| 3 | **2F — Salariés et contrats** | instrumenter le dossier salarié et le cycle contractuel | modèles `Employee`, `Contract`, règles SAL-CON validées | élevée |
| 4 | **2G — Temps, absences, congés** | instrumenter demandes et décisions | modèles de saisie et de validation | élevée |
| 5 | **2H — Variables, frais BTP, chantiers** | instrumenter les éléments variables et les frais | modèles dédiés, règles BTP validées | élevée |
| 6 | **2I — Paie, bulletins, déclarations** | instrumenter périodes, clôtures, bulletins | moteur de paie et périodes | très élevée |
| 7 | **2J — Documents et exports** | instrumenter dépôt/suppression de document et exports | stockage privé, policies | moyenne à élevée |

### 8.1 Détail des lots 2D et 2E

**2D — cycle de vie utilisateur**

- Opérations réelles à tracer : aucune aujourd'hui.
- Événements : `user.updated`, `user.deleted`.
- Composants : nouvelle action ou ressource d'administration, `config/audit.php`.
- Risques : mot de passe journalisé, suppression du dernier administrateur, causer orphelin.
- Tests : diff réel, aucun événement sans changement, exclusion des secrets, rollback.

**2E — administration RBAC**

- Opérations réelles à tracer : retrait de rôle ; attribution hors bootstrap.
- Événements : `rbac.role_removed`, `rbac.role_assigned`.
- Composants : écran/action de gestion des rôles, `config/audit.php`.
- Risques : élévation de privilèges, doublons Spatie, dernier administrateur.
- Tests : attribution/retrait, absence de changement, absence de doublon, permissions refusées.

### 8.2 Lots conditionnels

**Recommandation** : ne pas ouvrir 2F avant que les modèles et les règles SAL-CON soient livrés et validés. Instrumenter un domaine naissant coûte moins cher que rétro-instrumenter un domaine en production, mais instrumenter un domaine instable produit des événements à refaire.

---

## 9. Recommandation sur le découpage des travaux

1. **Ne pas créer de lot d'audit purement documentaire supplémentaire** : la présente cartographie clôt la phase d'analyse.
2. **Traiter 2D et 2E comme des lots mixtes** (fonctionnalité + audit) et non comme des lots d'audit isolés, puisqu'ils dépendent d'écrans qui n'existent pas.
3. **Livrer 2F en premier domaine métier**, dès que les modèles salarié/contrat existent : c'est la racine des dépendances RH.
4. **Instrumenter chaque domaine dès sa première mise en production**, avec un événement par opération métier significative, jamais un événement par champ.
5. **Interdire les observateurs globaux** et maintenir l'instrumentation explicite dans les actions.
6. **Prévoir, pour chaque lot**, la mise à jour simultanée de : `AuditEventType`, `config/audit.php` (allowlist, raisons, blacklist), les tests, et la documentation.
7. **Arbitrer avant 2F** : accès par établissement, données de paie journalisables, conservation.

---

## 10. Conclusion et décisions restant à prendre

**Conclusion** : la cartographie confirme l'absence totale de domaine RH/paie dans le code. Le socle d'audit est prêt à les accueillir sans modification structurelle, mais quatre points doivent être arbitrés avant l'instrumentation métier.

### Décisions à prendre

1. **Périmètre d'accès** : la mono-entreprise étant acquise, un utilisateur doit-il être restreint à certains établissements pour les données de paie ?
2. **Données de paie journalisables** : montants, dates d'effet, identifiants de bulletin — lesquels sont autorisés, lesquels restent exclus ?
3. **Conservation et intégrité** : niveau de preuve attendu, durée par catégorie, recours à un ancrage externe.
4. **Ordre de livraison** : confirmer que 2D et 2E sont conditionnés à la livraison des écrans d'administration, et que 2F ouvre les domaines métier.
5. **Volume** : stratégie pour les imports et les traitements de masse (une ligne par entité, seuil, corrélation).

### Verdict

**VALIDÉ AVEC RÉSERVES.**

La cartographie de l'existant est complète et sourcée. Les réserves portent sur l'absence de domaine métier, qui rend impossible toute planification détaillée au-delà de 2D/2E, et sur les quatre décisions transverses ci-dessus.

Aucune instrumentation n'a été ajoutée. Le Lot 2D n'a pas été commencé.
