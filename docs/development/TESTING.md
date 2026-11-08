# Tests automatisés — batiPaie

Ce document définit l'organisation, les conventions et les commandes des tests automatisés du projet (BP-23). Il complète `AGENTS.md`, `docs/development/WORKFLOW.md` et la skill `testing-quality-gates`.

## 1. Principes

- Pest 5 s'appuie sur PHPUnit 13 ; les suites `Unit` et `Feature` sont déclarées dans `phpunit.xml`.
- Un test décrit un comportement attendu et un critère d'acceptation, pas un détail d'implémentation.
- Aucun test ne doit être désactivé, ignoré ou affaibli pour obtenir un résultat vert.
- Aucune donnée réelle (salarié, paie, IBAN, donnée médicale) ne doit servir de jeu de test.
- Aucune règle de paie ne peut être testée sans source officielle, date d'effet et périmètre validés.

## 2. Organisation des suites

| Suite | Répertoire | Laravel | Base de données | Usage |
|---|---|---|---|---|
| `Unit` | `tests/Unit` | Non | Interdite | Logique pure, value objects, modèles sans accès BDD |
| `Feature` | `tests/Feature` | Oui (`Tests\TestCase`) | SQLite `:memory:` uniquement | HTTP, console, policies, composants Livewire/Filament, intégrations |

```
tests/
├── Pest.php          # rattachement du TestCase Laravel à la suite Feature
├── TestCase.php      # classe de base Laravel
├── Unit/             # aucun bootstrap Laravel
└── Feature/          # application initialisée
```

Rattachement actuel (`tests/Pest.php`) :

```php
pest()->extend(TestCase::class)->in('Feature');
```

Conséquences :

- les tests `Unit` restent liés à `PHPUnit\Framework\TestCase` et **n'initialisent pas Laravel** ;
- les tests `Feature` disposent de toute l'application (conteneur, facades, HTTP) ;
- `RefreshDatabase` n'est **pas** appliqué globalement : chaque test qui touche la base l'active explicitement.

## 3. Conventions de nommage

- Fichier : `<Sujet>Test.php`, un sujet par fichier, dans la suite correspondante.
  - Exemples : `tests/Unit/UserInitialsTest.php`, `tests/Feature/HomePageTest.php`.
- Ne pas conserver de fichiers `ExampleTest.php` : le nom doit décrire le sujet testé.
- Description du test en anglais, formulée de manière affirmative et décrivant clairement le comportement attendu :
  `test('derives the user initials from the full name', ...)`.
- Un scénario par test ; utiliser les datasets Pest pour les cas limites et les jeux de valeurs.
- Écrire les tests avec les fonctions Pest (`test()`, `expect()`, `dataset()`), pas avec des classes PHPUnit, sauf besoin technique explicite.
- Les noms de classes, méthodes et variables restent en anglais ; les libellés métier affichés peuvent être en français.

## 4. Tests unitaires (sans Laravel)

Un test `Unit` ne doit pas dépendre de l'application :

- pas de `app()`, de facade, de `config()`, de `DB`, de `Http`, de session ni de file d'attente ;
- pas de `Tests\TestCase`, pas de `RefreshDatabase` ;
- l'instanciation d'un modèle Eloquent est tolérée uniquement pour de la logique pure, sans connexion à la base
  (ex. `new User(['name' => '...'])`).

Si un test a besoin du conteneur, d'une route ou de la base, il appartient à la suite `Feature`.

## 5. Tests Feature et base de données

`phpunit.xml` impose l'environnement de test :

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Règles :

- ne jamais utiliser MySQL ni la base locale pour les tests ;
- activer `RefreshDatabase` **dans le test concerné** (ou un `pest()->use(...)` local à un sous-dossier), jamais globalement ;
- créer les données via les factories (`database/factories`) et des états explicites ;
- tester les permissions en positif et en négatif pour tout écran ou endpoint sensible ;
- pour les composants Livewire/Filament, tester le comportement visible et les autorisations, pas le rendu interne ;
- les tests RBAC (rôles, permissions, accès panel) suivent [`docs/security/RBAC.md`](../security/RBAC.md) et couvrent le positif, le négatif et l'absence de bypass ;
- les tests du bootstrap administrateur couvrent la commande, l'invitation, l'acceptation, la notification et la limitation de débit (voir [`docs/security/ADMIN-BOOTSTRAP.md`](../security/ADMIN-BOOTSTRAP.md)).

## 6. Règles métier et paie

- Chaque test lié à une règle sociale ou fiscale documente en commentaire la source, la date d'effet et le périmètre.
- Couvrir : cas nominal, limites, arrondis, dates d'effet, non-régression (un bug corrigé donne un test dédié).
- Ne jamais inventer un taux, un plafond, un minimum conventionnel ou un code DSN.
- En cas d'incertitude réglementaire, ne pas écrire de test : signaler le point bloquant pour validation métier.

## 7. Commandes locales

| Objectif | Commande |
|---|---|
| Toute la suite | `vendor/bin/pest` |
| Toute la suite via Artisan | `php artisan test` |
| Suite Unit uniquement | `vendor/bin/pest --testsuite=Unit` |
| Suite Feature uniquement | `vendor/bin/pest --testsuite=Feature` |
| Un répertoire | `vendor/bin/pest tests/Unit` |
| Un fichier | `vendor/bin/pest tests/Feature/HomePageTest.php` |
| Filtrer par nom | `vendor/bin/pest --filter=UserInitials` |
| S'arrêter au premier échec | `vendor/bin/pest --stop-on-failure` |
| Exécution parallèle | `vendor/bin/pest --parallel` |
| Pint — vérification | `vendor/bin/pint --test` |
| Pint — correction | `vendor/bin/pint` |
| PHPStan / Larastan | `vendor/bin/phpstan analyse --no-progress` |
| Gate complète locale | `composer test` |

Remarques :

- Aucun pilote de couverture (Xdebug/PCOV) n'est installé : `--coverage` n'est pas disponible localement et la CI ne mesure pas encore la couverture.
- `--testdox` déclenche actuellement un avertissement PHPUnit avec Pest 5.0.0 / PHPUnit 13.2.4 (`--no-output` injecté par Pest). Utiliser la sortie standard.
- Les tests utilisent SQLite en mémoire : aucune préparation de base n'est nécessaire, aucune migration ne doit être lancée sur une base réelle.

## 8. Gate avant Pull Request

1. `vendor/bin/pest`
2. `vendor/bin/pint --test`
3. `vendor/bin/phpstan analyse --no-progress`
4. CI verte (workflow géré par BP-24)
5. Revue du diff : aucun secret, aucune donnée réelle, aucune migration inutile

## 9. Ajouter un test — liste de contrôle

- [ ] Le fichier est nommé `<Sujet>Test.php` et placé dans la bonne suite.
- [ ] Un test `Unit` n'initialise pas Laravel et n'accède pas à la base.
- [ ] Un test `Feature` qui touche la base utilise SQLite `:memory:` et `RefreshDatabase` explicitement.
- [ ] Les données viennent de factories ou de datasets, jamais de données réelles.
- [ ] Le critère d'acceptation Jira est couvert, ainsi que les cas limites et les permissions.
- [ ] Les règles de paie citent leur source et leur date d'effet.
- [ ] `vendor/bin/pest`, `vendor/bin/pint --test` et `vendor/bin/phpstan analyse --no-progress` passent.
