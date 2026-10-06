# Pebble/Swagger

Génération d'un document OpenAPI 3 (Swagger) à partir de tags `oa-*` placés dans les docblocks des contrôleurs et des classes de résultat, pour PHP 8.1+.

`Parser` scanne un répertoire de contrôleurs, lit les docblocks de leurs méthodes et renvoie un tableau PHP prêt à passer à `json_encode()`. Les schémas référencés sont résolus dans un ou plusieurs namespaces de classes « résultat ».

## Installation

```bash
composer require sopheos/pebble_swagger
```

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/pebble-swagger/`](skills/pebble-swagger/). Il documente la syntaxe des tags `oa-*`, les patterns d'usage et les pièges de la librairie : classes `final`/`abstract`/`readonly` ignorées, `oa-` détecté n'importe où dans un docblock, `oa-query` ignoré hors GET/DELETE, schémas récursifs qui bouclent, motifs coupés au `:`, etc.

Dans un projet qui dépend de `sopheos/pebble_swagger`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement. Le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md` :

```bash
cp -r vendor/sopheos/pebble_swagger/skills/pebble-swagger .claude/skills/pebble-swagger
```

Pour la maintenance de la lib elle-même, voir [`CLAUDE.md`](CLAUDE.md). Les bugs connus sont listés dans [`TODO.md`](TODO.md).

## Exemple

```php
use Pebble\Swagger\Parser;

$doc = Parser::create(__DIR__ . '/src/Controllers')
    ->parser('App/Results')
    ->title('Mon API')
    ->version('1.0.0')
    ->servers('https://api.example.com')
    ->run();

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($doc);
```

```php
namespace App\Controllers;

class User
{
    /**
     * oa-url /api/users/{id}
     * oa-method get
     * oa-tags Users
     * oa-summary Détail d'un utilisateur
     * oa-private accessToken
     * oa-path id integer Identifiant
     * oa-required id
     * oa-res 200 UserResult
     * oa-res 404 ErrorResult
     */
    public function detail() {}
}
```

```php
namespace App\Results;

/**
 * oa-desc Utilisateur
 * oa-field id integer Identifiant
 * oa-field email string:email
 * oa-ref location Sub/LocationResult
 * oa-refs points Sub/PointResult
 * oa-required id email
 */
class UserResult {}
```

Le répertoire `demo/` contient une page Swagger UI (`php demo/server.php`, puis http://localhost:8080) qui affiche le document généré à partir des fixtures de `tests/ressources/`.

## Tags

Les valeurs sont séparées par des espaces. Le dernier argument d'un tag (description) peut contenir des espaces.

### Méthode de contrôleur

* `oa-url /chemin/{param}` Obligatoire.
* `oa-method get|post|put|patch|delete|options` Obligatoire, en minuscules.
* `oa-tags Tag1 Tag2` Tags Swagger (un mot chacun).
* `oa-summary Texte` Résumé. `oa-scope admin` le préfixe par `[admin]`.
* `oa-desc Texte` Description, une ligne par tag.
* `oa-public` Aucune authentification. `oa-private nomDuSchéma` Authentification par ce schéma.
* `oa-path nom type Description` Paramètre de chemin.
* `oa-query nom type Description` Paramètre de requête. **Ignoré** sauf pour `get` et `delete`.
* `oa-required nom1 nom2` Paramètres et champs `oa-data` obligatoires. Les paramètres de chemin doivent y figurer.
* `oa-json Schéma Description` Corps JSON (ignoré pour `get`/`delete`).
* `oa-data nom type Description` Champ d'un corps `multipart/form-data` (si pas d'`oa-json`, ignoré pour `get`/`delete`).
* `oa-code 404 Description` Réponse sans corps. Répété pour un même code, les descriptions sont jointes par `<br>`.
* `oa-res 200 Schéma Description` Réponse JSON. `Schéma[]` pour un tableau. Remplace un `oa-code` du même code.

### Classe de résultat (schéma)

* `oa-desc Texte` Description, une ligne par tag.
* `oa-field nom type Description` Propriété. Un `|` dans la description est un saut de ligne.
* `oa-ref nom Schéma` Propriété objet. `oa-refs nom Schéma` Propriété tableau d'objets.
* `oa-required nom1 nom2` Propriétés obligatoires.

### Types

* `integer`, `number`, `string`, `boolean`… : type simple.
* `type:format` : `string:date-time`, `string:email`, `integer:int64`, `string:binary`…
* `type:/regex/` : motif (sans `:` dans la regex, voir [`TODO.md`](TODO.md)).
* `array` (tableau de chaînes), `array:integer`, `array:string:email`.

Un nom de schéma est relatif au namespace passé à `parser()`, avec `/` comme séparateur : `Sub/PointResult` désigne `App\Results\Sub\PointResult` et devient le composant `Sub_PointResult`.

## Parser

`\Pebble\Swagger\Parser` assemble le document.

* `create(string $path) : static` / `__construct(string $path)` Répertoire des contrôleurs, scanné récursivement.
* `title(string $title) : static` Défaut `Title`.
* `description(string $value) : static` Défaut `Description`.
* `version(string $value) : static` Défaut `0.0.1`.
* `servers(mixed ...$values) : static` Remplace la liste des serveurs (défaut `http://127.0.0.1`).
* `jwtTokens(bool $global = true, mixed ...$names) : static` Remplace les schémas de sécurité « bearer JWT ». La sécurité globale n'est posée que si `$global` est vrai et qu'il y a **un seul** nom. Défaut : `jwtTokens(true, 'accessToken')`.
* `parser(string $namespace) : static` Ajoute un namespace de schémas (`App/Results` ou `App\Results`). Les namespaces sont essayés dans l'ordre d'ajout.
* `run() : array` Scanne, construit `paths` et `components.schemas` (uniquement les schémas utilisés).

## ApiParser

`\Pebble\Swagger\ApiParser` construit `paths`. Utilisé par `Parser`.

* `__construct(string $path, SchemaParsers $parsers)`
* `run() : array` Chemins triés par URL.
* `parseClass(string $classname)` Analyse les méthodes (y compris héritées) d'une classe.
* `scanClasses(string $folder) : array` (statique) Classes trouvées dans les fichiers `.php` du répertoire.
* `getClass(string $filename) : ?string` (statique) Première classe déclarée par `\nclass Nom` dans le fichier, si elle existe (autoload).

Une erreur de syntaxe lève `\Pebble\Swagger\Exception` avec la classe et la méthode en cause, par exemple `\App\Controllers\User:detail oa-url not found`.

## SchemaParser / SchemaParsers

* `new SchemaParser(string $namespace)` Résout les schémas d'un namespace.
  * `get(string $name) : ?array` Schéma de la classe `$namespace\$name`, `null` si elle n'existe pas ou n'a pas de tag `oa-*`. Mis en cache.
  * `cache() : array` Schémas résolus, indexés par nom de composant.
* `SchemaParsers::create()` Collection de `SchemaParser`.
  * `add(SchemaParser $parser) : static`
  * `get(string $name) : ?array` Premier résultat non nul.
  * `cache() : array` Fusion des caches.

## DocCollection / DocEntity

Lecture bas niveau d'un docblock.

* `DocCollection::create(string $doc) : static` Une `DocEntity` par ligne contenant `oa-`.
* `has(string $name) : bool`, `one(string $name) : ?DocEntity`, `all(string $name) : array`, `count() : int`.
* `parseMultiline(string $name, $sep = "\n") : string` Joint les valeurs des tags répétés.
* `DocCollection::parseType(string $type, array $schema = []) : array` Convertit la syntaxe de type ci-dessus en schéma OpenAPI.
* `DocEntity` : `$name`, `$value`, `value(int $pos = 0) : string`, `values(int $start = 0, ?int $len = null) : array`, `text(int $start = 0, ?int $len = null) : string`.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Les fixtures sont dans `tests/ressources/` (chargées par `tests/bootstrap.php`). `Controllers/` et `Results/` servent aussi à la démo. Les bugs connus sont figés par des tests annotés `// BUG:` qui vérifient le comportement actuel.
