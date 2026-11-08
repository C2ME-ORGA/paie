# BP-192 — Lot 0 : étude de faisabilité Spatie Activitylog + Filament Activity Log

**Ticket Jira de référence :** BP-192 — Implémenter le journal d'audit métier complet  
**Lot :** 0 — étude exclusivement documentaire  
**Date de l'étude :** 8 novembre 2026  
**Branche observée :** `develop`  
**Commit observé :** `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`  
**État du worktree au début de l'étude :** propre, aucune modification non commitée  
**Périmètre :** aucun code, aucune migration, aucune dépendance, aucune opération Jira ou Git modificatrice

> **Limite de traçabilité Jira.** Le contenu détaillé du ticket BP-192 n'était pas accessible depuis cette session (aucun connecteur Jira disponible). L'étude s'appuie donc sur la mission transmise, les critères fonctionnels qui y sont cités, `AGENTS.md` et l'état réel du dépôt. Les critères d'acceptation Jira devront être rapprochés du présent rapport avant tout arbitrage final.

---

## 1. Résumé exécutif

### Verdict

**GO avec adaptations.**

La combinaison suivante est techniquement compatible avec le dépôt et peut être retenue :

1. **`spatie/laravel-activitylog` 5.1.4** comme moteur de persistance et d'événements ;
2. **`alizharb/filament-activity-log` 2.0.0** comme interface d'administration en lecture seule ;
3. **une couche métier BatiPaie** (`AuditManager`, `AuditContext`, `AuditSanitizer`, `AuditLogger`, `AuditEventType`) pour la conformité, le filtrage, l'attribution et les opérations non couvertes par Eloquent.

Cette combinaison n'est toutefois **pas suffisante en l'état** : les deux packages ne garantissent ni l'exhaustivité métier, ni l'inaltérabilité de la base, ni l'absence de données sensibles dans les traces. Le plugin est jeune, mono-mainteneur et activé par défaut avec une consultation large pour tout utilisateur du panel. Des adaptations bloquantes sont donc exigées.

### Constats majeurs

- Le dépôt est au tout début de son histoire fonctionnelle : **aucun modèle métier de paie, salarié, contrat, chantier ou bulletin n'existe**. Seuls `User`, `AdministratorInvitation` et les modèles Spatie Permission sont présents.
- **Aucun package d'audit n'est installé** : ni `spatie/laravel-activitylog`, ni `alizharb/filament-activity-log`, ni équivalent.
- Les contraintes Composer sont compatibles et une **résolution à blanc complète** a été obtenue sans modifier le dépôt : Laravel 13.40.0, Filament 5.11.1, Livewire 4.4.8, Spatie Permission 8.5.0, Activitylog 5.1.4, plugin 2.0.0.
- Spatie Activitylog couvre correctement les événements Eloquent `created`, `updated`, `deleted` et `restored`, mais **ne couvre pas** les mises à jour/suppressions de masse, les écritures SQL directes, les `forceDelete`, les lectures, les exports, les changements de rôles/permissions, ni les événements d'authentification.
- Le plugin fournit une interface riche (ressource, timeline, filtres, widgets, relation manager), mais :
  - ses **widgets sont visibles par tout utilisateur du panel** si `widgets.enabled` reste à `true` ;
  - son **dashboard d'audit n'a pas de contrôle `canAccess()`** quand il est activé ;
  - son **relation manager n'a pas de contrôle d'autorisation propre** observé ;
  - sa **politique remplace toute policy applicative** sur le modèle `Activity` ;
  - son **mode immuable masque l'interface**, mais ne rend pas la base inaltérable ;
  - sa **signature HMAC détecte la modification d'une ligne, pas sa suppression**, et nécessite une clé stable ;
  - son **buffering Spatie est incompatible** avec son propre enrichissement de métadonnées, car le buffer insère en masse sans déclencher les événements de modèle.
- La **rédaction du plugin est une protection d'affichage**, pas un filtrage à l'écriture. Un champ sensible écrit dans `attribute_changes` ou `properties` reste en base.
- Le **whitelist d'écriture** doit être centralisé dans BatiPaie, avec une liste noire absolue (mots de passe, tokens, IBAN, données bancaires, données médicales, etc.), et doit couvrir à la fois les changements d'attributs, les propriétés libres et les descriptions.

### Conditions bloquantes avant tout GO de mise en œuvre

1. **Ne jamais activer le plugin sans `permissions.enabled = true`** et sans mapping vers une permission BatiPaie dédiée (`audit.view`, `audit.export`).
2. **Désactiver les widgets et le dashboard du plugin**, ou les remplacer par des composants dont `canView()` / `canAccess()` vérifient `audit.view`.
3. **Ne pas exposer le relation manager** sur une ressource métier sans contrôle d'autorisation propre.
4. **Laisser `mutations.enabled = false`** et activer `privacy.immutable_mode = true`.
5. **Laisser `activitylog.buffer.enabled = false`** tant que l'on utilise l'intégrité/risque du plugin.
6. **Écrire un `AuditSanitizer` à l'écriture**, avec liste blanche par modèle et liste noire absolue, avant toute journalisation de données réelles.
7. **Instrumenter explicitement** les opérations non couvertes par les événements Eloquent (rôles/permissions, invitations, lectures sensibles, exports, commandes, jobs, suppressions de masse).
8. **Décider du niveau de preuve** : la signature HMAC du plugin est utile mais ne remplace ni des droits SQL restrictifs, ni des sauvegardes, ni un ancrage externe.

---

## 2. État initial du dépôt

### 2.1 Git

| Élément | Valeur observée |
|---|---|
| Branche courante | `develop` |
| Commit courant | `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` |
| Auteur du commit | Vortech Studio |
| Date du commit | 8 novembre 2026, 15:39:49 +0100 |
| Sujet du commit | `[BP-191] Sécuriser le RBAC et l’initialisation administrateur (#9)` |
| Worktree | Propre, aucune modification suivie ou non suivie |
| Branche changée pendant l'étude | Non |

Le worktree était propre et le fichier de livraison était absent : la création du rapport ne risque donc pas d'écraser un travail existant.

### 2.2 Versions réellement installées

| Composant | Version constatée | Source |
|---|---:|---|
| PHP CLI local | 8.5.12 | `php -v` |
| Plateforme PHP Composer | `^8.4` | `composer.lock`, clé `platform` |
| Laravel Framework | 13.40.0 | `composer.lock` |
| Filament | 5.11.1 | `composer.lock` |
| Livewire | 4.4.8 | `composer.lock` |
| Spatie Laravel Permission | 8.5.0 | `composer.lock` |
| Carbon | 3.15.1 | `composer.lock` |
| Spatie Laravel Package Tools | 1.93.3 | `composer.lock` |
| Spatie Activitylog | **absent** | `composer.json`, `composer.lock`, `vendor/` |
| Ali Harb Filament Activity Log | **absent** | `composer.json`, `composer.lock`, `vendor/` |

Le runtime local est PHP 8.5.12, tandis que la cible déclarée est PHP `^8.4`. La résolution Composer à blanc a été volontairement réalisée avec une plateforme simulée **PHP 8.4.0**, pour vérifier la cible de production et non seulement le poste local.

### 2.3 Packages d'audit présents

Aucun. Le répertoire `vendor/spatie` contient uniquement :

- `spatie/invade` (dépendance transitive, probablement Filament) ;
- `spatie/laravel-package-tools` ;
- `spatie/laravel-permission` ;
- `spatie/shiki-php` (dépendance transitive de coloration syntaxique).

Aucun répertoire `activitylog`, `audit` ou `filament-activity-log` n'est présent.

### 2.4 Configuration Filament actuelle

`app/Providers/Filament/AdminPanelProvider.php` :

- panel par défaut, identifiant `admin`, chemin `/admin` ;
- guard `web` ;
- page de login Filament activée ;
- `strictAuthorization()` activé ;
- découverte automatique des ressources, pages et widgets dans `app/Filament/...` ;
- seules pages enregistrées : `Dashboard`, `AccountWidget`, `FilamentInfoWidget` ;
- middleware standard Filament (cookies, session, CSRF, bindings, événements).

**Aucune ressource Filament métier n'existe.** Aucune policy métier n'est donc présente.

### 2.5 Organisation des domaines et modèles

Modèles applicatifs réellement présents :

| Modèle | Fichier | Rôle |
|---|---|---|
| `User` | `app/Models/User.php` | Utilisateur authentifiable, rôles Spatie, accès panel |
| `AdministratorInvitation` | `app/Models/AdministratorInvitation.php` | Invitation unique d'amorçage administrateur |

Modèles Spatie Permission utilisés indirectement :

- `Spatie\Permission\Models\Role` ;
- `Spatie\Permission\Models\Permission`.

Il n'existe **aucun** modèle de salarié, contrat, établissement, chantier, temps, absence, bulletin, déclaration, CIBTP ou paie. Le référentiel documentaire `docs/business-rules/employees-and-contracts/` décrit un domaine futur, mais **aucun code correspondant n'est présent**. Le présent rapport ne doit donc pas extrapoler ces modules.

### 2.6 Points d'entrée applicatifs

| Point d'entrée | Type | Écriture ? |
|---|---|---|
| `GET /` | Route web publique | Non |
| `GET /admin/login` | Filament, public | Session éventuelle |
| `GET /admin` | Filament, authentifié + permission panel | Non |
| `POST /admin/logout` | Filament, authentifié | Session |
| `GET /admin/invitation/{token}` | Route web publique à jeton | Non |
| `POST /admin/invitation/{token}` | Route web publique à jeton | Oui : crée `User`, met à jour `AdministratorInvitation` |
| `GET /api/user` | API Sanctum authentifiée | Non |
| `GET /up` | Health check | Non |
| `GET/PUT /storage/{path}` | Disque privé signé | Lecture/écriture de fichiers |
| `batipaie:admin-invite` | Commande console | Oui : crée une invitation |
| `php artisan db:seed --class=RbacSeeder` | Seeder console | Oui : rôles/permissions |

### 2.7 Migrations existantes

1. `0001_01_01_000000_create_users_table.php`
2. `0001_01_01_000001_create_cache_table.php`
3. `0001_01_01_000002_create_jobs_table.php`
4. `2026_11_06_204616_create_permission_tables.php`
5. `2026_11_06_225734_create_personal_access_tokens_table.php`
6. `2026_11_08_000000_create_administrator_invitations_table.php`

Aucune table `activity_log`, aucun journal d'audit, aucune table d'historique métier.

### 2.8 Vérifications non effectuées

Conformément à la mission :

- aucun test n'a été lancé ;
- aucune migration n'a été exécutée ;
- aucune base de données n'a été lue ou modifiée ;
- aucun package n'a été installé ou désinstallé ;
- aucun fichier `.env` n'a été lu, affiché ou recherché ;
- aucun commit, push, merge, tag ou changement de branche n'a été effectué ;
- Jira n'a pas été modifié.

---

## 3. Compatibilité des packages

### 3.1 Contraintes Composer vérifiées

| Package | Version retenue | PHP | Laravel / Illuminate | Filament | Spatie Activitylog | Verdict |
|---|---:|---|---|---|---|---|
| `spatie/laravel-activitylog` | 5.1.4 | `^8.4` | `illuminate/config`, `database`, `support` : `^12.0 || ^13.0` | Non applicable | — | Compatible |
| `alizharb/filament-activity-log` | 2.0.0 | `^8.3` | `illuminate/database`, `support` : `^12.0 || ^13.0` | `^4.0 || ^5.0` (actions `^4.2 || ^5.0`, tables `^4.0 || ^5.0`) | `^4.0 || ^5.0` | Compatible |
| `phiki/phiki` | 2.2.1 (transitif) | Compatible | Compatible | — | — | Compatible |
| `spatie/laravel-package-tools` | 1.93.3 (déjà installé) | — | — | — | — | Compatible |

Le plugin ne déclare **aucune contrainte directe sur Livewire**. Livewire 4.4.8 est requis par `filament/support` (`^4.4.8`) dans Filament 5.11.1 ; la résolution à blanc confirme que l'ensemble tient.

### 3.2 Résolution Composer à blanc réellement effectuée

Une résolution a été réalisée dans un répertoire temporaire hors dépôt (`%TEMP%\bp192-composer-check`), avec un `composer.json` minimal et une plateforme PHP simulée **8.4.0** :

```json
{
  "require": {
    "php": "8.4.*",
    "laravel/framework": "13.40.0",
    "filament/filament": "5.11.1",
    "livewire/livewire": "4.4.8",
    "spatie/laravel-permission": "8.5.0",
    "spatie/laravel-activitylog": "^5.1",
    "alizharb/filament-activity-log": "^2.0"
  },
  "config": {
    "platform": { "php": "8.4.0" },
    "allow-plugins": { "php-http/discovery": true }
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

Commande : `composer update --dry-run --no-scripts --no-plugins --no-interaction`.

Résultat observé, sans conflit :

- `laravel/framework` v13.40.0 ;
- `filament/filament` v5.11.1 ;
- `livewire/livewire` v4.4.8 ;
- `spatie/laravel-permission` 8.5.0 ;
- `spatie/laravel-activitylog` 5.1.4 ;
- `alizharb/filament-activity-log` v2.0.0 ;
- `phiki/phiki` v2.2.1 ;
- `spatie/laravel-package-tools` 1.93.3.

Aucun `composer.lock` ni répertoire `vendor/` n'a été créé dans le répertoire temporaire : le mode `--dry-run` a bien été respecté. Le dépôt n'a pas été modifié.

### 3.3 Dépendances transitives notables

Le plugin ajoute notamment :

- `phiki/phiki` (coloration syntaxique des diffs) ;
- `masterminds/html5` ;
- `symfony/html-sanitizer` ;
- `spatie/invade` ;
- `ryangjchandler/blade-capture-directive` ;
- `nette/php-generator` ;
- `spatie/shiki-php` ;
- `scrivo/highlight.php`.

Ces dépendances sont cohérentes avec l'écosystème Filament déjà installé. Elles augmentent toutefois la surface d'approvisionnement logiciel et doivent être couvertes par la politique de veille et de `composer audit`.

### 3.4 Absence d'avis de sécurité publié

Au moment de la consultation (8 novembre 2026) :

- Packagist ne signalait aucun avis de sécurité pour `spatie/laravel-activitylog` ni pour `alizharb/filament-activity-log` ;
- l'API GitHub des avis de sécurité du plugin renvoyait une liste vide.

Cette absence d'avis **ne prouve pas l'absence de vulnérabilité**. Elle signifie seulement qu'aucun avis publié n'était visible à cette date.

### 3.5 Limite de la démonstration

La résolution à blanc démontre la **compatibilité des contraintes** et l'absence de conflit connu à la date de l'étude. Elle ne démontre pas :

- le bon fonctionnement de l'interface dans le panel BatiPaie ;
- la compatibilité de chaque fonctionnalité du plugin avec les policies BatiPaie ;
- l'absence de régression sur les futures migrations métier ;
- la tenue en charge sur un volume de journal important.

Ces points devront être validés par des tests d'intégration au Lot 1.

---

## 4. Analyse de Spatie Activitylog 5.1.4

### 4.1 Compatibilité

`spatie/laravel-activitylog` 5.1.4 exige PHP `^8.4` et Laravel/Illuminate `^12.0 || ^13.0`. Le dépôt cible PHP `^8.4` et Laravel 13.40.0 : la compatibilité est directe.

Le package est un package Laravel standard, auto-découvert via `Spatie\Activitylog\ActivitylogServiceProvider`. Il publie une configuration et une migration. Aucun service externe n'est requis.

### 4.2 API publique

Les points d'entrée principaux sont :

- helper `activity()` ;
- façade `Spatie\Activitylog\Facades\Activity` ;
- trait `Spatie\Activitylog\Models\Concerns\LogsActivity` ;
- trait `CausesActivity` / `HasActivity` ;
- modèle `Spatie\Activitylog\Models\Activity` ;
- `LogOptions` dans `Spatie\Activitylog\Support\LogOptions` ;
- enum `Spatie\Activitylog\Enums\ActivityEvent`.

Exemple de journalisation manuelle :

```php
activity()
    ->performedOn($subject)
    ->causedBy($actor)
    ->event('validated')
    ->withProperties(['reason' => $reason])
    ->log('Paie validée');
```

Le modèle `Activity` expose notamment `subject`, `causer`, `attribute_changes`, `properties`, `getProperty()`, et les scopes `forSubject`, `causedBy`, `forEvent`, `inLog`.

### 4.3 Mécanisme `LogsActivity`

Le trait enregistre par défaut les événements :

- `created` ;
- `updated` ;
- `deleted` ;
- `restored` si le modèle utilise `SoftDeletes`.

Il est possible de restreindre avec `protected static $recordEvents = [...]` ou d'exclure avec `$doNotRecordEvents`.

Le trait ne journalise **que les événements de modèle Eloquent**. Il ne voit pas :

- les `forceDelete` (événement `forceDeleted` non enregistré par défaut) ;
- les `update()` / `delete()` de masse sur un `Builder` ;
- les `insert()`, `upsert()`, `DB::table()->update()` ou `DB::statement()` ;
- les `saveQuietly()` et les blocs `Model::withoutEvents()` ;
- les opérations effectuées par des outils externes.

### 4.4 Journalisation manuelle

La journalisation manuelle est le mécanisme à privilégier pour les événements métier qui ne sont pas de simples mutations Eloquent : validation de paie, clôture, export, consultation sensible, changement de permission, opération système, etc.

Elle permet de définir explicitement :

- le sujet (`performedOn`) ;
- l'auteur (`causedBy`, `causedByAnonymous`) ;
- l'événement (`event`) ;
- les propriétés libres (`withProperties`, `withProperty`) ;
- les changements (`withChanges`) ;
- le nom de journal (`useLog` / `inLog`) ;
- la date (`createdAt`).

### 4.5 Attribution de l'auteur

Par défaut, `CauserResolver` utilise l'utilisateur du guard d'authentification configuré (`activitylog.default_auth_driver`, sinon le guard Laravel courant).

Il est possible de :

- forcer un auteur avec `causedBy($model)` ;
- forcer un identifiant avec `causedBy($id)` ;
- dissocier avec `causedByAnonymous()` ;
- définir un auteur par défaut avec `Activity::defaultCauser($user)` ;
- utiliser `CauserResolver::resolveUsing()` ou `withCauser()`.

### 4.6 Attribution des événements système

Sans utilisateur authentifié (console, job, scheduler, migration), le causer est `null`.

C'est un point critique pour BP-192 : un événement système ne doit pas être confondu avec un événement utilisateur. Il faut :

- soit un **acteur système explicite** (compte technique dédié, par exemple `system`), ;
- soit un causer `null` **accompagné de propriétés** `source`, `command`, `job`, `request_id`, `system_actor` ;
- jamais laisser un événement système sans contexte.

Le package ne fournit pas de notion native de « compte système » : cette convention doit être portée par `AuditContext` / `AuditManager`.

### 4.7 Récupération des valeurs avant/après

Le package stocke les changements dans la colonne `attribute_changes` (Spatie v5) :

- `created` : `attributes` contient les valeurs créées ;
- `updated` : `attributes` contient les nouvelles valeurs, `old` les anciennes ;
- `deleted` : `old` contient les valeurs supprimées ;
- `restored` : le comportement dépend du modèle ; le package journalise l'événement, mais il ne faut pas supposer un diff complet sans test.

Les valeurs sont sérialisées en JSON. Les casts Eloquent sont appliqués, sauf si `useAttributeRawValues()` est utilisé.

**Attention :** `$hidden` ne protège pas la journalisation. Le trait lit les attributs via `getAttribute()`, pas via la sérialisation. Un attribut `password` ou `remember_token` peut donc être journalisé même s'il est masqué dans les réponses JSON.

### 4.8 Filtrage des attributs

`LogOptions` propose :

| Méthode | Effet |
|---|---|
| `logOnly([...])` | Liste blanche explicite |
| `logFillable()` | Tous les attributs `$fillable` |
| `logUnguarded()` | Tous les attributs non `$guarded` |
| `logAll()` | Tous les attributs |
| `logExcept([...])` | Exclusion d'attributs |
| `default_except_attributes` | Exclusion globale dans la configuration |
| `logOnlyDirty()` | Seulement les attributs réellement modifiés |
| `dontLogEmptyChanges()` | Ne pas stocker un changement vide |
| `dontLogIfAttributesChangedOnly([...])` | Ne pas déclencher si seuls ces attributs changent |
| `useAttributeRawValues([...])` | Valeurs brutes, sans mutateurs |

Pour BatiPaie, la seule stratégie acceptable est une **liste blanche explicite** (`logOnly`) par modèle et par événement, complétée par une liste noire absolue centralisée. `logFillable()` sur `User` inclurait `password`, qui est pourtant `$fillable`. `logAll()` est à proscrire sur les modèles RH/paie.

### 4.9 Événements personnalisés

`event()` accepte une chaîne ou `ActivityEvent`. Les noms personnalisés sont donc possibles :

```php
activity()->event('payroll.validated')->...
activity()->event('audit.exported')->...
```

Le plugin Filament sait afficher des événements personnalisés si on les ajoute à sa configuration `events`. La colonne `event` reste une chaîne nullable, sans contrainte d'énumération en base.

### 4.10 Gestion des suppressions

- `deleted` est journalisé pour une suppression Eloquent standard.
- Une suppression douce (`SoftDeletes`) déclenche `deleted`, puis `restored` à la restauration.
- Une suppression physique via `forceDelete()` déclenche `forceDeleted`, **non enregistré par défaut**.
- Une suppression de masse via `Builder::delete()` ne déclenche aucun événement de modèle.
- Une suppression SQL directe n'est pas vue.
- Le package ne protège pas la ligne d'audit contre sa propre suppression : le modèle `Activity` n'utilise pas `SoftDeletes` par défaut et expose `public $guarded = []`.

### 4.11 Gestion des transactions

Sans buffering, si l'activité et le sujet sont sur la **même connexion**, l'activité est écrite dans la même transaction : un rollback supprime l'activité avec la modification métier.

Avec une connexion différente ou avec le buffering, le package diffère l'écriture jusqu'au commit ; en cas de rollback, l'activité est abandonnée. Les écritures sur des bases différentes ne sont pas atomiques : si l'activité échoue après le commit du sujet, la modification métier reste enregistrée sans trace.

Conséquences pour BatiPaie :

- pour un **journal de changement métier**, l'atomicité avec la transaction est souhaitable ;
- pour un **événement de sécurité** (tentative refusée, export, accès), il faut au contraire une trace indépendante, non annulée par le rollback métier ;
- il faut donc distinguer plusieurs catégories d'événements et ne pas utiliser un unique comportement transactionnel.

### 4.12 Traitements en arrière-plan

Le package fonctionne en queue, mais :

- il n'y a pas d'utilisateur authentifié dans un worker : le causer est `null` sauf `causedBy()` explicite ;
- le buffering est vidé après chaque job, mais les événements de modèle du plugin ne sont pas déclenchés par l'insertion en masse ;
- les jobs longs doivent réinitialiser leur contexte pour éviter de réutiliser un acteur ou un `request_id` obsolète ;
- les échecs de job doivent être journalisés comme événements système, pas comme actions utilisateur.

### 4.13 Opérations de masse

Aucune couverture automatique :

- `Model::where(...)->update([...])` ;
- `Model::where(...)->delete()` ;
- `DB::table(...)->update()` / `delete()` ;
- `insert()` / `upsert()` ;
- `truncate()`.

Si BP-192 exige la traçabilité des opérations de masse, `AuditManager` devra exposer une méthode dédiée qui journalise l'intention, le nombre de lignes affectées et les critères, puis l'exécution réelle. Une simple observation Eloquent ne suffit pas.

### 4.14 Structure de la table `activity_log`

Migration Spatie 5.1.4 :

```text
id
log_name            string nullable, index
description         text
subject_type        string nullable
subject_id          bigint nullable
event               string nullable
causer_type         string nullable
causer_id           bigint nullable
attribute_changes   json nullable
properties          json nullable
created_at          timestamp nullable
updated_at          timestamp nullable
```

Index implicites : `log_name`, `(subject_type, subject_id)`, `(causer_type, causer_id)`.

Absences notables :

- pas de `company_id` / `tenant_id` ;
- pas d'`ip_address` ni de `user_agent` ;
- pas de `request_id` ;
- pas de `reason` / `justification` ;
- pas de `hash` / `signature` ;
- pas de `retention_hold` ;
- pas de `deleted_at` (pas de suppression douce native) ;
- pas de contrainte d'immuabilité.

### 4.15 Personnalisation du modèle et de la migration

Spatie v5 permet :

- de publier la migration et de l'adapter avant exécution ;
- de créer un modèle `App\Models\Activity` étendant `Spatie\Activitylog\Models\Activity` ;
- de changer `$table` et `$connection` ;
- de pointer `activitylog.activity_model` vers ce modèle ;
- de remplacer les actions `log_activity` et `clean_log` via la configuration.

Le plugin Filament lit le modèle configuré via `config('activitylog.activity_model')`, ce qui permet une certaine coexistence. Mais il enregistre lui-même une policy sur ce modèle et ajoute des colonnes par sa propre migration.

### 4.16 Stratégie de conservation

Le package fournit :

- `php artisan activitylog:clean` ;
- option `--days` ;
- option `--force` en production ;
- configuration `clean_after_days`, valeur par défaut **365 jours** ;
- action `CleanActivityLogAction`, qui supprime physiquement les lignes anciennes.

Il n'y a **aucune** notion de :

- conservation légale ;
- suspension de purge (`retention hold`) ;
- archivage ;
- anonymisation ;
- conservation différenciée par catégorie ;
- preuve d'intégrité.

La valeur par défaut de 365 jours est un **choix du package**, pas une durée légale BatiPaie. Elle ne doit pas être reprise sans arbitrage.

### 4.17 Risques de journalisation involontaire de données sensibles

Risques principaux :

1. `logAll()` ou `logFillable()` sur `User` journalise le hash du mot de passe.
2. `$hidden` ne protège pas la journalisation.
3. `properties` peut contenir n'importe quelle donnée passée par le développeur.
4. `description` peut interpoler un nom, un email, un IBAN ou un token.
5. Les valeurs sont stockées en clair dans la base (JSON), lisibles par tout compte SQL disposant de `SELECT`.
6. Les sauvegardes de base contiennent le journal.
7. Les exports Filament peuvent recopier les valeurs.
8. Les logs applicatifs (Monolog) peuvent doubler l'information si le journal est lui-même loggé.

### 4.18 Limites du package par rapport à BP-192

| Exigence probable BP-192 | Couverture Spatie seule | Écart |
|---|---|---|
| Création/modification/suppression Eloquent | Oui | — |
| Suppression douce et restauration | Oui | — |
| Suppression physique `forceDelete` | Non par défaut | Appel explicite requis |
| Mises à jour/suppressions de masse | Non | Appel explicite requis |
| Écritures SQL directes | Non | Appel explicite requis |
| Attribution utilisateur | Oui | — |
| Attribution système | Non native | Convention BatiPaie |
| Avant/après | Oui pour `updated`/`deleted` | Champs filtrés à définir |
| Événements métier personnalisés | Oui | Enum et catalogue à définir |
| Filtrage des données sensibles | Oui mais déclaratif | Whitelist centrale requise |
| Lecture seule pour utilisateurs ordinaires | Non | Policy/UI BatiPaie |
| Inaltérabilité | Non | Droits SQL, sauvegardes, ancrage |
| Preuve d'intégrité | Non | Couche BatiPaie ou plugin |
| Rétention/legal hold | Non | Décision et outillage |
| Isolation établissement/société | Non | Colonne et scope |
| Audit des lectures/exports | Non | Événements explicites |
| Audit des rôles/permissions | Non | Événements explicites |
| Audit des authentifications | Non | Listeners Laravel |

**Conclusion Partie 2 :** Spatie Activitylog est un bon moteur de persistance, pas une solution de conformité complète. Il doit être encadré par une couche métier.

---

## 5. Analyse de `alizharb/filament-activity-log` 2.0.0

### 5.1 Identité, maturité et maintenance

| Élément | Valeur constatée le 8 novembre 2026 |
|---|---|
| Auteur | Ali Harb (mainteneur unique identifié) |
| Licence | MIT |
| Création du dépôt | 2 décembre 2025 |
| Dernière version | 2.0.0, publiée le 16 août 2026 |
| Dernier commit | 31 octobre 2026 |
| Étoiles GitHub | 32 |
| Forks | 21 |
| Issues ouvertes | 0 |
| Téléchargements Packagist | 180 603 au total ; 30 492/mois ; 319/jour |
| Dépendants Packagist | 7 |
| Score de santé affiché par Filament | 78/100, dont sécurité 61/100 |
| Avis de sécurité publiés | Aucun visible à la date de consultation |

Le plugin est **jeune** (moins d'un an), **mono-mainteneur**, avec une adoption encore limitée. La page Filament précise explicitement qu'il s'agit d'un plugin communautaire, non audité ni approuvé par l'équipe Filament. Le score de sécurité de 61/100 est un signal à prendre en compte, même s'il provient d'une analyse automatisée et non d'un audit approfondi.

**Conséquence :** version à épingler précisément (`2.0.0`), revue de code avant intégration, veille sur les avis, et capacité de repli sur une interface Filament minimale maison.

### 5.2 Compatibilité exacte

| Contrainte | Plugin 2.0.0 | Dépôt | Verdict |
|---|---|---|---|
| PHP | `^8.3` | `^8.4` | Compatible |
| Laravel / Illuminate | `^12.0 \|\| ^13.0` | 13.40.0 | Compatible |
| Filament | `^4.0 \|\| ^5.0` | 5.11.1 | Compatible |
| Filament Actions | `^4.2 \|\| ^5.0` | 5.11.1 | Compatible |
| Filament Tables | `^4.0 \|\| ^5.0` | 5.11.1 | Compatible |
| Spatie Activitylog | `^4.0 \|\| ^5.0` | 5.1.4 | Compatible |
| Livewire | Pas de contrainte directe | 4.4.8 via Filament | Compatible par transitivité |
| `spatie/laravel-package-tools` | `^1.92` | 1.93.3 | Compatible |

La documentation du plugin indique que Spatie 5 est pleinement supporté sur Laravel 13 / PHP 8.4–8.5, à condition d'utiliser le schéma v5 (`attribute_changes`, pas de `batch_uuid`). Le dépôt n'ayant jamais installé Activitylog, il s'agira d'une **installation neuve v5**, pas d'une migration v4 → v5.

### 5.3 Installation et enregistrement

Séquence prévue par le package :

1. `composer require alizharb/filament-activity-log` ;
2. ajouter `ActivityLogPlugin::make()` dans `AdminPanelProvider::panel()` ;
3. exécuter `php artisan filament-activity-log:install` ;
4. l'installateur publie :
   - la configuration Spatie,
   - la migration Spatie,
   - la configuration du plugin,
   - la migration du plugin,
   - éventuellement traductions et vues ;
5. il propose ensuite d'exécuter `php artisan migrate` puis `filament-activity-log:doctor`.

Pour BatiPaie, il faudra **ne pas** lancer l'installateur en aveugle : revue des fichiers publiés, contrôle de l'ordre des migrations, personnalisation du modèle `Activity`, puis exécution contrôlée.

### 5.4 Migrations et configuration nécessaires

Le plugin publie **une seule migration** : `upgrade_activity_log_with_audit_control_columns`.

Elle ajoute à la table du modèle d'activité :

| Colonne | Type | Rôle |
|---|---|---|
| `risk_score` | tinyint unsigned nullable, index | Score de risque 0–100 |
| `risk_level` | string(16) nullable, index | Niveau low/medium/high/critical |
| `retention_hold` | boolean, index | Suspension de purge |
| `integrity_hash` | char(64) nullable, index | HMAC-SHA256 |
| `request_id` | string(100) nullable | Corrélation de requête |
| `ip_address` | string(45) nullable | IPv4/IPv6 |
| index composites | — | log/date, event/date, subject/date, causer/date, risk/date, request/date, IP/date |

Points d'attention :

- la migration **ne crée pas** la table `activity_log` : elle suppose que la migration Spatie a déjà été exécutée ;
- si la table n'existe pas, elle **retourne silencieusement** sans erreur ;
- il faut donc garantir l'ordre Spatie → plugin, puis vérifier la présence des colonnes ;
- le plugin ajoute des colonnes à la table existante : il n'y a pas de table d'audit séparée ;
- un modèle `Activity` personnalisé est supporté si `activitylog.activity_model` pointe vers lui ;
- la migration du plugin lit ce même modèle pour connaître la table et la connexion.

### 5.5 Interface de consultation

Le plugin enregistre une ressource Filament `ActivityLogResource` avec :

- liste paginée ;
- vue détaillée ;
- recherche globale ;
- filtres par `log_name`, `event`, `risk_level`, `retention_hold`, date, causer, subject, `request_id`, `ip_address` ;
- colonnes event, risk, subject, causer, IP, request ID, navigateur, description, date ;
- action timeline ;
- action « voir le lot » ;
- export Filament ;
- actions de purge, suppression, restauration et revert, **conditionnées par la configuration**.

### 5.6 Filtres, timeline, widgets et relation manager

- **Timeline** : action de table `ActivityLogTimelineTableAction`, sources configurables ; limite par défaut 50.
- **Widgets** : `ActivityChartWidget`, `LatestActivityWidget`, `ActivityHeatmapWidget`, `ActivityStatsWidget`.
- **Dashboard d'audit** : page `AuditDashboard`, désactivée par défaut.
- **Relation manager** : `ActivitiesRelationManager`, utilisable sur une ressource via `getRelations()`, avec relation `activitiesAsSubject()` ou `activities()`.
- **Page « activités utilisateur »** : `UserActivitiesPage`, activée par défaut.

**Points de sécurité observés dans le code :**

1. `ActivityChartWidget::canView()` et `LatestActivityWidget::canView()` ne vérifient **que la configuration** (`widgets.enabled` et `widgets.<nom>.enabled`). Ils **ne vérifient ni la policy ni `audit.view`**. Comme `widgets.enabled` vaut `true` par défaut et que le plugin enregistre les widgets dans le panel, **tout utilisateur ayant accès au panel peut voir les dernières activités et le graphique**, même sans permission d'audit.
2. `AuditDashboard` n'a **aucune méthode `canAccess()`** observée. Si `dashboard.enabled` est activé, tout utilisateur du panel peut y accéder.
3. `ActivitiesRelationManager` n'a **aucun `canViewForRecord()` / `canViewAny()` propre** observé. Ajouté à une ressource, il expose l'historique à tout utilisateur pouvant voir cette ressource.
4. `UserActivitiesPage::canAccess()` délègue à `ActivityLogResource::canViewAny()`, ce qui est cohérent si la permission est correctement configurée.

**Conséquence :** ne pas activer les widgets ni le dashboard par défaut ; ne pas ajouter le relation manager sans surcharge d'autorisation ; ou remplacer ces composants par des composants BatiPaie.

### 5.7 Gestion des permissions

Configuration par défaut :

```php
'permissions' => [
    'enabled' => false,
    'view_any' => 'view_any_activity',
    'view' => 'view_activity',
    'create' => 'create_activity',
    'update' => 'update_activity',
    'delete' => 'delete_activity',
    'delete_any' => 'delete_any_activity',
    'prune' => 'prune_activity_logs',
    'restore' => 'restore_activity',
    'force_delete' => 'force_delete_activity',
    'export' => 'export_activity_logs',
    'hold' => 'manage_activity_retention_holds',
    'allow_export_when_disabled' => false,
],
```

Comportement de `ActivityPolicy` :

- si `permissions.enabled = false` :
  - `viewAny()` et `view()` retournent **`true`** ;
  - `create()`, `update()`, `delete()`, `restore()`, `forceDelete()` retournent `false` ;
  - `exportAny()` retourne `allow_export_when_disabled`, donc `false` par défaut ;
- si `permissions.enabled = true` :
  - chaque action vérifie l'ability Gate configurée ;
  - `viewAny` / `view` peuvent être mappées vers `audit.view` ;
  - `export` vers `audit.export` ;
  - les mutations restent dépendantes de `mutations.enabled` et de `mutations.custom_authorization`.

**Risque majeur :** en configuration par défaut, tout utilisateur du panel voit tout le journal. Ce n'est pas acceptable pour BatiPaie. Il faut :

```php
'permissions' => [
    'enabled' => true,
    'view_any' => 'audit.view',
    'view' => 'audit.view',
    'export' => 'audit.export',
    // ...
],
```

et ajouter ces permissions au référentiel RBAC **uniquement lorsque la fonctionnalité d'audit existe**, conformément à `docs/security/RBAC.md`.

Le plugin utilise des **abilities Gate** (`view_any_activity`, etc.) et non directement des noms de permissions Spatie. Le `Gate::before` de Spatie permet de faire correspondre une permission du même nom, mais BatiPaie doit rester explicite : `audit.view` est une permission, `view_any` est l'ability configurée du plugin.

### 5.8 Possibilités de personnalisation

Le plugin permet :

- de personnaliser les libellés, groupes, icônes et le tri de navigation ;
- de personnaliser les colonnes, filtres et actions via `configureTableUsing()` ;
- de personnaliser le relation manager via `configureRelationManagerTableUsing()` ;
- de remplacer le modèle de ressource via `resource.class` ;
- de remplacer le modèle d'activité via `activitylog.activity_model` ;
- de définir un authorizer personnalisé via `permissions.custom_authorization` ;
- de définir un auteur de mutation via `mutations.custom_authorization` ;
- de définir un scope de requête via `query.scope` ou `scopeActivitiesUsing()` ;
- d'ajouter des collecteurs de contexte, des sources de timeline, des règles d'audit et des restorers.

**Limite structurelle :** le plugin enregistre lui-même `Gate::policy(Activity::class, ActivityPolicy::class)` dans `packageBooted()`. Toute policy applicative sur `Activity` sera **écrasée**. Il faut donc passer par `permissions.custom_authorization` pour la consultation, et non par une policy maison.

### 5.9 Mécanismes de masquage des données sensibles

Le plugin propose une **rédaction d'affichage** :

```php
'privacy' => [
    'redacted_value' => '[redacted]',
    'redaction' => [
        'enabled' => true,
        'fields' => ['password', 'token', 'api_key', 'secret', ...],
        'patterns' => ['/(^|_)(password|token|secret|key)$/'],
    ],
],
```

Elle s'applique :

- aux résumés de changements ;
- aux données brutes ;
- aux textes d'aide des actions de revert ;
- à certains champs d'export.

Elle **ne s'applique pas** :

- au stockage en base ;
- aux `attribute_changes` déjà écrits ;
- aux `properties` déjà écrits ;
- aux descriptions ;
- aux données envoyées à un éventuel consommateur hors plugin.

**Conclusion :** la rédaction du plugin est une défense en profondeur pour l'interface, pas une stratégie de confidentialité. Le filtrage doit être fait **avant l'écriture** par `AuditSanitizer`.

### 5.10 Mode immutable

Configuration :

```php
'privacy' => [
    'immutable_mode' => true,
],
```

Effet observé :

- masque les actions de suppression, suppression de masse, purge, restauration et revert dans l'interface ;
- `ActivityPruner::pruneBefore()` refuse l'exécution si `immutable_mode` est actif ou si les mutations sont désactivées.

**Ce que le mode immutable ne fait pas :**

- il ne bloque pas une suppression via `Activity::find()->delete()` ;
- il ne bloque pas un `DELETE` SQL ;
- il ne bloque pas une modification de ligne via SQL ;
- il ne bloque pas une désactivation de la configuration ;
- il ne rend pas la table append-only.

C'est une **protection applicative d'interface**, pas une garantie d'inaltérabilité de la base.

### 5.11 Mécanismes d'intégrité et de vérification

Le plugin signe chaque nouvelle activité avec un HMAC-SHA256 :

- clé : `ACTIVITY_LOG_INTEGRITY_KEY` ; le repli sur `APP_KEY` n'est autorisé qu'en local/test, jamais en production selon la politique BatiPaie du Lot 1E ;
- payload canonique : id, log_name, description, subject, causer, event, properties, attribute_changes, batch_uuid, risk, retention, request_id, ip_address, created_at, updated_at ;
- stockage dans `integrity_hash` ;
- commande `php artisan filament-activity-log:verify-integrity --chunk=500` ;
- commande `filament-activity-log:backfill` pour les enregistrements existants.

Limites :

- **détecte une modification de ligne, pas une suppression** ;
- une ligne supprimée n'a plus de hash à vérifier ;
- un attaquant disposant de la clé HMAC peut recalculer un hash valide ;
- la clé ne doit pas être compromise, ni changée sans procédure ;
- le hash est écrit par un `UPDATE` après l'`INSERT` : cela nécessite un droit `UPDATE` sur la table ;
- si le droit `UPDATE` est refusé, l'activité peut être insérée mais l'écriture du hash échoue ;
- le hash couvre `updated_at` et `retention_hold` : toute modification non re-signée invalide la vérification ;
- il n'y a pas de chaîne de hachage entre lignes, donc pas de détection d'une suppression ou d'un rejeu ;
- un `DELETE` SQL reste indétectable sans sauvegarde ou registre externe.

**Recommandation :** considérer la signature comme un **indice de falsification**, pas comme une preuve d'intégrité. Pour un niveau de preuve supérieur, ajouter un ancrage externe (empreinte quotidienne du journal, stockée hors base) et des droits SQL restrictifs.

### 5.12 Restauration et revert

Les mutations sont **désactivées par défaut** (`mutations.enabled = false`). Les actions correspondantes sont donc masquées.

Si elles sont activées :

- le plugin vérifie la policy de l'activité et du sujet ;
- il bloque les conflits d'écrasement (`block_on_conflict`) ;
- il exclut les attributs sensibles et système ;
- il exécute dans une transaction ;
- il écrit une activité compensatoire ;
- il peut restaurer un enregistrement supprimé via `RestoresActivitySubjects`.

**Pour BatiPaie :** laisser les mutations désactivées. Le journal d'audit ne doit pas être un outil de modification ou de restauration de données métier. Si un besoin de restauration existe, il doit passer par un processus métier dédié, autorisé, testé et audité, pas par l'interface d'audit.

### 5.13 Risques de sécurité du plugin

| Risque | Gravité | Détail | Mitigation |
|---|---|---|---|
| Consultation par défaut | Élevée | `permissions.enabled=false` ⇒ `viewAny`/`view` vrais | Activer les permissions et mapper `audit.view` |
| Widgets non autorisés | Élevée | `canView()` ne vérifie que la config | Désactiver les widgets ou les remplacer |
| Dashboard non autorisé | Élevée | Pas de `canAccess()` observé | Laisser `dashboard.enabled=false` |
| Relation manager non autorisé | Élevée | Pas de contrôle propre observé | Ne pas l'ajouter ou surcharger l'autorisation |
| Policy écrasée | Moyenne | `Gate::policy` du plugin remplace la policy applicative | Utiliser `permissions.custom_authorization` |
| Mutations | Élevée | Revert/restore/delete si activés | `mutations.enabled=false`, `immutable_mode=true` |
| Rédaction stockage | Élevée | Masquage uniquement à l'affichage | `AuditSanitizer` à l'écriture |
| HMAC | Moyenne | Pas de détection de suppression, clé unique | Sauvegardes, ancrage externe, rotation |
| Buffer | Moyenne | Contourne les événements de modèle du plugin | Laisser `buffer.enabled=false` |
| Droits SQL | Élevée | `UPDATE`/`DELETE` possibles sur `activity_log` | Restreindre les grants, séparer les rôles |
| Export | Moyenne | Peut recopier des données sensibles | Permission dédiée, redaction, audit de l'export |
| IP/user-agent | Moyenne | Données personnelles | Anonymiser ou limiter, durée à arbitrer |
| Email causer | Moyenne | Affichage de l'email dans les colonnes | Changer `causer.display_attribute` |
| Titre du sujet | Moyenne | `title_attributes` inclut `email` | Configurer des attributs non sensibles |
| Config désactivable | Élevée | `ACTIVITYLOG_ENABLED=false` arrête tout | Contrôle de santé, test CI, alerte |
| `$guarded=[]` | Élevée | Modèle `Activity` mass-assignable | Ne jamais exposer le modèle, grants SQL |
| Absence de tenant | Moyenne | Pas de `company_id` | Ajouter colonne/scope avant multi-établissement |

### 5.14 Dépendances supplémentaires

Le plugin ajoute `phiki/phiki` et ses dépendances de parsing/coloration. Cela reste proportionné pour un affichage de diffs, mais :

- augmente le nombre de packages ;
- ajoute des composants HTML/sanitizer ;
- doit être couvert par `composer audit` et la veille ;
- ne doit pas devenir une dépendance critique du moteur d'audit lui-même (le moteur reste Spatie).

### 5.15 Problèmes connus et maturité

Au moment de la consultation :

- aucune issue ouverte sur GitHub ;
- aucun avis de sécurité publié ;
- plugin actif (dernier commit 31 octobre 2026) ;
- mais **peu de recul** : création décembre 2025, 32 étoiles, 7 dépendants, un seul mainteneur ;
- la page Filament affiche une santé de 78/100 et une sécurité de 61/100 ;
- la documentation elle-même reconnaît que la signature HMAC ne protège pas contre la suppression et que le mode immutable n'est qu'une protection d'interface.

**Conclusion Partie 3 :** le plugin est fonctionnel et adapté à une interface de consultation, mais il ne doit pas être déployé avec ses valeurs par défaut. Il doit être configuré en lecture seule, derrière une permission dédiée, avec les widgets/dashboard désactivés ou sécurisés, et avec une revue de code versionnée.

---

## 6. Cartographie des ressources métier du dépôt

### 6.1 Ressources réellement présentes

| Ressource | Modèle | Table | Création | Modification | Suppression | Journalisation actuelle |
|---|---|---|---|---|---|---|
| Utilisateur | `App\Models\User` | `users` | `AcceptAdministratorInvitation` | `forceFill(email_verified_at)`, `assignRole`, hash de mot de passe à la création | Aucune dans le code | Aucune |
| Invitation administrateur | `App\Models\AdministratorInvitation` | `administrator_invitations` | `InviteAdministrator` | `forceFill(revoked_at)`, `forceFill(consumed_at)` | Aucune dans le code | Aucune |
| Rôle Spatie | `Spatie\Permission\Models\Role` | `roles` | `RbacSynchronizer::firstOrCreate` | `syncPermissions` | Aucune dans le code | Aucune |
| Permission Spatie | `Spatie\Permission\Models\Permission` | `permissions` | `RbacSynchronizer::firstOrCreate` | Aucune dans le code | Aucune dans le code | Aucune |
| Session | Framework | `sessions` | Laravel | Laravel | Laravel | Aucune |
| Token Sanctum | Framework | `personal_access_tokens` | Aucun endpoint d'émission | Laravel | Laravel | Aucune |
| Reset password | Framework | `password_reset_tokens` | Aucun flux applicatif | Laravel | Laravel | Aucune |

### 6.2 Points d'entrée de création

| Ressource | Chemin | Mécanisme |
|---|---|---|
| `User` | `AcceptAdministratorInvitation::handle()` | `User::query()->create([...])` |
| `AdministratorInvitation` | `InviteAdministrator::handle()` | `AdministratorInvitation::query()->create([...])` |
| `Role` | `RbacSynchronizer::synchronize()` | `Role::query()->firstOrCreate([...])` |
| `Permission` | `RbacSynchronizer::ensurePermissions()` | `Permission::query()->firstOrCreate([...])` |

### 6.3 Points d'entrée de modification

| Ressource | Chemin | Mécanisme |
|---|---|---|
| `User` | `AcceptAdministratorInvitation::handle()` | `forceFill(['email_verified_at' => now()])->save()` |
| `User` | `AcceptAdministratorInvitation::handle()` | `assignRole($administratorRole)` |
| `AdministratorInvitation` | `InviteAdministrator::handle()` | `forceFill(['revoked_at' => $now])->save()` |
| `AdministratorInvitation` | `AcceptAdministratorInvitation::handle()` | `forceFill(['consumed_at' => now()])->save()` |
| `AdministratorInvitation` | `InviteAdministrator::handle()` (échec mail) | `forceFill(['revoked_at' => now()])->save()` |
| `Role` | `RbacSynchronizer::synchronize()` | `syncPermissions([...])` |

### 6.4 Points d'entrée de suppression

**Aucune suppression applicative n'est présente dans `app/`.** Il n'y a :

- aucun `delete()` sur `User` ;
- aucun `delete()` sur `AdministratorInvitation` ;
- aucun `forceDelete()` ;
- aucune suppression de masse ;
- aucune commande de purge.

Les suppressions possibles sont donc :

- des suppressions manuelles en base ou via un futur outil ;
- des suppressions de rôles/permissions hors matrice (le synchroniseur ne supprime pas les éléments non gérés) ;
- des suppressions framework (sessions, tokens expirés) non auditées.

### 6.5 Opérations SQL directes

Le code applicatif ne contient **aucune écriture SQL directe**. On relève seulement :

- `whereRaw('LOWER(email) = ?', [...])` en lecture, dans `InviteAdministrator` et `AcceptAdministratorInvitation` ;
- des `lockForUpdate()` en lecture/verrouillage.

Les migrations utilisent le schema builder, ce qui est hors flux applicatif.

### 6.6 Opérations de masse

**Aucune opération de masse métier** n'existe. `syncPermissions()` est une opération Spatie de synchronisation de pivot, pas une mise à jour de masse Eloquent sur un modèle métier. Elle ne déclenche pas de journalisation Activitylog.

### 6.7 Traitements automatisés

| Traitement | Existence | Utilisateur authentifié | Journalisation actuelle |
|---|---|---|---|
| Commande `batipaie:admin-invite` | Oui | Non | Aucune |
| Seeder `RbacSeeder` | Oui | Non | Aucune |
| Jobs | Aucun job applicatif | — | — |
| Scheduler | Aucune tâche applicative | — | — |
| Notifications | Envoi synchrone d'invitation | Non | Aucune |
| Migrations | Oui | Non | Aucune |

### 6.8 Opérations exécutées sans utilisateur authentifié

1. `batipaie:admin-invite` : création d'invitation depuis la console.
2. `RbacSeeder` / `RbacSynchronizer` : création et synchronisation des rôles/permissions.
3. `GET/POST /admin/invitation/{token}` : action portée par un jeton, sans session authentifiée.
4. Les migrations et seeders.
5. Les éventuels futurs workers de queue (aucun aujourd'hui).
6. Le health check `/up` (pas de donnée métier).

### 6.9 Zones non couvertes par une journalisation automatique

Même après ajout de `LogsActivity` sur `User` et `AdministratorInvitation` :

- `assignRole()` et `syncPermissions()` : pivots Spatie, pas des événements de modèle des modèles métier ;
- la création de rôles/permissions par `firstOrCreate` : modèle Spatie, pas de trait `LogsActivity` par défaut ;
- les invitations : création et consommation doivent être des événements métier explicites, pas seulement des `created`/`updated` ;
- les échecs d'envoi d'email : seul un log Monolog existe ;
- les connexions/déconnexions/échecs de connexion : aucun listener d'audit ;
- les lectures et exports : aucun événement ;
- les suppressions manuelles en base : invisibles.

### 6.10 Conclusion de la cartographie

Le dépôt ne contient **aucune ressource métier de paie**. Toute conception qui prétendrait cartographier des salariés, contrats, bulletins ou chantiers serait une extrapolation. Le Lot 1 d'audit doit donc :

- instrumenter le socle existant (utilisateurs, invitations, RBAC) ;
- poser un **modèle d'instrumentation réutilisable** pour les futurs modules ;
- ne pas prétendre couvrir des modules inexistants.

---

## 7. Couverture réelle des opérations

### 7.1 Matrice de couverture

| Opération | Aujourd'hui | Spatie seul | Spatie + plugin | Nécessite une couche BatiPaie |
|---|---|---|---|---|
| `created` Eloquent | Non | Oui | Oui | Filtrage/whitelist |
| `updated` Eloquent | Non | Oui | Oui | Filtrage/whitelist |
| `deleted` Eloquent | Non | Oui | Oui | Filtrage/whitelist |
| `restored` SoftDeletes | Non | Oui | Oui | Filtrage/whitelist |
| `forceDelete` | Non | Non par défaut | Non par défaut | Oui |
| Mise à jour de masse | Non | Non | Non | Oui |
| Suppression de masse | Non | Non | Non | Oui |
| `insert`/`upsert` | Non | Non | Non | Oui |
| SQL direct | Non | Non | Non | Oui |
| `saveQuietly` / `withoutEvents` | Non | Non | Non | Oui + revue de code |
| Attribution utilisateur | Non | Oui | Oui | Contexte |
| Attribution système | Non | Partielle (`null`) | Partielle (`null`) | Oui |
| Avant/après | Non | Oui | Oui | Whitelist |
| Événements métier | Non | Oui (manuel) | Oui (manuel) | Oui (enum/catalogue) |
| Rôles/permissions | Non | Non | Non | Oui |
| Authentification | Non | Non | Non | Oui (listeners) |
| Lectures sensibles | Non | Non | Non | Oui |
| Exports | Non | Non | Partiel (UI) | Oui (événement + permission) |
| Consultations du journal | Non | Non | Partiel (UI) | Oui (audit.view) |
| Suppression du journal | Non | Non | UI conditionnelle | Droits SQL + politique |
| Preuve d'intégrité | Non | Non | HMAC partiel | Ancrage/DB |
| Isolation société/établissement | Non | Non | Scope possible | Colonne/scope |
| Rétention/legal hold | Non | Purge simple | Hold UI | Décision + outillage |

### 7.2 Interprétation

- **Spatie seul** couvre correctement le socle CRUD Eloquent, mais pas les opérations critiques de conformité.
- **Le plugin** ajoute une interface de lecture et des métadonnées, mais ne change pas la couverture des événements.
- **La couche BatiPaie** est indispensable pour :
  - les événements métier explicites ;
  - les opérations non Eloquent ;
  - le filtrage à l'écriture ;
  - l'attribution système ;
  - la politique de conservation ;
  - l'isolation et les permissions ;
  - les tests de non-régression.

### 7.3 Rappel méthodologique

Les observers Eloquent, les traits `LogsActivity` et les hooks de modèle ne constituent **pas** une couverture exhaustive. Ils couvrent les mutations de modèle qui passent par Eloquent et qui déclenchent des événements. Toute opération qui contourne Eloquent doit être instrumentée explicitement.

---

## 8. Analyse des données sensibles

### 8.1 Champs réellement présents

| Table / champ | Classification | Journalisation recommandée |
|---|---|---|
| `users.name` | Donnée personnelle | Autorisable, éventuellement masquée |
| `users.email` | Donnée personnelle | Autorisable avec accès restreint, ou masquée |
| `users.email_verified_at` | Donnée technique/personnelle | Autorisable |
| `users.password` | Secret d'authentification | **Interdit** |
| `users.remember_token` | Secret d'authentification | **Interdit** |
| `administrator_invitations.name` | Donnée personnelle | Autorisable |
| `administrator_invitations.email` | Donnée personnelle | Autorisable avec restriction |
| `administrator_invitations.token_hash` | Secret (hash de jeton) | **Interdit** |
| `administrator_invitations.expires_at` | Donnée technique | Autorisable |
| `administrator_invitations.consumed_at` | Donnée technique | Autorisable |
| `administrator_invitations.revoked_at` | Donnée technique | Autorisable |
| `sessions.payload` | Données de session, potentiellement sensibles | **Interdit** |
| `sessions.ip_address` | Donnée personnelle | À limiter/anonymiser |
| `sessions.user_agent` | Donnée personnelle/technique | À limiter |
| `personal_access_tokens.token` | Secret (hash) | **Interdit** |
| `personal_access_tokens.abilities` | Donnée de sécurité | Autorisable |
| `password_reset_tokens.token` | Secret | **Interdit** |
| `roles.name`, `permissions.name` | Donnée de sécurité | Autorisable |
| Pivots `model_has_roles`, `role_has_permissions` | Donnée de sécurité | Autorisable sous forme d'événement |

**Aucun IBAN, BIC, NIR, numéro de sécurité sociale, donnée bancaire, donnée médicale, montant de paie ou bulletin n'est présent dans le dépôt actuel.** Il ne faut pas inventer ces champs dans le rapport de couverture ; en revanche, la stratégie de filtrage doit être conçue pour les accueillir.

### 8.2 Stratégie de liste blanche

Principe : **refus par défaut**. Un attribut n'est journalisé que s'il figure dans un catalogue explicite.

Structure recommandée (à créer au Lot 1, pas dans ce lot) :

```text
Config/Audit/audit_fields.php
[
    App\Models\User::class => [
        'created' => ['name', 'email', 'email_verified_at'],
        'updated' => ['name', 'email', 'email_verified_at'],
        'deleted' => ['name', 'email'],
    ],
    App\Models\AdministratorInvitation::class => [
        'created' => ['name', 'email', 'expires_at'],
        'updated' => ['consumed_at', 'revoked_at'],
        'deleted' => ['name', 'email', 'expires_at'],
    ],
    // ...
]
```

Le catalogue doit être :

- **par modèle** ;
- **par événement** ;
- **exhaustif** : un modèle sans entrée ne journalise aucun attribut ;
- **testé** : un champ ajouté au modèle ne doit pas être journalisé par accident.

### 8.3 Liste noire absolue

Indépendamment de la liste blanche, `AuditSanitizer` doit supprimer récursivement :

- `password`, `password_confirmation`, `current_password`, `new_password` ;
- `remember_token`, `token`, `api_token`, `access_token`, `refresh_token`, `secret`, `api_key`, `private_key` ;
- `token_hash`, `authorization`, `cookie`, `set-cookie` ;
- `iban`, `bic`, `bank_account`, `rib`, `account_number`, `routing_number` ;
- `nir`, `social_security_number`, `ssn` ;
- toute clé contenant `medical`, `health`, `diagnosis`, `sick_leave` ;
- tout champ marqué `sensitive` dans le catalogue.

La liste noire s'applique :

- aux `attribute_changes.attributes` ;
- aux `attribute_changes.old` ;
- aux `properties` (y compris les tableaux imbriqués) ;
- aux descriptions si elles contiennent des valeurs interpolées ;
- aux exports et à l'interface (défense en profondeur).

### 8.4 Propriétés libres et descriptions

`withProperties()` est un point d'entrée non typé. Il faut :

- interdire les tableaux de données métier non filtrés ;
- imposer des clés connues (`reason`, `source`, `request_id`, `export_format`, etc.) ;
- ne jamais y mettre un dump de modèle, un payload HTTP, un contenu de fichier ou un message d'erreur brut ;
- revoir les descriptions pour éviter l'interpolation de données personnelles.

### 8.5 Rédaction d'affichage

La rédaction du plugin est utile pour :

- masquer les secrets dans l'UI ;
- éviter un copier-coller accidentel ;
- protéger les exports Filament.

Elle ne remplace pas le filtrage d'écriture. Il faut considérer qu'un champ non filtré est **compromis** dès qu'il est en base, car :

- un administrateur SQL peut le lire ;
- une sauvegarde peut le contenir ;
- un export peut le recopier ;
- une faille applicative peut l'exposer.

### 8.6 Conservation

Aucune durée légale ne doit être inventée. Les durées suivantes sont des **décisions à arbitrer** :

- durée de conservation du journal d'audit ;
- durée de conservation des événements de sécurité ;
- durée de conservation des données personnelles dans le journal ;
- sort des données personnelles après expiration ;
- procédure d'anonymisation ou de suppression ;
- conservation des preuves d'intégrité ;
- conservation des exports.

Le package Spatie supprime par défaut après 365 jours. Ce n'est pas une recommandation BatiPaie.

---

## 9. Analyse des risques de sécurité

### 9.1 Risques et mitigations

| # | Risque | Impact | Probabilité | Mitigation |
|---|---|---|---|---|
| 1 | Consultation large par défaut du plugin | Fuite de données RH/paie | Élevée si plugin non configuré | `permissions.enabled=true`, `audit.view` |
| 2 | Widgets visibles sans permission | Fuite de données | Élevée | Désactiver les widgets ou surcharger `canView()` |
| 3 | Dashboard sans `canAccess()` | Fuite de données | Moyenne | Laisser `dashboard.enabled=false` |
| 4 | Relation manager non autorisé | Fuite d'historique | Moyenne | Ne pas l'ajouter ou surcharger l'autorisation |
| 5 | Policy `Activity` écrasée | Contournement d'une policy maison | Moyenne | `permissions.custom_authorization` |
| 6 | Mutations activées | Modification/restauration de données via l'audit | Élevée | `mutations.enabled=false` |
| 7 | Rédaction uniquement UI | Données sensibles en base | Élevée | `AuditSanitizer` à l'écriture |
| 8 | `logAll`/`logFillable` | Mot de passe/token journalisé | Élevée | `logOnly` + liste noire |
| 9 | `$hidden` mal interprété | Faux sentiment de sécurité | Moyenne | Tests de non-fuite |
| 10 | Buffer activé | Intégrité/risque non calculés | Moyenne | `buffer.enabled=false` |
| 11 | HMAC sans ancrage | Suppression/modification indétectable | Moyenne | Sauvegardes, ancrage externe |
| 12 | Clé HMAC dans `APP_KEY` | Rotation difficile, compromission | Moyenne | Clé dédiée + rotation |
| 13 | `UPDATE`/`DELETE` SQL | Journal falsifiable | Élevée | Grants restrictifs, rôles DB |
| 14 | `ACTIVITYLOG_ENABLED=false` | Audit silencieusement arrêté | Élevée | Contrôle de santé, test CI |
| 15 | `disableLogging()` | Contournement ponctuel | Moyenne | Revue de code, tests, grep CI |
| 16 | Absence de tenant | Fuite inter-établissement | Moyenne | `company_id` + scope |
| 17 | IP/user-agent | Données personnelles | Moyenne | Anonymisation, accès restreint |
| 18 | Email dans les colonnes | Données personnelles | Moyenne | `causer.display_attribute` non sensible |
| 19 | Export | Fuite en masse | Moyenne | `audit.export`, redaction, journalisation |
| 20 | Journalisation du journal | Boucle/volume | Faible | Ne pas mettre `LogsActivity` sur `Activity` |
| 21 | Événements système sans acteur | Imputabilité impossible | Élevée | `AuditContext` + acteur système |
| 22 | Jobs sans contexte | Attribution erronée | Moyenne | Contexte par job, tests |
| 23 | Suppression de masse | Aucune trace | Élevée | `AuditManager` explicite |
| 24 | Rôles/permissions non audités | Escalade invisible | Élevée | Événements explicites |
| 25 | Auth non auditée | Intrusion invisible | Élevée | Listeners login/logout/failed |

### 9.2 Protection contre la modification des traces

Niveaux possibles, du plus faible au plus fort :

1. **Applicatif** : aucune action UI de modification ; policy ; `immutable_mode`.
2. **Modèle** : `Activity` non exposé, pas de route de mise à jour, pas de `Fillable` large.
3. **SQL** : utilisateur applicatif avec `INSERT`/`SELECT` seulement sur `activity_log`.
4. **Base** : triggers ou vues append-only, révocation des droits de modification.
5. **Externe** : export signé, empreinte quotidienne, stockage WORM ou coffre-fort.

Le plugin impose un `UPDATE` après `INSERT` pour `integrity_hash`, `risk_score`, `risk_level`, `request_id`, `ip_address`. Un droit strictement `INSERT`/`SELECT` **casse** donc son enrichissement. Il faut choisir :

- soit accepter un `UPDATE` limité aux colonnes de métadonnées (grant colonne par colonne) ;
- soit désactiver l'intégrité/risque du plugin et calculer les métadonnées avant l'insertion ;
- soit utiliser une table d'audit séparée, append-only, avec un signeur externe.

C'est une décision d'architecture à arbitrer avant le Lot 1.

### 9.3 Protection contre la suppression des traces

- `Activity` n'utilise pas `SoftDeletes` : une suppression est physique.
- `immutable_mode` masque l'action UI, mais ne bloque pas SQL.
- `retention_hold` protège de la purge du plugin, pas d'un `DELETE`.
- Les sauvegardes de base sont indispensables.
- Un ancrage externe (hash de lot) permet de détecter une suppression de lignes, mais pas de la récupérer.
- Les droits SQL doivent retirer `DELETE` à l'utilisateur applicatif.

### 9.4 Contrôle des accès

Modèle recommandé :

| Permission | Usage | Rôles pressentis |
|---|---|---|
| `audit.view` | Consulter le journal | Administrateur, Direction, Responsable RH, Gestionnaire paie selon périmètre |
| `audit.export` | Exporter | Administrateur, Direction, Expert-comptable selon décision |
| `audit.view_sensitive` | Voir les valeurs non masquées | Décision métier, probablement très restreint |
| `audit.manage_retention` | Poser/lever un hold | Compliance/administrateur, si activé |
| `audit.verify` | Lancer la vérification d'intégrité | Administrateur technique |

Ces permissions ne doivent être créées qu'avec la fonctionnalité, conformément à `app/Enums/PermissionName.php` et `docs/security/RBAC.md`. Aucune ne doit être ajoutée par le présent lot.

### 9.5 Intégration Spatie Permission

Le mapping plugin → RBAC doit être explicite :

```php
'permissions' => [
    'enabled' => true,
    'view_any' => 'audit.view',
    'view' => 'audit.view',
    'export' => 'audit.export',
    'hold' => 'audit.manage_retention',
    'create' => null,
    'update' => null,
    'delete' => null,
    'delete_any' => null,
    'prune' => null,
    'restore' => null,
    'force_delete' => null,
],
```

Les abilities de mutation restent `null` ou désactivées, et `mutations.enabled=false`.

### 9.6 Identification des événements système

Convention recommandée :

- un acteur technique `System` ou un causer `null` ;
- des propriétés obligatoires : `source` (`console`, `queue`, `scheduler`, `migration`, `http`), `actor_type` (`user`, `system`), `command` ou `job`, `request_id` si disponible ;
- un `log_name` distinct (`system` ou `audit-system`) ;
- un événement explicite (`system.invitation.created`, `system.rbac.synchronized`) ;
- jamais un événement système présenté comme un `created` utilisateur.

### 9.7 Journalisation transactionnelle

Décision recommandée :

- **changements métier** : même transaction que la mutation, pour garantir l'atomicité ;
- **événements de sécurité** : écriture indépendante après l'événement, pour ne pas perdre une tentative refusée ou un export ;
- **exports** : journaliser après succès, avec le format, le périmètre et le demandeur ;
- **échecs** : journaliser l'échec sans exposer le secret ou le payload.

Il faut éviter un unique mode « tout dans la transaction » ou « tout après commit » : le comportement doit dépendre de la catégorie d'événement.

### 9.8 Prévention de la falsification

- HMAC par ligne (plugin) : détecte la modification, pas la suppression.
- Chaîne de hash par lot : détecte la suppression/insertion si un ancrage externe conserve la tête de chaîne.
- Export signé quotidien : preuve externe.
- Droits SQL : empêchent la modification directe.
- Séparation des rôles : l'application n'a pas les droits de purge.
- Journalisation des actions sur le journal lui-même (hold, purge, export) : méta-audit.
- Tests de vérification après chaque opération sensible.

### 9.9 Politique de conservation et purge

À arbitrer, sans invention de durée légale :

- catégories de journaux (métier, sécurité, accès, export, système) ;
- durée par catégorie ;
- purge automatique ou manuelle ;
- validation à deux personnes pour une purge ;
- conservation des preuves ;
- anonymisation des données personnelles ;
- sort des journaux en cas de départ d'un salarié ;
- procédure d'export pour une demande RGPD ;
- sauvegarde et restauration.

---

## 10. Architecture cible proposée

### 10.1 Vue d'ensemble

```text
                         ┌─────────────────────────────────────┐
                         │        Interface Filament           │
                         │  ActivityLogResource (lecture seule)│
                         │  + permission audit.view            │
                         └──────────────────┬──────────────────┘
                                            │ lecture
                         ┌──────────────────▼──────────────────┐
                         │      Plugin Ali Harb 2.0.0          │
                         │  filtres, timeline, infolist         │
                         │  widgets/dashboard désactivés        │
                         │  mutations désactivées               │
                         └──────────────────┬──────────────────┘
                                            │ modèle Activity
                         ┌──────────────────▼──────────────────┐
                         │      Spatie Activitylog 5.1.4       │
                         │  table activity_log + colonnes       │
                         │  LogsActivity + activity()           │
                         └──────────────────┬──────────────────┘
                                            │ écriture
        ┌───────────────────────────────────▼───────────────────────────────────┐
        │                        Couche métier BatiPaie                          │
        │  AuditLogger (contrat) → AuditManager → AuditContext → AuditSanitizer  │
        │  AuditEventType (catalogue)                                            │
        └───────────────────────────────────┬───────────────────────────────────┘
                                            │ appels explicites
        ┌───────────────────────────────────▼───────────────────────────────────┐
        │  Actions / Services / Filament / Console / Jobs / Listeners            │
        │  User, AdministratorInvitation, RBAC, auth, exports, lectures sensibles│
        └───────────────────────────────────────────────────────────────────────┘
```

### 10.2 Composants proposés et pertinence

| Composant | Rôle | Pertinence | Remarque |
|---|---|---|---|
| `app/Contracts/AuditLogger.php` | Contrat d'écriture d'audit | **Oui** | Découple les domaines du package ; facilite les tests |
| `app/Services/Audit/AuditManager.php` | Orchestration Spatie | **Oui** | Centralise log name, event, causer, contexte, transaction |
| `app/Services/Audit/AuditContext.php` | Contexte d'exécution | **Oui** | Acteur, source, request_id, commande/job, société |
| `app/Services/Audit/AuditSanitizer.php` | Filtrage centralisé | **Oui, bloquant** | Whitelist + liste noire, récursif, à l'écriture |
| `app/Enums/AuditEventType.php` | Catalogue d'événements | **Oui** | Évite les chaînes magiques, documente les événements |

Ces fichiers **ne sont pas créés dans ce lot**. Leur pertinence est évaluée, pas leur implémentation.

### 10.3 Contrat `AuditLogger`

Proposition de contrat minimal :

```php
interface AuditLogger
{
    public function log(
        AuditEventType $event,
        Model $subject,
        array $properties = [],
        ?string $description = null,
    ): void;

    public function logSystemEvent(
        AuditEventType $event,
        array $properties = [],
        ?string $description = null,
    ): void;

    public function logAccess(
        AuditEventType $event,
        string $resource,
        array $properties = [],
    ): void;
}
```

Principes :

- pas de dépendance à Filament dans le contrat ;
- pas de tableau d'attributs non filtré : le manager applique le sanitizer ;
- pas de `subject` obligatoire pour les événements d'accès ou système ;
- retour void ou `Activity` selon le besoin de test ; éviter de fuiter le modèle Spatie dans les domaines.

### 10.4 `AuditManager`

Responsabilités :

- choisir le `log_name` (`audit`, `security`, `system`, `access`) ;
- résoudre l'acteur depuis `AuditContext` ;
- appeler `AuditSanitizer` sur les changements et les propriétés ;
- construire l'activité via `activity()` ;
- décider de la transaction (métier vs sécurité) ;
- refuser les événements non catalogués ;
- ne jamais exposer le modèle `Activity` aux appelants ;
- journaliser les échecs de journalisation sans perdre l'opération métier, selon la criticité.

### 10.5 `AuditContext`

Champs recommandés :

```text
actor_type        user | system | anonymous
actor_id          identifiant utilisateur ou null
actor_label       libellé non sensible
source            http | console | queue | scheduler | migration | test
request_id        UUID de requête
command           nom de commande console
job               classe du job
company_id        société/établissement concerné
ip_address        si collecté
user_agent        si collecté
occurred_at       horodatage
```

Contraintes :

- binding `scoped` pour éviter les fuites entre requêtes ;
- réinitialisation explicite au début de chaque job ;
- pas de contexte global statique non nettoyé ;
- tests en console, queue et HTTP ;
- aucune donnée secrète dans le contexte.

### 10.6 `AuditSanitizer`

Responsabilités :

- charger le catalogue de champs autorisés par modèle/événement ;
- si le modèle n'est pas catalogué, retourner un tableau vide (refus par défaut) ;
- supprimer récursivement les clés de la liste noire ;
- filtrer `attribute_changes.attributes`, `attribute_changes.old`, `properties`, et les descriptions ;
- refuser les valeurs de type objet non sérialisable ;
- tronquer les valeurs trop longues ;
- journaliser un avertissement si un champ non catalogué est détecté en debug/test ;
- être testé avec des tableaux imbriqués, des JSON, des objets et des valeurs null.

Point d'application recommandé :

1. **obligatoire** : `AuditManager` avant `withChanges()` / `withProperties()` ;
2. **défense en profondeur** : callback global `Activity::beforeLogging()` pour les journalisations automatiques `LogsActivity` ;
3. **interdiction** : ne pas compter sur la rédaction du plugin.

### 10.7 `AuditEventType`

Catalogue initial proposé, à valider avec BP-192 :

```text
created
updated
deleted
restored
force_deleted
system.invitation.created
system.invitation.consumed
system.invitation.revoked
system.rbac.synchronized
system.role.assigned
system.role.removed
system.permission.changed
security.login.succeeded
security.login.failed
security.logout
security.access.denied
audit.viewed
audit.exported
audit.integrity.verified
audit.retention.held
audit.retention.released
payroll.validated
payroll.closed
payroll.recalculated
```

Les événements `payroll.*` ne doivent être ajoutés qu'avec le module de paie correspondant. Le présent lot ne doit pas les créer.

### 10.8 Chemins d'écriture

| Chemin | Mécanisme | Stratégie |
|---|---|---|
| CRUD Eloquent simple | `LogsActivity` + `logOnly` | Automatique, filtré |
| Événement métier | `AuditManager::log()` | Explicite |
| Opération de masse | `AuditManager` + compteur | Explicite, avant/après |
| SQL direct | `AuditManager` | Explicite, obligatoire |
| Console | `AuditManager::logSystemEvent()` | Acteur système |
| Job | `AuditContext` + `AuditManager` | Contexte par job |
| Auth | Listeners Laravel | Événements dédiés |
| RBAC | `RbacSynchronizer` + listeners Spatie | Événements dédiés |
| Export | Contrôleur/action Filament | Événement après succès |
| Lecture sensible | Policy/contrôleur | Événement d'accès |

### 10.9 Chemins de lecture

- Interface Filament : ressource `ActivityLogResource` en lecture seule.
- Permission : `audit.view`.
- Export : `audit.export`, désactivé par défaut.
- Widgets/dashboard : désactivés ou sécurisés.
- Relation manager : non exposé sans autorisation.
- API : aucun endpoint d'audit au Lot 1.
- Accès direct base : restreint aux DBAs, hors application.

### 10.10 Autorisation

- Ne pas compter sur `visible()` / `hidden()` Filament.
- Ne pas utiliser `skipAuthorization()`.
- La ressource doit être couverte par la policy du plugin configurée vers `audit.view`.
- Les pages personnalisées doivent surcharger `canAccess()`.
- Les widgets doivent surcharger `canView()`.
- Le relation manager doit surcharger `canViewForRecord()`.
- Les actions d'export et de purge doivent vérifier une permission distincte.
- Les tests doivent couvrir positif et négatif pour chaque rôle.

### 10.11 Compatibilité avec le plugin

Le plugin **n'impose pas** une structure incompatible avec l'architecture proposée :

- il lit le modèle Spatie configuré ;
- il accepte un modèle `Activity` personnalisé ;
- il accepte des colonnes supplémentaires ;
- il accepte un scope de requête ;
- il accepte un authorizer personnalisé.

Contraintes à respecter :

1. ne pas définir de policy applicative sur `Activity` (elle serait écrasée) ;
2. ne pas activer le buffer ;
3. ne pas activer les mutations ;
4. ne pas compter sur la rédaction pour le stockage ;
5. ne pas activer les widgets/dashboard sans autorisation ;
6. ne pas insérer les activités en masse hors modèle ;
7. garder la clé d'intégrité stable ;
8. vérifier l'ordre des migrations.

---

## 11. Limites et adaptations nécessaires

| # | Limite | Adaptation | Impact |
|---|---|---|---|
| 1 | Le journal ne couvre que les événements Eloquent | `AuditManager` + appels explicites | Développement supplémentaire |
| 2 | Pas de couverture des opérations de masse | API d'audit dédiée, comptage | Tests spécifiques |
| 3 | Pas de couverture SQL directe | Revue de code + audit explicite | Discipline |
| 4 | Pas d'acteur système natif | `AuditContext` + acteur `system` | Convention |
| 5 | Pas de tenant/société | Colonne `company_id` + scope | Migration |
| 6 | Pas de `reason`/justification | Colonne ou propriété obligatoire | Migration/convention |
| 7 | Pas de `request_id` | Colonne plugin + middleware | Config |
| 8 | Pas de Preuve d'intégrité suffisante | HMAC + ancrage externe | Infrastructure |
| 9 | Suppression non détectée | Sauvegardes + ancrage | Infrastructure |
| 10 | Rédaction uniquement UI | `AuditSanitizer` à l'écriture | Bloquant |
| 11 | Permissions plugin désactivées par défaut | `permissions.enabled=true` + mapping | Bloquant |
| 12 | Widgets non autorisés | Désactiver/remplacer | Bloquant |
| 13 | Dashboard non autorisé | Laisser désactivé | Bloquant |
| 14 | Relation manager non autorisé | Ne pas l'ajouter/surcharger | Bloquant |
| 15 | Policy `Activity` écrasée | `custom_authorization` | Architecture |
| 16 | Buffer incompatible avec l'intégrité | Laisser désactivé | Config |
| 17 | Mutations dangereuses | Laisser désactivées + immutable | Config |
| 18 | Export potentiellement sensible | Permission + redaction + audit | Config |
| 19 | IP/user-agent personnels | Anonymiser/limiter | RGPD |
| 20 | Email affiché | Changer les attributs d'affichage | Config |
| 21 | `ACTIVITYLOG_ENABLED=false` | Contrôle de santé + test CI | Exploitation |
| 22 | `$guarded=[]` sur `Activity` | Ne pas exposer, grants SQL | Sécurité |
| 23 | Plugin jeune/mono-mainteneur | Version épinglée + revue + veille | Risque |
| 24 | Dépendances supplémentaires | `composer audit`, politique de veille | Maintenance |
| 25 | Absence de tests d'intégration | Lot 1 avec tests Pest | Qualité |

### 11.1 Adaptation sur le modèle `Activity`

Créer un modèle applicatif `App\Models\Activity` qui :

- étend `Spatie\Activitylog\Models\Activity` ;
- implémente le contrat Spatie ;
- fixe la table `activity_log` ;
- ajoute les colonnes BatiPaie (`company_id`, `reason`, éventuellement `correlation_id`) ;
- ne réactive pas `LogsActivity` sur lui-même (éviter la récursion) ;
- reste non exposé aux domaines.

La migration BatiPaie devra être ajoutée **après** la migration Spatie et la migration du plugin, ou fusionnée dans une migration unique revue.

### 11.2 Adaptation sur la configuration Spatie

```php
// config/activitylog.php
'enabled' => env('ACTIVITYLOG_ENABLED', true),
'clean_after_days' => null, // ou valeur arbitrée, pas 365 par défaut
'default_log_name' => 'audit',
'default_except_attributes' => [
    'password',
    'password_confirmation',
    'remember_token',
    'token',
    'token_hash',
    'secret',
],
'buffer' => [
    'enabled' => false, // obligatoire avec le plugin
],
```

Ne pas mettre `clean_after_days` à une valeur non arbitrée. La purge doit être une commande planifiée, contrôlée, avec sauvegarde et journalisation.

### 11.3 Adaptation sur la configuration du plugin

```php
'permissions' => [
    'enabled' => true,
    'view_any' => 'audit.view',
    'view' => 'audit.view',
    'export' => 'audit.export',
    'hold' => 'audit.manage_retention',
    'allow_export_when_disabled' => false,
],
'mutations' => [
    'enabled' => false,
],
'privacy' => [
    'immutable_mode' => true,
],
'widgets' => [
    'enabled' => false,
],
'dashboard' => [
    'enabled' => false,
],
'integrity' => [
    'enabled' => true,
    'key' => env('ACTIVITY_LOG_INTEGRITY_KEY'),
],
'auto_context' => [
    'enabled' => true,
    'capture_ip' => true,
    'anonymize_ip' => false, // à arbitrer
    'capture_browser' => true, // à arbitrer
],
```

Les widgets pourront être réactivés plus tard avec des composants BatiPaie autorisés.

### 11.4 Adaptation sur les droits SQL

Scénarios :

- **Lecture/écriture applicative** : `SELECT`, `INSERT` sur `activity_log` ;
- **Métadonnées plugin** : `UPDATE` sur `integrity_hash`, `risk_score`, `risk_level`, `request_id`, `ip_address`, `retention_hold` ;
- **Purge** : rôle séparé, jamais utilisé par l'application web ;
- **Vérification** : `SELECT` seulement ;
- **Sauvegarde** : rôle dédié.

Si l'on veut un vrai append-only, il faut désactiver l'enrichissement post-insertion du plugin ou utiliser un signeur externe. C'est une décision à arbitrer.

### 11.5 Adaptation sur les tests

Tests indispensables au Lot 1 :

- création d'un `User` : `password` et `remember_token` absents du journal ;
- création/consommation d'invitation : `token_hash` absent ;
- modification de rôle : événement présent, avec acteur ;
- opération console : acteur système, source `console` ;
- opération job : acteur système, source `queue`, contexte réinitialisé ;
- opération de masse : événement explicite avec compteur ;
- export : permission refusée/acceptée, événement journalisé ;
- consultation : `audit.view` refusée/acceptée ;
- widget/dashboard : invisibles sans permission ;
- relation manager : refusé sans permission ;
- intégrité : hash valide après création, invalide après `UPDATE` SQL contrôlé ;
- suppression : détectée par l'ancrage externe (si implémenté) ;
- rollback : l'activité métier est annulée avec la transaction ;
- échec de journalisation : l'opération métier reste cohérente selon la criticité ;
- buffer désactivé : métadonnées présentes ;
- `ACTIVITYLOG_ENABLED=false` : test de garde.

---

## 12. Décisions métier restant à arbitrer

Aucune de ces décisions ne doit être tranchée par l'IA seule. Elles doivent être validées par la direction, le métier paie/RH, le DPO ou le conseil juridique selon le sujet.

1. **Périmètre des événements à journaliser** : tout changement de données, ou seulement les actions sensibles et les validations ?
2. **Niveau de détail** : valeur avant/après complète, ou seulement la liste des champs modifiés ?
3. **Données personnelles dans le journal** : nom/email en clair, masqués, ou pseudonymisés ?
4. **Accès au journal** : qui peut consulter ? Qui peut exporter ? Qui peut voir les valeurs non masquées ?
5. **Isolation** : le journal doit-il être filtré par société/établissement dès le Lot 1, ou seulement lors du multi-établissement ?
6. **Événements de lecture** : faut-il journaliser chaque consultation d'un bulletin ou d'une donnée de paie, ou seulement les consultations sensibles/exports ?
7. **Événements d'authentification** : conserver les connexions réussies, les échecs, les déconnexions, les changements de mot de passe ?
8. **Événements RBAC** : journaliser les changements de rôles/permissions, et à quel niveau de détail ?
9. **Événements système** : quel acteur technique, quel libellé, quel log name ?
10. **Durée de conservation** : par catégorie, avec justification ; ne pas reprendre 365 jours par défaut.
11. **Purge** : automatique ou manuelle ? Double validation ? Sauvegarde préalable ?
12. **Legal hold** : nécessaire ? Qui peut le poser/lever ? Le plugin suffit-il ou faut-il un processus externe ?
13. **Niveau de preuve d'intégrité** : HMAC seul, chaîne de hash, ancrage externe, WORM ?
14. **Droits SQL** : accepte-t-on un `UPDATE` applicatif sur la table d'audit pour les métadonnées du plugin ?
15. **Export** : autorisé ? Par qui ? Format ? Chiffré ? Journalisé ?
16. **Consultation par l'expert-comptable externe** : accès limité à quelle période, quelles données, quel journal ?
17. **RGPD** : procédure d'accès, de rectification, d'effacement et d'anonymisation du journal.
18. **Données de paie futures** : quels champs seront considérés comme sensibles (IBAN, NIR, montants, bulletin) et donc interdits ou masqués.
19. **Journalisation des échecs** : conserve-t-on les tentatives refusées ? Combien de temps ? Avec quelles données ?
20. **Responsable du journal** : qui vérifie l'intégrité, qui consulte les alertes, qui décide d'une purge ?

---

## 13. Proposition de découpage des prochains lots

> **Le Lot 1 n'est pas commencé.** Ce découpage est une proposition, pas une exécution.

### Lot 1A — Socle technique et schéma

- installer `spatie/laravel-activitylog` 5.1.4 et `alizharb/filament-activity-log` 2.0.0 ;
- publier et revoir les migrations ;
- créer le modèle `Activity` applicatif si nécessaire ;
- ajouter les colonnes BatiPaie (`company_id`, `reason`, éventuellement `correlation_id`) ;
- configurer `activitylog.default_except_attributes`, `buffer.enabled=false` ;
- vérifier l'ordre des migrations et les colonnes ;
- tests de schéma et de configuration.

**Gate :** migration rejouable, aucun secret journalisé, buffer désactivé.

### Lot 1B — Couche métier d'audit

- créer `AuditLogger`, `AuditManager`, `AuditContext`, `AuditSanitizer`, `AuditEventType` ;
- catalogue de champs par modèle/événement ;
- tests unitaires du sanitizer (imbrication, secrets, null, JSON) ;
- tests du contexte (HTTP, console, queue) ;
- tests d'idempotence et de transaction.

**Gate :** aucun champ interdit ne peut être écrit, même par erreur d'appel.

### Lot 1C — Instrumentation du socle existant

- `User` : création, vérification email, changement de mot de passe, rôles ;
- `AdministratorInvitation` : création, consommation, révocation, échec d'envoi ;
- `RbacSynchronizer` : synchronisation des rôles/permissions ;
- listeners d'authentification ;
- commande `batipaie:admin-invite` : événement système ;
- tests positif/négatif.

**Gate :** chaque action du socle produit un événement avec acteur, source et champs filtrés.

### Lot 1D — Interface de consultation en lecture seule

- enregistrer le plugin avec `permissions.enabled=true` ;
- mapper `audit.view` / `audit.export` ;
- désactiver widgets, dashboard, mutations ;
- ajouter `immutable_mode=true` ;
- tests d'accès positif/négatif ;
- vérifier l'absence de relation manager non autorisé ;
- vérifier l'export et sa permission.

**Gate :** un utilisateur sans `audit.view` ne voit ni ressource, ni widget, ni dashboard, ni relation manager.

### Lot 2 — Instrumentation des premiers modules métier

- définir le patron d'instrumentation pour salariés, contrats, temps, absences ;
- appliquer `logOnly` par modèle ;
- couvrir les opérations de masse et les imports ;
- tests de non-régression.

### Lot 3 — Conservation, intégrité et exploitation

- politique de conservation par catégorie ;
- purge contrôlée ;
- legal hold si retenu ;
- vérification d'intégrité planifiée ;
- ancrage externe ;
- alertes ;
- procédure d'incident.

### Lot 4 — Reporting et exports

- exports autorisés et journalisés ;
- reporting d'audit ;
- accès expert-comptable ;
- tests de fuite et de permission.

---

## 14. Verdict final

### Verdict explicite

**GO avec adaptations.**

La combinaison Spatie Activitylog + Ali Harb Filament Activity Log + couche métier BatiPaie est **techniquement viable** et **compatible** avec le dépôt. Elle est **retenue sous conditions**, et non validée en l'état.

### Conditions de validation

Le GO devient effectif lorsque :

1. la permission `audit.view` (et `audit.export` si l'export est retenu) est ajoutée au référentiel RBAC avec la fonctionnalité ;
2. le plugin est configuré avec `permissions.enabled=true`, widgets/dashboard désactivés ou sécurisés, mutations désactivées, `immutable_mode=true` ;
3. `AuditSanitizer` applique une liste blanche à l'écriture et une liste noire absolue ;
4. `activitylog.buffer.enabled=false` ;
5. les opérations non Eloquent sont instrumentées explicitement ;
6. les droits SQL et la stratégie de conservation sont arbitrés ;
7. les tests de non-fuite et de permissions sont écrits et exécutés ;
8. le ticket BP-192 est rapproché des critères d'acceptation Jira complets.

### Comparaison avec une implémentation entièrement personnalisée

| Critère | Spatie + plugin + couche BatiPaie | Implémentation 100 % maison |
|---|---|---|
| Délai de mise en œuvre | Rapide | Long |
| Interface de consultation | Fournie | À construire |
| Timeline/filtres/widgets | Fournis | À construire |
| Contrôle du schéma | Bon avec modèle personnalisé | Total |
| Contrôle de l'autorisation | À configurer finement | Total |
| Risque de dépendance tierce | Moyen (plugin jeune) | Faible |
| Risque de bug de sécurité | Moyen (surface plugin) | À porter soi-même |
| Couverture métier | Nécessite la couche BatiPaie | À construire entièrement |
| Preuve d'intégrité | HMAC partiel | Conception libre (chaîne, WORM) |
| Maintenance | Dépend du mainteneur | Interne |
| Coût total | Modéré | Élevé |
| Adéquation à la date cible 1.0.0 | Bonne | Risquée |

**Recommandation :** retenir Spatie + plugin pour l'interface, mais **ne pas considérer le plugin comme le moteur de conformité**. Le moteur de conformité est la couche BatiPaie. Le plugin est un accélérateur d'interface, remplaçable si sa maintenance ou sa sécurité se dégradent.

### Repli recommandé

Si la revue de code du plugin ou la veille sécurité révèle un risque non acceptable :

- conserver Spatie Activitylog ;
- remplacer le plugin par une ressource Filament minimale maison en lecture seule ;
- réutiliser `AuditManager` / `AuditSanitizer` / `AuditContext` ;
- conserver les mêmes permissions et tests.

Ce repli est peu coûteux si la couche métier est correctement isolée. Il justifie d'autant plus l'architecture proposée.

---

## Annexe A — Sources consultées

Date de consultation : **8 novembre 2026**.

### Spatie Activitylog

- Documentation officielle : <https://spatie.be/docs/laravel-activitylog/v5/introduction>
- Dépôt : <https://github.com/spatie/laravel-activitylog>
- Documentation source : `docs/introduction.md`, `docs/installation-and-setup.md`, `docs/advanced-usage/logging-model-events.md`, `docs/advanced-usage/log-options.md`, `docs/advanced-usage/buffering.md`, `docs/advanced-usage/causer-resolver.md`, `docs/advanced-usage/before-logging-hook.md`, `docs/advanced-usage/disabling-logging.md`, `UPGRADING.md`
- Code source : `src/Models/Concerns/LogsActivity.php`, `src/Models/Activity.php`, `src/Support/ActivityLogger.php`, `src/Support/CauserResolver.php`, `src/Support/ActivityLogStatus.php`, `src/Support/ActivityBuffer.php`, `src/Actions/LogActivityAction.php`, `src/Actions/CleanActivityLogAction.php`, `src/Commands/CleanActivitylogCommand.php`, `src/ActivitylogServiceProvider.php`
- Migration : `database/migrations/create_activity_log_table.php.stub`, tag 5.1.4
- `composer.json`, tag 5.1.4
- Packagist : <https://packagist.org/packages/spatie/laravel-activitylog>
- Métadonnées Packagist : <https://repo.packagist.org/p2/spatie/laravel-activitylog.json>

### Ali Harb Filament Activity Log

- Dépôt : <https://github.com/AlizHarb/filament-activity-log>
- Documentation Filament : <https://filamentphp.com/plugins/alizharb-activity-log>
- README : <https://raw.githubusercontent.com/AlizHarb/filament-activity-log/main/README.md>
- `composer.json` : <https://raw.githubusercontent.com/AlizHarb/filament-activity-log/main/composer.json>
- Configuration : `config/filament-activity-log.php`
- Migration : `database/migrations/upgrade_activity_log_with_audit_control_columns.php`
- Code source : `src/ActivityLogPlugin.php`, `src/ActivityLogServiceProvider.php`, `src/Policies/ActivityPolicy.php`, `src/Support/ActivityQuery.php`, `src/Support/ActivityMutation.php`, `src/Support/ActivityIntegrity.php`, `src/Support/AuditMetadata.php`, `src/Support/ActivityPruner.php`, `src/Support/RetentionManager.php`, `src/Resources/ActivityLogs/ActivityLogResource.php`, `src/Resources/ActivityLogs/Tables/ActivityLogTable.php`, `src/RelationManagers/ActivitiesRelationManager.php`, `src/Widgets/ActivityChartWidget.php`, `src/Widgets/LatestActivityWidget.php`, `src/Pages/UserActivitiesPage.php`, `src/Pages/AuditDashboard.php`, `src/Commands/InstallCommand.php`, `src/Commands/VerifyIntegrityCommand.php`
- Documentation interne : `docs/audit-compliance.md`, `docs/architecture.md`
- Packagist : <https://packagist.org/packages/alizharb/filament-activity-log>
- Métadonnées Packagist : <https://repo.packagist.org/p2/alizharb/filament-activity-log.json>

### Référentiels internes

- `AGENTS.md`
- `docs/security/RBAC.md`
- `docs/business-rules/README.md`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Models/User.php`
- `app/Models/AdministratorInvitation.php`
- `app/Actions/Administration/InviteAdministrator.php`
- `app/Actions/Administration/AcceptAdministratorInvitation.php`
- `app/Support/Rbac/RbacSynchronizer.php`
- `database/migrations/*`

---

## Annexe B — Vérifications réellement effectuées

### Commandes de consultation Git

| Commande | Résultat |
|---|---|
| `git rev-parse --abbrev-ref HEAD` | `develop` |
| `git log -1 ...` | commit `296adca...`, auteur Vortech Studio, 8 nov. 2026 |
| `git status --porcelain=v1` | aucun fichier au début de l'étude |
| `git status --branch --short` | `## develop` au début de l'étude |

### Vérifications PHP / Composer

| Commande | Résultat |
|---|---|
| `php -v` | PHP 8.5.12 |
| Lecture de `composer.json` | Versions cibles vérifiées |
| Lecture de `composer.lock` | Laravel 13.40.0, Filament 5.11.1, Livewire 4.4.8, Spatie Permission 8.5.0 |
| Recherche `activitylog` / `audit` dans `composer.lock` | Aucun résultat |
| `Get-ChildItem vendor/spatie` | Aucun package d'audit |
| `composer --version` | Composer 2.10.3 |
| Résolution à blanc dans `%TEMP%` | Succès, aucune installation, aucun fichier dans le dépôt |

### Vérifications de code

| Élément | Vérification |
|---|---|
| Modèles applicatifs | `User`, `AdministratorInvitation` uniquement |
| Ressources Filament | Aucune ressource métier |
| Policies | Aucune policy métier |
| Jobs | Aucun job applicatif |
| Commandes | `batipaie:admin-invite` uniquement |
| Routes | Web, API, invitation, health check |
| Écritures SQL directes | Aucune dans `app/` |
| Opérations de masse | Aucune dans `app/` |
| Données sensibles | `users.password`, `users.remember_token`, `administrator_invitations.token_hash`, sessions, tokens Sanctum |
| Champs paie/IBAN/NIR | Aucun dans le dépôt actuel |

### Vérifications de documentation externe

| Élément | Résultat |
|---|---|
| Contraintes Spatie 5.1.4 | PHP `^8.4`, Illuminate `^12 || ^13` |
| Contraintes plugin 2.0.0 | PHP `^8.3`, Filament `^4 || ^5`, Spatie `^4 || ^5`, Illuminate `^12 || ^13` |
| Compatibilité Livewire 4 | Via Filament 5.11.1, résolution confirmée |
| Avis de sécurité publiés | Aucun visible à la date de consultation |
| Maturité plugin | 32 étoiles, 21 forks, 7 dépendants, 180 603 téléchargements, 0 issue ouverte |
| Score Filament | 78/100, sécurité 61/100 |

### Vérifications non effectuées

- Aucun test Pest exécuté.
- Aucune migration exécutée.
- Aucune base de données interrogée.
- Aucun package installé.
- Aucun fichier `.env` lu.
- Aucune action Jira.
- Aucun commit, push, merge, tag ou changement de branche.
- Aucun déploiement.

### Conclusion de vérification

Le présent rapport est **documentaire**. La seule modification apportée au dépôt est la création de ce fichier. Le Lot 1 n'a pas été commencé, les packages ne sont pas installés, et aucune règle métier ou réglementaire n'a été inventée.
