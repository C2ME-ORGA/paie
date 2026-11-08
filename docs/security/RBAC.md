# RBAC — rôles, permissions et accès au panel

Ce document décrit le socle RBAC mis en place dans le cadre de **BP-191, Lot 1A**.
Il complète `AGENTS.md`, `docs/development/WORKFLOW.md` et `docs/development/TESTING.md`.
L'initialisation du premier administrateur est décrite dans
[`ADMIN-BOOTSTRAP.md`](ADMIN-BOOTSTRAP.md) (BP-191, Lot 1B).

## 1. Principes

- Les permissions sont définies dans le code, jamais créées librement depuis une interface.
- Les permissions sont indépendantes des rôles : un rôle est un ensemble de permissions.
- Le refus est la valeur par défaut : aucune permission implicite, aucun wildcard.
- Les autorisations sont contrôlées côté serveur (permission, policy, `canAccess`, `canView`, `authorize`).
- Aucun `Gate::before` de type super-administrateur ne doit exister.
- L'Administrateur gère la plateforme ; il ne reçoit aucun accès métier implicite.
- Aucun compte, mot de passe ou secret par défaut n'est créé.
- Les permissions métier ne sont créées qu'avec le module qui les utilise.

## 2. Rôles de référence

| Code machine | Libellé affiché | Accès panel au Lot 1A |
|---|---|---|
| `administrator` | Administrateur | Oui, via `administration.panel.access` |
| `management` | Direction | Oui, via `administration.panel.access` |
| `payroll_manager` | Comptable / Gestionnaire de paie | Oui, via `administration.panel.access` |
| `hr_manager` | Responsable RH | Oui, via `administration.panel.access` |
| `employee` | Salarié | Non, aucune permission active |
| `site_manager` | Chef d'équipe / Responsable chantier | Oui, via `administration.panel.access` |
| `external_accountant` | Expert-comptable externe | Non, aucune permission active |

Les codes machine sont persistés dans Spatie et doivent rester stables. Les libellés sont
en français et peuvent évoluer sans casser le code.

Au Lot 1A, la seule permission active est `administration.panel.access`. Elle est accordée
aux rôles internes qui utiliseront le back-office. Le rôle Salarié n'y accède pas. Le rôle
Expert-comptable externe reste sans permission tant qu'un accès en lecture seule, limité à
des données et à une période explicites, n'est pas implémenté. Les permissions métier seront
ajoutées avec leurs modules et leurs policies.

## 3. Convention de nommage des permissions

Format : `module[.resource].action`.

- Le dernier segment est toujours l'action.
- Les modules simples peuvent utiliser deux segments ; les sous-ressources en utilisent trois.
- Les suffixes `_own` et `_team` expriment une portée, jamais une autorisation à eux seuls.
- Les noms ne doivent jamais reprendre une ability Laravel (`view`, `viewAny`, `create`,
  `update`, `delete`, etc.), car le `Gate::before` de Spatie accorde une ability du même nom
  à un utilisateur qui possède la permission correspondante.

Permission active au Lot 1A :

```text
administration.panel.access
```

## 4. Matrice active du Lot 1A

| Permission | Administrator | Management | Payroll manager | HR manager | Employee | Site manager | External accountant |
|---|---:|---:|---:|---:|---:|---:|---:|
| `administration.panel.access` | ✅ | ✅ | ✅ | ✅ | — | ✅ | — |

Cette matrice est la source de vérité dans `app/Support/Rbac/RbacMatrix.php`.
Toute permission accordée à un rôle géré en dehors de cette matrice est retirée lors de la
prochaine synchronisation. Les rôles et permissions non gérés par la matrice ne sont pas
supprimés.

## 5. Permissions différées

Les permissions suivantes sont prévues mais **ne sont pas créées** tant que la fonctionnalité
correspondante n'existe pas :

- administration des utilisateurs : `users.view`, `users.invite`, `users.update`,
  `users.assign_roles` ;
- administration des rôles : `roles.manage` ;
- consultation des permissions : `permissions.view` ;
- audit : `audit.view` ;
- réglages : `settings.manage` ;
- paie : `payroll.view`, `payroll.prepare`, `payroll.validate`, `payroll.close`,
  `payroll.exports.download`, `payroll.variables.view`, `payroll.variables.manage` ;
- salariés et contrats : `employees.*`, `contracts.*` ;
- temps, absences, chantiers, frais : `worktime.*`, `leave.*`, `sites.*`, `expenses.*` ;
- bulletins, déclarations, CIBTP, documents, reporting, portail et intégrations.

`permissions.manage` n'est pas prévu comme permission d'interface : les permissions sont
définies dans le code. Une interface future pourra consulter les permissions et attribuer
des permissions existantes aux rôles, sans permettre d'en créer librement.

L'export est toujours une permission distincte de la consultation. Il devra également être
audité et protégé par un stockage privé et des liens temporaires.

## 6. Synchronisation Spatie

`App\Support\Rbac\RbacSynchronizer` :

- utilise le guard `web` explicitement ;
- exécute la synchronisation dans une transaction ;
- crée ou récupère les rôles et permissions avec `firstOrCreate` ;
- remplace les permissions de chaque rôle géré par celles de la matrice ;
- ne crée aucun utilisateur ;
- ne supprime pas les rôles ou permissions non gérés ;
- invalide le cache Spatie à la fin, même si la matrice n'a pas changé.

Le seeder `Database\Seeders\RbacSeeder` appelle uniquement ce synchroniseur. Le
`DatabaseSeeder` l'appelle ; il ne crée ni compte, ni mot de passe, ni administrateur.

Commande manuelle de synchronisation :

```bash
php artisan db:seed --class=RbacSeeder --force
php artisan permission:cache-reset
```

En production, relancer également les workers de queue si des rôles ou permissions ont changé :

```bash
php artisan queue:restart
```

## 7. Guard

Le seul guard applicatif est `web` (`config/auth.php`). Filament est configuré avec
`->authGuard('web')`. Les rôles et permissions sont créés avec `guard_name = web`.

Sanctum réutilise ce guard pour les sessions. Si un guard `api` distinct est ajouté plus tard,
il ne faudra pas réutiliser implicitement les rôles `web` : Spatie exige des rôles et
permissions portant le bon `guard_name`.

## 8. Cache Spatie

- Le cache est configuré pour 24 heures via `config/permission.php`.
- Le store par défaut suit `CACHE_STORE` : `database` en local/production, `array` en test.
- Les modèles `Role` et `Permission` invalident le cache lors de leurs sauvegardes/suppressions.
- Une écriture SQL directe ne déclenche pas cette invalidation : utiliser
  `php artisan permission:cache-reset`.
- Un worker de queue ou un processus long peut conserver une collection en mémoire :
  redémarrer les workers après une modification RBAC.

## 9. Procédure de mise à jour du référentiel

1. Ajouter la permission dans `app/Enums/PermissionName.php` uniquement si la fonctionnalité existe.
2. Mettre à jour la matrice dans `app/Support/Rbac/RbacMatrix.php`.
3. Mettre à jour ce document et les tests.
4. Exécuter les tests RBAC et la suite complète.
5. Exécuter `php artisan db:seed --class=RbacSeeder --force` dans l'environnement cible.
6. Exécuter `php artisan permission:cache-reset`.
7. Redémarrer les workers si nécessaire.
8. Vérifier les accès positifs et négatifs dans l'application.

Ne jamais modifier les rôles ou permissions directement en base pour contourner ce processus.

## 10. Accès Filament et policies

`App\Models\User` implémente `Filament\Models\Contracts\FilamentUser`. `canAccessPanel()`
vérifie la permission `administration.panel.access` sur le guard `web`. L'accès ne dépend
pas du nom du rôle.

Le panel active `strictAuthorization()`. Aucune ressource Filament métier n'existe au Lot 1A,
donc aucune policy métier n'est nécessaire. Lors de l'ajout d'une ressource :

- créer la policy Laravel avant d'exposer la ressource ;
- couvrir `viewAny`, `view`, `create`, `update`, `delete` et les actions utilisées ;
- tester le positif et le négatif ;
- ne jamais utiliser `skipAuthorization()` ;
- ne pas compter sur `visible()` ou `hidden()` comme autorisation.

Les pages personnalisées doivent surcharger `canAccess()`. Les widgets doivent surcharger
`canView()` lorsqu'ils exposent des données sensibles. Les actions personnalisées doivent
appeler `authorize()`. Les colonnes éditables en ligne contournent les policies Filament et
doivent être évitées sur les données sensibles. Les actions groupées utilisent les méthodes
`deleteAny`, `restoreAny`, etc., ou `authorizeIndividualRecords()`.

## 11. Middleware Spatie

Les alias suivants sont enregistrés dans `bootstrap/app.php` :

```text
role
permission
role_or_permission
```

Ils correspondent aux middlewares `Spatie\Permission\Middleware\*`. Le package 8.5 ne les
enregistre pas lui-même.

## 12. Points d'entrée et contrôles serveur — Lot 1C

### Tableau des points d'entrée

| Point d'entrée | Exposition | Protection actuelle |
|---|---|---|
| `GET /` | Publique | Page d'accueil statique, aucune donnée métier. |
| `GET /admin/login` | Publique | Formulaire Filament, CSRF et limitation de débit Filament. |
| `GET /admin` | Authentifiée | Session `web` + permission `administration.panel.access`. |
| `POST /admin/logout` | Authentifiée | Session `web`, CSRF, déconnexion Filament. |
| `GET /admin/invitation/{token}` | Publique avec jeton | Format `[a-f0-9]{64}`, hash, expiration, limitation de débit, en-têtes de sécurité. |
| `POST /admin/invitation/{token}` | Publique avec jeton | Mêmes contrôles, CSRF, validation du mot de passe, consommation atomique. |
| `GET /api/user` | Authentifiée Sanctum | Utilisateur courant uniquement ; `password` et `remember_token` masqués. |
| `GET /up` | Publique | Health check Laravel, aucune donnée sensible. |
| `GET /sanctum/csrf-cookie` | Publique | Cookie CSRF standard Sanctum. |
| `GET/PUT /storage/{path}` | Signée | Disque privé, signature requise ; hors RBAC. |
| Endpoints Livewire | Publiques avec contrôles techniques | Aucun composant applicatif ; chaque futur composant devra autoriser ses actions. |
| Téléchargements Filament export/import | Authentifiés/signés | Aucun export métier actif ; policy ou propriétaire selon Filament. |

### Panel Filament

- `AdminPanelProvider` utilise `authGuard('web')` et `strictAuthorization()`.
- `User::canAccessPanel()` vérifie `administration.panel.access` sur le guard `web`.
- Le Dashboard, `AccountWidget` et `FilamentInfoWidget` héritent de la protection du panel.
- Le Dashboard ne contient aucune donnée métier et ne nécessite pas de policy supplémentaire.
- `AccountWidget` vérifie l'authentification ; `FilamentInfoWidget` n'expose que des informations techniques.
- Aucune ressource, page personnalisée ou action Filament métier n'existe.
- Aucune policy artificielle n'a été créée.

### Routes web

- La page d'accueil reste publique et sans donnée sensible.
- Les routes d'invitation restent publiques, avec validation stricte du jeton, expiration, limitation de débit et CSRF en soumission.
- Le panel et le logout restent protégés par le middleware Filament.
- Aucune route HTTP ne déclenche la commande `batipaie:admin-invite`.
- La commande de bootstrap reste exclusivement console.

### API

- `/api/user` requiert `auth:sanctum`.
- Le endpoint retourne uniquement l'utilisateur authentifié ; un paramètre d'URL ne permet pas de cibler un autre compte.
- `password` et `remember_token` ne sont pas sérialisés.
- Aucun rôle, permission ou endpoint de mutation n'est exposé.
- Les méthodes `POST` et `DELETE` sur `/api/user` renvoient 405.
- Le modèle `User` supporte les tokens Sanctum, mais aucun endpoint d'émission de token n'existe actuellement.

### Bootstrap administrateur

- `batipaie:admin-invite` vérifie `runningInConsole()`.
- Aucune route HTTP ne l'expose.
- Le flux d'acceptation conserve les protections du Lot 1B : jeton à usage unique, transaction, rôle `administrator` exclusif, refus si un administrateur existe déjà.
- Aucune permission métier n'est attribuée.

### Limites du périmètre actuel

- Aucune ressource métier (salariés, contrats, paie, temps, absences, chantiers) n'existe.
- Aucun contrôle de portée de données n'est encore applicable.
- Aucune interface de gestion des utilisateurs, rôles ou permissions.
- Aucune policy métier ni permission métier active.
- L'API ne fournit pas encore de gestion de tokens Sanctum.
- Les endpoints Livewire et de stockage sont des surfaces techniques ; leur autorisation métier sera à traiter avec les composants concernés.

## 13. Règles de sécurité

- Refus par défaut : une permission absente signifie un refus.
- Aucun wildcard : `enable_wildcard_permission` reste à `false`.
- Aucun bypass super-administrateur : un administrateur sans permission métier est refusé.
- Le rôle Salarié n'accède pas au panel d'administration.
- L'Expert-comptable externe ne reçoit aucune permission de modification ou d'administration.
- Les exports sont séparés des consultations et seront audités.
- Les données de paie, bancaires, médicales et les bulletins ne doivent jamais apparaître
  dans les logs, les URLs, les notifications ou les messages d'erreur.
- Aucun compte ou secret par défaut n'est créé par le référentiel.

## 14. Tests

Les tests RBAC couvrent :

- les sept rôles, leurs codes et leurs libellés ;
- l'unicité et le format des permissions ;
- l'absence de permission métier prématurée ;
- la création, l'idempotence et la mise à jour de la matrice ;
- le guard `web`, le cache et l'absence de création d'utilisateur ;
- l'accès au panel en positif et en négatif ;
- le refus du rôle Salarié et de l'Expert-comptable ;
- l'absence de bypass pour l'Administrateur ;
- les alias middleware, `strictAuthorization()` et l'absence de wildcard ;
- les pages publiques et les URL directes du panel ;
- l'API `/api/user` en 401, 200, auto-accès et absence d'attributs sensibles ;
- l'absence de route HTTP pour la commande de bootstrap administrateur.

Référence : `docs/development/TESTING.md` pour les conventions de tests et
[`ADMIN-BOOTSTRAP.md`](ADMIN-BOOTSTRAP.md) pour l'initialisation du premier administrateur.
