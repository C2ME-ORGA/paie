# BatiPaie

Application interne C2ME de gestion des ressources humaines et de préparation/calcul
de la paie pour le secteur du BTP.

- Dépôt : `C2ME-ORGA/paie`
- Stack : PHP 8.4+, Laravel 13, Livewire 4, Filament 5, MySQL, Vite, Pest
- Version cible 1.0.0 : 30 novembre 2026

## Prérequis

- PHP **8.4 minimum** avec les extensions `pdo_mysql`, `pdo_sqlite`, `intl`, `zip`,
  `mbstring`, `bcmath`, `fileinfo`, `openssl`
- Composer 2
- Node.js 24 / npm 11
- MySQL 8 (ou MariaDB compatible, `utf8mb4`)
- Laravel Herd (développement sous Windows)

## Installation locale

```bash
composer setup        # dépendances PHP, .env, APP_KEY, dépendances npm et build
```

`composer setup` copie `.env.example` vers `.env` si nécessaire. Créer ensuite la base
et l'utilisateur MySQL applicatif décrits dans
[`docs/development/ENVIRONMENTS.md`](docs/development/ENVIRONMENTS.md), puis lancer :

```bash
composer setup:db     # migrations de la base locale
herd link             # sert le projet sur http://paie.c2me.test
herd secure           # optionnel : HTTPS local
```

## Commandes

| Objectif | Commande |
|---|---|
| Serveur de développement complet | `composer dev` |
| Frontend seul | `npm run dev` / `npm run build` |
| Tests | `vendor/bin/pest` ou `composer test` |
| Formatage (vérification) | `vendor/bin/pint --test` |
| Analyse statique | `vendor/bin/phpstan analyse --no-progress` |

## Configuration

- `.env.example` : modèle de **développement local** (aucun secret).
- `.env.production.example` : modèle de **production** (placeholders uniquement).
- `.env` : jamais versionné, jamais transmis par un canal non sécurisé.

## Documentation

- [`docs/development/ENVIRONMENTS.md`](docs/development/ENVIRONMENTS.md) — environnements
  local, test et production (Ubuntu, aaPanel, Nginx, MySQL, Cron, Supervisor).
- [`docs/development/TESTING.md`](docs/development/TESTING.md) — conventions et commandes de test.
- [`docs/development/WORKFLOW.md`](docs/development/WORKFLOW.md) — branches, PR, releases.
- [`AGENTS.md`](AGENTS.md) — règles de travail du dépôt.

## Sécurité

Aucune donnée réelle de salarié, aucun identifiant, aucun secret ne doit être commité.
Les documents RH et de paie sont stockés en privé et chaque accès doit être contrôlé.

---

Application propriétaire C2ME — tous droits réservés.
