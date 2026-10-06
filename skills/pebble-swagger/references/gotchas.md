# pebble-swagger — gotchas

Things the tag names don't tell you, grouped by area. Every item below is pinned by a test in `tests/`. Items marked **(bug)** are listed in the package's `TODO.md` and may be fixed in a later version. Check the test of the same name in `vendor/sopheos/pebble_swagger/tests/` to see the current behavior.

## Class discovery

- **(bug) `final`, `abstract` and `readonly` controllers are skipped.** `getClass()` only matches `"\nclass Name"`. The endpoints silently vanish from the document. Declare controllers as plain `class`.
- **The class must be loadable.** `getClass()` returns `null` unless `class_exists()` (with autoload) succeeds, so a file outside the autoloader is ignored without error.
- **`getClass()` returns the FQCN with a leading `\`** (`\App\Controllers\User`), which also prefixes error messages.
- **Inherited methods are parsed through each child class.** A tagged method of an abstract parent is documented through its concrete subclass, even though the parent itself is skipped.
- **Methods without any `oa-` text are skipped**, and `paths` is sorted by URL.

## Docblocks

- **`oa-` anywhere in a line starts a tag.** "Talks about the Goa-trance festival" creates a tag `oa-trance`, and the method is then treated as an endpoint: `oa-url not found`.
- **Values are split on spaces**; repeated spaces are fine.
- **(bug) A value `0` is dropped.** `array_filter()` removes it: `Number of 0 items` becomes `Number of items`.
- **`oa-desc` is repeated, one tag per line.** Lines are joined with `"\n"` then passed through `nl2br()` (`First line<br />\nSecond line`).

## Operations

- **`oa-url` and `oa-method` are both required**; the error names the method (`\App\Invalid\MissingUrl:index oa-url not found`).
- **`oa-method` is case-sensitive.** `GET` throws `oa-method format is not valid : GET`.
- **`oa-query` is ignored on `post`.** Only `get` and `delete` read it, silently.
- **A path parameter is `required: true` only if it is listed in `oa-required`.** OpenAPI requires it for every path parameter.
- **`oa-scope` prefixes the summary** (`[admin] Get an article`). **`oa-public` gives `security: []`**, `oa-private name` gives `[{name: []}]`.
- **Repeated `oa-code` for one status are joined with `<br>`.**
- **`oa-res` replaces an `oa-code` with the same status**, and has no `description` unless one is given after the schema name.
- **`oa-res 200 Name[]`** gives an array of `$ref`.
- **`oa-data` fields make a `multipart/form-data` body** whose `required` comes from `oa-required`.
- **An operation with only `oa-url`/`oa-method` is an empty array** (no `responses`).
- **Same URL and verb in two methods: the last parsed silently wins.**
- **An unknown schema name throws** `oa-res NopeResult not found`.
- **(bug) A schema name written with `\` builds a broken `$ref`.** `Sub\PointResult` gives `#/components/schemas/Sub\PointResult`, while the component is `Sub_PointResult`. Use `/`.

## Schemas

- **A name is relative to the namespace given to `parser()`**, `/` and `\` being equivalent in the namespace; `Sub/PointResult` becomes the component `Sub_PointResult`. Nested schemas are cached before their parent.
- **Only schemas actually referenced end up in `components.schemas`.**
- **A missing class, or a class whose docblock has no `oa-` tag, resolves to `null`**, which callers report as `not found`. Those `null`s stay in `SchemaParser::cache()`.
- **A schema without `oa-field`/`oa-ref`/`oa-refs` throws** `\App\Schemas\NoProps properties not found`.
- **An `oa-ref`/`oa-refs` target is looked up in the same namespace only**, even when several `parser()` namespaces are registered.
- **`|` in an `oa-field` description is a line break** (`<br />\n`).
- **`oa-required` is not checked** against the declared properties.
- **Every schema carries a non-standard `path` key** with the PHP class name.
- **(bug) A schema that references itself (or a cycle) recurses until PHP runs out of memory.** There is no way to describe a tree; use a plain `object` field instead.

## Types

- `integer`, `string:date-time`, `string:/^[a-z]+$/`, `array` (strings), `array:integer`, `array:string:email` — see the table in `SKILL.md`.
- **(bug) A pattern is cut at its first `:` and keeps its `/` delimiters.** `string:/^[0-9]{2}:[0-9]{2}$/` gives `pattern: "/^[0-9]{2}"`, and even `/^[a-z]+$/` is read by OpenAPI tools as requiring literal slashes. Prefer a `format` or put the rule in the description.

## Parser

- **Defaults:** title `Title`, description `Description`, version `0.0.1`, server `http://127.0.0.1`, and a global `accessToken` bearer scheme. Override what you publish.
- **`servers()` replaces the list.**
- **`jwtTokens()` replaces the schemes.** With several names, no global `security` is set, even with `$global = true`; with `$global = false` the schemes are kept but not applied globally.
- **(bug) Empty objects are encoded as JSON arrays.** `jwtTokens(false)` without schemas gives `"components":[]`, and an operation without other tags gives `"get":[]`.
