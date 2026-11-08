# BP-192 — Lot 2 — Décisions d'architecture préalables à l'instrumentation métier

- **Ticket** : BP-192 (journal d'audit métier), phase « Lot 2 — étude d'architecture ».
- **Nature** : étude en lecture seule. Aucun code applicatif, migration, configuration, permission ou interface n'est modifié.
- **Date** : 2026-11-08.
- **Branche** : `develop`.
- **HEAD** : `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` — `[BP-191] Sécuriser le RBAC et l’initialisation administrateur (#9)`.
- **Documents de référence** : `docs/business-rules/bp-192-audit-business-mapping.md` (cartographie), `bp-192-audit-foundation.md`, `bp-192-audit-implementation.md`, `bp-192-audit-instrumentation.md`, `bp-192-audit-filament.md`, `bp-192-audit-consolidation.md`, `bp-192-audit-package-assessment.md`, `docs/security/RBAC.md`.

Conventions de lecture :

- **Fait** : vérifié dans le code, la configuration ou la base de migration, avec référence.
- **Déduction** : conséquence technique d'un ou plusieurs faits.
- **Recommandation** : proposition non implémentée.
- **À valider** : décision qui ne peut pas être tranchée par l'analyse technique seule.

---

## 1. Contexte et état initial

### 1.1 Références Git

| Élément | Valeur |
|---|---|
| Branche | `develop` |
| HEAD | `296adca07191cb7ee7755cce91a9d0a3bd17ccc8` |
| Modifications préexistantes | 56 entrées liées aux lots 0 → 1E.3 |
| Modifications ajoutées depuis | `AGENTS.md` (règle permanente de tests ciblés, demandée explicitement) |
| Fichiers non suivis | `docs/business-rules/bp-192-audit-business-mapping.md` (livrable du lot précédent) |

**Fait** : l'état Git contient 58 entrées : les 56 modifications préexistantes des lots 0 → 1E.3, la modification `AGENTS.md` issue de la consigne permanente sur les tests, et le rapport de cartographie non suivi. Ce lot n'ajoute que le présent document.

### 1.2 Ce qui est réellement implémenté

**Fait** : deux modèles applicatifs seulement (`App\Models\User`, `App\Models\AdministratorInvitation`). Aucun modèle RH, paie, chantier, congé, document ou établissement.

**Fait** : les domaines opérationnels sont l'identité, l'amorçage administrateur, le RBAC et l'audit. La cartographie `bp-192-audit-business-mapping.md` a établi ce constat et refusé d'inventer des événements métier.

### 1.3 Socle d'audit disponible

| Composant | Rôle | Référence |
|---|---|---|
| `AuditManager` | façade métier, contexte, corrélation | `app/Services/Audit/AuditManager.php` |
| `AuditLogger` | contrat d'écriture unique | `app/Contracts/AuditLogger.php` |
| `SpatieAuditLogger` | implémentation, seul écrivain Activitylog | `app/Services/Audit/SpatieAuditLogger.php` |
| `AuditSanitizer` | allowlist, blacklist, métadonnées | `app/Services/Audit/AuditSanitizer.php` |
| `AuditContext` | origine, acteur, corrélation, raison | `app/Services/Audit/AuditContext.php` |
| `AuditIntegrityConfiguration` | garde de configuration HMAC en production | `app/Support/Audit/AuditIntegrityConfiguration.php` |
| `AuditEventType` | catalogue fermé de 16 événements | `app/Enums/AuditEventType.php` |
| Activitylog | persistance | `spatie/laravel-activitylog ~5.1.4` |
| Plugin Filament | signature, risque, interface | `alizharb/filament-activity-log ~2.0.0` |

---

## 2. Décision mono-entreprise

### 2.1 Décision

**Décision validée (rappel, non remise en question)** : BatiPaie est un logiciel destiné à une seule entreprise. Aucune architecture multi-tenant, aucun `tenant_id` / `company_id`, aucun filtrage par entreprise, aucun changement de contexte d'entreprise.

### 2.2 Conséquences constatées dans le code

| Élément | État actuel | Cohérence avec la décision |
|---|---|---|
| Migrations | aucune colonne `tenant_id` ou `company_id` | conforme |
| `config/filament-activity-log.php` → `query.scope` | `null` | conforme |
| `config/filament-activity-log.php` → `cache.context_key` | `null` | conforme tant qu'aucun scope n'existe |
| `config/permission.php` → `teams` | `false` | conforme |
| `ActivityQuery::applyScope()` | aucun scope enregistré | conforme |
| `ActivityPolicy` | autorisation par permission, sans notion d'entité | conforme |

**Fait** : `filament-activity-log:doctor` (`vendor/alizharb/filament-activity-log/src/Commands/DoctorCommand.php`) affiche un contrôle « global query scope » en `review` lorsque `query.scope` est `null`. Ce contrôle n'échoue pas la commande (seuls la table, les mutations et le buffer peuvent la faire échouer).

**Déduction** : le `review` du doctor sur le scope tenant est attendu en mono-entreprise et ne doit pas être interprété comme une anomalie.

### 2.3 Ce que la décision ne supprime pas

**Fait** : les contrôles d'accès restent en place : permission `audit.view`, policy de ressource, `privacy.immutable_mode = true`, identification du causer, séparation des rôles.

**Recommandation** : documenter explicitement dans le code et la documentation que `query.scope` reste `null` par décision fonctionnelle, afin qu'un futur contributeur ne l'ajoute pas par réflexe « multi-tenant ».

**À valider** : la décision mono-entreprise ne dit pas si un utilisateur doit être restreint à certains **établissements** de l'entreprise unique. Un établissement n'est pas un tenant, mais peut être un périmètre de confidentialité paie. Cette question devra être tranchée avant l'instrumentation des domaines RH, sans remettre en cause la mono-entreprise.

---

## 3. Architecture actuelle du journal

### 3.1 Chemin d'écriture

**Fait**, séquence réelle :

1. appel métier → `AuditManager::record(event, subject, oldValues, newValues, context)` ;
2. résolution du contexte : contexte explicite, sinon `currentContext()`, sinon contexte par défaut ;
3. génération d'un `correlation_id` UUID si absent ;
4. délégation à `AuditLogger::record()` ;
5. `SpatieAuditLogger` valide le sujet et l'acteur, puis `AuditSanitizer::sanitizeChanges()` et `sanitizeMetadata()` ;
6. écriture `activity()->useLog()->event()->performedOn()->withChanges()->withProperties()->causedBy()` puis `->log($event->description())` ;
7. événement de modèle `Activity::created` → `AuditMetadata::persist()` (plugin Filament) → calcul `risk_score` / `risk_level`, `request_id`, `ip_address`, puis **signature HMAC** écrite dans `integrity_hash`.

**Déduction** : la signature n'est pas produite par `SpatieAuditLogger`, mais par le hook `Activity::created` du plugin. Toute écriture qui contournerait cet événement (buffer, `DB::table()->insert()`) produirait une ligne non signée.

### 3.2 Catalogue d'événements et canaux

**Fait** : `AuditEventType` contient 16 cas. `category()` classe `security.*` et `rbac.*` en `Security`, `system.*` en `System`, le reste en `Business`. `logName()` en déduit `audit`, `security` ou `system`.

| Catégorie | Canal `log_name` | Événements actuels | Producteurs réels |
|---|---|---|---|
| `business` | `audit` | `user.created`, `user.updated`, `user.deleted`, `administrator_invitation.*` | création utilisateur et cycle invitation uniquement |
| `security` | `security` | `rbac.*`, `security.login_*`, `security.logout`, `security.access_denied` | RBAC (partiel), authentification ; `access_denied` sans producteur |
| `system` | `system` | `system.command_completed` | aucun producteur |

### 3.3 Stockage

**Fait** : table `activity_log` créée par `database/migrations/2026_11_08_160743_create_activity_log_table.php` :

- `log_name`, `description`, `subject_type`/`subject_id` (morphs), `event`, `causer_type`/`causer_id` (morphs), `attribute_changes` (JSON), `properties` (JSON), timestamps.

**Fait** : colonnes complémentaires ajoutées par `2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php` : `risk_score`, `risk_level`, `retention_hold`, `integrity_hash` (`char(64)`), `request_id`, `ip_address`, plus la table de propriété `activity_log_audit_control_objects`.

**Fait** : la colonne `batch_uuid` n'existe pas. Le regroupement de lot repose donc sur `properties.correlation_id`.

**Fait** : `activity_log.buffer.enabled = false` dans `config/activitylog.php`, avec le commentaire indiquant que les insertions bufferisées contourneraient les événements de modèle et empêcheraient l'application des métadonnées.

### 3.4 Chemin de lecture

**Fait** : `ActivityLogResource` du plugin est enregistrée par `AdminPanelProvider::panel()` via `ActivityLogPlugin`. Elle exige `audit.view` (`config/filament-activity-log.php`, clé `permissions`). Les actions `delete`, `delete_any`, `revert`, `restore`, `export`, `prune` et `hold` sont désactivées ou non autorisées.

**Fait** : `ActivityPolicy` du plugin prend en charge l'autorisation du modèle `Activity` ; les tests `AuditFilamentInterfaceTest` couvrent l'accès autorisé, refusé, l'absence de verbe de mutation et l'absence d'export.

---

## 4. Politique de conservation

### 4.1 Configuration actuelle

| Paramètre | Valeur | Référence | Effet |
|---|---|---|---|
| `activitylog.clean_after_days` | `null` | `config/activitylog.php` | aucune durée par défaut |
| `activitylog.clean_after_days` (package) | 365 jours | défaut du package, écrasé ici | non applicable |
| `filament-activity-log.retention.enabled` | `false` | `config/filament-activity-log.php` | `RetentionManager::setHold()` lève `RetentionException('retention.unavailable')` |
| `filament-activity-log.retention.log_activity` | `false` | idem | aucune trace de pose/levée de hold |
| `filament-activity-log.mutations.enabled` | `false` | idem | `ActivityPruner::pruneBefore()` lève `AuthorizationException` |
| `filament-activity-log.privacy.immutable_mode` | `true` | idem | purge et mutations refusées côté interface |
| Tâche planifiée de purge | absente | `routes/console.php`, `bootstrap/app.php` | aucune purge automatique |

### 4.2 Comportement réel de la commande Spatie

**Fait** (`vendor/spatie/laravel-activitylog/src/Commands/CleanActivitylogCommand.php`) :

- signature : `activitylog:clean {log?} {--days=} {--force}` ;
- `--days` prioritaire, sinon `config('activitylog.clean_after_days')` ;
- refuse toute valeur non entière ou inférieure à 1 ;
- `ConfirmableTrait` impose `--force` en production ;
- l'argument optionnel `log` permet de cibler un `log_name`.

**Fait** (`vendor/spatie/laravel-activitylog/src/Actions/CleanActivityLogAction.php`) :

- calcul `Carbon::now()->subDays($days)` ;
- `where('created_at', '<', $cutoff)` ;
- `->when($logName !== null, fn ($q) => $q->inLog($logName))` ;
- `->delete()` en une seule requête.

**Déduction** : cette commande :

- **ne respecte pas** `retention_hold` ;
- **ne journalise pas** la purge ;
- **ne vérifie pas** la signature des lignes supprimées ;
- **ne produit aucune archive** ;
- supprime par lots non bornés (une seule requête `DELETE`).

**Fait** : `tests/Feature/Audit/AuditFoundationTest.php` vérifie déjà que la commande refuse de s'exécuter sans durée explicite.

### 4.3 Mécanismes de hold disponibles mais inactifs

**Fait** (`vendor/alizharb/filament-activity-log/src/Support/RetentionManager.php`) : la pose d'un hold exige `retention.enabled = true`, la colonne `retention_hold`, et l'autorisation `hold` via `Gate::authorize('hold', $activity)`. Elle re-signe la ligne après modification et peut journaliser un événement `audit-control`.

**Fait** (`.../Support/ActivityPruner.php`) : `pruneBefore()` respecte `retention_hold = false` et exige `mutations.enabled = true` et `privacy.immutable_mode = false`, ainsi que l'autorisation `pruneAny`.

**Déduction** : dans la configuration actuelle, aucune purge conforme au hold n'est possible. La seule purge exécutable est `activitylog:clean`, qui ignorerait le hold.

### 4.4 Risques d'une suppression automatique

| Risque | Détail | Gravité |
|---|---|---|
| Destruction de preuve | un `DELETE` massif non journalisé est irréversible sans sauvegarde | élevée |
| Contournement du hold | `activitylog:clean` ignore `retention_hold` | élevée |
| Perte de corrélation | suppression partielle d'un lot (`correlation_id`) | moyenne |
| Incohérence de vérification | les lignes restantes restent valides, donc la purge est indétectable | élevée |
| Données personnelles | conserver indéfiniment e-mails et noms | moyenne |
| Volume | croissance non bornée de `activity_log` | moyenne |

### 4.5 Catégories et sensibilité

| Catégorie | Volume attendu | Sensibilité | Valeur probatoire |
|---|---|---|---|
| `business` | croissant avec les domaines RH/paie | élevée (données personnelles, rémunérations à venir) | forte |
| `security` | faible à moyen | moyenne (identités, rôles, connexions) | forte |
| `system` | faible | faible | faible |

**Déduction** : la conservation différenciée par catégorie est techniquement possible dès aujourd'hui, puisque `logName()` dérive la catégorie et que `activitylog:clean` accepte un `log_name`. Elle reste cependant dangereuse tant que la commande ne respecte ni le hold ni la journalisation de purge.

### 4.6 Tableau comparatif des stratégies

| Stratégie | Avantages | Risques | Complexité |
|---|---|---|---|
| **Conservation permanente** | historique complet, aucune perte de preuve, aucune purge à auditer | volume croissant, conservation de données personnelles sans base arbitrée, coût de sauvegarde | faible techniquement, élevée juridiquement |
| **Durée uniforme** | simplicité opérationnelle, une seule tâche | même durée pour un échec de connexion et une clôture de paie ; peu de différenciation | faible |
| **Durée par catégorie** | adaptée à la valeur probatoire ; purge ciblée par `log_name` | trois tâches à piloter ; risque d'incohérence ; le hold doit être respecté | moyenne |
| **Archivage puis purge** | maîtrise du volume, conservation de la preuve hors base chaude | gestion des archives, manifeste d'intégrité, restauration à tester | élevée |

### 4.7 Conséquences sur les chaînes HMAC

**Fait** : chaque ligne porte une signature indépendante ; il n'existe aucun chaînage.

**Déduction** :

- supprimer un préfixe ancien ne rend pas invalides les lignes restantes ;
- une suppression, même massive, n'est pas détectable par `filament-activity-log:verify-integrity` ;
- une purge doit donc être **tracée ailleurs** (journal d'exploitation, manifeste d'archive) pour rester prouvable.

### 4.8 Archivage, sauvegarde, restauration

**Fait** : le disque `local` pointe sur `storage_path('app/private')` (`config/filesystems.php`), ce qui permet un archivage privé hors base.

**Fait** : aucune table, aucun disque, aucune commande d'archive n'existe pour l'audit.

**Recommandation** :

1. avant toute purge, exporter les lignes éligibles vers un stockage privé, avec un manifeste `(id, created_at, integrity_hash)` signé ;
2. vérifier la signature des lignes exportées avant suppression ;
3. journaliser l'opération de purge (nombre, catégorie, période, opérateur) dans un canal séparé ;
4. tester la restauration d'une archive et relancer `filament-activity-log:verify-integrity` ;
5. inclure `activity_log` dans la politique de sauvegarde base et vérifier que la restauration ne casse pas les signatures (les données signées sont dans la ligne, pas dans un état externe).

### 4.9 Recommandation argumentée

**Recommandation** : stratégie « durée par catégorie + archivage préalable + hold respecté », avec des durées **à valider juridiquement**.

Proposition technique de paramétrage (les valeurs absolues ne sont pas fixées par cette étude) :

| Catégorie | Ordre de grandeur relatif | Justification |
|---|---|---|
| `security` | le plus long | preuve d'accès et de privilèges, utile en investigation |
| `business` | aligné sur la durée de contestation des données RH/paie, **à confirmer** | preuve des créations/modifications/suppressions métier |
| `system` | le plus court | faible valeur probatoire |

**À valider** : les durées chiffrées, la base légale de conservation, et l'articulation avec les obligations propres à la paie. Aucune durée n'est inventée ici.

**Recommandation** : ne pas activer `activitylog:clean` en l'état. Construire d'abord une commande de purge applicative qui respecte `retention_hold`, archive et journalise. Cela relève d'un lot dédié, hors 2A/2B.

---

## 5. Étude HMAC et intégrité

### 5.1 Génération

**Fait** (`vendor/alizharb/filament-activity-log/src/Support/AuditMetadata.php::persist()`) : après l'insertion, le plugin rafraîchit le modèle, calcule le risque, les colonnes de contexte, puis `app(ActivityIntegrity::class)->hash($activity)` et écrit `integrity_hash` par une requête brute (`toBase()->update()`), ce qui évite de déclencher à nouveau les événements de modèle.

**Fait** (`.../ActivityLogServiceProvider::packageBooted()`) : le hook est `$activityModel::created(...)`. Un buffer activé empêcherait donc la signature.

**Fait** (`.../Support/ActivityIntegrity.php::hash()`) : la clé est `config('filament-activity-log.integrity.key') ?: config('app.key')`.

### 5.2 Données réellement signées

**Fait** : le payload canonique contient exactement :

`id`, `log_name`, `description`, `subject_type`, `subject_id`, `causer_type`, `causer_id`, `event`, `properties`, `attribute_changes`, `batch_uuid`, `risk_score`, `risk_level`, `retention_hold`, `request_id`, `ip_address`, `created_at`, `updated_at`.

**Fait** : `canonicalize()` trie les clés associatives, convertit les `Collection`, `DateTimeInterface` et `BackedEnum`, puis `json_encode(..., JSON_THROW_ON_ERROR)`.

**Déduction** : `batch_uuid` est absent de la table, donc absent du payload réel. `properties` contient `origin`, `correlation_id`, `reason` et les métadonnées allowlistées ; ces valeurs sont donc signées.

### 5.3 Stockage et vérification

**Fait** : signature stockée dans `activity_log.integrity_hash` (`char(64)`), indexée.

**Fait** : commande `filament-activity-log:verify-integrity {--chunk=500}` (`.../Commands/VerifyIntegrityCommand.php`) : parcourt les lignes par `chunkById`, appelle `ActivityIntegrity::verify()`, collecte les identifiants invalides et retourne un échec si la liste n'est pas vide.

**Fait** : `verify()` exige un `integrity_hash` non vide et utilise `hash_equals()`.

**Déduction** : une ligne sans signature est considérée invalide. Les lignes antérieures à l'activation de l'intégrité, ou insérées hors du hook, seraient donc signalées.

### 5.4 Comportements face aux altérations

| Scénario | Détection | Explication |
|---|---|---|
| Modification d'un champ signé | **oui** | la signature ne correspond plus |
| Modification de `description`, `properties`, `attribute_changes` | **oui** | inclus dans le payload |
| Modification de `created_at` / `updated_at` | **oui** | inclus dans le payload |
| Modification de `integrity_hash` | **oui** | la comparaison échoue |
| Modification de `retention_hold` | **oui** | inclus ; le `RetentionManager` re-signe lors d'un changement légitime |
| Suppression d'une ligne | **non** | aucune signature ne référence les autres lignes |
| Suppression d'un bloc ancien | **non** | aucune chaîne, aucune ancre |
| Réordonnancement de l'affichage | **non** | aucune notion de séquence ; l'ordre repose sur `created_at` et `id` |
| Réattribution de `created_at` entre deux lignes | **oui** | les deux signatures deviennent invalides |
| Absence d'événement attendu | **non** | aucune preuve d'exhaustivité |
| Restauration d'une sauvegarde ancienne | **non détectée** | les signatures restent valides dans la sauvegarde |

### 5.5 Limites structurantes

1. **Pas de chaînage** : aucune ligne ne contient le hash de la précédente.
2. **Pas de séquence** : pas de numéro monotone signé.
3. **Pas d'identification de clé** : aucune colonne `key_id`.
4. **Rotation non gérée** : changer la clé rend toutes les anciennes signatures invérifiables. `docs/business-rules/bp-192-audit-consolidation.md` §5.3 le documente déjà.
5. **Re-signature possible** : `filament-activity-log:backfill` (`.../Commands/BackfillAuditMetadataCommand.php`) recalcule et réécrit `integrity_hash` pour des lignes existantes. **Déduction** : un opérateur disposant de la clé et de la console peut modifier une ligne puis la re-signer. Le HMAC protège contre une altération SQL sans accès applicatif, pas contre un administrateur système.
6. **Fallback `APP_KEY`** : limité à `local` et `testing` depuis le lot correctif final. Tout autre environnement (`staging`, `preproduction`, `production`, nom personnalisé) exige une clé dédiée, d'au moins 32 caractères et différente de `APP_KEY`.
7. **Sauvegardes** : elles contiennent les lignes signées ; un `DELETE` en base reste indétectable si la sauvegarde n'est pas comparée.

### 5.6 Séparation clé HMAC / `APP_KEY`

**Fait** : `app/Support/Audit/AuditIntegrityConfiguration.php::assertConfigured()` impose, hors `local` et `testing` :

- intégrité activée ;
- clé non vide ;
- longueur ≥ 32 caractères ;
- clé différente de `APP_KEY` (`hash_equals`).

**Fait** : en `local` et `testing`, la méthode retourne immédiatement. Le fallback du package s'applique.

**Déduction (corrigée par le lot correctif final)** : la séparation est désormais garantie dans tout environnement autre que `local` et `testing`, y compris `staging` et `preproduction`.

**Recommandation (mise en œuvre)** : la garde repose sur une liste positive (`local`, `testing`) ; tout autre environnement exige une clé dédiée, sans possibilité de désactivation par variable d'environnement.

### 5.7 Approches d'intégrité comparées

| Approche | Avantages | Limites | Complexité |
|---|---|---|---|
| **HMAC individuel** (existant) | simple, détecte toute modification de ligne, aucun impact sur les performances d'écriture | ne détecte ni suppression, ni réordonnancement, ni absence ; re-signature possible | déjà en place |
| **Chaînage cryptographique** (`previous_hash` signé) | détecte suppression et réordonnancement dans une plage conservée | casse à la première purge ; nécessite migration, re-signature de l'historique, verrou d'écriture sérialisé ; coût sur les écritures concurrentes | élevée |
| **Vérification périodique** | détecte les altérations a posteriori, peu coûteuse | ne détecte que ce qui existe encore ; dépend d'une planification fiable | faible |
| **Ancrage externe** (manifeste signé publié hors base) | détecte une suppression de masse ou un rejeu de sauvegarde ; preuve opposable | dépend d'un tiers ou d'un stockage immuable ; opérationnel à maintenir | moyenne |
| **Archivage immuable** (WORM, stockage objet avec verrou) | preuve forte, conservation longue | coût, intégration, restauration, hors périmètre actuel | élevée |

### 5.8 Recommandation

**Recommandation** : conserver le HMAC individuel comme socle, et ajouter deux mesures à faible coût :

1. **vérification planifiée** de `filament-activity-log:verify-integrity`, avec alerte sur échec ;
2. **ancrage périodique** : manifeste quotidien contenant `min(id)`, `max(id)`, nombre de lignes, et une empreinte calculée sur la concaténation ordonnée des `integrity_hash`, signé avec la clé HMAC et stocké hors base (fichier privé ou coffre). Ce manifeste détecte les suppressions de masse et les restaurations incohérentes sans modifier le schéma.

**Recommandation** : ne pas implémenter de chaînage maintenant. Il entrerait en conflit avec la purge et imposerait une réécriture de l'historique, pour un bénéfice limité tant que la conservation n'est pas arbitrée.

**À valider** : le niveau de preuve attendu (interne, audit, contentieux) détermine si l'ancrage externe suffit ou si un archivage immuable est requis.

---

## 6. Confidentialité et données personnelles

### 6.1 Mécanismes en place

**Fait** : `AuditSanitizer::sanitizeChanges()` applique l'allowlist `audit.fields[subject][event]`, puis `sanitizeValues()` retire les clés sensibles (`isSensitiveKey()`), récursivement, avec une profondeur maximale (`audit.max_depth = 8`).

**Fait** : `sanitizeMetadata()` applique l'allowlist `audit.metadata` et valide les valeurs par catégorie : valeurs contrôlées (`metadata_values`), identifiants opaques (`metadata_identifier_pattern`), identifiants techniques (`metadata_technical_identifier_pattern`), et `reason` via une allowlist (`audit.reasons`, longueur 200).

**Fait** : `config/audit.php` contient une blacklist de clés (`password`, `remember_token`, `token`, `token_hash`, `iban`, `bic`, `nir`, `ssn`, `medical_data`, etc.) et quatre motifs larges.

**Fait** : `config/filament-activity-log.php` ajoute une redaction d'affichage et `immutable_mode`.

### 6.2 Allowlists actuelles

| Sujet | Événement | Champs autorisés |
|---|---|---|
| `User` | `user.created`, `user.updated` | `name`, `email`, `email_verified_at` |
| `User` | `user.deleted` | `name`, `email` |
| `User` | `rbac.role_assigned`, `rbac.role_removed` | `name`, `email` |
| `AdministratorInvitation` | `created` | `name`, `email`, `expires_at` |
| `AdministratorInvitation` | `consumed` | `consumed_at` |
| `AdministratorInvitation` | `revoked` | `revoked_at` |

### 6.3 Données examinées

| Donnée | Présence dans le code | Traitement actuel | Recommandation |
|---|---|---|---|
| Adresse e-mail | `users.email`, `administrator_invitations.email` | journalisée en clair | voir 6.4 |
| Nom | `users.name`, `administrator_invitations.name` | journalisé en clair | conserver |
| Identifiants techniques | `id`, `subject_id`, `correlation_id` | journalisés | conserver |
| Adresse IP | colonnes présentes, collecte désactivée | non renseignée | décider avant activation |
| Mot de passe | `users.password` | exclu | conserver l'exclusion |
| `remember_token` | `users.remember_token` | exclu | conserver |
| Jeton d'invitation brut | mémoire, URL, e-mail | jamais persisté | conserver |
| `token_hash` | `administrator_invitations.token_hash` | exclu | conserver |
| Jeton Sanctum | `personal_access_tokens.token` | non journalisé | exclure |
| IBAN / BIC / RIB | aucun modèle | blacklist présente | conserver, étendre aux futurs modèles |
| NIR / sécurité sociale | aucun modèle | blacklist présente | conserver |
| Rémunérations | aucun modèle | non applicable | à arbitrer avant paie |
| Données médicales | aucun modèle | blacklist présente | exclusion totale recommandée |
| Documents | aucun modèle | non applicable | journaliser les métadonnées, jamais le contenu |

**Fait** : aucun domaine RH ou paie n'existe. **Déduction** : la politique de protection de ces domaines n'est pas implémentée ; seules des recommandations peuvent être formulées.

### 6.4 Faut-il conserver l'e-mail en clair dans `old_values` / `new_values` ?

Analyse par événement :

| Événement | Utilité de l'e-mail | Recommandation |
|---|---|---|
| `user.created` | reconstituer l'identité si le compte est supprimé plus tard | **conserver** `name` et `email` dans `new_values` |
| `user.updated` | prouver un changement d'adresse de connexion (événement de sécurité) | conserver l'e-mail **uniquement s'il change réellement** ; ne pas répéter une valeur inchangée |
| `user.deleted` | identifier le compte supprimé après disparition de la ligne | **conserver** `name` et `email` dans `old_values` |
| `administrator_invitation.created` | identifier le destinataire de l'invitation | conserver, avec accès restreint |
| `rbac.role_assigned` / `role_removed` | l'e-mail n'apporte rien : le `subject_id` identifie déjà l'utilisateur | **retirer** `email` de l'allowlist RBAC, conserver `name` |

**Déduction** : l'allowlist actuelle `rbac.role_assigned` → `name`, `email` journalise une donnée personnelle sans nécessité probatoire, alors que `subject_id` et `causer_id` suffisent.

### 6.5 Politique minimale proposée

| Niveau | Données | Règle |
|---|---|---|
| **En clair** | `name`, `email` (création/suppression), dates techniques, noms de rôles/permissions, `correlation_id` | uniquement si le champ est nécessaire à la reconstitution de l'événement |
| **Masquées** | e-mail dans les sorties console ou les messages ; adresse IP si un jour collectée | masquage avant persistance ou avant affichage selon le cas |
| **Exclues** | mot de passe, `remember_token`, jetons, `token_hash`, cookies, sessions, IBAN/BIC/RIB, NIR, médical, contenu de document, corps d'e-mail | jamais dans `activity_log`, jamais dans `properties` |

**Recommandation** : ne pas journaliser un champ « par défaut parce qu'il est dans le modèle ». Ne transmettre à `AuditManager` que les champs réellement modifiés.

**Recommandation** : documenter que `audit.fields` est une décision de sécurité : toute modification doit être justifiée dans la revue de code.

---

## 7. Événements RBAC

### 7.1 Fonctionnement constaté

**Fait** (`app/Support/Rbac/RbacSynchronizer.php::synchronize()`) :

- `DB::transaction` englobe toute la synchronisation ;
- `ensurePermissions()` crée les `Permission` via `firstOrCreate` (ligne 120) ;
- chaque rôle est créé via `Role::firstOrCreate` (ligne 37) ;
- `syncPermissions()` remplace les permissions du rôle (ligne 51) ;
- le diff `added` / `removed` est calculé **avant** l'écriture ;
- un `AuditEventType::RbacPermissionAssigned` ou `RbacPermissionRevoked` est émis par permission ajoutée ou retirée (lignes 62 et 70) ;
- `finally` vide le cache Spatie.

**Fait** (`app/Actions/Administration/AcceptAdministratorInvitation.php`) : `assignRole()` est suivi d'un `RbacRoleAssigned` explicite, avec `role` et `guard` en métadonnées.

**Fait** : `config/permission.php` → `events_enabled = false`.

**Fait** : `tests/Feature/Audit/AuditRbacInstrumentationTest.php` contient un test « does not rely on Spatie permission events and therefore cannot duplicate them ».

### 7.2 Couverture réelle

| Opération | Couverte | Preuve |
|---|---|---|
| Attribution de rôle | **oui**, mais un seul point d'entrée (acceptation d'invitation) | `AcceptAdministratorInvitation` |
| Retrait de rôle | **non**, aucun point d'entrée | test API uniquement |
| Attribution de permission à un rôle | **oui**, par diff | `RbacSynchronizer` |
| Révocation de permission d'un rôle | **oui**, par diff | `RbacSynchronizer` |
| Création de rôle | **non** | `Role::firstOrCreate` |
| Création de permission | **non** | `Permission::firstOrCreate` |
| Mise à jour de rôle | **aucune opération** | aucune | 
| Suppression de rôle | **non applicable** : le synchroniseur ne supprime jamais | commentaire de classe |
| Suppression de permission | **non applicable** | aucune opération |

### 7.3 Événements candidats

| Candidat | Opération réelle | Catégorie proposée | Décision |
|---|---|---|---|
| `rbac.role_assigned` | oui (invitation) | `security` | conserver |
| `rbac.role_removed` | non (à créer en 2B) | `security` | créer en 2B |
| `rbac.permission_assigned` | oui (synchronisation) | `security` | conserver |
| `rbac.permission_revoked` | oui (synchronisation) | `security` | conserver |
| `rbac.role_created` | oui techniquement (`firstOrCreate`) | `system` | **non retenu** (voir 7.4) |
| `rbac.permission_created` | oui techniquement | `system` | **non retenu** (voir 7.4) |
| `rbac.role_updated` | aucune opération | — | **non retenu** |
| `rbac.role_deleted` | aucune opération | — | **non retenu** |

### 7.4 Créations de rôles et permissions : journaliser ou exclure ?

**Fait** : les rôles et permissions ne sont pas créés par un utilisateur. Ils sont définis dans le code (`app/Enums/RbacRole.php`, `app/Enums/PermissionName.php`, `app/Support/Rbac/RbacMatrix.php`) et matérialisés par `RbacSynchronizer` au déploiement ou au seed. `PermissionName` documente explicitement que seules les permissions dont la fonctionnalité existe peuvent être ajoutées.

**Déduction** : la création d'un rôle est un événement de **déploiement**, pas une action métier. Elle est déjà traçable par Git, la revue de code et l'historique de release.

**Recommandation** : ne pas créer `rbac.role_created` ni `rbac.permission_created`. Si une preuve d'exécution en production est souhaitée, journaliser un unique `system.rbac_synchronized` avec les compteurs (rôles créés, permissions créées, permissions ajoutées, permissions retirées) en catégorie `system`. Cela évite un événement par ligne et ne pollue pas le canal `security`.

**À valider** : le besoin d'un événement de synchronisation agrégé. Il n'est pas indispensable au regard de BP-192.

### 7.5 Risques de doublons

| Risque | Condition | Effet | Prévention |
|---|---|---|---|
| Événements Spatie Permission | `permission.events_enabled = true` | `assignRole`/`removeRole` émettent `RoleAttachedEvent`/`RoleDetachedEvent` **en plus** de l'audit explicite | maintenir `events_enabled = false` |
| `syncPermissions` avec événements activés | idem | la méthode révoque **toutes** les permissions courantes puis les réattribue : un rôle inchangé produirait N révocations et M attributions parasites | ne jamais activer les événements sans supprimer le diff |
| Observateur global sur `Role`/`Permission` | ajout ultérieur | double écriture | interdire par revue et test |
| `assignRole` sur modèle non persisté | utilisation future | l'attache est différée à l'événement `saved` | toujours utiliser un utilisateur existant |

**Fait** : `AuditRbacInstrumentationTest` vérifie déjà l'absence de dépendance aux événements Spatie. **Recommandation** : ajouter un test qui échoue si `config('permission.events_enabled')` passe à `true` sans décision explicite.

---

## 8. Préparation du Lot 2A — cycle de vie utilisateur

### 8.1 Opérations réellement disponibles

| Opération | Disponible | Point d'entrée |
|---|---|---|
| Création | **oui** | `POST /admin/invitation/{token}` → `AcceptAdministratorInvitation::handle()` |
| Modification | **non** | aucun contrôleur, action, Livewire ou ressource Filament |
| Suppression | **non** | idem |
| Désactivation | **non** | aucune colonne d'état |

**Déduction** : `user.updated` et `user.deleted` ne sont aujourd'hui produits que par les tests (`tests/Feature/Audit/AuditUserInstrumentationTest.php`) via `AuditManager::record()` directement. Ils ne doivent pas être comptés comme couverts en production.

### 8.2 Champs modifiables et champs à tracer

| Champ | Modifiable | À tracer | Justification |
|---|---|---|---|
| `name` | oui (futur écran) | oui | identité, changement visible |
| `email` | oui | oui, si réellement modifié | identifiant de connexion |
| `email_verified_at` | oui | oui | état de vérification |
| `password` | oui | **jamais la valeur** | secret ; journaliser éventuellement `password_changed = true` en métadonnée |
| `remember_token` | oui (technique) | **non** | secret |
| `roles` | via RBAC | via `rbac.*` | ne pas dupliquer dans `user.updated` |

**Recommandation** : pour un changement de mot de passe, émettre `user.updated` avec `new_values = []` et `metadata.password_changed = true`, ou un événement dédié `user.password_changed` si le besoin d'audit est confirmé. Ne jamais transmettre le hash.

### 8.3 Points d'entrée à instrumenter

**Recommandation** :

- si un écran Filament de gestion des utilisateurs est créé, instrumenter l'**action** métier, pas la ressource ni un observateur ;
- vérifier la permission côté serveur (`users.update` / `users.delete`, à créer avec la fonctionnalité) ;
- utiliser `AuditContext::user(actor: Auth::user())` pour l'acteur ;
- calculer `old_values` avant l'écriture et `new_values` après ;
- exécuter l'écriture métier et l'audit dans la même transaction.

### 8.4 Comportement attendu en cas de suppression

**Fait** : `User` n'utilise pas `SoftDeletes`. Une suppression est donc physique. `SpatieAuditLogger` accepte un sujet supprimé tant que sa clé existe, et `user.deleted` conserve `subject_id`.

**Conséquences** :

- la ligne `users` disparaît, mais les `activity_log` pointant vers elle restent (aucune contrainte de clé étrangère) ;
- `causer_id` peut devenir orphelin ; l'interface affiche déjà `Auteur supprimé` lorsque `causer_id` est nul (`AdminPanelProvider::panel()`), et la relation `causer` absente est gérée par l'opérateur `??` ;
- les jetons Sanctum de l'utilisateur doivent être révoqués par la même opération ;
- la suppression du dernier administrateur doit être interdite.

**Recommandation** : ne pas réutiliser `user.updated` pour une suppression. Écrire `user.deleted` avec `old_values = ['name', 'email']` et aucune valeur dans `new_values`.

**Recommandation** : ne jamais supprimer les lignes d'audit liées à l'utilisateur supprimé ; le journal doit survivre à son sujet.

### 8.5 Tests à prévoir pour 2A

| Test | Assertion |
|---|---|
| modification nominale | une ligne, `old_values` et `new_values` limités aux champs modifiés |
| changement de mot de passe | aucune valeur de mot de passe ni hash dans `attribute_changes` ni dans `properties` |
| changement d'e-mail | `old_values.email` et `new_values.email` corrects, ou masquage selon la décision |
| suppression | `user.deleted`, `subject_id` conservé, `new_values` vide |
| acteur | `causer_id` = administrateur ayant agi |
| permission refusée | aucune écriture et aucune ligne d'audit |
| transaction annulée | aucune ligne d'audit |
| dernier administrateur | suppression refusée et aucune ligne `user.deleted` |
| non-régression | `user.created` et le cycle d'invitation inchangés |

---

## 9. Préparation du Lot 2B — administration RBAC

### 9.1 Opérations et points d'entrée

| Opération | Existe | Point d'entrée actuel |
|---|---|---|
| Attribution de rôle | oui | `AcceptAdministratorInvitation::handle()` uniquement |
| Retrait de rôle | non | aucun |
| Attribution de permission à un rôle | oui (indirecte) | `RbacSynchronizer::synchronize()` |
| Révocation de permission d'un rôle | oui (indirecte) | `RbacSynchronizer::synchronize()` |
| Création de rôle | oui (technique) | `Role::firstOrCreate()` |
| Création de permission | oui (technique) | `Permission::firstOrCreate()` |

### 9.2 Événements nécessaires

| Événement | Nécessaire | Catégorie | Remarque |
|---|---|---|---|
| `rbac.role_assigned` | oui | `security` | généraliser au futur écran |
| `rbac.role_removed` | oui | `security` | à instrumenter avec le premier point d'entrée |
| `rbac.permission_assigned` | oui | `security` | déjà produit par diff |
| `rbac.permission_revoked` | oui | `security` | déjà produit par diff |
| `rbac.role_created` | non retenu | — | décision de code, traçable par Git |
| `rbac.permission_created` | non retenu | — | idem |
| `rbac.role_updated` | non retenu | — | aucune opération |
| `rbac.role_deleted` | non retenu | — | le synchroniseur ne supprime jamais |

### 9.3 Risques spécifiques

| Risque | Description | Prévention |
|---|---|---|
| Élévation de privilèges non tracée | attribuer `administrator` sans audit | instrumentation obligatoire de l'action, pas de l'interface |
| Retrait du dernier administrateur | verrouillage de l'application | contrôle métier + audit |
| Doublons Spatie | `events_enabled = true` | maintenir `false`, test dédié |
| Divergence matrice / base | rôle modifié manuellement en SQL | la synchronisation suivante répare et audite le diff |
| Permission inexistante | ajout d'une permission sans fonctionnalité | respecter `PermissionName` et `docs/security/RBAC.md` |
| Rôle supprimé manuellement | utilisateurs sans rôle | le synchroniseur recrée le rôle mais pas les attributions |

### 9.4 Tests à prévoir pour 2B

| Test | Assertion |
|---|---|
| attribution | une seule ligne `rbac.role_assigned`, métadonnées `role`, `guard` |
| retrait | une seule ligne `rbac.role_removed` |
| acteur | `causer_id` = administrateur |
| refus | aucune ligne si l'acteur n'a pas la permission |
| dernier administrateur | retrait refusé, aucune ligne |
| synchronisation sans changement | aucune ligne |
| synchronisation avec ajout | une ligne par permission ajoutée |
| synchronisation avec retrait | une ligne par permission retirée |
| doublons | aucune ligne Spatie en plus |
| transaction | rollback ⇒ aucune ligne |

---

## 10. Garde d'encodage

### 10.1 Périmètre actuel

**Fait** (`tests/Feature/Audit/AuditEncodingTest.php::auditSourceFiles()`) : la garde contrôle exactement :

| Fichier | Contenu contrôlé |
|---|---|
| `app/Enums/AuditEventType.php` | descriptions |
| `app/Actions/Administration/AcceptAdministratorInvitation.php` | raisons |
| `app/Actions/Administration/InviteAdministrator.php` | raisons |
| `app/Support/Rbac/RbacSynchronizer.php` | raison de synchronisation |
| `app/Listeners/AuditAuthenticationEvents.php` | raison d'échec |
| `config/audit.php` | allowlists, raisons |
| `config/filament-activity-log.php` | libellés |
| `config/activitylog.php` | commentaires |
| `.env.example` | variables |
| `.env.production.example` | variables |
| `docs/security/RBAC.md` | documentation |
| `docs/business-rules/bp-192-audit-*.md` (glob) | documentation d'audit |

**Fait** : la garde vérifie aussi les **16 descriptions exactes** et les **11 raisons exactes**, et contient un test négatif qui injecte volontairement une séquence et vérifie l'échec.

**Fait** : les séquences recherchées sont `e2949c c2ae`, `e2949c c2bf`, `e2949c c3a1`, `e2949c e294a4`, `e2949c c3a2`, `e2949c c3ab`, `c394 c387`, `c383 c2a9`, `c383 c2a8`, et le caractère de remplacement U+FFFD.

### 10.2 Fichiers et répertoires non couverts

| Zone | Exemples de textes français | Risque |
|---|---|---|
| `app/Providers/Filament/AdminPanelProvider.php` | `Système`, `Automatisé`, `Auteur supprimé`, `Origine`, `Auteur` | affichage de l'interface d'audit |
| `app/Enums/RbacRole.php` | libellés des rôles | affichage RBAC |
| `app/Console/Commands/InviteAdministratorCommand.php` | messages console | exploitation |
| `app/Http/Controllers/Auth/AcceptAdministratorInvitationController.php` | messages de redirection et d'erreur | parcours d'invitation |
| `app/Http/Requests/Auth/AcceptAdministratorInvitationRequest.php` | attributs de validation | messages de validation |
| `app/Models/User.php`, `app/Models/AdministratorInvitation.php` | commentaires et docblocks | documentation interne |
| `app/Notifications/AdministratorInvitationNotification.php` | contenu de l'e-mail | communication |
| `resources/views/auth/*.blade.php`, `resources/views/welcome.blade.php` | libellés | interface |
| `lang/fr/*.php` | traductions | messages |
| `config/administration.php`, `config/permission.php` | commentaires | documentation |
| `database/seeders/*`, `database/factories/*` | libellés de test | données de démonstration |
| `docs/security/*.md`, `docs/development/*.md` | documentation | documentation |

### 10.3 `AdminPanelProvider.php` : trois causes distinctes à ne pas confondre

| Cas | Signature | Vérification |
|---|---|---|
| **Fichier réellement corrompu** | octets non UTF-8, ou séquences `e2949c...`, `c383c2a9`, U+FFFD dans le fichier | `file_get_contents()` + `mb_check_encoding()` + `bin2hex()` |
| **Affichage terminal trompeur** | fichier UTF-8 valide, console affichant des caractères de remplacement | les octets attendus sont présents : `é` = `c3a9`, `’` = `e28099` |
| **Artefact de diff corrompu** | fichier `.diff` en UTF-16LE avec BOM `ff fe`, contenu déjà dégradé | `bin2hex(substr($bytes, 0, 4))` et `mb_detect_encoding()` |

**Fait** : lors du Lot 1E.3, `AdminPanelProvider.php` a présenté un affichage console illisible alors que ses octets étaient corrects (`Système` = `53797374 c3a8 6d65`, `Automatisé` = `...73 c3a9`). L'artefact `lot-1-complete.diff` était, lui, en UTF-16LE avec BOM et contenu lossy. Un `git diff --output=...` direct produit un fichier UTF-8 correct.

**Déduction** : la confusion provenait de la chaîne d'affichage et de la génération de l'artefact, pas du fichier source.

### 10.4 Risques de faux positifs

| Source | Nature | Traitement |
|---|---|---|
| `tests/Feature/Audit/AuditEncodingTest.php` | contient volontairement les séquences | exclure `tests/` du scan global |
| documentation illustrant un mojibake | contenu pédagogique | interdire les séquences littérales ; les décrire en hexadécimal |
| `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `public/build/` | dépendances et artefacts | exclure |
| fichiers binaires (images, PDF, archives) | non textuels | exclure par extension |
| `composer.lock`, `package-lock.json` | ASCII, aucun libellé | exclure ou traiter en ASCII strict |
| majuscule `Â` légitime | rare mais possible en français | ne pas la bannir seule ; cibler `Â` suivi d'espace ou d'un caractère de contrôle |
| U+01F8 / U+01F9 | jamais légitimes en français, observés dans la console | **ajouter** à la liste négative |

**Fait** : la liste actuelle ne contient ni U+01F8/U+01F9 ni U+00C2 seul, alors que la console de l'environnement a produit des rendus illisibles (par exemple un `r` suivi de U+01F8, puis `f`, puis U+01F8, puis `rentiel`). **Déduction** : un copier-coller depuis la console pourrait introduire une corruption non détectée.

### 10.5 Amélioration proposée (sans modifier les fichiers existants)

**Recommandation** :

1. créer une fonction `auditTextFiles()` qui scanne, par extension, `app/`, `config/`, `resources/views/`, `lang/fr/`, `database/`, `routes/` et `docs/`, en excluant `vendor`, `node_modules`, `storage`, `bootstrap/cache`, `public/build`, `tests`, et les extensions binaires ;
2. conserver la liste explicite actuelle pour les assertions de texte exact ;
3. ajouter U+01F8, U+01F9 et U+00C2 suivi d'une espace à la liste négative ;
4. ajouter une assertion dédiée aux libellés de `AdminPanelProvider.php` (`Système`, `Automatisé`, `Auteur supprimé`, `Origine`, `Auteur`) ;
5. ajouter un test qui vérifie qu'aucun fichier de `app/` ne contient U+FFFD ;
6. conserver le test négatif existant.

**Déduction** : ce lot relève de la qualité, pas du Lot 2 métier. Il peut être livré indépendamment, sans dépendre des décisions de conservation ou d'intégrité.

---

## 11. Risques techniques

| # | Risque | Nature | Impact | Recommandation |
|---|---|---|---|---|
| 1 | Purge via `activitylog:clean` | Fait | ignore le hold, non journalisée, non archivée | ne pas l'exécuter ; construire une purge applicative |
| 2 | Absence de chaînage HMAC | Fait | suppression indétectable | vérification planifiée + ancrage externe |
| 3 | Re-signature via `filament-activity-log:backfill` | Fait | un administrateur peut blanchir une modification | restreindre l'accès console, tracer son exécution |
| 4 | Rotations de clé non gérées | Fait | historique invérifiable | procédure de rotation + conservation de l'ancienne clé |
| 5 | Garde limitée à `production` | **Corrigé** | repli `APP_KEY` limité à `local` et `testing` ; tout autre environnement exige une clé dédiée | corrigé par le lot correctif final |
| 6 | Buffer Activitylog activable | Fait | lignes non signées | test de configuration bloquant |
| 7 | Événements Spatie Permission activables | Fait | doublons et bruit | test bloquant + documentation |
| 8 | Observateurs ajoutés ultérieurement | Risque | doublons | règle « instrumentation explicite uniquement » |
| 9 | E-mail en clair dans `rbac.role_*` | Fait | donnée personnelle inutile | retirer de l'allowlist RBAC |
| 10 | Aucune isolation par établissement | Décision non tranchée | accès trop large aux données de paie | trancher avant les domaines RH |
| 11 | Conservation non arbitrée | Fait | conservation illimitée ou purge non conforme | décision juridique |
| 12 | Garde d'encodage partielle | Fait | corruption non détectée | étendre le périmètre |
| 13 | Pas de clé d'idempotence | Fait | doublons en cas de rejeu | corréler, ou ajouter une clé en métadonnée |
| 14 | Sauvegardes non testées en restauration | Risque | perte de preuve | exercice de restauration périodique |

---

## 12. Décisions à valider

Liste courte, à trancher avant toute implémentation :

1. **Conservation** : durées par catégorie (`business`, `security`, `system`), base légale, et recours au hold. Aucune durée n'est fixée par cette étude.
2. **Intégrité** : niveau de preuve attendu. Le HMAC individuel suffit-il, ou faut-il une vérification planifiée, un ancrage externe, voire un archivage immuable ?
3. **Archivage** : faut-il un archivage immuable, ou un export privé avec manifeste signé suffit-il ?
4. **Confidentialité des événements utilisateur** : conserver l'e-mail en clair à la création et à la suppression ? Le journaliser lors d'une modification ? Retirer `email` de l'allowlist RBAC ?
5. **Périmètre RBAC** : exclure les créations de rôles et permissions, ou les journaliser en `system` sous forme agrégée ?
6. **Garde d'encodage** : étendre à tout `app/`, `config/`, `resources/views/`, `lang/fr/`, `database/`, `docs/` ?
7. **Périmètre d'accès** : la mono-entreprise étant acquise, un utilisateur doit-il être restreint à certains établissements pour les données de paie ?

---

## 13. Recommandations finales

1. **Ne pas activer la purge** avant d'avoir une commande qui respecte le hold, archive et journalise l'opération.
2. **Conserver le HMAC individuel** et ajouter une vérification planifiée avec alerte.
3. **Ancrer périodiquement** un manifeste signé hors base, sans modifier le schéma.
4. **Retirer `email` de l'allowlist `rbac.role_assigned` / `rbac.role_removed`** : le `subject_id` suffit.
5. **Ne pas journaliser les créations de rôles et permissions** ; privilégier un éventuel événement agrégé `system`.
6. **Maintenir `permission.events_enabled = false`** et verrouiller par test.
7. **Étendre la garde d'encodage** aux sources applicatives, en excluant `tests/` et les artefacts.
8. **Documenter la mono-entreprise** dans le code et la documentation pour éviter l'ajout réflexe d'un scope tenant.
9. **Trancher la question de l'établissement** avant l'instrumentation RH.
10. **Rester sur une instrumentation explicite** : pas d'observateur global, pas de `LogsActivity`.

---

## 14. Plan d'implémentation proposé

| Phase | Contenu | Dépendances | Complexité | Statut |
|---|---|---|---|---|
| 0 | Validation des décisions de la section 12 | métier, DPO, juridique | faible | à faire |
| 1 | Lot 2A : cycle de vie utilisateur | décisions 4 et 7 | moyenne | à valider |
| 2 | Lot 2B : administration RBAC | décisions 5 et 7 | moyenne | à valider |
| 3 | Purge contrôlée : commande applicative respectant le hold, archivage, journal d'exploitation | décision 1 | moyenne à élevée | hors 2A/2B |
| 4 | Exploitation de l'intégrité : vérification planifiée, manifeste ancré, procédure de rotation | décisions 2 et 3 | moyenne | hors 2A/2B |
| 5 | Garde d'encodage étendue | décision 6 | faible | indépendant |
| 6 | Domaines RH et paie | livraison des domaines | élevée | non planifiable aujourd'hui |

**Recommandation d'ordre** : 5 (faible coût, bénéfice immédiat), puis 0, puis 1 et 2, puis 3 et 4. La phase 5 peut être livrée sans attendre les arbitrages métier.

**Rappel** : cette étude ne commence ni le Lot 2A ni le Lot 2B. Toute implémentation attend une validation explicite.
