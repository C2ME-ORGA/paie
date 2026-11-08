# BP-192 — Lot 1E : consolidation et sécurisation finale du socle d’audit

**Ticket Jira :** BP-192 — Implémenter le journal d’audit métier complet  
**Lot :** 1E — corrections ciblées et non-régression  
**Date :** 8 novembre 2026  
**Branche :** `develop`  
**Commit de départ :** `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`

---

## 1. Objet

Le Lot 1E corrige quatre points identifiés lors de la revue du diff complet :

1. rollback destructif potentiel de la migration des colonnes d’audit ;
2. encadrement insuffisant des valeurs de métadonnées libres ;
3. classification des événements RBAC comme événements métier ;
4. absence de garde-fou explicite sur la clé HMAC en production.

Aucune refonte de l’architecture d’audit n’a été réalisée.

---

## 2. Rollback des migrations

### 2.1 Problème

La migration `2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php` ajoute des colonnes et des index de façon conditionnelle. Laravel ne conserve pas l’information permettant de savoir si une colonne existait avant la migration.

Un `down()` qui supprime toute colonne existante peut donc supprimer une colonne créée par un autre package, une migration antérieure ou une intervention manuelle.

### 2.2 Solution retenue

Une table de propriété explicite est créée sur la connexion de la table d’activité :

```text
activity_log_audit_control_objects
  object_type  string(16)   -- "column" ou "index"
  object_name  string(100)
  primary key (object_type, object_name)
```

La migration :

1. détermine les colonnes et index absents avant exécution ;
2. enregistre leur propriété dans cette table **avant** les opérations DDL ;
3. crée uniquement ces objets ;
4. en `down()`, ne supprime que les objets enregistrés comme créés par cette migration ;
5. supprime ensuite la table de propriété.

La migration vérifie également l’existence réelle de chaque objet avant de le supprimer, ce qui couvre une exécution partielle ou une suppression manuelle.

### 2.3 Installations antérieures

Les installations qui ont exécuté l’ancienne migration avant l’introduction de la table de propriété n’ont pas de marqueur.

Dans ce cas, `down()` est volontairement **conservateur** : il ne supprime aucune colonne. Les colonnes sont laissées en place pour éviter toute perte de données. Un nettoyage manuel documenté reste possible.

### 2.4 Tests

`tests/Feature/Audit/AuditMigrationRollbackTest.php` couvre :

- installation sur schéma vierge ;
- rollback normal ;
- colonnes préexistantes conservées ;
- index préexistant conservé ;
- rollback sans marqueur de propriété ;
- table `activity_log` absente : exception explicite ;
- réexécution après rollback.

Si la table `activity_log` est absente, `up()` échoue désormais explicitement au lieu d’être marquée exécutée sans installer le schéma attendu. Les tests utilisent SQLite en mémoire.

---

## 3. Encadrement des métadonnées

### 3.1 Classification

`config/audit.php` distingue trois familles.

#### A. Valeurs techniques contrôlées

Valeurs autorisées explicitement dans `metadata_values` :

| Clé | Valeurs |
|---|---|
| `source` | `http`, `console`, `queue`, `scheduler`, `test`, `auth` |
| `channel` | `administrator_invitation`, `rbac_synchronization`, `session` |
| `origin` | `user`, `system`, `automated` |
| `guard` | `web` |
| `actor_type` | `user`, `system`, `automated`, `invitation_recipient` |
| `initiated_by` | `invitation_recipient` |

Toute autre valeur est supprimée avant persistance.

#### B. Identifiants techniques

`request_id` et `correlation_id` doivent respecter :

```text
/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/
```

Les corrélations générées par `AuditManager` sont des UUID. Le format accepte aussi des identifiants opaques sûrs déjà utilisés par les tests et imports, sans espaces, chemins ou fragments de payload.

`command`, `job`, `role` et `permission` doivent respecter :

```text
/^[A-Za-z0-9][A-Za-z0-9_.:\-]{0,199}$/
```

#### C. Valeurs descriptives libres

`reason` n’est plus une chaîne libre. Elle doit appartenir à une liste explicite de motifs métier :

- Acceptation d’une invitation administrateur ;
- Révocation d’une invitation en attente ;
- Création d’une invitation administrateur ;
- Échec d’envoi de l’invitation administrateur ;
- Révocation après échec d’envoi de l’invitation administrateur ;
- Synchronisation du référentiel RBAC ;
- Échec d’authentification ;
- Retrait du rôle administrateur ;
- Création d’un utilisateur ;
- Mise à jour d’un utilisateur ;
- Suppression d’un utilisateur.

Une raison inconnue, trop longue (plus de 200 caractères), non scalaire, structurée ou contenant un token, un mot de passe ou une donnée bancaire est **supprimée**. L’événement d’audit reste écrit, mais sans `reason`.

Aucune valeur rejetée n’est écrite dans un log technique.

### 3.2 Point d’application

Le filtrage reste centralisé dans `AuditSanitizer::sanitizeMetadata()`. Aucune règle de métadonnée n’a été ajoutée dans les modèles, contrôleurs, listeners ou composants Filament.

### 3.3 Tests

`tests/Unit/Audit/AuditSanitizerTest.php` couvre :

- motif autorisé ;
- motif inconnu ;
- motif trop long ;
- motif structuré ;
- motif contenant token, mot de passe ou IBAN ;
- corrélation valide et invalide ;
- identifiants role/permission valides et invalides ;
- métadonnée inconnue ;
- absence de fuite dans le résultat.

`tests/Feature/Audit/AuditLoggerTest.php` vérifie qu’un motif invalide ne fait pas échouer l’audit et n’apparaît pas dans la ligne d’activité.

---

## 4. Classification des événements

### 4.1 Nouvelle classification

Un enum dédié a été ajouté :

```text
App\Enums\AuditEventCategory
  business
  security
  system
```

`AuditEventType::category()` applique :

| Famille | Catégorie | Log name |
|---|---|---|
| `user.*`, `administrator_invitation.*` | `business` | `audit` |
| `rbac.*` | `security` | `security` |
| `security.*` | `security` | `security` |
| `system.*` | `system` | `system` |

`isBusiness()` ne dépend plus du log name `audit` : il dépend de la catégorie explicite.

### 4.2 Compatibilité

Les valeurs d’événements persistées (`rbac.role_assigned`, etc.) sont inchangées.

Les anciennes traces RBAC écrites avant le Lot 1E conservent leur `log_name = audit`. Les nouvelles traces RBAC utilisent `log_name = security`. Les filtres de consultation peuvent donc afficher les deux canaux pour la période de transition.

Aucune réécriture de données historiques n’a été effectuée.

---

## 5. Intégrité HMAC en production

### 5.1 Garde-fou

`App\Support\Audit\AuditIntegrityConfiguration::assertConfigured()` est appelé au démarrage par `AppServiceProvider`.

En production :

- l’intégrité doit rester activée ;
- `ACTIVITY_LOG_INTEGRITY_KEY` doit être renseignée ;
- la clé doit contenir au moins 32 caractères ;
- la clé doit être différente de `APP_KEY` ;
- l’absence, une clé trop courte ou une clé identique à `APP_KEY` provoque une exception explicite au démarrage ;
- aucun repli silencieux sur `APP_KEY` n’est autorisé.

En local et en test, le repli du package sur `APP_KEY` reste possible pour ne pas bloquer le développement.

### 5.2 Génération et conservation

Procédure recommandée :

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

La valeur produite doit être :

- stockée dans le gestionnaire de secrets de l’environnement ;
- stable entre redémarrages et déploiements ;
- sauvegardée séparément de la base ;
- accessible uniquement aux processus autorisés ;
- jamais affichée dans les logs, l’interface ou une erreur.

### 5.3 Rotation

Le package ne gère qu’une seule clé. Une rotation rend les anciennes signatures invérifiables par la commande standard.

Aucun mécanisme multi-clés n’a été implémenté dans ce lot. Toute rotation doit être précédée d’une procédure documentée : vérification de l’intégrité avant rotation, conservation externe de l’ancienne clé, période de transition et archivage des preuves.

### 5.4 Limite de sécurité

Le HMAC détecte une modification de ligne. Il ne protège pas contre :

- la suppression d’une ligne ;
- une modification coordonnée de la base et du mécanisme de vérification ;
- un attaquant disposant de la clé HMAC.

Le journal n’est pas un stockage inviolable. Les droits SQL, les sauvegardes et un ancrage externe restent nécessaires pour un niveau de preuve supérieur.

---

## 6. Corrections du Lot 1C rappelées

- `initiated_by = invitation_recipient` documente l’acceptation d’invitation sans inventer d’acteur authentifié.
- `administrator_invitation.delivery_failed` distingue l’échec de livraison de la révocation réelle.
- `administrator_invitation.revoked` reste utilisé lorsque `revoked_at` est effectivement positionné.

---

## 7. Fichiers créés

- `app/Enums/AuditEventCategory.php`
- `app/Support/Audit/AuditIntegrityConfiguration.php`
- `tests/Feature/Audit/AuditMigrationRollbackTest.php`
- `tests/Feature/Audit/AuditIntegrityConfigurationTest.php`
- `tests/Feature/Audit/AuditEncodingTest.php`
- `docs/business-rules/bp-192-audit-consolidation.md`

## 8. Fichiers modifiés

- `database/migrations/2026_11_08_160744_upgrade_activity_log_with_audit_control_columns.php`
- `app/Services/Audit/AuditSanitizer.php`
- `config/audit.php`
- `app/Enums/AuditEventType.php`
- `app/Providers/AppServiceProvider.php`
- `.env.example`
- `.env.production.example`
- `tests/Unit/Audit/AuditSanitizerTest.php`
- `tests/Unit/Audit/AuditEventTypeTest.php`
- `tests/Feature/Audit/AuditLoggerTest.php`
- `tests/Feature/Audit/AuditRbacInstrumentationTest.php`
- `docs/business-rules/bp-192-audit-implementation.md`
- `docs/business-rules/bp-192-audit-instrumentation.md`

---

## 9. Tests

### 9.1 Tests ciblés

| Commande | Résultat |
|---|---|
| `php artisan test --filter=Audit` | **139 tests, 139 réussis, 536 assertions** |
| `php artisan test --filter=AuditMigrationRollbackTest` | **7 tests, 7 réussis, 63 assertions** |
| `php artisan test --filter=AuditIntegrityConfigurationTest` | **6 tests, 6 réussis, 10 assertions** |
| `php artisan test --filter=AuditEncodingTest` | **5 tests, 5 réussis, 46 assertions** |

### 9.2 Suite complète

```bash
php artisan test
```

Résultat : **220 tests, 220 réussis, 869 assertions**.

Le reporter Pest signale également **1 warning non détaillé**, sans test en échec ni test ignoré.

### 9.3 Qualité

| Commande | Résultat |
|---|---|
| `vendor/bin/pint --test` | Réussi |
| `vendor/bin/phpstan analyse --no-progress` | Réussi, 0 erreur |
| `composer test` | Réussi |
| `composer validate --no-check-publish --strict` | Réussi |
| `composer audit` | Aucun avis de sécurité ; 2 packages abandonnés préexistants |

---

## 10. Limites et risques restants

- Le rollback d’une installation antérieure au marqueur de propriété ne supprime pas les colonnes ; un nettoyage manuel reste nécessaire.
- La validation des métadonnées supprime les valeurs invalides ; elle ne signale pas leur rejet à l’appelant.
- La liste des raisons est statique et doit être maintenue avec les futurs domaines.
- Les anciennes traces RBAC restent dans le canal `audit` ; aucune réécriture historique n’est faite.
- La clé HMAC ne peut pas être tournée sans procédure externe de conservation des anciennes signatures.
- Le HMAC ne détecte pas les suppressions de lignes.
- La consultation du journal n’est pas elle-même auditée.
- Les exports, la conservation, le legal hold et l’ancrage externe restent hors périmètre.

---

## 11. Écarts par rapport à BP-192

Aucun écart fonctionnel nouveau. Le lot corrige des faiblesses de sécurité et de compatibilité sans ajouter de domaine métier ni de permission supplémentaire.

---

## 12. Encodage UTF-8 et artefact `lot-1-complete.diff`

Les fichiers source, configurations, tests et documentations du dépôt sont valides en
UTF-8. Les chaînes d’audit réelles sont correctes : `Création d’un utilisateur`,
`Révocation d’une invitation`, `Échec d’authentification`, etc.

Les séquences de corruption de type double encodage observées provenaient de
l’artefact généré `lot-1-complete.diff`. Cet artefact est en UTF-16LE avec BOM
(`ff fe`) et son contenu a subi une conversion lossy avant enregistrement. Un
`git diff --output` direct produit, lui, un fichier UTF-8 correct. L’artefact avait
été ajouté à l’index Git ; il a été retiré de l’index sans supprimer sa copie locale :

```bash
git rm --cached -f lot-1-complete.diff
```

Le fichier reste présent dans le worktree comme fichier non suivi ; aucune règle
`.gitignore` n’a été ajoutée et aucun autre fichier utilisateur n’a été modifié.

Un contrôle automatisé `tests/Feature/Audit/AuditEncodingTest.php` vérifie :

- l’UTF-8 valide des descriptions d’événements et des motifs `reason` ;
- l’absence des séquences de double encodage listées dans le test ;
- la présence exacte des onze motifs de production dans la liste blanche ;
- l’absence de séquences de corruption dans les fichiers applicatifs et les documentations concernées.

## 13. Verdict

**LOT 1E VALIDÉ.**

Le rollback ne supprime plus de colonnes dont la propriété n’est pas démontrée, les métadonnées libres sont strictement encadrées, les événements RBAC sont classés en sécurité et la clé HMAC est obligatoire et contrôlée en production. Les tests ciblés et la suite complète passent, ainsi que Pint et PHPStan.
