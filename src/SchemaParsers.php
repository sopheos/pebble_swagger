<?php

namespace Pebble\Swagger;

class SchemaParsers
{
    /**
     * @var SchemaParser[]
     */
    private array $parsers = [];

    public static function create(): static
    {
        return new static();
    }

    public function add(SchemaParser $parser): static
    {
        $this->parsers[] = $parser;
        return $this;
    }

    public function cache(): array
    {
        $cache = [];
        foreach ($this->parsers as $parser) {
            $cache = array_merge($cache, $parser->cache());
        }

        return $cache;
    }

    public function get(string $name): ?array
    {
        foreach ($this->parsers as $parser) {
            if (($res = $parser->get($name))) {
                return $res;
            }
        }

        return null;
    }
}
