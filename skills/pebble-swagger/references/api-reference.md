# pebble-swagger — API cheat sheet

Quick lookup by intent. This is not exhaustive. Read the source in `vendor/sopheos/pebble_swagger/src/` for exact signatures and for edge cases not covered here. The tag syntax is in `SKILL.md`.

## Parser (`Pebble\Swagger\Parser`)

All setters are fluent (they return `static`).

| Intent | Method |
| ------ | ------ |
| Start from a controllers directory (scanned recursively) | `Parser::create(string $path): static` / `new Parser(string $path)` |
| `info.title` / `info.description` / `info.version` | `title(string)` / `description(string)` / `version(string)` |
| Replace `servers` | `servers(mixed ...$values)` |
| Replace the bearer-JWT security schemes | `jwtTokens(bool $global = true, mixed ...$names)` |
| Add a namespace where schema names are looked up | `parser(string $namespace)` (`App/Results` or `App\Results`) |
| Build the document | `run(): array` |

Defaults: `openapi: 3.0.0`, `info: {title: Title, description: Description, version: 0.0.1}`, `servers: [{url: http://127.0.0.1}]`, and `jwtTokens(true, 'accessToken')` (scheme `accessToken` + global `security`).

`jwtTokens()` creates `{type: http, scheme: bearer, bearerFormat: JWT}` per name and sets the global `security` only when `$global` is true **and** there is exactly one name. `jwtTokens(false)` removes both.

`run()` returns `openapi`, `info`, `servers`, `components` (`securitySchemes`, and `schemas` only if some were used), `security` (if global), `paths`.

## ApiParser (`Pebble\Swagger\ApiParser`)

| Intent | Method |
| ------ | ------ |
| Build | `new ApiParser(string $path, SchemaParsers $parsers)` |
| Scan and return `paths`, `ksort`ed by URL | `run(): array` |
| Parse the methods of one class (including inherited ones) | `parseClass(string $classname)` |
| FQCNs of the loadable classes of every `.php` file under a folder | `ApiParser::scanClasses(string $folder): array` |
| FQCN (`\Ns\Name`) of the first `\nclass Name` in a file, or `null` if not `class_exists()` | `ApiParser::getClass(string $filename): ?string` |

Errors: `Exception("\Class:method <tag> <message>")`, e.g. `\App\Controllers\User:show oa-url not found`, `… oa-method format is not valid : GET`, `… oa-res NopeResult not found`, `… oa-path format is not valid`.

### Operation built from method tags

| Tag | Output |
| --- | ------ |
| `oa-tags A B` | `tags: [A, B]` |
| `oa-scope s` + `oa-summary t` | `summary: "[s] t"` |
| `oa-desc` × n | `description: nl2br(lines joined by "\n")` |
| `oa-public` | `security: []` |
| `oa-private name` | `security: [{name: []}]` |
| `oa-path n type desc` | `parameters[]: {in: path, name, schema, required?: true, description?}` |
| `oa-query n type desc` (get/delete) | `parameters[]: {in: query, …}` |
| `oa-json Schema desc` (not get/delete) | `requestBody: {content: {application/json: {schema: {$ref}}}, description?}` |
| `oa-data n type desc` (not get/delete, no oa-json) | `requestBody.content.multipart/form-data.schema: {type: object, properties, required?}` |
| `oa-code 404 desc` | `responses.404: {description}` |
| `oa-res 200 Schema desc` | `responses.200: {content: {application/json: {schema: {$ref}}}, description?}` |
| `oa-res 200 Schema[] desc` | same with `schema: {type: array, items: {$ref}}` |

`$ref` is `#/components/schemas/` + the name with `/` replaced by `_`. Responses are `ksort`ed.

## SchemaParser (`Pebble\Swagger\SchemaParser`) / SchemaParsers

| Intent | Method |
| ------ | ------ |
| Resolver for one namespace (`/` or `\`) | `new SchemaParser(string $namespace)` |
| Schema of `$namespace\$name` (`/` → `\`), cached; `null` if no class or no `oa-` tag | `get(string $name): ?array` |
| Everything resolved so far, keyed by component name (nulls included) | `cache(): array` |
| Ordered list of resolvers | `SchemaParsers::create()`, `add(SchemaParser): static` |
| First non-null schema | `SchemaParsers::get(string $name): ?array` |
| Merged caches (what `Parser::run()` puts in `components.schemas`) | `SchemaParsers::cache(): array` |

Schema shape: `{path: "\Ns\Class", description?, type: object, properties, required?}`. `oa-field` descriptions turn `|` into `<br />\n`.

Errors: `Exception("\Ns\Class oa-field name not found")`, `… type not found`, `… oa-ref Name not found`, `… properties not found`.

## DocCollection (`Pebble\Swagger\DocCollection`) / DocEntity

| Intent | Method |
| ------ | ------ |
| One `DocEntity` per line containing `oa-` (from `oa-` to end of line) | `DocCollection::create(string $doc): static` |
| Lookup | `has(string $name): bool`, `one(string $name): ?DocEntity`, `all(string $name): DocEntity[]`, `count(): int` |
| Join repeated tags | `parseMultiline(string $name, $sep = "\n"): string` |
| Type syntax → schema | `DocCollection::parseType(string $type, array $schema = []): array` |
| Tag name and values | `DocEntity::$name`, `DocEntity::$value` |
| One value (`''` if missing) | `value(int $pos = 0): string` |
| Slice / joined slice | `values(int $start = 0, ?int $len = null): array`, `text(int $start = 0, ?int $len = null): string` |
