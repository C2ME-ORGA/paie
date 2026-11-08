# BP-192 — Lot 1A : socle technique du journal d'audit

**Ticket Jira :** BP-192 — Implémenter le journal d'audit métier complet  
**Lot :** 1A — socle technique et schéma de persistance  
**Date :** 8 novembre 2026  
**Branche :** `develop`  
**Commit de départ :** `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`  
**Rapport de référence conservé :** [`bp-192-audit-package-assessment.md`](bp-192-audit-package-assessment.md)

> Ce lot ne réalise pas la couche métier d'audit (`AuditLogger`, `AuditManager`, `AuditContext`, `AuditSanitizer`, `AuditEventType`), ni l'interface Filament définitive. Ces éléments restent dans les lots 1B à 1D.

---

## 1. Résultat du lot

Le socle technique est installé et configuré :

- `spatie/laravel-activitylog` **5.1.4** ;
- `alizharb/filament-activity-log` **2.0.0** ;
- table `activity_log` créée par migration ;
- colonnes d'audit complémentaires du plugin ajoutées ;
- buffer Spatie désactivé ;
- mutations du plugin désactivées ;
- mode immutable activé ;
- permissions du plugin activées et mappées vers `audit.view` ;
- widgets, dashboard, page « activités utilisateur » et retention hold désactivés ;
- aucune journalisation automatique de modèle activée ;
- aucune purge par défaut ;
- tests de schéma, configuration, autorisation et intégrité exécutés.

**Limite volontaire :** le plugin n'est pas enregistré dans le panel Filament pendant le Lot 1A. La ressource, les widgets, le dashboard et la page utilisateur ne sont donc pas exposés. L'enregistrement et la finalisation de l'interface appartiennent au Lot 1D.

---

## 2. Packages installés

| Package | Version | Rôle |
|---|---:|---|
| `spatie/laravel-activitylog` | 5.1.4 | Moteur de persistance et API de journalisation |
| `alizharb/filament-activity-log` | 2.0.0 | Interface de consultation Filament, policy et métadonnées |
| `phiki/phiki` | 2.2.1 | Dépendance transitive du plugin (coloration des diffs) |

Installation réalisée avec des contraintes de niveau patch dans `composer.json` :

```json
"alizharb/filament-activity-log": "~2.0.0",
"spatie/laravel-activitylog": "~5.1.4"
```

Les versions réellement installées sont verrouillées à **2.0.0** et **5.1.4** dans `composer.lock`. La contrainte `~` n'autorise qu'une mise à jour de correctif dans la même branche mineure ; elle n'autorise ni changement majeur ni changement mineur. Ce choix conserve l'épinglage demandé tout en permettant à `composer validate --strict` de rester vert.

La résolution n'a entraîné **aucune mise à jour ni suppression** d'un autre package : 3 installations, 0 mise à jour, 0 suppression.

### 2.1 Compatibilité vérifiée

| Contrainte | Valeur |
|---|---|
| PHP local | 8.5.12 |
| Plateforme Composer | `^8.4` |
| Laravel | 13.40.0 |
| Filament | 5.11.1 |
| Livewire | 4.4.8 |
| Spatie Permission | 8.5.0 |
| Activitylog 5.1.4 | PHP `^8.4`, Illuminate `^12 || ^13` |
| Plugin 2.0.0 | PHP `^8.3`, Filament `^4 || ^5`, Activitylog `^4 || ^5`, Illuminate `^12 || ^13` |

`composer audit` ne signale **aucun avis de sécurité** pour les packages installés à la date du lot. Il signale deux packages abandonnés déjà présents avant l'installation (`dragon-code/contracts`, `dragon-code/support`).

`composer validate --no-check-publish --strict` est **vert**. Les contraintes `~` empêchent toute mise à jour majeure ou mineure non arbitrée, et le lock fixe les versions réellement installées.

---

## 3. Fichiers de configuration ajoutés

| Fichier | Rôle |
|---|---|
| `config/activitylog.php` | Configuration Spatie : buffer, exclusions, purge, modèle |
| `config/filament-activity-log.php` | Configuration du plugin : permissions, mutations, privacy, widgets, contexte |

### 3.1 `config/activitylog.php`

Décisions :

- `buffer.enabled = false` : les insertions bufferisées contourneraient les événements de modèle du plugin et empêcheraient le calcul du risque, du hash d'intégrité et du contexte ;
- `default_log_name = 'audit'` ;
- `clean_after_days = null` : aucune purge par défaut, la commande `activitylog:clean` exige un `--days` explicite ;
- `default_except_attributes` : liste de sécurité contenant notamment `password`, `remember_token`, `token`, `token_hash`, `secret`, `iban`, `bic`, `nir`, `ssn`, `medical_data` ;
- `activity_model` : modèle Spatie natif, aucun modèle applicatif spécifique n'est nécessaire à ce stade.

**Important :** `default_except_attributes` est un filet de sécurité, **pas** la stratégie de confidentialité. La liste blanche par modèle/événement sera portée par `AuditSanitizer` au Lot 1B.

### 3.2 `config/filament-activity-log.php`

Paramètres de sécurité positionnés :

```php
'permissions' => [
    'enabled' => true,
    'view_any' => 'audit.view',
    'view' => 'audit.view',
    'export' => 'audit.export',
    'create' => null,
    'update' => null,
    'delete' => null,
    'delete_any' => null,
    'prune' => null,
    'restore' => null,
    'force_delete' => null,
    'hold' => null,
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
'pages' => [
    'user_activities' => [
        'enabled' => false,
    ],
],
'auto_context' => [
    'enabled' => false,
],
'retention' => [
    'enabled' => false,
],
```

La rédaction d'affichage du plugin a été étendue aux champs bancaires, NIR, données médicales et secrets courants. Elle reste une **défense en profondeur** : elle ne filtre pas les données au stockage.

---

## 4. Migrations et schéma retenu

### 4.1 Migrations publiées

| Migration | Rôle |
|---|---|
| `2026_11_08_160743_create_activity_log_table.php` | Création de la table Spatie |
| `2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php` | Ajout des colonnes d'audit du plugin |

L'ordre est garanti par les horodatages et a été vérifié par les tests sur SQLite en mémoire. La migration du plugin retourne silencieusement si la table Spatie n'existe pas : l'ordre est donc critique et couvert par un test de schéma.

La migration Spatie publiée a reçu une méthode `down()` explicite (`Schema::dropIfExists('activity_log')`) pour rester réversible. La migration du plugin possède déjà son `down()` pour retirer ses colonnes et index.

### 4.2 Colonnes disponibles

Table `activity_log` :

| Colonne | Usage |
|---|---|
| `id` | Identifiant |
| `log_name` | Catégorie de journal (`audit`, `security`, `system`, etc.) |
| `description` | Description lisible |
| `subject_type`, `subject_id` | Ressource concernée |
| `event` | Type d'événement |
| `causer_type`, `causer_id` | Auteur ou acteur système |
| `attribute_changes` | Valeurs avant/après (JSON) |
| `properties` | Métadonnées non sensibles (JSON) |
| `risk_score`, `risk_level` | Métadonnées de risque du plugin |
| `retention_hold` | Colonne de suspension de purge, feature désactivée |
| `integrity_hash` | HMAC-SHA256 de la ligne |
| `request_id` | Corrélation de requête |
| `ip_address` | Adresse IP, non collectée automatiquement en 1A |
| `created_at`, `updated_at` | Horodatage |

Index composites ajoutés : log/date, event/date, subject/date, causer/date, risk/date, request/date, IP/date.

### 4.3 Colonnes non ajoutées et justification

| Colonne | Décision | Justification |
|---|---|---|
| `company_id` | Non ajoutée | Aucun modèle, table ou domaine « entreprise/établissement » n'existe dans le dépôt. Une clé sans cible et sans politique d'isolation serait spéculative. |
| `reason` | Non ajoutée | Aucune opération actuelle ne produit une justification métier. Le champ pourra être porté par `properties` puis promu en colonne lorsque `AuditManager` définira le besoin et la validation. |
| `correlation_id` | Non ajoutée | Le plugin fournit déjà `request_id` et `properties` pour la corrélation. Le hash HMAC du plugin ne couvre pas une colonne personnalisée ; l'ajouter maintenant créerait une métadonnée non signée. |

Ces colonnes pourront être ajoutées lorsque les modules et la couche `AuditContext` existeront, avec une migration dédiée et une mise à jour de la stratégie d'intégrité.

---

## 5. Autorisation et permissions

### 5.1 État au Lot 1A

- `audit.view` et `audit.export` sont **mappées dans la configuration du plugin** ;
- elles ne sont **pas encore créées** dans `PermissionName` ni dans `RbacMatrix` ;
- elles ne sont **accordées à aucun rôle** ;
- aucune interface d'audit n'est enregistrée dans le panel ;
- la ressource `ActivityLogResource` reste inaccessible par défaut.

Cette décision évite de créer un système d'autorisation parallèle à BP-191 et évite d'exposer une interface avant la validation de la matrice en Lot 1D.

### 5.2 Comportement vérifié

Les tests montrent que :

- sans `audit.view`, `ActivityLogResource::canViewAny()` retourne `false` ;
- avec la permission créée uniquement dans le test, il retourne `true` ;
- `permissions.custom_authorization` prend bien le pas sur le mapping RBAC : un authorizer renvoyant `false` refuse l'accès même si la permission existe, et un authorizer renvoyant `true` l'autorise ;
- les mutations (`update`, `delete`, `restore`) restent refusées même avec `audit.view`.

### 5.3 Policy `Activity`

Le provider du plugin enregistre `Gate::policy(Activity::class, ActivityPolicy::class)` au démarrage du package. Il n'existe **aucune policy applicative** sur `Activity` dans le dépôt. Le test de fondation vérifie que la policy active est bien celle du plugin.

Conséquence : une future policy BatiPaie sur `Activity` serait écrasée par le plugin. Il faudra utiliser `permissions.custom_authorization` ou remplacer la ressource, et ne pas compter sur une policy maison.

---

## 6. Intégrité et limites connues

Le plugin signe les nouvelles lignes avec un HMAC-SHA256 dans `integrity_hash`. La clé provient de `ACTIVITY_LOG_INTEGRITY_KEY`. Le repli sur `APP_KEY` n'est autorisé qu'en `local` et `testing` ; dans tout autre environnement (`staging`, `preproduction`, `production`, nom personnalisé), une clé dédiée, stable, d'au moins 32 caractères et différente de `APP_KEY` est obligatoire (contrôle du Lot 1E, durci par le lot correctif final).

### 6.1 Ce qui est vérifié

- une ligne créée reçoit un hash non nul ;
- `php artisan filament-activity-log:verify-integrity` réussit sur une ligne intacte ;
- après une modification SQL de `description`, la commande échoue.

### 6.2 Ce qui n'est pas garanti

- **La suppression d'une ligne n'est pas détectable** par le HMAC : une ligne supprimée n'a plus de hash à vérifier.
- Le hash ne constitue pas une chaîne entre lignes : une suppression, un rejeu ou une insertion ne sont pas détectés par le seul HMAC.
- Une personne disposant de la clé HMAC peut recalculer un hash valide.
- Le hash est écrit par un `UPDATE` après l'`INSERT` : les droits SQL doivent autoriser la mise à jour des colonnes de métadonnées, ou l'intégrité doit être désactivée/repensée.
- Le mode `immutable_mode` masque les actions de l'interface ; il ne rend pas la table append-only.
- `Activity` n'utilise pas `SoftDeletes` : une suppression est physique.
- Aucune purge automatique n'est configurée, mais un opérateur peut lancer `activitylog:clean --days=N` : la politique de conservation reste à arbitrer.

**Conclusion :** à l'issue du Lot 1A, les traces sont **signées et vérifiables contre une modification de ligne**, mais elles ne sont **pas inviolables**. Les droits SQL, les sauvegardes, l'ancrage externe et la politique de conservation restent nécessaires.

---

## 7. Journalisation et purge

### 7.1 Aucune journalisation automatique

Aucun modèle existant (`User`, `AdministratorInvitation`, modèles Spatie Permission) n'utilise `LogsActivity`. Aucun observer, listener ou trait d'audit n'a été ajouté. Le socle est prêt, mais **aucune opération métier n'est encore journalisée**.

La journalisation sera centralisée dans `AuditManager` au Lot 1B, puis instrumentée au Lot 1C.

### 7.2 Pas de purge arbitraire

`clean_after_days` vaut `null`. La commande `activitylog:clean` refuse de s'exécuter sans `--days` explicite. Un test vérifie qu'une activité n'est pas supprimée par une commande sans période.

Aucune tâche planifiée de purge n'a été ajoutée. Aucune durée légale de conservation n'a été inventée.

---

## 8. Vérifications et tests exécutés

### 8.1 Tests ciblés

Commande :

```bash
php artisan test --filter=AuditFoundation
```

Résultat : **11 tests, 11 réussis, 44 assertions**.

Couverture :

- colonnes Spatie et colonnes d'audit présentes ;
- absence de `company_id`, `reason`, `correlation_id` ;
- buffer désactivé ;
- `clean_after_days` nul ;
- exclusions sensibles présentes ;
- plugin installé et fonctionnalités de présentation désactivées ;
- widgets, dashboard et page utilisateur désactivés ;
- aucun modèle existant n'utilise `LogsActivity` ;
- policy `Activity` du plugin active ;
- refus sans `audit.view` ;
- autorisation avec `audit.view` ;
- priorité de `permissions.custom_authorization` ;
- mutations refusées ;
- commande de purge refusée sans période ;
- persistance immédiate et signature HMAC ;
- détection d'une modification de ligne.

### 8.2 Suite complète

Commande :

```bash
php artisan test
```

Résultat : **94 tests, 94 réussis, 385 assertions**.

### 8.3 Qualité

| Commande | Résultat |
|---|---|
| `vendor/bin/pint --test` | Réussi |
| `vendor/bin/phpstan analyse --no-progress` | Réussi, 0 erreur |
| `composer audit` | Aucun avis de sécurité ; 2 packages abandonnés préexistants |
| `composer validate --no-check-publish --strict` | Réussi |

### 8.4 Migrations

Les migrations ont été exécutées **uniquement** par `RefreshDatabase` sur SQLite `:memory:` dans les tests. Aucune migration n'a été lancée sur la base locale ou de production. Aucun fichier `.env` n'a été lu.

---

## 9. Fichiers modifiés ou créés

### Créés

- `config/activitylog.php`
- `config/filament-activity-log.php`
- `database/migrations/2026_11_08_160743_create_activity_log_table.php`
- `database/migrations/2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php`
- `tests/Feature/Audit/AuditFoundationTest.php`
- `docs/business-rules/bp-192-audit-foundation.md`

### Modifiés

- `composer.json` : ajout des deux dépendances épinglées ;
- `composer.lock` : ajout de 3 packages, aucune mise à jour ;
- `app/Providers/Filament/AdminPanelProvider.php` : **non modifié au final** — le plugin n'est pas enregistré dans le panel en 1A ;
- `.env.example` et `.env.production.example` : documentation de `ACTIVITYLOG_ENABLED` et `ACTIVITY_LOG_INTEGRITY_KEY` ;
- `.gitignore` : ajout de `/public/css/alizharb` pour les assets générés par `filament:upgrade`.

### Assets générés

`php artisan filament:upgrade`, exécuté par le script Composer, a publié :

- `public/css/alizharb/filament-activity-log/filament-activity-log.css` ;
- les assets Filament habituels.

Le CSS du plugin est ignoré par Git et sera régénéré au déploiement par `filament:upgrade`.

---

## 10. Décisions reportées aux lots suivants

### Lot 1B — couche métier

- `AuditLogger`, `AuditManager`, `AuditContext`, `AuditSanitizer`, `AuditEventType` ;
- liste blanche par modèle/événement ;
- filtrage des `properties` et des descriptions ;
- acteur système et contexte console/queue ;
- tests de non-fuite.

### Lot 1C — instrumentation

- `User`, `AdministratorInvitation`, RBAC, authentification ;
- commandes et jobs ;
- opérations de masse et SQL directes ;
- tests positif/négatif.

### Lot 1D — interface et permissions

- création de `audit.view` / `audit.export` dans le référentiel RBAC ;
- enregistrement de `ActivityLogPlugin` dans le panel ;
- activation éventuelle de la ressource, avec widgets/dashboard maintenus désactivés ou sécurisés ;
- décision sur `permissions.custom_authorization` ;
- tests d'accès complet.

### Lots ultérieurs

- `company_id` et isolation multi-établissement ;
- `reason` et `correlation_id` si justifiés ;
- politique de conservation et purge contrôlée ;
- legal hold ;
- ancrage externe des empreintes ;
- reporting et exports ;
- droits SQL restrictifs et séparation des rôles.

---

## 11. Limites et points d'attention

1. Le plugin n'est pas encore visible : c'est volontaire, l'interface est réservée au Lot 1D.
2. `audit.view` n'existe pas encore : la ressource serait refusée à tous même si elle était enregistrée.
3. Le HMAC ne détecte pas les suppressions et ne rend pas la base immuable.
4. Le hash est recalculable par toute personne disposant de la clé ; la clé doit être dédiée, stable, sauvegardée et protégée.
5. Le plugin utilise `UPDATE` après `INSERT` pour ses métadonnées : les droits SQL devront être arbitrés.
6. Aucun modèle métier n'est instrumenté : le Lot 1A ne prouve pas la couverture fonctionnelle de BP-192.
7. Les assets CSS du plugin sont générés et ignorés par Git ; le déploiement doit exécuter les scripts Composer/Filament.
8. Les contraintes `~` n'autorisent que des correctifs ; le lock fixe les versions exactes installées.
9. Les deux packages abandonnés signalés par `composer audit` sont préexistants et sans lien avec BP-192 ; ils restent à traiter séparément.

---

## 12. Conclusion

Le Lot 1A est **terminé techniquement** :

- packages compatibles et installés aux versions verrouillées (5.1.4 et 2.0.0) ;
- migrations examinées et exécutées uniquement en test ;
- schéma couvrant auteur, événement, ressource, avant/après, horodatage, métadonnées et origine ;
- buffer désactivé ;
- mutations désactivées ;
- mode immutable configuré ;
- aucune journalisation automatique non maîtrisée ;
- aucune purge arbitraire ;
- tests et contrôles qualité réussis.

L'interface de consultation et la couche métier de confidentialité restent **hors périmètre** et doivent être traitées dans les lots 1B à 1D.
