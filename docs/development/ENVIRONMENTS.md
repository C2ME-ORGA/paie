# Environnements — BatiPaie

Ce document décrit les trois environnements du projet et la préparation de la
production. Il complète `README.md`, `docs/development/TESTING.md`,
`docs/development/WORKFLOW.md` et `AGENTS.md`.

## 1. Vue d'ensemble

| Composant | Développement local | Tests automatisés | Production |
|---|---|---|---|
| Système | Windows 10/11 | Windows local et `ubuntu-latest` (CI) | **Ubuntu Server 24.04 LTS** |
| Serveur web | Laravel Herd | Aucun (PHP CLI) | **Nginx via aaPanel 8.x** |
| Runtime PHP | PHP 8.5 (Herd) | PHP 8.4 (CI) | **PHP-FPM 8.4 minimum** |
| Base de données | MySQL 8 | SQLite `:memory:` | **MySQL** |
| Cache / queue / session | `database` / `database` / `file` | `array` / `sync` / `array` | `database` / `database` / `database` |
| Mail | `log` | `array` | SMTP |
| Frontend | Vite (`npm run dev`) | `npm run build` (CI) | Build généré sur le serveur (`npm ci && npm run build`) — `public/build` n'est pas versionné |
| Planification | `composer dev` | Non applicable | **Cron** (`schedule:run`) |
| Processus persistants | `composer dev` | Non applicable | **Supervisor** (`queue:work`) |

Prérequis communs : PHP ≥ 8.4 avec `pdo_mysql`, `pdo_sqlite`, `intl`, `zip`,
`mbstring`, `bcmath`, `fileinfo`, `openssl` ; Composer 2 ; Node.js 24 / npm 11.

> Aucun environnement n'utilise Docker. Aucun déploiement automatique n'est mis en
> place : la mise en production est une opération manuelle et contrôlée.

---

## 2. Développement local (Windows + Laravel Herd)

1. Cloner le dépôt et installer :

   ```bash
   composer setup
   ```

   Cette commande installe les dépendances PHP, copie `.env.example` vers `.env`,
   génère `APP_KEY`, installe les dépendances npm et construit le frontend.

2. Créer la base et l'utilisateur MySQL locaux (voir §4.4 pour les principes de
   privilèges ; en local un compte unique aux droits complets sur la base du projet
   suffit). `DB_HOST=127.0.0.1` implique une connexion **TCP** : MySQL identifie
   alors le client par l'adresse `127.0.0.1`, pas par `localhost` (voir §4.4.1).

   ```sql
   CREATE DATABASE paie_c2me CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'batipaie'@'localhost' IDENTIFIED BY '<mot de passe local>';
   CREATE USER 'batipaie'@'127.0.0.1' IDENTIFIED BY '<mot de passe local>';
   GRANT ALL PRIVILEGES ON paie_c2me.* TO 'batipaie'@'localhost';
   GRANT ALL PRIVILEGES ON paie_c2me.* TO 'batipaie'@'127.0.0.1';
   ```

   Renseigner `DB_USERNAME` / `DB_PASSWORD` dans `.env`. **Ne jamais utiliser
   `root`** pour l'application.

3. Appliquer les migrations :

   ```bash
   composer setup:db
   ```

4. Servir le site :

   ```bash
   herd link      # http://paie.c2me.test
   herd secure    # HTTPS local optionnel
   ```

5. Lancer le serveur de développement complet :

   ```bash
   composer dev   # serveur PHP, Vite, queue et logs
   ```

### Variables locales

Le modèle `.env.example` est orienté développement :

- `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug` ;
- `SESSION_SECURE_COOKIE=false`, `SESSION_SAME_SITE=lax` (HTTP local) ;
- `MAIL_MAILER=log` (les mails sont écrits dans `storage/logs`) ;
- `SESSION_DRIVER=file`.

Ne jamais copier `.env` en production.

---

## 3. Tests automatisés

- Framework : **Pest 5** (PHPUnit 13).
- Configuration : `phpunit.xml` impose `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
  `DB_DATABASE=:memory:`, `CACHE_STORE=array`, `SESSION_DRIVER=array`,
  `QUEUE_CONNECTION=sync` et `MAIL_MAILER=array`.
- **Aucun test ne touche MySQL ni une base réelle** : la base SQLite est créée en
  mémoire et détruite à la fin du processus.
- La CI (`.github/workflows/tests.yml`) rejoue les mêmes réglages sur PHP 8.4.

```bash
vendor/bin/pest
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
composer validate --no-check-publish --strict
```

Les tests `Unit` n'initialisent pas Laravel et n'accèdent ni à `config()`, ni à la
base. Les tests `Feature` peuvent démarrer l'application.

---

## 4. Production — Ubuntu Server 24.04 LTS + aaPanel 8.x

> **Chemins à adapter.** Les chemins indiqués (`/www/wwwroot/...`, utilisateur
> `www`, socket PHP) sont les conventions usuelles d'aaPanel. Ils doivent être
> vérifiés sur l'installation réelle avant application.
>
> **Aucune commande de ce document ne doit être exécutée depuis un poste de
> développement.** Toutes les opérations se font sur le serveur, par un
> administrateur habilité.

### 4.1 Création du site dans aaPanel

1. Panneau aaPanel → **Website** → **Add site**.
2. Renseigner le domaine de production et laisser aaPanel créer le répertoire
   (par défaut `/www/wwwroot/<domaine>`).
3. Choisir **PHP 8.4** (ou la version 8.4+ installée) et **MySQL**.
4. Ne pas activer de « one-click deploy » applicatif : le code est déployé depuis
   le dépôt Git, pas par un template aaPanel.
5. Vérifier que le **document root** pointe sur le sous-répertoire `public/` du
   projet (§4.2) — jamais sur la racine du dépôt.

### 4.2 Vhost Nginx et document root

Le document root doit être `/www/wwwroot/<site>/public`. Le vhost doit :

- servir `index.php` et router toutes les URLs vers Laravel ;
- interdire l'accès aux fichiers cachés (`.env`, `.git`, etc.) ;
- transmettre les scripts PHP à PHP-FPM.

Exemple à adapter (socket FastCGI selon la version PHP d'aaPanel) :

```nginx
server {
    listen 80;
    server_name paie.example.com;

    root /www/wwwroot/paie.c2me/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/tmp/php-cgi-84.sock;  # à adapter
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> Ne pas exposer `/www/wwwroot/paie.c2me` comme racine : `storage/`, `vendor/` et
> `.env` seraient accessibles.

### 4.3 PHP-FPM et extensions

- Version minimale : **PHP 8.4** (`composer.json` exige `^8.4`).
- Extensions obligatoires : `pdo_mysql`, `pdo_sqlite` (outillage), `intl`, `zip`,
  `mbstring`, `bcmath`, `fileinfo`, `openssl`, `curl`.
- **Node.js 24 et npm 11** : requis sur le serveur, car les assets sont construits
  sur place (`npm ci && npm run build`). `public/build` n'étant pas versionné, il
  ne peut pas provenir du dépôt. Alternative : produire le build sur un poste
  d'intégration puis le transférer avec l'application, en conservant la même
  version de Node.
- Vérifier après installation : `php -m` et `composer check-platform-reqs`.
- Limites recommandées : `memory_limit=512M`, `max_execution_time` adapté aux
  imports/exports, fuseau `Europe/Paris` (le fuseau applicatif est déjà fixé dans
  `config/app.php`).

### 4.4 MySQL : comptes et privilèges

Trois niveaux de comptes, **distincts** :

| Compte | Usage | Privilèges |
|---|---|---|
| Administrateur (aaPanel / DBA) | Création de la base, des comptes, sauvegardes | Administration du serveur MySQL |
| **`batipaie`** (runtime) | Application, queue, sessions, cache | `SELECT, INSERT, UPDATE, DELETE` sur `paie_c2me.*` uniquement |
| **`batipaie_deploy`** (déploiement) | `php artisan migrate` | `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES` sur `paie_c2me.*` |

Le compte runtime **ne doit pas** disposer de privilèges DDL (`CREATE`, `ALTER`,
`DROP`) : une faille applicative ne doit pas pouvoir modifier le schéma.

```sql
CREATE DATABASE paie_c2me CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comptes runtime : DML uniquement
CREATE USER 'batipaie'@'localhost' IDENTIFIED BY '<mot de passe runtime>';
CREATE USER 'batipaie'@'127.0.0.1' IDENTIFIED BY '<mot de passe runtime>';
GRANT SELECT, INSERT, UPDATE, DELETE ON paie_c2me.* TO 'batipaie'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON paie_c2me.* TO 'batipaie'@'127.0.0.1';

-- Comptes de déploiement : DDL, utilisés uniquement pour les migrations
CREATE USER 'batipaie_deploy'@'localhost' IDENTIFIED BY '<mot de passe distinct>';
CREATE USER 'batipaie_deploy'@'127.0.0.1' IDENTIFIED BY '<mot de passe distinct>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON paie_c2me.* TO 'batipaie_deploy'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON paie_c2me.* TO 'batipaie_deploy'@'127.0.0.1';

FLUSH PRIVILEGES;
```

### 4.4.1 Connexion TCP, socket Unix et `skip_name_resolve`

Le mode de connexion détermine **l'hôte** avec lequel MySQL identifie le compte :

- `DB_HOST=127.0.0.1` (valeur des modèles `.env`) → connexion **TCP** sur le port
  3306. MySQL voit le client comme `127.0.0.1` et le compte doit exister pour cet
  hôte (`'batipaie'@'127.0.0.1'`).
- `DB_HOST=localhost` → PDO MySQL utilise le **socket Unix** sous Linux (nom de
  socket dépendant de la configuration `pdo_mysql.default_socket`). MySQL identifie
  alors le compte comme `'batipaie'@'localhost'`.
- Avec **`skip_name_resolve=ON`** (réglage fréquent et recommandé), MySQL désactive
  la résolution DNS inverse : une connexion TCP depuis `127.0.0.1` n'est **jamais**
  associée à `localhost`. Seul `'batipaie'@'127.0.0.1'` correspond.
- Vérification (lecture seule) : `SELECT @@skip_name_resolve;`
- Symptôme en cas d'oubli : `SQLSTATE[HY000] [1045] Access denied for user
  'batipaie'@'127.0.0.1'`.

Deux stratégies équivalentes, à choisir explicitement :

1. **Créer les comptes pour les deux hôtes** (`'localhost'` et `'127.0.0.1'`), comme
   ci-dessus : fonctionne quel que soit `skip_name_resolve` et quel que soit le mode
   de connexion.
2. **Aligner `DB_HOST` sur l'hôte du compte** : `DB_HOST=localhost` avec des comptes
   `@'localhost'` (socket), ou `DB_HOST=127.0.0.1` avec des comptes `@'127.0.0.1'`
   (TCP).

### 4.4.2 Migrations avec le compte de déploiement

Le cache de configuration (`bootstrap/cache/config.php`) **fige les valeurs
d'environnement**. S'il est actif, `php artisan migrate` ignore les variables
`DB_USERNAME` / `DB_PASSWORD` exportées dans le shell et réutilise le compte runtime
(sans droits DDL) : la migration échoue. La séquence doit donc être explicite :

```bash
cd /www/wwwroot/paie.c2me

# 1. Supprimer le cache de configuration (obligatoire avant la migration)
php artisan config:clear

# 2. Saisir le mot de passe sans affichage et sans argument de commande.
#    Le nom de compte n'est pas un secret ; le mot de passe n'apparaît ni dans
#    l'historique du shell ni dans la liste des processus (`ps`).
export DB_USERNAME=batipaie_deploy
read -r -s -p "Mot de passe de batipaie_deploy : " DB_PASSWORD
echo
export DB_PASSWORD

# 3. Migrer (les variables exportées priment sur .env tant que la
#    configuration n'est pas mise en cache)
php artisan migrate --force
MIGRATION_STATUS=$?

# 4. Supprimer immédiatement les identifiants de l'environnement du shell
unset DB_USERNAME DB_PASSWORD

# 5. Reconstruire le cache de production avec le compte runtime (.env),
#    uniquement si la migration a réussi
if [ "$MIGRATION_STATUS" -eq 0 ]; then
    php artisan optimize
fi

# 6. Vérifier l'état (lecture seule, avec le compte runtime)
php artisan migrate:status
```

- **Ne jamais** écrire le mot de passe dans la commande
  (`DB_PASSWORD='…' php artisan migrate`) : il serait visible dans l'historique du
  shell et dans `ps`.
- `read -s` n'affiche pas la saisie et ne l'enregistre pas dans l'historique.
- Cette saisie suppose une **session interactive** : elle n'est pas utilisable
  telle quelle depuis un cron ou une CI (prévoir un mécanisme de secrets dédié si
  l'automatisation devient nécessaire).
- `unset` doit être exécuté **avant** `optimize` : la reconstruction du cache ne
  doit jamais se faire avec les identifiants de déploiement.
- Ne jamais écrire le mot de passe de déploiement dans le `.env` runtime, dans un
  fichier versionné, dans un log ou dans une trace (`set -x`).
- **Variante isolée** : créer un fichier `.env.deploy` (copie de `.env` avec les
  identifiants `batipaie_deploy` et **le même `APP_KEY`**), permissions `600`,
  exécuter `php artisan migrate --force --env=deploy`, puis supprimer
  immédiatement `.env.deploy`. Ne jamais versionner ce fichier.
- **Ne jamais** lancer `optimize` ni `config:cache` pendant que les identifiants de
  déploiement sont exportés : le cache figerait le compte de déploiement pour
  l'application.
- Après la migration, contrôler l'état avec le compte runtime :
  `php artisan migrate:status` (lecture seule) puis vérifier que
  `bootstrap/cache/config.php` a bien été reconstruit.
- En cas de doute : `php artisan config:clear` puis `php artisan optimize`.
- Les mots de passe sont générés sur le serveur, stockés dans `.env` (runtime) et
  dans le gestionnaire de secrets de l'administrateur (déploiement). Ils ne sont
  jamais commités.
- `root` n'est jamais utilisé par l'application ni par les migrations.

> `SESSION_DRIVER=database`, `CACHE_STORE=database` et `QUEUE_CONNECTION=database`
> utilisent respectivement les tables `sessions`, `cache`/`cache_locks` et
> `jobs`/`failed_jobs`, créées par les migrations du dépôt
> (`0001_01_01_000000_create_users_table.php` pour `sessions`, les migrations
> `cache` et `jobs` pour les autres). Le driver `database` est donc valide.

### 4.5 Variables d'environnement de production

Le modèle `.env.production.example` liste les variables attendues. Points
obligatoires :

- `APP_ENV=production`, `APP_DEBUG=false` ;
- `APP_KEY` généré sur le serveur (`php artisan key:generate`) ;
- `APP_URL` en `https://` ;
- `DB_*` avec le compte **runtime** `batipaie` ;
- `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, `SESSION_HTTP_ONLY=true` ;
- `LOG_LEVEL=warning` (ou `error`) ;
- `MAIL_MAILER=smtp` avec les paramètres du fournisseur.

Le fichier `.env` de production est créé sur le serveur et n'est jamais versionné.

**Permissions et édition.** Le fichier appartient à l'utilisateur PHP-FPM avec des
droits restrictifs (jamais lisibles par « others ») :

```bash
sudo chown www:www /www/wwwroot/paie.c2me/.env
sudo chmod 640 /www/wwwroot/paie.c2me/.env
```

L'utilisateur `www` n'a généralement pas de shell interactif : l'édition se fait
donc en root, puis les droits sont rétablis immédiatement.

```bash
cd /www/wwwroot/paie.c2me
sudo cp .env .env.bak                            # sauvegarde temporaire
sudo -e .env                                     # édition (sudoedit)
sudo chown www:www .env && sudo chmod 640 .env   # rétablir propriétaire et droits
php artisan config:clear && php artisan optimize # si la configuration était en cache
sudo chown -R www:www storage bootstrap/cache    # si les commandes ont tourné en root
rm .env.bak                                      # ou déplacer hors du site
```

- Le gestionnaire de fichiers d'aaPanel peut aussi éditer `.env` : après
  enregistrement, vérifier et rétablir systématiquement `www:www` / `640`, car
  l'éditeur peut recréer le fichier avec le propriétaire root.
- Ne jamais laisser `.env` en `644` ou plus permissif.
- Toute modification de `.env` impose de rejouer `php artisan config:clear` puis
  `php artisan optimize` lorsque la configuration est mise en cache.
- Ne jamais afficher le contenu de `.env` dans un ticket, un journal, une PR ou une
  capture d'écran.

### 4.6 Permissions des répertoires

Seuls `storage/` et `bootstrap/cache/` doivent être inscriptibles par le compte
PHP-FPM :

```bash
cd /www/wwwroot/paie.c2me
sudo chown -R www:www storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

- Le reste du dépôt reste en lecture seule pour `www`.
- `.env` doit être lisible uniquement par le compte PHP-FPM.

### 4.7 HTTPS et reverse proxy

- Activer un certificat (Let's Encrypt recommandé, géré par aaPanel) et forcer la
  redirection HTTP → HTTPS.
- Conserver `SESSION_SECURE_COOKIE=true`.
- **Si la terminaison TLS est assurée par un proxy en amont** (load balancer,
  CDN), configurer `TrustProxies` dans `bootstrap/app.php` pour que Laravel
  détecte le schéma `https` et l'IP cliente réelle. Cette configuration dépend de
  l'infrastructure réelle et doit être validée avant activation.
- Ne jamais désactiver la vérification CSRF ni les cookies sécurisés pour
  contourner un problème de certificat.

### 4.8 Planification (Cron)

Le scheduler Laravel doit être exécuté chaque minute, **sous l'identité de
l'utilisateur du site** (celui qui exécute PHP-FPM, `www` dans les conventions
aaPanel). Exécuté par root, il créerait des fichiers appartenant à root dans
`storage/logs` et `storage/framework`, et PHP-FPM ne pourrait plus y écrire.

Deux configurations sont possibles. **Le cas A est préférable** lorsqu'aaPanel
permet de sélectionner directement l'utilisateur de la tâche.

#### Cas A — tâche planifiée dans la crontab de `www` (recommandé)

La tâche est enregistrée directement pour l'utilisateur `www` (par exemple
`sudo crontab -u www -e`, ou l'option « Run as user » / l'utilisateur du site dans
aaPanel). Aucun `sudo` n'est nécessaire :

```cron
* * * * * cd /www/wwwroot/paie.c2me && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

#### Cas B — tâche planifiée dans la crontab de root

Si la tâche est enregistrée dans la crontab de root (souvent la configuration par
défaut d'un panneau), la commande doit déléguer explicitement l'exécution à `www` :

```cron
* * * * * cd /www/wwwroot/paie.c2me && sudo -u www /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

- La délégation doit être autorisée et correctement configurée : root peut passer
  à `www` par défaut ; si la tâche est exécutée par un autre compte, ajouter une
  règle dédiée dans `/etc/sudoers.d/`, limitée à cette commande. Vérifier avec
  `sudo -u www whoami` (doit répondre `www`).
- La redirection vers `/dev/null` évite de créer un fichier de log appartenant à
  root ; consulter `storage/logs` pour le résultat.
- Contrôler qu'aucun fichier de `storage/logs` ou `storage/framework` n'appartient
  à root (`ls -l`) et corriger le cas échéant avec
  `sudo chown -R www:www storage bootstrap/cache`.

Dans les deux cas :

- Adapter le chemin du binaire PHP à la version réellement installée par aaPanel ;
  ne pas recopier un chemin d'installation supposé.
- Vérifier l'exécution via `php artisan schedule:list` et les logs applicatifs.
- Aucune tâche planifiée métier n'existe encore : la tâche est préparée pour la
  suite du projet.

### 4.9 Workers de queue (Supervisor) — conditionnel

`QUEUE_CONNECTION=database` est la valeur cible. Dès que des jobs seront
dispatched (exports, imports, notifications), un worker persistant est nécessaire :

```ini
[program:batipaie-queue]
command=/usr/bin/php8.4 /www/wwwroot/paie.c2me/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/www/wwwroot/paie.c2me
autostart=true
autorestart=true
user=www
numprocs=1
redirect_stderr=true
stdout_logfile=/www/wwwroot/paie.c2me/storage/logs/queue.log
stopwaitsecs=3600
```

- Installation et supervision via aaPanel (gestionnaire Supervisor) ou
  `supervisorctl` selon l'installation.
- Après chaque déploiement : `php artisan queue:restart`.
- Ne pas lancer plusieurs workers sans vérifier l'idempotence des jobs.

### 4.10 Sauvegardes et journalisation

- **Base de données** : `mysqldump` quotidien (ou sauvegarde aaPanel), chiffré,
  conservé hors du serveur, avec test de restauration périodique.
- **Fichiers** : sauvegarde de `storage/app/private` (documents RH/paie) et de
  `.env` (dans le gestionnaire de secrets, jamais en clair dans un dépôt).
- **Journalisation** : `LOG_STACK=daily`, `LOG_LEVEL=warning`, rotation configurée
  (`LOG_DAILY_DAYS` si nécessaire), surveillance de `storage/logs`.
- Ne jamais journaliser de données de paie, d'IBAN ou de données médicales.
- Surveiller l'espace disque : les logs et exports peuvent croître rapidement.

### 4.11 Mise à jour applicative et caches

Procédure manuelle, après validation de la version :

```bash
cd /www/wwwroot/paie.c2me

php artisan down                       # maintenance
git fetch --all --tags
git checkout <tag ou commit validé>    # jamais une branche de travail
composer install --no-dev --optimize-autoloader
npm ci && npm run build                # Node.js 24 requis sur le serveur

# 1. Supprimer le cache de configuration avant de changer les identifiants
if ! php artisan config:clear; then
    echo "Échec de config:clear : arrêt du déploiement, maintenance conservée."
else
    # 2. Migrations avec le compte de déploiement (détail : §4.4.2)
    export DB_USERNAME=batipaie_deploy
    read -r -s -p "Mot de passe de batipaie_deploy : " DB_PASSWORD
    echo
    export DB_PASSWORD

    if php artisan migrate --force; then
        # Supprimer immédiatement les identifiants temporaires : les commandes
        # suivantes doivent utiliser le compte runtime défini dans le .env.
        unset DB_USERNAME DB_PASSWORD

        # 3. Synchroniser le référentiel RBAC (rôles/permissions uniquement,
        #    aucun utilisateur ni secret), puis vider le cache Spatie.
        #    Ne sortir de maintenance que si chaque vérification et optimisation
        #    réussit. En cas d'échec, l'application reste en maintenance.
        if php artisan db:seed --class=RbacSeeder --force &&
           php artisan permission:cache-reset &&
           php artisan migrate:status &&
           php artisan optimize &&
           php artisan filament:optimize &&
           php artisan queue:restart; then
            php artisan up
        else
            echo "Échec d'une vérification ou optimisation : maintenance conservée."
        fi
    else
        MIGRATION_STATUS=$?
        unset DB_USERNAME DB_PASSWORD
        echo "Échec de la migration (code $MIGRATION_STATUS) : maintenance conservée."
    fi
fi
```

- Exécuter ces commandes sous l'identité de l'utilisateur du site (voir §4.8) ou
  rétablir ensuite `sudo chown -R www:www storage bootstrap/cache`, afin que
  PHP-FPM conserve l'accès en écriture.
- Toujours sauvegarder la base avant une migration.
- En cas d'erreur : diagnostiquer sans exécuter `php artisan up` tant que la cause
  n'est pas corrigée et les contrôles relancés.
- Ne jamais modifier `.env` pendant que `config:cache` est actif sans rejouer
  `php artisan optimize` ensuite.
- `php artisan queue:restart` est à conserver si des workers sont actifs.
- Aucun déploiement automatique n'est configuré : cette procédure est exécutée
  manuellement par l'administrateur.
- Après la synchronisation RBAC, l'initialisation du premier administrateur est
  une opération manuelle : `php artisan batipaie:admin-invite` (voir
  [`docs/security/ADMIN-BOOTSTRAP.md`](../security/ADMIN-BOOTSTRAP.md)). Ne pas
  l'automatiser dans le déploiement.

---

## 5. Matrice des variables

| Variable | Local | Test | Production | Remarque |
|---|---|---|---|---|
| `APP_ENV` | `local` | `testing` | `production` | Obligatoire |
| `APP_DEBUG` | `true` | `true`* | `false` | *Jamais `true` en production |
| `APP_KEY` | généré | clé de test CI | généré sur le serveur | Secret |
| `APP_URL` | `http://paie.c2me.test` | — | `https://<domaine>` | Obligatoire |
| `APP_LOCALE` | `fr` | `fr` | `fr` | Défaut `fr` |
| `DB_CONNECTION` | `mysql` | `sqlite` | `mysql` | Obligatoire |
| `DB_DATABASE` | `paie_c2me` | `:memory:` | `paie_c2me` | |
| `DB_USERNAME` | compte dédié | — | `batipaie` | Jamais `root` |
| `SESSION_DRIVER` | `file` | `array` | `database` | |
| `SESSION_SECURE_COOKIE` | `false` | — | `true` | HTTPS obligatoire |
| `SESSION_SAME_SITE` | `lax` | — | `lax` | |
| `SESSION_HTTP_ONLY` | `true` | — | `true` | |
| `CACHE_STORE` | `database` | `array` | `database` | |
| `QUEUE_CONNECTION` | `database` | `sync` | `database` | Worker requis en prod |
| `MAIL_MAILER` | `log` | `array` | `smtp` | |
| `ADMIN_INVITATION_EXPIRY_HOURS` | `24` | `24` | `24` | Durée de l'invitation administrateur |
| `LOG_LEVEL` | `debug` | `debug` | `warning` | |

Les variables non listées (Redis, AWS, Memcached, Slack, etc.) sont optionnelles
et ne sont utilisées que si le service correspondant est activé.

*`APP_DEBUG` en test : dépend de la configuration locale ; les tests ne dépendent
jamais de sa valeur.*

---

## 6. Checklist de mise en production

**Avant**

- [ ] Version validée (tag/commit), CI verte, PR fusionnée vers `production`.
- [ ] Sauvegarde de la base et de `.env` réalisée et vérifiée.
- [ ] Migrations relues et testées sur une copie de la base.
- [ ] `.env` de production complété (aucun placeholder restant).
- [ ] Droits MySQL runtime/déploiement appliqués.

**Pendant**

- [ ] `php artisan down`
- [ ] Déploiement du commit validé
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm ci && npm run build` (Node.js 24)
- [ ] `php artisan config:clear`
- [ ] Mot de passe `batipaie_deploy` saisi sans affichage (`read -s`), puis `php artisan migrate --force` (§4.4.2)
- [ ] `unset DB_USERNAME DB_PASSWORD` effectué après la migration
- [ ] `php artisan db:seed --class=RbacSeeder --force` (rôles/permissions uniquement, aucun utilisateur)
- [ ] `php artisan permission:cache-reset`
- [ ] `php artisan optimize` + `php artisan filament:optimize` avec les identifiants runtime du `.env`
- [ ] `php artisan migrate:status` (contrôle lecture seule)
- [ ] `php artisan queue:restart`
- [ ] `php artisan up`

**Après**

- [ ] Page de connexion Filament accessible en HTTPS.
- [ ] Invitation du premier administrateur via `php artisan batipaie:admin-invite` (procédure manuelle, voir `docs/security/ADMIN-BOOTSTRAP.md`).
- [ ] Cookie de session marqué `Secure` (vérification navigateur).
- [ ] `storage/logs` sans erreur critique.
- [ ] Tâche cron exécutée (`schedule:list`, logs).
- [ ] Worker actif si des jobs existent (`queue:monitor`/Supervisor).
- [ ] Sauvegarde post-déploiement planifiée.

---

## 7. Responsabilités et limites

- Le dépôt ne versionne **aucun secret** ni fichier `.env`.
- Les workflows GitHub Actions ne déploient rien : ils contrôlent la qualité et
  publient une release (tag + notes).
- Les réglages aaPanel, Nginx, MySQL, Cron et Supervisor sont documentés ici mais
  configurés manuellement sur le serveur ; ils ne peuvent pas être vérifiés depuis
  le dépôt.
- Toute modification de ces procédures doit être revue avant application en
  production.
