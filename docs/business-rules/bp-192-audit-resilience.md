# BP-192 — Résilience du journal d'audit et politique d'authentification

- **Ticket** : BP-192, lot correctif final avant fusion de la PR #10.
- **Date** : 2026-11-08.
- **Branche** : `feat/BP-192-audit-logging`.
- **Nature** : durcissement de la configuration HMAC et politique d'échec de l'audit sur l'authentification.

---

## 1. Politique de clé HMAC par environnement

**Fait** : `app/Support/Audit/AuditIntegrityConfiguration.php::assertConfigured()` applique une **liste positive** :

| Environnement | Fallback `APP_KEY` | Clé dédiée |
|---|---|---|
| `local` | autorisé | facultative |
| `testing` | autorisé | facultative |
| `staging` | **interdit** | obligatoire |
| `preproduction` | **interdit** | obligatoire |
| `production` | **interdit** | obligatoire |
| `prod`, nom personnalisé | **interdit** | obligatoire |

Contrôles appliqués hors `local` et `testing` :

1. intégrité activée (`filament-activity-log.integrity.enabled`) ;
2. `ACTIVITY_LOG_INTEGRITY_KEY` non vide ;
3. longueur ≥ 32 caractères ;
4. valeur différente de `APP_KEY` (comparaison `hash_equals`).

**Déduction** : aucun environnement ne peut désactiver cette obligation par variable d'environnement. Le fallback reste possible uniquement pour le développement local et les tests automatisés.

**Note** : `staging` et `preproduction` ne sont pas des environnements existants du projet à ce jour. La règle est anticipative : dès qu'un de ces environnements sera créé, il exigera une clé dédiée.

### 1.1 Stabilité et rotation

- la clé doit rester stable entre déploiements ;
- toute rotation rend les signatures antérieures invérifiables par la commande standard ;
- aucune gestion multi-clés ni `key_id` n'existe : conserver l'ancienne clé hors ligne avant rotation ;
- ne jamais stocker la clé dans le dépôt, un journal ou une capture d'écran.

### 1.2 `config:cache`, `config:clear`, `optimize`

**Fait** : `config('filament-activity-log.integrity.key')` provient de `Env::get('ACTIVITY_LOG_INTEGRITY_KEY')`, évalué au moment de la construction du cache de configuration.

| Situation | Conséquence |
|---|---|
| `config:cache` avec clé valide | clé figée dans `bootstrap/cache/config.php` |
| `.env` modifié sans `config:clear` | ancienne clé conservée |
| cache construit sans clé, exécution hors `local`/`testing` | exception au démarrage (fail-closed) |
| `php artisan optimize` en environnement protégé | le contrôle s'exécute pendant l'optimisation et interrompt le déploiement si la clé manque |

**Déduction** : le cache ne peut pas provoquer un downgrade silencieux : soit la clé est présente et utilisée, soit le démarrage échoue. Après toute modification de `.env`, exécuter `config:clear` puis `optimize`.

**Recommandation** : vérifier la clé avant `optimize` dans la procédure de déploiement, afin de transformer un échec de démarrage en échec de déploiement explicite.

---

## 2. Connexion : fail-closed réel

### 2.1 Comportement avant correction

**Fait** : `Illuminate\Auth\SessionGuard::login()` exécute `updateSession()` **avant** `fireLoginEvent()`. Si le listener d'audit échouait :

- la session contenait déjà l'identifiant utilisateur ;
- l'exception remontait ;
- l'utilisateur restait authentifié à la requête suivante.

### 2.2 Comportement après correction

**Fait** : `app/Listeners/AuditAuthenticationEvents.php::login()` capture uniquement `AuditLoggingException`, puis :

1. supprime le cookie « remember me » du guard ;
2. invalide la session et régénère le jeton CSRF ;
3. oublie les guards résolus (`Auth::forgetGuards()`), sans appeler `Auth::logout()` ;
4. invalide le `remember_token` de l'utilisateur côté serveur ;
5. signale l'échec au canal de secours ;
6. lève `AuditLoginUnavailableException` (HTTP 503) avec un message générique.

**Déduction** : aucune session authentifiée ne subsiste après un échec d'audit de connexion. L'utilisateur reçoit une erreur contrôlée, sans donnée technique ni secret.

**Point clé** : `Auth::logout()` n'est pas utilisé, car il déclencherait un événement `Logout` et rappellerait le listener d'audit qui vient d'échouer. La récupération passe par les primitives de session et de guard.

### 2.3 Cookie remember-me et réponse 503

**Fait** : `Cookie::forget()` place la suppression dans le `CookieJar`, mais `AddQueuedCookiesToResponse` ne l'ajoute à la réponse qu'après le retour du pipeline. Une exception 503 interrompt ce traitement.

Deux protections sont donc combinées :

1. **Invalidation serveur** : `remember_token` est mis à `null` et persisté. Tout recaller existant devient inutilisable, indépendamment de la réponse HTTP.
2. **Cookie de suppression** : `bootstrap/app.php` enregistre un callback `respond()` qui, uniquement pour `AuditLoginUnavailableException`, recopie les cookies en file dans la réponse 503.

**Déduction** : le navigateur reçoit le cookie d'expiration, et même s'il ne le recevait pas, le jeton serveur ne permettrait plus la réauthentification.

**Effet secondaire documenté** : l'invalidation du `remember_token` déconnecte aussi les sessions « remember me » des autres appareils de cet utilisateur. Ce comportement est volontaire et conservateur : une panne du journal d'audit ne doit pas laisser un accès persistant.

**Limite** : si la base de données est elle-même indisponible, l'invalidation du jeton échoue. Le recaller ne peut alors pas être validé non plus, puisque `retrieveByToken()` interroge la même base. La session et le guard ont déjà été réinitialisés.

---

## 3. Déconnexion : fail-open contrôlé

**Fait** : `SessionGuard::logout()` vide la session avant de dispatcher l'événement `Logout`.

**Fait** : `AuditAuthenticationEvents::logout()` capture uniquement `AuditLoggingException`, écrit un signal `critical` sur le canal de secours, puis rend la main.

**Déduction** :

- la déconnexion aboutit toujours ;
- aucune réauthentification n'est possible ;
- une exception étrangère à l'audit continue de remonter ;
- aucune récursion n'est possible.

---

## 4. Échec d'authentification : fail-open contrôlé

**Fait** : `AuditAuthenticationEvents::failed()` capture uniquement `AuditLoggingException`, signale l'échec au canal de secours, puis rend la main.

**Déduction** :

- `Auth::attempt()` retourne normalement `false` ;
- le formulaire conserve le refus habituel ;
- aucune erreur HTTP 500 n'est substituée au refus ;
- un compte inconnu reste non journalisé (anti-énumération) ;
- le mot de passe saisi n'est jamais transmis au journal ni au canal de secours.

---

## 5. Canal de secours

### 5.1 Handler et destination

**Fait** : canal `audit_fallback` dans `config/logging.php` :

```text
driver   : daily
path     : storage/logs/audit-fallback.log
level    : critical
rotation : 30 fichiers
```

- indépendant de la base de données d'audit (`activity_log`) ;
- sélectionnable via `AUDIT_FALLBACK_LOG_CHANNEL` (`config/audit.php`, clé `fallback_channel`) ;
- niveau `critical` uniquement.

### 5.2 Données enregistrées

**Fait** (`app/Support/Audit/AuditFallbackLogger.php`) :

| Champ | Contenu |
|---|---|
| message | `Audit journal write failed.` |
| `event` | identifiant technique (`security.logout`, `administrator_invitation.delivery_failed`, …) |
| `correlation_id` | UUID de l'opération, si disponible |
| `exception` | nom de classe de l'exception |

Aucun nom, e-mail, mot de passe, jeton, IBAN, NIR, contenu de document ou charge utile métier.

### 5.3 Limites

- **Best-effort** : si le canal de secours est lui-même indisponible (disque plein, permissions), l'échec est silencieux et l'exception d'origine est conservée.
- Le fichier `storage/logs/audit-fallback.log` doit être supervisé (alerte sur toute ligne `critical`).
- Ce canal ne remplace pas le journal d'audit : il ne contient ni la valeur avant/après ni le sujet complet.
- Une rotation/purge des logs applicatifs peut supprimer ces signaux : la supervision doit les ingérer avant.

---

## 6. Échec de livraison d'invitation : état partiel documenté

**Fait** : `app/Actions/Administration/InviteAdministrator.php` révoque l'invitation (`revoked_at`) **avant** d'écrire `administrator_invitation.delivery_failed`.

**Comportement retenu** :

- la révocation est conservée même si l'audit échoue ;
- l'invitation n'est jamais réactivée automatiquement ;
- la logique d'envoi n'est pas modifiée ;
- l'échec d'audit est signalé au canal de secours puis propagé ;
- la commande retourne un code d'échec.

**Déduction** : l'état final est **sûr** (invitation révoquée, donc inutilisable) mais **partiellement audité** (absence éventuelle de la ligne `delivery_failed`). Cet état est explicitement accepté.

---

## 7. Tests

| Fichier | Couverture |
|---|---|
| `tests/Feature/Audit/AuditIntegrityEnvironmentTest.php` | 10 scénarios d'environnement, clé courte, clé identique, intégrité désactivée, clé valide |
| `tests/Feature/Audit/ActivityIntegrityFallbackTest.php` | fallback réel sur `APP_KEY`, priorité de la clé dédiée, absence totale de clé |
| `tests/Feature/Audit/AuditFailClosedTest.php` | connexion sans session résiduelle, déconnexion effective, refus normal, compte inconnu non journalisé, invalidation du `remember_token` |
| `tests/Feature/Audit/AuditLoginHttpTest.php` | point d'entrée Filament/Livewire : 503, absence de session, cookie d'expiration, recaller préexistant invalide, connexion normale |
| `tests/Feature/Audit/AuditInvitationInstrumentationTest.php` | invitation révoquée malgré l'échec de l'audit de livraison |
| `tests/Feature/Audit/AuditIntegrityConfigurationTest.php` | contrôles de clé adaptés à la politique par environnement |

---

## 8. Synthèse des politiques

| Événement | Politique | Effet d'un échec d'audit |
|---|---|---|
| Création utilisateur, invitation, RBAC | fail-closed transactionnel | opération annulée |
| Connexion réussie | fail-closed avec récupération | session invalidée, HTTP 503 contrôlé |
| Déconnexion | fail-open contrôlé | déconnexion effectuée, signal de secours |
| Échec d'authentification | fail-open contrôlé | refus normal, signal de secours |
| Événements techniques | fail-open | signal de secours |

---

## 9. Références

- `app/Support/Audit/AuditIntegrityConfiguration.php`
- `app/Support/Audit/AuditFallbackLogger.php`
- `app/Listeners/AuditAuthenticationEvents.php`
- `app/Actions/Administration/InviteAdministrator.php`
- `config/audit.php`, `config/logging.php`, `config/filament-activity-log.php`
- `.env.example`, `.env.production.example`
- `docs/development/ENVIRONMENTS.md`
