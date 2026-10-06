# CLAUDE.md — pebble_swagger

Ce fichier guide Claude Code quand il **maintient** cette librairie. Pour l'**utiliser** depuis un projet (et annoter des contrôleurs), voir le skill [`skills/pebble-swagger/`](skills/pebble-swagger/SKILL.md).

## Rôle

`sopheos/pebble_swagger`, namespace `Pebble\Swagger\`, PHP >= 8.1, extension `mbstring`, aucune dépendance runtime. La lib génère un document OpenAPI 3.0 (tableau PHP à passer à `json_encode()`) à partir de tags `oa-*` écrits dans les docblocks :
- des méthodes de contrôleurs, trouvées en scannant un répertoire (`paths`) ;
- des classes « résultat », résolues par namespace (`components.schemas`).

Pas d'annotations PHP 8 (attributs), pas de validation du document produit, pas de cache disque.

## Commandes

```bash
composer install
vendor/bin/phpunit            # toute la suite
vendor/bin/phpunit --filter ApiParserTest
php demo/server.php           # Swagger UI sur http://localhost:8080 (fixtures Controllers/ + Results/)
```

## Carte de `src/`

| Fichier | Rôle |
|---|---|
| `Parser.php` | Façade : `info`, `servers`, schémas de sécurité JWT, liste des `SchemaParser`. `run()` assemble `paths` et `components.schemas` |
| `ApiParser.php` | Scan du répertoire (`scanClasses()`/`getClass()` par regex), lecture des tags de méthode, construction des opérations |
| `SchemaParsers.php` | Liste ordonnée de `SchemaParser` : `get()` renvoie le premier résultat non nul, `cache()` fusionne les caches |
| `SchemaParser.php` | Résolution d'un nom de schéma dans un namespace, lecture des tags de classe, cache par nom de composant |
| `DocCollection.php` | Découpage d'un docblock en `DocEntity` (une par ligne contenant `oa-`), `parseType()` pour la syntaxe `type:format` |
| `DocEntity.php` | Un tag : `$name` et ses valeurs séparées par des espaces |
| `Exception.php` | Exception unique, message = morceaux joints par des espaces |

`demo/` (intouchable) charge `tests/bootstrap.php` et scanne `tests/ressources/Controllers` avec le namespace `App/Results`.

## Tests

- PHPUnit 9.5. `tests/bootstrap.php` inclut récursivement tous les fichiers de `tests/ressources/` (les classes doivent exister avant `class_exists()`).
- Fixtures :
  - `Controllers/`, `Results/` : fixtures d'origine, utilisées aussi par `demo/`. Ne pas y ajouter de cas d'erreur ;
  - `Api/` : un contrôleur qui utilise tous les tags de méthode ;
  - `Kinds/` : classes `final`, `abstract` et héritée (`ChildController.php` fait un `require_once` du parent, l'ordre d'inclusion n'est pas garanti) ;
  - `Invalid/` : contrôleurs en erreur ou à cas particulier, analysés un par un avec `parseClass()` ;
  - `Schemas/` : schémas pour `SchemaParserTest` (types, erreurs, schéma récursif `Node`).
- `ApiParserTest::parseClasses()` lit la propriété privée `json` par une closure liée, pour tester une classe sans scanner un répertoire.
- Le schéma récursif provoque une erreur fatale : son test lance un sous-processus PHP avec `memory_limit=32M`.
- Les classes de test n'ont pas de namespace. Les méthodes s'appellent `testPhraseEnCamelCase`, les assertions passent par `self::assertSame`, et des bannières `// ----` séparent les sections.

## Conventions du code

Respecter le style existant, sans le « moderniser » au passage :
- pas de `declare(strict_types=1)` ;
- méthodes fluides qui renvoient `static`, constructeurs statiques `create()` ;
- `if` d'une ligne sans accolades tolérés ;
- erreurs levées par `Exception::create(...$morceaux)` ou par `ApiParser::error()`, qui préfixe `\Classe:méthode`.

Une modification de comportement doit être répercutée dans `skills/pebble-swagger/` (SKILL.md, `references/api-reference.md`, `references/gotchas.md`) et dans le `README.md`. Un nouveau tag `oa-*` doit être ajouté à la table des tags du skill **et** du README.

## Bugs connus

Ils sont listés dans [`TODO.md`](TODO.md). Chacun est **figé par un test** annoté `// BUG:` qui vérifie le comportement *actuel*, dans la section « Known bugs » du fichier de test de la classe concernée.

Pour corriger un bug :
1. Corriger `src/`.
2. Réécrire le test `// BUG:` pour qu'il vérifie le comportement attendu.
3. Mettre à jour l'entrée « (bug) » de `skills/pebble-swagger/references/gotchas.md` et le SKILL.md.
4. Retirer l'entrée de `TODO.md` (il ne liste que ce qui reste à faire).
