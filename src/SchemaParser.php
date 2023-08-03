<?php

namespace Pebble\Swagger;

use ReflectionException;

class SchemaParser
{
    private array $cache = [];
    private string $namespace;

    public function __construct(string $namespace)
    {
        $this->namespace =  '/' . $namespace;
        $this->namespace = str_replace('\\', '/', $this->namespace);
    }

    public function cache(): array
    {
        return $this->cache;
    }

    public function get(string $name): ?array
    {
        $key = str_replace(['/', '\\'], '_', $name);
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->parse($name);
        }

        return $this->cache[$key];
    }

    private function parse(string $name): ?array
    {
        try {
            $classname = '/' . trim($this->namespace . '/' . $name, '/');
            $classname = str_replace('/', '\\', $this->namespace . '/' . $name);
            $reflection = new \ReflectionClass($classname);
        } catch (ReflectionException $ex) {
            return null;
        }

        $docs = DocCollection::create($reflection->getDocComment() ?: '');

        if (!$docs->count()) {
            return null;
        }

        $properties = [];
        $json = [];
        $json['path'] = $classname;

        foreach ($docs->all('oa-field') as $doc) {
            if (!($name = $doc->value(0))) {
                throw Exception::create($classname, 'oa-field', 'name not found');
            }

            if (!($type = $doc->value(1))) {
                throw Exception::create($classname, 'oa-field', 'type not found');
            }

            $properties[$name] = DocCollection::parseType($type);
            if (($desc = $doc->text(2))) {
                $desc = implode("\n", explode("|", $desc));
                $desc = nl2br($desc);
                $properties[$name]['description'] = $desc;
            }
        }

        foreach ($docs->all('oa-ref') as $doc) {
            if (!($name = $doc->value(0))) {
                throw Exception::create($classname, 'oa-ref', 'format is not valid');
            }

            if (!($ref = $doc->value(1))) {
                throw Exception::create($classname, 'oa-ref', 'format is not valid');
            }

            if (!$this->get($ref)) {
                throw Exception::create($classname, 'oa-ref', $ref, 'not found');
            }

            $properties[$name] = [
                '$ref' => '#/components/schemas/' . str_replace('/', '_', $ref)
            ];
        }

        foreach ($docs->all('oa-refs') as $doc) {
            if (!($name = $doc->value(0))) {
                throw Exception::create($classname, 'oa-refs', 'format is not valid');
            }

            if (!($ref = $doc->value(1))) {
                throw Exception::create($classname, 'oa-refs', 'format is not valid');
            }

            if (!$this->get($ref)) {
                throw Exception::create($classname, 'oa-refs', $ref, 'not found');
            }

            $properties[$name] = [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/' . str_replace('/', '_', $ref)
                ]
            ];
        }

        if (!$properties) {
            throw Exception::create($classname, 'properties', 'not found');
        }

        if (($description = $docs->parseMultiline('oa-desc'))) {
            $json['description'] = nl2br($description);;
        }

        $json['type'] = 'object';
        $json['properties'] = $properties;

        $required = [];
        foreach ($docs->all('oa-required') as $doc) {
            $required = array_merge($required, $doc->values());
        }

        if ($required) {
            $json['required'] = $required;
        }

        return $json;
    }
}
