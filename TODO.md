# TODO — pebble_swagger

Problèmes restant à traiter, détectés lors de l'audit du 2026-10-06. Le code `src/` n'a **pas** été modifié. Chaque bug est figé par un test qui vérifie le comportement actuel : il faut l'adapter au moment de la correction.

## Bugs

- [ ] **Les classes `final`, `abstract` et `readonly` sont ignorées par le scan.** `src/ApiParser.php:339`.
  - `getClass()` cherche `"\nclass"`. Un modificateur devant `class` fait échouer la regex, `getClass()` renvoie `null` et les endpoints du contrôleur disparaissent du document, sans erreur.
  - Correctif : `preg_match('/^\s*(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)/mi', …)`.
  - Test : `tests/ApiParserTest.php::testFinalAbstractAndReadonlyClassesAreSkipped`.
- [ ] **Un schéma récursif boucle jusqu'à l'épuisement de la mémoire.** `src/SchemaParser.php:26-27`.
  - `get()` ne met le schéma en cache qu'après le retour de `parse()`. Un `oa-ref`/`oa-refs` vers la classe elle-même (arbre, commentaire avec réponses) ou un cycle A → B → A rappelle `parse()` à l'infini : erreur fatale « Allowed memory size » (ou boucle sans fin avec `memory_limit=-1`).
  - Correctif : poser une valeur provisoire dans le cache avant `parse()` (par exemple `$this->cache[$key] = ['$ref' => …]` ou un marqueur), puis la remplacer.
  - Test : `tests/SchemaParserTest.php::testRecursiveSchemaExhaustsMemory`.
- [ ] **Un motif `type:/regex/` est coupé au premier `:` et garde ses délimiteurs.** `src/DocCollection.php:98, 119-120`.
  - `explode(':', $type)` coupe `string:/^[0-9]{2}:[0-9]{2}$/` en `/^[0-9]{2}`. Les `/` sont recopiés dans `pattern`, alors qu'OpenAPI attend une regex ECMA 262 sans délimiteurs : `/^[a-z]+$/` n'accepte que des valeurs entourées de `/`.
  - Correctif : `explode(':', $type, 2)` (puis 3 pour `array`), et `trim($format, '/')` pour le motif.
  - Test : `tests/DocCollectionTest.php::testPatternIsCutAtTheFirstColonAndKeepsItsDelimiters`.
- [ ] **Un nom de schéma avec `\` produit un `$ref` cassé.** `src/ApiParser.php:267, 287`, `src/SchemaParser.php:84, 104`.
  - La clé du cache remplace `/` et `\` par `_`, mais le `$ref` ne remplace que `/`. `oa-res 200 Sub\PointResult` pointe vers `#/components/schemas/Sub\PointResult` alors que le composant s'appelle `Sub_PointResult`.
  - Correctif : `str_replace(['/', '\\'], '_', $name)` partout (une méthode commune).
  - Test : `tests/ApiParserTest.php::testBackslashInAResultNameBuildsABrokenRef`.
- [ ] **Les objets vides sont encodés en tableaux JSON.** `src/Parser.php:25`, `src/ApiParser.php:66`.
  - `components` sans schéma de sécurité ni schéma, et une opération sans autre tag que `oa-url`/`oa-method`, sont des `[]` PHP que `json_encode()` écrit `[]` au lieu de `{}`. Le document n'est plus valide OpenAPI.
  - Correctif : `new \stdClass()` ou `(object) []` pour ces valeurs, ou ne pas émettre `components` vide.
  - Test : `tests/ParserTest.php::testEmptyObjectsAreEncodedAsJsonArrays`.
- [ ] **Un mot `0` disparaît des valeurs d'un tag.** `src/DocCollection.php:25`.
  - `array_filter()` sans callback retire `'0'` en plus des chaînes vides : `oa-field count integer Number of 0 items` donne la description `Number of items`, et `oa-code 0 …` perd son code.
  - Correctif : `array_filter(…, fn($v) => $v !== '')`.
  - Test : `tests/DocCollectionTest.php::testAZeroWordIsDropped`.

## Dette / qualité

- [ ] Conformité OpenAPI 3.0 non garantie (comportements figés par des tests, voir le skill) : un paramètre `oa-path` n'est `required: true` que s'il figure dans `oa-required` (`src/ApiParser.php:185`) ; une réponse `oa-res` sans description n'a pas de `description` (`src/ApiParser.php:293`) ; une opération sans `oa-code`/`oa-res` n'a pas de `responses` ; chaque schéma porte une clé non standard `path` (`src/SchemaParser.php:51`, à renommer `x-path`).
- [ ] `src/DocCollection.php:23-24` : `oa-` est cherché n'importe où dans une ligne. Un docblock qui contient le texte `oa-` (« Goa-trance », « no oa- tag ») transforme la méthode en endpoint et lève `oa-url not found`. Chercher plutôt `/^\s*\*\s*oa-/`.
- [ ] `src/ApiParser.php:120-142` : `oa-query` est ignoré sans erreur pour `post`/`put`/`patch`/`options`, et `oa-json`/`oa-data` pour `get`/`delete`.
- [ ] `src/ApiParser.php:62` : `oa-method` est sensible à la casse (`GET` lève une exception). `head` et `trace` ne sont pas acceptés.
- [ ] `src/ApiParser.php:66` : deux méthodes avec le même `oa-url` et le même `oa-method` s'écrasent sans avertissement.
- [ ] `src/SchemaParser.php:79, 97` : un `oa-ref`/`oa-refs` n'est résolu que dans le namespace du schéma qui le contient, pas dans les autres `SchemaParser`.
- [ ] `src/ApiParser.php:333` : la regex du namespace exige un saut de ligne avant `namespace`, et `getClass()` ne lit que la première classe d'un fichier.
- [ ] `src/ApiParser.php:172, 198` : affectation doublée `$name = $name = $doc->value(0);`.
- [ ] `src/SchemaParser.php:114` : `;;` en fin de ligne.
- [ ] `src/Parser.php:54, 63` : `servers()` et `jwtTokens()` acceptent `mixed ...`, alors que ce sont des chaînes.
