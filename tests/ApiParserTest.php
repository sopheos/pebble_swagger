<?php

use Pebble\Swagger\ApiParser;
use Pebble\Swagger\Exception;
use Pebble\Swagger\SchemaParser;
use Pebble\Swagger\SchemaParsers;
use PHPUnit\Framework\TestCase;

class ApiParserTest extends TestCase
{
    private static ?array $paths = null;

    private function parser(string $dir = 'Api'): ApiParser
    {
        $parsers = (new SchemaParsers())->add(new SchemaParser('App/Results'));
        return new ApiParser(__DIR__ . '/ressources/' . $dir, $parsers);
    }

    private function paths(): array
    {
        return self::$paths ??= $this->parser()->run();
    }

    /**
     * Parses the given classes one by one and returns the private result.
     */
    private function parseClasses(string ...$classnames): array
    {
        $parser = $this->parser();
        foreach ($classnames as $classname) {
            $parser->parseClass($classname);
        }

        return (fn() => $this->json)->call($parser);
    }

    // -------------------------------------------------------------------------
    // Operations
    // -------------------------------------------------------------------------

    public function testPathsAreSortedAndUndocumentedMethodsSkipped()
    {
        self::assertSame(['/api/articles', '/api/articles/{id}', '/api/articles/{id}/image'], array_keys($this->paths()));
        self::assertSame(['get', 'delete'], array_keys($this->paths()['/api/articles/{id}']));

        // An operation with only oa-url / oa-method is an empty array.
        self::assertSame([], $this->paths()['/api/articles/{id}']['delete']);
    }

    public function testTagsSummaryScopeDescriptionAndPublic()
    {
        $op = $this->paths()['/api/articles/{id}']['get'];

        self::assertSame(['Articles', 'Public'], $op['tags']);
        self::assertSame('[admin] Get an article', $op['summary']);
        self::assertSame("First line<br />\nSecond line", $op['description']);
        self::assertSame([], $op['security']);
    }

    public function testPathAndQueryParameters()
    {
        $params = $this->paths()['/api/articles/{id}']['get']['parameters'];

        self::assertSame([
            'in' => 'path',
            'name' => 'id',
            'schema' => ['type' => 'integer', 'format' => 'int64'],
            'required' => true,
            'description' => 'Article id',
        ], $params[0]);
        self::assertSame(['in' => 'query', 'name' => 'fields', 'schema' => ['type' => 'array', 'items' => ['type' => 'string']], 'description' => 'Fields to return'], $params[1]);
        self::assertCount(3, $params);
    }

    public function testPathParameterIsRequiredOnlyIfListedInOaRequired()
    {
        $params = $this->paths()['/api/articles/{id}/image']['put']['parameters'];

        self::assertSame([['in' => 'path', 'name' => 'id', 'schema' => ['type' => 'integer']]], $params);
    }

    public function testResponsesFromOaCodeAndOaRes()
    {
        $responses = $this->paths()['/api/articles/{id}']['get']['responses'];

        self::assertSame(['description' => 'Not found<br>Or deleted'], $responses[404]);
        self::assertSame(
            ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserResult']]], 'description' => 'The article'],
            $responses[200]
        );
        self::assertSame([200, 404], array_keys($responses));

        // Schema[] is an array of $ref.
        $schema = $this->paths()['/api/articles/{id}/image']['put']['responses'][200]['content']['application/json']['schema'];

        self::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Sub_PointResult']], $schema);
    }

    public function testOaResOverridesOaCodeAndMayHaveNoDescription()
    {
        $responses = $this->paths()['/api/articles']['post']['responses'];

        self::assertSame(['description' => 'Created'], $responses[201]);
        self::assertSame(['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResult']]]], $responses[400]);
    }

    public function testOaJsonBodyAndOaPrivateAndQueryIgnoredOnPost()
    {
        $op = $this->paths()['/api/articles']['post'];

        self::assertSame([['adminToken' => []]], $op['security']);
        self::assertSame(
            ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserResult']]], 'description' => 'Article to create'],
            $op['requestBody']
        );
        self::assertArrayNotHasKey('parameters', $op);
    }

    public function testOaDataBuildsAMultipartBody()
    {
        $schema = $this->paths()['/api/articles/{id}/image']['put']['requestBody']['content']['multipart/form-data']['schema'];

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'Image file'],
                'alt' => ['type' => 'string'],
            ],
            'required' => ['file'],
        ], $schema);
    }

    public function testSameUrlAndMethodIsOverwrittenByTheLastParsed()
    {
        $json = $this->parseClasses('\App\Invalid\DuplicateA', '\App\Invalid\DuplicateB');

        self::assertSame('Second', $json['/dup']['get']['summary']);
    }

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    /**
     * @dataProvider errorProvider
     */
    public function testInvalidDocblockThrows(string $classname, string $message)
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        $this->parseClasses($classname);
    }

    public static function errorProvider(): array
    {
        return [
            'missing url' => ['\App\Invalid\MissingUrl', '\App\Invalid\MissingUrl:index oa-url not found'],
            'upper-case method' => ['\App\Invalid\UpperCaseMethod', 'oa-method format is not valid : GET'],
            'oa- in plain text' => ['\App\Invalid\AccidentalTag', '\App\Invalid\AccidentalTag:index oa-url not found'],
            'unknown schema' => ['\App\Invalid\UnknownResult', 'oa-res NopeResult not found'],
        ];
    }

    // -------------------------------------------------------------------------
    // Class discovery
    // -------------------------------------------------------------------------

    public function testGetClassReturnsTheFullyQualifiedNameOfALoadableClass()
    {
        self::assertSame('\App\Controllers\User', ApiParser::getClass(__DIR__ . '/ressources/Controllers/User.php'));

        // A class that class_exists() cannot load is ignored.
        $file = tempnam(sys_get_temp_dir(), 'pebble_swagger_');
        file_put_contents($file, "<?php\n\nnamespace App\\Nowhere;\n\nclass NotLoaded\n{\n}\n");
        $classname = ApiParser::getClass($file);
        unlink($file);

        self::assertNull($classname);
    }

    public function testInheritedMethodsAreParsedThroughTheChildClass()
    {
        self::assertSame(['/kinds/child', '/kinds/inherited'], array_keys($this->parser('Kinds')->run()));
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testFinalAbstractAndReadonlyClassesAreSkipped()
    {
        // BUG: getClass() only matches "\nclass", so a class declared with a
        // modifier is silently ignored by scanClasses().
        self::assertNull(ApiParser::getClass(__DIR__ . '/ressources/Kinds/FinalController.php'));
        self::assertNull(ApiParser::getClass(__DIR__ . '/ressources/Kinds/AbstractController.php'));

        $file = tempnam(sys_get_temp_dir(), 'pebble_swagger_');
        file_put_contents($file, "<?php\n\nnamespace App\\Kinds;\n\nreadonly class ChildController\n{\n}\n");
        $readonly = ApiParser::getClass($file);
        file_put_contents($file, "<?php\n\nnamespace App\\Kinds;\n\nclass ChildController\n{\n}\n");
        $plain = ApiParser::getClass($file);
        unlink($file);

        self::assertNull($readonly);
        self::assertSame('\App\Kinds\ChildController', $plain);
    }

    public function testBackslashInAResultNameBuildsABrokenRef()
    {
        // BUG: the schema cache key replaces '/' and '\' with '_', but the $ref
        // only replaces '/', so 'Sub\PointResult' points to a missing component.
        $json = $this->parseClasses('\App\Invalid\BackslashResult');

        self::assertSame(
            '#/components/schemas/Sub\PointResult',
            $json['/backslash']['get']['responses'][200]['content']['application/json']['schema']['$ref']
        );
    }
}
