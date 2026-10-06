<?php

use Pebble\Swagger\Exception;
use Pebble\Swagger\SchemaParser;
use Pebble\Swagger\SchemaParsers;
use PHPUnit\Framework\TestCase;

class SchemaParserTest extends TestCase
{
    private function schemas(): SchemaParser
    {
        return new SchemaParser('App/Schemas');
    }

    // -------------------------------------------------------------------------
    // SchemaParser
    // -------------------------------------------------------------------------

    public function testFieldsDescriptionAndRequired()
    {
        $schema = $this->schemas()->get('Misc');

        self::assertSame('\App\Schemas\Misc', $schema['path']);
        self::assertSame("A misc schema<br />\non two lines", $schema['description']);
        self::assertSame('object', $schema['type']);
        self::assertSame(['type' => 'string', 'pattern' => '/^[a-z]+$/', 'description' => 'Code'], $schema['properties']['code']);
        self::assertSame(['type' => 'integer', 'format' => 'int32', 'description' => 'Number of items'], $schema['properties']['count']);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'string', 'format' => 'email']], $schema['properties']['tags']);
        self::assertSame(['code', 'unknown'], $schema['required']);
    }

    public function testPipeInAFieldDescriptionIsALineBreak()
    {
        $schema = $this->schemas()->get('Misc');

        self::assertSame("Label<br />\nsecond line", $schema['properties']['label']['description']);
    }

    public function testRefAndRefsPointToComponentsWithSlashesReplaced()
    {
        $parser = new SchemaParser('App\Results');
        $schema = $parser->get('UserResult');

        self::assertSame(['$ref' => '#/components/schemas/Sub_LocationResult'], $schema['properties']['location']);
        self::assertSame(
            ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Sub_PointResult']],
            $schema['properties']['points']
        );
        // Nested schemas are cached before the schema that references them.
        self::assertSame(['Sub_LocationResult', 'Sub_PointResult', 'UserResult'], array_keys($parser->cache()));
    }

    public function testNamespaceAcceptsSlashesOrBackslashes()
    {
        self::assertSame(
            (new SchemaParser('App/Results'))->get('Sub/PointResult'),
            (new SchemaParser('App\Results'))->get('Sub\PointResult')
        );
    }

    public function testUnknownClassOrClassWithoutTagIsNull()
    {
        $parser = $this->schemas();

        self::assertNull($parser->get('Nope'));
        self::assertNull($parser->get('NoTags'));
        self::assertSame(['Nope' => null, 'NoTags' => null], $parser->cache());
    }

    public function testSchemaWithoutPropertyThrows()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('\App\Schemas\NoProps properties not found');

        $this->schemas()->get('NoProps');
    }

    public function testRefToAnUnknownSchemaThrows()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('\App\Schemas\MissingRef oa-refs NoTags not found');

        $this->schemas()->get('MissingRef');
    }

    public function testRefIsResolvedInTheSameNamespaceOnly()
    {
        $parsers = (new SchemaParsers())
            ->add($this->schemas())
            ->add(new SchemaParser('App/Results'));

        self::assertNotNull($parsers->get('UserResult'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('\App\Schemas\CrossNamespace oa-ref UserResult not found');
        $parsers->get('CrossNamespace');
    }

    // -------------------------------------------------------------------------
    // SchemaParsers
    // -------------------------------------------------------------------------

    public function testSchemaParsersAsksEachParserInOrder()
    {
        $parsers = SchemaParsers::create()
            ->add($this->schemas())
            ->add(new SchemaParser('App/Results'));

        self::assertSame('\App\Schemas\Misc', $parsers->get('Misc')['path']);
        self::assertSame('\App\Results\ErrorResult', $parsers->get('ErrorResult')['path']);
        self::assertNull($parsers->get('Nope'));
        self::assertNotNull($parsers->cache()['ErrorResult']);
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testRecursiveSchemaExhaustsMemory()
    {
        // BUG: get() only caches a schema once parse() returns, so a schema that
        // references itself (directly or through a cycle) recurses forever.
        $script = tempnam(sys_get_temp_dir(), 'pebble_swagger_');
        file_put_contents($script, '<?php require ' . var_export(__DIR__ . '/bootstrap.php', true) . ';'
            . ' (new Pebble\Swagger\SchemaParser("App/Schemas"))->get("Node");');

        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -d memory_limit=32M ' . escapeshellarg($script) . ' 2>&1', $output, $code);
        unlink($script);

        self::assertSame(255, $code);
        self::assertMatchesRegularExpression('/Allowed memory size|Maximum call stack size/', implode("\n", $output));
    }
}
