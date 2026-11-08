# BP-192 — Lot 1C : instrumentation du socle applicatif

**Ticket Jira :** BP-192 — Implémenter le journal d’audit métier complet  
**Lot :** 1C — instrumentation des événements existants  
**Date :** 8 novembre 2026  
**Branche :** `develop`  
**Commit de départ :** `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`  
**Documents de référence :**

- [`bp-192-audit-package-assessment.md`](bp-192-audit-package-assessment.md) ;
- [`bp-192-audit-foundation.md`](bp-192-audit-foundation.md) ;
- [`bp-192-audit-implementation.md`](bp-192-audit-implementation.md).

> Ce document décrit le Lot 1C à sa date. Les corrections du Lot 1E
> (`initiated_by`, `administrator_invitation.delivery_failed`, classification
> RBAC en sécurité et validation des métadonnées) sont documentées dans
> [`bp-192-audit-consolidation.md`](bp-192-audit-consolidation.md).

---

## 1. Objet

Le Lot 1C branche la couche métier du Lot 1B sur les points d’entrée réels du socle applicatif :

- création d’utilisateur via l’acceptation d’une invitation administrateur ;
- cycle de vie des invitations administrateur ;
- attribution/retrait effectifs de permissions lors de la synchronisation RBAC ;
- attribution de rôle administrateur lors du bootstrap ;
- connexion, déconnexion et échec de connexion.

Aucun futur domaine métier n’a été instrumenté. Aucun observer global ni journalisation automatique de modèle n’a été ajouté.

---

## 2. Événements instrumentés

| Événement | Point d’entrée | Origine | Déclenchement |
|---|---|---|---|
| `user.created` | `AcceptAdministratorInvitation` | Système | Création effective de l’utilisateur administrateur |
| `administrator_invitation.created` | `InviteAdministrator` / `batipaie:admin-invite` | Système | Création d’une invitation |
| `administrator_invitation.consumed` | `AcceptAdministratorInvitation` | Système | Consommation d’une invitation |
| `administrator_invitation.revoked` | `InviteAdministrator` | Système | Révocation d’une invitation en attente ou après échec d’envoi |
| `rbac.role_assigned` | `AcceptAdministratorInvitation` | Système | Attribution du rôle administrateur au nouvel utilisateur |
| `rbac.permission_assigned` | `RbacSynchronizer` | Système ou acteur courant | Permission effectivement ajoutée à un rôle |
| `rbac.permission_revoked` | `RbacSynchronizer` | Système ou acteur courant | Permission effectivement retirée d’un rôle |
| `security.login_succeeded` | `AuditAuthenticationEvents` | Utilisateur | Connexion réussie |
| `security.login_failed` | `AuditAuthenticationEvents` | Système | Échec de connexion sur un compte connu |
| `security.logout` | `AuditAuthenticationEvents` | Utilisateur | Déconnexion |

### 2.1 Événements préparés mais sans point d’entrée réel

| Événement | État |
|---|---|
| `user.updated` | Aucun écran/service de modification d’utilisateur n’existe dans le dépôt. L’événement est défini, filtré et testé au niveau de l’API d’audit ; il devra être appelé lors de l’ajout d’un point d’entrée de modification. |
| `user.deleted` | Aucune suppression d’utilisateur n’existe. L’événement est défini, filtré et testé ; il devra être appelé lors de l’ajout d’un point d’entrée de suppression. |
| `rbac.role_removed` | Aucun retrait de rôle n’existe hors tests. L’événement est défini et testé ; il devra être appelé lors de l’ajout d’une gestion des rôles. |

Ces absences sont volontaires : créer un écran ou un observer uniquement pour produire un événement serait hors périmètre.

---

## 3. Points d’entrée instrumentés

### 3.1 `AcceptAdministratorInvitation`

Fichier : `app/Actions/Administration/AcceptAdministratorInvitation.php`

Après création de l’utilisateur, vérification de l’adresse et attribution du rôle, la même transaction écrit :

1. `user.created` avec `name`, `email`, `email_verified_at` ;
2. `rbac.role_assigned` avec les métadonnées `role=administrator`, `guard=web` ;
3. `administrator_invitation.consumed` avec `consumed_at`.

Le contexte est `AuditContext::system()` avec :

- `source=http` ;
- `channel=administrator_invitation` ;
- une corrélation UUID commune aux trois événements ;
- une justification métier.

Aucun acteur humain n’est attribué : l’invité n’est pas authentifié au moment de l’acceptation.

### 3.2 `InviteAdministrator` et `batipaie:admin-invite`

Fichier : `app/Actions/Administration/InviteAdministrator.php`

- `administrator_invitation.created` est écrit dans la transaction de création.
- Les invitations en attente devenues inutilisables sont révoquées et tracées par `administrator_invitation.revoked`.
- Un échec d’envoi du mail révoque l’invitation et écrit un second `administrator_invitation.revoked`.
- Le contexte est `AuditContext::system()` avec `source=console`, `channel=administrator_invitation`, `command=batipaie:admin-invite` et une corrélation commune à l’opération.

La commande ne produit pas d’événement de complétion séparé : l’événement métier `administrator_invitation.created` couvre déjà l’opération et évite un doublon technique.

### 3.3 `RbacSynchronizer`

Fichier : `app/Support/Rbac/RbacSynchronizer.php`

Avant chaque `syncPermissions()`, le synchroniseur compare :

- les permissions actuellement attachées au rôle ;
- les permissions attendues par `RbacMatrix`.

Seules les différences effectives produisent :

- `rbac.permission_assigned` ;
- `rbac.permission_revoked`.

Métadonnées : `role`, `permission`, `guard`, `source`, `channel=rbac_synchronization`, `reason`.

Une synchronisation sans changement ne produit aucun événement. La création des lignes `Role` et `Permission` n’est pas tracée séparément : l’événement utile est l’attribution effective de la permission au rôle.

Le contexte est celui du manager courant : en seeder/console, il est système ; si une future interface authentifiée déclenche la synchronisation, l’acteur réel est conservé.

### 3.4 Authentification

Fichier : `app/Listeners/AuditAuthenticationEvents.php`  
Enregistrement : `AppServiceProvider::registerAuditListeners()`.

Écoute des événements Laravel :

- `Illuminate\Auth\Events\Login` → `security.login_succeeded` ;
- `Illuminate\Auth\Events\Logout` → `security.logout` ;
- `Illuminate\Auth\Events\Failed` → `security.login_failed`.

Règles :

- aucun mot de passe, credential ou payload de connexion n’est lu ou stocké ;
- la connexion réussie et la déconnexion sont attribuées à l’utilisateur réel ;
- l’échec est attribué à l’origine système, jamais à l’utilisateur ciblé ;
- un échec sur un compte inconnu n’est pas journalisé, faute de sujet persistant et pour éviter de transformer le journal en outil d’énumération de comptes ;
- les métadonnées sont limitées à `source=auth`, `channel=session`, `guard`.

Les événements `Attempting` ne sont pas écoutés car ils contiennent les credentials.

---

## 4. Champs autorisés

Configuration : `config/audit.php`.

| Ressource | Événement | Champs |
|---|---|---|
| `User` | `user.created` | `name`, `email`, `email_verified_at` |
| `User` | `user.updated` | `name`, `email`, `email_verified_at` |
| `User` | `user.deleted` | `name`, `email` |
| `User` | `rbac.role_assigned` | `name`, `email` |
| `User` | `rbac.role_removed` | `name`, `email` |
| `AdministratorInvitation` | `administrator_invitation.created` | `name`, `email`, `expires_at` |
| `AdministratorInvitation` | `administrator_invitation.consumed` | `consumed_at` |
| `AdministratorInvitation` | `administrator_invitation.revoked` | `revoked_at` |

Les événements RBAC de permission portent sur le modèle `Role` et n’exposent aucun attribut : la sémantique est portée par les métadonnées `role` et `permission`.

---

## 5. Métadonnées autorisées

Liste globale : `source`, `channel`, `command`, `job`, `request_id`, `correlation_id`, `origin`, `reason`, `actor_type`, `guard`, `role`, `permission`.

Aucun email, token, mot de passe ou payload d’authentification n’est ajouté aux métadonnées.

---

## 6. Exclusions sensibles

Le sanitizer du Lot 1B reste la barrière d’écriture :

- `password`, `password_confirmation`, `current_password`, `new_password` ;
- `remember_token`, `token`, `token_hash`, `access_token`, `refresh_token`, `api_token` ;
- `secret`, `api_key`, `private_key` ;
- `authorization`, `cookie`, `session`, credentials ;
- IBAN, BIC, comptes bancaires, NIR, sécurité sociale ;
- données médicales, carte bancaire, CVV/CVC, PIN.

Test explicite : les tokens d’invitation (valeur brute et hash) ne sont jamais présents dans les événements d’invitation.

---

## 7. Transactions

- Les événements de création d’invitation, de consommation, de création d’utilisateur, d’attribution de rôle et de synchronisation RBAC sont écrits dans la transaction métier correspondante.
- Un rollback annule l’opération et ses traces.
- La révocation consécutive à un échec d’envoi de mail intervient après le commit de la création ; elle est tracée séparément avec la même corrélation.
- Les tests de transaction du Lot 1B restent valides.

---

## 8. Doublons évités

- Aucun observer Eloquent global.
- Aucun trait `LogsActivity`.
- Aucun listener sur les événements Spatie Permission (`permission.events_enabled=false`) : les attributions/retraits sont calculés par diff dans `RbacSynchronizer`.
- Le changement de rôle n’est pas dupliqué en `user.updated`.
- La commande `batipaie:admin-invite` ne produit pas d’événement de complétion en plus de `administrator_invitation.created`.
- Une synchronisation RBAC sans changement ne produit aucun événement.
- Une acceptation d’invitation produit exactement trois événements fonctionnels, une seule fois.

---

## 9. Tests

### 9.1 Suite Audit

Commande :

```bash
php artisan test --filter=Audit
```

Résultat : **96 tests, 96 réussis, 320 assertions**.

Couverture d’instrumentation :

- création d’invitation via commande ;
- création, consommation et rôle lors de l’acceptation ;
- absence de doublon après une seconde acceptation ;
- absence d’événement en cas d’échec de validation ;
- révocation d’une invitation expirée ;
- révocation après échec d’envoi ;
- absence de token/hash dans les traces ;
- attribution/retrait effectif de permissions RBAC ;
- synchronisation RBAC sans changement ;
- rôle attribué/retiré au niveau de l’API d’audit ;
- création/modification/suppression d’utilisateur au niveau de l’API d’audit ;
- connexion réussie, déconnexion, échec sur compte connu ;
- absence de trace pour un compte inconnu ;
- absence d’acteur humain sur les événements système ;
- absence de doublons d’événements d’authentification.

### 9.2 Suite complète

Commande :

```bash
php artisan test
```

Résultat : **179 tests, 179 réussis, 661 assertions**.

### 9.3 Qualité

| Commande | Résultat |
|---|---|
| `vendor/bin/pint --test` | Réussi |
| `vendor/bin/phpstan analyse --no-progress` | Réussi, 0 erreur |
| `composer test` | Réussi |
| `composer validate --no-check-publish --strict` | Réussi |

---

## 10. Événements volontairement non instrumentés

| Élément | Raison |
|---|---|
| `user.updated` sur un chemin réel | Aucun écran/service de modification d’utilisateur dans le dépôt. |
| `user.deleted` sur un chemin réel | Aucune suppression d’utilisateur dans le dépôt. |
| `rbac.role_removed` sur un chemin réel | Aucun retrait de rôle dans le dépôt. |
| Création des lignes `Role` / `Permission` | Opération interne de bootstrap, sans décision métier supplémentaire ; les attributions effectives sont tracées. |
| `system.command_completed` | Éviter un doublon avec l’événement métier de la commande. |
| Changement de mot de passe | Aucun flux de changement de mot de passe dans l’application. |
| Vérification d’adresse électronique | `MustVerifyEmail` n’est pas activé. |
| Réinitialisation de mot de passe | Aucun flux de réinitialisation exposé. |
| Échec de connexion sur compte inconnu | Pas de sujet persistant ; risque d’énumération de comptes. |
| Consultations simples | Hors périmètre initial de BP-192. |
| Exports | Hors périmètre du Lot 1C. |

---

## 11. Limites

- Le journal HMAC du Lot 1A ne détecte toujours pas la suppression d’une ligne.
- Le logger accepte désormais un sujet supprimé qui conserve sa clé, afin de permettre `user.deleted` ; un sujet sans clé reste refusé.
- L’échec d’écriture d’audit est fail-closed : il peut faire échouer l’opération métier, notamment pendant la connexion. Ce choix privilégie la traçabilité.
- `User`, `AdministratorInvitation` et `RbacSynchronizer` sont les seuls domaines instrumentés.
- Aucun écran de gestion des utilisateurs ou des rôles n’existe encore ; les événements correspondants restent testés au niveau de l’API.
- Les permissions `audit.view` / `audit.export` et l’interface Filament restent au Lot 1D.
- La politique de conservation, la purge, le legal hold et l’ancrage externe restent hors périmètre.

---

## 12. Règles pour les lots suivants

1. Tout nouveau point d’entrée de modification/suppression d’utilisateur doit appeler `AuditManager` avec `UserUpdated` / `UserDeleted`.
2. Tout nouveau point d’entrée de retrait de rôle doit appeler `AuditManager` avec `RbacRoleRemoved`.
3. Toute nouvelle permission RBAC doit être ajoutée à `RbacMatrix` et bénéficiera automatiquement du diff du synchroniseur.
4. Ne jamais ajouter d’observer global ni de `LogsActivity` sur `User` sans validation explicite.
5. Ne jamais activer les événements Spatie Permission sans supprimer le diff du synchroniseur, sous peine de doublons.
6. Ne jamais journaliser de token, mot de passe, credential ou payload d’authentification.
