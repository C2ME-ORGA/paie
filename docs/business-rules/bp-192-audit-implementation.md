# BP-192 — Lot 1B : couche métier d'audit

**Ticket Jira :** BP-192 — Implémenter le journal d'audit métier complet  
**Lot :** 1B — couche métier centralisée  
**Date :** 8 novembre 2026  
**Branche :** `develop`  
**Commit de départ :** `296adca07191cb7ee7755cce91a9d0a3bd17ccc8`  
**Documents de référence :**

- [`bp-192-audit-package-assessment.md`](bp-192-audit-package-assessment.md) — étude de faisabilité ;
- [`bp-192-audit-foundation.md`](bp-192-audit-foundation.md) — socle technique du Lot 1A.

> Ce document décrit le Lot 1B à sa date. Les évolutions des Lots 1D et 1E
> (permission `audit.view`, interface Filament, classification RBAC en sécurité,
> validation des métadonnées et intégrité HMAC) sont documentées dans
> [`bp-192-audit-filament.md`](bp-192-audit-filament.md) et
> [`bp-192-audit-consolidation.md`](bp-192-audit-consolidation.md).

---

## 1. Objet et périmètre

Le Lot 1B fournit l’API métier officielle de journalisation. Il ne journalise encore **aucune opération métier réelle** : l’instrumentation de `User`, `AdministratorInvitation`, du RBAC, de l’authentification, des commandes et des jobs appartient au Lot 1C.

Le package Spatie reste un détail d’implémentation. La couche métier ne dépend pas de Filament et n’expose jamais le modèle Spatie `Activity` aux domaines.

---

## 2. Architecture retenue

```text
Domaines métier
    │
    ▼
AuditManager  ──────────────►  AuditContext
    │                               │
    │                               ├── acteur utilisateur / système / automatisé
    │                               ├── corrélation
    │                               ├── justification
    │                               └── métadonnées autorisées
    ▼
AuditLogger (contrat)
    │
    ▼
SpatieAuditLogger ──────────► AuditSanitizer
    │                               │
    │                               ├── liste blanche par modèle/événement
    │                               ├── liste noire de clés sensibles
    │                               └── filtrage récursif
    ▼
Spatie Activitylog
    │
    ▼
Table activity_log
```

### 2.1 Répartition des responsabilités

| Composant | Responsabilité |
|---|---|
| `AuditEventType` | Catalogue central des événements autorisés, validation et canal de journal |
| `AuditOrigin` | Origine `user`, `system` ou `automated` |
| `AuditContext` | Contexte immuable : acteur, origine, corrélation, justification, métadonnées |
| `AuditSanitizer` | Politique de confidentialité : liste blanche, liste noire, récursion, objets |
| `AuditLogger` | Contrat d’écriture officiel |
| `SpatieAuditLogger` | Implémentation Spatie : sanitization, construction, persistance, résultat |
| `AuditManager` | Façade métier : contexte courant, contexte temporaire, réinitialisation, délégation |
| `AuditResult` | Résultat explicite sans exposition du modèle Spatie |
| `AuditLoggingException` | Erreur d’écriture sans valeur auditée dans le message |

### 2.2 Règle d’utilisation

Les domaines métier utilisent **`AuditManager`**. Les services internes qui ont besoin d’un contrôle explicite du contexte peuvent utiliser le contrat `AuditLogger`.

Ils ne doivent jamais :

- appeler directement `activity()` ;
- appeler `SpatieAuditLogger` ;
- écrire dans `activity_log` avec Eloquent ou SQL ;
- fournir un tableau de propriétés Spatie brut.

Cette règle est une **convention architecturale**. Elle n’interdit pas techniquement l’accès direct au package, mais elle est vérifiable en revue de code et par recherche des appels à `activity()` dans `app/`.

Au Lot 1B, le seul appel applicatif à `activity()` se trouve dans `SpatieAuditLogger`.

---

## 3. Catalogue d’événements

### 3.1 Emplacement

`app/Enums/AuditEventType.php`

Le catalogue est un enum PHP à valeur string. Il refuse les chaînes arbitraires et garantit l’unicité et la stabilité des noms.

### 3.2 Convention de nommage

Format : `domaine.action` ou `domaine.sous_domaine.action`.

- segments en minuscules ;
- séparateur `.` ;
- underscore autorisé dans un segment ;
- préfixes techniques réservés :
  - `security.*` → journal `security` ;
  - `rbac.*` → journal `security` (classification consolidée au Lot 1E) ;
  - `system.*` → journal `system` ;
  - les événements métier → journal `audit`.

### 3.3 Catalogue initial

| Événement | Valeur | Nature |
|---|---|---|
| `UserCreated` | `user.created` | Métier |
| `UserUpdated` | `user.updated` | Métier |
| `UserDeleted` | `user.deleted` | Métier |
| `AdministratorInvitationCreated` | `administrator_invitation.created` | Métier |
| `AdministratorInvitationConsumed` | `administrator_invitation.consumed` | Métier |
| `AdministratorInvitationRevoked` | `administrator_invitation.revoked` | Métier |
| `RbacRoleAssigned` | `rbac.role_assigned` | Sécurité |
| `RbacRoleRemoved` | `rbac.role_removed` | Sécurité |
| `SecurityAccessDenied` | `security.access_denied` | Technique/sécurité |
| `SystemCommandCompleted` | `system.command_completed` | Technique/système |

Ce catalogue est une **préparation**. Aucun de ces événements n’est instrumenté dans le Lot 1B.

### 3.4 Ajouter un événement

1. Ajouter un cas dans `AuditEventType`.
2. Ajouter la description associée.
3. Ajouter les champs autorisés dans `config/audit.php` si l’événement porte des attributs.
4. Ajouter les tests de validation et de sanitization.
5. Instrumenter l’opération au Lot 1C ou dans le lot du domaine concerné.

`AuditEventType::fromValue()` valide une valeur externe ; `AuditLogger` n’accepte que l’enum, ce qui empêche un type inconnu d’atteindre la persistance.

---

## 4. Contexte d’audit

### 4.1 Emplacement

`app/Services/Audit/AuditContext.php`

Le contexte est un objet immuable (`final readonly`) contenant :

| Champ | Rôle |
|---|---|
| `origin` | `user`, `system` ou `automated` |
| `actor` | Modèle utilisateur pour une origine `user`, `null` sinon |
| `correlationId` | Corrélation d’une opération ou d’un lot d’événements |
| `reason` | Justification métier éventuelle |
| `metadata` | Métadonnées autorisées, filtrées avant écriture |

### 4.2 Règles d’attribution

- Une origine `user` **exige** un acteur.
- Une origine `system` ou `automated` **interdit** un acteur humain.
- Une opération sans utilisateur authentifié ne doit jamais recevoir artificiellement un utilisateur.
- Un job ou un traitement automatisé doit utiliser `AuditContext::automated()`. Le contexte par défaut sans utilisateur est `system`, pas `automated`.

### 4.3 Contexte par défaut

`AuditManager::currentContext()` construit un contexte si aucun contexte explicite n’est actif :

- utilisateur authentifié présent → contexte `user` avec cet acteur ;
- sinon → contexte `system` ;
- une corrélation UUID est générée ;
- la source `http` ou `console` est ajoutée aux métadonnées.

### 4.4 Isolation et réinitialisation

`AuditManager` est lié en **scoped** dans le conteneur Laravel. Laravel réinitialise les instances scoped entre les jobs d’une queue et, avec Octane, entre les requêtes.

En complément :

- `AuditManager::withContext()` isole un contexte temporaire et restaure le précédent dans un `finally` ;
- `AuditManager::resetContext()` force la reconstruction du contexte par défaut ;
- les jobs longs ou les workers de longue durée doivent appeler explicitement `resetContext()` ou utiliser `withContext()`.

### 4.5 Exemple job

```php
final class ImportPayrollVariables
{
    public function __construct(
        private readonly AuditManager $audit,
    ) {}

    public function handle(): void
    {
        $this->audit->withContext(
            AuditContext::automated(
                correlationId: (string) Str::uuid(),
                reason: 'Import automatique des variables de paie',
            ),
            function (): void {
                // Travail métier.
            },
        );
    }
}
```

---

## 5. Sanitizer et confidentialité

### 5.1 Emplacement

`app/Services/Audit/AuditSanitizer.php`  
Configuration : `config/audit.php`

### 5.2 Principe

Le sanitizer est appliqué **systématiquement** par `SpatieAuditLogger`, avant toute construction d’activité Spatie.

- un modèle/événement absent de la liste blanche ne journalise aucun attribut ;
- un champ absent de la liste blanche est supprimé ;
- un champ sensible est supprimé **même s’il figure dans la liste blanche** ;
- les métadonnées ont leur propre liste blanche ;
- les tableaux et objets JSON sont filtrés récursivement ;
- un objet non supporté provoque une erreur et **aucune donnée brute n’est écrite**.

### 5.3 Liste blanche par ressource et événement

```php
'fields' => [
    User::class => [
        'user.created' => ['name', 'email', 'email_verified_at'],
        'user.updated' => ['name', 'email', 'email_verified_at'],
        'user.deleted' => ['name', 'email'],
    ],
],
```

Cette configuration est un **catalogue de sécurité**. Elle ne déclenche aucune journalisation automatique sur `User` ou `AdministratorInvitation`.

### 5.4 Liste noire

La liste noire centralisée couvre notamment :

- mots de passe et confirmations ;
- tokens d’accès, de réinitialisation et tokens hashés ;
- secrets, clés API et clés privées ;
- `authorization`, cookies et sessions ;
- credentials ;
- IBAN, BIC, comptes bancaires, NIR, sécurité sociale ;
- données médicales et de santé ;
- numéros de carte, CVV/CVC et PIN.

La détection normalise la casse et les séparateurs (`access_token`, `accessToken`, `Access-Token`, `access.token`) puis applique des motifs larges. Les faux positifs sont préférés aux fuites.

### 5.5 Récursion et objets

- tableaux associatifs : les clés sensibles sont supprimées à chaque niveau ;
- tableaux listes : les valeurs sont filtrées récursivement ;
- `JsonSerializable` : le résultat est filtré récursivement ;
- `DateTimeInterface` : converti en chaîne ISO 8601 ;
- enums : valeur ou nom ;
- modèles Eloquent et objets inconnus : **refusés**, jamais sérialisés automatiquement ;
- profondeur maximale configurable (`max_depth`, 8 par défaut).

### 5.6 Anciennes et nouvelles valeurs

Les valeurs précédentes et les nouvelles valeurs sont filtrées séparément avec la même liste blanche. Un champ interdit dans l’ancien état ne peut pas réapparaître dans le nouvel état, et inversement.

---

## 6. API publique

### 6.1 `AuditManager`

Point d’entrée recommandé pour les domaines :

```php
$result = app(AuditManager::class)->record(
    event: AuditEventType::UserUpdated,
    subject: $user,
    oldValues: ['name' => $oldName],
    newValues: ['name' => $newName],
);
```

Signature :

```php
public function record(
    AuditEventType $event,
    Model $subject,
    array $oldValues = [],
    array $newValues = [],
    ?AuditContext $context = null,
): AuditResult;
```

Le contexte explicite est optionnel. Sans contexte, le contexte courant est utilisé. Sans contexte courant, le contexte par défaut est construit.

### 6.2 Gestion du contexte

```php
$audit = app(AuditManager::class);

$audit->currentContext();

$audit->withContext(
    AuditContext::system(reason: 'Synchronisation RBAC'),
    function () use ($audit): void {
        // Opérations.
    },
);

$audit->resetContext();
```

### 6.3 `AuditLogger`

Contrat bas niveau :

```php
public function record(
    AuditEventType $event,
    Model $subject,
    array $oldValues,
    array $newValues,
    AuditContext $context,
): AuditResult;
```

Le contrat n’expose aucune méthode de modification, de suppression ou d’ajout de propriétés Spatie libres.

### 6.4 `AuditResult`

```php
final readonly class AuditResult
{
    public function __construct(
        public int $activityId,
        public AuditEventType $event,
        public string $subjectType,
        public int|string $subjectId,
        public string $logName,
    ) {}
}
```

Le modèle Spatie `Activity` n’est pas exposé.

### 6.5 Erreurs

`AuditLoggingException` est levée lorsque :

- le sujet n’est pas persisté ;
- le sanitizer refuse une valeur ;
- Spatie ne persiste pas l’activité ;
- une erreur d’écriture survient.

Les messages ne contiennent jamais les valeurs auditées. L’exception précédente est conservée pour le diagnostic technique, sans payload métier.

---

## 7. Transactions

Spatie Activitylog écrit l’activité sur la même connexion que le sujet. Dans une transaction Laravel :

- **commit** : la modification métier et l’activité sont conservées ;
- **rollback** : les deux sont annulées.

Ce comportement est celui attendu pour un événement décrivant une modification métier : aucune trace ne doit affirmer qu’une opération annulée a été validée.

Les événements de sécurité qui devraient survivre à un rollback (tentative refusée, export, accès) ne sont pas traités dans ce lot. Ils nécessiteront une stratégie dédiée, documentée, probablement une connexion ou un canal séparé. Aucun système asynchrone n’a été introduit.

---

## 8. Exemples d’utilisation

### 8.1 Modification métier avec acteur courant

```php
final class UpdateEmployee
{
    public function __construct(
        private readonly AuditManager $audit,
    ) {}

    public function handle(Employee $employee, array $data): Employee
    {
        $oldValues = $employee->only(['name', 'email']);

        $employee->update($data);

        $this->audit->record(
            event: AuditEventType::UserUpdated,
            subject: $employee,
            oldValues: $oldValues,
            newValues: $employee->only(['name', 'email']),
        );

        return $employee;
    }
}
```

Le contexte est celui de la requête HTTP : acteur authentifié, corrélation générée, source `http`.

### 8.2 Opération système explicite

```php
$audit->withContext(
    AuditContext::system(
        correlationId: $batchId,
        reason: 'Réinitialisation technique',
    ),
    function () use ($audit, $user): void {
        $audit->record(
            event: AuditEventType::UserUpdated,
            subject: $user,
            oldValues: ['name' => $oldName],
            newValues: ['name' => $newName],
        );
    },
);
```

Aucun acteur humain n’est attaché à cette opération.

### 8.3 Traitement automatisé

```php
$audit->withContext(
    AuditContext::automated(
        correlationId: $importId,
        reason: 'Import planifié',
        metadata: ['job' => ImportPayrollVariables::class],
    ),
    fn () => $import->run(),
);
```

---

## 9. Règles pour les futurs développeurs

1. Utiliser `AuditManager` pour toute journalisation métier.
2. Ne jamais appeler `activity()` directement dans un domaine.
3. Ne jamais écrire dans `activity_log` avec Eloquent ou SQL.
4. Ne jamais passer de propriétés Spatie brutes.
5. Ajouter un cas à `AuditEventType` avant d’instrumenter un nouvel événement.
6. Ajouter les champs autorisés dans `config/audit.php` avec une revue sécurité.
7. Ne jamais ajouter `password`, token, IBAN, NIR ou donnée médicale à une liste blanche.
8. Utiliser `AuditContext::system()` ou `automated()` pour toute opération sans utilisateur.
9. Ne jamais attribuer un utilisateur authentifié à une opération système.
10. Encapsuler les jobs et traitements longs dans `withContext()`.
11. Vérifier les transactions : une modification annulée ne doit pas laisser d’audit.
12. Ajouter des tests positif/négatif et des tests de non-fuite.

---

## 10. Limites connues

- Aucun événement métier réel n’est instrumenté dans ce lot.
- Les événements de sécurité résistant à un rollback ne sont pas encore traités.
- Le sanitizer ne peut pas deviner qu’une valeur secrète se trouve sous une clé anodine ; la liste blanche et la liste noire restent la protection principale.
- Les descriptions libres fournies par un appelant ne sont pas acceptées : seules les descriptions fixes du catalogue sont utilisées.
- La `reason` est une justification métier courte ; elle ne doit jamais contenir de secret, de token ou de donnée bancaire.
- Le contexte reste en mémoire dans le manager scoped ; les workers de longue durée doivent appeler `resetContext()` ou utiliser `withContext()`.
- `resetContext()` ne doit pas être appelé à l’intérieur d’un `withContext()` : le `finally` restaure volontairement le contexte précédent.
- La politique de conservation, la purge contrôlée, le legal hold, l’ancrage externe et l’interface Filament restent hors périmètre.
- La signature HMAC du Lot 1A ne détecte toujours pas les suppressions de lignes.
- Les permissions `audit.view` / `audit.export` ne sont pas encore créées.

---

## 11. Vérifications et tests

### 11.1 Suite Audit

Commande :

```bash
php artisan test --filter=Audit
```

Résultat : **75 tests, 75 réussis, 201 assertions**.

Couverture :

- catalogue et validation des événements ;
- contextes utilisateur, système et automatisé ;
- refus d’un acteur humain sur une origine système ;
- isolation et restauration du contexte ;
- liste blanche, liste noire, casse et séparateurs ;
- structures imbriquées, tableaux d’objets, JSON et dates ;
- refus des objets non supportés ;
- métadonnées filtrées ;
- écriture d’un événement valide ;
- acteur, origine, corrélation et justification ;
- absence de secrets dans `activity_log` ;
- refus d’un sujet non persisté ;
- refus d’un acteur non persisté ;
- échec fermé du sanitizer ;
- commit et rollback transactionnels.

### 11.2 Suite complète

Commande :

```bash
php artisan test
```

Résultat : **158 tests, 158 réussis, 542 assertions**.

### 11.3 Qualité

| Commande | Résultat |
|---|---|
| `vendor/bin/pint --test` | Réussi |
| `vendor/bin/phpstan analyse --no-progress` | Réussi, 0 erreur |
| `composer validate --no-check-publish --strict` | Réussi |
| `composer audit` | Aucun avis de sécurité ; 2 packages abandonnés préexistants |

### 11.4 Bases de données

Les tests utilisent SQLite `:memory:` via `RefreshDatabase`. Aucune migration n’a été exécutée sur la base locale ou de production. Aucun fichier `.env` n’a été lu.

---

## 12. Éléments reportés au Lot 1C

- instrumentation de `User` ;
- instrumentation de `AdministratorInvitation` ;
- instrumentation de `RbacSynchronizer` ;
- événements d’authentification ;
- commandes et jobs ;
- opérations de masse et SQL directes ;
- tests d’intégration des domaines ;
- revue des appels directs à `activity()`.

Le Lot 1C ne doit pas commencer sans validation explicite du Lot 1B.
