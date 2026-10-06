<?php

use Pebble\Swagger\DocCollection;
use Pebble\Swagger\DocEntity;
use PHPUnit\Framework\TestCase;

class DocCollectionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Docblock parsing
    // -------------------------------------------------------------------------

    public function testCreateKeepsOnlyLinesWithAnOaTag()
    {
        $docs = DocCollection::create("/**\r\n * Some text\r\n * oa-url /api/x\r\n * oa-tags A B\r\n */");

        self::assertCount(2, $docs);
        self::assertSame('/api/x', $docs->one('oa-url')->text());
        self::assertSame(['A', 'B'], $docs->one('oa-tags')->values());
    }

    public function testCreateWithoutTagIsEmpty()
    {
        self::assertCount(0, DocCollection::create(''));
        self::assertCount(0, DocCollection::create("/**\n * No tag here\n */"));
    }

    public function testOaAnywhereInALineStartsATag()
    {
        $docs = DocCollection::create("/**\n * Talks about the Goa-trance festival.\n */");

        self::assertCount(1, $docs);
        self::assertTrue($docs->has('oa-trance'));
        self::assertSame(['festival.'], $docs->one('oa-trance')->values());
    }

    public function testValuesAreSplitOnSpacesAndEmptyOnesDropped()
    {
        $doc = DocCollection::create(" * oa-field  label   string  A label")->one('oa-field');

        self::assertSame(['label', 'string', 'A', 'label'], $doc->values());
        self::assertSame('A label', $doc->text(2));
    }

    public function testOneAllHas()
    {
        $docs = DocCollection::create(" * oa-code 200 OK\n * oa-code 404 Missing\n");

        self::assertTrue($docs->has('oa-code'));
        self::assertFalse($docs->has('oa-res'));
        self::assertSame('200', $docs->one('oa-code')->value());
        self::assertNull($docs->one('oa-res'));
        self::assertCount(2, $docs->all('oa-code'));
        self::assertSame([], $docs->all('oa-res'));
    }

    public function testParseMultilineJoinsRepeatedTags()
    {
        $docs = DocCollection::create(" * oa-desc First line\n * oa-desc Second line\n");

        self::assertSame("First line\nSecond line", $docs->parseMultiline('oa-desc'));
        self::assertSame('First line, Second line', $docs->parseMultiline('oa-desc', ', '));
        self::assertSame('', $docs->parseMultiline('oa-summary'));
    }

    // -------------------------------------------------------------------------
    // Types
    // -------------------------------------------------------------------------

    /**
     * @dataProvider typeProvider
     */
    public function testParseType(string $type, array $expected)
    {
        self::assertSame($expected, DocCollection::parseType($type));
    }

    public static function typeProvider(): array
    {
        return [
            'plain' => ['integer', ['type' => 'integer']],
            'format' => ['string:date-time', ['type' => 'string', 'format' => 'date-time']],
            'pattern' => ['string:/^[a-z]+$/', ['type' => 'string', 'pattern' => '/^[a-z]+$/']],
            'array default' => ['array', ['type' => 'array', 'items' => ['type' => 'string']]],
            'array of' => ['array:integer', ['type' => 'array', 'items' => ['type' => 'integer']]],
            'array of with format' => ['array:string:email', ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'email']]],
            'empty' => ['', []],
        ];
    }

    // -------------------------------------------------------------------------
    // DocEntity
    // -------------------------------------------------------------------------

    public function testDocEntityAccessors()
    {
        $doc = new DocEntity('oa-path', ['id', 'integer', 'The', 'id']);

        self::assertSame('id', $doc->value());
        self::assertSame('integer', $doc->value(1));
        self::assertSame('', $doc->value(9));
        self::assertSame(['integer', 'The'], $doc->values(1, 2));
        self::assertSame('The id', $doc->text(2));
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testAZeroWordIsDropped()
    {
        // BUG: array_filter() without callback removes the string '0' as well
        // as the empty strings produced by double spaces.
        $doc = DocCollection::create(" * oa-field count integer Number of 0 items")->one('oa-field');

        self::assertSame('Number of items', $doc->text(2));
    }

    public function testPatternIsCutAtTheFirstColonAndKeepsItsDelimiters()
    {
        // BUG: the type is exploded on ':', so a pattern containing ':' is
        // truncated; the '/' delimiters are also kept, which OpenAPI (ECMA 262)
        // reads as literal slashes.
        self::assertSame(
            ['type' => 'string', 'pattern' => '/^[0-9]{2}'],
            DocCollection::parseType('string:/^[0-9]{2}:[0-9]{2}$/')
        );
    }
}
