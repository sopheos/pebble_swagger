<?php

use Pebble\Swagger\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    private function parser(): Parser
    {
        return Parser::create(__DIR__ . '/ressources/Controllers')->parser('App/Results');
    }

    // -------------------------------------------------------------------------
    // Document
    // -------------------------------------------------------------------------

    public function testDefaultsAndNoUnusedSchema()
    {
        $json = Parser::create(__DIR__ . '/ressources/Kinds')->parser('App/Results')->run();

        self::assertSame('3.0.0', $json['openapi']);
        self::assertSame(['title' => 'Title', 'description' => 'Description', 'version' => '0.0.1'], $json['info']);
        self::assertSame([['url' => 'http://127.0.0.1']], $json['servers']);
        self::assertSame(['accessToken'], array_keys($json['components']['securitySchemes']));
        self::assertSame([['accessToken' => []]], $json['security']);
        self::assertArrayNotHasKey('schemas', $json['components']);
    }

    public function testInfoAndServers()
    {
        $json = $this->parser()
            ->title('My API')
            ->description('Desc')
            ->version('1.2.3')
            ->servers('https://a.test', 'https://b.test')
            ->run();

        self::assertSame(['title' => 'My API', 'description' => 'Desc', 'version' => '1.2.3'], $json['info']);
        self::assertSame([['url' => 'https://a.test'], ['url' => 'https://b.test']], $json['servers']);
    }

    public function testFixturesProduceThePathsAndOnlyTheUsedSchemas()
    {
        $json = $this->parser()->run();

        self::assertSame(['/api/organizer', '/api/points'], array_keys($json['paths']));
        self::assertSame(
            ['Sub_PointResult', 'Sub_LocationResult', 'UserResult', 'ErrorResult'],
            array_keys($json['components']['schemas'])
        );
        self::assertSame('[{"accessToken":[]}]', json_encode($json['paths']['/api/points']['get']['security']));
    }

    public function testJwtTokensWithSeveralNamesHasNoGlobalSecurity()
    {
        $json = $this->parser()->jwtTokens(true, 'user', 'admin')->run();

        self::assertSame(['user', 'admin'], array_keys($json['components']['securitySchemes']));
        self::assertSame(['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'], $json['components']['securitySchemes']['admin']);
        self::assertArrayNotHasKey('security', $json);
    }

    public function testJwtTokensWithoutGlobalKeepsTheScheme()
    {
        $json = $this->parser()->jwtTokens(false, 'accessToken')->run();

        self::assertArrayHasKey('accessToken', $json['components']['securitySchemes']);
        self::assertArrayNotHasKey('security', $json);
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testEmptyObjectsAreEncodedAsJsonArrays()
    {
        // BUG: empty PHP arrays become "[]" with json_encode(), where OpenAPI
        // expects objects: "components" without scheme nor schema, and an
        // operation with no other tag than oa-url / oa-method.
        $json = json_encode(Parser::create(__DIR__ . '/ressources/Kinds')->jwtTokens(false)->run(), JSON_UNESCAPED_SLASHES);

        self::assertStringContainsString('"components":[]', $json);
        self::assertStringContainsString('"/kinds/child":{"get":[]}', $json);
    }
}
