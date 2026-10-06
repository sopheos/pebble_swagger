---
name: pebble-swagger
description: How to correctly document API endpoints and response schemas with oa-* docblock tags and generate the OpenAPI 3 (Swagger) document using the sopheos/pebble_swagger PHP library (namespace Pebble\Swagger — classes Parser, ApiParser, SchemaParser, SchemaParsers, DocCollection, DocEntity, Exception). Use this whenever the project's composer.json requires sopheos/pebble_swagger, code imports from Pebble\Swagger\*, docblocks contain oa-url / oa-method / oa-res / oa-field tags, or you're asked to add or change an API endpoint, a controller action, a request parameter or body, a response or result class, or the Swagger/OpenAPI documentation in a PHP project that has this library available — even if the request is phrased generically like "add a GET /users/{id} endpoint", "document this controller" or "why is my route missing from Swagger" without naming the library. Also check this before writing zircote/swagger-php attributes, a hand-written openapi.yaml, or untagged controller methods in such a project, since this library has its own tag syntax and non-obvious and in places broken behavior (final/abstract/readonly classes are skipped, any "oa-" text in a docblock makes the method an endpoint, oa-query is ignored outside GET/DELETE, path parameters are not required unless listed in oa-required, recursive schemas exhaust memory, regex patterns are cut at ':') that hand-rolled annotations would miss.
---

# pebble-swagger

`sopheos/pebble_swagger` builds an OpenAPI 3.0 document from `oa-*` tags written in plain docblocks. `Parser` scans a controllers directory, turns each tagged **method** into an operation under `paths`, and resolves the response/body schema names it references into tagged **result classes** under `components.schemas`. `run()` returns a PHP array you `json_encode()` and serve to Swagger UI. There are no PHP attributes, no validation of the produced document, and no caching.

Namespace: `Pebble\Swagger\*`. Source lives in `vendor/sopheos/pebble_swagger/src/`. Read it directly when you need an exact method signature; this skill focuses on *the tag syntax* and the behavior that isn't obvious from it.

## Orientation

- `Parser::create($controllersDir)->parser('App/Results')->title(…)->version(…)->servers(…)->run()` is the whole public workflow.
- `ApiParser` scans the directory, reads method docblocks, builds `paths`.
- `SchemaParser` resolves a schema name inside one namespace (`parser()` adds one); `SchemaParsers` tries them in order.
- `DocCollection`/`DocEntity` split a docblock into tags; `DocCollection::parseType()` implements the type syntax.
- Every syntax error throws `Pebble\Swagger\Exception` with `\Class:method` (or `\Class` for schemas) and the faulty tag.

For the full tag table and method cheat sheet, see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md`. Read it before debugging an endpoint that is missing from Swagger UI.

## Tag syntax

A tag is `oa-name value1 value2 …`, values separated by spaces; the last value (a description) may contain spaces. One tag per line. Tags marked "once" only use their first occurrence.

### On a controller method

| Tag | Syntax | Effect |
| --- | ------ | ------ |
| `oa-url` | `oa-url /api/users/{id}` | **Required.** Path of the operation |
| `oa-method` | `oa-method get` | **Required.** `get`, `post`, `put`, `patch`, `delete` or `options`, **lower-case** |
| `oa-tags` | `oa-tags Users Admin` | Swagger tags, one word each (once) |
| `oa-summary` | `oa-summary Get a user` | Summary (once) |
| `oa-scope` | `oa-scope admin` | Prefixes the summary: `[admin] Get a user` (once) |
| `oa-desc` | `oa-desc One line` | Description; repeat the tag for each line |
| `oa-public` | `oa-public` | No authentication (`security: []`) |
| `oa-private` | `oa-private accessToken` | Authentication with that security scheme (once) |
| `oa-path` | `oa-path id integer User id` | Path parameter `name type [description]` |
| `oa-query` | `oa-query page integer Page number` | Query parameter, **only for `get` and `delete`** |
| `oa-required` | `oa-required id email` | Marks `oa-path`/`oa-query` parameters and `oa-data` fields as required; repeatable |
| `oa-json` | `oa-json UserForm Payload` | JSON body `Schema [description]`; not for `get`/`delete` (once) |
| `oa-data` | `oa-data file string:binary The file` | Field of a `multipart/form-data` body; used when there is no `oa-json`, not for `get`/`delete` |
| `oa-code` | `oa-code 404 Not found` | Response without body; repeated codes are joined with `<br>` |
| `oa-res` | `oa-res 200 UserResult The user` | JSON response `code Schema [description]`; `Schema[]` for an array; replaces an `oa-code` of the same code |

### On a result class (schema)

| Tag | Syntax | Effect |
| --- | ------ | ------ |
| `oa-desc` | `oa-desc A user` | Schema description; repeat for each line |
| `oa-field` | `oa-field email string:email Email\|second line` | Property `name type [description]`; `\|` in the description is a line break |
| `oa-ref` | `oa-ref location Sub/LocationResult` | Property holding another schema |
| `oa-refs` | `oa-refs points Sub/PointResult` | Property holding an array of another schema |
| `oa-required` | `oa-required id email` | Required properties; repeatable |

A result class needs at least one `oa-field`, `oa-ref` or `oa-refs`.

### Types

| Syntax | Schema |
| ------ | ------ |
| `integer`, `number`, `string`, `boolean`, `object` | `{type}` |
| `string:date-time`, `string:email`, `integer:int64`, `string:binary` | `{type, format}` |
| `string:/^[a-z]+$/` | `{type, pattern: "/^[a-z]+$/"}` (delimiters kept; no `:` inside) |
| `array` | array of strings |
| `array:integer`, `array:string:email` | `{type: array, items: {type[, format]}}` |

No spaces inside a type. No `enum`, `nullable`, `default` or `example`.

### Schema names

A schema name is the class name **relative to the namespace given to `parser()`**, with `/` for sub-namespaces: with `parser('App/Results')`, `Sub/PointResult` is `App\Results\Sub\PointResult` and becomes the component `Sub_PointResult`. Always use `/`, never `\`.

## Core recipes

### Serve the document

```php
use Pebble\Swagger\Parser;

$doc = Parser::create(__DIR__ . '/../src/Controllers')   // scanned recursively
    ->parser('App/Results')                               // where schema names are looked up
    ->title('My API')
    ->version('1.4.0')
    ->servers('https://api.example.com')
    ->jwtTokens(true, 'accessToken')                      // default; one name => global security
    ->run();

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($doc);
```

Point Swagger UI at that URL. Generate it in dev or at build time: it reflects every controller on each call.

### Annotate an endpoint

```php
namespace App\Controllers;

class User   // plain "class": not final, abstract or readonly
{
    /**
     * oa-url /api/users/{id}
     * oa-method get
     * oa-tags Users
     * oa-summary Get a user
     * oa-desc Returns the user and its location.
     * oa-private accessToken
     * oa-path id integer User id
     * oa-query with array:string Relations to include
     * oa-required id
     * oa-res 200 UserResult
     * oa-res 404 ErrorResult Unknown user
     */
    public function show(string $id) {}

    /**
     * oa-url /api/users/{id}/avatar
     * oa-method post
     * oa-tags Users
     * oa-path id integer
     * oa-data file string:binary Image
     * oa-data alt string
     * oa-required id file
     * oa-code 204 Saved
     * oa-res 400 ErrorResult
     */
    public function avatar(string $id) {}
}
```

Always list every `oa-path` parameter in `oa-required`, and give every `oa-res` a description: OpenAPI requires both, and an `oa-code` with the same status cannot supply the description because `oa-res` replaces it.

### Annotate a result class

```php
namespace App\Results;

/**
 * oa-desc A user
 * oa-field id integer Identifier
 * oa-field email string:email
 * oa-field role string admin|editor|viewer
 * oa-ref location Sub/LocationResult
 * oa-refs points Sub/PointResult
 * oa-required id email
 */
class UserResult {}
```

Refs inside a schema are resolved in the **same** namespace only. Never make a schema reference itself or form a cycle: it recurses until PHP runs out of memory.

## Behavior to keep in mind while writing code

- **Controllers must be declared `class Name`** at the start of a line (bug). `final`, `abstract` and `readonly` classes are silently skipped. Only the first class of each file is read, and it must be loadable (autoload or already included).
- **Any `oa-` in a method docblock makes it an endpoint.** A comment like "no oa- tag here" then throws `oa-url not found`.
- **`oa-method` is lower-case**; `GET` throws.
- **`oa-query` is silently ignored on `post`/`put`/`patch`/`options`**, and `oa-json`/`oa-data` on `get`/`delete`.
- **Path parameters are not required by default.** List them in `oa-required`.
- **`oa-res` replaces an `oa-code` of the same status**, and has no `description` unless you give one.
- **Inherited methods are documented through each concrete child class**; two methods with the same URL and verb overwrite each other silently.
- **A name used in `oa-res`/`oa-json`/`oa-ref` must resolve to a class with at least one property**, or generation throws `… not found` / `properties not found`.
- **Schema names use `/`.** `Sub\PointResult` builds a `$ref` to a missing component (bug).
- **Recursive schemas exhaust memory** (bug).
- **Patterns keep their `/` delimiters and are cut at the first `:`** (bug). A word `0` disappears from tag values (bug).
- **`jwtTokens()` replaces the schemes**; global security is set only for exactly one name.

Read `references/gotchas.md` for the rest before assuming the generated document is valid OpenAPI.
