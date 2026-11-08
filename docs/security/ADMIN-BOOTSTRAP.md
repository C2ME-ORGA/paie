# Initialisation du premier administrateur

Ce document décrit le bootstrap du premier compte administrateur de BatiPaie
(BP-191, Lot 1B). Il complète [`RBAC.md`](RBAC.md).

## 1. Principe

Le premier administrateur n'est jamais créé directement par une commande avec un
mot de passe fourni par l'opérateur. La commande crée une **invitation à usage
unique** ; le compte n'est créé qu'après acceptation du lien reçu par e-mail et
définition du mot de passe par le destinataire.

Le compte reçoit uniquement le rôle technique `administrator` et la permission
active `administration.panel.access`. Aucun rôle métier ni permission métier ne
lui est attribué automatiquement.

## 2. Prérequis

- Le référentiel RBAC doit être synchronisé : le rôle `administrator` et la
  permission `administration.panel.access` doivent exister.
- Aucun utilisateur ne doit déjà posséder le rôle `administrator`.
- `APP_URL` doit être correct : le lien d'invitation est généré à partir de cette
  valeur.
- Le mail doit être configuré (`MAIL_MAILER`, `MAIL_FROM_ADDRESS`, SMTP).
- La commande s'exécute depuis la console, sous l'identité applicative du site.
- La durée de validité est configurable par `ADMIN_INVITATION_EXPIRY_HOURS`
  (24 heures par défaut).

Après une modification de `.env`, rejouer :

```bash
php artisan config:clear
php artisan optimize
```

## 3. Commande de bootstrap

```bash
php artisan batipaie:admin-invite
```

La commande demande le nom et l'adresse e-mail. Les options sont également
disponibles pour une exécution non interactive :

```bash
php artisan batipaie:admin-invite \
    --name="Prénom Nom" \
    --email="administrateur@exemple.fr"
```

La commande n'accepte **jamais** de mot de passe ni de jeton en argument. Elle ne
crée aucun utilisateur.

### Contrôles effectués

- exécution uniquement depuis la console ;
- validité du nom et de l'adresse e-mail ;
- présence du rôle RBAC `administrator` ;
- absence de tout utilisateur ayant déjà le rôle `administrator` ;
- absence d'un compte existant avec la même adresse e-mail ;
- absence d'une invitation valide déjà en attente ;
- remplacement d'une invitation expirée après invalidation ;
- refus si l'état des invitations ne permet pas de garantir l'unicité.

La commande verrouille la ligne du rôle `administrator` pendant la transaction.
Deux exécutions concurrentes sont ainsi sérialisées : une seule invitation peut
être créée.

### Sortie

La commande affiche :

- la confirmation de création ;
- l'adresse e-mail masquée ;
- la date d'expiration ;
- le rappel qu'aucun utilisateur n'a été créé.

Elle n'affiche jamais le jeton, le lien complet, le mot de passe ou un secret.

## 4. Invitation et e-mail

L'e-mail contient :

- une présentation sobre ;
- le nom de l'application ;
- le lien d'acceptation ;
- la durée de validité ;
- une indication à ignorer le message si la demande n'est pas sollicitée.

Le jeton brut n'est **pas stocké en base** : seule son empreinte SHA-256 est
conservée dans `administrator_invitations.token_hash`.

La notification n'est pas mise en file d'attente. Cela évite de sérialiser le
jeton brut dans la table `jobs`. Elle est envoyée après le commit de la
transaction ; si l'envoi échoue, l'invitation est révoquée pour permettre une
nouvelle tentative propre. Le test d'échec de transport vérifie la révocation,
l'absence d'utilisateur créé et la possibilité de relancer la commande.

Aucun e-mail réel n'est envoyé pendant les tests (`Notification::fake()` et
`MAIL_MAILER=array`).

## 5. Verrouillage global et concurrence

La commande et l'acceptation utilisent le même ordre de verrous transactionnels :

1. ligne du rôle `administrator` (`guard_name = web`) avec `lockForUpdate()` ;
2. ligne de l'invitation avec `lockForUpdate()`.

Ce verrou sur le rôle est le mutex global du bootstrap. Le code est conçu pour
sérialiser toutes les opérations d'initialisation, y compris deux invitations
distinctes acceptées en parallèle : la seconde transaction doit attendre le
verrou, puis relire l'absence d'administrateur avec une lecture verrouillante et
refuser l'opération. Cette relecture verrouillante évite de dépendre d'un
snapshot de transaction potentiellement antérieur au commit concurrent.

Pour la même invitation, le verrou de la ligne d'invitation et `consumed_at`
empêchent une double consommation. La création du compte, l'attribution du rôle
et la consommation de l'invitation restent dans une seule transaction.

La validation MySQL/InnoDB a été exécutée sur une base dédiée aux tests. Les
tests SQLite restent utiles pour l'état final et les refus, mais ne démontrent
pas le comportement réel de `SELECT ... FOR UPDATE`.

Le jeton doit correspondre à `[a-f0-9]{64}` dans les routes : le format est
exactement celui produit par `bin2hex(random_bytes(32))`.

### Validation MySQL/InnoDB exécutée

Environnement observé :

- serveur MySQL **8.4.11** ;
- moteur par défaut **InnoDB** ;
- isolation **REPEATABLE-READ** ;
- base dédiée : `batipaie_bp191_concurrency_test` ;
- deux processus PHP indépendants, synchronisés par un protocole
  `READY`/`GO` sur leurs entrées standard.

Le harnais est :

```text
tests/MySQL/run-administrator-bootstrap-concurrency.php
tests/MySQL/accept-administrator-invitation-worker.php
```

Il applique `migrate:fresh` et le seeder RBAC **uniquement** sur la base dont le
nom est vérifié par une garde stricte. Il refuse tout autre nom.

Résultats réellement observés :

- **5 itérations « deux invitations distinctes »** : 5/5 réussies ;
- **5 itérations « même invitation »** : 5/5 réussies ;
- pour chaque itération : 1 succès, 1 refus contrôlé, 1 administrateur, 1 compte,
  1 invitation consommée, aucune transaction ouverte ;
- une seconde exécution de **2 + 2 itérations** a été utilisée pour relever les
  compteurs InnoDB : `Innodb_row_lock_waits` est passé de **34 à 38** et
  `Innodb_row_lock_time` de **26 448 à 29 712 ms**, avec
  `Innodb_row_lock_current_waits = 0` après exécution ;
- aucune exception SQL non gérée n'a été remontée ;
- aucune transaction n'est restée ouverte (`information_schema.innodb_trx`
  contrôlé après chaque itération).

Les compteurs InnoDB d'attente de verrou corroborent la sérialisation observée.
Aucune trace SQL détaillée (general log) n'a été activée, afin de ne pas
journaliser de données applicatives.

### Scénarios complémentaires de consolidation

Lors de la phase de consolidation, des scénarios MySQL supplémentaires ont été
exécutés avec des processus PHP indépendants :

- deux commandes `batipaie:admin-invite` simultanées : 3/3 itérations avec un
  seul code de succès, une seule invitation, aucun utilisateur et aucun
  administrateur ;
- acceptation d'une invitation pendant qu'une commande de bootstrap est lancée :
  3/3 itérations avec un seul administrateur, un seul compte, une seule
  invitation consommée et aucune transaction ouverte ;
- acceptation d'une invitation expirée : 1/1 avec refus métier contrôlé, aucun
  compte et aucune consommation.

Ces scénarios complètent la validation du mutex global ; ils ne remplacent pas
les tests SQLite de non-régression.

Les refus sont des exceptions métier contrôlées
(`AdministratorInvitationException`) ; le scénario distinct exige
`administrator_exists`, le scénario même jeton accepte `invalid_invitation` ou
`administrator_exists`.

### Limites des tests SQLite

SQLite sérialise les écritures et ne démontre pas le comportement réel de
`SELECT ... FOR UPDATE` sur InnoDB. Les tests SQLite du projet vérifient :

- le refus d'une seconde acceptation du même jeton ;
- le refus d'une seconde invitation distincte après création du premier
  administrateur ;
- l'unicité du compte administrateur dans l'état final.

Ils ne constituent pas une preuve de concurrence MySQL. La preuve MySQL est
fournie par le harnais multi-processus décrit ci-dessus.

### Commandes de vérification MySQL

Créer une base dédiée (une seule fois, avec un compte autorisé) :

```sql
CREATE DATABASE batipaie_bp191_concurrency_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Sous PowerShell, sans modifier `.env` :

```powershell
$env:BP191_MYSQL_TEST_DATABASE = 'batipaie_bp191_concurrency_test'
$env:BP191_MYSQL_ITERATIONS = '5'
php tests/MySQL/run-administrator-bootstrap-concurrency.php
```

Sous Linux/macOS :

```bash
BP191_MYSQL_TEST_DATABASE=batipaie_bp191_concurrency_test \
BP191_MYSQL_ITERATIONS=5 \
php tests/MySQL/run-administrator-bootstrap-concurrency.php
```

Le harnais utilise les identifiants applicatifs existants, mais force
`DB_DATABASE` sur la base de test. Il ne lit ni ne modifie `.env`, et il refuse
toute autre base. Il est destructif uniquement sur cette base dédiée.

Cette validation n'est pas exécutée par la CI SQLite actuelle ; elle doit être
lancée manuellement sur un environnement MySQL/InnoDB dédié.

## 6. Acceptation

Le destinataire ouvre le lien, vérifie les informations affichées et définit son
mot de passe.

- Le `GET` affiche le formulaire sans consommer le jeton.
- Le `POST` valide le mot de passe, crée le compte, attribue le rôle
  `administrator` et marque l'invitation comme consommée dans une transaction.
- Le jeton est verrouillé pendant l'acceptation ; une double soumission ne peut
  pas créer deux comptes.
- Le compte est marqué comme e-mail vérifié.
- Aucune connexion automatique n'est effectuée : l'utilisateur est redirigé vers
  la page de connexion Filament.

### Politique de mot de passe

- 12 caractères minimum ;
- majuscules et minuscules ;
- lettres, chiffres et symboles ;
- confirmation obligatoire ;
- contrôle `uncompromised()` en production ;
- aucun mot de passe en clair n'est conservé : le cast Laravel `hashed` du modèle
  `User` applique le hash.

### Protection du parcours

- CSRF actif via le groupe `web` et `@csrf` ;
- limitation de débit : 10 requêtes/minute pour l'affichage et 5/minute pour la
  soumission, avec des préfixes distincts ;
- en-têtes `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`,
  `X-Frame-Options: DENY`, `Cache-Control: no-store` et CSP restrictive ;
- aucun script, police, image ou ressource tierce sur la page ;
- réponse générique 404 pour un jeton invalide, expiré, déjà consommé ou révoqué ;
- message générique si l'adresse e-mail appartient déjà à un compte.

## 7. Cas d'erreur et traitement

| Situation | Comportement | Traitement |
|---|---|---|
| Un administrateur existe déjà | Commande et acceptation refusées | Le bootstrap est terminé ; utiliser la gestion des comptes (lot ultérieur). |
| Une invitation valide est en attente | Commande refusée | Attendre l'acceptation ou l'expiration. |
| Invitation expirée | Ancienne invitation révoquée, nouvelle créée | Relancer la commande. |
| Adresse déjà utilisée | Commande refusée ; acceptation refusée | Ne jamais écraser le compte. Attendre l'expiration puis relancer avec une autre adresse. |
| Rôle RBAC absent | Commande/acceptation en échec fail-closed | Exécuter le seeder RBAC et vérifier la matrice. |
| Envoi d'e-mail en échec | Invitation révoquée, commande en échec | Vérifier la configuration SMTP puis relancer. |
| Jeton invalide, expiré ou consommé | Réponse 404 générique | Demander une nouvelle invitation à l'administrateur technique. |
| Trop de tentatives | Réponse 429 | Attendre la fin de la fenêtre de limitation. |
| État de consommation incohérent | Commande refusée | Intervention technique ; ne pas créer un second bootstrap. |

## 8. Adresse e-mail déjà utilisée

Le mécanisme ne récupère jamais un compte existant et ne réinitialise jamais son
mot de passe. Il n'attribue jamais le rôle administrateur à un compte existant.

Procédure sécurisée :

1. ne pas modifier le compte existant ;
2. ne pas réutiliser l'invitation ;
3. attendre l'expiration de l'invitation en cours ;
4. relancer la commande avec une autre adresse e-mail ;
5. si l'urgence l'exige, faire traiter le cas par l'administrateur technique
   selon la procédure d'exploitation, sans contourner l'authentification.

L'invitation restée valide ne peut pas aboutir : l'acceptation vérifie l'unicité
de l'adresse dans la transaction.

## 9. Précautions de production

- Exécuter la commande en SSH, sous l'utilisateur du site, jamais depuis une page
  web.
- Vérifier `APP_URL` en HTTPS avant l'envoi : le lien doit pointer vers le domaine
  de production.
- Utiliser un vrai transport SMTP ; ne jamais utiliser `MAIL_MAILER=log` ou
  `MAIL_MAILER=array` en production. La commande refuse ces mailers lorsque
  `APP_ENV=production`.
- Ne jamais copier le lien ou le jeton dans un ticket, un chat, un journal ou une
  capture d'écran.
- Les journaux d'accès HTTP peuvent contenir l'URL complète, donc le jeton.
  Restreindre leur accès, leur conservation et, si possible, masquer le segment
  `/admin/invitation/` au niveau Nginx. Les journaux ne doivent pas être exposés
  dans un outil d'analytique ou un stockage non maîtrisé.
- En local, `MAIL_MAILER=log` écrit le lien dans `storage/logs/laravel.log` :
  considérer ce fichier comme sensible, ne pas le partager et le purger après
  usage.
- Activer HTTPS, `SESSION_SECURE_COOKIE=true` et `APP_DEBUG=false`.
- Derrière un reverse proxy, configurer `TrustProxies` pour que la limitation
  par IP utilise l’adresse réelle du client (voir
  `docs/development/ENVIRONMENTS.md`).
- Ne pas automatiser la commande dans le déploiement : elle est exécutée
  manuellement après la synchronisation RBAC.
- Après une modification RBAC, relancer `php artisan permission:cache-reset` et
  les workers de queue si nécessaire. Le bootstrap lui-même n'utilise pas la
  queue pour le jeton.

## 10. Commandes de vérification

```bash
php artisan migrate:status
php artisan route:list --path=admin/invitation
php artisan db:seed --class=RbacSeeder --force
php artisan batipaie:admin-invite --name="Prénom Nom" --email="administrateur@exemple.fr"
```

Vérifications attendues :

- la table `administrator_invitations` existe ;
- aucune colonne ne contient le jeton brut ;
- aucune invitation consommée ne peut être réutilisée ;
- le compte créé possède uniquement le rôle `administrator` ;
- l'accès au panel fonctionne après connexion ;
- aucun compte n'existe avant l'acceptation de l'invitation.
